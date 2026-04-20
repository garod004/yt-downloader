<?php
require_once dirname(__DIR__) . '/documentos_bootstrap.php';
include 'verificar_permissao.php';

$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Erro: Dompdf nao encontrado no servidor. Envie a pasta vendor completa para public_html/vendor.');
}
require_once $autoloadPath;

if (!class_exists('Dompdf\\Dompdf') || !class_exists('Dompdf\\Options')) {
    die('Erro: Dompdf instalado incorretamente no servidor.');
}

include 'conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Erro: ID do cliente nao especificado.');
}

$cliente_id = (int) $_GET['id'];

function stmtFetchSingleAssoc(mysqli_stmt $stmt)
{
    if (method_exists($stmt, 'get_result')) {
        $result = @$stmt->get_result();
        if ($result !== false) {
            $row = $result->fetch_assoc();
            return $row ?: null;
        }
    }

    $meta = $stmt->result_metadata();
    if ($meta === false) {
        return null;
    }

    $fields = [];
    $rowData = [];
    $bindResult = [];

    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
        $rowData[$field->name] = null;
        $bindResult[] = &$rowData[$field->name];
    }

    call_user_func_array([$stmt, 'bind_result'], $bindResult);

    if (!$stmt->fetch()) {
        return null;
    }

    $current = [];
    foreach ($fields as $name) {
        $current[$name] = $rowData[$name];
    }

    return $current;
}

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function titleCasePt($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_convert_case')) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($value));
}

$cliente = null;
$sql = 'SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email FROM clientes WHERE id = ?';

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $cliente = stmtFetchSingleAssoc($stmt);
    $stmt->close();
} else {
    error_log('Erro DB [' . basename(__FILE__) . ']: ' . $conn->error);
    die('Erro interno ao gerar documento. Por favor, tente novamente.');
}

$conn->close();

if (!$cliente) {
    die('Erro: Cliente nao encontrado.');
}

$nome_cliente = esc(titleCasePt($cliente['nome'] ?? 'Nao informado'));
$nacionalidade_cliente = esc(titleCasePt($cliente['nacionalidade'] ?? 'Nao informado'));
$profissao_cliente = esc(titleCasePt($cliente['profissao'] ?? 'Nao informado'));
$estado_civil_cliente = esc(titleCasePt($cliente['estado_civil'] ?? 'Nao informado'));
$rg_cliente = esc(trim((string) ($cliente['rg'] ?? 'Nao informado')));
$cpf_cliente = esc(trim((string) ($cliente['cpf'] ?? 'Nao informado')));
$endereco_cliente = esc(titleCasePt($cliente['endereco'] ?? 'Nao informado'));
$cidade_cliente = esc(titleCasePt($cliente['cidade'] ?? 'Nao informado'));
$uf_cliente = esc(strtoupper(trim((string) ($cliente['uf'] ?? 'NA'))));
$telefone_cliente = esc(trim((string) ($cliente['telefone'] ?? 'Nao informado')));
$email_cliente = esc(trim((string) ($cliente['email'] ?? 'Nao informado')));

