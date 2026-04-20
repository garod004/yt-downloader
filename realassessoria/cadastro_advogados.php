<?php
require_once __DIR__ . '/mvp_utils.php';
require_once __DIR__ . '/advogados_utils.php';

if (!$is_admin) {
    header('Location: dashboard.php');
    exit();
}

garantirTabelaAdvogados($conn);

$mensagem = '';
$tipoMensagem = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $documento = trim($_POST['documento'] ?? '');
        $oab = trim($_POST['oab'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $uf = normalizarUf($_POST['uf'] ?? '');
        $fone = trim($_POST['fone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '' || $documento === '' || $oab === '' || $endereco === '' || $cidade === '' || $uf === '') {
            $mensagem = 'Preencha todos os campos obrigatorios.';
            $tipoMensagem = 'erro';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Informe um e-mail valido.';
            $tipoMensagem = 'erro';
        } else {
            if ($id > 0) {
                $sql = "UPDATE advogados SET nome=?, documento=?, oab=?, endereco=?, cidade=?, uf=?, fone=?, email=?, ativo=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ssssssssii', $nome, $documento, $oab, $endereco, $cidade, $uf, $fone, $email, $ativo, $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) {
                        $mensagem = 'Advogado atualizado com sucesso.';
                        $tipoMensagem = 'ok';
                    } else {
                        $mensagem = 'Erro ao atualizar advogado.';
                        $tipoMensagem = 'erro';
                    }
                }
            } else {
                $sql = "INSERT INTO advogados (nome, documento, oab, endereco, cidade, uf, fone, email, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ssssssssi', $nome, $documento, $oab, $endereco, $cidade, $uf, $fone, $email, $ativo);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) {
                        $mensagem = 'Advogado cadastrado com sucesso.';
                        $tipoMensagem = 'ok';
                    } else {
                        $mensagem = 'Erro ao cadastrar advogado.';
                        $tipoMensagem = 'erro';
                    }
                }
            }
        }
    }

    if ($acao === 'excluir') {
        $idExcluir = intval($_POST['id'] ?? 0);
        if ($idExcluir > 0) {
            $stmt = $conn->prepare("DELETE FROM advogados WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $idExcluir);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    $mensagem = 'Advogado excluido com sucesso.';
                    $tipoMensagem = 'ok';
                } else {
                    $mensagem = 'Erro ao excluir advogado.';
                    $tipoMensagem = 'erro';
                }
            }
        }
    }
}

$advogados = array();
$res = $conn->query("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados ORDER BY nome ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $advogados[] = $row;
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $idEditar = intval($_GET['editar']);
    foreach ($advogados as $item) {
        if (intval($item['id']) === $idEditar) {
            $editar = $item;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Advogados</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; color: #1f2937; }
        .topbar { background: #0f172a; color: #fff; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #fff; text-decoration: none; margin-left: 10px; }
        .container { max-width: 1100px; margin: 20px auto; padding: 0 14px; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); padding: 16px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 20px; }
        h2 { margin: 0 0 12px; font-size: 18px; }
        .msg-ok { background: #dcfce7; color: #166534; padding: 10px 12px; border-radius: 8px; }
        .msg-erro { background: #fee2e2; color: #991b1b; padding: 10px 12px; border-radius: 8px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 10px 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; }
        .field label { font-size: 13px; font-weight: 600; }
        .field input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .actions { margin-top: 12px; display: flex; gap: 10px; }
        .btn { border: none; border-radius: 8px; padding: 10px 14px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #0ea5e9; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-danger { background: #ef4444; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px 8px; font-size: 13px; text-align: left; }
        th { background: #f8fafc; }
        .badge { padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-on { background: #dcfce7; color: #166534; }
        .badge-off { background: #fee2e2; color: #991b1b; }
        .inline-form { display: inline; }
        @media (max-width: 800px) {
            .grid { grid-template-columns: 1fr; }
            table { font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div><strong>Cadastro de Advogados</strong></div>
        <div>
            <a href="painel_administrativo.php">Painel Administrativo</a>
            <a href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="container">
        <?php if ($mensagem !== ''): ?>
            <div class="card <?php echo $tipoMensagem === 'ok' ? 'msg-ok' : 'msg-erro'; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><?php echo $editar ? 'Editar advogado' : 'Novo advogado'; ?></h2>
            <form method="post">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?php echo intval($editar['id'] ?? 0); ?>">

                <div class="grid">
                    <div class="field">
                        <label>Nome</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($editar['nome'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>CNPJ ou CPF</label>
                        <input type="text" name="documento" value="<?php echo htmlspecialchars($editar['documento'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>OAB</label>
                        <input type="text" name="oab" value="<?php echo htmlspecialchars($editar['oab'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>Endereco</label>
                        <input type="text" name="endereco" value="<?php echo htmlspecialchars($editar['endereco'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>Cidade</label>
                        <input type="text" name="cidade" value="<?php echo htmlspecialchars($editar['cidade'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>UF</label>
                        <input type="text" name="uf" maxlength="2" value="<?php echo htmlspecialchars($editar['uf'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>Fone (opcional)</label>
                        <input type="text" name="fone" value="<?php echo htmlspecialchars($editar['fone'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>E-mail (opcional)</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($editar['email'] ?? ''); ?>">
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <label>
                        <input type="checkbox" name="ativo" <?php echo (!isset($editar['ativo']) || intval($editar['ativo']) === 1) ? 'checked' : ''; ?>>
                        Ativo para selecao nos documentos
                    </label>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary"><?php echo $editar ? 'Atualizar' : 'Cadastrar'; ?></button>
                    <a class="btn btn-secondary" href="cadastro_advogados.php" style="text-decoration:none;display:inline-flex;align-items:center;">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Advogados cadastrados</h2>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Documento</th>
                            <th>OAB</th>
                            <th>Cidade/UF</th>
                            <th>Fone</th>
                            <th>E-mail</th>
                            <th>Status</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($advogados)): ?>
                            <tr><td colspan="8">Nenhum advogado cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($advogados as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($item['documento']); ?></td>
                                    <td><?php echo htmlspecialchars($item['oab']); ?></td>
                                    <td><?php echo htmlspecialchars($item['cidade'] . '/' . strtoupper($item['uf'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['fone']); ?></td>
                                    <td><?php echo htmlspecialchars($item['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo intval($item['ativo']) === 1 ? 'badge-on' : 'badge-off'; ?>">
                                            <?php echo intval($item['ativo']) === 1 ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-secondary" style="text-decoration:none;" href="cadastro_advogados.php?editar=<?php echo intval($item['id']); ?>">Editar</a>
                                        <form class="inline-form" method="post" onsubmit="return confirm('Deseja excluir este advogado?');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?php echo intval($item['id']); ?>">
                                            <button type="submit" class="btn btn-danger">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
