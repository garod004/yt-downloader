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

function formatarCPF($cpf) {
    if (empty($cpf)) return '-';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return '-';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $data;
}

$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = (int)$_SESSION['usuario_id'];
$is_parceiro = ($tipo_usuario === 'parceiro');

$filtro_indicador = isset($_GET['indicador']) ? trim($_GET['indicador']) : '';
$filtro_responsavel = isset($_GET['responsavel']) ? trim($_GET['responsavel']) : '';
$filtro_advogado = isset($_GET['advogado']) ? trim($_GET['advogado']) : '';

$sql = "SELECT nome, cpf, data_contrato, indicador, responsavel, advogado, beneficio
        FROM clientes
        WHERE 1=1";

$params = [];
$types = '';

if ($is_parceiro) {
    $sql .= " AND usuario_cadastro_id = ?";
    $params[] = $usuario_id;
    $types .= 'i';
}

if ($filtro_indicador !== '') {
    $sql .= " AND indicador LIKE ?";
    $params[] = '%' . $filtro_indicador . '%';
    $types .= 's';
}

if ($filtro_responsavel !== '') {
    $sql .= " AND responsavel LIKE ?";
    $params[] = '%' . $filtro_responsavel . '%';
    $types .= 's';
}

if ($filtro_advogado !== '') {
    $sql .= " AND advogado LIKE ?";
    $params[] = '%' . $filtro_advogado . '%';
    $types .= 's';
}

$sql .= " ORDER BY nome ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = stmt_get_result($stmt);

$filtros = [];
if ($filtro_indicador !== '') $filtros[] = 'Indicador: ' . htmlspecialchars($filtro_indicador);
if ($filtro_responsavel !== '') $filtros[] = 'Responsável: ' . htmlspecialchars($filtro_responsavel);
if ($filtro_advogado !== '') $filtros[] = 'Advogado: ' . htmlspecialchars($filtro_advogado);
$filtros_texto = !empty($filtros) ? implode(' | ', $filtros) : 'Sem filtros';

$linhas_html = '';
while ($row = $resultado->fetch_assoc()) {
    $linhas_html .= '<tr>';
    $linhas_html .= '<td>' . htmlspecialchars($row['nome'] ?? '-') . '</td>';
    $linhas_html .= '<td>' . htmlspecialchars(formatarCPF($row['cpf'] ?? '')) . '</td>';
    $linhas_html .= '<td>' . htmlspecialchars(formatarData($row['data_contrato'] ?? '')) . '</td>';
    $linhas_html .= '<td>' . htmlspecialchars($row['indicador'] ?? '-') . '</td>';
    $linhas_html .= '<td>' . htmlspecialchars($row['responsavel'] ?? '-') . '</td>';
    $linhas_html .= '<td>' . htmlspecialchars($row['advogado'] ?? '-') . '</td>';
    $linhas_html .= '<td>' . htmlspecialchars($row['beneficio'] ?? '-') . '</td>';
    $linhas_html .= '</tr>';
}

if ($linhas_html === '') {
    $linhas_html = '<tr><td colspan="7" style="text-align:center;">Nenhum registro encontrado para o relatório.</td></tr>';
}

$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #222; }
        h1 { text-align: center; margin: 0 0 6px; font-size: 18px; }
        .meta { text-align: center; margin-bottom: 10px; color: #666; font-size: 9px; }
        .filtros { margin-bottom: 10px; background: #f3f3f3; border: 1px solid #ddd; padding: 6px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d9d9d9; padding: 5px; text-align: left; }
        th { background: #e9eff7; }
    </style>
</head>
<body>
    <h1>Relatório de Justiça</h1>
    <div class="meta">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    <div class="filtros"><strong>Filtros:</strong> ' . $filtros_texto . '</div>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>CPF</th>
                <th>Data Contrato</th>
                <th>Indicador</th>
                <th>Responsável</th>
                <th>Advogado</th>
                <th>Benefício</th>
            </tr>
        </thead>
        <tbody>
            ' . $linhas_html . '
        </tbody>
    </table>
</body>
</html>';

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('relatorio_justica.pdf', ['Attachment' => false]);
exit;



