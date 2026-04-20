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

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
    $tipo_usuario = trim($_POST['tipo_usuario'] ?? 'usuario');
    $is_admin = ($tipo_usuario === 'admin') ? 1 : 0;
    
    // Validações
    if (empty($nome) || empty($email) || empty($senha)) {
        $mensagem = "❌ Todos os campos são obrigatórios.";
    } elseif ($senha !== $confirmar_senha) {
        $mensagem = "❌ As senhas não coincidem.";
    } elseif (strlen($senha) < 6) {
        $mensagem = "❌ A senha deve ter no mínimo 6 caracteres.";
    } else {
        // Verificar se o email já existe
        $sql_check = "SELECT id FROM usuarios WHERE email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $mensagem = "❌ Este email já está cadastrado.";
        } else {
            // Hash da senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            // Inserir novo usuário
            $sql = "INSERT INTO usuarios (nome, email, senha, is_admin, tipo_usuario) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssis", $nome, $email, $senha_hash, $is_admin, $tipo_usuario);
            
            if ($stmt->execute()) {
                $mensagem = "✅ Usuário cadastrado com sucesso!";
            } else {
                $mensagem = "❌ Erro ao cadastrar usuário: " . $stmt->error;
            }
            
            $stmt->close();
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
    <title>Cadastrar Usuário</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .form-title {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #1e88e5;
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .btn-container {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 160, 71, 0.5);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #fdd835 0%, #f9a825 100%);
            color: #333;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(253, 216, 53, 0.4);
        }
        
        .mensagem {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: 600;
        }
        
        .mensagem.sucesso {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
            border: 2px solid #43a047;
        }
        
        .mensagem.erro {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            border: 2px solid #e53935;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div style="display:flex;align-items:center;gap:12px;">
                <img src="img/logo.meusis.png" alt="Logo MeuSIS" class="navbar-logo">
                <div class="logo">Cadastrar Usuário</div>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-btn"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="listar_usuarios.php" class="nav-btn"><i class="fas fa-users"></i> Usuários</a></li>
            </ul>
        </div>
    </nav>

    <div class="form-container">
        <h1 class="form-title">Cadastrar Novo Usuário</h1>
        
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem <?php echo (strpos($mensagem, '❌') !== false) ? 'erro' : 'sucesso'; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="cadastrar_usuario.php">
            <div class="form-group">
                <label for="nome">Nome Completo *</label>
                <input type="text" id="nome" name="nome" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha *</label>
                <input type="password" id="senha" name="senha" required minlength="6">
                <small style="color: #666;">Mínimo 6 caracteres</small>
            </div>
            
            <div class="form-group">
                <label for="confirmar_senha">Confirmar Senha *</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="tipo_usuario">Tipo de Usuário *</label>
                <select id="tipo_usuario" name="tipo_usuario" required style="padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 100%; font-size: 14px;">
                    <option value="usuario" selected>Usuário</option>
                    <option value="parceiro">Parceiro</option>
                    <option value="admin">Administrador</option>
                </select>
                <small style="color: #666; display: block; margin-top: 5px;">
                    <strong>Usuário:</strong> Acessa todos os clientes, SEM acesso ao financeiro<br>
                    <strong>Parceiro:</strong> Acessa apenas seus clientes, COM acesso ao financeiro<br>
                    <strong>Administrador:</strong> Acesso total ao sistema
                </small>
            </div>
            
            <div class="btn-container">
                <button type="submit" class="btn btn-primary">Cadastrar Usuário</button>
                <a href="listar_usuarios.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <footer class="footer">
        <p>Dioleno N. Silva - Todos os direitos reservados</p>
    </footer>

    <script>
    // Validação de senhas iguais
    document.querySelector('form').addEventListener('submit', function(e) {
        const senha = document.getElementById('senha').value;
        const confirmarSenha = document.getElementById('confirmar_senha').value;
        
        if (senha !== confirmarSenha) {
            e.preventDefault();
            alert('As senhas não coincidem!');
        }
    });
    </script>
</body>
</html>
