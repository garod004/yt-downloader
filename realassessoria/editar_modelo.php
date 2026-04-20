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
    header("Location: listar_modelos.php");
    exit();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/src/ModeloSubstituicao.php';

$stmt = $conn->prepare(
    "SELECT id, nome, categoria, descricao, conteudo FROM modelos_documentos WHERE id = ? AND ativo = 1 LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$modelo_row = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();

if (!$modelo_row) {
    header("Location: listar_modelos.php");
    exit();
}

$categorias = ModeloSubstituicao::categorias();
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Modelo — Real Assessoria</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/modelo_editor_styles.php'; ?>
</head>
<body>
<?php
$titulo_pagina   = 'Editar Modelo: ' . htmlspecialchars($modelo_row['nome']);
$acao_form       = 'editar';
$modelo_id       = (int)$modelo_row['id'];
$modelo_nome     = $modelo_row['nome'];
$modelo_cat      = $modelo_row['categoria'];
$modelo_desc     = $modelo_row['descricao'] ?? '';
$modelo_conteudo = $modelo_row['conteudo'];
include __DIR__ . '/modelo_editor_form.php';
?>
</body>
</html>
