<?php
require_once __DIR__ . '/mvp_utils.php';

if ($tipo_usuario === 'usuario') {
    header('Location: dashboard.php');
    exit();
}

function numeroSeguro($valor)
{
    if ($valor === null) {
        return 0.0;
    }

    if (is_int($valor) || is_float($valor)) {
        return (float)$valor;
    }

    $texto = trim((string)$valor);
    if ($texto === '') {
        return 0.0;
    }

    $texto = str_ireplace('R$', '', $texto);
    $texto = preg_replace('/\s+/', '', $texto);

    // Aceita formatos: 1234.56, 1.234,56, 1234,56
    if (strpos($texto, ',') !== false && strpos($texto, '.') !== false) {
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    } elseif (strpos($texto, ',') !== false) {
        $texto = str_replace(',', '.', $texto);
    }

    return (float)$texto;
}

function obterValorParcelaBase(array $row)
{
    $valor = numeroSeguro($row['valor_parcela'] ?? 0);
    if ($valor > 0) {
        return $valor;
    }

    for ($i = 1; $i <= 24; $i++) {
        $campo = 'parcela' . $i;
        $parcela = numeroSeguro($row[$campo] ?? 0);
        if ($parcela > 0) {
            return $parcela;
        }
    }

    $qtd = numeroSeguro($row['qtd_parcelas'] ?? 0);
    $pago = numeroSeguro($row['pago'] ?? 0);
    if ($qtd > 0 && $pago > 0) {
        return $pago / $qtd;
    }

    return 0.0;
}

function obterQtdParcelasBase(array $row, $valorParcela)
{
    $qtd = numeroSeguro($row['qtd_parcelas'] ?? 0);
    if ($qtd > 0) {
        return $qtd;
    }

    $pagas = numeroSeguro($row['parcelas_pagas'] ?? 0);
    $faltantes = numeroSeguro($row['parcelas_faltantes'] ?? 0);
    if (($pagas + $faltantes) > 0) {
        return $pagas + $faltantes;
    }

    $countParcelas = 0;
    for ($i = 1; $i <= 24; $i++) {
        $campo = 'parcela' . $i;
        if (numeroSeguro($row[$campo] ?? 0) > 0) {
            $countParcelas++;
        }
    }
    if ($countParcelas > 0) {
        return (float)$countParcelas;
    }

    $pago = numeroSeguro($row['pago'] ?? 0);
    if ($valorParcela > 0 && $pago > 0) {
        return $pago / $valorParcela;
    }

    return 0.0;
}

function obterParcelasFaltantesBase(array $row, $qtdParcelas)
{
    $faltantes = numeroSeguro($row['parcelas_faltantes'] ?? 0);
    if ($faltantes > 0) {
        return $faltantes;
    }

    $pagas = numeroSeguro($row['parcelas_pagas'] ?? 0);
    if ($qtdParcelas > 0) {
        $calc = $qtdParcelas - $pagas;
        return $calc > 0 ? $calc : 0.0;
    }

    return 0.0;
}

$totais = array(
    'clientes_com_financeiro' => 0,
    'total_previsto' => 0.0,
    'total_recebido' => 0.0,
    'total_pendente' => 0.0,
);

$sqlResumo = "SELECT c.id AS cliente_id, c.situacao, f.*
              FROM financeiro f
              INNER JOIN clientes c ON c.id = f.cliente_id
              WHERE 1=1" . mvpPermissaoClienteWhere('c', $tipo_usuario);
