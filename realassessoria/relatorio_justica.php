<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

function formatarCPF($cpf) {
    if (empty($cpf)) return '-';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return '-';
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $data;
}

$tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
$usuario_id = (int)$_SESSION['usuario_id'];
$is_parceiro = ($tipo_usuario === 'parceiro');

$filtro_indicador = isset($_GET['indicador']) ? trim($_GET['indicador']) : '';
$filtro_responsavel = isset($_GET['responsavel']) ? trim($_GET['responsavel']) : '';
$filtro_advogado = isset($_GET['advogado']) ? trim($_GET['advogado']) : '';

$pdf_query = http_build_query([
    'indicador' => $filtro_indicador,
    'responsavel' => $filtro_responsavel,
    'advogado' => $filtro_advogado,
]);

$sql = "SELECT nome, cpf, data_contrato, indicador, responsavel, advogado, beneficio
        FROM clientes
        WHERE 1=1";

$params = [];
$types = '';

if ($is_parceiro) {
    $sql .= " AND usuario_cadastro_id = ?";
    $params[] = $usuario_id;
    $types .= 'i';
}

if ($filtro_indicador !== '') {
    $sql .= " AND indicador LIKE ?";
    $params[] = '%' . $filtro_indicador . '%';
    $types .= 's';
}

if ($filtro_responsavel !== '') {
    $sql .= " AND responsavel LIKE ?";
    $params[] = '%' . $filtro_responsavel . '%';
    $types .= 's';
}

if ($filtro_advogado !== '') {
    $sql .= " AND advogado LIKE ?";
    $params[] = '%' . $filtro_advogado . '%';
    $types .= 's';
}

$sql .= " ORDER BY nome ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Justiça</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #2c3e50;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .header {
            padding: 18px 20px;
            background: linear-gradient(90deg, #3498db, #36d399);
            color: #1f2d3d;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: 0.5px;
        }

        .header .acoes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            background: #ffffff;
            border: 1px solid #d7dee8;
            color: #2c3e50;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .content {
            padding: 16px;
        }

        .meta {
            margin-bottom: 12px;
            color: #5b6b79;
            font-size: 14px;
        }

        .filtros {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
            background: #f8fbff;
            border: 1px solid #e3e8ef;
            border-radius: 8px;
            padding: 12px;
        }

        .filtro-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filtro-item label {
            font-size: 13px;
            font-weight: 700;
            color: #435365;
        }

        .filtro-item input {
            border: 1px solid #d7dee8;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 14px;
            outline: none;
        }

        .filtro-acoes {
            display: flex;
            gap: 8px;
            align-items: end;
            flex-wrap: wrap;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        th, td {
            border: 1px solid #e3e8ef;
            padding: 10px 8px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #eef3f9;
            color: #2c3e50;
            font-weight: 700;
        }

        tbody tr:nth-child(even) {
            background: #fafcff;
        }

        .vazio {
            padding: 24px;
            text-align: center;
            color: #6c7a89;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Relatório de Justiça</h1>
            <div class="acoes">
                <a class="btn" href="gerar_relatorio_justica_pdf.php?<?php echo htmlspecialchars($pdf_query); ?>">PDF</a>
                <button class="btn" type="button" onclick="window.print()">Imprimir</button>
                <a class="btn" href="gerador_relatorios.php">Voltar</a>
            </div>
        </div>
        <div class="content">
            <div class="meta">Gerado em: <?php echo date('d/m/Y H:i:s'); ?></div>

            <form method="GET" class="filtros">
                <div class="filtro-item">
                    <label for="indicador">Indicador</label>
                    <input type="text" id="indicador" name="indicador" value="<?php echo htmlspecialchars($filtro_indicador); ?>" placeholder="Digite o indicador">
                </div>
                <div class="filtro-item">
                    <label for="responsavel">Responsável</label>
                    <input type="text" id="responsavel" name="responsavel" value="<?php echo htmlspecialchars($filtro_responsavel); ?>" placeholder="Digite o responsável">
                </div>
                <div class="filtro-item">
                    <label for="advogado">Advogado</label>
                    <input type="text" id="advogado" name="advogado" value="<?php echo htmlspecialchars($filtro_advogado); ?>" placeholder="Digite o advogado">
                </div>
                <div class="filtro-acoes">
                    <button class="btn" type="submit">Filtrar</button>
                    <a class="btn" href="relatorio_justica.php">Limpar</a>
                </div>
            </form>

            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Data Contrato</th>
                                <th>Indicador</th>
                                <th>Responsável</th>
                                <th>Advogado</th>
                                <th>Benefício</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nome'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(formatarCPF($row['cpf'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars(formatarData($row['data_contrato'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($row['indicador'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['responsavel'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['advogado'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['beneficio'] ?? '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="vazio">Nenhum registro encontrado para o relatório.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
