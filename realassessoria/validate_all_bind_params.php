<?php
// Validar e corrigir todos os bind_param

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// UPDATE: 35 parâmetros
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
    'cliente_id'  // 36: i (mas UPDATE tem 35, então este é o último)
];

// Corrigir: UPDATE tem 35 parâmetros, não 36
$updateParamsCorrect = [
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
    'cliente_id'  // 35: i (WHERE id = ?)
];

$updateTypes = [];
foreach ($updateParamsCorrect as $param) {
    if (strpos($param, 'realizado') !== false || strpos($param, 'assinado') !== false || $param === 'cliente_id') {
        $updateTypes[] = 'i';
    } else {
        $updateTypes[] = 's';
    }
}
$updateString = implode('', $updateTypes);
echo "UPDATE - String correta: $updateString\n";
echo "UPDATE - Tamanho: " . strlen($updateString) . " (deve ser 35)\n\n";

// INSERT: 36 parâmetros
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

$insertTypes = [];
foreach ($insertParams as $param) {
    if (strpos($param, 'realizado') !== false || strpos($param, 'assinado') !== false || $param === 'usuario_id') {
        $insertTypes[] = 'i';
    } else {
        $insertTypes[] = 's';
    }
}
$insertString = implode('', $insertTypes);
echo "INSERT - String correta: $insertString\n";
echo "INSERT - Tamanho: " . strlen($insertString) . " (deve ser 36)\n\n";

// Corrigir UPDATE
if (preg_match('/\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/', $content, $matches)) {
    $oldUpdate = $matches[0];
    $newUpdate = str_replace('bind_param("' . substr($matches[0], 18, -2) . '"', 'bind_param("' . $updateString . '"', $oldUpdate);
    // Melhor abordagem: substituir diretamente
    $content = preg_replace(
        '/\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
        '$stmt->bind_param("' . $updateString . '"',
        $content,
        1
    );
    echo "✓ UPDATE corrigido!\n";
}

// Corrigir INSERT
if (preg_match('/\$bindResult = @\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/', $content, $matches)) {
    $content = preg_replace(
        '/\$bindResult = @\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
        '$bindResult = @$stmt->bind_param("' . $insertString . '"',
        $content,
        1
    );
    echo "✓ INSERT corrigido!\n";
}

// Corrigir $typesString
$content = preg_replace(
    '/\$typesString = "ssssssssssssssssssssssssss[^"]+";/',
    '$typesString = "' . $insertString . '";',
    $content
);
echo "✓ typesString corrigido!\n";

file_put_contents($file, $content);
echo "\n✓ Arquivo salvo!\n";
?>


