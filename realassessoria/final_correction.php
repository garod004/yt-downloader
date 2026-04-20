<?php
$file = 'salvar_cliente_ajax.php';
$lines = file($file);

// String correta: 36 caracteres
$correct = "sssssssssssssssssssssssssssisssiisi";

echo "String correta: $correct\n";
echo "Tamanho: " . strlen($correct) . "\n\n";

// Linha 319 (índice 318)
$line319 = $lines[318];
echo "Linha 319 atual: " . trim($line319) . "\n";

// Extrair string atual
if (preg_match('/bind_param\("([^"]+)"/', $line319, $matches)) {
    $current = $matches[1];
    echo "String atual extraída: $current\n";
    echo "Tamanho atual: " . strlen($current) . "\n\n";
    
    if ($current !== $correct) {
        echo "CORRIGINDO linha 319...\n";
        $lines[318] = str_replace('bind_param("' . $current . '"', 'bind_param("' . $correct . '"', $line319);
        echo "Linha 319 corrigida: " . trim($lines[318]) . "\n";
    } else {
        echo "Linha 319 já está correta!\n";
    }
}

// Corrigir $typesString (linha 261, índice 260)
$line261 = $lines[260];
if (preg_match('/\$typesString = "([^"]+)";/', $line261, $matches)) {
    $current = $matches[1];
    echo "\ntypesString atual: $current\n";
    echo "Tamanho: " . strlen($current) . "\n";
    
    if ($current !== $correct) {
        echo "CORRIGINDO typesString...\n";
        $lines[260] = preg_replace('/\$typesString = "[^"]+";/', '$typesString = "' . $correct . '";', $line261);
        echo "typesString corrigido!\n";
    } else {
        echo "typesString já está correto!\n";
    }
}

// Salvar
file_put_contents($file, implode('', $lines));
echo "\n✓ Arquivo salvo!\n";
?>


