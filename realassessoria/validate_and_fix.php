<?php
// Validação e correção FINAL

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

// Parâmetros na ordem EXATA do bind_param
$insertParams = [
    'nome', 'cpf', 'data_nascimento', 'rg',  // 1-4: s
    'estado_civil', 'nacionalidade', 'profissao',  // 5-7: s
    'senha_meuinss', 'senha_email', 'beneficio', 'situacao',  // 8-11: s
    'indicador', 'responsavel', 'advogado',  // 12-14: s
    'endereco', 'cidade', 'uf', 'cep',  // 15-18: s
    'telefone', 'telefone2', 'telefone3', 'email',  // 19-22: s
    'data_contrato', 'data_enviado', 'numero_processo',  // 23-25: s
    'data_avaliacao_social', 'hora_avaliacao_social', 'endereco_avaliacao_social', 'avaliacao_social_realizado',  // 26-29: s,s,s,i
    'data_pericia', 'hora_pericia', 'endereco_pericia', 'pericia_realizado',  // 30-33: s,s,s,i
    'contrato_assinado', 'observacao',  // 34-35: i,s
    'usuario_id'  // 36: i
];

echo "INSERT bind_param - Parâmetros: " . count($insertParams) . "\n";

// Gerar string de tipos
$insertTypes = [];
foreach ($insertParams as $i => $param) {
    $type = 's';
    if ($param === 'avaliacao_social_realizado' || $param === 'pericia_realizado' || 
        $param === 'contrato_assinado' || $param === 'usuario_id') {
        $type = 'i';
    }
    $insertTypes[] = $type;
}

$insertString = implode('', $insertTypes);
echo "INSERT - String de tipos gerada: $insertString\n";
echo "INSERT - Tamanho: " . strlen($insertString) . "\n";
echo "INSERT - Match: " . ($insertPlaceholders === strlen($insertString) && count($insertParams) === strlen($insertString) ? "SIM ✓" : "NÃO ✗") . "\n\n";

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
$updateParams = [
    'nome', 'cpf', 'data_nascimento', 'rg',  // 1-4: s
    'estado_civil', 'nacionalidade', 'profissao',  // 5-7: s
    'senha_meuinss', 'senha_email', 'beneficio', 'situacao',  // 8-11: s
    'indicador', 'responsavel', 'advogado',  // 12-14: s
    'endereco', 'cidade', 'uf', 'cep',  // 15-18: s
    'telefone', 'telefone2', 'telefone3', 'email',  // 19-22: s
    'data_contrato', 'data_enviado', 'numero_processo',  // 23-25: s
    'data_avaliacao_social', 'hora_avaliacao_social', 'endereco_avaliacao_social', 'avaliacao_social_realizado',  // 26-29: s,s,s,i
    'data_pericia', 'hora_pericia', 'endereco_pericia', 'pericia_realizado',  // 30-33: s,s,s,i
    'contrato_assinado', 'observacao',  // 34-35: i,s
    'cliente_id'  // 36: i
];

echo "UPDATE bind_param - Parâmetros: " . count($updateParams) . "\n";

// Gerar string de tipos para UPDATE
$updateTypes = [];
foreach ($updateParams as $param) {
    $type = 's';
    if ($param === 'avaliacao_social_realizado' || $param === 'pericia_realizado' || 
        $param === 'contrato_assinado' || $param === 'cliente_id') {
        $type = 'i';
    }
    $updateTypes[] = $type;
}

$updateString = implode('', $updateTypes);
echo "UPDATE - String de tipos gerada: $updateString\n";
echo "UPDATE - Tamanho: " . strlen($updateString) . "\n";
echo "UPDATE - Match: " . ($updatePlaceholders === strlen($updateString) && count($updateParams) === strlen($updateString) ? "SIM ✓" : "NÃO ✗") . "\n\n";

// Verificar strings atuais no arquivo
echo "=== VERIFICANDO ARQUIVO ATUAL ===\n";
if (preg_match('/\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    echo "UPDATE atual: $current (" . strlen($current) . " chars)\n";
    echo "Correto: " . ($current === $updateString ? "SIM" : "NÃO") . "\n\n";
}

if (preg_match('/\$bindResult = @\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    echo "INSERT atual: $current (" . strlen($current) . " chars)\n";
    echo "Correto: " . ($current === $insertString ? "SIM" : "NÃO") . "\n\n";
}

if (preg_match('/\$typesString = "([^"]+)";/', $content, $matches)) {
    $current = $matches[1];
    echo "typesString atual: $current (" . strlen($current) . " chars)\n";
    echo "Correto: " . ($current === $insertString ? "SIM" : "NÃO") . "\n\n";
}

// Aplicar correções
echo "=== APLICANDO CORREÇÕES ===\n";
$content = preg_replace(
    '/\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
    '$stmt->bind_param("' . $updateString . '"',
    $content,
    1
);
echo "✓ UPDATE corrigido\n";

$content = preg_replace(
    '/\$bindResult = @\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
    '$bindResult = @$stmt->bind_param("' . $insertString . '"',
    $content,
    1
);
echo "✓ INSERT corrigido\n";

$content = preg_replace(
    '/\$typesString = "ssssssssssssssssssssssssss[^"]+";/',
    '$typesString = "' . $insertString . '";',
    $content
);
echo "✓ typesString corrigido\n";

file_put_contents($file, $content);
echo "\n✓ Arquivo salvo!\n";
?>


