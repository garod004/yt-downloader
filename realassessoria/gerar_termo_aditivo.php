<?php // Este Ã© o ÃšNICO <?php que vocÃª precisa no inÃ­cio
include 'verificar_permissao.php'; // Verificar permissÃµes
require_once 'vendor/autoload.php'; // Inclui o autoloader do Composer (para Dompdf)
include 'conexao.php';            // Inclui o arquivo de conexÃ£o com o banco

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Obter o ID do cliente da URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Erro: ID do cliente nÃ£o especificado.");
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
    die("Erro: Cliente nÃ£o encontrado.");
}


// 3. Preparar os dados para o template
// Use htmlspecialchars para proteger contra XSS ao exibir dados
// Formatar nome: Primeira letra maiÃºscula de cada palavra
$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$nacionalidade_cliente = htmlspecialchars(mb_convert_case($cliente['nacionalidade'] ?: 'NÃ£o Informada', MB_CASE_TITLE, 'UTF-8')); // Adicionei fallback
$profissao_cliente = htmlspecialchars(mb_convert_case($cliente['profissao'] ?: 'NÃ£o Informada', MB_CASE_TITLE, 'UTF-8'));     // Adicionei fallback
$estado_civil_cliente = htmlspecialchars(mb_convert_case($cliente['estado_civil'] ?: 'NÃ£o Informado', MB_CASE_TITLE, 'UTF-8')); // Adicionei fallback
$rg_cliente = htmlspecialchars($cliente['rg'] ?: 'NÃ£o Informado');
$cpf_cliente = htmlspecialchars($cliente['cpf'] ?: 'NÃ£o Informado');
$endereco_cliente = htmlspecialchars(mb_convert_case($cliente['endereco'] ?: 'NÃ£o Informado', MB_CASE_TITLE, 'UTF-8'));
$cidade_cliente = htmlspecialchars(mb_convert_case($cliente['cidade'] ?: 'NÃ£o Informado', MB_CASE_TITLE, 'UTF-8'));
$uf_cliente = htmlspecialchars(strtoupper($cliente['uf'] ?: 'NÃ£o Informado'));
$telefone_cliente = htmlspecialchars($cliente['telefone'] ?: 'NÃ£o Informado');
$email_cliente = htmlspecialchars($cliente['email'] ?: 'NÃ£o Informado');
$observacao_cliente = nl2br(htmlspecialchars($cliente['observacao'] ?: 'Nenhuma observaÃ§Ã£o.'));
$data_contrato = htmlspecialchars($cliente['data_contrato'] ?: 'NÃ£o Informado'); 

$data_atual = date('d-m-Y');
$cidade_estado = "Sua Cidade - UF"; // Substitua pela sua cidade e estado

// --- INÃCIO DO TEMPLATE HTML DO CONTRATO ---
$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Termo Aditivo</title>
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
        .subtitle {
            text-align: center;
            font-size: 10pt;
            color: #4A3524;
            margin: 15px 0;
            font-weight: bold;
        }
        .opcoes p {
            margin: 8px 0;
            padding-left: 20px;
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
            <h1>TERMO ADITIVO</h1>
            <p>ANEXO IV - IN NÂº 77/PRES/INSS</p>
        </div>
    </div>
    
    <div class="subtitle">
        ANEXO IV, INSTRUÃ‡ÃƒO NORMATIVA NÂº 77 /PRES/INSS, DE 21 DE JANEIRO DE 2015
    </div>

    <!-- Outorgante -->
    <div class="section-title">OUTORGANTE:</div>
    <div class="content-box">
        <strong>$nome_cliente</strong>, $nacionalidade_cliente, $estado_civil_cliente, $profissao_cliente, 
        Portador do documento de identidade NÂº: $rg_cliente, portador(a) do CPF nÂº $cpf_cliente,
        residente e domiciliado(a) na $endereco_cliente, $cidade_cliente - $uf_cliente,
        telefone para contato $telefone_cliente, e-mail: $email_cliente.
    </div>

    <!-- Poderes -->
    <div class="content-box">
        <p>A quem confere poderes especiais para representÃ¡-lo perante o INSS, bem como usar de todos os meios legais para o fiel 
        cumprimento do presente mandato, por encontrar-se:</p>
        
        <div class="opcoes">
            <p>( X ) Incapacitado de locomover-se ou portador de molÃ©stia contagiosa,</p>
            <p>(  ) Ausente (viagem dentro paÃ­s ou exterior) perÃ­odo ______________</p>
            <p>(  ) ResidÃªncia no exterior (indicar o paÃ­s ________________) com fins especÃ­ficos de:</p>
        </div>
    </div>

    <div class="section-title">INDICAR UMA DAS OPÃ‡Ã•ES ABAIXO:</div>
    <div class="content-box">
        <div class="opcoes">
            <p>( X ) Receber mensalidades de benefÃ­cios, receber quantias atrasadas e firmar os respectivos recibos.</p>
            <p>( X ) Requerer benefÃ­cios, revisÃ£o e interpor recursos.</p>
            <p>(  ) ComprovaÃ§Ã£o de vida junto a rede bancÃ¡ria.</p>
            <p>( X ) Cadastro de Senha para informaÃ§Ãµes previdenciÃ¡rias pela internet.</p>
            <p>( X ) Requerimentos diversos.</p>
        </div>
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
EOT; // O EOT; deve estar em sua prÃ³pria linha, sem espaÃ§os ou tabulaÃ§Ãµes
// --- FIM DO TEMPLATE HTML DO CONTRATO ---

// 4. Configurar e Gerar o PDF com Dompdf
$options = new Options();
$options->set('defaultFont', 'Helvetica'); // Define uma fonte padrÃ£o para evitar problemas de caractere
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Se vocÃª tiver imagens externas ou CSS via URL

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Opcional) Configurar o tamanho e orientaÃ§Ã£o do papel
$dompdf->setPaper('A4', 'portrait'); // 'portrait' ou 'landscape'

// Renderizar o HTML para PDF
$dompdf->render();

// 5. Enviar o PDF para o navegador para download ou visualizaÃ§Ã£o
$nome_arquivo = "Termo_Aditivo_" . str_replace(' ', '_', $nome_cliente) . ".pdf";
$dompdf->stream($nome_arquivo, array("Attachment" => 1)); // 1 = ForÃ§ar download direto

exit(0); // Garante que o script pare apÃ³s o envio do PDF

?>
