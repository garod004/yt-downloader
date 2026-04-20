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
            <strong>$nome_cliente</strong>, $nacionalidade_cliente, $estado_civil_cliente, $profissao_cliente, 
            portador(a) do documento de identidade nº $rg_cliente, inscrito(a) no CPF/MF sob o nº $cpf_cliente,
            residente e domiciliado(a) em $endereco_cliente, $cidade_cliente - $uf_cliente,
            telefone: $telefone_cliente, e-mail: $email_cliente.
        </div>

        <div class="section-title">OUTORGADOS:</div>
        <div class="content">
            <strong>Dioleno Nóbrega Silva</strong>, Brasileiro, Casado, portador do documento de identificação RG: 184,603, SSP/RR, CPF: 629.880.852-34,
            com domicílio na Rua M, Nº65, Bairro: Nova Cidade, Manaus - AM, CEP: 69.097-015,
            e-mail: dioleno.nobrega@gmail.com, fone: (92)99129-0577, com escritório profissional no endereço acima citado.
        </div>

        <div class="section-title">PODERES ESPECÍFICOS:</div>
        <div class="content">
            A quem confere os poderes para representa-lo perante o INSS – INSTITUTO NACIONAL DE SEGURIDADE SOCIAL,
            podendo receber benefícios, interpor recursos às instancias superiores, receber mensalidades e quantias devidas,
            assinar recibos, fazer recadastramentos, bem como representa-lo junto a instituição bancária que recolhe o referido
            benefício, podendo, para tanto, assinar documentos, atualizar dados cadastrais, alegar e prestar declarações e informações,
            solicitar e retirar senha e cartão magnético, enfim, praticar e recorrer a todos os meios legais necessários ao fiel
            cumprimento do presente mandato.
        </div>

        <div class="signatures">
            <div class="signature-date">
                $cidade_cliente/$uf_cliente, $data_atual
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
$nome_arquivo = "Procuração_" . str_replace(' ', '_', $nome_cliente) . ".pdf";
$dompdf->stream($nome_arquivo, array("Attachment" => 1)); // 1 = Forçar download direto

exit(0); // Garante que o script pare após o envio do PDF

?>


