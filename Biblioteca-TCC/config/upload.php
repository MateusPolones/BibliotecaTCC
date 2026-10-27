<?php
function salvarCapa(array $arquivo): array {
    if ($arquivo["error"] !== UPLOAD_ERR_OK) {
        return [null, "Erro no envio da imagem."];
    }
    if ($arquivo["size"] > 2 * 1024 * 1024) {
        return [null, "A imagem deve ter no máximo 2 MB."];
    }

    $permitidos = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    $info = @getimagesize($arquivo["tmp_name"]);
    if (!$info || !isset($permitidos[$info["mime"]])) {
        return [null, "Envie uma imagem JPG, PNG ou WEBP."];
    }

    $pasta = __DIR__ . "/../uploads/capas/";
    if (!is_dir($pasta)) {
        mkdir($pasta, 0777, true);
    }

    $nome = bin2hex(random_bytes(8)) . "." . $permitidos[$info["mime"]];
    if (!move_uploaded_file($arquivo["tmp_name"], $pasta . $nome)) {
        return [null, "Não foi possível salvar a imagem."];
    }
    return [$nome, null];
}

function apagarCapa(?string $nome): void {
    if ($nome) {
        $caminho = __DIR__ . "/../uploads/capas/" . basename($nome);
        if (is_file($caminho)) {
            unlink($caminho);
        }
    }
}
