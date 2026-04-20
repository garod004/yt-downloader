<?php
if (defined('APP_SECURITY_RLS_LOADED')) {
    return;
}
define('APP_SECURITY_RLS_LOADED', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('rls_tipo_usuario')) {
    function rls_tipo_usuario() {
        return (string) ($_SESSION['tipo_usuario'] ?? 'usuario');
    }
}

if (!function_exists('rls_usuario_id')) {
    function rls_usuario_id() {
        return (int) ($_SESSION['usuario_id'] ?? 0);
    }
}

if (!function_exists('rls_is_admin')) {
    function rls_is_admin() {
        $tipo = rls_tipo_usuario();
        return ($tipo === 'admin' || (isset($_SESSION['is_admin']) && (int) $_SESSION['is_admin'] === 1));
    }
}

if (!function_exists('rls_is_parceiro')) {
    function rls_is_parceiro() {
        return rls_tipo_usuario() === 'parceiro';
    }
}

if (!function_exists('rls_cliente_where_fragment')) {
    function rls_cliente_where_fragment($alias) {
        $alias = trim((string) $alias);
        if ($alias === '') {
            $alias = 'clientes';
        }

        if (!rls_is_parceiro()) {
            return '';
        }

        return " AND ({$alias}.usuario_cadastro_id = ? OR {$alias}.usuario_cadastro_id IS NULL) ";
    }
}

if (!function_exists('rls_can_access_cliente')) {
    function rls_can_access_cliente($conn, $clienteId) {
        $clienteId = (int) $clienteId;
        if ($clienteId <= 0 || !($conn instanceof mysqli)) {
            return false;
        }

        if (rls_is_admin() || rls_tipo_usuario() === 'usuario') {
            return true;
        }

        if (!rls_is_parceiro()) {
            return false;
        }

        $sql = 'SELECT usuario_cadastro_id FROM clientes WHERE id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $clienteId);
        $stmt->execute();

        $row = null;
        if (method_exists($stmt, 'get_result')) {
            $result = @$stmt->get_result();
            if ($result !== false) {
                $row = $result->fetch_assoc();
            }
        }

        if ($row === null) {
            $meta = $stmt->result_metadata();
            if ($meta !== false) {
                $usuarioCadastroId = null;
                $stmt->bind_result($usuarioCadastroId);
                if ($stmt->fetch()) {
                    $row = array('usuario_cadastro_id' => $usuarioCadastroId);
                }
            }
        }

        $stmt->close();

        if (!$row) {
            return false;
        }

        $ownerId = $row['usuario_cadastro_id'];
        return ($ownerId === null || (int) $ownerId === rls_usuario_id());
    }
}

if (!function_exists('rls_enforce_cliente_or_die')) {
    function rls_enforce_cliente_or_die($conn, $clienteId, $json = false) {
        if (rls_can_access_cliente($conn, $clienteId)) {
            return;
        }

        if (!headers_sent()) {
            http_response_code(403);
        }

        if ($json) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode(array('success' => false, 'message' => 'Acesso negado para este cliente.'));
            exit();
        }

        die('Erro: Voce nao tem permissao para acessar este cliente.');
    }
}
