<?php
require_once __DIR__ . '/mvp_utils.php';

$mensagem = '';
$erro = '';
$cliente_filtro_id = intval($_GET['cliente_id'] ?? 0);
$cliente_id_form = $cliente_filtro_id;
$processo_id_form = intval($_GET['editar'] ?? 0);

$status_padrao = array(
    'ENVIADO',
    'NEGADO',
    'APROVADO',
    'PAGO',
    'PERICIA',
    'JUSTICA',
    'AVALIACAO SOCIAL',
    'INDEFERIDO',
    'DEFERIDO',
    'ESCRITORIO',
    'PENDENCIA',
    'CANCELADO',
    'FALTA A SENHA DO MEUINSS',
    'ESPERANDO DATA CERTA',
    'FALTA ASSINAR CONTRATO',
    'CLIENTE NAO PAGOU O ESCRITORIO',
    'BAIXA DEFINITIVA',
    'CADASTRO DE BIOMETRIA',
    'CONCLUIDO SEM DECISAO',
    'REENVIAR',
    'PAGANDO',
    'ATENDIMENTO',
    'A CRIANCA AINDA NAO NASCEU'
);

$beneficios_padrao = array(
    'Aposentadoria Agricultor',
    'Aposentadoria Pescador',
    'Aposentadoria Indígena',
    'Aposentadoria por Tempo de Contribuição Urbano',
    'Aposentadoria Especial Urbano',
    'Aposentadoria por Invalidez Urbano',
    'Aposentadoria Híbrida',
    'Pensão por Morte',
    'BPC por Idade',
    'BPC por Doença',
    'Auxílio Doença',
    'Auxílio Acidente',
    'Auxílio Reclusão',
    'Salário Maternidade Urbano',
    'Salário Maternidade Agricultora',
    'Salário Maternidade Pescadora',
    'Salário Maternidade Indígena',
    'Divórcio',
    'Ação Trabalhista',
    'Empréstimo'
);

$numero_processo_form = '';
$orgao_form = '';
$assunto_form = '';
$fase_form = '';
$status_form = 'ENVIADO';
$beneficio_form = '';
$data_distribuicao_form = '';
$data_ultimo_andamento_form = '';
$observacoes_form = '';

function garantirEstruturaProcessos($conn)
{
    $sqlCreate = "CREATE TABLE IF NOT EXISTS processos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        usuario_cadastro_id INT NOT NULL,
        numero_processo VARCHAR(80) NOT NULL,
        orgao VARCHAR(120) DEFAULT NULL,
        assunto VARCHAR(180) DEFAULT NULL,
        fase VARCHAR(80) DEFAULT NULL,
        status VARCHAR(60) DEFAULT 'ativo',
        data_distribuicao DATE DEFAULT NULL,
        data_ultimo_andamento DATE DEFAULT NULL,
        observacoes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sqlCreate)) {
        return array(false, 'Erro ao criar tabela processos: ' . $conn->error);
    }

    $requiredColumns = array(
        'cliente_id' => 'INT NOT NULL',
        'usuario_cadastro_id' => 'INT NOT NULL',
        'numero_processo' => 'VARCHAR(80) NOT NULL',
        'orgao' => 'VARCHAR(120) DEFAULT NULL',
        'assunto' => 'VARCHAR(180) DEFAULT NULL',
        'fase' => 'VARCHAR(80) DEFAULT NULL',
        'status' => "VARCHAR(60) DEFAULT 'ativo'",
        'beneficio' => 'VARCHAR(180) DEFAULT NULL',
        'data_distribuicao' => 'DATE DEFAULT NULL',
        'data_ultimo_andamento' => 'DATE DEFAULT NULL',
        'observacoes' => 'TEXT',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    );

    $existing = array();
    $resultCols = $conn->query('SHOW COLUMNS FROM processos');
    if (!$resultCols) {
        return array(false, 'Erro ao verificar colunas da tabela processos: ' . $conn->error);
    }

    while ($col = $resultCols->fetch_assoc()) {
        $existing[$col['Field']] = true;
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!isset($existing[$column])) {
            $sqlAlter = "ALTER TABLE processos ADD COLUMN {$column} {$definition}";
            if (!$conn->query($sqlAlter)) {
                return array(false, 'Erro ao atualizar tabela processos (coluna ' . $column . '): ' . $conn->error);
            }
        }
    }

    return array(true, null);
}

list($estruturaOk, $erroEstrutura) = garantirEstruturaProcessos($conn);
if (!$estruturaOk) {
    $erro = $erroEstrutura;
}

