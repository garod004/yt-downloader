<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

echo json_encode([
    'logged_in' => isset($_SESSION['usuario_id'])
]);
?>
