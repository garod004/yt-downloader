<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/src/ModeloSubstituicao.php';

$categorias = ModeloSubstituicao::categorias();
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Modelo — Real Assessoria</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/modelo_editor_styles.php'; ?>
</head>
<body>
<?php
$titulo_pagina  = 'Novo Modelo de Documento';
$acao_form      = 'criar';
$modelo_id      = 0;
$modelo_nome    = '';
$modelo_cat     = 'Geral';
$modelo_desc    = '';
$modelo_conteudo = '';
include __DIR__ . '/modelo_editor_form.php';
?>
</body>
</html>
