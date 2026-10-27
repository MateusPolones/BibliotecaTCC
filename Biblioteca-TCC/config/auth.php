<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Descobre sozinho o caminho do projeto (ex.: /Biblioteca-TCC ou vazio se estiver na raiz do site)
if (!defined('BASE')) {
    $raiz = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT']));
    $proj = str_replace('\\', '/', (string)realpath(dirname(__DIR__)));
    if ($raiz !== '' && strpos($proj, $raiz) === 0) {
        $base = substr($proj, strlen($raiz));
    } else {
        $base = preg_replace('#/(admin|aluno)$#', '', dirname($_SERVER['SCRIPT_NAME']));
    }
    define('BASE', rtrim(str_replace('\\', '/', $base), '/'));
}

function exigirLogin() {
    global $conexao;

    if (!isset($_SESSION['usuario_id'])) {
        header("Location: " . BASE . "/index.php");
        exit;
    }

    // Confere no banco o tipo atual da conta (se o admin foi promovido ou rebaixado, vale na hora)
    if (isset($conexao)) {
        $stmt = $conexao->prepare("SELECT tipo FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $linha = $stmt->get_result()->fetch_assoc();

        if (!$linha) {
            session_unset();
            session_destroy();
            header("Location: " . BASE . "/index.php");
            exit;
        }
        $_SESSION['tipo'] = $linha['tipo'];
    }
}

function exigirAdmin() {
    exigirLogin();
    if ($_SESSION['tipo'] !== 'admin') {
        header("Location: " . BASE . "/aluno/catalogo.php");
        exit;
    }
}

function exigirAluno() {
    exigirLogin();
    if ($_SESSION['tipo'] !== 'aluno') {
        header("Location: " . BASE . "/aluno/catalogo.php");
        exit;
    }
}
