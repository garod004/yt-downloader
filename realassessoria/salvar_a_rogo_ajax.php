<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario nao autenticado']);
    exit;
}

require_once 'conexao.php';

// Garante a existencia da tabela A ROGO antes de qualquer operacao
$sql_criar_tabela = "CREATE TABLE IF NOT EXISTS a_rogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    identidade VARCHAR(100) NULL,
    cpf VARCHAR(20) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_a_rogo_cliente_id (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sql_criar_tabela)) {
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    exit;
}

$acao = $_POST['acao'] ?? '';
$cliente_id = $_POST['cliente_id'] ?? '';

if (!$cliente_id || !is_numeric($cliente_id)) {
    echo json_encode(['success' => false, 'message' => 'Cliente invalido']);
    exit;
}

rls_enforce_cliente_or_die($conn, (int)$cliente_id, true);

if ($acao === 'adicionar') {
    $nome = trim($_POST['nome'] ?? '');
    $identidade = trim($_POST['identidade'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');

    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'Nome e obrigatorio']);
        exit;
    }

    $sql = 'INSERT INTO a_rogo (cliente_id, nome, identidade, cpf) VALUES (?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('isss', $cliente_id, $nome, $identidade, $cpf);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'A ROGO cadastrado com sucesso', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }

    $stmt->close();
}
elseif ($acao === 'listar') {
    $sql = 'SELECT id, nome, identidade, cpf FROM a_rogo WHERE cliente_id = ? ORDER BY id DESC';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $registros = [];
    while ($row = $result->fetch_assoc()) {
        $registros[] = $row;
    }

    echo json_encode(['success' => true, 'registros' => $registros]);
    $stmt->close();
}
elseif ($acao === 'atualizar') {
    $a_rogo_id = $_POST['a_rogo_id'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $identidade = trim($_POST['identidade'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');

    if (!$a_rogo_id || !is_numeric($a_rogo_id)) {
        echo json_encode(['success' => false, 'message' => 'ID A ROGO invalido']);
        exit;
    }

    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'Nome e obrigatorio']);
        exit;
    }

    $sql = 'UPDATE a_rogo SET nome = ?, identidade = ?, cpf = ? WHERE id = ? AND cliente_id = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('sssii', $nome, $identidade, $cpf, $a_rogo_id, $cliente_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'A ROGO atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }

    $stmt->close();
}
elseif ($acao === 'excluir') {
    $a_rogo_id = $_POST['a_rogo_id'] ?? '';

    if (!$a_rogo_id || !is_numeric($a_rogo_id)) {
        echo json_encode(['success' => false, 'message' => 'ID A ROGO invalido']);
        exit;
    }

    $sql = 'DELETE FROM a_rogo WHERE id = ? AND cliente_id = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('ii', $a_rogo_id, $cliente_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'A ROGO removido com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro nao encontrado']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }

    $stmt->close();
}
else {
    echo json_encode(['success' => false, 'message' => 'Acao invalida']);
}

$conn->close();
?>