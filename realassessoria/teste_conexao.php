<?php
// Teste de conexão e consulta simples ao banco
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = getenv('DB_HOST') ?: 'meusisdns.com.br';
$user = getenv('DB_USER') ?: 'u118093551_dioleno';
$pass = getenv('DB_PASS') ?: '31D18f12g06hs*';
$db   = getenv('DB_NAME') ?: 'u118093551_realassessoria';
$port = getenv('DB_PORT') ?: 3306;

$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    echo '<b>Erro de conexão:</b> ' . $conn->connect_error;
    exit;
}

// Mostra o banco conectado e conta de clientes
$res = $conn->query('SELECT DATABASE() as db, COUNT(*) as total FROM clientes');
if ($res && $row = $res->fetch_assoc()) {
    echo '<b>Banco conectado:</b> ' . htmlspecialchars($row['db']) . '<br>';
    echo '<b>Total de clientes:</b> ' . (int)$row['total'] . '<br>';
} else {
    echo 'Conectou, mas não encontrou tabela clientes ou erro na consulta.';
}

$conn->close();
?>
