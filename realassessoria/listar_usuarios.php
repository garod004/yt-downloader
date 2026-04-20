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

// Buscar todos os usuários
$sql = "SELECT id, nome, email, is_admin, tipo_usuario FROM usuarios ORDER BY nome ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Usuários</title>
    <link rel="stylesheet" href="cadastrar_cliente.css">
    <style>
        .usuarios-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-title {
            text-align: center;
            color: #1e3c72;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .btn-novo {
            display: inline-block;
            margin-bottom: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-novo:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 160, 71, 0.5);
        }
        
        .tabela-usuarios {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .tabela-usuarios table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tabela-usuarios thead {
            background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
            color: white;
        }
        
        .tabela-usuarios th,
        .tabela-usuarios td {
            padding: 15px;
            text-align: left;
        }
        
        .tabela-usuarios tbody tr:hover {
            background: #f5f5f5;
        }
        
        .tabela-usuarios tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            color: white;
        }
        
        .badge-user {
            background: linear-gradient(135deg, #fdd835 0%, #f9a825 100%);
            color: #333;
        }
        
        .btn-action {
            padding: 6px 12px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
            color: white;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.4);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
            color: white;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(229, 57, 53, 0.4);
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
                <img src="img/logo_Real_Assessoria.png" alt="Logo Real Assessoria" class="navbar-logo">
                <div class="logo">Gerenciar Usuários</div>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-btn"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="cadastrar_usuario.php" class="nav-btn"><i class="fas fa-user-plus"></i> Novo Usuário</a></li>
            </ul>
        </div>
    </nav>

    <div class="usuarios-container">
        <h1 class="page-title">Usuários do Sistema</h1>
        
        <a href="cadastrar_usuario.php" class="btn-novo">+ Novo Usuário</a>
        
        <div class="tabela-usuarios">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($usuario = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <?php 
                                    $tipo = $usuario['tipo_usuario'] ?? ($usuario['is_admin'] == 1 ? 'admin' : 'usuario');
                                    if ($tipo == 'admin'): 
                                    ?>
                                        <span class="badge badge-admin">Administrador</span>
                                    <?php elseif ($tipo == 'parceiro'): ?>
                                        <span class="badge badge-user" style="background: #ffa726;">Parceiro</span>
                                    <?php else: ?>
                                        <span class="badge badge-user">Usuário</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" class="btn-action btn-edit">Editar</a>
                                    <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                        <a href="excluir_usuario.php?id=<?php echo $usuario['id']; ?>" 
                                           class="btn-action btn-delete" 
                                           onclick="return confirm('Tem certeza que deseja excluir este usuário?')">Excluir</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #666;">
                                Nenhum usuário cadastrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <p>Dioleno N. Silva - Todos os direitos reservados</p>
    </footer>

    <script>
    // Detectar navegação com botão voltar após logout
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.logged_in) {
                        window.location.href = 'login.php';
                    }
                });
        }
    });
    </script>
</body>
</html>
