<?php
session_start();

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexao.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Função para formatar telefone
function formatarTelefone($telefone) {
    if (empty($telefone)) return '';
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 1) . ' ' . substr($telefone, 3, 4) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

// Obter tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'];
$is_admin = ($tipo_usuario === 'admin' || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1));
$is_parceiro = ($tipo_usuario === 'parceiro');

// Capturar filtros da URL
$cpf_search = isset($_GET['cpf_search']) ? trim($_GET['cpf_search']) : '';
$filtro_nome = isset($_GET['filtro_nome']) ? trim($_GET['filtro_nome']) : '';
$filtro_beneficio = isset($_GET['filtro_beneficio']) ? trim($_GET['filtro_beneficio']) : '';
$filtro_status = isset($_GET['filtro_status']) ? trim($_GET['filtro_status']) : '';
$filtro_indicador = isset($_GET['filtro_indicador']) ? trim($_GET['filtro_indicador']) : '';

// Construir query com os mesmos filtros do listar_clientes.php
$sql = "SELECT nome, beneficio, situacao, indicador, telefone, telefone2, data_contrato
        FROM clientes WHERE 1=1";
$types = "";
$params = array();

// Filtrar por usuário PARCEIRO (apenas seus clientes)
if ($is_parceiro) {
    $sql .= " AND usuario_cadastro_id = ?";
    $types .= "i";
    $params[] = $usuario_id;
}

if ($cpf_search !== '') {
    // Normalize digits so user can search with or without formatting
    $cpf_norm = preg_replace('/\D/', '', $cpf_search);
    // remove punctuation from cpf column for comparison
    $sql .= " AND REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') LIKE ?";
    $types .= "s";
    $params[] = "%" . $cpf_norm . "%";
}

if ($filtro_nome !== '') {
    $sql .= " AND nome LIKE ?";
    $types .= "s";
    $params[] = "%" . $filtro_nome . "%";
}

if ($filtro_beneficio !== '') {
    $sql .= " AND beneficio = ?";
    $types .= "s";
    $params[] = $filtro_beneficio;
}

if ($filtro_status !== '') {
    $sql .= " AND situacao = ?";
    $types .= "s";
    $params[] = $filtro_status;
}

if ($filtro_indicador !== '') {
    $sql .= " AND indicador LIKE ?";
    $types .= "s";
    $params[] = "%" . $filtro_indicador . "%";
}

$sql .= " ORDER BY nome ASC";

// Preparar e executar a consulta
if ($stmt = $conn->prepare($sql)) {
    // bind params dynamically only if there are params
    if (!empty($params)) {
        $bind_names = array();
        $bind_names[] = & $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = & $params[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    }

    $stmt->execute();
    $resultado = stmt_get_result($stmt);
} else {
    error_log("Erro DB [" . basename(__FILE__) . "]: " . $conn->error); die("Erro interno ao gerar documento. Por favor, tente novamente.");
}

// Criar string com filtros aplicados
$filtros_texto = [];
if (!empty($filtro_nome)) $filtros_texto[] = "Nome: " . htmlspecialchars($filtro_nome);
if (!empty($filtro_beneficio)) $filtros_texto[] = "Benefício: " . htmlspecialchars($filtro_beneficio);
if (!empty($filtro_status)) $filtros_texto[] = "Status: " . htmlspecialchars($filtro_status);
if (!empty($filtro_indicador)) $filtros_texto[] = "Indicador: " . htmlspecialchars($filtro_indicador);
if (!empty($cpf_search)) $filtros_texto[] = "CPF: " . htmlspecialchars($cpf_search);
$filtros_aplicados = !empty($filtros_texto) ? implode(" | ", $filtros_texto) : "Todos os clientes";

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
            margin: 20px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .info-relatorio {
            text-align: center;
            margin-bottom: 15px;
            font-size: 9px;
            color: #666;
        }
        
        .filtros {
            margin-bottom: 15px;
            padding: 8px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            font-size: 9px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th {
            background-color: #4f8cff;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10px;
            border: 1px solid #ddd;
        }
        
        td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            font-size: 9px;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .total {
            margin-top: 10px;
            text-align: right;
            font-weight: bold;
            font-size: 10px;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #999;
        }
    </style>
</head>
<body>
    <h1>Relatório de Clientes Filtrados</h1>
    <div class="info-relatorio">
        Gerado em: ' . date('d/m/Y H:i:s') . '
    </div>
    <div class="filtros">
        <strong>Filtros aplicados:</strong> ' . $filtros_aplicados . '
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 25%;">Nome</th>
                <th style="width: 20%;">Benefício</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 12%;">Indicador</th>
                <th style="width: 12%;">Telefone</th>
                <th style="width: 12%;">Telefone 2</th>
                <th style="width: 12%;">Data Contrato</th>
            </tr>
        </thead>
        <tbody>';

$contador = 0;

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $contador++;
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['nome'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['beneficio'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['situacao'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars(mb_strtoupper($row['indicador'] ?? '', 'UTF-8')) . '</td>';
        $html .= '<td>' . htmlspecialchars(formatarTelefone($row['telefone'] ?? '')) . '</td>';
        $html .= '<td>' . htmlspecialchars(formatarTelefone($row['telefone2'] ?? '')) . '</td>';
        $html .= '<td>' . htmlspecialchars(!empty($row['data_contrato']) ? date('d/m/Y', strtotime($row['data_contrato'])) : '') . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Nenhum cliente encontrado com os filtros aplicados.</td></tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="total">
        Total de clientes: ' . $contador . '
    </div>
    
    <div class="footer">
        <p>Dioleno N. Silva - Todos os direitos reservados</p>
    </div>
</body>
</html>';

// Fechar conexão
$stmt->close();
$conn->close();

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

// Enviar PDF para o navegador
$dompdf->stream($filename, array("Attachment" => true));
?>



