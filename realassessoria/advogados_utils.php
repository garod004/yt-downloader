<?php

function garantirTabelaAdvogados($conn)
{
    static $done = false;
    if ($done) return true;

    $sql = "CREATE TABLE IF NOT EXISTS advogados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        documento VARCHAR(20) NOT NULL,
        oab VARCHAR(80) NOT NULL,
        endereco VARCHAR(255) NOT NULL,
        cidade VARCHAR(120) NOT NULL,
        uf CHAR(2) NOT NULL,
        fone VARCHAR(30) NOT NULL,
        email VARCHAR(180) NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_advogados_ativo (ativo),
        INDEX idx_advogados_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $done = (bool)$conn->query($sql);
    return $done;
}

function limparDocumento($documento)
{
    return preg_replace('/\D/', '', (string)$documento);
}

function rotuloDocumento($documento)
{
    $numeros = limparDocumento($documento);
    return strlen($numeros) === 14 ? 'CNPJ' : 'CPF';
}

function normalizarUf($uf)
{
    return strtoupper(substr(trim((string)$uf), 0, 2));
}

function obterAdvogadoContratado($conn, $advogadoId = 0)
{
    garantirTabelaAdvogados($conn);

    $advogadoId = intval($advogadoId);
    if ($advogadoId > 0) {
        $sql = "SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $advogadoId);
            $stmt->execute();
            $result = stmt_get_result($stmt);
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
    }

    $result = $conn->query("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados WHERE ativo = 1 ORDER BY nome ASC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    $result = $conn->query("SELECT id, nome, documento, oab, endereco, cidade, uf, fone, email, ativo FROM advogados ORDER BY nome ASC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

function dadosFallbackAdvogado()
{
    return array(
        'nome' => 'ADVOGADO NAO CADASTRADO',
        'documento' => 'NAO INFORMADO',
        'oab' => 'NAO INFORMADA',
        'endereco' => 'NAO INFORMADO',
        'cidade' => 'NAO INFORMADA',
        'uf' => '--',
        'fone' => 'NAO INFORMADO',
        'email' => 'NAO INFORMADO'
    );
}

function prepararDadosAdvogadoDocumento($advogado)
{
    $base = $advogado ?: dadosFallbackAdvogado();

    $documento = trim((string)($base['documento'] ?? ''));
    if ($documento === '') {
        $documento = 'NAO INFORMADO';
    }

    return array(
        'nome' => htmlspecialchars(mb_convert_case((string)($base['nome'] ?? ''), MB_CASE_TITLE, 'UTF-8')),
        'documento' => htmlspecialchars($documento),
        'documento_rotulo' => htmlspecialchars(rotuloDocumento($documento)),
        'oab' => htmlspecialchars((string)($base['oab'] ?? 'NAO INFORMADA')),
        'endereco' => htmlspecialchars(mb_convert_case((string)($base['endereco'] ?? ''), MB_CASE_TITLE, 'UTF-8')),
        'cidade' => htmlspecialchars(mb_convert_case((string)($base['cidade'] ?? ''), MB_CASE_TITLE, 'UTF-8')),
        'uf' => htmlspecialchars(normalizarUf($base['uf'] ?? '--')),
        'fone' => htmlspecialchars((string)($base['fone'] ?? 'NAO INFORMADO')),
        'email' => htmlspecialchars((string)($base['email'] ?? 'NAO INFORMADO'))
    );
}
