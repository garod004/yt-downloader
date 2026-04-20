<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}

$modelo_id  = (int)($_GET['modelo_id']  ?? 0);
$cliente_id = (int)($_GET['cliente_id'] ?? 0);
if ($modelo_id <= 0) {
    header("Location: listar_modelos.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/advogados_utils.php';

// Buscar modelo
$stmt = $conn->prepare(
    "SELECT id, nome, categoria, conteudo FROM modelos_documentos WHERE id = ? AND ativo = 1 LIMIT 1"
);
$stmt->bind_param('i', $modelo_id);
$stmt->execute();
$modelo = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();
if (!$modelo) {
    header("Location: listar_modelos.php");
    exit();
}

// Detectar quais campos o modelo usa
$conteudo_modelo = $modelo['conteudo'];
$usa_advogado   = strpos($conteudo_modelo, '{{advogado_')   !== false;
$usa_advogado_2 = strpos($conteudo_modelo, '{{advogado_2_') !== false;
$usa_advogado_3 = strpos($conteudo_modelo, '{{advogado_3_') !== false;
$usa_filho      = strpos($conteudo_modelo, '{{filho_')      !== false;
$usa_incapaz    = strpos($conteudo_modelo, '{{incapaz_')    !== false;
$usa_a_rogo     = strpos($conteudo_modelo, '{{a_rogo_')     !== false;

// Buscar clientes
$clientes = [];
$resC = $conn->query("SELECT id, nome, cpf FROM clientes ORDER BY nome ASC");
if ($resC) {
    while ($row = $resC->fetch_assoc()) $clientes[] = $row;
}

// Buscar advogados ativos
$advogados = [];
garantirTabelaAdvogados($conn);
$resA = $conn->query("SELECT id, nome, oab FROM advogados WHERE ativo = 1 ORDER BY nome ASC");
if ($resA) {
    while ($row = $resA->fetch_assoc()) $advogados[] = $row;
}

// Dependentes do cliente pré-selecionado
$filhos = $incapazes = $a_rogos = [];
$cliente_nome = '';
if ($cliente_id > 0) {
    $stmtCN = $conn->prepare("SELECT nome FROM clientes WHERE id = ? LIMIT 1");
    $stmtCN->bind_param('i', $cliente_id);
    $stmtCN->execute();
    $rowCN = stmt_get_result($stmtCN)->fetch_assoc();
    $cliente_nome = $rowCN['nome'] ?? '';
    $stmtCN->close();

    if ($cliente_nome !== '') {
        if ($usa_filho) {
            $s = $conn->prepare("SELECT id, nome FROM filhos_menores WHERE cliente_id = ? ORDER BY nome ASC");
            $s->bind_param('i', $cliente_id);
            $s->execute();
            $r = stmt_get_result($s);
            while ($row = $r->fetch_assoc()) $filhos[] = $row;
            $s->close();
        }
        if ($usa_incapaz) {
            $s = $conn->prepare("SELECT id, nome FROM incapazes WHERE cliente_id = ? ORDER BY nome ASC");
            $s->bind_param('i', $cliente_id);
            $s->execute();
            $r = stmt_get_result($s);
            while ($row = $r->fetch_assoc()) $incapazes[] = $row;
            $s->close();
        }
        if ($usa_a_rogo) {
            $s = $conn->prepare("SELECT id, nome FROM a_rogo WHERE cliente_id = ? ORDER BY nome ASC");
            $s->bind_param('i', $cliente_id);
            $s->execute();
            $r = stmt_get_result($s);
            while ($row = $r->fetch_assoc()) $a_rogos[] = $row;
            $s->close();
        }
    } else {
        $cliente_id = 0;
    }
}

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Documento — <?= htmlspecialchars($modelo['nome']) ?></title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', Arial, sans-serif; margin: 0; }

        .page-header {
            background: #243447;
            color: #fff;
            padding: 16px 28px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .page-header h1 { margin: 0; font-size: 18px; font-weight: 700; }
        .page-header .back-link {
            color: #7f97aa;
            text-decoration: none;
            font-size: 13px;
            margin-left: auto;
        }
        .page-header .back-link:hover { color: #fff; }

        .container { max-width: 680px; margin: 28px auto; padding: 0 20px; }

        .modelo-info {
            background: #e8f4fd;
            border-left: 4px solid #3e79b7;
            padding: 12px 18px;
            border-radius: 0 6px 6px 0;
            margin-bottom: 20px;
        }
        .modelo-info strong { color: #1c2d3c; font-size: 15px; }
        .modelo-info span { font-size: 12px; color: #5c788e; margin-left: 8px; }

        .form-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            padding: 22px 24px;
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #3d5166;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 14px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e8eef4;
        }
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #3d5166;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #c5d5e4;
            border-radius: 6px;
            font-size: 13px;
            color: #2c3e50;
            background: #fff;
        }
        .form-group select:focus { outline: none; border-color: #3e79b7; }
        .form-group select:disabled { background: #f5f7fa; color: #999; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-success { background: #27ae60; color: #fff; }
        .btn-success:hover { background: #1e8a4c; }
        .btn-secondary { background: #7f97aa; color: #fff; }
        .btn-secondary:hover { background: #5c788e; }
        .btn-outline {
            background: transparent;
            color: #3e79b7;
            border: 1px solid #3e79b7;
        }
        .btn-outline:hover { background: #e8f4fd; }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            padding: 10px 16px;
            font-size: 13px;
            margin-bottom: 12px;
            display: none;
        }

        #loadingMsg {
            display: none;
            font-size: 13px;
            color: #7f97aa;
            align-items: center;
            gap: 6px;
        }

        .acoes-retorno {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
    </style>
</head>
<body>

<div class="page-header">
    <i class="fas fa-file-pdf" style="font-size:20px;color:#7f97aa;"></i>
    <h1>Gerar Documento</h1>
    <a href="listar_modelos.php<?= $cliente_id > 0 ? '?cliente_id=' . $cliente_id : '' ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Voltar para Modelos
    </a>
</div>

<div class="container">

    <div class="modelo-info">
        <strong><?= htmlspecialchars($modelo['nome']) ?></strong>
        <span><?= htmlspecialchars($modelo['categoria']) ?></span>
    </div>

    <div id="erroMsg" class="alert-error"></div>

    <form id="formGerar" method="POST" action="gerar_documento_pdf.php" target="_blank">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="modelo_id" value="<?= $modelo_id ?>">

        <!-- CLIENTE -->
        <div class="form-card">
            <div class="section-title">Cliente *</div>
            <div class="form-group">
                <label for="cliente_id">Selecione o cliente</label>
                <select id="cliente_id" name="cliente_id" required>
                    <option value="">— Selecione o cliente —</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"
                        <?= (int)$c['id'] === $cliente_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome']) ?> — <?= htmlspecialchars($c['cpf']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($usa_advogado): ?>
        <!-- ADVOGADOS -->
        <div class="form-card">
            <div class="section-title">Advogados</div>
            <div class="form-group">
                <label for="advogado_1_id">Advogado 1</label>
                <select id="advogado_1_id" name="advogado_1_id">
                    <option value="0">— Não informar —</option>
                    <?php foreach ($advogados as $adv): ?>
                    <option value="<?= (int)$adv['id'] ?>">
                        <?= htmlspecialchars($adv['nome']) ?> — OAB <?= htmlspecialchars($adv['oab']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($usa_advogado_2): ?>
            <div class="form-group">
                <label for="advogado_2_id">Advogado 2</label>
                <select id="advogado_2_id" name="advogado_2_id">
                    <option value="0">— Não informar —</option>
                    <?php foreach ($advogados as $adv): ?>
                    <option value="<?= (int)$adv['id'] ?>">
                        <?= htmlspecialchars($adv['nome']) ?> — OAB <?= htmlspecialchars($adv['oab']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($usa_advogado_3): ?>
            <div class="form-group">
                <label for="advogado_3_id">Advogado 3</label>
                <select id="advogado_3_id" name="advogado_3_id">
                    <option value="0">— Não informar —</option>
                    <?php foreach ($advogados as $adv): ?>
                    <option value="<?= (int)$adv['id'] ?>">
                        <?= htmlspecialchars($adv['nome']) ?> — OAB <?= htmlspecialchars($adv['oab']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($usa_filho || $usa_incapaz || $usa_a_rogo): ?>
        <!-- DEPENDENTES -->
        <div class="form-card">
            <div class="section-title">Dependentes
                <?php if ($cliente_id <= 0): ?>
                <span style="font-weight:400;color:#c0392b;font-size:11px;"> — selecione um cliente primeiro</span>
                <?php endif; ?>
            </div>

            <?php if ($usa_filho): ?>
            <div class="form-group">
                <label for="filho_id">Filho menor</label>
                <select id="filho_id" name="filho_id" <?= $cliente_id <= 0 ? 'disabled' : '' ?>>
                    <option value="0">— Não informar —</option>
                    <?php foreach ($filhos as $f): ?>
                    <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($usa_incapaz): ?>
            <div class="form-group">
                <label for="incapaz_id">Incapaz</label>
                <select id="incapaz_id" name="incapaz_id" <?= $cliente_id <= 0 ? 'disabled' : '' ?>>
                    <option value="0">— Não informar —</option>
                    <?php foreach ($incapazes as $inc): ?>
                    <option value="<?= (int)$inc['id'] ?>"><?= htmlspecialchars($inc['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($usa_a_rogo): ?>
            <div class="form-group">
                <label for="a_rogo_id">A Rogo</label>
                <select id="a_rogo_id" name="a_rogo_id" <?= $cliente_id <= 0 ? 'disabled' : '' ?>>
                    <option value="0">— Não informar —</option>
                    <?php foreach ($a_rogos as $ar): ?>
                    <option value="<?= (int)$ar['id'] ?>"><?= htmlspecialchars($ar['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-success" id="btnGerar">
                <i class="fas fa-file-pdf"></i> Gerar PDF
            </button>
            <span id="loadingMsg"><i class="fas fa-spinner fa-spin"></i> Gerando PDF...</span>
        </div>
    </form>

    <div class="acoes-retorno">
        <?php if ($cliente_id > 0): ?>
        <a href="listar_clientes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Clientes
        </a>
        <a href="listar_modelos.php?cliente_id=<?= $cliente_id ?>" class="btn btn-outline">
            <i class="fas fa-file-alt"></i> Escolher outro modelo
        </a>
        <?php else: ?>
        <a href="listar_modelos.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Modelos
        </a>
        <?php endif; ?>
    </div>

</div>

<script>
const modeloId  = <?= $modelo_id ?>;
const clienteId = <?= $cliente_id ?>;

document.getElementById('cliente_id').addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('modelo_id', modeloId);
    if (this.value) {
        url.searchParams.set('cliente_id', this.value);
    } else {
        url.searchParams.delete('cliente_id');
    }
    window.location.href = url.toString();
});

document.getElementById('formGerar').addEventListener('submit', function (e) {
    const clienteSel = document.getElementById('cliente_id').value;
    if (!clienteSel) {
        e.preventDefault();
        const err = document.getElementById('erroMsg');
        err.textContent = 'Selecione um cliente antes de gerar o documento.';
        err.style.display = 'block';
        return;
    }
    document.getElementById('loadingMsg').style.display = 'inline-flex';
    document.getElementById('btnGerar').disabled = true;
    setTimeout(() => {
        document.getElementById('loadingMsg').style.display = 'none';
        document.getElementById('btnGerar').disabled = false;
    }, 6000);
});
</script>
</body>
</html>
