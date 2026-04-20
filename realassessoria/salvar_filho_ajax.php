<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'conexao.php';

$acao = $_POST['acao'] ?? '';
$cliente_id = $_POST['cliente_id'] ?? '';

// Validar cliente_id
if (!$cliente_id || !is_numeric($cliente_id)) {
    echo json_encode(['success' => false, 'message' => 'Cliente inválido']);
    exit;
}

rls_enforce_cliente_or_die($conn, (int)$cliente_id, true);

// ADICIONAR FILHO
if ($acao === 'adicionar') {
    $nome = $_POST['nome'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $senha_gov = $_POST['senha_gov'] ?? '';
    
    if (empty($nome)) {
        echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
        exit;
    }
    
    if (empty($data_nascimento)) {
        echo json_encode(['success' => false, 'message' => 'Data de nascimento é obrigatória']);
        exit;
    }
    
    $sql = "INSERT INTO filhos_menores (cliente_id, nome, data_nascimento, cpf, senha_gov) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }
    
    $stmt->bind_param('issss', $cliente_id, $nome, $data_nascimento, $cpf, $senha_gov);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Filho cadastrado com sucesso', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }
    
    $stmt->close();
}

// LISTAR FILHOS
elseif ($acao === 'listar') {
    $sql = "SELECT id, nome, data_nascimento, cpf, senha_gov FROM filhos_menores WHERE cliente_id = ? ORDER BY data_nascimento DESC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }
    
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $filhos = [];
    while ($row = $result->fetch_assoc()) {
        $filhos[] = $row;
    }
    
    echo json_encode(['success' => true, 'filhos' => $filhos]);
    $stmt->close();
}

// ATUALIZAR FILHO
elseif ($acao === 'atualizar') {
    $filho_id = $_POST['filho_id'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $senha_gov = $_POST['senha_gov'] ?? '';
    
    if (!$filho_id || !is_numeric($filho_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do filho inválido']);
        exit;
    }
    
    if (empty($nome)) {
        echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
        exit;
    }
    
    if (empty($data_nascimento)) {
        echo json_encode(['success' => false, 'message' => 'Data de nascimento é obrigatória']);
        exit;
    }
    
    $sql = "UPDATE filhos_menores SET nome = ?, data_nascimento = ?, cpf = ?, senha_gov = ? WHERE id = ? AND cliente_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }
    
    $stmt->bind_param('ssssii', $nome, $data_nascimento, $cpf, $senha_gov, $filho_id, $cliente_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0 || $conn->affected_rows === 0) {
            echo json_encode(['success' => true, 'message' => 'Filho atualizado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Filho não encontrado']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }
    
    $stmt->close();
}

// EXCLUIR FILHO
elseif ($acao === 'excluir') {
    $filho_id = $_POST['filho_id'] ?? '';
    
    if (!$filho_id || !is_numeric($filho_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do filho inválido']);
        exit;
    }
    
    $sql = "DELETE FROM filhos_menores WHERE id = ? AND cliente_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
        exit;
    }
    
    $stmt->bind_param('ii', $filho_id, $cliente_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Filho removido com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Filho não encontrado']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    }
    
    $stmt->close();
}

else {
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

$conn->close();
?>
