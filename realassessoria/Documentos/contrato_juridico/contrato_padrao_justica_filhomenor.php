<?php
require_once dirname(__DIR__) . '/documentos_bootstrap.php';
include 'verificar_permissao.php';
require_once 'vendor/autoload.php';
include 'conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!($conn instanceof mysqli)) {
    error_log('Erro DB [' . basename(__FILE__) . ']: conexão mysqli indisponível.');
    die('Erro interno ao gerar documento. Por favor, tente novamente.');
}

function stmt_fetch_first_assoc_contrato_justica($stmt) {
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

    $fields = array();
    $rowData = array();
    $bindResult = array();

    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
        $rowData[$field->name] = null;
        $bindResult[] = &$rowData[$field->name];
    }

    call_user_func_array(array($stmt, 'bind_result'), $bindResult);

    if ($stmt->fetch()) {
        $current = array();
        foreach ($fields as $name) {
            $current[$name] = $rowData[$name];
        }
        return $current;
    }

    return null;
}

// 1. Obter o ID do cliente da URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Erro: ID do cliente não especificado.");
}

$cliente_id = (int) $_GET['id'];

// 2. Buscar os dados do cliente no banco de dados
$cliente = null;
$sql = "SELECT id, nome, nacionalidade, profissao, estado_civil, rg, cpf, endereco, cidade, uf, telefone, email, observacao FROM clientes WHERE id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cliente = stmt_fetch_first_assoc_contrato_justica($stmt);
    $stmt->close();
} else {
    error_log("Erro DB [" . basename(__FILE__) . "]: " . $conn->error);
    die("Erro interno ao gerar documento. Por favor, tente novamente.");
}

// 3. Buscar dados do primeiro filho menor cadastrado
$filho_menor = null;
$sql_filho = "SELECT nome, cpf, data_nascimento FROM filhos_menores WHERE cliente_id = ? ORDER BY data_nascimento DESC LIMIT 1";

if ($stmt_filho = $conn->prepare($sql_filho)) {
    $stmt_filho->bind_param("i", $cliente_id);
    $stmt_filho->execute();
    $filho_menor = stmt_fetch_first_assoc_contrato_justica($stmt_filho);
    $stmt_filho->close();
}

$conn->close();

if (!$cliente) {
    die("Erro: Cliente não encontrado.");
}

