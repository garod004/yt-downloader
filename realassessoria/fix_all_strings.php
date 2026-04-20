<?php
// String correta baseada na ordem EXATA dos parâmetros
// 1-28: s (nome até endereco_avaliacao_social)
// 29: i (avaliacao_social_realizado)
// 30-32: s (data_pericia, hora_pericia, endereco_pericia)
// 33: i (pericia_realizado)
// 34: i (contrato_assinado)
// 35: s (observacao)
// 36: i (usuario_id/cliente_id)

$correctString = "sssssssssssssssssssssssssssisssiisi";

echo "String correta: $correctString\n";
echo "Tamanho: " . strlen($correctString) . "\n\n";

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// Verificar strings atuais
echo "=== VERIFICANDO STRINGS ATUAIS ===\n";

// UPDATE
if (preg_match('/\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    echo "UPDATE: $current\n";
    echo "Tamanho: " . strlen($current) . "\n";
    echo "Correto: " . ($current === $correctString ? "SIM" : "NÃO") . "\n\n";
}

// INSERT
if (preg_match('/\$bindResult = @\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    echo "INSERT: $current\n";
    echo "Tamanho: " . strlen($current) . "\n";
    echo "Correto: " . ($current === $correctString ? "SIM" : "NÃO") . "\n\n";
}

// typesString
if (preg_match('/\$typesString = "([^"]+)";/', $content, $matches)) {
    $current = $matches[1];
    echo "typesString: $current\n";
    echo "Tamanho: " . strlen($current) . "\n";
    echo "Correto: " . ($current === $correctString ? "SIM" : "NÃO") . "\n\n";
}

// Corrigir todas as ocorrências
echo "=== APLICANDO CORREÇÕES ===\n";

// Corrigir UPDATE
$content = preg_replace(
    '/\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
    '$stmt->bind_param("' . $correctString . '"',
    $content,
    1
);
echo "✓ UPDATE corrigido\n";

// Corrigir INSERT
$content = preg_replace(
    '/\$bindResult = @\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
    '$bindResult = @$stmt->bind_param("' . $correctString . '"',
    $content,
    1
);
echo "✓ INSERT corrigido\n";

// Corrigir typesString
$content = preg_replace(
    '/\$typesString = "ssssssssssssssssssssssssss[^"]+";/',
    '$typesString = "' . $correctString . '";',
    $content
);
echo "✓ typesString corrigido\n";

file_put_contents($file, $content);
echo "\n✓ Arquivo salvo!\n";
?>


