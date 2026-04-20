<?php
$grupos_marcadores = ModeloSubstituicao::obterGruposMarcadores();
?>

<div class="page-header">
    <i class="fas fa-file-alt" style="font-size:20px;color:#7f97aa;"></i>
    <h1><?= $titulo_pagina ?></h1>
    <a href="listar_modelos.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para lista</a>
</div>

<div class="container">

    <div id="msgErro" class="alert alert-error" style="display:none;"></div>

    <form id="formModelo" method="POST" action="salvar_modelo_ajax.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="acao" value="<?= htmlspecialchars($acao_form) ?>">
        <?php if ($modelo_id > 0): ?>
        <input type="hidden" name="id" value="<?= $modelo_id ?>">
        <?php endif; ?>

        <div class="form-card">
            <div class="section-title">Identificação</div>
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label for="nome">Nome do modelo *</label>
                    <input type="text" id="nome" name="nome" maxlength="150" required
                           value="<?= htmlspecialchars($modelo_nome) ?>" placeholder="Ex: Procuração Administrativa">
                </div>
                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <select id="categoria" name="categoria">
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                            <?= $cat === $modelo_cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:2;">
                    <label for="descricao">Descrição (opcional)</label>
                    <input type="text" id="descricao" name="descricao" maxlength="255"
                           value="<?= htmlspecialchars($modelo_desc) ?>" placeholder="Breve descrição do modelo">
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="section-title">Marcadores disponíveis — clique para inserir no editor</div>
            <div class="campos-grid">
                <?php foreach ($grupos_marcadores as $grupo => $marcadores): ?>
                <div class="campos-grupo-title"><?= htmlspecialchars($grupo) ?></div>
                <?php foreach ($marcadores as $marc): ?>
                <button type="button" class="btn-campo" onclick="inserirCampo(<?= json_encode($marc) ?>)">
                    <?= htmlspecialchars($marc) ?>
                </button>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-card">
            <div class="section-title">Conteúdo do modelo (HTML)</div>
            <textarea id="conteudo" name="conteudo"><?= htmlspecialchars($modelo_conteudo) ?></textarea>
        </div>

        <div class="footer-form">
            <button type="submit" class="btn btn-primary" id="btnSalvar">
                <i class="fas fa-save"></i>
                <?= $acao_form === 'criar' ? 'Criar Modelo' : 'Salvar Alterações' ?>
            </button>
            <a href="listar_modelos.php" class="btn btn-secondary">Cancelar</a>
            <span id="spinnerSalvar" style="display:none;font-size:13px;color:#7f97aa;">
                <i class="fas fa-spinner fa-spin"></i> Salvando...
            </span>
        </div>
    </form>
</div>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#conteudo',
    language: 'pt_BR',
    height: 560,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'charmap', 'searchreplace',
        'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'table', 'wordcount'
    ],
    toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent | table | link | code fullscreen',
    content_style: "body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.6; }",
    promotion: false,
    branding: false
});

function inserirCampo(marcador) {
    const editor = tinymce.get('conteudo');
    if (editor) {
        editor.insertContent(marcador);
        editor.focus();
    } else {
        const ta = document.getElementById('conteudo');
        const pos = ta.selectionStart;
        ta.value = ta.value.substring(0, pos) + marcador + ta.value.substring(pos);
        ta.selectionStart = ta.selectionEnd = pos + marcador.length;
        ta.focus();
    }
}

document.getElementById('formModelo').addEventListener('submit', async function (e) {
    e.preventDefault();

    const editor = tinymce.get('conteudo');
    if (editor) {
        document.getElementById('conteudo').value = editor.getContent();
    }

    const erroEl = document.getElementById('msgErro');
    erroEl.style.display = 'none';

    const btnSalvar = document.getElementById('btnSalvar');
    const spinner   = document.getElementById('spinnerSalvar');
    btnSalvar.disabled = true;
    spinner.style.display = 'inline-flex';

    try {
        const fd = new FormData(this);
        const resp = await fetch(this.action, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.sucesso) {
            window.location.href = 'listar_modelos.php';
        } else {
            erroEl.textContent = data.mensagem || 'Erro desconhecido.';
            erroEl.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } catch (err) {
        erroEl.textContent = 'Erro de comunicação com o servidor.';
        erroEl.style.display = 'block';
    } finally {
        btnSalvar.disabled = false;
        spinner.style.display = 'none';
    }
});
</script>
