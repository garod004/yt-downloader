<?php
session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

require_once 'conexao.php';
require_once 'beneficio_utils.php';

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Conexão com o banco indisponível']);
    exit();
}

// Contar clientes por status
$stats = [
    'total_clientes' => 0,
    'indeferido' => 0,
    'pagando' => 0,
    'concluido_sem_decisao' => 0,
    'enviado' => 0
];

$sql = "SELECT COUNT(*) as total FROM clientes";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_clientes'] = (int)$row['total'];
}

// Indeferido
$sql = "SELECT COUNT(*) as total FROM clientes WHERE situacao = 'indeferido'";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['indeferido'] = (int)$row['total'];
}

// Pagando
$sql = "SELECT COUNT(*) as total FROM clientes WHERE situacao = 'pagando'";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['pagando'] = (int)$row['total'];
}

// Concluído Sem Decisão
$sql = "SELECT COUNT(*) as total
        FROM clientes
        WHERE LOWER(REPLACE(TRIM(situacao), '_', ' ')) IN (
            'concluido sem decisao',
            'concluído sem decisão',
            'concluso sem decisao',
            'concluso sem decisão'
        )";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['concluido_sem_decisao'] = (int)$row['total'];
}

// Enviado
$sql = "SELECT COUNT(*) as total FROM clientes WHERE situacao = 'enviado'";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['enviado'] = (int)$row['total'];
}

// Beneficios (centralizado)
$stats['bpc_doenca'] = beneficio_contar_clientes($conn, 'BPC por Doença');
$stats['bpc_idade'] = beneficio_contar_clientes($conn, 'BPC por Idade');

// Categoria agregada de maternidade
$stats['salario_maternidade'] = beneficio_contar_clientes($conn, 'Salário Maternidade');

// Categoria agregada de aposentadoria
$stats['aposentadoria'] = beneficio_contar_clientes($conn, 'Aposentadoria');

$stats['auxilio_doenca'] = beneficio_contar_clientes($conn, 'Auxílio Doença');
$stats['pensao_morte'] = beneficio_contar_clientes($conn, 'Pensão por Morte');

$conn->close();

header('Content-Type: application/json');
echo json_encode($stats);
?>
