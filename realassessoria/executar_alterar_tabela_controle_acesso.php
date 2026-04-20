<?php
require_once 'conexao.php';
$sql = file_get_contents('alterar_tabela_controle_acesso.sql');
if ($conn->multi_query($sql)) {
    echo "Tabela controle_acesso alterada.";
} else {
    echo "Erro ao alterar tabela: " . $conn->error;
}
$conn->close();
?>