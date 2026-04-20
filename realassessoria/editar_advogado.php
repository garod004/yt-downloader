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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: gerenciar_advogados.php");
    exit();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/advogados_utils.php';
garantirTabelaAdvogados($conn);

$stmt = $conn->prepare(
    "SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados WHERE id = ? LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$adv = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();

if (!$adv) {
    header("Location: gerenciar_advogados.php");
    exit();
}

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Advogado — Real Assessoria</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/advogado_form_styles.php'; ?>
</head>
<body>
<?php
$titulo_pagina = 'Editar Advogado: ' . htmlspecialchars($adv['nome']);
$acao_form     = 'editar';
include __DIR__ . '/advogado_form.php';
?>
</body>
</html>
