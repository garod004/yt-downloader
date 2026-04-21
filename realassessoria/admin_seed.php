<?php
require_once __DIR__ . '/conexao.php';

$log = [];

$conn->query("DELETE FROM modelos_documentos WHERE criado_por = 'seed'");
$log[] = "Modelos antigos removidos.";

// ── Advogados ────────────────────────────────────────────────────────────────
$advogados = [
    [
        'nome'      => 'EDSON SILVA SANTIAGO',
        'documento' => '22.162.240/0001-25',
        'oab'       => 'OAB/RR 619',
        'endereco'  => 'Rua Professor Agnelo Bittencourt, nº 335, Centro, CEP 69301-430',
        'cidade'    => 'Boa Vista',
        'uf'        => 'RR',
        'fone'      => '(95) 98118-1380',
        'email'     => 'edsonsilvaadvocacia@hotmail.com',
    ],
    [
        'nome'      => 'OSTIVALDO MENEZES DO NASCIMENTO JÚNIOR',
        'documento' => '22.162.240/0001-25',
        'oab'       => 'OAB/RR 1280',
        'endereco'  => 'Rua Professor Agnelo Bittencourt, nº 335, Centro, CEP 69301-430',
        'cidade'    => 'Boa Vista',
        'uf'        => 'RR',
        'fone'      => '(95) 98118-1380',
        'email'     => 'edsonsilvaadvocacia@hotmail.com',
    ],
];

$stmtChk = $conn->prepare("SELECT id FROM advogados WHERE nome = ? LIMIT 1");
$stmtIns = $conn->prepare("INSERT INTO advogados (nome,documento,oab,endereco,cidade,uf,fone,email) VALUES (?,?,?,?,?,?,?,?)");
foreach ($advogados as $adv) {
    $stmtChk->bind_param('s', $adv['nome']);
    $stmtChk->execute();
    $stmtChk->store_result();
    if ($stmtChk->num_rows > 0) { $log[] = "Advogado já existe: {$adv['nome']}"; continue; }
    $stmtIns->bind_param('ssssssss', $adv['nome'], $adv['documento'], $adv['oab'], $adv['endereco'], $adv['cidade'], $adv['uf'], $adv['fone'], $adv['email']);
    $stmtIns->execute();
    $log[] = "Advogado inserido: {$adv['nome']}";
}

// ── Modelos ──────────────────────────────────────────────────────────────────
$modelos = [];

// =============================================================================
// 1. PROCURAÇÃO ADMINISTRATIVA INSS — PADRÃO  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Administrativa INSS (Padrão)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração para representação perante o INSS — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Administrativa — INSS · Real Assessoria</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_estado_civil}}, {{cliente_profissao}}, portador(a) do documento de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone: {{cliente_telefone}}, e-mail: {{cliente_email}}.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{empresa_proprietarios}}, com domicílio em {{empresa_endereco}}, {{empresa_cidade}}/AM, e-mail: {{empresa_email}}, fone: {{empresa_fone}}, com escritório profissional no endereço acima citado.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>A quem confere os poderes para representá-lo perante o INSS – INSTITUTO NACIONAL DE SEGURIDADE SOCIAL, podendo receber benefícios, interpor recursos às instâncias superiores, receber mensalidades e quantias devidas, assinar recibos, fazer recadastramentos, bem como representá-lo junto à instituição bancária que recolhe o referido benefício, podendo, para tanto, assinar documentos, atualizar dados cadastrais, alegar e prestar declarações e informações, solicitar e retirar senha e cartão magnético, enfim, praticar e recorrer a todos os meios legais necessários ao fiel cumprimento do presente mandato.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table" style="margin-top:24pt;">
<tr><td style="width:60%;"><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td><td></td></tr>
</table>
</div>
HTML,
];

// =============================================================================
// 2. PROCURAÇÃO ADMINISTRATIVA INSS — A ROGO  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Administrativa INSS (A Rogo)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração INSS assinada a rogo — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Administrativa — INSS · A Rogo · Real Assessoria</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) do documento de identidade nº {{cliente_rg}}, portador(a) do CPF nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone para contato {{cliente_telefone}}, e-mail: {{cliente_email}}.</p>
<p style="margin-top:6pt;">Assina a rogo do(a) outorgante: <strong>{{a_rogo_nome}}</strong>, portador(a) da cédula de identidade nº {{a_rogo_identidade}}, inscrito(a) no CPF sob o nº {{a_rogo_cpf}}, por ser o(a) outorgante analfabeto(a) ou por outra razão que o(a) impossibilite de assinar.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ: {{empresa_cnpj}}, {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, telefone: {{empresa_fone}}, e-mail: {{empresa_email}}.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>A quem confere os poderes para representá-lo perante o INSS – Instituto Nacional do Seguro Social, podendo receber benefícios, interpor recursos às instâncias superiores, receber mensalidades e quantias devidas, assinar recibos, fazer recadastramentos, bem como representá-lo junto à instituição bancária que recolhe o referido benefício, podendo, para tanto, assinar documentos, atualizar dados cadastrais, alegar e prestar declarações e informações, solicitar e retirar senha e cartão magnético, enfim, praticar e recorrer a todos os meios legais necessários ao fiel cumprimento do presente mandato.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}<br/>(Outorgante — assina a rogo)</td>
  <td><span class="sig-line"></span>{{a_rogo_nome}}<br/>CPF: {{a_rogo_cpf}}<br/>(Assina a rogo)</td>
</tr>
<tr>
  <td style="padding-top:22pt;"><span class="sig-line"></span>Testemunha 1</td>
  <td style="padding-top:22pt;"><span class="sig-line"></span>Testemunha 2</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 3. PROCURAÇÃO ADMINISTRATIVA INSS — INCAPAZ  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Administrativa INSS (Incapaz)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração INSS por representante legal de incapaz — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Administrativa — INSS · Representante de Incapaz · Real Assessoria</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) do documento de identidade nº {{cliente_rg}}, portador(a) do CPF nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone para contato {{cliente_telefone}}, e-mail: {{cliente_email}}, neste ato, na qualidade de representante legal do(a) incapaz <strong>{{incapaz_nome}}</strong>, inscrito(a) no CPF sob o nº {{incapaz_cpf}}, nascido(a) em {{incapaz_data_nascimento}}.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ: {{empresa_cnpj}}, {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, telefone: {{empresa_fone}}, e-mail: {{empresa_email}}.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>A quem confere os poderes para representá-lo perante o INSS – Instituto Nacional do Seguro Social, podendo receber benefícios, interpor recursos às instâncias superiores, receber mensalidades e quantias devidas, assinar recibos, fazer recadastramentos, bem como representá-lo junto à instituição bancária que recolhe o referido benefício, podendo, para tanto, assinar documentos, atualizar dados cadastrais, alegar e prestar declarações e informações, solicitar e retirar senha e cartão magnético, enfim, praticar e recorrer a todos os meios legais necessários ao fiel cumprimento do presente mandato.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table" style="margin-top:24pt;">
<tr><td style="width:60%;"><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td><td></td></tr>
</table>
</div>
HTML,
];

// =============================================================================
// 4. PROCURAÇÃO ADMINISTRATIVA INSS — FILHO MENOR  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Administrativa INSS (Filho Menor)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração INSS por responsável legal de filho menor — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Administrativa — INSS · Responsável por Filho Menor · Real Assessoria</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_estado_civil}}, {{cliente_profissao}}, portador(a) do documento de identidade nº {{cliente_rg}}, portador(a) do CPF nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone para contato {{cliente_telefone}}, e-mail: {{cliente_email}}, neste ato na qualidade de representante legal do(a) filho(a) menor <strong>{{filho_nome}}</strong>, inscrito(a) no CPF sob o nº {{filho_cpf}}, nascido(a) em {{filho_data_nascimento}}.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ: {{empresa_cnpj}}, {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, telefone: {{empresa_fone}}, e-mail: {{empresa_email}}.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>A quem confere os poderes para representá-lo perante o INSS – Instituto Nacional do Seguro Social, podendo receber benefícios, interpor recursos às instâncias superiores, receber mensalidades e quantias devidas, assinar recibos, fazer recadastramentos, bem como representá-lo junto à instituição bancária que recolhe o referido benefício, podendo, para tanto, assinar documentos, atualizar dados cadastrais, alegar e prestar declarações e informações, solicitar e retirar senha e cartão magnético, enfim, praticar e recorrer a todos os meios legais necessários ao fiel cumprimento do presente mandato.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table" style="margin-top:24pt;">
<tr><td style="width:60%;"><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td><td></td></tr>
</table>
</div>
HTML,
];

// =============================================================================
// 5. PROCURAÇÃO JUDICIAL — PADRÃO  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Judicial (Padrão)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração Ad Judicia Et Extra — Edson Santiago Advogados',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Ad Judicia Et Extra · Edson Santiago Advogados</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_estado_civil}}, {{cliente_profissao}}, portador(a) do documento de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{advogado_1_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}}, e {{advogado_2_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, ambos com endereço profissional na {{advogado_1_endereco}}, {{advogado_1_cidade}}/{{advogado_1_uf}}, tel.: {{advogado_1_fone}}, e-mail: {{advogado_1_email}}, onde deverão receber intimações.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>Por meio do presente instrumento particular de mandato, o(a) outorgante nomeia e constitui seus bastante procuradores os advogados acima qualificados, conferindo-lhes poderes específicos para representá-lo(a) em juízo ou fora dele, ativa e passivamente, com a cláusula <em>Ad Judicia e Et Extra</em>, em qualquer juízo, instância ou tribunal, inclusive Juizados Especiais, para praticar todos os atos necessários à defesa de seus interesses, podendo, para tanto, propor, contestar, acompanhar, transigir, acordar, discordar, firmar termos de compromisso, substabelecer, e praticar todos os demais atos necessários ao fiel e cabal cumprimento deste mandato.</p>
</div>

