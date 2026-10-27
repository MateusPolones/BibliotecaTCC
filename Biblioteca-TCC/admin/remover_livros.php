<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
require_once "../config/upload.php";
exigirAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../aluno/catalogo.php");
    exit;
}

$id = (int)($_POST["id"] ?? 0);

$stmt = $conexao->prepare(
    "SELECT COUNT(*) AS total FROM reservas
     WHERE livro_id = ? AND status IN ('reservado','retirado','atrasado')"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$ativas = $stmt->get_result()->fetch_assoc()["total"];

if ($ativas > 0) {
    $_SESSION['flash'] = ['erro', 'Não é possível remover: há reservas ativas deste livro.'];
    header("Location: ../aluno/catalogo.php");
    exit;
}

$stmt = $conexao->prepare("SELECT capa FROM livros WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$livro = $stmt->get_result()->fetch_assoc();

if ($livro) {
    $stmt = $conexao->prepare("DELETE FROM livros WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    apagarCapa($livro["capa"]);
    $_SESSION['flash'] = ['ok', 'Livro removido.'];
}

header("Location: ../aluno/catalogo.php");
exit;
