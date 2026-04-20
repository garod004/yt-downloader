<?php

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function backupDbEscapeIdentifier($value)
{
    return '`' . str_replace('`', '``', (string)$value) . '`';
}

function backupDbSqlValue($conn, $value)
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . $conn->real_escape_string((string)$value) . "'";
}

function backupDbCurrentName($conn)
{
    $result = $conn->query('SELECT DATABASE() AS db_name');
    if ($result && ($row = $result->fetch_assoc())) {
        return (string)($row['db_name'] ?? '');
    }

    return '';
}

function backupDbWriteSqlDump($conn, $sqlFilePath)
{
    $databaseName = backupDbCurrentName($conn);
    if ($databaseName === '') {
        throw new RuntimeException('Nao foi possivel identificar o banco de dados ativo.');
    }

    $handle = fopen($sqlFilePath, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Nao foi possivel criar o arquivo SQL do backup.');
    }

    $write = function ($content) use ($handle) {
        if (fwrite($handle, $content) === false) {
            throw new RuntimeException('Falha ao gravar o conteudo do backup.');
        }
    };

    try {
        $write("-- Backup gerado automaticamente\n");
        $write('-- Banco: ' . $databaseName . "\n");
        $write('-- Data: ' . date('Y-m-d H:i:s') . "\n\n");
        $write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        $write("SET time_zone = '+00:00';\n");
        $write("SET NAMES utf8mb4;\n");
        $write("SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $objects = $conn->query('SHOW FULL TABLES FROM ' . backupDbEscapeIdentifier($databaseName));
        if (!($objects instanceof mysqli_result)) {
            throw new RuntimeException('Nao foi possivel listar as tabelas do banco.');
        }

        while ($objectRow = $objects->fetch_row()) {
            $objectName = (string)($objectRow[0] ?? '');
            $objectType = strtoupper((string)($objectRow[1] ?? 'BASE TABLE'));
            if ($objectName === '') {
                continue;
            }

            if ($objectType === 'VIEW') {
                $createView = $conn->query('SHOW CREATE VIEW ' . backupDbEscapeIdentifier($objectName));
                if ($createView instanceof mysqli_result) {
                    $createRow = $createView->fetch_assoc();
                    if ($createRow) {
                        $createSql = '';
                        foreach ($createRow as $key => $value) {
                            if (stripos((string)$key, 'Create View') !== false) {
                                $createSql = (string)$value;
                                break;
                            }
                        }
                        if ($createSql !== '') {
                            $write('-- View: ' . $objectName . "\n");
                            $write('DROP VIEW IF EXISTS ' . backupDbEscapeIdentifier($objectName) . ";\n");
                            $write($createSql . ";\n\n");
                        }
                    }
                    $createView->close();
                }
                continue;
            }

            $createTable = $conn->query('SHOW CREATE TABLE ' . backupDbEscapeIdentifier($objectName));
            if (!($createTable instanceof mysqli_result)) {
                continue;
            }

            $createRow = $createTable->fetch_assoc();
            $createSql = '';
            if ($createRow) {
                foreach ($createRow as $key => $value) {
                    if (stripos((string)$key, 'Create Table') !== false) {
                        $createSql = (string)$value;
                        break;
                    }
                }
            }
            $createTable->close();

            if ($createSql === '') {
                continue;
            }

            $write('-- Tabela: ' . $objectName . "\n");
            $write('DROP TABLE IF EXISTS ' . backupDbEscapeIdentifier($objectName) . ";\n");
            $write($createSql . ";\n\n");

            $dataResult = $conn->query('SELECT * FROM ' . backupDbEscapeIdentifier($objectName));
            if (!($dataResult instanceof mysqli_result)) {
                $write("\n");
                continue;
            }

            $fields = $dataResult->fetch_fields();
            $columnNames = array();
            foreach ($fields as $field) {
                $columnNames[] = backupDbEscapeIdentifier($field->name);
            }

            $batch = array();
            while ($dataRow = $dataResult->fetch_assoc()) {
                $values = array();
                foreach ($fields as $field) {
                    $values[] = backupDbSqlValue($conn, array_key_exists($field->name, $dataRow) ? $dataRow[$field->name] : null);
                }
                $batch[] = '(' . implode(', ', $values) . ')';

                if (count($batch) >= 100) {
                    $write('INSERT INTO ' . backupDbEscapeIdentifier($objectName) . ' (' . implode(', ', $columnNames) . ') VALUES ' . implode(",\n", $batch) . ";\n");
                    $batch = array();
                }
            }

            if (!empty($batch)) {
                $write('INSERT INTO ' . backupDbEscapeIdentifier($objectName) . ' (' . implode(', ', $columnNames) . ') VALUES ' . implode(",\n", $batch) . ";\n");
            }

            $write("\n");
            $dataResult->close();
        }

        $write("SET FOREIGN_KEY_CHECKS = 1;\n");
    } catch (Throwable $e) {
        fclose($handle);
        throw $e;
    }

    fclose($handle);
    return $databaseName;
}

function backupDbCreateZip($sqlFilePath, $zipFilePath, $zipPassword = '')
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('A extensao ZipArchive nao esta disponivel no servidor.');
    }

    $zip = new ZipArchive();
    $status = $zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($status !== true) {
        throw new RuntimeException('Nao foi possivel criar o arquivo ZIP do backup.');
    }

    $entryName = basename($sqlFilePath);
    if (!$zip->addFile($sqlFilePath, $entryName)) {
        $zip->close();
        throw new RuntimeException('Nao foi possivel adicionar o SQL ao arquivo ZIP.');
    }

    if ($zipPassword !== '') {
        if (method_exists($zip, 'setPassword')) {
            $zip->setPassword($zipPassword);
        }
        if (method_exists($zip, 'setEncryptionName') && defined('ZipArchive::EM_AES_256')) {
            $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
        }
    }

    $zip->close();
}

