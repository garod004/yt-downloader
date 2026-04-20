<?php
require_once dirname(__DIR__) . '/documentos_bootstrap.php';
include 'verificar_permissao.php';
require_once 'vendor/autoload.php';
include 'conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Obter o ID do cliente da URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Erro: ID do cliente não especificado.");
}

$cliente_id = $_GET['id'];
// 2. Buscar os dados do cliente no banco de dados
$cliente = null;
$sql = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email, observacao FROM clientes WHERE id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = stmt_get_result($stmt);

    if ($result->num_rows == 1) {
        $cliente = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    error_log("Erro DB [" . basename(__FILE__) . "]: " . $conn->error); die("Erro interno ao gerar documento. Por favor, tente novamente.");
}
$conn->close();

if (!$cliente) {
    die("Erro: Cliente não encontrado.");
}

// 3. PREPARAR OS DADOS ANTES DO HEREDOC
$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$nacionalidade_cliente = htmlspecialchars(mb_convert_case($cliente['nacionalidade'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));
$profissao_cliente = htmlspecialchars(mb_convert_case($cliente['profissao'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));
$estado_civil_cliente = htmlspecialchars(mb_convert_case($cliente['estado_civil'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$rg_cliente = htmlspecialchars($cliente['rg'] ?: 'Não Informado');
$cpf_cliente = htmlspecialchars($cliente['cpf'] ?: 'Não Informado');
$endereco_cliente = htmlspecialchars(mb_convert_case($cliente['endereco'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$cidade_cliente = htmlspecialchars(mb_convert_case($cliente['cidade'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$uf_cliente = htmlspecialchars(strtoupper($cliente['uf'] ?: 'Não Informado'));
$telefone_cliente = htmlspecialchars($cliente['telefone'] ?: 'Não Informado');
$email_cliente = htmlspecialchars($cliente['email'] ?: 'Não Informado');
$observacao_cliente = nl2br(htmlspecialchars($cliente['observacao'] ?: 'Nenhuma observação.'));

$data_atual = date('d/m/Y');

$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Prestação de Serviço</title>
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

        .info-label,
        .info-value {
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

        .content-box p,
        .clausula {
            margin: 0 0 5px 0;
            padding-left: 6px;
            border-left: 2px solid #d3dfec;
        }

        .content-box p:last-child,
        .clausula:last-child {
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
            <p>PRESTAÇÃO DE SERVIÇO</p>
        </div>
    </div>
    <div class="section-title">CONTRATANTE:</div>
    <div class="info-row">
        <div class="info-label"><strong>$nome_cliente</strong>, $nacionalidade_cliente, $profissao_cliente, $estado_civil_cliente, Portador do documento de identidade Nº: $rg_cliente, portador(a) do CPF nº $cpf_cliente, residente e domiciliado(a) na $endereco_cliente, $cidade_cliente - $uf_cliente, telefone para contato $telefone_cliente, e-mail: $email_cliente.</div>
    </div>
    <div class="section-title">CONTRATADO:</div>
    <div class="content-box">
        Real Assessoria Previdenciária, CNPJ: 13.244.474/0001-20, Rua M, Nº 65, Nova Cidade, CEP:69.097-030, Manaus - AM, neste ato representada por Dioleno Nóbrega Silva, (92)99129-0577, E-mail: dioleno.nobrega@gmail.com.
    </div>
   <div class="content-box">
        <p>As partes acima qualificadas celebram, de maneira justa e acordada, o presente CONTRATO DE HONORARIOS POR SERVICOS PRESTADOS, ficando desde ja aceito que o referido instrumento sera regido pelas condicoes previstas em Lei, devidamente especificadas pelas clausulas e condicoes a seguir descritas.</p>
        <p>CLAUSULA 1º - O presente instrumento tem como objetivo a prestacao de servicos de assessoria a serem realizados pelo Contratado para CONCESSAO/RESTABELECIMENTO DE BENEFICIO PREVIDENCIARIO em face do INSS, na representacao e defesa dos interesses do(a) Contratante, sendo realizado na via administrativa ou judicial, em qualquer juizo e/ou instancia, apresentar e opor acoes, bem como de interpor os recursos necessarios e competentes para garantir a protecao e o exercicio dos seus direitos.</p>
        <p>CLAUSULA 2º - O Contratante assume a obrigatoriedade de efetuar o pagamento pelos servicos prestados ao Contratado no valor de <strong>30% (trinta por cento) sobre os valores retroativos (parcelas vencidas) mais 15 parcelas de R$ 500,00 (quinhentos reais)</strong> no ato da implantacao do beneficio. Caso nao haja cumprimento no acordo de pagamento, a contratante pagara a titulo de juros um percentual de 3% sobre cada parcela.</p>
        <p>CLAUSULA 3º - Deixando o Contratante de imotivadamente ter o patrocinio destes causidicos, ora Contratado, nao a desobriga ao pagamento dos honorarios ajustados integralmente.</p>
        <p>Paragrafo unico - Em caso de desistencia ou qualquer ato de desidia do Contratante como deixar de comparecer em qualquer ato do processo que gera a extincao ou a improcedencia da acao ou de alguns dos pedidos da demanda, sera aplicada uma multa de R$ 2.000,00 (dois mil reais).</p>
        <p>CLAUSULA 4º - Caso haja morte ou incapacidade civil em ocorrencia do contratado, a contratada constituida receberá os honorarios na proporcao do trabalho realizado.</p>
        <p>CLAUSULA 5º - As partes elegem o foro da Comarca de Manaus - AM.</p>
        <p>CLAUSULA 6º - Por estarem assim justos e contratados, firmam o presente CONTRATO DE HONORARIOS POR SERVICOS PRESTADOS em duas vias de igual teor e forma, para que o mesmo, nos seus devidos fins de direito, produza seus juridicos e legais efeitos, juntamente com as 02 (duas) testemunhas, igualmente subscritas, identificadas e assinadas.</p>
    </div>
    <div class="signatures">
        <div class="signature-date">
           $cidade_cliente - $uf_cliente, $data_atual
        </div>
        <div class="signature-line"></div>
        <div class="signature-name">Assinatura Contratante</div>
        <div class="signature-line"></div>
        <div class="signature-name">Assinatura Contratado</div>
    </div>
    <div class="footer-bar"></div>
</body>
</html>
EOT;

$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nome_arquivo = "Contrato_Justica_Padrao_" . str_replace(' ', '_', $nome_cliente) . ".pdf";
$dompdf->stream($nome_arquivo, array("Attachment" => 1));

exit(0);
