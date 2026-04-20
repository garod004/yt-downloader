<?php
include 'conexao.php';

mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: text/plain; charset=UTF-8');

function opcaoIntegracao($nome, $padrao = null) {
    if (PHP_SAPI === 'cli') {
        global $argv;
        if (is_array($argv)) {
            foreach ($argv as $argumento) {
                if (strpos($argumento, '--' . $nome . '=') === 0) {
                    return substr($argumento, strlen($nome) + 3);
                }
                if ($argumento === '--' . $nome) {
                    return '1';
                }
            }
        }
    }

    if (isset($_GET[$nome])) {
        return $_GET[$nome];
    }

    $env = getenv(strtoupper('meusis_' . $nome));
    if ($env !== false && $env !== '') {
        return $env;
    }

    return $padrao;
}

function valorBooleanoIntegracao($valor, $padrao = false) {
    if ($valor === null) {
        return $padrao;
    }

    $normalizado = strtolower(trim((string) $valor));
    return in_array($normalizado, array('1', 'true', 'sim', 'yes', 'on'), true);
}

function tabelaExisteIntegracao($conn, $nomeTabela) {
    $nomeTabela = $conn->real_escape_string($nomeTabela);
    $result = $conn->query("SHOW TABLES LIKE '{$nomeTabela}'");
    $existe = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    return $existe;
}

function colunaExisteIntegracao($conn, $tabela, $coluna) {
    $tabela = $conn->real_escape_string($tabela);
    $coluna = $conn->real_escape_string($coluna);
    $result = $conn->query("SHOW COLUMNS FROM `{$tabela}` LIKE '{$coluna}'");
    $existe = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    return $existe;
}

function garantirColunaIntegracao($conn, $tabela, $coluna, $definicao) {
    if (colunaExisteIntegracao($conn, $tabela, $coluna)) {
        return true;
    }
    $sql = "ALTER TABLE `{$tabela}` ADD COLUMN {$definicao}";
    return $conn->query($sql) === true;
}

function garantirIndiceIntegracao($conn, $tabela, $indice, $sqlIndice) {
    $indiceSeguro = $conn->real_escape_string($indice);
    $tabelaSegura = $conn->real_escape_string($tabela);
    $dbResult = $conn->query('SELECT DATABASE() AS db_name');
    $dbRow = $dbResult ? $dbResult->fetch_assoc() : null;
    $dbName = $dbRow ? $dbRow['db_name'] : '';
    if ($dbResult) {
        $dbResult->free();
    }

    $sql = "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = '{$tabelaSegura}' AND INDEX_NAME = '{$indiceSeguro}' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $result->free();
        return true;
    }
    if ($result) {
        $result->free();
    }

    return $conn->query($sqlIndice) === true;
}

function buscarColunasTabelaIntegracao($conn, $tabela) {
    $colunas = array();
    $result = $conn->query("SHOW COLUMNS FROM `{$tabela}`");
    if (!$result) {
        return $colunas;
    }
    while ($row = $result->fetch_assoc()) {
        $colunas[] = $row['Field'];
    }
    $result->free();
    return $colunas;
}

function fetchAllAssocIntegracao($conn, $sql, $tipos = '', $params = array()) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return array(false, array(), $conn->error);
    }

    if ($tipos !== '' && !empty($params)) {
        $bind = array();
        $bind[] = &$tipos;
        foreach ($params as $indice => $valor) {
            $bind[] = &$params[$indice];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        return array(false, array(), $erro);
    }

    $result = stmt_get_result($stmt);
    $rows = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return array(true, $rows, '');
}

function normalizarCpfIntegracao($cpf) {
    return preg_replace('/\D+/', '', (string) $cpf);
}

function valorVazioIntegracao($valor) {
    if ($valor === null) {
        return true;
    }
    if (is_string($valor)) {
        return trim($valor) === '' || trim($valor) === '0000-00-00' || trim($valor) === '00:00:00';
    }
    return false;
}

