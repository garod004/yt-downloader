<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario nao autenticado']);
    exit;
}

require_once 'conexao.php';

$sql_criar_tabela = "CREATE TABLE IF NOT EXISTS incapazes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    data_nascimento DATE NULL,
    cpf VARCHAR(20) NULL,
    senha_gov VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_incapazes_cliente_id (cliente_id)
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
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $senha_gov = trim($_POST['senha_gov'] ?? '');

    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'Nome e obrigatorio']);
        exit;
    }

    if ($data_nascimento === '') {
        echo json_encode(['success' => false, 'message' => 'Data de nascimento e obrigatoria']);
        exit;
    }

    $sql = 'INSERT INTO incapazes (cliente_id, nome, data_nascimento, cpf, senha_gov) VALUES (?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('issss', $cliente_id, $nome, $data_nascimento, $cpf, $senha_gov);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Incapaz cadastrado com sucesso', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }

    $stmt->close();
}
elseif ($acao === 'listar') {
    $sql = 'SELECT id, nome, data_nascimento, cpf, senha_gov FROM incapazes WHERE cliente_id = ? ORDER BY data_nascimento DESC';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $result = stmt_get_result($stmt);

    $registros = [];
    while ($row = $result->fetch_assoc()) {
        $registros[] = $row;
    }

    echo json_encode(['success' => true, 'registros' => $registros]);
    $stmt->close();
}
elseif ($acao === 'atualizar') {
    $incapaz_id = $_POST['incapaz_id'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $senha_gov = trim($_POST['senha_gov'] ?? '');

    if (!$incapaz_id || !is_numeric($incapaz_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do incapaz invalido']);
        exit;
    }

    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'Nome e obrigatorio']);
        exit;
    }

    if ($data_nascimento === '') {
        echo json_encode(['success' => false, 'message' => 'Data de nascimento e obrigatoria']);
        exit;
    }

    $sql = 'UPDATE incapazes SET nome = ?, data_nascimento = ?, cpf = ?, senha_gov = ? WHERE id = ? AND cliente_id = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('ssssii', $nome, $data_nascimento, $cpf, $senha_gov, $incapaz_id, $cliente_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Incapaz atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }

    $stmt->close();
}
elseif ($acao === 'excluir') {
    $incapaz_id = $_POST['incapaz_id'] ?? '';

    if (!$incapaz_id || !is_numeric($incapaz_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do incapaz invalido']);
        exit;
    }

    $sql = 'DELETE FROM incapazes WHERE id = ? AND cliente_id = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }

    $stmt->bind_param('ii', $incapaz_id, $cliente_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Incapaz removido com sucesso']);
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
