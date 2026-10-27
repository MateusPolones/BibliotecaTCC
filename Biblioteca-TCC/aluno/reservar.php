<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
require_once "../config/prazo.php";
exigirAluno();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: catalogo.php");
    exit;
}

$livro_id = (int)($_POST["livro_id"] ?? 0);
$uid = $_SESSION["usuario_id"];

function voltar($tipo, $texto, $destino = "catalogo.php") {
    $_SESSION['flash'] = [$tipo, $texto];
    header("Location: $destino");
    exit;
}

$stmt = $conexao->prepare("SELECT (bloqueado_ate > CURDATE()) AS bloqueado FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()["bloqueado"]) {
    voltar('erro', 'Você está bloqueado e não pode reservar livros no momento.');
}

$stmt = $conexao->prepare(
    "SELECT id FROM reservas
     WHERE usuario_id = ? AND livro_id = ? AND status IN ('reservado','retirado','atrasado')"
);
$stmt->bind_param("ii", $uid, $livro_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    voltar('erro', 'Você já tem uma reserva ativa deste livro.');
}

// Calcula o prazo antes da transação (a função pode criar a tabela de configurações)
garantirTabelaConfig($conexao);
$prazo = prazoPadraoData($conexao);

$conexao->begin_transaction();

$stmt = $conexao->prepare(
    "UPDATE livros SET quantidade_disponivel = quantidade_disponivel - 1
     WHERE id = ? AND quantidade_disponivel > 0"
);
$stmt->bind_param("i", $livro_id);
$stmt->execute();

if ($stmt->affected_rows === 1) {
    $stmt = $conexao->prepare(
        "INSERT INTO reservas (usuario_id, livro_id, data_reserva, data_prevista_devolucao)
         VALUES (?, ?, CURDATE(), ?)"
    );
    $stmt->bind_param("iis", $uid, $livro_id, $prazo);
    $stmt->execute();
    $conexao->commit();
    voltar('ok', 'Livro reservado! Retire-o na biblioteca dentro do prazo.', "minhas_reservas.php");
} else {
    $conexao->rollback();
    voltar('erro', 'Este livro não está mais disponível.');
}
