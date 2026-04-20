<?php
// parser_cnis_txt.php
// Função para ler arquivo CNIS em TXT e calcular tempo de contribuição

function parse_cnis_txt($filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['error' => 'Não foi possível abrir o arquivo.'];
    }

    $periodos = [];
    $totalDias = 0;
    while (($line = fgets($handle)) !== false) {
        // Exemplo de linha: "01/01/2000 a 31/12/2005 EMPRESA XYZ ..."
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s*a\s*(\d{2}\/\d{2}\/\d{4})/', $line, $matches)) {
            $inicio = DateTime::createFromFormat('d/m/Y', $matches[1]);
            $fim = DateTime::createFromFormat('d/m/Y', $matches[2]);
            if ($inicio && $fim) {
                $interval = $inicio->diff($fim);
                $dias = $interval->days + 1; // inclui o dia final
                $totalDias += $dias;
                $periodos[] = [
                    'inicio' => $matches[1],
                    'fim' => $matches[2],
                    'dias' => $dias
                ];
            }
        }
    }
    fclose($handle);

    $anos = floor($totalDias / 365);
    $meses = floor(($totalDias % 365) / 30);
    $dias = $totalDias % 30;

    return [
        'periodos' => $periodos,
        'total_dias' => $totalDias,
        'tempo_formatado' => "$anos anos, $meses meses, $dias dias"
    ];
}
?>