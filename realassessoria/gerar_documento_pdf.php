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
        body { margin:0; padding:0; background:#fff; font-family:\'DejaVu Sans\',sans-serif; font-size:10pt; color:#1a1a1a; line-height:1.5; }
        p { margin:0; }
        table { border-collapse:collapse; }
        td, th { vertical-align:top; padding:0; }
        strong, b { font-weight:bold; }

        /* ── Sistema A: Navy (Procurações + Contratos RA) ── */
        .doc-bar     { background:#2c3e50; color:#fff; padding:14px 22px; font-size:15pt; font-weight:bold; letter-spacing:1px; text-align:center; }
        .doc-bar-sub { background:#2c3e50; color:#a8bfcc; padding:3px 22px 13px; font-size:10pt; font-weight:normal; text-align:center; }
        .doc-sec     { background:#34495e; color:#fff; padding:7px 22px; font-size:9.5pt; font-weight:bold; letter-spacing:.4px; }
        .doc-body    { padding:10px 22px 6px; }
        .doc-body p  { margin-bottom:5pt; text-align:justify; }
        .doc-sig     { padding:16px 22px 22px; }
        .lv          { width:100%; }
        .lv td       { padding:2pt 8pt 2pt 0; }
        .lv .lb      { font-weight:bold; white-space:nowrap; width:1%; }

        /* ── Sistema B: Simples (Edson Santiago) ── */
        .plain        { padding:1.8cm 2.2cm; }
        .plain-title  { font-size:13pt; font-weight:bold; text-align:center; margin-bottom:14pt; }
        .plain p      { text-align:justify; margin-bottom:7pt; }
        .plain-sec    { font-size:10pt; font-weight:bold; margin-top:11pt; margin-bottom:3pt; }
        .bank         { width:75%; margin:10pt auto; }
        .bank td      { border:1px solid #999; padding:6pt 8pt; text-align:center; font-size:8.5pt; }

        /* ── Sistema C: Dourado (Declaração Hipossuf. DECLARANTE + Residência) ── */
        .dgold-title   { font-size:24pt; font-weight:bold; padding:20px 22px 2px; }
        .dgold-sub     { font-size:9pt; color:#999; letter-spacing:2px; text-transform:uppercase; padding:0 22px 10px; }
        .dgold-rule    { border:0; border-top:2.5px solid #c8956c; margin:0 22px; }
        .dgold-sec     { background:#ece8e0; color:#5a4530; padding:8px 22px; font-weight:bold; font-size:9.5pt; margin-top:8px; }
        .dgold-body    { border-left:4px solid #c8956c; padding:10px 22px 10px 18px; margin:10px 22px; }
        .dgold-body p  { margin-bottom:5pt; text-align:justify; }
        .dgold-date    { text-align:center; padding:20px 22px 10px; }
        .dgold-sig     { text-align:center; padding:8px 22px; }
        .dgold-sigline { display:block; width:200px; border:0; border-top:1px solid #333; margin:0 auto 5pt; }
        .dgold-footer  { background:#c8956c; height:8px; margin-top:30px; }

        /* ── Sistema D: Acadêmico simples (Declaração art.98 CPC) ── */
        .dsimple            { padding:2cm 2.8cm; }
        .dsimple-title      { font-size:14pt; font-weight:bold; text-align:center; margin-bottom:22pt; }
        .dsimple p          { text-indent:2em; text-align:justify; margin-bottom:8pt; }
        .dsimple-date       { text-align:center; margin:20pt 0; text-indent:0; }
        .dsimple-sigwrap    { text-align:center; margin-top:28pt; }
        .dsimple-sigline    { display:block; width:220px; border:0; border-top:1px solid #333; margin:0 auto 5pt; }

        /* ── Assinaturas compartilhadas ── */
        .sig-table          { width:100%; margin-top:20pt; }
        .sig-table td       { text-align:center; padding:4pt 6pt; }
        .sig-line           { display:block; border:0; border-top:1px solid #333; margin:0 auto 4pt; width:90%; }
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
