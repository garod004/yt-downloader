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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Erro: ID do cliente n+ao especificado.');
}

$cliente_id = (int) $_GET['id'];
$cliente = null;
$sql = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email, observacao FROM clientes WHERE id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $result = stmt_get_result($stmt);

    if ($result->num_rows === 1) {
        $cliente = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    die('Erro na preparacao da consulta: ' . $conn->error);
}

$conn->query("CREATE TABLE IF NOT EXISTS a_rogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    identidade VARCHAR(100) NULL,
    cpf VARCHAR(20) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_a_rogo_cliente_id (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$a_rogo = null;
$sql_a_rogo = "SELECT nome, identidade, cpf FROM a_rogo WHERE cliente_id = ? ORDER BY id DESC LIMIT 1";
if ($stmt_a_rogo = $conn->prepare($sql_a_rogo)) {
    $stmt_a_rogo->bind_param('i', $cliente_id);
    $stmt_a_rogo->execute();
    $result_a_rogo = stmt_get_result($stmt_a_rogo);
    if ($result_a_rogo->num_rows === 1) {
        $a_rogo = $result_a_rogo->fetch_assoc();
    }
    $stmt_a_rogo->close();
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
$observacao_cliente = nl2br(htmlspecialchars($cliente['observacao'] ?: 'Nenhuma observacao.'));

$a_rogo_nome = 'Nao Informado';
$a_rogo_identidade = 'Nao Informado';
$a_rogo_cpf = 'Nao Informado';
if ($a_rogo) {
    $a_rogo_nome = htmlspecialchars(mb_convert_case($a_rogo['nome'] ?: 'Nao Informado', MB_CASE_TITLE, 'UTF-8'));
    $a_rogo_identidade = htmlspecialchars($a_rogo['identidade'] ?: 'Nao Informado');
    $a_rogo_cpf = htmlspecialchars($a_rogo['cpf'] ?: 'Nao Informado');
}

$data_atual = date('d/m/Y');

$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Prestacao de Servico - A Rogo</title>
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
        .info-row {
            margin-bottom: 7px;
            border: 1px solid #d9e4ef;
            border-radius: 8px;
            padding: 7px 8px;
            background: #f8fbff;
        }
        .info-label {
            color: #1a1a1a;
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
        .content-box p {
            margin: 0 0 5px 0;
            padding-left: 6px;
            border-left: 2px solid #d3dfec;
        }
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
            <p>PRESTACAO DE SERVICO</p>
        </div>
    </div>

    <div class="section-title">CONTRATANTE:</div>
    <div class="info-row">
        <div class="info-label"><strong>$nome_cliente</strong>, $nacionalidade_cliente, $profissao_cliente, $estado_civil_cliente,
        Portador do documento de identidade No: $rg_cliente, portador(a) do CPF no $cpf_cliente, residente e domiciliado(a) na
        $endereco_cliente, $cidade_cliente - $uf_cliente, telefone para contato $telefone_cliente, e-mail: $email_cliente, neste ato,
        por impossibilidade de assinatura, firmando o presente instrumento a rogo por intermédio de <strong>$a_rogo_nome</strong>,
        portador(a) da identidade no $a_rogo_identidade e inscrito(a) no CPF sob o no $a_rogo_cpf.
        </div>
    </div>

    <div class="section-title">CONTRATADO:</div>
    <div class="content-box">
        Real Assessoria Previdenciaria, CNPJ: 13.244.474/0001-20, Rua M, No 65, Nova Cidade,
        CEP:69.097-030, Manaus - AM, neste ato representada por Dioleno Nobrega Silva, (92)99129-
        0577, E-mail: dioleno.nobrega@gmail.com.
    </div>

    <div class="content-box">
        <p>As partes acima qualificadas celebram, de maneira justa e acordada, o presente CONTRATO DE HONORARIOS POR SERVICOS PRESTADOS, ficando desde ja aceito que o referido instrumento sera regido pelas condicoes previstas em Lei, devidamente especificadas pelas clausulas e condicoes a seguir descritas.</p>
        <p>CLAUSULA 1a - O presente instrumento tem como objetivo a prestacao de servicos de assessoria a serem realizados pelo Contratado para CONCESSAO/RESTABELECIMENTO DE BENEFICIO PREVIDENCIARIO em face do INSS, na representacao e defesa dos interesses do(a) Contratante, sendo realizado na via administrativa ou judicial, em qualquer juizo e/ou instancia, apresentar e opor acoes, bem como de interpor os recursos necessarios e competentes para garantir a protecao e o exercicio dos seus direitos.</p>
        <p>CLAUSULA 2a - O Contratante assume a obrigatoriedade de efetuar o pagamento pelos servicos prestados ao Contratado no valor de <strong>30% (trinta por cento) sobre os valores retroativos (parcelas vencidas) mais 15 parcelas de R$ 500,00 (quinhentos reais)</strong> no ato da implantacao do beneficio. Caso nao haja cumprimento no acordo de pagamento, a contratante pagara a titulo de juros um percentual de 3% sobre cada parcela.</p>
        <p>CLAUSULA 3a - Deixando o Contratante de imotivadamente ter o patrocinio destes causidicos, ora Contratado, nao a desobriga ao pagamento dos honorarios ajustados integralmente.</p>
        <p>Paragrafo unico - Em caso de desistencia ou qualquer ato de desidia do Contratante como deixar de comparecer em qualquer ato do processo que gera a extincao ou a improcedencia da acao ou de alguns dos pedidos da demanda, sera aplicada uma multa de R$ 2.000,00 (dois mil reais).</p>
        <p>CLAUSULA 5a - Caso haja morte ou incapacidade civil em ocorrencia do contratado, sua advogada constituida como representante legal recebera os honorarios na proporcao do trabalho realizado.</p>
        <p>CLAUSULA 6a - As partes elegem o foro da Comarca de Manaus - AM.</p>
        <p>Por estarem assim justos e contratados, firmam o presente CONTRATO DE HONORARIOS POR SERVICOS PRESTADOS em duas vias de igual teor e forma, para que o mesmo, nos seus devidos fins de direito, produza seus juridicos e legais efeitos, juntamente com as 02 (duas) testemunhas, igualmente subscritas, identificadas e assinadas.</p>
    </div>

    <div class="signatures">
        <div class="signature-date">
            $cidade_cliente - $uf_cliente, $data_atual
        </div>

        <div class="signature-line"></div>
        <div class="signature-name">Assinatura do(a) Contratante
        <p><strong>$nome_cliente</strong></p>
        </div>

        <div class="signature-line"></div>
        <div class="signature-name">Assinatura de quem firma a rogo, a pedido do(a) Contratante
        <p><strong>$a_rogo_nome</strong></p>
        </div>

        <div class="signature-line"></div>
        <div class="signature-name">Assinatura do Contratado</div>
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

    $nome_arquivo = 'Contrato_Honorarios_Padrao_A_Rogo_' . str_replace(' ', '_', $nome_cliente) . '.pdf';
    $dompdf->stream($nome_arquivo, array('Attachment' => 1));
} catch (Throwable $e) {
    error_log('Erro ao gerar contrato A ROGO em PDF (Dompdf): ' . $e->getMessage());
    die('Erro ao gerar contrato A ROGO em PDF: ' . $e->getMessage());
}

exit(0);
?>
