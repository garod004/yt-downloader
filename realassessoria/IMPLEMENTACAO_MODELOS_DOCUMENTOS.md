# Implementação: Sistema de Modelos de Documentos com Geração de PDF

Este documento descreve **todas** as mudanças feitas para implementar o sistema de modelos de documentos em um sistema PHP + MySQLi. O objetivo é que outra IA possa replicar exatamente este sistema em um projeto diferente.

---

## Visão Geral

O sistema permite:
- Cadastrar modelos de documentos com HTML + marcadores `{{campo}}`
- Editar modelos via editor TinyMCE (WYSIWYG)
- Selecionar um cliente e dependentes (advogados, filho menor, incapaz, a rogo) para preencher os marcadores
- Gerar um PDF profissional via **Dompdf** com os dados substituídos
- Registrar log de auditoria de cada PDF gerado

---

## 1. Dependências

### Composer
O `composer.json` já possui `dompdf/dompdf ^3.1` instalado em `vendor/`. **Não é necessário adicionar novamente.** Em `gerar_documento_pdf.php` usar sempre `vendor/autoload.php` — ignorar a pasta `/dompdf` local que é uma cópia legada.

```php
require_once __DIR__ . '/vendor/autoload.php'; // correto
// NÃO usar: require_once __DIR__ . '/dompdf/src/...';
```

### TinyMCE
Usado no editor de modelos. Pode ser via CDN gratuito (com `no-api-key`) ou com chave da Tiny Cloud. A chave é lida via variável de ambiente `TINYMCE_API_KEY`.

---

## 2. Banco de Dados

### 2.1 Nova tabela: `modelos_documentos`

```sql
CREATE TABLE IF NOT EXISTS `modelos_documentos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(150) NOT NULL COMMENT 'Nome do modelo',
  `categoria`   VARCHAR(80)  DEFAULT 'Geral',
  `descricao`   VARCHAR(255) DEFAULT NULL,
  `conteudo`    LONGTEXT NOT NULL COMMENT 'HTML com marcadores {{campo}}',
  `ativo`       TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=soft delete',
  `criado_por`  VARCHAR(100) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Nova tabela: `advogados`

Criada automaticamente pela função `garantirTabelaAdvogados()` se não existir:

```sql
CREATE TABLE IF NOT EXISTS advogados (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(255) NOT NULL,
    documento   VARCHAR(20)  NOT NULL,
    oab         VARCHAR(80)  NOT NULL,
    endereco    VARCHAR(255) NOT NULL,
    cidade      VARCHAR(120) NOT NULL,
    uf          CHAR(2)      NOT NULL,
    fone        VARCHAR(30)  NOT NULL,
    email       VARCHAR(180) NOT NULL,
    ativo       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_advogados_ativo (ativo),
    INDEX idx_advogados_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 Tabelas existentes que o sistema consome

O sistema lê dados das seguintes tabelas já existentes no projeto:

| Tabela | Colunas usadas |
|--------|---------------|
| `clientes` | id, nome, cpf, rg, data_nascimento, estado_civil, profissao, telefone, email, endereco, cep, cidade, uf, nacionalidade, beneficio, numero_processo, situacao |
| `filhos_menores` | id, nome, cpf, data_nascimento, cliente_id |
| `incapazes` | id, nome, cpf, data_nascimento, cliente_id |
| `a_rogo` | id, nome, identidade, cpf, cliente_id |
| `configuracoes_sistema` | chave, valor (lê: empresa_nome, empresa_cnpj, empresa_fone, empresa_email, empresa_proprietarios, empresa_endereco, empresa_cidade) |
| `controle_acesso` | tabela de log — usuario, acao, descricao, id_alterado, nome_alterado |

### 2.4 Inserir chaves de empresa faltantes

Arquivo: `setup_configuracoes.sql`

```sql
INSERT IGNORE INTO `configuracoes_sistema` (`chave`, `valor`) VALUES
('empresa_endereco', ''),
('empresa_cidade',   '');
```

Executar uma vez no servidor antes de usar o sistema de modelos.

---

## 3. Arquivos Criados

### 3.1 `src/ModeloSubstituicao.php`

Classe pura (sem dependência de DB ou sessão) que centraliza toda a lógica de substituição de marcadores.

```php
<?php

class ModeloSubstituicao
{
    // Formata CPF: "76466833291" → "764.668.332-91"
    public static function formatarCpf(?string $cpf): string
    {
        $numeros = preg_replace('/\D/', '', (string)($cpf ?? ''));
        if (strlen($numeros) !== 11) {
            return (string)($cpf ?? '');
        }
        return substr($numeros, 0, 3) . '.'
             . substr($numeros, 3, 3) . '.'
             . substr($numeros, 6, 3) . '-'
             . substr($numeros, 9, 2);
    }

    // Formata data MySQL "YYYY-MM-DD" → "DD/MM/YYYY"
    public static function formatarData(?string $data): string
    {
        if (empty($data) || $data === '0000-00-00') return '';
        $ts = strtotime($data);
        return $ts !== false ? date('d/m/Y', $ts) : '';
    }

    // Data de hoje por extenso: "10 de abril de 2026"
    public static function dataHojeExtenso(): string
    {
        $meses = [
            '', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
        ];
        return date('d') . ' de ' . $meses[(int)date('m')] . ' de ' . date('Y');
    }

