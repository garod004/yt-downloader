<script>
// Função para abrir cálculo CNIS com dados do cliente selecionado
function abrirCalculoCNIS() {
    var selecionado = document.querySelector('input[name="cliente_id_radio"]:checked');
    if (!selecionado) {
        alert('Selecione um cliente primeiro!');
        return;
    }
    var tr = selecionado.closest('tr');
    if (!tr) {
        alert('Erro ao localizar dados do cliente.');
        return;
    }
    var clienteData = tr.getAttribute('data-cliente');
    if (!clienteData) {
        alert('Erro ao localizar dados do cliente.');
        return;
    }
    try {
        var cliente = JSON.parse(clienteData);
        var idade = '';
        if (cliente.data_nascimento && cliente.data_nascimento !== '0000-00-00') {
            var dn = new Date(cliente.data_nascimento);
            var hoje = new Date();
            idade = hoje.getFullYear() - dn.getFullYear();
            var m = hoje.getMonth() - dn.getMonth();
            if (m < 0 || (m === 0 && hoje.getDate() < dn.getDate())) {
                idade--;
            }
        }
        var params = new URLSearchParams({
            nome: cliente.nome || '',
            cpf: cliente.cpf || '',
            data_nascimento: cliente.data_nascimento || '',
            idade: idade
        });
        window.location.href = 'enviar_cnis.php?' + params.toString();
    } catch (e) {
        alert('Erro ao processar dados do cliente.');
    }
}
</script>
<?php
session_start();

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Autenticação: redireciona se o usuário não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}

// Definir tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'];
$is_admin = ($tipo_usuario === 'admin' || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1));
$is_parceiro = ($tipo_usuario === 'parceiro');
$is_usuario = ($tipo_usuario === 'usuario');

// Inclui o arquivo de conexão UMA VEZ
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/beneficio_utils.php';

$db_indisponivel = (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error);
$db_erro_tecnico = isset($db_connection_error) ? $db_connection_error : ((isset($conn) && $conn instanceof mysqli && $conn->connect_error) ? $conn->connect_error : '');

// Função para formatar data de AAAA-MM-DD para DD-MM-AAAA
function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return '';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data;
}

// Função para formatar CPF para 000.000.000-00
function formatarCPF($cpf) {
    if (empty($cpf)) return '';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

// Função para formatar telefone para (00)0 0000-0000
function formatarTelefone($telefone) {
    if (empty($telefone)) return '';
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 1) . ' ' . substr($telefone, 3, 4) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

// Busca linhas de um statement de forma compativel com servidores sem mysqlnd.
function stmtFetchAllAssoc($stmt) {
    if (method_exists($stmt, 'get_result')) {
        $result = @$stmt->get_result();
        if ($result !== false) {
            $rows = array();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            return array(true, $rows, null);
        }
    }

    $meta = $stmt->result_metadata();
    if ($meta === false) {
        return array(false, array(), 'Nao foi possivel ler os metadados da consulta.');
    }

    $fields = array();
    $rowData = array();
    $bindResult = array();
    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
        $rowData[$field->name] = null;
        $bindResult[] = &$rowData[$field->name];
    }

    call_user_func_array(array($stmt, 'bind_result'), $bindResult);

    $rows = array();
    while ($stmt->fetch()) {
        $current = array();
        foreach ($fields as $name) {
            $current[$name] = $rowData[$name];
        }
        $rows[] = $current;
    }

    return array(true, $rows, null);
}

