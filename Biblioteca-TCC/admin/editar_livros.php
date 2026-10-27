<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
require_once "../config/upload.php";
exigirAdmin();

$id = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

$stmt = $conexao->prepare("SELECT * FROM livros WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$livro = $stmt->get_result()->fetch_assoc();

if (!$livro) {
    $_SESSION['flash'] = ['erro', 'Livro não encontrado.'];
    header("Location: ../aluno/catalogo.php");
    exit;
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo    = trim($_POST["titulo"] ?? "");
    $autor     = trim($_POST["autor"] ?? "");
    $genero_id = (int)($_POST["genero_id"] ?? 0) ?: null;
    $sinopse   = trim($_POST["sinopse"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $qtd       = (int)($_POST["quantidade"] ?? 0);

    $capaNova = $livro["capa"];
    $capaAntiga = null;
    $novaDisp = $livro["quantidade_disponivel"] + ($qtd - $livro["quantidade_total"]);

    if ($titulo === "" || $autor === "" || $qtd < 1) {
        $erro = "Preencha título, autor e uma quantidade de pelo menos 1.";
    } elseif ($novaDisp < 0) {
        $emprestados = $livro["quantidade_total"] - $livro["quantidade_disponivel"];
        $erro = "Há $emprestados exemplar(es) reservado(s) ou emprestado(s). A quantidade total não pode ser menor que isso.";
    } elseif (!empty($_FILES["capa"]["name"])) {
        [$nomeCapa, $erroCapa] = salvarCapa($_FILES["capa"]);
        if ($erroCapa) {
            $erro = $erroCapa;
        } else {
            $capaAntiga = $livro["capa"];
            $capaNova = $nomeCapa;
        }
    }

    if ($erro === "") {
        $stmt = $conexao->prepare(
            "UPDATE livros SET titulo=?, autor=?, genero_id=?, sinopse=?, descricao=?, capa=?,
             quantidade_total=?, quantidade_disponivel=? WHERE id=?"
        );
        $stmt->bind_param("ssisssiii", $titulo, $autor, $genero_id, $sinopse, $descricao, $capaNova, $qtd, $novaDisp, $id);
        $stmt->execute();
        apagarCapa($capaAntiga);

        $_SESSION['flash'] = ['ok', 'Livro atualizado!'];
        header("Location: ../aluno/catalogo.php");
        exit;
    }
}

$v = fn($campo) => $_POST[$campo] ?? $livro[$campo];
$generoAtual = $_POST["genero_id"] ?? $livro["genero_id"];
$qtdAtual = $_POST["quantidade"] ?? $livro["quantidade_total"];
$generos = $conexao->query("SELECT id, nome FROM generos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar livro - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../config/menu.php"; ?>
<main id="conteudo">
    <h1>Editar livro</h1>
    <?php if ($erro): ?><p class="msg-erro" role="alert"><?= e($erro) ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="id" value="<?= $livro['id'] ?>">

        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" value="<?= e($v('titulo')) ?>" required>

        <label for="autor">Autor</label>
        <input type="text" id="autor" name="autor" value="<?= e($v('autor')) ?>" required>

        <label for="genero_id">Gênero</label>
        <select id="genero_id" name="genero_id">
            <option value="">Sem gênero</option>
            <?php foreach ($generos as $g): ?>
                <option value="<?= $g['id'] ?>" <?= ((string)$generoAtual === (string)$g['id']) ? 'selected' : '' ?>><?= e($g['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="sinopse">Sinopse</label>
        <textarea id="sinopse" name="sinopse" rows="4"><?= e($v('sinopse')) ?></textarea>

        <label for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" rows="3"><?= e($v('descricao')) ?></textarea>

        <label for="quantidade">Quantidade total de exemplares</label>
        <input type="number" id="quantidade" name="quantidade" min="1" value="<?= (int)$qtdAtual ?>" required>

        <?php if ($livro['capa']): ?>
            <p>Capa atual:</p>
            <img src="../uploads/capas/<?= e($livro['capa']) ?>" alt="Capa atual do livro <?= e($livro['titulo']) ?>" width="120">
        <?php endif; ?>

        <label for="capa">Trocar capa (deixe vazio para manter a atual)</label>
        <input type="file" id="capa" name="capa" accept="image/jpeg,image/png,image/webp">

        <div class="linha-botoes">
            <button type="submit" class="principal">Salvar alterações</button>
            <a class="botao" href="../aluno/catalogo.php">Cancelar</a>
        </div>
    </form>
</main>
</body>
</html>
