<?php // Este é o ÚNICO <?php que você precisa no início
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
$sql = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email, observacao, data_contrato FROM clientes WHERE id = ?";

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
// Use htmlspecialchars para proteger contra XSS ao exibir dados
// Formatar nome: Primeira letra maiúscula de cada palavra
$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$nacionalidade_cliente = htmlspecialchars(mb_convert_case($cliente['nacionalidade'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8')); // Adicionei fallback
$profissao_cliente = htmlspecialchars(mb_convert_case($cliente['profissao'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));     // Adicionei fallback
$estado_civil_cliente = htmlspecialchars(mb_convert_case($cliente['estado_civil'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8')); // Adicionei fallback
$rg_cliente = htmlspecialchars($cliente['rg'] ?: 'Não Informado');
$cpf_cliente = htmlspecialchars($cliente['cpf'] ?: 'Não Informado');
$endereco_cliente = htmlspecialchars(mb_convert_case($cliente['endereco'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$cidade_cliente = htmlspecialchars(mb_convert_case($cliente['cidade'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$uf_cliente = htmlspecialchars(strtoupper($cliente['uf'] ?: 'Não Informado'));
$telefone_cliente = htmlspecialchars($cliente['telefone'] ?: 'Não Informado');
$email_cliente = htmlspecialchars($cliente['email'] ?: 'Não Informado');
$observacao_cliente = nl2br(htmlspecialchars($cliente['observacao'] ?: 'Nenhuma observação.'));
$data_contrato = htmlspecialchars($cliente['data_contrato'] ?: 'Não Informado'); 

$data_atual = date('d-m-Y');
$cidade_estado = "Sua Cidade - UF"; // Substitua pela sua cidade e estado

// --- INÍCIO DO TEMPLATE HTML DO CONTRATO ---
$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Hipossuficiência</title>
    <style>
        @page {
            margin: 3mm 5mm;
        }
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 0;
            padding: 20px;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #C8956E;
        }
        .header-left h1 {
            font-size: 28pt;
            font-weight: bold;
            color: #4A3524;
            margin: 0 0 5px 0;
            letter-spacing: 1px;
        }
        .header-left p {
            font-size: 11pt;
            color: #666;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .section-title {
            background: #C8956E;
            color: white;
            padding: 8px 15px;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 0 10px 0;
        }
        .content-box {
            background: #f9f9f9;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #C8956E;
            text-align: justify;
            line-height: 1.6;
        }
        .signatures {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-date {
            text-align: center;
            margin-bottom: 40px;
            font-size: 10pt;
        }
        .signature-line {
            border-top: 2px solid #333;
            width: 350px;
            margin: 50px auto 8px auto;
        }
        .signature-name {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            margin-top: 5px;
        }
        .footer-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 15px;
            background: #C8956E;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <h1>HIPOSSUFICIÊNCIA</h1>
            <p>DECLARAÇÃO</p>
        </div>
    </div>

    <!-- Declarante -->
    <div class="section-title">DECLARANTE:</div>
    <div class="content-box">
        <strong>$nome_cliente</strong>, $nacionalidade_cliente, $estado_civil_cliente, $profissao_cliente, 
        Portador do documento de identidade Nº: $rg_cliente, portador(a) do CPF nº $cpf_cliente,
        residente e domiciliado(a) na $endereco_cliente, $cidade_cliente - $uf_cliente,
        telefone para contato $telefone_cliente, e-mail: $email_cliente.
    </div>

    <!-- Declaração -->
    <div class="content-box">
        <p>Nos termos do art. 14, §1, da Lei n.º 5584/1970, das Leis 1060/1950 e 7115/1983 e Constituição Federal, art. 5º, LXXIV, 
        a parte declara para os devidos fins e sob as penas da Lei, não ter como arcar com o pagamento de custas e demais despesas 
        processuais sem prejuízo de seu sustento, pelo que requer os benefícios da justiça gratuita.</p>
    </div>

    <!-- Assinaturas -->
    <div class="signatures">
        <div class="signature-date">
           $cidade_cliente - $uf_cliente, $data_atual
        </div>
        
        <div class="signature-line"></div>
        <div class="signature-name">$nome_cliente<br>CPF: $cpf_cliente</div>
    </div>

    <div class="footer-bar"></div>
</body>
</html>
EOT; // O EOT; deve estar em sua própria linha, sem espaços ou tabulações
// --- FIM DO TEMPLATE HTML DO CONTRATO ---

// 4. Configurar e Gerar o PDF com Dompdf
$options = new Options();
$options->set('defaultFont', 'Helvetica'); // Define uma fonte padrão para evitar problemas de caractere
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Se você tiver imagens externas ou CSS via URL

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Opcional) Configurar o tamanho e orientação do papel
$dompdf->setPaper('A4', 'portrait'); // 'portrait' ou 'landscape'

// Renderizar o HTML para PDF
$dompdf->render();

// 5. Enviar o PDF para o navegador para download ou visualização
$nome_arquivo = "Hipossuficiencia_" . str_replace(' ', '_', $nome_cliente) . ".pdf";
$dompdf->stream($nome_arquivo, array("Attachment" => 1)); // 1 = Forçar download direto

exit(0); // Garante que o script pare após o envio do PDF

?>


