<?php
require_once __DIR__ . '/mvp_utils.php';
require_once __DIR__ . '/log_utils.php';

if (!$is_admin) {
    header('Location: dashboard.php');
    exit();
}

$config_empresa = array(
    'empresa_nome' => '',
    'empresa_cnpj' => '',
    'empresa_fone' => '',
    'empresa_email' => '',
    'empresa_proprietarios' => '',
);
$mensagem_config = '';
$erro_config = '';
$mensagem_backup = '';
$erro_backup = '';
$detalhe_backup = '';
$aviso_backup = '';
$mensagem_teste_email = '';
$erro_teste_email = '';
$ultimo_backup_info = null;
$ultimo_backup_agendado = null;
$dias_sem_falha = null;
$forcar_aba_config = false;

$sqlCfgTable = "CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    chave VARCHAR(100) NOT NULL PRIMARY KEY,
    valor TEXT NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$conn->query($sqlCfgTable);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config_sistema'])) {
    $forcar_aba_config = true;
    $config_empresa['empresa_nome'] = isset($_POST['empresa_nome']) ? trim((string)$_POST['empresa_nome']) : '';
    $config_empresa['empresa_cnpj'] = isset($_POST['empresa_cnpj']) ? trim((string)$_POST['empresa_cnpj']) : '';
    $config_empresa['empresa_fone'] = isset($_POST['empresa_fone']) ? trim((string)$_POST['empresa_fone']) : '';
    $config_empresa['empresa_email'] = isset($_POST['empresa_email']) ? trim((string)$_POST['empresa_email']) : '';
    $config_empresa['empresa_proprietarios'] = isset($_POST['empresa_proprietarios']) ? trim((string)$_POST['empresa_proprietarios']) : '';

    if (mb_strlen($config_empresa['empresa_nome'], 'UTF-8') > 150) {
        $erro_config = 'O nome da empresa deve ter no maximo 150 caracteres.';
    } elseif (mb_strlen($config_empresa['empresa_cnpj'], 'UTF-8') > 30) {
        $erro_config = 'O CNPJ deve ter no maximo 30 caracteres.';
    } elseif (mb_strlen($config_empresa['empresa_fone'], 'UTF-8') > 30) {
        $erro_config = 'O fone de contato deve ter no maximo 30 caracteres.';
    } elseif ($config_empresa['empresa_email'] !== '' && !filter_var($config_empresa['empresa_email'], FILTER_VALIDATE_EMAIL)) {
        $erro_config = 'Informe um e-mail valido.';
    } elseif (mb_strlen($config_empresa['empresa_email'], 'UTF-8') > 150) {
        $erro_config = 'O e-mail deve ter no maximo 150 caracteres.';
    } elseif (mb_strlen($config_empresa['empresa_proprietarios'], 'UTF-8') > 500) {
        $erro_config = 'O campo de proprietarios deve ter no maximo 500 caracteres.';
    } else {
        $stmtSaveCfg = $conn->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        if ($stmtSaveCfg) {
            $chave_cfg = '';
            $valor_cfg = '';
            $stmtSaveCfg->bind_param('ss', $chave_cfg, $valor_cfg);

            $ok_salvar = true;
            foreach ($config_empresa as $chave_cfg_item => $valor_cfg_item) {
                $chave_cfg = $chave_cfg_item;
                $valor_cfg = $valor_cfg_item;
                if (!$stmtSaveCfg->execute()) {
                    $ok_salvar = false;
                    break;
                }
            }

            if ($ok_salvar) {
                $mensagem_config = 'Nome da empresa salvo com sucesso.';
                $mensagem_config = 'Configuracoes da empresa salvas com sucesso.';
                // Log alteração de configuração
                if (isset($_SESSION['usuario_nome'])) {
                    registrar_log($conn, $_SESSION['usuario_nome'], 'alteracao', 'Alterou configurações do sistema');
                }
            } else {
                $erro_config = 'Nao foi possivel salvar a configuracao. Tente novamente.';
            }
            $stmtSaveCfg->close();
        } else {
            $erro_config = 'Nao foi possivel preparar o salvamento da configuracao.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['executar_backup_sistema']) || isset($_POST['testar_email_backup']))) {
    $forcar_aba_config = true;
    require_once __DIR__ . '/backup_utils.php';

    if (isset($_POST['executar_backup_sistema'])) {
        try {
            $resultadoBackup = executarBackupBanco($conn, array(
                'base_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'backups',
            ));

            $mensagem_backup = 'Backup gerado com sucesso: ' . $resultadoBackup['zip_name'];
            $detalhe_backup = $resultadoBackup['mail_message'];
            if (!empty($resultadoBackup['password_sent'])) {
                $aviso_backup = 'A senha para abrir o arquivo criptografado foi enviada em e-mail separado.';
            }
        } catch (Throwable $e) {
            $erro_backup = 'Nao foi possivel gerar o backup: ' . $e->getMessage();
        }
    }

    if (isset($_POST['testar_email_backup'])) {
        $resultadoTesteEmail = backupDbSendTestEmail();
        if (!empty($resultadoTesteEmail['sent'])) {
            $mensagem_teste_email = $resultadoTesteEmail['message'];
        } else {
            $erro_teste_email = $resultadoTesteEmail['message'];
        }
    }

    $ultimo_backup_info = backupDbGetLatestBackupInfo(__DIR__ . DIRECTORY_SEPARATOR . 'backups');
}

if (!function_exists('formatarTamanhoArquivoPainel')) {
    function formatarTamanhoArquivoPainel($bytes)
    {
        $bytes = max(0, (int)$bytes);
        $units = array('B', 'KB', 'MB', 'GB');
        $power = $bytes > 0 ? (int)floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $value = $bytes / pow(1024, $power);
        return number_format($value, $power === 0 ? 0 : 2, ',', '.') . ' ' . $units[$power];
    }
}

if ($ultimo_backup_info === null) {
    require_once __DIR__ . '/backup_utils.php';
    $ultimo_backup_info = backupDbGetLatestBackupInfo(__DIR__ . DIRECTORY_SEPARATOR . 'backups');
}

if ($ultimo_backup_agendado === null) {
    require_once __DIR__ . '/backup_utils.php';
    $ultimo_backup_agendado = backupDbGetLatestSchedulerStatus(__DIR__ . DIRECTORY_SEPARATOR . 'backups');
    $dias_sem_falha = backupDbGetDaysSinceLastError(__DIR__ . DIRECTORY_SEPARATOR . 'backups');
}

if ($config_empresa['empresa_nome'] === '' && $config_empresa['empresa_cnpj'] === '' && $config_empresa['empresa_fone'] === '' && $config_empresa['empresa_email'] === '' && $config_empresa['empresa_proprietarios'] === '') {
    $stmtLoadCfg = $conn->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('empresa_nome', 'empresa_cnpj', 'empresa_fone', 'empresa_email', 'empresa_proprietarios')");
    if ($stmtLoadCfg) {
        $stmtLoadCfg->execute();
        $resCfg = stmt_get_result($stmtLoadCfg);
        if ($resCfg) {
            while ($rowCfg = $resCfg->fetch_assoc()) {
                $chave = (string)($rowCfg['chave'] ?? '');
                if (array_key_exists($chave, $config_empresa)) {
                    $config_empresa[$chave] = (string)($rowCfg['valor'] ?? '');
                }
            }
        }
        $stmtLoadCfg->close();
    }
}

$kpi = array(
    'usuarios' => 0,
    'clientes' => 0,
    'processos' => 0,
    'tarefas_abertas' => 0,
    'tarefas_atrasadas' => 0,
    'financeiro_pendente' => 0.0,
);

$res = $conn->query("SELECT COUNT(*) AS total FROM usuarios");
if ($res && $row = $res->fetch_assoc()) {
    $kpi['usuarios'] = intval($row['total']);
}

$res = $conn->query("SELECT COUNT(*) AS total FROM clientes");
if ($res && $row = $res->fetch_assoc()) {
    $kpi['clientes'] = intval($row['total']);
}

$res = $conn->query("SELECT COUNT(*) AS total FROM processos");
if ($res && $row = $res->fetch_assoc()) {
    $kpi['processos'] = intval($row['total']);
}

$res = $conn->query("SELECT COUNT(*) AS total FROM tarefas_prazos WHERE status <> 'concluida'");
if ($res && $row = $res->fetch_assoc()) {
    $kpi['tarefas_abertas'] = intval($row['total']);
}

$hoje = date('Y-m-d');
$stmtAtraso = $conn->prepare("SELECT COUNT(*) AS total FROM tarefas_prazos WHERE status <> 'concluida' AND data_vencimento < ?");
if ($stmtAtraso) {
    $stmtAtraso->bind_param('s', $hoje);
    $stmtAtraso->execute();
    $resultAtraso = stmt_get_result($stmtAtraso);
    if ($resultAtraso && $row = $resultAtraso->fetch_assoc()) {
        $kpi['tarefas_atrasadas'] = intval($row['total']);
    }
    $stmtAtraso->close();
}

$resPend = $conn->query("SELECT SUM(COALESCE(valor_parcela,0) * COALESCE(parcelas_faltantes,0)) AS pendente FROM financeiro");
if ($resPend && $row = $resPend->fetch_assoc()) {
    $kpi['financeiro_pendente'] = floatval($row['pendente'] ?? 0);
}

$ultimosUsuarios = array();
$resUltimos = $conn->query("SELECT id, nome, email, tipo_usuario FROM usuarios ORDER BY id DESC LIMIT 10");
if ($resUltimos) {
    while ($u = $resUltimos->fetch_assoc()) {
        $ultimosUsuarios[] = $u;
    }
}

function moedaBr($valor)
{
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif; margin: 0; background: #f1f5f9; color: #0f172a; }
        .topbar { background: #0b1220; color: #fff; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; box-shadow: 0 3px 10px rgba(15, 23, 42, 0.2); }
        .topbar-title { font-size: 11pt; font-weight: 700; letter-spacing: 0.2px; }
        .topbar-links { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .topbar a { color: #fff; text-decoration: none; padding: 5px 9px; border-radius: 7px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.16); font-size: 8.5pt; font-weight: 700; }
        .topbar a:hover { background: rgba(255,255,255,0.2); }
        .topbar-toggle { display: none; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: #fff; padding: 6px 10px; border-radius: 8px; cursor: pointer; font-size: 9pt; font-weight: 700; }
        .topbar-toggle:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 12px auto; padding: 0 10px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 10px; }
        .card { background: #fff; border-radius: 8px; border: 1px solid #dbe3ef; border-top-width: 3px; padding: 10px 12px; box-shadow: 0 3px 12px rgba(15, 23, 42, 0.07); }
        .card:nth-child(1) { border-top-color: #3b82f6; }
        .card:nth-child(2) { border-top-color: #22c55e; }
        .card:nth-child(3) { border-top-color: #8b5cf6; }
        .card:nth-child(4) { border-top-color: #f59e0b; }
        .card:nth-child(5) { border-top-color: #ef4444; }
        .card:nth-child(6) { border-top-color: #0f766e; }
        .label { font-size: 8pt; color: #475569; margin-bottom: 3px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.25px; }
        .value { font-size: 13pt; font-weight: 700; line-height: 1.1; }
        .admin-tabs { margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; }
        .admin-tab-btn {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 8.5pt;
            font-weight: 700;
            cursor: pointer;
        }
        .admin-tab-btn.active {
            border-color: #0b1220;
            background: #0b1220;
            color: #ffffff;
        }
        .admin-tab-panel { display: none; }
        .admin-tab-panel.active { display: block; }
        .box { margin-top: 10px; background: #fff; border-radius: 8px; border: 1px solid #dbe3ef; padding: 12px; box-shadow: 0 3px 12px rgba(15, 23, 42, 0.07); }
        .box h2 { margin: 0 0 8px; font-size: 10.5pt; color: #0f172a; }
        .config-help { margin: 0 0 8px; color: #334155; font-size: 9pt; line-height: 1.45; }
        .config-form { display: grid; gap: 8px 10px; max-width: 760px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .config-form label { font-size: 8pt; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.25px; }
        .config-form input[type="text"],
        .config-form input[type="email"],
        .config-form textarea {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 9pt;
            color: #0f172a;
            background: #f8fafc;
        }
        .config-form textarea { resize: vertical; min-height: 70px; grid-column: 1 / -1; }
        .config-form .field-full { grid-column: 1 / -1; }
        .config-form button {
            width: fit-content;
            border: 1px solid #0b1220;
            background: #0b1220;
            color: #ffffff;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 8.5pt;
            font-weight: 700;
            cursor: pointer;
        }
        .config-form button:hover { filter: brightness(1.1); }
        .config-feedback { margin: 0; font-size: 9pt; font-weight: 700; }
        .config-feedback.ok { color: #166534; }
        .config-feedback.err { color: #b91c1c; }
        .backup-box {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        .backup-box h3 {
            margin: 0 0 6px;
            font-size: 9.5pt;
            color: #0f172a;
        }
        .backup-help {
            margin: 0 0 8px;
            color: #475569;
            font-size: 8.8pt;
            line-height: 1.45;
        }
        .backup-form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .backup-form button {
            width: fit-content;
            border: 1px solid #0b1220;
            background: #0b1220;
            color: #ffffff;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 8.5pt;
            font-weight: 700;
            cursor: pointer;
        }
        .backup-form button:hover { filter: brightness(1.1); }
        .backup-form .secondary-btn {
            background: #ffffff;
            color: #0b1220;
            border-color: #cbd5e1;
        }
        .backup-grid {
            display: grid;
            gap: 8px;
        }
        .backup-meta {
            margin: 0;
            font-size: 8.5pt;
            color: #334155;
        }
        .backup-note {
            margin: 0;
            font-size: 8.5pt;
            color: #166534;
            font-weight: 700;
        }
        .backup-last {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .backup-last strong {
            color: #0f172a;
        }
        .backup-status-ok {
            color: #166534;
            font-weight: 700;
        }
        .backup-status-error {
            color: #b91c1c;
            font-weight: 700;
        }
        .backup-days-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .backup-days-badge.good {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .backup-days-badge.warn {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .backup-days-badge.none {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .backup-env-list {
            margin: 8px 0 0;
            padding: 0;
            list-style: none;
            color: #475569;
            font-size: 8.4pt;
            line-height: 1.5;
        }
        .lgpd-box {
            margin-top: 12px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 9px 10px;
            color: #334155;
            font-size: 9pt;
            line-height: 1.45;
        }
        .site-footer {
            margin: 14px auto 16px;
            max-width: 1200px;
            padding: 0 10px;
            color: #64748b;
            font-size: 8.5pt;
            text-align: center;
        }
        .table-wrap { overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; background: #ffffff; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 8.5pt; }
        th { background: #eff5fb; color: #334155; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2px; font-size: 7.8pt; }
        tbody tr:nth-child(even) { background: #fbfdff; }
        tbody tr:hover { background: #f1f7ff; }
        @media (max-width: 768px) {
            .topbar-toggle { display: inline-flex; align-items: center; gap: 6px; }
            .topbar-links a {
                width: 34px;
                height: 34px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .topbar-links a span { display: none; }

            body.mobile-topbar-expanded .topbar-links a {
                width: auto;
                height: auto;
                padding: 6px 10px;
            }
            body.mobile-topbar-expanded .topbar-links a span { display: inline; }
            .cards { grid-template-columns: 1fr; }
            .config-form { grid-template-columns: 1fr; }
            .config-form textarea,
            .config-form .field-full { grid-column: auto; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-user-shield"></i> Painel Administrativo</div>
        <div class="topbar-links">
            <a href="dashboard.php" title="Dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="listar_usuarios.php" title="Usuarios"><i class="fas fa-user-shield"></i> <span>Usuarios</span></a>
            <a href="#" title="Configuracoes" data-admin-tab-link="configuracoes"><i class="fas fa-cogs"></i> <span>Configuracoes</span></a>
            <a href="cadastro_advogados.php" title="Advogados"><i class="fas fa-gavel"></i> <span>Advogados</span></a>
            <a href="logout.php" title="Sair"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a>
        </div>
        <button type="button" class="topbar-toggle" id="topbarToggle" title="Expandir ou recolher menu mobile">
            <i class="fas fa-align-justify" id="topbarToggleIcon"></i>
            <span id="topbarToggleLabel">Expandir menu</span>
        </button>
    </div>

    <div class="container">
        <div class="cards">
            <div class="card"><div class="label">Usuarios</div><div class="value"><?php echo (int)$kpi['usuarios']; ?></div></div>
            <div class="card"><div class="label">Clientes</div><div class="value"><?php echo (int)$kpi['clientes']; ?></div></div>
            <div class="card"><div class="label">Processos</div><div class="value"><?php echo (int)$kpi['processos']; ?></div></div>
            <div class="card"><div class="label">Tarefas em aberto</div><div class="value"><?php echo (int)$kpi['tarefas_abertas']; ?></div></div>
            <div class="card"><div class="label">Tarefas atrasadas</div><div class="value"><?php echo (int)$kpi['tarefas_atrasadas']; ?></div></div>
            <div class="card"><div class="label">Financeiro pendente</div><div class="value"><?php echo htmlspecialchars(moedaBr($kpi['financeiro_pendente'])); ?></div></div>
        </div>

        <div class="admin-tabs">
            <button type="button" class="admin-tab-btn active" data-admin-tab="resumo">Resumo</button>
            <button type="button" class="admin-tab-btn" data-admin-tab="configuracoes">Configuracoes do Sistema</button>
            <button type="button" class="admin-tab-btn" data-admin-tab="controle_acesso">Controle de Acesso</button>
        </div>

        <div class="admin-tab-panel active" data-admin-tab-panel="resumo">
            <div class="box">
                <h2>Ultimos usuarios cadastrados</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Perfil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimosUsuarios)): ?>
                                <tr><td colspan="4">Nenhum usuario encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ultimosUsuarios as $u): ?>
                                    <tr>
                                        <td><?php echo (int)$u['id']; ?></td>
                                        <td><?php echo htmlspecialchars($u['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['tipo_usuario'] ?: 'usuario'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="admin-tab-panel" data-admin-tab-panel="configuracoes">
            <div class="box">
                <h2>Configuracoes do Sistema</h2>
                <p class="config-help">Cadastre os dados da sua empresa para uso interno do sistema.</p>
                <form method="post" class="config-form">
                    <label for="empresa_nome">Nome da Empresa</label>
                    <input type="text" id="empresa_nome" name="empresa_nome" maxlength="150" value="<?php echo htmlspecialchars($config_empresa['empresa_nome'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Digite o nome da empresa">

                    <label for="empresa_cnpj">CNPJ da Empresa</label>
                    <input type="text" id="empresa_cnpj" name="empresa_cnpj" maxlength="30" value="<?php echo htmlspecialchars($config_empresa['empresa_cnpj'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Digite o CNPJ">

                    <label for="empresa_fone">Fone de Contato</label>
                    <input type="text" id="empresa_fone" name="empresa_fone" maxlength="30" value="<?php echo htmlspecialchars($config_empresa['empresa_fone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Digite o fone de contato">

                    <label for="empresa_email">E-mail da Empresa</label>
                    <input type="email" id="empresa_email" name="empresa_email" maxlength="150" value="<?php echo htmlspecialchars($config_empresa['empresa_email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Digite o e-mail da empresa">

                    <label for="empresa_proprietarios" class="field-full">Proprietarios da Empresa</label>
                    <textarea id="empresa_proprietarios" name="empresa_proprietarios" maxlength="500" placeholder="Informe os proprietarios da empresa"><?php echo htmlspecialchars($config_empresa['empresa_proprietarios'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                    <button type="submit" name="salvar_config_sistema" value="1" class="field-full">Salvar configuracoes</button>
                    <?php if ($mensagem_config !== ''): ?>
                        <p class="config-feedback ok field-full"><?php echo htmlspecialchars($mensagem_config, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if ($erro_config !== ''): ?>
                        <p class="config-feedback err field-full"><?php echo htmlspecialchars($erro_config, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </form>

                <div class="backup-box">
                    <h3>Backup do Banco de Dados</h3>
                    <p class="backup-help">Gera um arquivo SQL, compacta em ZIP na pasta protegida <strong>backups</strong> e tenta enviar o arquivo por SMTP para o e-mail configurado.</p>
                    <p class="backup-help">Quando o ZIP estiver protegido por senha, a senha sera enviada em uma segunda mensagem de e-mail separada.</p>
                    <div class="backup-grid">
                        <form method="post" class="backup-form">
                            <button type="submit" name="executar_backup_sistema" value="1">Gerar backup agora</button>
                            <button type="submit" name="testar_email_backup" value="1" class="secondary-btn">Testar envio de e-mail</button>
                            <p class="backup-meta">Destino local: backups/</p>
                        </form>
                        <?php if ($ultimo_backup_info): ?>
                            <div class="backup-last">
                                <p class="backup-meta"><strong>Ultimo backup:</strong> <?php echo htmlspecialchars($ultimo_backup_info['file_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="backup-meta"><strong>Data:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i:s', (int)$ultimo_backup_info['file_time']), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="backup-meta"><strong>Tamanho:</strong> <?php echo htmlspecialchars(formatarTamanhoArquivoPainel($ultimo_backup_info['file_size']), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="backup-last">
                                <p class="backup-meta"><strong>Ultimo backup:</strong> nenhum arquivo encontrado ainda.</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($dias_sem_falha !== null): ?>
                            <div class="backup-last" style="display:flex;align-items:center;gap:10px;">
                                <span style="font-size:8.5pt;color:#334155;"><strong>Dias sem falha:</strong></span>
                                <span class="backup-days-badge <?php echo $dias_sem_falha >= 1 ? 'good' : 'warn'; ?>">
                                    <?php echo $dias_sem_falha === 0 ? 'Menos de 1 dia' : $dias_sem_falha . ($dias_sem_falha === 1 ? ' dia' : ' dias'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ($ultimo_backup_agendado): ?>
                            <div class="backup-last">
                                <p class="backup-meta"><strong>Ultimo backup automatico:</strong>
                                    <span class="<?php echo $ultimo_backup_agendado['status'] === 'OK' ? 'backup-status-ok' : 'backup-status-error'; ?>">
                                        <?php echo htmlspecialchars($ultimo_backup_agendado['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </p>
                                <p class="backup-meta"><strong>Data:</strong> <?php echo htmlspecialchars($ultimo_backup_agendado['time'] ? date('d/m/Y H:i:s', (int)$ultimo_backup_agendado['time']) : 'nao identificada', ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="backup-meta"><strong>Detalhes:</strong> <?php echo htmlspecialchars((string)$ultimo_backup_agendado['details'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="backup-last">
                                <p class="backup-meta"><strong>Ultimo backup automatico:</strong> sem execucao registrada ainda.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($ultimo_backup_agendado && $ultimo_backup_agendado['status'] === 'ERROR'): ?>
                        <p class="config-feedback err">Atencao: o ultimo backup automatico falhou. Verifique o log em backups/backup_scheduler.log.</p>
                    <?php endif; ?>
                    <?php if ($mensagem_backup !== ''): ?>
                        <p class="config-feedback ok"><?php echo htmlspecialchars($mensagem_backup, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if ($detalhe_backup !== ''): ?>
                        <p class="backup-meta"><?php echo htmlspecialchars($detalhe_backup, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if ($aviso_backup !== ''): ?>
                        <p class="backup-note"><?php echo htmlspecialchars($aviso_backup, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if ($mensagem_teste_email !== ''): ?>
                        <p class="config-feedback ok"><?php echo htmlspecialchars($mensagem_teste_email, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if ($erro_teste_email !== ''): ?>
                        <p class="config-feedback err"><?php echo htmlspecialchars($erro_teste_email, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if ($erro_backup !== ''): ?>
                        <p class="config-feedback err"><?php echo htmlspecialchars($erro_backup, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <ul class="backup-env-list">
                        <li>SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS</li>
                        <li>SMTP_FROM_EMAIL, SMTP_FROM_NAME, SMTP_SECURE</li>
                        <li>BACKUP_EMAIL_TO para o destinatario do backup</li>
                        <li>BACKUP_KEEP_FILES para quantidade de backups mantidos</li>
                        <li>BACKUP_ZIP_PASSWORD opcional para proteger o ZIP</li>
                    </ul>
                </div>

                <div class="lgpd-box">
                    Este sistema opera em conformidade com a Lei Geral de Protecao de Dados (LGPD - Lei no 13.709/2018),
                    adotando medidas para protecao, privacidade e tratamento adequado dos dados pessoais.
                </div>
            </div>
        </div>

        <div class="admin-tab-panel" data-admin-tab-panel="controle_acesso">
            <div class="box">
                <h2>Controle de Acesso</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>USUÁRIO</th>
                                <th>ID REGISTRO</th>
                                <th>DATA/HORA</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $logs = array();
                        $resLogs = $conn->query("SELECT usuario, id_alterado, data_hora FROM controle_acesso ORDER BY data_hora DESC LIMIT 100");
                        if ($resLogs) {
                            while ($log = $resLogs->fetch_assoc()) {
                                $logs[] = $log;
                            }
                        }
                        if (empty($logs)) {
                            echo '<tr><td colspan="6">Nenhum registro encontrado.</td></tr>';
                        } else {
                            foreach ($logs as $log) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($log['usuario']) . '</td>';
                                echo '<td>' . htmlspecialchars($log['id_alterado'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['data_hora']))) . '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">Dioleno N. Silva - Todos os direitos reservados</footer>

    <script>
        (function () {
            const key = 'mvpTopbarExpanded';
            const btn = document.getElementById('topbarToggle');
            const icon = document.getElementById('topbarToggleIcon');
            const label = document.getElementById('topbarToggleLabel');
            if (!btn || !icon || !label) {
                return;
            }

            function setState(expanded) {
                document.body.classList.toggle('mobile-topbar-expanded', expanded);
                icon.className = expanded ? 'fas fa-compress-alt' : 'fas fa-align-justify';
                label.textContent = expanded ? 'Recolher menu' : 'Expandir menu';
                try {
                    localStorage.setItem(key, expanded ? '1' : '0');
                } catch (e) {
                    // Ignora falha de armazenamento.
                }
            }

            btn.addEventListener('click', function () {
                const expanded = !document.body.classList.contains('mobile-topbar-expanded');
                setState(expanded);
            });

            let initial = false;
            try {
                initial = localStorage.getItem(key) === '1';
            } catch (e) {
                initial = false;
            }
            setState(initial);
        })();

        (function () {
            const buttons = Array.from(document.querySelectorAll('[data-admin-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-admin-tab-panel]'));
            const quickLink = document.querySelector('[data-admin-tab-link="configuracoes"]');

            if (!buttons.length || !panels.length) {
                return;
            }

            function activate(tabName) {
                buttons.forEach(function (btn) {
                    btn.classList.toggle('active', btn.getAttribute('data-admin-tab') === tabName);
                });
                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.getAttribute('data-admin-tab-panel') === tabName);
                });
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activate(btn.getAttribute('data-admin-tab'));
                });
            });

            if (quickLink) {
                quickLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    activate('configuracoes');
                });
            }

            activate('<?php echo $forcar_aba_config ? 'configuracoes' : 'resumo'; ?>');
        })();
    </script>
</body>
</html>