    /**
     * Constrói o mapa {{marcador}} → valor.
     * Todos os valores são escapados para HTML.
     *
     * @param array  $cliente     Linha da tabela clientes
     * @param array  $empresa     Mapa chave→valor de configuracoes_sistema
     * @param string $usuarioNome Nome do usuário logado
     * @param array  $advogados   Até 3 advogados: [0 => [...], 1 => [...], 2 => [...]]
     * @param array  $filho       Linha de filhos_menores (ou [])
     * @param array  $incapaz     Linha de incapazes (ou [])
     * @param array  $aRogo       Linha de a_rogo (ou [])
     */
    public static function construirMapa(
        array $cliente,
        array $empresa,
        string $usuarioNome = '',
        array $advogados = [],
        array $filho = [],
        array $incapaz = [],
        array $aRogo = []
    ): array {
        $h = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $mapa = [
            // CLIENTE
            '{{cliente_nome}}'            => $h($cliente['nome']            ?? ''),
            '{{cliente_cpf}}'             => $h(self::formatarCpf($cliente['cpf'] ?? null)),
            '{{cliente_rg}}'              => $h($cliente['rg']              ?? ''),
            '{{cliente_data_nascimento}}' => $h(self::formatarData($cliente['data_nascimento'] ?? null)),
            '{{cliente_estado_civil}}'    => $h($cliente['estado_civil']    ?? ''),
            '{{cliente_profissao}}'       => $h($cliente['profissao']       ?? ''),
            '{{cliente_telefone}}'        => $h($cliente['telefone']        ?? ''),
            '{{cliente_email}}'           => $h($cliente['email']           ?? ''),
            '{{cliente_endereco}}'        => $h($cliente['endereco']        ?? ''),
            '{{cliente_cep}}'             => $h($cliente['cep']             ?? ''),
            '{{cliente_cidade}}'          => $h($cliente['cidade']          ?? ''),
            '{{cliente_uf}}'              => $h($cliente['uf']              ?? ''),
            '{{cliente_nacionalidade}}'   => $h($cliente['nacionalidade']   ?? ''),
            '{{cliente_beneficio}}'       => $h($cliente['beneficio']       ?? ''),
            '{{cliente_numero_processo}}' => $h($cliente['numero_processo'] ?? ''),
            '{{cliente_situacao}}'        => $h($cliente['situacao']        ?? ''),

            // EMPRESA
            '{{empresa_nome}}'            => $h($empresa['empresa_nome']          ?? ''),
            '{{empresa_cnpj}}'            => $h($empresa['empresa_cnpj']          ?? ''),
            '{{empresa_fone}}'            => $h($empresa['empresa_fone']          ?? ''),
            '{{empresa_email}}'           => $h($empresa['empresa_email']         ?? ''),
            '{{empresa_proprietarios}}'   => $h($empresa['empresa_proprietarios'] ?? ''),
            '{{empresa_endereco}}'        => $h($empresa['empresa_endereco']      ?? ''),
            '{{empresa_cidade}}'          => $h($empresa['empresa_cidade']        ?? ''),

            // DATA E USUÁRIO
            '{{data_hoje}}'               => $h(date('d/m/Y')),
            '{{data_hoje_extenso}}'       => $h(self::dataHojeExtenso()),
            '{{usuario_nome}}'            => $h($usuarioNome),

            // FILHO MENOR
            '{{filho_nome}}'              => $h($filho['nome']            ?? ''),
            '{{filho_cpf}}'               => $h(self::formatarCpf($filho['cpf'] ?? null)),
            '{{filho_data_nascimento}}'   => $h(self::formatarData($filho['data_nascimento'] ?? null)),

            // INCAPAZ
            '{{incapaz_nome}}'            => $h($incapaz['nome']            ?? ''),
            '{{incapaz_cpf}}'             => $h(self::formatarCpf($incapaz['cpf'] ?? null)),
            '{{incapaz_data_nascimento}}' => $h(self::formatarData($incapaz['data_nascimento'] ?? null)),

            // A ROGO
            '{{a_rogo_nome}}'             => $h($aRogo['nome']       ?? ''),
            '{{a_rogo_identidade}}'       => $h($aRogo['identidade'] ?? ''),
            '{{a_rogo_cpf}}'              => $h(self::formatarCpf($aRogo['cpf'] ?? null)),
        ];

        // ADVOGADOS 1, 2, 3
        for ($i = 1; $i <= 3; $i++) {
            $adv = $advogados[$i - 1] ?? [];
            $mapa["{{advogado_{$i}_nome}}"]      = $h($adv['nome']      ?? '');
            $mapa["{{advogado_{$i}_documento}}"] = $h($adv['documento'] ?? '');
            $mapa["{{advogado_{$i}_oab}}"]       = $h($adv['oab']       ?? '');
            $mapa["{{advogado_{$i}_endereco}}"]  = $h($adv['endereco']  ?? '');
            $mapa["{{advogado_{$i}_cidade}}"]    = $h($adv['cidade']    ?? '');
            $mapa["{{advogado_{$i}_uf}}"]        = $h($adv['uf']        ?? '');
            $mapa["{{advogado_{$i}_fone}}"]      = $h($adv['fone']      ?? '');
            $mapa["{{advogado_{$i}_email}}"]     = $h($adv['email']     ?? '');
        }

        // Aliases sem número → advogado 1 (retrocompatibilidade)
        $mapa['{{advogado_nome}}']      = $mapa['{{advogado_1_nome}}'];
        $mapa['{{advogado_documento}}'] = $mapa['{{advogado_1_documento}}'];
        $mapa['{{advogado_oab}}']       = $mapa['{{advogado_1_oab}}'];
        $mapa['{{advogado_endereco}}']  = $mapa['{{advogado_1_endereco}}'];
        $mapa['{{advogado_cidade}}']    = $mapa['{{advogado_1_cidade}}'];
        $mapa['{{advogado_uf}}']        = $mapa['{{advogado_1_uf}}'];
        $mapa['{{advogado_fone}}']      = $mapa['{{advogado_1_fone}}'];
        $mapa['{{advogado_email}}']     = $mapa['{{advogado_1_email}}'];

        return $mapa;
    }

    // Aplica o mapa ao conteúdo. Marcadores não reconhecidos ficam intactos.
    public static function substituir(string $conteudo, array $mapa): string
    {
        return str_replace(array_keys($mapa), array_values($mapa), $conteudo);
    }

    // Retorna todos os marcadores {{...}} presentes no conteúdo
    public static function extrairMarcadores(string $conteudo): array
    {
        preg_match_all('/\{\{[a-z0-9_]+\}\}/', $conteudo, $matches);
        return array_unique($matches[0]);
    }

    // Retorna marcadores presentes no conteúdo que não estão no mapa
    public static function marcadoresInvalidos(string $conteudo, array $mapa): array
    {
        return array_values(array_diff(self::extrairMarcadores($conteudo), array_keys($mapa)));
    }

    public static function validarNomeModelo(string $nome): bool
    {
        $nome = trim($nome);
        return $nome !== '' && strlen($nome) <= 150;
    }

    public static function validarCategoria(string $categoria): bool
    {
        $permitidas = ['Geral', 'Contrato', 'Procuração', 'Declaração', 'Requerimento', 'Ofício', 'Outro'];
        return in_array($categoria, $permitidas, true);
    }
}
```

---

### 3.2 `advogados_utils.php`

Funções para criar e consultar a tabela de advogados.

```php
<?php

function garantirTabelaAdvogados($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS advogados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        documento VARCHAR(20) NOT NULL,
        oab VARCHAR(80) NOT NULL,
        endereco VARCHAR(255) NOT NULL,
        cidade VARCHAR(120) NOT NULL,
        uf CHAR(2) NOT NULL,
        fone VARCHAR(30) NOT NULL,
        email VARCHAR(180) NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_advogados_ativo (ativo),
        INDEX idx_advogados_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return (bool)$conn->query($sql);
}

