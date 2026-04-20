<?php
http_response_code(403);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array(
    'success' => false,
    'message' => 'Endpoint de teste desabilitado em ambiente web.',
));

