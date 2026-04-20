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

// Processar formulário
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tipo_usuario = $_POST['tipo_usuario'] ?? 'usuario';
    $senha = trim($_POST['senha'] ?? '');
    
    if (empty($nome) || empty($email)) {
        $erro = "Nome e email são obrigatórios.";
    } else {
        // Verificar se email já existe em outro usuário
        $sql_check = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $email, $usuario_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $erro = "Este email já está cadastrado em outro usuário.";
        } else {
            // Atualizar usuário
            $is_admin = ($tipo_usuario === 'admin') ? 1 : 0;
            
            if (!empty($senha)) {
                // Atualizar com nova senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql_update = "UPDATE usuarios SET nome = ?, email = ?, senha = ?, is_admin = ?, tipo_usuario = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sssisi", $nome, $email, $senha_hash, $is_admin, $tipo_usuario, $usuario_id);
            } else {
                // Atualizar sem alterar senha
                $sql_update = "UPDATE usuarios SET nome = ?, email = ?, is_admin = ?, tipo_usuario = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("ssisi", $nome, $email, $is_admin, $tipo_usuario, $usuario_id);
            }
            
            if ($stmt_update->execute()) {
                $mensagem = "Usuário atualizado com sucesso!";
                // Atualizar dados exibidos
                $usuario['nome'] = $nome;
                $usuario['email'] = $email;
                $usuario['tipo_usuario'] = $tipo_usuario;
                $usuario['is_admin'] = $is_admin;
            } else {
                $erro = "Erro ao atualizar usuário: " . $conn->error;
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário</title>
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
            color: #1e3c72;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .btn-voltar {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .btn-voltar:hover {
            background: #5a6268;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
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
        
        .btn-primary {
            background: #1e3c72;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2a5298;
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
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="listar_usuarios.php" class="btn-voltar">← Voltar para Gerenciador de Usuários</a>
        
        <h1 class="page-title">Editar Usuário</h1>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Nova Senha:</label>
                <input type="password" id="senha" name="senha">
                <div class="info-text">Deixe em branco para manter a senha atual</div>
            </div>
            
            <div class="form-group">
                <label for="tipo_usuario">Tipo de Usuário:</label>
                <select id="tipo_usuario" name="tipo_usuario" required>
                    <option value="admin" <?php echo ($usuario['tipo_usuario'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                    <option value="parceiro" <?php echo ($usuario['tipo_usuario'] === 'parceiro') ? 'selected' : ''; ?>>Parceiro</option>
                    <option value="usuario" <?php echo ($usuario['tipo_usuario'] === 'usuario') ? 'selected' : ''; ?>>Usuário</option>
                </select>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="listar_usuarios.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
