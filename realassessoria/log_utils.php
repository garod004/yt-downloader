<?php
// Função utilitária para registrar logs de acesso e alteração (com debug)
function registrar_log($conn, $usuario, $acao, $descricao = null, $id_alterado = null, $nome_alterado = null) {
    // Garante que id_alterado e nome_alterado nunca sejam NULL
    if ($id_alterado === null) $id_alterado = 0;
    if ($nome_alterado === null) $nome_alterado = '';
    error_log('[DEBUG] Entrou em registrar_log: usuario=' . var_export($usuario, true) . ' acao=' . var_export($acao, true) . ' descricao=' . var_export($descricao, true) . ' id_alterado=' . var_export($id_alterado, true) . ' nome_alterado=' . var_export($nome_alterado, true));
    $stmt = $conn->prepare("INSERT INTO controle_acesso (usuario, acao, descricao, id_alterado, nome_alterado) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssds", $usuario, $acao, $descricao, $id_alterado, $nome_alterado);
        if (!$stmt->execute()) {
            error_log('[LOG_ERRO] Falha ao registrar log: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log('[LOG_ERRO] Falha ao preparar statement de log: ' . $conn->error);
    }
    error_log('[DEBUG] Saiu de registrar_log');
}
