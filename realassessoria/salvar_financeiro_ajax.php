<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Definir tipo de usuário e permissões
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_logado_id = $_SESSION['usuario_id'];

// USUARIO não tem acesso ao financeiro
if ($tipo_usuario === 'usuario') {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit();
}

include 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit();
}

$cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;

if ($cliente_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit();
}

rls_enforce_cliente_or_die($conn, $cliente_id, true);

// Função para converter valor monetário em float
function converterMoeda($valor) {
    if (empty($valor)) return 0;
    
    $valor = str_replace('R$', '', $valor);
    $valor = str_replace(' ', '', $valor);
    $valor = trim($valor);
    
    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }
    
    return floatval($valor);
}

$status = $_POST['status'] ?? '';
$data_contrato = $_POST['data_contrato'] ?? null;
$data_aprovado = $_POST['data_aprovado'] ?? null;
$data_vencimento = $_POST['data_vencimento'] ?? null;

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
    $valor_parcela_i = $_POST['parcela'.$i] ?? '';
    if (!empty($valor_parcela_i)) {
        ${'parcela'.$i} = converterMoeda($valor_parcela_i);
    } else {
        ${'parcela'.$i} = null;
    }
    ${'data_parcela'.$i} = !empty($_POST['data_parcela'.$i]) ? $_POST['data_parcela'.$i] : null;
    ${'paga'.$i} = isset($_POST['paga'.$i]) ? 1 : 0;
}

// Verificar se já existe registro financeiro
$sql_check = "SELECT id FROM financeiro WHERE cliente_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $cliente_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$exists = $result_check->fetch_assoc();
$stmt_check->close();

if ($exists) {
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
                   qtd_parcelas, valor_parcela, parcelas_pagas, parcelas_faltantes, 
                   retroativo, percentual_retroativo, saldo_retroativo, honorarios_bruto, 
                   honorarios_parceiro, honorarios_advogado, honorarios_liquido, saldo_negativo, 
                   pago, parcela1, data_parcela1, paga1, parcela2, data_parcela2, paga2, parcela3, data_parcela3, paga3, 
                   parcela4, data_parcela4, paga4, parcela5, data_parcela5, paga5, parcela6, data_parcela6, paga6, 
                   parcela7, data_parcela7, paga7, parcela8, data_parcela8, paga8, parcela9, data_parcela9, paga9, 
                   parcela10, data_parcela10, paga10, parcela11, data_parcela11, paga11, parcela12, data_parcela12, paga12, 
                   parcela13, data_parcela13, paga13, parcela14, data_parcela14, paga14, parcela15, data_parcela15, paga15, 
                   parcela16, data_parcela16, paga16, parcela17, data_parcela17, paga17, parcela18, data_parcela18, paga18, 
                   parcela19, data_parcela19, paga19, parcela20, data_parcela20, paga20, parcela21, data_parcela21, paga21, 
                   parcela22, data_parcela22, paga22, parcela23, data_parcela23, paga23, parcela24, data_parcela24, paga24)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_save = $conn->prepare($sql_insert);
    $stmt_save->bind_param("issssdddddddddddddssissississississississississississississississississississississississi", 
                    $cliente_id, $status, $data_contrato, $data_aprovado, $data_vencimento,
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
                            $parcela22, $data_parcela22, $paga22, $parcela23, $data_parcela23, $paga23, $parcela24, $data_parcela24, $paga24);
}

if ($stmt_save->execute()) {
    echo json_encode(['success' => true, 'message' => 'Salvo com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
}

$stmt_save->close();
$conn->close();
