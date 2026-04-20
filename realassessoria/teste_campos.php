<?php
include 'conexao.php';
$result = $conn->query('DESCRIBE clientes');
echo "Campos com 'senha':\n";
while($row = $result->fetch_assoc()) {
    if(stripos($row['Field'], 'senha') !== false) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
}
?>