function backupDbBuildMailer()
{
    $smtpHost = trim((string)env_value('SMTP_HOST', ''));
    $smtpPort = intval(env_value('SMTP_PORT', '587'));
    $smtpUser = trim((string)env_value('SMTP_USER', ''));
    $smtpPass = (string)env_value('SMTP_PASS', '');
    $smtpSecure = strtolower(trim((string)env_value('SMTP_SECURE', 'tls')));
    $smtpFromEmail = trim((string)env_value('SMTP_FROM_EMAIL', $smtpUser));
    $smtpFromName = trim((string)env_value('SMTP_FROM_NAME', 'Sistema de Backup'));
    $smtpAuthEnv = strtolower(trim((string)env_value('SMTP_AUTH', 'auto')));

    if ($smtpHost === '' || $smtpFromEmail === '') {
        throw new RuntimeException('SMTP nao configurado por completo.');
    }

    $mailer = new PHPMailer(true);

    $mailer->isSMTP();
    $mailer->Host = $smtpHost;
    $mailer->Port = $smtpPort > 0 ? $smtpPort : 587;
    $mailer->CharSet = 'UTF-8';
    $mailer->SMTPAutoTLS = true;

    if ($smtpAuthEnv === '0' || $smtpAuthEnv === 'false' || $smtpAuthEnv === 'nao') {
        $mailer->SMTPAuth = false;
    } elseif ($smtpAuthEnv === '1' || $smtpAuthEnv === 'true' || $smtpAuthEnv === 'sim') {
        $mailer->SMTPAuth = true;
    } else {
        $mailer->SMTPAuth = ($smtpUser !== '' || $smtpPass !== '');
    }

    if ($mailer->SMTPAuth) {
        $mailer->Username = $smtpUser;
        $mailer->Password = $smtpPass;
    }

    if ($smtpSecure === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtpSecure === 'tls') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mailer->SMTPSecure = false;
        $mailer->SMTPAutoTLS = false;
    }

    $mailer->setFrom($smtpFromEmail, $smtpFromName !== '' ? $smtpFromName : 'Sistema de Backup');

    return $mailer;
}

function backupDbGeneratePassword($length = 18)
{
    $length = max(12, intval($length));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';
    for ($index = 0; $index < $length; $index++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }
    return $password;
}

