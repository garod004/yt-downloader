<?php
require_once dirname(__DIR__) . '/documentos_bootstrap.php';
include 'verificar_permissao.php'; // Verificar permissões
require_once 'vendor/autoload.php'; // Inclui o autoloader do Composer (para Dompdf)
include 'conexao.php';            // Inclui o arquivo de conexão com o banco

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Obter o ID do cliente da URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Erro: ID do cliente não especificado.");
}
$cliente_id = $_GET['id'];

// 2. Buscar os dados do cliente no banco de dados
$cliente = null;
$sql = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, cep FROM clientes WHERE id = ?";

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

// 3. Preparar os dados para o template
$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$nacionalidade_cliente = htmlspecialchars(mb_convert_case($cliente['nacionalidade'] ?: 'Brasileiro', MB_CASE_TITLE, 'UTF-8'));
$profissao_cliente = htmlspecialchars(mb_convert_case($cliente['profissao'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));
$estado_civil_cliente = htmlspecialchars(mb_convert_case($cliente['estado_civil'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$rg_cliente = htmlspecialchars($cliente['rg'] ?: 'Não Informado');
$cpf_cliente = htmlspecialchars($cliente['cpf'] ?: 'Não Informado');
$endereco_cliente = htmlspecialchars(mb_convert_case($cliente['endereco'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$cidade_cliente = htmlspecialchars(mb_convert_case($cliente['cidade'] ?: 'Boa Vista', MB_CASE_TITLE, 'UTF-8'));
$uf_cliente = htmlspecialchars(strtoupper($cliente['uf'] ?: 'RR'));
$cep_cliente = htmlspecialchars($cliente['cep'] ?: 'Não Informado');

// Data atual formatada por extenso
$meses = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
    5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
    9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
];
$dia = date('d');
$mes = $meses[(int)date('m')];
$ano = date('Y');
$data_extenso = "$dia de $mes de $ano";

// --- INÍCIO DO TEMPLATE HTML ---
$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Declaração de Hipossuficiência</title>
    <style>
        @page {
            margin: 40mm 25mm 25mm 25mm;
        }
        body { 
            font-family: 'Times New Roman', serif; 
            margin: 0;
            padding: 0;
            font-size: 12pt;
            line-height: 1.8;
            color: #000;
            text-align: justify;
        }
        .titulo {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 60px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .conteudo {
            text-indent: 60px;
            margin-bottom: 20px;
        }
        .assinatura {
            margin-top: 120px;
            text-align: center;
        }
        .linha-assinatura {
            border-top: 1px solid #000;
            width: 350px;
            margin: 0 auto;
        }
        .nome-assinante {
            margin-top: 5px;
            font-size: 11pt;
        }
        .cpf-assinante {
            margin-top: 2px;
            font-size: 11pt;
        }
        .local-data {
            margin-top: 40px;
            margin-bottom: 80px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="titulo">
        DECLARAÇÃO DE HIPOSSUFICIÊNCIA
    </div>

    <div class="conteudo">
        Sr. <strong>$nome_cliente</strong>, $nacionalidade_cliente, $profissao_cliente, portador da carteira de identidade n° $rg_cliente, 
        e inscrito no CPF/MF sob o nº $cpf_cliente, residente e domiciliado no município de $cidade_cliente, Estado de $uf_cliente, 
        sito a $endereco_cliente, CEP: $cep_cliente. DECLARO que não possuo condições econômicas de arcar com as custas, 
        despesas processuais e honorários advocatícios para ingressar com um processo judicial relativo ao pedido de 
        pagamentos retroativos contra a União, sem prejuízo de meu sustento e de minha família.
    </div>

    <div class="conteudo" style="text-indent: 60px;">
        Nesse sentido, solicito a GRATUIDADE DA JUSTIÇA, com base no art. 98 do CPC.
    </div>

    <div class="local-data">
        $cidade_cliente/$uf_cliente, $data_extenso.
    </div>

    <div class="assinatura">
        <div class="linha-assinatura"></div>
        <div class="nome-assinante"><strong>$nome_cliente</strong></div>
        <div class="cpf-assinante">CPF/MF n° $cpf_cliente</div>
    </div>
</body>
</html>
EOT;
// --- FIM DO TEMPLATE HTML ---

// 4. Configurar e Gerar o PDF com Dompdf
$options = new Options();
$options->set('defaultFont', 'Times New Roman');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 5. Enviar o PDF para o navegador para download
$nome_arquivo = "Declaracao_Hipossuficiencia_" . str_replace(' ', '_', $nome_cliente) . ".pdf";
$dompdf->stream($nome_arquivo, array("Attachment" => 1));

exit(0);
?>



