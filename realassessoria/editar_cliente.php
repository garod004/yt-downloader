<?php
session_start();
require_once 'conexao.php';
require_once __DIR__ . '/log_utils.php';
mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_INDEX);

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_logado_id = $_SESSION['usuario_id'];

// Definir tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$is_admin = ($tipo_usuario === 'admin' || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1));
$is_parceiro = ($tipo_usuario === 'parceiro'); 

function formatar_hora($hora) {
    if (empty($hora)) return '';
    $hora = trim($hora);
    if (strlen($hora) >= 5) $hora = substr($hora, 0, 5);
    if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) return $hora;
    return '';
}

function formatarCPF($cpf) {
    if (empty($cpf)) return '';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatarTelefone($telefone) {
    if (empty($telefone)) return '';
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 1) . ' ' . substr($telefone, 3, 4) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

function normalizar_situacao($situacao) {
    $situacao = trim((string)$situacao);
    if ($situacao === '') return $situacao;

    $normalizado = strtolower($situacao);
    $normalizado = str_replace('_', ' ', $normalizado);
    $normalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalizado);
    $normalizado = preg_replace('/\s+/', ' ', (string)$normalizado);
    $normalizado = trim((string)$normalizado);

    if ($normalizado === 'concluido sem decisao' || $normalizado === 'concluso sem decisao') {
        return 'concluido_sem_decisao';
    }

    return $situacao;
}

$mensagem = '';
$cliente = null;

// --- GET: BUSCAR CLIENTE ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {$id_para_editar = intval($_GET['id']);}

