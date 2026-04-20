<?php
// Variáveis esperadas: $titulo_pagina, $acao_form, $adv (array), $csrf
$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA',
        'PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>

<div class="page-header">
    <i class="fas fa-user-tie" style="font-size:20px;color:#7f97aa;"></i>
    <h1><?= $titulo_pagina ?></h1>
    <a href="gerenciar_advogados.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="container">
    <div id="msgErro" class="alert-error"></div>

    <form id="formAdvogado" method="POST" action="salvar_advogado_ajax.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="acao" value="<?= htmlspecialchars($acao_form) ?>">
        <?php if ((int)($adv['id'] ?? 0) > 0): ?>
        <input type="hidden" name="id" value="<?= (int)$adv['id'] ?>">
        <?php endif; ?>

        <div class="form-card">
            <div class="section-title">Dados do Advogado</div>

            <div class="form-row">
                <div class="form-group" style="flex:3;">
                    <label for="nome">Nome completo *</label>
                    <input type="text" id="nome" name="nome" maxlength="255" required
                           value="<?= htmlspecialchars($adv['nome'] ?? '') ?>">
                </div>
                <div class="form-group" style="flex:2;">
                    <label for="oab">OAB *</label>
                    <input type="text" id="oab" name="oab" maxlength="80" required
                           placeholder="Ex: OAB/AM 12345"
                           value="<?= htmlspecialchars($adv['oab'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="documento">CPF / CNPJ *</label>
                    <input type="text" id="documento" name="documento" maxlength="20" required
                           placeholder="000.000.000-00"
                           value="<?= htmlspecialchars($adv['documento'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="fone">Telefone *</label>
                    <input type="text" id="fone" name="fone" maxlength="30" required
                           placeholder="(92) 9 9999-9999"
                           value="<?= htmlspecialchars($adv['fone'] ?? '') ?>">
                </div>
                <div class="form-group" style="flex:2;">
                    <label for="email">E-mail *</label>
                    <input type="email" id="email" name="email" maxlength="180" required
                           value="<?= htmlspecialchars($adv['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:3;">
                    <label for="endereco">Endereço *</label>
                    <input type="text" id="endereco" name="endereco" maxlength="255" required
                           value="<?= htmlspecialchars($adv['endereco'] ?? '') ?>">
                </div>
                <div class="form-group" style="flex:2;">
                    <label for="cidade">Cidade *</label>
                    <input type="text" id="cidade" name="cidade" maxlength="120" required
                           value="<?= htmlspecialchars($adv['cidade'] ?? '') ?>">
                </div>
                <div class="form-group" style="flex:0 0 90px;min-width:80px;">
                    <label for="uf">UF *</label>
                    <select id="uf" name="uf">
                        <?php foreach ($ufs as $uf): ?>
                        <option value="<?= $uf ?>" <?= ($adv['uf'] ?? 'AM') === $uf ? 'selected' : '' ?>>
                            <?= $uf ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($acao_form === 'editar'): ?>
            <div class="form-row">
                <div class="form-group" style="flex:0 0 auto;min-width:140px;">
                    <label for="ativo">Status</label>
                    <select id="ativo" name="ativo">
                        <option value="1" <?= ($adv['ativo'] ?? 1) == 1 ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= ($adv['ativo'] ?? 1) == 0 ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="footer-form">
                <button type="submit" class="btn btn-primary" id="btnSalvar">
                    <i class="fas fa-save"></i>
                    <?= $acao_form === 'criar' ? 'Cadastrar' : 'Salvar Alterações' ?>
                </button>
                <a href="gerenciar_advogados.php" class="btn btn-secondary">Cancelar</a>
                <span id="spinner" style="display:none;font-size:13px;color:#7f97aa;">
                    <i class="fas fa-spinner fa-spin"></i> Salvando...
                </span>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('formAdvogado').addEventListener('submit', async function (e) {
    e.preventDefault();
    const erroEl  = document.getElementById('msgErro');
    const btnSalv = document.getElementById('btnSalvar');
    const spinner = document.getElementById('spinner');
    erroEl.style.display = 'none';
    btnSalv.disabled = true;
    spinner.style.display = 'inline-flex';
    try {
        const resp = await fetch(this.action, { method: 'POST', body: new FormData(this) });
        const data = await resp.json();
        if (data.sucesso) {
            window.location.href = 'gerenciar_advogados.php';
        } else {
            erroEl.textContent = data.mensagem || 'Erro desconhecido.';
            erroEl.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } catch (err) {
        erroEl.textContent = 'Erro de comunicação com o servidor.';
        erroEl.style.display = 'block';
    } finally {
        btnSalv.disabled = false;
        spinner.style.display = 'none';
    }
});
</script>
