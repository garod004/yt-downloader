<?php
require_once dirname(__DIR__) . '/documentos_bootstrap.php';
include 'verificar_permissao.php';
$autoloadPath = APP_ROOT_DIR . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Erro: Dompdf não encontrado no servidor. Envie a pasta vendor completa para public_html/vendor.');
}
require_once $autoloadPath;

if (!class_exists('Dompdf\\Dompdf') || !class_exists('Dompdf\\Options')) {
    die('Erro: Dompdf instalado incorretamente no servidor.');
}
include 'conexao.php';
include_once 'advogados_utils.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Erro: ID do cliente não especificado.');
}
$cliente_id = (int) $_GET['id'];
$advogado_id = isset($_GET['advogado_id']) ? intval($_GET['advogado_id']) : 0;

$cliente = null;
$sql_cliente = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email FROM clientes WHERE id = ?";
if ($stmt = $conn->prepare($sql_cliente)) {
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $result = stmt_get_result($stmt);
    if ($result->num_rows === 1) {
        $cliente = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    die('Erro na preparação da consulta do cliente: ' . $conn->error);
}

// Garante tabela a_rogo para evitar falha em ambientes ainda não migrados.
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
if ($stmt_rogo = $conn->prepare($sql_a_rogo)) {
    $stmt_rogo->bind_param('i', $cliente_id);
    $stmt_rogo->execute();
    $result_rogo = stmt_get_result($stmt_rogo);
    if ($result_rogo->num_rows === 1) {
        $a_rogo = $result_rogo->fetch_assoc();
    }
    $stmt_rogo->close();
}

$advogado = obterAdvogadoContratado($conn, $advogado_id);
$adv = prepararDadosAdvogadoDocumento($advogado);

$conn->close();

if (!$cliente) {
    die('Erro: Cliente não encontrado.');
}

$nome_cliente        = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$nacionalidade       = htmlspecialchars(mb_convert_case($cliente['nacionalidade'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));
$profissao           = htmlspecialchars(mb_convert_case($cliente['profissao']    ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));
$estado_civil        = htmlspecialchars(mb_convert_case($cliente['estado_civil'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$rg_cliente          = htmlspecialchars($cliente['rg']       ?: 'Não Informado');
$cpf_cliente         = htmlspecialchars($cliente['cpf']      ?: 'Não Informado');
$endereco_cliente    = htmlspecialchars(mb_convert_case($cliente['endereco'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$cidade_cliente      = htmlspecialchars(mb_convert_case($cliente['cidade']   ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$uf_cliente          = htmlspecialchars(strtoupper($cliente['uf'] ?: 'Não Informado'));
$telefone_cliente    = htmlspecialchars($cliente['telefone'] ?: 'Não Informado');
$email_cliente       = htmlspecialchars($cliente['email']    ?: 'Não Informado');

$a_rogo_nome        = htmlspecialchars(mb_convert_case($a_rogo['nome']       ?? 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$a_rogo_identidade  = htmlspecialchars($a_rogo['identidade'] ?? 'Não Informado');
$a_rogo_cpf         = htmlspecialchars($a_rogo['cpf']        ?? 'Não Informado');

$advogado_outorgado_texto = '<strong>' . $adv['nome'] . '</strong>, advogado(a), inscrito(a) na OAB sob o n.º '
    . $adv['oab'] . ', portador(a) do ' . $adv['documento_rotulo'] . ' n.º ' . $adv['documento']
    . ', residente e domiciliado(a) em ' . $adv['endereco'] . ', ' . $adv['cidade'] . ' - ' . $adv['uf']
    . ', e-mail: ' . $adv['email'] . ', telefone: ' . $adv['fone'] . '.';

$data_atual = date('d/m/Y');
$logo_path  = APP_ROOT_DIR . '/img/logo_castro_1.jpg';
$logo_src   = '';

if (file_exists($logo_path)) {
    $logo_ext  = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
    $logo_mime = $logo_ext === 'png' ? 'image/png' : 'image/jpeg';
    $logo_data = base64_encode(file_get_contents($logo_path));
    $logo_src  = 'data:' . $logo_mime . ';base64,' . $logo_data;
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $logo_src = $scheme . '://' . $host . '/img/logo_castro_1.jpg';
    }
}


$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Procuração "Ad Judicia" - A Rogo</title>
    <style>
        @page {
            margin-top: 3mm;
            margin-bottom: 3mm;
            margin-left: 2cm;
            margin-right: 2cm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10.3pt;
            line-height: 1.3;
            color: #222;
            text-align: justify;
            margin: 0;
            padding: 0;
        }
        h1 {
            text-align: center;
            font-size: 14pt;
            margin: 0 0 6px 0;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }
        h2 {
            text-align: center;
            font-size: 10.2pt;
            font-weight: normal;
            font-style: italic;
            margin: 0 0 12px 0;
        }
        .section-title {
            font-weight: bold;
            font-size: 10.3pt;
            margin-top: 20px;
            margin-bottom: 10px;
            background: #9c6a41;
            color: #fff;
            padding: 8px 15px;
            border-radius: 5px;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 2px 6px rgba(74,53,36,0.07);
            display: inline-block;
        }
        .content {
            margin: 0 0 20px 0;
        }
        .rogo-bloco {
            margin-top: 35px;
            margin-bottom: 15px;
        }
        .poderes {
            font-size: 9.8pt;
            line-height: 1.25;
        }
        .signatures {
            margin-top: 35px;
            page-break-inside: avoid;
        }
        .signature-date {
            text-align: center;
            margin-bottom: 18px;
            font-size: 9.8pt;
        }
        .sig-row {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .sig-row-testemunhas {
            margin-top: 35px;
        }
        .sig-col {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 8px;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 86%;
            margin: 0 auto 4px auto;
        }
        .signature-name {
            font-size: 9.4pt;
            text-align: center;
        }
        .logo-topo {
            text-align: center;
            margin-bottom: 35px;
        }
        .logo-topo img {
            max-height: 62px;
            width: auto;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-left">
            <h1>PROCURAÇÃO</h1>
            <p>'Ad judícia' A ROGO</p>
        </div>
    </div>

    <div class="section-title">OUTORGANTE:</div>
    <div class="info-row">
        <div class="info-label">
            <strong>$nome_cliente</strong>, $nacionalidade, $profissao, $estado_civil,
            portador(a) do documento de identidade n.º $rg_cliente, inscrito(a) no CPF/MF sob o n.º $cpf_cliente,
            residente e domiciliado(a) em $endereco_cliente, $cidade_cliente - $uf_cliente, telefone: $telefone_cliente, e-mail: $email_cliente.
        </div>
    </div>

    <div class="rogo-bloco">
        <strong>Assina a rogo do(a) outorgante:</strong> <strong>$a_rogo_nome</strong>,
        portador(a) da cédula de identidade n.º $a_rogo_identidade,
        inscrito(a) no CPF sob o n.º $a_rogo_cpf,
        por ser o(a) outorgante analfabeto(a) ou por outra razão que o(a) impossibilite de assinar.
    </div>

    <div class="section-title">OUTORGADOS:</div>
    <div class="content">
        <strong>EDSON SILVA SANTIAGO</strong>, Brasileiro, Casado, Advogado, inscrito na OAB/RR sob o nº 619, 
        <strong>OSTIVALDO MENEZES DO NASCIMENTO JÚNIOR</strong>, Brasileiro, Casado, Advogado, inscrito na OAB/RR 
        sob o nº 1280, ambos com endereço profissional na Rua Professor Agnelo Bitencourt, nº 335, Bairro: Centro, 
        CEP: 69301-430, Boa Vista/RR, tel.: (95) 98118-1380, e e-mail: edsonsilvaadvocacia@hotmail.com, onde 
        deverão receber intimações.
    </div>

    <div class="section-title">PODERES ESPECÍFICOS:</div>
    <div class="content">
        Por meio do presente instrumento particular de mandato, a outorgante nomeia e constitui seus bastante 
        procuradores os advogados acima qualificados, conferindo-lhes poderes específicos para representá-la em 
        juízo ou fora dele, ativa e passivamente, com a cláusula "Ad Judicia" e "Et Extra", em qualquer juízo, 
        instância ou tribunal, inclusive Juizados Especiais, para praticar todos os atos necessários à defesa de 
        seus interesses, podendo, para tanto, propor, contestar, acompanhar, transigir, acordar, discordar, firmar 
        termos de compromisso, substabelecer, e praticar todos os demais atos necessários ao fiel e cabal cumprimento 
        deste mandato.
    </div>



        <div class="signatures">
        <div class="signature-date">
            $cidade_cliente/$uf_cliente, $data_atual
        </div>

        <div class="sig-row">
            <div class="sig-col">
                <div class="signature-line"></div>
                <div class="signature-name">
                    <strong>$nome_cliente</strong><br>
                    CPF/MF: $cpf_cliente<br>
                    (Outorgante – assina a rogo)
                </div>
            </div>
            <div class="sig-col">
                <div class="signature-line"></div>
                <div class="signature-name">
                    <strong>$a_rogo_nome</strong><br>
                    CPF: $a_rogo_cpf<br>
                    (Assina a rogo)
                </div>
            </div>
        </div>

        <div class="sig-row sig-row-testemunhas">
            <div class="sig-col">
                <div class="signature-line"></div>
                <div class="signature-name">Testemunha 1</div>
            </div>
            <div class="sig-col">
                <div class="signature-line"></div>
                <div class="signature-name">Testemunha 2</div>
            </div>
        </div>
    </div>

</body>
</html>
EOT;

$nome_arquivo = 'Procuracao_A_Rogo_' . str_replace(' ', '_', $nome_cliente) . '.pdf';

try {
    $options = new Options();
    $options->set('defaultFont', 'Times New Roman');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->setChroot(__DIR__);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($nome_arquivo, array('Attachment' => 1));
} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . htmlspecialchars($e->getMessage()));
}
exit(0);
?>
