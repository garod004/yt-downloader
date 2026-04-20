<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexao.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar se o usuario esta logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($cliente_id <= 0) {
    echo 'Cliente inválido.';
    exit();
}

rls_enforce_cliente_or_die($conn, $cliente_id, false);

// Funcoes de formatacao
function formatarCPF($cpf) {
    if (empty($cpf)) return '-';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return '-';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $data;
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

function formatarHora($hora) {
    if (empty($hora)) return '-';
    if (strlen($hora) >= 5) {
        return substr($hora, 0, 5);
    }
    return $hora;
}

function calcularIdade($data_nascimento) {
    if (empty($data_nascimento) || $data_nascimento === '0000-00-00') return '-';
    try {
        $nascimento = new DateTime($data_nascimento);
        $hoje = new DateTime();
        $diff = $hoje->diff($nascimento);
        return $diff->y . ' anos';
    } catch (Exception $e) {
        return '-';
    }
}

// Definir tipo de usuario e permissoes
$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'];
$is_parceiro = ($tipo_usuario === 'parceiro');

if ($is_parceiro) {
    $sql = "SELECT id, nome, cpf, data_nascimento, senha_meuinss, beneficio, situacao, indicador,
                endereco, cidade, telefone, email, rg, estado_civil, nacionalidade, profissao,
                data_contrato, data_avaliacao_social, data_pericia, observacao,
                data_enviado, responsavel, advogado, numero_processo, uf,
                telefone2, telefone3, senha_email, cep,
                hora_avaliacao_social, endereco_avaliacao_social, realizado_a_s,
                hora_pericia, endereco_pericia, realizado_pericia
            FROM clientes WHERE id = ? AND usuario_cadastro_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $cliente_id, $usuario_id);
} else {
    $sql = "SELECT id, nome, cpf, data_nascimento, senha_meuinss, beneficio, situacao, indicador,
                endereco, cidade, telefone, email, rg, estado_civil, nacionalidade, profissao,
                data_contrato, data_avaliacao_social, data_pericia, observacao,
                data_enviado, responsavel, advogado, numero_processo, uf,
                telefone2, telefone3, senha_email, cep,
                hora_avaliacao_social, endereco_avaliacao_social, realizado_a_s,
                hora_pericia, endereco_pericia, realizado_pericia
            FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $cliente_id);
}

$stmt->execute();
$result = stmt_get_result($stmt);
$cliente = $result->fetch_assoc();
$stmt->close();

if (!$cliente) {
    echo 'Cliente não encontrado.';
    $conn->close();
    exit();
}

// Buscar filhos menores
$filhos = [];
$sql_f = "SELECT nome, cpf, senha_gov, data_nascimento FROM filhos_menores WHERE cliente_id = ? ORDER BY data_nascimento DESC";
$stmt_f = $conn->prepare($sql_f);
$stmt_f->bind_param('i', $cliente_id);
$stmt_f->execute();
$result_f = stmt_get_result($stmt_f);
while ($row = $result_f->fetch_assoc()) {
    $filhos[] = $row;
}
$stmt_f->close();

$conn->close();