function limparDocumento($documento)
{
    return preg_replace('/\D/', '', (string)$documento);
}

function rotuloDocumento($documento)
{
    return strlen(limparDocumento($documento)) === 14 ? 'CNPJ' : 'CPF';
}

function normalizarUf($uf)
{
    return strtoupper(substr(trim((string)$uf), 0, 2));
}

// Busca advogado por ID. Se não encontrar, retorna o primeiro ativo. Se não houver nenhum, retorna null.
function obterAdvogadoContratado($conn, $advogadoId = 0)
{
    garantirTabelaAdvogados($conn);
    $advogadoId = intval($advogadoId);

    if ($advogadoId > 0) {
        $stmt = $conn->prepare("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $advogadoId);
            $stmt->execute();
            $row = stmt_get_result($stmt)->fetch_assoc();
            $stmt->close();
            if ($row) return $row;
        }
    }

    $result = $conn->query("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados WHERE ativo = 1 ORDER BY nome ASC LIMIT 1");
    if ($result && $result->num_rows > 0) return $result->fetch_assoc();

    $result = $conn->query("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados ORDER BY nome ASC LIMIT 1");
    if ($result && $result->num_rows > 0) return $result->fetch_assoc();

    return null;
}

function dadosFallbackAdvogado()
{
    return ['nome'=>'ADVOGADO NAO CADASTRADO','documento'=>'NAO INFORMADO','oab'=>'NAO INFORMADA',
            'endereco'=>'NAO INFORMADO','cidade'=>'NAO INFORMADA','uf'=>'--','fone'=>'NAO INFORMADO','email'=>'NAO INFORMADO'];
}

function prepararDadosAdvogadoDocumento($advogado)
{
    $base = $advogado ?: dadosFallbackAdvogado();
    $documento = trim((string)($base['documento'] ?? ''));
    if ($documento === '') $documento = 'NAO INFORMADO';
    return [
        'nome'             => htmlspecialchars(mb_convert_case((string)($base['nome'] ?? ''), MB_CASE_TITLE, 'UTF-8')),
        'documento'        => htmlspecialchars($documento),
        'documento_rotulo' => htmlspecialchars(rotuloDocumento($documento)),
        'oab'              => htmlspecialchars((string)($base['oab'] ?? 'NAO INFORMADA')),
        'endereco'         => htmlspecialchars(mb_convert_case((string)($base['endereco'] ?? ''), MB_CASE_TITLE, 'UTF-8')),
        'cidade'           => htmlspecialchars(mb_convert_case((string)($base['cidade'] ?? ''), MB_CASE_TITLE, 'UTF-8')),
        'uf'               => htmlspecialchars(normalizarUf($base['uf'] ?? '--')),
        'fone'             => htmlspecialchars((string)($base['fone'] ?? 'NAO INFORMADO')),
        'email'            => htmlspecialchars((string)($base['email'] ?? 'NAO INFORMADO')),
    ];
}
```

---

### 3.2.1 `criar_tabela_modelos_documentos.sql`

Arquivo de migração com o DDL da tabela principal. Executar uma vez no banco.

```sql
CREATE TABLE IF NOT EXISTS `modelos_documentos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(150) NOT NULL COMMENT 'Nome do modelo',
  `categoria`   VARCHAR(80)  DEFAULT 'Geral',
  `descricao`   VARCHAR(255) DEFAULT NULL,
  `conteudo`    LONGTEXT NOT NULL COMMENT 'HTML com marcadores {{campo}}',
  `ativo`       TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=soft delete',
  `criado_por`  VARCHAR(100) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.3 `listar_modelos.php`

Página que lista todos os modelos ativos. Aceita `?cliente_id=X` via GET para exibir um banner de cliente pré-selecionado e passar o ID adiante nos links de geração.

**Fluxo:**
1. Verifica sessão
2. Gera CSRF token se ausente
3. Lê `?cliente_id` do GET e valida no banco
4. Busca todos os modelos: `SELECT id, nome, categoria, descricao, criado_por, created_at FROM modelos_documentos WHERE ativo = 1 ORDER BY categoria ASC, nome ASC`
5. Renderiza tabela com botões: Editar → `editar_modelo.php?id=X`, Gerar → `gerar_documento.php?modelo_id=X&cliente_id=Y`, Excluir (só admin, via AJAX)
6. Busca client-side via `#campoBusca` (filtra linhas da tabela)
7. Exclusão via `fetch('excluir_modelo.php', {method:'POST', body: FormData com id + csrf_token})`

**Banner de cliente** (exibido quando `cliente_id > 0`):
```html
<div class="banner-cliente">
    Gerando documento para: <strong>{cliente_nome}</strong>
    <a href="listar_modelos.php">× Remover cliente</a>
</div>
```

**Link de geração com cliente:**
```php
href="gerar_documento.php?modelo_id=<?= (int)$m['id'] ?><?= $cliente_id > 0 ? '&cliente_id=' . $cliente_id : '' ?>"
```

---

### 3.4 `criar_modelo.php`

Editor de novo modelo. Usa TinyMCE para edição WYSIWYG do HTML.

**Campos do formulário:**
- `nome` (text, obrigatório)
- `categoria` (select: Geral, Contrato, Procuração, Declaração, Requerimento, Ofício, Outro)
- `descricao` (text, opcional)
- `conteudo` (textarea → TinyMCE)
- `csrf_token` (hidden)
- `acao` = `criar` (hidden)

**Botões de campo:** Cada marcador disponível tem um botão `<button class="btn-campo" onclick="inserirCampo('{{marcador}}')">` que chama:
```javascript
function inserirCampo(marcador) {
    if (tinymce.activeEditor) {
        tinymce.activeEditor.insertContent(marcador);
        tinymce.activeEditor.focus();
    }
}
```

**Submit via AJAX:**
```javascript
document.getElementById('formModelo').addEventListener('submit', async function (e) {
    e.preventDefault();
    // Sincronizar TinyMCE → textarea
    const editor = tinymce.get('conteudo');
    if (editor) document.getElementById('conteudo').value = editor.getContent();

    const fd = new FormData(this);
    const resp = await fetch(this.action, { method: 'POST', body: fd });
    const data = await resp.json();
    if (data.sucesso) {
        window.location.href = 'listar_modelos.php';
    } else {
        // exibir data.mensagem no #msgErro
    }
});
```

