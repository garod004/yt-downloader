<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}
if (!(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Permissão negada. Apenas administradores podem desativar advogados.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Token inválido.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido.']);
    exit;
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/log_utils.php';
require_once __DIR__ . '/advogados_utils.php';

garantirTabelaAdvogados($conn);

try {
    $stmt = $conn->prepare("UPDATE advogados SET ativo = 0 WHERE id = ? AND ativo = 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) throw new Exception('Advogado não encontrado ou já inativo.');
    $stmt->close();
    $nome = "ID $id";

    $usuario_nome = $_SESSION['usuario_nome'] ?? 'admin';
    registrar_log($conn, $usuario_nome, 'DESATIVAR_ADVOGADO', "Advogado ID $id ('$nome') desativado.", $id, $nome);
    $conn->close();

    echo json_encode(['sucesso' => true, 'mensagem' => "Advogado \"$nome\" desativado com sucesso."]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
ob_end_flush();
