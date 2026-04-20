<?php
$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// String correta: 36 caracteres
// 1-28: s (28 caracteres)
// 29: i
// 30-32: s (3 caracteres)
// 33: i
// 34: i
// 35: s
// 36: i
$correct = "sssssssssssssssssssssssssssisssiisi";

echo "String correta: $correct\n";
echo "Tamanho: " . strlen($correct) . "\n\n";

// Substituir todas as ocorrências incorretas
$patterns = [
    '/\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/' => '$stmt->bind_param("' . $correct . '"',
    '/\$bindResult = @\$stmt->bind_param\("ssssssssssssssssssssssssss[^"]+"\)/' => '$bindResult = @$stmt->bind_param("' . $correct . '"',
    '/\$typesString = "ssssssssssssssssssssssssss[^"]+";/' => '$typesString = "' . $correct . '";'
];

foreach ($patterns as $pattern => $replacement) {
    $content = preg_replace($pattern, $replacement, $content);
}

file_put_contents($file, $content);
echo "✓ Arquivo corrigido!\n";
?>


