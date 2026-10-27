<?php
// Abra uma vez no navegador e depois APAGUE este arquivo.
require_once "config/conexao.php";

$nome  = "Administrador";
$email = "admin@biblioteca.com";   // troque se quiser
$senha = "admin123";               // troque por uma senha sua

$stmt = $conexao->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    die("Esse admin já existe. Apague este arquivo.");
}

$hash = password_hash($senha, PASSWORD_DEFAULT);
$stmt = $conexao->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'admin')");
$stmt->bind_param("sss", $nome, $email, $hash);

echo $stmt->execute()
    ? "Admin criado! Agora APAGUE este arquivo (criar_admin.php)."
    : "Erro: " . $stmt->error;
