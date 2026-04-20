<?php
session_start();

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar se o usuário é administrador
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: dashboard.php");
    exit();
}

$usuario_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($usuario_id <= 0) {
    header("Location: listar_usuarios.php");
    exit();
}

// Impedir que o usuário exclua a si mesmo
if ($usuario_id == $_SESSION['usuario_id']) {
    $_SESSION['erro'] = "Você não pode excluir seu próprio usuário.";
    header("Location: listar_usuarios.php");
    exit();
}

// Buscar dados do usuário
$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header("Location: listar_usuarios.php");
    exit();
}

// Processar exclusão
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar'])) {
        // Verificar se há clientes cadastrados por este usuário
        $sql_check = "SELECT COUNT(*) as total FROM clientes WHERE usuario_cadastro_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $usuario_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $count = $result_check->fetch_assoc()['total'];
        $stmt_check->close();
        
        if ($count > 0) {
            $erro = "Este usuário possui $count cliente(s) cadastrado(s). Não é possível excluir.";
        } else {
            // Excluir usuário
            $sql_delete = "DELETE FROM usuarios WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $usuario_id);
            
            if ($stmt_delete->execute()) {
                $_SESSION['mensagem'] = "Usuário excluído com sucesso!";
                header("Location: listar_usuarios.php");
                exit();
            } else {
                $erro = "Erro ao excluir usuário: " . $conn->error;
            }
            $stmt_delete->close();
        }
    } else {
        header("Location: listar_usuarios.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Usuário</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .page-title {
            text-align: center;
            color: #dc3545;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .warning-box p {
            margin: 10px 0;
            color: #856404;
        }
        
        .warning-box strong {
            color: #dc3545;
            font-size: 18px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .user-info p {
            margin: 5px 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1 class="page-title">⚠️ Excluir Usuário</h1>
        
        <?php if ($erro): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        
        <div class="warning-box">
            <p><strong>ATENÇÃO!</strong></p>
            <p>Você está prestes a excluir este usuário permanentemente.</p>
            <p>Esta ação não pode ser desfeita.</p>
        </div>
        
        <div class="user-info">
            <p><strong>Nome:</strong> <?php echo htmlspecialchars($usuario['nome']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
            <p><strong>Tipo:</strong> <?php 
                $tipos = ['admin' => 'Administrador', 'parceiro' => 'Parceiro', 'usuario' => 'Usuário'];
                echo $tipos[$usuario['tipo_usuario']] ?? $usuario['tipo_usuario']; 
            ?></p>
        </div>
        
        <form method="POST">
            <div class="btn-group">
                <button type="submit" name="confirmar" value="1" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este usuário?')">Confirmar Exclusão</button>
                <a href="listar_usuarios.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