function escolherValorCampoIntegracao($campo, $atual, $origem, $preferirOrigem) {
    if ($campo === 'id') {
        return $atual;
    }
    if ($campo === 'meusis_id') {
        return $origem;
    }
    if ($preferirOrigem && !valorVazioIntegracao($origem)) {
        return $origem;
    }
    if (valorVazioIntegracao($atual) && !valorVazioIntegracao($origem)) {
        return $origem;
    }
    return $atual;
}

function montarUpdateIntegracao($tabela, $idCampo, $idValor, $dados) {
    $set = array();
    $tipos = '';
    $params = array();

    foreach ($dados as $campo => $valor) {
        $set[] = "`{$campo}` = ?";
        $tipos .= is_int($valor) ? 'i' : 's';
        $params[] = $valor;
    }

    $tipos .= is_int($idValor) ? 'i' : 's';
    $params[] = $idValor;

    return array(
        "UPDATE `{$tabela}` SET " . implode(', ', $set) . " WHERE `{$idCampo}` = ?",
        $tipos,
        $params,
    );
}

function executarStatementIntegracao($conn, $sql, $tipos, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return array(false, $conn->error);
    }

    if ($tipos !== '' && !empty($params)) {
        $bind = array();
        $bind[] = &$tipos;
        foreach ($params as $indice => $valor) {
            $bind[] = &$params[$indice];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    $ok = $stmt->execute();
    $erro = $ok ? '' : $stmt->error;
    $stmt->close();
    return array($ok, $erro);
}

function inserirRegistroIntegracao($conn, $tabela, $dados) {
    $campos = array_keys($dados);
    $placeholders = array_fill(0, count($campos), '?');
    $tipos = '';
    $params = array();

    foreach ($campos as $campo) {
        $valor = $dados[$campo];
        $tipos .= is_int($valor) ? 'i' : 's';
        $params[] = $valor;
    }

    $sql = "INSERT INTO `{$tabela}` (`" . implode('`, `', $campos) . "`) VALUES (" . implode(', ', $placeholders) . ')';
    list($ok, $erro) = executarStatementIntegracao($conn, $sql, $tipos, $params);
    if (!$ok) {
        return array(false, 0, $erro);
    }

    return array(true, (int) $conn->insert_id, '');
}

function linhaPorMeuSisIdOuCpf($conn, $meusisId, $cpfNormalizado) {
    if ($meusisId > 0 && colunaExisteIntegracao($conn, 'clientes', 'meusis_id')) {
        list($ok, $rows) = fetchAllAssocIntegracao($conn, 'SELECT * FROM clientes WHERE meusis_id = ? LIMIT 1', 'i', array($meusisId));
        if ($ok && !empty($rows)) {
            return $rows[0];
        }
    }

    if ($cpfNormalizado !== '') {
        list($ok, $rows) = fetchAllAssocIntegracao($conn, "SELECT * FROM clientes WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? LIMIT 1", 's', array($cpfNormalizado));
        if ($ok && !empty($rows)) {
            return $rows[0];
        }
    }

    return null;
}

function linhaFilhoExistenteIntegracao($conn, $clienteId, $meusisId, $cpfNormalizado, $nome, $dataNascimento) {
    if ($meusisId > 0 && colunaExisteIntegracao($conn, 'filhos_menores', 'meusis_id')) {
        list($ok, $rows) = fetchAllAssocIntegracao($conn, 'SELECT * FROM filhos_menores WHERE meusis_id = ? LIMIT 1', 'i', array($meusisId));
        if ($ok && !empty($rows)) {
            return $rows[0];
        }
    }

    if ($cpfNormalizado !== '') {
        list($ok, $rows) = fetchAllAssocIntegracao(
            $conn,
            "SELECT * FROM filhos_menores WHERE cliente_id = ? AND REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? LIMIT 1",
            'is',
            array($clienteId, $cpfNormalizado)
        );
        if ($ok && !empty($rows)) {
            return $rows[0];
        }
    }

    list($ok, $rows) = fetchAllAssocIntegracao(
        $conn,
        'SELECT * FROM filhos_menores WHERE cliente_id = ? AND nome = ? AND data_nascimento = ? LIMIT 1',
        'iss',
        array($clienteId, $nome, $dataNascimento)
    );
    if ($ok && !empty($rows)) {
        return $rows[0];
    }

    return null;
}

if (!($conn instanceof mysqli)) {
    $erro = isset($db_connection_error) && $db_connection_error !== '' ? $db_connection_error : 'Conexao com o banco atual indisponivel.';
    echo "Falha ao conectar no banco atual: {$erro}\n";
    exit(1);
}

$destinoHost = opcaoIntegracao('target_host', '');
$destinoPorta = (int) opcaoIntegracao('target_port', '3306');
$destinoUsuario = opcaoIntegracao('target_user', '');
$destinoSenha = opcaoIntegracao('target_pass', '');
$destinoBanco = opcaoIntegracao('target_db', '');

if ($destinoHost !== '' || $destinoUsuario !== '' || $destinoBanco !== '') {
    if ($conn instanceof mysqli) {
        $conn->close();
    }

    $hostFinal = $destinoHost !== '' ? $destinoHost : '127.0.0.1';
    $usuarioFinal = $destinoUsuario !== '' ? $destinoUsuario : 'root';
    $bancoFinal = $destinoBanco !== '' ? $destinoBanco : 'realassessoria';
    $conn = @new mysqli($hostFinal, $usuarioFinal, $destinoSenha, $bancoFinal, $destinoPorta);
    if (!($conn instanceof mysqli) || $conn->connect_error) {
        echo 'Falha ao conectar no banco alvo informado: ' . (($conn instanceof mysqli) ? $conn->connect_error : 'mysqli indisponivel') . "\n";
        exit(1);
    }
    $conn->set_charset('utf8mb4');
}

$origemHost = opcaoIntegracao('source_host', '127.0.0.1');
$origemPorta = (int) opcaoIntegracao('source_port', '3306');
$origemUsuario = opcaoIntegracao('source_user', 'root');
$origemSenha = opcaoIntegracao('source_pass', '');
$origemBanco = opcaoIntegracao('source_db', 'meusis_import');

$aplicar = valorBooleanoIntegracao(opcaoIntegracao('apply', '0'), false);
$preferirOrigem = valorBooleanoIntegracao(opcaoIntegracao('prefer_source', '0'), false);
$sincronizarUsuarios = valorBooleanoIntegracao(opcaoIntegracao('sync_users', '1'), true);

echo "Integracao MeuSis -> Nobrega Previdencia\n";
echo "Modo: " . ($aplicar ? 'APLICAR' : 'SIMULACAO') . "\n";
echo "Banco de origem: {$origemHost}:{$origemPorta}/{$origemBanco}\n\n";

$origemConn = @new mysqli($origemHost, $origemUsuario, $origemSenha, $origemBanco, $origemPorta);
if (!($origemConn instanceof mysqli) || $origemConn->connect_error) {
    echo 'Falha ao conectar no banco de origem MeuSis: ' . (($origemConn instanceof mysqli) ? $origemConn->connect_error : 'mysqli indisponivel') . "\n";
    exit(1);
}
$origemConn->set_charset('utf8mb4');

if (!tabelaExisteIntegracao($origemConn, 'clientes')) {
    echo "A origem nao possui a tabela clientes.\n";
    exit(1);
}

garantirColunaIntegracao($conn, 'clientes', 'meusis_id', 'meusis_id INT(10) UNSIGNED NULL AFTER id');
garantirIndiceIntegracao($conn, 'clientes', 'uniq_clientes_meusis_id', 'ALTER TABLE clientes ADD UNIQUE KEY uniq_clientes_meusis_id (meusis_id)');

if (tabelaExisteIntegracao($conn, 'usuarios')) {
    garantirColunaIntegracao($conn, 'usuarios', 'meusis_id', 'meusis_id INT(10) UNSIGNED NULL AFTER id');
    garantirIndiceIntegracao($conn, 'usuarios', 'uniq_usuarios_meusis_id', 'ALTER TABLE usuarios ADD UNIQUE KEY uniq_usuarios_meusis_id (meusis_id)');
}

if (tabelaExisteIntegracao($conn, 'filhos_menores')) {
    garantirColunaIntegracao($conn, 'filhos_menores', 'meusis_id', 'meusis_id INT(10) UNSIGNED NULL AFTER id');
    garantirIndiceIntegracao($conn, 'filhos_menores', 'idx_filhos_menores_meusis_id', 'ALTER TABLE filhos_menores ADD KEY idx_filhos_menores_meusis_id (meusis_id)');
}

$resumo = array(
    'usuarios_vinculados' => 0,
    'usuarios_inseridos' => 0,
    'clientes_inseridos' => 0,
    'clientes_atualizados' => 0,
    'clientes_ignorados' => 0,
    'filhos_inseridos' => 0,
    'filhos_atualizados' => 0,
    'filhos_ignorados' => 0,
    'avisos' => array(),
);

$mapaUsuarios = array();
$colunasUsuariosDestino = tabelaExisteIntegracao($conn, 'usuarios') ? buscarColunasTabelaIntegracao($conn, 'usuarios') : array();

if ($sincronizarUsuarios && tabelaExisteIntegracao($origemConn, 'usuarios') && tabelaExisteIntegracao($conn, 'usuarios')) {
    list($okUsuarios, $usuariosOrigem, $erroUsuarios) = fetchAllAssocIntegracao($origemConn, 'SELECT * FROM usuarios');
    if (!$okUsuarios) {
        $resumo['avisos'][] = 'Falha ao ler usuarios da origem: ' . $erroUsuarios;
    } else {
        foreach ($usuariosOrigem as $usuarioOrigem) {
            $meusisUsuarioId = (int) $usuarioOrigem['id'];
            $email = isset($usuarioOrigem['email']) ? trim((string) $usuarioOrigem['email']) : '';
            $usuarioDestino = null;

            list($okPorMeuSis, $rowsPorMeuSis) = fetchAllAssocIntegracao($conn, 'SELECT * FROM usuarios WHERE meusis_id = ? LIMIT 1', 'i', array($meusisUsuarioId));
            if ($okPorMeuSis && !empty($rowsPorMeuSis)) {
                $usuarioDestino = $rowsPorMeuSis[0];
            } elseif ($email !== '') {
                list($okPorEmail, $rowsPorEmail) = fetchAllAssocIntegracao($conn, 'SELECT * FROM usuarios WHERE email = ? LIMIT 1', 's', array($email));
                if ($okPorEmail && !empty($rowsPorEmail)) {
                    $usuarioDestino = $rowsPorEmail[0];
                }
            }

            if ($usuarioDestino) {
                $mapaUsuarios[$meusisUsuarioId] = (int) $usuarioDestino['id'];
                $resumo['usuarios_vinculados']++;
                if ($aplicar && (int) ($usuarioDestino['meusis_id'] ?? 0) !== $meusisUsuarioId) {
                    list($okUpdateUsuario, $erroUpdateUsuario) = executarStatementIntegracao(
                        $conn,
                        'UPDATE usuarios SET meusis_id = ? WHERE id = ?',
                        'ii',
                        array($meusisUsuarioId, (int) $usuarioDestino['id'])
                    );
                    if (!$okUpdateUsuario) {
                        $resumo['avisos'][] = 'Falha ao vincular usuario ' . $email . ': ' . $erroUpdateUsuario;
                    }
                }
                continue;
            }

            if (!$aplicar) {
                $resumo['usuarios_inseridos']++;
                continue;
            }

            $dadosUsuario = array();
            foreach (array('nome', 'email', 'senha', 'is_admin', 'tipo_usuario', 'DATA') as $campo) {
                if (in_array($campo, $colunasUsuariosDestino, true) && array_key_exists($campo, $usuarioOrigem)) {
                    $dadosUsuario[$campo] = $usuarioOrigem[$campo];
                }
            }
            if (in_array('meusis_id', $colunasUsuariosDestino, true)) {
                $dadosUsuario['meusis_id'] = $meusisUsuarioId;
            }

            list($okInsertUsuario, $novoUsuarioId, $erroInsertUsuario) = inserirRegistroIntegracao($conn, 'usuarios', $dadosUsuario);
            if ($okInsertUsuario) {
                $mapaUsuarios[$meusisUsuarioId] = $novoUsuarioId;
                $resumo['usuarios_inseridos']++;
            } else {
                $resumo['avisos'][] = 'Falha ao inserir usuario de origem ' . $email . ': ' . $erroInsertUsuario;
            }
        }
    }
}

$colunasClientesDestino = buscarColunasTabelaIntegracao($conn, 'clientes');
list($okClientesOrigem, $clientesOrigem, $erroClientesOrigem) = fetchAllAssocIntegracao($origemConn, 'SELECT * FROM clientes ORDER BY id ASC');
if (!$okClientesOrigem) {
    echo 'Falha ao ler clientes da origem: ' . $erroClientesOrigem . "\n";
    exit(1);
}

$mapaClientes = array();

if ($aplicar) {
    $conn->begin_transaction();
}

foreach ($clientesOrigem as $clienteOrigem) {
    $meusisClienteId = (int) $clienteOrigem['id'];
    $cpfNormalizado = normalizarCpfIntegracao(isset($clienteOrigem['cpf']) ? $clienteOrigem['cpf'] : '');
    $clienteDestino = linhaPorMeuSisIdOuCpf($conn, $meusisClienteId, $cpfNormalizado);

    $usuarioOrigemId = isset($clienteOrigem['usuario_id']) ? (int) $clienteOrigem['usuario_id'] : 0;
    $usuarioDestinoId = isset($mapaUsuarios[$usuarioOrigemId]) ? (int) $mapaUsuarios[$usuarioOrigemId] : 0;

    if ($clienteDestino) {
        $mapaClientes[$meusisClienteId] = (int) $clienteDestino['id'];
        $camposAtualizacao = array();

        foreach ($colunasClientesDestino as $coluna) {
            if ($coluna === 'id') {
                continue;
            }

            if ($coluna === 'meusis_id') {
                if ((int) ($clienteDestino['meusis_id'] ?? 0) !== $meusisClienteId) {
                    $camposAtualizacao['meusis_id'] = $meusisClienteId;
                }
                continue;
            }

            if ($coluna === 'usuario_cadastro_id' || $coluna === 'usuario_id') {
                if ($usuarioDestinoId > 0 && valorVazioIntegracao($clienteDestino[$coluna] ?? null)) {
                    $camposAtualizacao[$coluna] = $usuarioDestinoId;
                }
                continue;
            }

            if (($coluna === 'created_by' || $coluna === 'updated_by') && $usuarioDestinoId > 0 && valorVazioIntegracao($clienteDestino[$coluna] ?? null)) {
                $camposAtualizacao[$coluna] = $usuarioDestinoId;
                continue;
            }

            if (!array_key_exists($coluna, $clienteOrigem)) {
                continue;
            }

            $novoValor = escolherValorCampoIntegracao($coluna, $clienteDestino[$coluna] ?? null, $clienteOrigem[$coluna], $preferirOrigem);
            if (($clienteDestino[$coluna] ?? null) !== $novoValor) {
                $camposAtualizacao[$coluna] = $novoValor;
            }
        }

        if (!empty($camposAtualizacao)) {
            $resumo['clientes_atualizados']++;
            if ($aplicar) {
                list($sqlUpdate, $tiposUpdate, $paramsUpdate) = montarUpdateIntegracao('clientes', 'id', (int) $clienteDestino['id'], $camposAtualizacao);
                list($okUpdate, $erroUpdate) = executarStatementIntegracao($conn, $sqlUpdate, $tiposUpdate, $paramsUpdate);
                if (!$okUpdate) {
                    $resumo['avisos'][] = 'Falha ao atualizar cliente CPF ' . ($clienteOrigem['cpf'] ?? 'sem-cpf') . ': ' . $erroUpdate;
                }
            }
        } else {
            $resumo['clientes_ignorados']++;
        }
        continue;
    }

    $dadosInsert = array();
    foreach ($colunasClientesDestino as $coluna) {
        if ($coluna === 'id') {
            continue;
        }

        if ($coluna === 'meusis_id') {
            $dadosInsert['meusis_id'] = $meusisClienteId;
            continue;
        }

        if ($coluna === 'usuario_cadastro_id' || $coluna === 'usuario_id') {
            if ($usuarioDestinoId > 0) {
                $dadosInsert[$coluna] = $usuarioDestinoId;
            }
            continue;
        }

        if (($coluna === 'created_by' || $coluna === 'updated_by') && $usuarioDestinoId > 0) {
            $dadosInsert[$coluna] = $usuarioDestinoId;
            continue;
        }

        if (array_key_exists($coluna, $clienteOrigem)) {
            $dadosInsert[$coluna] = $clienteOrigem[$coluna];
        }
    }

    $resumo['clientes_inseridos']++;
    if ($aplicar) {
        list($okInsertCliente, $novoClienteId, $erroInsertCliente) = inserirRegistroIntegracao($conn, 'clientes', $dadosInsert);
        if ($okInsertCliente) {
            $mapaClientes[$meusisClienteId] = $novoClienteId;
        } else {
            $resumo['avisos'][] = 'Falha ao inserir cliente CPF ' . ($clienteOrigem['cpf'] ?? 'sem-cpf') . ': ' . $erroInsertCliente;
        }
    }
}

if (tabelaExisteIntegracao($origemConn, 'filhos_menores') && tabelaExisteIntegracao($conn, 'filhos_menores')) {
    $colunasFilhosDestino = buscarColunasTabelaIntegracao($conn, 'filhos_menores');
    list($okFilhosOrigem, $filhosOrigem, $erroFilhosOrigem) = fetchAllAssocIntegracao($origemConn, 'SELECT * FROM filhos_menores ORDER BY id ASC');
    if (!$okFilhosOrigem) {
        $resumo['avisos'][] = 'Falha ao ler filhos_menores da origem: ' . $erroFilhosOrigem;
    } else {
        foreach ($filhosOrigem as $filhoOrigem) {
            $clienteOrigemId = isset($filhoOrigem['cliente_id']) ? (int) $filhoOrigem['cliente_id'] : 0;
            if (!isset($mapaClientes[$clienteOrigemId])) {
                $resumo['filhos_ignorados']++;
                continue;
            }

            $clienteDestinoId = (int) $mapaClientes[$clienteOrigemId];
            $meusisFilhoId = (int) $filhoOrigem['id'];
            $cpfFilhoNormalizado = normalizarCpfIntegracao(isset($filhoOrigem['cpf']) ? $filhoOrigem['cpf'] : '');
            $filhoDestino = linhaFilhoExistenteIntegracao(
                $conn,
                $clienteDestinoId,
                $meusisFilhoId,
                $cpfFilhoNormalizado,
                isset($filhoOrigem['nome']) ? $filhoOrigem['nome'] : '',
                isset($filhoOrigem['data_nascimento']) ? $filhoOrigem['data_nascimento'] : ''
            );

            if ($filhoDestino) {
                $camposAtualizacaoFilho = array();
                foreach ($colunasFilhosDestino as $coluna) {
                    if ($coluna === 'id' || $coluna === 'cliente_id') {
                        continue;
                    }
                    if ($coluna === 'meusis_id') {
                        if ((int) ($filhoDestino['meusis_id'] ?? 0) !== $meusisFilhoId) {
                            $camposAtualizacaoFilho['meusis_id'] = $meusisFilhoId;
                        }
                        continue;
                    }
                    if (!array_key_exists($coluna, $filhoOrigem)) {
                        continue;
                    }
                    $novoValorFilho = escolherValorCampoIntegracao($coluna, $filhoDestino[$coluna] ?? null, $filhoOrigem[$coluna], $preferirOrigem);
                    if (($filhoDestino[$coluna] ?? null) !== $novoValorFilho) {
                        $camposAtualizacaoFilho[$coluna] = $novoValorFilho;
                    }
                }

                if (!empty($camposAtualizacaoFilho)) {
                    $resumo['filhos_atualizados']++;
                    if ($aplicar) {
                        list($sqlUpdateFilho, $tiposUpdateFilho, $paramsUpdateFilho) = montarUpdateIntegracao('filhos_menores', 'id', (int) $filhoDestino['id'], $camposAtualizacaoFilho);
                        list($okUpdateFilho, $erroUpdateFilho) = executarStatementIntegracao($conn, $sqlUpdateFilho, $tiposUpdateFilho, $paramsUpdateFilho);
                        if (!$okUpdateFilho) {
                            $resumo['avisos'][] = 'Falha ao atualizar filho menor ' . ($filhoOrigem['nome'] ?? 'sem-nome') . ': ' . $erroUpdateFilho;
                        }
                    }
                } else {
                    $resumo['filhos_ignorados']++;
                }
                continue;
            }

            $dadosInsertFilho = array('cliente_id' => $clienteDestinoId);
            foreach ($colunasFilhosDestino as $coluna) {
                if ($coluna === 'id' || $coluna === 'cliente_id') {
                    continue;
                }
                if ($coluna === 'meusis_id') {
                    $dadosInsertFilho['meusis_id'] = $meusisFilhoId;
                    continue;
                }
                if (array_key_exists($coluna, $filhoOrigem)) {
                    $dadosInsertFilho[$coluna] = $filhoOrigem[$coluna];
                }
            }

            $resumo['filhos_inseridos']++;
            if ($aplicar) {
                list($okInsertFilho, $novoFilhoId, $erroInsertFilho) = inserirRegistroIntegracao($conn, 'filhos_menores', $dadosInsertFilho);
                if (!$okInsertFilho) {
                    $resumo['avisos'][] = 'Falha ao inserir filho menor ' . ($filhoOrigem['nome'] ?? 'sem-nome') . ': ' . $erroInsertFilho;
                }
            }
        }
    }
}

if ($aplicar) {
    if (empty($resumo['avisos'])) {
        $conn->commit();
    } else {
        $conn->rollback();
        $resumo['avisos'][] = 'Transacao revertida por seguranca devido aos avisos acima.';
    }
}

echo "Resumo\n";
echo 'Usuarios vinculados: ' . $resumo['usuarios_vinculados'] . "\n";
echo 'Usuarios a inserir/inseridos: ' . $resumo['usuarios_inseridos'] . "\n";
echo 'Clientes inseridos: ' . $resumo['clientes_inseridos'] . "\n";
echo 'Clientes atualizados: ' . $resumo['clientes_atualizados'] . "\n";
echo 'Clientes ignorados: ' . $resumo['clientes_ignorados'] . "\n";
echo 'Filhos inseridos: ' . $resumo['filhos_inseridos'] . "\n";
echo 'Filhos atualizados: ' . $resumo['filhos_atualizados'] . "\n";
echo 'Filhos ignorados: ' . $resumo['filhos_ignorados'] . "\n";

if (!empty($resumo['avisos'])) {
    echo "\nAvisos\n";
    foreach ($resumo['avisos'] as $aviso) {
        echo '- ' . $aviso . "\n";
    }
}

echo "\nObservacoes\n";
echo "- O dump do MeuSis precisa estar importado em um banco temporario, por padrao 'meusis_import'.\n";
echo "- O modo padrao e simulacao. Use ?apply=1 ou --apply para gravar.\n";
echo "- Para priorizar os dados do MeuSis sobre os dados atuais quando houver conflito, use ?prefer_source=1.\n";
echo "- As tabelas incapazes e a_rogo nao sao sincronizadas por este script porque nao constam no dump analisado.\n";

$origemConn->close();
if ($conn instanceof mysqli) {
    $conn->close();
}
