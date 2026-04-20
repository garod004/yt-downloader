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

function dashboardDateBr($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }

    $parts = explode('-', (string) $date);
    if (count($parts) !== 3) {
        return (string) $date;
    }

    return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
}

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . appPath('index.html'));
    exit();
}

$empresa_nome_topo = 'Real Assessoria Previdenciaria';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/log_utils.php';

// Registrar acesso ao dashboard
if (isset($conn) && $conn instanceof mysqli && isset($_SESSION['usuario_nome'])) {
    registrar_log($conn, $_SESSION['usuario_nome'], 'acesso', 'Acessou o dashboard');
}

if (isset($conn) && $conn instanceof mysqli) {
    $sqlCfgEmpresa = "SELECT valor FROM configuracoes_sistema WHERE chave = 'empresa_nome' LIMIT 1";
    if ($resCfgEmpresa = $conn->query($sqlCfgEmpresa)) {
        if ($rowCfgEmpresa = $resCfgEmpresa->fetch_assoc()) {
            $nomeSalvo = trim((string)($rowCfgEmpresa['valor'] ?? ''));
            if ($nomeSalvo !== '') {
                $empresa_nome_topo = $nomeSalvo;
            }
        }
    }
}

$tipo_usuario_dashboard = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_logado_dashboard = (int) ($_SESSION['usuario_id'] ?? 0);
$etapas_concluidas = 0;
$etapas_pendentes = 0;
$etapas_atrasadas = 0;
$eventos_painel_etapas = array();
$agendamentos_inss_dashboard = array();
$hoje_dashboard = date('Y-m-d');
$amanha_dashboard = date('Y-m-d', strtotime('+1 day'));
$em3dias_dashboard = date('Y-m-d', strtotime('+3 days'));

if (isset($conn) && $conn instanceof mysqli) {
    $sqlTarefasDashboard = "SELECT t.id, t.titulo, t.status, t.data_vencimento
        FROM tarefas_prazos t
        LEFT JOIN clientes c ON c.id = t.cliente_id
        WHERE 1=1" . ($tipo_usuario_dashboard === 'parceiro' ? " AND (c.usuario_cadastro_id = ? OR c.usuario_cadastro_id IS NULL OR c.id IS NULL)" : '') . "
        ORDER BY t.data_vencimento ASC, t.id DESC
        LIMIT 300";
    $stmtTarefasDashboard = $conn->prepare($sqlTarefasDashboard);
    if ($stmtTarefasDashboard) {
        if ($tipo_usuario_dashboard === 'parceiro') {
            $stmtTarefasDashboard->bind_param('i', $usuario_logado_dashboard);
        }
        $stmtTarefasDashboard->execute();
        $resultTarefasDashboard = stmt_get_result($stmtTarefasDashboard);
        while ($resultTarefasDashboard && ($tarefaDashboard = $resultTarefasDashboard->fetch_assoc())) {
            if (($tarefaDashboard['status'] ?? '') === 'concluida') {
                $etapas_concluidas++;
            } else {
                $etapas_pendentes++;
                if (!empty($tarefaDashboard['data_vencimento']) && $tarefaDashboard['data_vencimento'] < $hoje_dashboard) {
                    $etapas_atrasadas++;
                }
            }

            if (!empty($tarefaDashboard['data_vencimento'])) {
                $eventos_painel_etapas[] = array(
                    'id' => (int) ($tarefaDashboard['id'] ?? 0),
                    'data' => (string) $tarefaDashboard['data_vencimento'],
                    'status' => (string) ($tarefaDashboard['status'] ?? 'aberta'),
                    'titulo' => (string) ($tarefaDashboard['titulo'] ?? '')
                );
            }
        }
        $stmtTarefasDashboard->close();
    }

    $sqlAvaliacoesDashboard = "SELECT c.id, c.nome, c.data_avaliacao_social, c.hora_avaliacao_social
        FROM clientes c
        WHERE c.data_avaliacao_social IS NOT NULL
            AND c.data_avaliacao_social != '0000-00-00'
            AND c.data_avaliacao_social >= CURDATE()
            AND (c.realizado_a_s IS NULL OR c.realizado_a_s = 0)" . ($tipo_usuario_dashboard === 'parceiro' ? " AND c.usuario_cadastro_id = ?" : '') . "
        ORDER BY c.data_avaliacao_social ASC
        LIMIT 200";
    $stmtAvaliacoesDashboard = $conn->prepare($sqlAvaliacoesDashboard);
    if ($stmtAvaliacoesDashboard) {
        if ($tipo_usuario_dashboard === 'parceiro') {
            $stmtAvaliacoesDashboard->bind_param('i', $usuario_logado_dashboard);
        }
        $stmtAvaliacoesDashboard->execute();
        $resultAvaliacoesDashboard = stmt_get_result($stmtAvaliacoesDashboard);
        while ($resultAvaliacoesDashboard && ($avaliacaoDashboard = $resultAvaliacoesDashboard->fetch_assoc())) {
            if (!empty($avaliacaoDashboard['data_avaliacao_social'])) {
                $agendamentos_inss_dashboard[] = array(
                    'tipo' => 'Avaliação Social',
                    'tipo_class' => 'avaliacao',
                    'nome' => (string) ($avaliacaoDashboard['nome'] ?? ''),
                    'data' => (string) $avaliacaoDashboard['data_avaliacao_social'],
                    'hora' => (string) ($avaliacaoDashboard['hora_avaliacao_social'] ?? '')
                );
                $eventos_painel_etapas[] = array(
                    'id' => 'av_' . (int) ($avaliacaoDashboard['id'] ?? 0),
                    'data' => (string) $avaliacaoDashboard['data_avaliacao_social'],
                    'status' => 'avaliacao',
                    'titulo' => 'Av. Social: ' . (string) ($avaliacaoDashboard['nome'] ?? '')
                );
            }
        }
        $stmtAvaliacoesDashboard->close();
    }

    $sqlPericiasDashboard = "SELECT c.id, c.nome, c.data_pericia, c.hora_pericia
        FROM clientes c
        WHERE c.data_pericia IS NOT NULL
            AND c.data_pericia != '0000-00-00'
            AND c.data_pericia >= CURDATE()
            AND (c.realizado_pericia IS NULL OR c.realizado_pericia = 0)" . ($tipo_usuario_dashboard === 'parceiro' ? " AND c.usuario_cadastro_id = ?" : '') . "
        ORDER BY c.data_pericia ASC
        LIMIT 200";
    $stmtPericiasDashboard = $conn->prepare($sqlPericiasDashboard);
    if ($stmtPericiasDashboard) {
        if ($tipo_usuario_dashboard === 'parceiro') {
            $stmtPericiasDashboard->bind_param('i', $usuario_logado_dashboard);
        }
        $stmtPericiasDashboard->execute();
        $resultPericiasDashboard = stmt_get_result($stmtPericiasDashboard);
        while ($resultPericiasDashboard && ($periciaDashboard = $resultPericiasDashboard->fetch_assoc())) {
            if (!empty($periciaDashboard['data_pericia'])) {
                $agendamentos_inss_dashboard[] = array(
                    'tipo' => 'Perícia INSS',
                    'tipo_class' => 'pericia',
                    'nome' => (string) ($periciaDashboard['nome'] ?? ''),
                    'data' => (string) $periciaDashboard['data_pericia'],
                    'hora' => (string) ($periciaDashboard['hora_pericia'] ?? '')
                );
                $eventos_painel_etapas[] = array(
                    'id' => 'per_' . (int) ($periciaDashboard['id'] ?? 0),
                    'data' => (string) $periciaDashboard['data_pericia'],
                    'status' => 'pericia',
                    'titulo' => 'Pericia INSS: ' . (string) ($periciaDashboard['nome'] ?? '')
                );
            }
        }
        $stmtPericiasDashboard->close();
    }
}

