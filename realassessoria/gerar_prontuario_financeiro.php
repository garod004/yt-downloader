<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'vendor/autoload.php';
include 'conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cliente_id <= 0) {
    die("ID de cliente inválido.");
}

rls_enforce_cliente_or_die($conn, $cliente_id, false);

// Definir tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_logado_id = $_SESSION['usuario_id'];
$is_admin = ($tipo_usuario === 'admin');
$is_parceiro = ($tipo_usuario === 'parceiro');

// Buscar dados do cliente com verificação de permissão
if ($is_admin) {
    // Admin vê todos os clientes
    $sql = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cliente_id);
} else if ($is_parceiro) {
    // Parceiro vê apenas seus clientes
    $sql = "SELECT * FROM clientes WHERE id = ? AND usuario_cadastro_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cliente_id, $usuario_logado_id);
} else {
    // Usuario vê todos mas sem financeiro (já verificado antes de chegar aqui)
    $sql = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cliente_id);
}
$stmt->execute();
$result = stmt_get_result($stmt);
$cliente = $result->fetch_assoc();
$stmt->close();

// Buscar dados financeiros
$sql_fin = "SELECT * FROM financeiro WHERE cliente_id = ?";
$stmt_fin = $conn->prepare($sql_fin);
$stmt_fin->bind_param("i", $cliente_id);
$stmt_fin->execute();
$result_fin = stmt_get_result($stmt_fin);
$financeiro = $result_fin->fetch_assoc();
$stmt_fin->close();

$conn->close();

if (!$cliente || !$financeiro) {
    die("Cliente ou dados financeiros não encontrados.");
}

function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return '-';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $data;
}

function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarValorParcela($valor) {
    // Se já está formatado (contém R$), retornar como está
    if (strpos($valor, 'R$') !== false) {
        return $valor;
    }
    
    // Se está vazio, retornar traço
    if (empty($valor)) {
        return '-';
    }
    
    // Se é numérico, formatar como moeda
    if (is_numeric($valor)) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
    
    // Caso contrário, retornar o valor original
    return htmlspecialchars($valor);
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 20mm; }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #3b82ff;
            padding-bottom: 15px;
        }
        h1 {
            color: #3b82ff;
            font-size: 24pt;
            margin: 0;
            text-transform: uppercase;
        }
        .subtitle {
            color: #666;
            font-size: 11pt;
            margin-top: 5px;
        }
        .cliente-info {
            background: #f4f8fb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82ff;
        }
        .cliente-info p {
            margin: 5px 0;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: linear-gradient(90deg, #3b82ff, #06b6d4);
            color: white;
            padding: 10px;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th {
            background: #e3eafc;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ccc;
        }
        table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .valor-destaque {
            font-weight: bold;
            color: #3b82ff;
            font-size: 11pt;
        }
        .total-row {
            background: #f4f8fb;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Prontuário Financeiro</h1>
        <p class="subtitle">Documento gerado em ' . date('d/m/Y H:i') . '</p>
    </div>
    
    <div class="cliente-info">
        <p><strong>Cliente:</strong> ' . htmlspecialchars($cliente['nome']) . '</p>
        <p><strong>CPF:</strong> ' . htmlspecialchars($cliente['cpf']) . '</p>
        <p><strong>Status:</strong> ' . htmlspecialchars($financeiro['status']) . '</p>
    </div>
    
    <div class="section">
        <div class="section-title">Datas</div>
        <table>
            <tr>
                <th>Data Contrato</th>
                <th>Data Aprovado</th>
                <th>Data Vencimento</th>
            </tr>
            <tr>
                <td>' . formatarData($financeiro['data_contrato']) . '</td>
                <td>' . formatarData($financeiro['data_aprovado']) . '</td>
                <td>' . formatarData($financeiro['data_vencimento']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Parcelas</div>
        <table>
            <tr>
                <th>Qtd Parcelas</th>
                <th>Valor Parcela</th>
                <th>Parcelas Pagas</th>
                <th>Parcelas Faltantes</th>
            </tr>
            <tr>
                <td>' . number_format($financeiro['qtd_parcelas'], 0) . '</td>
                <td class="valor-destaque">' . formatarMoeda($financeiro['valor_parcela']) . '</td>
                <td>' . number_format($financeiro['parcelas_pagas'], 0) . '</td>
                <td class="valor-destaque">' . number_format($financeiro['parcelas_faltantes'], 0) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Valores Retroativos</div>
        <table>
            <tr>
                <th>R$ Retroativo</th>
                <th>% Retroativo</th>
                <th>R$ Saldo Retroativo</th>
            </tr>
            <tr>
                <td class="valor-destaque">' . formatarMoeda($financeiro['retroativo']) . '</td>
                <td>' . number_format($financeiro['percentual_retroativo'], 2) . '%</td>
                <td class="valor-destaque">' . formatarMoeda($financeiro['saldo_retroativo']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Honorários</div>
        <table>
            <tr>
                <th>Honorários Bruto</th>
                               
            </tr>
            <tr>
                <td class="valor-destaque">' . formatarMoeda($financeiro['honorarios_bruto']) . '</td>
                
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Pagamentos</div>
        <table>
            <tr>
                <th>Saldo Negativo</th>
                <th>Pago</th>
            </tr>
            <tr>
                <td class="valor-destaque">' . formatarMoeda($financeiro['saldo_negativo']) . '</td>
                <td class="valor-destaque">' . formatarMoeda($financeiro['pago']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Parcelas</div>
        <table>
            <tr>
                <th style="width: 15%;">Parcela</th>
                <th style="width: 45%;">Valor</th>
                <th style="width: 40%;">Data</th>
            </tr>';

// Gerar linhas para as 24 parcelas
for ($i = 1; $i <= 24; $i++) {
    $valor = $financeiro["parcela$i"] ?? '';
    $data = $financeiro["data_parcela$i"] ?? '';
    
    // Só mostrar parcelas que tenham valor ou data preenchidos
    if (!empty($valor) || !empty($data)) {
        $html .= '
            <tr>
                <td>' . $i . 'ª</td>
                <td>' . formatarValorParcela($valor) . '</td>
                <td>' . formatarData($data) . '</td>
            </tr>';
    }
}

$html .= '
        </table>
    </div>
    
    <div class="footer">
        <p>Dioleno N. Silva - Todos os direitos reservados</p>
        <p>Este documento foi gerado automaticamente e contém informações confidenciais.</p>
    </div>
</body>
</html>
';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Prontuario_Financeiro_" . $cliente['nome'] . ".pdf", array("Attachment" => false));
?>



