<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/backup_utils.php';

$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'backup_scheduler.log';
$lockFile = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'backup_scheduler.lock';
$stampFile = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'backup_last_success_date.txt';
$context = 'script=executar_backup_agendado.php';
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

if (!is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'backups')) {
    @mkdir(__DIR__ . DIRECTORY_SEPARATOR . 'backups', 0775, true);
}

try {
    $lockHandle = @fopen($lockFile, 'c+');
    if ($lockHandle === false) {
        throw new RuntimeException('Nao foi possivel criar o lock do agendador de backup.');
    }

    if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
        $line = sprintf("[%s] SKIP | motivo=processo_em_execucao\n", $now);
        file_put_contents($logFile, $line, FILE_APPEND);
        fclose($lockHandle);
        exit(0);
    }

    $lastSuccessDate = '';
    if (is_file($stampFile)) {
        $lastSuccessDate = trim((string)@file_get_contents($stampFile));
    }

    if ($lastSuccessDate === $today) {
        $line = sprintf("[%s] SKIP | motivo=backup_ja_executado_no_dia\n", $now);
        file_put_contents($logFile, $line, FILE_APPEND);
        @flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        exit(0);
    }

    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        throw new RuntimeException('Conexao com banco indisponivel.');
    }

    $result = executarBackupBanco($conn);
    @file_put_contents($stampFile, $today);
    $line = sprintf(
        "[%s] OK | zip=%s | mail_sent=%s | password_sent=%s\n",
        $now,
        $result['zip_name'] ?? 'n/a',
        !empty($result['mail_sent']) ? '1' : '0',
        !empty($result['password_sent']) ? '1' : '0'
    );
    file_put_contents($logFile, $line, FILE_APPEND);
    @flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
} catch (Throwable $e) {
    $line = sprintf("[%s] ERROR | %s\n", $now, $e->getMessage());
    file_put_contents($logFile, $line, FILE_APPEND);
    backupDbSendFailureAlert($e->getMessage(), $context);
    exit(1);
}
