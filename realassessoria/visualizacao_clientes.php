<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.html');
    exit();
}

$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = $_SESSION['usuario_id'];
$is_admin = ($tipo_usuario === 'admin' || (!empty($_SESSION['is_admin']) && (int) $_SESSION['is_admin'] === 1));
$is_parceiro = ($tipo_usuario === 'parceiro');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/beneficio_utils.php';

$db_indisponivel = (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error);
$db_erro_tecnico = isset($db_connection_error) ? $db_connection_error : ((isset($conn) && $conn instanceof mysqli && $conn->connect_error) ? $conn->connect_error : '');
$conn_visual = ($conn instanceof mysqli) ? $conn : null;

function stmtFetchAllAssocVisual($stmt) {
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

function tabelaExisteVisual($conn, $tabela) {
    $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tabela);
    $ok = $stmt->execute();
    if (!$ok) {
        $stmt->close();
        return false;
    }

    list($fetchOk, $rows) = stmtFetchAllAssocVisual($stmt);
    $stmt->close();
    return $fetchOk && !empty($rows);
}

function carregarUltimoVinculoVisual($conn, $tabela, $clienteIds, $colunasSql, $ordemSql) {
    if (empty($clienteIds) || !tabelaExisteVisual($conn, $tabela)) {
        return array();
    }

    $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));
    $sql = "SELECT cliente_id, {$colunasSql} FROM {$tabela} WHERE cliente_id IN ({$placeholders}) ORDER BY cliente_id ASC, {$ordemSql}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return array();
    }

    $types = str_repeat('i', count($clienteIds));
    $bindNames = array();
    $bindNames[] = &$types;
    foreach ($clienteIds as $index => $clienteId) {
        $bindNames[] = &$clienteIds[$index];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindNames);

    $mapa = array();
    if ($stmt->execute()) {
        list($ok, $rows) = stmtFetchAllAssocVisual($stmt);
        if ($ok) {
            foreach ($rows as $row) {
                $clienteId = (int) ($row['cliente_id'] ?? 0);
                if ($clienteId > 0 && !isset($mapa[$clienteId])) {
                    $mapa[$clienteId] = $row;
                }
            }
        }
    }

    $stmt->close();
    return $mapa;
}

function valorFiltroVisual($chave) {
    return isset($_GET[$chave]) ? trim((string) $_GET[$chave]) : '';
}

function textoVisual($valor, $padrao = '-') {
    if ($valor === null) {
        return $padrao;
    }

    $texto = trim((string) $valor);
    if ($texto === '' || $texto === '0000-00-00') {
        return $padrao;
    }

    return $texto;
}

function formatarDataVisualPhp($data) {
    $texto = textoVisual($data, '-');
    if ($texto === '-') {
        return $texto;
    }

    $partes = explode('-', $texto);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }

    return $texto;
}

function formatarCpfVisualPhp($cpf) {
    $texto = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($texto) === 11) {
        return substr($texto, 0, 3) . '.' . substr($texto, 3, 3) . '.' . substr($texto, 6, 3) . '-' . substr($texto, 9, 2);
    }
    return textoVisual($cpf, '-');
}

function formatarTelefoneVisualPhp($telefone) {
    $texto = preg_replace('/\D/', '', (string) $telefone);
    if (strlen($texto) === 11) {
        return '(' . substr($texto, 0, 2) . ') ' . substr($texto, 2, 1) . ' ' . substr($texto, 3, 4) . '-' . substr($texto, 7, 4);
    }
    if (strlen($texto) === 10) {
        return '(' . substr($texto, 0, 2) . ') ' . substr($texto, 2, 4) . '-' . substr($texto, 6, 4);
    }
    return textoVisual($telefone, '-');
}

function calcularIdadeVisualPhp($dataNascimento) {
    $texto = textoVisual($dataNascimento, '');
    if ($texto === '') {
        return '-';
    }

    try {
        $nascimento = new DateTime($texto);
        $hoje = new DateTime();
        return (string) $hoje->diff($nascimento)->y;
    } catch (Exception $e) {
        return '-';
    }
}

