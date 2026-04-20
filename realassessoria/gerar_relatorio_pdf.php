<?php
session_start();

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexao.php';
require_once 'beneficio_utils.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Função para formatar CPF
function formatarCPF($cpf) {
    if (empty($cpf)) return '';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

// Função para formatar data de AAAA-MM-DD para DD/MM/AAAA
function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return '';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $data;
}

function formatarTelefone($telefone) {
    if (empty($telefone)) return '';
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    }
    if (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

// Capturar filtros
$filtro_indicador = $_GET['indicador'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_beneficio = $_GET['beneficio'] ?? '';
$mostrar_total_cadastrados = isset($_GET['mostrar_total_cadastrados']) && $_GET['mostrar_total_cadastrados'] === '1';

// Definir tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'];
$is_parceiro = ($tipo_usuario === 'parceiro');

// Construir query com filtros
$sql = "SELECT c.data_contrato, c.nome, c.indicador, c.beneficio, c.situacao, c.telefone
    FROM clientes c
    WHERE 1=1";
$params = [];
$types = "";

// Filtrar por usuário PARCEIRO (apenas seus clientes)
if ($is_parceiro) {
    $sql .= " AND c.usuario_cadastro_id = ?";
    $params[] = $usuario_id;
    $types .= "i";
}

if (!empty($filtro_indicador)) {
    $sql .= " AND c.indicador = ?";
    $params[] = $filtro_indicador;
    $types .= "s";
}

if (!empty($filtro_status)) {
    $sql .= " AND c.situacao = ?";
    $params[] = $filtro_status;
    $types .= "s";
}

if (!empty($filtro_beneficio)) {
    beneficio_aplicar_filtro($sql, $types, $params, $filtro_beneficio, 'c.beneficio');
}

$sql .= " ORDER BY c.nome ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = stmt_get_result($stmt);

$total_cadastrados = null;
if ($mostrar_total_cadastrados) {
    $sql_total = "SELECT COUNT(*) AS total FROM clientes c WHERE 1=1";
    $params_total = array();
    $types_total = "";

    if ($is_parceiro) {
        $sql_total .= " AND c.usuario_cadastro_id = ?";
        $params_total[] = $usuario_id;
        $types_total .= "i";
    }

    $stmt_total = $conn->prepare($sql_total);
    if ($stmt_total) {
        if (!empty($params_total)) {
            $stmt_total->bind_param($types_total, ...$params_total);
        }
        $stmt_total->execute();
        $res_total = stmt_get_result($stmt_total);
        if ($res_total && $row_total = $res_total->fetch_assoc()) {
            $total_cadastrados = (int)$row_total['total'];
        }
        $stmt_total->close();
    }
}

// Criar string com filtros aplicados
$filtros_texto = [];
if (!empty($filtro_indicador)) $filtros_texto[] = "Indicador: " . htmlspecialchars($filtro_indicador);
if (!empty($filtro_status)) $filtros_texto[] = "Status: " . htmlspecialchars($filtro_status);
if (!empty($filtro_beneficio)) $filtros_texto[] = "Benefício: " . htmlspecialchars($filtro_beneficio);
$filtros_aplicados = !empty($filtros_texto) ? implode(" | ", $filtros_texto) : "Sem filtros";

// Gerar HTML do relatório
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .info-relatorio {
            text-align: center;
            margin-bottom: 10px;
            font-size: 9px;
            color: #666;
        }
        
        .filtros {
            background: #f0f0f0;
            padding: 8px;
            margin-bottom: 15px;
            border-radius: 3px;
            font-size: 9px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background: #4CAF50;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-size: 9px;
            border: 1px solid #ddd;
        }
        
        td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            font-size: 9px;
        }
        
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .total {
            text-align: right;
            font-weight: bold;
            margin-top: 10px;
            font-size: 10px;
        }
        
        .rodape {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #666;
            padding: 10px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <h1>RELATÓRIO DE CLIENTES</h1>
    <div class="info-relatorio">
        Data de Geração: ' . date('d/m/Y H:i:s') . '
    </div>
    
    <div class="filtros">
        <strong>Filtros Aplicados:</strong> ' . $filtros_aplicados . '
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Data do Contrato</th>
                <th>Nome</th>
                <th>Indicador</th>
                <th>Benefício</th>
                <th>Status</th>
                <th>Fone</th>
            </tr>
        </thead>
        <tbody>';

if ($resultado->num_rows > 0) {
    while($row = $resultado->fetch_assoc()) {
        $html .= '<tr>';
        $html .= '<td>' . formatarData($row['data_contrato'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nome'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['indicador'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['beneficio'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['situacao'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars(formatarTelefone($row['telefone'] ?? '')) . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="6" style="text-align: center; padding: 20px;">Nenhum registro encontrado</td></tr>';
}

$total_registros = $resultado->num_rows;

$html .= '
        </tbody>
    </table>
    
    <div class="total">
        Total de registros: ' . $total_registros . '
        ' . ($mostrar_total_cadastrados ? ' | Total de clientes cadastrados: ' . (int)$total_cadastrados : '') . '
    </div>
    
    <div class="rodape">
        Relatório gerado pelo sistema - ' . date('d/m/Y H:i:s') . '
    </div>
</body>
</html>';

// Configurar Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Nome do arquivo
$filename = 'relatorio_clientes_' . date('Y-m-d_His') . '.pdf';

// Enviar para o navegador como download
$dompdf->stream($filename, array("Attachment" => true));

$stmt->close();
$conn->close();
?>



