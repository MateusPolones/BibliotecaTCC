<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
require_once "../config/avaliacoes.php";
exigirLogin();
garantirTabelaAvaliacoes($conexao);

$ehAdmin  = $_SESSION["tipo"] === "admin";
$uid      = (int)$_SESSION["usuario_id"];
$livro_id = (int)($_GET["id"] ?? $_POST["livro_id"] ?? 0);

$stmt = $conexao->prepare(
    "SELECT l.*, g.nome AS genero FROM livros l
     LEFT JOIN generos g ON g.id = l.genero_id WHERE l.id = ?"
);
$stmt->bind_param("i", $livro_id);
$stmt->execute();
$livro = $stmt->get_result()->fetch_assoc();

if (!$livro) {
    $_SESSION['flash'] = ['erro', 'Livro não encontrado.'];
    header("Location: catalogo.php");
    exit;
}

// Qualquer aluno pode avaliar qualquer livro (o admin só lê e modera)
$podeAvaliar = !$ehAdmin;

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "salvar" && !$ehAdmin) {
        $nota = $_POST["nota"] ?? "";
        $coment = str_replace("\r\n", "\n", trim($_POST["comentario"] ?? ""));

        if (!in_array($nota, ["0", "1", "2", "3", "4", "5"], true)) {
            $erro = "Escolha uma nota de 0 a 5 estrelas.";
        } elseif (mb_strlen($coment, "UTF-8") > 500) {
            $erro = "O comentário pode ter no máximo 500 caracteres.";
        } else {
            $notaInt = (int)$nota;
            $comentDb = $coment === "" ? null : $coment;
            $stmt = $conexao->prepare(
                "INSERT INTO avaliacoes (usuario_id, livro_id, nota, comentario) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE nota = VALUES(nota), comentario = VALUES(comentario)"
            );
            $stmt->bind_param("iiis", $uid, $livro_id, $notaInt, $comentDb);
            $stmt->execute();
            $_SESSION['flash'] = ['ok', 'Obrigado! Sua avaliação foi salva.'];
            header("Location: livro.php?id=$livro_id");
            exit;
        }

    } elseif ($acao === "remover") {
        $aid = (int)($_POST["avaliacao_id"] ?? 0);
        $souAdmin = $ehAdmin ? 1 : 0;
        $stmt = $conexao->prepare(
            "DELETE FROM avaliacoes WHERE id = ? AND livro_id = ? AND (usuario_id = ? OR ? = 1)"
        );
        $stmt->bind_param("iiii", $aid, $livro_id, $uid, $souAdmin);
        $stmt->execute();
        $_SESSION['flash'] = $stmt->affected_rows > 0
            ? ['ok', 'Avaliação removida.']
            : ['erro', 'Não foi possível remover a avaliação.'];
        header("Location: livro.php?id=$livro_id");
        exit;
    }
}

$stmt = $conexao->prepare(
    "SELECT a.id, a.usuario_id, a.nota, a.comentario, a.atualizado_em, u.nome
     FROM avaliacoes a JOIN usuarios u ON u.id = a.usuario_id
     WHERE a.livro_id = ? ORDER BY a.atualizado_em DESC"
);
$stmt->bind_param("i", $livro_id);
$stmt->execute();
$avaliacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = count($avaliacoes);
$media = $total ? array_sum(array_column($avaliacoes, "nota")) / $total : 0;

$minha = null;
foreach ($avaliacoes as $a) {
    if ((int)$a["usuario_id"] === $uid) { $minha = $a; break; }
}

