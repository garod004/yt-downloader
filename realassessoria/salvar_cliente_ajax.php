<?php
// #region agent log
function debug_log($location, $message, $data = [], $hypothesisId = '') {
    // Fallback simples primeiro - escrever direto na raiz
    $simpleLog = __DIR__ . '/debug_simple.txt';
    $simpleMsg = date('Y-m-d H:i:s') . " [$location] $message";
    if (!empty($data)) {
        $simpleMsg .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    @file_put_contents($simpleLog, $simpleMsg . "\n", FILE_APPEND);
    
    try {
        $logDir = __DIR__ . '/.cursor';
        $logFile = $logDir . '/debug.log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logEntry = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => round(microtime(true) * 1000),
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => $hypothesisId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Ignorar erros de log para não quebrar o fluxo
    }
}

function normalizar_situacao($situacao) {
    $situacao = trim((string)$situacao);
    if ($situacao === '') {
        return $situacao;
    }

    $normalizado = strtolower($situacao);
    $normalizado = str_replace('_', ' ', $normalizado);
    $normalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalizado);
    $normalizado = preg_replace('/\s+/', ' ', (string)$normalizado);
    $normalizado = trim((string)$normalizado);

    if ($normalizado === 'concluido sem decisao' || $normalizado === 'concluso sem decisao') {
        return 'concluido_sem_decisao';
    }

    return $situacao;
}
// #endregion agent log

// #region agent log
try {
    debug_log('salvar_cliente_ajax.php:1', 'Arquivo carregado - antes de qualquer código', ['php_version' => PHP_VERSION], 'ALL');
} catch (Throwable $e) {
    // Continuar mesmo se o log falhar
}
// #endregion agent log

// Capturar TODOS os erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// #region agent log
debug_log('salvar_cliente_ajax.php:28', 'Antes de ob_start', ['headers_sent' => headers_sent()], 'C');
// #endregion agent log

// Capturar qualquer output indesejado
ob_start();

