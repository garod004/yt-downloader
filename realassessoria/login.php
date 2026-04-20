<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

function appPath($relativePath = '')
{
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($basePath === '' || $basePath === '.') {
        $basePath = '';
    }

    $relativePath = ltrim((string) $relativePath, '/');

    if ($relativePath === '') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    return ($basePath === '' ? '' : $basePath) . '/' . $relativePath;
}

function loginPagePath()
{
    return appPath('index.html');
}

function dashboardPagePath()
{
    return appPath('dashboard.php');
}

function senhaConfere($senhaInformada, $senhaBanco)
{
    $senhaInformada = (string) $senhaInformada;
    $senhaBanco = (string) $senhaBanco;

    if ($senhaBanco === '') {
        return false;
    }

    if (password_verify($senhaInformada, $senhaBanco)) {
        return true;
    }

    return hash_equals($senhaBanco, $senhaInformada);
}

function precisaRehashSenha($senhaBanco)
{
    $senhaBanco = (string) $senhaBanco;

    if ($senhaBanco === '') {
        return false;
    }

    $info = password_get_info($senhaBanco);
    if (($info['algo'] ?? null) === null || ($info['algo'] ?? 0) === 0) {
        return true;
    }

    return password_needs_rehash($senhaBanco, PASSWORD_DEFAULT);
}

function redirectWithError($message, $email = '')
{
    $query = http_build_query([
        'erro' => $message,
        'email' => $email,
    ]);
    header('Location: ' . loginPagePath() . ($query !== '' ? '?' . $query : ''));
    exit();
}

$loginPage = loginPagePath();
$dashboardPage = dashboardPagePath();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['usuario_id'])) {
    header('Location: ' . $dashboardPage);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $loginPage);
    exit();
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($email === '' || $senha === '') {
    redirectWithError('Informe seu e-mail e sua senha para continuar.', $email);
}

$conexaoPath = __DIR__ . '/conexao.php';
$logUtilsPath = __DIR__ . '/log_utils.php';

if (!file_exists($conexaoPath)) {
    error_log('Arquivo de conexao nao encontrado: ' . $conexaoPath);
    redirectWithError('Erro de configuracao do sistema.', $email);
}

require_once $conexaoPath;
if (file_exists($logUtilsPath)) {
    require_once $logUtilsPath;
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    error_log('Conexao indisponivel no login.');
    redirectWithError('Erro ao conectar ao banco de dados.', $email);
}

$sql = "SELECT id, nome, senha, is_admin, tipo_usuario FROM usuarios WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $sql = "SELECT id, nome, senha, is_admin FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log('Falha ao preparar SELECT usuario: ' . $conn->error);
        $conn->close();
        redirectWithError('Erro interno ao processar login.', $email);
    }

    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        error_log('Falha ao executar SELECT usuario: ' . $stmt->error);
        $stmt->close();
        $conn->close();
        redirectWithError('Erro interno ao processar login.', $email);
    }

    $stmt->store_result();
    $stmt->bind_result($usuarioId, $usuarioNome, $senhaHashBanco, $usuarioIsAdmin);
    $tipoUsuarioBanco = null;
} else {
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        error_log('Falha ao executar SELECT usuario: ' . $stmt->error);
        $stmt->close();
        $conn->close();
        redirectWithError('Erro interno ao processar login.', $email);
    }

    $stmt->store_result();
    $stmt->bind_result($usuarioId, $usuarioNome, $senhaHashBanco, $usuarioIsAdmin, $tipoUsuarioBanco);
}

if ($stmt->fetch()) {
    if (senhaConfere($senha, $senhaHashBanco)) {
        if (precisaRehashSenha($senhaHashBanco)) {
            $novoHashSenha = password_hash($senha, PASSWORD_DEFAULT);
            if ($novoHashSenha !== false) {
                $stmtAtualizaSenha = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id = ? LIMIT 1');
                if ($stmtAtualizaSenha) {
                    $stmtAtualizaSenha->bind_param('si', $novoHashSenha, $usuarioId);
                    $stmtAtualizaSenha->execute();
                    $stmtAtualizaSenha->close();
                }
            }
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuarioId;
        $_SESSION['usuario_nome'] = $usuarioNome;
        $_SESSION['is_admin'] = $usuarioIsAdmin;
        $_SESSION['tipo_usuario'] = $tipoUsuarioBanco ?: ($usuarioIsAdmin == 1 ? 'admin' : 'usuario');

        if (function_exists('registrar_log')) {
            registrar_log($conn, $usuarioNome, 'acesso', 'Login realizado com sucesso');
        }

        $stmt->close();
        $conn->close();
        header('Location: ' . $dashboardPage);
        exit();
    }

    $stmt->close();
    $conn->close();
    redirectWithError('Senha incorreta.', $email);
}

$stmt->close();
$conn->close();
redirectWithError('Usuario nao encontrado.', $email);
