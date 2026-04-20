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
require_once __DIR__ . '/advogados_utils.php';

garantirTabelaAdvogados($conn);

$is_admin = rls_is_admin();

$advogados = [];
$res = $conn->query("SELECT id, nome, oab, documento, cidade, uf, fone, email, ativo FROM advogados ORDER BY nome ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $advogados[] = $row;
}

$msg_flash = $_SESSION['msg_advogados'] ?? null;
unset($_SESSION['msg_advogados']);

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advogados — Real Assessoria</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', Arial, sans-serif; margin: 0; }
        .page-header {
            background: #243447; color: #fff; padding: 16px 28px;
            display: flex; align-items: center; gap: 14px;
        }
        .page-header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .page-header .back-link { color: #7f97aa; text-decoration: none; font-size: 13px; margin-left: auto; }
        .page-header .back-link:hover { color: #fff; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 20px; }
        .toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600;
            cursor: pointer; border: none; text-decoration: none; transition: background .15s;
        }
        .btn-primary { background: #3e79b7; color: #fff; }
        .btn-primary:hover { background: #2f5f91; }
        .btn-sm { padding: 5px 10px; font-size: 11px; }
        .btn-edit { background: #f0a500; color: #fff; }
        .btn-edit:hover { background: #c98400; }
        .btn-danger { background: #c0392b; color: #fff; }
        .btn-danger:hover { background: #96301e; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.07); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #243447; color: #fff; padding: 10px 14px; font-size: 12px; text-align: left; font-weight: 600; text-transform: uppercase; }
        tbody tr { border-bottom: 1px solid #eef2f7; }
        tbody tr:hover { background: #f7fafd; }
        tbody tr.inativo { opacity: .55; }
        tbody td { padding: 10px 14px; font-size: 13px; color: #2c3e50; vertical-align: middle; }
        .badge-ativo { background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-inativo { background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .acoes { display: flex; gap: 6px; }
        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .empty-state { text-align: center; padding: 48px 20px; color: #7f97aa; }
        .empty-state i { font-size: 48px; margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

<div class="page-header">
    <i class="fas fa-user-tie" style="font-size:22px;color:#7f97aa;"></i>
    <h1>Advogados</h1>
    <a href="listar_modelos.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para Modelos</a>
</div>

<div class="container">

    <?php if ($msg_flash): ?>
    <div class="alert alert-<?= $msg_flash['tipo'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($msg_flash['texto']) ?>
    </div>
    <?php endif; ?>

    <div class="toolbar">
        <a href="cadastrar_advogado.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Advogado
        </a>
    </div>

    <div class="card">
        <?php if (empty($advogados)): ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            Nenhum advogado cadastrado.<br>
            <a href="cadastrar_advogado.php" style="color:#3e79b7;margin-top:8px;display:inline-block;">Cadastrar agora</a>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>OAB</th>
                    <th>Documento</th>
                    <th>Cidade / UF</th>
                    <th>Fone</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($advogados as $adv): ?>
                <tr class="<?= $adv['ativo'] ? '' : 'inativo' ?>">
                    <td><strong><?= htmlspecialchars($adv['nome']) ?></strong></td>
                    <td><?= htmlspecialchars($adv['oab']) ?></td>
                    <td><?= htmlspecialchars($adv['documento']) ?></td>
                    <td><?= htmlspecialchars($adv['cidade']) ?> / <?= htmlspecialchars($adv['uf']) ?></td>
                    <td><?= htmlspecialchars($adv['fone']) ?></td>
                    <td>
                        <span class="<?= $adv['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>">
                            <?= $adv['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td>
                        <div class="acoes">
                            <a href="editar_advogado.php?id=<?= (int)$adv['id'] ?>" class="btn btn-sm btn-edit">
                                <i class="fas fa-pen"></i> Editar
                            </a>
                            <?php if ($is_admin): ?>
                            <button type="button" class="btn btn-sm btn-danger"
                                    onclick="excluirAdvogado(<?= (int)$adv['id'] ?>, <?= htmlspecialchars(json_encode($adv['nome'])) ?>)">
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

async function excluirAdvogado(id, nome) {
    if (!confirm(`Desativar o advogado "${nome}"?`)) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);
    try {
        const resp = await fetch('excluir_advogado.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.sucesso) {
            location.reload();
        } else {
            alert('Erro: ' + data.mensagem);
        }
    } catch (e) {
        alert('Erro de comunicação.');
    }
}
</script>
</body>
</html>
