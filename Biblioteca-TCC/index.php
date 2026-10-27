<?php
require_once "config/conexao.php";
session_start();

if (isset($_SESSION['usuario_id'])) {
    header("Location: aluno/catalogo.php");
    exit;
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    $stmt = $conexao->prepare("SELECT id, nome, senha, tipo FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if ($usuario && password_verify($senha, $usuario["senha"])) {
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["nome"]       = $usuario["nome"];
        $_SESSION["tipo"]       = $usuario["tipo"];
        header("Location: aluno/catalogo.php");
        exit;
    }
    $erro = "E-mail ou senha incorretos.";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar - Biblioteca Oswaldo Walder</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="pagina-entrada">
<main class="caixa-entrada">
    <h1>Biblioteca Oswaldo Walder</h1>
    <p>Entre para consultar o acervo e reservar livros.</p>

    <?php if ($erro): ?><p class="msg-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

    <form method="POST">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" autocomplete="username" required>

        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required>

        <button type="submit" class="principal">Entrar</button>
    </form>
    <p>Ainda não tem conta? <a href="registro.php">Cadastre-se como aluno</a></p>
</main>
<script src="js/script.js" defer></script>
</body>
</html>
