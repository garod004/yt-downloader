<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastro.html');
    exit();
}

require_once __DIR__ . '/conexao.php';

function redirectCadastro($message, $nome = '', $email = '', $data = '')
{
    $query = http_build_query([
        'erro' => $message,
        'nome' => $nome,
        'email' => $email,
        'data' => $data,
    ]);

    header('Location: cadastro.html' . ($query !== '' ? '?' . $query : ''));
    exit();
}

function colunaExiste($conn, $tabela, $coluna)
{
    $sql = "SHOW COLUMNS FROM `{$tabela}` LIKE ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $coluna);
    $stmt->execute();
    $result = method_exists($stmt, 'get_result') ? $stmt->get_result() : stmt_get_result($stmt);
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');
$data = trim($_POST['data'] ?? '');

if ($nome === '' || $email === '' || $senha === '') {
    redirectCadastro('Todos os campos obrigatorios devem ser preenchidos.', $nome, $email, $data);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectCadastro('Informe um e-mail valido.', $nome, $email, $data);
}

if (strlen($senha) < 6) {
    redirectCadastro('A senha deve ter no minimo 6 caracteres.', $nome, $email, $data);
}

$sqlCheck = 'SELECT id FROM usuarios WHERE email = ? LIMIT 1';
$stmtCheck = $conn->prepare($sqlCheck);
if (!$stmtCheck) {
    redirectCadastro('Nao foi possivel validar o cadastro agora.', $nome, $email, $data);
}

$stmtCheck->bind_param('s', $email);
$stmtCheck->execute();
$resultCheck = method_exists($stmtCheck, 'get_result') ? $stmtCheck->get_result() : stmt_get_result($stmtCheck);

if ($resultCheck && $resultCheck->num_rows > 0) {
    $stmtCheck->close();
    redirectCadastro('Este e-mail ja esta cadastrado.', $nome, $email, $data);
}

$stmtCheck->close();

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);
$temTipoUsuario = colunaExiste($conn, 'usuarios', 'tipo_usuario');
$temData = colunaExiste($conn, 'usuarios', 'DATA');

if ($temTipoUsuario && $temData) {
    $sqlInsert = 'INSERT INTO usuarios (nome, email, senha, is_admin, tipo_usuario, DATA) VALUES (?, ?, ?, ?, ?, ?)';
    $stmtInsert = $conn->prepare($sqlInsert);
    $isAdmin = 0;
    $tipoUsuario = 'usuario';
    if (!$stmtInsert) {
        redirectCadastro('Nao foi possivel concluir o cadastro agora.', $nome, $email, $data);
    }
    $stmtInsert->bind_param('sssiss', $nome, $email, $senhaHash, $isAdmin, $tipoUsuario, $data);
} elseif ($temTipoUsuario) {
    $sqlInsert = 'INSERT INTO usuarios (nome, email, senha, is_admin, tipo_usuario) VALUES (?, ?, ?, ?, ?)';
    $stmtInsert = $conn->prepare($sqlInsert);
    $isAdmin = 0;
    $tipoUsuario = 'usuario';
    if (!$stmtInsert) {
        redirectCadastro('Nao foi possivel concluir o cadastro agora.', $nome, $email, $data);
    }
    $stmtInsert->bind_param('sssis', $nome, $email, $senhaHash, $isAdmin, $tipoUsuario);
} elseif ($temData) {
    $sqlInsert = 'INSERT INTO usuarios (nome, email, senha, is_admin, DATA) VALUES (?, ?, ?, ?, ?)';
    $stmtInsert = $conn->prepare($sqlInsert);
    $isAdmin = 0;
    if (!$stmtInsert) {
        redirectCadastro('Nao foi possivel concluir o cadastro agora.', $nome, $email, $data);
    }
    $stmtInsert->bind_param('sssis', $nome, $email, $senhaHash, $isAdmin, $data);
} else {
    $sqlInsert = 'INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (?, ?, ?, ?)';
    $stmtInsert = $conn->prepare($sqlInsert);
    $isAdmin = 0;
    if (!$stmtInsert) {
        redirectCadastro('Nao foi possivel concluir o cadastro agora.', $nome, $email, $data);
    }
    $stmtInsert->bind_param('sssi', $nome, $email, $senhaHash, $isAdmin);
}

if (!$stmtInsert->execute()) {
    $stmtInsert->close();
    redirectCadastro('Erro ao cadastrar usuario.', $nome, $email, $data);
}

$stmtInsert->close();
header('Location: index.html?sucesso=' . urlencode('Cadastro realizado com sucesso. Faça login para continuar.'));
exit();
?>