**TinyMCE init:**
```javascript
tinymce.init({
    selector: '#conteudo',
    language: 'pt_BR',
    height: 550,
    plugins: ['advlist','autolink','lists','link','charmap','searchreplace',
              'visualblocks','code','fullscreen','insertdatetime','table','wordcount'],
    toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent | table | link | code fullscreen',
    content_style: 'body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.6; }',
    promotion: false
});
```

---

### 3.5 `editar_modelo.php`

Idêntico a `criar_modelo.php`, mas:
- Lê `?id=X` do GET
- Busca o modelo no banco: `SELECT id, nome, categoria, descricao, conteudo FROM modelos_documentos WHERE id = ? AND ativo = 1`
- Pré-preenche todos os campos
- `acao` = `editar` (hidden)
- Adiciona `id` do modelo como hidden input

---

### 3.6 `salvar_modelo_ajax.php`

Endpoint POST que salva criação ou edição. Retorna JSON.

```php
<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

// Auth + CSRF
if (!isset($_SESSION['usuario_id'])) { echo json_encode(['sucesso'=>false,'mensagem'=>'Sessão expirada.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['sucesso'=>false,'mensagem'=>'Método não permitido.']); exit; }
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) { echo json_encode(['sucesso'=>false,'mensagem'=>'Token inválido.']); exit; }

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/log_utils.php';

try {
    $acao      = $_POST['acao'] ?? '';
    $nome      = trim(strip_tags($_POST['nome'] ?? ''));
    $categoria = trim(strip_tags($_POST['categoria'] ?? 'Geral'));
    $descricao = trim(strip_tags($_POST['descricao'] ?? ''));
    $conteudo  = $_POST['conteudo'] ?? ''; // HTML do TinyMCE — não usar strip_tags

    if ($nome === '')     throw new Exception('O nome do modelo é obrigatório.');
    if ($conteudo === '') throw new Exception('O conteúdo do modelo é obrigatório.');
    if (strlen($conteudo) > 5 * 1024 * 1024) throw new Exception('Conteúdo excede 5 MB.');

    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';

    if ($acao === 'criar') {
        $stmt = $conn->prepare("INSERT INTO modelos_documentos (nome, categoria, descricao, conteudo, criado_por) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $nome, $categoria, $descricao, $conteudo, $usuario_nome);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
        registrar_log($conn, $usuario_nome, 'CRIAR_MODELO', "Modelo '$nome' (ID: $id) criado.");
        $_SESSION['msg_modelos'] = ['tipo'=>'success','texto'=>"Modelo \"$nome\" criado com sucesso!"];
        echo json_encode(['sucesso'=>true,'mensagem'=>'Modelo criado com sucesso!','id'=>$id]);

    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID inválido.');
        $stmt = $conn->prepare("UPDATE modelos_documentos SET nome=?, categoria=?, descricao=?, conteudo=? WHERE id=? AND ativo=1");
        $stmt->bind_param('ssssi', $nome, $categoria, $descricao, $conteudo, $id);
        $stmt->execute();
        $afetados = $stmt->affected_rows;
        $stmt->close();
        if ($afetados === -1) throw new Exception('Erro ao atualizar.');
        // affected_rows = 0 pode ser sem mudança real (conteúdo idêntico), não é erro
        registrar_log($conn, $usuario_nome, 'EDITAR_MODELO', "Modelo ID $id ('$nome') atualizado.");
        $_SESSION['msg_modelos'] = ['tipo'=>'success','texto'=>"Modelo \"$nome\" atualizado com sucesso!"];
        echo json_encode(['sucesso'=>true,'mensagem'=>'Modelo atualizado com sucesso!']);

    } else {
        throw new Exception('Ação inválida.');
    }
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['sucesso'=>false,'mensagem'=>$e->getMessage()]);
}
ob_end_flush();
```

---

### 3.7 `excluir_modelo.php`

Soft-delete (marca `ativo = 0`). Apenas admins. Retorna JSON.

```php
<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) { echo json_encode(['sucesso'=>false,'mensagem'=>'Sessão expirada.']); exit; }
if (!(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Permissão negada. Apenas administradores podem excluir modelos.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['sucesso'=>false,'mensagem'=>'Método não permitido.']); exit; }

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) { echo json_encode(['sucesso'=>false,'mensagem'=>'Token inválido.']); exit; }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['sucesso'=>false,'mensagem'=>'ID inválido.']); exit; }

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/log_utils.php';

try {
    // Busca nome antes de excluir (para o log)
    $stmtN = $conn->prepare("SELECT nome FROM modelos_documentos WHERE id = ? AND ativo = 1 LIMIT 1");
    $stmtN->bind_param('i', $id);
    $stmtN->execute();
    $rowN = stmt_get_result($stmtN)->fetch_assoc();
    $nome_modelo = $rowN['nome'] ?? '';
    $stmtN->close();
    if ($nome_modelo === '') throw new Exception('Modelo não encontrado.');

    $stmt = $conn->prepare("UPDATE modelos_documentos SET ativo = 0 WHERE id = ? AND ativo = 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) throw new Exception('Modelo não encontrado ou já excluído.');
    $stmt->close();

    $usuario_nome = $_SESSION['usuario_nome'] ?? 'admin';
    registrar_log($conn, $usuario_nome, 'EXCLUIR_MODELO', "Modelo ID $id ('$nome_modelo') excluído (soft delete).");
    $conn->close();

    echo json_encode(['sucesso'=>true,'mensagem'=>"Modelo \"$nome_modelo\" excluído com sucesso."]);
} catch (Exception $e) {
    echo json_encode(['sucesso'=>false,'mensagem'=>$e->getMessage()]);
}
ob_end_flush();
```

---

### 3.8 `gerar_documento.php`

Formulário onde o usuário escolhe o cliente e dependentes antes de gerar o PDF.

