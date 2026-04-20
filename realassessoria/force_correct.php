<?php
// String correta: 28s + i + 3s + i + i + s + i = 36 caracteres
$correct36 = '';
for ($i = 0; $i < 28; $i++) $correct36 .= 's';
$correct36 .= 'i';
for ($i = 0; $i < 3; $i++) $correct36 .= 's';
$correct36 .= 'i';
$correct36 .= 'i';
$correct36 .= 's';
$correct36 .= 'i';

echo "String correta gerada: $correct36\n";
echo "Tamanho: " . strlen($correct36) . "\n\n";

$file = 'salvar_cliente_ajax.php';
$lines = file($file);

// Linha 197 (índice 196) - UPDATE
if (isset($lines[196])) {
    $old = $lines[196];
    $lines[196] = preg_replace('/bind_param\("[^"]+"/', 'bind_param("' . $correct36 . '"', $old);
    if ($old !== $lines[196]) {
        echo "✓ Linha 197 corrigida\n";
    }
}

// Linha 319 (índice 318) - INSERT
if (isset($lines[318])) {
    $old = $lines[318];
    $lines[318] = preg_replace('/bind_param\("[^"]+"/', 'bind_param("' . $correct36 . '"', $old);
    if ($old !== $lines[318]) {
        echo "✓ Linha 319 corrigida\n";
    }
}

// Linha 261 (índice 260) - typesString
if (isset($lines[260])) {
    $old = $lines[260];
    $lines[260] = preg_replace('/\$typesString = "[^"]+";/', '$typesString = "' . $correct36 . '";', $old);
    if ($old !== $lines[260]) {
        echo "✓ Linha 261 corrigida\n";
    }
}

file_put_contents($file, implode('', $lines));
echo "\n✓ Arquivo salvo!\n\n";

// Verificar
$content = file_get_contents($file);
if (preg_match('/\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $found = $matches[1];
    echo "UPDATE: " . strlen($found) . " chars - " . ($found === $correct36 ? "✓ CORRETO" : "✗ INCORRETO") . "\n";
    if ($found !== $correct36) {
        echo "  Esperado: $correct36\n";
        echo "  Encontrado: $found\n";
    }
}
if (preg_match('/\$bindResult = @\$stmt->bind_param\("([^"]+)"/', $content, $matches)) {
    $found = $matches[1];
    echo "INSERT: " . strlen($found) . " chars - " . ($found === $correct36 ? "✓ CORRETO" : "✗ INCORRETO") . "\n";
    if ($found !== $correct36) {
        echo "  Esperado: $correct36\n";
        echo "  Encontrado: $found\n";
    }
}
if (preg_match('/\$typesString = "([^"]+)";/', $content, $matches)) {
    $found = $matches[1];
    echo "typesString: " . strlen($found) . " chars - " . ($found === $correct36 ? "✓ CORRETO" : "✗ INCORRETO") . "\n";
    if ($found !== $correct36) {
        echo "  Esperado: $correct36\n";
        echo "  Encontrado: $found\n";
    }
}
?>


