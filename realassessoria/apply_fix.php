<?php
// String correta com 36 caracteres: sssssssssssssssssssssssssssisssiisi
$correct36 = "sssssssssssssssssssssssssssisssiisi";

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

echo "String correta (36 chars): $correct36\n";
echo "Tamanho: " . strlen($correct36) . "\n\n";

// Substituir bind_param na linha 319 (INSERT)
$content = preg_replace(
    '/bind_param\("ssssssssssssssssssssssssss[^"]+"\)/',
    'bind_param("' . $correct36 . '"',
    $content,
    1
);

// Substituir $typesString
$content = preg_replace(
    '/\$typesString = "ssssssssssssssssssssssssss[^"]+";/',
    '$typesString = "' . $correct36 . '";',
    $content
);

file_put_contents($file, $content);

echo "✓ Correção aplicada!\n";
?>


