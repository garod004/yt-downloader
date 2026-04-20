<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
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

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/log_utils.php';
require_once __DIR__ . '/advogados_utils.php';

garantirTabelaAdvogados($conn);

try {
    $acao     = $_POST['acao'] ?? '';
    $nome     = trim($_POST['nome']     ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $oab      = trim($_POST['oab']      ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade   = trim($_POST['cidade']   ?? '');
    $uf       = normalizarUf($_POST['uf'] ?? 'AM');
    $fone     = trim($_POST['fone']     ?? '');
    $email    = trim($_POST['email']    ?? '');

    if ($nome === '')      throw new Exception('O nome é obrigatório.');
    if ($oab === '')       throw new Exception('A OAB é obrigatória.');
    if ($documento === '') throw new Exception('O CPF/CNPJ é obrigatório.');
    if ($endereco === '')  throw new Exception('O endereço é obrigatório.');
    if ($cidade === '')    throw new Exception('A cidade é obrigatória.');
    if ($fone === '')      throw new Exception('O telefone é obrigatório.');
    if ($email === '')     throw new Exception('O e-mail é obrigatório.');

    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';

    if ($acao === 'criar') {
        $stmt = $conn->prepare(
            "INSERT INTO advogados (nome, documento, oab, endereco, cidade, uf, fone, email) VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('ssssssss', $nome, $documento, $oab, $endereco, $cidade, $uf, $fone, $email);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
        registrar_log($conn, $usuario_nome, 'CRIAR_ADVOGADO', "Advogado '$nome' (ID: $id) cadastrado.", $id, $nome);
        $_SESSION['msg_advogados'] = ['tipo' => 'success', 'texto' => "Advogado \"$nome\" cadastrado com sucesso!"];
        echo json_encode(['sucesso' => true, 'mensagem' => 'Advogado cadastrado com sucesso!']);

    } elseif ($acao === 'editar') {
        $id    = (int)($_POST['id'] ?? 0);
        $ativo = (int)($_POST['ativo'] ?? 1);
        if ($id <= 0) throw new Exception('ID inválido.');
        $stmt = $conn->prepare(
            "UPDATE advogados SET nome=?, documento=?, oab=?, endereco=?, cidade=?, uf=?, fone=?, email=?, ativo=? WHERE id=?"
        );
        $stmt->bind_param('ssssssssii', $nome, $documento, $oab, $endereco, $cidade, $uf, $fone, $email, $ativo, $id);
        $stmt->execute();
        if ($stmt->affected_rows === -1) throw new Exception('Erro ao atualizar.');
        $stmt->close();
        registrar_log($conn, $usuario_nome, 'EDITAR_ADVOGADO', "Advogado ID $id ('$nome') atualizado.", $id, $nome);
        $_SESSION['msg_advogados'] = ['tipo' => 'success', 'texto' => "Advogado \"$nome\" atualizado com sucesso!"];
        echo json_encode(['sucesso' => true, 'mensagem' => 'Advogado atualizado com sucesso!']);

    } else {
        throw new Exception('Ação inválida.');
    }
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
ob_end_flush();