if ($stmt = $conn->prepare("SELECT c.*, 
    u1.nome as nome_criador, 
    u2.nome as nome_editor,
    c.created_at,
    c.updated_at,
    c.usuario_cadastro_id
    FROM clientes c 
    LEFT JOIN usuarios u1 ON c.created_by = u1.id 
    LEFT JOIN usuarios u2 ON c.updated_by = u2.id 
    WHERE c.id = ?")) {
    $stmt->bind_param("i", $id_para_editar);
        $stmt->execute();
        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc() ?: null;
        $stmt->close();

if (!$cliente) {
    $mensagem = "❌ Cliente não encontrado.";
} elseif ($is_parceiro && $cliente['usuario_cadastro_id'] != $usuario_logado_id && $cliente['usuario_cadastro_id'] !== null) {
    // Parceiro só edita seus próprios clientes
    $mensagem = "❌ Você não tem permissão para editar este cliente.";
    $cliente = null;
}
// Admin e Usuario podem editar todos os clientes
     
}
else {$mensagem = "Erro interno. Por favor, tente novamente.";
    }


// --- POST: ATUALIZAR CLIENTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $data_contrato = $_POST['data_contrato'] ?? '';
    $beneficio = $_POST['beneficio'] ?? '';
    $data_enviado = $_POST['data_enviado'] ?? '';
    $situacao = normalizar_situacao($_POST['situacao'] ?? '');
    $indicador = $_POST['indicador'] ?? '';
    $responsavel = $_POST['responsavel'] ?? '';
    $advogado = $_POST['advogado'] ?? '';
    $numero_processo = $_POST['numero_processo'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $nacionalidade = $_POST['nacionalidade'] ?? '';
    $profissao = $_POST['profissao'] ?? '';
    $estado_civil = $_POST['estado_civil'] ?? '';
    $rg = $_POST['rg'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $idade = $_POST['idade'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $uf = $_POST['uf'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $telefone2 = $_POST['telefone2'] ?? '';
    $telefone3 = $_POST['telefone3'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha_email = $_POST['senha_email'] ?? '';
    $senha_meuinss = $_POST['senha_meuinss'] ?? '';
    $data_avaliacao_social = $_POST['data_avaliacao_social'] ?? '';
    $hora_avaliacao_social = formatar_hora($_POST['hora_avaliacao_social'] ?? '');
    $endereco_avaliacao_social = $_POST['endereco_avaliacao_social'] ?? '';
    $realizado_a_s = isset($_POST['realizado_a_s']) ? 1 : 0;
    $data_pericia = $_POST['data_pericia'] ?? '';
    $hora_pericia = formatar_hora($_POST['hora_pericia'] ?? '');
    $endereco_pericia = $_POST['endereco_pericia'] ?? '';
    $realizado_pericia = isset($_POST['realizado_pericia']) ? 1 : 0;
    $contrato_assinado = isset($_POST['contrato_assinado']) ? 1 : 0;
    $observacao = $_POST['observacao'] ?? '';
    
    // VALIDAÇÃO
    if (empty($id) || empty($nome) || empty($cpf)) {
        $mensagem = "❌ Erro: ID, nome e cpf são obrigatórios.";
        $sql = null;
    } else {
        // SQL
        $sql = "UPDATE clientes SET 
            data_contrato = ?, /* 1 */
            beneficio = ?,     /* 2 */
            data_enviado = ?,  /* 3 */
            situacao = ?,      /* 4 */
            indicador = ?,     /* 5 */
            responsavel = ?,   /* 6 */
            advogado = ?,      /* 7 */
            numero_processo = ?, /* 8 */
            nome = ?,          /* 9 */
            nacionalidade = ?, /* 10 */
            profissao = ?,     /* 11 */
            estado_civil = ?,  /* 12 */
            rg = ?,
            data_nascimento = ?,
            idade = ?,            /* 13 */
            cpf = ?,           /* 14 */
            endereco = ?, 
            cep = ?,     /* 15 */
            cidade = ?,        /* 16 */
            uf = ?,            /* 17 */
            telefone = ?,
            telefone2 = ?,
            telefone3 = ?,
            email = ?,         /* 19 */
            senha_email = ?,   /* 20 */
            senha_meuinss = ?, /* 21 */
            data_avaliacao_social = ?,     /* 22 */
            hora_avaliacao_social = ?,     /* 23 */
            endereco_avaliacao_social = ?, /* 24 */
            realizado_a_s = ?,             /* 25 */
            data_pericia = ?,              /* 26 */
            hora_pericia = ?,              /* 27 */
            endereco_pericia = ?,          /* 28 */
            realizado_pericia = ?,         /* 29 */
            contrato_assinado = ?,         /* 30 */
            observacao = ?,                /* 31 */
            updated_by = ?,                /* 32 */
            updated_at = NOW()
        WHERE id = ?";
    }
        // Só prepara e executa se $sql não estiver vazio
        if (!empty($sql) && ($stmt = $conn->prepare($sql))) {
            $tipos_correto = str_repeat('s', 29) . 'isssiisii';
            $stmt->bind_param(
                $tipos_correto,
                $data_contrato,      // s
                $beneficio,          // s
                $data_enviado,       // s
    $situacao,           // s
    $indicador,          // s
    $responsavel,        // s
    $advogado,           // s
    $numero_processo,    // s
    $nome,               // s
    $nacionalidade,      // s
    $profissao,          // s
    $estado_civil,       // s
    $rg,
    $data_nascimento,
    $idade,                 // s
    $cpf,                // s
    $endereco,
    $cep,           // s
    $cidade,             // s
    $uf,                 // s
    $telefone,
    $telefone2,
    $telefone3,           // s
    $email,              // s
    $senha_email,        // s
    $senha_meuinss,      // s
    $data_avaliacao_social, // s
    $hora_avaliacao_social, // s
    $endereco_avaliacao_social, // s
    $realizado_a_s,      // i (Correto, você converte para 1 ou 0)
    $data_pericia,       // s
    $hora_pericia,       // s
    $endereco_pericia,   // s
    $realizado_pericia,  // i (Correto, você converte para 1 ou 0)
    $contrato_assinado,  // i (Correto, você converte para 1 ou 0)
    $observacao,         // s
    $usuario_logado_id,  // i (ID do usuário que está editando)
    $id                  // i
);
                  if ($stmt->execute()) {
                      // Log alteração de qualquer campo do cliente
                      if (isset($_SESSION['usuario_nome'])) {
                          // Buscar nome atualizado do cliente para garantir que está correto
                          $nome_cliente_log = $nome;
                          if (empty($nome_cliente_log) && !empty($id)) {
                              $stmt_nome = $conn->prepare("SELECT nome FROM clientes WHERE id = ? LIMIT 1");
                              if ($stmt_nome) {
                                  $stmt_nome->bind_param("i", $id);
                                  $stmt_nome->execute();
                                  $stmt_nome->bind_result($nome_cliente_log_db);
                                  if ($stmt_nome->fetch()) {
                                      $nome_cliente_log = $nome_cliente_log_db;
                                  }
                                  $stmt_nome->close();
                              }
                          }
                          registrar_log($conn, $_SESSION['usuario_nome'], 'alteracao', 'Alterou dados do cliente', $id, $nome_cliente_log);
                      }
                      header("Location: editar_cliente.php?id=" . urlencode($id) . "&sucesso=1");
                      exit();
                  } else {
                      // Log detalhado do SQL e parâmetros
                      error_log("[ERRO UPDATE CLIENTE] SQL: " . $sql);
                      error_log("[ERRO UPDATE CLIENTE] PARAMS: " . var_export([
                          $nome, $nascimento, $sexo, $estado_civil, $nacionalidade, $profissao, $rg, $cpf, $endereco, $cep, $bairro, $cidade, $uf, $telefone, $email, $indicador, $responsavel, $data_contrato, $data_entrada, $data_concessao, $nb, $banco, $agencia, $conta, $tipo_conta, $valor_beneficio, $tipo_beneficio, $status, $senha_gov, $senha_email, $senha_meuinss, $data_avaliacao_social, $hora_avaliacao_social, $endereco_avaliacao_social, $realizado_a_s, $data_pericia, $hora_pericia, $endereco_pericia, $realizado_pericia, $contrato_assinado, $observacao, $usuario_logado_id, $id
                      ], true));
                      error_log("Erro DB editar_cliente: " . $stmt->error);
                      die("Erro interno ao salvar. Por favor, tente novamente.");
                  }
        } else {
            $mensagem = "Erro interno. Por favor, tente novamente.";
        }
    }

?>

<!doctype html>
<html lang="pt-br">
                <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width,initial-scale=1">
                <title>Editar Cliente</title>
                <link rel="stylesheet" href="editar_cliente.css">
                <style>
                /* Botões modernos para combinar com listar_clientes.php */
                .btn { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:10px; font-weight:600; text-decoration:none; cursor:pointer; border:none; }
                .btn svg{width:16px;height:16px}
                .btn-primary { background: linear-gradient(90deg,#3b82ff,#06b6d4); color:#fff; box-shadow:0 6px 18px rgba(59,130,255,0.12); }
                .btn-ghost { background: transparent; color: #1857d6; border:1px solid rgba(24,87,214,0.08); }
                .navbar .container { display:flex; align-items:center; justify-content:space-between; }
                .nav-menu { display:flex; gap:12px; list-style:none; align-items:center; }
                </style>
                </head>

    <body>

                       
                            
                <nav class="navbar">
                    <div class="container">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <img src="img/logo.meusis.png" alt="Logo MeuSIS" class="navbar-logo">
                            <div class="logo">Editar Cliente</div>
                        </div>
                            <ul class="nav-menu">
                                <li>
                                    <button type="submit" form="editForm" class="btn btn-primary" title="Salvar alterações">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><polyline points="17 21 17 13 7 13 7 21" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><polyline points="7 3 7 8 15 8" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Salvar
                                    </button>
                                </li>
                                <li>
                                    <a href="listar_clientes.php" class="btn btn-primary" title="Ir para listagem">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Listagem
                                    </a>
                                </li>
                            </ul>

                             <?php
                            // Exibe mensagem de sucesso se veio por GET
                            if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
                                echo '<div class="mensagem-sucesso">Cliente atualizado com sucesso!</div>';
                            } elseif (!empty($mensagem)) {
                                $cls = (strpos($mensagem, '❌') !== false) ? 'mensagem-erro' : 'mensagem-sucesso';
                                echo "<div class=\"{$cls}\">" . htmlspecialchars($mensagem) . "</div>";
                            }
                        ?>

                        <?php if ($cliente): ?>
                    </div>

                        

                </nav>

        <main class="container">
                    
         <?php if ($cliente && (isset($cliente['nome_criador']) || isset($cliente['nome_editor']))): ?>
                                <div style="background: #f0f8ff; padding: 15px; margin-bottom: 20px; border-radius: 5px; border-left: 4px solid #4CAF50;">
                                    <h3 style="margin: 0 0 10px 0; color: #333;">Informações de Auditoria</h3>
                                    <?php if (!empty($cliente['nome_criador'])): ?>
                                        <p style="margin: 5px 0; color: #555;">
                                            <strong>Cadastrado por:</strong> <?php echo htmlspecialchars($cliente['nome_criador']); ?>
                                            <?php if (!empty($cliente['created_at'])): ?>
                                                em <?php echo date('d/m/Y H:i', strtotime($cliente['created_at'])); ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($cliente['nome_editor'])): ?>
                                        <p style="margin: 5px 0; color: #555;">
                                            <strong>Última alteração por:</strong> <?php echo htmlspecialchars($cliente['nome_editor']); ?>
                                            <?php if (!empty($cliente['updated_at'])): ?>
                                                em <?php echo date('d/m/Y H:i', strtotime($cliente['updated_at'])); ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
         
<?php error_log('ID do cliente no form: ' . var_export($cliente['id'], true)); ?>
                                <form id="editForm" class="form-grid" method="post" action="editar_cliente.php">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($cliente['id']); ?>">

                                            <div>
                                                <label for="data_contrato">Data do Contrato</label>
                                                <input type="date" id="data_contrato" name="data_contrato" value="<?php echo htmlspecialchars($cliente['data_contrato']); ?>" class="input-field" required>
                                            </div>

                                            <div>
                                                <label for="beneficio">Benefício</label>
                                                <select id="beneficio" name="beneficio">
                                                    <option value="">-- selecione --</option>
                                                    <option value="aposentadoria_agricultor" <?php echo ($cliente['beneficio']=='aposentadoria_agricultor')?'selected':''; ?>>Aposentadoria Agricultor</option>
                                                    <option value="aposentadoria_pescador" <?php echo ($cliente['beneficio']=='aposentadoria_pescador')?'selected':''; ?>>Aposentadoria Pescador</option>
                                                    <option value="aposentadoria_indigena" <?php echo ($cliente['beneficio']=='aposentadoria_indigena')?'selected':''; ?>>Aposentadoria Indígena</option>
                                                    <option value="aposentadoria_tempo_contribuicao" <?php echo ($cliente['beneficio']=='aposentadoria_tempo_contribuicao')?'selected':''; ?>>Aposentadoria por tempo de contribuição Urbano</option>
                                                    <option value="aposentadoria_especial_urbana" <?php echo ($cliente['beneficio']=='aposentadoria_especial_urbana')?'selected':''; ?>>Aposentadoria Especial Urbano</option>
                                                    <option value="aposentadoria_invalidez" <?php echo ($cliente['beneficio']=='aposentadoria_invalidez')?'selected':''; ?>>Aposentadoria por Invalidez Urbano</option>
                                                    <option value="aposentadoria_hibrida" <?php echo ($cliente['beneficio']=='aposentadoria_hibrida')?'selected':''; ?>>Aposentadoria Híbrida</option>
                                                    <option value="pensao_por_morte" <?php echo ($cliente['beneficio']=='pensao_por_morte')?'selected':''; ?>>Pensão por morte</option>
                                                    <option value="bpc_idade" <?php echo ($cliente['beneficio']=='bpc_idade')?'selected':''; ?>>BPC por idade</option>
                                                    <option value="bpc_doenca" <?php echo ($cliente['beneficio']=='bpc_doenca')?'selected':''; ?>>BPC por doença</option>
                                                    <option value="auxilio_doenca" <?php echo ($cliente['beneficio']=='auxilio_doenca')?'selected':''; ?>>Auxílio Doença</option>
                                                    <option value="auxilio_acidente" <?php echo ($cliente['beneficio']=='auxilio_acidente')?'selected':''; ?>>Auxílio Acidente</option>
                                                    <option value="auxilio_reclusao" <?php echo ($cliente['beneficio']=='auxilio_reclusao')?'selected':''; ?>>Auxílio Reclusão</option>
                                                    <option value="salario_maternidade_urbano" <?php echo ($cliente['beneficio']=='salario_maternidade_urbano')?'selected':''; ?>>Salário Maternidade Urbano</option>
                                                    <option value="salario_maternidade_agricultora" <?php echo ($cliente['beneficio']=='salario_maternidade_agricultora')?'selected':''; ?>>Salário Maternidade Agricultora</option>
                                                    <option value="salario_maternidade_pescadora" <?php echo ($cliente['beneficio']=='salario_maternidade_pescadora')?'selected':''; ?>>Salário Maternidade Pescadora</option>
                                                    <option value="salario_maternidade_indigena" <?php echo ($cliente['beneficio']=='salario_maternidade_indigena')?'selected':''; ?>>Salário Maternidade Indígena</option>
                                                    <option value="divorcio" <?php echo ($cliente['beneficio']=='divorcio')?'selected':''; ?>>Divórcio</option>
                                                    <option value="acao_trabalhista" <?php echo ($cliente['beneficio']=='acao_trabalhista')?'selected':''; ?>>Ação Trabalhista</option>
                                                    <!-- adicione demais opções conforme necessário -->
                                                </select>
                                            </div>

                                            <div>
                                                <label for="data_enviado">Data de Enviado</label>
                                                <input type="date" id="data_enviado" name="data_enviado" value="<?php echo htmlspecialchars($cliente['data_enviado']); ?>" class="input-field">
                                            </div>

                                            <div class="form-group">
                                                <label for="situacao">Status:</label>
                                                <select id="situacao" name="situacao" class="input-field">
                                                <option value="">Selecione</option>

                                                <option value="enviado" <?= ($cliente['situacao'] == 'enviado') ? 'selected' : '' ?>>Enviado</option>
                                                <option value="negado" <?= ($cliente['situacao'] == 'negado') ? 'selected' : '' ?>>Negado</option>
                                                <option value="aprovado" <?= ($cliente['situacao'] == 'aprovado') ? 'selected' : '' ?>>Aprovado</option>
                                                <option value="pago" <?= ($cliente['situacao'] == 'pago') ? 'selected' : '' ?>>Pago</option>
                                                <option value="pericia" <?= ($cliente['situacao'] == 'pericia') ? 'selected' : '' ?>>Perícia</option>
                                                <option value="justica" <?= ($cliente['situacao'] == 'justica') ? 'selected' : '' ?>>Justiça</option>
                                                <option value="avaliacao_social" <?= ($cliente['situacao'] == 'avaliacao_social') ? 'selected' : '' ?>>Avaliação Social</option>
                                                <option value="indeferido" <?= ($cliente['situacao'] == 'indeferido') ? 'selected' : '' ?>>Indeferido</option>
                                                <option value="deferido" <?= ($cliente['situacao'] == 'deferido') ? 'selected' : '' ?>>Deferido</option>
                                                <option value="escritorio" <?= ($cliente['situacao'] == 'escritorio') ? 'selected' : '' ?>>Escritório</option>
                                                <option value="pendencia" <?= ($cliente['situacao'] == 'pendencia') ? 'selected' : '' ?>>Pendência</option>
                                                <option value="cancelado" <?= ($cliente['situacao'] == 'cancelado') ? 'selected' : '' ?>>Cancelado</option>
                                                <option value="falta_senha_meuinss" <?= ($cliente['situacao'] == 'falta_senha_meuinss') ? 'selected' : '' ?>>Falta a Senha do MeuINSS</option>
                                                <option value="esperando_data_certa" <?= ($cliente['situacao'] == 'esperando_data_certa') ? 'selected' : '' ?>>Esperando a Data Certa</option>
                                                <option value="falta_assinar_contrato" <?= ($cliente['situacao'] == 'falta_assinar_contrato') ? 'selected' : '' ?>>Falta Assinar Contrato</option>
                                                <option value="nao_pagou_escritorio" <?= ($cliente['situacao'] == 'nao_pagou_escritorio') ? 'selected' : '' ?>>Não Pagou o Escritório</option>
                                                <option value="baixa_definitiva" <?= ($cliente['situacao'] == 'baixa_definitiva') ? 'selected' : '' ?>>Baixa Definitiva</option>
                                                <option value="cadastro_biometria" <?= ($cliente['situacao'] == 'cadastro_biometria') ? 'selected' : '' ?>>Cadastro de Biometria</option>
                                                <option value="concluido_sem_decisao" <?= ($cliente['situacao'] == 'concluido_sem_decisao') ? 'selected' : '' ?>>Concluído Sem Decisão</option>
                                                <option value="reenvia" <?= ($cliente['situacao'] == 'reenvia') ? 'selected' : '' ?>>Reenviar</option>
                                                <option value="pagando" <?= ($cliente['situacao'] == 'pagando') ? 'selected' : '' ?>>Pagando</option>
                                                <option value="atendimento" <?= ($cliente['situacao'] == 'atendimento') ? 'selected' : '' ?>>Atendimento</option>
                                                <option value="crianca_nao_nasceu" <?= ($cliente['situacao'] == 'crianca_nao_nasceu') ? 'selected' : '' ?>>A criança ainda não nasceu</option>
                                                </select>
                                            </div>



                                            <div>
                                                <label for="indicador">Indicador</label>
                                                <input type="text" id="indicador" name="indicador" value="<?php echo htmlspecialchars($cliente['indicador']); ?>" required class="input-field">
                                            </div>

                                            <div>
                                                <label for="responsavel">Responsável</label>
                                                <input type="text" id="responsavel" name="responsavel" value="<?php echo htmlspecialchars($cliente['responsavel']); ?>" required class="input-field">
                                            </div>

                                            <div>
                                                <label for="advogado">Advogado</label>
                                                <input type="text" id="advogado" name="advogado" value="<?php echo htmlspecialchars($cliente['advogado']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="numero_processo">Número do Processo</label>
                                                <input type="text" id="numero_processo" name="numero_processo" value="<?php echo htmlspecialchars($cliente['numero_processo']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="nome">Nome</label>
                                                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($cliente['nome']); ?>" required class="input-field">
                                            </div>

                                            <div>
                                                <label for="nacionalidade">Nacionalidade</label>
                                                <input type="text" id="nacionalidade" name="nacionalidade" value="<?php echo htmlspecialchars($cliente['nacionalidade']); ?>" required class="input-field">
                                            </div>

                                            <div>
                                                <label for="profissao">Profissão</label>
                                                <input type="text" id="profissao" name="profissao" value="<?php echo htmlspecialchars($cliente['profissao']); ?>" required class="input-field">
                                            </div>

                                            <div class="form-group">
                                                <label for="estado_civil">Estado Civil:</label>
                                                <select id="estado_civil" name="estado_civil" class="input-field">
                                                    <option value="">Selecione</option>

                                                    <option value="solteiro(a)" 
                                                        <?= ($cliente['estado_civil'] == 'solteiro(a)') ? 'selected' : '' ?>>
                                                        Solteiro(a)
                                                    </option>

                                                    <option value="casado(a)" 
                                                        <?= ($cliente['estado_civil'] == 'casado(a)') ? 'selected' : '' ?>>
                                                        Casado(a)
                                                    </option>

                                                    <option value="divorciado(a)" 
                                                        <?= ($cliente['estado_civil'] == 'divorciado(a)') ? 'selected' : '' ?>>
                                                        Divorciado(a)
                                                    </option>

                                                    <option value="viúvo(a)" 
                                                        <?= ($cliente['estado_civil'] == 'viúvo(a)') ? 'selected' : '' ?>>
                                                        Viúvo(a)
                                                    </option>

                                                    <option value="união estável" 
                                                        <?= ($cliente['estado_civil'] == 'união estável') ? 'selected' : '' ?>>
                                                        União Estável
                                                    </option>

                                                    <option value="separado(a)" 
                                                        <?= ($cliente['estado_civil'] == 'separado(a)') ? 'selected' : '' ?>>
                                                        Separado(a)
                                                    </option>

                                                </select>
                                            </div>


                                            <div>
                                                <label for="rg">Identidade (RG)</label>
                                                <input type="text" id="rg" name="rg" value="<?php echo htmlspecialchars($cliente['rg']); ?>" required class="input-field">
                                            </div>

                                            <div>
                                                <label for="data_nascimento">Data Nascimento</label>
                                                <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo htmlspecialchars($cliente['data_nascimento']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="idade">Idade</label>
                                                <input type="text" id="idade" name="idade" value="<?php echo htmlspecialchars($cliente['idade']); ?>" class="input-field">
                                            </div>
                                            
                                            <div>
                                                <label for="cpf">CPF</label>
                                                <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars(formatarCPF($cliente['cpf'])); ?>" required class="input-field">
                                            </div>

                                            <div class="full">
                                                <label for="endereco">Endereço</label>
                                                <input type="text" id="endereco" name="endereco" value="<?php echo htmlspecialchars($cliente['endereco']); ?>" required class="input-field">
                                            </div>

                                            <div class="full">
                                                <label for="cep">CEP</label>
                                                <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($cliente['cep']); ?>" required class="input-field">
                                            </div>
                                            
                                            <script>
                                            (function() {
                                                var cepInput = document.getElementById('cep');
                                                if (cepInput) {
                                                    cepInput.addEventListener('input', function(e) {
                                                        let value = e.target.value.replace(/\D/g, '');
                                                        if (value.length <= 8) {
                                                            if (value.length > 5) {
                                                                value = value.replace(/^(\d{2})(\d{3})(\d{0,3})/, '$1.$2-$3');
                                                            } else if (value.length > 2) {
                                                                value = value.replace(/^(\d{2})(\d{0,3})/, '$1.$2');
                                                            }
                                                        }
                                                        e.target.value = value;
                                                    });
                                                }
                                            })();
                                            </script>

                                            <div>
                                                <label for="cidade">Cidade</label>
                                                <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cliente['cidade']); ?>" required class="input-field">
                                            </div>

                                            <div>
                                                <label for="uf">UF</label>
                                                <select id="uf" name="uf" class="input-field" required>
                                                <option value="">Selecione</option>
                                                <option value="AC" <?php echo ($cliente['uf']=='AC')?'selected':''; ?>>AC</option>
                                                <option value="AL" <?php echo ($cliente['uf']=='AL')?'selected':''; ?>>AL</option>
                                                <option value="AP" <?php echo ($cliente['uf']=='AP')?'selected':''; ?>>AP</option>
                                                <option value="AM" <?php echo ($cliente['uf']=='AM')?'selected':''; ?>>AM</option>
                                                <option value="BA" <?php echo ($cliente['uf']=='BA')?'selected':''; ?>>BA</option>
                                                <option value="CE" <?php echo ($cliente['uf']=='CE')?'selected':''; ?>>CE</option>
                                                <option value="ES" <?php echo ($cliente['uf']=='ES')?'selected':''; ?>>ES</option>
                                                <option value="DF" <?php echo ($cliente['uf']=='DF')?'selected':''; ?>>DF</option>
                                                <option value="MA" <?php echo ($cliente['uf']=='MA')?'selected':''; ?>>MA</option>
                                                <option value="MT" <?php echo ($cliente['uf']=='MT')?'selected':''; ?>>MT</option>
                                                <option value="MS" <?php echo ($cliente['uf']=='MS')?'selected':''; ?>>MS</option>
                                                <option value="MG" <?php echo ($cliente['uf']=='MG')?'selected':''; ?>>MG</option>
                                                <option value="PA" <?php echo ($cliente['uf']=='PA')?'selected':''; ?>>PA</option>
                                                <option value="PB" <?php echo ($cliente['uf']=='PB')?'selected':''; ?>>PB</option>
                                                <option value="PR" <?php echo ($cliente['uf']=='PR')?'selected':''; ?>>PR</option>
                                                <option value="PE" <?php echo ($cliente['uf']=='PE')?'selected':''; ?>>PE</option>
                                                <option value="PI" <?php echo ($cliente['uf']=='PI')?'selected':''; ?>>PI</option>
                                                <option value="RJ" <?php echo ($cliente['uf']=='RJ')?'selected':''; ?>>RJ</option>
                                                <option value="RN" <?php echo ($cliente['uf']=='RN')?'selected':''; ?>>RN</option>
                                                <option value="RS" <?php echo ($cliente['uf']=='RS')?'selected':''; ?>>RS</option>
                                                <option value="RO" <?php echo ($cliente['uf']=='RO')?'selected':''; ?>>RO</option>
                                                <option value="RR" <?php echo ($cliente['uf']=='RR')?'selected':''; ?>>RR</option>
                                                <option value="SC" <?php echo ($cliente['uf']=='SC')?'selected':''; ?>>SC</option>
                                                <option value="SP" <?php echo ($cliente['uf']=='SP')?'selected':''; ?>>SP</option>
                                                <option value="SE" <?php echo ($cliente['uf']=='SE')?'selected':''; ?>>SE</option>
                                                <option value="TO" <?php echo ($cliente['uf']=='TO')?'selected':''; ?>>TO</option>
                                            </select>
                                                
                                            </div>

                                            <div>
                                                <label for="telefone">Telefone</label>
                                                <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars(formatarTelefone($cliente['telefone'])); ?>" required class="input-field">
                                            </div>

                                            <div>
                                                <label for="telefone2">Telefone</label>
                                                <input type="text" id="telefone2" name="telefone2" value="<?php echo htmlspecialchars(formatarTelefone($cliente['telefone2'])); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="telefone3">Telefone</label>
                                                <input type="text" id="telefone3" name="telefone3" value="<?php echo htmlspecialchars(formatarTelefone($cliente['telefone3'])); ?>" class="input-field">
                                            </div>
                                            
                                            <script>
                                            (function() {
                                                function formatarTelefone(input) {
                                                    input.addEventListener('input', function(e) {
                                                        let value = e.target.value.replace(/\D/g, '');
                                                        if (value.length <= 11) {
                                                            if (value.length > 10) {
                                                                value = value.replace(/^(\d{2})(\d{1})(\d{4})(\d{4})$/, '($1)$2 $3-$4');
                                                            } else if (value.length > 6) {
                                                                value = value.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1)$2-$3');
                                                            } else if (value.length > 2) {
                                                                value = value.replace(/^(\d{2})(\d{0,5})/, '($1)$2');
                                                            }
                                                        }
                                                        e.target.value = value;
                                                    });
                                                }
                                                
                                                var tel1 = document.getElementById('telefone');
                                                var tel2 = document.getElementById('telefone2');
                                                var tel3 = document.getElementById('telefone3');
                                                
                                                if (tel1) formatarTelefone(tel1);
                                                if (tel2) formatarTelefone(tel2);
                                                if (tel3) formatarTelefone(tel3);
                                            })();
                                            </script>

                                            <div>
                                                <label for="email">Email</label>
                                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($cliente['email']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="senha_email">Senha do E-mail</label>
                                                <input type="text" id="senha_email" name="senha_email" value="<?php echo htmlspecialchars($cliente['senha_email']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="senha_meuinss">Senha Gov (MeuINSS)</label>
                                                <input type="text" id="senha_meuinss" name="senha_meuinss" value="<?php echo htmlspecialchars($cliente['senha_meuinss']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="data_avaliacao_social">Data da Avaliação Social</label>
                                                <input type="date" id="data_avaliacao_social" name="data_avaliacao_social" value="<?php echo htmlspecialchars($cliente['data_avaliacao_social']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="hora_avaliacao_social">Hora da Avaliação Social</label>
                                                <input type="time" id="hora_avaliacao_social" name="hora_avaliacao_social" value="<?php echo htmlspecialchars(formatar_hora($cliente['hora_avaliacao_social'])); ?>" class="input-field">
                                            </div>

                                            <div class="full">
                                                <label for="endereco_avaliacao_social">Endereço da Avaliação Social</label>
                                                <input type="text" id="endereco_avaliacao_social" name="endereco_avaliacao_social" value="<?php echo htmlspecialchars($cliente['endereco_avaliacao_social']); ?>" class="input-field">
                                            </div>

                                            <div style="display: flex !important; align-items: center !important; padding-top: 25px !important;">
                                                <label style="display: flex !important; align-items: center !important; margin: 0 !important; white-space: nowrap !important;">Realizado Avaliação Social? <input type="checkbox" name="realizado_a_s" value="1" <?php echo ($cliente['realizado_a_s']==1)?'checked':''; ?> style="width: auto !important; height: auto !important; margin-left: 8px !important; margin-top: 0 !important; margin-bottom: 0 !important;"></label>
                                            </div>

                                            <div>
                                                <label for="data_pericia">Data da Perícia</label>
                                                <input type="date" id="data_pericia" name="data_pericia" value="<?php echo htmlspecialchars($cliente['data_pericia']); ?>" class="input-field">
                                            </div>

                                            <div>
                                                <label for="hora_pericia">Hora da Perícia</label>
                                                <input type="time" id="hora_pericia" name="hora_pericia" value="<?php echo htmlspecialchars(formatar_hora($cliente['hora_pericia'])); ?>" class="input-field">
                                            </div>

                                            <div class="full">
                                                <label for="endereco_pericia">Endereço da Perícia</label>
                                                <input type="text" id="endereco_pericia" name="endereco_pericia" value="<?php echo htmlspecialchars($cliente['endereco_pericia']); ?>" class="input-field">
                                            </div>

                                            <div style="display: flex !important; align-items: center !important; padding-top: 25px !important;">
                                                <label style="display: flex !important; align-items: center !important; margin: 0 !important;">Realizado Perícia? <input type="checkbox" name="realizado_pericia" value="1" <?php echo ($cliente['realizado_pericia']==1)?'checked':''; ?> style="width: auto !important; height: auto !important; margin-left: 8px !important; margin-top: 0 !important; margin-bottom: 0 !important;"></label>
                                            </div>

                                            <div style="display: flex !important; align-items: center !important; padding-top: 25px !important;">
                                                <label style="display: flex !important; align-items: center !important; margin: 0 !important;">Contrato Assinado? <input type="checkbox" name="contrato_assinado" value="1" <?php echo ($cliente['contrato_assinado']==1)?'checked':''; ?> style="width: auto !important; height: auto !important; margin-left: 8px !important; margin-top: 0 !important; margin-bottom: 0 !important;"></label>
                                            </div>

                                            <div class="form-group full-width">
                                            <label>Observação</label>
                                            <textarea id="observacoes" name="observacao" placeholder="Digite suas observações aqui..." class="input-field textarea" rows="4"><?php echo htmlspecialchars($cliente['observacao'] ?? ''); ?></textarea>
                                            </div>



                                </form>

            <?php else: ?>

            <p class="mensagem-erro">❌ Cliente não encontrado.</p>

            <?php endif; ?>


        </main>
        <script>
        // Função para calcular idade
        function calcularIdade() {
            const dataNascimento = document.getElementById('data_nascimento').value;
            const idadeInput = document.getElementById('idade');
            
            if (dataNascimento && idadeInput) {
                const hoje = new Date();
                const nascimento = new Date(dataNascimento);
                let idade = hoje.getFullYear() - nascimento.getFullYear();
                const mes = hoje.getMonth() - nascimento.getMonth();
                
                if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                    idade--;
                }
                
                idadeInput.value = idade >= 0 ? idade : '';
            }
        }

        // Formatar CPF e Telefone automaticamente ao digitar
        document.addEventListener('DOMContentLoaded', function() {
            // Calcular idade ao mudar data de nascimento
            const dataNascimentoInput = document.getElementById('data_nascimento');
            if (dataNascimentoInput) {
                dataNascimentoInput.addEventListener('change', calcularIdade);
                // Calcular idade ao carregar a página se já houver data
                if (dataNascimentoInput.value) {
                    calcularIdade();
                }
            }

            const cpfInput = document.getElementById('cpf');
            if (cpfInput) {
                cpfInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length <= 11) {
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    }
                    e.target.value = value;
                });
            }

            const telefoneInput = document.getElementById('telefone');
            if (telefoneInput) {
                telefoneInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length <= 11) {
                        if (value.length > 2) {
                            value = '(' + value.substring(0, 2) + ')' + value.substring(2);
                        }
                        if (value.length > 8) {
                            value = value.substring(0, 4) + value.substring(4, 5) + ' ' + value.substring(5);
                        }
                        if (value.length > 11) {
                            value = value.substring(0, 11) + '-' + value.substring(11);
                        }
                    }
                    e.target.value = value;
                });
            }
        });
        </script>

        <footer class="footer">
            <p>Dioleno N. Silva - Todos os direitos reservados</p>
        </footer>
    </body>
</html>