// Se houve erro ao salvar, mantém o que a pessoa digitou
$notaAtual  = $_POST["nota"] ?? ($minha !== null ? (string)$minha["nota"] : "");
$comentAtual = $_POST["comentario"] ?? ($minha["comentario"] ?? "");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($livro['titulo']) ?> - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../config/menu.php"; ?>
<main id="conteudo">
    <p><a href="catalogo.php">← Voltar ao catálogo</a></p>
    <?php mostrarFlash(); ?>

    <div class="ficha-livro">
        <?php if ($livro['capa']): ?>
            <img src="../uploads/capas/<?= e($livro['capa']) ?>" alt="Capa do livro <?= e($livro['titulo']) ?>">
        <?php else: ?>
            <div class="sem-capa" role="img" aria-label="Livro sem capa"><span><?= e($livro['titulo']) ?></span></div>
        <?php endif; ?>
        <div>
            <h1><?= e($livro['titulo']) ?></h1>
            <p class="autor"><?= e($livro['autor']) ?></p>
            <p class="genero"><?= e($livro['genero'] ?? 'Gênero não informado') ?></p>
            <p class="estoque"><?= (int)$livro['quantidade_disponivel'] ?> disponível(is)</p>
            <h2>Sinopse</h2>
            <p><?= nl2br(e($livro['sinopse'] ?: 'Sem sinopse.')) ?></p>
            <?php if ($livro['descricao']): ?><p><?= nl2br(e($livro['descricao'])) ?></p><?php endif; ?>
        </div>
    </div>

    <h2 id="avaliacoes">Avaliações</h2>
    <?php if ($total): ?>
        <p class="nota-media">
            <span aria-hidden="true"><?= estrelasTexto($media) ?></span>
            <strong><?= number_format($media, 1, ',', '') ?> de 5</strong>
            (<?= $total ?> avaliação(ões))
        </p>
    <?php else: ?>
        <p>Este livro ainda não tem avaliações.</p>
    <?php endif; ?>

    <?php if ($ehAdmin): ?>
        <?php /* admin só lê e modera */ ?>
    <?php elseif ($podeAvaliar): ?>
        <h3 id="avaliar"><?= $minha ? 'Editar minha avaliação' : 'Avaliar este livro' ?></h3>
        <?php if ($erro): ?><p class="msg-erro" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="POST" class="formulario">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="livro_id" value="<?= $livro_id ?>">

            <fieldset class="estrelas">
                <legend>Sua nota (0 a 5 estrelas)</legend>
                <?php for ($i = 0; $i <= 5; $i++): ?>
                    <input type="radio" id="nota<?= $i ?>" name="nota" value="<?= $i ?>"
                           <?= $notaAtual === (string)$i ? 'checked' : '' ?> required>
                    <label for="nota<?= $i ?>"><?= $i ?> <span aria-hidden="true">★</span><span class="so-leitor"> estrela(s)</span></label>
                <?php endfor; ?>
            </fieldset>

            <label for="comentario">Comentário (opcional)</label>
            <textarea id="comentario" name="comentario" rows="4" maxlength="500" aria-describedby="contador"><?= e($comentAtual) ?></textarea>
            <p id="contador" class="contador"></p>

            <div class="linha-botoes">
                <button type="submit" class="principal">Salvar avaliação</button>
            </div>
        </form>
        <script>
        (function () {
            var t = document.getElementById('comentario'), c = document.getElementById('contador');
            if (!t || !c) return;
            function atualizar() { c.textContent = t.value.length + ' de 500 caracteres'; }
            t.addEventListener('input', atualizar);
            atualizar();
        })();
        </script>
    <?php endif; ?>

    <?php if ($total): ?>
    <ul class="lista-avaliacoes">
        <?php foreach ($avaliacoes as $a): ?>
            <li>
                <p class="cabeca-aval">
                    <strong><?= e(explode(' ', trim($a['nome']))[0]) ?></strong>
                    <span aria-hidden="true"><?= estrelasTexto($a['nota']) ?></span>
                    <span class="so-leitor"><?= (int)$a['nota'] ?> de 5 estrelas</span>
                    <small><?= date("d/m/Y", strtotime($a['atualizado_em'])) ?></small>
                </p>
                <?php if ($a['comentario']): ?><p><?= nl2br(e($a['comentario'])) ?></p><?php endif; ?>

                <?php if ($ehAdmin || (int)$a['usuario_id'] === $uid): ?>
                    <form method="POST" class="em-linha" onsubmit="return confirm('Remover esta avaliação?');">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="livro_id" value="<?= $livro_id ?>">
                        <input type="hidden" name="avaliacao_id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="perigo"><?= $ehAdmin && (int)$a['usuario_id'] !== $uid ? 'Remover avaliação' : 'Excluir minha avaliação' ?></button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</main>
</body>
</html>