**Lógica PHP:**
```php
$modelo_id  = (int)($_GET['modelo_id']  ?? 0);
$cliente_id = (int)($_GET['cliente_id'] ?? 0);
if ($modelo_id <= 0) { header('Location: listar_modelos.php'); exit; }

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Buscar modelo (inclui conteudo para detectar campos)
$stmt = $conn->prepare("SELECT id, nome, categoria, conteudo FROM modelos_documentos WHERE id = ? AND ativo = 1 LIMIT 1");
// ...

// Detectar quais campos o modelo usa (para mostrar só os selects necessários)
$conteudo_modelo = $modelo['conteudo'];
$usa_advogado   = strpos($conteudo_modelo, '{{advogado_')   !== false;
$usa_advogado_2 = strpos($conteudo_modelo, '{{advogado_2_') !== false;
$usa_advogado_3 = strpos($conteudo_modelo, '{{advogado_3_') !== false;
$usa_filho      = strpos($conteudo_modelo, '{{filho_')      !== false;
$usa_incapaz    = strpos($conteudo_modelo, '{{incapaz_')    !== false;
$usa_a_rogo     = strpos($conteudo_modelo, '{{a_rogo_')     !== false;

// Buscar clientes
$clientes = [];
$stmtC = $conn->prepare("SELECT id, nome, cpf FROM clientes ORDER BY nome ASC");
// ...

// Buscar advogados ativos
$advogados = [];
$resA = $conn->query("SELECT id, nome, oab FROM advogados WHERE ativo = 1 ORDER BY nome ASC");
// ...

// Buscar dependentes do cliente pré-selecionado
$filhos = $incapazes = $a_rogos = [];
if ($cliente_id > 0) {
    // filhos_menores, incapazes, a_rogo WHERE cliente_id = ?
}
```

**Formulário HTML:**
```html
<form id="formGerar" method="POST" action="gerar_documento_pdf.php" target="_blank">
    <input type="hidden" name="csrf_token" value="...">
    <input type="hidden" name="modelo_id"  value="...">

    <!-- CLIENTE (obrigatório) -->
    <select id="cliente_id" name="cliente_id" required>
        <option value="">— Selecione o cliente —</option>
        <?php foreach ($clientes as $c): ?>
            <option value="<?= (int)$c['id'] ?>"
                <?= (int)$c['id'] === $cliente_id ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nome']) ?> — <?= htmlspecialchars($c['cpf']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- ADVOGADOS (só se $usa_advogado) -->
    <?php if ($usa_advogado): ?>
        <select name="advogado_1_id">
            <option value="0">— Não informar —</option>
            <?php foreach ($advogados as $adv): ?>
                <option value="<?= $adv['id'] ?>"><?= htmlspecialchars($adv['nome']) ?> — OAB <?= htmlspecialchars($adv['oab']) ?></option>
            <?php endforeach; ?>
        </select>
        <!-- advogado_2_id se $usa_advogado_2, advogado_3_id se $usa_advogado_3 -->
    <?php endif; ?>

    <!-- FILHO (só se $usa_filho) -->
    <?php if ($usa_filho): ?>
        <select name="filho_id" <?= $cliente_id <= 0 ? 'disabled' : '' ?>>
            <option value="0">— Não informar —</option>
            <?php foreach ($filhos as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>

    <!-- INCAPAZ (só se $usa_incapaz) -->
    <!-- A ROGO (só se $usa_a_rogo) -->

    <button type="submit" id="btnGerar">Gerar PDF</button>
</form>
```

**JavaScript — recarrega a página quando cliente muda (para atualizar dependentes):**
```javascript
document.getElementById('cliente_id').addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('modelo_id', modeloId);
    if (this.value) {
        url.searchParams.set('cliente_id', this.value);
    } else {
        url.searchParams.delete('cliente_id');
    }
    window.location.href = url.toString();
});
```

**Botão de retorno ao cliente** (exibido após o formulário, quando `cliente_id > 0`):
```html
<div class="acoes-retorno" style="margin-top:12px;">
    <?php if ($cliente_id > 0): ?>
        <a href="listar_clientes.php" class="btn btn-secondary">
            ← Voltar para clientes
        </a>
        <a href="listar_modelos.php?cliente_id=<?= $cliente_id ?>" class="btn btn-outline">
            ← Escolher outro modelo
        </a>
    <?php else: ?>
        <a href="listar_modelos.php" class="btn btn-secondary">← Voltar para modelos</a>
    <?php endif; ?>
</div>
```

**Submit — mostra loading e desabilita botão por 5s:**
```javascript
document.getElementById('formGerar').addEventListener('submit', function (e) {
    if (!document.getElementById('cliente_id').value) {
        e.preventDefault();
        // exibir erro
        return;
    }
    document.getElementById('loadingMsg').style.display = 'block';
    document.getElementById('btnGerar').disabled = true;
    setTimeout(() => {
        document.getElementById('loadingMsg').style.display = 'none';
        document.getElementById('btnGerar').disabled = false;
    }, 5000);
});
```

---

### 3.9 `gerar_documento_pdf.php`

Endpoint POST que gera e faz stream do PDF. É o arquivo mais crítico.