function backupDbSendEmail($zipFilePath, $generatedFileName, $zipPassword)
{
    $backupTo = trim((string)env_value('BACKUP_EMAIL_TO', ''));
    $passwordEmailTo = trim((string)env_value('BACKUP_PASSWORD_EMAIL_TO', $backupTo));

    if ($backupTo === '') {
        return array(
            'sent' => false,
            'password_sent' => false,
            'message' => 'Backup gerado localmente, mas o e-mail nao foi enviado porque BACKUP_EMAIL_TO nao esta configurado.',
        );
    }

    try {
        $mailer = backupDbBuildMailer();
        $mailer->addAddress($backupTo);
        $mailer->Subject = 'Backup do banco de dados - ' . date('d/m/Y H:i');
        $mailer->Body = "Segue em anexo o backup do banco de dados gerado em " . date('d/m/Y H:i:s') . ".\n\nArquivo: " . $generatedFileName;
        $mailer->addAttachment($zipFilePath, basename($zipFilePath));
        $mailer->send();

        $passwordSent = false;
        $passwordMessage = 'Arquivo criptografado enviado por e-mail com sucesso.';

        if ($zipPassword !== '' && $passwordEmailTo !== '') {
            $passwordMailer = backupDbBuildMailer();
            $passwordMailer->addAddress($passwordEmailTo);
            $passwordMailer->Subject = 'Senha do backup criptografado - ' . date('d/m/Y H:i');
            $passwordMailer->Body = "Senha para descompactar o arquivo " . $generatedFileName . ":\n\n" . $zipPassword . "\n\nGuarde esta senha em local seguro.";
            $passwordMailer->send();
            $passwordSent = true;
            $passwordMessage = 'Arquivo criptografado enviado por e-mail com sucesso. A senha foi enviada em uma mensagem separada.';
        }

        return array(
            'sent' => true,
            'password_sent' => $passwordSent,
            'message' => $passwordMessage,
        );
    } catch (MailException $e) {
        return array(
            'sent' => false,
            'password_sent' => false,
            'message' => 'Backup gerado localmente, mas houve falha no envio do e-mail: ' . $e->getMessage(),
        );
    }
}

function backupDbSendTestEmail()
{
    $backupTo = trim((string)env_value('BACKUP_EMAIL_TO', ''));
    if ($backupTo === '') {
        return array(
            'sent' => false,
            'message' => 'Nao foi possivel enviar o teste porque BACKUP_EMAIL_TO nao esta configurado.',
        );
    }

    try {
        $mailer = backupDbBuildMailer();
        $mailer->addAddress($backupTo);
        $mailer->Subject = 'Teste de envio SMTP - ' . date('d/m/Y H:i');
        $mailer->Body = "Este e um teste de envio SMTP do sistema Nobrega Previdencia.\n\nData e hora: " . date('d/m/Y H:i:s');
        $mailer->send();

        return array(
            'sent' => true,
            'message' => 'E-mail de teste enviado com sucesso para ' . $backupTo . '.',
        );
    } catch (Throwable $e) {
        return array(
            'sent' => false,
            'message' => 'Falha no teste de envio: ' . $e->getMessage(),
        );
    }
}

function backupDbSendFailureAlert($errorMessage, $context = '')
{
    $backupTo = trim((string)env_value('BACKUP_EMAIL_TO', ''));
    if ($backupTo === '') {
        return array(
            'sent' => false,
            'message' => 'BACKUP_EMAIL_TO nao configurado para alerta de falha.',
        );
    }

    try {
        $mailer = backupDbBuildMailer();
        $mailer->addAddress($backupTo);
        $mailer->Subject = 'ALERTA: Falha no backup automatico - ' . date('d/m/Y H:i');
        $body = "O backup automatico diario falhou.\n\n";
        $body .= 'Data e hora: ' . date('d/m/Y H:i:s') . "\n";
        $body .= 'Erro: ' . (string)$errorMessage . "\n";
        if ($context !== '') {
            $body .= 'Contexto: ' . (string)$context . "\n";
        }
        $mailer->Body = $body;
        $mailer->send();

        return array(
            'sent' => true,
            'message' => 'Alerta de falha enviado para ' . $backupTo . '.',
        );
    } catch (Throwable $e) {
        return array(
            'sent' => false,
            'message' => 'Falha ao enviar alerta de erro: ' . $e->getMessage(),
        );
    }
}

