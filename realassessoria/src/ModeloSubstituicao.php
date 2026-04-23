<?php

class ModeloSubstituicao
{
    public static function formatarCpf(?string $cpf): string
    {
        $numeros = preg_replace('/\D/', '', (string)($cpf ?? ''));
        if (strlen($numeros) !== 11) {
            return (string)($cpf ?? '');
        }
        return substr($numeros, 0, 3) . '.'
             . substr($numeros, 3, 3) . '.'
             . substr($numeros, 6, 3) . '-'
             . substr($numeros, 9, 2);
    }

    public static function formatarData(?string $data): string
    {
        if (empty($data) || $data === '0000-00-00') return '';
        $ts = strtotime($data);
        return $ts !== false ? date('d/m/Y', $ts) : '';
    }

    public static function dataHojeExtenso(): string
    {
        $meses = [
            '', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
        ];
        return date('d') . ' de ' . $meses[(int)date('m')] . ' de ' . date('Y');
    }

    public static function construirMapa(
        array $cliente,
        array $empresa,
        string $usuarioNome = '',
        array $advogados = [],
        array $filho = [],
        array $incapaz = [],
        array $aRogo = []
    ): array {
        $h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $mapa = [
            // CLIENTE
            '{{cliente_nome}}'            => $h($cliente['nome']            ?? ''),
            '{{cliente_cpf}}'             => $h(self::formatarCpf($cliente['cpf'] ?? null)),
            '{{cliente_rg}}'              => $h($cliente['rg']              ?? ''),
            '{{cliente_data_nascimento}}' => $h(self::formatarData($cliente['data_nascimento'] ?? null)),
            '{{cliente_estado_civil}}'    => $h($cliente['estado_civil']    ?? ''),
            '{{cliente_profissao}}'       => $h($cliente['profissao']       ?? ''),
            '{{cliente_telefone}}'        => $h($cliente['telefone']        ?? ''),
            '{{cliente_email}}'           => $h($cliente['email']           ?? ''),
            '{{cliente_endereco}}'        => $h($cliente['endereco']        ?? ''),
            '{{cliente_cep}}'             => $h($cliente['cep']             ?? ''),
            '{{cliente_cidade}}'          => $h($cliente['cidade']          ?? ''),
            '{{cliente_uf}}'              => $h($cliente['uf']              ?? ''),
            '{{cliente_nacionalidade}}'   => $h($cliente['nacionalidade']   ?? ''),
            '{{cliente_beneficio}}'       => $h($cliente['beneficio']       ?? ''),
            '{{cliente_numero_processo}}' => $h($cliente['numero_processo'] ?? ''),
            '{{cliente_situacao}}'        => $h($cliente['situacao']        ?? ''),

            // EMPRESA
            '{{empresa_nome}}'            => $h($empresa['empresa_nome']          ?? ''),
            '{{empresa_cnpj}}'            => $h($empresa['empresa_cnpj']          ?? ''),
            '{{empresa_fone}}'            => $h($empresa['empresa_fone']          ?? ''),
            '{{empresa_email}}'           => $h($empresa['empresa_email']         ?? ''),
            '{{empresa_proprietarios}}'   => $h($empresa['empresa_proprietarios'] ?? ''),
            '{{empresa_endereco}}'        => $h($empresa['empresa_endereco']      ?? ''),
            '{{empresa_cidade}}'          => $h($empresa['empresa_cidade']        ?? ''),

            // DATA E USUÁRIO
            '{{data_hoje}}'               => $h(date('d/m/Y')),
            '{{data_hoje_extenso}}'       => $h(self::dataHojeExtenso()),
            '{{usuario_nome}}'            => $h($usuarioNome),

            // FILHO MENOR
            '{{filho_nome}}'              => $h($filho['nome']            ?? ''),
            '{{filho_cpf}}'               => $h(self::formatarCpf($filho['cpf'] ?? null)),
            '{{filho_data_nascimento}}'   => $h(self::formatarData($filho['data_nascimento'] ?? null)),

            // INCAPAZ
            '{{incapaz_nome}}'            => $h($incapaz['nome']            ?? ''),
            '{{incapaz_cpf}}'             => $h(self::formatarCpf($incapaz['cpf'] ?? null)),
            '{{incapaz_data_nascimento}}' => $h(self::formatarData($incapaz['data_nascimento'] ?? null)),

            // A ROGO
            '{{a_rogo_nome}}'             => $h($aRogo['nome']       ?? ''),
            '{{a_rogo_identidade}}'       => $h($aRogo['identidade'] ?? ''),
            '{{a_rogo_cpf}}'              => $h(self::formatarCpf($aRogo['cpf'] ?? null)),
        ];

        // ADVOGADOS 1, 2, 3
        for ($i = 1; $i <= 3; $i++) {
            $adv = $advogados[$i - 1] ?? [];
            $mapa["{{advogado_{$i}_nome}}"]      = $h($adv['nome']      ?? '');
            $mapa["{{advogado_{$i}_documento}}"] = $h($adv['documento'] ?? '');
            $mapa["{{advogado_{$i}_oab}}"]       = $h($adv['oab']       ?? '');
            $mapa["{{advogado_{$i}_endereco}}"]  = $h($adv['endereco']  ?? '');
            $mapa["{{advogado_{$i}_cidade}}"]    = $h($adv['cidade']    ?? '');
            $mapa["{{advogado_{$i}_uf}}"]        = $h($adv['uf']        ?? '');
            $mapa["{{advogado_{$i}_fone}}"]      = $h($adv['fone']      ?? '');
            $mapa["{{advogado_{$i}_email}}"]     = $h($adv['email']     ?? '');
        }

        // Aliases sem número → advogado 1
        $mapa['{{advogado_nome}}']      = $mapa['{{advogado_1_nome}}'];
        $mapa['{{advogado_documento}}'] = $mapa['{{advogado_1_documento}}'];
        $mapa['{{advogado_oab}}']       = $mapa['{{advogado_1_oab}}'];
        $mapa['{{advogado_endereco}}']  = $mapa['{{advogado_1_endereco}}'];
        $mapa['{{advogado_cidade}}']    = $mapa['{{advogado_1_cidade}}'];
        $mapa['{{advogado_uf}}']        = $mapa['{{advogado_1_uf}}'];
        $mapa['{{advogado_fone}}']      = $mapa['{{advogado_1_fone}}'];
        $mapa['{{advogado_email}}']     = $mapa['{{advogado_1_email}}'];

        return $mapa;
    }

