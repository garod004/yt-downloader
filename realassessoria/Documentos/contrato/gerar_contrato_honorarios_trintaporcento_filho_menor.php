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

function formatar_data_brasil($data) {
    if (empty($data) || $data === '0000-00-00') {
        return 'Nao Informada';
    }

    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }

    return $data;
}

function exibir_mensagem_documento($mensagem) {
    http_response_code(422);
    echo '<!DOCTYPE html>';
    echo '<html lang="pt-BR"><head><meta charset="UTF-8"><title>Documento indisponivel</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f8fafc;color:#1f2937;margin:0;padding:32px;display:flex;justify-content:center}';
    echo '.aviso{max-width:680px;background:#fff;border:1px solid #fecaca;border-left:6px solid #dc2626;border-radius:12px;padding:24px;box-shadow:0 10px 30px rgba(15,23,42,.08)}';
    echo 'h1{margin:0 0 12px 0;font-size:22px;color:#991b1b}p{margin:0;font-size:15px;line-height:1.5}</style></head><body>';
    echo '<div class="aviso"><h1>Não foi possível gerar o documento</h1><p>' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p></div>';
    echo '</body></html>';
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Erro: ID do cliente nao especificado.');
}

$cliente_id = (int) $_GET['id'];

$sql_cliente = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email
                FROM clientes
                WHERE id = ?
                LIMIT 1";
$cliente = null;
if ($stmt_cliente = $conn->prepare($sql_cliente)) {
    $stmt_cliente->bind_param('i', $cliente_id);
    $stmt_cliente->execute();
    $cliente = stmt_fetch_first_assoc_compat($stmt_cliente);
    $stmt_cliente->close();
} else {
    die('Erro na preparacao da consulta do cliente: ' . $conn->error);
}

$filho_menor = null;
$sql_filho = "SELECT nome, cpf, data_nascimento
              FROM filhos_menores
              WHERE cliente_id = ?
              ORDER BY id DESC
              LIMIT 1";
if ($stmt_filho = $conn->prepare($sql_filho)) {
    $stmt_filho->bind_param('i', $cliente_id);
    $stmt_filho->execute();
    $filho_menor = stmt_fetch_first_assoc_compat($stmt_filho);
    $stmt_filho->close();
} else {
    die('Erro na preparacao da consulta do filho menor: ' . $conn->error);
}

$conn->close();

if (!$cliente) {
    die('Erro: Cliente nao encontrado.');
}

if (!$filho_menor) {
    exibir_mensagem_documento('Nenhum filho menor cadastrado para este cliente. Cadastre o filho menor na ficha do cliente e tente novamente.');
}

$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
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

