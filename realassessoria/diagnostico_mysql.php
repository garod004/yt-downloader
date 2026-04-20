<?php
// Diagnóstico direto de conexão MySQL local
header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'realassessoria';
$port = 3306;

// Testa conexão
$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    echo "FALHA AO CONECTAR\n";
    echo "Host: $host\nUsuário: $user\nSenha: (em branco)\nBanco: $db\nPorta: $port\n";
    echo "Erro MySQL: " . $conn->connect_error . "\n";
    echo "Código do erro: " . $conn->connect_errno . "\n";
    exit(1);
}

// Testa se tabela clientes existe

$table_check = $conn->query("SHOW TABLES LIKE 'clientes'");
if (!$table_check || $table_check->num_rows == 0) {
    echo "Conectou, mas a tabela 'clientes' NÃO existe no banco $db.\n";
    exit(2);
}

// Conta clientes
$total = $conn->query("SELECT COUNT(*) AS total FROM clientes");
if ($total && $row = $total->fetch_assoc()) {
    echo "Conexão OK! Total de clientes: " . $row['total'] . "\n";
} else {
    echo "Conexão OK, mas falha ao contar clientes.\n";
}
$conn->close();