$status_do_banco = array();
$resStatusClientes = $conn->query("SELECT DISTINCT situacao AS status_valor FROM clientes WHERE situacao IS NOT NULL AND situacao <> ''");
if ($resStatusClientes) {
    while ($row = $resStatusClientes->fetch_assoc()) {
        $valor = trim((string)($row['status_valor'] ?? ''));
        if ($valor !== '') {
            $status_do_banco[] = strtoupper($valor);
        }
    }
}

if ($estruturaOk) {
    $resStatusProcessos = $conn->query("SELECT DISTINCT status AS status_valor FROM processos WHERE status IS NOT NULL AND status <> ''");
    if ($resStatusProcessos) {
        while ($row = $resStatusProcessos->fetch_assoc()) {
            $valor = trim((string)($row['status_valor'] ?? ''));
            if ($valor !== '') {
                $status_do_banco[] = strtoupper($valor);
            }
        }
    }
}

$status_padrao = array_values(array_unique(array_merge($status_padrao, $status_do_banco)));

// A lista $beneficios_padrao já é completa e canônica.
// Não mesclamos valores do banco para evitar entradas corrompidas ou com encoding errado.

if ($cliente_filtro_id > 0 && !mvpPodeAcessarCliente($conn, $cliente_filtro_id, $tipo_usuario, $usuario_logado_id, $is_admin)) {
    $erro = 'Voce nao tem permissao para visualizar processos deste cliente.';
    $cliente_filtro_id = 0;
}

