<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Definir tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_logado_id = $_SESSION['usuario_id'];
$is_admin = ($tipo_usuario === 'admin');
$is_parceiro = ($tipo_usuario === 'parceiro');

// USUARIO não tem acesso ao financeiro
if ($tipo_usuario === 'usuario') {
    header("Location: dashboard.php");
    exit();
}

include 'conexao.php';

$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cliente_id <= 0) {
    die("ID de cliente inválido.");
}

rls_enforce_cliente_or_die($conn, $cliente_id, false);

// Buscar dados do cliente com verificação de permissão
if ($is_admin) {
    // Admin vê todos
    $sql = "SELECT nome, cpf, data_contrato, situacao FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cliente_id);
} else if ($is_parceiro) {
    // Parceiro vê apenas seus clientes
    $sql = "SELECT nome, cpf, data_contrato, situacao FROM clientes WHERE id = ? AND usuario_cadastro_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cliente_id, $usuario_logado_id);
}
$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();
$stmt->close();

if (!$cliente) {
    die("Cliente não encontrado.");
}

// Buscar ou criar registro financeiro
$sql_fin = "SELECT * FROM financeiro WHERE cliente_id = ?";
$stmt_fin = $conn->prepare($sql_fin);
$stmt_fin->bind_param("i", $cliente_id);
$stmt_fin->execute();
$result_fin = $stmt_fin->get_result();
$financeiro = $result_fin->fetch_assoc();
$stmt_fin->close();

