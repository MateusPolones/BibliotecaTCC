<?php
// Funções do prazo de devolução definido pelo admin (calendário).
// A tabela "configuracoes" é criada sozinha na primeira vez, não precisa rodar SQL.

function garantirTabelaConfig($conexao) {
    $conexao->query(
        "CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(60) PRIMARY KEY,
            valor VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function lerConfig($conexao, $chave) {
    $stmt = $conexao->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    return $linha ? $linha["valor"] : null;
}

function gravarConfig($conexao, $chave, $valor) {
    $stmt = $conexao->prepare(
        "INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
    );
    $stmt->bind_param("ss", $chave, $valor);
    $stmt->execute();
}

function apagarConfig($conexao, $chave) {
    $stmt = $conexao->prepare("DELETE FROM configuracoes WHERE chave = ?");
    $stmt->bind_param("s", $chave);
    $stmt->execute();
}

// [hoje, limite máximo de 1 ano] no horário do banco
function intervaloPrazo($conexao) {
    $r = $conexao->query("SELECT CURDATE() AS hoje, DATE_ADD(CURDATE(), INTERVAL 365 DAY) AS maximo")->fetch_assoc();
    return [$r["hoje"], $r["maximo"]];
}

function dataValida($conexao, $data) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$data);
    if (!$d || $d->format('Y-m-d') !== $data) {
        return false;
    }
    [$hoje, $maximo] = intervaloPrazo($conexao);
    return $data >= $hoje && $data <= $maximo;
}

// Data escolhida pelo admin para novas reservas (ou null se não há uma válida)
function prazoPadraoDefinido($conexao) {
    $data = lerConfig($conexao, 'prazo_padrao_data');
    if ($data !== null && dataValida($conexao, $data)) {
        return $data;
    }
    return null;
}

// Data de devolução que uma reserva nova vai receber
function prazoPadraoData($conexao) {
    $definido = prazoPadraoDefinido($conexao);
    if ($definido !== null) {
        return $definido;
    }
    return $conexao->query("SELECT DATE_ADD(CURDATE(), INTERVAL 7 DAY) AS d")->fetch_assoc()["d"];
}
