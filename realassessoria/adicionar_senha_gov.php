<?php
/**
 * Script para adicionar a coluna senha_gov na tabela filhos_menores
 * Execute este arquivo uma vez através do navegador ou linha de comando
 */

require_once 'conexao.php';

// Verificar se a coluna já existe
$checkColumn = "SHOW COLUMNS FROM filhos_menores LIKE 'senha_gov'";
$result = $conn->query($checkColumn);

if ($result->num_rows > 0) {
    echo "A coluna 'senha_gov' já existe na tabela filhos_menores.\n";
} else {
    // Adicionar a coluna
    $sql = "ALTER TABLE filhos_menores ADD COLUMN senha_gov VARCHAR(255) NULL AFTER cpf";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Coluna 'senha_gov' adicionada com sucesso na tabela filhos_menores!\n";
    } else {
        echo "✗ Erro ao adicionar coluna: " . $conn->error . "\n";
    }
}

$conn->close();
?>