$avaliacao_social = !empty($cliente['realizado_a_s']) ? 'Realizada' : 'Não realizada';
$pericia_inss = !empty($cliente['realizado_pericia']) ? 'Realizada' : 'Não realizada';

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; }
        h1 { text-align: center; font-size: 18px; margin: 0 0 8px; }
        .sub { text-align: center; font-size: 9px; color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; vertical-align: top; }
        th { background: #f0f0f0; text-align: left; width: 28%; }
        .sec-title { font-weight: bold; margin: 12px 0 6px; font-size: 12px; }
        .filhos th { background: #4CAF50; color: #fff; }
        .rodape { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #666; padding: 6px 0; border-top: 1px solid #ddd; }
        .rodape .linha { display: block; }
    </style>
</head>
<body>
    <h1>RELATÓRIO DO CLIENTE</h1>
    <div class="sub">Gerado em: ' . date('d/m/Y H:i:s') . '</div>

    <table>
        <tr><th>CÓDIGO</th><td>' . htmlspecialchars($cliente['id'] ?? '-') . '</td></tr>
        <tr><th>DATA DO CONTRATO</th><td>' . formatarData($cliente['data_contrato'] ?? '') . '</td></tr>
        <tr><th>DATA DE ENVIADO</th><td>' . formatarData($cliente['data_enviado'] ?? '') . '</td></tr>
        <tr><th>RESPONSÁVEL</th><td>' . htmlspecialchars($cliente['responsavel'] ?? '-') . '</td></tr>
        <tr><th>ADVOGADO</th><td>' . htmlspecialchars($cliente['advogado'] ?? '-') . '</td></tr>
        <tr><th>BENEFÍCIO</th><td>' . htmlspecialchars($cliente['beneficio'] ?? '-') . '</td></tr>
        <tr><th>STATUS</th><td>' . htmlspecialchars($cliente['situacao'] ?? '-') . '</td></tr>
        <tr><th>Nº DO PROCESSO</th><td>' . htmlspecialchars($cliente['numero_processo'] ?? '-') . '</td></tr>
        <tr><th>NOME DO CLIENTE</th><td>' . htmlspecialchars($cliente['nome'] ?? '-') . '</td></tr>
        <tr><th>NACIONALIDADE</th><td>' . htmlspecialchars($cliente['nacionalidade'] ?? '-') . '</td></tr>
        <tr><th>ESTADO CIVIL</th><td>' . htmlspecialchars($cliente['estado_civil'] ?? '-') . '</td></tr>
        <tr><th>PROFISSÃO</th><td>' . htmlspecialchars($cliente['profissao'] ?? '-') . '</td></tr>
        <tr><th>IDENTIDADE</th><td>' . htmlspecialchars($cliente['rg'] ?? '-') . '</td></tr>
        <tr><th>NASCIMENTO</th><td>' . formatarData($cliente['data_nascimento'] ?? '') . '</td></tr>
        <tr><th>IDADE</th><td>' . htmlspecialchars(calcularIdade($cliente['data_nascimento'] ?? '')) . '</td></tr>
        <tr><th>CPF</th><td>' . formatarCPF($cliente['cpf'] ?? '') . '</td></tr>
        <tr><th>SENHA DO GOV</th><td>' . htmlspecialchars($cliente['senha_meuinss'] ?? '-') . '</td></tr>
        <tr><th>ENDEREÇO</th><td>' . htmlspecialchars($cliente['endereco'] ?? '-') . '</td></tr>
        <tr><th>CIDADE</th><td>' . htmlspecialchars($cliente['cidade'] ?? '-') . '</td></tr>
        <tr><th>ESTADO</th><td>' . htmlspecialchars($cliente['uf'] ?? '-') . '</td></tr>
        <tr><th>FONE DE CONTATO</th><td>' . formatarTelefone($cliente['telefone'] ?? '') . '</td></tr>
        <tr><th>E-MAIL</th><td>' . htmlspecialchars($cliente['email'] ?? '-') . '</td></tr>
        <tr><th>DATA DE AVALIAÇÃO SOCIAL</th><td>' . formatarData($cliente['data_avaliacao_social'] ?? '') . '</td></tr>
        <tr><th>HORA AVALIAÇÃO SOCIAL</th><td>' . formatarHora($cliente['hora_avaliacao_social'] ?? '') . '</td></tr>
        <tr><th>ENDEREÇO AVALIAÇÃO SOCIAL</th><td>' . htmlspecialchars($cliente['endereco_avaliacao_social'] ?? '-') . '</td></tr>
        <tr><th>AVALIAÇÃO SOCIAL</th><td>' . $avaliacao_social . '</td></tr>
        <tr><th>DATA PERÍCIA</th><td>' . formatarData($cliente['data_pericia'] ?? '') . '</td></tr>
        <tr><th>HORA PERÍCIA</th><td>' . formatarHora($cliente['hora_pericia'] ?? '') . '</td></tr>
        <tr><th>ENDEREÇO PERÍCIA</th><td>' . htmlspecialchars($cliente['endereco_pericia'] ?? '-') . '</td></tr>
        <tr><th>PERÍCIA INSS</th><td>' . $pericia_inss . '</td></tr>
    </table>

    <div class="sec-title">FILHOS MENORES</div>
    <table class="filhos">
        <thead>
            <tr>
                <th>NOME</th>
                <th>CPF</th>
                <th>SENHA DO GOV</th>
                <th>NASCIMENTO</th>
                <th>IDADE</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($filhos)) {
    foreach ($filhos as $filho) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($filho['nome'] ?? '-') . '</td>';
        $html .= '<td>' . formatarCPF($filho['cpf'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($filho['senha_gov'] ?? '-') . '</td>';
        $html .= '<td>' . formatarData($filho['data_nascimento'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars(calcularIdade($filho['data_nascimento'] ?? '')) . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="5" style="text-align:center;">Nenhum filho menor cadastrado.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="rodape">
        <span class="linha">Real Assessoria Previdenciária</span>
        <span class="linha">Rua M, Nº 65, Nova Cidade, Manaus-AM | Whatsapp: (92) 99129-0577 | Instagram: @DiolenoNS</span>
        <span class="linha">www.realassessoria.com.br</span>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'RELATÓRIO DO CLIENTE.pdf';
$dompdf->stream($filename, array('Attachment' => false));
?>



