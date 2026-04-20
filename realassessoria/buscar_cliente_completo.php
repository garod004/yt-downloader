<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/security_rls.php';

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

include 'conexao.php';

$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cliente_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit();
}

rls_enforce_cliente_or_die($conn, $cliente_id, true);

$sql = "SELECT * FROM clientes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cliente_id);

$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$cliente) {
    echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
    exit();
}

// Formatar dados
function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return '-';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data;
}

function formatarCPF($cpf) {
    if (empty($cpf)) return '-';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatarTelefone($telefone) {
    if (empty($telefone)) return '-';
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 1) . ' ' . substr($telefone, 3, 4) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ')' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

function formatarCEP($cep) {
    if (empty($cep)) return '-';
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) === 8) {
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }
    return $cep;
}

// Formatar os dados
$cliente['cpf'] = formatarCPF($cliente['cpf']);
$cliente['data_nascimento_formatada'] = formatarData($cliente['data_nascimento']);
$cliente['telefone'] = formatarTelefone($cliente['telefone']);
$cliente['telefone2'] = formatarTelefone($cliente['telefone2']);
$cliente['telefone3'] = !empty($cliente['telefone3']) ? formatarTelefone($cliente['telefone3']) : '-';
$cliente['cep'] = formatarCEP($cliente['cep']);
$cliente['data_contrato'] = formatarData($cliente['data_contrato']);
$cliente['data_avaliacao_social'] = formatarData($cliente['data_avaliacao_social']);
$cliente['data_pericia'] = formatarData($cliente['data_pericia']);

// Calcular idade automaticamente
$idade = '-';
if (!empty($cliente['data_nascimento']) && $cliente['data_nascimento'] !== '0000-00-00') {
    $nascimento = new DateTime($cliente['data_nascimento']);
    $hoje = new DateTime();
    $diff = $hoje->diff($nascimento);
    $idade = $diff->y . ' anos';
}
$cliente['idade'] = $idade;

// Formatar campos booleanos
$cliente['avaliacao_social_realizado'] = (!empty($cliente['avaliacao_social_realizado']) && $cliente['avaliacao_social_realizado'] == 1) ? 'Sim' : 'Não';
$cliente['pericia_realizado'] = (!empty($cliente['pericia_realizado']) && $cliente['pericia_realizado'] == 1) ? 'Sim' : 'Não';
$cliente['contrato_assinado'] = (!empty($cliente['contrato_assinado']) && $cliente['contrato_assinado'] == 1) ? 'Sim' : 'Não';

// Substituir valores vazios por "-"
foreach ($cliente as $key => $value) {
    if (empty($value) && $value !== '0' && $value !== 0) {
        $cliente[$key] = '-';
    }
}

echo json_encode(['success' => true, 'cliente' => $cliente]);
