<?php
$file = 'salvar_cliente_ajax.php';
$lines = file($file);

// String correta: 36 caracteres
$correct = "sssssssssssssssssssssssssssisssiisi";

echo "String correta: $correct\n";
echo "Tamanho: " . strlen($correct) . "\n\n";

// Linha 197 (UPDATE) - índice 196
if (isset($lines[196])) {
    $line197 = $lines[196];
    echo "Linha 197: " . trim($line197) . "\n";
    if (preg_match('/bind_param\("([^"]+)"/', $line197, $matches)) {
        $current = $matches[1];
        echo "String atual: $current\n";
        echo "Tamanho atual: " . strlen($current) . "\n";
        if ($current !== $correct) {
            $lines[196] = str_replace('bind_param("' . $current . '"', 'bind_param("' . $correct . '"', $line197);
            echo "✓ Linha 197 corrigida!\n";
        }
    }
}

// Linha 319 (INSERT) - índice 318
if (isset($lines[318])) {
    $line319 = $lines[318];
    echo "\nLinha 319: " . trim($line319) . "\n";
    if (preg_match('/bind_param\("([^"]+)"/', $line319, $matches)) {
        $current = $matches[1];
        echo "String atual: $current\n";
        echo "Tamanho atual: " . strlen($current) . "\n";
        if ($current !== $correct) {
            $lines[318] = str_replace('bind_param("' . $current . '"', 'bind_param("' . $correct . '"', $line319);
            echo "✓ Linha 319 corrigida!\n";
        }
    }
}

// Linha 261 (typesString) - índice 260
if (isset($lines[260])) {
    $line261 = $lines[260];
    echo "\nLinha 261: " . trim($line261) . "\n";
    if (preg_match('/\$typesString = "([^"]+)";/', $line261, $matches)) {
        $current = $matches[1];
        echo "String atual: $current\n";
        echo "Tamanho atual: " . strlen($current) . "\n";
        if ($current !== $correct) {
            $lines[260] = preg_replace('/\$typesString = "[^"]+";/', '$typesString = "' . $correct . '";', $line261);
            echo "✓ Linha 261 corrigida!\n";
        }
    }
}

file_put_contents($file, implode('', $lines));
echo "\n✓ Arquivo salvo!\n";
?>


