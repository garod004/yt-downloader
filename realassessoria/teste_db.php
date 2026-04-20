<?php
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('success' => false, 'message' => 'Acesso negado.'));
exit;
?>
