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

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Advogado — Real Assessoria</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/advogado_form_styles.php'; ?>
</head>
<body>
<?php
$titulo_pagina = 'Novo Advogado';
$acao_form     = 'criar';
$adv = ['id'=>0,'nome'=>'','documento'=>'','oab'=>'','endereco'=>'','cidade'=>'','uf'=>'AM','fone'=>'','email'=>'','ativo'=>1];
include __DIR__ . '/advogado_form.php';
?>
</body>
</html>
