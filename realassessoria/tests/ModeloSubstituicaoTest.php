<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/ModeloSubstituicao.php';

class ModeloSubstituicaoTest extends TestCase
{
    // --- formatarCpf ---

    public function testFormatarCpfValido(): void
    {
        $this->assertSame('764.668.332-91', ModeloSubstituicao::formatarCpf('76466833291'));
    }

    public function testFormatarCpfComPontuacaoJaExistente(): void
    {
        $this->assertSame('764.668.332-91', ModeloSubstituicao::formatarCpf('764.668.332-91'));
    }

    public function testFormatarCpfVazio(): void
    {
        $this->assertSame('', ModeloSubstituicao::formatarCpf(''));
    }

    public function testFormatarCpfNull(): void
    {
        $this->assertSame('', ModeloSubstituicao::formatarCpf(null));
    }

    public function testFormatarCpfCurtoRetornaOriginal(): void
    {
        $this->assertSame('123', ModeloSubstituicao::formatarCpf('123'));
    }

    // --- formatarData ---

    public function testFormatarDataValida(): void
    {
        $this->assertSame('15/03/1990', ModeloSubstituicao::formatarData('1990-03-15'));
    }

    public function testFormatarDataZero(): void
    {
        $this->assertSame('', ModeloSubstituicao::formatarData('0000-00-00'));
    }

    public function testFormatarDataVazia(): void
    {
        $this->assertSame('', ModeloSubstituicao::formatarData(''));
    }

    public function testFormatarDataNull(): void
    {
        $this->assertSame('', ModeloSubstituicao::formatarData(null));
    }

    // --- dataHojeExtenso ---

    public function testDataHojeExtensoFormato(): void
    {
        $resultado = ModeloSubstituicao::dataHojeExtenso();
        $this->assertMatchesRegularExpression(
            '/^\d{1,2} de [a-záéíóúãõç]+ de \d{4}$/u',
            $resultado
        );
    }

    public function testDataHojeExtensoContemAno(): void
    {
        $this->assertStringContainsString(date('Y'), ModeloSubstituicao::dataHojeExtenso());
    }

    // --- categorias / validarCategoria ---

    public function testCategoriasRetornaArray(): void
    {
        $cats = ModeloSubstituicao::categorias();
        $this->assertIsArray($cats);
        $this->assertNotEmpty($cats);
    }

    public function testValidarCategoriaValida(): void
    {
        foreach (ModeloSubstituicao::categorias() as $cat) {
            $this->assertTrue(ModeloSubstituicao::validarCategoria($cat));
        }
    }

    public function testValidarCategoriaInvalida(): void
    {
        $this->assertFalse(ModeloSubstituicao::validarCategoria('Inexistente'));
    }

    public function testValidarCategoriaVazia(): void
    {
        $this->assertFalse(ModeloSubstituicao::validarCategoria(''));
    }

    // --- validarNomeModelo ---

    public function testValidarNomeModeloValido(): void
    {
        $this->assertTrue(ModeloSubstituicao::validarNomeModelo('Procuração Administrativa'));
    }

    public function testValidarNomeModeloVazio(): void
    {
        $this->assertFalse(ModeloSubstituicao::validarNomeModelo(''));
    }

    public function testValidarNomeModeloSoEspacos(): void
    {
        $this->assertFalse(ModeloSubstituicao::validarNomeModelo('   '));
    }

    public function testValidarNomeModeloMuitoLongo(): void
    {
        $this->assertFalse(ModeloSubstituicao::validarNomeModelo(str_repeat('a', 151)));
    }

    public function testValidarNomeModeloLimite150(): void
    {
        $this->assertTrue(ModeloSubstituicao::validarNomeModelo(str_repeat('a', 150)));
    }

    // --- extrairMarcadores ---

    public function testExtrairMarcadoresSimples(): void
    {
        $marcadores = ModeloSubstituicao::extrairMarcadores('Olá {{cliente_nome}}, CPF: {{cliente_cpf}}');
        $this->assertContains('{{cliente_nome}}', $marcadores);
        $this->assertContains('{{cliente_cpf}}', $marcadores);
        $this->assertCount(2, $marcadores);
    }

