<?php
session_start();

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}

// Verificar se o usuário é administrador
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: listar_clientes.php");
    exit();
}

// Verificar se foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: listar_clientes.php");
    exit();
}

// Verificar se o ID do cliente foi enviado
if (!isset($_POST['cliente_id']) || empty($_POST['cliente_id'])) {
    header("Location: listar_clientes.php");
    exit();
}

$cliente_id = intval($_POST['cliente_id']);

// Incluir conexão com o banco
include 'conexao.php';

// Buscar nome do cliente antes de excluir (para log)
$sql_buscar = "SELECT nome FROM clientes WHERE id = ?";
$stmt_buscar = $conn->prepare($sql_buscar);
$stmt_buscar->bind_param("i", $cliente_id);
$stmt_buscar->execute();
$result_buscar = $stmt_buscar->get_result();

if ($result_buscar->num_rows === 0) {
    $stmt_buscar->close();
    $conn->close();
    header("Location: listar_clientes.php");
    exit();
}

$cliente = $result_buscar->fetch_assoc();
$nome_cliente = $cliente['nome'];
$stmt_buscar->close();

// Excluir o cliente
$sql_excluir = "DELETE FROM clientes WHERE id = ?";
$stmt_excluir = $conn->prepare($sql_excluir);
$stmt_excluir->bind_param("i", $cliente_id);

if ($stmt_excluir->execute()) {
    // Log opcional - você pode criar uma tabela de logs se desejar
    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Administrador';
    $data_hora = date('Y-m-d H:i:s');
    
    // Exemplo de log em arquivo (opcional)
    $log_mensagem = "[{$data_hora}] Usuário '{$usuario_nome}' excluiu o cliente ID: {$cliente_id} - Nome: {$nome_cliente}\n";
    file_put_contents('logs/exclusoes.log', $log_mensagem, FILE_APPEND);
    
    header("Location: listar_clientes.php");
    exit();
} else {
    header("Location: listar_clientes.php");
    exit();
}

$stmt_excluir->close();
$conn->close();
?>
