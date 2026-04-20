<?php
session_start();

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexao.php';
require_once 'beneficio_utils.php';

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

// Definir tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'];
$is_admin = ($tipo_usuario === 'admin' || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1));
$is_parceiro = ($tipo_usuario === 'parceiro');

// Capturar filtros
$filtro_indicador = $_GET['indicador'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_beneficio = $_GET['beneficio'] ?? '';
$mostrar_total_cadastrados = isset($_GET['mostrar_total_cadastrados']) && $_GET['mostrar_total_cadastrados'] === '1';

// Construir query com filtros
$sql = "SELECT c.data_contrato, c.nome, c.indicador, c.beneficio, c.situacao, c.telefone
    FROM clientes c
    WHERE 1=1";

$params = [];
$types = "";

// Filtrar por usuário PARCEIRO (apenas seus clientes)
// ADMIN e USUARIO veem todos os clientes
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
$resultado = $stmt->get_result();

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
        $res_total = $stmt_total->get_result();
        if ($res_total && $row_total = $res_total->fetch_assoc()) {
            $total_cadastrados = (int)$row_total['total'];
        }
        $stmt_total->close();
    }
}

// Buscar valores únicos para os filtros
$indicadores = $conn->query("SELECT DISTINCT indicador FROM clientes WHERE indicador IS NOT NULL AND indicador != '' ORDER BY indicador")->fetch_all(MYSQLI_ASSOC);
$status_list = $conn->query("SELECT DISTINCT situacao FROM clientes WHERE situacao IS NOT NULL AND situacao != '' ORDER BY situacao")->fetch_all(MYSQLI_ASSOC);

// Lista padrão de benefícios (fiel ao cadastro correto)
$beneficios_padrao = array(
    'Aposentadoria Agricultor',
    'Aposentadoria Pescador',
    'Aposentadoria Indígena',
    'Aposentadoria por Tempo de Contribuição Urbano',
    'Aposentadoria Especial Urbano',
    'Aposentadoria por Invalidez Urbano',
    'Aposentadoria Híbrida',
    'Pensão por Morte',
    'BPC por Idade',
    'BPC por Doença',
    'Auxílio Doença',
    'Auxílio Acidente',
    'Auxílio Reclusão',
    'Salário Maternidade Urbano',
    'Salário Maternidade Agricultora',
    'Salário Maternidade Pescadora',
    'Salário Maternidade Indígena',
    'Divórcio',
    'Ação Trabalhista',
    'Empréstimo'
);

