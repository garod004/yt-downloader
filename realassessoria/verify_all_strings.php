<?php
// String correta de 36 caracteres
$correct36 = "sssssssssssssssssssssssssssisssiisi";

echo "String correta: $correct36\n";
echo "Tamanho: " . strlen($correct36) . "\n\n";

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// Verificar UPDATE
if (preg_match('/\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    echo "UPDATE: $current\n";
    echo "Tamanho: " . strlen($current) . "\n";
    echo "Correto: " . ($current === $correct36 ? "SIM ✓" : "NÃO ✗") . "\n\n";
}

// Verificar INSERT
if (preg_match('/\$bindResult = @\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    echo "INSERT: $current\n";
    echo "Tamanho: " . strlen($current) . "\n";
    echo "Correto: " . ($current === $correct36 ? "SIM ✓" : "NÃO ✗") . "\n\n";
}

// Verificar typesString
if (preg_match('/\$typesString = "([^"]+)";/', $content, $matches)) {
    $current = $matches[1];
    echo "typesString: $current\n";
    echo "Tamanho: " . strlen($current) . "\n";
    echo "Correto: " . ($current === $correct36 ? "SIM ✓" : "NÃO ✗") . "\n";
}
?>