<div class="doc-sig">
<p>{{advogado_1_cidade}}/{{advogado_1_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table" style="margin-top:24pt;">
<tr><td style="width:60%;"><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td><td></td></tr>
</table>
</div>
HTML,
];

// =============================================================================
// 6. PROCURAÇÃO JUDICIAL — INCAPAZ  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Judicial (Incapaz)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração Ad Judicia por representante legal de incapaz — Edson Santiago',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Ad Judicia Et Extra · Representante de Incapaz · Edson Santiago Advogados</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_estado_civil}}, {{cliente_profissao}}, portador(a) do documento de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, neste ato, na qualidade de representante legal do(a) incapaz <strong>{{incapaz_nome}}</strong>, inscrito(a) no CPF sob o nº {{incapaz_cpf}}, nascido(a) em {{incapaz_data_nascimento}}.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{advogado_1_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}}, e {{advogado_2_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, ambos com endereço profissional na {{advogado_1_endereco}}, {{advogado_1_cidade}}/{{advogado_1_uf}}, tel.: {{advogado_1_fone}}, e-mail: {{advogado_1_email}}, onde deverão receber intimações.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>Por meio do presente instrumento particular de mandato, o(a) outorgante nomeia e constitui seus bastante procuradores os advogados acima qualificados, conferindo-lhes poderes específicos para representar o(a) incapaz em juízo ou fora dele, ativa e passivamente, com a cláusula <em>Ad Judicia e Et Extra</em>, em qualquer juízo, instância ou tribunal, inclusive Juizados Especiais, para praticar todos os atos necessários à defesa de seus interesses, podendo, para tanto, propor, contestar, acompanhar, transigir, acordar, discordar, firmar termos de compromisso, substabelecer, e praticar todos os demais atos necessários ao fiel e cabal cumprimento deste mandato.</p>
</div>

<div class="doc-sig">
<p>{{advogado_1_cidade}}/{{advogado_1_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table" style="margin-top:24pt;">
<tr><td style="width:60%;"><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td><td></td></tr>
</table>
</div>
HTML,
];

// =============================================================================
// 7. PROCURAÇÃO JUDICIAL — FILHO MENOR  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Judicial (Filho Menor)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração Ad Judicia por responsável legal de filho menor — Edson Santiago',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Ad Judicia Et Extra · Responsável por Filho Menor · Edson Santiago Advogados</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_estado_civil}}, {{cliente_profissao}}, portador(a) do documento de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, neste ato na qualidade de representante legal do(a) filho(a) menor <strong>{{filho_nome}}</strong>, inscrito(a) no CPF sob o nº {{filho_cpf}}, nascido(a) em {{filho_data_nascimento}}.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{advogado_1_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}}, e {{advogado_2_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, ambos com endereço profissional na {{advogado_1_endereco}}, {{advogado_1_cidade}}/{{advogado_1_uf}}, tel.: {{advogado_1_fone}}, e-mail: {{advogado_1_email}}, onde deverão receber intimações.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>Por meio do presente instrumento particular de mandato, o(a) outorgante nomeia e constitui seus bastante procuradores os advogados acima qualificados, conferindo-lhes poderes específicos para representar o(a) filho(a) menor em juízo ou fora dele, ativa e passivamente, com a cláusula <em>Ad Judicia e Et Extra</em>, em qualquer juízo, instância ou tribunal, inclusive Juizados Especiais, para praticar todos os atos necessários à defesa de seus interesses, podendo, para tanto, propor, contestar, acompanhar, transigir, acordar, discordar, firmar termos de compromisso, substabelecer, e praticar todos os demais atos necessários ao fiel e cabal cumprimento deste mandato.</p>
</div>

<div class="doc-sig">
<p>{{advogado_1_cidade}}/{{advogado_1_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table" style="margin-top:24pt;">
<tr><td style="width:60%;"><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td><td></td></tr>
</table>
</div>
HTML,
];

// =============================================================================
// 8. CONTRATO PRESTAÇÃO DE SERVIÇO 30%  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato Prestação de Serviço 30% (Real Assessoria)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato de honorários 30% sobre o benefício — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">CONTRATO</div>
<div class="doc-bar-sub">Prestação de Serviço · Real Assessoria</div>

<div class="doc-sec">CONTRATANTE</div>
<div class="doc-body">
<table class="lv">
<tr><td class="lb">Nome:</td><td>{{cliente_nome}}</td></tr>
<tr><td class="lb">Nacionalidade:</td><td>{{cliente_nacionalidade}}</td></tr>
<tr><td class="lb">Estado civil:</td><td>{{cliente_estado_civil}}</td></tr>
<tr><td class="lb">Profissão:</td><td>{{cliente_profissao}}</td></tr>
<tr><td class="lb">CPF:</td><td>{{cliente_cpf}}</td></tr>
<tr><td class="lb">RG:</td><td>{{cliente_rg}}</td></tr>
<tr><td class="lb">Telefone:</td><td>{{cliente_telefone}}</td></tr>
<tr><td class="lb">Endereço:</td><td>{{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}</td></tr>
<tr><td class="lb">E-mail:</td><td>{{cliente_email}}</td></tr>
</table>
</div>

<div class="doc-sec">CONTRATADO</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ: {{empresa_cnpj}}, {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, {{empresa_fone}}, e-mail: {{empresa_email}}.</p>
</div>

<div class="doc-sec">CLÁUSULAS E CONDIÇÕES</div>
<div class="doc-body">
<p>As partes acima qualificadas celebram, de maneira justa e acordada, o presente CONTRATO DE HONORÁRIOS POR SERVIÇOS PRESTADOS, ficando desde já aceito que o referido instrumento será regido pelas condições previstas em lei, devidamente especificadas pelas cláusulas e condições a seguir descritas.</p>

<p><strong>CLÁUSULA 1</strong> — O presente instrumento tem como objetivo a prestação de serviços de assessoria a serem realizados pelo Contratado para concessão/restabelecimento de benefício previdenciário em face do INSS, na representação e defesa dos interesses do(a) Contratante, sendo realizado na via administrativa ou judicial, em qualquer juízo e/ou instância, apresentar e opor ações, bem como interpor os recursos necessários e competentes para garantir a proteção e o exercício dos seus direitos.</p>

<p><strong>CLÁUSULA 2</strong> — O Contratante assume a obrigatoriedade de efetuar o pagamento pelos serviços prestados ao Contratado no valor de <strong>30% (trinta por cento)</strong> do benefício no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, o Contratante pagará, a título de juros, um percentual de 3% sobre cada parcela.</p>

<p><strong>CLÁUSULA 3</strong> — Deixando o Contratante de, imotivadamente, ter o patrocínio destes causídicos, ora Contratado, não se desobriga ao pagamento dos honorários ajustados integralmente.</p>

<p><strong>PARÁGRAFO ÚNICO</strong> — Em caso de desistência ou qualquer ato de desídia do Contratante, como deixar de comparecer em qualquer ato do processo que gere extinção ou improcedência da ação ou de alguns dos pedidos da demanda, será aplicada multa de R$ 2.000,00 (dois mil reais).</p>

<p><strong>CLÁUSULA 4</strong> — Caso haja morte ou incapacidade civil em ocorrência do contratado, a contratada constituída receberá os honorários na proporção do trabalho realizado.</p>

<p><strong>CLÁUSULA 5</strong> — As partes elegem o foro da Comarca de {{cliente_cidade}}/{{cliente_uf}}.</p>

<p><strong>CLÁUSULA 6</strong> — Por estarem assim justos e contratados, firmam o presente contrato em duas vias de igual teor e forma, para que o mesmo produza seus jurídicos e legais efeitos, juntamente com as 02 (duas) testemunhas abaixo assinadas.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>Assinatura Contratante<br/>{{cliente_nome}}</td>
  <td><span class="sig-line"></span>Assinatura Contratado</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 9. CONTRATO PRESTAÇÃO DE SERVIÇO 40%  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato Prestação de Serviço 40% (Real Assessoria)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato de honorários 40% sobre o benefício — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">CONTRATO</div>
<div class="doc-bar-sub">Prestação de Serviço · Real Assessoria</div>

<div class="doc-sec">CONTRATANTE</div>
<div class="doc-body">
<table class="lv">
<tr><td class="lb">Nome:</td><td>{{cliente_nome}}</td></tr>
<tr><td class="lb">Nacionalidade:</td><td>{{cliente_nacionalidade}}</td></tr>
<tr><td class="lb">Estado civil:</td><td>{{cliente_estado_civil}}</td></tr>
<tr><td class="lb">Profissão:</td><td>{{cliente_profissao}}</td></tr>
<tr><td class="lb">CPF:</td><td>{{cliente_cpf}}</td></tr>
<tr><td class="lb">RG:</td><td>{{cliente_rg}}</td></tr>
<tr><td class="lb">Telefone:</td><td>{{cliente_telefone}}</td></tr>
<tr><td class="lb">Endereço:</td><td>{{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}</td></tr>
<tr><td class="lb">E-mail:</td><td>{{cliente_email}}</td></tr>
</table>
</div>

<div class="doc-sec">CONTRATADO</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ: {{empresa_cnpj}}, {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, {{empresa_fone}}, e-mail: {{empresa_email}}.</p>
</div>

<div class="doc-sec">CLÁUSULAS E CONDIÇÕES</div>
<div class="doc-body">
<p>As partes acima qualificadas celebram, de maneira justa e acordada, o presente CONTRATO DE HONORÁRIOS POR SERVIÇOS PRESTADOS, ficando desde já aceito que o referido instrumento será regido pelas condições previstas em Lei, devidamente especificadas pelas cláusulas e condições a seguir descritas.</p>

<p><strong>CLÁUSULA 1ª</strong> — O presente instrumento tem como objetivo a prestação de serviços de assessoria a serem realizados pelo Contratado, na representação e defesa dos interesses do(a) Contratante, sendo realizado na via administrativa ou judicial, em qualquer juízo e/ou instância, apresentar e opor ações, bem como de interpor os recursos necessários e competentes para garantir a proteção e o exercício dos seus direitos.</p>

<p><strong>CLÁUSULA 2ª</strong> — O Contratante assume a obrigatoriedade de efetuar o pagamento pelos serviços prestados ao Contratado no valor de <strong>40% (Quarenta por cento)</strong> do benefício no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<p><strong>CLÁUSULA 3ª</strong> — Deixando o Contratante de imotivadamente ter o patrocínio destes causídicos, ora Contratado, não a desobriga ao pagamento dos honorários ajustados integralmente.</p>

<p><strong>PARÁGRAFO ÚNICO</strong> — Em caso de desistência ou qualquer ato de desídia do Contratante como deixar de comparecer em qualquer ato do processo que gera a extinção ou a improcedência da ação ou de alguns dos pedidos da demanda, será aplicada uma multa de R$ 2.000,00 (dois mil reais).</p>

<p><strong>CLÁUSULA 4ª</strong> — Caso haja morte ou incapacidade civil em ocorrência do contratado, a contratada constituída receberá os honorários na proporção do trabalho realizado.</p>

<p><strong>CLÁUSULA 5ª</strong> — As partes elegem o foro da Comarca de {{cliente_cidade}}/{{cliente_uf}}.</p>

<p><strong>CLÁUSULA 6ª</strong> — Por estarem assim justos e contratados, firmam o presente CONTRATO DE HONORÁRIOS POR SERVIÇOS PRESTADOS em duas vias de igual teor e forma, para que o mesmo produza seus jurídicos e legais efeitos, juntamente com as 02 (duas) testemunhas abaixo assinadas.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>Assinatura Contratante</td>
  <td><span class="sig-line"></span>Assinatura Contratado</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 10. CONTRATO PRESTAÇÃO DE SERVIÇO — A ROGO  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato Prestação de Serviço — A Rogo (Real Assessoria)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato 30% retroativo + 15x R$500 assinado a rogo — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">CONTRATO</div>
<div class="doc-bar-sub">Prestação de Serviço · A Rogo · Real Assessoria</div>

<div class="doc-sec">CONTRATANTE</div>
<div class="doc-body">
<table class="lv">
<tr><td class="lb">Nome:</td><td>{{cliente_nome}}</td></tr>
<tr><td class="lb">Nacionalidade:</td><td>{{cliente_nacionalidade}}</td></tr>
<tr><td class="lb">Profissão:</td><td>{{cliente_profissao}}</td></tr>
<tr><td class="lb">Estado civil:</td><td>{{cliente_estado_civil}}</td></tr>
<tr><td class="lb">RG:</td><td>{{cliente_rg}}</td></tr>
<tr><td class="lb">CPF:</td><td>{{cliente_cpf}}</td></tr>
<tr><td class="lb">Endereço:</td><td>{{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}</td></tr>
<tr><td class="lb">Telefone:</td><td>{{cliente_telefone}}</td></tr>
<tr><td class="lb">E-mail:</td><td>{{cliente_email}}</td></tr>
</table>
<p style="margin-top:6pt;">Por impossibilidade de assinatura, firma o presente instrumento a rogo por intermédio de <strong>{{a_rogo_nome}}</strong>, portador(a) da identidade nº {{a_rogo_identidade}}, inscrito(a) no CPF sob o nº {{a_rogo_cpf}}.</p>
</div>

<div class="doc-sec">CONTRATADO</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ: {{empresa_cnpj}}, {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, {{empresa_fone}}, e-mail: {{empresa_email}}.</p>
</div>

<div class="doc-sec">CLÁUSULAS E CONDIÇÕES</div>
<div class="doc-body">
<p>As partes acima qualificadas celebram, de maneira justa e acordada, o presente CONTRATO DE HONORÁRIOS POR SERVIÇOS PRESTADOS.</p>

<p><strong>CLÁUSULA 1ª</strong> — O presente instrumento tem como objetivo a prestação de serviços de assessoria a serem realizados pelo Contratado para CONCESSÃO/RESTABELECIMENTO DE BENEFÍCIO PREVIDENCIÁRIO em face do INSS, na representação e defesa dos interesses do(a) Contratante, sendo realizado na via administrativa ou judicial, em qualquer juízo e/ou instância, apresentar e opor ações, bem como de interpor os recursos necessários e competentes para garantir a proteção e o exercício dos seus direitos.</p>

<p><strong>CLÁUSULA 2ª</strong> — O Contratante assume a obrigatoriedade de efetuar o pagamento pelos serviços prestados ao Contratado no valor de <strong>30% (trinta por cento) sobre os valores retroativos (parcelas vencidas) mais 15 parcelas de R$ 500,00 (quinhentos reais)</strong> no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<p><strong>CLÁUSULA 3ª</strong> — Deixando o Contratante de imotivadamente ter o patrocínio destes causídicos, não a desobriga ao pagamento dos honorários ajustados integralmente.</p>

<p><strong>PARÁGRAFO ÚNICO</strong> — Em caso de desistência ou qualquer ato de desídia do Contratante, será aplicada multa de R$ 2.000,00 (dois mil reais).</p>

<p><strong>CLÁUSULA 4ª</strong> — Caso haja morte ou incapacidade civil em ocorrência do contratado, sua advogada constituída como representante legal receberá os honorários na proporção do trabalho realizado.</p>

<p><strong>CLÁUSULA 5ª</strong> — As partes elegem o foro da Comarca de {{cliente_cidade}}/{{cliente_uf}}.</p>

<p>Por estarem assim justos e contratados, firmam o presente CONTRATO em duas vias de igual teor e forma, juntamente com as 02 (duas) testemunhas abaixo assinadas.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>Contratante (assina a rogo)</td>
  <td><span class="sig-line"></span>{{a_rogo_nome}}<br/>Assina a rogo</td>
</tr>
<tr>
  <td style="padding-top:22pt;" colspan="2" style="text-align:center;"><span class="sig-line" style="width:50%;margin:0 auto;display:block;"></span>Assinatura do Contratado</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 11. CONTRATO PRESTAÇÃO DE SERVIÇO — INCAPAZ  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato Prestação de Serviço — Incapaz (Real Assessoria)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato 30% retroativo + 15x R$500 por rep. legal de incapaz — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">CONTRATO</div>
<div class="doc-bar-sub">Prestação de Serviço · Representante de Incapaz · Real Assessoria</div>

<div class="doc-sec">CONTRATANTE</div>
<div class="doc-body">
<table class="lv">
<tr><td class="lb">Nome:</td><td>{{cliente_nome}}</td></tr>
<tr><td class="lb">Nacionalidade:</td><td>{{cliente_nacionalidade}}</td></tr>
<tr><td class="lb">Profissão:</td><td>{{cliente_profissao}}</td></tr>
<tr><td class="lb">Estado civil:</td><td>{{cliente_estado_civil}}</td></tr>
<tr><td class="lb">RG:</td><td>{{cliente_rg}}</td></tr>
<tr><td class="lb">CPF:</td><td>{{cliente_cpf}}</td></tr>
<tr><td class="lb">Endereço:</td><td>{{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}</td></tr>
<tr><td class="lb">Telefone:</td><td>{{cliente_telefone}}</td></tr>
<tr><td class="lb">E-mail:</td><td>{{cliente_email}}</td></tr>
</table>
<p style="margin-top:6pt;">Neste ato, na qualidade de representante legal do(a) incapaz <strong>{{incapaz_nome}}</strong>, inscrito(a) no CPF sob o nº {{incapaz_cpf}}, nascido(a) em {{incapaz_data_nascimento}}.</p>
</div>

<div class="doc-sec">CONTRATADO</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ: {{empresa_cnpj}}, {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, {{empresa_fone}}, e-mail: {{empresa_email}}.</p>
</div>

<div class="doc-sec">CLÁUSULAS E CONDIÇÕES</div>
<div class="doc-body">
<p>As partes acima qualificadas celebram, de maneira justa e acordada, o presente CONTRATO DE HONORÁRIOS POR SERVIÇOS PRESTADOS.</p>

<p><strong>CLÁUSULA 1ª</strong> — O presente instrumento tem como objetivo a prestação de serviços de assessoria a serem realizados pelo Contratado para CONCESSÃO/RESTABELECIMENTO DE BENEFÍCIO PREVIDENCIÁRIO em face do INSS, na representação e defesa dos interesses do(a) Contratante, sendo realizado na via administrativa ou judicial, em qualquer juízo e/ou instância, apresentar e opor ações, bem como de interpor os recursos necessários e competentes para garantir a proteção e o exercício dos seus direitos.</p>

<p><strong>CLÁUSULA 2ª</strong> — O Contratante assume a obrigatoriedade de efetuar o pagamento pelos serviços prestados ao Contratado no valor de <strong>30% (trinta por cento) sobre os valores retroativos (parcelas vencidas) mais 15 parcelas de R$ 500,00 (quinhentos reais)</strong> no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<p><strong>CLÁUSULA 3ª</strong> — Deixando o Contratante de imotivadamente ter o patrocínio destes causídicos, não a desobriga ao pagamento dos honorários ajustados integralmente.</p>

<p><strong>PARÁGRAFO ÚNICO</strong> — Em caso de desistência ou qualquer ato de desídia do Contratante, será aplicada multa de R$ 2.000,00 (dois mil reais).</p>

<p><strong>CLÁUSULA 4ª</strong> — Caso haja morte ou incapacidade civil em ocorrência do contratado, sua advogada constituída receberá os honorários na proporção do trabalho realizado.</p>

<p><strong>CLÁUSULA 5ª</strong> — As partes elegem o foro da Comarca de {{cliente_cidade}}/{{cliente_uf}}.</p>

<p><strong>CLÁUSULA 6ª</strong> — Por estarem assim justos e contratados, firmam o presente CONTRATO em duas vias de igual teor e forma, juntamente com as 02 (duas) testemunhas abaixo assinadas.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>Contratante</td>
  <td><span class="sig-line"></span>Assinatura do Contratado</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 12. CONTRATO PRESTAÇÃO DE SERVIÇO — FILHO MENOR  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato Prestação de Serviço — Filho Menor (Real Assessoria)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato 30% retroativo + 15x R$500 por responsável de filho menor — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">CONTRATO</div>
<div class="doc-bar-sub">Prestação de Serviço · Responsável por Filho Menor · Real Assessoria</div>

<div class="doc-sec">CONTRATANTE</div>
<div class="doc-body">
<table class="lv">
<tr><td class="lb">Nome:</td><td>{{cliente_nome}}</td></tr>
<tr><td class="lb">Nacionalidade:</td><td>{{cliente_nacionalidade}}</td></tr>
<tr><td class="lb">Profissão:</td><td>{{cliente_profissao}}</td></tr>
<tr><td class="lb">Estado civil:</td><td>{{cliente_estado_civil}}</td></tr>
<tr><td class="lb">RG:</td><td>{{cliente_rg}}</td></tr>
<tr><td class="lb">CPF:</td><td>{{cliente_cpf}}</td></tr>
<tr><td class="lb">Endereço:</td><td>{{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}</td></tr>
<tr><td class="lb">Telefone:</td><td>{{cliente_telefone}}</td></tr>
<tr><td class="lb">E-mail:</td><td>{{cliente_email}}</td></tr>
</table>
<p style="margin-top:6pt;">Neste ato, na qualidade de representante legal do(a) filho(a) menor <strong>{{filho_nome}}</strong>, inscrito(a) no CPF sob o nº {{filho_cpf}}, nascido(a) em {{filho_data_nascimento}}.</p>
</div>

<div class="doc-sec">CONTRATADO</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ nº {{empresa_cnpj}}, com endereço na {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, telefone {{empresa_fone}}, e-mail {{empresa_email}}.</p>
</div>

<div class="doc-sec">CLÁUSULAS E CONDIÇÕES</div>
<div class="doc-body">
<p>As partes acima qualificadas celebram o presente CONTRATO DE HONORÁRIOS POR SERVIÇOS PRESTADOS, que será regido pelas cláusulas e condições abaixo.</p>

<p><strong>Cláusula 1ª — Do objeto.</strong> O presente instrumento tem por objeto a prestação de serviços de assessoria para concessão e/ou restabelecimento de benefício previdenciário perante o INSS, inclusive na via administrativa ou judicial, abrangendo os atos e recursos necessários à defesa dos interesses do(a) CONTRATANTE e do(a) menor representado(a).</p>

<p><strong>Cláusula 2ª — Dos honorários.</strong> Pelos serviços prestados, o(a) CONTRATANTE pagará ao CONTRATADO o equivalente a <strong>30% (trinta por cento) sobre os valores retroativos (parcelas vencidas) mais 15 parcelas de R$ 500,00 (quinhentos reais)</strong>, a serem iniciadas no ato da implantação do benefício.</p>

<p><strong>Cláusula 3ª — Do inadimplemento.</strong> Em caso de descumprimento do acordo de pagamento, incidirá juros de 3% (três por cento) sobre cada parcela em atraso.</p>

<p><strong>Cláusula 4ª — Da rescisão ou desistência.</strong> A revogação imotivada do patrocínio ou a desistência injustificada por parte do(a) CONTRATANTE não o(a) desobriga do pagamento integral dos honorários contratados.</p>

<p><strong>Parágrafo único.</strong> Na hipótese de desistência, abandono, omissão relevante ou não comparecimento do(a) CONTRATANTE a atos indispensáveis ao andamento do procedimento, que acarretem extinção ou improcedência do pedido, será devida multa compensatória de R$ 2.000,00 (dois mil reais).</p>

<p><strong>Cláusula 5ª — Da sucessão dos honorários.</strong> Em caso de morte ou incapacidade civil do CONTRATADO, os honorários serão pagos por seus sucessores ou representante legal, na proporção do trabalho efetivamente realizado.</p>

<p><strong>Cláusula 6ª — Do foro.</strong> Fica eleito o foro da Comarca de {{cliente_cidade}}/{{cliente_uf}} para dirimir quaisquer controvérsias oriundas deste contrato.</p>

<p>Por estarem justos e contratados, firmam o presente instrumento em duas vias de igual teor e forma, juntamente com duas testemunhas.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CONTRATANTE</td>
  <td><span class="sig-line"></span>{{empresa_nome}}<br/>CONTRATADO</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 13. CONTRATO DE HONORÁRIOS ADVOCATÍCIOS 30%  [Sistema B — Plain]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato de Honorários Advocatícios 30% (Edson Santiago)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato judicial 30% sobre implantação — Edson Santiago Advogados',
    'conteudo'  => <<<'HTML'
<div class="plain">
<div class="plain-title">CONTRATO DE HONORÁRIOS</div>

<p>Pelo presente instrumento particular de contrato de honorários advocatícios, <strong>EDSON SANTIAGO ADVOGADOS ASSOCIADOS</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 22.162.240/0001-25, com sede na Rua Professor Agnelo Bittencourt, nº 335, bairro Centro, Boa Vista-RR, CEP 69301-430, neste ato representada pelos sócios <strong>{{advogado_1_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}} e <strong>{{advogado_2_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, doravante denominado CONTRATADO; e do outro lado o(a) Sr(a). <strong>{{cliente_nome}}</strong>, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) da carteira de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, doravante denominado(a) CONTRATANTE.</p>

<div class="plain-sec">DO OBJETO DO CONTRATO</div>
<p><strong>Cláusula 1ª.</strong> O presente instrumento particular tem por objeto a prestação de serviços advocatícios ao CONTRATANTE, especificamente no que se refere à propositura de ação judicial/administrativa em face do INSS para requerimento de benefício previdenciário. Este instrumento também abrange a prestação de consultoria jurídica ao CONTRATANTE, sempre que necessário, para o esclarecimento de questões relacionadas ao processo.</p>

<div class="plain-sec">DAS DESPESAS</div>
<p><strong>Cláusula 2ª.</strong> Todas as despesas efetuadas pelo CONTRATADO, mesmo que indiretamente relacionadas com a sua atuação, incluindo-se cópias, digitalizações, envio de correspondências, peças técnicas, pedidos de certidões, emolumentos, viagens, pagamento de taxas e demais gastos de natureza diversa da verba honorária, ficarão a expensas do CONTRATANTE, desde que previamente autorizadas.</p>
<p><strong>Cláusula 3ª.</strong> Todas as eventuais despesas serão acompanhadas de documento comprobatório, devidamente organizado pelo CONTRATADO.</p>

<div class="plain-sec">DOS HONORÁRIOS ADVOCATÍCIOS</div>
<p><strong>Cláusula 4ª.</strong> O CONTRATANTE, a título de contraprestação pelos serviços jurídicos prestados, pagará ao CONTRATADO o valor de <strong>30% (Trinta por cento)</strong> no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<table class="bank">
<tr>
<td><strong>BANCO BRASIL</strong><br/>AGÊNCIA: 2617-4<br/>CONTA CORRENTE: 58681-1<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25 (CHAVE PIX)</td>
<td><strong>BANCO ITAÚ</strong><br/>AGÊNCIA: 1352<br/>CONTA CORRENTE: 17777-6<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25</td>
</tr>
</table>

<p><strong>Cláusula 5ª.</strong> Os honorários aqui previstos serão integralmente devidos pelo CONTRATANTE em caso de rescisão imotivada do presente contrato.</p>
<p><strong>Cláusula 6ª.</strong> Os honorários contratuais convencionados no presente instrumento particular não se confundem com eventuais honorários de sucumbência impostos à parte contrária por sentença judicial.</p>
<p><strong>Cláusula 7ª.</strong> Distribuída a medida judicial, o total dos honorários serão devidos mesmo que haja composição amigável quanto ao pedido, venha o CONTRATANTE a desistir do pedido ou, ainda, se for cassada a procuração sem culpa do CONTRATADO.</p>
<p><strong>Cláusula 8ª.</strong> Na hipótese de desistência antes do ajuizamento da ação, serão devidos 50% (cinquenta por cento) do valor contratado.</p>

<div class="plain-sec">DA VIGÊNCIA E RESCISÃO</div>
<p><strong>Cláusula 9ª.</strong> Este contrato tem vigência até o adimplemento das obrigações ajustadas e pode ser rescindido a qualquer tempo por qualquer das partes, mediante aviso prévio de 30 (trinta) dias, por escrito e com comprovante de entrega.</p>
<p><strong>Cláusula 10ª.</strong> Na hipótese de rescisão antecipada pelo CONTRATANTE, será devido ao CONTRATADO todos os valores pactuados neste contrato, bem como o percentual correspondente à parcela do serviço já executada.</p>

<div class="plain-sec">DA RESPONSABILIDADE</div>
<p><strong>Cláusula 11ª.</strong> É obrigação do CONTRATANTE, sempre que solicitado, entregar, fornecer ou disponibilizar ao CONTRATADO todos os documentos necessários, provas, informações e subsídios, em tempo hábil.</p>
<p><strong>Cláusula 12ª.</strong> Qualquer omissão ou negligência por parte do CONTRATANTE será de sua inteira responsabilidade, caso advenha algum prejuízo a seus interesses.</p>
<p><strong>Cláusula 13ª.</strong> Caso o CONTRATANTE falte com a verdade em suas declarações com o CONTRATADO, o presente instrumento particular será rescindido sem prejuízo dos honorários já convencionados.</p>
<p><strong>Cláusula 14ª.</strong> Fica expressamente ciente o CONTRATANTE que em caso de improcedência da ação a ser proposta, em não sendo beneficiário da justiça gratuita, poderá haver condenação de honorários de sucumbência ao advogado da parte contrária, assim como condenação ao pagamento de custas processuais, ônus esses que serão de inteira responsabilidade do CONTRATANTE e desvinculados do presente instrumento particular e isento de qualquer desconto referente aos honorários contratuais devidos ao CONTRATADO.</p>

<div class="plain-sec">DO FORO</div>
<p><strong>Cláusula 15ª.</strong> Para dirimir quaisquer controvérsias oriundas deste contrato, as partes elegem o foro da Comarca de Boa Vista/RR.</p>

<div class="plain-sec">DA ASSINATURA DIGITAL</div>
<p><strong>Cláusula 16ª.</strong> As partes admitem a possibilidade de utilização de assinatura eletrônica mediante certificado do IC-BRASIL ou E-Notariado, sendo que cada parte arcará com seu respectivo custo.</p>
<p><strong>Cláusula 17ª.</strong> A assinatura eletrônica passa a ser admitida em todos os documentos que envolvam as partes, de maneira que os documentos assim assinados constituem documentos eletrônicos para os fins do art. 10, caput, e parágrafo segundo, da MP 2.200-2/01, c/c o provimento nº 100 do CNJ.</p>

<p>E por estarem assim justos e contratados, assinam o presente instrumento em 02 (duas) vias de igual teor e forma, e na presença de 02 (duas) testemunhas abaixo assinadas.</p>

<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>

<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td>
  <td><span class="sig-line"></span>Edson Santiago Advogados Associados<br/>CNPJ 22.162.240/0001-25<br/>{{advogado_1_nome}} — {{advogado_1_oab}}</td>
</tr>
<tr>
  <td style="padding-top:22pt;" colspan="2"><span class="sig-line" style="width:50%;margin:0 auto;display:block;"></span>{{advogado_2_nome}} — {{advogado_2_oab}}</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 14. CONTRATO DE HONORÁRIOS PADRÃO (Edson Santiago) — 30% retro + 15x R$500  [Sistema B]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato de Honorários Padrão (Edson Santiago)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato judicial 30% retroativo + 15 parcelas R$500 — Edson Santiago Advogados',
    'conteudo'  => <<<'HTML'
<div class="plain">
<div class="plain-title">CONTRATO DE HONORÁRIOS</div>

<p>Pelo presente instrumento particular de contrato de honorários advocatícios, <strong>EDSON SANTIAGO ADVOGADOS ASSOCIADOS</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 22.162.240/0001-25, com sede na Rua Professor Agnelo Bittencourt, nº 335, bairro Centro, Boa Vista-RR, CEP 69301-430, neste ato representada pelos sócios <strong>{{advogado_1_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}} e <strong>{{advogado_2_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, doravante denominado CONTRATADO; e do outro lado o(a) Sr(a). <strong>{{cliente_nome}}</strong>, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) da carteira de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, doravante denominado(a) CONTRATANTE.</p>

<div class="plain-sec">DO OBJETO DO CONTRATO</div>
<p><strong>Cláusula 1ª.</strong> O presente instrumento particular tem por objeto a prestação de serviços advocatícios ao CONTRATANTE, especificamente no que se refere à propositura de ação judicial/administrativa em face do INSS para requerimento de benefício previdenciário. Este instrumento também abrange a prestação de consultoria jurídica ao CONTRATANTE, sempre que necessário, para o esclarecimento de questões relacionadas ao processo.</p>

<div class="plain-sec">DAS DESPESAS</div>
<p><strong>Cláusula 2ª.</strong> Todas as despesas efetuadas pelo CONTRATADO, mesmo que indiretamente relacionadas com a sua atuação, incluindo-se cópias, digitalizações, envio de correspondências, peças técnicas, pedidos de certidões, emolumentos, viagens, pagamento de taxas e demais gastos de natureza diversa da verba honorária, ficarão a expensas do CONTRATANTE, desde que previamente autorizadas.</p>
<p><strong>Cláusula 3ª.</strong> Todas as eventuais despesas serão acompanhadas de documento comprobatório, devidamente organizado pelo CONTRATADO.</p>

<div class="plain-sec">DOS HONORÁRIOS ADVOCATÍCIOS</div>
<p><strong>Cláusula 4ª.</strong> O CONTRATANTE, a título de contraprestação pelos serviços jurídicos prestados, pagará ao CONTRATADO o valor de <strong>30% (Trinta por cento) sobre os valores retroativos (parcelas vencidas) e mais 15 parcelas de R$ 500,00 (Quinhentos Reais)</strong> no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<table class="bank">
<tr>
<td><strong>BANCO BRASIL</strong><br/>AGÊNCIA: 2617-4<br/>CONTA CORRENTE: 58681-1<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25 (CHAVE PIX)</td>
<td><strong>BANCO ITAÚ</strong><br/>AGÊNCIA: 1352<br/>CONTA CORRENTE: 17777-6<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25</td>
</tr>
</table>

<p><strong>Cláusula 5ª.</strong> Os honorários aqui previstos serão integralmente devidos pelo CONTRATANTE em caso de rescisão imotivada do presente contrato.</p>
<p><strong>Cláusula 6ª.</strong> Os honorários contratuais convencionados no presente instrumento particular não se confundem com eventuais honorários de sucumbência impostos à parte contrária por sentença judicial.</p>
<p><strong>Cláusula 7ª.</strong> Distribuída a medida judicial, o total dos honorários serão devidos mesmo que haja composição amigável quanto ao pedido, venha o CONTRATANTE a desistir do pedido ou, ainda, se for cassada a procuração sem culpa do CONTRATADO.</p>
<p><strong>Cláusula 8ª.</strong> Na hipótese de desistência antes do ajuizamento da ação, serão devidos 50% (cinquenta por cento) do valor contratado.</p>

<div class="plain-sec">DA VIGÊNCIA E RESCISÃO</div>
<p><strong>Cláusula 9ª.</strong> Este contrato tem vigência até o adimplemento das obrigações ajustadas e pode ser rescindido a qualquer tempo por qualquer das partes, mediante aviso prévio de 30 (trinta) dias, por escrito e com comprovante de entrega.</p>
<p><strong>Cláusula 10ª.</strong> Na hipótese de rescisão antecipada pelo CONTRATANTE, será devido ao CONTRATADO todos os valores pactuados neste contrato.</p>

<div class="plain-sec">DA RESPONSABILIDADE</div>
<p><strong>Cláusula 11ª.</strong> É obrigação do CONTRATANTE, sempre que solicitado, entregar, fornecer ou disponibilizar ao CONTRATADO todos os documentos necessários, provas, informações e subsídios, em tempo hábil.</p>
<p><strong>Cláusula 12ª.</strong> Qualquer omissão ou negligência por parte do CONTRATANTE será de sua inteira responsabilidade, caso advenha algum prejuízo a seus interesses.</p>
<p><strong>Cláusula 13ª.</strong> Caso o CONTRATANTE falte com a verdade em suas declarações com o CONTRATADO, o presente instrumento particular será rescindido sem prejuízo dos honorários já convencionados.</p>
<p><strong>Cláusula 14ª.</strong> Fica expressamente ciente o CONTRATANTE que em caso de improcedência da ação a ser proposta, em não sendo beneficiário da justiça gratuita, poderá haver condenação de honorários de sucumbência ao advogado da parte contrária, assim como condenação ao pagamento de custas processuais, ônus esses que serão de inteira responsabilidade do CONTRATANTE.</p>

<div class="plain-sec">DO FORO</div>
<p><strong>Cláusula 15ª.</strong> Para dirimir quaisquer controvérsias oriundas deste contrato, as partes elegem o foro da Comarca de Boa Vista/RR.</p>

<div class="plain-sec">DA ASSINATURA DIGITAL</div>
<p><strong>Cláusula 16ª.</strong> As partes admitem a possibilidade de utilização de assinatura eletrônica mediante certificado do IC-BRASIL ou E-Notariado.</p>
<p><strong>Cláusula 17ª.</strong> A assinatura eletrônica passa a ser admitida em todos os documentos que envolvam as partes, nos termos do art. 10 da MP 2.200-2/01, c/c o provimento nº 100 do CNJ.</p>

<p>E por estarem assim justos e contratados, assinam o presente instrumento em 02 (duas) vias de igual teor e forma, e na presença de 02 (duas) testemunhas abaixo assinadas.</p>

<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>

<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td>
  <td><span class="sig-line"></span>Edson Santiago Advogados Associados<br/>CNPJ 22.162.240/0001-25<br/>{{advogado_1_nome}} — {{advogado_1_oab}}</td>
</tr>
<tr>
  <td style="padding-top:22pt;" colspan="2"><span class="sig-line" style="width:50%;margin:0 auto;display:block;"></span>{{advogado_2_nome}} — {{advogado_2_oab}}</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 15. CONTRATO DE HONORÁRIOS — INCAPAZ  [Sistema B — Plain]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato de Honorários — Incapaz (Edson Santiago)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato judicial por rep. legal de incapaz — Edson Santiago Advogados',
    'conteudo'  => <<<'HTML'
<div class="plain">
<div class="plain-title">CONTRATO DE HONORÁRIOS</div>

<p>Pelo presente instrumento particular de contrato de honorários advocatícios, <strong>EDSON SANTIAGO ADVOGADOS ASSOCIADOS</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 22.162.240/0001-25, com sede na Rua Professor Agnelo Bittencourt, nº 335, bairro Centro, Boa Vista-RR, CEP 69301-430, neste ato representada pelos sócios <strong>{{advogado_1_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}} e <strong>{{advogado_2_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, doravante denominado CONTRATADO; e do outro lado o(a) Sr(a). <strong>{{cliente_nome}}</strong>, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) da carteira de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, doravante denominado(a) CONTRATANTE, neste ato, na qualidade de representante legal do(a) incapaz <strong>{{incapaz_nome}}</strong>, inscrito(a) no CPF sob o nº {{incapaz_cpf}}, nascido(a) em {{incapaz_data_nascimento}}.</p>

<div class="plain-sec">DO OBJETO DO CONTRATO</div>
<p><strong>Cláusula 1ª.</strong> O presente instrumento particular tem por objeto a prestação de serviços advocatícios ao CONTRATANTE, especificamente no que se refere à propositura de ação judicial/administrativa em face do INSS para requerimento de benefício previdenciário.</p>

<div class="plain-sec">DAS DESPESAS</div>
<p><strong>Cláusula 2ª.</strong> Todas as despesas efetuadas pelo CONTRATADO ficarão a expensas do CONTRATANTE, desde que previamente autorizadas.</p>
<p><strong>Cláusula 3ª.</strong> Todas as eventuais despesas serão acompanhadas de documento comprobatório.</p>

<div class="plain-sec">DOS HONORÁRIOS ADVOCATÍCIOS</div>
<p><strong>Cláusula 4ª.</strong> O CONTRATANTE pagará ao CONTRATADO o valor de <strong>30% (Trinta por cento) sobre os valores retroativos (parcelas vencidas) e mais 15 parcelas de R$ 500,00 (Quinhentos Reais)</strong> no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<table class="bank">
<tr>
<td><strong>BANCO BRASIL</strong><br/>AGÊNCIA: 2617-4<br/>CONTA CORRENTE: 58681-1<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25 (CHAVE PIX)</td>
<td><strong>BANCO ITAÚ</strong><br/>AGÊNCIA: 1352<br/>CONTA CORRENTE: 17777-6<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25</td>
</tr>
</table>

<p><strong>Cláusula 5ª.</strong> Os honorários serão integralmente devidos em caso de rescisão imotivada.</p>
<p><strong>Cláusula 6ª.</strong> Os honorários contratuais não se confundem com eventuais honorários de sucumbência.</p>
<p><strong>Cláusula 7ª.</strong> Distribuída a medida judicial, o total dos honorários serão devidos mesmo que haja composição amigável.</p>
<p><strong>Cláusula 8ª.</strong> Na hipótese de desistência antes do ajuizamento, serão devidos 50% do valor contratado.</p>

<div class="plain-sec">DA VIGÊNCIA E RESCISÃO</div>
<p><strong>Cláusula 9ª.</strong> Este contrato tem vigência até o adimplemento das obrigações ajustadas.</p>
<p><strong>Cláusula 10ª.</strong> Na rescisão antecipada pelo CONTRATANTE, serão devidos todos os valores pactuados.</p>

<div class="plain-sec">DA RESPONSABILIDADE</div>
<p><strong>Cláusula 11ª.</strong> É obrigação do CONTRATANTE fornecer ao CONTRATADO todos os documentos necessários em tempo hábil.</p>
<p><strong>Cláusula 12ª.</strong> Qualquer omissão ou negligência do CONTRATANTE será de sua inteira responsabilidade.</p>
<p><strong>Cláusula 13ª.</strong> Declarações falsas implicarão rescisão do contrato sem prejuízo dos honorários.</p>
<p><strong>Cláusula 14ª.</strong> O CONTRATANTE está ciente de que eventuais honorários de sucumbência e custas processuais são de sua inteira responsabilidade.</p>

<div class="plain-sec">DO FORO</div>
<p><strong>Cláusula 15ª.</strong> As partes elegem o foro da Comarca de Boa Vista/RR.</p>

<div class="plain-sec">DA ASSINATURA DIGITAL</div>
<p><strong>Cláusula 16ª.</strong> As partes admitem assinatura eletrônica mediante certificado do IC-BRASIL ou E-Notariado.</p>
<p><strong>Cláusula 17ª.</strong> A assinatura eletrônica é admitida nos termos do art. 10 da MP 2.200-2/01.</p>

<p>E por estarem assim justos e contratados, assinam o presente instrumento em 02 (duas) vias.</p>

<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>

<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td>
  <td><span class="sig-line"></span>Edson Santiago Advogados Associados<br/>CNPJ 22.162.240/0001-25<br/>{{advogado_1_nome}} — {{advogado_1_oab}}</td>
</tr>
<tr>
  <td style="padding-top:22pt;" colspan="2"><span class="sig-line" style="width:50%;margin:0 auto;display:block;"></span>{{advogado_2_nome}} — {{advogado_2_oab}}</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 16. CONTRATO DE HONORÁRIOS — FILHO MENOR  [Sistema B — Plain]  ← NOVO
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato de Honorários — Filho Menor (Edson Santiago)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato judicial por responsável de filho menor 30% retroativo + 15x R$500 — Edson Santiago',
    'conteudo'  => <<<'HTML'
<div class="plain">
<div class="plain-title">CONTRATO DE HONORÁRIOS</div>

<p>Pelo presente instrumento particular de contrato de honorários advocatícios, <strong>EDSON SANTIAGO ADVOGADOS ASSOCIADOS</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 22.162.240/0001-25, com sede na Rua Professor Agnelo Bittencourt, nº 335, bairro Centro, Boa Vista-RR, CEP 69301-430, neste ato representada pelos sócios <strong>{{advogado_1_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}} e <strong>{{advogado_2_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, doravante denominado CONTRATADO; e do outro lado o(a) Sr(a). <strong>{{cliente_nome}}</strong>, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) da carteira de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, doravante denominado(a) CONTRATANTE. Neste ato representando o(a) seu(ua) filho(a) o(a) menor: <strong>{{filho_nome}}</strong>, CPF nº {{filho_cpf}}, nascido(a) em {{filho_data_nascimento}}.</p>

<div class="plain-sec">DO OBJETO DO CONTRATO</div>
<p><strong>Cláusula 1ª.</strong> O presente instrumento particular tem por objeto a prestação de serviços advocatícios ao CONTRATANTE, especificamente no que se refere à propositura de ação judicial/administrativa em face do INSS para requerimento de benefício previdenciário. Este instrumento também abrange a prestação de consultoria jurídica ao CONTRATANTE, sempre que necessário, para o esclarecimento de questões relacionadas ao processo.</p>

<div class="plain-sec">DAS DESPESAS</div>
<p><strong>Cláusula 2ª.</strong> Todas as despesas efetuadas pelo CONTRATADO ficarão a expensas do CONTRATANTE, desde que previamente autorizadas.</p>
<p><strong>Cláusula 3ª.</strong> Todas as eventuais despesas serão acompanhadas de documento comprobatório.</p>

<div class="plain-sec">DOS HONORÁRIOS ADVOCATÍCIOS</div>
<p><strong>Cláusula 4ª.</strong> O CONTRATANTE pagará ao CONTRATADO o valor de <strong>30% (Trinta por cento) sobre os valores retroativos (parcelas vencidas) e mais 15 parcelas de R$ 500,00 (Quinhentos Reais)</strong> no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<table class="bank">
<tr>
<td><strong>BANCO BRASIL</strong><br/>AGÊNCIA: 2617-4<br/>CONTA CORRENTE: 58681-1<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25 (CHAVE PIX)</td>
<td><strong>BANCO ITAÚ</strong><br/>AGÊNCIA: 1352<br/>CONTA CORRENTE: 17777-6<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25</td>
</tr>
</table>

<p><strong>Cláusula 5ª.</strong> Os honorários serão integralmente devidos em caso de rescisão imotivada.</p>
<p><strong>Cláusula 6ª.</strong> Os honorários contratuais não se confundem com eventuais honorários de sucumbência.</p>
<p><strong>Cláusula 7ª.</strong> Distribuída a medida judicial, o total dos honorários serão devidos mesmo que haja composição amigável.</p>
<p><strong>Cláusula 8ª.</strong> Na hipótese de desistência antes do ajuizamento, serão devidos 50% do valor contratado.</p>

<div class="plain-sec">DA VIGÊNCIA E RESCISÃO</div>
<p><strong>Cláusula 9ª.</strong> Este contrato tem vigência até o adimplemento das obrigações ajustadas.</p>
<p><strong>Cláusula 10ª.</strong> Na rescisão antecipada pelo CONTRATANTE, serão devidos todos os valores pactuados.</p>

<div class="plain-sec">DA RESPONSABILIDADE</div>
<p><strong>Cláusula 11ª.</strong> É obrigação do CONTRATANTE fornecer ao CONTRATADO todos os documentos necessários em tempo hábil.</p>
<p><strong>Cláusula 12ª.</strong> Qualquer omissão ou negligência do CONTRATANTE será de sua inteira responsabilidade.</p>
<p><strong>Cláusula 13ª.</strong> Declarações falsas implicarão rescisão do contrato sem prejuízo dos honorários.</p>
<p><strong>Cláusula 14ª.</strong> O CONTRATANTE está ciente de que eventuais honorários de sucumbência e custas processuais são de sua inteira responsabilidade.</p>

<div class="plain-sec">DO FORO</div>
<p><strong>Cláusula 15ª.</strong> As partes elegem o foro da Comarca de Boa Vista/RR.</p>

<div class="plain-sec">DA ASSINATURA DIGITAL</div>
<p><strong>Cláusula 16ª.</strong> As partes admitem assinatura eletrônica mediante certificado do IC-BRASIL ou E-Notariado.</p>
<p><strong>Cláusula 17ª.</strong> A assinatura eletrônica é admitida nos termos do art. 10 da MP 2.200-2/01.</p>

<p>E por estarem assim justos e contratados, assinam o presente instrumento em 02 (duas) vias.</p>

<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>

<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}</td>
  <td><span class="sig-line"></span>Edson Santiago Advogados Associados<br/>CNPJ 22.162.240/0001-25<br/>{{advogado_1_nome}} — {{advogado_1_oab}}</td>
</tr>
<tr>
  <td style="padding-top:22pt;" colspan="2"><span class="sig-line" style="width:50%;margin:0 auto;display:block;"></span>{{advogado_2_nome}} — {{advogado_2_oab}}</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 17. CONTRATO DE HONORÁRIOS — A ROGO  [Sistema B — Plain]  ← NOVO
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato de Honorários — A Rogo (Edson Santiago)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato judicial assinado a rogo 30% retroativo + 15x R$500 — Edson Santiago',
    'conteudo'  => <<<'HTML'
<div class="plain">
<div class="plain-title">CONTRATO DE HONORÁRIOS</div>

<p>Pelo presente instrumento particular de contrato de honorários advocatícios, <strong>EDSON SANTIAGO ADVOGADOS ASSOCIADOS</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 22.162.240/0001-25, com sede na Rua Professor Agnelo Bittencourt, nº 335, bairro Centro, Boa Vista-RR, CEP 69301-430, neste ato representada pelos sócios <strong>{{advogado_1_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}} e <strong>{{advogado_2_nome}}</strong>, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, doravante denominado CONTRATADO; e do outro lado o(a) Sr(a). <strong>{{cliente_nome}}</strong>, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) da carteira de identidade nº {{cliente_rg}}, inscrito(a) no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, doravante denominado(a) CONTRATANTE, por impossibilidade de assinatura, firmando o presente instrumento a rogo por intermédio de <strong>{{a_rogo_nome}}</strong>, portador(a) da identidade nº {{a_rogo_identidade}} e inscrito(a) no CPF sob o nº {{a_rogo_cpf}}.</p>

<div class="plain-sec">DO OBJETO DO CONTRATO</div>
<p><strong>Cláusula 1ª.</strong> O presente instrumento particular tem por objeto a prestação de serviços advocatícios ao CONTRATANTE, especificamente no que se refere à propositura de ação judicial/administrativa em face do INSS para requerimento de benefício previdenciário.</p>

<div class="plain-sec">DAS DESPESAS</div>
<p><strong>Cláusula 2ª.</strong> Todas as despesas efetuadas pelo CONTRATADO ficarão a expensas do CONTRATANTE, desde que previamente autorizadas.</p>
<p><strong>Cláusula 3ª.</strong> Todas as eventuais despesas serão acompanhadas de documento comprobatório.</p>

<div class="plain-sec">DOS HONORÁRIOS ADVOCATÍCIOS</div>
<p><strong>Cláusula 4ª.</strong> O CONTRATANTE pagará ao CONTRATADO o valor de <strong>30% (Trinta por cento) sobre os valores retroativos (parcelas vencidas) e mais 15 parcelas de R$ 500,00 (Quinhentos Reais)</strong> no ato da implantação do benefício. Caso não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.</p>

<table class="bank">
<tr>
<td><strong>BANCO BRASIL</strong><br/>AGÊNCIA: 2617-4<br/>CONTA CORRENTE: 58681-1<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25 (CHAVE PIX)</td>
<td><strong>BANCO ITAÚ</strong><br/>AGÊNCIA: 1352<br/>CONTA CORRENTE: 17777-6<br/>EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br/>CNPJ: 22.162.240/0001-25</td>
</tr>
</table>

<p><strong>Cláusula 5ª.</strong> Os honorários serão integralmente devidos em caso de rescisão imotivada.</p>
<p><strong>Cláusula 6ª.</strong> Os honorários contratuais não se confundem com eventuais honorários de sucumbência.</p>
<p><strong>Cláusula 7ª.</strong> Distribuída a medida judicial, o total dos honorários serão devidos mesmo que haja composição amigável.</p>
<p><strong>Cláusula 8ª.</strong> Na hipótese de desistência antes do ajuizamento, serão devidos 50% do valor contratado.</p>

<div class="plain-sec">DA VIGÊNCIA E RESCISÃO</div>
<p><strong>Cláusula 9ª.</strong> Este contrato tem vigência até o adimplemento das obrigações ajustadas.</p>
<p><strong>Cláusula 10ª.</strong> Na rescisão antecipada pelo CONTRATANTE, serão devidos todos os valores pactuados.</p>

<div class="plain-sec">DA RESPONSABILIDADE</div>
<p><strong>Cláusula 11ª.</strong> É obrigação do CONTRATANTE fornecer ao CONTRATADO todos os documentos necessários em tempo hábil.</p>
<p><strong>Cláusula 12ª.</strong> Qualquer omissão ou negligência do CONTRATANTE será de sua inteira responsabilidade.</p>
<p><strong>Cláusula 13ª.</strong> Declarações falsas implicarão rescisão do contrato sem prejuízo dos honorários.</p>
<p><strong>Cláusula 14ª.</strong> O CONTRATANTE está ciente de que eventuais honorários de sucumbência e custas processuais são de sua inteira responsabilidade.</p>

<div class="plain-sec">DO FORO</div>
<p><strong>Cláusula 15ª.</strong> As partes elegem o foro da Comarca de Boa Vista/RR.</p>

<div class="plain-sec">DA ASSINATURA DIGITAL</div>
<p><strong>Cláusula 16ª.</strong> As partes admitem assinatura eletrônica mediante certificado do IC-BRASIL ou E-Notariado.</p>
<p><strong>Cláusula 17ª.</strong> A assinatura eletrônica é admitida nos termos do art. 10 da MP 2.200-2/01.</p>

<p>E por estarem assim justos e contratados, assinam o presente instrumento em 02 (duas) vias.</p>

<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>

<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}<br/>(Contratante — assina a rogo)</td>
  <td><span class="sig-line"></span>{{a_rogo_nome}}<br/>CPF: {{a_rogo_cpf}}<br/>(Assina a rogo)</td>
</tr>
<tr>
  <td style="padding-top:22pt;" colspan="2"><span class="sig-line" style="width:50%;margin:0 auto;display:block;"></span>Edson Santiago Advogados Associados<br/>{{advogado_1_nome}} — {{advogado_1_oab}}</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 18. DECLARAÇÃO DE HIPOSSUFICIÊNCIA (art. 98 CPC)  [Sistema D — Acadêmico]
// =============================================================================
$modelos[] = [
    'nome'      => 'Declaração de Hipossuficiência (art. 98 CPC)',
    'categoria' => 'Declaração',
    'descricao' => 'Declaração de hipossuficiência para gratuidade de justiça — art. 98 CPC',
    'conteudo'  => <<<'HTML'
<div class="dsimple">
<div class="dsimple-title">DECLARAÇÃO DE HIPOSSUFICIÊNCIA</div>

<p>Sr. {{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_profissao}}, portador da carteira de identidade nº {{cliente_rg}}, e inscrito no CPF/MF sob o nº {{cliente_cpf}}, residente e domiciliado no município de {{cliente_cidade}}, Estado de {{cliente_uf}}, sito a {{cliente_endereco}}, CEP: {{cliente_cep}}. DECLARO que não possuo condições econômicas de arcar com as custas, despesas processuais e honorários advocatícios para ingressar com um processo judicial relativo ao pedido de pagamentos retroativos contra a União, sem prejuízo de meu sustento e de minha família.</p>

<p>Nesse sentido, solicito a GRATUIDADE DA JUSTIÇA, com base no art. 98 do CPC.</p>

<p class="dsimple-date">{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>

<div class="dsimple-sigwrap">
<span class="dsimple-sigline"></span>
{{cliente_nome}}<br/>
CPF/MF nº {{cliente_cpf}}
</div>
</div>
HTML,
];

// =============================================================================
// 19. DECLARAÇÃO DE HIPOSSUFICIÊNCIA (DECLARANTE)  [Sistema C — Dourado]
// =============================================================================
$modelos[] = [
    'nome'      => 'Declaração de Hipossuficiência (DECLARANTE)',
    'categoria' => 'Declaração',
    'descricao' => 'Declaração de hipossuficiência com qualificação completa — art. 14 Lei 5584/1970',
    'conteudo'  => <<<'HTML'
<div class="dgold-title">HIPOSSUFICIÊNCIA</div>
<div class="dgold-sub">DECLARAÇÃO</div>
<hr class="dgold-rule"/>

<div class="dgold-sec">DECLARANTE</div>
<div class="dgold-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_estado_civil}}, {{cliente_profissao}}, Portador do documento de identidade Nº: {{cliente_rg}}, portador(a) do CPF nº {{cliente_cpf}}, residente e domiciliado(a) na {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone para contato {{cliente_telefone}}, e-mail: {{cliente_email}}.</p>
</div>

<div class="dgold-sec">DECLARAÇÃO</div>
<div class="dgold-body">
<p>Nos termos do art. 14, §1, da Lei n.º 5584/1970, das Leis 1060/1950 e 7115/1983 e Constituição Federal, art. 5º, LXXIV, a parte declara para os devidos fins e sob as penas da Lei, não ter como arcar com o pagamento de custas e demais despesas processuais sem prejuízo de seu sustento, pelo que requer os benefícios da justiça gratuita.</p>
</div>

<div class="dgold-date">{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</div>
<div class="dgold-sig">
<span class="dgold-sigline"></span>
{{cliente_nome}}<br/>
CPF: {{cliente_cpf}}
</div>
<div class="dgold-footer"></div>
HTML,
];

// =============================================================================
// 20. DECLARAÇÃO DE RESIDÊNCIA  [Sistema C — Dourado]
// =============================================================================
$modelos[] = [
    'nome'      => 'Declaração de Residência',
    'categoria' => 'Declaração',
    'descricao' => 'Declaração de residência sem comprovante em nome próprio',
    'conteudo'  => <<<'HTML'
<div class="dgold-title">RESIDÊNCIA</div>
<div class="dgold-sub">DECLARAÇÃO</div>
<hr class="dgold-rule"/>

<div class="dgold-sec">DECLARANTE</div>
<div class="dgold-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_estado_civil}}, {{cliente_profissao}}, Portador do documento de identidade Nº: {{cliente_rg}}, portador(a) do CPF nº {{cliente_cpf}}, residente e domiciliado(a) na {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone para contato {{cliente_telefone}}, e-mail: {{cliente_email}}.</p>
</div>

<div class="dgold-sec">DECLARAÇÃO</div>
<div class="dgold-body">
<p>Venho por meio desta declarar que não possuo comprovante de residência em meu nome, declaro ainda, que estou ciente de que declaração falsa pode implicar na sanção penal prevista no art. 299 do Código Penal, <em>in verbis</em>. Art. 299 — Omitir, em documento público ou particular, declaração que dele devia constar, ou nele inserir ou fazer inserir declaração falsa ou diversa da que devia ser escrita, com o fim de prejudicar direito, criar obrigação ou alterar a verdade sobre fato juridicamente relevante: Pena — reclusão, de um a cinco anos, e multa, se o documento é público, e reclusão de um a três anos, e multa, de quinhentos mil réis a cinco contos de réis, se o documento é particular. Parágrafo único — Se o agente é funcionário público, e comete o crime prevalecendo-se do cargo, ou se a falsificação ou alteração é de assentamento de registro civil, aumenta-se a pena de sexta parte.</p>
</div>

<div class="dgold-date">{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</div>
<div class="dgold-sig">
<span class="dgold-sigline"></span>
{{cliente_nome}}<br/>
CPF: {{cliente_cpf}}
</div>
<div class="dgold-footer"></div>
HTML,
];

// =============================================================================
// 21. CONTRATO PRESTAÇÃO DE SERVIÇO 30% — FILHO MENOR  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Contrato Prestação de Serviço 30% — Filho Menor (Real Assessoria)',
    'categoria' => 'Contrato',
    'descricao' => 'Contrato 30% sobre o benefício por responsável de filho menor — Real Assessoria',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">CONTRATO</div>
<div class="doc-bar-sub">Prestação de Serviço · 30% · Responsável por Filho Menor · Real Assessoria</div>

<div class="doc-sec">CONTRATANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) do RG nº {{cliente_rg}}, inscrito(a) no CPF sob o nº {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone {{cliente_telefone}}, e-mail {{cliente_email}}, neste ato na qualidade de representante legal do(a) filho(a) menor <strong>{{filho_nome}}</strong>, inscrito(a) no CPF sob o nº {{filho_cpf}}, nascido(a) em {{filho_data_nascimento}}.</p>
</div>

<div class="doc-sec">CONTRATADO</div>
<div class="doc-body">
<p>{{empresa_nome}}, CNPJ nº {{empresa_cnpj}}, com endereço na {{empresa_endereco}}, {{empresa_cidade}}/AM, neste ato representada por {{empresa_proprietarios}}, telefone {{empresa_fone}}, e-mail {{empresa_email}}.</p>
</div>

<div class="doc-sec">CLÁUSULAS E CONDIÇÕES</div>
<div class="doc-body">
<p>As partes acima qualificadas celebram o presente CONTRATO DE HONORÁRIOS POR SERVIÇOS PRESTADOS, que será regido pelas cláusulas e condições abaixo.</p>

<p><strong>Cláusula 1ª — Do objeto.</strong> O presente instrumento tem por objeto a prestação de serviços de assessoria para concessão e/ou restabelecimento de benefício previdenciário perante o INSS, inclusive na via administrativa ou judicial, abrangendo os atos e recursos necessários à defesa dos interesses do(a) CONTRATANTE e do(a) menor representado(a).</p>

<p><strong>Cláusula 2ª — Dos honorários.</strong> Pelos serviços prestados, o(a) CONTRATANTE pagará ao CONTRATADO o equivalente a <strong>30% (trinta por cento) sobre o benefício</strong>, a serem iniciadas no ato da implantação do benefício.</p>

<p><strong>Cláusula 3ª — Do inadimplemento.</strong> Em caso de descumprimento do acordo de pagamento, incidirá juros de 3% (três por cento) sobre cada parcela em atraso, sem prejuízo das demais medidas cabíveis.</p>

<p><strong>Cláusula 4ª — Da rescisão ou desistência.</strong> A revogação imotivada do patrocínio ou a desistência injustificada por parte do(a) CONTRATANTE não o(a) desobriga do pagamento integral dos honorários contratados.</p>

<p><strong>Parágrafo único.</strong> Na hipótese de desistência, abandono, omissão relevante ou não comparecimento do(a) CONTRATANTE a atos indispensáveis ao andamento do procedimento, que acarretem extinção, improcedência do pedido ou prejuízo à demanda, será devida multa compensatória de R$ 2.000,00 (dois mil reais).</p>

<p><strong>Cláusula 5ª — Da sucessão dos honorários.</strong> Em caso de morte ou incapacidade civil do CONTRATADO, os honorários serão pagos por seus sucessores ou representante legal, na proporção do trabalho efetivamente realizado.</p>

<p><strong>Cláusula 6ª — Do foro.</strong> Fica eleito o foro da Comarca de {{cliente_cidade}}/{{cliente_uf}} para dirimir quaisquer controvérsias oriundas deste contrato.</p>

<p>Por estarem justos e contratados, firmam o presente instrumento em duas vias de igual teor e forma, juntamente com duas testemunhas.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CONTRATANTE</td>
  <td><span class="sig-line"></span>{{empresa_nome}}<br/>CONTRATADO</td>
</tr>
</table>
</div>
HTML,
];

// =============================================================================
// 22. PROCURAÇÃO JUDICIAL A ROGO  [Sistema A — Navy]
// =============================================================================
$modelos[] = [
    'nome'      => 'Procuração Judicial A Rogo (Edson Santiago)',
    'categoria' => 'Procuração',
    'descricao' => 'Procuração Ad Judicia Et Extra assinada a rogo — Edson Santiago Advogados',
    'conteudo'  => <<<'HTML'
<div class="doc-bar">PROCURAÇÃO</div>
<div class="doc-bar-sub">Ad Judicia Et Extra · A Rogo · Edson Santiago Advogados</div>

<div class="doc-sec">OUTORGANTE</div>
<div class="doc-body">
<p>{{cliente_nome}}, {{cliente_nacionalidade}}, {{cliente_profissao}}, {{cliente_estado_civil}}, portador(a) do documento de identidade n.º {{cliente_rg}}, inscrito(a) no CPF/MF sob o n.º {{cliente_cpf}}, residente e domiciliado(a) em {{cliente_endereco}}, {{cliente_cidade}}/{{cliente_uf}}, telefone: {{cliente_telefone}}, e-mail: {{cliente_email}}.</p>
<p style="margin-top:6pt;">Assina a rogo do(a) outorgante: <strong>{{a_rogo_nome}}</strong>, portador(a) da cédula de identidade n.º {{a_rogo_identidade}}, inscrito(a) no CPF sob o n.º {{a_rogo_cpf}}, por ser o(a) outorgante analfabeto(a) ou por outra razão que o(a) impossibilite de assinar.</p>
</div>

<div class="doc-sec">OUTORGADOS</div>
<div class="doc-body">
<p>{{advogado_1_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_1_oab}}, {{advogado_2_nome}}, Brasileiro, Casado, Advogado, inscrito na {{advogado_2_oab}}, ambos com endereço profissional na {{advogado_1_endereco}}, {{advogado_1_cidade}}/{{advogado_1_uf}}, tel.: {{advogado_1_fone}}, e e-mail: {{advogado_1_email}}, onde deverão receber intimações.</p>
</div>

<div class="doc-sec">PODERES ESPECÍFICOS</div>
<div class="doc-body">
<p>Por meio do presente instrumento particular de mandato, o(a) outorgante nomeia e constitui seus bastante procuradores os advogados acima qualificados, conferindo-lhes poderes específicos para representá-lo(a) em juízo ou fora dele, ativa e passivamente, com a cláusula <em>Ad Judicia</em> e <em>Et Extra</em>, em qualquer juízo, instância ou tribunal, inclusive Juizados Especiais, para praticar todos os atos necessários à defesa de seus interesses, podendo, para tanto, propor, contestar, acompanhar, transigir, acordar, discordar, firmar termos de compromisso, substabelecer, e praticar todos os demais atos necessários ao fiel e cabal cumprimento deste mandato.</p>
</div>

<div class="doc-sig">
<p>{{cliente_cidade}}/{{cliente_uf}}, {{data_hoje_extenso}}.</p>
<table class="sig-table">
<tr>
  <td><span class="sig-line"></span>{{cliente_nome}}<br/>CPF/MF: {{cliente_cpf}}<br/>(Outorgante — assina a rogo)</td>
  <td><span class="sig-line"></span>{{a_rogo_nome}}<br/>CPF: {{a_rogo_cpf}}<br/>(Assina a rogo)</td>
</tr>
<tr>
  <td style="padding-top:22pt;"><span class="sig-line"></span>Testemunha 1</td>
  <td style="padding-top:22pt;"><span class="sig-line"></span>Testemunha 2</td>
</tr>
</table>
</div>
HTML,
];

// ── Inserir modelos ──────────────────────────────────────────────────────────
$ins2 = $conn->prepare(
    "INSERT INTO modelos_documentos (nome, categoria, descricao, conteudo, criado_por) VALUES (?,?,?,?,'seed')"
);

foreach ($modelos as $m) {
    $ins2->bind_param('ssss', $m['nome'], $m['categoria'], $m['descricao'], $m['conteudo']);
    $ins2->execute();
    $log[] = "Modelo inserido: {$m['nome']} (id={$conn->insert_id})";
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Seed</title>
<style>
body{font-family:monospace;padding:2rem;background:#111;color:#0f0;}
h2{color:#ff0;}
li{margin:.3rem 0;}
.ok{color:#0f0;}
</style></head>
<body>
<h2>Seed concluído — <?= count($modelos) ?> modelos inseridos</h2>
<ul>
<?php foreach ($log as $line): ?>
  <li class="ok"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p style="color:#888; margin-top:20px;">Pode fechar esta aba agora.</p>
</body>
</html>