// Converter para array com mesmo formato do banco para compatibilidade
$beneficios = array_map(function($b) { return array('beneficio' => $b); }, $beneficios_padrao);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELATÓRIO DE CLIENTES</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <style>
        .filtros-container {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .botoes-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .tabela-relatorio {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 900px;
        }
        
        .tabela-relatorio thead {
            background: #4CAF50;
            color: white;
        }
        
        .tabela-relatorio th,
        .tabela-relatorio td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .tabela-relatorio tbody tr:hover {
            background: #f5f5f5;
        }
        
        .tabela-relatorio tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        .btn-secondary {
            background: #2196F3;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #0b7dda;
        }
        
        .btn-voltar {
            background: #666;
            color: white;
        }
        
        .btn-voltar:hover {
            background: #555;
        }
        
        .total-registros {
            text-align: right;
            margin: 10px 0;
            font-weight: bold;
            color: #333;
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin: 20px 0;
        }
        .logo-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .logo-header img {
            height: 50px;
            width: auto;
        }
        
        .logo-header h1 {
            margin: 0;
            flex-grow: 1;
        }
        
        /* Responsividade Completa */
        
        /* Desktop Grande (1440px+) */
        @media (min-width: 1440px) {
            .container {
                max-width: 1400px;
                margin: 40px auto;
            }
        }
        
        /* Notebook/Desktop Padrão (1024px - 1439px) */
        @media (max-width: 1439px) {
            .container {
                max-width: 95%;
                margin: 30px auto;
            }
            
            .filtros-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
        }
        
        /* Tablet Horizontal (768px - 1023px) */
        @media (max-width: 1023px) {
            .container {
                max-width: 98%;
                padding: 20px 15px;
            }
            
            .filtros-container {
                padding: 15px;
            }
            
            .filtros-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .logo-header {
                gap: 12px;
            }
            
            .logo-header img {
                height: 45px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .tabela-relatorio th,
            .tabela-relatorio td {
                padding: 10px 8px;
                font-size: 13px;
            }
            
            .botoes-container {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .btn {
                padding: 10px 16px;
            }
        }
        
        /* Tablet e Mobile (até 900px) - Conversão para Cards */
        @media (max-width: 900px) {
            .container {
                padding: 15px 10px;
            }
            
            .filtros-container {
                padding: 12px 10px;
                margin: 15px 0;
            }
            
            .filtros-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .logo-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .logo-header img {
                height: 50px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            .botoes-container {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn {
                width: 100%;
                padding: 12px;
                justify-content: center;
            }
            
            /* Tabela em formato de cards */
            .table-wrapper {
                overflow-x: visible;
            }
            
            .tabela-relatorio {
                border: 0;
                box-shadow: none;
                width: 100%;
                display: block;
                min-width: 0;
            }
            
            .tabela-relatorio thead {
                display: none;
            }
            
            .tabela-relatorio tbody {
                display: block;
            }
            
            .tabela-relatorio tbody tr {
                display: block;
                margin-bottom: 15px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                border-left: 4px solid #4CAF50;
                overflow: hidden;
            }
            
            .tabela-relatorio tbody tr:hover {
                background: #f5f9f5;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .tabela-relatorio tbody td {
                display: block !important;
                text-align: right !important;
                padding: 10px 15px !important;
                border: none !important;
                border-bottom: 1px solid #f0f0f0 !important;
                position: relative;
                min-height: 40px;
            }
            
            .tabela-relatorio tbody td:last-child {
                border-bottom: none !important;
            }
            
            .tabela-relatorio tbody td:before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 15px;
                font-weight: 700;
                color: #4CAF50;
                text-align: left;
                width: 45%;
                text-transform: uppercase;
            }
            
            .total-registros {
                text-align: center;
                font-size: 14px;
                padding: 10px;
                background: white;
                border-radius: 8px;
                margin-bottom: 10px;
            }
        }
        
        /* Mobile (até 599px) */
        @media (max-width: 599px) {
            .container {
                padding: 12px 8px;
            }
            
            .filtros-container {
                padding: 10px 8px;
            }
            
            .form-group label {
                font-size: 12px;
            }
            
            .form-group select {
                font-size: 13px;
                padding: 8px;
            }
            
            .logo-header img {
                height: 40px;
            }
            
            h1 {
                font-size: 18px;
            }
            
            .btn {
                font-size: 13px;
                padding: 10px;
            }
            
            .tabela-relatorio tbody td {
                padding: 8px 12px !important;
                font-size: 12px !important;
            }
            
            .tabela-relatorio tbody td:before {
                font-size: 11px !important;
                width: 42% !important;
            }
            
            .total-registros {
                font-size: 13px;
            }
        }
        
        /* Mobile Pequeno (até 360px) */
        @media (max-width: 360px) {
            .container {
                padding: 10px 5px;
            }
            
            .filtros-container {
                padding: 8px 5px;
            }
            
            .logo-header img {
                height: 35px;
            }
            
            h1 {
                font-size: 16px;
            }
            
            .btn {
                font-size: 12px;
                padding: 8px;
            }
            
            .tabela-relatorio tbody td {
                padding: 6px 10px !important;
                font-size: 11px !important;
            }
            
            .tabela-relatorio tbody td:before {
                font-size: 10px !important;
                width: 40% !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-header">
            <img src="img/logo_Real_Assessoria.png" alt="Logo Real Assessoria">
            <h1>RELATÓRIO DE CLIENTES</h1>
        </div>
        
        <!-- Filtros -->
        <div class="filtros-container">
            <form method="GET" action="relatorio_clientes.php">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label>Indicador:</label>
                        <select name="indicador" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach($indicadores as $ind): ?>
                                <option value="<?php echo htmlspecialchars($ind['indicador']); ?>" 
                                    <?php echo $filtro_indicador == $ind['indicador'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ind['indicador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach($status_list as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['situacao']); ?>" 
                                    <?php echo $filtro_status == $st['situacao'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st['situacao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Benefício:</label>
                        <select name="beneficio" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach($beneficios as $ben): ?>
                                <option value="<?php echo htmlspecialchars($ben['beneficio']); ?>" 
                                    <?php echo $filtro_beneficio == $ben['beneficio'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ben['beneficio']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <label style="display:flex; align-items:center; gap:8px; margin:0;">
                            <input type="checkbox" name="mostrar_total_cadastrados" value="1" <?php echo $mostrar_total_cadastrados ? 'checked' : ''; ?>>
                            Mostrar total de clientes cadastrados
                        </label>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                    <a href="relatorio_clientes.php" class="btn btn-secondary">Limpar Filtros</a>
                    <a href="gerar_relatorio_pdf.php?indicador=<?php echo urlencode($filtro_indicador); ?>&status=<?php echo urlencode($filtro_status); ?>&beneficio=<?php echo urlencode($filtro_beneficio); ?>&mostrar_total_cadastrados=<?php echo $mostrar_total_cadastrados ? '1' : '0'; ?>" 
                       class="btn btn-primary">Gerar PDF</a>
                    <a href="listar_clientes.php" class="btn btn-voltar">Voltar</a>
                </div>
            </form>
        </div>
        
        <!-- Total de registros -->
        <div class="total-registros">
            Total de registros: <?php echo $resultado->num_rows; ?>
            <?php if ($mostrar_total_cadastrados): ?>
                | Total de clientes cadastrados: <?php echo (int)$total_cadastrados; ?>
            <?php endif; ?>
        </div>
        
        <!-- Tabela de resultados -->
        <div class="table-wrapper">
        <table class="tabela-relatorio">
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
            <tbody>
                <?php if ($resultado->num_rows > 0): ?>
                    <?php while($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Data do Contrato"><?php echo formatarData($row['data_contrato'] ?? ''); ?></td>
                            <td data-label="Nome"><?php echo htmlspecialchars($row['nome'] ?? ''); ?></td>
                            <td data-label="Indicador"><?php echo htmlspecialchars($row['indicador'] ?? ''); ?></td>
                            <td data-label="Benefício"><?php echo htmlspecialchars($row['beneficio'] ?? ''); ?></td>
                            <td data-label="Status"><?php echo htmlspecialchars($row['situacao'] ?? ''); ?></td>
                            <td data-label="Fone"><?php echo formatarTelefone($row['telefone'] ?? ''); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">
                            Nenhum registro encontrado com os filtros selecionados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

    </div>
    
    <script>
    // Detectar navegação com botão voltar após logout
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) {
                        window.location.href = 'index.html';
                    }
                })
                .catch(() => {
                    window.location.href = 'index.html';
                });
        }
    });
    
    window.onunload = function(){};
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
