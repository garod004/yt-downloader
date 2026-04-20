<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/security_rls.php';

$is_admin = rls_is_admin();

// Cliente pré-selecionado (opcional)
$cliente_id = (int)($_GET['cliente_id'] ?? 0);
$cliente_nome = '';
if ($cliente_id > 0 && isset($conn) && $conn instanceof mysqli) {
    $stmtC = $conn->prepare("SELECT nome FROM clientes WHERE id = ? LIMIT 1");
    $stmtC->bind_param('i', $cliente_id);
    $stmtC->execute();
    $rowC = stmt_get_result($stmtC)->fetch_assoc();
    $cliente_nome = $rowC['nome'] ?? '';
    $stmtC->close();
    if ($cliente_nome === '') {
        $cliente_id = 0;
    }
}

// Buscar modelos
$modelos = [];
if (isset($conn) && $conn instanceof mysqli) {
    $res = $conn->query(
        "SELECT id, nome, categoria, descricao, criado_por, created_at
         FROM modelos_documentos
         WHERE ativo = 1
         ORDER BY categoria ASC, nome ASC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $modelos[] = $row;
        }
    }
}

// Mensagem flash
$msg_flash = $_SESSION['msg_modelos'] ?? null;
unset($_SESSION['msg_modelos']);

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modelos de Documentos — Real Assessoria</title>
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
        .page-header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .page-header .back-link {
            color: #7f97aa;
            text-decoration: none;
            font-size: 13px;
            margin-left: auto;
        }
        .page-header .back-link:hover { color: #fff; }

        .banner-cliente {
            background: #e8f4fd;
            border-left: 4px solid #3e79b7;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #1c2d3c;
        }
        .banner-cliente a {
            margin-left: auto;
            color: #c0392b;
            text-decoration: none;
            font-size: 13px;
        }
        .banner-cliente a:hover { text-decoration: underline; }

        .container {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 20px;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-primary { background: #3e79b7; color: #fff; }
        .btn-primary:hover { background: #2f5f91; }
        .btn-secondary { background: #7f97aa; color: #fff; }
        .btn-secondary:hover { background: #5c788e; }
        .btn-sm { padding: 5px 11px; font-size: 12px; }
        .btn-edit { background: #f0a500; color: #fff; }
        .btn-edit:hover { background: #c98400; }
        .btn-danger { background: #c0392b; color: #fff; }
        .btn-danger:hover { background: #96301e; }
        .btn-success { background: #27ae60; color: #fff; }
        .btn-success:hover { background: #1e8a4c; }

        .search-box input {
            padding: 8px 14px;
            border: 1px solid #c5d5e4;
            border-radius: 6px;
            font-size: 13px;
            width: 240px;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #243447;
            color: #fff;
            padding: 10px 14px;
            font-size: 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        tbody tr { border-bottom: 1px solid #eef2f7; }
        tbody tr:hover { background: #f7fafd; }
        tbody td { padding: 10px 14px; font-size: 13px; color: #2c3e50; vertical-align: middle; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            background: #e8f4fd;
            color: #2471a3;
        }

        .acoes { display: flex; gap: 6px; flex-wrap: wrap; }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #7f97aa;
        }
        .empty-state i { font-size: 48px; margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

<div class="page-header">
    <i class="fas fa-file-alt" style="font-size:22px;color:#7f97aa;"></i>
    <h1>Modelos de Documentos</h1>
    <a href="listar_clientes.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para Clientes</a>
</div>

<?php if ($cliente_id > 0): ?>
<div class="banner-cliente">
    <i class="fas fa-user-circle" style="color:#3e79b7;"></i>
    Gerando documento para: <strong><?= htmlspecialchars($cliente_nome) ?></strong>
    <a href="listar_modelos.php"><i class="fas fa-times"></i> Remover cliente</a>
</div>
<?php endif; ?>

<div class="container">

    <?php if ($msg_flash): ?>
    <div class="alert alert-<?= $msg_flash['tipo'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($msg_flash['texto']) ?>
    </div>
    <?php endif; ?>

    <div class="toolbar">
        <a href="criar_modelo.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Modelo
        </a>
        <a href="gerenciar_advogados.php" class="btn btn-secondary">
            <i class="fas fa-user-tie"></i> Advogados
        </a>
        <div class="search-box" style="margin-left:auto;">
            <input type="text" id="campoBusca" placeholder="Buscar modelo..." oninput="filtrarModelos()">
        </div>
    </div>

    <div class="card">
        <?php if (empty($modelos)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            Nenhum modelo cadastrado ainda.<br>
            <a href="criar_modelo.php" style="color:#3e79b7;margin-top:8px;display:inline-block;">Criar o primeiro modelo</a>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Descrição</th>
                    <th>Criado por</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaModelos">
            <?php foreach ($modelos as $m): ?>
                <tr data-nome="<?= htmlspecialchars(strtolower($m['nome'] . ' ' . $m['categoria'] . ' ' . $m['descricao'])) ?>">
                    <td><strong><?= htmlspecialchars($m['nome']) ?></strong></td>
                    <td><span class="badge"><?= htmlspecialchars($m['categoria']) ?></span></td>
                    <td style="color:#666;"><?= htmlspecialchars($m['descricao'] ?? '') ?></td>
                    <td><?= htmlspecialchars($m['criado_por'] ?? '') ?></td>
                    <td><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
                    <td>
                        <div class="acoes">
                            <a href="gerar_documento.php?modelo_id=<?= (int)$m['id'] ?><?= $cliente_id > 0 ? '&cliente_id=' . $cliente_id : '' ?>"
                               class="btn btn-sm btn-success" title="Gerar PDF">
                                <i class="fas fa-file-pdf"></i> Gerar
                            </a>
                            <a href="editar_modelo.php?id=<?= (int)$m['id'] ?>"
                               class="btn btn-sm btn-edit" title="Editar modelo">
                                <i class="fas fa-pen"></i> Editar
                            </a>
                            <?php if ($is_admin): ?>
                            <button type="button" class="btn btn-sm btn-danger"
                                    onclick="excluirModelo(<?= (int)$m['id'] ?>, <?= htmlspecialchars(json_encode($m['nome'])) ?>)"
                                    title="Excluir modelo">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
const csrfToken = <?= json_encode($csrf) ?>;

function filtrarModelos() {
    const q = document.getElementById('campoBusca').value.toLowerCase();
    document.querySelectorAll('#tabelaModelos tr').forEach(tr => {
        tr.style.display = tr.dataset.nome.includes(q) ? '' : 'none';
    });
}

async function excluirModelo(id, nome) {
    if (!confirm(`Excluir o modelo "${nome}"? Esta ação não pode ser desfeita.`)) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);
    try {
        const resp = await fetch('excluir_modelo.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.sucesso) {
            location.reload();
        } else {
            alert('Erro: ' + data.mensagem);
        }
    } catch (e) {
        alert('Erro de comunicação com o servidor.');
    }
}
</script>
</body>
</html>