    public function testExtrairMarcadoresSemMarcadores(): void
    {
        $this->assertEmpty(ModeloSubstituicao::extrairMarcadores('Texto sem marcadores.'));
    }

    public function testExtrairMarcadoresDuplicadosRetornaUnico(): void
    {
        $marcadores = ModeloSubstituicao::extrairMarcadores('{{cliente_nome}} e {{cliente_nome}} novamente');
        $this->assertCount(1, $marcadores);
    }

    // --- marcadoresInvalidos ---

    public function testMarcadoresInvalidosRetornaInexistentes(): void
    {
        $mapa = ['{{cliente_nome}}' => 'João'];
        $invalidos = ModeloSubstituicao::marcadoresInvalidos('{{cliente_nome}} {{campo_inexistente}}', $mapa);
        $this->assertContains('{{campo_inexistente}}', $invalidos);
        $this->assertNotContains('{{cliente_nome}}', $invalidos);
    }

    public function testMarcadoresInvalidosVazioQuandoTodosValidos(): void
    {
        $mapa = ['{{cliente_nome}}' => 'João', '{{cliente_cpf}}' => '000.000.000-00'];
        $invalidos = ModeloSubstituicao::marcadoresInvalidos('{{cliente_nome}} - {{cliente_cpf}}', $mapa);
        $this->assertEmpty($invalidos);
    }

    // --- substituir ---

    public function testSubstituirSimples(): void
    {
        $resultado = ModeloSubstituicao::substituir(
            'Olá {{cliente_nome}}',
            ['{{cliente_nome}}' => 'Maria']
        );
        $this->assertSame('Olá Maria', $resultado);
    }

    public function testSubstituirMarcadorDesconhecidoFicaIntacto(): void
    {
        $resultado = ModeloSubstituicao::substituir(
            '{{cliente_nome}} {{desconhecido}}',
            ['{{cliente_nome}}' => 'João']
        );
        $this->assertStringContainsString('{{desconhecido}}', $resultado);
    }

    public function testSubstituirMultiplosMarcadores(): void
    {
        $mapa = ['{{a}}' => 'X', '{{b}}' => 'Y', '{{c}}' => 'Z'];
        $resultado = ModeloSubstituicao::substituir('{{a}}-{{b}}-{{c}}', $mapa);
        $this->assertSame('X-Y-Z', $resultado);
    }

    // --- construirMapa ---

    public function testConstruirMapaContemChavesDeCliente(): void
    {
        $cliente = ['nome' => 'João Silva', 'cpf' => '12345678901'];
        $mapa = ModeloSubstituicao::construirMapa($cliente, []);
        $this->assertArrayHasKey('{{cliente_nome}}', $mapa);
        $this->assertArrayHasKey('{{cliente_cpf}}', $mapa);
        $this->assertSame('João Silva', $mapa['{{cliente_nome}}']);
        $this->assertSame('123.456.789-01', $mapa['{{cliente_cpf}}']);
    }

    public function testConstruirMapaEscapeXSS(): void
    {
        $cliente = ['nome' => '<script>alert(1)</script>'];
        $mapa = ModeloSubstituicao::construirMapa($cliente, []);
        $this->assertStringNotContainsString('<script>', $mapa['{{cliente_nome}}']);
        $this->assertStringContainsString('&lt;script&gt;', $mapa['{{cliente_nome}}']);
    }

    public function testConstruirMapaAdvogadoAliasAponta1(): void
    {
        $advogados = [['nome' => 'Dr. Pedro', 'oab' => 'OAB/AM 123', 'documento' => '12345678901',
                       'endereco' => 'Rua A', 'cidade' => 'Manaus', 'uf' => 'AM',
                       'fone' => '92999999999', 'email' => 'pedro@adv.com']];
        $mapa = ModeloSubstituicao::construirMapa([], [], '', $advogados);
        $this->assertSame($mapa['{{advogado_1_nome}}'], $mapa['{{advogado_nome}}']);
        $this->assertSame($mapa['{{advogado_1_oab}}'],  $mapa['{{advogado_oab}}']);
    }

