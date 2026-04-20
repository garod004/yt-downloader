<?php
// Gerar string correta de 36 caracteres baseada na ordem EXATA dos parâmetros

$params = [
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

$types = [];
foreach ($params as $i => $param) {
    $type = 's';
    if ($param === 'avaliacao_social_realizado' || $param === 'pericia_realizado' || 
        $param === 'contrato_assinado' || $param === 'usuario_id') {
        $type = 'i';
    }
    $types[] = $type;
    echo ($i+1) . ". $param = $type\n";
}

$correctString = implode('', $types);
echo "\nString correta gerada: $correctString\n";
echo "Tamanho: " . strlen($correctString) . "\n";
echo "Deve ser 36: " . (strlen($correctString) === 36 ? "SIM ✓" : "NÃO ✗") . "\n";

// Aplicar correção
$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// Corrigir UPDATE
$content = preg_replace(
    '/\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
    '$stmt->bind_param("' . $correctString . '"',
    $content,
    1
);

// Corrigir INSERT
$content = preg_replace(
    '/\$bindResult = @\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
    '$bindResult = @$stmt->bind_param("' . $correctString . '"',
    $content,
    1
);

// Corrigir typesString
$content = preg_replace(
    '/\$typesString = "ssssssssssssssssssssssssss[^"]+";/',
    '$typesString = "' . $correctString . '";',
    $content
);

file_put_contents($file, $content);
echo "\n✓ Arquivo corrigido!\n";
?>


