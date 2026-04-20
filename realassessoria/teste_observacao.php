<?php
require_once 'conexao.php';

// Teste de INSERT
$teste_obs = "Teste de observação " . date('Y-m-d H:i:s');
$sql_test = "INSERT INTO clientes (usuario_id, nome, cpf, observacao, created_by) VALUES (1, 'TESTE OBS', '000.000.000-00', ?, 1)";
$stmt = $conn->prepare($sql_test);
$stmt->bind_param("s", $teste_obs);

if ($stmt->execute()) {
    $last_id = $conn->insert_id;
    echo "✓ INSERT executado. ID: $last_id<br>";
    
    // Buscar para verificar
    $sql_check = "SELECT id, nome, observacao FROM clientes WHERE id = ?";
    $stmt2 = $conn->prepare($sql_check);
    $stmt2->bind_param("i", $last_id);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $row = $result->fetch_assoc();
    
    echo "✓ Dados salvos:<br>";
    echo "ID: " . $row['id'] . "<br>";
    echo "Nome: " . $row['nome'] . "<br>";
    echo "Observação: " . ($row['observacao'] ?? 'NULL') . "<br><br>";
    
    // Teste de UPDATE
    $teste_obs_update = "Observação atualizada " . date('Y-m-d H:i:s');
    $sql_update = "UPDATE clientes SET observacao = ? WHERE id = ?";
    $stmt3 = $conn->prepare($sql_update);
    $stmt3->bind_param("si", $teste_obs_update, $last_id);
    
    if ($stmt3->execute()) {
        echo "✓ UPDATE executado<br>";
        
        // Buscar novamente
        $stmt2->execute();
        $result = $stmt2->get_result();
        $row = $result->fetch_assoc();
        
        echo "✓ Dados após update:<br>";
        echo "Observação: " . ($row['observacao'] ?? 'NULL') . "<br><br>";
    }
    
    // Limpar teste
    $sql_delete = "DELETE FROM clientes WHERE id = ?";
    $stmt4 = $conn->prepare($sql_delete);
    $stmt4->bind_param("i", $last_id);
    $stmt4->execute();
    echo "✓ Registro de teste removido<br>";
    
} else {
    echo "✗ Erro no INSERT: " . $stmt->error;
}

$conn->close();
?>
