<?php
// String correta de 36 caracteres
// Baseada na ordem: 1-28: s, 29: i, 30-32: s, 33: i, 34: i, 35: s, 36: i
$correct36 = "sssssssssssssssssssssssssssisssiisi";

echo "String correta: $correct36\n";
echo "Tamanho: " . strlen($correct36) . "\n\n";

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// Substituir todas as ocorrências da string de 35 caracteres pela de 36
$content = str_replace('bind_param("ssssssssssssssssssssssssssisssiisi"', 'bind_param("' . $correct36 . '"', $content);
$content = str_replace('$typesString = "ssssssssssssssssssssssssssisssiisi"', '$typesString = "' . $correct36 . '"', $content);

file_put_contents($file, $content);
echo "✓ Correção aplicada!\n";

// Verificar
if (strpos($content, $correct36) !== false) {
    echo "✓ String correta encontrada no arquivo!\n";
} else {
    echo "✗ String correta NÃO encontrada!\n";
}
?>