function tabelaExiste($conn, $nomeTabela) {
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $nome = $conn->real_escape_string($nomeTabela);
    $sql = "SHOW TABLES LIKE '" . $nome . "'";
    $result = $conn->query($sql);
    return ($result instanceof mysqli_result) && ($result->num_rows > 0);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Clientes</title>
    <!-- Inclua seu arquivo CSS externo -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="modal_documentos.css?v=<?php echo time(); ?>">
    <style>
        :root {
            --verde-neon: #00ff88;
            --vermelho-neon: #ff3366;
            --azul-neon: #0066ff;
            --amarelo-neon: #ffcc00;
            --dark-bg: #0a0e27;
            --dark-card: rgba(15, 23, 42, 0.8);
            --admin-bg: #f1f5f9;
            --admin-surface: #ffffff;
            --admin-border: #dbe3ef;
            --admin-text: #0f172a;
            --admin-muted: #475569;
        }
        
        /* Melhorias gerais de renderização de texto */
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        body, input, textarea, select, button, td, th, label, span, div {
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Background futurista animado */
        html {
            height: 100%;
            overflow: hidden;
        }
        body {
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: var(--admin-bg);
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: var(--admin-text);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            font-smooth: always;
        }
        .admin-topbar {
            background: #0b1220;
            color: #ffffff;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            flex-shrink: 0;
            z-index: 100;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.2);
        }
        .admin-topbar-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .admin-topbar-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-topbar-links {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .admin-topbar-links a {
            color: #ffffff;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            font-size: 9pt;
            transition: background 0.2s ease;
        }
        .admin-topbar-links a:hover {
            background: rgba(255, 255, 255, 0.18);
        }
        .admin-topbar .toolbar-buttons {
            margin: 0;
            padding: 0;
            border-bottom: 0;
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .admin-topbar .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 8px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            flex-shrink: 0;
        }
        .admin-topbar .toolbar-group-title {
            font-size: 10px;
            font-weight: 700;
            color: #cbd5e1;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin-right: 2px;
            white-space: nowrap;
        }
        .admin-topbar .toolbar-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #ffffff;
            border-radius: 8px;
            box-shadow: none;
            white-space: nowrap;
        }
        .admin-topbar .toolbar-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: none;
            transform: translateY(-1px);
        }
        .admin-topbar #statusSalvamento {
            color: #86efac;
            margin-left: 4px;
            white-space: nowrap;
            font-size: 9pt;
            font-weight: 700;
        }
        /* Layout com sidebar */
        .app-layout {
            display: flex;
            align-items: stretch;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        .sidebar-left {
            width: 195px;
            min-width: 195px;
            background: #000000;
            flex-shrink: 0;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px 8px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            z-index: 90;
        }
        .sidebar-nav-links {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        .sidebar-nav-links a {
            color: #ffffff;
            text-decoration: none;
            padding: 7px 10px;
            border-radius: 6px;
            background: rgba(255,255,255,0.08);
            font-size: 9pt;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 7px;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            font-weight: 600;
        }
        .sidebar-nav-links a:hover {
            background: rgba(255,255,255,0.22);
        }
        .sidebar-group {
            margin-bottom: 4px;
        }
        .sidebar-group-title {
            font-size: 8.5px;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            padding: 5px 6px 2px;
        }
        .sidebar-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.14);
            color: #ffffff;
            border-radius: 5px;
            padding: 6px 9px;
            cursor: pointer;
            font-size: 8.5pt;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            text-align: left;
            transition: background 0.18s ease;
            font-weight: 600;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .sidebar-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .sidebar-btn i {
            font-size: 11px;
            flex-shrink: 0;
        }
        .sidebar-btn-red {
            background: rgba(220, 38, 38, 0.2);
            border-color: rgba(248, 113, 113, 0.42);
            color: #ffe2e2;
        }
        .sidebar-btn-red:hover {
            background: rgba(220, 38, 38, 0.32);
        }
        .sidebar-btn-green {
            background: rgba(22, 163, 74, 0.2);
            border-color: rgba(74, 222, 128, 0.42);
            color: #eafff1;
        }
        .sidebar-btn-green:hover {
            background: rgba(22, 163, 74, 0.32);
        }
        .sidebar-btn-blue {
            background: rgba(37, 99, 235, 0.2);
            border-color: rgba(96, 165, 250, 0.42);
            color: #e8f1ff;
        }
        .sidebar-btn-blue:hover {
            background: rgba(37, 99, 235, 0.32);
        }
        .sidebar-btn-yellow {
            background: rgba(234, 179, 8, 0.24);
            border-color: rgba(253, 224, 71, 0.45);
            color: #fff8db;
        }
        .sidebar-btn-yellow:hover {
            background: rgba(234, 179, 8, 0.36);
        }
        .sidebar-status {
            color: #86efac;
            font-size: 8pt;
            font-weight: 700;
            padding: 4px 6px;
            min-height: 20px;
        }
        .main-content {
            flex: 1;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .page-shell {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding: 12px;
            overflow: hidden;
        }
        .container-clientes {
            flex: 1;
            min-height: 0;
            padding: 20px;
            background: var(--admin-surface);
            border-radius: 12px;
            border: 1px solid var(--admin-border);
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);
            width: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
            overflow: hidden;
            box-sizing: border-box;
        }
        
        h2 {
            color: #000000;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            letter-spacing: 1px;
            flex-shrink: 0;
            font-size: 28px;
        }
        .table-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            flex: 1;
            min-height: 0;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #FFFFFF;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            min-width: 1000px;
            border: 1px solid #D0D0D0;
        }
        
        th, td {
            padding: 16px 12px;
            text-align: left;
            font-size: 16px;
        }
        
        th {
            background: #E0E0E0;
            color: #000000;
            font-weight: 600;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            border-bottom: 2px solid #C0C0C0;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        tr:nth-child(even) {
            background: #F5F5F5;
        }
        
        tr {
            transition: background 0.2s ease;
            border-bottom: 1px solid #E0E0E0;
            background: #FFFFFF;
        }
        
        tr:hover {
            background: #B0E0FF !important;
        }
        
        td {
            color: #000000 !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            font-weight: 400;
            background: transparent !important;
        }
        
        table tbody tr td {
            color: #000000 !important;
            background: transparent !important;
        }
        
        table tbody tr:nth-child(even) td {
            background: #F5F5F5 !important;
            color: #000000 !important;
        }
        
        table tbody tr:hover td {
            background: #D6EBFF !important;
            color: #000000 !important;
        }
        
        td.senha-campo {
            color: #ffffff !important;
            text-transform: none !important;
        }
        .marcador-vinculos {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .marcador-vinculo {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: 700;
            color: #ffffff;
            background: #2563eb;
            letter-spacing: 0.4px;
            vertical-align: middle;
        }

        .marcador-vinculo-incapaz {
            background: #dc2626;
        }

        .marcador-vinculo-arogo {
            background: #16a34a;
        }
        
        td:first-child, th:first-child {
            text-align: center;
        }
        .footer-buttons {
            margin-top: 28px;
            text-align: center;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }
        .top-buttons {
            margin-top: 0;
            margin-bottom: 18px;
            justify-content: flex-end;
        }
        /* Barra superior futurista */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #D8E8F0;
            padding: 12px 16px;
            border-radius: 6px;
            border: 1px solid #B0C8E0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
            position: relative;
            z-index: 50;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        .topbar-left h2 {
            margin: 0;
            font-size: 24px;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            color: #000000;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .topbar-right .btn-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-box {
            display: inline-block;
            margin-top: 8px;
        }
        .search-box input[type="text"] {
            padding: 10px 14px;
            border-radius: 4px;
            border: 1px solid #90CAF9;
            background: #E3F2FD;
            color: #000000;
            font-size: 16px;
            width: 200px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        .search-box input[type="text"]:focus {
            outline: none;
            border-color: #64B5F6;
            box-shadow: 0 0 5px rgba(100, 181, 246, 0.3);
            background: #BBDEFB;
        }
        
        .search-box input[type="text"]::placeholder {
            color: #999999;
        }
        
        /* Botões futuristas com cores neon */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            cursor: pointer;
            border: none;
            text-decoration: none;
            color: #fff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 1px;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn svg { width: 16px; height: 16px; opacity: 0.95; }
        .btn:active { transform: translateY(1px) scale(0.98); }
        .btn:hover { 
            transform: translateY(-3px) scale(1.05); 
            filter: brightness(1.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--azul-neon) 0%, #0044cc 100%);
            box-shadow: 0 6px 20px rgba(0, 102, 255, 0.4), 0 0 20px rgba(0, 102, 255, 0.2);
        }
        
        .btn-primary:hover {
            box-shadow: 0 8px 30px rgba(0, 102, 255, 0.6), 0 0 30px rgba(0, 102, 255, 0.4);
        }
        
        .btn-accent {
            background: linear-gradient(135deg, var(--verde-neon) 0%, #00cc66 100%);
            box-shadow: 0 6px 20px rgba(0, 255, 136, 0.4), 0 0 20px rgba(0, 255, 136, 0.2);
        }
        
        .btn-accent:hover {
            box-shadow: 0 8px 30px rgba(0, 255, 136, 0.6), 0 0 30px rgba(0, 255, 136, 0.4);
        }
        
        .btn-ghost {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(10px);
            color: var(--verde-neon);
            border: 1px solid rgba(0, 255, 136, 0.3);
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
        }
        
        .btn-ghost:hover {
            background: rgba(0, 255, 136, 0.1);
            border-color: var(--verde-neon);
            box-shadow: 0 0 25px rgba(0, 255, 136, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--amarelo-neon) 0%, #cc9900 100%);
            box-shadow: 0 6px 20px rgba(255, 204, 0, 0.4), 0 0 20px rgba(255, 204, 0, 0.2);
            color: #000;
        }
        
        .btn-warning:hover {
            box-shadow: 0 8px 30px rgba(255, 204, 0, 0.6), 0 0 30px rgba(255, 204, 0, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--vermelho-neon) 0%, #cc0033 100%);
            box-shadow: 0 6px 20px rgba(255, 51, 102, 0.4), 0 0 20px rgba(255, 51, 102, 0.2);
        }
        
        .btn-danger:hover {
            box-shadow: 0 8px 30px rgba(255, 51, 102, 0.6), 0 0 30px rgba(255, 51, 102, 0.4);
        }
        .btn-excluir {
            background: linear-gradient(135deg, #ff5722 0%, #f4511e 100%);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            text-transform: uppercase;
        }
        .btn-excluir:hover {
            background: linear-gradient(135deg, #f4511e 0%, #e64a19 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 87, 34, 0.4);
        }
        .btn-excluir i {
            font-size: 12px;
        }
        .btn-excluir-tab {
            background: linear-gradient(90deg, #ff5722, #f4511e) !important;
            color: white !important;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-excluir-tab:hover {
            background: linear-gradient(90deg, #f4511e, #e64a19) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 87, 34, 0.4);
        }
        /* Tipografia e transformação solicitada: Calibri 10pt e CAIXA ALTA */
        @font-face {
            font-family: 'CalibriFallback';
            src: local('Calibri'), local('Calibri Regular');
        }
        .container-clientes,
        body,
        table, th,
        input, textarea, select,
        .btn, .topbar-left h2,
        .sem-resultados, .erro-mensagem {
            font-family: 'CalibriFallback', 'Calibri', 'Segoe UI', Arial, sans-serif !important;
            font-size: 10pt !important;
            text-transform: uppercase !important;
        }
        
        /* TD - fonte Calibri SEM uppercase por padrão */
        td {
            font-family: 'CalibriFallback', 'Calibri', 'Segoe UI', Arial, sans-serif !important;
            font-size: 10pt !important;
        }
        
        /* Aplicar uppercase em colunas específicas (todas EXCETO a 5ª que é senha) */
        td:nth-child(1), td:nth-child(2), td:nth-child(3), td:nth-child(4),
        td:nth-child(6), td:nth-child(7), td:nth-child(8), td:nth-child(9),
        td:nth-child(10), td:nth-child(11), td:nth-child(12) {
            text-transform: uppercase !important;
        }
        
        /* Garantir que coluna 5 (senha) NUNCA tenha uppercase */
        td:nth-child(5) {
            text-transform: none !important;
        }
        
        /* Centralizar campos específicos: CPF (4), SENHA GOV (5), INDICADOR (8) */
        th:nth-child(4), td:nth-child(4),  /* CPF */
        th:nth-child(5), td:nth-child(5),  /* SENHA GOV */
        th:nth-child(8), td:nth-child(8) { /* INDICADOR */
            text-align: center !important;
        }
        
        /* Garanta que inputs não fiquem em itálico (letra cursiva) */
        input, textarea {
            font-style: normal !important;
            -webkit-font-smoothing: antialiased;
        }
        /* actions dropdown (mobile) */
        .actions-dropdown {
            position: relative;
            display: none;
        }
        .actions-toggle {
            padding: 8px 12px;
            border-radius: 10px;
            background: rgba(15,27,45,0.06);
            border: 1px solid rgba(15,27,45,0.04);
            cursor: pointer;
            font-weight: 600;
        }
        .actions-menu {
            position: absolute;
            right: 0;
            top: 44px;
            min-width: 180px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(12,20,40,0.12);
            padding: 8px;
            display: none;
            z-index: 60;
        }
        .actions-menu .menu-item {
            display: block;
            margin: 6px 0;
            width: 100%;
            text-align: left;
        }
        /* Abas de ações futuristas */
        .actions-tabs {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 30px rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.2);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        .actions-tabs::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                var(--azul-neon), 
                var(--verde-neon), 
                var(--amarelo-neon), 
                var(--vermelho-neon));
            border-radius: 15px 15px 0 0;
        }
        
        .tabs-header {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }
        
        .tab-btn {
            padding: 8px 16px;
            border: none;
            background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
            cursor: pointer;
            font-weight: 600;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            font-size: 10pt;
            color: #fff;
            border-radius: 6px 6px 0 0;
            transition: all 0.2s;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(30, 136, 229, 0.3);
            letter-spacing: 0.5px;
        }
        
        .tab-btn:hover {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            box-shadow: 0 4px 12px rgba(67, 160, 71, 0.4);
            transform: translateY(-2px);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(67, 160, 71, 0.5);
        }
        a.tab-btn {
            text-decoration: none;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .form-cadastro-container.tab-content.active {
            display: block;
        }
        .footer-buttons a, .footer-buttons button {
            margin: 0;
        }
        .botao-editar, .botao-cadastrar, .botao-contrato, .botao-procuracao, .voltar-link {
            background: linear-gradient(90deg, #4f8cff 0%, #38c6ff 100%);
            color: #fff;
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(79,140,255,0.08);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .botao-editar:hover, .botao-cadastrar:hover, .voltar-link:hover, .botao-contrato:hover, .botao-procuracao:hover {
            background: linear-gradient(90deg, #38c6ff 0%, #4f8cff 100%);
            box-shadow: 0 4px 16px rgba(56,198,255,0.12);
        }
        .botao-contrato {
            background: linear-gradient(90deg, #38c6ff 0%, #4f8cff 100%);
        }
        .botao-procuracao {
            background: linear-gradient(90deg, #4f8cff 0%, #38c6ff 100%);
        }
        .acao-botoes {
            white-space: nowrap;
            min-width: 120px;
        }
        .erro-mensagem {
            color: var(--vermelho-neon);
            font-weight: bold;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            text-align: center;
            padding: 15px;
            background: rgba(255, 51, 102, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 51, 102, 0.3);
            box-shadow: 0 0 20px rgba(255, 51, 102, 0.3);
            text-shadow: 0 0 10px rgba(255, 51, 102, 0.5);
        }
        
        .sem-resultados {
            text-align: center;
            padding: 30px;
            color: rgba(255, 255, 255, 0.5);
            font-style: italic;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            font-size: 14pt;
        }
        input[type="radio"] {
            accent-color: #4f8cff;
            width: 18px;
            height: 18px;
        }
        /* Responsividade Completa */
        
        /* Desktop Grande (1440px+) */
        @media (min-width: 1440px) {
            .container-clientes {
                max-width: 96%;
                margin: 40px auto;
            }
        }
        
        /* Notebook/Desktop Padrão (1024px - 1439px) */
        @media (max-width: 1439px) {
            .container-clientes {
                max-width: 95%;
                margin: 30px auto;
            }
        }
        
        /* Tablet Horizontal (768px - 1023px) */
        @media (max-width: 1023px) {
            .container-clientes {
                margin: 20px 15px;
                padding: 20px 15px;
            }
            
            .topbar {
                padding: 12px;
                gap: 10px;
            }
            
            .topbar-left h2 {
                font-size: 18px !important;
            }
            
            .search-box input[type="text"] {
                width: 160px;
            }
            
            .actions-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .tabs-header {
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 6px;
            }
            
            .tab-btn {
                padding: 7px 12px;
                font-size: 9pt !important;
                white-space: nowrap;
            }
            
            table {
                font-size: 9pt !important;
            }
            
            th, td {
                padding: 8px 6px;
            }
        }
        
        /* Tablet e Mobile (até 900px) - Conversão para Cards */
        @media (max-width: 900px) {
            body {
                padding: 0;
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            }
            
            .container-clientes {
                margin: 10px;
                padding: 15px 12px;
                border-radius: 12px;
            }
            
            .topbar {
                flex-direction: column;
                position: static;
                padding: 12px;
            }
            
            .topbar-left {
                text-align: center;
                width: 100%;
            }
            
            .topbar-left h2 {
                font-size: 16px !important;
                margin-bottom: 8px;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input[type="text"] {
                width: 100%;
                padding: 10px;
            }
            
            .actions-tabs {
                padding: 10px;
            }
            
            .tabs-header {
                overflow-x: auto;
                flex-wrap: nowrap;
                gap: 6px;
                padding-bottom: 8px;
            }
            
            .tab-btn {
                font-size: 8pt !important;
                padding: 8px 12px;
                min-width: fit-content;
            }
            
            /* Tabela responsiva em cards */
            .table-container {
                overflow-x: visible;
            }
            
            table {
                border: 0;
                width: 100%;
                display: block;
                min-width: 0;
            }
            
            table thead {
                display: none !important;
            }
            
            table tbody {
                display: block;
            }
            
            table tbody tr {
                display: block !important;
                margin-bottom: 15px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                padding: 12px;
                border-left: 4px solid #1e88e5;
            }
            
            table tbody tr:hover {
                background: #f8fafc;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            table tbody td {
                display: block !important;
                text-align: right !important;
                padding: 8px 10px !important;
                border: none !important;
                position: relative;
                min-height: 36px;
            }
            
            table tbody td:before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 10px;
                top: 8px;
                font-weight: 700;
                color: #1e88e5;
                text-align: left;
                width: 45%;
                text-transform: uppercase;
            }
            
            table tbody td:first-child {
                text-align: center !important;
                padding: 10px !important;
            }
            
            table tbody td:first-child:before {
                display: none;
            }
            
            .btn-excluir {
                width: 100%;
                justify-content: center;
                padding: 10px 14px;
                font-size: 10px;
            }
            
            .acao-botoes {
                display: flex;
                flex-direction: column;
                gap: 6px;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
                padding: 10px 14px;
            }
            
            .footer-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .footer-buttons .btn {
                width: 100%;
            }
        }
        
        /* Mobile (até 599px) */
        @media (max-width: 599px) {
            .container-clientes {
                margin: 5px;
                padding: 12px 8px;
            }
            
            .topbar {
                padding: 10px 8px;
            }
            
            .topbar-left h2 {
                font-size: 14px !important;
            }
            
            .search-box input[type="text"] {
                padding: 8px;
                font-size: 9pt !important;
            }
            
            .actions-tabs {
                padding: 8px;
            }
            
            .tab-btn {
                font-size: 7pt !important;
                padding: 6px 10px;
            }
            
            table tbody tr {
                padding: 10px;
                margin-bottom: 12px;
            }
            
            table tbody td {
                padding: 6px 8px;
                font-size: 8pt !important;
                min-height: 32px;
            }
            
            table tbody td:before {
                font-size: 7pt !important;
                width: 42%;
            }
            
            .btn {
                font-size: 8pt !important;
                padding: 8px 10px;
            }
        }
        
        /* Mobile Pequeno (até 360px) */
        @media (max-width: 360px) {
            .container-clientes {
                margin: 3px;
                padding: 8px 5px;
            }
            
            .topbar-left h2 {
                font-size: 12px !important;
            }
            
            .tab-btn {
                font-size: 6pt !important;
                padding: 5px 8px;
            }
            
            table tbody td {
                padding: 5px;
                font-size: 7pt !important;
            }
            
            table tbody td:before {
                font-size: 6pt !important;
            }
            
            .btn {
                font-size: 7pt !important;
                padding: 6px 8px;
            }
        }
        
        /* Modal Pop-up Movível Futurista */
        .modal-popup {
            position: fixed;
            width: 10cm;
            height: 20cm;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6), 0 0 80px rgba(0, 255, 136, 0.3);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 2px solid rgba(0, 255, 136, 0.4);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        .modal-popup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, 
                var(--azul-neon), 
                var(--verde-neon), 
                var(--amarelo-neon), 
                var(--vermelho-neon));
        }
        
        .modal-header {
            background: linear-gradient(135deg, 
                rgba(0, 102, 255, 0.8) 0%, 
                rgba(0, 255, 136, 0.8) 50%,
                rgba(255, 204, 0, 0.8) 100%);
            background-size: 200% 200%;
            animation: headerGradient 5s ease infinite;
            color: white;
            padding: 15px 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
            user-select: none;
            box-shadow: 0 4px 20px rgba(0, 255, 136, 0.3);
        }
        
        .modal-titulo {
            font-weight: 700;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            letter-spacing: 2px;
            text-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
        }
        
        .modal-fechar {
            background: rgba(255, 51, 102, 0.3);
            border: 1px solid rgba(255, 51, 102, 0.5);
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            padding: 0;
            line-height: 1;
            box-shadow: 0 0 15px rgba(255, 51, 102, 0.3);
        }
        
        .modal-fechar:hover {
            background: rgba(255, 51, 102, 0.6);
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 0 25px rgba(255, 51, 102, 0.6);
        }
        
        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            font-size: 9pt;
            color: rgba(255, 255, 255, 0.95);
            background: rgba(15, 23, 42, 0.7);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        .modal-info-linha {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            border-bottom: 1px solid rgba(0, 255, 136, 0.2);
            padding-bottom: 12px;
            transition: all 0.3s;
        }
        
        .modal-info-linha:hover {
            border-bottom-color: var(--verde-neon);
            padding-left: 5px;
        }
        
        .modal-info-label {
            font-weight: 700;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            color: var(--verde-neon);
            font-size: 8.5pt;
            margin-bottom: 5px;
            text-transform: uppercase;
            text-shadow: 0 0 10px rgba(0, 255, 136, 0.5);
            letter-spacing: 1px;
        }
        
        /* Área superior fixa (formulário) */
        .form-area-fixed {
            flex-shrink: 0;
            overflow-y: auto;
            max-height: 60vh;
        }
        
        /* Container de filtros */
        .filtros-container {
            background: #f8f9fa !important;
            border: 2px solid #cccccc !important;
            border-radius: 8px !important;
            padding: 12px !important;
            margin-bottom: 12px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
            flex-shrink: 0;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: optimizeLegibility !important;
        }
        
        .filtros-container label {
            color: #333333 !important;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif !important;
            font-weight: 600 !important;
        }
        
        .filtros-container select {
            background: #e3f2fd !important;
            border: 1px solid #4a90e2 !important;
            color: #333333 !important;
            border-radius: 4px !important;
            padding: 6px 10px !important;
            transition: all 0.2s !important;
        }
        
        .filtros-container select:focus {
            outline: none !important;
            border-color: #1976d2 !important;
            box-shadow: 0 0 5px rgba(25, 118, 210, 0.3) !important;
            background: #bbdefb !important;
        }
        
        .modal-info-valor {
            color: rgba(255, 255, 255, 0.9);
            font-size: 9pt;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            word-wrap: break-word;
            padding: 5px 0;
        }
        
        .modal-info-valor.senha-valor {
            text-transform: none !important;
        }
        /* Estilos para o formulário de edição no topo */
        .form-cadastro-container {
            background: #ffffff;
            flex-shrink: 0;
            border: 1px solid #d6dde7;
            border-radius: 6px;
            padding: 8px 9px;
            margin-bottom: 10px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
            position: relative;
        }

        .cadastro-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e7edf5;
        }

        .cadastro-header-title {
            margin: 0;
            color: #111827;
            font-size: 10pt;
            letter-spacing: 0.2px;
        }

        .cadastro-header-actions {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-wrap: wrap;
        }

        .compact-action-btn {
            min-height: 26px;
            padding: 4px 11px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: #ffffff;
            line-height: 1;
            box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.12);
        }

        .compact-action-btn.btn-save {
            background: #1f9d55;
        }

        .compact-action-btn.btn-close {
            background: #cf3341;
        }

        .compact-action-btn.btn-cancel {
            background: #6b7280;
        }
        
        .form-row {
            display: flex;
            gap: 3px;
            margin-bottom: 2px;
            align-items: flex-end;
            flex-wrap: wrap;
            padding-bottom: 2px;
            border-bottom: 1px solid #eef3f8;
        }
        .form-row:last-of-type {
            border-bottom: none;
            padding-bottom: 0;
        }
        .form-field {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 82px;
        }
        .form-field label {
            font-size: 7pt;
            font-weight: 600;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            color: #4b5563;
            margin-bottom: 1px;
            text-transform: uppercase;
            letter-spacing: 0.18px;
            line-height: 1.1;
        }
        
        .form-field label .required {
            color: #dc3545;
            font-weight: bold;
            margin-left: 3px;
        }
        .form-field input, .form-field select, .form-field textarea {
            font-size: 8.2pt;
            padding: 3px 5px;
            border: 1px solid #9bb0c9;
            border-radius: 2px;
            background: #f4f8fc;
            color: #333333;
            transition: all 0.2s;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif !important;
            min-height: 24px;
            box-sizing: border-box;
        }
        
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.14);
            background: #e0efff;
        }
        
        .form-field input::placeholder, .form-field textarea::placeholder {
            color: #999999;
        }
        
        .form-field select option {
            background: #e3f2fd;
            color: #333333;
        }
        .form-field input.no-uppercase {
            text-transform: none !important;
        }
        .form-field input[type="text"], .form-field input[type="date"], .form-field input[type="time"] {
            height: 24px;
        }
        .split-inputs {
            display: flex;
            gap: 4px;
        }
        .split-inputs input {
            flex: 1;
            min-width: 0;
        }
        .link-input {
            color: #003366;
        }
        
        /* Centralizar campos específicos */
        #indicador, #responsavel, #advogado, #senha_meuinss, #cpf {
            text-align: center !important;
        }
        .form-field-small {
            flex: 0 0 56px;
        }
        .form-field-medium {
            flex: 0 0 94px;
        }
        .form-field-large {
            flex: 1 1 136px;
        }
        .form-field-date {
            flex: 0 0 130px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 0;
            padding: 0 2px 1px;
        }
        .checkbox-group label {
            font-size: 7pt;
            font-weight: 600;
            margin-right: 2px;
            text-transform: uppercase;
            color: #4b5563;
            white-space: nowrap;
        }
        .checkbox-group input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
            accent-color: var(--azul-neon);
        }
        .toolbar-buttons {
            display: flex;
            gap: 4px;
            padding: 6px 0;
            border-bottom: 1px solid #dbe3ef;
            margin-bottom: 8px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        .toolbar-btn {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 9pt;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
            font-weight: 600;
            color: #1e293b;
            box-shadow: none;
            letter-spacing: 0.5px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .toolbar-btn:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.15);
        }
        .toolbar-btn i {
            font-size: 12px;
        }
        #statusSalvamento {
            margin-left: 10px;
            font-size: 9pt;
            color: #28a745;
            font-weight: 600;
        }
        
        /* Campos código e idade */
        /* #codigo agora usa os estilos padrão dos campos de formulário */
        #idade,
        #filho_idade {
            color: #333333 !important;
            font-weight: 400 !important;
            background: #e3f2fd !important;
            border: 1px solid #4a90e2 !important;
        }
        
        /* Responsividade - Media Queries */
        @media screen and (max-width: 1400px) {
            .form-field-large {
                flex: 1 1 136px;
            }
            .form-field-medium {
                flex: 0 0 96px;
            }
        }
        
        @media screen and (max-width: 1200px) {
            .form-field-large {
                flex: 1 1 126px;
            }
            .form-field-medium {
                flex: 0 0 92px;
            }
            .form-field-small {
                flex: 0 0 56px;
            }
        }
        
        @media screen and (max-width: 992px) {
            .form-row {
                gap: 4px;
            }
            .form-field-large {
                flex: 1 1 118px;
            }
            .form-field-medium {
                flex: 1 1 92px;
            }
            .form-field-small {
                flex: 0 0 52px;
            }
            .toolbar-btn {
                padding: 8px 12px;
                font-size: 9pt;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .cadastro-header-bar {
                align-items: flex-start;
            }

            .cadastro-header-actions {
                width: 100%;
            }
        }
        
        @media screen and (max-width: 768px) {
            .admin-topbar-title {
                font-size: 16px;
            }
            .admin-topbar-row {
                align-items: flex-start;
            }
            .admin-topbar .toolbar-group {
                padding: 4px;
                gap: 6px;
            }
            .admin-topbar .toolbar-group-title {
                display: none;
            }
            .admin-topbar .toolbar-btn {
                width: 34px;
                height: 34px;
                min-width: 34px;
                padding: 0;
                font-size: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .admin-topbar .toolbar-btn i {
                font-size: 14px;
                margin: 0;
            }
            .admin-topbar #statusSalvamento {
                display: none;
            }
            .form-cadastro-container {
                padding: 8px;
            }
            .form-row {
                gap: 5px;
                margin-bottom: 5px;
            }
            .form-field {
                flex: 1 1 45%;
                min-width: 92px;
            }
            .form-field-small,
            .form-field-medium,
            .form-field-large {
                flex: 1 1 45%;
                min-width: 92px;
            }
            .form-field label {
                font-size: 7pt;
            }
            .form-field input, .form-field select, .form-field textarea {
                font-size: 7.9pt;
                padding: 3px 5px;
            }
            .compact-action-btn {
                min-height: 25px;
                padding: 4px 9px;
                font-size: 7.6pt;
            }
            .toolbar-buttons {
                gap: 4px;
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            .toolbar-btn {
                padding: 6px 10px;
                font-size: 8pt;
                white-space: nowrap;
                flex-shrink: 0;
            }
        }
        
        @media screen and (max-width: 576px) {
            .form-cadastro-container {
                padding: 6px;
            }
            .form-row {
                flex-direction: column;
                gap: 4px;
                border-bottom: none;
                padding-bottom: 0;
            }
            .form-field,
            .form-field-small,
            .form-field-medium,
            .form-field-large {
                flex: 1 1 100%;
                min-width: 100%;
                width: 100%;
            }
            .form-field label {
                font-size: 7pt;
            }
            .form-field input, .form-field select, .form-field textarea {
                font-size: 8pt;
                width: 100%;
            }
            .cadastro-header-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .cadastro-header-actions {
                justify-content: flex-end;
            }
            .toolbar-buttons {
                justify-content: center;
            }
            .toolbar-btn {
                flex: 0 0 auto;
                padding: 5px 8px;
                font-size: 7pt;
            }
            .checkbox-group {
                flex-direction: column;
                align-items: flex-start;
                margin-top: 10px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .page-shell {
                margin: 10px auto;
                padding: 0 8px;
            }
            .form-cadastro-container {
                padding: 5px;
                margin-bottom: 5px;
            }
            .form-row {
                margin-bottom: 4px;
            }
            .form-field label {
                font-size: 6.5pt;
                margin-bottom: 1px;
            }
            .form-field input, .form-field select, .form-field textarea {
                font-size: 7.5pt;
                padding: 2px 4px;
                height: 22px;
            }
            .compact-action-btn {
                min-height: 24px;
                padding: 4px 8px;
                font-size: 7.2pt;
            }
            .toolbar-buttons {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            .toolbar-btn {
                padding: 6px 10px;
                font-size: 7pt;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .toolbar-btn i {
                font-size: 10px;
            }
        }
        .kpi-grid-clientes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin: 10px 0 14px;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .kpi-card-green {
            background: #ecfdf5;
            border-color: #86efac;
        }
        .kpi-card-blue {
            background: #eff6ff;
            border-color: #93c5fd;
        }
        .kpi-card-red {
            background: #fef2f2;
            border-color: #fca5a5;
        }
        .kpi-card-yellow {
            background: #fefce8;
            border-color: #fde047;
        }
        .kpi-label {
            font-size: 12px;
            color: #475569;
            margin-bottom: 5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .kpi-value {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }
        .filtros-container {
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 12px;
        }
        .filtros-grid {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filtros-title {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0 6px 8px 0;
            white-space: nowrap;
        }
        .filtro-item {
            margin: 0;
        }
        .filtro-item-beneficio {
            min-width: 220px;
        }
        .filtro-item-status {
            min-width: 170px;
        }
        .filtro-item-nome {
            min-width: 220px;
        }
        .filtro-item-cpf {
            min-width: 160px;
        }
        .filtro-item-indicador {
            min-width: 180px;
        }
        .filtro-label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .filtro-control {
            width: 100%;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 7px 10px;
            background: #ffffff;
            color: #0f172a;
            box-sizing: border-box;
        }
        .filtro-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
        }
        .filtro-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: auto;
            flex-wrap: wrap;
        }
        .btn-filtro {
            border: none;
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-filtro:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
        }
        .btn-aplicar {
            background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%);
        }
        .btn-limpar {
            background: #64748b;
        }
        .btn-pdf {
            background: linear-gradient(135deg, #dc2626 0%, #f43f5e 100%);
        }
        @media screen and (max-width: 992px) {
            .filtro-actions {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="admin-topbar">
        <div class="admin-topbar-row">
            <div class="admin-topbar-title"><i class="fas fa-users"></i> Lista de Clientes</div>
        </div>
    </div>
    <div class="app-layout">
    <aside class="sidebar-left">
        <div class="sidebar-nav-links">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php if ($tipo_usuario !== 'usuario'): ?>
                <a href="financeiro_resumo.php"><i class="fas fa-wallet"></i> Financeiro</a>
            <?php endif; ?>
            <a href="tarefas_prazos.php"><i class="fas fa-calendar-check"></i> Prazos</a>
        </div>
        <div class="sidebar-group">
            <div class="sidebar-group-title">Navegação</div>
            <button type="button" class="sidebar-btn" onclick="navegarPrimeiro()" title="Primeiro Registro">
                <i class="fas fa-fast-backward"></i> PRIMEIRO
            </button>
            <button type="button" class="sidebar-btn" onclick="navegarAnterior()" title="Registro Anterior">
                <i class="fas fa-step-backward"></i> ANTERIOR
            </button>
            <button type="button" class="sidebar-btn" onclick="navegarProximo()" title="Próximo Registro">
                <i class="fas fa-step-forward"></i> PRÓXIMO
            </button>
            <button type="button" class="sidebar-btn" onclick="navegarUltimo()" title="Último Registro">
                <i class="fas fa-fast-forward"></i> ÚLTIMO
            </button>
        </div>
        <div class="sidebar-group">
            <div class="sidebar-group-title">Ações</div>
            <button type="button" class="sidebar-btn" onclick="mostrarListagemClientes()" title="Voltar para listagem de clientes">
                <i class="fas fa-list"></i> LISTAR CLIENTES
            </button>
            <button type="button" class="sidebar-btn" onclick="window.location.href='visualizacao_clientes.php'" title="Abrir nova tela de visualização">
                <i class="fas fa-table-cells-large"></i> VISUALIZAÇÃO
            </button>
            <button type="button" class="sidebar-btn" onclick="abrirProcessosCliente()" title="Ver processos da pessoa selecionada">
                <i class="fas fa-gavel"></i> PROCESSOS
            </button>
            <?php if ($tipo_usuario !== 'usuario'): ?>
                <button type="button" class="sidebar-btn" onclick="abrirFinanceiro()" title="Financeiro">
                    <i class="fas fa-dollar-sign"></i> FINANCEIRO
                </button>
                <button type="button" class="sidebar-btn" onclick="abrirCalculoCNIS()" title="Cálculo CNIS">
                    <i class="fas fa-calculator"></i> CÁLCULO CNIS
                </button>
            <?php endif; ?>
        </div>
        <div class="sidebar-group">
            <div class="sidebar-group-title">Cadastros</div>
            <button type="button" class="sidebar-btn sidebar-btn-yellow" onclick="switchTab(null, 'cadastro_pessoas')" title="Cadastro de Pessoas">
                <i class="fas fa-user-plus"></i> CADASTRAR PESSOAS
            </button>
            <button type="button" class="sidebar-btn sidebar-btn-blue" onclick="switchTab(null, 'agendamento')" title="Cadastro de Filhos">
                <i class="fas fa-child"></i> FILHOS
            </button>
            <button type="button" class="sidebar-btn sidebar-btn-red" onclick="switchTab(null, 'incapaz')" title="Cadastro de Incapaz">
                <i class="fas fa-wheelchair"></i> INCAPAZ
            </button>
            <button type="button" class="sidebar-btn sidebar-btn-green" onclick="switchTab(null, 'a_rogo')" title="Cadastro A ROGO">
                <i class="fas fa-user-edit"></i> A ROGO
            </button>
            <button type="button" class="sidebar-btn" onclick="window.location.href='cadastro_advogados.php'" title="Cadastrar Advogados">
                <i class="fas fa-user-tie"></i> CADASTRAR ADVOGADOS
            </button>
        </div>
        <div class="sidebar-group">
            <div class="sidebar-group-title">Relatórios</div>
            <button type="button" class="sidebar-btn" onclick="abrirModelosCliente()" title="Gerar documento a partir de um modelo">
                <i class="fas fa-file-alt"></i> MODELOS
            </button>
            <button type="button" class="sidebar-btn" onclick="abrirModalDocumentos()" title="Gerar Documentos">
                <i class="fas fa-file-alt"></i> DOCUMENTOS
            </button>
            <button type="button" class="sidebar-btn" onclick="abrirRelatorio()" title="Relatório">
                <i class="fas fa-file-pdf"></i> GERADOR
            </button>
            <button type="button" class="sidebar-btn" onclick="abrirRelatorioCliente()" title="Relatório do Cliente">
                <i class="fas fa-file-pdf"></i> CLIENTE
            </button>
        </div>
        <div class="sidebar-group">
            <div class="sidebar-group-title">Portais</div>
            <button type="button" class="sidebar-btn" onclick="abrirMeuINSS()" title="Abrir MeuINSS com CPF do cliente">
                <i class="fas fa-id-card"></i> MEUINSS
            </button>
            <button type="button" class="sidebar-btn" onclick="abrirPJE()" title="Abrir PJE com CPF do cliente">
                <i class="fas fa-balance-scale"></i> PJE
            </button>
            <a href="logout.php" class="sidebar-btn" style="display:block;text-align:left;"><i class="fas fa-sign-out-alt"></i> SAIR</a>
        </div>
        <div id="statusSalvamento" class="sidebar-status"></div>
    </aside>
    <div class="main-content">
    <div class="page-shell">
    <div class="container-clientes">
        <div class="form-area-fixed">
        <form id="formPrincipal" method="post">
            <input type="hidden" name="cliente_id" id="cliente_id_hidden" value="">

            <div class="kpi-grid-clientes">
                <div class="kpi-card kpi-card-green">
                    <div class="kpi-label">Total na listagem</div>
                    <div class="kpi-value" id="kpiTotalClientes">0</div>
                </div>
                <div class="kpi-card kpi-card-blue">
                    <div class="kpi-label">APROVADO</div>
                    <div class="kpi-value" id="kpiComProcesso">0</div>
                </div>
                <div class="kpi-card kpi-card-red">
                    <div class="kpi-label">Status pagando</div>
                    <div class="kpi-value" id="kpiPagando">0</div>
                </div>
                <div class="kpi-card kpi-card-yellow">
                    <div class="kpi-label">CONCLUSO SEM DECISÃO</div>
                    <div class="kpi-value" id="kpiComTelefone">0</div>
                </div>
            </div>
            
            <!-- Formulário completo de edição -->
            <div id="cadastro_pessoas" class="form-cadastro-container tab-content">
                <div class="cadastro-header-bar">
                    <h3 class="cadastro-header-title">CADASTRO DE PESSOAS</h3>
                    <div class="cadastro-header-actions">
                        <button type="button" onclick="salvarRegistro()" class="compact-action-btn btn-save">
                            💾 SALVAR
                        </button>
                        <button type="button" onclick="excluirRegistro()" class="compact-action-btn btn-close" style="background:#c0392b;">
                            🗑️ EXCLUIR
                        </button>
                        <button type="button" onclick="mostrarListagemClientes()" class="compact-action-btn btn-close">
                            ❌ FECHAR
                        </button>
                    </div>
                </div>
                <!-- Linha 1 -->
                <div class="form-row">
                    <div class="form-field form-field-small">
                        <label>CÓDIGO</label>
                        <input type="text" name="codigo" id="codigo" readonly>
                    </div>
                    <div class="form-field form-field-date">
                        <label>DATA CONTRATO<span class="required">*</span></label>
                        <input type="date" name="data_contrato" id="data_contrato" required>
                    </div>
                    <div class="checkbox-group">
                        <label>ASSINOU CONTRATO?</label>
                        <input type="checkbox" name="contrato_assinado" id="contrato_assinado" value="1">
                    </div>
                    <div class="form-field form-field-date">
                        <label>DATA ENVIADO</label>
                        <input type="date" name="data_enviado" id="data_enviado">
                    </div>
                    <div class="form-field form-field-medium">
                        <label>INDICADOR<span class="required">*</span></label>
                        <input type="text" name="indicador" id="indicador" required>
                    </div>
                    <div class="form-field form-field-medium">
                        <label>RESPONSÁVEL<span class="required">*</span></label>
                        <input type="text" name="responsavel" id="responsavel" required>
                    </div>
                    <div class="form-field form-field-medium">
                        <label>ADVOGADO</label>
                        <input type="text" name="advogado" id="advogado">
                    </div>
                    <div class="form-field form-field-large">
                        <label>BENEFÍCIO<span class="required">*</span></label>
                        <select name="beneficio" id="beneficio" required>
                            <option value="">Selecione</option>
                            <option value="Aposentadoria do Agricultor">Aposentadoria do Agricultor</option>
                            <option value="APOSENTADORIA PESCADOR">APOSENTADORIA PESCADOR</option>
                            <option value="APOSENTADORIA INDÍGENA">APOSENTADORIA INDÍGENA</option>
                            <option value="Aposentadoria por tempo de contribuição Urbana">Aposentadoria por tempo de contribuição Urbana</option>
                            <option value="Aposentadoria Especial Urbana">Aposentadoria Especial Urbana</option>
                            <option value="Aposentadoria por Invalidez Urbana">Aposentadoria por Invalidez Urbana</option>
                            <option value="Aposentadoria Híbrida">Aposentadoria Híbrida</option>
                            <option value="Pensão por morte">Pensão por morte</option>
                            <option value="BPC por idade">BPC por idade</option>
                            <option value="BPC por doença">BPC por doença</option>
                            <option value="Auxílio-Doença">Auxílio-Doença</option>
                            <option value="Auxílio-Acidente">Auxílio-Acidente</option>
                            <option value="Auxílio-Reclusão">Auxílio-Reclusão</option>
                            <option value="Salário-Maternidade Urbano">Salário-Maternidade Urbano</option>
                            <option value="Salário-Maternidade Agricultora">Salário-Maternidade Agricultora</option>
                            <option value="Salário-Maternidade Pescadora">Salário-Maternidade Pescadora</option>
                            <option value="SALÁRIO-MATERNIDADE INDÍGENA">SALÁRIO-MATERNIDADE INDÍGENA</option>
                            <option value="Divórcio">Divórcio</option>
                            <option value="AÇÃO TRABALHISTA">AÇÃO TRABALHISTA</option>
                            <option value="REGULARIZAÇÃO DE TERRAS">REGULARIZAÇÃO DE TERRAS</option>
                            <option value="REGULARIZAÇÃO DE IMÓVEIS">REGULARIZAÇÃO DE IMÓVEIS</option>
                            <option value="PASSAPORT">PASSAPORT</option>
                            <option value="2º VIA DE DOCUMENTOS">2º VIA DE DOCUMENTOS</option>
                            <option value="ISENÇÃO DE IMPOSTO DEFICIENTE">ISENÇÃO DE IMPOSTO DEFICIENTE</option>
                            <option value="JUROS ABUSIVOS">JUROS ABUSIVOS</option>
                            <option value="COBRANÇAS INDEVIDAS">COBRANÇAS INDEVIDAS</option>
                            <option value="AÇÃO JUDICIAL">AÇÃO JUDICIAL</option>
                        </select>
                    </div>
                    <div class="form-field form-field-large">
                        <label>STATUS<span class="required">*</span></label>
                        <select name="situacao" id="situacao" required>
                            <option value="">Selecione</option>
                            <option value="ENVIADO">ENVIADO</option>
                            <option value="NEGADO">NEGADO</option>
                            <option value="APROVADO">APROVADO</option>
                            <option value="PAGO">PAGO</option>
                            <option value="PERÍCIA">PERÍCIA</option>
                            <option value="JUSTIÇA">JUSTIÇA</option>
                            <option value="AVALIAÇÃO SOCIAL">AVALIAÇÃO SOCIAL</option>
                            <option value="INDEFERIDO">INDEFERIDO</option>
                            <option value="DEFERIDO">DEFERIDO</option>
                            <option value="ESCRITÓRIO">ESCRITÓRIO</option>
                            <option value="PENDÊNCIA">PENDÊNCIA</option>
                            <option value="CANCELADO">CANCELADO</option>
                            <option value="FALTA A SENHA DO MEUINSS">FALTA A SENHA DO MEUINSS</option>
                            <option value="ESPERANDO DATA CERTA">ESPERANDO DATA CERTA</option>
                            <option value="FALTA ASSINAR CONTRATO">FALTA ASSINAR CONTRATO</option>
                            <option value="CLIENTE NÃO PAGOU O ESCRITÓRIO">CLIENTE NÃO PAGOU O ESCRITÓRIO</option>
                            <option value="BAIXA DEFINITIVA">BAIXA DEFINITIVA</option>
                            <option value="CADASTRO DE BIOMETRIA">CADASTRO DE BIOMETRIA</option>
                            <option value="CONCLUÍDO SEM DECISÃO">CONCLUÍDO SEM DECISÃO</option>
                            <option value="REENVIAR">REENVIAR</option>
                            <option value="PAGANDO">PAGANDO</option>
                            <option value="ATENDIMENTO">ATENDIMENTO</option>
                            <option value="A CRIANÇA AINDA NÃO NASCEU">A CRIANÇA AINDA NÃO NASCEU</option>
                        </select>
                    </div>
                    <div class="form-field" style="flex: 0 0 260px; min-width: 260px;">
                        <label>Nº PROCESSO</label>
                        <input type="text" name="numero_processo" id="numero_processo">
                    </div>
                    <div class="form-field" style="flex: 0 0 180px;">
                        <label>SENHA GOV</label>
                        <input type="text" name="senha_meuinss" id="senha_meuinss" class="no-uppercase">
                    </div>
                </div>
                
                <!-- Linha 2 -->
                <div class="form-row">
                    <div class="form-field" style="flex: 3 1 260px;">
                        <label>NOME<span class="required">*</span></label>
                        <input type="text" name="nome" id="nome" required>
                    </div>
                    <div class="form-field form-field-large">
                        <label>NACIONALIDADE<span class="required">*</span></label>
                        <input type="text" name="nacionalidade" id="nacionalidade" required>
                    </div>
                    <div class="form-field form-field-large">
                        <label>ESTADO CIVIL<span class="required">*</span></label>
                        <select name="estado_civil" id="estado_civil" required>
                            <option value="">Selecione</option>
                            <option value="SOLTEIRO(A)">SOLTEIRO(A)</option>
                            <option value="CASADO(A)">CASADO(A)</option>
                            <option value="DIVORCIADO(A)">DIVORCIADO(A)</option>
                            <option value="VIÚVO(A)">VIÚVO(A)</option>
                            <option value="UNIÃO ESTÁVEL">UNIÃO ESTÁVEL</option>
                        </select>
                    </div>
                    <div class="form-field form-field-large">
                        <label>PROFISSÃO<span class="required">*</span></label>
                        <input type="text" name="profissao" id="profissao" required>
                    </div>
                    <div class="form-field form-field-large">
                        <label>IDENTIDADE</label>
                        <input type="text" name="rg" id="rg">
                    </div>
                    <div class="form-field form-field-date">
                        <label>DATA DE NASCIMENTO<span class="required">*</span></label>
                        <input type="date" name="data_nascimento" id="data_nascimento" required>
                    </div>
                    <div class="form-field form-field-small">
                        <label>IDADE</label>
                        <input type="text" name="idade" id="idade" readonly style="background: rgba(15, 23, 42, 0.8); color: #ff3366 !important; font-weight: 700 !important; border: 1px solid rgba(255, 51, 102, 0.3);">
                    </div>
                    <div class="form-field form-field-large">
                        <label>CPF<span class="required">*</span></label>
                        <input type="text" name="cpf" id="cpf" maxlength="14" oninput="formatarCPF(this)" required>
                    </div>
                </div>
                
                <!-- Linha 3 -->
                <div class="form-row">
                    <div class="form-field form-field-large">
                        <label>ENDEREÇO<span class="required">*</span></label>
                        <input type="text" name="endereco" id="endereco" required>
                    </div>
                    <div class="form-field form-field-large">
                        <label>CIDADE<span class="required">*</span></label>
                        <input type="text" name="cidade" id="cidade" required>
                    </div>
                    <div class="form-field form-field-small">
                        <label>UF<span class="required">*</span></label>
                        <select name="estado" id="estado" required>
                            <option value="">Selecione</option>
                            <option value="AM">AM</option>
                            <option value="AC">AC</option>
                            <option value="RO">RO</option>
                            <option value="RR">RR</option>
                            <option value="PA">PA</option>
                            <option value="AP">AP</option>
                            <option value="TO">TO</option>
                            <option value="MA">MA</option>
                            <option value="PI">PI</option>
                            <option value="CE">CE</option>
                            <option value="RN">RN</option>
                            <option value="PB">PB</option>
                            <option value="PE">PE</option>
                            <option value="AL">AL</option>
                            <option value="SE">SE</option>
                            <option value="BA">BA</option>
                            <option value="MG">MG</option>
                            <option value="ES">ES</option>
                            <option value="RJ">RJ</option>
                            <option value="SP">SP</option>
                            <option value="PR">PR</option>
                            <option value="SC">SC</option>
                            <option value="RS">RS</option>
                            <option value="MS">MS</option>
                            <option value="MT">MT</option>
                            <option value="GO">GO</option>
                            <option value="DF">DF</option>
                        </select>
                    </div>
                </div>
                
                <!-- Linha 5 -->
                <div class="form-row">
                    <div class="form-field form-field-large">
                        <label>FONE 1</label>
                        <input type="text" name="telefone" id="telefone" maxlength="16" oninput="formatarTelefone(this)">
                    </div>
                    <div class="form-field form-field-large">
                        <label>FONE 2</label>
                        <input type="text" name="telefone2" id="telefone2" maxlength="16" oninput="formatarTelefone(this)">
                    </div>
                    <div class="form-field form-field-large">
                        <label>FONE 3</label>
                        <input type="text" name="telefone3" id="telefone3" maxlength="16" oninput="formatarTelefone(this)">
                    </div>
                    <div class="form-field form-field-large">
                        <label>E-MAIL</label>
                        <input type="email" name="email" id="email">
                    </div>
                    <div class="form-field form-field-medium">
                        <label>SENHA E-MAIL</label>
                        <input type="text" name="senha_email" id="senha_email" class="no-uppercase">
                    </div>
                </div>
                
                <!-- Linha 6: Avaliação Social -->
                <div class="form-row">
                    <div class="form-field form-field-date">
                        <label>DATA AVALIAÇÃO SOCIAL</label>
                        <input type="date" name="data_avaliacao_social" id="data_avaliacao_social">
                    </div>
                    <div class="form-field form-field-small">
                        <label>HORA</label>
                        <input type="time" name="hora_avaliacao_social" id="hora_avaliacao_social">
                    </div>
                    <div class="form-field form-field-large">
                        <label>ENDEREÇO AVALIAÇÃO SOCIAL</label>
                        <div class="split-inputs">
                            <input type="text" name="endereco_avaliacao_social" id="endereco_avaliacao_social" placeholder="Endereço">
                            <input type="text" name="endereco_avaliacao_social_link" id="endereco_avaliacao_social_link" class="link-input" placeholder="Link do endereço" oninput="updateLinkColor('endereco_avaliacao_social_link')">
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <label>AVALIAÇÃO SOCIAL</label>
                        <input type="checkbox" name="avaliacao_social_realizado" id="avaliacao_social_realizado" value="1">
                    </div>
                </div>
                
                <!-- Linha 7: Perícia -->
                <div class="form-row">
                    <div class="form-field form-field-date">
                        <label>DATA PERÍCIA INSS</label>
                        <input type="date" name="data_pericia" id="data_pericia">
                    </div>
                    <div class="form-field form-field-small">
                        <label>HORA</label>
                        <input type="time" name="hora_pericia" id="hora_pericia">
                    </div>
                    <div class="form-field form-field-large">
                        <label>ENDEREÇO PERÍCIA</label>
                        <div class="split-inputs">
                            <input type="text" name="endereco_pericia" id="endereco_pericia" placeholder="Endereço">
                            <input type="text" name="endereco_pericia_link" id="endereco_pericia_link" class="link-input" placeholder="Link do endereço" oninput="updateLinkColor('endereco_pericia_link')">
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <label>PERÍCIA INSS</label>
                        <input type="checkbox" name="pericia_realizado" id="pericia_realizado" value="1">
                    </div>
                </div>
                
                <!-- Linha 8: Observação -->
                <div class="form-row">
                    <div class="form-field" style="flex: 1;">
                        <label>OBSERVAÇÃO</label>
                        <textarea name="observacao" id="observacao" rows="3" style="resize: vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- Abas de Navegação -->
            <div class="actions-tabs">
                <div id="agendamento" class="tab-content">
                    <div style="padding: 20px; background: #fafcff; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0; color: #000000; font-size: 11pt;">CADASTRO DE FILHOS MENORES</h3>
                            <button type="button" onclick="fecharAbaFilhos()" class="compact-action-btn btn-close">
                                ❌ FECHAR
                            </button>
                        </div>
                        
                        <!-- Indicador do cliente selecionado -->
                        <div id="indicadorClienteFilhos" style="background: #e3f2fd; border: 2px solid #2196F3; border-radius: 5px; padding: 10px; margin-bottom: 15px; display: none;">
                            <span style="font-weight: bold; color: #000000; font-size: 9pt;">📋 CLIENTE SELECIONADO:</span>
                            <span id="nomeClienteFilhos" style="color: #333; font-size: 9pt; margin-left: 8px;"></span>
                            <span style="color: #666; font-size: 8pt; margin-left: 8px;">(ID: <span id="idClienteFilhos"></span>)</span>
                        </div>
                        
                        <!-- Formulário para adicionar filho menor -->
                        <div style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
                            <input type="hidden" id="filho_id_edicao" value="">
                            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                <div style="flex: 2; min-width: 200px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">NOME DO FILHO(A)</label>
                                    <input type="text" id="filho_nome" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">DATA DE NASCIMENTO</label>
                                    <input type="date" id="filho_data_nascimento" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;" onchange="calcularIdadeFilho()">
                                </div>
                                <div style="flex: 0 0 80px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">IDADE</label>
                                    <input type="text" id="filho_idade" readonly style="width: 100%; padding: 5px; border: 1px solid #4a90e2; border-radius: 3px; font-size: 9pt; background: #e3f2fd; color: #333333 !important; font-weight: 400 !important;">
                                </div>
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">CPF</label>
                                    <input type="text" id="filho_cpf" maxlength="14" oninput="formatarCPFFilho(this)" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">SENHA GOV</label>
                                    <input type="text" id="filho_senha_gov" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 0 0 auto;">
                                    <button type="button" id="btnSalvarFilho" onclick="adicionarFilhoMenor()" class="compact-action-btn btn-save">
                                        ➕ ADICIONAR
                                    </button>
                                    <button type="button" id="btnCancelarEdicao" onclick="cancelarEdicaoFilho()" class="compact-action-btn btn-cancel" style="display: none; margin-left: 5px;">
                                        ❌ CANCELAR
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lista de filhos menores cadastrados -->
                        <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                            <h4 style="margin-bottom: 10px; color: #333; font-size: 10pt;">Filhos Cadastrados</h4>
                            <table id="tabelaFilhosMenores" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #e9ecef; border-bottom: 2px solid #999;">
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd;">NOME</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 130px; color: #000000;">DATA NASCIMENTO</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 80px;">IDADE</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 130px;">CPF</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 130px;">SENHA GOV</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 90px;">EDITAR</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 90px;">EXCLUIR</th>
                                    </tr>
                                </thead>
                                <tbody id="listaFilhosMenores">
                                    <tr>
                                        <td colspan="6" style="padding: 20px; text-align: center; color: #999; font-size: 9pt;">
                                            Nenhum filho cadastrado
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="a_rogo" class="tab-content">
                    <div style="padding: 20px; background: #fafcff; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0; color: #000000; font-size: 11pt;">CADASTRO A ROGO</h3>
                            <button type="button" onclick="fecharAbaARogo()" class="compact-action-btn btn-close">
                                ❌ FECHAR
                            </button>
                        </div>

                        <div id="indicadorClienteARogo" style="background: #e3f2fd; border: 2px solid #2196F3; border-radius: 5px; padding: 10px; margin-bottom: 15px; display: none;">
                            <span style="font-weight: bold; color: #000000; font-size: 9pt;">📋 CLIENTE SELECIONADO:</span>
                            <span id="nomeClienteARogo" style="color: #333; font-size: 9pt; margin-left: 8px;"></span>
                            <span style="color: #666; font-size: 8pt; margin-left: 8px;">(ID: <span id="idClienteARogo"></span>)</span>
                        </div>

                        <div style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
                            <input type="hidden" id="a_rogo_id_edicao" value="">
                            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                <div style="flex: 2; min-width: 220px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">NOME</label>
                                    <input type="text" id="a_rogo_nome" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 1; min-width: 160px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">IDENTIDADE</label>
                                    <input type="text" id="a_rogo_identidade" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 1; min-width: 160px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">CPF</label>
                                    <input type="text" id="a_rogo_cpf" maxlength="14" oninput="formatarCPF(this)" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 0 0 auto;">
                                    <button type="button" id="btnSalvarARogo" onclick="adicionarARogo()" class="compact-action-btn btn-save">
                                        ➕ ADICIONAR
                                    </button>
                                    <button type="button" id="btnCancelarEdicaoARogo" onclick="cancelarEdicaoARogo()" class="compact-action-btn btn-cancel" style="display: none; margin-left: 5px;">
                                        ❌ CANCELAR
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                            <h4 style="margin-bottom: 10px; color: #333; font-size: 10pt;">A ROGO Cadastrados</h4>
                            <table id="tabelaARogo" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #e9ecef; border-bottom: 2px solid #999;">
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd;">NOME</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 180px;">IDENTIDADE</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 150px;">CPF</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 90px;">EDITAR</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 90px;">EXCLUIR</th>
                                    </tr>
                                </thead>
                                <tbody id="listaARogo">
                                    <tr>
                                        <td colspan="5" style="padding: 20px; text-align: center; color: #999; font-size: 9pt;">
                                            Nenhum registro A ROGO cadastrado
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="incapaz" class="tab-content">
                    <div style="padding: 20px; background: #fafcff; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0; color: #000000; font-size: 11pt;">CADASTRO DE INCAPAZ</h3>
                            <button type="button" onclick="fecharAbaIncapaz()" class="compact-action-btn btn-close">
                                ❌ FECHAR
                            </button>
                        </div>

                        <div id="indicadorClienteIncapaz" style="background: #e3f2fd; border: 2px solid #2196F3; border-radius: 5px; padding: 10px; margin-bottom: 15px; display: none;">
                            <span style="font-weight: bold; color: #000000; font-size: 9pt;">📋 CLIENTE SELECIONADO:</span>
                            <span id="nomeClienteIncapaz" style="color: #333; font-size: 9pt; margin-left: 8px;"></span>
                            <span style="color: #666; font-size: 8pt; margin-left: 8px;">(ID: <span id="idClienteIncapaz"></span>)</span>
                        </div>

                        <div style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
                            <input type="hidden" id="incapaz_id_edicao" value="">
                            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                <div style="flex: 2; min-width: 200px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">NOME DO INCAPAZ</label>
                                    <input type="text" id="incapaz_nome" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">DATA DE NASCIMENTO</label>
                                    <input type="date" id="incapaz_data_nascimento" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;" onchange="calcularIdadeIncapaz()">
                                </div>
                                <div style="flex: 0 0 80px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">IDADE</label>
                                    <input type="text" id="incapaz_idade" readonly style="width: 100%; padding: 5px; border: 1px solid #4a90e2; border-radius: 3px; font-size: 9pt; background: #e3f2fd; color: #333333 !important; font-weight: 400 !important;">
                                </div>
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">CPF</label>
                                    <input type="text" id="incapaz_cpf" maxlength="14" oninput="formatarCPFIncapaz(this)" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 1; min-width: 140px;">
                                    <label style="display: block; font-size: 8pt; font-weight: bold; margin-bottom: 3px; color: #000000;">SENHA GOV</label>
                                    <input type="text" id="incapaz_senha_gov" style="width: 100%; padding: 5px; border: 1px solid #999; border-radius: 3px; font-size: 9pt;">
                                </div>
                                <div style="flex: 0 0 auto;">
                                    <button type="button" id="btnSalvarIncapaz" onclick="adicionarIncapaz()" class="compact-action-btn btn-save">
                                        ➕ ADICIONAR
                                    </button>
                                    <button type="button" id="btnCancelarEdicaoIncapaz" onclick="cancelarEdicaoIncapaz()" class="compact-action-btn btn-cancel" style="display: none; margin-left: 5px;">
                                        ❌ CANCELAR
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                            <h4 style="margin-bottom: 10px; color: #333; font-size: 10pt;">Incapazes Cadastrados</h4>
                            <table id="tabelaIncapazes" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #e9ecef; border-bottom: 2px solid #999;">
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd;">NOME</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 130px; color: #000000;">DATA NASCIMENTO</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 80px;">IDADE</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 130px;">CPF</th>
                                        <th style="padding: 8px; text-align: left; font-size: 8pt; border: 1px solid #ddd; width: 130px;">SENHA GOV</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 90px;">EDITAR</th>
                                        <th style="padding: 8px; text-align: center; font-size: 8pt; border: 1px solid #ddd; width: 90px;">EXCLUIR</th>
                                    </tr>
                                </thead>
                                <tbody id="listaIncapazes">
                                    <tr>
                                        <td colspan="7" style="padding: 20px; text-align: center; color: #999; font-size: 9pt;">
                                            Nenhum incapaz cadastrado
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filtros-container">
                <div class="filtros-grid">
                    <div class="filtros-title">Filtros</div>
                    <div class="form-field filtro-item filtro-item-beneficio">
                        <label class="filtro-label">BENEFÍCIO</label>
                        <select id="filtro_beneficio" name="filtro_beneficio" class="filtro-control">
                            <option value="">Todos</option>
                            <?php
                            $filtro_beneficio_selected = isset($_GET['filtro_beneficio']) ? $_GET['filtro_beneficio'] : '';
                            $beneficios_ordem_fixa = [
                                'Aposentadoria do Agricultor',
                                'APOSENTADORIA PESCADOR',
                                'APOSENTADORIA INDÍGENA',
                                'Aposentadoria por tempo de contribuição Urbana',
                                'Aposentadoria Especial Urbana',
                                'Aposentadoria por Invalidez Urbana',
                                'Aposentadoria Híbrida',
                                'Pensão por morte',
                                'BPC por idade',
                                'BPC por doença',
                                'Auxílio-Doença',
                                'Auxílio-Acidente',
                                'Auxílio-Reclusão',
                                'Salário-Maternidade Urbano',
                                'Salário-Maternidade Agricultora',
                                'Salário-Maternidade Pescadora',
                                'SALÁRIO-MATERNIDADE INDÍGENA',
                                'Divórcio',
                                'AÇÃO TRABALHISTA',
                                'REGULARIZAÇÃO DE TERRAS',
                                'REGULARIZAÇÃO DE IMÓVEIS',
                                'PASSAPORT',
                                '2º VIA DE DOCUMENTOS',
                                'ISENÇÃO DE IMPOSTO DEFICIENTE',
                                'JUROS ABUSIVOS',
                                'COBRANÇAS INDEVIDAS',
                                'AÇÃO JUDICIAL'
                            ];

                            foreach ($beneficios_ordem_fixa as $benef) {
                                $selected = ($filtro_beneficio_selected === $benef) ? 'selected' : '';
                                $benef_esc = htmlspecialchars($benef, ENT_QUOTES, 'UTF-8');
                                echo '<option value="' . $benef_esc . '" ' . $selected . '>' . $benef_esc . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-field filtro-item filtro-item-status">
                        <label class="filtro-label">STATUS</label>
                        <select id="filtro_status" name="filtro_status" class="filtro-control">
                            <option value="">Todos</option>
                            <?php
                            $filtro_status_selected = isset($_GET['filtro_status']) ? $_GET['filtro_status'] : '';

                            $status_ordem_fixa = [
                                'ENVIADO',
                                'NEGADO',
                                'APROVADO',
                                'PAGO',
                                'PERÍCIA',
                                'JUSTIÇA',
                                'AVALIAÇÃO SOCIAL',
                                'INDEFERIDO',
                                'DEFERIDO',
                                'ESCRITÓRIO',
                                'PENDÊNCIA',
                                'CANCELADO',
                                'FALTA A SENHA DO MEUINSS',
                                'ESPERANDO DATA CERTA',
                                'FALTA ASSINAR CONTRATO',
                                'CLIENTE NÃO PAGOU O ESCRITÓRIO',
                                'BAIXA DEFINITIVA',
                                'CADASTRO DE BIOMETRIA',
                                'CONCLUÍDO SEM DECISÃO',
                                'REENVIAR',
                                'PAGANDO',
                                'ATENDIMENTO',
                                'A CRIANÇA AINDA NÃO NASCEU'
                            ];

                            foreach ($status_ordem_fixa as $status) {
                                $selected = ($filtro_status_selected === $status) ? 'selected' : '';
                                $status_esc = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
                                echo '<option value="' . $status_esc . '" ' . $selected . '>' . $status_esc . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-field filtro-item filtro-item-nome">
                        <label class="filtro-label">NOME</label>
                        <input type="text" id="filtro_nome" name="filtro_nome" placeholder="Digite o nome" value="<?php echo isset($_GET['filtro_nome']) ? htmlspecialchars($_GET['filtro_nome'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="filtro-control">
                    </div>
                    <div class="form-field filtro-item filtro-item-cpf">
                        <label class="filtro-label">CPF</label>
                        <input type="text" id="cpf_search" name="cpf_search" placeholder="Digite o CPF" value="<?php echo isset($_GET['cpf_search']) ? htmlspecialchars($_GET['cpf_search'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="filtro-control" maxlength="14" oninput="formatarCPF(this)">
                    </div>
                    <div class="form-field filtro-item filtro-item-indicador">
                        <label class="filtro-label">INDICADOR</label>
                        <input type="text" id="filtro_indicador" name="filtro_indicador" placeholder="Digite o indicador" class="filtro-control">
                    </div>
                    <div class="filtro-actions">
                        <button type="button" onclick="aplicarFiltros()" class="btn-filtro btn-aplicar">
                            <i class="fas fa-filter"></i> APLICAR FILTROS
                        </button>
                        <button type="button" onclick="limparFiltros()" class="btn-filtro btn-limpar">
                            <i class="fas fa-times"></i> LIMPAR
                        </button>
                        <button type="button" onclick="gerarPDFFiltrado()" class="btn-filtro btn-pdf">
                            <i class="fas fa-file-pdf"></i> GERAR PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
            <!-- Tabela de clientes -->
            <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>#ID</th>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Senha gov</th>
                        <th>Benefício</th>
                        <th>Status</th>
                        <th>Indicador</th>
                        <th>Av. Social</th>
                        <th>Perícia</th>
                        <th>Telefone</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cpf_search = isset($_GET['cpf_search']) ? trim($_GET['cpf_search']) : '';
                    $filtro_nome = isset($_GET['filtro_nome']) ? trim($_GET['filtro_nome']) : '';
                    $filtro_beneficio = isset($_GET['filtro_beneficio']) ? trim($_GET['filtro_beneficio']) : '';
                    $filtro_status = isset($_GET['filtro_status']) ? trim($_GET['filtro_status']) : '';
                    $filtro_indicador = isset($_GET['filtro_indicador']) ? trim($_GET['filtro_indicador']) : '';
                    $filtro_av_social = isset($_GET['filtro_av_social']) ? trim($_GET['filtro_av_social']) : '';
                    $filtro_pericia = isset($_GET['filtro_pericia']) ? trim($_GET['filtro_pericia']) : '';

                    $exprTemFilho = tabelaExiste($conn, 'filhos_menores')
                        ? "EXISTS (SELECT 1 FROM filhos_menores fm WHERE fm.cliente_id = clientes.id LIMIT 1)"
                        : "0";
                    $exprTemIncapaz = tabelaExiste($conn, 'incapazes')
                        ? "EXISTS (SELECT 1 FROM incapazes i WHERE i.cliente_id = clientes.id LIMIT 1)"
                        : "0";
                    $exprTemARogo = tabelaExiste($conn, 'a_rogo')
                        ? "EXISTS (SELECT 1 FROM a_rogo ar WHERE ar.cliente_id = clientes.id LIMIT 1)"
                        : "0";
                    
                    $sql = "SELECT id, nome, cpf, data_nascimento, senha_meuinss, beneficio, situacao, indicador,
                            endereco, cidade, telefone, email, rg, estado_civil, nacionalidade, profissao,
                            data_contrato, data_avaliacao_social, data_pericia, observacao,
                            data_enviado, responsavel, advogado, numero_processo, uf,
                            telefone2, telefone3, senha_email, cep,
                            hora_avaliacao_social, endereco_avaliacao_social, realizado_a_s,
                            hora_pericia, endereco_pericia, realizado_pericia, contrato_assinado,
                            (" . $exprTemFilho . ") AS tem_filho,
                            (" . $exprTemIncapaz . ") AS tem_incapaz,
                            (" . $exprTemARogo . ") AS tem_a_rogo
                            FROM clientes WHERE 1=1";
                    $types = "";
                    $params = array();
                    
                    // Filtrar por usuário PARCEIRO (apenas seus clientes)
                    // ADMIN e USUARIO veem todos os clientes
                    if ($is_parceiro) {
                        $sql .= " AND usuario_cadastro_id = ?";
                        $types .= "i";
                        $params[] = $usuario_id;
                    }

                    if ($cpf_search !== '') {
                        // Normalize digits so user can search with or without formatting
                        $cpf_norm = preg_replace('/\D/', '', $cpf_search);
                        // remove punctuation from cpf column for comparison
                        $sql .= " AND REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') LIKE ?";
                        $types .= "s";
                        $params[] = "%" . $cpf_norm . "%";
                    }
                    
                    if ($filtro_nome !== '') {
                        $sql .= " AND nome LIKE ?";
                        $types .= "s";
                        $params[] = "%" . $filtro_nome . "%";
                    }
                    
                    if ($filtro_beneficio !== '') {
                        beneficio_aplicar_filtro($sql, $types, $params, $filtro_beneficio, 'beneficio');
                    }
                    
                    if ($filtro_status !== '') {
                        $sql .= " AND situacao = ?";
                        $types .= "s";
                        $params[] = $filtro_status;
                    }
                    
                    if ($filtro_indicador !== '') {
                        $sql .= " AND indicador LIKE ?";
                        $types .= "s";
                        $params[] = "%" . $filtro_indicador . "%";
                    }
                    
                    if ($filtro_av_social !== '') {
                        if ($filtro_av_social === 'realizado') {
                            $sql .= " AND data_avaliacao_social IS NOT NULL AND data_avaliacao_social != '0000-00-00'";
                        } else {
                            $sql .= " AND (data_avaliacao_social IS NULL OR data_avaliacao_social = '0000-00-00')";
                        }
                    }
                    
                    if ($filtro_pericia !== '') {
                        if ($filtro_pericia === 'realizado') {
                            $sql .= " AND data_pericia IS NOT NULL AND data_pericia != '0000-00-00'";
                        } else {
                            $sql .= " AND (data_pericia IS NULL OR data_pericia = '0000-00-00')";
                        }
                    }

                    if ($db_indisponivel) {
                        $erroDetalhado = isset($db_connection_error) ? $db_connection_error : 'Erro de conexao com o banco de dados.';
                        echo "<tr><td colspan='11' class='erro-mensagem'>" . htmlspecialchars($erroDetalhado, ENT_QUOTES, 'UTF-8');
                        echo "<br><span style='color:#c00;font-size:10pt'>";
                        if (trim($db_erro_tecnico) !== '') {
                            echo htmlspecialchars($db_erro_tecnico, ENT_QUOTES, 'UTF-8');
                        } else {
                            echo 'Erro técnico vazio: verifique se o MySQL está rodando, se o banco realassessoria existe e se o usuário root está sem senha.';
                        }
                        echo "</span></td></tr>";
                    } elseif ($stmt = $conn->prepare($sql)) {
                        // bind params dynamically only if there are params
                        if (!empty($params)) {
                            $bind_names = array();
                            $bind_names[] = & $types;
                            for ($i = 0; $i < count($params); $i++) {
                                $bind_names[] = & $params[$i];
                            }
                            call_user_func_array(array($stmt, 'bind_param'), $bind_names);
                        }

                        if (!$stmt->execute()) {
                            echo "<tr><td colspan='11' class='erro-mensagem'>Erro ao carregar dados. Tente novamente.</td></tr>";
                        } else {
                            list($ok, $rows, $fetchError) = stmtFetchAllAssoc($stmt);
                            if (!$ok) {
                                echo "<tr><td colspan='11' class='erro-mensagem'>Erro na leitura dos resultados: " . htmlspecialchars($fetchError) . "</td></tr>";
                            } elseif (count($rows) > 0) {
                                foreach ($rows as $row) {
                                // Armazenar todos os dados em JSON para JavaScript usar
                                $rowJson = json_encode($row, JSON_HEX_QUOT | JSON_HEX_APOS);
                                $marcadores = array();
                                if (!empty($row['tem_filho'])) {
                                    $marcadores[] = "<span class='marcador-vinculo' title='Tem cadastro: Filho'>F</span>";
                                }
                                if (!empty($row['tem_incapaz'])) {
                                    $marcadores[] = "<span class='marcador-vinculo marcador-vinculo-incapaz' title='Tem cadastro: Incapaz'>I</span>";
                                }
                                if (!empty($row['tem_a_rogo'])) {
                                    $marcadores[] = "<span class='marcador-vinculo marcador-vinculo-arogo' title='Tem cadastro: A Rogo'>R</span>";
                                }
                                $marcadorHtml = '';
                                if (!empty($marcadores)) {
                                    $marcadorHtml = " <span class='marcador-vinculos'>" . implode('', $marcadores) . "</span>";
                                }
                                // Calcular idade
                                $idade = '';
                                if (!empty($row['data_nascimento']) && $row['data_nascimento'] !== '0000-00-00') {
                                    $dn = DateTime::createFromFormat('Y-m-d', $row['data_nascimento']);
                                    if ($dn) {
                                        $hoje = new DateTime();
                                        $idade = $hoje->diff($dn)->y;
                                    }
                                }
                                $queryString = http_build_query([
                                    'nome' => $row['nome'],
                                    'cpf' => $row['cpf'],
                                    'data_nascimento' => $row['data_nascimento'],
                                    'idade' => $idade
                                ]);
                                echo "<tr class='cliente-row' data-cliente='" . htmlspecialchars($rowJson, ENT_QUOTES) . "' onclick='carregarClienteNoFormulario(this)' style='cursor: pointer;'>";
                                // Radio button para seleção
                                echo "<td data-label='' onclick='event.stopPropagation();'><input type='radio' name='cliente_id_radio' value='" . htmlspecialchars($row['id']) . "'></td>";
                                echo "<td data-label='#ID'>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td data-label='NOME'>" . htmlspecialchars($row['nome']) . $marcadorHtml . "</td>";
                                echo "<td data-label='CPF'>" . htmlspecialchars(formatarCPF($row['cpf'])) . "</td>";
                                echo "<td data-label='SENHA GOV' class='senha-campo' style='text-transform: none !important;'>" . htmlspecialchars($row['senha_meuinss']) . "</td>";
                                echo "<td data-label='BENEFÍCIO'>" . htmlspecialchars($row['beneficio']) . "</td>";
                                echo "<td data-label='STATUS'>" . htmlspecialchars($row['situacao']) . "</td>";
                                echo "<td data-label='INDICADOR'>" . htmlspecialchars($row['indicador']) . "</td>";
                                echo "<td data-label='AV. SOCIAL'>" . htmlspecialchars(formatarData($row['data_avaliacao_social'])) . "</td>";
                                echo "<td data-label='PERÍCIA'>" . htmlspecialchars(formatarData($row['data_pericia'])) . "</td>";
                                echo "<td data-label='TELEFONE'>" . htmlspecialchars(formatarTelefone($row['telefone'])) . "</td>";
                                echo "</tr>";
                            }
                            } else {
                                echo "<tr><td colspan='11' class='sem-resultados'>Nenhum cliente cadastrado.</td></tr>";
                            }
                        }
                        $stmt->close();
                    } else {
                        echo "<tr><td colspan='11' class='erro-mensagem'>Erro ao carregar dados. Tente novamente.</td></tr>";
                    }
                    if ($conn instanceof mysqli) {
                        $conn->close();
                    }
                    ?>
                </tbody>
            </table>
            </div>
            
        </form>

        <!-- Modal Pop-up de Informações do Cliente -->
        <div id="modalCliente" class="modal-popup" style="display: none;">
            <div class="modal-header">
                <span class="modal-titulo">INFORMAÇÕES DO CLIENTE</span>
                <button class="modal-fechar" onclick="fecharModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalConteudo">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>

        <script>
        // Forçar senhas a manterem formatação original
        document.addEventListener('DOMContentLoaded', function() {
            const senhasCells = document.querySelectorAll('.senha-campo');
            senhasCells.forEach(function(cell) {
                cell.style.textTransform = 'none';
                cell.style.setProperty('text-transform', 'none', 'important');
            });

            coletarClientesDaTabela();
            atualizarKpisListagem();
            
            // Adicionar evento de clique nos radio buttons para abrir modal
            const radioButtons = document.querySelectorAll('input[name="cliente_id_radio"]');
            radioButtons.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        abrirModalCliente(this.value);
                    }
                });
            });
        });

        function atualizarKpisListagem() {
            const rows = document.querySelectorAll('.cliente-row');
            let total = 0;
            let aprovados = 0;
            let pagando = 0;
            let conclusoSemDecisao = 0;

            rows.forEach(function(row) {
                const raw = row.getAttribute('data-cliente');
                if (!raw) {
                    return;
                }

                try {
                    const cliente = JSON.parse(raw);
                    total++;

                    const situacao = (cliente.situacao || '')
                        .toString()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase()
                        .trim();

                    if (situacao === 'aprovado') {
                        aprovados++;
                    }

                    if (situacao.indexOf('pagando') !== -1) {
                        pagando++;
                    }

                    if (situacao === 'concluido sem decisao' || situacao === 'concluso sem decisao') {
                        conclusoSemDecisao++;
                    }
                } catch (e) {
                    // Ignora linhas com data-cliente invalido para nao quebrar a tela.
                }
            });

            const elTotal = document.getElementById('kpiTotalClientes');
            const elComProcesso = document.getElementById('kpiComProcesso');
            const elPagando = document.getElementById('kpiPagando');
            const elComTelefone = document.getElementById('kpiComTelefone');

            if (elTotal) elTotal.textContent = String(total);
            if (elComProcesso) elComProcesso.textContent = String(aprovados);
            if (elPagando) elPagando.textContent = String(pagando);
            if (elComTelefone) elComTelefone.textContent = String(conclusoSemDecisao);
        }
        
        function enviarPara(destino) {
            var form = document.getElementById('formSelecionarCliente');
            var selecionado = form.querySelector('input[name="cliente_id"]:checked');
            if (!selecionado) {
                alert('Selecione um cliente primeiro!');
                return;
            }
            // Redireciona para o destino com o id do cliente na mesma aba
            window.location.href = destino + '?id=' + selecionado.value;
        }

        // Função para abrir editar diretamente
        function abrirEditar(event) {
            if (event) event.preventDefault();
            var form = document.getElementById('formSelecionarCliente');
            var selecionado = form.querySelector('input[name="cliente_id"]:checked');
            if (!selecionado) {
                alert('Selecione um cliente primeiro!');
                return false;
            }
            window.location.href = 'editar_cliente.php?id=' + selecionado.value;
            return false;
        }

        // Função para abrir financeiro diretamente
        function abrirFinanceiro(event) {
            if (event) event.preventDefault();
            var form = document.getElementById('formSelecionarCliente');
            var selecionado = form.querySelector('input[name="cliente_id"]:checked');
            if (!selecionado) {
                alert('Selecione um cliente primeiro!');
                return false;
            }
            window.location.href = 'financeiro.php?id=' + selecionado.value;
            return false;
        }

        // Função para abrir relatório
        function abrirRelatorio(event) {
            if (event) event.preventDefault();
            var clienteId = obterClienteSelecionadoId();
            var destino = 'gerador_relatorios.php';
            if (clienteId) {
                destino += '?id=' + encodeURIComponent(clienteId);
            }
            window.location.href = destino;
            return false;
        }

        function obterClienteSelecionadoId() {
            var selecionadoRadio = document.querySelector('input[name="cliente_id_radio"]:checked');
            if (selecionadoRadio) {
                return selecionadoRadio.value;
            }
            var hidden = document.getElementById('cliente_id_hidden');
            if (hidden && hidden.value) {
                return hidden.value;
            }
            return '';
        }

        function abrirModelosCliente() {
            var clienteId = obterClienteSelecionadoId();
            window.location.href = clienteId
                ? 'listar_modelos.php?cliente_id=' + clienteId
                : 'listar_modelos.php';
        }

        // Função para abrir relatório do cliente selecionado
        function abrirRelatorioCliente(event) {
            if (event) event.preventDefault();
            var clienteId = obterClienteSelecionadoId();
            if (!clienteId) {
                alert('Selecione um cliente primeiro!');
                return false;
            }
            window.location.href = 'gerar_relatorio_cliente.php?id=' + encodeURIComponent(clienteId);
            return false;
        }

        function obterCpfSelecionadoSomenteNumeros() {
            var campoCpf = document.getElementById('cpf');
            var cpf = campoCpf && campoCpf.value ? campoCpf.value.replace(/\D/g, '') : '';
            if (!cpf) {
                alert('Selecione um cliente primeiro para obter o CPF.');
                return '';
            }
            return cpf;
        }

        function abrirPortalComCpf(destino) {
            var cpf = obterCpfSelecionadoSomenteNumeros();
            if (!cpf) {
                return false;
            }

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = destino;
            form.target = '_blank';

            var inputCpf = document.createElement('input');
            inputCpf.type = 'hidden';
            inputCpf.name = 'cpf';
            inputCpf.value = cpf;

            form.appendChild(inputCpf);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            return false;
        }

        function abrirMeuINSS() {
            return abrirPortalComCpf('abrir_meuinss.php');
        }

        function abrirPJE() {
            return abrirPortalComCpf('abrir_pje.php');
        }

        // ========== MODAL MOVÍVEL ==========
        let modalDragging = false;
        let modalOffsetX = 0;
        let modalOffsetY = 0;
        
        // Função para abrir modal com informações do cliente
        function abrirModalCliente(clienteId) {
            const modal = document.getElementById('modalCliente');
            const conteudo = document.getElementById('modalConteudo');
            
            // Mostrar modal com mensagem de carregamento
            modal.style.display = 'flex';
            conteudo.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">Carregando informações...</div>';
            
            // Buscar todos os dados do cliente via AJAX
            fetch('buscar_cliente_completo.php?id=' + clienteId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const c = data.cliente;
                        
                        // Preencher apenas com os campos solicitados
                        conteudo.innerHTML = `
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Data do Contrato:</div>
                                <div class="modal-info-valor">${c.data_contrato}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Benefício:</div>
                                <div class="modal-info-valor">${c.beneficio}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Data de Enviado:</div>
                                <div class="modal-info-valor">${c.data_enviado || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Status:</div>
                                <div class="modal-info-valor">${c.situacao}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Indicador:</div>
                                <div class="modal-info-valor">${c.indicador}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Responsável:</div>
                                <div class="modal-info-valor">${c.responsavel || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Advogado(a):</div>
                                <div class="modal-info-valor">${c.advogado || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Nome:</div>
                                <div class="modal-info-valor">${c.nome}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Data de Nascimento:</div>
                                <div class="modal-info-valor">${c.data_nascimento_formatada}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Idade:</div>
                                <div class="modal-info-valor">${c.idade}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">CPF:</div>
                                <div class="modal-info-valor">${c.cpf}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Endereço:</div>
                                <div class="modal-info-valor">${c.endereco}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Cidade:</div>
                                <div class="modal-info-valor">${c.cidade}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">UF:</div>
                                <div class="modal-info-valor">${c.estado}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Telefone:</div>
                                <div class="modal-info-valor">${c.telefone}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Telefone 2:</div>
                                <div class="modal-info-valor">${c.telefone2}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Telefone 3:</div>
                                <div class="modal-info-valor">${c.telefone3}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">E-mail:</div>
                                <div class="modal-info-valor">${c.email}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Senha do E-mail:</div>
                                <div class="modal-info-valor senha-valor">${c.senha_email || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Senha do Gov:</div>
                                <div class="modal-info-valor senha-valor">${c.senha_meuinss}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Data da Avaliação Social:</div>
                                <div class="modal-info-valor">${c.data_avaliacao_social}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Hora da Avaliação Social:</div>
                                <div class="modal-info-valor">${c.hora_avaliacao_social || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Endereço da Avaliação Social:</div>
                                <div class="modal-info-valor">${c.endereco_avaliacao_social || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Realizado Avaliação Social?:</div>
                                <div class="modal-info-valor">${c.avaliacao_social_realizado}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Data da Perícia:</div>
                                <div class="modal-info-valor">${c.data_pericia}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Hora da Perícia:</div>
                                <div class="modal-info-valor">${c.hora_pericia || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Endereço da Perícia:</div>
                                <div class="modal-info-valor">${c.endereco_pericia || '-'}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Realizado Perícia?:</div>
                                <div class="modal-info-valor">${c.pericia_realizado}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Contrato Assinado?:</div>
                                <div class="modal-info-valor">${c.contrato_assinado}</div>
                            </div>
                            <div class="modal-info-linha">
                                <div class="modal-info-label">Observação:</div>
                                <div class="modal-info-valor">${c.observacao || '-'}</div>
                            </div>
                        `;
                    } else {
                        conteudo.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Erro ao carregar dados: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    conteudo.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Erro ao carregar informações do cliente.</div>';
                    console.error('Erro:', error);
                });
            
            // Configurar drag (mover modal)
            const header = modal.querySelector('.modal-header');
            header.onmousedown = iniciarDrag;
        }
        
        function fecharModal() {
            document.getElementById('modalCliente').style.display = 'none';
        }
        
        function iniciarDrag(e) {
            modalDragging = true;
            const modal = document.getElementById('modalCliente');
            const rect = modal.getBoundingClientRect();
            modalOffsetX = e.clientX - rect.left;
            modalOffsetY = e.clientY - rect.top;
            
            document.onmousemove = arrastarModal;
            document.onmouseup = pararDrag;
            
            e.preventDefault();
        }
        
        function arrastarModal(e) {
            if (!modalDragging) return;
            
            const modal = document.getElementById('modalCliente');
            let x = e.clientX - modalOffsetX;
            let y = e.clientY - modalOffsetY;
            
            // Limitar para não sair da tela
            const maxX = window.innerWidth - modal.offsetWidth;
            const maxY = window.innerHeight - modal.offsetHeight;
            
            x = Math.max(0, Math.min(x, maxX));
            y = Math.max(0, Math.min(y, maxY));
            
            modal.style.left = x + 'px';
            modal.style.top = y + 'px';
            modal.style.transform = 'none';
        }
        
        function pararDrag() {
            modalDragging = false;
            document.onmousemove = null;
            document.onmouseup = null;
        }

        // Função para trocar entre abas
        function switchTab(event, tabId) {
            // Remove active de todos os botões e conteúdos
            var tabButtons = document.querySelectorAll('.tab-btn');
            var tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(function(btn) {
                btn.classList.remove('active');
            });
            
            tabContents.forEach(function(content) {
                content.classList.remove('active');
            });
            
            // Adiciona active no botão clicado e no conteúdo correspondente
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
            var tabContent = document.getElementById(tabId);
            if (tabContent) {
                tabContent.classList.add('active');
            }
        }

        function mostrarListagemClientes() {
            var tabButtons = document.querySelectorAll('.tab-btn');
            var tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(function(btn) {
                btn.classList.remove('active');
            });

            tabContents.forEach(function(content) {
                content.classList.remove('active');
            });
        }
        
        // Função para aplicar filtros
        function aplicarFiltros() {
            try {
                var filtrosKey = 'mvpClientesFiltros';
                var beneficioEl = document.getElementById('filtro_beneficio');
                var statusEl = document.getElementById('filtro_status');
                var indicadorEl = document.getElementById('filtro_indicador');
                var cpfEl = document.getElementById('cpf_search');
                var nomeEl = document.getElementById('filtro_nome');
                
                if (!beneficioEl || !statusEl || !indicadorEl || !cpfEl || !nomeEl) {
                    console.error('Elementos de filtro não encontrados!');
                    alert('Erro: Elementos de filtro não encontrados na página.');
                    return;
                }
                
                var beneficio = beneficioEl.value || '';
                var status = statusEl.value || '';
                var indicador = indicadorEl.value ? indicadorEl.value.trim() : '';
                var cpf = cpfEl.value ? cpfEl.value.trim() : '';
                var nome = nomeEl.value ? nomeEl.value.trim() : '';
                
                // Construir URL base
                var url = new URL(window.location.href);
                
                // Limpar parâmetros anteriores de filtros
                url.searchParams.delete('filtro_beneficio');
                url.searchParams.delete('filtro_status');
                url.searchParams.delete('filtro_indicador');
                url.searchParams.delete('cpf_search');
                url.searchParams.delete('filtro_nome');
                
                // Adicionar novos parâmetros se tiverem valor
                // URLSearchParams.set já faz o encoding automaticamente
                if (beneficio !== '') {
                    url.searchParams.set('filtro_beneficio', beneficio);
                }
                
                if (status !== '') {
                    url.searchParams.set('filtro_status', status);
                }
                
                if (indicador !== '') {
                    url.searchParams.set('filtro_indicador', indicador);
                }
                
                if (cpf !== '') {
                    url.searchParams.set('cpf_search', cpf);
                }
                
                if (nome !== '') {
                    url.searchParams.set('filtro_nome', nome);
                }

                try {
                    localStorage.setItem(filtrosKey, JSON.stringify({
                        filtro_beneficio: beneficio,
                        filtro_status: status,
                        filtro_indicador: indicador,
                        cpf_search: cpf,
                        filtro_nome: nome
                    }));
                } catch (e) {
                    // Ignora falha de armazenamento.
                }
                
                console.log('Aplicando filtros:', { beneficio, status, indicador, cpf, nome });
                console.log('URL final:', url.toString());
                
                // Redirecionar para a URL com os filtros
                window.location.href = url.toString();
            } catch (error) {
                console.error('Erro ao aplicar filtros:', error);
                alert('Erro ao aplicar filtros: ' + error.message);
            }
        }
        
        // Função para limpar filtros
        function limparFiltros() {
            try {
                var filtrosKey = 'mvpClientesFiltros';
                if (document.getElementById('filtro_beneficio')) {
                    document.getElementById('filtro_beneficio').value = '';
                }
                if (document.getElementById('filtro_status')) {
                    document.getElementById('filtro_status').value = '';
                }
                if (document.getElementById('filtro_indicador')) {
                    document.getElementById('filtro_indicador').value = '';
                }
                if (document.getElementById('cpf_search')) {
                    document.getElementById('cpf_search').value = '';
                }
                if (document.getElementById('filtro_nome')) {
                    document.getElementById('filtro_nome').value = '';
                }
                
                var url = new URL(window.location.href);
                url.searchParams.delete('filtro_beneficio');
                url.searchParams.delete('filtro_status');
                url.searchParams.delete('filtro_indicador');
                url.searchParams.delete('cpf_search');
                url.searchParams.delete('filtro_nome');

                try {
                    localStorage.removeItem(filtrosKey);
                } catch (e) {
                    // Ignora falha de armazenamento.
                }
                
                window.location.href = url.toString();
            } catch (error) {
                console.error('Erro ao limpar filtros:', error);
                alert('Erro ao limpar filtros. Verifique o console para mais detalhes.');
            }
        }
        
        // Função para gerar PDF com filtros aplicados
        function gerarPDFFiltrado() {
            try {
                var beneficioEl = document.getElementById('filtro_beneficio');
                var statusEl = document.getElementById('filtro_status');
                var indicadorEl = document.getElementById('filtro_indicador');
                var cpfEl = document.getElementById('cpf_search');
                var nomeEl = document.getElementById('filtro_nome');
                
                var beneficio = beneficioEl ? beneficioEl.value : '';
                var status = statusEl ? statusEl.value : '';
                var indicador = indicadorEl ? indicadorEl.value.trim() : '';
                var cpf = cpfEl ? cpfEl.value.trim() : '';
                var nome = nomeEl ? nomeEl.value.trim() : '';
                
                // Construir URL para gerar PDF
                var url = 'gerar_pdf_clientes_filtrados.php?';
                var params = [];
                
                if (beneficio !== '') {
                    params.push('filtro_beneficio=' + encodeURIComponent(beneficio));
                }
                if (status !== '') {
                    params.push('filtro_status=' + encodeURIComponent(status));
                }
                if (indicador !== '') {
                    params.push('filtro_indicador=' + encodeURIComponent(indicador));
                }
                if (cpf !== '') {
                    params.push('cpf_search=' + encodeURIComponent(cpf));
                }
                if (nome !== '') {
                    params.push('filtro_nome=' + encodeURIComponent(nome));
                }
                
                url += params.join('&');
                
                console.log('Gerando PDF com URL:', url);
                
                // Abrir na mesma aba
                window.location.href = url;
            } catch (error) {
                console.error('Erro ao gerar PDF:', error);
                alert('Erro ao gerar PDF: ' + error.message);
            }
        }
        
        // Função para excluir cliente
        function confirmarExclusao(clienteId, clienteNome) {
            // Criar formulário para enviar via POST
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'excluir_cliente.php';
            
            var inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'cliente_id';
            inputId.value = clienteId;
            
            form.appendChild(inputId);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Função para excluir cliente selecionado via radio button
        function excluirClienteSelecionado() {
            var form = document.getElementById('formSelecionarCliente');
            var selecionado = form.querySelector('input[name="cliente_id"]:checked');
            if (!selecionado) {
                alert('Selecione um cliente primeiro!');
                return;
            }
            
            // Buscar o nome do cliente na linha selecionada
            var tr = selecionado.closest('tr');
            var tdNome = tr.querySelector('td[data-label="NOME"]');
            var clienteNome = tdNome ? tdNome.textContent.trim() : 'este cliente';
            
            confirmarExclusao(selecionado.value, clienteNome);
        }
        
        // Restaurar valores dos filtros (URL tem prioridade; sem URL usa localStorage)
        (function() {
            try {
                var filtrosKey = 'mvpClientesFiltros';

                // Controle de versão dos filtros salvos. Ao incrementar MVP_FILTROS_VERSION
                // (ex.: 'v1' -> 'v2') todos os filtros do sistema são limpos no primeiro acesso.
                var MVP_FILTROS_VERSION     = 'v1';
                var MVP_FILTROS_VERSION_KEY = 'mvpFiltrosVersion';
                try {
                    if (localStorage.getItem(MVP_FILTROS_VERSION_KEY) !== MVP_FILTROS_VERSION) {
                        ['mvpAgendaFiltroMes', 'mvpProcessosFiltroCliente',
                         'mvpFinanceiroResumoFiltros', 'mvpClientesFiltros'].forEach(function (k) {
                            localStorage.removeItem(k);
                        });
                        localStorage.setItem(MVP_FILTROS_VERSION_KEY, MVP_FILTROS_VERSION);
                    }
                } catch (e) { /* Ignora falha de armazenamento. */ }
                var url = new URL(window.location.href);
                var beneficio = url.searchParams.get('filtro_beneficio');
                var status = url.searchParams.get('filtro_status');
                var indicador = url.searchParams.get('filtro_indicador');
                var cpf = url.searchParams.get('cpf_search');
                var nome = url.searchParams.get('filtro_nome');

                if (!beneficio && !status && !indicador && !cpf && !nome) {
                    try {
                        var salvo = JSON.parse(localStorage.getItem(filtrosKey) || '{}');
                        beneficio = salvo.filtro_beneficio || '';
                        status = salvo.filtro_status || '';
                        indicador = salvo.filtro_indicador || '';
                        cpf = salvo.cpf_search || '';
                        nome = salvo.filtro_nome || '';
                    } catch (e) {
                        // Ignora leitura invalida.
                    }
                }

                if (beneficio && document.getElementById('filtro_beneficio')) {
                    document.getElementById('filtro_beneficio').value = beneficio;
                }
                if (status && document.getElementById('filtro_status')) {
                    document.getElementById('filtro_status').value = status;
                }
                if (indicador && document.getElementById('filtro_indicador')) {
                    document.getElementById('filtro_indicador').value = indicador;
                }
                if (cpf && document.getElementById('cpf_search')) {
                    document.getElementById('cpf_search').value = cpf;
                }
                if (nome && document.getElementById('filtro_nome')) {
                    document.getElementById('filtro_nome').value = nome;
                }
            } catch (error) {
                console.error('Erro ao restaurar filtros:', error);
            }
        })();
        </script>

        <script>
        // Forçar inputs e textos para CAIXA ALTA em tempo real e normalizar itálico
        (function(){
            var form = document.getElementById('formSelecionarCliente');
            if (!form) return;

            function normalizeValue(el){
                if (!el) return;
                // Não aplicar em campos com classe 'no-uppercase'
                if (el.classList.contains('no-uppercase')) return;
                
                var tag = el.tagName.toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select'){
                    if (el.type === 'radio' || el.type === 'checkbox' || el.readOnly) return;
                    // preserve caret position when possible
                    try{
                        var start = el.selectionStart;
                        var end = el.selectionEnd;
                    } catch(e){ var start = null; }
                    el.value = (el.value || '').toString().toUpperCase();
                    el.style.fontStyle = 'normal';
                    if (start !== null){ try{ el.setSelectionRange(start, start); } catch(e){} }
                }
            }

            // Apply to existing inputs/textareas initially
            var inputs = form.querySelectorAll('input[type="text"], input[type="search"], input[type="email"], input[type="tel"], textarea');
            inputs.forEach(function(i){ i.value = (i.value||'').toString().toUpperCase(); i.style.fontStyle='normal'; });

            // On input: convert to uppercase (exceto senhas e email)
            form.addEventListener('input', function(e){
                var t = e.target;
                if (!t) return;
                // Não aplicar uppercase em campos com classe 'no-uppercase'
                if (t.classList.contains('no-uppercase')) return;
                if (t.matches('input[type="text"], input[type="search"], input[type="email"], input[type="tel"], textarea')){
                    normalizeValue(t);
                }
            }, true);

            // On paste: ensure pasted text becomes uppercase
            form.addEventListener('paste', function(e){
                var t = e.target;
                if (!t) return;
                if (t.matches('input[type="text"], textarea')){
                    e.preventDefault();
                    var paste = (e.clipboardData || window.clipboardData).getData('text') || '';
                    paste = paste.toUpperCase();
                    try{
                        var start = t.selectionStart;
                        var end = t.selectionEnd;
                        var val = t.value;
                        t.value = val.slice(0,start) + paste + val.slice(end);
                        t.style.fontStyle = 'normal';
                        t.setSelectionRange(start + paste.length, start + paste.length);
                    }catch(err){ t.value = paste; }
                }
            }, true);

            // Also convert visible table/header text to uppercase (existing content)
            // EXCETO coluna de senha
            try{
                document.querySelectorAll('table td:not(.senha-campo), table th, .sem-resultados, .erro-mensagem, .topbar-left h2').forEach(function(el){
                    if (el && el.innerText) el.innerText = el.innerText.toUpperCase();
                });
            }catch(e){}
        })();
        </script>
        
        <script>
        // Detectar navegação com botão voltar após logout
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                // Página carregada do cache (botão voltar)
                fetch('check_session.php')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.logged_in) {
                            window.location.href = 'index.html';
                        }
                    })
                    .catch(() => {
                        window.location.href = 'index.html';
                    });
            }
        });
        
        // Desabilitar cache completamente
        window.onunload = function(){};
        
        // ========== NOVO SISTEMA: SINCRONIZAÇÃO FORMULÁRIO ↔ TABELA ==========
        let todosClientes = []; // Array com todos os clientes
        let indiceAtual = -1; // Índice do cliente atualmente carregado
        let salvandoAutomaticamente = false;
        let filhosMenores = []; // Array para armazenar filhos menores
        let incapazes = []; // Array para armazenar incapazes
        let aRogoRegistros = []; // Array para armazenar registros A ROGO
        
        // Ao carregar a página, coletar todos os clientes da tabela
        document.addEventListener('DOMContentLoaded', function() {
            coletarClientesDaTabela();
            configurarEventosFormulario();
            
            // Auto-calcular idade ao mudar data de nascimento
            document.getElementById('data_nascimento').addEventListener('change', calcularIdade);
        });
        
        // Coletar dados de todos os clientes da tabela
        function coletarClientesDaTabela() {
            todosClientes = [];
            const linhas = document.querySelectorAll('.cliente-row');
            console.log('Total de linhas encontradas:', linhas.length);
            linhas.forEach(function(linha, idx) {
                try {
                    const clienteJson = linha.getAttribute('data-cliente');
                    if (clienteJson) {
                        const cliente = JSON.parse(clienteJson);
                        todosClientes.push(cliente);
                    }
                } catch(e) {
                    console.error('Erro ao parsear dados do cliente:', e);
                }
            });
            console.log('Total de clientes carregados:', todosClientes.length);
        }
        
        // Função chamada quando clica em uma linha da tabela
        function carregarClienteNoFormulario(linhaElement) {
            try {
                // Remover highlight de todas as linhas
                document.querySelectorAll('.cliente-row').forEach(function(tr) {
                    tr.style.backgroundColor = '';
                });
                
                // Highlight na linha selecionada
                linhaElement.style.backgroundColor = '#e3f2fd';
                
                // Obter dados do cliente
                const clienteJson = linhaElement.getAttribute('data-cliente');
                const cliente = JSON.parse(clienteJson);
                
                // Encontrar índice no array
                indiceAtual = todosClientes.findIndex(c => c.id === cliente.id);
                
                // Preencher formulário
                preencherFormulario(cliente);
                
                // Marcar radio button
                const radio = linhaElement.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                // Abrir automaticamente o cadastro do cliente
                switchTab(null, 'cadastro_pessoas');
                
            } catch(e) {
                console.error('Erro ao carregar cliente:', e);
                alert('Erro ao carregar dados do cliente.');
            }
        }

        function parseEnderecoComLink(valor) {
            const separator = ' | ';
            if (!valor) {
                return { endereco: '', link: '' };
            }
            const partes = valor.split(separator);
            if (partes.length >= 2) {
                const endereco = (partes.shift() || '').trim();
                const link = partes.join(separator).trim();
                return { endereco, link };
            }
            return { endereco: valor, link: '' };
        }

        function buildEnderecoComLink(endereco, link) {
            const enderecoTrim = (endereco || '').trim();
            const linkTrim = (link || '').trim();
            if (linkTrim) {
                return enderecoTrim ? `${enderecoTrim} | ${linkTrim}` : linkTrim;
            }
            return enderecoTrim;
        }

        function updateLinkColor(id) {
            const input = document.getElementById(id);
            if (!input) return;
            input.style.color = input.value && input.value.trim() ? '#003366' : '#333333';
        }

        function normalizarBeneficio(valor) {
            if (!valor) return '';

            let v = String(valor).trim().replaceAll('_', ' ').replace(/\s+/g, ' ');
            v = v
                .replaceAll('Ã¡', 'á').replaceAll('Ã ', 'à').replaceAll('Ã¢', 'â').replaceAll('Ã£', 'ã').replaceAll('Ã¤', 'ä')
                .replaceAll('Ã', 'Á').replaceAll('Ã€', 'À').replaceAll('Ã‚', 'Â').replaceAll('Ãƒ', 'Ã').replaceAll('Ã„', 'Ä')
                .replaceAll('Ã©', 'é').replaceAll('Ãª', 'ê').replaceAll('Ã‰', 'É').replaceAll('ÃŠ', 'Ê')
                .replaceAll('Ã­', 'í').replaceAll('Ã', 'Í')
                .replaceAll('Ã³', 'ó').replaceAll('Ã´', 'ô').replaceAll('Ãµ', 'õ').replaceAll('Ã“', 'Ó').replaceAll('Ã”', 'Ô').replaceAll('Ã•', 'Õ')
                .replaceAll('Ãº', 'ú').replaceAll('Ãš', 'Ú')
                .replaceAll('Ã§', 'ç').replaceAll('Ã‡', 'Ç');

            const chave = v
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toUpperCase();

            const mapa = {
                'APOSENTADORIA AGRICULTOR': 'Aposentadoria do Agricultor',
                'APOSENTADORIA DO AGRICULTOR': 'Aposentadoria do Agricultor',
                'APOSENTADORIA PESCADOR': 'APOSENTADORIA PESCADOR',
                'APOSENTADORIA DO PESCADOR': 'APOSENTADORIA PESCADOR',
                'APOSENTADORIA INDIGENA': 'APOSENTADORIA INDÍGENA',
                'APOSENTADORIA POR TEMPO DE CONTRIBUICAO URBANO': 'Aposentadoria por tempo de contribuição Urbana',
                'APOSENTADORIA POR TEMPO DE CONTRIBUICAO URBANA': 'Aposentadoria por tempo de contribuição Urbana',
                'APOSENTADORIA ESPECIAL URBANO': 'Aposentadoria Especial Urbana',
                'APOSENTADORIA ESPECIAL URBANA': 'Aposentadoria Especial Urbana',
                'APOSENTADORIA POR INVALIDEZ URBANO': 'Aposentadoria por Invalidez Urbana',
                'APOSENTADORIA POR INVALIDEZ URBANA': 'Aposentadoria por Invalidez Urbana',
                'APOSENTADORIA HIBRIDA': 'Aposentadoria Híbrida',
                'PENSAO POR MORTE': 'Pensão por morte',
                'BPC POR IDADE': 'BPC por idade',
                'BPC POR DOENCA': 'BPC por doença',
                'BPC DOENCA': 'BPC por doença',
                'AUXILIO DOENCA': 'Auxílio-Doença',
                'AUXILIO ACIDENTE': 'Auxílio-Acidente',
                'AUXILIO RECLUSAO': 'Auxílio-Reclusão',
                'SALARIO MATERNIDADE URBANO': 'Salário-Maternidade Urbano',
                'SALARIO MATERNIDADE AGRICULTORA': 'Salário-Maternidade Agricultora',
                'SALARIO MATERNIDADE PESCADORA': 'Salário-Maternidade Pescadora',
                'SALARIO MATERNIDADE INDIGENA': 'SALÁRIO-MATERNIDADE INDÍGENA',
                'DIVORCIO': 'Divórcio',
                'ACAO TRABALHISTA': 'AÇÃO TRABALHISTA'
            };

            return mapa[chave] || v;
        }
        
        // Preencher todos os campos do formulário
        function preencherFormulario(cliente) {
            document.getElementById('cliente_id_hidden').value = cliente.id || '';
            document.getElementById('codigo').value = cliente.id || '';
            document.getElementById('nome').value = cliente.nome || '';
            document.getElementById('cpf').value = cliente.cpf || '';
            document.getElementById('data_nascimento').value = cliente.data_nascimento || '';
            document.getElementById('rg').value = cliente.rg || '';
            document.getElementById('estado_civil').value = cliente.estado_civil || '';
            document.getElementById('nacionalidade').value = cliente.nacionalidade || '';
            document.getElementById('profissao').value = cliente.profissao || '';
            document.getElementById('senha_meuinss').value = cliente.senha_meuinss || '';
            document.getElementById('senha_email').value = cliente.senha_email || '';
            document.getElementById('beneficio').value = normalizarBeneficio(cliente.beneficio || '');
            document.getElementById('situacao').value = cliente.situacao || '';
            document.getElementById('indicador').value = cliente.indicador || '';
            document.getElementById('responsavel').value = cliente.responsavel || '';
            document.getElementById('advogado').value = cliente.advogado || '';
            document.getElementById('endereco').value = cliente.endereco || '';
            document.getElementById('cidade').value = cliente.cidade || '';
            document.getElementById('estado').value = cliente.uf || 'AM';
            document.getElementById('telefone').value = cliente.telefone || '';
            document.getElementById('telefone2').value = cliente.telefone2 || '';
            document.getElementById('telefone3').value = cliente.telefone3 || '';
            document.getElementById('email').value = cliente.email || '';
            document.getElementById('data_contrato').value = cliente.data_contrato || '';
            document.getElementById('data_enviado').value = cliente.data_enviado || '';
            document.getElementById('numero_processo').value = cliente.numero_processo || '';
            document.getElementById('data_avaliacao_social').value = cliente.data_avaliacao_social || '';
            document.getElementById('hora_avaliacao_social').value = cliente.hora_avaliacao_social || '';
            const av = parseEnderecoComLink(cliente.endereco_avaliacao_social || '');
            document.getElementById('endereco_avaliacao_social').value = av.endereco;
            document.getElementById('endereco_avaliacao_social_link').value = av.link;
            updateLinkColor('endereco_avaliacao_social_link');
            document.getElementById('avaliacao_social_realizado').checked = cliente.realizado_a_s == 1;
            document.getElementById('data_pericia').value = cliente.data_pericia || '';
            document.getElementById('hora_pericia').value = cliente.hora_pericia || '';
            const per = parseEnderecoComLink(cliente.endereco_pericia || '');
            document.getElementById('endereco_pericia').value = per.endereco;
            document.getElementById('endereco_pericia_link').value = per.link;
            updateLinkColor('endereco_pericia_link');
            document.getElementById('pericia_realizado').checked = cliente.realizado_pericia == 1;
            document.getElementById('contrato_assinado').checked = cliente.contrato_assinado == 1;
            document.getElementById('observacao').value = cliente.observacao || '';
            
            calcularIdade();
            
            // Carregar filhos menores do cliente
            carregarFilhosMenores(cliente.id);
            carregarIncapazes(cliente.id);
            carregarARogo(cliente.id);
        }
        
        // Calcular idade automaticamente
        function calcularIdade() {
            const dataNasc = document.getElementById('data_nascimento').value;
            if (dataNasc) {
                const nascimento = new Date(dataNasc);
                const hoje = new Date();
                let idade = hoje.getFullYear() - nascimento.getFullYear();
                const mes = hoje.getMonth() - nascimento.getMonth();
                if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                    idade--;
                }
                document.getElementById('idade').value = idade + ' anos';
            } else {
                document.getElementById('idade').value = '';
            }
        }
        
        // Função para limpar formulário (novo registro)
        function limparFormulario() {
            document.getElementById('formPrincipal').reset();
            document.getElementById('cliente_id_hidden').value = '';
            document.getElementById('codigo').value = '';
            document.getElementById('idade').value = '';
            indiceAtual = -1;
            
            // Limpar lista de filhos menores e esconder indicador
            filhosMenores = [];
            atualizarTabelaFilhos();
            document.getElementById('indicadorClienteFilhos').style.display = 'none';

            // Limpar lista de incapazes e esconder indicador
            incapazes = [];
            atualizarTabelaIncapazes();
            const indicadorIncapaz = document.getElementById('indicadorClienteIncapaz');
            if (indicadorIncapaz) {
                indicadorIncapaz.style.display = 'none';
            }
            limparCamposIncapaz();

            // Limpar lista de A ROGO e esconder indicador
            aRogoRegistros = [];
            atualizarTabelaARogo();
            const indicadorARogo = document.getElementById('indicadorClienteARogo');
            if (indicadorARogo) {
                indicadorARogo.style.display = 'none';
            }
            limparCamposARogo();
            
            // Remover highlight de todas as linhas
            document.querySelectorAll('.cliente-row').forEach(function(tr) {
                tr.style.backgroundColor = '';
            });
        }
        
        // ========== FUNÇÕES DA BARRA DE FERRAMENTAS ==========
        
        function navegarPrimeiro() {
            if (todosClientes.length === 0) {
                alert('Nenhum cliente cadastrado.');
                return;
            }
            indiceAtual = 0;
            preencherFormulario(todosClientes[0]);
            highlightLinhaNaTabela(todosClientes[0].id);
        }
        
        function navegarAnterior() {
            if (todosClientes.length === 0) {
                alert('Nenhum cliente cadastrado.');
                return;
            }
            if (indiceAtual > 0) {
                indiceAtual--;
                preencherFormulario(todosClientes[indiceAtual]);
                highlightLinhaNaTabela(todosClientes[indiceAtual].id);
            } else {
                alert('Você já está no primeiro registro.');
            }
        }
        
        function navegarProximo() {
            if (todosClientes.length === 0) {
                alert('Nenhum cliente cadastrado.');
                return;
            }
            if (indiceAtual < todosClientes.length - 1) {
                indiceAtual++;
                preencherFormulario(todosClientes[indiceAtual]);
                highlightLinhaNaTabela(todosClientes[indiceAtual].id);
            } else {
                alert('Você já está no último registro.');
            }
        }
        
        function navegarUltimo() {
            if (todosClientes.length === 0) {
                alert('Nenhum cliente cadastrado.');
                return;
            }
            indiceAtual = todosClientes.length - 1;
            preencherFormulario(todosClientes[indiceAtual]);
            highlightLinhaNaTabela(todosClientes[indiceAtual].id);
        }
        
        function novoRegistro() {
            if (confirm('Deseja criar um novo registro? Os dados não salvos serão perdidos.')) {
                limparFormulario();
                document.getElementById('nome').focus();
            }
        }
        
        function excluirRegistro() {
            const clienteId = document.getElementById('cliente_id_hidden').value;
            const clienteNome = document.getElementById('nome').value;
            
            if (!clienteId) {
                alert('Selecione um cliente primeiro.');
                return;
            }
            
            if (!confirm('Tem certeza que deseja excluir o cliente "' + clienteNome + '"?\n\nEsta ação não pode ser desfeita!')) {
                return;
            }
            
            // Enviar via AJAX
            const formData = new FormData();
            formData.append('cliente_id', clienteId);
            
            fetch('excluir_cliente.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Cliente excluído com sucesso!');
                location.reload();
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir cliente. Você tem permissão para excluir?');
            });
        }
        
        function editarRegistro() {
            const clienteId = document.getElementById('cliente_id_hidden').value;
            
            if (!clienteId) {
                alert('Por favor, selecione um cliente da tabela para editar.');
                return;
            }
            
            // Focar no campo nome
            document.getElementById('nome').focus();
            document.getElementById('nome').select();
            
            alert('Modo de edição ativado. Modifique os campos desejados e clique em SALVAR.');
        }
        
        function salvarRegistro() {
            try {
                console.log('=== FUNÇÃO SALVAR CHAMADA ===');
                
                // Função auxiliar para obter valor de elemento de forma segura
                function getValue(id, defaultValue = '') {
                    const elem = document.getElementById(id);
                    return elem ? (elem.value || defaultValue) : defaultValue;
                }
                
                // Função auxiliar para verificar checkbox
                function isChecked(id) {
                    const elem = document.getElementById(id);
                    return elem ? elem.checked : false;
                }
                
                // Coletar TODOS os campos manualmente
                const formData = new FormData();
            
            // Adicionar TODOS os campos do formulário
            formData.append('cliente_id', getValue('cliente_id_hidden', '0'));
            formData.append('nome', getValue('nome', ''));
            formData.append('cpf', getValue('cpf', ''));
            formData.append('data_nascimento', getValue('data_nascimento', ''));
            formData.append('rg', getValue('rg', ''));
            formData.append('estado_civil', getValue('estado_civil', ''));
            formData.append('nacionalidade', getValue('nacionalidade', ''));
            formData.append('profissao', getValue('profissao', ''));
            formData.append('senha_meuinss', getValue('senha_meuinss', ''));
            formData.append('senha_email', getValue('senha_email', ''));
            formData.append('beneficio', getValue('beneficio', ''));
            formData.append('situacao', getValue('situacao', ''));
            formData.append('indicador', getValue('indicador', ''));
            formData.append('responsavel', getValue('responsavel', ''));
            formData.append('advogado', getValue('advogado', ''));
            formData.append('endereco', getValue('endereco', ''));
            formData.append('cidade', getValue('cidade', ''));
            formData.append('estado', getValue('estado', 'AM'));
            formData.append('cep', getValue('cep', ''));
            formData.append('telefone', getValue('telefone', ''));
            formData.append('telefone2', getValue('telefone2', ''));
            formData.append('telefone3', getValue('telefone3', ''));
            formData.append('email', getValue('email', ''));
            formData.append('data_contrato', getValue('data_contrato', ''));
            formData.append('data_enviado', getValue('data_enviado', ''));
            formData.append('numero_processo', getValue('numero_processo', ''));
            formData.append('data_avaliacao_social', getValue('data_avaliacao_social', ''));
            formData.append('hora_avaliacao_social', getValue('hora_avaliacao_social', ''));
            formData.append(
                'endereco_avaliacao_social',
                buildEnderecoComLink(
                    getValue('endereco_avaliacao_social', ''),
                    getValue('endereco_avaliacao_social_link', '')
                )
            );
            formData.append('data_pericia', getValue('data_pericia', ''));
            formData.append('hora_pericia', getValue('hora_pericia', ''));
            formData.append(
                'endereco_pericia',
                buildEnderecoComLink(
                    getValue('endereco_pericia', ''),
                    getValue('endereco_pericia_link', '')
                )
            );
            formData.append('observacao', getValue('observacao', ''));
            
            // Checkboxes
            if (isChecked('contrato_assinado')) {
                formData.append('contrato_assinado', '1');
            }
            if (isChecked('avaliacao_social_realizado')) {
                formData.append('avaliacao_social_realizado', '1');
            }
            if (isChecked('pericia_realizado')) {
                formData.append('pericia_realizado', '1');
            }
            
            // Validar nome
            const nomeValue = formData.get('nome');
            if (!nomeValue || nomeValue.trim() === '') {
                alert('O campo NOME é obrigatório!');
                const nomeElem = document.getElementById('nome');
                if (nomeElem) nomeElem.focus();
                return;
            }
            
            // Log dos dados
            console.log('Enviando dados:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }
            
            // Mostrar status
            const status = document.getElementById('statusSalvamento');
            if (status) {
                status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                status.style.color = '#ffc107';
            }
            
            fetch('salvar_cliente_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                // Se não for sucesso HTTP, tentar ler o texto mesmo assim
                if (!response.ok && response.status !== 200) {
                    console.warn('Resposta HTTP não OK:', response.status);
                }
                
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                
                // Verificar se a resposta está vazia
                if (!text || text.trim() === '') {
                    throw new Error('Resposta vazia do servidor');
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                    console.error('Resposta recebida:', text);
                    
                    // Tentar extrair mensagem de erro se houver
                    let errorMsg = 'ERRO DE RESPOSTA DO SERVIDOR';
                    if (text.includes('ERRO') || text.includes('Erro')) {
                        errorMsg += ':\n\n' + text.substring(0, 500);
                    } else {
                        errorMsg += ':\n\nResposta não é JSON válido.\n' + text.substring(0, 500);
                    }
                    
                    alert(errorMsg);
                    status.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro!';
                    status.style.color = '#dc3545';
                    return;
                }
                
                if (data.success) {
                    if (status) {
                        status.innerHTML = '<i class="fas fa-check-circle"></i> Salvo!';
                        status.style.color = '#28a745';
                    }
                    
                    alert('✓ ' + data.message);
                    
                    // Atualizar ID se for novo registro
                    if (data.cliente_id) {
                        const clienteIdHidden = document.getElementById('cliente_id_hidden');
                        const codigoElem = document.getElementById('codigo');
                        if (clienteIdHidden && !clienteIdHidden.value) {
                            clienteIdHidden.value = data.cliente_id;
                        }
                        if (codigoElem) {
                            codigoElem.value = data.cliente_id;
                        }
                    }
                    
                    // Recarregar página após 1 segundo para atualizar tabela
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    if (status) {
                        status.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro!';
                        status.style.color = '#dc3545';
                    }
                    console.error('Erro do servidor:', data.message);
                    alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
                }
                
                // Limpar status após 3 segundos
                if (status) {
                    setTimeout(() => {
                        status.innerHTML = '';
                    }, 3000);
                }
            })
            .catch(error => {
                if (status) {
                    status.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro!';
                    status.style.color = '#dc3545';
                }
                console.error('Erro de rede:', error);
                console.error('Stack trace:', error.stack);
                alert('Erro ao salvar cliente: ' + (error.message || 'Erro desconhecido') + '\n\nVerifique o console do navegador (F12) para mais detalhes.');
            });
            } catch (e) {
                console.error('Erro na função salvarRegistro:', e);
                alert('Erro ao executar função de salvar: ' + e.message);
            }
        }
        
        function imprimirRegistro() {
            const clienteId = document.getElementById('cliente_id_hidden').value;
            if (!clienteId) {
                alert('Selecione um cliente primeiro.');
                return;
            }
            
            // Abrir menu de documentos (removido - botões agora estão na toolbar)
            // document.querySelector('.tabs-header button:first-child').click();
        }
        
        function buscarRegistro() {
            const termo = prompt('Digite o nome ou CPF do cliente:');
            if (!termo) return;
            
            const encontrado = todosClientes.find(c => 
                c.nome.toUpperCase().includes(termo.toUpperCase()) || 
                c.cpf.includes(termo.replace(/\D/g, ''))
            );
            
            if (encontrado) {
                indiceAtual = todosClientes.indexOf(encontrado);
                preencherFormulario(encontrado);
                highlightLinhaNaTabela(encontrado.id);
            } else {
                alert('Cliente não encontrado.');
            }
        }
        
        // Highlight na linha da tabela
        function highlightLinhaNaTabela(clienteId) {
            document.querySelectorAll('.cliente-row').forEach(function(tr) {
                const data = tr.getAttribute('data-cliente');
                if (data) {
                    const cliente = JSON.parse(data);
                    if (cliente.id == clienteId) {
                        tr.style.backgroundColor = '#e3f2fd';
                        tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Marcar radio
                        const radio = tr.querySelector('input[type="radio"]');
                        if (radio) radio.checked = true;
                    } else {
                        tr.style.backgroundColor = '';
                    }
                }
            });
        }
        
        // Função para gerar documentos
        function gerarDocumento(url) {
            const clienteId = document.getElementById('cliente_id_hidden').value;
            if (!clienteId) {
                alert('Selecione um cliente primeiro.');
                return;
            }
            window.open(url + '?id=' + clienteId, '_blank');
        }
        
        // Função para abrir financeiro
        function abrirFinanceiro() {
            const clienteId = document.getElementById('cliente_id_hidden').value;
            if (!clienteId) {
                alert('Selecione um cliente primeiro.');
                return;
            }
            window.location.href = 'financeiro.php?id=' + clienteId;
        }

        function abrirProcessosCliente() {
            const clienteId = document.getElementById('cliente_id_hidden').value;
            if (!clienteId) {
                alert('Selecione um cliente primeiro.');
                return;
            }
            window.location.href = 'processos.php?cliente_id=' + encodeURIComponent(clienteId);
        }
        
        // Configurar eventos do formulário (auto-save opcional)
        function configurarEventosFormulario() {
            // Adicionar listeners para teclas de atalho
            document.addEventListener('keydown', function(e) {
                // Ctrl + S = Salvar
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    salvarRegistro();
                }
                // Ctrl + N = Novo
                if (e.ctrlKey && e.key === 'n') {
                    e.preventDefault();
                    novoRegistro();
                }
                // Ctrl + Seta Direita = Próximo
                if (e.ctrlKey && e.key === 'ArrowRight') {
                    e.preventDefault();
                    navegarProximo();
                }
                // Ctrl + Seta Esquerda = Anterior
                if (e.ctrlKey && e.key === 'ArrowLeft') {
                    e.preventDefault();
                    navegarAnterior();
                }
            });
        }
        
        // ========== FUNÇÕES PARA CADASTRO DE FILHOS MENORES ==========
        
        // Calcular idade do filho
        function calcularIdadeFilho() {
            const dataNasc = document.getElementById('filho_data_nascimento').value;
            if (dataNasc) {
                const nascimento = new Date(dataNasc);
                const hoje = new Date();
                let idade = hoje.getFullYear() - nascimento.getFullYear();
                const mes = hoje.getMonth() - nascimento.getMonth();
                if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                    idade--;
                }
                document.getElementById('filho_idade').value = idade + ' anos';
            } else {
                document.getElementById('filho_idade').value = '';
            }
        }
        
        // Formatar CPF do filho (000.000.000-00)
        function formatarCPFFilho(input) {
            let valor = input.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            
            if (valor.length <= 11) {
                valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            
            input.value = valor;
        }
        
        // Formatar Telefone (00)0 0000-0000
        function formatarTelefone(input) {
            let valor = input.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            
            if (valor.length <= 11) {
                // Formato: (00)0 0000-0000
                valor = valor.replace(/^(\d{2})(\d)/, '($1)$2');
                valor = valor.replace(/(\d{1})(\d{4})(\d)/, '$1 $2-$3');
            }
            
            input.value = valor;
        }
        
        // Formatar CPF do cliente (000.000.000-00)
        function formatarCPF(input) {
            let valor = input.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            
            if (valor.length <= 11) {
                valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            
            input.value = valor;
        }
        
        // Adicionar ou atualizar filho menor
        function adicionarFilhoMenor() {
            const nome = document.getElementById('filho_nome').value.trim();
            const dataNascimento = document.getElementById('filho_data_nascimento').value;
            const idade = document.getElementById('filho_idade').value;
            const cpf = document.getElementById('filho_cpf').value.trim();
            const senhaGov = document.getElementById('filho_senha_gov').value.trim();
            const filhoIdEdicao = document.getElementById('filho_id_edicao').value;
            
            if (!nome) {
                alert('Por favor, informe o nome do filho(a)');
                document.getElementById('filho_nome').focus();
                return;
            }
            
            if (!dataNascimento) {
                alert('Por favor, informe a data de nascimento');
                document.getElementById('filho_data_nascimento').focus();
                return;
            }
            
            const clienteId = document.getElementById('cliente_id_hidden').value;
            if (!clienteId) {
                alert('Por favor, selecione ou salve um cliente primeiro');
                return;
            }
            
            // Salvar no banco de dados via AJAX
            const formData = new FormData();
            formData.append('acao', filhoIdEdicao ? 'atualizar' : 'adicionar');
            formData.append('cliente_id', clienteId);
            formData.append('nome', nome);
            formData.append('data_nascimento', dataNascimento);
            formData.append('cpf', cpf);
            formData.append('senha_gov', senhaGov);
            if (filhoIdEdicao) {
                formData.append('filho_id', filhoIdEdicao);
            }
            
            fetch('salvar_filho_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (filhoIdEdicao) {
                        // Atualizar no array local
                        const index = filhosMenores.findIndex(f => f.id == filhoIdEdicao);
                        if (index !== -1) {
                            filhosMenores[index] = {
                                id: filhoIdEdicao,
                                nome: nome,
                                data_nascimento: dataNascimento,
                                cpf: cpf,
                                senha_gov: senhaGov
                            };
                        }
                        alert('Filho atualizado com sucesso!');
                    } else {
                        // Adicionar ao array local
                        const filho = {
                            id: data.id,
                            nome: nome,
                            data_nascimento: dataNascimento,
                            cpf: cpf,
                            senha_gov: senhaGov
                        };
                        filhosMenores.push(filho);
                        alert('Filho cadastrado com sucesso!');
                    }
                    
                    // Atualizar tabela
                    atualizarTabelaFilhos();
                    
                    // Limpar campos e resetar modo
                    limparCamposFilho();
                    
                } else {
                    alert('Erro ao salvar: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao cadastrar filho');
            });
        }
        
        // Atualizar tabela de filhos
        function atualizarTabelaFilhos() {
            const tbody = document.getElementById('listaFilhosMenores');
            
            if (filhosMenores.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="padding: 20px; text-align: center; color: #999; font-size: 9pt;">
                            Nenhum filho cadastrado
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            filhosMenores.forEach((filho, index) => {
                const dataBr = filho.data_nascimento ? new Date(filho.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR') : '';
                
                // Calcular idade
                let idade = '';
                if (filho.data_nascimento) {
                    const nascimento = new Date(filho.data_nascimento);
                    const hoje = new Date();
                    let anos = hoje.getFullYear() - nascimento.getFullYear();
                    const mes = hoje.getMonth() - nascimento.getMonth();
                    if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                        anos--;
                    }
                    idade = anos + ' anos';
                }
                
                html += `
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${filho.nome}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${dataBr}</td>
                        <td style="padding: 8px; font-size: 9pt; text-align: center; border: 1px solid #ddd;">${idade}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${filho.cpf || '-'}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${filho.senha_gov || '-'}</td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <button type="button" onclick="editarFilhoMenor(${filho.id})" 
                                style="padding: 4px 10px; background: #ffc107; color: #000; border: none; border-radius: 3px; cursor: pointer; font-size: 8pt; width: 80px;">
                                ✏️ Editar
                            </button>
                        </td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <button type="button" onclick="removerFilhoMenor(${filho.id})" 
                                style="padding: 4px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 8pt; width: 80px;">
                                🗑️ Excluir
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // Editar filho menor
        function editarFilhoMenor(filhoId) {
            const filho = filhosMenores.find(f => f.id == filhoId);
            if (!filho) return;
            
            // Preencher campos do formulário
            document.getElementById('filho_id_edicao').value = filho.id;
            document.getElementById('filho_nome').value = filho.nome;
            document.getElementById('filho_data_nascimento').value = filho.data_nascimento;
            document.getElementById('filho_cpf').value = filho.cpf || '';
            calcularIdadeFilho();
            
            // Mudar botão para modo de edição
            const btnSalvar = document.getElementById('btnSalvarFilho');
            btnSalvar.innerHTML = '✏️ ATUALIZAR';
            btnSalvar.style.background = '#007bff';
            
            // Mostrar botão cancelar
            document.getElementById('btnCancelarEdicao').style.display = 'inline-block';
            
            // Focar no campo nome
            document.getElementById('filho_nome').focus();
        }
        
        // Cancelar edição
        function cancelarEdicaoFilho() {
            limparCamposFilho();
        }
        
        // Limpar campos do formulário de filho
        function limparCamposFilho() {
            document.getElementById('filho_id_edicao').value = '';
            document.getElementById('filho_nome').value = '';
            document.getElementById('filho_data_nascimento').value = '';
            document.getElementById('filho_idade').value = '';
            document.getElementById('filho_cpf').value = '';
            document.getElementById('filho_senha_gov').value = '';
            
            // Restaurar botão para modo de adição
            const btnSalvar = document.getElementById('btnSalvarFilho');
            btnSalvar.innerHTML = '➥ ADICIONAR';
            btnSalvar.style.background = '#28a745';
            
            // Esconder botão cancelar
            document.getElementById('btnCancelarEdicao').style.display = 'none';
            
            document.getElementById('filho_nome').focus();
        }
        
        // Fechar aba de filhos
        function fecharAbaFilhos() {
            const abaAgendamento = document.getElementById('agendamento');
            if (abaAgendamento) {
                abaAgendamento.style.display = 'none';
            }
        }
        
        // Remover filho menor
        function removerFilhoMenor(filhoId) {
            if (!confirm('Deseja realmente excluir este registro?')) {
                return;
            }
            
            const clienteId = document.getElementById('cliente_id_hidden').value;
            
            const formData = new FormData();
            formData.append('acao', 'excluir');
            formData.append('cliente_id', clienteId);
            formData.append('filho_id', filhoId);
            
            fetch('salvar_filho_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remover do array local
                    filhosMenores = filhosMenores.filter(f => f.id != filhoId);
                    atualizarTabelaFilhos();
                    alert('Filho removido com sucesso!');
                } else {
                    alert('Erro ao remover: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao remover filho');
            });
        }
        
        // Carregar filhos menores do cliente
        function carregarFilhosMenores(clienteId) {
            const indicador = document.getElementById('indicadorClienteFilhos');
            const nomeCliente = document.getElementById('nome').value;
            const idSpan = document.getElementById('idClienteFilhos');
            const nomeSpan = document.getElementById('nomeClienteFilhos');
            
            if (!clienteId) {
                filhosMenores = [];
                atualizarTabelaFilhos();
                indicador.style.display = 'none';
                return;
            }
            
            // Atualizar indicador visual
            indicador.style.display = 'block';
            idSpan.textContent = clienteId;
            nomeSpan.textContent = nomeCliente || 'Carregando...';
            
            const formData = new FormData();
            formData.append('acao', 'listar');
            formData.append('cliente_id', clienteId);
            
            fetch('salvar_filho_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    filhosMenores = data.filhos;
                    atualizarTabelaFilhos();
                } else {
                    console.error('Erro ao carregar filhos:', data.message);
                    filhosMenores = [];
                    atualizarTabelaFilhos();
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                filhosMenores = [];
                atualizarTabelaFilhos();
            });
        }

        // ========== FUNÇÕES PARA CADASTRO DE INCAPAZ ==========

        function calcularIdadeIncapaz() {
            const dataNasc = document.getElementById('incapaz_data_nascimento').value;
            if (dataNasc) {
                const nascimento = new Date(dataNasc);
                const hoje = new Date();
                let idade = hoje.getFullYear() - nascimento.getFullYear();
                const mes = hoje.getMonth() - nascimento.getMonth();
                if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                    idade--;
                }
                document.getElementById('incapaz_idade').value = idade + ' anos';
            } else {
                document.getElementById('incapaz_idade').value = '';
            }
        }

        function formatarCPFIncapaz(input) {
            let valor = input.value.replace(/\D/g, '');
            if (valor.length <= 11) {
                valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            input.value = valor;
        }

        function adicionarIncapaz() {
            const nome = document.getElementById('incapaz_nome').value.trim();
            const dataNascimento = document.getElementById('incapaz_data_nascimento').value;
            const cpf = document.getElementById('incapaz_cpf').value.trim();
            const senhaGov = document.getElementById('incapaz_senha_gov').value.trim();
            const incapazIdEdicao = document.getElementById('incapaz_id_edicao').value;

            if (!nome) {
                alert('Por favor, informe o nome do incapaz');
                document.getElementById('incapaz_nome').focus();
                return;
            }

            if (!dataNascimento) {
                alert('Por favor, informe a data de nascimento');
                document.getElementById('incapaz_data_nascimento').focus();
                return;
            }

            const clienteId = document.getElementById('cliente_id_hidden').value;
            if (!clienteId) {
                alert('Por favor, selecione ou salve um cliente primeiro');
                return;
            }

            const formData = new FormData();
            formData.append('acao', incapazIdEdicao ? 'atualizar' : 'adicionar');
            formData.append('cliente_id', clienteId);
            formData.append('nome', nome);
            formData.append('data_nascimento', dataNascimento);
            formData.append('cpf', cpf);
            formData.append('senha_gov', senhaGov);
            if (incapazIdEdicao) {
                formData.append('incapaz_id', incapazIdEdicao);
            }

            fetch('salvar_incapaz_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (incapazIdEdicao) {
                        const index = incapazes.findIndex(i => i.id == incapazIdEdicao);
                        if (index !== -1) {
                            incapazes[index] = {
                                id: incapazIdEdicao,
                                nome: nome,
                                data_nascimento: dataNascimento,
                                cpf: cpf,
                                senha_gov: senhaGov
                            };
                        }
                        alert('Incapaz atualizado com sucesso!');
                    } else {
                        incapazes.push({
                            id: data.id,
                            nome: nome,
                            data_nascimento: dataNascimento,
                            cpf: cpf,
                            senha_gov: senhaGov
                        });
                        alert('Incapaz cadastrado com sucesso!');
                    }

                    atualizarTabelaIncapazes();
                    limparCamposIncapaz();
                } else {
                    alert('Erro ao salvar: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao cadastrar incapaz');
            });
        }

        function atualizarTabelaIncapazes() {
            const tbody = document.getElementById('listaIncapazes');
            if (!tbody) {
                return;
            }

            if (incapazes.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="padding: 20px; text-align: center; color: #999; font-size: 9pt;">
                            Nenhum incapaz cadastrado
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            incapazes.forEach((incapaz) => {
                const dataBr = incapaz.data_nascimento ? new Date(incapaz.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR') : '';
                let idade = '';
                if (incapaz.data_nascimento) {
                    const nascimento = new Date(incapaz.data_nascimento);
                    const hoje = new Date();
                    let anos = hoje.getFullYear() - nascimento.getFullYear();
                    const mes = hoje.getMonth() - nascimento.getMonth();
                    if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                        anos--;
                    }
                    idade = anos + ' anos';
                }

                html += `
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${incapaz.nome}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${dataBr}</td>
                        <td style="padding: 8px; font-size: 9pt; text-align: center; border: 1px solid #ddd;">${idade}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${incapaz.cpf || '-'}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${incapaz.senha_gov || '-'}</td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <button type="button" onclick="editarIncapaz(${incapaz.id})"
                                style="padding: 4px 10px; background: #ffc107; color: #000; border: none; border-radius: 3px; cursor: pointer; font-size: 8pt; width: 80px;">
                                ✏️ Editar
                            </button>
                        </td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <button type="button" onclick="removerIncapaz(${incapaz.id})"
                                style="padding: 4px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 8pt; width: 80px;">
                                🗑️ Excluir
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function editarIncapaz(incapazId) {
            const registro = incapazes.find(i => i.id == incapazId);
            if (!registro) return;

            document.getElementById('incapaz_id_edicao').value = registro.id;
            document.getElementById('incapaz_nome').value = registro.nome || '';
            document.getElementById('incapaz_data_nascimento').value = registro.data_nascimento || '';
            document.getElementById('incapaz_cpf').value = registro.cpf || '';
            document.getElementById('incapaz_senha_gov').value = registro.senha_gov || '';
            calcularIdadeIncapaz();

            const btnSalvar = document.getElementById('btnSalvarIncapaz');
            btnSalvar.innerHTML = '✏️ ATUALIZAR';
            btnSalvar.style.background = '#007bff';

            document.getElementById('btnCancelarEdicaoIncapaz').style.display = 'inline-block';
            document.getElementById('incapaz_nome').focus();
        }

        function cancelarEdicaoIncapaz() {
            limparCamposIncapaz();
        }

        function limparCamposIncapaz() {
            const campoId = document.getElementById('incapaz_id_edicao');
            const campoNome = document.getElementById('incapaz_nome');
            const campoData = document.getElementById('incapaz_data_nascimento');
            const campoIdade = document.getElementById('incapaz_idade');
            const campoCpf = document.getElementById('incapaz_cpf');
            const campoSenha = document.getElementById('incapaz_senha_gov');
            const btnSalvar = document.getElementById('btnSalvarIncapaz');
            const btnCancelar = document.getElementById('btnCancelarEdicaoIncapaz');

            if (!campoId || !campoNome || !campoData || !campoIdade || !campoCpf || !campoSenha || !btnSalvar || !btnCancelar) {
                return;
            }

            campoId.value = '';
            campoNome.value = '';
            campoData.value = '';
            campoIdade.value = '';
            campoCpf.value = '';
            campoSenha.value = '';

            btnSalvar.innerHTML = '➕ ADICIONAR';
            btnSalvar.style.background = '#28a745';
            btnCancelar.style.display = 'none';
        }

        function fecharAbaIncapaz() {
            const abaIncapaz = document.getElementById('incapaz');
            if (abaIncapaz) {
                abaIncapaz.style.display = 'none';
            }
        }

        function removerIncapaz(incapazId) {
            if (!confirm('Deseja realmente excluir este registro?')) {
                return;
            }

            const clienteId = document.getElementById('cliente_id_hidden').value;
            const formData = new FormData();
            formData.append('acao', 'excluir');
            formData.append('cliente_id', clienteId);
            formData.append('incapaz_id', incapazId);

            fetch('salvar_incapaz_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    incapazes = incapazes.filter(i => i.id != incapazId);
                    atualizarTabelaIncapazes();
                    alert('Incapaz removido com sucesso!');
                } else {
                    alert('Erro ao remover: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao remover incapaz');
            });
        }

        function carregarIncapazes(clienteId) {
            const indicador = document.getElementById('indicadorClienteIncapaz');
            const idSpan = document.getElementById('idClienteIncapaz');
            const nomeSpan = document.getElementById('nomeClienteIncapaz');
            const nomeCliente = document.getElementById('nome').value;

            if (!indicador || !idSpan || !nomeSpan) {
                return;
            }

            if (!clienteId) {
                incapazes = [];
                atualizarTabelaIncapazes();
                indicador.style.display = 'none';
                return;
            }

            indicador.style.display = 'block';
            idSpan.textContent = clienteId;
            nomeSpan.textContent = nomeCliente || 'Carregando...';

            const formData = new FormData();
            formData.append('acao', 'listar');
            formData.append('cliente_id', clienteId);

            fetch('salvar_incapaz_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    incapazes = data.registros || [];
                    atualizarTabelaIncapazes();
                } else {
                    console.error('Erro ao carregar incapazes:', data.message);
                    incapazes = [];
                    atualizarTabelaIncapazes();
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                incapazes = [];
                atualizarTabelaIncapazes();
            });
        }

        // ========== FUNÇÕES PARA CADASTRO A ROGO ==========

        function adicionarARogo() {
            const nome = document.getElementById('a_rogo_nome').value.trim();
            const identidade = document.getElementById('a_rogo_identidade').value.trim();
            const cpf = document.getElementById('a_rogo_cpf').value.trim();
            const aRogoIdEdicao = document.getElementById('a_rogo_id_edicao').value;

            if (!nome) {
                alert('Por favor, informe o nome do A ROGO');
                document.getElementById('a_rogo_nome').focus();
                return;
            }

            const clienteId = document.getElementById('cliente_id_hidden').value;
            if (!clienteId) {
                alert('Por favor, selecione ou salve um cliente primeiro');
                return;
            }

            const formData = new FormData();
            formData.append('acao', aRogoIdEdicao ? 'atualizar' : 'adicionar');
            formData.append('cliente_id', clienteId);
            formData.append('nome', nome);
            formData.append('identidade', identidade);
            formData.append('cpf', cpf);

            if (aRogoIdEdicao) {
                formData.append('a_rogo_id', aRogoIdEdicao);
            }

            fetch('salvar_a_rogo_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (aRogoIdEdicao) {
                        const index = aRogoRegistros.findIndex(r => r.id == aRogoIdEdicao);
                        if (index !== -1) {
                            aRogoRegistros[index] = {
                                id: aRogoIdEdicao,
                                nome: nome,
                                identidade: identidade,
                                cpf: cpf
                            };
                        }
                        alert('A ROGO atualizado com sucesso!');
                    } else {
                        aRogoRegistros.push({
                            id: data.id,
                            nome: nome,
                            identidade: identidade,
                            cpf: cpf
                        });
                        alert('A ROGO cadastrado com sucesso!');
                    }

                    atualizarTabelaARogo();
                    limparCamposARogo();
                } else {
                    alert('Erro ao salvar: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao cadastrar A ROGO');
            });
        }

        function atualizarTabelaARogo() {
            const tbody = document.getElementById('listaARogo');
            if (!tbody) {
                return;
            }

            if (aRogoRegistros.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center; color: #999; font-size: 9pt;">
                            Nenhum registro A ROGO cadastrado
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            aRogoRegistros.forEach((registro) => {
                html += `
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${registro.nome}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${registro.identidade || '-'}</td>
                        <td style="padding: 8px; font-size: 9pt; border: 1px solid #ddd;">${registro.cpf || '-'}</td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <button type="button" onclick="editarARogo(${registro.id})"
                                style="padding: 4px 10px; background: #ffc107; color: #000; border: none; border-radius: 3px; cursor: pointer; font-size: 8pt; width: 80px;">
                                ✏️ Editar
                            </button>
                        </td>
                        <td style="padding: 8px; text-align: center; border: 1px solid #ddd;">
                            <button type="button" onclick="removerARogo(${registro.id})"
                                style="padding: 4px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 8pt; width: 80px;">
                                🗑️ Excluir
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function editarARogo(aRogoId) {
            const registro = aRogoRegistros.find(r => r.id == aRogoId);
            if (!registro) return;

            document.getElementById('a_rogo_id_edicao').value = registro.id;
            document.getElementById('a_rogo_nome').value = registro.nome || '';
            document.getElementById('a_rogo_identidade').value = registro.identidade || '';
            document.getElementById('a_rogo_cpf').value = registro.cpf || '';

            const btnSalvar = document.getElementById('btnSalvarARogo');
            btnSalvar.innerHTML = '✏️ ATUALIZAR';
            btnSalvar.style.background = '#007bff';

            document.getElementById('btnCancelarEdicaoARogo').style.display = 'inline-block';
            document.getElementById('a_rogo_nome').focus();
        }

        function cancelarEdicaoARogo() {
            limparCamposARogo();
        }

        function limparCamposARogo() {
            const campoId = document.getElementById('a_rogo_id_edicao');
            const campoNome = document.getElementById('a_rogo_nome');
            const campoIdentidade = document.getElementById('a_rogo_identidade');
            const campoCpf = document.getElementById('a_rogo_cpf');
            const btnSalvar = document.getElementById('btnSalvarARogo');
            const btnCancelar = document.getElementById('btnCancelarEdicaoARogo');

            if (!campoId || !campoNome || !campoIdentidade || !campoCpf || !btnSalvar || !btnCancelar) {
                return;
            }

            campoId.value = '';
            campoNome.value = '';
            campoIdentidade.value = '';
            campoCpf.value = '';

            btnSalvar.innerHTML = '➕ ADICIONAR';
            btnSalvar.style.background = '#28a745';
            btnCancelar.style.display = 'none';
        }

        function fecharAbaARogo() {
            const abaARogo = document.getElementById('a_rogo');
            if (abaARogo) {
                abaARogo.style.display = 'none';
            }
        }

        function removerARogo(aRogoId) {
            if (!confirm('Deseja realmente excluir este registro A ROGO?')) {
                return;
            }

            const clienteId = document.getElementById('cliente_id_hidden').value;
            const formData = new FormData();
            formData.append('acao', 'excluir');
            formData.append('cliente_id', clienteId);
            formData.append('a_rogo_id', aRogoId);

            fetch('salvar_a_rogo_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    aRogoRegistros = aRogoRegistros.filter(r => r.id != aRogoId);
                    atualizarTabelaARogo();
                    alert('Registro A ROGO removido com sucesso!');
                } else {
                    alert('Erro ao remover: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao remover A ROGO');
            });
        }

        function carregarARogo(clienteId) {
            const indicador = document.getElementById('indicadorClienteARogo');
            const idSpan = document.getElementById('idClienteARogo');
            const nomeSpan = document.getElementById('nomeClienteARogo');
            const nomeCliente = document.getElementById('nome').value;

            if (!indicador || !idSpan || !nomeSpan) {
                return;
            }

            if (!clienteId) {
                aRogoRegistros = [];
                atualizarTabelaARogo();
                indicador.style.display = 'none';
                return;
            }

            indicador.style.display = 'block';
            idSpan.textContent = clienteId;
            nomeSpan.textContent = nomeCliente || 'Carregando...';

            const formData = new FormData();
            formData.append('acao', 'listar');
            formData.append('cliente_id', clienteId);

            fetch('salvar_a_rogo_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    aRogoRegistros = data.registros || [];
                    atualizarTabelaARogo();
                } else {
                    console.error('Erro ao carregar A ROGO:', data.message);
                    aRogoRegistros = [];
                    atualizarTabelaARogo();
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                aRogoRegistros = [];
                atualizarTabelaARogo();
            });
        }
        
        </script>

       
    </div>
    </div>
    </div><!-- /main-content -->
    </div><!-- /app-layout -->

    <!-- Modal de Documentos -->
    <?php include 'modal_documentos.html'; ?>
    <script src="modal_documentos.js?v=<?php echo time(); ?>"></script>
</body>
</html>