<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexao.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id   = $_SESSION['usuario_id'];
$is_parceiro  = ($tipo_usuario === 'parceiro');

// Status permitidos
$status_validos = ['APROVADO', 'PAGANDO', 'PAGO'];
$status_filtro  = strtoupper(trim($_GET['status'] ?? ''));

if (!in_array($status_filtro, $status_validos, true)) {
    die('Status inválido.');
}

$titulos = [
    'APROVADO' => 'Relatório de Aprovados',
    'PAGANDO'  => 'Relatório de Pagando',
    'PAGO'     => 'Relatório de Pagos',
];
$titulo = $titulos[$status_filtro];

$cores = [
    'APROVADO' => '#27ae60',
    'PAGANDO'  => '#2980b9',
    'PAGO'     => '#8e44ad',
];
$cor_cabecalho = $cores[$status_filtro];

function formatarDataStatus($data) {
    if (empty($data) || $data === '0000-00-00') return '';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $data;
}

// Construir query — LEFT JOIN com financeiro para pegar data_aprovado
$sql = "SELECT c.nome, c.data_contrato, f.data_aprovado, c.indicador, c.situacao
        FROM clientes c
        LEFT JOIN financeiro f ON f.cliente_id = c.id
        WHERE c.situacao = ?";
$params = [$status_filtro];
$types  = "s";

if ($is_parceiro) {
    $sql .= " AND c.usuario_cadastro_id = ?";
    $params[] = $usuario_id;
    $types   .= "i";
}

$sql .= " ORDER BY c.nome ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Erro DB [gerar_relatorio_status.php]: " . $conn->error);
    die("Erro interno ao gerar relatório.");
}
$stmt->bind_param($types, ...$params);
$stmt->execute();

if (function_exists('stmt_get_result')) {
    $resultado = stmt_get_result($stmt);
} else {
    $resultado = $stmt->get_result();
}

// Gerar HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 20px; }
        h1   { text-align: center; color: #333; font-size: 18px; margin-bottom: 5px; }
        .info-relatorio { text-align: center; margin-bottom: 15px; font-size: 9px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th {
            background-color: ' . $cor_cabecalho . ';
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 10px;
            border: 1px solid #ddd;
        }
        td { padding: 6px 6px; border: 1px solid #ddd; font-size: 9px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .total  { margin-top: 10px; text-align: right; font-weight: bold; font-size: 10px; }
        .footer { text-align: center; margin-top: 30px; font-size: 8px; color: #999; }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($titulo) . '</h1>
    <div class="info-relatorio">Gerado em: ' . date('d/m/Y H:i:s') . '</div>

    <table>
        <thead>
            <tr>
                <th style="width:30%;">Nome</th>
                <th style="width:15%;">Data do Contrato</th>
                <th style="width:15%;">Data Aprovado</th>
                <th style="width:25%;">Indicador</th>
                <th style="width:15%;">Status</th>
            </tr>
        </thead>
        <tbody>';

$contador = 0;

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $contador++;
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['nome'] ?? '')                                   . '</td>';
        $html .= '<td>' . htmlspecialchars(formatarDataStatus($row['data_contrato'] ?? ''))      . '</td>';
        $html .= '<td>' . htmlspecialchars(formatarDataStatus($row['data_aprovado'] ?? ''))      . '</td>';
        $html .= '<td>' . htmlspecialchars(mb_strtoupper($row['indicador'] ?? '', 'UTF-8'))      . '</td>';
        $html .= '<td>' . htmlspecialchars($row['situacao'] ?? '')                               . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="5" style="text-align:center;padding:20px;">Nenhum registro encontrado.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="total">Total de registros: ' . $contador . '</div>
    <div class="footer">Relatório gerado pelo sistema &mdash; ' . date('d/m/Y H:i:s') . '</div>
</body>
</html>';

$stmt->close();
$conn->close();

// Gerar PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'relatorio_' . strtolower($status_filtro) . '_' . date('Y-m-d_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
?>
