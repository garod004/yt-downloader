<?php
$correct36 = '';
for ($i = 1; $i <= 28; $i++) $correct36 .= 's';
$correct36 .= 'i';
for ($i = 30; $i <= 32; $i++) $correct36 .= 's';
$correct36 .= 'i';
$correct36 .= 'i';
$correct36 .= 's';
$correct36 .= 'i';

echo "String correta: $correct36\n";
echo "Tamanho: " . strlen($correct36) . "\n\n";

$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

$allCorrect = true;

if (preg_match('/\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    $correct = ($current === $correct36);
    echo "UPDATE: " . strlen($current) . " chars - " . ($correct ? "✓" : "✗") . "\n";
    if (!$correct) $allCorrect = false;
}

if (preg_match('/\$bindResult = @\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $current = $matches[1];
    $correct = ($current === $correct36);
    echo "INSERT: " . strlen($current) . " chars - " . ($correct ? "✓" : "✗") . "\n";
    if (!$correct) $allCorrect = false;
}

if (preg_match('/\$typesString = "([^"]+)";/', $content, $matches)) {
    $current = $matches[1];
    $correct = ($current === $correct36);
    echo "typesString: " . strlen($current) . " chars - " . ($correct ? "✓" : "✗") . "\n";
    if (!$correct) $allCorrect = false;
}

echo "\n" . ($allCorrect ? "✓ TODAS AS STRINGS ESTÃO CORRETAS!" : "✗ ALGUMAS STRINGS ESTÃO INCORRETAS!") . "\n";
?>


