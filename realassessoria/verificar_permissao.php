<?php
// Arquivo de verificaÃ§Ã£o de permissÃµes para acesso a documentos
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/security_rls.php';

session_start();

// Verificar se o usuÃ¡rio estÃ¡ logado
if (!isset($_SESSION['usuario_id'])) {
    die("Erro: Acesso negado. FaÃ§a login para continuar.");
}


// Verificar permissÃ£o quando um ID de cliente Ã© fornecido
if (isset($_GET['id']) && !empty($_GET['id'])) {
    include_once 'conexao.php';
    $cliente_id = intval($_GET['id']);

    rls_enforce_cliente_or_die($conn, $cliente_id, false);
}
?>

