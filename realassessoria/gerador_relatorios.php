<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}

$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$cliente_id_pre = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Relatórios - MeuSIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --line: rgba(146, 194, 243, 0.2);
            --line-strong: rgba(146, 194, 243, 0.32);
            --text: #e7f3ff;
            --muted: #8eb2d6;
        }

        body {
            font-family: 'Barlow', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(1100px 550px at 100% -20%, rgba(27, 178, 244, 0.18), transparent),
                radial-gradient(700px 420px at -15% 20%, rgba(36, 214, 162, 0.12), transparent),
                linear-gradient(180deg, #081526 0%, #06101d 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #e7f3ff;
        }

        .modal-container {
            background: linear-gradient(180deg, rgba(17, 40, 67, 0.96), rgba(11, 28, 47, 0.96));
            border-radius: 18px;
            box-shadow: 0 26px 68px rgba(0, 0, 0, 0.42);
            width: 100%;
            max-width: 980px;
            min-height: 620px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--line);
            backdrop-filter: blur(6px);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 18px;
            background: linear-gradient(135deg, rgba(27, 178, 244, 0.2), rgba(36, 214, 162, 0.18));
            color: var(--text);
            border-bottom: 1px solid var(--line);
        }

        .modal-header h2 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0.4px;
            font-family: 'Barlow', 'Segoe UI', sans-serif;
        }

        .modal-subtitle {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 500;
        }

        .modal-close {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--line-strong);
            background: rgba(8, 24, 41, 0.85);
            color: #cbe4ff;
            cursor: pointer;
            font-size: 28px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .modal-close:hover {
            background: #12385c;
            color: #f0f8ff;
        }

        .documentos-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 14px;
            padding: 22px;
            background: rgba(8, 24, 41, 0.45);
            flex: 1;
            align-content: start;
        }

        .btn-documento {
            background: linear-gradient(180deg, rgba(18, 43, 72, 0.95), rgba(12, 29, 48, 0.95));
            color: #e7f3ff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 18px 10px;
            min-height: 96px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .btn-documento::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: currentColor;
            opacity: 0.55;
        }

        .btn-documento:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 24px rgba(0, 0, 0, 0.38);
            border-color: rgba(36, 214, 162, 0.45);
        }

        .btn-documento i {
            font-size: 30px;
        }

        .btn-titulo {
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: 0.3px;
            text-transform: none;
        }

        .btn-relatorio {
            color: #d7ebff;
            border-color: rgba(146, 194, 243, 0.22);
        }

        .btn-pdf {
            color: #7cd4ff;
            border-color: rgba(27, 178, 244, 0.28);
        }

        .btn-cliente {
            color: #8bf2cc;
            border-color: rgba(36, 214, 162, 0.30);
        }

        .btn-financeiro {
            color: #ffd184;
            border-color: rgba(255, 209, 132, 0.32);
        }

        .btn-justica {
            color: #d3b0ff;
            border-color: rgba(211, 176, 255, 0.34);
        }

        .modal-footer {
            padding: 18px 20px;
            border-top: 1px solid var(--line);
            background: rgba(8, 24, 41, 0.45);
            display: flex;
            justify-content: flex-end;
        }

        .btn-secondary {
            background: linear-gradient(130deg, #123357 0%, #0e2844 100%);
            color: #d8eaff;
            border: 1px solid rgba(146, 194, 243, 0.25);
            padding: 10px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
        }

        .btn-secondary:hover {
            background: linear-gradient(130deg, #1b4a77 0%, #12385c 100%);
            color: #f0f8ff;
        }

        @media (max-width: 900px) {
            .documentos-grid {
                grid-template-columns: repeat(3, minmax(140px, 1fr));
            }
        }

        @media (max-width: 700px) {
            .modal-header h2 {
                font-size: 20px;
            }

            .documentos-grid {
                grid-template-columns: repeat(2, minmax(120px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="modal-container">
        <div class="modal-header">
            <div>
                <h2>Gerador de Relatorios</h2>
                <p class="modal-subtitle">Emissao rapida de documentos, relatorios e prontuarios</p>
            </div>
            <a class="modal-close" href="listar_clientes.php" title="Fechar">&times;</a>
        </div>

        <div class="documentos-grid">
            <a class="btn-documento btn-relatorio" href="relatorio_clientes.php">
                <i class="fas fa-table-list"></i>
                <span class="btn-titulo">Relatório por Status e Benefício</span>
            </a>

            <a class="btn-documento btn-pdf" href="gerar_relatorio_pdf.php">
                <i class="fas fa-file-pdf"></i>
                <span class="btn-titulo">PDF Relatorio de Clientes</span>
            </a>

            <button class="btn-documento btn-pdf" type="button" onclick="abrirRelatorioIndicador()">
                <i class="fas fa-file-pdf"></i>
                <span class="btn-titulo">Relatório por Indicador</span>
            </button>

            <a class="btn-documento btn-justica" href="relatorio_justica.php">
                <i class="fas fa-scale-balanced"></i>
                <span class="btn-titulo">Relatorio de Justica</span>
            </a>

            <a class="btn-documento btn-pdf" href="gerar_relatorio_status.php?status=APROVADO">
                <i class="fas fa-file-pdf"></i>
                <span class="btn-titulo">Relatório de Aprovados</span>
            </a>

            <a class="btn-documento btn-pdf" href="gerar_relatorio_status.php?status=PAGANDO">
                <i class="fas fa-file-pdf"></i>
                <span class="btn-titulo">Relatório de Pagando</span>
            </a>

            <a class="btn-documento btn-pdf" href="gerar_relatorio_status.php?status=PAGO">
                <i class="fas fa-file-pdf"></i>
                <span class="btn-titulo">Relatório de Pagos</span>
            </a>


        </div>

        <div class="modal-footer">
            <a class="btn-secondary" href="listar_clientes.php">Fechar</a>
        </div>
    </div>

    <script>
        const clienteIdInicial = <?php echo $cliente_id_pre > 0 ? $cliente_id_pre : 'null'; ?>;

        function obterClienteIdParaRelatorio() {
            if (clienteIdInicial && Number(clienteIdInicial) > 0) {
                return Number(clienteIdInicial);
            }

            const idInformado = window.prompt('Informe o código do cliente:');
            if (!idInformado) {
                return null;
            }

            const idNumerico = Number(idInformado);
            if (!Number.isInteger(idNumerico) || idNumerico <= 0) {
                alert('Código do cliente inválido.');
                return null;
            }

            return idNumerico;
        }

        function abrirRelatorioCliente() {
            const clienteId = obterClienteIdParaRelatorio();
            if (!clienteId) return;
            window.location.href = 'gerar_relatorio_cliente.php?id=' + encodeURIComponent(clienteId);
        }

        function abrirRelatorioAgendamentoCliente() {
            const clienteId = obterClienteIdParaRelatorio();
            if (!clienteId) return;
            window.location.href = 'gerar_relatorio_agendamentos.php?id=' + encodeURIComponent(clienteId);
        }

        function abrirProntuarioFinanceiro() {
            const clienteId = obterClienteIdParaRelatorio();
            if (!clienteId) return;
            window.location.href = 'gerar_prontuario_financeiro.php?id=' + encodeURIComponent(clienteId);
        }

        function abrirRelatorioIndicador() {
            const indicador = window.prompt('Informe o nome do indicador (deixe em branco para todos):');
            if (indicador === null) return;
            const url = 'gerar_pdf_clientes_filtrados.php' + (indicador.trim() !== '' ? '?filtro_indicador=' + encodeURIComponent(indicador.trim()) : '');
            window.location.href = url;
        }
    </script>
</body>
</html>