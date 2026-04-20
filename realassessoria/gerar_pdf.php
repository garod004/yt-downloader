<?php
require_once __DIR__ . '/security_bootstrap.php';

session_start();
require_once __DIR__ . '/security_rls.php';

// Impedir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id']) || !isset($_GET['id'])) { header("Location: index.html"); exit(); }
include 'conexao.php';
require('fpdm.php'); // Inclui a biblioteca FPDM

$cliente_id = (int)$_GET['id'];

if ($cliente_id <= 0) {
    die('Cliente invalido.');
}

rls_enforce_cliente_or_die($conn, $cliente_id, false);

// Buscar dados do cliente no BD
$sql = "SELECT * FROM clientes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$result = stmt_get_result($stmt);

if ($cliente = $result->fetch_assoc()) {
    // Preparar os dados para o PDF
    $fields = array(
        'nome' => $cliente['nome'],
        'nacionalidade' => $cliente['nacionalidade'],
        'profissao' => $cliente['profissao'],
        'estado_civil' => $cliente['estado_civil'],
        'rg' => $cliente['rg'],
        'cpf' => $cliente['cpf'],
        'endereco' => $cliente['endereco'],
        'cidade' => $cliente['cidade'],
        'uf' => $cliente['uf'],
        'telefone' => $cliente['telefone'],
        'email' => $cliente['email'],
        'observacao' => $cliente['observacao']
    );
    
    // Configurar e gerar o PDF
    // Você pode criar um template PDF com campos para preenchimento, mas para simplificar, 
    // vamos gerar um PDF simples do zero usando FPDM de forma básica.
    
    // A biblioteca FPDM é normalmente usada para preencher formulários PDF existentes, 
    // se você não tiver um template, a FPDF normal pode ser mais simples para gerar texto cru.
    // Vou reverter para um script mais simples usando FPDF/FPDM para gerar texto básico.
    
    // Nota: O exemplo abaixo imprime texto simples no navegador, não em um formulário PDF.

    $pdf = new FPDM('template_vazio.pdf'); // Você precisaria de um template PDF vazio aqui.
    // Como criar um template é complexo, sugiro FPDF pura se não quiser um template pré-definido.
    
    // --- Usando FPDF pura para gerar texto simples (melhor para este caso): ---
    require('fpdf.php'); // Você precisaria baixar a biblioteca FPDF e colocar em uma pasta 'fpdf'

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(40,10,'Dados do Cliente: ' . $cliente['nome']);
    $pdf->Ln(20);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(40,10,utf8_decode('Nacionalidade: ' . $cliente['nacionalidade']));
    $pdf->Ln();
    $pdf->Cell(40,10,utf8_decode('Profissão: ' . $cliente['profissao']));
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('Estado Civil: ' . $cliente['estado_civil']));
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('RG: ' . $cliente['rg']));
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('CPF: ' . $cliente['cpf']));
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('endereco'. $cliente['endereco'])); 
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('cidade'. $cliente['cidade'])); 
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('uf'. $cliente['uf'])); 
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('Telefone: ' . $cliente['telefone']));
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('Email: ' . $cliente['email']));
    $pdf->Ln();
    $pdf->Cell(0,10,utf8_decode('Observação: ' . $cliente['observacao']));
    
    // ... adicione todas as outras células de dados aqui ...
    $pdf->Output();


} else {
    echo "Cliente não encontrado ou você não tem permissão para visualizá-lo.";
}

$stmt->close();
$conn->close();
?>



