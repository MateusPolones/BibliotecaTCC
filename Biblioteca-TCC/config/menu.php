<?php
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function mostrarFlash() {
    if (!empty($_SESSION['flash'])) {
        [$tipo, $texto] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $classe = $tipo === 'ok' ? 'msg-ok' : 'msg-erro';
        echo '<p class="' . $classe . '" role="alert">' . e($texto) . '</p>';
    }
}

$ehAdmin = ($_SESSION['tipo'] ?? '') === 'admin';
$pagina  = basename($_SERVER['SCRIPT_NAME']);
function ativo($arquivo) {
    global $pagina;
    return $pagina === $arquivo ? ' aria-current="page"' : '';
}
?>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<header class="topo">
    <div class="topo-interno">
        <a class="marca" href="<?= BASE ?>/aluno/catalogo.php">Biblioteca Oswaldo Walder</a>
        <nav aria-label="Menu principal">
            <a href="<?= BASE ?>/aluno/catalogo.php"<?= ativo('catalogo.php') ?>>Catálogo</a>
            <?php if ($ehAdmin): ?>
                <a href="<?= BASE ?>/admin/livros.php"<?= ativo('livros.php') ?>>Adicionar livro</a>
                <a href="<?= BASE ?>/admin/atrasos.php"<?= ativo('atrasos.php') ?>>Reservas e atrasos</a>
                <a href="<?= BASE ?>/admin/usuario.php"<?= ativo('usuario.php') ?>>Usuários</a>
            <?php else: ?>
                <a href="<?= BASE ?>/aluno/minhas_reservas.php"<?= ativo('minhas_reservas.php') ?>>Minhas reservas</a>
            <?php endif; ?>
            <span class="quem"><?= e($_SESSION['nome']) ?><?= $ehAdmin ? ' (administrador)' : '' ?></span>
            <a href="<?= BASE ?>/logout.php">Sair</a>
        </nav>
    </div>
</header>
<script src="<?= BASE ?>/js/script.js" defer></script>
