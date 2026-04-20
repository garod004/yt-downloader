<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Nao autorizado.'));
    exit;
}

require_once __DIR__ . '/conexao.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Falha interna.'));
    exit;
}

$cpf = isset($_POST['cpf']) ? preg_replace('/\D/', '', (string)$_POST['cpf']) : '';
if ($cpf === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'CPF nao fornecido.'));
    exit;
}

$stmt = $conn->prepare('SELECT nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email, observacoes FROM clientes WHERE cpf = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Falha interna.'));
    exit;
}

$stmt->bind_param('s', $cpf);
$stmt->execute();
$result = stmt_get_result($stmt);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $row['success'] = true;
    echo json_encode($row);
} else {
    echo json_encode(array('success' => false, 'message' => 'Cadastro nao encontrado para o CPF informado.'));
}

$stmt->close();
?>
