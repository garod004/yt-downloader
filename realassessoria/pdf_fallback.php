<?php
if (!function_exists('pdf_fallback_escape')) {
    function pdf_fallback_escape($text) {
        $t = str_replace('\\', '\\\\', (string)$text);
        $t = str_replace('(', '\\(', $t);
        $t = str_replace(')', '\\)', $t);
        return $t;
    }
}

if (!function_exists('pdf_fallback_to_winansi')) {
    function pdf_fallback_to_winansi($text) {
        $text = (string)$text;
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($conv !== false) {
                return $conv;
            }
        }
        return preg_replace('/[^\x20-\x7E\r\n\t]/', '', $text);
    }
}

if (!function_exists('pdf_fallback_build')) {
    function pdf_fallback_build($title, $lines) {
        $objects = array();

        $stream = "BT\n/F1 12 Tf\n";
        $titleText = pdf_fallback_escape(pdf_fallback_to_winansi($title));
        $stream .= "1 0 0 1 50 800 Tm ({$titleText}) Tj\n";

        $y = 780;
        foreach ($lines as $line) {
            if ($y < 40) {
                break;
            }
            $line = pdf_fallback_escape(pdf_fallback_to_winansi($line));
            $stream .= "1 0 0 1 50 {$y} Tm ({$line}) Tj\n";
            $y -= 14;
        }
        $stream .= "ET";

        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[] = "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = array();
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}

if (!function_exists('pdf_fallback_download')) {
    function pdf_fallback_download($fileName, $title, $bodyText) {
        $bodyText = str_replace("\r\n", "\n", (string)$bodyText);
        $rawLines = explode("\n", $bodyText);
        $lines = array();

        foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line === '') {
                $lines[] = ' ';
                continue;
            }

            while (strlen($line) > 95) {
                $chunk = substr($line, 0, 95);
                $breakPos = strrpos($chunk, ' ');
                if ($breakPos === false) {
                    $breakPos = 95;
                }
                $lines[] = trim(substr($line, 0, $breakPos));
                $line = trim(substr($line, $breakPos));
            }
            $lines[] = $line;
        }

        $pdf = pdf_fallback_build($title, $lines);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit();
    }
}
?>
