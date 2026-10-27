<?php
require_once "config/conexao.php";
session_start();

$erro = "";
$sucesso = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome  = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($nome === "" || $email === "" || strlen($senha) < 6) {
        $erro = "Preencha todos os campos. A senha precisa ter ao menos 6 caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "E-mail inválido.";
    } else {
        $stmt = $conexao->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $erro = "Este e-mail já está cadastrado.";
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conexao->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'aluno')");
            $stmt->bind_param("sss", $nome, $email, $hash);
            $stmt->execute();
            $sucesso = "Cadastro feito! Já pode entrar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro - Biblioteca Oswaldo Walder</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="pagina-entrada">
<main class="caixa-entrada">
    <h1>Cadastro de aluno</h1>

    <?php if ($erro): ?><p class="msg-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
    <?php if ($sucesso): ?><p class="msg-ok" role="status"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

    <form method="POST">
        <label for="nome">Nome completo</label>
        <input type="text" id="nome" name="nome" autocomplete="name" required>

        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" autocomplete="email" required>

        <label for="senha">Senha (mínimo 6 caracteres)</label>
        <input type="password" id="senha" name="senha" minlength="6" autocomplete="new-password" required>

        <button type="submit" class="principal">Criar conta</button>
    </form>
    <p><a href="index.php">Já tenho conta</a></p>
</main>
<script src="js/script.js" defer></script>
</body>
</html>