$filho_nome = htmlspecialchars(mb_convert_case($filho_menor['nome'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
$filho_cpf = htmlspecialchars($filho_menor['cpf'] ?: 'Nao Informado');
$filho_data_nasc = htmlspecialchars(formatar_data_brasil($filho_menor['data_nascimento'] ?? ''));

$data_atual = date('d/m/Y');

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Honorarios - Filho Menor 30%</title>
    <style>
        @page {
            size: A4;
            margin: 4mm 20mm;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 8.9pt;
            line-height: 1.22;
            color: #1a1a1a;
            background: #f3f6fa;
        }
        .header {
            margin-bottom: 8px;
            padding: 9px 11px 8px 11px;
            border-bottom: 2px solid #0f3b69;
            background: #0f3b69;
            border-radius: 10px 10px 0 0;
        }
        .header-left h1 {
            font-size: 15.5pt;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            letter-spacing: 0.9px;
            text-transform: uppercase;
        }
        .header-left p {
            font-size: 7.8pt;
            color: #dbeafe;
            margin: 2px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
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
        .content-box {
            background: #f8fbff;
            padding: 8px 9px;
            margin: 7px 0;
            border: 1px solid #d9e4ef;
            border-left: 4px solid #0f3b69;
            border-radius: 8px;
            text-align: justify;
            line-height: 1.22;
        }
        .clausula,
        .content-box p {
            margin: 0 0 5px 0;
            padding-left: 6px;
            border-left: 2px solid #d3dfec;
        }
        .clausula:last-child,
        .content-box p:last-child {
            margin-bottom: 0;
        }
        .signatures,
        .assinaturas {
            margin-top: 9px;
            page-break-inside: avoid;
            border-top: 1px solid #d9e4ef;
            padding-top: 8px;
        }
        .signature-date,
        .data-local,
        .local-data {
            text-align: center;
            margin-bottom: 12px;
            font-size: 8.5pt;
            color: #334155;
        }
        .signature-line,
        .linha {
            border-top: 1px solid #0f3b69;
            width: 300px;
            margin: 22px auto 4px auto;
        }
        .signature-name,
        .assinatura {
            text-align: center;
            font-weight: 700;
            font-size: 8.4pt;
            color: #0b2f57;
            margin-top: 2px;
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
    <div class="header">
        <div class="header-left">
            <h1>CONTRATO</h1>
            <p>Prestacao de Servico</p>
        </div>
    </div>

    <div class="section-title">Contratante</div>
    <div class="content-box">
        <strong>$nome_cliente</strong>, $nacionalidade_cliente, $profissao_cliente, $estado_civil_cliente, portador(a) do RG nº $rg_cliente e inscrito(a) no CPF sob o nº $cpf_cliente, residente e domiciliado(a) em $endereco_cliente, $cidade_cliente - $uf_cliente, telefone $telefone_cliente, e-mail $email_cliente, neste ato na qualidade de representante legal do(a) filho(a) menor <strong>$filho_nome</strong>, inscrito(a) no CPF sob o nº $filho_cpf, nascido(a) em $filho_data_nasc.
    </div>

    <div class="section-title">Contratado</div>
    <div class="content-box">
        REAL ASSESSORIA PREVIDENCIARIA, CNPJ nº 13.244.474/0001-20, com endereco na Rua M, nº 65, Nova Cidade, CEP 69.097-030, Manaus - AM, neste ato representada por Dioleno Nobrega Silva, telefone (92) 99129-0577, e-mail dioleno.nobrega@gmail.com.
    </div>

    <div class="content-box">
        <div class="clausula">As partes acima qualificadas celebram o presente <strong>CONTRATO DE HONORARIOS POR SERVICOS PRESTADOS</strong>, que sera regido pelas clausulas e condicoes abaixo.</div>

        <div class="clausula"><strong>Clausula 1ª - Do objeto.</strong> O presente instrumento tem por objeto a prestacao de servicos de assessoria para concessao e/ou restabelecimento de beneficio previdenciario perante o INSS, inclusive na via administrativa ou judicial, abrangendo os atos e recursos necessarios a defesa dos interesses do(a) CONTRATANTE e do(a) menor representado(a).</div>

        <div class="clausula"><strong>Clausula 2ª - Dos honorarios.</strong> Pelos servicos prestados, o(a) CONTRATANTE pagara ao CONTRATADO o equivalente a <strong>30% (trinta por cento) sobre o benefício</strong>, a serem iniciadas no ato da implantacao do beneficio.</div>

        <div class="clausula"><strong>Clausula 3ª - Do inadimplemento.</strong> Em caso de descumprimento do acordo de pagamento, incidira juros de 3% (tres por cento) sobre cada parcela em atraso, sem prejuizo das demais medidas cabiveis.</div>

        <div class="clausula"><strong>Clausula 4ª - Da rescisao ou desistencia.</strong> A revogacao imotivada do patrocinio ou a desistencia injustificada por parte do(a) CONTRATANTE nao o(a) desobriga do pagamento integral dos honorarios contratados.</div>

        <div class="clausula"><strong>Paragrafo unico.</strong> Na hipotese de desistencia, abandono, omissao relevante ou nao comparecimento do(a) CONTRATANTE a atos indispensaveis ao andamento do procedimento, que acarretem extincao, improcedencia do pedido ou prejuizo a demanda, sera devida multa compensatoria de R$ 2.000,00 (dois mil reais).</div>

        <div class="clausula"><strong>Clausula 5ª - Da sucessao dos honorarios.</strong> Em caso de morte ou incapacidade civil do CONTRATADO, os honorarios serão pagos por seus sucessores ou representante legal, na proporcao do trabalho efetivamente realizado.</div>

        <div class="clausula"><strong>Clausula 6ª - Do foro.</strong> Fica eleito o foro da Comarca de Manaus - AM para dirimir quaisquer controversias oriundas deste contrato.</div>

        <div class="clausula">Por estarem justos e contratados, firmam o presente instrumento em duas vias de igual teor e forma, juntamente com duas testemunhas.</div>
    </div>

    <div class="assinaturas">
        <div class="local-data">$cidade_cliente - $uf_cliente, $data_atual</div>

        <div class="linha"></div>
        <div class="assinatura"><strong>$nome_cliente</strong><br>CONTRATANTE</div>

        <div class="linha"></div>
        <div class="assinatura"><strong>REAL ASSESSORIA PREVIDENCIARIA</strong><br>CONTRATADO</div>
    </div>

    <div class="footer-bar"></div>
</body>
</html>
HTML;

try {
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $nome_arquivo = 'Contrato_30_Filho_Menor_' . preg_replace('/\s+/', '_', $nome_cliente) . '.pdf';
    $dompdf->stream($nome_arquivo, array('Attachment' => 1));
} catch (Throwable $e) {
    error_log('Erro ao gerar contrato 30% filho menor em PDF: ' . $e->getMessage());
    die('Erro ao gerar contrato 30% filho menor em PDF: ' . $e->getMessage());
}

exit(0);
?>