function backupDbGetLatestSchedulerStatus($baseDir)
{
    $logFile = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backup_scheduler.log';
    if (!is_file($logFile)) {
        return null;
    }

    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return null;
    }

    $lastLine = trim((string)$lines[count($lines) - 1]);
    $matches = array();
    if (!preg_match('/^\[(.*?)\]\s+(OK|ERROR)\s+\|\s+(.*)$/', $lastLine, $matches)) {
        return array(
            'raw' => $lastLine,
            'status' => 'UNKNOWN',
            'time' => null,
            'details' => $lastLine,
            'log_file' => $logFile,
        );
    }

    $time = strtotime($matches[1]);
    return array(
        'raw' => $lastLine,
        'status' => strtoupper((string)$matches[2]),
        'time' => $time !== false ? $time : null,
        'details' => trim((string)$matches[3]),
        'log_file' => $logFile,
    );
}

function backupDbGetDaysSinceLastError($baseDir)
{
    $logFile = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backup_scheduler.log';
    if (!is_file($logFile)) {
        return null;
    }

    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return null;
    }

    $lastErrorTime = null;
    foreach (array_reverse($lines) as $line) {
        $line = trim((string)$line);
        $matches = array();
        if (preg_match('/^\[(.*?)\]\s+ERROR/', $line, $matches)) {
            $t = strtotime($matches[1]);
            if ($t !== false) {
                $lastErrorTime = $t;
            }
            break;
        }
    }

    if ($lastErrorTime === null) {
        // No error ever logged - return days since first log line
        $firstLine = trim((string)$lines[0]);
        $m = array();
        if (preg_match('/^\[(.*?)\]/', $firstLine, $m)) {
            $t = strtotime($m[1]);
            if ($t !== false) {
                return (int)floor((time() - $t) / 86400);
            }
        }
        return null;
    }

    return (int)floor((time() - $lastErrorTime) / 86400);
}

function backupDbGetLatestBackupInfo($baseDir)
{
    $pattern = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backup_*.zip';
    $files = glob($pattern);
    if (!$files) {
        return null;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $latest = $files[0];
    return array(
        'file_name' => basename($latest),
        'file_path' => $latest,
        'file_time' => filemtime($latest),
        'file_size' => filesize($latest),
    );
}

function backupDbCleanup($baseDir, $keepFiles = 10)
{
    $keepFiles = max(1, intval($keepFiles));
    $files = glob(rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backup_*.zip');
    if (!$files) {
        return;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $toDelete = array_slice($files, $keepFiles);
    foreach ($toDelete as $file) {
        @unlink($file);
        $sqlPair = preg_replace('/\.zip$/i', '.sql', $file);
        if ($sqlPair && is_file($sqlPair)) {
            @unlink($sqlPair);
        }
    }
}

function executarBackupBanco($conn, $options = array())
{
    if (!($conn instanceof mysqli) || $conn->connect_error) {
        throw new RuntimeException('Conexao com o banco indisponivel para backup.');
    }

    $baseDir = isset($options['base_dir']) ? (string)$options['base_dir'] : (__DIR__ . DIRECTORY_SEPARATOR . 'backups');
    $keepFiles = isset($options['keep_files']) ? intval($options['keep_files']) : intval(env_value('BACKUP_KEEP_FILES', '10'));
    $zipPassword = isset($options['zip_password']) ? (string)$options['zip_password'] : (string)env_value('BACKUP_ZIP_PASSWORD', '');
    if ($zipPassword === '') {
        $zipPassword = backupDbGeneratePassword();
    }

    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        throw new RuntimeException('Nao foi possivel criar o diretorio de backups.');
    }

    $timestamp = date('Ymd_His');
    $sqlFileName = 'backup_' . $timestamp . '.sql';
    $zipFileName = 'backup_' . $timestamp . '.zip';
    $sqlFilePath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $sqlFileName;
    $zipFilePath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zipFileName;

    $databaseName = backupDbWriteSqlDump($conn, $sqlFilePath);
    backupDbCreateZip($sqlFilePath, $zipFilePath, $zipPassword);
    backupDbCleanup($baseDir, $keepFiles);
    $mailResult = backupDbSendEmail($zipFilePath, $zipFileName, $zipPassword);

    return array(
        'database_name' => $databaseName,
        'sql_file' => $sqlFilePath,
        'zip_file' => $zipFilePath,
        'zip_name' => $zipFileName,
        'mail_sent' => !empty($mailResult['sent']),
        'password_sent' => !empty($mailResult['password_sent']),
        'mail_message' => (string)($mailResult['message'] ?? ''),
    );
}
