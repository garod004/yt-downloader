<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}
if (!(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Permissão negada. Apenas administradores podem excluir modelos.']);
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

try {
    $stmt = $conn->prepare("UPDATE modelos_documentos SET ativo = 0 WHERE id = ? AND ativo = 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        throw new Exception('Modelo não encontrado ou já excluído.');
    }
    $stmt->close();
    $nome_modelo = "ID $id";

    $usuario_nome = $_SESSION['usuario_nome'] ?? 'admin';
    registrar_log($conn, $usuario_nome, 'EXCLUIR_MODELO', "Modelo ID $id ('$nome_modelo') excluído (soft delete).", $id, $nome_modelo);
    $conn->close();

    echo json_encode(['sucesso' => true, 'mensagem' => "Modelo \"$nome_modelo\" excluído com sucesso."]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
ob_end_flush();
