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

function stmt_fetch_first_assoc_compat_incapaz($stmt) {
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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Erro: ID do cliente nao especificado.');
}

$cliente_id = (int) $_GET['id'];
$cliente = null;
$sql = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email, observacao FROM clientes WHERE id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $cliente = stmt_fetch_first_assoc_compat_incapaz($stmt);
    $stmt->close();
} else {
    die('Erro na preparacao da consulta: ' . $conn->error);
}

$incapaz = null;
$sql_incapaz = "SELECT nome, cpf, data_nascimento FROM incapazes WHERE cliente_id = ? ORDER BY id DESC LIMIT 1";
if ($stmt_incapaz = $conn->prepare($sql_incapaz)) {
    $stmt_incapaz->bind_param('i', $cliente_id);
    $stmt_incapaz->execute();
    $incapaz = stmt_fetch_first_assoc_compat_incapaz($stmt_incapaz);
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

$incapaz_nome = 'Nao Informado';
$incapaz_cpf = 'Nao Informado';
$incapaz_data_nasc = 'Nao Informada';
if ($incapaz) {
    $incapaz_nome = htmlspecialchars(mb_convert_case($incapaz['nome'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
    $incapaz_cpf = htmlspecialchars($incapaz['cpf'] ?: 'Nao Informado');
    $incapaz_data_nasc_raw = $incapaz['data_nascimento'] ?: '';
    if ($incapaz_data_nasc_raw !== '' && strlen($incapaz_data_nasc_raw) === 10 && strpos($incapaz_data_nasc_raw, '-') !== false) {
        $incapaz_data_nasc = htmlspecialchars(implode('/', array_reverse(explode('-', $incapaz_data_nasc_raw))));
    }
}

$data_atual = date('d/m/Y');

$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Procuração</title>
    <style>
        @page {
            size: A4;
            margin: 4mm 20mm;
        }
        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 8.9pt;
            line-height: 1.22;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            background: #f3f6fa;
            text-align: justify;
        }
        .documento {
            border: 1px solid #c6d2e0;
            border-radius: 10px;
            background: #ffffff;
            page-break-inside: avoid;
        }
        .header {
            margin-bottom: 8px;
            padding: 9px 11px 8px 11px;
            border-bottom: 2px solid #0f3b69;
            background: #0f3b69;
            border-radius: 10px 10px 0 0;
            text-align: left;
        }
        h1 {
            margin: 0;
            font-size: 15.5pt;
            letter-spacing: 0.9px;
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
            text-align: left;
        }
        .miolo {
            padding: 8px 9px 10px 9px;
        }
        .section-title {
            background: #0f3b69;
            color: #ffffff;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 7.9pt;
            font-weight: 700;
            letter-spacing: 0.45px;
            margin: 8px 0 5px 0;
            display: inline-block;
            text-transform: uppercase;
        }
        .content {
            background: #f8fbff;
            padding: 8px 9px;
            margin: 7px 0;
            border: 1px solid #d9e4ef;
            border-left: 4px solid #0f3b69;
            border-radius: 8px;
            text-indent: 0;
        }
        .signatures {
            margin-top: 9px;
            page-break-inside: avoid;
            border-top: 1px solid #d9e4ef;
            padding-top: 8px;
        }
        .signature-date {
            text-align: center;
            margin-bottom: 12px;
            font-size: 8.5pt;
            color: #334155;
        }
        .signature-line {
            border-top: 1px solid #0f3b69;
            width: 300px;
            margin: 22px auto 4px auto;
        }
        .signature-name {
            text-align: center;
            font-size: 8.4pt;
            margin-top: 2px;
            color: #0b2f57;
            font-weight: 700;
        }
        .footer-bar {
            position: fixed;
            left: 20mm;
            right: 20mm;
            bottom: 4mm;
            height: 6px;
            background: #0f3b69;
            border-radius: 3px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="documento">
        <div class="header">
            <h1>PROCURAÇÃO</h1>
        </div>

        <div class="miolo">

        <div class="section-title">OUTORGANTE:</div>
        <div class="content">
            <strong>$nome_cliente</strong>, $nacionalidade_cliente, $profissao_cliente, $estado_civil_cliente,
            portador(a) do documento de identidade nº $rg_cliente, portador(a) do CPF nº $cpf_cliente,
            residente e domiciliado(a) em $endereco_cliente, $cidade_cliente - $uf_cliente,
            telefone para contato $telefone_cliente, e-mail: $email_cliente,
            neste ato, na qualidade de representante legal do(a) incapaz <strong>$incapaz_nome</strong>,
            inscrito(a) no CPF sob o nº $incapaz_cpf, nascido(a) em $incapaz_data_nasc.
        </div>

        <div class="section-title">OUTORGADOS:</div>
        <div class="content">
            Real Assessoria Previdenciaria, CNPJ: 13.244.474/0001-20, Rua M, nº 65, Nova Cidade,
            CEP: 69.097-030, Manaus - AM, neste ato representada por Dioleno Nobrega Silva,
            brasileiro, casado, empresario, CPF: 629.880.852-34, telefone: (92) 99129-0577,
            e-mail: dioleno.nobrega@gmail.com.
        </div>

        <div class="section-title">PODERES ESPECÍFICOS:</div>
        <div class="content">
            A quem confere os poderes para representa-lo perante o INSS - Instituto Nacional do Seguro Social,
            podendo receber beneficios, interpor recursos as instancias superiores, receber mensalidades e quantias devidas,
            assinar recibos, fazer recadastramentos, bem como representa-lo junto a instituicao bancaria que recolhe o referido
            beneficio, podendo, para tanto, assinar documentos, atualizar dados cadastrais, alegar e prestar declaracoes e
            informacoes, solicitar e retirar senha e cartao magnetico, enfim, praticar e recorrer a todos os meios legais
            necessarios ao fiel cumprimento do presente mandato.
        </div>

        <div class="signatures">
            <div class="signature-date">
                $cidade_cliente - $uf_cliente, $data_atual
            </div>

            <div class="signature-line"></div>
            <div class="signature-name">
                <strong>$nome_cliente</strong><br>
                CPF/MF: $cpf_cliente
            </div>
        </div>
        </div>
    </div>

    <div class="footer-bar"></div>
</body>
</html>
EOT;

try {
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $nome_arquivo = 'Procuracao_Incapaz_' . str_replace(' ', '_', $nome_cliente) . '.pdf';
    $dompdf->stream($nome_arquivo, array('Attachment' => 1));
} catch (Throwable $e) {
    error_log('Erro ao gerar procuracao incapaz em PDF (Dompdf): ' . $e->getMessage());
    die('Erro ao gerar procuracao incapaz em PDF: ' . $e->getMessage());
}

exit(0);
?>