<?php
// Validação EXATA dos placeholders e parâmetros

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// Contar placeholders na SQL INSERT
$insertSQL = "INSERT INTO clientes (
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

$insertPlaceholders = substr_count($insertSQL, '?');
echo "INSERT SQL - Placeholders: $insertPlaceholders\n";

// Parâmetros do INSERT na ordem EXATA
$insertParams = [
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

// Gerar string de tipos baseada nos nomes das variáveis
$insertParamNames = [
    'nome', 'cpf', 'data_nascimento', 'rg',
    'estado_civil', 'nacionalidade', 'profissao',
    'senha_meuinss', 'senha_email', 'beneficio', 'situacao',
    'indicador', 'responsavel', 'advogado',
    'endereco', 'cidade', 'uf', 'cep',
    'telefone', 'telefone2', 'telefone3', 'email',
    'data_contrato', 'data_enviado', 'numero_processo',
    'data_avaliacao_social', 'hora_avaliacao_social', 'endereco_avaliacao_social', 'avaliacao_social_realizado',
    'data_pericia', 'hora_pericia', 'endereco_pericia', 'pericia_realizado',
    'contrato_assinado', 'observacao',
    'usuario_id'
];

$insertTypes = [];
foreach ($insertParamNames as $param) {
    if ($param === 'avaliacao_social_realizado' || $param === 'pericia_realizado' || 
        $param === 'contrato_assinado' || $param === 'usuario_id') {
        $insertTypes[] = 'i';
    } else {
        $insertTypes[] = 's';
    }
}

$insertString = implode('', $insertTypes);
echo "INSERT bind_param - Parâmetros: " . count($insertParamNames) . "\n";
echo "INSERT - String de tipos gerada: $insertString\n";
echo "INSERT - Tamanho da string: " . strlen($insertString) . "\n";
echo "INSERT - Match: " . ($insertPlaceholders === strlen($insertString) ? "SIM ✓" : "NÃO ✗") . "\n\n";

// Contar placeholders na SQL UPDATE
$updateSQL = "UPDATE clientes SET 
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

$updatePlaceholders = substr_count($updateSQL, '?');
echo "UPDATE SQL - Placeholders: $updatePlaceholders\n";

// Parâmetros do UPDATE na ordem EXATA
$updateParamNames = [
    'nome', 'cpf', 'data_nascimento', 'rg',
    'estado_civil', 'nacionalidade', 'profissao',
    'senha_meuinss', 'senha_email', 'beneficio', 'situacao',
    'indicador', 'responsavel', 'advogado',
    'endereco', 'cidade', 'uf', 'cep',
    'telefone', 'telefone2', 'telefone3', 'email',
    'data_contrato', 'data_enviado', 'numero_processo',
    'data_avaliacao_social', 'hora_avaliacao_social', 'endereco_avaliacao_social', 'avaliacao_social_realizado',
    'data_pericia', 'hora_pericia', 'endereco_pericia', 'pericia_realizado',
    'contrato_assinado', 'observacao',
    'cliente_id'
];

$updateTypes = [];
foreach ($updateParamNames as $param) {
    if ($param === 'avaliacao_social_realizado' || $param === 'pericia_realizado' || 
        $param === 'contrato_assinado' || $param === 'cliente_id') {
        $updateTypes[] = 'i';
    } else {
        $updateTypes[] = 's';
    }
}

$updateString = implode('', $updateTypes);
echo "UPDATE bind_param - Parâmetros: " . count($updateParamNames) . "\n";
echo "UPDATE - String de tipos gerada: $updateString\n";
echo "UPDATE - Tamanho da string: " . strlen($updateString) . "\n";
echo "UPDATE - Match: " . ($updatePlaceholders === strlen($updateString) ? "SIM ✓" : "NÃO ✗") . "\n\n";

// Extrair strings atuais do arquivo
echo "=== VERIFICANDO ARQUIVO ATUAL ===\n";
if (preg_match('/\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $currentUpdate = $matches[1];
    echo "UPDATE atual no arquivo: $currentUpdate\n";
    echo "Tamanho: " . strlen($currentUpdate) . "\n";
    echo "Correto: " . ($currentUpdate === $updateString ? "SIM" : "NÃO") . "\n\n";
}

if (preg_match('/\$bindResult = @\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $currentInsert = $matches[1];
    echo "INSERT atual no arquivo: $currentInsert\n";
    echo "Tamanho: " . strlen($currentInsert) . "\n";
    echo "Correto: " . ($currentInsert === $insertString ? "SIM" : "NÃO") . "\n\n";
}

if (preg_match('/\$typesString = "([^"]+)";/', $content, $matches)) {
    $currentTypes = $matches[1];
    echo "typesString atual no arquivo: $currentTypes\n";
    echo "Tamanho: " . strlen($currentTypes) . "\n";
    echo "Correto: " . ($currentTypes === $insertString ? "SIM" : "NÃO") . "\n\n";
}

// Corrigir se necessário
$needsFix = false;
if (!isset($currentUpdate) || $currentUpdate !== $updateString) {
    $content = preg_replace(
        '/\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
        '$stmt->bind_param("' . $updateString . '"',
        $content,
        1
    );
    $needsFix = true;
    echo "✓ UPDATE corrigido!\n";
}

if (!isset($currentInsert) || $currentInsert !== $insertString) {
    $content = preg_replace(
        '/\$bindResult = @\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
        '$bindResult = @$stmt->bind_param("' . $insertString . '"',
        $content,
        1
    );
    $needsFix = true;
    echo "✓ INSERT corrigido!\n";
}

if (!isset($currentTypes) || $currentTypes !== $insertString) {
    $content = preg_replace(
        '/\$typesString = "ssssssssssssssssssssssssss[^"]+";/',
        '$typesString = "' . $insertString . '";',
        $content
    );
    $needsFix = true;
    echo "✓ typesString corrigido!\n";
}

if ($needsFix) {
    file_put_contents($file, $content);
    echo "\n✓ Arquivo salvo com correções!\n";
} else {
    echo "\n✓ Todas as strings já estão corretas!\n";
}
?>


