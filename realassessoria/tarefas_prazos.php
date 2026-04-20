<?php
require_once __DIR__ . '/mvp_utils.php';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';

    if ($acao === 'atualizar_status') {
        $tarefa_id = intval($_POST['tarefa_id'] ?? 0);
        $novo_status = trim($_POST['novo_status'] ?? 'aberta');

        if ($tarefa_id > 0) {
            $sqlPerm = "SELECT t.id
                        FROM tarefas_prazos t
                        LEFT JOIN clientes c ON c.id = t.cliente_id
                        WHERE t.id = ?" . ($tipo_usuario === 'parceiro' ? " AND (c.usuario_cadastro_id = ? OR c.usuario_cadastro_id IS NULL OR c.id IS NULL)" : '');
            $stmtPerm = $conn->prepare($sqlPerm);
            if ($stmtPerm) {
                if ($tipo_usuario === 'parceiro') {
                    $stmtPerm->bind_param('ii', $tarefa_id, $usuario_logado_id);
                } else {
                    $stmtPerm->bind_param('i', $tarefa_id);
                }
                $stmtPerm->execute();
                $resultPerm = stmt_get_result($stmtPerm);
                $okPerm = $resultPerm && $resultPerm->num_rows > 0;
                $stmtPerm->close();

                if ($okPerm) {
                    $concluidaEm = ($novo_status === 'concluida') ? date('Y-m-d H:i:s') : null;
                    $sqlUp = "UPDATE tarefas_prazos SET status = ?, concluida_em = ? WHERE id = ?";
                    $stmtUp = $conn->prepare($sqlUp);
                    if ($stmtUp) {
                        $stmtUp->bind_param('ssi', $novo_status, $concluidaEm, $tarefa_id);
                        if ($stmtUp->execute()) {
                            $mensagem = 'Status da tarefa atualizado.';
                        } else {
                            $erro = 'Nao foi possivel atualizar o status.';
                        }
                        $stmtUp->close();
                    }
                } else {
                    $erro = 'Voce nao pode atualizar esta tarefa.';
                }
            }
        }
    } else {
        $cliente_id = intval($_POST['cliente_id'] ?? 0);
        $processo_id = intval($_POST['processo_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'tarefa');
        $prioridade = trim($_POST['prioridade'] ?? 'media');
        $data_vencimento = trim($_POST['data_vencimento'] ?? '');
        $hora_vencimento = trim($_POST['hora_vencimento'] ?? '');
        $usuario_responsavel_id = intval($_POST['usuario_responsavel_id'] ?? $usuario_logado_id);

        if ($titulo === '' || $data_vencimento === '') {
            $erro = 'Titulo e vencimento sao obrigatorios.';
        } elseif ($cliente_id > 0 && !mvpPodeAcessarCliente($conn, $cliente_id, $tipo_usuario, $usuario_logado_id, $is_admin)) {
            $erro = 'Cliente sem permissao.';
        } else {
            if (!$is_admin) {
                $usuario_responsavel_id = $usuario_logado_id;
            }

            $sqlIns = "INSERT INTO tarefas_prazos (cliente_id, processo_id, usuario_responsavel_id, usuario_cadastro_id, titulo, descricao, tipo, prioridade, status, data_vencimento, hora_vencimento)
                       VALUES (NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, ?, ?, 'aberta', ?, NULLIF(?, ''))";
            $stmtIns = $conn->prepare($sqlIns);
            if ($stmtIns) {
                $stmtIns->bind_param(
                    'iiiissssss',
                    $cliente_id,
                    $processo_id,
                    $usuario_responsavel_id,
                    $usuario_logado_id,
                    $titulo,
                    $descricao,
                    $tipo,
                    $prioridade,
                    $data_vencimento,
                    $hora_vencimento
                );

                if ($stmtIns->execute()) {
                    $mensagem = 'Tarefa/prazo cadastrado com sucesso.';
                } else {
                    $erro = 'Erro ao cadastrar tarefa: ' . $stmtIns->error;
                }
                $stmtIns->close();
            } else {
                $erro = 'Erro ao preparar tarefa.';
            }
        }
    }
}

$clientes = array();
$sqlClientes = "SELECT id, nome, usuario_cadastro_id FROM clientes WHERE 1=1" . mvpPermissaoClienteWhere('clientes', $tipo_usuario) . " ORDER BY nome ASC";
$stmtClientes = $conn->prepare($sqlClientes);
if ($stmtClientes) {
    if ($tipo_usuario === 'parceiro') {
        $stmtClientes->bind_param('i', $usuario_logado_id);
    }
    $stmtClientes->execute();
    $resultClientes = stmt_get_result($stmtClientes);
    while ($row = $resultClientes->fetch_assoc()) {
        $clientes[] = $row;
    }
    $stmtClientes->close();
}

$processos = array();
$sqlProcessos = "SELECT p.id, p.numero_processo, c.nome AS cliente_nome
                 FROM processos p
                 INNER JOIN clientes c ON c.id = p.cliente_id
                 WHERE 1=1" . mvpPermissaoClienteWhere('c', $tipo_usuario) . "
                 ORDER BY p.updated_at DESC LIMIT 200";
$stmtProcessos = $conn->prepare($sqlProcessos);
if ($stmtProcessos) {
    if ($tipo_usuario === 'parceiro') {
        $stmtProcessos->bind_param('i', $usuario_logado_id);
    }
    $stmtProcessos->execute();
    $resultProcessos = stmt_get_result($stmtProcessos);
    while ($row = $resultProcessos->fetch_assoc()) {
        $processos[] = $row;
    }
    $stmtProcessos->close();
}

$usuarios = array();
if ($is_admin) {
    $resUsuarios = $conn->query("SELECT id, nome FROM usuarios ORDER BY nome ASC");
    if ($resUsuarios) {
        while ($u = $resUsuarios->fetch_assoc()) {
            $usuarios[] = $u;
        }
    }
} else {
    $usuarios[] = array('id' => $usuario_logado_id, 'nome' => ($_SESSION['usuario_nome'] ?? 'Usuario atual'));
}

$listaTarefas = array();
$sqlTarefas = "SELECT t.id, t.titulo, t.tipo, t.prioridade, t.status, t.data_vencimento, t.hora_vencimento,
                      c.nome AS cliente_nome, c.telefone AS titular_telefone, p.numero_processo, u.nome AS responsavel_nome
               FROM tarefas_prazos t
               LEFT JOIN clientes c ON c.id = t.cliente_id
               LEFT JOIN processos p ON p.id = t.processo_id
               LEFT JOIN usuarios u ON u.id = t.usuario_responsavel_id
               WHERE 1=1" . ($tipo_usuario === 'parceiro' ? " AND (c.usuario_cadastro_id = ? OR c.usuario_cadastro_id IS NULL OR c.id IS NULL)" : '') . "
               ORDER BY t.data_vencimento ASC, t.hora_vencimento ASC, t.id DESC
               LIMIT 300";
$stmtTarefas = $conn->prepare($sqlTarefas);
if ($stmtTarefas) {
    if ($tipo_usuario === 'parceiro') {
        $stmtTarefas->bind_param('i', $usuario_logado_id);
    }
    $stmtTarefas->execute();
    $resultTarefas = stmt_get_result($stmtTarefas);
    while ($row = $resultTarefas->fetch_assoc()) {
        $listaTarefas[] = $row;
    }
    $stmtTarefas->close();
}

// Avaliações Sociais pendentes (não realizadas)
$listaAvaliacoes = array();
$sqlAv = "SELECT c.id, c.nome, c.telefone, c.data_avaliacao_social, c.hora_avaliacao_social
          FROM clientes c
          WHERE c.data_avaliacao_social IS NOT NULL AND c.data_avaliacao_social != '0000-00-00'
            AND c.data_avaliacao_social >= CURDATE()
            AND (c.realizado_a_s IS NULL OR c.realizado_a_s = 0)"
        . ($tipo_usuario === 'parceiro' ? " AND c.usuario_cadastro_id = ?" : '') .
        " ORDER BY c.data_avaliacao_social ASC LIMIT 200";
$stmtAv = $conn->prepare($sqlAv);
if ($stmtAv) {
    if ($tipo_usuario === 'parceiro') { $stmtAv->bind_param('i', $usuario_logado_id); }
    $stmtAv->execute();
    $resultAv = stmt_get_result($stmtAv);
    while ($row = $resultAv->fetch_assoc()) { $listaAvaliacoes[] = $row; }
    $stmtAv->close();
}

// Perícias INSS pendentes (não realizadas)
$listaPericias = array();
$sqlPer = "SELECT c.id, c.nome, c.telefone, c.data_pericia, c.hora_pericia
           FROM clientes c
           WHERE c.data_pericia IS NOT NULL AND c.data_pericia != '0000-00-00'
             AND c.data_pericia >= CURDATE()
             AND (c.realizado_pericia IS NULL OR c.realizado_pericia = 0)"
         . ($tipo_usuario === 'parceiro' ? " AND c.usuario_cadastro_id = ?" : '') .
         " ORDER BY c.data_pericia ASC LIMIT 200";
$stmtPer = $conn->prepare($sqlPer);
if ($stmtPer) {
    if ($tipo_usuario === 'parceiro') { $stmtPer->bind_param('i', $usuario_logado_id); }
    $stmtPer->execute();
    $resultPer = stmt_get_result($stmtPer);
    while ($row = $resultPer->fetch_assoc()) { $listaPericias[] = $row; }
    $stmtPer->close();
}

$hoje = date('Y-m-d');
$amanha = date('Y-m-d', strtotime('+1 day'));
$em3dias = date('Y-m-d', strtotime('+3 days'));

$totalConcluidas = 0;
$totalPendentes = 0;
$totalAtrasadas = 0;
$eventosCalendario = array();
$mesesDisponiveis = array();

foreach ($listaTarefas as $itemTarefa) {
    if (($itemTarefa['status'] ?? '') === 'concluida') {
        $totalConcluidas++;
    } else {
        $totalPendentes++;
        if (!empty($itemTarefa['data_vencimento']) && $itemTarefa['data_vencimento'] < $hoje) {
            $totalAtrasadas++;
        }
    }

    if (!empty($itemTarefa['data_vencimento'])) {
        $mesKey = substr($itemTarefa['data_vencimento'], 0, 7);
        if ($mesKey !== '') {
            $mesesDisponiveis[$mesKey] = true;
        }

        $eventosCalendario[] = array(
            'id' => (int)$itemTarefa['id'],
            'data' => $itemTarefa['data_vencimento'],
            'status' => $itemTarefa['status'],
            'titulo' => $itemTarefa['titulo']
        );
    }
}

// Avaliações Sociais no calendário
foreach ($listaAvaliacoes as $av) {
    if (!empty($av['data_avaliacao_social'])) {
        $mesKey = substr($av['data_avaliacao_social'], 0, 7);
        if ($mesKey !== '') { $mesesDisponiveis[$mesKey] = true; }
        $eventosCalendario[] = array(
            'id'     => 'av_' . (int)$av['id'],
            'data'   => $av['data_avaliacao_social'],
            'status' => 'avaliacao',
            'titulo' => 'Av. Social: ' . $av['nome']
        );
    }
}

// Perícias INSS no calendário
foreach ($listaPericias as $per) {
    if (!empty($per['data_pericia'])) {
        $mesKey = substr($per['data_pericia'], 0, 7);
        if ($mesKey !== '') { $mesesDisponiveis[$mesKey] = true; }
        $eventosCalendario[] = array(
            'id'     => 'per_' . (int)$per['id'],
            'data'   => $per['data_pericia'],
            'status' => 'pericia',
            'titulo' => 'Perícia INSS: ' . $per['nome']
        );
    }
}

$totalPainel = $totalConcluidas + $totalPendentes;
$taxaConcluidas = $totalPainel > 0 ? (int)round(($totalConcluidas * 100) / $totalPainel) : 0;
$taxaPendentes = $totalPainel > 0 ? 100 - $taxaConcluidas : 0;
$taxaAtrasadas = $totalPainel > 0 ? (int)round(($totalAtrasadas * 100) / $totalPainel) : 0;
$eventosCalendarioJson = json_encode($eventosCalendario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$mesAtualFiltro = date('Y-m');
$mesesDisponiveis[$mesAtualFiltro] = true;
$mesesChaves = array_keys($mesesDisponiveis);
rsort($mesesChaves);

$nomesMes = array(
    '01' => 'Janeiro',
    '02' => 'Fevereiro',
    '03' => 'Marco',
    '04' => 'Abril',
    '05' => 'Maio',
    '06' => 'Junho',
    '07' => 'Julho',
    '08' => 'Agosto',
    '09' => 'Setembro',
    '10' => 'Outubro',
    '11' => 'Novembro',
    '12' => 'Dezembro'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarefas e Prazos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif; margin: 0; background: #f1f5f9; color: #0f172a; }
        .topbar { background: #0b1220; color: #fff; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; box-shadow: 0 3px 10px rgba(15, 23, 42, 0.2); }
        .topbar-links { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .topbar a { color: #fff; text-decoration: none; padding: 6px 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.16); font-size: 9pt; }
        .topbar a:hover { background: rgba(255,255,255,0.2); }
        .topbar-toggle { display: none; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: #fff; padding: 6px 10px; border-radius: 8px; cursor: pointer; font-size: 9pt; font-weight: 700; }
        .topbar-toggle:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 16px auto; padding: 0 12px; }
        .card { background: #fff; border-radius: 8px; border: 1px solid #dbe3ef; padding: 14px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08); margin-bottom: 12px; }
        h2 { margin: 0 0 10px; font-size: 12pt; color: #0f172a; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        label { font-size: 9pt; display: block; margin-bottom: 4px; font-weight: 700; color: #334155; }
        input, select, textarea { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 9pt; font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18); }
        button { background: #0ea5e9; border: none; color: #fff; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 9pt; }
        th { background: #f8fafc; color: #334155; font-weight: 700; }
        tbody tr:hover { background: #f8fafc; }
        .ok { color: #065f46; background: #d1fae5; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
        .erro { color: #991b1b; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
        .tag { padding: 2px 8px; border-radius: 999px; font-size: 9pt; font-weight: 600; }
        .alerta-atraso { background: #fee2e2; color: #991b1b; }
        .alerta-hoje { background: #fef3c7; color: #92400e; }
        .alerta-proximo { background: #dbeafe; color: #1d4ed8; }
        .alerta-ok { background: #dcfce7; color: #166534; }
        .visual-board { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: stretch; }
        .kpi-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
        .kpi-box { border: 1px solid #dbe3ef; border-radius: 10px; padding: 10px; background: #f8fafc; }
        .kpi-label { font-size: 9pt; color: #334155; font-weight: 700; margin-bottom: 4px; }
        .kpi-value { font-size: 14pt; font-weight: 700; }
        .kpi-value.ok { color: #166534; }
        .kpi-value.pending { color: #b45309; }
        .kpi-value.overdue { color: #b91c1c; }
        .stage-list { display: grid; gap: 10px; }
        .stage-item { display: grid; gap: 4px; }
        .stage-head { display: flex; justify-content: space-between; align-items: center; font-size: 9pt; color: #334155; font-weight: 700; }
        .stage-track { height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .stage-fill { height: 100%; border-radius: 999px; }
        .stage-fill.ok { background: linear-gradient(90deg, #22c55e, #16a34a); }
        .stage-fill.pending { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stage-fill.overdue { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .calendar-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .calendar-title { font-size: 10pt; font-weight: 700; color: #0f172a; }
        .calendar-nav { display: flex; gap: 8px; }
        .calendar-nav button { padding: 6px 10px; background: #0f172a; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
        .calendar-weekday { font-size: 8pt; color: #64748b; text-align: center; font-weight: 700; padding: 3px 0; }
        .calendar-day { min-height: 52px; border: 1px solid #dbe3ef; border-radius: 8px; background: #fff; padding: 6px; display: flex; flex-direction: column; justify-content: space-between; gap: 6px; }
        .calendar-day.empty { background: #f8fafc; border-style: dashed; }
        .calendar-number { font-size: 9pt; font-weight: 700; color: #334155; }
        .calendar-signals { display: flex; gap: 4px; flex-wrap: wrap; }
        .signal { width: 8px; height: 8px; border-radius: 999px; display: inline-block; }
        .signal.pending { background: #f59e0b; }
        .signal.ok { background: #16a34a; }
        .calendar-legend { display: flex; gap: 12px; align-items: center; margin-top: 10px; font-size: 8.5pt; color: #475569; flex-wrap: wrap; }
        .legend-item { display: inline-flex; align-items: center; gap: 6px; }
        .acoes-wrap { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-whatsapp { background: #22c55e; color: #fff; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 8.5pt; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .btn-whatsapp.disabled { background: #94a3b8; pointer-events: none; }
        .agenda-toolbar { display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
        .agenda-toolbar label { margin: 0; }
        .agenda-toolbar select { width: auto; min-width: 220px; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 12px; }
        .modal-backdrop.open { display: flex; }
        .modal-box { width: min(460px, 100%); background: #fff; border-radius: 10px; border: 1px solid #dbe3ef; box-shadow: 0 14px 28px rgba(15, 23, 42, 0.2); padding: 14px; }
        .modal-title { font-size: 11pt; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .modal-text { font-size: 9pt; color: #334155; line-height: 1.45; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-primary-whatsapp { background: #16a34a; }
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
            .grid { grid-template-columns: 1fr; }
            .visual-board { grid-template-columns: 1fr; }
            .kpi-row { grid-template-columns: 1fr; }
            .agenda-toolbar { justify-content: flex-start; }
            .agenda-toolbar select { width: 100%; min-width: 0; }
        }
        .signal.avaliacao { background: #f97316; }
        .signal.pericia   { background: #dc2626; }
        .alerta-amanha    { background: #dc2626; color: #fff; }
        tr.linha-amanha   { background: #fee2e2 !important; }
        tr.linha-amanha td { font-weight: 700; color: #991b1b; }
        .tag-tipo-agend { padding: 2px 8px; border-radius: 999px; font-size: 8.5pt; font-weight: 600; }
        .tag-tipo-avaliacao { background: #fff7ed; color: #c2410c; }
        .tag-tipo-pericia   { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="topbar">
        <div><i class="fas fa-calendar-check"></i> Tarefas e Prazos</div>
        <div class="topbar-links">
            <a href="dashboard.php" title="Dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="processos.php" title="Processos"><i class="fas fa-gavel"></i> <span>Processos</span></a>
            <a href="financeiro_resumo.php" title="Financeiro"><i class="fas fa-wallet"></i> <span>Financeiro</span></a>
            <a href="logout.php" title="Sair"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a>
        </div>
        <button type="button" class="topbar-toggle" id="topbarToggle" title="Expandir ou recolher menu mobile">
            <i class="fas fa-align-justify" id="topbarToggleIcon"></i>
            <span id="topbarToggleLabel">Expandir menu</span>
        </button>
    </div>

    <div class="container">
        <div class="card">
            <h2>Novo prazo/tarefa</h2>
            <?php if ($mensagem): ?><div class="ok"><?php echo htmlspecialchars($mensagem); ?></div><?php endif; ?>
            <?php if ($erro): ?><div class="erro"><?php echo htmlspecialchars($erro); ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="acao" value="criar">
                <div class="grid">
                    <div>
                        <label for="titulo">Titulo</label>
                        <input id="titulo" name="titulo" maxlength="160" required>
                    </div>
                    <div>
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo">
                            <option value="tarefa">Tarefa</option>
                            <option value="prazo">Prazo</option>
                            <option value="audiencia">Audiencia</option>
                            <option value="protocolo">Protocolo</option>
                        </select>
                    </div>
                    <div>
                        <label for="prioridade">Prioridade</label>
                        <select id="prioridade" name="prioridade">
                            <option value="baixa">Baixa</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                            <option value="critica">Critica</option>
                        </select>
                    </div>
                    <div>
                        <label for="usuario_responsavel_id">Responsavel</label>
                        <select id="usuario_responsavel_id" name="usuario_responsavel_id">
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="cliente_id">Cliente (opcional)</label>
                        <select id="cliente_id" name="cliente_id">
                            <option value="0">Sem cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo (int)$cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="processo_id">Processo (opcional)</label>
                        <select id="processo_id" name="processo_id">
                            <option value="0">Sem processo</option>
                            <?php foreach ($processos as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['numero_processo'] . ' - ' . $p['cliente_nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="data_vencimento">Data de vencimento</label>
                        <input type="date" id="data_vencimento" name="data_vencimento" required>
                    </div>
                    <div>
                        <label for="hora_vencimento">Hora</label>
                        <input type="time" id="hora_vencimento" name="hora_vencimento">
                    </div>
                </div>

                <div style="margin-top: 12px;">
                    <label for="descricao">Descricao</label>
                    <textarea id="descricao" name="descricao" rows="3"></textarea>
                </div>

                <div style="margin-top: 12px;">
                    <button type="submit">Salvar prazo/tarefa</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Painel de etapas</h2>
            <div class="visual-board">
                <div>
                    <div class="kpi-row">
                        <div class="kpi-box">
                            <div class="kpi-label">Tarefas realizadas</div>
                            <div class="kpi-value ok"><?php echo (int)$totalConcluidas; ?></div>
                        </div>
                        <div class="kpi-box">
                            <div class="kpi-label">Tarefas pendentes</div>
                            <div class="kpi-value pending"><?php echo (int)$totalPendentes; ?></div>
                        </div>
                        <div class="kpi-box">
                            <div class="kpi-label">Tarefas atrasadas</div>
                            <div class="kpi-value overdue"><?php echo (int)$totalAtrasadas; ?></div>
                        </div>
                    </div>
                    <div class="stage-list">
                        <div class="stage-item">
                            <div class="stage-head">
                                <span>Etapa concluida</span>
                                <span><?php echo (int)$taxaConcluidas; ?>%</span>
                            </div>
                            <div class="stage-track"><div class="stage-fill ok" style="width: <?php echo (int)$taxaConcluidas; ?>%;"></div></div>
                        </div>
                        <div class="stage-item">
                            <div class="stage-head">
                                <span>Etapa pendente</span>
                                <span><?php echo (int)$taxaPendentes; ?>%</span>
                            </div>
                            <div class="stage-track"><div class="stage-fill pending" style="width: <?php echo (int)$taxaPendentes; ?>%;"></div></div>
                        </div>
                        <div class="stage-item">
                            <div class="stage-head">
                                <span>Etapa atrasada</span>
                                <span><?php echo (int)$taxaAtrasadas; ?>%</span>
                            </div>
                            <div class="stage-track"><div class="stage-fill overdue" style="width: <?php echo (int)$taxaAtrasadas; ?>%;"></div></div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="calendar-header">
                        <div class="calendar-title" id="calendarTitle">Calendario</div>
                        <div class="calendar-nav">
                            <button type="button" id="calendarPrev"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" id="calendarNext"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="calendar-grid" id="calendarGrid"></div>
                    <div class="calendar-legend">
                        <span class="legend-item"><span class="signal ok"></span> Concluída</span>
                        <span class="legend-item"><span class="signal pending"></span> Pendente</span>
                        <span class="legend-item"><span class="signal avaliacao"></span> Av. Social</span>
                        <span class="legend-item"><span class="signal pericia"></span> Perícia INSS</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Agenda e alertas</h2>
            <div class="agenda-toolbar">
                <label for="filtroMes">Mes da agenda</label>
                <select id="filtroMes" name="filtroMes">
                    <option value="todos">Todos os meses</option>
                    <?php foreach ($mesesChaves as $mesKey): ?>
                        <?php
                            $anoMes = explode('-', $mesKey);
                            $mesNum = $anoMes[1] ?? '';
                            $anoNum = $anoMes[0] ?? '';
                            $nomeMesAtual = $nomesMes[$mesNum] ?? $mesKey;
                        ?>
                        <option value="<?php echo htmlspecialchars($mesKey); ?>" <?php echo ($mesKey === $mesAtualFiltro ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($nomeMesAtual . ' / ' . $anoNum); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Titulo</th>
                            <th>Tipo</th>
                            <th>Prioridade</th>
                            <th>Cliente</th>
                            <th>Processo</th>
                            <th>Responsavel</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Alerta</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listaTarefas)): ?>
                            <tr><td colspan="11">Nenhuma tarefa cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($listaTarefas as $t): ?>
                                <?php
                                    $alertClass = 'alerta-ok';
                                    $alertText = 'No prazo';
                                    if ($t['status'] === 'concluida') {
                                        $alertText = 'Concluida';
                                    } elseif ($t['data_vencimento'] < $hoje) {
                                        $alertClass = 'alerta-atraso';
                                        $alertText = 'Atrasada';
                                    } elseif ($t['data_vencimento'] === $hoje) {
                                        $alertClass = 'alerta-hoje';
                                        $alertText = 'Vence hoje';
                                    } elseif ($t['data_vencimento'] <= $em3dias) {
                                        $alertClass = 'alerta-proximo';
                                        $alertText = 'Vence em breve';
                                    }
                                ?>
                                <tr data-mes="<?php echo htmlspecialchars(substr((string)$t['data_vencimento'], 0, 7)); ?>">
                                    <td><?php echo (int)$t['id']; ?></td>
                                    <td><?php echo htmlspecialchars($t['titulo']); ?></td>
                                    <td><?php echo htmlspecialchars($t['tipo']); ?></td>
                                    <td><?php echo htmlspecialchars($t['prioridade']); ?></td>
                                    <td><?php echo htmlspecialchars($t['cliente_nome'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($t['numero_processo'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($t['responsavel_nome'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(mvpDateBr($t['data_vencimento']) . ($t['hora_vencimento'] ? ' ' . substr($t['hora_vencimento'], 0, 5) : '')); ?></td>
                                    <td><?php echo htmlspecialchars($t['status']); ?></td>
                                    <td><span class="tag <?php echo $alertClass; ?>"><?php echo htmlspecialchars($alertText); ?></span></td>
                                    <td>
                                        <?php
                                            $telefoneWhatsapp = preg_replace('/\D+/', '', (string)($t['titular_telefone'] ?? ''));
                                            if (strlen($telefoneWhatsapp) === 10 || strlen($telefoneWhatsapp) === 11) {
                                                $telefoneWhatsapp = '55' . $telefoneWhatsapp;
                                            }
                                            $mensagemWhatsapp = rawurlencode(
                                                'Ola, ' . ($t['cliente_nome'] ?: 'titular') .
                                                '! Lembrete da tarefa: "' . $t['titulo'] .
                                                '". Vencimento: ' . mvpDateBr($t['data_vencimento']) .
                                                ($t['hora_vencimento'] ? ' ' . substr($t['hora_vencimento'], 0, 5) : '') .
                                                '. Status atual: ' . $t['status'] . '.'
                                            );
                                            $linkWhatsapp = $telefoneWhatsapp !== '' ? ('https://wa.me/' . $telefoneWhatsapp . '?text=' . $mensagemWhatsapp) : '';
                                        ?>
                                        <div class="acoes-wrap">
                                            <?php if ($t['status'] !== 'concluida'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="acao" value="atualizar_status">
                                                    <input type="hidden" name="tarefa_id" value="<?php echo (int)$t['id']; ?>">
                                                    <input type="hidden" name="novo_status" value="concluida">
                                                    <button type="submit" style="background:#16a34a;">Concluir</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="acao" value="atualizar_status">
                                                    <input type="hidden" name="tarefa_id" value="<?php echo (int)$t['id']; ?>">
                                                    <input type="hidden" name="novo_status" value="aberta">
                                                    <button type="submit" style="background:#f59e0b;">Reabrir</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($linkWhatsapp !== ''): ?>
                                                <button
                                                    type="button"
                                                    class="btn-whatsapp js-whatsapp-alert"
                                                    data-whatsapp-link="<?php echo htmlspecialchars($linkWhatsapp); ?>"
                                                    data-whatsapp-nome="<?php echo htmlspecialchars((string)($t['cliente_nome'] ?: 'titular')); ?>"
                                                    data-whatsapp-titulo="<?php echo htmlspecialchars((string)$t['titulo']); ?>"
                                                >
                                                    <i class="fab fa-whatsapp"></i> Alertar titular
                                                </button>
                                            <?php else: ?>
                                                <span class="btn-whatsapp disabled"><i class="fab fa-whatsapp"></i> Sem telefone</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Avaliações Sociais e Perícias INSS -->
        <div class="card" id="card-agendamentos-inss">
            <h2><i class="fas fa-hospital"></i> Avaliações Sociais e Perícias INSS Pendentes</h2>
            <?php
                $agendamentosInss = array();
                foreach ($listaAvaliacoes as $av) {
                    $agendamentosInss[] = array(
                        'tipo'       => 'Avaliação Social',
                        'tipo_class' => 'avaliacao',
                        'nome'       => $av['nome'],
                        'telefone'   => $av['telefone'],
                        'data'       => $av['data_avaliacao_social'],
                        'hora'       => $av['hora_avaliacao_social'],
                    );
                }
                foreach ($listaPericias as $per) {
                    $agendamentosInss[] = array(
                        'tipo'       => 'Perícia INSS',
                        'tipo_class' => 'pericia',
                        'nome'       => $per['nome'],
                        'telefone'   => $per['telefone'],
                        'data'       => $per['data_pericia'],
                        'hora'       => $per['hora_pericia'],
                    );
                }
                usort($agendamentosInss, function($a, $b) { return strcmp($a['data'], $b['data']); });
            ?>
            <?php if (empty($agendamentosInss)): ?>
                <p style="color:#64748b; font-size:9pt; margin:4px 0;">Nenhum agendamento pendente.</p>
            <?php else: ?>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Hora</th>
                            <th>Alerta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agendamentosInss as $ag): ?>
                        <?php
                            $agAlertClass = 'alerta-ok';
                            $agAlertText  = 'No prazo';
                            $agLinhaClass = '';
                            if ($ag['data'] < $hoje) {
                                $agAlertClass = 'alerta-atraso';
                                $agAlertText  = 'Atrasado';
                            } elseif ($ag['data'] === $hoje) {
                                $agAlertClass = 'alerta-hoje';
                                $agAlertText  = 'Hoje!';
                            } elseif ($ag['data'] === $amanha) {
                                $agAlertClass = 'alerta-amanha';
                                $agAlertText  = 'AMANHÃ!';
                                $agLinhaClass = ' linha-amanha';
                            } elseif ($ag['data'] <= $em3dias) {
                                $agAlertClass = 'alerta-proximo';
                                $agAlertText  = 'Em breve';
                            }
                        ?>
                        <tr class="linha-agendamento<?php echo $agLinhaClass; ?>" data-mes="<?php echo htmlspecialchars(substr((string)$ag['data'], 0, 7)); ?>">
                            <td><span class="tag-tipo-agend tag-tipo-<?php echo htmlspecialchars($ag['tipo_class']); ?>"><?php echo htmlspecialchars($ag['tipo']); ?></span></td>
                            <td><?php echo htmlspecialchars($ag['nome']); ?></td>
                            <td><?php echo htmlspecialchars(mvpDateBr($ag['data'])); ?></td>
                            <td><?php echo htmlspecialchars(!empty($ag['hora']) ? substr($ag['hora'], 0, 5) : '-'); ?></td>
                            <td><span class="tag <?php echo $agAlertClass; ?>"><?php echo htmlspecialchars($agAlertText); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-backdrop" id="whatsappModal" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="whatsappModalTitle">
            <div class="modal-title" id="whatsappModalTitle">Confirmar disparo do alerta</div>
            <div class="modal-text" id="whatsappModalText">Voce deseja abrir o WhatsApp para enviar o alerta?</div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="whatsappCancel">Cancelar</button>
                <button type="button" class="btn-primary-whatsapp" id="whatsappConfirm">
                    <i class="fab fa-whatsapp"></i> Abrir WhatsApp
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const eventosCalendario = <?php echo $eventosCalendarioJson ? $eventosCalendarioJson : '[]'; ?>;

            const calendarGrid = document.getElementById('calendarGrid');
            const calendarTitle = document.getElementById('calendarTitle');
            const calendarPrev = document.getElementById('calendarPrev');
            const calendarNext = document.getElementById('calendarNext');
            const filtroMes = document.getElementById('filtroMes');
            const filtroMesKey = 'mvpAgendaFiltroMes';

            // Controle de versão dos filtros salvos. Ao incrementar MVP_FILTROS_VERSION
            // (ex.: 'v1' -> 'v2') todos os filtros do sistema são limpos no primeiro acesso.
            const MVP_FILTROS_VERSION     = 'v1';
            const MVP_FILTROS_VERSION_KEY = 'mvpFiltrosVersion';
            try {
                if (localStorage.getItem(MVP_FILTROS_VERSION_KEY) !== MVP_FILTROS_VERSION) {
                    ['mvpAgendaFiltroMes', 'mvpProcessosFiltroCliente',
                     'mvpFinanceiroResumoFiltros', 'mvpClientesFiltros'].forEach(function (k) {
                        localStorage.removeItem(k);
                    });
                    localStorage.setItem(MVP_FILTROS_VERSION_KEY, MVP_FILTROS_VERSION);
                }
            } catch (e) { /* Ignora falha de armazenamento. */ }

            const whatsappModal = document.getElementById('whatsappModal');
            const whatsappModalText = document.getElementById('whatsappModalText');
            const whatsappCancel = document.getElementById('whatsappCancel');
            const whatsappConfirm = document.getElementById('whatsappConfirm');

            const monthNames = ['Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            const weekNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
            let pendingWhatsappLink = '';

            const mapEventos = {};
            eventosCalendario.forEach(function (ev) {
                if (!ev || !ev.data) {
                    return;
                }
                if (!mapEventos[ev.data]) {
                    mapEventos[ev.data] = { pendente: 0, concluida: 0, avaliacao: 0, pericia: 0 };
                }
                if (ev.status === 'concluida') {
                    mapEventos[ev.data].concluida += 1;
                } else if (ev.status === 'avaliacao') {
                    mapEventos[ev.data].avaliacao += 1;
                } else if (ev.status === 'pericia') {
                    mapEventos[ev.data].pericia += 1;
                } else {
                    mapEventos[ev.data].pendente += 1;
                }
            });

            let currentDate = new Date();

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function toYmd(dateObj) {
                return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate());
            }

            function toYm(dateObj) {
                return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1);
            }

            function existeOpcaoFiltro(valor) {
                if (!filtroMes) {
                    return false;
                }
                for (let i = 0; i < filtroMes.options.length; i += 1) {
                    if (filtroMes.options[i].value === valor) {
                        return true;
                    }
                }
                return false;
            }

            function salvarFiltroMes(valor) {
                try {
                    localStorage.setItem(filtroMesKey, valor);
                } catch (e) {
                    // Ignora falha de armazenamento.
                }
            }

            function aplicarMesNoCalendario(valorMes) {
                if (!valorMes || valorMes === 'todos') {
                    return;
                }
                const parts = valorMes.split('-');
                if (parts.length !== 2) {
                    return;
                }
                const y = parseInt(parts[0], 10);
                const m = parseInt(parts[1], 10);
                if (!isNaN(y) && !isNaN(m) && m >= 1 && m <= 12) {
                    currentDate = new Date(y, m - 1, 1);
                }
            }

            function restaurarFiltroMesSalvo() {
                if (!filtroMes) {
                    return;
                }
                let salvo = '';
                try {
                    salvo = localStorage.getItem(filtroMesKey) || '';
                } catch (e) {
                    salvo = '';
                }

                if (salvo !== '' && existeOpcaoFiltro(salvo)) {
                    filtroMes.value = salvo;
                    aplicarMesNoCalendario(salvo);
                }
            }

            function aplicaFiltroTabela() {
                if (!filtroMes) {
                    return;
                }
                const valor = filtroMes.value;
                const linhas = document.querySelectorAll('tbody tr[data-mes]');
                linhas.forEach(function (linha) {
                    const mesLinha = linha.getAttribute('data-mes') || '';
                    const exibir = valor === 'todos' || valor === mesLinha;
                    linha.style.display = exibir ? '' : 'none';
                });
            }

            function sincronizaFiltroComCalendario() {
                if (!filtroMes) {
                    return;
                }

                const mesAtual = toYm(currentDate);
                let optionExiste = existeOpcaoFiltro(mesAtual);

                if (optionExiste) {
                    filtroMes.value = mesAtual;
                    salvarFiltroMes(mesAtual);
                    aplicaFiltroTabela();
                }
            }

            function renderCalendar() {
                if (!calendarGrid || !calendarTitle) {
                    return;
                }

                calendarGrid.innerHTML = '';

                weekNames.forEach(function (w) {
                    const wd = document.createElement('div');
                    wd.className = 'calendar-weekday';
                    wd.textContent = w;
                    calendarGrid.appendChild(wd);
                });

                const y = currentDate.getFullYear();
                const m = currentDate.getMonth();
                calendarTitle.textContent = monthNames[m] + ' de ' + y;

                const firstDay = new Date(y, m, 1).getDay();
                const daysInMonth = new Date(y, m + 1, 0).getDate();

                for (let i = 0; i < firstDay; i += 1) {
                    const empty = document.createElement('div');
                    empty.className = 'calendar-day empty';
                    calendarGrid.appendChild(empty);
                }

                for (let d = 1; d <= daysInMonth; d += 1) {
                    const itemDate = new Date(y, m, d);
                    const keyDate = toYmd(itemDate);
                    const marks = mapEventos[keyDate] || { pendente: 0, concluida: 0 };

                    const day = document.createElement('div');
                    day.className = 'calendar-day';
                    day.title = 'Pendentes: ' + marks.pendente + ' | Concluidas: ' + marks.concluida;

                    const num = document.createElement('div');
                    num.className = 'calendar-number';
                    num.textContent = String(d);
                    day.appendChild(num);

                    const sig = document.createElement('div');
                    sig.className = 'calendar-signals';

                    if (marks.concluida > 0) {
                        const ok = document.createElement('span');
                        ok.className = 'signal ok';
                        sig.appendChild(ok);
                    }
                    if (marks.pendente > 0) {
                        const pending = document.createElement('span');
                        pending.className = 'signal pending';
                        sig.appendChild(pending);
                    }
                    if (marks.avaliacao > 0) {
                        const av = document.createElement('span');
                        av.className = 'signal avaliacao';
                        av.title = marks.avaliacao + ' Av. Social';
                        sig.appendChild(av);
                    }
                    if (marks.pericia > 0) {
                        const per = document.createElement('span');
                        per.className = 'signal pericia';
                        per.title = marks.pericia + ' Perícia INSS';
                        sig.appendChild(per);
                    }

                    day.appendChild(sig);
                    calendarGrid.appendChild(day);
                }
            }

            if (calendarPrev) {
                calendarPrev.addEventListener('click', function () {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                    renderCalendar();
                    sincronizaFiltroComCalendario();
                });
            }

            if (calendarNext) {
                calendarNext.addEventListener('click', function () {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                    renderCalendar();
                    sincronizaFiltroComCalendario();
                });
            }

            if (filtroMes) {
                filtroMes.addEventListener('change', function () {
                    aplicarMesNoCalendario(filtroMes.value);
                    renderCalendar();
                    salvarFiltroMes(filtroMes.value);
                    aplicaFiltroTabela();
                });
            }

            document.addEventListener('click', function (ev) {
                const target = ev.target;
                if (!target) {
                    return;
                }
                const btnWhatsapp = target.closest ? target.closest('.js-whatsapp-alert') : null;
                if (!btnWhatsapp) {
                    return;
                }

                const link = btnWhatsapp.getAttribute('data-whatsapp-link') || '';
                const nome = btnWhatsapp.getAttribute('data-whatsapp-nome') || 'titular';
                const titulo = btnWhatsapp.getAttribute('data-whatsapp-titulo') || 'tarefa';
                pendingWhatsappLink = link;

                if (whatsappModalText) {
                    whatsappModalText.textContent = 'Deseja abrir o WhatsApp para enviar o alerta da tarefa "' + titulo + '" para ' + nome + '?';
                }

                if (whatsappModal) {
                    whatsappModal.classList.add('open');
                    whatsappModal.setAttribute('aria-hidden', 'false');
                }
            });

            function fecharModalWhatsapp() {
                pendingWhatsappLink = '';
                if (whatsappModal) {
                    whatsappModal.classList.remove('open');
                    whatsappModal.setAttribute('aria-hidden', 'true');
                }
            }

            if (whatsappCancel) {
                whatsappCancel.addEventListener('click', function () {
                    fecharModalWhatsapp();
                });
            }

            if (whatsappModal) {
                whatsappModal.addEventListener('click', function (ev) {
                    if (ev.target === whatsappModal) {
                        fecharModalWhatsapp();
                    }
                });
            }

            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && whatsappModal && whatsappModal.classList.contains('open')) {
                    fecharModalWhatsapp();
                }
            });

            if (whatsappConfirm) {
                whatsappConfirm.addEventListener('click', function () {
                    if (pendingWhatsappLink) {
                        window.open(pendingWhatsappLink, '_blank', 'noopener,noreferrer');
                    }
                    fecharModalWhatsapp();
                });
            }

            // Alerta automático para compromissos amanhã
            (function () {
                const hoje2 = new Date();
                const amanha2 = new Date(hoje2);
                amanha2.setDate(amanha2.getDate() + 1);
                const pad = function (n) { return String(n).padStart(2, '0'); };
                const amanhaStr = amanha2.getFullYear() + '-' + pad(amanha2.getMonth() + 1) + '-' + pad(amanha2.getDate());

                const avalAmanha = eventosCalendario.filter(function (ev) { return ev.data === amanhaStr && ev.status === 'avaliacao'; });
                const perAmanha  = eventosCalendario.filter(function (ev) { return ev.data === amanhaStr && ev.status === 'pericia'; });

                const msgs = [];
                if (avalAmanha.length > 0) {
                    msgs.push('\uD83D\uDCCB ' + avalAmanha.length + ' Avalia\u00e7\u00e3o(ões) Social(is) AMANHÃ:\n' +
                        avalAmanha.map(function (e) { return '  \u2022 ' + e.titulo; }).join('\n'));
                }
                if (perAmanha.length > 0) {
                    msgs.push('\uD83C\uDFE5 ' + perAmanha.length + ' Per\u00edcia(s) INSS AMANHÃ:\n' +
                        perAmanha.map(function (e) { return '  \u2022 ' + e.titulo; }).join('\n'));
                }
                if (msgs.length > 0) {
                    setTimeout(function () {
                        alert('\u26A0\uFE0F ATEN\u00c7\u00c3O \u2014 Compromissos para AMANH\u00c3!\n\n' + msgs.join('\n\n'));
                    }, 600);
                }
            })();

            restaurarFiltroMesSalvo();
            renderCalendar();
            aplicaFiltroTabela();

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
    </script>
</body>
</html>
