<?php
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
$sql = "SELECT id, nome, cpf, numero_processo, 
        data_avaliacao_social, hora_avaliacao_social, endereco_avaliacao_social,
        data_pericia, hora_pericia, endereco_pericia 
        FROM clientes WHERE id = ?";

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

// 2.1. Buscar dados dos filhos menores, se houver
$filhos = [];
$sql_filhos = "SELECT nome, cpf, data_nascimento FROM filhos_menores WHERE cliente_id = ? ORDER BY data_nascimento DESC";
if ($stmt_filhos = $conn->prepare($sql_filhos)) {
    $stmt_filhos->bind_param("i", $cliente_id);
    $stmt_filhos->execute();
    $result_filhos = stmt_get_result($stmt_filhos);
    
    while ($row = $result_filhos->fetch_assoc()) {
        $filhos[] = $row;
    }
    $stmt_filhos->close();
}

$conn->close();

if (!$cliente) {
    die("Erro: Cliente não encontrado.");
}

// 3. Preparar os dados para o template
$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$cpf_cliente = htmlspecialchars($cliente['cpf'] ?: 'Não Informado');
$numero_processo = htmlspecialchars($cliente['numero_processo'] ?: 'Não Informado');

// Dados Avaliação Social
$data_avaliacao_social = $cliente['data_avaliacao_social'] && $cliente['data_avaliacao_social'] != '0000-00-00' 
    ? date('d/m/Y', strtotime($cliente['data_avaliacao_social'])) 
    : 'Não Agendada';
$hora_avaliacao_social = htmlspecialchars($cliente['hora_avaliacao_social'] ?: 'Não Informada');
$endereco_avaliacao_social_raw = $cliente['endereco_avaliacao_social'] ?: 'Não Informado';

// Dados Perícia
$data_pericia = $cliente['data_pericia'] && $cliente['data_pericia'] != '0000-00-00' 
    ? date('d/m/Y', strtotime($cliente['data_pericia'])) 
    : 'Não Agendada';
$hora_pericia = htmlspecialchars($cliente['hora_pericia'] ?: 'Não Informada');
$endereco_pericia_raw = $cliente['endereco_pericia'] ?: 'Não Informado';

$data_atual = date('d/m/Y');

function parse_endereco_link($valor) {
    $separator = ' | ';
    $parts = explode($separator, $valor, 2);
    if (count($parts) === 2) {
        return [trim($parts[0]), trim($parts[1])];
    }
    return [trim($valor), ''];
}

list($endereco_avaliacao_social_text, $endereco_avaliacao_social_link) = parse_endereco_link($endereco_avaliacao_social_raw);
list($endereco_pericia_text, $endereco_pericia_link) = parse_endereco_link($endereco_pericia_raw);

$endereco_avaliacao_social_text = htmlspecialchars(mb_convert_case($endereco_avaliacao_social_text ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$endereco_pericia_text = htmlspecialchars(mb_convert_case($endereco_pericia_text ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));

$endereco_avaliacao_social_link_safe = htmlspecialchars($endereco_avaliacao_social_link);
$endereco_pericia_link_safe = htmlspecialchars($endereco_pericia_link);

$endereco_avaliacao_social_html = $endereco_avaliacao_social_text;
if (!empty($endereco_avaliacao_social_link_safe)) {
    $endereco_avaliacao_social_html .= ' <a href="' . $endereco_avaliacao_social_link_safe . '" class="map-link">' . $endereco_avaliacao_social_link_safe . '</a>';
}

$endereco_pericia_html = $endereco_pericia_text;
if (!empty($endereco_pericia_link_safe)) {
    $endereco_pericia_html .= ' <a href="' . $endereco_pericia_link_safe . '" class="map-link">' . $endereco_pericia_link_safe . '</a>';
}

// Ícone do endereço (tenta ims/iss.png e img/inss.png)
$endereco_icon_html = '';
$icon_candidates = [
    __DIR__ . '/ims/iss.png',
    __DIR__ . '/img/iss.png',
    __DIR__ . '/img/inss.png',
];
foreach ($icon_candidates as $icon_path) {
    if (file_exists($icon_path)) {
        $icon_data = base64_encode(file_get_contents($icon_path));
        $endereco_icon_html = '<img src="data:image/png;base64,' . $icon_data . '" class="icon-inline" alt="" />';
        break;
    }
}

// Logo INSS no canto superior direito
$logo_inss_html = '';
$logo_inss_path = __DIR__ . '/img/logo-INSS.png';
if (file_exists($logo_inss_path)) {
    $logo_inss_data = base64_encode(file_get_contents($logo_inss_path));
    $logo_inss_html = '<img src="data:image/png;base64,' . $logo_inss_data . '" class="logo-top-right" alt="" />';
}

// --- INÍCIO DO TEMPLATE HTML ---
$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Agendamentos</title>
    <style>
        @page {
            margin: 5mm 8mm;
        }
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 0;
            padding: 0;
            font-size: 14pt;
            line-height: 1.05;
            color: #6aa9d6;
        }
        .header {
            text-align: center;
            margin-bottom: 4px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #C8956E;
            position: relative;
            padding-top: 22px;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            color: #003366;
            margin: 0 0 2px 0;
            letter-spacing: 0.3px;
        }
        .header p {
            font-size: 14pt;
            color: #6aa9d6;
            margin: 0;
        }
        .section {
            margin-bottom: 4px;
            padding: 4px;
            background: #f9f9f9;
            border-left: 2px solid #C8956E;
            border-radius: 2px;
        }
        .section-title {
            font-size: 16pt;
            font-weight: bold;
            color: #003366;
            margin-bottom: 3px;
            padding-bottom: 1px;
            border-bottom: 1px solid #C8956E;
        }
        .info-row {
            display: flex;
            margin-bottom: 2px;
            padding: 0;
        }
        .info-label {
            font-weight: bold;
            color: #6aa9d6;
            width: 120px;
            flex-shrink: 0;
            font-size: 14pt;
        }
        .info-value {
            color: #6aa9d6;
            flex: 1;
            font-size: 14pt;
        }
        .icon-inline {
            height: 16px;
            width: 16px;
            margin-left: 3px;
            vertical-align: -1px;
        }
        .map-link {
            color: #003366;
            text-decoration: none;
        }
        .logo-top-right {
            position: absolute;
            top: 0;
            right: 0;
            height: 40px;
            width: auto;
        }
        .title-dark {
            color: #003366;
        }
        .destaque {
            background: #fff;
            padding: 4px;
            border-radius: 2px;
            border: 1px solid #ddd;
        }
        .footer {
            margin-top: 4px;
            text-align: center;
            font-size: 14pt;
            color: #6aa9d6;
            padding-top: 3px;
            border-top: 1px solid #ddd;
        }
        .alert-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 2px;
            padding: 4px;
            margin-top: 4px;
            font-size: 14pt;
            color: #6aa9d6;
        }
        .alert-box strong {
            color: #003366;
        }
    </style>