// Função para formatar valor para exibição (adiciona R$ e formata)
function formatarValorExibicao($valor) {
    if (empty($valor) || $valor == 0) return '';
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Formatar valores monetários para exibição
if ($financeiro) {
    $financeiro['valor_parcela'] = formatarValorExibicao($financeiro['valor_parcela'] ?? 0);
    $financeiro['retroativo'] = formatarValorExibicao($financeiro['retroativo'] ?? 0);
    $financeiro['saldo_retroativo'] = formatarValorExibicao($financeiro['saldo_retroativo'] ?? 0);
    $financeiro['honorarios_bruto'] = formatarValorExibicao($financeiro['honorarios_bruto'] ?? 0);
    $financeiro['honorarios_parceiro'] = formatarValorExibicao($financeiro['honorarios_parceiro'] ?? 0);
    $financeiro['honorarios_advogado'] = formatarValorExibicao($financeiro['honorarios_advogado'] ?? 0);
    $financeiro['honorarios_liquido'] = formatarValorExibicao($financeiro['honorarios_liquido'] ?? 0);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $data_contrato = $_POST['data_contrato'] ?? null;
    $data_aprovado = $_POST['data_aprovado'] ?? null;
    $data_vencimento = $_POST['data_vencimento'] ?? null;
    // Função para converter valor monetário em float
    function converterMoeda($valor) {
        $valor = str_replace('R$', '', $valor);
        $valor = trim($valor);
        
        // Verificar se tem vírgula (formato brasileiro: 5.000,00)
        if (strpos($valor, ',') !== false) {
            // Formato brasileiro: remover pontos de milhares e trocar vírgula por ponto
            $valor = str_replace('.', '', $valor); // Remove pontos de milhares
            $valor = str_replace(',', '.', $valor); // Troca vírgula por ponto
        } else {
            // Formato americano ou número puro (5000.00 ou 5000)
            // Não remover pontos, apenas garantir que está no formato correto
            // Se não tem ponto decimal, assumir que é um número inteiro
        }
        
        return floatval($valor);
    }
    
    $qtd_parcelas = floatval($_POST['qtd_parcelas'] ?? 0);
    $valor_parcela = converterMoeda($_POST['valor_parcela'] ?? '0');
    $parcelas_pagas = floatval($_POST['parcelas_pagas'] ?? 0);
    $parcelas_faltantes = floatval($_POST['parcelas_faltantes'] ?? 0);
    $retroativo = converterMoeda($_POST['retroativo'] ?? '0');
    $percentual_retroativo = floatval($_POST['percentual_retroativo'] ?? 0);
    $saldo_retroativo = converterMoeda($_POST['saldo_retroativo'] ?? '0');
    $honorarios_bruto = converterMoeda($_POST['honorarios_bruto'] ?? '0');
    $honorarios_parceiro = converterMoeda($_POST['honorarios_parceiro'] ?? '0');
    $honorarios_advogado = converterMoeda($_POST['honorarios_advogado'] ?? '0');
    $honorarios_liquido = converterMoeda($_POST['honorarios_liquido'] ?? '0');
    $saldo_negativo = converterMoeda($_POST['saldo_negativo'] ?? '0');
    $pago = converterMoeda($_POST['pago'] ?? '0');
    
    // Capturar valores das 24 parcelas dinamicamente
    for($i = 1; $i <= 24; $i++) {
        $valor_parcela = $_POST['parcela'.$i] ?? '';
        ${'parcela'.$i} = !empty($valor_parcela) ? converterMoeda($valor_parcela) : null;
        ${'data_parcela'.$i} = $_POST['data_parcela'.$i] ?? null;
        ${'paga'.$i} = isset($_POST['paga'.$i]) ? 1 : 0;
    }
    
    if ($financeiro) {
        // Atualizar
        $sql_update = "UPDATE financeiro SET status=?, data_contrato=?, data_aprovado=?, data_vencimento=?, 
                       qtd_parcelas=?, valor_parcela=?, parcelas_pagas=?, parcelas_faltantes=?, 
                       retroativo=?, percentual_retroativo=?, saldo_retroativo=?, honorarios_bruto=?, 
                       honorarios_parceiro=?, honorarios_advogado=?, honorarios_liquido=?, saldo_negativo=?, 
                       pago=?, parcela1=?, data_parcela1=?, paga1=?, parcela2=?, data_parcela2=?, paga2=?, parcela3=?, data_parcela3=?, paga3=?, 
                       parcela4=?, data_parcela4=?, paga4=?, parcela5=?, data_parcela5=?, paga5=?, parcela6=?, data_parcela6=?, paga6=?, 
                       parcela7=?, data_parcela7=?, paga7=?, parcela8=?, data_parcela8=?, paga8=?, parcela9=?, data_parcela9=?, paga9=?, 
                       parcela10=?, data_parcela10=?, paga10=?, parcela11=?, data_parcela11=?, paga11=?, parcela12=?, data_parcela12=?, paga12=?, 
                       parcela13=?, data_parcela13=?, paga13=?, parcela14=?, data_parcela14=?, paga14=?, parcela15=?, data_parcela15=?, paga15=?, 
                       parcela16=?, data_parcela16=?, paga16=?, parcela17=?, data_parcela17=?, paga17=?, parcela18=?, data_parcela18=?, paga18=?, 
                       parcela19=?, data_parcela19=?, paga19=?, parcela20=?, data_parcela20=?, paga20=?, parcela21=?, data_parcela21=?, paga21=?, 
                       parcela22=?, data_parcela22=?, paga22=?, parcela23=?, data_parcela23=?, paga23=?, parcela24=?, data_parcela24=?, paga24=?
                       WHERE cliente_id=?";

        $stmt_save = $conn->prepare($sql_update);
        if (!$stmt_save) {
            error_log("Erro DB financeiro [" . basename(__FILE__) . "]: " . $conn->error); die("Erro interno ao salvar dados financeiros. Por favor, tente novamente.");
        }
        $stmt_save->bind_param("ssssdddddddddddddssissississississississississississississississississississississississii", 
                        
                        $status, $data_contrato, $data_aprovado, $data_vencimento,
                               $qtd_parcelas, $valor_parcela, $parcelas_pagas, $parcelas_faltantes,
                               $retroativo, $percentual_retroativo, $saldo_retroativo, $honorarios_bruto,
                               $honorarios_parceiro, $honorarios_advogado, $honorarios_liquido, $saldo_negativo,
                               $pago, $parcela1, $data_parcela1, $paga1, $parcela2, $data_parcela2, $paga2, $parcela3, $data_parcela3, $paga3, 
                                $parcela4, $data_parcela4, $paga4, $parcela5, $data_parcela5, $paga5, $parcela6, $data_parcela6, $paga6, 
                                $parcela7, $data_parcela7, $paga7, $parcela8, $data_parcela8, $paga8, $parcela9, $data_parcela9, $paga9, 
                                $parcela10, $data_parcela10, $paga10, $parcela11, $data_parcela11, $paga11, $parcela12, $data_parcela12, $paga12, 
                                $parcela13, $data_parcela13, $paga13, $parcela14, $data_parcela14, $paga14, $parcela15, $data_parcela15, $paga15, 
                                $parcela16, $data_parcela16, $paga16, $parcela17, $data_parcela17, $paga17, $parcela18, $data_parcela18, $paga18, 
                                $parcela19, $data_parcela19, $paga19, $parcela20, $data_parcela20, $paga20, $parcela21, $data_parcela21, $paga21, 
                                $parcela22, $data_parcela22, $paga22, $parcela23, $data_parcela23, $paga23, $parcela24, $data_parcela24, $paga24, 
                               $cliente_id);
    } else {
        // Inserir
        $sql_insert = "INSERT INTO financeiro (cliente_id, status, data_contrato, data_aprovado, data_vencimento, 
                       qtd_parcelas, valor_parcela, parcelas_pagas, parcelas_faltantes, retroativo, 
                       percentual_retroativo, saldo_retroativo, honorarios_bruto, honorarios_parceiro, 
                       honorarios_advogado, honorarios_liquido, saldo_negativo, pago, 
                       parcela1, data_parcela1, paga1, parcela2, data_parcela2, paga2, parcela3, data_parcela3, paga3, 
                       parcela4, data_parcela4, paga4, parcela5, data_parcela5, paga5, parcela6, data_parcela6, paga6, 
                       parcela7, data_parcela7, paga7, parcela8, data_parcela8, paga8, parcela9, data_parcela9, paga9, 
                       parcela10, data_parcela10, paga10, parcela11, data_parcela11, paga11, parcela12, data_parcela12, paga12, 
                       parcela13, data_parcela13, paga13, parcela14, data_parcela14, paga14, parcela15, data_parcela15, paga15, 
                       parcela16, data_parcela16, paga16, parcela17, data_parcela17, paga17, parcela18, data_parcela18, paga18, 
                       parcela19, data_parcela19, paga19, parcela20, data_parcela20, paga20, parcela21, data_parcela21, paga21, 
                       parcela22, data_parcela22, paga22, parcela23, data_parcela23, paga23, parcela24, data_parcela24, paga24)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_save = $conn->prepare($sql_insert);
        if (!$stmt_save) {
            error_log("Erro DB financeiro [" . basename(__FILE__) . "]: " . $conn->error); die("Erro interno ao salvar dados financeiros. Por favor, tente novamente.");
        }
        $stmt_save->bind_param("issssdddddddddddddssissississississississississississississississississississississississi",
        
        $cliente_id, $status, $data_contrato, $data_aprovado, $data_vencimento,
                               $qtd_parcelas, $valor_parcela, $parcelas_pagas, $parcelas_faltantes,
                               $retroativo, $percentual_retroativo, $saldo_retroativo, $honorarios_bruto,
                               $honorarios_parceiro, $honorarios_advogado, $honorarios_liquido, $saldo_negativo,
                               $pago,
                                $parcela1, $data_parcela1, $paga1, $parcela2, $data_parcela2, $paga2, $parcela3, $data_parcela3, $paga3, 
                                $parcela4, $data_parcela4, $paga4, $parcela5, $data_parcela5, $paga5, $parcela6, $data_parcela6, $paga6, 
                                $parcela7, $data_parcela7, $paga7, $parcela8, $data_parcela8, $paga8, $parcela9, $data_parcela9, $paga9, 
                                $parcela10, $data_parcela10, $paga10, $parcela11, $data_parcela11, $paga11, $parcela12, $data_parcela12, $paga12, 
                                $parcela13, $data_parcela13, $paga13, $parcela14, $data_parcela14, $paga14, $parcela15, $data_parcela15, $paga15, 
                                $parcela16, $data_parcela16, $paga16, $parcela17, $data_parcela17, $paga17, $parcela18, $data_parcela18, $paga18, 
                                $parcela19, $data_parcela19, $paga19, $parcela20, $data_parcela20, $paga20, $parcela21, $data_parcela21, $paga21, 
                                $parcela22, $data_parcela22, $paga22, $parcela23, $data_parcela23, $paga23, $parcela24, $data_parcela24, $paga24);
    }
    
    if ($stmt_save->execute()) {
        $mensagem = "Dados financeiros salvos com sucesso!";
        $tipo_mensagem = "sucesso";
        // Recarregar dados
        $stmt_fin = $conn->prepare($sql_fin);
        $stmt_fin->bind_param("i", $cliente_id);
        $stmt_fin->execute();
        $result_fin = $stmt_fin->get_result();
        $financeiro = $result_fin->fetch_assoc();
        $stmt_fin->close();
        
        // Se for requisição AJAX (auto-save), retornar JSON e sair
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'mensagem' => $mensagem]);
            $stmt_save->close();
            $conn->close();
            exit();
        }
    } else {
        $mensagem = "Erro ao salvar: " . $conn->error;
        $tipo_mensagem = "erro";
        
        // Se for requisição AJAX (auto-save), retornar JSON e sair
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'mensagem' => $mensagem]);
            $stmt_save->close();
            $conn->close();
            exit();
        }
    }
    $stmt_save->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - <?php echo htmlspecialchars($cliente['nome']); ?></title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
        }
        .topbar { background: #0b1220; color: #fff; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; box-shadow: 0 3px 10px rgba(15, 23, 42, 0.2); }
        .topbar-links { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .topbar a { color: #fff; text-decoration: none; padding: 6px 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.16); font-size: 9pt; }
        .topbar a:hover { background: rgba(255,255,255,0.2); }
        .topbar-toggle { display: none; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: #fff; padding: 6px 10px; border-radius: 8px; cursor: pointer; font-size: 9pt; font-weight: 700; }
        .topbar-toggle:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 16px auto; padding: 0 12px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .card { background: #fff; border-radius: 8px; border: 1px solid #dbe3ef; padding: 14px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08); }
        .kpi-title { font-size: 9pt; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.2px; font-weight: 600; }
        .kpi-value { font-size: 12pt; font-weight: 700; color: #0f172a; }
        .table-box { background: #fff; border-radius: 8px; border: 1px solid #dbe3ef; padding: 14px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08); }
        .table-box h2 { margin: 0 0 10px; font-size: 12pt; font-weight: 700; color: #0f172a; text-transform: uppercase; }
        h1 {
            color: #0f172a;
            text-align: center;
            margin-bottom: 10px;
            font-size: 18pt;
            text-transform: uppercase;
        }
        .cliente-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            font-size: 10pt;
            text-transform: uppercase;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group.full {
            grid-column: span 4;
        }
        .form-group.half {
            grid-column: span 2;
        }
        label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        input, select, textarea {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 9pt;
            font-family: 'Calibri', 'CalibriFallback', 'Segoe UI', Arial, sans-serif;
            text-transform: uppercase;
        }
        input[readonly] {
            background: #f8fafc;
        }
        .campo-moeda {
            text-align: center;
        }
        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 10pt;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            text-transform: uppercase;
        }
        .btn-salvar {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        .btn-salvar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        .btn-voltar {
            background: #475569;
            color: #fff;
            box-shadow: 0 4px 15px rgba(71, 85, 105, 0.3);
        }
        .btn-voltar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(71, 85, 105, 0.4);
        }
        .btn-pdf {
            background: #0f766e;
            color: #fff;
            box-shadow: 0 4px 15px rgba(15, 118, 110, 0.3);
        }
        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 118, 110, 0.4);
        }
        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            font-size: 9pt;
            text-transform: uppercase;
        }
        .sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .section-title {
            grid-column: span 4;
            font-size: 11pt;
            font-weight: 700;
            color: #0f172a;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #cbd5e1;
            text-transform: uppercase;
        }
        .planilha-wrap { overflow: auto; margin-bottom: 14px; }
        .planilha-financeira, .planilha-parcelas {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #ffffff;
        }
        .planilha-financeira th, .planilha-financeira td,
        .planilha-parcelas th, .planilha-parcelas td {
            border: 1px solid #dbe3ef;
            padding: 8px;
            font-size: 9pt;
            vertical-align: middle;
        }
        .planilha-financeira th,
        .planilha-parcelas th {
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
        }
        .planilha-financeira td.label-cell {
            background: #f8fafc;
            font-weight: 700;
            color: #334155;
            width: 18%;
            text-transform: uppercase;
        }
        .planilha-financeira td.value-cell {
            width: 32%;
        }
        .planilha-financeira input,
        .planilha-financeira select,
        .planilha-financeira textarea,
        .planilha-parcelas input,
        .planilha-parcelas select {
            width: 100%;
            box-sizing: border-box;
        }
        .planilha-parcelas td:first-child {
            text-align: center;
            width: 75px;
            font-weight: 700;
            background: #f8fafc;
        }
        .planilha-parcelas td:last-child {
            text-align: center;
            width: 95px;
        }
        .painel-pagamentos {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 14px;
            align-items: start;
            margin-bottom: 16px;
        }
        .grafico-box {
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #fff;
            padding: 10px;
            display: flex;
            justify-content: center;
        }
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group.full,
            .form-group.half,
            .section-title {
                grid-column: span 2;
            }
            .painel-pagamentos {
                grid-template-columns: 1fr;
            }
        }
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full,
            .form-group.half,
            .section-title {
                grid-column: span 1;
            }
            .buttons {
                flex-direction: column;
            }
            .btn {
                text-align: center;
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
            <div class="card">
                <div class="kpi-title">Cliente</div>
                <div class="kpi-value"><?php echo htmlspecialchars($cliente['nome']); ?></div>
            </div>
            <div class="card">
                <div class="kpi-title">CPF</div>
                <div class="kpi-value"><?php echo htmlspecialchars($cliente['cpf']); ?></div>
            </div>
        </div>

        <div class="table-box">
        <h2>Financeiro</h2>
        
        <?php if (isset($mensagem)): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="formFinanceiro">
            <div class="section-title">Planilha financeira</div>
            <div class="planilha-wrap">
                <table class="planilha-financeira">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Valor</th>
                            <th>Campo</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell">Status</td>
                            <td class="value-cell">
                                <select id="status" name="status" required>
                                    <option value="">Selecione</option>
                                    <option value="enviado" <?php echo ($cliente['situacao'] == 'enviado') ? 'selected' : ''; ?>>Enviado</option>
                                    <option value="negado" <?php echo ($cliente['situacao'] == 'negado') ? 'selected' : ''; ?>>Negado</option>
                                    <option value="aprovado" <?php echo ($cliente['situacao'] == 'aprovado') ? 'selected' : ''; ?>>Aprovado</option>
                                    <option value="pago" <?php echo ($cliente['situacao'] == 'pago') ? 'selected' : ''; ?>>Pago</option>
                                    <option value="pericia" <?php echo ($cliente['situacao'] == 'pericia') ? 'selected' : ''; ?>>Perícia</option>
                                    <option value="justica" <?php echo ($cliente['situacao'] == 'justica') ? 'selected' : ''; ?>>Justiça</option>
                                    <option value="avaliacao_social" <?php echo ($cliente['situacao'] == 'avaliacao_social') ? 'selected' : ''; ?>>Avaliação Social</option>
                                    <option value="indeferido" <?php echo ($cliente['situacao'] == 'indeferido') ? 'selected' : ''; ?>>Indeferido</option>
                                    <option value="deferido" <?php echo ($cliente['situacao'] == 'deferido') ? 'selected' : ''; ?>>Deferido</option>
                                    <option value="escritorio" <?php echo ($cliente['situacao'] == 'escritorio') ? 'selected' : ''; ?>>Escritório</option>
                                    <option value="pendencia" <?php echo ($cliente['situacao'] == 'pendencia') ? 'selected' : ''; ?>>Pendência</option>
                                    <option value="cancelado" <?php echo ($cliente['situacao'] == 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                                    <option value="falta_senha_meuinss" <?php echo ($cliente['situacao'] == 'falta_senha_meuinss') ? 'selected' : ''; ?>>Falta a Senha do MeuINSS</option>
                                    <option value="esperando_data_certa" <?php echo ($cliente['situacao'] == 'esperando_data_certa') ? 'selected' : ''; ?>>Esperando a Data Certa</option>
                                    <option value="falta_assinar_contrato" <?php echo ($cliente['situacao'] == 'falta_assinar_contrato') ? 'selected' : ''; ?>>Falta Assinar Contrato</option>
                                    <option value="nao_pagou_escritorio" <?php echo ($cliente['situacao'] == 'nao_pagou_escritorio') ? 'selected' : ''; ?>>Não Pagou o Escritório</option>
                                    <option value="baixa_definitiva" <?php echo ($cliente['situacao'] == 'baixa_definitiva') ? 'selected' : ''; ?>>Baixa Definitiva</option>
                                    <option value="cadastro_biometria" <?php echo ($cliente['situacao'] == 'cadastro_biometria') ? 'selected' : ''; ?>>Cadastro de Biometria</option>
                                    <option value="concluido_sem_decisao" <?php echo ($cliente['situacao'] == 'concluido_sem_decisao') ? 'selected' : ''; ?>>Concluído Sem Decisão</option>
                                    <option value="reenvia" <?php echo ($cliente['situacao'] == 'reenvia') ? 'selected' : ''; ?>>Reenviar</option>
                                    <option value="pagando" <?php echo ($cliente['situacao'] == 'pagando') ? 'selected' : ''; ?>>Pagando</option>
                                    <option value="atendimento" <?php echo ($cliente['situacao'] == 'atendimento') ? 'selected' : ''; ?>>Atendimento</option>
                                    <option value="crianca_nao_nasceu" <?php echo ($cliente['situacao'] == 'crianca_nao_nasceu') ? 'selected' : ''; ?>>A criança ainda não nasceu</option>
                                </select>
                            </td>
                            <td class="label-cell">Data contrato</td>
                            <td class="value-cell"><input type="date" id="data_contrato" name="data_contrato" value="<?php echo $cliente['data_contrato'] ?? ''; ?>" readonly></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Data aprovado</td>
                            <td class="value-cell"><input type="date" id="data_aprovado" name="data_aprovado" value="<?php echo $financeiro['data_aprovado'] ?? ''; ?>"></td>
                            <td class="label-cell">Data vencimento</td>
                            <td class="value-cell"><input type="date" id="data_vencimento" name="data_vencimento" value="<?php echo $financeiro['data_vencimento'] ?? ''; ?>"></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Qtd parcelas</td>
                            <td class="value-cell"><input type="number" id="qtd_parcelas" name="qtd_parcelas" step="1" min="0" data-skip-format="true" value="<?php echo isset($financeiro['qtd_parcelas']) ? intval($financeiro['qtd_parcelas']) : ''; ?>"></td>
                            <td class="label-cell">Valor da parcela</td>
                            <td class="value-cell"><input type="text" id="valor_parcela" name="valor_parcela" class="campo-moeda" value="<?php echo $financeiro['valor_parcela'] ?? ''; ?>"></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Parcelas pagas</td>
                            <td class="value-cell"><input type="number" id="parcelas_pagas" name="parcelas_pagas" step="1" min="0" data-skip-format="true" value="<?php echo isset($financeiro['parcelas_pagas']) ? intval($financeiro['parcelas_pagas']) : ''; ?>"></td>
                            <td class="label-cell">Parcelas faltantes</td>
                            <td class="value-cell"><input type="number" id="parcelas_faltantes" name="parcelas_faltantes" step="1" min="0" data-skip-format="true" value="<?php echo isset($financeiro['parcelas_faltantes']) ? intval($financeiro['parcelas_faltantes']) : ''; ?>" readonly></td>
                        </tr>
                        <tr>
                            <td class="label-cell">R$ retroativo</td>
                            <td class="value-cell"><input type="text" id="retroativo" name="retroativo" class="campo-moeda" value="<?php echo $financeiro['retroativo'] ?? ''; ?>"></td>
                            <td class="label-cell">% retroativo</td>
                            <td class="value-cell"><input type="number" id="percentual_retroativo" name="percentual_retroativo" step="any" min="0" max="100" value="<?php echo isset($financeiro['percentual_retroativo']) ? (intval($financeiro['percentual_retroativo']) == $financeiro['percentual_retroativo'] ? intval($financeiro['percentual_retroativo']) : $financeiro['percentual_retroativo']) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <td class="label-cell">R$ saldo retroativo</td>
                            <td class="value-cell"><input type="text" id="saldo_retroativo" name="saldo_retroativo" class="campo-moeda" value="<?php echo $financeiro['saldo_retroativo'] ?? ''; ?>" readonly></td>
                            <td class="label-cell">R$ honorários bruto</td>
                            <td class="value-cell"><input type="text" id="honorarios_bruto" name="honorarios_bruto" class="campo-moeda" value="<?php echo $financeiro['honorarios_bruto'] ?? ''; ?>" readonly></td>
                        </tr>
                        <tr>
                            <td class="label-cell">R$ honorário parceiro</td>
                            <td class="value-cell"><input type="text" id="honorarios_parceiro" name="honorarios_parceiro" class="campo-moeda" value="<?php echo $financeiro['honorarios_parceiro'] ?? ''; ?>"></td>
                            <td class="label-cell">R$ honorários advogado</td>
                            <td class="value-cell"><input type="text" id="honorarios_advogado" name="honorarios_advogado" class="campo-moeda" value="<?php echo $financeiro['honorarios_advogado'] ?? ''; ?>"></td>
                        </tr>
                        <tr>
                            <td class="label-cell">R$ honorários líquido</td>
                            <td class="value-cell"><input type="text" id="honorarios_liquido" name="honorarios_liquido" class="campo-moeda" value="<?php echo $financeiro['honorarios_liquido'] ?? ''; ?>" readonly></td>
                            <td class="label-cell">R$ saldo negativo</td>
                            <td class="value-cell"><input type="text" id="saldo_negativo" name="saldo_negativo" class="campo-moeda" value="<?php echo $financeiro['saldo_negativo'] ?? ''; ?>" readonly></td>
                        </tr>
                        <tr>
                            <td class="label-cell">R$ pago</td>
                            <td class="value-cell"><input type="text" id="pago" name="pago" class="campo-moeda" value="<?php echo $financeiro['pago'] ?? ''; ?>" readonly></td>
                            <td class="label-cell">Resumo gráfico</td>
                            <td class="value-cell">Atualizado automaticamente</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="painel-pagamentos">
                <div></div>
                <div class="grafico-box">
                    <canvas id="graficoSaldo" style="max-width: 320px; max-height: 320px;"></canvas>
                </div>
            </div>

            <div class="section-title">Planilha de parcelas</div>
            <div class="planilha-wrap">
                <table class="planilha-parcelas">
                    <thead>
                        <tr>
                            <th>Parcela</th>
                            <th>Valor (R$)</th>
                            <th>Data</th>
                            <th>Paga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($i = 1; $i <= 24; $i++): ?>
                            <tr>
                                <td><?php echo $i; ?>ª</td>
                                <td><input type="text" id="parcela<?php echo $i; ?>" name="parcela<?php echo $i; ?>" class="campo-moeda parcela-valor" value="<?php echo $financeiro['parcela'.$i] ?? ''; ?>"></td>
                                <td><input type="date" id="data_parcela<?php echo $i; ?>" name="data_parcela<?php echo $i; ?>" value="<?php echo $financeiro['data_parcela'.$i] ?? ''; ?>"></td>
                                <td>
                                    <input type="checkbox" id="paga<?php echo $i; ?>" name="paga<?php echo $i; ?>" class="checkbox-paga" value="1" <?php echo (!empty($financeiro['paga'.$i]) && $financeiro['paga'.$i] == 1) ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="buttons">
                <a href="listar_clientes.php" class="btn btn-voltar">Voltar</a>
                <a href="gerar_prontuario_financeiro.php?id=<?php echo $cliente_id; ?>" class="btn btn-pdf">Gerar Prontuário PDF</a>
            </div>
            <div id="mensagemAutoSave" style="text-align: center; margin-top: 10px; font-size: 12px; color: #666; min-height: 20px;"></div>
        </form>
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

    // Função para extrair valor numérico de campo formatado
    function extrairValor(campo) {
        let valor = campo.value.replace('R$', '').trim();
        
        // Verificar se tem vírgula (formato brasileiro: 5.000,00)
        if (valor.includes(',')) {
            // Formato brasileiro: remover pontos de milhares e trocar vírgula por ponto
            valor = valor.replace(/\./g, ''); // Remove pontos de milhares
            valor = valor.replace(',', '.'); // Troca vírgula por ponto
        }
        // Se não tem vírgula, manter como está (formato americano ou número puro)
        
        return parseFloat(valor) || 0;
    }
    
    // Calcular parcelas faltantes automaticamente
    function calcularParcelasFaltantes() {
        const qtd = parseInt(document.getElementById('qtd_parcelas').value) || 0;
        const pagas = parseInt(document.getElementById('parcelas_pagas').value) || 0;
        document.getElementById('parcelas_faltantes').value = qtd - pagas;
    }
    
    // Calcular saldo retroativo (retroativo * percentual / 100)
    function calcularSaldoRetroativo() {
        const retroativo = extrairValor(document.getElementById('retroativo'));
        const percentual = parseFloat(document.getElementById('percentual_retroativo').value) || 0;
        const saldo = retroativo * (percentual / 100);
        
        const campoSaldo = document.getElementById('saldo_retroativo');
        const campoSaldoFormatado = 'R$ ' + saldo.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        campoSaldo.value = campoSaldoFormatado;
        campoSaldo.setAttribute('data-formatted', 'true');
        
        // Copiar valor para Honorários Bruto
        const campoBruto = document.getElementById('honorarios_bruto');
        campoBruto.value = campoSaldoFormatado;
        campoBruto.setAttribute('data-formatted', 'true');
        
        // Recalcular honorários líquido
        calcularHonorariosLiquido();
    }
    
    // Calcular honorários líquido
    function calcularHonorariosLiquido() {
        const bruto = extrairValor(document.getElementById('honorarios_bruto'));
        const parceiro = extrairValor(document.getElementById('honorarios_parceiro'));
        const advogado = extrairValor(document.getElementById('honorarios_advogado'));
        const liquido = bruto - parceiro - advogado;
        
        const campoLiquido = document.getElementById('honorarios_liquido');
        const campoLiquidoFormatado = 'R$ ' + liquido.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        campoLiquido.value = campoLiquidoFormatado;
        campoLiquido.setAttribute('data-formatted', 'true');
        
        // Recalcular saldo negativo quando honorários líquido mudar
        calcularTotaisParcelas();
    }
    
    // Adicionar eventos
    document.getElementById('qtd_parcelas').addEventListener('input', calcularParcelasFaltantes);
    document.getElementById('parcelas_pagas').addEventListener('input', calcularParcelasFaltantes);
    
    // Event listeners específicos para campos que precisam de cálculo em tempo real
    document.getElementById('percentual_retroativo').addEventListener('input', calcularSaldoRetroativo);
    
    // Calcular ao carregar (antes da formatação)
    calcularParcelasFaltantes();
    calcularSaldoRetroativo();
    calcularHonorariosLiquido();
    
    // Função para formatar moeda (valores já em reais, não centavos)
    function formatarMoeda(input) {
        let valor = input.value;
        
        // Se o campo está vazio, não fazer nada
        if (!valor || valor.trim() === '') {
            return;
        }
        
        // Se já tem R$, extrair o número formatado e reformatar
        if (valor.includes('R$')) {
            valor = valor.replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
            let numero = parseFloat(valor) || 0;
            input.value = 'R$ ' + numero.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            return;
        }
        
        // Se não tem R$, tratar como string de dígitos ou número
        // Primeiro, tentar extrair apenas números e pontos/vírgulas
        let valorLimpo = valor.replace(/[^\d,.-]/g, ''); // Mantém apenas dígitos, vírgula, ponto e hífen
        
        // Detectar formato brasileiro: tem vírgula (separador decimal) e pode ter pontos (separadores de milhares)
        if (valorLimpo.includes(',')) {
            // Formato brasileiro: remover pontos (milhares) e trocar vírgula por ponto
            valorLimpo = valorLimpo.replace(/\./g, ''); // Remove todos os pontos (separadores de milhares)
            valorLimpo = valorLimpo.replace(',', '.'); // Troca vírgula por ponto para parseFloat
            let numero = parseFloat(valorLimpo) || 0;
            input.value = 'R$ ' + numero.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            return;
        }
        
        // Detectar formato americano: tem ponto como separador decimal (apenas um ponto no final)
        if (valorLimpo.includes('.')) {
            let partes = valorLimpo.split('.');
            // Se tem mais de 2 partes, o último ponto é decimal e os anteriores são milhares
            if (partes.length > 2) {
                // Formato com milhares: remover pontos intermediários
                let parteInteira = partes.slice(0, -1).join(''); // Todas as partes exceto a última
                let parteDecimal = partes[partes.length - 1]; // Última parte é decimal
                valorLimpo = parteInteira + '.' + parteDecimal;
            }
            // Se tem exatamente 2 partes, já está no formato correto
            let numero = parseFloat(valorLimpo) || 0;
            input.value = 'R$ ' + numero.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            return;
        }
        
        // Se é apenas dígitos, tratar como centavos (dividir por 100)
        valorLimpo = valorLimpo.replace(/\D/g, ''); // Remove tudo que não é dígito
        if (!valorLimpo || valorLimpo === '0') {
            input.value = '';
            return;
        }
        
        // Converter para número e dividir por 100 para obter casas decimais
        let numero = parseFloat(valorLimpo) / 100;
        input.value = 'R$ ' + numero.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Aplicar formatação em todos os campos de moeda
    document.querySelectorAll('.campo-moeda').forEach(function(campo) {
        // Sempre adicionar event listeners para campos editáveis
        if (!campo.readOnly) {
            // Limpar formatação quando o usuário começar a digitar
            campo.addEventListener('focus', function(e) {
                // Se o campo tem valor formatado, limpar para permitir digitação
                if (e.target.value && e.target.value.includes('R$')) {
                    let valorNumerico = e.target.value.replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
                    e.target.value = valorNumerico;
                }
            });
            
            // Formatar ao sair do campo
            campo.addEventListener('blur', function(e) {
                if (e.target.value && !e.target.dataset.skipFormat) {
                    formatarMoeda(e.target);
                    // Se for campo de parcela, recalcular totais
                    if (e.target.id.startsWith('parcela') && !e.target.id.includes('data')) {
                        calcularTotaisParcelas();
                    }
                    // Se for campo de retroativo, recalcular saldo retroativo
                    if (e.target.id === 'retroativo') {
                        calcularSaldoRetroativo();
                    }
                    // Se for campo de honorários, recalcular honorários líquido
                    if (e.target.id === 'honorarios_parceiro' || e.target.id === 'honorarios_advogado') {
                        calcularHonorariosLiquido();
                    }
                }
            });
            
            // Para campos específicos que precisam de cálculo em tempo real (sem formatar durante digitação)
            if (campo.id === 'retroativo') {
                campo.addEventListener('input', function() {
                    calcularSaldoRetroativo();
                });
            }
            
            if (campo.id === 'honorarios_parceiro' || campo.id === 'honorarios_advogado') {
                campo.addEventListener('input', function() {
                    calcularHonorariosLiquido();
                });
            }
        }
    });
    
    // Adicionar evento nos checkboxes de parcela paga
    document.querySelectorAll('.checkbox-paga').forEach(function(checkbox) {
        checkbox.addEventListener('change', calcularTotaisParcelas);
    });
    
    // Calcular totais após formatação dos campos
    setTimeout(function() {
        calcularTotaisParcelas();
    }, 100);
    
    // Calcular totais de parcelas (R$ Pago e R$ Saldo Negativo)
    function calcularTotaisParcelas() {
        let totalPago = 0;
        let totalFaltante = 0;
        
        // Somar parcelas pagas e faltantes
        for (let i = 1; i <= 24; i++) {
            const campoParcela = document.getElementById('parcela' + i);
            const checkboxPaga = document.getElementById('paga' + i);
            
            if (campoParcela && campoParcela.value) {
                const valor = extrairValor(campoParcela);
                
                // Se checkbox estiver marcado, é parcela paga
                if (checkboxPaga && checkboxPaga.checked) {
                    totalPago += valor;
                } else {
                    totalFaltante += valor;
                }
            }
        }
        
        // Atualizar R$ Pago com a soma das parcelas pagas
        const campoPago = document.getElementById('pago');
        const campoPagoFormatado = 'R$ ' + totalPago.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        campoPago.value = campoPagoFormatado;
        campoPago.setAttribute('data-formatted', 'true');
        
        // Atualizar R$ Saldo Negativo com a soma das parcelas faltantes
        const campoSaldoNegativo = document.getElementById('saldo_negativo');
        const campoSaldoNegativoFormatado = 'R$ ' + totalFaltante.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        campoSaldoNegativo.value = campoSaldoNegativoFormatado;
        campoSaldoNegativo.setAttribute('data-formatted', 'true');
    }
    
    // Sistema de Auto-Save
    let autoSaveTimeout;
    let isSaving = false;
    
    function prepararDadosFormulario() {
        const formData = new FormData(document.getElementById('formFinanceiro'));
        
        // Converter campos de moeda formatados para números
        document.querySelectorAll('.campo-moeda').forEach(function(campo) {
            if (campo.value && campo.value.includes('R$')) {
                let valorNumerico = campo.value.replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
                formData.set(campo.name, valorNumerico);
            }
        });
        
        return formData;
    }
    
    function salvarAutomaticamente() {
        if (isSaving) return;
        
        isSaving = true;
        const mensagemEl = document.getElementById('mensagemAutoSave');
        mensagemEl.textContent = 'Salvando...';
        mensagemEl.style.color = '#666';
        
        const formData = prepararDadosFormulario();
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            return response.text().then(text => ({ success: true, mensagem: 'Salvo' }));
        })
        .then(data => {
            if (data.success) {
                mensagemEl.textContent = '✓ Salvo automaticamente';
                mensagemEl.style.color = '#28a745';
            } else {
                mensagemEl.textContent = '✗ ' + (data.mensagem || 'Erro ao salvar');
                mensagemEl.style.color = '#dc3545';
            }
            setTimeout(() => {
                mensagemEl.textContent = '';
            }, 2000);
            isSaving = false;
        })
        .catch(error => {
            mensagemEl.textContent = '✗ Erro ao salvar';
            mensagemEl.style.color = '#dc3545';
            console.error('Erro ao salvar:', error);
            isSaving = false;
        });
    }
    
    // Adicionar event listeners para auto-save em todos os campos editáveis
    function configurarAutoSave() {
        const campos = document.querySelectorAll('#formFinanceiro input, #formFinanceiro select');
        
        campos.forEach(function(campo) {
            // Ignorar campos readonly e checkboxes (que serão tratados separadamente)
            if (campo.readOnly || campo.type === 'checkbox') {
                return;
            }
            
            // Event listener para input, change, blur
            ['input', 'change', 'blur'].forEach(function(evento) {
                campo.addEventListener(evento, function() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(salvarAutomaticamente, 1000); // Salva após 1 segundo sem digitação
                });
            });
        });
        
        // Tratar checkboxes separadamente
        document.querySelectorAll('#formFinanceiro input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(salvarAutomaticamente, 500); // Salva mais rápido para checkboxes
            });
        });
    }
    
    // Configurar auto-save quando a página carregar
    configurarAutoSave();
    
    // Detectar navegação com botão voltar após logout
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
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
    
    window.onunload = function(){};
    
    // Função para atualizar o gráfico
    function atualizarGrafico() {
        let totalPago = 0;
        let totalVencido = 0;
        let totalAVencer = 0;

        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        for (let i = 1; i <= 24; i++) {
            const campoParcela = document.getElementById('parcela' + i);
            const campoData = document.getElementById('data_parcela' + i);
            const checkboxPaga = document.getElementById('paga' + i);

            if (!campoParcela || !campoParcela.value) {
                continue;
            }

            const valor = extrairValor(campoParcela);
            const dataVencimento = campoData && campoData.value ? new Date(campoData.value + 'T00:00:00') : null;

            if (checkboxPaga && checkboxPaga.checked) {
                totalPago += valor;
            } else if (dataVencimento && dataVencimento < hoje) {
                totalVencido += valor;
            } else {
                totalAVencer += valor;
            }
        }

        const total = totalPago + totalVencido + totalAVencer;
        
        // Criar ou atualizar gráfico
        const ctx = document.getElementById('graficoSaldo').getContext('2d');
        
        if (window.chartSaldo) {
            window.chartSaldo.destroy();
        }
        
        window.chartSaldo = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pagos', 'Vencidos', 'A vencer'],
                datasets: [{
                    data: [totalPago, totalVencido, totalAVencer],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',   // Verde para pagos
                        'rgba(220, 53, 69, 0.8)',   // Vermelho para vencidos
                        'rgba(255, 140, 0, 0.85)'   // Laranja para a vencer
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 140, 0, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 12,
                                family: 'Calibri'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': R$ ' + value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' (' + percentage + '%)';
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Visão Geral das Parcelas',
                        font: {
                            size: 16,
                            family: 'Calibri',
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
                        }
                    }
                }
            }
        });
    }
    
    // Atualizar gráfico quando houver mudanças nas parcelas
    document.querySelectorAll('.parcela-valor, .checkbox-paga, input[id^="data_parcela"]').forEach(function(element) {
        element.addEventListener('change', function() {
            setTimeout(atualizarGrafico, 100);
        });
    });
    
    // Atualizar gráfico ao carregar a página
    setTimeout(atualizarGrafico, 500);
    </script>
</body>
</html>





