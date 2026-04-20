<?php
// parser_cnis_pdf.php
// Função para ler arquivo CNIS em PDF e calcular tempo de contribuição
// Requer a biblioteca Smalot\PdfParser (https://github.com/smalot/pdfparser)

require_once __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Parser;

function parse_cnis_pdf($filePath) {

    $texto_extraido = '';
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        $texto_extraido = $text;
    } catch (Exception $e) {
        return ['error' => 'Erro ao ler o PDF: ' . $e->getMessage()];
    }

    $lines = explode("\n", $text);
    $periodos = [];
    $totalDias = 0;
    foreach ($lines as $line) {
        // Procurar duas datas dd/mm/yyyy na mesma linha (Data Início e Data Fim)
        if (preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $line, $matches) && count($matches[1]) >= 2) {
            $inicio = DateTime::createFromFormat('d/m/Y', $matches[1][0]);
            $fim = DateTime::createFromFormat('d/m/Y', $matches[1][1]);
            if ($inicio && $fim) {
                $interval = $inicio->diff($fim);
                $dias = $interval->days + 1;
                $totalDias += $dias;
                $periodos[] = [
                    'inicio' => $matches[1][0],
                    'fim' => $matches[1][1],
                    'dias' => $dias
                ];
            }
        }
    }
    $anos = floor($totalDias / 365);
    $meses = floor(($totalDias % 365) / 30);
    $dias = $totalDias % 30;
    return [
        'periodos' => $periodos,
        'total_dias' => $totalDias,
        'tempo_formatado' => "$anos anos, $meses meses, $dias dias",
        'texto_extraido' => $texto_extraido
    ];
}
?>