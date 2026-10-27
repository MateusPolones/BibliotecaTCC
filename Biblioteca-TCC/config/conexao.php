<?php
// No seu computador (XAMPP) use os valores abaixo.
// Ao publicar em uma hospedagem, troque pelos dados do banco que ela fornecer.
$db_host  = "localhost";
$db_user  = "root";
$db_senha = "";
$db_nome  = "biblioteca_oswaldo_walder";

$conexao = new mysqli($db_host, $db_user, $db_senha, $db_nome);

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}

$conexao->set_charset("utf8mb4");
