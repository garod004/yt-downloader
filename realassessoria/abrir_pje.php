<?php
$cpf = isset($_POST['cpf']) ? preg_replace('/\D/', '', $_POST['cpf']) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrindo PJE...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .cpf-info {
            font-size: 18px;
            margin-top: 20px;
            font-weight: bold;
        }
        .instructions {
            margin-top: 20px;
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Abrindo PJE - Tribunal Regional Federal</h2>
        <div class="spinner"></div>
        <div class="cpf-info">CPF: <?php echo htmlspecialchars($cpf); ?></div>
        <div class="instructions">
            O site do PJE será aberto em uma nova aba.<br>
            Cole o CPF no campo de consulta: <strong><?php echo htmlspecialchars($cpf); ?></strong>
        </div>
    </div>

    <script>
        // Copiar CPF para área de transferência
        var cpf = '<?php echo $cpf; ?>';

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        // Criar input invisível para copiar o CPF (método alternativo mais confiável)
        function copiarCPF() {
            var textarea = document.createElement('textarea');
            textarea.value = cpf;
            textarea.style.position = 'fixed';
            textarea.style.left = '-999999px';
            textarea.style.top = '-999999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            
            try {
                var sucesso = document.execCommand('copy');
                console.log('Tentativa de cópia:', sucesso);
                if (sucesso) {
                    var safeCpf = escapeHtml(cpf);
                    document.querySelector('.instructions').innerHTML = 
                        '✅ <strong>CPF COPIADO!</strong> Cole com Ctrl+V no site do PJE<br><strong>CPF: ' + safeCpf + '</strong><br><br>' +
                        '<div style="background:rgba(255,255,255,0.2);padding:15px;border-radius:8px;margin:15px 0;">' +
                        '<p style="margin:5px 0;font-size:16px;">Clique para copiar novamente:</p>' +
                        '<input type="text" value="' + safeCpf + '" id="cpfInput" readonly style="width:200px;padding:8px;text-align:center;font-size:16px;font-weight:bold;border:2px solid #fff;border-radius:5px;background:#fff;color:#007bff;" onclick="this.select();document.execCommand(\'copy\');alert(\'CPF copiado!\');">' +
                        '</div>' +
                        '<button onclick="window.close()" style="margin:10px;padding:10px 20px;background:#fff;color:#007bff;border:none;border-radius:5px;cursor:pointer;font-weight:bold;">Fechar</button>';
                } else {
                    mostrarInputManual();
                }
            } catch (err) {
                console.error('Erro ao copiar:', err);
                mostrarInputManual();
            }
            
            document.body.removeChild(textarea);
        }
        
        function mostrarInputManual() {
            var safeCpf = escapeHtml(cpf);
            document.querySelector('.instructions').innerHTML = 
                '⚠️ Copie o CPF manualmente:<br><br>' +
                '<div style="background:rgba(255,255,255,0.2);padding:15px;border-radius:8px;margin:15px 0;">' +
                '<input type="text" value="' + safeCpf + '" id="cpfInput" readonly style="width:200px;padding:12px;text-align:center;font-size:18px;font-weight:bold;border:2px solid #fff;border-radius:5px;background:#fff;color:#007bff;" onclick="this.select();document.execCommand(\'copy\');alert(\'CPF copiado!\');">' +
                '<br><small style="margin-top:10px;display:block;">Clique no campo acima para selecionar e copiar</small>' +
                '</div>' +
                '<button onclick="window.close()" style="margin:10px;padding:10px 20px;background:#fff;color:#007bff;border:none;border-radius:5px;cursor:pointer;font-weight:bold;">Fechar</button>';
        }
        
        // Esperar página carregar completamente antes de copiar
        window.addEventListener('load', function() {
            setTimeout(copiarCPF, 100);
        });
        
        // Abrir PJE em nova aba ANTES de tentar copiar (importante!)
        window.open('https://pje1g.trf1.jus.br/consultapublica/ConsultaPublica/listView.seam', '_blank');
    </script>
</body>
</html>
