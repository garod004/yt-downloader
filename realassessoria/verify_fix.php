<?php
// Contar parâmetros na ordem exata do bind_param
$params = [
    'nome', 'cpf', 'data_nascimento', 'rg',  // 1-4: 4s
    'estado_civil', 'nacionalidade', 'profissao',  // 5-7: 3s
    'senha_meuinss', 'senha_email', 'beneficio', 'situacao',  // 8-11: 4s
    'indicador', 'responsavel', 'advogado',  // 12-14: 3s
    'endereco', 'cidade', 'uf', 'cep',  // 15-18: 4s
    'telefone', 'telefone2', 'telefone3', 'email',  // 19-22: 4s
    'data_contrato', 'data_enviado', 'numero_processo',  // 23-25: 3s
    'data_avaliacao_social', 'hora_avaliacao_social', 'endereco_avaliacao_social', 'avaliacao_social_realizado',  // 26-29: 3s + 1i
    'data_pericia', 'hora_pericia', 'endereco_pericia', 'pericia_realizado',  // 30-33: 3s + 1i
    'contrato_assinado', 'observacao',  // 34-35: 1i + 1s
    'usuario_id'  // 36: 1i
];

$types = [];
foreach ($params as $i => $param) {
    if (strpos($param, 'realizado') !== false || strpos($param, 'assinado') !== false || $param === 'usuario_id') {
        $types[] = 'i';
    } else {
        $types[] = 's';
    }
}

$correctString = implode('', $types);
echo "String correta gerada: $correctString\n";
echo "Tamanho: " . strlen($correctString) . "\n";
echo "Total de parâmetros: " . count($params) . "\n\n";

// Verificar arquivo
$file = 'salvar_cliente_ajax.php';
$content = file_get_contents($file);

// Extrair string do bind_param
if (preg_match('/bind_param\("([^"]+)"/', $content, $matches)) {
    $found = $matches[1];
    echo "String encontrada no arquivo: $found\n";
    echo "Tamanho encontrado: " . strlen($found) . "\n\n";
    
    if ($found !== $correctString) {
        echo "CORRIGINDO...\n";
        $content = str_replace('bind_param("' . $found . '"', 'bind_param("' . $correctString . '"', $content);
        $content = preg_replace('/\$typesString = "[^"]+";/', '$typesString = "' . $correctString . '";', $content);
        file_put_contents($file, $content);
        echo "✓ Correção aplicada!\n";
    } else {
        echo "✓ String já está correta!\n";
    }
}
?>


