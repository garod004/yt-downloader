<?php
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $GLOBALS['DOMPDF_AUTOLOAD_MISSING'] = true;
    return;
}

$GLOBALS['DOMPDF_AUTOLOAD_MISSING'] = false;
require_once $autoloadPath;
?>
