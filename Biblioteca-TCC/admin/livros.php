<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
require_once "../config/upload.php";
exigirAdmin();

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo    = trim($_POST["titulo"] ?? "");
    $autor     = trim($_POST["autor"] ?? "");
    $genero_id = (int)($_POST["genero_id"] ?? 0) ?: null;
    $sinopse   = trim($_POST["sinopse"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $qtd       = (int)($_POST["quantidade"] ?? 0);
    $capa      = null;

    if ($titulo === "" || $autor === "" || $qtd < 1) {
        $erro = "Preencha título, autor e uma quantidade de pelo menos 1.";
    } elseif (!empty($_FILES["capa"]["name"])) {
        [$capa, $erroCapa] = salvarCapa($_FILES["capa"]);
        if ($erroCapa) {
            $erro = $erroCapa;
        }
    }

    if ($erro === "") {
        $stmt = $conexao->prepare(
            "INSERT INTO livros (titulo, autor, genero_id, sinopse, descricao, capa, quantidade_total, quantidade_disponivel)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssisssii", $titulo, $autor, $genero_id, $sinopse, $descricao, $capa, $qtd, $qtd);
        $stmt->execute();
        $_SESSION['flash'] = ['ok', 'Livro cadastrado com sucesso!'];
        header("Location: ../aluno/catalogo.php");
        exit;
    }
}

$generos = $conexao->query("SELECT id, nome FROM generos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adicionar livro - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../config/menu.php"; ?>
<main id="conteudo">
    <h1>Adicionar livro</h1>
    <?php if ($erro): ?><p class="msg-erro" role="alert"><?= e($erro) ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="formulario">
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" value="<?= e($_POST['titulo'] ?? '') ?>" required>

        <label for="autor">Autor</label>
        <input type="text" id="autor" name="autor" value="<?= e($_POST['autor'] ?? '') ?>" required>

        <label for="genero_id">Gênero</label>
        <select id="genero_id" name="genero_id">
            <option value="">Sem gênero</option>
            <?php foreach ($generos as $g): ?>
                <option value="<?= $g['id'] ?>" <?= ((string)($_POST['genero_id'] ?? '') === (string)$g['id']) ? 'selected' : '' ?>><?= e($g['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="sinopse">Sinopse</label>
        <textarea id="sinopse" name="sinopse" rows="4"><?= e($_POST['sinopse'] ?? '') ?></textarea>

        <label for="descricao">Descrição (edição, páginas, ano etc.)</label>
        <textarea id="descricao" name="descricao" rows="3"><?= e($_POST['descricao'] ?? '') ?></textarea>

        <label for="quantidade">Quantidade de exemplares</label>
        <input type="number" id="quantidade" name="quantidade" min="1" value="<?= (int)($_POST['quantidade'] ?? 1) ?>" required>

        <label for="capa">Capa (JPG, PNG ou WEBP, até 2 MB)</label>
        <input type="file" id="capa" name="capa" accept="image/jpeg,image/png,image/webp">

        <div class="linha-botoes">
            <button type="submit" class="principal">Salvar livro</button>
            <a class="botao" href="../aluno/catalogo.php">Cancelar</a>
        </div>
    </form>
</main>
</body>
</html>
