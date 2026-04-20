<?php
$correct36 = str_repeat('s', 28) . 'i' . str_repeat('s', 3) . 'i' . 'i' . 's' . 'i';

echo "String correta: $correct36\n";
echo "Tamanho: " . strlen($correct36) . "\n\n";

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

$allCorrect = true;

if (preg_match('/\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    $correct = ($current === $correct36);
    echo "UPDATE: " . strlen($current) . " chars - " . ($correct ? "✓ CORRETO" : "✗ INCORRETO") . "\n";
    if (!$correct) {
        echo "  Esperado: $correct36\n";
        echo "  Encontrado: $current\n";
        $allCorrect = false;
    }
}

if (preg_match('/\$bindResult = @\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    $correct = ($current === $correct36);
    echo "INSERT: " . strlen($current) . " chars - " . ($correct ? "✓ CORRETO" : "✗ INCORRETO") . "\n";
    if (!$correct) {
        echo "  Esperado: $correct36\n";
        echo "  Encontrado: $current\n";
        $allCorrect = false;
    }
}

if (preg_match('/\$typesString = "([^"]+)";/', $content, $matches)) {
    $current = $matches[1];
    $correct = ($current === $correct36);
    echo "typesString: " . strlen($current) . " chars - " . ($correct ? "✓ CORRETO" : "✗ INCORRETO") . "\n";
    if (!$correct) {
        echo "  Esperado: $correct36\n";
        echo "  Encontrado: $current\n";
        $allCorrect = false;
    }
}

echo "\n" . ($allCorrect ? "✓ TODAS AS STRINGS ESTÃO CORRETAS COM 36 CARACTERES!" : "✗ ALGUMAS STRINGS AINDA ESTÃO INCORRETAS!") . "\n";
?>