</head>
<body>
    <div class="header">
        $logo_inss_html
        <h1>COMPROVANTE DO PROTOCOLO DE AGENDAMENTO</h1>
        <p>Data de Emissão: $data_atual</p>
    </div>

    <!-- Dados do Cliente -->
    <div class="section">
        <div class="section-title">DADOS DO REPRESENTANTE</div>
        <div class="destaque">
            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value"><strong>$nome_cliente</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">CPF:</div>
                <div class="info-value">$cpf_cliente</div>
            </div>
            <div class="info-row">
                <div class="info-label title-dark">Nº Processo:</div>
                <div class="info-value title-dark">$numero_processo</div>
            </div>
        </div>
    </div>
EOT;

// Adicionar seção de filhos, se houver
if (!empty($filhos)) {
    $html .= <<<EOT

    <!-- Dados do(s) Filho(s) -->
    <div class="section">
        <div class="section-title">DADOS DO(S) REQUERENTES(S)</div>
EOT;

    foreach ($filhos as $index => $filho) {
        $nome_filho = htmlspecialchars(mb_convert_case($filho['nome'], MB_CASE_TITLE, 'UTF-8'));
        $cpf_filho = htmlspecialchars($filho['cpf'] ?: 'Não Informado');
        $data_nascimento_filho = $filho['data_nascimento'] && $filho['data_nascimento'] != '0000-00-00' 
            ? date('d/m/Y', strtotime($filho['data_nascimento'])) 
            : 'Não Informada';
        
        $filho_numero = $index + 1;
        
        $html .= <<<EOT

        <div class="destaque" style="margin-bottom: 4px;">
            <div style="font-weight: bold; color: #003366; margin-bottom: 3px; font-size: 16pt;">Filho $filho_numero:</div>
            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value"><strong>$nome_filho</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">CPF:</div>
                <div class="info-value">$cpf_filho</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data de Nascimento:</div>
                <div class="info-value">$data_nascimento_filho</div>
            </div>
        </div>
EOT;
    }
    
    $html .= <<<EOT

    </div>
EOT;
}

$html .= <<<EOT

    <!-- Avaliação Social -->
    <div class="section">
        <div class="section-title">AVALIAÇÃO SOCIAL</div>
        <div class="destaque">
            <div class="info-row">
                <div class="info-label">Data:</div>
                <div class="info-value"><strong>$data_avaliacao_social</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Horário:</div>
                <div class="info-value">$hora_avaliacao_social</div>
            </div>
            <div class="info-row">
                <div class="info-label">Endereço:$endereco_icon_html</div>
                <div class="info-value">$endereco_avaliacao_social_html</div>
            </div>
        </div>
    </div>

    <!-- Perícia -->
    <div class="section">
        <div class="section-title">PERÍCIA MÉDICA</div>
        <div class="destaque">
            <div class="info-row">
                <div class="info-label">Data:</div>
                <div class="info-value"><strong>$data_pericia</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Horário:</div>
                <div class="info-value">$hora_pericia</div>
            </div>
            <div class="info-row">
                <div class="info-label">Endereço:$endereco_icon_html</div>
                <div class="info-value">$endereco_pericia_html</div>
            </div>
        </div>
    </div>

    <div class="alert-box">
        <strong>⚠ IMPORTANTE:</strong> O comparecimento nas datas e horários agendados é obrigatório. 
        Leve documento de identidade original com foto e CPF, leve toda a documentação médica que você nos enviou.
    </div>

    <div class="footer">
        <p style="margin: 0; font-weight: bold; color: #6aa9d6; font-size: 14pt;">Real Assessoria Previdenciária</p>
        <p style="margin: 1px 0; font-size: 14pt;">Rua M, Nº 65, Nova Cidade, Manaus-AM | Whatsapp: (92) 99129-0577 | Instagram: @DiolenoNS</p>
        <p style="margin: 1px 0; font-size: 13pt;">Dioleno N. Silva - Todos os direitos reservados</p>
        <p style="margin: 1px 0; font-size: 14pt;">Documento gerado em $data_atual</p>
    </div>
</body>
</html>
EOT;

// --- GERAR O PDF ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nome do arquivo
$nome_arquivo = "Relatorio_Agendamentos_" . str_replace(' ', '_', $nome_cliente) . ".pdf";

// Exibir o PDF no navegador
$dompdf->stream($nome_arquivo, array("Attachment" => false));
?>



