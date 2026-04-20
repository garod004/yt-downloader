<?php
session_start();

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

session_unset();
session_destroy();

// Limpar cookie de sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Redirecionar para login
header("Location: " . appPath('index.html'));
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
exit();
?>
