<?php
ob_start();
session_start();

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    ob_end_clean();
    http_response_code(500);
    exit('<p style="color:red;">Execute <code>composer install</code> no servidor.</p>');
}
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acesso negado.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    exit('Token inválido.');
}

$modelo_id     = (int)($_POST['modelo_id']     ?? 0);
$cliente_id    = (int)($_POST['cliente_id']    ?? 0);
$advogado_1_id = (int)($_POST['advogado_1_id'] ?? 0);
$advogado_2_id = (int)($_POST['advogado_2_id'] ?? 0);
$advogado_3_id = (int)($_POST['advogado_3_id'] ?? 0);
$filho_id      = (int)($_POST['filho_id']      ?? 0);
$incapaz_id    = (int)($_POST['incapaz_id']    ?? 0);
$a_rogo_id     = (int)($_POST['a_rogo_id']     ?? 0);

if ($modelo_id <= 0 || $cliente_id <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo '<p style="color:red;">Parâmetros inválidos.</p>';
    echo '<p><a href="javascript:history.back()">Voltar</a></p>';
    exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/log_utils.php';
require_once __DIR__ . '/advogados_utils.php';
require_once __DIR__ . '/src/ModeloSubstituicao.php';

try {
    $stmtM = $conn->prepare("SELECT nome, conteudo FROM modelos_documentos WHERE id = ? AND ativo = 1 LIMIT 1");
    $stmtM->bind_param('i', $modelo_id);
    $stmtM->execute();
    $modelo = stmt_get_result($stmtM)->fetch_assoc();
    $stmtM->close();
    if (!$modelo) throw new Exception('Modelo não encontrado.');

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

    // Busca até 3 advogados em uma única query
    garantirTabelaAdvogados($conn);
    $advogados = [[], [], []];
    $adv_ids = [$advogado_1_id, $advogado_2_id, $advogado_3_id];
    $adv_ids_filtrados = array_filter($adv_ids, fn($id) => $id > 0);
    if (!empty($adv_ids_filtrados)) {
        $ph = implode(',', array_fill(0, count($adv_ids_filtrados), '?'));
        $sA = $conn->prepare("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email FROM advogados WHERE id IN ($ph)");
        $sA->bind_param(str_repeat('i', count($adv_ids_filtrados)), ...$adv_ids_filtrados);
        $sA->execute();
        $advByid = [];
        $rA = stmt_get_result($sA);
        while ($row = $rA->fetch_assoc()) $advByid[$row['id']] = $row;
        $sA->close();
        foreach ($adv_ids as $idx => $id) {
            if ($id > 0 && isset($advByid[$id])) $advogados[$idx] = $advByid[$id];
        }
    }

    $filho = [];
    if ($filho_id > 0) {
        $s = $conn->prepare("SELECT nome, cpf, data_nascimento FROM filhos_menores WHERE id = ? AND cliente_id = ? LIMIT 1");
        $s->bind_param('ii', $filho_id, $cliente_id);
        $s->execute();
        $filho = stmt_get_result($s)->fetch_assoc() ?? [];
        $s->close();
    }

    $incapaz = [];
    if ($incapaz_id > 0) {
        $s = $conn->prepare("SELECT nome, cpf, data_nascimento FROM incapazes WHERE id = ? AND cliente_id = ? LIMIT 1");
        $s->bind_param('ii', $incapaz_id, $cliente_id);
        $s->execute();
        $incapaz = stmt_get_result($s)->fetch_assoc() ?? [];
        $s->close();
    }

    $aRogo = [];
    if ($a_rogo_id > 0) {
        $s = $conn->prepare("SELECT nome, identidade, cpf FROM a_rogo WHERE id = ? AND cliente_id = ? LIMIT 1");
        $s->bind_param('ii', $a_rogo_id, $cliente_id);
        $s->execute();
        $aRogo = stmt_get_result($s)->fetch_assoc() ?? [];
        $s->close();
    }

    // Config de empresa cacheada na sessão (muda raramente)
    if (empty($_SESSION['empresa_config'])) {
        $empresa = [];
        $chaves = ['empresa_nome', 'empresa_cnpj', 'empresa_fone', 'empresa_email',
                   'empresa_proprietarios', 'empresa_endereco', 'empresa_cidade'];
        $placeholders = implode(',', array_fill(0, count($chaves), '?'));
        $stmtE = $conn->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ($placeholders)");
        $stmtE->bind_param(str_repeat('s', count($chaves)), ...$chaves);
        $stmtE->execute();
        $resE = stmt_get_result($stmtE);
        while ($rowE = $resE->fetch_assoc()) $empresa[$rowE['chave']] = $rowE['valor'];
        $stmtE->close();
        $_SESSION['empresa_config'] = $empresa;
    } else {
        $empresa = $_SESSION['empresa_config'];
    }

    $mapa = ModeloSubstituicao::construirMapa(
        $cliente, $empresa,
        $_SESSION['usuario_nome'] ?? '',
        $advogados, $filho, $incapaz, $aRogo
    );
    $conteudo_final = ModeloSubstituicao::substituir($modelo['conteudo'], $mapa);

    $nome_pdf = preg_replace('/[^a-zA-Z0-9_-]/', '_', $modelo['nome']) . '_' . date('Ymd_His') . '.pdf';
    $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
        @page { margin: 0; }
        body { margin:0; padding:0; background:#dce4ec; font-family:\'DejaVu Sans\',sans-serif; font-size:9pt; color:#1a1a1a; line-height:1.45; }
        p { margin:0; }
        table { border-collapse:collapse; }
        td { vertical-align:top; padding:0; }
        strong, b { font-weight:bold; }
    </style></head><body>' . $conteudo_final . '</body></html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    registrar_log(
        $conn,
        $_SESSION['usuario_nome'] ?? 'Sistema',
        'GERAR_PDF',
        "PDF '{$modelo['nome']}' gerado para cliente ID $cliente_id ({$cliente['nome']}).",
        $cliente_id,
        $cliente['nome']
    );
    $conn->close();

    ob_end_clean();
    $dompdf->stream($nome_pdf, ['Attachment' => false]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo '<p style="color:red;font-family:Arial;padding:20px;">Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p style="font-family:Arial;padding:0 20px;"><a href="javascript:history.back()">← Voltar</a></p>';
}