// 4. Preparar dados do cliente
$nome_cliente = htmlspecialchars(mb_convert_case($cliente['nome'], MB_CASE_TITLE, 'UTF-8'));
$nacionalidade_cliente = htmlspecialchars(mb_convert_case($cliente['nacionalidade'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));
$profissao_cliente = htmlspecialchars(mb_convert_case($cliente['profissao'] ?: 'Não Informada', MB_CASE_TITLE, 'UTF-8'));
$estado_civil_cliente = htmlspecialchars(mb_convert_case($cliente['estado_civil'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$rg_cliente = htmlspecialchars($cliente['rg'] ?: 'Não Informado');
$cpf_cliente = htmlspecialchars($cliente['cpf'] ?: 'Não Informado');
$endereco_cliente = htmlspecialchars(mb_convert_case($cliente['endereco'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$cidade_cliente = htmlspecialchars(mb_convert_case($cliente['cidade'] ?: 'Não Informado', MB_CASE_TITLE, 'UTF-8'));
$uf_cliente = htmlspecialchars(strtoupper($cliente['uf'] ?: 'Não Informado'));

$data_atual = date('d/m/Y');

// 5. Preparar dados do filho menor (se houver)
$texto_filho = "";
if ($filho_menor) {
    $nome_filho = htmlspecialchars(mb_convert_case($filho_menor['nome'], MB_CASE_TITLE, 'UTF-8'));
    $cpf_filho = htmlspecialchars($filho_menor['cpf'] ?: 'Não Informado');
    $data_nasc_filho = htmlspecialchars($filho_menor['data_nascimento'] ?: 'Não Informada');

    if (strlen($data_nasc_filho) === 10 && strpos($data_nasc_filho, '-') !== false) {
        $data_nasc_filho = implode('/', array_reverse(explode('-', $data_nasc_filho)));
    }

    $texto_filho = "<strong>$nome_filho</strong>, CPF nº $cpf_filho, nascido em $data_nasc_filho.";
}

// 6. Template do contrato
$html = <<<EOT
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Honorários</title>
    <style>
        @page { margin: 10mm 12mm; }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #222;
        }
        h1 {
            text-align: center;
            font-size: 16pt;
            margin: 0 0 12px 0;
            letter-spacing: 0.5px;
        }
        .logo-header {
            text-align: center;
            margin: 0 0 20px 0;
        }
        .logo-header img {
            width: 350px;
            height: auto;
        }
        .clausula-title {
            font-weight: bold;
            margin-top: 12px;
        }
        .bloco {
            margin: 8px 0 12px 0;
            text-align: justify;
        }
        .assinaturas {
            margin-top: 30px;
        }
        .linha-assinatura {
            border-top: 1px solid #333;
            width: 70%;
            margin: 28px auto 6px auto;
        }
        .assinatura-texto {
            text-align: center;
            font-size: 10pt;
        }
        .banco {
            margin: 12px 0 16px 0;
            width: 100%;
            border: 2px solid #000;
            border-collapse: collapse;
        }
        .banco td {
            border: 2px solid #000;
            padding: 10px 12px;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            font-size: 11pt;
            line-height: 1.4;
            width: 50%;
        }
    </style>
</head>
<body>
    <h1>CONTRATO DE HONORÁRIOS</h1>

    <div class="bloco">
        Pelo presente instrumento particular de contrato de honorários advocatícios, <strong>EDSON SANTIAGO ADVOGADOS ASSOCIADOS</strong>,
        pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 22.162.240/0001-25, com sede na Rua Professor Agnelo
        Bittencourt, nº 335, bairro Centro, Boa Vista-RR, CEP 69301-430, neste ato representada pelos sócios <strong>EDSON SILVA
        SANTIAGO</strong>, Brasileiro, Casado, Advogado, inscrito na OAB/RR sob o nº 619 e <strong>OSTIVALDO MENEZES DO NASCIMENTO JÚNIOR</strong>,
        Brasileiro, Casado, Advogado, inscrito na OAB/RR sob o nº 1280, doravante denominado <strong>CONTRATADO</strong>; e do outro lado
        o(a) Sr(a). <strong>$nome_cliente</strong>, $nacionalidade_cliente, $profissao_cliente, $estado_civil_cliente, portador(a) da carteira
        de identidade nº $rg_cliente, inscrito(a) no CPF/MF sob o nº $cpf_cliente, residente e domiciliado(a) em $endereco_cliente, $cidade_cliente - $uf_cliente, doravante denominado(a) <strong>CONTRATANTE</strong>. Neste ato representando o(a) seu(ua) filho(a) o(a) menor: $texto_filho
    </div>

    <div class="clausula-title"><strong>DO OBJETO DO CONTRATO</strong></div>
    <div class="bloco">
        <strong>Cláusula 1ª.</strong> O presente instrumento particular tem por objeto a prestação de serviços advocatícios ao <strong>CONTRATANTE</strong>,
        especificamente no que se refere à propositura de ação judicial/administrativa em face do INSS para requerimento de benefício previdenciário. Este instrumento também abrange a prestação de
        consultoria jurídica ao <strong>CONTRATANTE</strong>, sempre que necessário, para o esclarecimento de questões relacionadas ao processo.
    </div>

    <div class="clausula-title"><strong>DAS DESPESAS</strong></div>
    <div class="bloco">
        <strong>Cláusula 2ª.</strong> Todas as despesas efetuadas pelo <strong>CONTRATADO</strong>, mesmo que indiretamente relacionadas com a sua atuação,
        incluindo-se cópias, digitalizações, envio de correspondências, peças técnicas, pedidos de certidões, emolumentos,
        viagens, pagamento de taxas e demais gastos de natureza diversa da verba honorária, ficarão a expensas do <strong>CONTRATANTE</strong>,
        desde que previamente autorizadas.
    </div>
    <div class="bloco">
        <strong>Cláusula 3ª.</strong> Todas as eventuais despesas serão acompanhadas de documento comprobatório, devidamente organizado pelo
        <strong>CONTRATADO</strong>.
    </div>

    <div class="clausula-title"><strong>DOS HONORÁRIOS ADVOCATÍCIOS</strong></div>
    <div class="bloco">
        <strong>Cláusula 4ª.</strong> O <strong>CONTRATANTE</strong>, a título de contraprestação pelos serviços jurídicos prestados, pagará ao <strong>CONTRATADO</strong> o
        valor de <strong>30% (Trinta por cento) sobre os valores retroativos (parcelas vencidas) e mais 15 parcelas de R$ 500,00 (Quinhentos Reais)</strong> no ato da implantação do benefício. Caso
        não haja cumprimento no acordo de pagamento, a contratante pagará a título de juros um percentual de 3% sobre cada parcela.
    </div>
    <table class="banco" role="presentation">
        <tr>
            <td>
                BANCO BRASIL<br>
                AGÊNCIA: 2617-4<br>
                CONTA CORRENTE: 58681-1<br>
                EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br>
                CNPJ: 22.162.240/0001-25 (CHAVE PIX)
            </td>
            <td>
                BANCO ITAÚ<br>
                AGÊNCIA: 1352<br>
                CONTA CORRENTE: 17777-6<br>
                EDSON SANTIAGO ADVOGADOS ASSOCIADOS<br>
                CNPJ: 22.162.240/0001-25
            </td>
        </tr>
    </table>
    <div class="bloco">
        <strong>Cláusula 5ª.</strong> Os honorários aqui previstos serão integralmente devidos pelo <strong>CONTRATANTE</strong> em caso de rescisão imotivada
        do presente contrato. A revogação do mandato no curso do processo não importará em qualquer alteração da presente
        avença, ainda que em caráter proporcional, declarando que ainda que acaso decida alterar seu mandatário, honrará
        integralmente os termos do presente contrato.
    </div>
    <div class="bloco">
        <strong>Cláusula 6ª.</strong> Os honorários contratuais convencionados no presente instrumento particular não se confundem com eventuais
        honorários de sucumbência impostos à parte contrária por sentença judicial.
    </div>
    <div class="bloco">
        <strong>Cláusula 7ª.</strong> Distribuída a medida judicial, o total dos honorários serão devidos mesmo que haja composição amigável
        quanto ao pedido, venha o <strong>CONTRATANTE</strong> a desistir do pedido ou, ainda, se for cassada a procuração sem culpa do
        <strong>CONTRATADO</strong>.
    </div>
    <div class="bloco">
        <strong>Cláusula 8ª.</strong> Na hipótese de desistência antes do ajuizamento da ação, serão devidos 50% (cinquenta por cento) do valor
        contratado.
    </div>

    <div class="clausula-title">DA VIGÊNCIA E RESCISÃO</div>
    <div class="bloco">
        <strong>Cláusula 9ª.</strong> Este contrato tem vigência até o adimplemento das obrigações ajustadas e pode ser rescindido a qualquer
        tempo por qualquer das partes, mediante aviso prévio de 30 (trinta) dias, por escrito e com comprovante de entrega.
    </div>
    <div class="bloco">
        <strong>Cláusula 10ª.</strong> Na hipótese de rescisão antecipada pelo <strong>CONTRATANTE</strong>, será devido ao <strong>CONTRATADO</strong> todos os valores
        pactuados neste contrato, bem como o percentual correspondente à parcela do serviço já executada.
    </div>

    <div class="clausula-title"><strong>DA RESPONSABILIDADE</strong></div>
    <div class="bloco">
        <strong>Cláusula 11ª.</strong> É obrigação do <strong>CONTRATANTE</strong>, sempre que solicitado, entregar, fornecer ou disponibilizar ao <strong>CONTRATADO</strong>
        todos os documentos necessários, provas, informações e subsídios, em tempo hábil, para que este possa cumprir com suas
        obrigações contratuais, não se responsabilizando o <strong>CONTRATADO</strong>, por quaisquer prejuízos em face da desídia do
        <strong>CONTRATANTE</strong>.
    </div>
    <div class="bloco">
        <strong>Cláusula 12ª.</strong> Qualquer omissão ou negligência por parte do <strong>CONTRATANTE</strong> será de sua inteira responsabilidade, caso
        advenha algum prejuízo a seus interesses.
    </div>
    <div class="bloco">
        <strong>Cláusula 13ª.</strong> Caso o <strong>CONTRATANTE</strong> falte com a verdade em suas declarações com o <strong>CONTRATADO</strong>, o presente instrumento
        particular será rescindido sem prejuízo dos honorários já convencionados.
    </div>
    <div class="bloco">
        <strong>Cláusula 14ª.</strong> Fica expressamente ciente o <strong>CONTRATANTE</strong> que em caso de improcedência da ação a ser proposta, em não
        sendo beneficiário da justiça gratuita, poderá haver condenação de honorários de sucumbência ao advogado da parte
        contrária, assim como condenação ao pagamento de custas processuais, ônus esses que serão de inteira responsabilidade
        do <strong>CONTRATANTE</strong> e desvinculados do presente instrumento particular e isento de qualquer desconto referente aos
        honorários contratuais devidos ao <strong>CONTRATADO</strong>.
    </div>

    <div class="clausula-title"><strong>DO FORO</strong></div>
    <div class="bloco">
        <strong>Cláusula 15ª.</strong> Para dirimir quaisquer controvérsias oriundas deste contrato, as partes elegem o foro da Comarca de
        Boa Vista-RR.
    </div>

    <div class="clausula-title"><strong>DA ASSINATURA DIGITAL</strong></div>
    <div class="bloco">
        <strong>Cláusula 16ª.</strong> As partes admitem a possibilidade de utilização de assinatura eletrônica mediante certificado do
        IC-BRASIL ou E-Notariado, sendo que cada parte arcará com seu respectivo custo.
    </div>
    <div class="bloco">
        <strong>Cláusula 17ª.</strong> A assinatura eletrônica passa a ser admitida em todos os documentos que envolvam as partes, seja na
        qualidade de parte, de interveniente anuente ou de terceiros, a quem o documento venha a ser oposto, de maneira que os
        documentos assim assinados constituem documentos eletrônicos para os fins do art. 10, caput, e parágrafo segundo, da
        MP 2.200-2/01, c/c o provimento nº 100 do CNJ.
    </div>

    <div class="bloco">
        E por estarem assim justos e contratados, assinam o presente instrumento em 02 (duas) vias de igual teor e forma,
        e na presença de 02 (duas) testemunhas abaixo assinadas.
    </div>

    <div class="assinaturas">
        <div class="assinatura-texto">$cidade_cliente - $uf_cliente, $data_atual</div>

        <div class="linha-assinatura"></div>
        <div class="assinatura-texto"><strong>$nome_cliente</strong><br>CPF/MF: $cpf_cliente</div>

        <div class="linha-assinatura"></div>
        <div class="assinatura-texto"><strong>Edson Santiago Advogados Associados</strong><br>CNPJ 22.162.240/0001-25<br><strong>Edson Santiago</strong><br>OAB/RR nº 619</div>

        <div class="linha-assinatura"></div>
        <div class="assinatura-texto"><strong>Edson Santiago Advogados Associados</strong><br>CNPJ 22.162.240/0001-25<br><strong>Ostivaldo Menezes do Nascimento Júnior</strong><br>OAB/RR nº 1280</div>
    </div>
</body>
</html>
EOT;

// 7. Gerar o PDF
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->setChroot(APP_ROOT_DIR);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nome_arquivo = "Contrato_Justica_Padrao_" . str_replace(' ', '_', $nome_cliente) . ".pdf";
$dompdf->stream($nome_arquivo, array("Attachment" => 1));

exit(0);
?>
