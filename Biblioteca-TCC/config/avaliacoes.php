<?php
// Avaliações dos livros (nota de 0 a 5 estrelas + comentário de até 500 caracteres).
// A tabela é criada sozinha na primeira vez, não precisa rodar SQL.

function garantirTabelaAvaliacoes($conexao) {
    $conexao->query(
        "CREATE TABLE IF NOT EXISTS avaliacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            livro_id INT NOT NULL,
            nota TINYINT UNSIGNED NOT NULL,
            comentario VARCHAR(500) NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY um_por_aluno (usuario_id, livro_id),
            CONSTRAINT fk_av_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_av_livro FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

// Ex.: 3.6 -> ★★★★☆
function estrelasTexto($media) {
    $n = max(0, min(5, (int)round((float)$media)));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}