$data_atual = date('d/m/Y');

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Prestacao de Servico</title>
    <style>
        @page {
            size: A4;
            margin: 4mm 20mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1a1a1a;
            font-size: 8.9pt;
            line-height: 1.22;
            background: #f3f6fa;
        }

        .documento {
            border: 1px solid #c6d2e0;
            border-radius: 10px;
            page-break-inside: avoid;
            background: #ffffff;
        }

        .topo {
            border-bottom: 2px solid #0f3b69;
            padding: 9px 11px 8px 11px;
            position: relative;
            background: #0f3b69;
            border-radius: 10px 10px 0 0;
        }

        .topo::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 2px;
            background: #7dd3fc;
        }

        .titulo {
            margin: 0;
            font-size: 15.5pt;
            letter-spacing: 0.9px;
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
        }

        .subtitulo {
            margin: 2px 0 0 0;
            font-size: 7.8pt;
            color: #dbeafe;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .miolo {
            padding: 8px 9px 10px 9px;
            background: #ffffff;
        }

        .secao {
            margin-bottom: 7px;
            border: 1px solid #d9e4ef;
            border-radius: 8px;
            padding: 7px 8px;
            background: #f8fbff;
        }

        .secao-titulo {
            display: inline-block;
            margin: -13px 0 4px 0;
            padding: 2px 9px;
            border-radius: 999px;
            background: #0f3b69;
            color: #fff;
            font-size: 7.9pt;
            font-weight: 700;
            letter-spacing: 0.45px;
            text-transform: uppercase;
        }

        .linha {
            margin: 2px 0;
        }

        .linha strong {
            color: #0b2f57;
        }

        .clausulas {
            margin-top: 6px;
            text-align: justify;
            border: 1px solid #d9e4ef;
            border-radius: 8px;
            padding: 7px 8px;
            background: #f8fbff;
        }

        .clausula {
            margin-bottom: 5px;
            padding-left: 6px;
            border-left: 2px solid #d3dfec;
        }

        .clausula:last-child {
            margin-bottom: 0;
        }

        .clausula strong {
            color: #0b2f57;
        }

        .assinaturas {
            margin-top: 9px;
            page-break-inside: avoid;
            border-top: 1px solid #d9e4ef;
            padding-top: 8px;
        }

        .data-local {
            text-align: center;
            margin-bottom: 12px;
            font-size: 8.5pt;
            color: #334155;
        }

        .assinatura-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .assinatura-grid td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 6px;
        }

        .linha-assinatura {
            border-top: 1px solid #0f3b69;
            margin-top: 20px;
            padding-top: 4px;
            font-weight: 700;
            font-size: 8.4pt;
            color: #0b2f57;
        }

        .rodape-fixo {
            position: fixed;
            left: 20mm;
            right: 20mm;
            bottom: 4mm;
            height: 6px;
            background: #0f3b69;
            border-radius: 3px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="documento">
            <div class="topo">
                <h1 class="titulo">CONTRATO</h1>
                <div class="subtitulo">PRESTACAO DE SERVICO</div>
            </div>

            <div class="miolo">
                <div class="secao">
                    <span class="secao-titulo">CONTRATANTE</span>
                    <div class="linha"><strong>Nome:</strong> $nome_cliente</div>
                    <div class="linha"><strong>Nacionalidade:</strong> $nacionalidade_cliente &nbsp;&nbsp; <strong>Estado civil:</strong> $estado_civil_cliente</div>
                    <div class="linha"><strong>Profissao:</strong> $profissao_cliente &nbsp;&nbsp; <strong>CPF:</strong> $cpf_cliente</div>
                    <div class="linha"><strong>RG:</strong> $rg_cliente &nbsp;&nbsp; <strong>Telefone:</strong> $telefone_cliente</div>
                    <div class="linha"><strong>Endereco:</strong> $endereco_cliente, $cidade_cliente - $uf_cliente</div>
                    <div class="linha"><strong>E-mail:</strong> $email_cliente</div>
                </div>

                <div class="secao">
                    <span class="secao-titulo">CONTRATADO</span>
                    <div class="linha">
                        Real Assessoria Previdenciaria, CNPJ: 13.244.474/0001-20, Rua M, no 65, Nova Cidade,
                        CEP: 69.097-030, Manaus - AM, neste ato representada por Dioleno Nobrega Silva,
                        (92) 99129-0577, e-mail: dioleno.nobrega@gmail.com.
                    </div>
                </div>

                <div class="clausulas">
                    <div class="clausula">
                        As partes acima qualificadas celebram, de maneira justa e acordada, o presente
                        <strong>CONTRATO DE HONORARIOS POR SERVICOS PRESTADOS</strong>, ficando desde ja aceito
                        que o referido instrumento sera regido pelas condicoes previstas em lei, devidamente
                        especificadas pelas clausulas e condicoes a seguir descritas.
                    </div>

                    <div class="clausula">
                        <strong>CLAUSULA 1</strong> - O presente instrumento tem como objetivo a prestacao de servicos de
                        assessoria a serem realizados pelo Contratado para concessao/restabelecimento de beneficio
                        previdenciario em face do INSS, na representacao e defesa dos interesses do(a) Contratante,
                        sendo realizado na via administrativa ou judicial, em qualquer juizo e/ou instancia,
                        apresentar e opor acoes, bem como interpor os recursos necessarios e competentes para
                        garantir a protecao e o exercicio dos seus direitos.
                    </div>

                    <div class="clausula">
                        <strong>CLAUSULA 2</strong> - O Contratante assume a obrigatoriedade de efetuar o pagamento pelos
                        servicos prestados ao Contratado no valor de <strong>30% (trinta por cento) do beneficio</strong>
                        no ato da implantacao do beneficio. Caso nao haja cumprimento no acordo de pagamento,
                        o Contratante pagara, a titulo de juros, um percentual de 3% sobre cada parcela.
                    </div>

                    <div class="clausula">
                        <strong>CLAUSULA 3</strong> - Deixando o Contratante de, imotivadamente, ter o patrocinio destes
                        causidicos, ora Contratado, nao se desobriga ao pagamento dos honorarios ajustados integralmente.
                    </div>

                    <div class="clausula">
                        <strong>PARAGRAFO UNICO</strong> - Em caso de desistencia ou qualquer ato de desidia do Contratante,
                        como deixar de comparecer em qualquer ato do processo que gere extincao ou improcedencia da
                        acao ou de alguns dos pedidos da demanda, sera aplicada multa de R$ 2.000,00 (dois mil reais).
                    </div>

                    <div class="clausula">
                        <strong>CLAUSULA 4</strong> - Caso haja morte ou incapacidade civil em ocorrencia do contratado,
                        a contratada constituida recebera os honorarios na proporcao do trabalho realizado.
                    </div>

                    <div class="clausula">
                        <strong>CLAUSULA 5</strong> - As partes elegem o foro da Comarca de Manaus - AM.
                    </div>

                    <div class="clausula">
                        <strong>CLAUSULA 6</strong> - Por estarem assim justos e contratados, firmam o presente contrato em
                        duas vias de igual teor e forma, para que o mesmo, nos seus devidos fins de direito, produza
                        seus juridicos e legais efeitos, juntamente com as 02 (duas) testemunhas, igualmente
                        subscritas, identificadas e assinadas.
                    </div>
                </div>

                <div class="assinaturas">
                    <div class="data-local">$cidade_cliente - $uf_cliente, $data_atual</div>

                    <table class="assinatura-grid">
                        <tr>
                            <td>
                                <div class="linha-assinatura">Assinatura Contratante<br>$nome_cliente</div>
                            </td>
                            <td>
                                <div class="linha-assinatura">Assinatura Contratado</div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
    </div>
    <div class="rodape-fixo"></div>
</body>
</html>
HTML;

$nome_arquivo = 'Contrato_Servicos_' . preg_replace('/\s+/', '_', trim((string) ($cliente['nome'] ?? 'cliente'))) . '.pdf';

try {
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->setChroot(dirname(__DIR__, 2));

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($nome_arquivo, ['Attachment' => 1]);
} catch (Throwable $e) {
    error_log('Erro ao gerar contrato em PDF: ' . $e->getMessage());
    die('Erro ao gerar contrato em PDF: ' . $e->getMessage());
}

exit(0);
?>