function normalizarStatusVisualPhp($status) {
    $texto = trim(mb_strtolower((string) $status, 'UTF-8'));
    $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if ($semAcento === false) {
        return $texto;
    }
    return strtolower($semAcento);
}

$filtro_nome = valorFiltroVisual('filtro_nome');
$filtro_beneficio = valorFiltroVisual('filtro_beneficio');
$filtro_status = valorFiltroVisual('filtro_status');
$filtro_indicador = valorFiltroVisual('filtro_indicador');
$filtro_numero_processo = valorFiltroVisual('filtro_numero_processo');
$cpf_search = valorFiltroVisual('cpf_search');
$tem_filtros_ativos = ($filtro_nome !== '' || $filtro_beneficio !== '' || $filtro_status !== '' || $filtro_indicador !== '' || $filtro_numero_processo !== '' || $cpf_search !== '');
$selected_id = (int) (isset($_GET['selected_id']) ? $_GET['selected_id'] : 0);

$clientes = array();
$erro_listagem = '';

if (!$db_indisponivel && $conn_visual !== null) {
    $sql = "SELECT id, nome, cpf, data_nascimento, senha_meuinss, beneficio, situacao, indicador,
                   endereco, cidade, telefone, email, rg, estado_civil, nacionalidade, profissao,
                   data_contrato, data_avaliacao_social, data_pericia, observacao,
                   data_enviado, responsavel, advogado, numero_processo, uf,
                   telefone2, telefone3, senha_email, cep,
                   hora_avaliacao_social, endereco_avaliacao_social, realizado_a_s,
                   hora_pericia, endereco_pericia, realizado_pericia, contrato_assinado
            FROM clientes
            WHERE 1=1";
    $types = '';
    $params = array();

    if ($is_parceiro) {
        $sql .= ' AND usuario_cadastro_id = ?';
        $types .= 'i';
        $params[] = $usuario_id;
    }

    if ($cpf_search !== '') {
        $cpf_norm = preg_replace('/\D/', '', $cpf_search);
        $sql .= " AND REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') LIKE ?";
        $types .= 's';
        $params[] = '%' . $cpf_norm . '%';
    }

    if ($filtro_nome !== '') {
        $sql .= ' AND nome LIKE ?';
        $types .= 's';
        $params[] = '%' . $filtro_nome . '%';
    }

    if ($filtro_beneficio !== '') {
        beneficio_aplicar_filtro($sql, $types, $params, $filtro_beneficio, 'beneficio');
    }

    if ($filtro_status !== '') {
        $sql .= ' AND situacao = ?';
        $types .= 's';
        $params[] = $filtro_status;
    }

    if ($filtro_indicador !== '') {
        $sql .= ' AND indicador LIKE ?';
        $types .= 's';
        $params[] = '%' . $filtro_indicador . '%';
    }

    if ($filtro_numero_processo !== '') {
        $sql .= ' AND numero_processo LIKE ?';
        $types .= 's';
        $params[] = '%' . $filtro_numero_processo . '%';
    }

    $sql .= ' ORDER BY nome ASC';

    $stmt = $conn_visual->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $bind_names = array();
            $bind_names[] = &$types;
            for ($index = 0; $index < count($params); $index++) {
                $bind_names[] = &$params[$index];
            }
            call_user_func_array(array($stmt, 'bind_param'), $bind_names);
        }

        if ($stmt->execute()) {
            list($ok, $rows, $fetchError) = stmtFetchAllAssocVisual($stmt);
            if ($ok) {
                $clientes = $rows;
            } else {
                $erro_listagem = $fetchError;
            }
        } else {
            $erro_listagem = 'Erro ao executar a consulta de clientes.';
        }
        $stmt->close();
    } else {
        $erro_listagem = 'Erro ao preparar a consulta de clientes.';
    }
}

