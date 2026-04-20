<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Resultado do Cálculo CNIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <style>
        html, body { height: 100%; min-height: 100%; }
        body { min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .container-cnis { max-width: 900px; min-width: 320px; width: 98vw; margin: 24px auto 0 auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 18px 18px 18px 18px; position: relative; min-height: 80vh; }
        .titulo-cnis { text-align: center; font-size: 2rem; color: #1976d2; margin-bottom: 18px; font-weight: 700; letter-spacing: 1px; }
        .card-sucesso { background: linear-gradient(90deg, #e3fcec 0%, #e0f7fa 100%); border: 1.5px solid #81c784; border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; color: #1976d2; font-size: 1.05rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .card-sucesso i { color: #43a047; font-size: 1.3rem; }
        .card-erro { background: #ffebee; border: 1.5px solid #e57373; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; color: #c62828; font-size: 1.05rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .card-erro i { color: #c62828; font-size: 1.3rem; }
        .tempo-total { text-align: center; font-size: 1.15rem; color: #1565c0; font-weight: 700; margin: 12px 0 8px 0; }
        .tabela-periodos { width: 100%; border-collapse: collapse; margin-top: 10px; background: #f8fafc; border-radius: 8px; overflow: hidden; font-size: 0.98rem; }
        .tabela-periodos th, .tabela-periodos td { padding: 7px 4px; text-align: center; border-bottom: 1px solid #e0e0e0; }
        .tabela-periodos th { background: #e3f2fd; color: #1976d2; font-weight: 700; font-size: 1rem; }
        .tabela-periodos tr:last-child td { border-bottom: none; }
        .grafico-pizza-cnis-fixed {
            position: fixed;
            top: 64px;
            right: 16px;
            z-index: 101;
            width: 180px;
            max-width: 45vw;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px #e3f2fd;
            padding: 10px 4px 4px 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.2s;
        }
        @media (max-width: 1100px) {
            .container-cnis { max-width: 99vw; padding: 8px 2vw; }
            .grafico-pizza-cnis-fixed { width: 140px; max-width: 60vw; }
        }
        @media (max-width: 900px) {
            .grafico-pizza-cnis-fixed {
                position: static;
                margin: 0 auto 10px auto;
                width: 90vw;
                max-width: 340px;
                top: unset;
                right: unset;
            }
        }
        @media (max-width: 600px) {
            .container-cnis { padding: 4px 1vw 8px 1vw; }
            .titulo-cnis { font-size: 1.1rem; }
            .grafico-pizza-cnis-fixed { width: 98vw; max-width: 99vw; padding: 4px 1vw 4px 1vw; }
            .tabela-periodos th, .tabela-periodos td { padding: 4px 2px; font-size: 0.92rem; }
        }
    </style>
</head>
<body>
<div class="container-cnis" style="position:relative;">
    <!-- Barra azul principal -->
    <div id="barra-azul-fixa" style="position:fixed;top:0;left:0;width:100vw;height:54px;background:#1a237e;z-index:100;box-shadow:0 2px 8px #0001;display:flex;align-items:center;">
        <a href="javascript:history.back()" style="margin-left:18px;display:flex;align-items:center;gap:8px;background:#1976d2;color:#fff;padding:8px 18px 8px 14px;border-radius:8px;font-weight:600;font-size:1rem;text-decoration:none;box-shadow:0 2px 8px #1976d255;transition:background 0.2s;position:relative;">
            <i class="fas fa-arrow-left" style="font-size:1.2rem;"></i> Voltar
        </a>
        <div style="flex:1;display:flex;justify-content:center;align-items:center;">
            <span style="color:#fff;font-size:1.25rem;font-weight:700;letter-spacing:1px;">Resultado do Cálculo CNIS</span>
        </div>
        <button onclick="gerarPDFPaisagem()" style="margin-right:18px;background:#43a047;color:#fff;padding:8px 18px 8px 14px;border-radius:8px;font-weight:600;font-size:1rem;display:flex;align-items:center;gap:8px;border:none;box-shadow:0 2px 8px #43a04755;cursor:pointer;transition:background 0.2s;">
            <i class="fas fa-file-pdf"></i> Gerar PDF
        </button>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
    function gerarPDFPaisagem() {
        const barraAzulFixa = document.getElementById('barra-azul-fixa');
        const barraAzul = document.getElementById('barra-azul-pdf');
        const graficoPizza = document.querySelector('.grafico-pizza-cnis-fixed');
        if (graficoPizza) graficoPizza.style.display = 'none';
        barraAzulFixa.style.display = 'none';
        // barraAzul.style.display = 'flex'; // Removido: não existe mais barra azul menor
        const container = document.querySelector('.container-cnis');
        html2canvas(container, {scale:2}).then(canvas => {
            // barraAzul.style.display = 'none';
            barraAzulFixa.style.display = 'flex';
            if (graficoPizza) graficoPizza.style.display = '';
            const imgData = canvas.toDataURL('image/png');
            const pdf = new window.jspdf.jsPDF({orientation: 'landscape', unit: 'pt', format: 'a4'});
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const imgWidth = pageWidth - 40;
            const imgHeight = canvas.height * imgWidth / canvas.width;
            pdf.addImage(imgData, 'PNG', 20, 20, imgWidth, imgHeight);
            // Rodapé
            const rodape = 'Real Assessoria Previdenciária\nCNPJ: 13.244.474/0001-20\nwww.realprevidencia.com.br';
            pdf.setFontSize(12);
            pdf.setTextColor('#1976d2');
            const lines = pdf.splitTextToSize(rodape, imgWidth);
            let y = pageHeight - 40;
            lines.forEach((line, i) => {
                pdf.text(line, pageWidth/2, y + i*16, {align: 'center'});
            });
            pdf.save('calculo_cnis.pdf');
        });
    }
    </script>
    <?php
    // processar_cnis.php
    // Recebe o arquivo enviado pelo formulário e salva no servidor

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['cnis_file']) && $_FILES['cnis_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['cnis_file']['tmp_name'];
            $fileName = $_FILES['cnis_file']['name'];
            $fileSize = $_FILES['cnis_file']['size'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $allowedMimeTypes = array(
                'pdf' => array('application/pdf'),
                'txt' => array('text/plain', 'text/x-log', 'application/octet-stream'),
            );
            $maxFileSize = 10 * 1024 * 1024;

            $allowedfileExtensions = array('pdf', 'txt');
            if ($fileSize > $maxFileSize) {
                echo '<div class="card-erro"><i class="fas fa-exclamation-triangle"></i>Erro: arquivo excede o limite de 10 MB.</div>';
            } elseif (in_array($fileExtension, $allowedfileExtensions, true)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realMimeType = $finfo ? finfo_file($finfo, $fileTmpPath) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                if (!isset($allowedMimeTypes[$fileExtension]) || !in_array($realMimeType, $allowedMimeTypes[$fileExtension], true)) {
                    echo '<div class="card-erro"><i class="fas fa-exclamation-triangle"></i>Erro: tipo de arquivo invalido.</div>';
                    return;
                }

                $uploadFileDir = __DIR__ . '/uploads_cnis/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $newFileName = uniqid('cnis_', true) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $nome_cliente = isset($_POST['nome_cliente']) ? htmlspecialchars($_POST['nome_cliente']) : '';
                    echo '<div class="card-sucesso"><i class="fas fa-check-circle"></i>Arquivo enviado com sucesso!';
                    if ($nome_cliente) {
                        echo '<br><span style="font-size:14px;font-weight:600;color:#1976d2;">Cliente: ' . $nome_cliente . '</span>';
                    }
                    echo '<br><span style="font-size:13px;font-weight:400;color:#1976d2;">Arquivo salvo como: ' . htmlspecialchars($newFileName) . '</span></div>';

                    if ($fileExtension === 'txt') {
                        require_once __DIR__ . '/parser_cnis_txt.php';
                        $resultado = parse_cnis_txt($dest_path);
                    } elseif ($fileExtension === 'pdf') {
                        require_once __DIR__ . '/parser_cnis_pdf.php';
                        $resultado = parse_cnis_pdf($dest_path);
                    } else {
                        $resultado = null;
                    }
                    if (isset($resultado['error'])) {
                        echo '<div class="card-erro"><i class="fas fa-exclamation-triangle"></i>Erro: ' . htmlspecialchars($resultado['error']) . '</div>';
                    } else {
                        echo '<div class="tempo-total"><i class="fas fa-clock"></i> Tempo total de contribuição:<br><span style="font-size:1.5rem;color:#388e3c;">' . htmlspecialchars($resultado['tempo_formatado']) . '</span></div>';
                        if (!empty($resultado['periodos'])) {
                            echo '<table class="tabela-periodos">';
                            echo '<tr><th>Início</th><th>Fim</th><th>Dias</th></tr>';
                            foreach ($resultado['periodos'] as $p) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($p['inicio']) . '</td>';
                                echo '<td>' . htmlspecialchars($p['fim']) . '</td>';
                                echo '<td>' . htmlspecialchars($p['dias']) . '</td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        } else {
                            echo '<div style="text-align:center;color:#c62828;font-weight:600;margin-top:18px;">Nenhum período encontrado.</div>';
                        }
                        // Gráfico de barras dos períodos
                        if (!empty($resultado['periodos'])) {
                            echo '<div class="grafico-cnis" style="margin:24px 0 0 0;"><canvas id="graficoPeriodos"></canvas></div>';
                            $labels = [];
                            $dias = [];
                            foreach ($resultado['periodos'] as $i => $p) {
                                $labels[] = ($i+1) . 'º vínculo';
                                $dias[] = $p['dias'];
                            }
                            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
                            echo '<script>document.addEventListener("DOMContentLoaded",function(){
                                var ctx = document.getElementById("graficoPeriodos").getContext("2d");
                                new Chart(ctx, {
                                    type: "bar",
                                    data: {
                                        labels: ' . json_encode($labels) . ',
                                        datasets: [{
                                            label: "Dias de contribuição por vínculo",
                                            data: ' . json_encode($dias) . ',
                                            backgroundColor: "#1976d2",
                                            borderRadius: 6,
                                        }]
                                    },
                                    options: {
                                        plugins: { legend: { display: false } },
                                        scales: { y: { beginAtZero: true, grid: { color: "#e3f2fd" } }, x: { grid: { color: "#e3f2fd" } } },
                                        responsive: true,
                                    }
                            });</script>';
                        }
                        // Gráfico de pizza dos anos, meses e dias
                        $anos = isset($resultado['anos']) ? (int)$resultado['anos'] : 0;
                        $meses = isset($resultado['meses']) ? (int)$resultado['meses'] : 0;
                        $dias_restantes = isset($resultado['dias_restantes']) ? (int)$resultado['dias_restantes'] : 0;
                        // fallback para compatibilidade
                        if (($anos + $meses + $dias_restantes) === 0 && !empty($resultado['tempo_formatado'])) {
                            preg_match('/(\d+)\s*anos?/', $resultado['tempo_formatado'], $ma);
                            preg_match('/(\d+)\s*meses?/', $resultado['tempo_formatado'], $mm);
                            preg_match('/(\d+)\s*dias?/', $resultado['tempo_formatado'], $md);
                            $anos = isset($ma[1]) ? (int)$ma[1] : 0;
                            $meses = isset($mm[1]) ? (int)$mm[1] : 0;
                            $dias_restantes = isset($md[1]) ? (int)$md[1] : 0;
                        }
                        if (($anos + $meses + $dias_restantes) > 0) {
                            echo '<div class="grafico-pizza-cnis-fixed">';
                            echo '<canvas id="graficoPizzaTempo" width="220" height="220"></canvas>';
                            echo '<div style="font-size:15px;color:#1976d2;font-weight:600;margin-top:8px;">Distribuição do tempo</div>';
                            echo '</div>';
                            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
                            echo '<script>document.addEventListener("DOMContentLoaded",function(){
                                var ctxPizza = document.getElementById("graficoPizzaTempo").getContext("2d");
                                new Chart(ctxPizza, {
                                    type: "pie",
                                    data: {
                                        labels: ["Anos", "Meses", "Dias"],
                                        datasets: [{
                                            data: ['.$anos.','.$meses.','.$dias_restantes.'],
                                            backgroundColor: ["#1976d2", "#43a047", "#fbc02d"],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        plugins: {
                                            legend: { display: true, position: "bottom" }
                                        }
                                    }
                                });
                            });</script>';
                        }
                    }


                } else {
                    echo '<div class="card-erro"><i class="fas fa-exclamation-triangle"></i>Erro ao mover o arquivo para o diretório de upload.</div>';
                }
            } else {
                echo '<div class="card-erro"><i class="fas fa-exclamation-triangle"></i>Tipo de arquivo não permitido. Envie um PDF ou TXT.</div>';
            }
        } else {
            echo '<div class="card-erro"><i class="fas fa-exclamation-triangle"></i>Erro no upload do arquivo.</div>';
        }
    } else {
        echo '<div class="card-erro"><i class="fas fa-exclamation-triangle"></i>Erro no upload do arquivo.</div>';
    }
?>
</div>
</body>
</html>