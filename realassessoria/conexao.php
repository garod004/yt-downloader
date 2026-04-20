<?php
require_once __DIR__ . '/security_bootstrap.php';

mysqli_report(MYSQLI_REPORT_OFF);

// Carrega .env local se existir (nao sobrescreve variaveis de ambiente ja definidas).
$_dotenv_file = __DIR__ . '/.env';
if (is_readable($_dotenv_file)) {
    foreach (file($_dotenv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        $eqPos = strpos($_line, '=');
        if ($eqPos === false) continue;
        $_key = trim(substr($_line, 0, $eqPos));
        $_val = trim(substr($_line, $eqPos + 1));
        // Remove aspas opcionais ao redor do valor
        if (strlen($_val) >= 2 && in_array($_val[0], ['"', "'"], true) && $_val[0] === $_val[strlen($_val) - 1]) {
            $_val = substr($_val, 1, -1);
        }
        if (!isset($_ENV[$_key]) || $_ENV[$_key] === '') {
            $_ENV[$_key] = $_val;
        }
    }
}
unset($_dotenv_file, $_line, $eqPos, $_key, $_val);

if (!function_exists('env_value')) {
    function env_value($name, $default = null) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }
        if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return $_SERVER[$name];
        }
        return $default;
    }
}

// Compatibilidade para hospedagens sem extensao mbstring.
if (!defined('MB_CASE_UPPER')) {
    define('MB_CASE_UPPER', 0);
}
if (!defined('MB_CASE_LOWER')) {
    define('MB_CASE_LOWER', 1);
}
if (!defined('MB_CASE_TITLE')) {
    define('MB_CASE_TITLE', 2);
}
if (!function_exists('mb_convert_case')) {
    function mb_convert_case($string, $mode, $encoding = null) {
        $value = (string)$string;
        if ($mode === MB_CASE_UPPER) {
            return strtoupper($value);
        }
        if ($mode === MB_CASE_LOWER) {
            return strtolower($value);
        }
        if ($mode === MB_CASE_TITLE) {
            return ucwords(strtolower($value));
        }
        return $value;
    }
}

// ================================================================
// CREDENCIAIS VIA AMBIENTE
// Use as variaveis: DB_HOST, DB_USER, DB_PASS, DB_NAME e DB_PORT.
// Opcional fallback local: DB_LOCAL_HOST, DB_LOCAL_USER, DB_LOCAL_PASS,
// DB_LOCAL_NAME e DB_LOCAL_PORT.
// ================================================================

$db_host  = env_value('DB_HOST', '');
$db_user  = env_value('DB_USER', '');
$db_pass  = env_value('DB_PASS', '');
$db_name  = env_value('DB_NAME', '');
$db_port  = (int)env_value('DB_PORT', '3306');

// Fallback local opcional quando o primario falhar.
$db_host_local = env_value('DB_LOCAL_HOST', '');
$db_user_local = env_value('DB_LOCAL_USER', '');
$db_pass_local = env_value('DB_LOCAL_PASS', '');
$db_name_local = env_value('DB_LOCAL_NAME', '');
$db_port_local = (int)env_value('DB_LOCAL_PORT', '3306');
// ================================================================

$conn = null;
$db_connection_error = '';

$erroProducao = 'configuracao nao informada';
$erroLocal = 'configuracao nao informada';

if ($db_host !== '' && $db_user !== '' && $db_name !== '') {
    $tentativa = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    if ($tentativa instanceof mysqli && !$tentativa->connect_error) {
        $conn = $tentativa;
    } else {
        $erroProducao = ($tentativa instanceof mysqli) ? $tentativa->connect_error : 'mysqli indisponivel';
    }
}

if (!$conn && $db_host_local !== '' && $db_user_local !== '' && $db_name_local !== '') {
    // Tenta conexao local como fallback.
    $tentativaLocal = @new mysqli($db_host_local, $db_user_local, $db_pass_local, $db_name_local, $db_port_local);
    if ($tentativaLocal instanceof mysqli && !$tentativaLocal->connect_error) {
        $conn = $tentativaLocal;
    } else {
        $erroLocal = ($tentativaLocal instanceof mysqli) ? $tentativaLocal->connect_error : 'mysqli indisponivel';
    }
}

if (!$conn) {
    $db_connection_error = "Falha ao conectar no banco configurado";
    error_log("Falha na conexao com o banco: " . $db_connection_error);
    error_log("Detalhe tecnico conexao principal: " . $erroProducao);
    error_log("Detalhe tecnico conexao fallback: " . $erroLocal);
    http_response_code(500);
    die("Erro ao conectar ao banco de dados.");
}

if ($conn) {
    $conn->set_charset('utf8mb4');
}

// Resultado compativel com mysqli_result para ambientes sem mysqlnd/get_result.
if (!class_exists('StmtArrayResult')) {
    class StmtArrayResult {
        public $num_rows = 0;
        private $rows = array();
        private $index = 0;

        public function __construct($rows) {
            $this->rows = $rows;
            $this->num_rows = count($rows);
        }

        public function fetch_assoc() {
            if ($this->index >= $this->num_rows) {
                return null;
            }
            $row = $this->rows[$this->index];
            $this->index++;
            return $row;
        }
    }
}

if (!function_exists('stmt_get_result')) {
    function stmt_get_result($stmt) {
        if (method_exists($stmt, 'get_result')) {
            $nativeResult = @$stmt->get_result();
            if ($nativeResult !== false) {
                return $nativeResult;
            }
        }

        $meta = $stmt->result_metadata();
        if ($meta === false) {
            return new StmtArrayResult(array());
        }

        $rowData = array();
        $bindVars = array();
        $fields = array();

        while ($field = $meta->fetch_field()) {
            $fields[] = $field->name;
            $rowData[$field->name] = null;
            $bindVars[] = &$rowData[$field->name];
        }

        call_user_func_array(array($stmt, 'bind_result'), $bindVars);

        $rows = array();
        while ($stmt->fetch()) {
            $current = array();
            foreach ($fields as $name) {
                $current[$name] = $rowData[$name];
            }
            $rows[] = $current;
        }

        return new StmtArrayResult($rows);
    }
}
?>
