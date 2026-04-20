<?php
include 'verificar_permissao.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Preparar os dados para o template
$data_atual = date('d/m/Y');
$cidade_estado = "Manaus - AM"; // Substitua pela sua cidade e estado

// Converter imagem para base64
$caminho_imagem = __DIR__ . '/img/assinatura.png';
$imagem_base64 = '';
if (file_exists($caminho_imagem)) {
    $imagem_data = file_get_contents($caminho_imagem);
    $imagem_base64 = 'data:image/png;base64,' . base64_encode($imagem_data);
}

// Converter imagem do logo para base64
$caminho_logo = __DIR__ . '/img/logo-INSS.png';
$logo_base64 = '';
if (file_exists($caminho_logo)) {
    $logo_data = file_get_contents($caminho_logo);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}

// --- INÍCIO DO TEMPLATE HTML DO CONTRATO ---
$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Termo de Responsabilidade</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; margin: 40px; font-size: 12px; }
        h1 { text-align: center; color: #333; font-size: 18px; }
        p { line-height: 1.5; margin-bottom: 10px; text-align: justify; }
        .assinatura { margin-top: 50px; text-align: center; }
        .assinatura p { margin-bottom: 3px; text-align: center; }
        .assinatura img { margin: 0 auto; }
        .data-local { text-align: right; margin-top: 70px; margi }
        .destaque { font-weight: bold; }
        .clausula { margin-bottom: 15px; }
        .clausula-titulo { font-weight: bold; text-decoration: underline; margin-bottom: 5px; }
        .page-break { page-break-after: always; } /* Para quebra de página se necessário */
        .logo { text-align: center; margin-bottom: 20px; }
        .logo img { width: 200px; height: auto; }
    </style>
</head>
<body>
    <div class="logo">
        <img src="$logo_base64" alt="Logo INSS">
    </div>

    <h1>TERMO DE RESPONSABILIDADE</h1>
    <p>Pelo presente Termo de Responsabilidade, comprometo-me a comunicar ao INSS qualquer
    evento que possa anular a presente Procuração, no prazo de trinta dias, a contar da data que o mesmo
    ocorra, principalmente o óbito do segurado/pensionista, mediante apresentação da respectiva certidão.
    Estou ciente que o descumprimento do compromisso ora assumido, além de obrigar a
    devolução de importâncias recebidas indevidamente, quando for o caso, sujeitar-me-á às penalidades
    previstas nos arts. 171 e 299, ambos do Código Penal.</p>

     <div class="data-local">
        <p>$cidade_estado, $data_atual.</p>
    </div>

    <div class="assinatura">
        <img src="$imagem_base64" alt="Assinatura" style="width: 160px; height: auto; display: block; margin: 0 auto;">
        <p style="margin-top: -10px;">Fabiana Rodrigues de Oliveira</p>
        <p>OAB/AM 12.308</p>
    </div>


    <h3>CÓDIGO PENAL</h3>
    <p>Art. 171. Obter, para si ou para outrem, vantagem ilícita, em prejuízo alheio, induzindo ou
    manter alguém em erro, mediante artifício, ardil ou qualquer outro meio fraudulento.</p>
    <p>Art. 299. Omitir, em documento público ou particular, declaração que devia constar, ou nele
    inserir ou fazer inserir declaração falsa ou diversa da que devia ser escrita, com o fim de prejudicar
    direito, criar, obrigação ou alterar a verdade sobre fato juridicamente relevante.</p>

    
   
    
   
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
$nome_arquivo = "Termo_Responsabilidade_" . date('Y-m-d') . ".pdf";
$dompdf->stream($nome_arquivo, array("Attachment" => 1)); // 1 = Forçar download direto

exit(0); // Garante que o script pare após o envio do PDF

?>