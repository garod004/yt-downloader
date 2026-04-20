<?php
require_once 'conexao.php';

echo "<h2>Verificação das Colunas de Auditoria</h2>";

// Verificar se as colunas existem
$result = $conn->query("DESCRIBE clientes");
$colunas_auditoria = [];

echo "<h3>Colunas de auditoria na tabela clientes:</h3>";
while($row = $result->fetch_assoc()) {
    if(strpos($row['Field'], 'created') !== false || strpos($row['Field'], 'updated') !== false) {
        $colunas_auditoria[] = $row['Field'];
        echo "✓ " . $row['Field'] . " - Tipo: " . $row['Type'] . "<br>";
    }
}

if (empty($colunas_auditoria)) {
    echo "<p style='color: red;'><strong>❌ As colunas de auditoria NÃO EXISTEM!</strong></p>";
    echo "<p>Execute o SQL em adicionar_auditoria.sql</p>";
} else {
    echo "<hr>";
    
    // Testar um registro
    $sql = "SELECT id, nome, created_by, created_at, updated_by, updated_at FROM clientes LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        echo "<h3>Exemplo de registro:</h3>";
        $row = $result->fetch_assoc();
        echo "ID: " . $row['id'] . "<br>";
        echo "Nome: " . $row['nome'] . "<br>";
        echo "Created By: " . ($row['created_by'] ?? 'NULL') . "<br>";
        echo "Created At: " . ($row['created_at'] ?? 'NULL') . "<br>";
        echo "Updated By: " . ($row['updated_by'] ?? 'NULL') . "<br>";
        echo "Updated At: " . ($row['updated_at'] ?? 'NULL') . "<br>";
    }
}

$conn->close();
?>