```php
<?php
ob_start();
session_start();

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    ob_end_clean(); http_response_code(500);
    exit('<p>Execute <code>composer install</code>.</p>');
}
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Auth
if (!isset($_SESSION['usuario_id'])) { http_response_code(403); exit('Acesso negado.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }

// CSRF
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) { http_response_code(403); exit('Token inválido.'); }

// Inputs
$modelo_id     = (int)($_POST['modelo_id']     ?? 0);
$cliente_id    = (int)($_POST['cliente_id']    ?? 0);
$advogado_1_id = (int)($_POST['advogado_1_id'] ?? 0);
$advogado_2_id = (int)($_POST['advogado_2_id'] ?? 0);
$advogado_3_id = (int)($_POST['advogado_3_id'] ?? 0);
$filho_id      = (int)($_POST['filho_id']      ?? 0);
$incapaz_id    = (int)($_POST['incapaz_id']    ?? 0);
$a_rogo_id     = (int)($_POST['a_rogo_id']     ?? 0);

if ($modelo_id <= 0 || $cliente_id <= 0) exit('Parâmetros inválidos.');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/log_utils.php';
require_once __DIR__ . '/advogados_utils.php';
require_once __DIR__ . '/src/ModeloSubstituicao.php';

try {
    // 1. Modelo
    $stmtM = $conn->prepare("SELECT nome, conteudo FROM modelos_documentos WHERE id = ? AND ativo = 1 LIMIT 1");
    $stmtM->bind_param('i', $modelo_id);
    $stmtM->execute();
    $modelo = stmt_get_result($stmtM)->fetch_assoc();
    $stmtM->close();
    if (!$modelo) throw new Exception('Modelo não encontrado.');

    // 2. Cliente
    $stmtC = $conn->prepare(
        "SELECT nome, cpf, rg, data_nascimento, estado_civil, profissao,
                telefone, email, endereco, cep, cidade, uf, nacionalidade,
                beneficio, numero_processo, situacao
         FROM clientes WHERE id = ? LIMIT 1"
    );
    $stmtC->bind_param('i', $cliente_id);
    $stmtC->execute();
    $cliente = stmt_get_result($stmtC)->fetch_assoc();
    $stmtC->close();
    if (!$cliente) throw new Exception('Cliente não encontrado.');

    // 3. Advogados 1, 2, 3
    garantirTabelaAdvogados($conn);
    $buscarAdv = fn(int $id) => $id > 0 ? (obterAdvogadoContratado($conn, $id) ?? []) : [];
    $advogados = [$buscarAdv($advogado_1_id), $buscarAdv($advogado_2_id), $buscarAdv($advogado_3_id)];

    // 4. Filho menor
    $filho = [];
    if ($filho_id > 0) {
        $s = $conn->prepare("SELECT nome, cpf, data_nascimento FROM filhos_menores WHERE id = ? AND cliente_id = ? LIMIT 1");
        $s->bind_param('ii', $filho_id, $cliente_id);
        $s->execute();
        $filho = stmt_get_result($s)->fetch_assoc() ?? [];
        $s->close();
    }

    // 5. Incapaz
    $incapaz = [];
    if ($incapaz_id > 0) {
        $s = $conn->prepare("SELECT nome, cpf, data_nascimento FROM incapazes WHERE id = ? AND cliente_id = ? LIMIT 1");
        $s->bind_param('ii', $incapaz_id, $cliente_id);
        $s->execute();
        $incapaz = stmt_get_result($s)->fetch_assoc() ?? [];
        $s->close();
    }

    // 6. A Rogo
    $aRogo = [];
    if ($a_rogo_id > 0) {
        $s = $conn->prepare("SELECT nome, identidade, cpf FROM a_rogo WHERE id = ? AND cliente_id = ? LIMIT 1");
        $s->bind_param('ii', $a_rogo_id, $cliente_id);
        $s->execute();
        $aRogo = stmt_get_result($s)->fetch_assoc() ?? [];
        $s->close();
    }

    // 7. Dados da empresa
    $empresa = [];
    $chaves = ['empresa_nome','empresa_cnpj','empresa_fone','empresa_email','empresa_proprietarios','empresa_endereco','empresa_cidade'];
    $placeholders = implode(',', array_fill(0, count($chaves), '?'));
    $stmtE = $conn->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ($placeholders)");
    $stmtE->bind_param(str_repeat('s', count($chaves)), ...$chaves);
    $stmtE->execute();
    $resE = stmt_get_result($stmtE);
    while ($rowE = $resE->fetch_assoc()) $empresa[$rowE['chave']] = $rowE['valor'];
    $stmtE->close();

    // 8. Substituição
    $substituicoes  = ModeloSubstituicao::construirMapa($cliente, $empresa, $_SESSION['usuario_nome'] ?? '', $advogados, $filho, $incapaz, $aRogo);
    $conteudo_final = ModeloSubstituicao::substituir($modelo['conteudo'], $substituicoes);

    // 9. Montar HTML para o PDF
    $nome_pdf = preg_replace('/[^a-zA-Z0-9_-]/', '_', $modelo['nome']) . '_' . date('Ymd_His') . '.pdf';
    $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
        @page { margin: 0; }
        body { margin:0; padding:0; background:#dce4ec; font-family:\'DejaVu Sans\',sans-serif; font-size:9pt; color:#1a1a1a; line-height:1.45; }
        p { margin:0; }
        table { border-collapse:collapse; }
        td { vertical-align:top; padding:0; }
        strong, b { font-weight:bold; }
    </style></head><body>' . $conteudo_final . '</body></html>';

    // 10. Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false); // segurança: sem requests externos
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // 11. Log
    registrar_log($conn, $_SESSION['usuario_nome'] ?? 'Sistema', 'GERAR_PDF',
        "PDF '{$modelo['nome']}' gerado para cliente ID $cliente_id ({$cliente['nome']}).");

    $conn->close();
    ob_end_clean(); // limpar buffer ANTES do stream

    // 12. Stream — abre no browser (não força download)
    $dompdf->stream($nome_pdf, ['Attachment' => false]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo '<p style="color:red;">Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="javascript:history.back()">Voltar</a></p>';
}
```

---

## 4. Arquivos Modificados

### 4.1 Tela de listagem de clientes (`listar_clientes.php`)

**Mudança:** O botão "MODELO" antes bloqueava com `alert()` se nenhum cliente estivesse selecionado. Agora redireciona sempre, passando o cliente apenas se houver um selecionado.

**Antes:**
```javascript
function abrirModelosCliente() {
    var clienteId = obterClienteSelecionadoId();
    if (!clienteId) {
        alert('Selecione um cliente primeiro.');
        return;
    }
    window.location.href = 'listar_modelos.php?cliente_id=' + clienteId;
}
```

**Depois:**
```javascript
function abrirModelosCliente() {
    var clienteId = obterClienteSelecionadoId();
    window.location.href = clienteId
        ? 'listar_modelos.php?cliente_id=' + clienteId
        : 'listar_modelos.php';
}
```

**Botão HTML (exemplo de como deve estar no sidebar/toolbar):**
```html
<button type="button" onclick="abrirModelosCliente()" title="Gerar documento a partir de um modelo">
    <i class="fas fa-file-alt"></i> MODELO
</button>
```

---

### 4.2 Menu/Sidebar principal

Adicionar link para "Modelos de Documentos" no menu de navegação global (presente em `dashboard.php` e demais páginas) para que seja acessível sem passar pela tela de clientes.

**Exemplo de entrada no menu:**
```html
<a href="listar_modelos.php" title="Modelos de Documentos">
    <i class="fas fa-file-alt"></i>
    <span>Modelos</span>
</a>
```

Verificar o componente/include que renderiza o menu lateral no projeto e adicionar o item na posição adequada (ex: após "Processos" ou "Financeiro").

---

### 4.3 `listar_advogados.php` — Integração com o CRUD de advogados

O arquivo `listar_advogados.php` já existe no projeto mas não gerencia a tabela `advogados` usada pelos modelos. Ele precisa ser atualizado (ou um novo CRUD criado) para:

- Listar advogados da tabela `advogados` (não confundir com outras tabelas existentes)
- Permitir **cadastrar** novo advogado com os campos: nome, documento (CPF/CNPJ), OAB, endereço, cidade, UF, fone, email
- Permitir **editar** e **desativar** (`ativo = 0`) advogados
- O formulário de cadastro/edição deve validar OAB e documento no frontend

**Arquivos a criar:**
| Arquivo | Descrição |
|---------|-----------|
| `listar_advogados.php` | Substituir/atualizar para listar da tabela `advogados` |
| `cadastrar_advogado.php` | Formulário de novo advogado |
| `editar_advogado.php` | Formulário de edição |
| `salvar_advogado_ajax.php` | Endpoint POST JSON: criar/editar |
| `excluir_advogado.php` | Soft-delete (admin). Endpoint POST JSON |