try {
    // #region agent log
    debug_log('salvar_cliente_ajax.php:33', 'Início do try', ['ob_level' => ob_get_level()], 'C');
    // #endregion agent log
    
    // #region agent log
    debug_log('salvar_cliente_ajax.php:36', 'Antes de session_start', ['session_status' => session_status()], 'C');
    // #endregion agent log
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Limpar qualquer output que possa ter vindo antes
    ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    
    // Autenticação
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
        exit();
    }
    
    // Permissões
    $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'usuario';
    $usuario_id = $_SESSION['usuario_id'];
    
    // Incluir conexão
    if (!file_exists('conexao.php')) {
        throw new Exception('Arquivo conexao.php não encontrado');
    }
    
    include 'conexao.php';
    
    if (!isset($conn)) {
        throw new Exception('Erro na conexão com banco de dados');
    }
    
    // Receber dados do POST
    $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
    $nome = $_POST['nome'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? null;
    $rg = $_POST['rg'] ?? '';
    $estado_civil = $_POST['estado_civil'] ?? '';
    $nacionalidade = $_POST['nacionalidade'] ?? '';
    $profissao = $_POST['profissao'] ?? '';
    $senha_meuinss = $_POST['senha_meuinss'] ?? '';
    $senha_email = $_POST['senha_email'] ?? '';
    $beneficio = $_POST['beneficio'] ?? '';
    $situacao = normalizar_situacao($_POST['situacao'] ?? '');
    $indicador = $_POST['indicador'] ?? '';
    $responsavel = $_POST['responsavel'] ?? '';
    $advogado = $_POST['advogado'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $uf = $_POST['estado'] ?? 'AM';
    $cep = $_POST['cep'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $telefone2 = $_POST['telefone2'] ?? '';
    $telefone3 = $_POST['telefone3'] ?? '';
    $email = $_POST['email'] ?? '';
    $data_contrato = $_POST['data_contrato'] ?? null;
    $data_enviado = $_POST['data_enviado'] ?? ''; // Inicializar como string vazia (coluna não permite NULL)
    $numero_processo = $_POST['numero_processo'] ?? '';
    $data_avaliacao_social = $_POST['data_avaliacao_social'] ?? ''; // Inicializar como string vazia (coluna não permite NULL)
    $hora_avaliacao_social = $_POST['hora_avaliacao_social'] ?? ''; // Inicializar como string vazia (coluna não permite NULL)
    $endereco_avaliacao_social = $_POST['endereco_avaliacao_social'] ?? '';
    $avaliacao_social_realizado = isset($_POST['avaliacao_social_realizado']) ? 1 : 0;
    $data_pericia = $_POST['data_pericia'] ?? ''; // Inicializar como string vazia (coluna não permite NULL)
    $hora_pericia = $_POST['hora_pericia'] ?? ''; // Inicializar como string vazia (coluna não permite NULL)
    $endereco_pericia = $_POST['endereco_pericia'] ?? '';
    $pericia_realizado = isset($_POST['pericia_realizado']) ? 1 : 0;
    $contrato_assinado = isset($_POST['contrato_assinado']) ? 1 : 0;
    $observacao = $_POST['observacao'] ?? '';
    
    // Converter datas vazias para NULL
    if (empty($data_nascimento)) $data_nascimento = null;
    if (empty($data_contrato)) $data_contrato = null;
    if (empty($data_enviado)) $data_enviado = ''; // Converter NULL para string vazia (coluna não permite NULL)
    if (empty($data_avaliacao_social)) $data_avaliacao_social = ''; // Converter NULL para string vazia (coluna não permite NULL)
    if (empty($hora_avaliacao_social)) $hora_avaliacao_social = ''; // Converter NULL para string vazia (coluna não permite NULL)
    if (empty($data_pericia)) $data_pericia = ''; // Converter NULL para string vazia (coluna não permite NULL)
    if (empty($hora_pericia)) $hora_pericia = ''; // Converter NULL para string vazia (coluna não permite NULL)
    
    // #region agent log
    debug_log('salvar_cliente_ajax.php:40', 'Dados recebidos do POST', [
        'cliente_id' => $cliente_id,
        'nome' => $nome,
        'cpf' => $cpf,
        'usuario_id' => $usuario_id,
        'nome_empty' => empty($nome),
        'data_nascimento' => $data_nascimento,
        'data_contrato' => $data_contrato
    ], 'B');
    // #endregion agent log
    
    // Validação básica
    if (empty($nome)) {
        echo json_encode(['success' => false, 'message' => 'O campo Nome é obrigatório.']);
        exit();
    }
    
    // ATUALIZAR registro existente
    if ($cliente_id > 0) {
        // Verificar permissões
        if ($tipo_usuario === 'parceiro') {
            $checkStmt = $conn->prepare("SELECT id FROM clientes WHERE id = ? AND usuario_cadastro_id = ?");
            if (!$checkStmt) {
                throw new Exception('Erro ao preparar verificação: ' . $conn->error);
            }
            $checkStmt->bind_param("ii", $cliente_id, $usuario_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Você não tem permissão para editar este cliente.']);
                exit();
            }
            $checkStmt->close();
        }
        
        $sql = "UPDATE clientes SET 
                nome = ?, cpf = ?, data_nascimento = ?, rg = ?,
                estado_civil = ?, nacionalidade = ?, profissao = ?,
                senha_meuinss = ?, senha_email = ?, beneficio = ?, situacao = ?,
                indicador = ?, responsavel = ?, advogado = ?,
                endereco = ?, cidade = ?, uf = ?, cep = ?,
                telefone = ?, telefone2 = ?, telefone3 = ?, email = ?,
                data_contrato = ?, data_enviado = ?, numero_processo = ?,
                data_avaliacao_social = ?, hora_avaliacao_social = ?, endereco_avaliacao_social = ?, realizado_a_s = ?,
                data_pericia = ?, hora_pericia = ?, endereco_pericia = ?, realizado_pericia = ?,
                contrato_assinado = ?, observacao = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Erro ao preparar UPDATE: ' . $conn->error);
        }
        
        $stmt->bind_param("ssssssssssssssssssssssssssssisssiisi", 
            $nome, $cpf, $data_nascimento, $rg,
            $estado_civil, $nacionalidade, $profissao,
            $senha_meuinss, $senha_email, $beneficio, $situacao,
            $indicador, $responsavel, $advogado,
            $endereco, $cidade, $uf, $cep,
            $telefone, $telefone2, $telefone3, $email,
            $data_contrato, $data_enviado, $numero_processo,
            $data_avaliacao_social, $hora_avaliacao_social, $endereco_avaliacao_social, $avaliacao_social_realizado,
            $data_pericia, $hora_pericia, $endereco_pericia, $pericia_realizado,
            $contrato_assinado, $observacao,
            $cliente_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao executar UPDATE: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Cliente atualizado com sucesso!', 'cliente_id' => $cliente_id]);
        $stmt->close();
        
    } else {
        // INSERIR novo registro
        // #region agent log
        debug_log('salvar_cliente_ajax.php:151', 'Iniciando INSERT - antes de preparar query', ['cliente_id' => $cliente_id], 'D');
        // #endregion agent log
        
        $sql = "INSERT INTO clientes (
                nome, cpf, data_nascimento, rg,
                estado_civil, nacionalidade, profissao,
                senha_meuinss, senha_email, beneficio, situacao,
                indicador, responsavel, advogado,
                endereco, cidade, uf, cep,
                telefone, telefone2, telefone3, email,
                data_contrato, data_enviado, numero_processo,
                data_avaliacao_social, hora_avaliacao_social, endereco_avaliacao_social, realizado_a_s,
                data_pericia, hora_pericia, endereco_pericia, realizado_pericia,
                contrato_assinado, observacao,
                usuario_cadastro_id
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?
            )";
        
        // #region agent log
        $paramCount = substr_count($sql, '?');
        // String de tipos: Contagem EXATA na ordem do bind_param:
        // 1-28: s (nome até endereco_avaliacao_social) = 28s
        // 29: i (avaliacao_social_realizado)
        // 30-32: s (data_pericia, hora_pericia, endereco_pericia) = 3s
        // 33: i (pericia_realizado)
        // 34: i (contrato_assinado)
        // 35: s (observacao)
        // 36: i (usuario_id)
        // Total: 28s + 1i + 3s + 1i + 1i + 1s + 1i = 36 caracteres
        $typesString = "ssssssssssssssssssssssssssssisssiisi"; // CORRIGIDO: 36 caracteres - ordem correta
        $typesCount = strlen($typesString);
        debug_log('salvar_cliente_ajax.php:179', 'Antes de preparar INSERT', [
            'sql_length' => strlen($sql),
            'param_count' => $paramCount,
            'types_string' => $typesString,
            'types_count' => $typesCount,
            'match' => $paramCount === $typesCount,
            'conn_error' => $conn->error ?? 'none'
        ], 'A');
        // #endregion agent log
        
        $stmt = $conn->prepare($sql);
        
        // #region agent log
        debug_log('salvar_cliente_ajax.php:181', 'Após preparar INSERT', [
            'stmt_prepared' => $stmt !== false,
            'conn_error' => $conn->error ?? 'none',
            'conn_errno' => $conn->errno ?? 0
        ], 'D');
        // #endregion agent log
        
        if (!$stmt) {
            throw new Exception('Erro ao preparar INSERT: ' . $conn->error);
        }
        
        // Verificar contagem antes de bind_param
        $bindParams = [
            $nome, $cpf, $data_nascimento, $rg,
            $estado_civil, $nacionalidade, $profissao,
            $senha_meuinss, $senha_email, $beneficio, $situacao,
            $indicador, $responsavel, $advogado,
            $endereco, $cidade, $uf, $cep,
            $telefone, $telefone2, $telefone3, $email,
            $data_contrato, $data_enviado, $numero_processo,
            $data_avaliacao_social, $hora_avaliacao_social, $endereco_avaliacao_social, $avaliacao_social_realizado,
            $data_pericia, $hora_pericia, $endereco_pericia, $pericia_realizado,
            $contrato_assinado, $observacao,
            $usuario_id
        ];
        
        // Verificar se a contagem está correta
        if (count($bindParams) !== strlen($typesString)) {
            throw new Exception('Erro: Número de parâmetros (' . count($bindParams) . ') não corresponde à string de tipos (' . strlen($typesString) . ')');
        }
        
        // #region agent log
        debug_log('salvar_cliente_ajax.php:185', 'Antes de bind_param', [
            'bind_params_count' => count($bindParams),
            'types_count' => strlen($typesString),
            'match' => count($bindParams) === strlen($typesString),
            'usuario_id' => $usuario_id,
            'usuario_id_type' => gettype($usuario_id),
            'nome_null' => is_null($nome),
            'cpf_null' => is_null($cpf)
        ], 'A');
        // #endregion agent log
        
        $bindResult = @$stmt->bind_param("ssssssssssssssssssssssssssssisssiisi", 
            $nome, $cpf, $data_nascimento, $rg,
            $estado_civil, $nacionalidade, $profissao,
            $senha_meuinss, $senha_email, $beneficio, $situacao,
            $indicador, $responsavel, $advogado,
            $endereco, $cidade, $uf, $cep,
            $telefone, $telefone2, $telefone3, $email,
            $data_contrato, $data_enviado, $numero_processo,
            $data_avaliacao_social, $hora_avaliacao_social, $endereco_avaliacao_social, $avaliacao_social_realizado,
            $data_pericia, $hora_pericia, $endereco_pericia, $pericia_realizado,
            $contrato_assinado, $observacao,
            $usuario_id
        );
        
        if (!$bindResult) {
            throw new Exception('Erro ao fazer bind_param: ' . $stmt->error . ' | Parâmetros: ' . count($bindParams) . ' | Tipos: ' . strlen($typesString));
        }
        
        // #region agent log
        debug_log('salvar_cliente_ajax.php:198', 'Após bind_param - antes de execute', [
            'stmt_error' => $stmt->error ?? 'none',
            'stmt_errno' => $stmt->errno ?? 0,
            'bind_result' => $bindResult
        ], 'A');
        // #endregion agent log
        
        // #region agent log
        debug_log('salvar_cliente_ajax.php:199', 'Antes de execute INSERT', [
            'ob_level' => ob_get_level(),
            'headers_sent' => headers_sent()
        ], 'E');
        // #endregion agent log
        
        if (!$stmt->execute()) {
            // #region agent log
            debug_log('salvar_cliente_ajax.php:200', 'Erro ao executar INSERT', [
                'stmt_error' => $stmt->error,
                'stmt_errno' => $stmt->errno,
                'conn_error' => $conn->error,
                'conn_errno' => $conn->errno
            ], 'E');
            // #endregion agent log
            throw new Exception('Erro ao executar INSERT: ' . $stmt->error . ' (Código: ' . $stmt->errno . ')');
        }
        
        // #region agent log
        debug_log('salvar_cliente_ajax.php:203', 'INSERT executado com sucesso', [
            'insert_id' => $conn->insert_id,
            'affected_rows' => $conn->affected_rows
        ], 'E');
        // #endregion agent log
        
        $novo_id = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Cliente cadastrado com sucesso!', 'cliente_id' => $novo_id]);
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    // #region agent log
    debug_log('salvar_cliente_ajax.php:210', 'Exceção capturada', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'ob_level' => ob_get_level(),
        'headers_sent' => headers_sent()
    ], 'ALL');
    // #endregion agent log
    
    // Limpar buffer
    @ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    $errorMsg = 'ERRO: ' . $e->getMessage();
    // Adicionar informações de debug se disponíveis
    if (isset($stmt) && $stmt->error) {
        $errorMsg .= ' | SQL Error: ' . $stmt->error;
    }
    if (isset($conn) && $conn->error) {
        $errorMsg .= ' | Conn Error: ' . $conn->error;
    }
    echo json_encode([
        'success' => false, 
        'message' => $errorMsg,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // #region agent log
    debug_log('salvar_cliente_ajax.php:220', 'Throwable capturado', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'type' => get_class($e),
        'trace' => $e->getTraceAsString()
    ], 'ALL');
    // #endregion agent log
    
    @ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false, 
        'message' => 'ERRO FATAL: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// #region agent log
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        debug_log('salvar_cliente_ajax.php:shutdown', 'Erro fatal no shutdown', [
            'type' => $error['type'],
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], 'ALL');
    }
});
// #endregion agent log

if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