$stmtResumo = $conn->prepare($sqlResumo);
if ($stmtResumo) {
    if ($tipo_usuario === 'parceiro') {
        $stmtResumo->bind_param('i', $usuario_logado_id);
    }
    $stmtResumo->execute();
    $resultResumo = stmt_get_result($stmtResumo);
    // Contabilizar clientes únicos com status PAGANDO
    $clientesPagando = array();
    while ($row = $resultResumo->fetch_assoc()) {
        $statusCliente = trim((string)($row['situacao'] ?? ''));
        $clienteId = (int)($row['cliente_id'] ?? $row['id'] ?? 0);
        if (mb_strtolower(str_replace([' ', '_'], '', $statusCliente)) === 'pagando' && $clienteId > 0) {
            $clientesPagando[$clienteId] = true;
        }
        $valorParcela = obterValorParcelaBase($row);
        $qtdParcelas = obterQtdParcelasBase($row, $valorParcela);
        $faltantes = obterParcelasFaltantesBase($row, $qtdParcelas);
        $pago = numeroSeguro($row['pago'] ?? 0);
        $totais['total_previsto'] += ($valorParcela * $qtdParcelas);
        $totais['total_recebido'] += $pago;
        $totais['total_pendente'] += ($valorParcela * $faltantes);
    }
    $totais['clientes_com_financeiro'] = count($clientesPagando);
    $stmtResumo->close();
}

$listaPendencias = array();
$sqlPend = "SELECT c.id AS cliente_id, c.nome, c.cpf, c.situacao AS status_cliente, f.*
            FROM financeiro f
            INNER JOIN clientes c ON c.id = f.cliente_id
            WHERE 1=1" . mvpPermissaoClienteWhere('c', $tipo_usuario) . "
            ORDER BY f.data_vencimento ASC, f.id DESC
            LIMIT 400";
$stmtPend = $conn->prepare($sqlPend);
if ($stmtPend) {
    if ($tipo_usuario === 'parceiro') {
        $stmtPend->bind_param('i', $usuario_logado_id);
    }
    $stmtPend->execute();
    $resultPend = stmt_get_result($stmtPend);
    while ($row = $resultPend->fetch_assoc()) {
        $statusCliente = trim((string)($row['status_cliente'] ?? ''));
        $statusFinanceiro = trim((string)($row['status'] ?? ''));
        $row['status_exibicao'] = $statusCliente !== '' ? $statusCliente : $statusFinanceiro;

        // Filtro: só clientes PAGANDO (case-insensitive, ignora espaços)
        if (mb_strtolower(str_replace([' ', '_'], '', $row['status_exibicao'])) !== 'pagando') {
            continue;
        }

        $valorParcelaNum = obterValorParcelaBase($row);
        $qtdParcelasNum = obterQtdParcelasBase($row, $valorParcelaNum);
        $faltantesNum = obterParcelasFaltantesBase($row, $qtdParcelasNum);
        $pendenteNum = $valorParcelaNum * $faltantesNum;

        if ($faltantesNum <= 0) {
            continue;
        }

        $row['valor_parcela_num'] = $valorParcelaNum;
        $row['qtd_parcelas_num'] = $qtdParcelasNum;
        $row['parcelas_faltantes_num'] = $faltantesNum;
        $row['pendente_num'] = $pendenteNum;
        $listaPendencias[] = $row;
    }
    $stmtPend->close();
}

usort($listaPendencias, function ($a, $b) {
    $da = (string)($a['data_vencimento'] ?? '9999-12-31');
    $db = (string)($b['data_vencimento'] ?? '9999-12-31');
    if ($da !== $db) {
        return strcmp($da, $db);
    }
    $pa = numeroSeguro($a['pendente_num'] ?? 0);
    $pb = numeroSeguro($b['pendente_num'] ?? 0);
    if ($pa === $pb) {
        return 0;
    }
    return ($pa < $pb) ? 1 : -1;
});

$statusPendencias = array();
foreach ($listaPendencias as $itemPendencia) {
    $statusItem = trim((string)($itemPendencia['status_exibicao'] ?? ''));
    if ($statusItem !== '') {
        $statusPendencias[$statusItem] = true;
    }
}
$statusPendencias = array_keys($statusPendencias);
sort($statusPendencias);

function moedaBr($valor)
{
    return 'R$ ' . number_format(numeroSeguro($valor), 2, ',', '.');
}

