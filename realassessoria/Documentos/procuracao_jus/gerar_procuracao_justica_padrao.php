<?php
require_once dirname(__DIR__) . '/documentos_bootstrap.php';
include 'verificar_permissao.php';

$autoloadPath = APP_ROOT_DIR . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Erro: Dompdf nao encontrado no servidor. Envie a pasta vendor completa para public_html/vendor.');
}
require_once $autoloadPath;

if (!class_exists('Dompdf\\Dompdf') || !class_exists('Dompdf\\Options')) {
    die('Erro: Dompdf instalado incorretamente no servidor.');
}

include 'conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function stmt_fetch_first_assoc_compat($stmt) {
    if (method_exists($stmt, 'get_result')) {
        $result = @$stmt->get_result();
        if ($result !== false) {
            $row = $result->fetch_assoc();
            return $row ?: null;
        }
    }

    $meta = $stmt->result_metadata();
    if ($meta === false) {
        return null;
    }

    $fields = array();
    $rowData = array();
    $bindResult = array();

    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
        $rowData[$field->name] = null;
        $bindResult[] = &$rowData[$field->name];
    }

    call_user_func_array(array($stmt, 'bind_result'), $bindResult);

    if ($stmt->fetch()) {
        $current = array();
        foreach ($fields as $name) {
            $current[$name] = $rowData[$name];
        }
        return $current;
    }

    return null;
}

function formatar_data_brasil_compat($data) {
    if (empty($data) || $data === '0000-00-00') {
        return 'Nao Informada';
    }

    if (strlen($data) === 10 && strpos($data, '-') !== false) {
        return implode('/', array_reverse(explode('-', $data)));
    }

    return $data;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Erro: ID do cliente nao especificado.');
}

$cliente_id = (int) $_GET['id'];

$cliente = null;
$sql = 'SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email FROM clientes WHERE id = ?';

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $cliente = stmt_fetch_first_assoc_compat($stmt);
    $stmt->close();
} else {
    error_log('Erro DB [' . basename(__FILE__) . ']: ' . $conn->error);
    die('Erro interno ao gerar documento. Por favor, tente novamente.');
}

$incapaz = null;
$sql_incapaz = 'SELECT nome, cpf, data_nascimento FROM incapazes WHERE cliente_id = ? ORDER BY id DESC LIMIT 1';

if ($stmt_incapaz = $conn->prepare($sql_incapaz)) {
    $stmt_incapaz->bind_param('i', $cliente_id);
    $stmt_incapaz->execute();
    $incapaz = stmt_fetch_first_assoc_compat($stmt_incapaz);
    $stmt_incapaz->close();
}

$conn->close();

if (!$cliente) {
    die('Erro: Cliente nao encontrado.');
}

$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$nacionalidade_cliente = htmlspecialchars(mb_convert_case($cliente['nacionalidade'] ?: 'Nao Informada', MB_CASE_TITLE, 'UTF-8'));
$profissao_cliente = htmlspecialchars(mb_convert_case($cliente['profissao'] ?: 'Nao Informada', MB_CASE_TITLE, 'UTF-8'));
$estado_civil_cliente = htmlspecialchars(mb_convert_case($cliente['estado_civil'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
$rg_cliente = htmlspecialchars($cliente['rg'] ?: 'Nao Informado');
$cpf_cliente = htmlspecialchars($cliente['cpf'] ?: 'Nao Informado');
$endereco_cliente = htmlspecialchars(mb_convert_case($cliente['endereco'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
$cidade_cliente = htmlspecialchars(mb_convert_case($cliente['cidade'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
$uf_cliente = htmlspecialchars(strtoupper($cliente['uf'] ?: 'Nao Informado'));
$telefone_cliente = htmlspecialchars($cliente['telefone'] ?: 'Nao Informado');
$email_cliente = htmlspecialchars($cliente['email'] ?: 'Nao Informado');
$data_atual = date('d/m/Y');

$incapaz_nome = 'Nao Informado';
$incapaz_cpf = 'Nao Informado';
$incapaz_data_nasc = 'Nao Informada';
if ($incapaz) {
    $incapaz_nome = htmlspecialchars(mb_convert_case($incapaz['nome'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
    $incapaz_cpf = htmlspecialchars($incapaz['cpf'] ?: 'Nao Informado');
    $incapaz_data_nasc = htmlspecialchars(formatar_data_brasil_compat($incapaz['data_nascimento'] ?: ''));
}

$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Procuração Justiça Incapaz</title>
    <style>
        @page {
            margin: 15mm 20mm;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #222;
            text-align: justify;
        }
        h1 {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin: 0 0 30px 0;
            letter-spacing: 1px;
        }
        .section-title {
            font-weight: bold;
            font-size: 12pt;
            margin-top: 20px;
            margin-bottom: 8px;
        }
        .content {
            margin: 12px 0;
            text-indent: 0;
        }
        .signatures {
            margin-top: 60px;
            page-break-inside: avoid;
        }
        .signature-date {
            text-align: center;
            margin-bottom: 50px;
            font-size: 11pt;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 400px;
            margin: 0 auto 8px auto;
        }
        .signature-name {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <h1>PROCURAÇÃO</h1>

    <div class="section-title">OUTORGANTE:</div>
    <div class="content">
        <strong>{$nome_cliente}</strong>, {$nacionalidade_cliente}, {$estado_civil_cliente}, {$profissao_cliente},
        portador(a) do documento de identidade nº {$rg_cliente}, inscrito(a) no CPF/MF sob o nº {$cpf_cliente},
        residente e domiciliado(a) em {$endereco_cliente}, {$cidade_cliente} - {$uf_cliente}.
    </div>

    <div class="section-title">OUTORGADOS:</div>
    <div class="content">
        <strong>EDSON SILVA SANTIAGO</strong>, Brasileiro, Casado, Advogado, inscrito na OAB/RR sob o nº 619, <strong>OSTIVALDO MENEZES DO NASCIMENTO JÚNIOR</strong>, Brasileiro, Casado, Advogado, inscrito na OAB/RR sob o nº 1280, ambos com endereço profissional na Rua Professor Agnelo Bitencourt, nº 335, Bairro: Centro, CEP: 69301-430, Boa Vista/RR, tel.: (95) 98118-1380, e e-mail: edsonsilvaadvocacia@hotmail.com, onde deverão receber intimações.
    </div>

    <div class="section-title">PODERES ESPECÍFICOS:</div>
    <div class="content">
        Por meio do presente instrumento particular de mandato, o(a) outorgante nomeia e constitui seus bastante procuradores os advogados acima qualificados, conferindo-lhes poderes específicos para representar o(a) incapaz em juízo ou fora dele, ativa e passivamente, com a cláusula Ad Judicia e Et Extra, em qualquer juízo, instância ou tribunal, inclusive Juizados Especiais, para praticar todos os atos necessários à defesa de seus interesses, podendo, para tanto, propor, contestar, acompanhar, transigir, acordar, discordar, firmar termos de compromisso, substabelecer, e praticar todos os demais atos necessários ao fiel e cabal cumprimento deste mandato.
    </div>

    <div class="signatures">
        <div class="signature-date">Boa Vista/Roraima, {$data_atual}</div>
        <div class="signature-line"></div>
        <div class="signature-name"><strong>{$nome_cliente}</strong><br>CPF/MF: {$cpf_cliente}</div>
    </div>
</body>
</html>
EOT;

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('procuracao_justica_incapaz.pdf', array('Attachment' => false));
exit;