    public static function substituir(string $conteudo, array $mapa): string
    {
        $html = str_replace(array_keys($mapa), array_values($mapa), $conteudo);
        return self::limparCamposVazios($html);
    }

    private static function limparCamposVazios(string $html): string
    {
        // Colapsar vírgulas consecutivas: "SILVA, , SOLTEIRO" → "SILVA, SOLTEIRO"
        do {
            $prev = $html;
            $html = preg_replace('/,\s*,/', ', ', $html);
        } while ($html !== $prev);

        // Remover rótulo vazio antes de pontuação: ", e-mail: ." ou ", fone: ," → ""
        $html = preg_replace('/,\s*\b(?:e-mail|email|fone|telefone|tel\.?):\s*(?=[,.])/ui', '', $html);

        // Remover preposição seguida de vírgula (campo de endereço vazio): "em , Rua" → "em Rua"
        $html = preg_replace('/\b(em|na|no|à|ao|pela|pelo)\s+,\s*/u', '$1 ', $html);

        // Remover vírgula antes de barra de UF: ", /AM" → "/AM"
        $html = preg_replace('/,\s*\/([A-Z]{2})\b/', '/$1', $html);

        // Remover vírgula antes de ponto final: "telefone: (xx),." → "telefone: (xx)."
        $html = preg_replace('/,(\s*\.)/', '$1', $html);

        return $html;
    }

    public static function extrairMarcadores(string $conteudo): array
    {
        preg_match_all('/\{\{[a-z0-9_]+\}\}/', $conteudo, $matches);
        return array_unique($matches[0]);
    }

    public static function marcadoresInvalidos(string $conteudo, array $mapa): array
    {
        return array_values(array_diff(self::extrairMarcadores($conteudo), array_keys($mapa)));
    }

    public static function validarNomeModelo(string $nome): bool
    {
        $nome = trim($nome);
        return $nome !== '' && strlen($nome) <= 150;
    }

    public static function categorias(): array
    {
        return ['Geral', 'Contrato', 'Procuração', 'Declaração', 'Requerimento', 'Ofício', 'Outro'];
    }

    public static function validarCategoria(string $categoria): bool
    {
        return in_array($categoria, self::categorias(), true);
    }

    public static function obterGruposMarcadores(): array
    {
        return [
            'Cliente' => [
                '{{cliente_nome}}', '{{cliente_cpf}}', '{{cliente_rg}}',
                '{{cliente_data_nascimento}}', '{{cliente_estado_civil}}', '{{cliente_profissao}}',
                '{{cliente_telefone}}', '{{cliente_email}}', '{{cliente_endereco}}',
                '{{cliente_cep}}', '{{cliente_cidade}}', '{{cliente_uf}}',
                '{{cliente_nacionalidade}}', '{{cliente_beneficio}}',
                '{{cliente_numero_processo}}', '{{cliente_situacao}}',
            ],
            'Empresa' => [
                '{{empresa_nome}}', '{{empresa_cnpj}}', '{{empresa_fone}}',
                '{{empresa_email}}', '{{empresa_proprietarios}}',
                '{{empresa_endereco}}', '{{empresa_cidade}}',
            ],
            'Data / Usuário' => [
                '{{data_hoje}}', '{{data_hoje_extenso}}', '{{usuario_nome}}',
            ],
            'Advogado 1' => [
                '{{advogado_1_nome}}', '{{advogado_1_oab}}', '{{advogado_1_documento}}',
                '{{advogado_1_endereco}}', '{{advogado_1_cidade}}', '{{advogado_1_uf}}',
                '{{advogado_1_fone}}', '{{advogado_1_email}}',
            ],
            'Advogado 2' => [
                '{{advogado_2_nome}}', '{{advogado_2_oab}}', '{{advogado_2_documento}}',
                '{{advogado_2_cidade}}', '{{advogado_2_uf}}',
            ],
            'Advogado 3' => [
                '{{advogado_3_nome}}', '{{advogado_3_oab}}', '{{advogado_3_documento}}',
            ],
            'Filho Menor' => [
                '{{filho_nome}}', '{{filho_cpf}}', '{{filho_data_nascimento}}',
            ],
            'Incapaz' => [
                '{{incapaz_nome}}', '{{incapaz_cpf}}', '{{incapaz_data_nascimento}}',
            ],
            'A Rogo' => [
                '{{a_rogo_nome}}', '{{a_rogo_identidade}}', '{{a_rogo_cpf}}',
            ],
        ];
    }
}