$total = count($clientes);
$aprovado = 0;
$pagando = 0;
$concluso_sem_decisao = 0;

foreach ($clientes as $clienteItem) {
    $statusNormalizado = normalizarStatusVisualPhp($clienteItem['situacao'] ?? '');
    if ($statusNormalizado === 'aprovado') {
        $aprovado++;
    }
    if (strpos($statusNormalizado, 'pagando') !== false) {
        $pagando++;
    }
    if ($statusNormalizado === 'concluido sem decisao' || $statusNormalizado === 'concluso sem decisao') {
        $concluso_sem_decisao++;
    }
}

$selectedCliente = null;
if ($selected_id > 0) {
    foreach ($clientes as $clienteItem) {
        if ((int) $clienteItem['id'] === $selected_id) {
            $selectedCliente = $clienteItem;
            break;
        }
    }
}
if ($selectedCliente === null && !empty($clientes)) {
    $selectedCliente = $clientes[0];
    $selected_id = (int) $selectedCliente['id'];
}

if (!$db_indisponivel && $conn_visual !== null && !empty($clientes)) {
    $clienteIds = array();
    foreach ($clientes as $clienteItem) {
        $clienteIds[] = (int) $clienteItem['id'];
    }

    $filhosMenores = carregarUltimoVinculoVisual(
        $conn_visual,
        'filhos_menores',
        $clienteIds,
        'nome, cpf, senha_gov, data_nascimento',
        'data_nascimento DESC, id DESC'
    );
    $incapazes = carregarUltimoVinculoVisual(
        $conn_visual,
        'incapazes',
        $clienteIds,
        'nome, cpf, senha_gov, data_nascimento',
        'updated_at DESC, id DESC'
    );
    $aRogo = carregarUltimoVinculoVisual(
        $conn_visual,
        'a_rogo',
        $clienteIds,
        'nome, identidade, cpf',
        'updated_at DESC, id DESC'
    );

    foreach ($clientes as &$clienteItem) {
        $clienteId = (int) ($clienteItem['id'] ?? 0);
        $clienteItem['vinculo_relacionado'] = null;

        if (isset($filhosMenores[$clienteId])) {
            $clienteItem['vinculo_relacionado'] = array(
                'tipo' => 'Filho menor',
                'nome' => textoVisual($filhosMenores[$clienteId]['nome'] ?? null),
                'cpf' => textoVisual($filhosMenores[$clienteId]['cpf'] ?? null),
                'senha_gov' => textoVisual($filhosMenores[$clienteId]['senha_gov'] ?? null),
                'data_nascimento' => textoVisual($filhosMenores[$clienteId]['data_nascimento'] ?? null),
            );
            continue;
        }

        if (isset($incapazes[$clienteId])) {
            $clienteItem['vinculo_relacionado'] = array(
                'tipo' => 'Incapaz',
                'nome' => textoVisual($incapazes[$clienteId]['nome'] ?? null),
                'cpf' => textoVisual($incapazes[$clienteId]['cpf'] ?? null),
                'senha_gov' => textoVisual($incapazes[$clienteId]['senha_gov'] ?? null),
                'data_nascimento' => textoVisual($incapazes[$clienteId]['data_nascimento'] ?? null),
            );
            continue;
        }

        if (isset($aRogo[$clienteId])) {
            $clienteItem['vinculo_relacionado'] = array(
                'tipo' => 'A rogo',
                'nome' => textoVisual($aRogo[$clienteId]['nome'] ?? null),
                'cpf' => textoVisual($aRogo[$clienteId]['cpf'] ?? null),
                'identidade' => textoVisual($aRogo[$clienteId]['identidade'] ?? null),
            );
        }
    }
    unset($clienteItem);
}