---

## 5. Sistema de Marcadores `{{campo}}`

### Todos os marcadores suportados

| Grupo | Marcador | Origem |
|-------|----------|--------|
| Cliente | `{{cliente_nome}}` | `clientes.nome` |
| Cliente | `{{cliente_cpf}}` | `clientes.cpf` (formatado ###.###.###-##) |
| Cliente | `{{cliente_rg}}` | `clientes.rg` |
| Cliente | `{{cliente_data_nascimento}}` | `clientes.data_nascimento` (formatado DD/MM/YYYY) |
| Cliente | `{{cliente_estado_civil}}` | `clientes.estado_civil` |
| Cliente | `{{cliente_profissao}}` | `clientes.profissao` |
| Cliente | `{{cliente_telefone}}` | `clientes.telefone` |
| Cliente | `{{cliente_email}}` | `clientes.email` |
| Cliente | `{{cliente_endereco}}` | `clientes.endereco` |
| Cliente | `{{cliente_cep}}` | `clientes.cep` |
| Cliente | `{{cliente_cidade}}` | `clientes.cidade` |
| Cliente | `{{cliente_uf}}` | `clientes.uf` |
| Cliente | `{{cliente_nacionalidade}}` | `clientes.nacionalidade` |
| Cliente | `{{cliente_beneficio}}` | `clientes.beneficio` |
| Cliente | `{{cliente_numero_processo}}` | `clientes.numero_processo` |
| Cliente | `{{cliente_situacao}}` | `clientes.situacao` |
| Empresa | `{{empresa_nome}}` | `configuracoes_sistema` |
| Empresa | `{{empresa_cnpj}}` | `configuracoes_sistema` |
| Empresa | `{{empresa_fone}}` | `configuracoes_sistema` |
| Empresa | `{{empresa_email}}` | `configuracoes_sistema` |
| Empresa | `{{empresa_proprietarios}}` | `configuracoes_sistema` |
| Empresa | `{{empresa_endereco}}` | `configuracoes_sistema` |
| Empresa | `{{empresa_cidade}}` | `configuracoes_sistema` |
| Data | `{{data_hoje}}` | `date('d/m/Y')` |
| Data | `{{data_hoje_extenso}}` | Ex: "10 de abril de 2026" |
| Usuário | `{{usuario_nome}}` | `$_SESSION['usuario_nome']` |
| Advogado 1 | `{{advogado_1_nome}}` | `advogados.nome` |
| Advogado 1 | `{{advogado_1_oab}}` | `advogados.oab` |
| Advogado 1 | `{{advogado_1_documento}}` | `advogados.documento` |
| Advogado 1 | `{{advogado_1_endereco}}` | `advogados.endereco` |
| Advogado 1 | `{{advogado_1_cidade}}` | `advogados.cidade` |
| Advogado 1 | `{{advogado_1_uf}}` | `advogados.uf` |
| Advogado 1 | `{{advogado_1_fone}}` | `advogados.fone` |
| Advogado 1 | `{{advogado_1_email}}` | `advogados.email` |
| Advogado 2 | `{{advogado_2_*}}` | mesmo padrão |
| Advogado 3 | `{{advogado_3_*}}` | mesmo padrão |
| Aliases compat. | `{{advogado_nome}}` etc. | apontam para advogado 1 |
| Filho menor | `{{filho_nome}}` | `filhos_menores.nome` |
| Filho menor | `{{filho_cpf}}` | `filhos_menores.cpf` (formatado) |
| Filho menor | `{{filho_data_nascimento}}` | `filhos_menores.data_nascimento` (formatado) |
| Incapaz | `{{incapaz_nome}}` | `incapazes.nome` |
| Incapaz | `{{incapaz_cpf}}` | `incapazes.cpf` (formatado) |
| Incapaz | `{{incapaz_data_nascimento}}` | `incapazes.data_nascimento` (formatado) |
| A Rogo | `{{a_rogo_nome}}` | `a_rogo.nome` |
| A Rogo | `{{a_rogo_identidade}}` | `a_rogo.identidade` |
| A Rogo | `{{a_rogo_cpf}}` | `a_rogo.cpf` (formatado) |

### Como a substituição funciona

```php
// 1. Construir mapa
$mapa = ModeloSubstituicao::construirMapa($cliente, $empresa, $usuarioNome, $advogados, $filho, $incapaz, $aRogo);

// 2. Aplicar ao conteúdo HTML do modelo
$conteudo_final = ModeloSubstituicao::substituir($modelo['conteudo'], $mapa);

// Internamente é apenas:
return str_replace(array_keys($mapa), array_values($mapa), $conteudo);
```

Marcadores não reconhecidos ficam intactos no HTML final (não causam erro).

---

## 6. Estrutura HTML dos Templates no Banco

Os templates são armazenados como HTML puro com CSS inline, otimizados para Dompdf. Estrutura padrão usada:

```html
<!-- Barra de título -->
<table width="100%" style="border-collapse:collapse;">
  <tr>
    <td style="background:#243447; color:#ffffff; font-weight:bold;
               font-size:12pt; letter-spacing:0.5pt; padding:7pt 14pt;
               font-family:DejaVu Sans,sans-serif;">PROCURAÇÃO</td>
  </tr>
</table>

<!-- Conteúdo principal com padding externo -->
<table width="100%" style="border-collapse:collapse;">
  <tr>
    <td style="padding:8pt 12pt 12pt 12pt;">

      <!-- Seção 1: OUTORGANTE -->
      <table width="100%" style="border-collapse:collapse; page-break-inside:avoid;">
        <tr>
          <td style="background:#7f97aa; color:#1c2d3c; font-weight:bold;
                     padding:3pt 8pt; font-size:9pt;">OUTORGANTE:</td>
        </tr>
        <tr>
          <td style="background:#ffffff; padding:5pt 8pt 7pt 8pt;
                     text-align:justify; line-height:1.45; font-size:9pt;">
            <strong>{{cliente_nome}}</strong>. {{cliente_nacionalidade}},
            {{cliente_estado_civil}}, {{cliente_profissao}}, portador(a) do
            documento de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF
            sob o nº {{cliente_cpf}}, residente em {{cliente_endereco}},
            CEP: {{cliente_cep}}, {{cliente_cidade}} - {{cliente_uf}},
            telefone: {{cliente_telefone}}, e-mail: {{cliente_email}}.
          </td>
        </tr>
      </table>

      <!-- Seção 2: OUTORGADOS (gap de 4pt entre seções) -->
      <table width="100%" style="border-collapse:collapse; margin-top:4pt; page-break-inside:avoid;">
        <tr>
          <td style="background:#7f97aa; color:#1c2d3c; font-weight:bold;
                     padding:3pt 8pt; font-size:9pt;">OUTORGADOS:</td>
        </tr>
        <tr>
          <td style="background:#ffffff; padding:5pt 8pt 7pt 8pt;
                     text-align:justify; line-height:1.45; font-size:9pt;">
            <strong>{{empresa_proprietarios}}</strong>. ...
          </td>
        </tr>
      </table>

      <!-- Seção 3: PODERES -->
      <table width="100%" style="border-collapse:collapse; margin-top:4pt; page-break-inside:avoid;">
        <!-- mesmo padrão -->
      </table>

      <!-- Cidade e data -->
      <p style="text-align:center; margin-top:10pt; font-size:9pt;">
        {{cliente_cidade}} - AM, {{data_hoje}}
      </p>

      <!-- Assinatura simples -->
      <table width="100%" style="border-collapse:collapse; margin-top:14pt; page-break-inside:avoid;">
        <tr>
          <td width="25%"></td>
          <td width="50%" style="border-top:1px solid #888888; text-align:center;
                                 padding-top:4pt; font-size:9pt; line-height:1.4;">
            <strong>{{cliente_nome}}</strong><br/>CPF/MF: {{cliente_cpf}}
          </td>
          <td width="25%"></td>
        </tr>
      </table>

    </td>
  </tr>
</table>
```

**Paleta de cores usada:**
| Elemento | Cor |
|----------|-----|
| Barra de título | `#243447` (azul-marinho escuro) |
| Cabeçalho de seção | `#7f97aa` (azul-acinzentado médio) |
| Texto do cabeçalho | `#1c2d3c` |
| Fundo da página | `#dce4ec` (cinza-azulado claro) |
| Fundo do card | `#ffffff` |

**CSS global do PDF** (em `gerar_documento_pdf.php`):
```css
@page { margin: 0; }
body {
    margin: 0; padding: 0;
    background: #dce4ec;
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9pt;
    color: #1a1a1a;
    line-height: 1.45;
}
p      { margin: 0; }
table  { border-collapse: collapse; }
td     { vertical-align: top; padding: 0; }
strong, b { font-weight: bold; }
```

---

## 7. Fluxo Completo do Usuário

```
listar_clientes.php
    └── [botão MODELO] → abrirModelosCliente()
            ├── sem cliente selecionado → listar_modelos.php
            └── com cliente → listar_modelos.php?cliente_id=X

listar_modelos.php
    ├── exibe banner "Gerando para: {nome}" se cliente_id presente
    ├── [botão Editar] → editar_modelo.php?id=X
    ├── [botão Gerar]  → gerar_documento.php?modelo_id=X&cliente_id=Y
    └── [botão Excluir (admin)] → fetch POST excluir_modelo.php

gerar_documento.php?modelo_id=X&cliente_id=Y
    ├── detecta marcadores do modelo → exibe apenas os selects necessários
    ├── pré-seleciona cliente Y se passado via GET
    ├── troca de cliente → recarrega página com novo cliente_id
    └── [submit] → POST gerar_documento_pdf.php (target="_blank")

gerar_documento_pdf.php [POST]
    ├── valida CSRF, sessão, IDs
    ├── busca modelo, cliente, advogados, dependentes, empresa
    ├── ModeloSubstituicao::construirMapa() + substituir()
    ├── monta HTML completo com CSS para Dompdf
    ├── Dompdf::render() → stream PDF no browser
    └── registrar_log() → controle_acesso

criar_modelo.php / editar_modelo.php
    └── [submit AJAX] → POST salvar_modelo_ajax.php → JSON → redirect
```

---

## 8. Segurança

| Proteção | Implementação |
|----------|--------------|
| Autenticação | `$_SESSION['usuario_id']` verificado em todos os endpoints |
| CSRF | `bin2hex(random_bytes(32))` na sessão, `hash_equals()` na validação |
| SQL Injection | Todas as queries usam `prepare()` + `bind_param()` |
| XSS | Todos os valores no mapa passam por `htmlspecialchars()` com `ENT_QUOTES\|ENT_HTML5` |
| Recursos externos no PDF | `isRemoteEnabled = false` no Dompdf |
| Exclusão restrita | `excluir_modelo.php` verifica `$_SESSION['is_admin'] == 1` |
| Tamanho máximo | `conteudo` limitado a 5 MB em `salvar_modelo_ajax.php` |
| Soft delete | `ativo = 0` em vez de `DELETE` — registros jamais são apagados fisicamente |

---

## 9. Resumo dos Arquivos

| Arquivo | Tipo | Descrição |
|---------|------|-----------|
| `src/ModeloSubstituicao.php` | **Novo** | Classe pura de substituição de marcadores |
| `advogados_utils.php` | **Novo** | Funções para tabela advogados |
| `listar_modelos.php` | **Novo** | Lista modelos, suporte a cliente_id via GET |
| `criar_modelo.php` | **Novo** | Formulário de criação com TinyMCE |
| `editar_modelo.php` | **Novo** | Formulário de edição com TinyMCE |
| `salvar_modelo_ajax.php` | **Novo** | Endpoint POST JSON: criar/editar |
| `excluir_modelo.php` | **Novo** | Endpoint POST JSON: soft-delete (admin) |
| `gerar_documento.php` | **Novo** | Formulário de seleção de dados + botões de retorno |
| `gerar_documento_pdf.php` | **Novo** | Gerador de PDF via Dompdf (POST) |
| `criar_tabela_modelos_documentos.sql` | **Novo** | DDL da tabela modelos_documentos |
| `setup_configuracoes.sql` | **Novo** | INSERT IGNORE para empresa_endereco e empresa_cidade |
| `listar_advogados.php` | **Novo/Modificado** | CRUD de advogados (tabela advogados) |
| `cadastrar_advogado.php` | **Novo** | Formulário de cadastro de advogado |
| `editar_advogado.php` | **Novo** | Formulário de edição de advogado |
| `salvar_advogado_ajax.php` | **Novo** | Endpoint POST JSON: criar/editar advogado |
| `excluir_advogado.php` | **Novo** | Endpoint POST JSON: soft-delete advogado (admin) |
| `listar_clientes.php` | **Modificado** | Função `abrirModelosCliente()` sem alert de bloqueio |
| Menu/Sidebar | **Modificado** | Link "Modelos" adicionado na navegação global |
