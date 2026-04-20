<?php
require_once __DIR__ . '/mvp_utils.php';
require_once __DIR__ . '/advogados_utils.php';

header('Content-Type: application/json; charset=UTF-8');

garantirTabelaAdvogados($conn);

$itens = array();
$result = $conn->query("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados WHERE ativo = 1 ORDER BY nome ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }
}

echo json_encode(array(
    'ok' => true,
    'items' => $itens
));