    public function testConstruirMapaAdvogadoVazioRetornaStringVazia(): void
    {
        $mapa = ModeloSubstituicao::construirMapa([], [], '', []);
        $this->assertSame('', $mapa['{{advogado_1_nome}}']);
    }

    public function testConstruirMapaFilhoMenor(): void
    {
        $filho = ['nome' => 'Lucas', 'cpf' => '98765432100', 'data_nascimento' => '2010-06-20'];
        $mapa = ModeloSubstituicao::construirMapa([], [], '', [], $filho);
        $this->assertSame('Lucas', $mapa['{{filho_nome}}']);
        $this->assertSame('987.654.321-00', $mapa['{{filho_cpf}}']);
        $this->assertSame('20/06/2010', $mapa['{{filho_data_nascimento}}']);
    }

    public function testConstruirMapaDataHoje(): void
    {
        $mapa = ModeloSubstituicao::construirMapa([], []);
        $this->assertSame(date('d/m/Y'), $mapa['{{data_hoje}}']);
    }

    public function testConstruirMapaUsuarioNome(): void
    {
        $mapa = ModeloSubstituicao::construirMapa([], [], 'Admin Teste');
        $this->assertSame('Admin Teste', $mapa['{{usuario_nome}}']);
    }

    public function testConstruirMapaEmpresaDados(): void
    {
        $empresa = ['empresa_nome' => 'Real Assessoria', 'empresa_cnpj' => '00.000.000/0001-00'];
        $mapa = ModeloSubstituicao::construirMapa([], $empresa);
        $this->assertSame('Real Assessoria', $mapa['{{empresa_nome}}']);
        $this->assertSame('00.000.000/0001-00', $mapa['{{empresa_cnpj}}']);
    }

    public function testConstruirMapaARogo(): void
    {
        $aRogo = ['nome' => 'Ana', 'identidade' => 'RG123456', 'cpf' => '11122233344'];
        $mapa = ModeloSubstituicao::construirMapa([], [], '', [], [], [], $aRogo);
        $this->assertSame('Ana', $mapa['{{a_rogo_nome}}']);
        $this->assertSame('RG123456', $mapa['{{a_rogo_identidade}}']);
        $this->assertSame('111.222.333-44', $mapa['{{a_rogo_cpf}}']);
    }

    // --- fluxo completo integrado ---

    public function testFluxoCompletoSubstituicao(): void
    {
        $cliente = [
            'nome'           => 'Maria Oliveira',
            'cpf'            => '98765432100',
            'rg'             => '1234567',
            'data_nascimento' => '1975-08-10',
            'estado_civil'   => 'solteira',
            'profissao'      => 'professora',
            'telefone'       => '92999999999',
            'email'          => 'maria@email.com',
            'endereco'       => 'Av. Teste, 100',
            'cep'            => '69000-000',
            'cidade'         => 'Manaus',
            'uf'             => 'AM',
            'nacionalidade'  => 'brasileira',
            'beneficio'      => 'Aposentadoria por Invalidez',
            'numero_processo' => '1234567-89.2024',
            'situacao'       => 'Em análise',
        ];
        $empresa = ['empresa_nome' => 'Real Assessoria'];
        $mapa = ModeloSubstituicao::construirMapa($cliente, $empresa, 'Admin');
        $template = 'Cliente: {{cliente_nome}}, CPF: {{cliente_cpf}}, Empresa: {{empresa_nome}}';
        $resultado = ModeloSubstituicao::substituir($template, $mapa);

        $this->assertStringContainsString('Maria Oliveira', $resultado);
        $this->assertStringContainsString('987.654.321-00', $resultado);
        $this->assertStringContainsString('Real Assessoria', $resultado);
        $this->assertStringNotContainsString('{{cliente_nome}}', $resultado);
        $this->assertStringNotContainsString('{{cliente_cpf}}', $resultado);
        $this->assertStringNotContainsString('{{empresa_nome}}', $resultado);
    }
}
