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
    <title>Cálculo CNIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <style>
        body {
            background: #f4f8fb;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .container-cnis {
            max-width: 540px;
            margin: 40px auto 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 32px 28px 24px 28px;
        }
        .cliente-card {
            background: linear-gradient(90deg, #e3f2fd 0%, #f0f8ff 100%);
            border: 1.5px solid #90caf9;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .cliente-card span {
            font-size: 15px;
            color: #0a2540;
        }
        .cliente-card i {
            color: #1976d2;
            margin-right: 7px;
        }
        .titulo-cnis {
            text-align: center;
            font-size: 2rem;
            color: #1976d2;
            margin-bottom: 18px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .form-upload {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 18px;
        }
        .form-upload label {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 4px;
        }
        .form-upload input[type="file"] {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #b3d9ff;
            background: #f0f8ff;
        }
        .form-upload button, .form-upload input[type="submit"] {
            background: linear-gradient(90deg, #1976d2 0%, #64b5f6 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px 0;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .form-upload button:hover, .form-upload input[type="submit"]:hover {
            background: linear-gradient(90deg, #1565c0 0%, #42a5f5 100%);
        }
        @media (max-width: 600px) {
            .container-cnis {
                padding: 12px 4vw 18px 4vw;
            }
            .titulo-cnis {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div style="position:fixed;top:0;left:0;width:100vw;height:54px;background:#1a237e;z-index:100;box-shadow:0 2px 8px #0001;display:flex;align-items:center;">
        <a href="javascript:history.back()" style="margin-left:18px;display:flex;align-items:center;gap:8px;background:#1976d2;color:#fff;padding:8px 18px 8px 14px;border-radius:8px;font-weight:600;font-size:1rem;text-decoration:none;box-shadow:0 2px 8px #1976d255;transition:background 0.2s;position:relative;">
            <i class="fas fa-arrow-left" style="font-size:1.2rem;"></i> Voltar
        </a>
        <div style="flex:1;display:flex;justify-content:center;align-items:center;">
            <span style="color:#fff;font-size:1.25rem;font-weight:700;letter-spacing:1px;">Cálculo CNIS</span>
        </div>
    </div>
    <div style="height:54px;"></div>
    <div class="container-cnis">
        <div class="titulo-cnis"><i class="fas fa-calculator"></i> Cálculo CNIS</div>
        <?php
        $nome = isset($_GET['nome']) ? htmlspecialchars($_GET['nome']) : '';
        $cpf = isset($_GET['cpf']) ? htmlspecialchars($_GET['cpf']) : '';
        $data_nascimento = isset($_GET['data_nascimento']) ? htmlspecialchars($_GET['data_nascimento']) : '';
        $idade = isset($_GET['idade']) ? htmlspecialchars($_GET['idade']) : '';
        if ($nome || $cpf || $data_nascimento) {
            echo '<div class="cliente-card">';
            echo '<span><i class="fas fa-user"></i><b>Nome:</b> ' . $nome . '</span>';
            echo '<span><i class="fas fa-id-card"></i><b>CPF:</b> ' . $cpf . '</span>';
            echo '<span><i class="fas fa-birthday-cake"></i><b>Data de Nascimento:</b> ' . $data_nascimento . '</span>';
            echo '<span><i class="fas fa-hourglass-half"></i><b>Idade:</b> ' . $idade . '</span>';
            echo '</div>';
        }
        ?>
        <form class="form-upload" action="processar_cnis.php" method="post" enctype="multipart/form-data">
            <label for="cnis_file"><i class="fas fa-file-upload"></i> Selecione o arquivo CNIS (PDF ou TXT):</label>
            <input type="file" name="cnis_file" id="cnis_file" accept=".pdf,.txt" required>
            <input type="hidden" name="nome_cliente" value="<?php echo htmlspecialchars($nome); ?>">
            <input type="submit" value="Enviar e Calcular">
        </form>
        <!-- O resultado do cálculo será exibido após o envio do formulário -->
    </div>
</body>
</html>