$clientes_json = json_encode($clientes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$beneficios_ordem_fixa = array(
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
);
$status_ordem_fixa = array(
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
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualização de Clientes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <style>
        :root {
            --bg-ink: #07111b;
            --bg-deep: #0d1b29;
            --bg-shell: rgba(12, 24, 37, 0.88);
            --bg-panel: rgba(19, 34, 51, 0.94);
            --bg-panel-soft: rgba(25, 44, 66, 0.92);
            --bg-panel-muted: rgba(255, 255, 255, 0.04);
            --stroke: rgba(191, 219, 254, 0.12);
            --stroke-strong: rgba(163, 230, 255, 0.22);
            --text: #f7fafc;
            --muted: rgba(222, 234, 246, 0.72);
            --muted-strong: rgba(222, 234, 246, 0.88);
            --mint: linear-gradient(135deg, #dff7d0 0%, #bdeebf 100%);
            --blue: linear-gradient(135deg, #d9effc 0%, #c0def4 100%);
            --pink: linear-gradient(135deg, #f3d8ef 0%, #e9c7e6 100%);
            --peach: linear-gradient(135deg, #fae5d3 0%, #f6d7be 100%);
            --teal: #78d4e8;
            --gold: #f5d78f;
            --rose: #f2a6c5;
            --shadow: 0 24px 60px rgba(0, 0, 0, 0.32);
            --shadow-soft: 0 12px 30px rgba(0, 0, 0, 0.2);
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: 'Manrope', 'Segoe UI', Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(120, 212, 232, 0.12), transparent 26%),
                radial-gradient(circle at bottom right, rgba(245, 215, 143, 0.1), transparent 22%),
                linear-gradient(180deg, #050b12 0%, #0a1622 100%);
            color: var(--text);
        }

        body {
            min-height: 100vh;
        }

        .page {
            display: block;
            min-height: 100vh;
            padding: 18px;
        }

        .workspace {
            min-width: 0;
        }

        .canvas {
            background:
                linear-gradient(180deg, rgba(11, 20, 31, 0.96) 0%, rgba(8, 16, 24, 0.98) 100%);
            border: 1px solid var(--stroke);
            border-radius: 32px;
            box-shadow: var(--shadow);
            min-height: calc(100vh - 36px);
            padding: 18px 22px 20px;
            position: relative;
            overflow: hidden;
        }

        .canvas::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 12% 10%, rgba(120, 212, 232, 0.08), transparent 20%),
                radial-gradient(circle at 88% 16%, rgba(242, 166, 197, 0.08), transparent 18%);
            pointer-events: none;
        }

        .page-actions {
            display: flex;
            justify-content: flex-start;
            margin: 0 0 12px;
            position: relative;
            z-index: 1;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }

        .back-button:hover {
            transform: translateY(-1px);
            background: rgba(120, 212, 232, 0.1);
            border-color: rgba(120, 212, 232, 0.22);
        }

        .kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin: 0 0 16px;
            position: relative;
            z-index: 1;
        }

        .kpi {
            border-radius: 22px;
            padding: 18px 20px;
            color: #0f1720;
            font-size: 14px;
            font-weight: 600;
            min-height: 86px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.14);
        }

        .kpi span:first-child {
            opacity: 0.8;
        }

        .kpi span:last-child {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }

        .kpi.mint { background: var(--mint); }
        .kpi.blue { background: var(--blue); }
        .kpi.pink { background: var(--pink); }
        .kpi.peach { background: var(--peach); }

        .main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.18fr) minmax(280px, 0.82fr);
            gap: 20px;
            position: relative;
            z-index: 1;
            align-items: start;
        }

        .panel {
            background: linear-gradient(180deg, rgba(19, 34, 51, 0.98) 0%, rgba(15, 28, 43, 0.98) 100%);
            border: 1px solid var(--stroke);
            border-radius: 26px;
            min-height: 120px;
            padding: 22px;
            box-shadow: var(--shadow-soft);
        }

        .panel.large {
            min-height: auto;
        }

        .panel.list {
            min-height: auto;
            display: flex;
            flex-direction: column;
            padding: 18px;
        }

        .panel.filters {
            min-height: auto;
            padding: 10px 12px;
        }

        .detail-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding-bottom: 18px;
            margin-bottom: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .detail-eyebrow {
            display: inline-flex;
            margin-bottom: 8px;
            color: var(--teal);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .detail-title {
            margin: 0;
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 42px;
            line-height: 0.96;
            letter-spacing: 0.2px;
        }

        .detail-meta {
            display: grid;
            gap: 6px;
            margin: 12px 0 0;
        }

        .detail-meta-row {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.45;
        }

        .detail-meta-row strong {
            color: var(--muted-strong);
        }

        .detail-related-card {
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .detail-related-title {
            margin: 0 0 10px;
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 32px;
            line-height: 0.98;
            letter-spacing: 0.2px;
            color: #ffffff;
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .panel-heading h2,
        .panel-heading h3 {
            margin: 0;
            font-size: 14px;
            color: var(--muted-strong);
            letter-spacing: 0.9px;
            text-transform: uppercase;
        }

        .panel-heading span {
            color: var(--muted);
            font-size: 12px;
        }

        .detail-stack {
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .side-stack {
            display: grid;
            gap: 12px;
            position: sticky;
            top: 0;
            align-self: start;
        }

        .filter-collapse {
            display: grid;
            gap: 8px;
        }

        .filter-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            list-style: none;
            cursor: pointer;
            color: var(--muted-strong);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .filter-toggle::-webkit-details-marker {
            display: none;
        }

        .filter-toggle::after {
            content: '+';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            color: #ffffff;
            font-size: 14px;
            line-height: 1;
            text-transform: none;
        }

        .filter-collapse[open] .filter-toggle::after {
            content: '-';
        }

        .filter-summary-text {
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: none;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px 8px;
        }

        .filter-group {
            display: grid;
            gap: 4px;
        }

        .filter-group label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            font-weight: 700;
            line-height: 1.1;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font: inherit;
            font-size: 12px;
            min-height: 34px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: rgba(120, 212, 232, 0.28);
            box-shadow: 0 0 0 3px rgba(120, 212, 232, 0.08);
        }

        .filter-group option {
            color: #081720;
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 0;
            grid-column: 1 / -1;
        }

        .filter-actions button,
        .filter-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            padding: 6px 10px;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }

        .filter-actions button:hover,
        .filter-actions a:hover {
            transform: translateY(-1px);
            background: rgba(120, 212, 232, 0.1);
            border-color: rgba(120, 212, 232, 0.22);
        }

        .list-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .list-head h3 {
            margin: 0;
            font-size: 14px;
            color: var(--muted-strong);
            text-transform: uppercase;
            letter-spacing: 0.9px;
        }

        .list-head span {
            color: var(--muted);
            font-size: 12px;
        }

        .client-list {
            display: grid;
            gap: 0;
            max-height: 520px;
            overflow: auto;
            padding-right: 0;
        }

        .client-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            border: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: transparent;
            color: #ffffff;
            text-align: left;
            padding: 12px 0;
            font: inherit;
            border-radius: 0;
            cursor: pointer;
            transition: background 0.18s ease;
        }

        .client-list .client-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .client-item:hover {
            background: rgba(120, 212, 232, 0.05);
            border-radius: 18px;
            padding-left: 14px;
            padding-right: 14px;
        }

        .client-item.active {
            color: #dff3ff;
            background: rgba(120, 212, 232, 0.08);
            border-radius: 18px;
            padding-left: 14px;
            padding-right: 14px;
        }

        .client-item-main {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .client-item-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 15px;
            font-weight: 700;
        }

        .client-item-meta {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--muted);
            font-size: 12px;
        }

        .client-item-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.07);
            font-size: 11px;
            color: rgba(255, 255, 255, 0.82);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .list-empty {
            padding: 18px 0 4px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .empty-state,
        .error-state {
            background: var(--bg-panel);
            border: 1px solid var(--stroke);
            border-radius: 24px;
            padding: 28px;
            font-size: 15px;
            line-height: 1.4;
            position: relative;
            z-index: 1;
        }

        .error-state {
            color: #ffd7d7;
        }

        @media screen and (max-width: 1024px) {
            .page {
                padding: 14px;
            }

            .canvas {
                min-height: auto;
            }

            .kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main-grid {
                grid-template-columns: 1fr;
            }

            .side-stack {
                position: static;
            }

            .filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .client-list {
                max-height: 360px;
            }
        }

        @media screen and (max-width: 640px) {
            .canvas {
                padding: 16px;
            }

            .kpis {
                grid-template-columns: 1fr;
            }

            .detail-hero {
                grid-template-columns: 1fr;
            }

            .detail-title {
                font-size: 34px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
            }

            .panel.large,
            .panel.list {
                min-height: auto;
            }

            .client-list {
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <main class="workspace">
            <div class="canvas">
                <div class="page-actions">
                    <a class="back-button" href="dashboard.php">Voltar</a>
                </div>

                <div class="kpis">
                    <div class="kpi mint"><span>Total na listagem</span><span><?php echo (int) $total; ?></span></div>
                    <div class="kpi blue"><span>Aprovado</span><span><?php echo (int) $aprovado; ?></span></div>
                    <div class="kpi pink"><span>Status Pagando</span><span><?php echo (int) $pagando; ?></span></div>
                    <div class="kpi peach"><span>Concluso sem decisão</span><span><?php echo (int) $concluso_sem_decisao; ?></span></div>
                </div>

                <?php if ($db_indisponivel): ?>
                    <div class="error-state">
                        Falha na conexão com o banco de dados.<br>
                        <?php echo htmlspecialchars($db_erro_tecnico !== '' ? $db_erro_tecnico : 'Verifique o MySQL e as credenciais da aplicação.', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php elseif ($erro_listagem !== ''): ?>
                    <div class="error-state">
                        <?php echo htmlspecialchars($erro_listagem, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php elseif (empty($clientes)): ?>
                    <div class="empty-state">
                        Nenhum cliente encontrado para os filtros atuais.
                    </div>
                <?php else: ?>
                    <div class="main-grid">
                        <div class="detail-stack">
                            <section class="panel large" id="panelDetalhes"></section>

                            <section class="panel filters">
                                <details class="filter-collapse" <?php echo $tem_filtros_ativos ? 'open' : ''; ?>>
                                    <summary class="filter-toggle">
                                        <span>Filtros</span>
                                        <span class="filter-summary-text"><?php echo $tem_filtros_ativos ? 'ativos' : 'mostrar'; ?></span>
                                    </summary>
                                    <form class="filter-form" method="get" action="visualizacao_clientes.php">
                                        <input type="hidden" id="selected_id" name="selected_id" value="<?php echo (int) $selected_id; ?>">
                                        <div class="filter-group">
                                            <label for="filtro_status">Status</label>
                                            <select id="filtro_status" name="filtro_status">
                                                <option value="">Todos</option>
                                                <?php foreach ($status_ordem_fixa as $status): ?>
                                                    <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtro_status === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label for="filtro_beneficio">Benefício</label>
                                            <select id="filtro_beneficio" name="filtro_beneficio">
                                                <option value="">Todos</option>
                                                <?php foreach ($beneficios_ordem_fixa as $beneficio): ?>
                                                    <option value="<?php echo htmlspecialchars($beneficio, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtro_beneficio === $beneficio ? 'selected' : ''; ?>><?php echo htmlspecialchars($beneficio, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label for="cpf_search">CPF</label>
                                            <input id="cpf_search" name="cpf_search" type="text" value="<?php echo htmlspecialchars($cpf_search, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="filter-group">
                                            <label for="filtro_nome">Nome</label>
                                            <input id="filtro_nome" name="filtro_nome" type="text" value="<?php echo htmlspecialchars($filtro_nome, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="filter-group">
                                            <label for="filtro_numero_processo">Nº Processo</label>
                                            <input id="filtro_numero_processo" name="filtro_numero_processo" type="text" value="<?php echo htmlspecialchars($filtro_numero_processo, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="filter-group">
                                            <label for="filtro_indicador">Indicador</label>
                                            <input id="filtro_indicador" name="filtro_indicador" type="text" value="<?php echo htmlspecialchars($filtro_indicador, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="filter-actions">
                                            <button type="submit">Aplicar</button>
                                            <a href="visualizacao_clientes.php">Limpar</a>
                                        </div>
                                    </form>
                                </details>
                            </section>
                        </div>

                        <div class="side-stack">
                            <section class="panel list">
                                <div class="list-head">
                                    <h3>Clientes na seleção</h3>
                                    <span id="listCount"><?php echo (int) $total; ?> registros</span>
                                </div>
                                <div class="client-list" id="clientList"></div>
                            </section>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php if (!$db_indisponivel && $erro_listagem === '' && !empty($clientes)): ?>
    <script>
        const clientes = <?php echo $clientes_json ? $clientes_json : '[]'; ?>;
        let clienteSelecionadoId = <?php echo (int) $selected_id; ?>;

        if (!clientes.some(function (cliente) {
            return Number(cliente.id) === Number(clienteSelecionadoId);
        }) && clientes.length) {
            clienteSelecionadoId = Number(clientes[0].id);
        }

        function textoOuPadrao(valor, padrao = '-') {
            const texto = (valor == null ? '' : String(valor)).trim();
            return texto === '' || texto === '0000-00-00' ? padrao : texto;
        }

        function escapeHtml(valor) {
            return String(valor == null ? '' : valor)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatarData(valor) {
            const texto = textoOuPadrao(valor, '-');
            if (texto === '-') {
                return texto;
            }
            const match = texto.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (match) {
                return match[3] + '/' + match[2] + '/' + match[1];
            }
            return texto;
        }

        function formatarCpf(valor) {
            const digits = String(valor || '').replace(/\D/g, '');
            if (digits.length === 11) {
                return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
            return textoOuPadrao(valor, '-');
        }

        function calcularIdade(valor) {
            const texto = textoOuPadrao(valor, '');
            if (!texto) {
                return '-';
            }

            const nascimento = new Date(texto + 'T00:00:00');
            if (Number.isNaN(nascimento.getTime())) {
                return '-';
            }

            const hoje = new Date();
            let idade = hoje.getFullYear() - nascimento.getFullYear();
            const mes = hoje.getMonth() - nascimento.getMonth();
            if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                idade--;
            }

            return idade >= 0 ? String(idade) : '-';
        }

        function normalizar(valor) {
            return String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        }

        function obterContextoAtual() {
            const filtrados = clientes.slice();
            let cliente = filtrados.find(function (item) {
                return Number(item.id) === Number(clienteSelecionadoId);
            }) || null;

            if (!cliente && filtrados.length) {
                cliente = filtrados[0];
                clienteSelecionadoId = Number(cliente.id);
            }

            return {
                filtrados: filtrados,
                cliente: cliente,
                indice: cliente ? filtrados.findIndex(function (item) {
                    return Number(item.id) === Number(cliente.id);
                }) : -1
            };
        }

        function renderDetalhes(cliente, contexto) {
            const meta = [
                ['CPF', formatarCpf(cliente.cpf)],
                ['Senha Gov', textoOuPadrao(cliente.senha_meuinss)],
                ['Status', textoOuPadrao(cliente.situacao)],
                ['Benefício', textoOuPadrao(cliente.beneficio)],
                ['Nº Processo', textoOuPadrao(cliente.numero_processo)]
            ];

            let vinculoHtml = '';
            if (cliente.vinculo_relacionado) {
                const vinculo = cliente.vinculo_relacionado;
                const metaVinculo = [];

                metaVinculo.push(['CPF', formatarCpf(vinculo.cpf)]);

                if (vinculo.tipo === 'A rogo') {
                    metaVinculo.push(['Identidade', textoOuPadrao(vinculo.identidade)]);
                } else {
                    metaVinculo.push(['Senha Gov', textoOuPadrao(vinculo.senha_gov)]);
                    metaVinculo.push(['Nascimento', formatarData(vinculo.data_nascimento)]);
                    metaVinculo.push(['Idade', calcularIdade(vinculo.data_nascimento)]);
                }

                vinculoHtml = '' +
                    '<div class="detail-related-card">' +
                        '<span class="detail-eyebrow">' + escapeHtml(vinculo.tipo) + '</span>' +
                        '<h3 class="detail-related-title">' + escapeHtml(textoOuPadrao(vinculo.nome, 'Sem nome')) + '</h3>' +
                        '<div class="detail-meta">' + metaVinculo.map(function (item) {
                            return '<div class="detail-meta-row"><strong>' + escapeHtml(item[0]) + ':</strong> ' + escapeHtml(item[1]) + '</div>';
                        }).join('') + '</div>' +
                    '</div>';
            }

            const html = '' +
                '<div class="detail-hero">' +
                    '<div>' +
                        '<span class="detail-eyebrow">Cliente selecionado</span>' +
                        '<h2 class="detail-title">' + escapeHtml(textoOuPadrao(cliente.nome, 'Sem nome')) + '</h2>' +
                        '<div class="detail-meta">' + meta.map(function (item) {
                            return '<div class="detail-meta-row"><strong>' + escapeHtml(item[0]) + ':</strong> ' + escapeHtml(item[1]) + '</div>';
                        }).join('') + '</div>' +
                    '</div>' +
                '</div>' +
                vinculoHtml;

            document.getElementById('panelDetalhes').innerHTML = html;
        }

        function renderLista(contexto) {
            const list = document.getElementById('clientList');
            list.innerHTML = contexto.filtrados.map(function (cliente) {
                const active = Number(cliente.id) === Number(clienteSelecionadoId) ? ' active' : '';
                return '' +
                    '<div class="client-item' + active + '" data-id="' + escapeHtml(String(cliente.id)) + '" role="button" tabindex="0">' +
                        '<span class="client-item-main">' +
                            '<span class="client-item-name">' + escapeHtml(textoOuPadrao(cliente.nome)) + '</span>' +
                            '<span class="client-item-meta">' + escapeHtml(formatarCpf(cliente.cpf)) + '</span>' +
                        '</span>' +
                        '<span class="client-item-tag">' + escapeHtml(textoOuPadrao(cliente.situacao)) + '</span>' +
                    '</div>';
            }).join('');

            Array.prototype.forEach.call(list.querySelectorAll('.client-item'), function (item) {
                function selecionarCliente() {
                    clienteSelecionadoId = Number(item.getAttribute('data-id'));
                    const campoSelecionado = document.getElementById('selected_id');
                    if (campoSelecionado) {
                        campoSelecionado.value = String(clienteSelecionadoId);
                    }
                    atualizarTela();
                }

                item.addEventListener('click', selecionarCliente);
                item.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selecionarCliente();
                    }
                });
            });
        }

        function atualizarTela() {
            if (!clientes.length) {
                return;
            }

            const contexto = obterContextoAtual();

            document.getElementById('listCount').textContent = contexto.filtrados.length + ' registros';

            if (!contexto.cliente) {
                document.getElementById('panelDetalhes').innerHTML = '<div class="empty-state">Nenhum cliente disponível para visualização.</div>';
                renderLista(contexto);
                return;
            }

            renderDetalhes(contexto.cliente, contexto);
            renderLista(contexto);
        }

        atualizarTela();
    </script>
    <?php endif; ?>
</body>
</html>