$hoje = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - Resumo</title>
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
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .card { background: #fff; border-radius: 8px; border: 1px solid #dbe3ef; padding: 14px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.10); transition: border-color 0.2s, box-shadow 0.2s; }
        .card-azul { border-color: #2563eb !important; box-shadow: 0 2px 12px rgba(37,99,235,0.10) !important; }
        .card-laranja { border-color: #f59e42 !important; box-shadow: 0 2px 12px rgba(245,158,66,0.10) !important; }
        .card-verde { border-color: #22c55e !important; box-shadow: 0 2px 12px rgba(34,197,94,0.10) !important; }
        .card-vermelho { border-color: #ef4444 !important; box-shadow: 0 2px 12px rgba(239,68,68,0.10) !important; }
        .kpi-title { font-size: 9pt; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.2px; font-weight: 600; }
        .kpi-value { font-size: 15pt; font-weight: 700; }
        .table-box { background: #fff; border-radius: 8px; border: 1px solid #dbe3ef; padding: 14px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08); }
        .table-box h2 { margin: 0 0 10px; font-size: 12pt; font-weight: 700; color: #0f172a; }
        .filtro-toolbar { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
        .filtro-toolbar label { margin:0; font-size:9pt; color:#334155; font-weight:700; }
        .filtro-toolbar select { padding:7px 9px; border:1px solid #cbd5e1; border-radius:8px; font-size:9pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 9pt; }
        th { background: #f8fafc; font-weight: 700; color: #334155; }
        tbody tr:hover { background: #f8fafc; }
        .tag { padding: 2px 8px; border-radius: 999px; font-size: 9pt; font-weight: 600; }
        .atrasado { background: #fee2e2; color: #991b1b; }
        .normal { background: #dcfce7; color: #166534; }
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

            .cards {
                grid-template-columns: 1fr;
            }

            .kpi-value {
                font-size: 14pt;
            }

            .filtro-toolbar {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div><i class="fas fa-wallet"></i> Financeiro Basico</div>
        <div class="topbar-links">
            <a href="dashboard.php" title="Dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="listar_clientes.php" title="Clientes"><i class="fas fa-users"></i> <span>Clientes</span></a>
            <a href="tarefas_prazos.php" title="Tarefas/Prazos"><i class="fas fa-calendar-check"></i> <span>Prazos</span></a>
            <a href="logout.php" title="Sair"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a>
        </div>
        <button type="button" class="topbar-toggle" id="topbarToggle" title="Expandir ou recolher menu mobile">
            <i class="fas fa-align-justify" id="topbarToggleIcon"></i>
            <span id="topbarToggleLabel">Expandir menu</span>
        </button>
    </div>

    <div class="container">
        <div class="cards">
            <div class="card card-azul">
                <div class="kpi-title">Clientes com financeiro</div>
                <div class="kpi-value"><?php echo (int)$totais['clientes_com_financeiro']; ?></div>
            </div>
            <div class="card card-laranja">
                <div class="kpi-title">Total previsto</div>
                <div class="kpi-value"><?php echo htmlspecialchars(moedaBr($totais['total_previsto'])); ?></div>
            </div>
            <div class="card card-verde">
                <div class="kpi-title">Total recebido</div>
                <div class="kpi-value"><?php echo htmlspecialchars(moedaBr($totais['total_recebido'])); ?></div>
            </div>
            <div class="card card-vermelho">
                <div class="kpi-title">Total pendente</div>
                <div class="kpi-value"><?php echo htmlspecialchars(moedaBr($totais['total_pendente'])); ?></div>
            </div>
        </div>

        <div class="table-box">
            <h2>Pendencias financeiras</h2>
            <div class="filtro-toolbar">
                <label for="filtroResumoStatus">Status</label>
                <select id="filtroResumoStatus">
                    <option value="todos">Todos</option>
                    <?php foreach ($statusPendencias as $statusOpcao): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($statusOpcao), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusOpcao); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filtroResumoAviso">Aviso</label>
                <select id="filtroResumoAviso">
                    <option value="todos">Todos</option>
                    <option value="atrasado">Vencido</option>
                    <option value="normal">Em dia</option>
                </select>
            </div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>CPF</th>
                            <th>Status</th>
                            <th>Vencimento</th>
                            <th>Parcela</th>
                            <th>Parcelas faltantes</th>
                            <th>Pendente</th>
                            <th>Aviso</th>
                            <th>Abrir ficha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listaPendencias)): ?>
                            <tr><td colspan="9">Sem pendencias encontradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($listaPendencias as $p): ?>
                                <?php $atrasado = (!empty($p['data_vencimento']) && $p['data_vencimento'] < $hoje); ?>
                                <tr data-status="<?php echo htmlspecialchars(strtolower((string)($p['status_exibicao'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" data-aviso="<?php echo $atrasado ? 'atrasado' : 'normal'; ?>">
                                    <td><?php echo htmlspecialchars($p['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($p['cpf'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(($p['status_exibicao'] ?? '') !== '' ? $p['status_exibicao'] : '-'); ?></td>
                                    <td><?php echo htmlspecialchars(mvpDateBr($p['data_vencimento'])); ?></td>
                                    <td><?php echo htmlspecialchars(moedaBr($p['valor_parcela_num'] ?? $p['valor_parcela'])); ?></td>
                                    <td><?php echo htmlspecialchars(number_format(numeroSeguro($p['parcelas_faltantes_num'] ?? $p['parcelas_faltantes']), 2, ',', '.')); ?></td>
                                    <td><?php echo htmlspecialchars(moedaBr($p['pendente_num'] ?? 0)); ?></td>
                                    <td>
                                        <?php if ($atrasado): ?>
                                            <span class="tag atrasado">Vencido</span>
                                        <?php else: ?>
                                            <span class="tag normal">Em dia</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="financeiro.php?id=<?php echo (int)$p['cliente_id']; ?>">Abrir</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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

            const filtroStatus = document.getElementById('filtroResumoStatus');
            const filtroAviso = document.getElementById('filtroResumoAviso');
            const filtroKey = 'mvpFinanceiroResumoFiltros';

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

            function salvarFiltrosResumo() {
                if (!filtroStatus || !filtroAviso) {
                    return;
                }
                try {
                    localStorage.setItem(
                        filtroKey,
                        JSON.stringify({ status: filtroStatus.value || 'todos', aviso: filtroAviso.value || 'todos' })
                    );
                } catch (e) {
                    // Ignora falha de armazenamento.
                }
            }

            function aplicarFiltrosResumo() {
                if (!filtroStatus || !filtroAviso) {
                    return;
                }
                const status = filtroStatus.value || 'todos';
                const aviso = filtroAviso.value || 'todos';
                document.querySelectorAll('tbody tr[data-status]').forEach(function (linha) {
                    const st = (linha.getAttribute('data-status') || '').toLowerCase();
                    const av = linha.getAttribute('data-aviso') || '';
                    const okStatus = status === 'todos' || st === status;
                    const okAviso = aviso === 'todos' || av === aviso;
                    linha.style.display = (okStatus && okAviso) ? '' : 'none';
                });
            }

            if (filtroStatus && filtroAviso) {
                try {
                    const salvo = JSON.parse(localStorage.getItem(filtroKey) || '{}');
                    if (salvo.status && filtroStatus.querySelector('option[value="' + String(salvo.status) + '"]')) {
                        filtroStatus.value = String(salvo.status);
                    }
                    if (salvo.aviso && filtroAviso.querySelector('option[value="' + String(salvo.aviso) + '"]')) {
                        filtroAviso.value = String(salvo.aviso);
                    }
                } catch (e) {
                    // Ignora falha de leitura.
                }

                filtroStatus.addEventListener('change', function () {
                    salvarFiltrosResumo();
                    aplicarFiltrosResumo();
                });
                filtroAviso.addEventListener('change', function () {
                    salvarFiltrosResumo();
                    aplicarFiltrosResumo();
                });

                aplicarFiltrosResumo();
            }
        })();
    </script>
</body>
</html>