usort($agendamentos_inss_dashboard, function ($left, $right) {
    return strcmp((string) ($left['data'] ?? ''), (string) ($right['data'] ?? ''));
});

$total_painel_etapas = $etapas_concluidas + $etapas_pendentes;
$taxa_concluidas_dashboard = $total_painel_etapas > 0 ? (int) round(($etapas_concluidas * 100) / $total_painel_etapas) : 0;
$taxa_pendentes_dashboard = $total_painel_etapas > 0 ? 100 - $taxa_concluidas_dashboard : 0;
$taxa_atrasadas_dashboard = $total_painel_etapas > 0 ? (int) round(($etapas_atrasadas * 100) / $total_painel_etapas) : 0;
$eventos_painel_etapas_json = json_encode($eventos_painel_etapas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard MeuSIS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-page: #5b8fc3;
            --bg-page-dark: #4275aa;
            --bg-surface: #f6f8fc;
            --panel: #ffffff;
            --panel-soft: #eef3fb;
            --stroke: #d7e2f0;
            --stroke-strong: #bdd0e7;
            --text: #29445f;
            --muted: #7287a1;
            --brand: #2fa5db;
            --brand-strong: #248bc6;
            --navy: #3e79b7;
            --purple: #8a42c5;
            --red: #f06273;
            --gold: #f1ad2b;
            --green: #8ecb52;
            --teal: #36b9a5;
            --slate: #5f6c7b;
            --shadow: 0 24px 48px rgba(20, 57, 97, 0.18);
            --shadow-soft: 0 10px 24px rgba(40, 72, 109, 0.1);
            --radius-xl: 26px;
            --radius-lg: 18px;
            --radius-md: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', 'Segoe UI', Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.18), transparent 18%),
                linear-gradient(180deg, var(--bg-page) 0%, var(--bg-page-dark) 100%);
            color: var(--text);
            min-height: 100dvh;
            overflow: hidden;
        }

        .app-shell {
            height: 100dvh;
            padding: clamp(8px, 1.8vw, 22px);
        }

        .app-frame {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            height: 100%;
            background: linear-gradient(180deg, #fdfefe 0%, #f3f7fc 100%);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .sidebar {
            background: linear-gradient(180deg, #fbfdff 0%, #f1f6fc 100%);
            border-right: 1px solid var(--stroke);
            padding: 14px 12px 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-height: 0;
            overflow: hidden;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 2px 4px 10px;
            border-bottom: 1px solid var(--stroke);
        }

        .logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid var(--stroke);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(54, 109, 167, 0.08);
            overflow: hidden;
            flex-shrink: 0;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-copy strong {
            display: block;
            font-family: 'Barlow', 'Segoe UI', Arial, sans-serif;
            font-size: 20px;
            line-height: 1;
            color: #4a6f96;
            letter-spacing: 0.2px;
        }

        .brand-copy span {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 500;
        }

        .nav-group {
            display: grid;
            gap: 4px;
        }

        .nav-title {
            padding: 0 6px 4px;
            color: #8ea2b7;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 9px;
            border-radius: 12px;
            color: #516b88;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
            transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }

        .menu-item i {
            width: 16px;
            text-align: center;
            color: #7e95af;
            transition: color 0.18s ease;
        }

        .menu-item:hover {
            background: #eef5fd;
            border-color: #d9e6f4;
            color: #2f557c;
            transform: translateX(2px);
        }

        .menu-item:hover i,
        .menu-item.is-active i {
            color: #ffffff;
        }

        .menu-item.is-active {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-strong) 100%);
            color: #ffffff;
            border-color: rgba(36, 139, 198, 0.38);
            box-shadow: 0 12px 24px rgba(47, 165, 219, 0.24);
        }

        .sidebar-note {
            margin-top: auto;
            background: linear-gradient(180deg, #f3f8fd 0%, #ebf3fc 100%);
            border: 1px solid var(--stroke);
            border-radius: 18px;
            padding: 10px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .sidebar-note strong {
            display: block;
            font-size: 12px;
            color: #436686;
            margin-bottom: 4px;
        }

        .sidebar-note p {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.28;
        }

        .sidebar-note .sidebar-verse {
            line-height: 1.45;
            text-align: justify;
            text-justify: inter-word;
        }

        .content-area {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            background:
                linear-gradient(180deg, rgba(238, 244, 251, 0.74) 0%, rgba(247, 250, 253, 0.96) 100%);
        }

        .topbar {
            display: none;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .mobile-menu-toggle {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--stroke);
            background: var(--panel);
            color: #4a6685;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            box-shadow: var(--shadow-soft);
            cursor: pointer;
        }

        .dashboard-content {
            padding: 14px;
            display: grid;
            gap: 10px;
            grid-template-rows: auto minmax(0, 1fr);
            min-height: 0;
            height: 100%;
            overflow: hidden;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 84px;
            border-radius: 16px;
            padding: 14px;
            color: #ffffff;
            text-decoration: none;
            box-shadow: 0 14px 26px rgba(56, 94, 136, 0.18);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 32px rgba(56, 94, 136, 0.22);
        }

        .stat-card--blue { background: linear-gradient(135deg, #3f8fd0 0%, #2876bb 100%); }
        .stat-card--green { background: linear-gradient(135deg, #87c654 0%, #5fa13b 100%); }
        .stat-card--gold { background: linear-gradient(135deg, #f4b744 0%, #eb9923 100%); }
        .stat-card--red { background: linear-gradient(135deg, #f26f76 0%, #da4f58 100%); }

        .stat-copy {
            display: grid;
            gap: 4px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            opacity: 0.92;
        }

        .stat-number {
            font-family: 'Barlow', 'Segoe UI', Arial, sans-serif;
            font-size: clamp(26px, 2.8vw, 34px);
            line-height: 0.95;
            font-weight: 700;
        }

        .stat-help {
            font-size: 11px;
            opacity: 0.84;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }

        .mobile-panel-switcher {
            display: none;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
        }

        .mobile-panel-button {
            border: 1px solid var(--stroke);
            background: rgba(255, 255, 255, 0.85);
            color: #446584;
            border-radius: 12px;
            padding: 8px 9px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .mobile-panel-button.is-active {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-strong) 100%);
            color: #ffffff;
            border-color: rgba(36, 139, 198, 0.38);
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
            gap: 10px;
            align-items: stretch;
            min-height: 0;
        }

        .panel-stack {
            display: grid;
            gap: 10px;
            min-height: 0;
            grid-template-rows: repeat(2, minmax(0, 1fr));
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--stroke);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .panel-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
        }

        .panel-bar--blue { background: linear-gradient(90deg, #3f8fd0 0%, #2876bb 100%); }
        .panel-bar--teal { background: linear-gradient(90deg, #38b6b0 0%, #278d87 100%); }
        .panel-bar--purple { background: linear-gradient(90deg, #9a4dd1 0%, #7b35b4 100%); }
        .panel-bar--slate { background: linear-gradient(90deg, #6d7c8f 0%, #556273 100%); }

        .panel-body {
            padding: 12px;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        .chart-body {
            height: auto;
            min-height: 0;
        }

        .chart-body canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .etapas-board {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            align-items: stretch;
            height: 100%;
            min-height: 0;
        }

        .etapas-kpis {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .etapas-kpi {
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 8px;
            background: #f8fafc;
        }

        .etapas-kpi-label {
            font-size: 11px;
            color: #334155;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .etapas-kpi-value {
            font-family: 'Barlow', 'Segoe UI', Arial, sans-serif;
            font-size: 21px;
            font-weight: 700;
            line-height: 1;
        }

        .etapas-kpi-value.done { color: #166534; }
        .etapas-kpi-value.pending { color: #b45309; }
        .etapas-kpi-value.overdue { color: #b91c1c; }

        .etapas-list {
            display: grid;
            gap: 8px;
        }

        .etapas-item {
            display: grid;
            gap: 3px;
        }

        .etapas-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #334155;
            font-weight: 700;
        }

        .etapas-track {
            height: 8px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .etapas-fill {
            height: 100%;
            border-radius: 999px;
        }

        .etapas-fill.done { background: linear-gradient(90deg, #22c55e, #16a34a); }
        .etapas-fill.pending { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .etapas-fill.overdue { background: linear-gradient(90deg, #ef4444, #dc2626); }

        .etapas-calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .etapas-calendar-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .etapas-calendar-nav {
            display: flex;
            gap: 8px;
        }

        .etapas-calendar-nav button {
            border: none;
            background: #0f172a;
            color: #ffffff;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            cursor: pointer;
        }

        .etapas-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 4px;
        }

        .etapas-weekday {
            font-size: 10px;
            color: #64748b;
            text-align: center;
            font-weight: 700;
            padding: 2px 0;
        }

        .etapas-day {
            min-height: 40px;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #ffffff;
            padding: 4px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 4px;
        }

        .etapas-day.empty {
            background: #f8fafc;
            border-style: dashed;
        }

        .etapas-number {
            font-size: 10px;
            font-weight: 700;
            color: #334155;
        }

        .etapas-signals {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .etapas-signal {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            display: inline-block;
        }

        .etapas-signal.pending { background: #f59e0b; }
        .etapas-signal.done { background: #16a34a; }
        .etapas-signal.avaliacao { background: #3b82f6; }
        .etapas-signal.pericia { background: #8b5cf6; }

        .etapas-legend {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 8px;
            font-size: 10px;
            color: #475569;
            flex-wrap: wrap;
        }

        .etapas-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .agenda-pendencias {
            overflow: hidden;
        }

        .agenda-table-wrap {
            overflow: auto;
            height: 100%;
            max-height: 100%;
        }

        .agenda-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .agenda-table th,
        .agenda-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            white-space: nowrap;
        }

        .agenda-table th {
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
        }

        .agenda-table tbody tr:hover {
            background: #f8fafc;
        }

        .agenda-tag-tipo {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .agenda-tag-avaliacao {
            color: #c2410c;
            background: #ffedd5;
        }

        .agenda-tag-pericia {
            color: #b91c1c;
            background: #fee2e2;
        }

        .agenda-alerta {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .agenda-alerta-ok {
            color: #166534;
            background: #dcfce7;
        }

        .agenda-alerta-proximo {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .agenda-alerta-hoje {
            color: #92400e;
            background: #fef3c7;
        }

        .agenda-alerta-atraso {
            color: #991b1b;
            background: #fee2e2;
        }

        .agenda-vazia {
            color: #64748b;
            font-size: 12px;
            padding: 4px 0;
        }

        .shortcut-list,
        .activity-list {
            display: grid;
            gap: 10px;
        }

        .shortcut-item,
        .activity-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: var(--panel-soft);
            border: 1px solid #dde7f4;
            border-radius: 14px;
            text-decoration: none;
            color: inherit;
        }

        .shortcut-item strong,
        .activity-item strong {
            display: block;
            font-size: 14px;
            color: #36597e;
            margin-bottom: 3px;
        }

        .shortcut-item span,
        .activity-item span {
            display: block;
            color: var(--muted);
            font-size: 12px;
        }

        .shortcut-badge,
        .activity-dot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            border-radius: 12px;
            font-size: 14px;
            color: #ffffff;
            flex-shrink: 0;
        }

        .shortcut-badge--blue,
        .activity-dot--blue { background: #3f8fd0; }
        .shortcut-badge--purple,
        .activity-dot--purple { background: #8a42c5; }
        .shortcut-badge--green,
        .activity-dot--green { background: #77ba45; }
        .shortcut-badge--gold,
        .activity-dot--gold { background: #f0ab29; }

        .mini-stats {
            display: grid;
            gap: 10px;
        }

        .mini-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            border-radius: 14px;
            border: 1px solid #dde7f4;
            background: linear-gradient(180deg, #fbfdff 0%, #f2f7fc 100%);
        }

        .mini-stat strong {
            display: block;
            color: #416586;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .mini-stat span {
            display: block;
            color: var(--muted);
            font-size: 12px;
        }

        .mini-stat-value {
            font-family: 'Barlow', 'Segoe UI', Arial, sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: #32577d;
            line-height: 1;
        }

        @media (max-width: 1180px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .etapas-board {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 920px) {
            .app-frame {
                grid-template-columns: 1fr;
            }

            .topbar {
                display: flex;
                justify-content: flex-end;
                padding: 12px 16px 0;
                background: transparent;
                border-bottom: 0;
                backdrop-filter: none;
            }

            .sidebar {
                display: none;
            }

            body.mobile-menu-expanded .sidebar {
                display: flex;
                border-right: 0;
                border-bottom: 1px solid var(--stroke);
                max-height: 38dvh;
                overflow: auto;
            }

            .mobile-menu-toggle {
                display: inline-flex;
            }

            .dashboard-content {
                padding: 12px;
            }

            .content-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .panel-bar {
                padding: 8px 10px;
                font-size: 11px;
            }

            .panel-body {
                padding: 10px;
            }
        }

        @media (max-width: 720px) {
            .app-shell {
                padding: 6px;
            }

            .app-frame {
                min-height: 0;
                border-radius: 24px;
            }

            .topbar {
                padding: 16px;
                flex-direction: column;
                align-items: flex-start;
            }

            .topbar-actions {
                width: 100%;
            }

            .topbar-link,
            .mobile-menu-toggle,
            .user-info {
                width: 100%;
                justify-content: center;
            }

            .dashboard-content {
                padding: 10px;
                gap: 8px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .stat-card {
                min-height: 72px;
                padding: 11px;
            }

            .stat-label,
            .stat-help {
                font-size: 10px;
            }

            .stat-number {
                font-size: 24px;
            }

            .stat-icon {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }

            .mobile-panel-switcher {
                display: grid;
                position: sticky;
                top: 0;
                z-index: 3;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .panel-stack {
                display: none;
            }

            .panel-stack.is-mobile-active {
                display: grid;
            }

            .chart-body {
                min-height: 0;
            }

            .panel-bar span:last-child {
                display: none;
            }

            .etapas-kpis {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-height: 820px) and (min-width: 721px) {
            .app-frame {
                grid-template-columns: 198px minmax(0, 1fr);
            }

            .sidebar-note {
                display: none;
            }

            .brand-copy span {
                display: none;
            }

            .stats-grid {
                gap: 8px;
            }

            .stat-card {
                min-height: 74px;
                padding: 11px;
            }

            .panel-stack,
            .content-grid,
            .dashboard-content {
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .brand {
                padding-bottom: 10px;
            }

            .topbar {
                padding: 10px 10px 0;
            }

            .panel-body {
                padding: 8px;
            }

            .etapas-kpis {
                gap: 6px;
            }

            .etapas-kpi {
                padding: 6px;
            }

            .agenda-table th,
            .agenda-table td {
                padding: 6px 8px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <div class="app-frame">
            <aside class="sidebar">
                <div class="brand">
                    <div class="logo">
                        <img src="img/logo_Real_Assessoria.png" alt="Logo Real Assessoria Previdenciária">
                    </div>
                    <div class="brand-copy">
                        <strong>Real</strong>
                        <span>Assessoria Previdenciária</span>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-title">Principal</div>
                    <a href="dashboard.php" class="menu-item is-active">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="listar_clientes.php" class="menu-item">
                        <i class="fas fa-users"></i>
                        <span>Clientes</span>
                    </a>
                    <a href="visualizacao_clientes.php" class="menu-item">
                        <i class="fas fa-table-cells-large"></i>
                        <span>Visualização</span>
                    </a>
                    <a href="processos.php" class="menu-item">
                        <i class="fas fa-gavel"></i>
                        <span>Processos</span>
                    </a>
                    <a href="tarefas_prazos.php" class="menu-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Prazos</span>
                    </a>
                    <a href="listar_modelos.php" class="menu-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Modelos</span>
                    </a>
                </div>

                <div class="nav-group">
                    <div class="nav-title">Operação</div>
                    <a href="gerador_relatorios.php" class="menu-item">
                        <i class="fas fa-file-lines"></i>
                        <span>Relatórios</span>
                    </a>
                    <?php if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'usuario'): ?>
                    <a href="financeiro_resumo.php" class="menu-item">
                        <i class="fas fa-wallet"></i>
                        <span>Financeiro</span>
                    </a>
                    <a href="enviar_cnis.php" class="menu-item" id="btn-calculo-cnis">
                        <i class="fas fa-calculator"></i>
                        <span>Cálculo CNIS</span>
                    </a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                    <a href="listar_usuarios.php" class="menu-item">
                        <i class="fas fa-user-shield"></i>
                        <span>Usuários</span>
                    </a>
                    <a href="painel_administrativo.php" class="menu-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Painel Admin</span>
                    </a>
                    <a href="enviar_cnis.php" class="menu-item">
                        <i class="fas fa-calculator"></i>
                        <span>CNIS avançado</span>
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="menu-item">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Sair</span>
                    </a>
                </div>

                <div class="sidebar-note">
                    <strong>Leitura rápida</strong>
                    <p class="sidebar-verse">Aqueles que esperam no Senhor<br>renovam as suas forcas,<br>voam alto como as aguias,<br>correm e nao ficam exaustos,<br>andam e nao se cansam.<br>Is. 40:31</p>
                </div>
            </aside>

            <div class="content-area">
                <header class="topbar">
                    <div class="topbar-actions">
                        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Alternar menu mobile">
                            <i class="fas fa-align-justify" id="mobileMenuToggleIcon"></i>
                            <span id="mobileMenuToggleLabel">Expandir menu</span>
                        </button>
                    </div>
                </header>

                <main class="dashboard-content">
                    <section class="stats-grid">
                        <a href="listar_clientes.php" class="stat-card stat-card--blue">
                            <div class="stat-copy">
                                <span class="stat-label">Clientes ativos</span>
                                <span class="stat-number" id="metricTotalClientes">--</span>
                                <span class="stat-help">Base total cadastrada</span>
                            </div>
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                        </a>

                        <a href="listar_clientes.php?filtro_status=PAGANDO" class="stat-card stat-card--green">
                            <div class="stat-copy">
                                <span class="stat-label">Pagando</span>
                                <span class="stat-number" id="metricPagando">--</span>
                                <span class="stat-help">Clientes em recebimento</span>
                            </div>
                            <div class="stat-icon"><i class="fas fa-sack-dollar"></i></div>
                        </a>

                        <a href="listar_clientes.php?filtro_status=ENVIADO" class="stat-card stat-card--gold">
                            <div class="stat-copy">
                                <span class="stat-label">Enviados</span>
                                <span class="stat-number" id="metricEnviado">--</span>
                                <span class="stat-help">Aguardando retorno</span>
                            </div>
                            <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                        </a>

                        <a href="listar_clientes.php?filtro_status=CONCLU%C3%8DDO%20SEM%20DECIS%C3%83O" class="stat-card stat-card--red">
                            <div class="stat-copy">
                                <span class="stat-label">Conclusos</span>
                                <span class="stat-number" id="metricConcluido">--</span>
                                <span class="stat-help">Sem decisão final</span>
                            </div>
                            <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                        </a>
                    </section>

                    <div class="mobile-panel-switcher" id="mobilePanelSwitcher">
                        <button type="button" class="mobile-panel-button is-active" data-target="primary">Carteira e etapas</button>
                        <button type="button" class="mobile-panel-button" data-target="secondary">Beneficios e agenda</button>
                    </div>

                    <section class="content-grid">
                        <div class="panel-stack is-mobile-active" data-panel-stack="primary">
                            <section class="panel">
                                <div class="panel-bar panel-bar--blue">
                                    <span>Carteira por status</span>
                                    <span>Distribuição atual</span>
                                </div>
                                <div class="panel-body chart-body">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </section>

                            <section class="panel">
                                <div class="panel-bar panel-bar--purple">
                                    <span>Painel de etapas</span>
                                    <span>Agenda e andamento</span>
                                </div>
                                <div class="panel-body">
                                    <div class="etapas-board">
                                        <div>
                                            <div class="etapas-kpis">
                                                <div class="etapas-kpi">
                                                    <div class="etapas-kpi-label">Tarefas realizadas</div>
                                                    <div class="etapas-kpi-value done"><?php echo (int) $etapas_concluidas; ?></div>
                                                </div>
                                                <div class="etapas-kpi">
                                                    <div class="etapas-kpi-label">Tarefas pendentes</div>
                                                    <div class="etapas-kpi-value pending"><?php echo (int) $etapas_pendentes; ?></div>
                                                </div>
                                                <div class="etapas-kpi">
                                                    <div class="etapas-kpi-label">Tarefas atrasadas</div>
                                                    <div class="etapas-kpi-value overdue"><?php echo (int) $etapas_atrasadas; ?></div>
                                                </div>
                                            </div>
                                            <div class="etapas-list">
                                                <div class="etapas-item">
                                                    <div class="etapas-head">
                                                        <span>Etapa concluida</span>
                                                        <span><?php echo (int) $taxa_concluidas_dashboard; ?>%</span>
                                                    </div>
                                                    <div class="etapas-track"><div class="etapas-fill done" style="width: <?php echo (int) $taxa_concluidas_dashboard; ?>%;"></div></div>
                                                </div>
                                                <div class="etapas-item">
                                                    <div class="etapas-head">
                                                        <span>Etapa pendente</span>
                                                        <span><?php echo (int) $taxa_pendentes_dashboard; ?>%</span>
                                                    </div>
                                                    <div class="etapas-track"><div class="etapas-fill pending" style="width: <?php echo (int) $taxa_pendentes_dashboard; ?>%;"></div></div>
                                                </div>
                                                <div class="etapas-item">
                                                    <div class="etapas-head">
                                                        <span>Etapa atrasada</span>
                                                        <span><?php echo (int) $taxa_atrasadas_dashboard; ?>%</span>
                                                    </div>
                                                    <div class="etapas-track"><div class="etapas-fill overdue" style="width: <?php echo (int) $taxa_atrasadas_dashboard; ?>%;"></div></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="etapas-calendar-header">
                                                <div class="etapas-calendar-title" id="etapasCalendarTitle">Calendario</div>
                                                <div class="etapas-calendar-nav">
                                                    <button type="button" id="etapasCalendarPrev"><i class="fas fa-chevron-left"></i></button>
                                                    <button type="button" id="etapasCalendarNext"><i class="fas fa-chevron-right"></i></button>
                                                </div>
                                            </div>
                                            <div class="etapas-calendar-grid" id="etapasCalendarGrid"></div>
                                            <div class="etapas-legend">
                                                <span class="etapas-legend-item"><span class="etapas-signal done"></span> Concluida</span>
                                                <span class="etapas-legend-item"><span class="etapas-signal pending"></span> Pendente</span>
                                                <span class="etapas-legend-item"><span class="etapas-signal avaliacao"></span> Av. Social</span>
                                                <span class="etapas-legend-item"><span class="etapas-signal pericia"></span> Pericia INSS</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="panel-stack" data-panel-stack="secondary">
                            <section class="panel">
                                <div class="panel-bar panel-bar--teal">
                                    <span>Benefícios mais solicitados</span>
                                    <span>Comparativo da base</span>
                                </div>
                                <div class="panel-body chart-body">
                                    <canvas id="beneficiosChart"></canvas>
                                </div>
                            </section>

                            <section class="panel agenda-pendencias">
                                <div class="panel-bar panel-bar--slate">
                                    <span>Avaliações Sociais e Perícias INSS Pendentes</span>
                                    <span>Agenda próxima</span>
                                </div>
                                <div class="panel-body">
                                    <?php if (empty($agendamentos_inss_dashboard)): ?>
                                        <div class="agenda-vazia">Nenhum agendamento pendente.</div>
                                    <?php else: ?>
                                        <div class="agenda-table-wrap">
                                            <table class="agenda-table">
                                                <thead>
                                                    <tr>
                                                        <th>Tipo</th>
                                                        <th>Cliente</th>
                                                        <th>Data</th>
                                                        <th>Hora</th>
                                                        <th>Alerta</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="agendaPendenciasBody">
                                                    <?php foreach ($agendamentos_inss_dashboard as $agendamentoDashboard): ?>
                                                        <?php
                                                            $alertaClasseDashboard = 'agenda-alerta-ok';
                                                            $alertaTextoDashboard = 'No prazo';
                                                            if (($agendamentoDashboard['data'] ?? '') < $hoje_dashboard) {
                                                                $alertaClasseDashboard = 'agenda-alerta-atraso';
                                                                $alertaTextoDashboard = 'Atrasado';
                                                            } elseif (($agendamentoDashboard['data'] ?? '') === $hoje_dashboard) {
                                                                $alertaClasseDashboard = 'agenda-alerta-hoje';
                                                                $alertaTextoDashboard = 'Hoje';
                                                            } elseif (($agendamentoDashboard['data'] ?? '') <= $em3dias_dashboard) {
                                                                $alertaClasseDashboard = 'agenda-alerta-proximo';
                                                                $alertaTextoDashboard = 'Em breve';
                                                            }
                                                        ?>
                                                        <tr data-agenda-date="<?php echo htmlspecialchars((string) ($agendamentoDashboard['data'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <td>
                                                                <span class="agenda-tag-tipo <?php echo ($agendamentoDashboard['tipo_class'] ?? '') === 'pericia' ? 'agenda-tag-pericia' : 'agenda-tag-avaliacao'; ?>">
                                                                    <?php echo htmlspecialchars((string) ($agendamentoDashboard['tipo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars((string) ($agendamentoDashboard['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars(dashboardDateBr((string) ($agendamentoDashboard['data'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars(!empty($agendamentoDashboard['hora']) ? substr((string) $agendamentoDashboard['hora'], 0, 5) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><span class="agenda-alerta <?php echo $alertaClasseDashboard; ?>"><?php echo htmlspecialchars($alertaTextoDashboard, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="agenda-vazia" id="agendaPendenciasEmpty" style="display: none;">Nenhuma atividade para este mes.</div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            const toggleBtn = document.getElementById('mobileMenuToggle');
            const toggleIcon = document.getElementById('mobileMenuToggleIcon');
            const toggleLabel = document.getElementById('mobileMenuToggleLabel');
            const storageKey = 'mvpTopbarExpanded';
            const fallbackKey = 'dashboardMobileMenuExpanded';
            if (!toggleBtn || !toggleIcon || !toggleLabel) {
                return;
            }

            function applyState(expanded) {
                document.body.classList.toggle('mobile-menu-expanded', expanded);
                toggleIcon.className = expanded ? 'fas fa-compress-alt' : 'fas fa-align-justify';
                toggleLabel.textContent = expanded ? 'Recolher menu' : 'Expandir menu';

                try {
                    localStorage.setItem(storageKey, expanded ? '1' : '0');
                } catch (e) {
                    // Ignora falha de armazenamento para não quebrar a tela.
                }
            }

            toggleBtn.addEventListener('click', function () {
                const expanded = !document.body.classList.contains('mobile-menu-expanded');
                applyState(expanded);
            });

            let initialExpanded = false;
            try {
                const unified = localStorage.getItem(storageKey);
                if (unified !== null) {
                    initialExpanded = unified === '1';
                } else {
                    const legacy = localStorage.getItem(fallbackKey);
                    initialExpanded = legacy === '1';
                    localStorage.setItem(storageKey, initialExpanded ? '1' : '0');
                }
            } catch (e) {
                initialExpanded = false;
            }

            applyState(initialExpanded);
        })();

        (function () {
            const eventosPainelEtapas = <?php echo $eventos_painel_etapas_json ? $eventos_painel_etapas_json : '[]'; ?>;
            const calendarGrid = document.getElementById('etapasCalendarGrid');
            const calendarTitle = document.getElementById('etapasCalendarTitle');
            const calendarPrev = document.getElementById('etapasCalendarPrev');
            const calendarNext = document.getElementById('etapasCalendarNext');
            const agendaPendenciasBody = document.getElementById('agendaPendenciasBody');
            const agendaPendenciasEmpty = document.getElementById('agendaPendenciasEmpty');
            const mobilePanelButtons = Array.from(document.querySelectorAll('.mobile-panel-button'));
            const mobilePanelStacks = Array.from(document.querySelectorAll('[data-panel-stack]'));

            if (!calendarGrid || !calendarTitle) {
                return;
            }

            const monthNames = ['Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            const weekNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
            const mapEventos = {};
            let currentDate = new Date();

            eventosPainelEtapas.forEach(function (evento) {
                if (!evento || !evento.data) {
                    return;
                }
                if (!mapEventos[evento.data]) {
                    mapEventos[evento.data] = { pending: 0, done: 0, avaliacao: 0, pericia: 0 };
                }
                if (evento.status === 'concluida') {
                    mapEventos[evento.data].done += 1;
                } else if (evento.status === 'avaliacao') {
                    mapEventos[evento.data].avaliacao += 1;
                } else if (evento.status === 'pericia') {
                    mapEventos[evento.data].pericia += 1;
                } else {
                    mapEventos[evento.data].pending += 1;
                }
            });

            function pad2(value) {
                return String(value).padStart(2, '0');
            }

            function toYmd(dateObj) {
                return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate());
            }

            function applyMobilePanels(target) {
                if (!mobilePanelButtons.length || !mobilePanelStacks.length) {
                    return;
                }

                const isMobile = window.matchMedia('(max-width: 720px)').matches;
                mobilePanelButtons.forEach(function (button) {
                    const isActive = isMobile && button.getAttribute('data-target') === target;
                    button.classList.toggle('is-active', isActive);
                });

                mobilePanelStacks.forEach(function (stack) {
                    if (!isMobile) {
                        stack.classList.remove('is-mobile-active');
                        return;
                    }
                    const isActive = stack.getAttribute('data-panel-stack') === target;
                    stack.classList.toggle('is-mobile-active', isActive);
                });
            }

            mobilePanelButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    applyMobilePanels(button.getAttribute('data-target') || 'primary');
                });
            });

            window.addEventListener('resize', function () {
                const activeButton = document.querySelector('.mobile-panel-button.is-active');
                const target = activeButton ? activeButton.getAttribute('data-target') : 'primary';
                applyMobilePanels(target || 'primary');
            });

            function syncAgendaPendencias() {
                if (!agendaPendenciasBody) {
                    return;
                }

                const currentMonth = currentDate.getMonth();
                const currentYear = currentDate.getFullYear();
                let visibleRows = 0;

                Array.from(agendaPendenciasBody.querySelectorAll('tr[data-agenda-date]')).forEach(function (row) {
                    const rowDate = row.getAttribute('data-agenda-date') || '';
                    const parts = rowDate.split('-');

                    if (parts.length !== 3) {
                        row.style.display = '';
                        visibleRows += 1;
                        return;
                    }

                    const rowYear = Number(parts[0]);
                    const rowMonth = Number(parts[1]) - 1;
                    const isVisible = rowYear === currentYear && rowMonth === currentMonth;
                    row.style.display = isVisible ? '' : 'none';
                    if (isVisible) {
                        visibleRows += 1;
                    }
                });

                if (agendaPendenciasEmpty) {
                    agendaPendenciasEmpty.style.display = visibleRows === 0 ? 'block' : 'none';
                }
            }

            function renderCalendar() {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                const firstDay = new Date(year, month, 1);
                const startWeekDay = firstDay.getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();

                calendarGrid.innerHTML = '';
                calendarTitle.textContent = monthNames[month] + ' de ' + year;

                weekNames.forEach(function (weekName) {
                    const weekday = document.createElement('div');
                    weekday.className = 'etapas-weekday';
                    weekday.textContent = weekName;
                    calendarGrid.appendChild(weekday);
                });

                for (let index = 0; index < startWeekDay; index += 1) {
                    const emptyDay = document.createElement('div');
                    emptyDay.className = 'etapas-day empty';
                    calendarGrid.appendChild(emptyDay);
                }

                for (let dayNumber = 1; dayNumber <= daysInMonth; dayNumber += 1) {
                    const dateObj = new Date(year, month, dayNumber);
                    const dateKey = toYmd(dateObj);
                    const day = document.createElement('div');
                    day.className = 'etapas-day';

                    const number = document.createElement('div');
                    number.className = 'etapas-number';
                    number.textContent = String(dayNumber);
                    day.appendChild(number);

                    const signals = document.createElement('div');
                    signals.className = 'etapas-signals';

                    const eventosDia = mapEventos[dateKey] || null;
                    if (eventosDia) {
                        if (eventosDia.done > 0) {
                            const signalDone = document.createElement('span');
                            signalDone.className = 'etapas-signal done';
                            signals.appendChild(signalDone);
                        }
                        if (eventosDia.pending > 0) {
                            const signalPending = document.createElement('span');
                            signalPending.className = 'etapas-signal pending';
                            signals.appendChild(signalPending);
                        }
                        if (eventosDia.avaliacao > 0) {
                            const signalAvaliacao = document.createElement('span');
                            signalAvaliacao.className = 'etapas-signal avaliacao';
                            signals.appendChild(signalAvaliacao);
                        }
                        if (eventosDia.pericia > 0) {
                            const signalPericia = document.createElement('span');
                            signalPericia.className = 'etapas-signal pericia';
                            signals.appendChild(signalPericia);
                        }
                    }

                    day.appendChild(signals);
                    calendarGrid.appendChild(day);
                }

                syncAgendaPendencias();
            }

            if (calendarPrev) {
                calendarPrev.addEventListener('click', function () {
                    currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
                    renderCalendar();
                });
            }

            if (calendarNext) {
                calendarNext.addEventListener('click', function () {
                    currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
                    renderCalendar();
                });
            }

            renderCalendar();
            applyMobilePanels('primary');
        })();

        // Buscar dados do banco
        fetch('get_dashboard_stats.php')
            .then(response => response.json())
            .then(data => {
                const totalClientesEl = document.getElementById('metricTotalClientes');
                const pagandoEl = document.getElementById('metricPagando');
                const enviadoEl = document.getElementById('metricEnviado');
                const concluidoEl = document.getElementById('metricConcluido');
                const indeferidoEl = document.getElementById('metricIndeferido');
                const pagandoMiniEl = document.getElementById('metricPagandoMini');
                const enviadoMiniEl = document.getElementById('metricEnviadoMini');

                if (totalClientesEl) {
                    totalClientesEl.textContent = String(data.total_clientes || 0);
                }
                if (pagandoEl) {
                    pagandoEl.textContent = String(data.pagando || 0);
                }
                if (enviadoEl) {
                    enviadoEl.textContent = String(data.enviado || 0);
                }
                if (concluidoEl) {
                    concluidoEl.textContent = String(data.concluido_sem_decisao || 0);
                }
                if (indeferidoEl) {
                    indeferidoEl.textContent = String(data.indeferido || 0);
                }
                if (pagandoMiniEl) {
                    pagandoMiniEl.textContent = String(data.pagando || 0);
                }
                if (enviadoMiniEl) {
                    enviadoMiniEl.textContent = String(data.enviado || 0);
                }

                // Gráfico de Status (Doughnut)
                const ctx1 = document.getElementById('statusChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: ['Indeferido', 'Pagando', 'Concluído Sem Decisão', 'Enviado'],
                        datasets: [{
                            data: [
                                data.indeferido || 0,
                                data.pagando || 0,
                                data.concluido_sem_decisao || 0,
                                data.enviado || 0
                            ],
                            backgroundColor: [
                                '#f26f76',
                                '#8ecb52',
                                '#3f8fd0',
                                '#f1ad2b'
                            ],
                            borderWidth: 3,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '64%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#4d6684',
                                    padding: 16,
                                    usePointStyle: true,
                                    font: {
                                        size: 13
                                    }
                                }
                            }
                        }
                    }
                });
                
                // Gráfico de Benefícios (Bar)
                const ctx2 = document.getElementById('beneficiosChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: ['BPC/Doença', 'BPC por Idade', 'Salário Maternidade', 'Aposentadoria', 'Auxílio Doença', 'Pensão por Morte'],
                        datasets: [{
                            label: 'Quantidade',
                            data: [
                                data.bpc_doenca || 0,
                                data.bpc_idade || 0,
                                data.salario_maternidade || 0,
                                data.aposentadoria || 0,
                                data.auxilio_doenca || 0,
                                data.pensao_morte || 0
                            ],
                            backgroundColor: [
                                '#8ecb52',
                                '#3f8fd0',
                                '#f1ad2b',
                                '#8a42c5',
                                '#36b9a5',
                                '#f26f76'
                            ],
                            borderWidth: 0,
                            borderRadius: 10,
                            maxBarThickness: 38
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(114, 135, 161, 0.18)'
                                },
                                border: {
                                    display: false
                                },
                                ticks: {
                                    stepSize: 1,
                                    color: '#6c84a1'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                border: {
                                    display: false
                                },
                                ticks: {
                                    color: '#6c84a1'
                                }
                            }
                        }
                    }
                });
            });
    </script>
    
    <script>
    // Detectar navegação com botão voltar após logout
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) {
                        window.location.href = '<?php echo htmlspecialchars(appPath('index.html'), ENT_QUOTES, 'UTF-8'); ?>';
                    }
                })
                .catch(() => {
                    window.location.href = '<?php echo htmlspecialchars(appPath('index.html'), ENT_QUOTES, 'UTF-8'); ?>';
                });
        }
    });
    
    window.onunload = function(){};
    </script>
</body>
</html>
