<?php

function renderizarHtmlProcuracaoAdministrativa(array $dados) {
    $tituloDocumento = $dados['titulo_documento'] ?? 'PROCURAÇÃO';
    $subtituloDocumento = $dados['subtitulo_documento'] ?? 'DOCUMENTO';
    $outorganteHtml = $dados['outorgante_html'] ?? '';
    $outorgadoHtml = $dados['outorgado_html'] ?? '';
    $poderesHtml = $dados['poderes_html'] ?? '';
    $assinaturasHtml = $dados['assinaturas_html'] ?? '';
    $cssExtra = $dados['css_extra'] ?? '';

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>{$tituloDocumento}</title>
    <style>
        @page {
            margin: 3mm 5mm;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #C8956E;
        }
        .header-left h1 {
            font-size: 28pt;
            font-weight: bold;
            color: #4A3524;
            margin: 0 0 5px 0;
            letter-spacing: 1px;
        }
        .header-left p {
            font-size: 11pt;
            color: #666;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .section-title {
            background: #C8956E;
            color: white;
            padding: 8px 15px;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 0 10px 0;
        }
        .content-box {
            background: #f9f9f9;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #C8956E;
            text-align: justify;
            line-height: 1.6;
        }
        .content-box p {
            margin: 0;
        }
        .signatures {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-date {
            text-align: center;
            margin-bottom: 40px;
            font-size: 10pt;
        }
        .signature-line {
            border-top: 2px solid #333;
            width: 350px;
            margin: 50px auto 8px auto;
        }
        .signature-name {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            margin-top: 5px;
        }
        .signature-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-top: 35px;
        }
        .signature-grid.two-columns .signature-item {
            display: table-cell;
            width: 50%;
            padding: 0 8px;
            vertical-align: bottom;
        }
        .signature-grid.two-columns .signature-line {
            width: 100%;
            margin: 40px 0 8px 0;
        }
        .footer-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 15px;
            background: #C8956E;
        }
        {$cssExtra}
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>{$tituloDocumento}</h1>
            <p>{$subtituloDocumento}</p>
        </div>
    </div>

    <div class="section-title">OUTORGANTE:</div>
    <div class="content-box">{$outorganteHtml}</div>

    <div class="section-title">OUTORGADO:</div>
    <div class="content-box">{$outorgadoHtml}</div>

    <div class="section-title">PODERES:</div>
    <div class="content-box">{$poderesHtml}</div>

    {$assinaturasHtml}

    <div class="footer-bar"></div>
</body>
</html>
HTML;
}

function renderizarAssinaturaProcuracaoPadrao($cidade, $uf, $data, $nome, $cpf) {
    return <<<HTML
<div class="signatures">
    <div class="signature-date">{$cidade} - {$uf}, {$data}</div>
    <div class="signature-line"></div>
    <div class="signature-name">OUTORGANTE: {$nome}<br>CPF: {$cpf}</div>
</div>
HTML;
}

function renderizarAssinaturaProcuracaoARogo($cidade, $uf, $data, $nomeCliente, $cpfCliente, $nomeRogo, $cpfRogo) {
    return <<<HTML
<div class="signatures">
    <div class="signature-date">{$cidade} - {$uf}, {$data}</div>
    <div class="signature-grid two-columns">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-name">{$nomeCliente}<br>CPF: {$cpfCliente}<br>(Outorgante - assina a rogo)</div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-name">{$nomeRogo}<br>CPF: {$cpfRogo}<br>(Assina a rogo)</div>
        </div>
    </div>
    <div class="signature-grid two-columns">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-name">Testemunha 1</div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-name">Testemunha 2</div>
        </div>
    </div>
</div>
HTML;
}