<?php
// Executa o script SQL para criar a tabela controle_acesso
require_once 'conexao.php';

$sql = file_get_contents('criar_tabela_controle_acesso.sql');
if ($conn->multi_query($sql)) {
    echo "Tabela controle_acesso criada ou já existe.";
} else {
    echo "Erro ao criar tabela: " . $conn->error;
}
$conn->close();
?>