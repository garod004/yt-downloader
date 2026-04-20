<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/security_rls.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Impede cache para evitar telas sensiveis no voltar do navegador.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}

require_once __DIR__ . '/conexao.php';

$tipo_usuario = rls_tipo_usuario();
$usuario_logado_id = rls_usuario_id();
$is_admin = rls_is_admin();
$is_parceiro = rls_is_parceiro();
$is_usuario = ($tipo_usuario === 'usuario');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    die('Erro: banco de dados indisponivel.');
}

function mvpDateBr($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }

    $parts = explode('-', $date);
    if (count($parts) !== 3) {
        return $date;
    }

    return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
}

function mvpPermissaoClienteWhere($alias, $tipo_usuario)
{
    return rls_cliente_where_fragment($alias);
}

function mvpPodeAcessarCliente($conn, $cliente_id, $tipo_usuario, $usuario_logado_id, $is_admin)
{
    return rls_can_access_cliente($conn, $cliente_id);
}
