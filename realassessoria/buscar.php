<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Nao autorizado.',
    ));
    exit;
}

include 'conexao.php';

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Banco indisponivel.',
    ));
    exit;
}

$termoBusca = isset($_GET['termoBusca']) ? trim((string) $_GET['termoBusca']) : '';
if ($termoBusca === '') {
    echo json_encode(array(
        'success' => true,
        'items' => array(),
        'message' => 'Informe um termo de busca.',
    ));
    $conn->close();
    exit;
}

$sql = 'SELECT id, nome, email FROM clientes WHERE nome LIKE ? ORDER BY nome LIMIT 50';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Falha interna ao preparar busca.',
    ));
    $conn->close();
    exit;
}

$paramTermo = '%' . $termoBusca . '%';
$stmt->bind_param('s', $paramTermo);
$stmt->execute();
$result = stmt_get_result($stmt);

$items = array();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = array(
            'id' => (int) $row['id'],
            'nome' => (string) $row['nome'],
            'email' => (string) $row['email'],
        );
    }
}

$stmt->close();
$conn->close();

echo json_encode(array(
    'success' => true,
    'items' => $items,
));
