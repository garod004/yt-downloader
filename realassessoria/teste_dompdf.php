<?php
// Diagnóstico: mostrar todos os erros PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('debugKeepTemp', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml('<h1>Teste Dompdf funcionando</h1><p>Se você vê este PDF, o Dompdf está OK.</p>');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('teste_dompdf.pdf', array('Attachment' => 0));
exit;
