<?php

function beneficio_normalizar_chave($valor) {
    $texto = trim((string)$valor);
    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        $texto = mb_strtoupper($texto, 'UTF-8');
    } else {
        $texto = strtoupper($texto);
    }

    $texto = strtr($texto, array(
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C'
    ));

    $texto = preg_replace('/[-_]+/u', ' ', $texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);
    return trim($texto);
}

function beneficio_coluna_segura($coluna) {
    $coluna = trim((string)$coluna);
    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $coluna)) {
        return $coluna;
    }
    return 'beneficio';
}

function beneficio_obter_condicao($beneficio, $coluna = 'beneficio') {
    $chave = beneficio_normalizar_chave($beneficio);
    if ($chave === '') {
        return null;
    }

    $c = beneficio_coluna_segura($coluna);

    $mapa = array(
        'BPC POR DOENCA' => "($c LIKE '%doen%' OR $c = 'bpc_doenca')",
        'BPC POR IDADE' => "($c LIKE '%idade%' OR $c = 'bpc_idade')",
        'AUXILIO DOENCA' => "(($c LIKE '%uxil%' AND $c LIKE '%oen%') OR $c = 'auxilio_doenca')",
        'AUXILIO ACIDENTE' => "($c LIKE '%uxil%' AND $c LIKE '%acident%')",
        'AUXILIO RECLUSAO' => "($c LIKE '%uxil%' AND $c LIKE '%reclus%')",
        'PENSAO POR MORTE' => "(($c LIKE '%ens%' AND $c LIKE '%morte%') OR $c = 'persao_por_morte')",
        'APOSENTADORIA DO AGRICULTOR' => "($c LIKE '%posentadoria%' AND $c LIKE '%agricult%')",
        'APOSENTADORIA AGRICULTOR' => "($c LIKE '%posentadoria%' AND $c LIKE '%agricult%')",
        'APOSENTADORIA PESCADOR' => "($c LIKE '%posentadoria%' AND $c LIKE '%pescador%')",
        'APOSENTADORIA INDIGENA' => "($c LIKE '%posentadoria%' AND ($c LIKE '%indigena%' OR $c LIKE '%indígena%'))",
        'APOSENTADORIA POR TEMPO DE CONTRIBUICAO URBANA' => "($c LIKE '%tempo%' AND $c LIKE '%contribui%' AND $c LIKE '%urban%')",
        'APOSENTADORIA POR TEMPO DE CONTRIBUICAO URBANO' => "($c LIKE '%tempo%' AND $c LIKE '%contribui%' AND $c LIKE '%urban%')",
        'APOSENTADORIA ESPECIAL URBANA' => "($c LIKE '%especial%' AND $c LIKE '%urban%')",
        'APOSENTADORIA ESPECIAL URBANO' => "($c LIKE '%especial%' AND $c LIKE '%urban%')",
        'APOSENTADORIA POR INVALIDEZ URBANA' => "($c LIKE '%invalidez%' AND $c LIKE '%urban%')",
        'APOSENTADORIA POR INVALIDEZ URBANO' => "($c LIKE '%invalidez%' AND $c LIKE '%urban%')",
        'APOSENTADORIA HIBRIDA' => "($c LIKE '%hibrid%')",
        'SALARIO MATERNIDADE URBANO' => "($c LIKE '%aternidade%' AND $c LIKE '%urban%')",
        'SALARIO MATERNIDADE AGRICULTORA' => "($c LIKE '%aternidade%' AND $c LIKE '%agricultora%')",
        'SALARIO MATERNIDADE PESCADORA' => "($c LIKE '%aternidade%' AND $c LIKE '%pescadora%')",
        'SALARIO MATERNIDADE INDIGENA' => "($c LIKE '%aternidade%' AND ($c LIKE '%indigena%' OR $c LIKE '%indígena%'))",
        'DIVORCIO' => "($c LIKE '%divorc%')",
        'ACAO TRABALHISTA' => "(($c LIKE '%acao%' OR $c LIKE '%ação%') AND $c LIKE '%trabalh%')",
        'EMPRESTIMO' => "($c LIKE '%mprestimo%' OR $c LIKE '%emprestimo%')",
        'REGULARIZACAO DE TERRAS' => "($c LIKE '%regulariza%' AND $c LIKE '%terra%')",
        'REGULARIZACAO DE IMOVEIS' => "($c LIKE '%regulariza%' AND ($c LIKE '%imovei%' OR $c LIKE '%imóvei%'))",
        'PASSAPORT' => "($c LIKE '%passaport%')",
        '2 VIA DE DOCUMENTOS' => "(($c LIKE '%2%' AND $c LIKE '%via%' AND $c LIKE '%document%') OR ($c LIKE '%via%' AND $c LIKE '%document%'))",
        'ISENCAO DE IMPOSTO DEFICIENTE' => "($c LIKE '%isencao%' AND $c LIKE '%imposto%' AND $c LIKE '%deficiente%')",
        'JUROS ABUSIVOS' => "($c LIKE '%juros%' AND $c LIKE '%abusiv%')",
        'COBRANCAS INDEVIDAS' => "($c LIKE '%cobranc%' AND $c LIKE '%indevid%')",
        'ACAO JUDICIAL' => "(($c LIKE '%acao%' OR $c LIKE '%ação%') AND $c LIKE '%judicial%')"
    );

    if (isset($mapa[$chave])) {
        return $mapa[$chave];
    }

    if (strpos($chave, 'SALARIO MATERNIDADE') !== false) {
        return "($c LIKE '%aternidade%')";
    }
    if (strpos($chave, 'APOSENTADORIA') !== false) {
        return "($c LIKE '%posentadoria%')";
    }

    return null;
}

function beneficio_aplicar_filtro(&$sql, &$types, array &$params, $beneficio, $coluna = 'beneficio') {
    $condicao = beneficio_obter_condicao($beneficio, $coluna);
    if ($condicao !== null) {
        $sql .= " AND " . $condicao;
        return;
    }

    $c = beneficio_coluna_segura($coluna);
    $sql .= " AND " . $c . " = ?";
    $types .= 's';
    $params[] = $beneficio;
}

function beneficio_contar_clientes(mysqli $conn, $beneficio, $coluna = 'beneficio') {
    $condicao = beneficio_obter_condicao($beneficio, $coluna);
    if ($condicao !== null) {
        $sql = "SELECT COUNT(*) AS total FROM clientes WHERE " . $condicao;
        $result = $conn->query($sql);
        if ($result && ($row = $result->fetch_assoc())) {
            return (int)$row['total'];
        }
        return 0;
    }

    $c = beneficio_coluna_segura($coluna);
    $sql = "SELECT COUNT(*) AS total FROM clientes WHERE " . $c . " = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $beneficio);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $result = $stmt->get_result();
    $total = 0;
    if ($result && ($row = $result->fetch_assoc())) {
        $total = (int)$row['total'];
    }
    $stmt->close();
    return $total;
}
