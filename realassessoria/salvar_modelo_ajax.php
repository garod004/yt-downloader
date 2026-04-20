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
require_once __DIR__ . '/src/ModeloSubstituicao.php';

try {
    $acao      = $_POST['acao'] ?? '';
    $nome      = trim(strip_tags($_POST['nome'] ?? ''));
    $categoria = trim(strip_tags($_POST['categoria'] ?? 'Geral'));
    $descricao = trim(strip_tags($_POST['descricao'] ?? ''));
    $conteudo  = $_POST['conteudo'] ?? '';

    if (!ModeloSubstituicao::validarNomeModelo($nome)) {
        throw new Exception('O nome é obrigatório e deve ter no máximo 150 caracteres.');
    }
    if ($conteudo === '') {
        throw new Exception('O conteúdo do modelo é obrigatório.');
    }
    if (strlen($conteudo) > 5 * 1024 * 1024) {
        throw new Exception('Conteúdo excede 5 MB.');
    }
    if (!ModeloSubstituicao::validarCategoria($categoria)) {
        throw new Exception('Categoria inválida.');
    }

    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';

    if ($acao === 'criar') {
        $stmt = $conn->prepare(
            "INSERT INTO modelos_documentos (nome, categoria, descricao, conteudo, criado_por) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssss', $nome, $categoria, $descricao, $conteudo, $usuario_nome);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
        registrar_log($conn, $usuario_nome, 'CRIAR_MODELO', "Modelo '$nome' (ID: $id) criado.", $id, $nome);
        $_SESSION['msg_modelos'] = ['tipo' => 'success', 'texto' => "Modelo \"$nome\" criado com sucesso!"];
        echo json_encode(['sucesso' => true, 'mensagem' => 'Modelo criado com sucesso!', 'id' => $id]);

    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('ID inválido.');
        }
        $stmt = $conn->prepare(
            "UPDATE modelos_documentos SET nome=?, categoria=?, descricao=?, conteudo=? WHERE id=? AND ativo=1"
        );
        $stmt->bind_param('ssssi', $nome, $categoria, $descricao, $conteudo, $id);
        $stmt->execute();
        $afetados = $stmt->affected_rows;
        $stmt->close();
        if ($afetados === -1) {
            throw new Exception('Erro ao atualizar.');
        }
        registrar_log($conn, $usuario_nome, 'EDITAR_MODELO', "Modelo ID $id ('$nome') atualizado.", $id, $nome);
        $_SESSION['msg_modelos'] = ['tipo' => 'success', 'texto' => "Modelo \"$nome\" atualizado com sucesso!"];
        echo json_encode(['sucesso' => true, 'mensagem' => 'Modelo atualizado com sucesso!']);

    } else {
        throw new Exception('Ação inválida.');
    }
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
ob_end_flush();
