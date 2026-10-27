<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
require_once "../config/prazo.php";
require_once "../config/avaliacoes.php";
exigirLogin();
garantirTabelaConfig($conexao);
garantirTabelaAvaliacoes($conexao);
$prazoNovo = prazoPadraoData($conexao);

$ehAdmin = $_SESSION["tipo"] === "admin";

$busca  = trim($_GET["q"] ?? "");
$genero = (int)($_GET["genero"] ?? 0);

$sql = "SELECT l.*, g.nome AS genero,
        (SELECT AVG(a.nota) FROM avaliacoes a WHERE a.livro_id = l.id) AS media,
        (SELECT COUNT(*) FROM avaliacoes a WHERE a.livro_id = l.id) AS total_aval
        FROM livros l
        LEFT JOIN generos g ON g.id = l.genero_id WHERE 1=1";
$tipos = "";
$params = [];

if ($busca !== "") {
    $sql .= " AND (l.titulo LIKE ? OR l.autor LIKE ?)";
    $like = "%" . $busca . "%";
    $tipos .= "ss";
    array_push($params, $like, $like);
}
if ($genero > 0) {
    $sql .= " AND l.genero_id = ?";
    $tipos .= "i";
    $params[] = $genero;
}
$sql .= " ORDER BY l.titulo";

$stmt = $conexao->prepare($sql);
if ($params) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$livros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$generos = $conexao->query("SELECT id, nome FROM generos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

$bloqueado = false;
$bloqueado_ate = null;
if (!$ehAdmin) {
    $stmt = $conexao->prepare("SELECT bloqueado_ate, (bloqueado_ate > CURDATE()) AS bloqueado FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION["usuario_id"]);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $bloqueado = (bool)$usuario["bloqueado"];
    $bloqueado_ate = $usuario["bloqueado_ate"];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catálogo - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../config/menu.php"; ?>
<main id="conteudo">
    <h1>Catálogo de livros</h1>
    <?php mostrarFlash(); ?>

    <?php if ($bloqueado): ?>
        <p class="msg-erro" role="alert">
            Suas reservas estão bloqueadas até <?= date("d/m/Y", strtotime($bloqueado_ate)) ?> por atraso na devolução.
        </p>
    <?php endif; ?>

    <?php if (!$ehAdmin && !$bloqueado): ?>
        <p class="aviso">Livros reservados hoje devem ser devolvidos até <strong><?= date("d/m/Y", strtotime($prazoNovo)) ?></strong>.</p>
    <?php endif; ?>

    <form method="GET" role="search" aria-label="Pesquisar livros" class="busca">
        <div>
            <label for="q">Título ou autor</label>
            <input type="search" id="q" name="q" value="<?= e($busca) ?>">
        </div>
        <div>
            <label for="genero">Gênero</label>
            <select id="genero" name="genero">
                <option value="0">Todos</option>
                <?php foreach ($generos as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= $genero === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="principal">Buscar</button>
        <a href="catalogo.php">Limpar filtros</a>
        <?php if ($ehAdmin): ?><a class="botao" href="../admin/livros.php">Adicionar livro</a><?php endif; ?>
    </form>

    <p aria-live="polite" class="contagem"><?= count($livros) ?> livro(s) encontrado(s).</p>

    <?php if (!$livros): ?>
        <p>Nenhum livro por aqui.
        <?php if ($ehAdmin): ?><a href="../admin/livros.php">Cadastre o primeiro livro.</a><?php endif; ?></p>
    <?php endif; ?>

    <div class="grade-livros">
    <?php foreach ($livros as $l): ?>
        <article class="cartao-livro">
            <?php if ($l['capa']): ?>
                <img src="../uploads/capas/<?= e($l['capa']) ?>" alt="Capa do livro <?= e($l['titulo']) ?>">
            <?php else: ?>
                <div class="sem-capa" role="img" aria-label="Livro sem capa"><span><?= e($l['titulo']) ?></span></div>
            <?php endif; ?>

            <div class="corpo-cartao">
                <h2><?= e($l['titulo']) ?></h2>
                <p class="autor"><?= e($l['autor']) ?></p>
                <p class="genero"><?= e($l['genero'] ?? 'Gênero não informado') ?></p>
                <p class="nota-media">
                    <?php if ($l['total_aval'] > 0): ?>
                        <span aria-hidden="true"><?= estrelasTexto($l['media']) ?></span>
                        <?= number_format((float)$l['media'], 1, ',', '') ?> de 5 (<?= (int)$l['total_aval'] ?>)
                    <?php else: ?>
                        Sem avaliações
                    <?php endif; ?>
                    · <a href="livro.php?id=<?= (int)$l['id'] ?>#avaliacoes">Ver<span class="so-leitor"> avaliações de <?= e($l['titulo']) ?></span></a>
                </p>
                <p class="estoque <?= $l['quantidade_disponivel'] < 1 ? 'zerado' : '' ?>">
                    <?= (int)$l['quantidade_disponivel'] ?> disponível(is)<?= $ehAdmin ? ' de ' . (int)$l['quantidade_total'] : '' ?>
                </p>

                <details>
                    <summary>Sinopse e detalhes</summary>
                    <p><?= nl2br(e($l['sinopse'] ?: 'Sem sinopse.')) ?></p>
                    <?php if ($l['descricao']): ?><p><?= nl2br(e($l['descricao'])) ?></p><?php endif; ?>
                </details>

                <?php if ($ehAdmin): ?>
                    <div class="acoes-admin">
                        <a class="botao" href="../admin/editar_livros.php?id=<?= $l['id'] ?>">Editar<span class="so-leitor"> <?= e($l['titulo']) ?></span></a>
                        <form method="POST" action="../admin/remover_livros.php" class="em-linha"
                              onsubmit="return confirm('Remover o livro &quot;<?= e($l['titulo']) ?>&quot;?');">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="perigo">Remover<span class="so-leitor"> <?= e($l['titulo']) ?></span></button>
                        </form>
                    </div>
                <?php elseif ($bloqueado): ?>
                    <button type="button" disabled>Reserva bloqueada</button>
                <?php elseif ($l['quantidade_disponivel'] < 1): ?>
                    <button type="button" disabled>Indisponível</button>
                <?php else: ?>
                    <form method="POST" action="reservar.php">
                        <input type="hidden" name="livro_id" value="<?= $l['id'] ?>">
                        <button type="submit" class="principal">Reservar<span class="so-leitor"> <?= e($l['titulo']) ?></span></button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</main>
</body>
</html>
