<?php
$s = "sssssssssssssssssssssssssssisssiisi";
echo "String: $s\n";
echo "Tamanho: " . strlen($s) . "\n";
echo "Caracteres:\n";
for ($i = 0; $i < strlen($s); $i++) {
    echo ($i+1) . ": " . $s[$i] . "\n";
}
?>


