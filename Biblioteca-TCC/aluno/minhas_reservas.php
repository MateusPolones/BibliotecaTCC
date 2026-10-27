<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
exigirAluno();

$uid = $_SESSION["usuario_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rid = (int)($_POST["reserva_id"] ?? 0);

    $conexao->begin_transaction();
    $stmt = $conexao->prepare(
        "UPDATE reservas SET status='cancelado'
         WHERE id = ? AND usuario_id = ? AND status = 'reservado'"
    );
    $stmt->bind_param("ii", $rid, $uid);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        $stmt = $conexao->prepare(
            "UPDATE livros SET quantidade_disponivel = quantidade_disponivel + 1
             WHERE id = (SELECT livro_id FROM reservas WHERE id = ?)"
        );
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $conexao->commit();
        $_SESSION['flash'] = ['ok', 'Reserva cancelada.'];
    } else {
        $conexao->rollback();
        $_SESSION['flash'] = ['erro', 'Não foi possível cancelar esta reserva.'];
    }
    header("Location: minhas_reservas.php");
    exit;
}

$stmt = $conexao->prepare(
    "SELECT r.id, r.livro_id, r.status, r.data_reserva, r.data_prevista_devolucao,
            (r.status IN ('reservado','retirado','atrasado') AND r.data_prevista_devolucao < CURDATE()) AS vencida,
            l.titulo, l.autor
     FROM reservas r JOIN livros l ON l.id = r.livro_id
     WHERE r.usuario_id = ? ORDER BY r.id DESC"
);
$stmt->bind_param("i", $uid);
$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$rotulos = [
    'reservado' => 'Reservado (aguardando retirada)',
    'retirado'  => 'Retirado',
    'atrasado'  => 'Atrasado',
    'devolvido' => 'Devolvido',
    'cancelado' => 'Cancelado',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas reservas - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../config/menu.php"; ?>
<main id="conteudo">
    <h1>Minhas reservas</h1>
    <?php mostrarFlash(); ?>

    <?php if (!$reservas): ?>
        <p>Você ainda não reservou nenhum livro. <a href="catalogo.php">Ver o catálogo</a></p>
    <?php else: ?>
    <div class="tabela-rolavel">
    <table>
        <caption>Histórico de reservas</caption>
        <thead>
            <tr>
                <th scope="col">Livro</th>
                <th scope="col">Reservado em</th>
                <th scope="col">Devolver até</th>
                <th scope="col">Situação</th>
                <th scope="col">Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservas as $r): ?>
            <tr>
                <td><?= e($r['titulo']) ?><br><small><?= e($r['autor']) ?></small></td>
                <td><?= date("d/m/Y", strtotime($r['data_reserva'])) ?></td>
                <td><?= date("d/m/Y", strtotime($r['data_prevista_devolucao'])) ?></td>
                <td><?= $r['vencida'] ? '<strong>Atrasado: devolva o quanto antes</strong>' : $rotulos[$r['status']] ?></td>
                <td>
                    <?php if ($r['status'] !== 'cancelado'): ?>
                        <a class="botao" href="livro.php?id=<?= (int)$r['livro_id'] ?>#avaliar">Avaliar<span class="so-leitor"> <?= e($r['titulo']) ?></span></a>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'reservado' && !$r['vencida']): ?>
                        <form method="POST" class="em-linha" onsubmit="return confirm('Cancelar esta reserva?');">
                            <input type="hidden" name="reserva_id" value="<?= $r['id'] ?>">
                            <button type="submit">Cancelar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