if ($processo_id_form > 0) {
    $stmtProcessoEdicao = $conn->prepare("SELECT id, cliente_id, numero_processo, orgao, assunto, fase, status, beneficio, data_distribuicao, data_ultimo_andamento, observacoes FROM processos WHERE id = ? LIMIT 1");
    if ($stmtProcessoEdicao) {
        $stmtProcessoEdicao->bind_param('i', $processo_id_form);
        $stmtProcessoEdicao->execute();
        $resultProcessoEdicao = stmt_get_result($stmtProcessoEdicao);
        $processoEdicao = $resultProcessoEdicao ? $resultProcessoEdicao->fetch_assoc() : null;
        $stmtProcessoEdicao->close();

        if ($processoEdicao && mvpPodeAcessarCliente($conn, intval($processoEdicao['cliente_id']), $tipo_usuario, $usuario_logado_id, $is_admin)) {
            $cliente_id_form = intval($processoEdicao['cliente_id']);
            $numero_processo_form = (string)($processoEdicao['numero_processo'] ?? '');
            $orgao_form = (string)($processoEdicao['orgao'] ?? '');
            $assunto_form = (string)($processoEdicao['assunto'] ?? '');
            $fase_form = (string)($processoEdicao['fase'] ?? '');
            $status_form = (string)($processoEdicao['status'] ?? 'ENVIADO');
            $beneficio_form = (string)($processoEdicao['beneficio'] ?? '');
            $data_distribuicao_form = (string)($processoEdicao['data_distribuicao'] ?? '');
            $data_ultimo_andamento_form = (string)($processoEdicao['data_ultimo_andamento'] ?? '');
            $observacoes_form = (string)($processoEdicao['observacoes'] ?? '');
        } else {
            $processo_id_form = 0;
            if ($erro === '') {
                $erro = 'Processo nao encontrado ou sem permissao para editar.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro === '') {
    $processo_id_form = intval($_POST['processo_id'] ?? 0);
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    $cliente_id_form = $cliente_id;
    $numero_processo_form = trim($_POST['numero_processo'] ?? '');
    $orgao_form = trim($_POST['orgao'] ?? '');
    $assunto_form = trim($_POST['assunto'] ?? '');
    $fase_form = trim($_POST['fase'] ?? '');
    $status_form = trim($_POST['status'] ?? 'ENVIADO');
    $beneficio_form = trim($_POST['beneficio'] ?? '');
    $data_distribuicao_form = trim($_POST['data_distribuicao'] ?? '');
    $data_ultimo_andamento_form = trim($_POST['data_ultimo_andamento'] ?? '');
    $observacoes_form = trim($_POST['observacoes'] ?? '');

    if ($cliente_id <= 0 || $numero_processo_form === '') {
        $erro = 'Informe cliente e numero do processo.';
    } elseif (!mvpPodeAcessarCliente($conn, $cliente_id, $tipo_usuario, $usuario_logado_id, $is_admin)) {
        $erro = 'Voce nao tem permissao para usar este cliente.';
    } else {
        if ($processo_id_form > 0) {
            $stmtCheckProcesso = $conn->prepare("SELECT cliente_id FROM processos WHERE id = ? LIMIT 1");
            if (!$stmtCheckProcesso) {
                $erro = 'Erro ao preparar validacao de processo: ' . $conn->error;
            } else {
                $stmtCheckProcesso->bind_param('i', $processo_id_form);
                $stmtCheckProcesso->execute();
                $resultCheckProcesso = stmt_get_result($stmtCheckProcesso);
                $rowCheckProcesso = $resultCheckProcesso ? $resultCheckProcesso->fetch_assoc() : null;
                $stmtCheckProcesso->close();

                if (!$rowCheckProcesso || !mvpPodeAcessarCliente($conn, intval($rowCheckProcesso['cliente_id']), $tipo_usuario, $usuario_logado_id, $is_admin)) {
                    $erro = 'Voce nao tem permissao para alterar este processo.';
                } else {
                    $sqlUpdate = "UPDATE processos
                                  SET cliente_id = ?, numero_processo = ?, orgao = ?, assunto = ?, fase = ?, status = ?, beneficio = ?,
                                      data_distribuicao = NULLIF(?, ''), data_ultimo_andamento = NULLIF(?, ''), observacoes = ?
                                  WHERE id = ?";
                    $stmtUpdate = $conn->prepare($sqlUpdate);
                    if (!$stmtUpdate) {
                        $erro = 'Erro ao preparar alteracao de processo: ' . $conn->error;
                    } else {
                        $stmtUpdate->bind_param(
                            'isssssssssi',
                            $cliente_id,
                            $numero_processo_form,
                            $orgao_form,
                            $assunto_form,
                            $fase_form,
                            $status_form,
                            $beneficio_form,
                            $data_distribuicao_form,
                            $data_ultimo_andamento_form,
                            $observacoes_form,
                            $processo_id_form
                        );

                        if ($stmtUpdate->execute()) {
                            $mensagem = 'Processo atualizado com sucesso.';
                        } else {
                            $erro = 'Erro ao atualizar processo: ' . $stmtUpdate->error;
                        }
                        $stmtUpdate->close();
                    }
                }
            }
        } else {
            $sql = "INSERT INTO processos (cliente_id, usuario_cadastro_id, numero_processo, orgao, assunto, fase, status, beneficio, data_distribuicao, data_ultimo_andamento, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $erro = 'Erro ao preparar cadastro de processo: ' . $conn->error;
            } else {
                $stmt->bind_param(
                    'iisssssssss',
                    $cliente_id,
                    $usuario_logado_id,
                    $numero_processo_form,
                    $orgao_form,
                    $assunto_form,
                    $fase_form,
                    $status_form,
                    $beneficio_form,
                    $data_distribuicao_form,
                    $data_ultimo_andamento_form,
                    $observacoes_form
                );

                if ($stmt->execute()) {
                    $mensagem = 'Processo cadastrado com sucesso.';
                    $processo_id_form = 0;
                    $numero_processo_form = '';
                    $orgao_form = '';
                    $assunto_form = '';
                    $fase_form = '';
                    $status_form = 'ENVIADO';
                    $beneficio_form = '';
                    $data_distribuicao_form = '';
                    $data_ultimo_andamento_form = '';
                    $observacoes_form = '';
                } else {
                    $erro = 'Erro ao cadastrar processo: ' . $stmt->error;
                }

                $stmt->close();
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
$sqlProcessos = "SELECT p.id, p.cliente_id, p.numero_processo, p.orgao, p.assunto, p.fase, p.status, p.beneficio, p.data_distribuicao, p.data_ultimo_andamento,
                        c.nome AS cliente_nome
                 FROM processos p
                 INNER JOIN clientes c ON c.id = p.cliente_id
                 WHERE 1=1";
$paramsProcessos = array();
$typesProcessos = '';

if ($tipo_usuario === 'parceiro') {
    $sqlProcessos .= mvpPermissaoClienteWhere('c', $tipo_usuario);
    $typesProcessos .= 'i';
    $paramsProcessos[] = $usuario_logado_id;
}

if ($cliente_filtro_id > 0) {
    $sqlProcessos .= " AND p.cliente_id = ?";
    $typesProcessos .= 'i';
    $paramsProcessos[] = $cliente_filtro_id;
}

$sqlProcessos .= " ORDER BY p.updated_at DESC LIMIT 200";
$stmtProcessos = $conn->prepare($sqlProcessos);
if ($stmtProcessos) {
    if ($typesProcessos !== '') {
        $bindArgs = array($typesProcessos);
        for ($i = 0; $i < count($paramsProcessos); $i++) {
            $bindArgs[] = &$paramsProcessos[$i];
        }
        call_user_func_array(array($stmtProcessos, 'bind_param'), $bindArgs);
    }

    $stmtProcessos->execute();
    $resultProcessos = stmt_get_result($stmtProcessos);
    while ($row = $resultProcessos->fetch_assoc()) {
        $processos[] = $row;
    }
    $stmtProcessos->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processos</title>
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
        button { background: #2563eb; border: none; color: #fff; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 9pt; }
        th { background: #f8fafc; color: #334155; font-weight: 700; }
        tbody tr:hover { background: #f8fafc; }
        .ok { color: #065f46; background: #d1fae5; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
        .erro { color: #991b1b; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
        .status { text-transform: capitalize; font-weight: 600; }
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
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div><i class="fas fa-gavel"></i> Modulo de Processos</div>
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
        <div class="card">
            <h2><?php echo ($processo_id_form > 0) ? 'Editar processo' : 'Novo processo'; ?></h2>
            <?php if ($mensagem): ?><div class="ok"><?php echo htmlspecialchars($mensagem); ?></div><?php endif; ?>
            <?php if ($erro): ?><div class="erro"><?php echo htmlspecialchars($erro); ?></div><?php endif; ?>
            <?php if ($cliente_filtro_id > 0): ?>
                <div class="ok" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <span>Filtro ativo: exibindo processos do cliente selecionado.</span>
                    <a href="processos.php" style="background:#0f766e; color:#fff; text-decoration:none; padding:6px 10px; border-radius:8px; font-size:9pt; font-weight:700;">Ver todos</a>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="processo_id" value="<?php echo (int)$processo_id_form; ?>">
                <div class="grid">
                    <div>
                        <label for="cliente_id">Cliente</label>
                        <select id="cliente_id" name="cliente_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo (int)$cliente['id']; ?>" <?php echo ((int)$cliente['id'] === (int)$cliente_id_form) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cliente['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="numero_processo">Numero do processo</label>
                        <input id="numero_processo" name="numero_processo" maxlength="80" required value="<?php echo htmlspecialchars($numero_processo_form, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label for="orgao">Orgao</label>
                        <input id="orgao" name="orgao" maxlength="120" value="<?php echo htmlspecialchars($orgao_form, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label for="assunto">Assunto</label>
                        <input id="assunto" name="assunto" maxlength="180" value="<?php echo htmlspecialchars($assunto_form, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label for="fase">Fase</label>
                        <input id="fase" name="fase" maxlength="80" placeholder="Inicial, recurso, execucao..." value="<?php echo htmlspecialchars($fase_form, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php foreach ($status_padrao as $status_opcao): ?>
                                <option value="<?php echo htmlspecialchars($status_opcao, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($status_form === $status_opcao) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_opcao, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="beneficio">Beneficio</label>
                        <select id="beneficio" name="beneficio">
                            <option value="">Selecione</option>
                            <?php foreach ($beneficios_padrao as $beneficio_opcao): ?>
                                <option value="<?php echo htmlspecialchars($beneficio_opcao, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($beneficio_form === $beneficio_opcao) ? 'selected' : ''; ?>><?php echo htmlspecialchars($beneficio_opcao, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="data_distribuicao">Data distribuicao</label>
                        <input type="date" id="data_distribuicao" name="data_distribuicao" value="<?php echo htmlspecialchars($data_distribuicao_form, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label for="data_ultimo_andamento">Ultimo andamento</label>
                        <input type="date" id="data_ultimo_andamento" name="data_ultimo_andamento" value="<?php echo htmlspecialchars($data_ultimo_andamento_form, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div style="margin-top: 12px;">
                    <label for="observacoes">Observacoes</label>
                    <textarea id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($observacoes_form, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div style="margin-top: 12px; display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="submit"><?php echo ($processo_id_form > 0) ? 'Atualizar processo' : 'Salvar processo'; ?></button>
                    <?php if ($processo_id_form > 0): ?>
                        <a href="<?php echo ($cliente_filtro_id > 0) ? ('processos.php?cliente_id=' . (int)$cliente_filtro_id) : 'processos.php'; ?>" style="display:inline-block; background:#475569; color:#fff; text-decoration:none; padding:8px 14px; border-radius:8px; font-weight:700; font-size:9pt;">Cancelar edicao</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                <h2 style="margin:0;"><?php echo ($cliente_filtro_id > 0) ? 'Processos da pessoa selecionada' : 'Ultimos processos'; ?></h2>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <label for="processFiltroCliente" style="margin:0; font-size:9pt;">Filtro por cliente</label>
                    <select id="processFiltroCliente" style="width:auto; min-width:220px;">
                        <option value="0">Todos os clientes</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo (int)$cliente['id']; ?>" <?php echo ((int)$cliente['id'] === (int)$cliente_filtro_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cliente['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="processFiltroAplicar" style="padding:7px 10px;">Aplicar</button>
                    <button type="button" id="processFiltroLimpar" style="padding:7px 10px; background:#475569;">Limpar</button>
                </div>
            </div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Numero</th>
                            <th>Orgao</th>
                            <th>Fase</th>
                            <th>Status</th>
                            <th>Beneficio</th>
                            <th>Distribuicao</th>
                            <th>Ultimo andamento</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($processos)): ?>
                            <tr><td colspan="10">Nenhum processo encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($processos as $p): ?>
                                <tr>
                                    <td><?php echo (int)$p['id']; ?></td>
                                    <td><?php echo htmlspecialchars($p['cliente_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($p['numero_processo']); ?></td>
                                    <td><?php echo htmlspecialchars($p['orgao'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($p['fase'] ?: '-'); ?></td>
                                    <td class="status"><?php echo htmlspecialchars($p['status'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($p['beneficio'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(mvpDateBr($p['data_distribuicao'])); ?></td>
                                    <td><?php echo htmlspecialchars(mvpDateBr($p['data_ultimo_andamento'])); ?></td>
                                    <td>
                                        <a href="processos.php?editar=<?php echo (int)$p['id']; ?><?php echo ($cliente_filtro_id > 0) ? ('&cliente_id=' . (int)$cliente_filtro_id) : ''; ?>" style="display:inline-block; background:#2563eb; color:#fff; text-decoration:none; padding:6px 10px; border-radius:8px; font-size:9pt; font-weight:700;">Editar</a>
                                    </td>
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
            const filterKey = 'mvpProcessosFiltroCliente';

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

            const btn = document.getElementById('topbarToggle');
            const icon = document.getElementById('topbarToggleIcon');
            const label = document.getElementById('topbarToggleLabel');
            const filtroCliente = document.getElementById('processFiltroCliente');
            const filtroAplicar = document.getElementById('processFiltroAplicar');
            const filtroLimpar = document.getElementById('processFiltroLimpar');

            function existeOpcaoFiltro(valor) {
                if (!filtroCliente) {
                    return false;
                }
                return !!filtroCliente.querySelector('option[value="' + String(valor) + '"]');
            }

            function salvarFiltro(valor) {
                try {
                    localStorage.setItem(filterKey, String(valor || '0'));
                } catch (e) {
                    // Ignora falha de armazenamento.
                }
            }

            function aplicarFiltroCliente(clienteId) {
                const url = new URL(window.location.href);
                if (clienteId && clienteId !== '0') {
                    url.searchParams.set('cliente_id', clienteId);
                } else {
                    url.searchParams.delete('cliente_id');
                }
                url.searchParams.delete('editar');
                window.location.href = url.toString();
            }

            if (filtroCliente) {
                const urlAtual = new URL(window.location.href);
                const clienteUrl = urlAtual.searchParams.get('cliente_id');
                if (clienteUrl && existeOpcaoFiltro(clienteUrl)) {
                    filtroCliente.value = clienteUrl;
                    salvarFiltro(clienteUrl);
                } else {
                    let salvo = '0';
                    try {
                        salvo = localStorage.getItem(filterKey) || '0';
                    } catch (e) {
                        salvo = '0';
                    }

                    if (salvo !== '0' && existeOpcaoFiltro(salvo)) {
                        filtroCliente.value = salvo;
                        const emEdicao = urlAtual.searchParams.has('editar');
                        if (!emEdicao) {
                            aplicarFiltroCliente(salvo);
                            return;
                        }
                    }
                }

                filtroCliente.addEventListener('change', function () {
                    salvarFiltro(filtroCliente.value || '0');
                });
            }

            if (filtroAplicar) {
                filtroAplicar.addEventListener('click', function () {
                    if (!filtroCliente) {
                        return;
                    }
                    const valor = filtroCliente.value || '0';
                    salvarFiltro(valor);
                    aplicarFiltroCliente(valor);
                });
            }

            if (filtroLimpar) {
                filtroLimpar.addEventListener('click', function () {
                    if (filtroCliente) {
                        filtroCliente.value = '0';
                    }
                    salvarFiltro('0');
                    aplicarFiltroCliente('0');
                });
            }

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
