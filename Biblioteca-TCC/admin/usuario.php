<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
exigirAdmin();

$meuId = (int)$_SESSION["usuario_id"];

function totalAdmins($conexao) {
    return (int)$conexao->query("SELECT COUNT(*) AS t FROM usuarios WHERE tipo = 'admin'")->fetch_assoc()["t"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";
    $uid  = (int)($_POST["usuario_id"] ?? 0);

    if ($acao === "desbloquear") {
        $stmt = $conexao->prepare("UPDATE usuarios SET bloqueado_ate = NULL WHERE id = ? AND tipo = 'aluno'");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $_SESSION['flash'] = ['ok', 'Bloqueio removido.'];

    } elseif ($acao === "promover") {
        $stmt = $conexao->prepare("SELECT id, nome, tipo FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $alvo = $stmt->get_result()->fetch_assoc();

        if (!$alvo) {
            $_SESSION['flash'] = ['erro', "Nenhuma conta com o id $uid."];
        } elseif ($alvo["tipo"] === "admin") {
            $_SESSION['flash'] = ['erro', $alvo["nome"] . " já é administrador."];
        } else {
            $stmt = $conexao->prepare("UPDATE usuarios SET tipo = 'admin', bloqueado_ate = NULL WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $_SESSION['flash'] = ['ok', $alvo["nome"] . " agora é administrador."];
        }

    } elseif ($acao === "rebaixar") {
        $stmt = $conexao->prepare("SELECT id, nome, tipo FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $alvo = $stmt->get_result()->fetch_assoc();

        if (!$alvo || $alvo["tipo"] !== "admin") {
            $_SESSION['flash'] = ['erro', 'Administrador não encontrado.'];
        } elseif (totalAdmins($conexao) <= 1) {
            $_SESSION['flash'] = ['erro', 'Não é possível: o site precisa ter pelo menos um administrador.'];
        } else {
            $stmt = $conexao->prepare("UPDATE usuarios SET tipo = 'aluno' WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            if ($uid === $meuId) {
                $_SESSION['tipo'] = 'aluno';
                $_SESSION['flash'] = ['ok', 'Você deixou de ser administrador.'];
                header("Location: ../aluno/catalogo.php");
                exit;
            }
            $_SESSION['flash'] = ['ok', $alvo["nome"] . " deixou de ser administrador."];
        }
    }

    header("Location: usuario.php");
    exit;
}

$admins = $conexao->query(
    "SELECT id, nome, email, criado_em FROM usuarios WHERE tipo = 'admin' ORDER BY nome"
)->fetch_all(MYSQLI_ASSOC);

$alunos = $conexao->query(
    "SELECT u.id, u.nome, u.email, u.criado_em, u.bloqueado_ate,
            (u.bloqueado_ate > CURDATE()) AS bloqueado,
            (SELECT COUNT(*) FROM reservas r WHERE r.usuario_id = u.id
               AND r.status IN ('reservado','retirado','atrasado')) AS ativas
     FROM usuarios u WHERE u.tipo = 'aluno' ORDER BY u.nome"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuários - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../config/menu.php"; ?>
<main id="conteudo">
    <h1>Usuários</h1>
    <?php mostrarFlash(); ?>

    <h2>Administradores</h2>
    <div class="tabela-rolavel">
    <table>
        <caption>Contas com acesso de administrador</caption>
        <thead>
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nome</th>
                <th scope="col">E-mail</th>
                <th scope="col">Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $a): ?>
            <tr>
                <td><?= (int)$a['id'] ?></td>
                <td><?= e($a['nome']) ?><?= (int)$a['id'] === $meuId ? ' <strong>(você)</strong>' : '' ?></td>
                <td><?= e($a['email']) ?></td>
                <td>
                    <?php if (count($admins) > 1): ?>
                        <form method="POST" class="em-linha"
                              onsubmit="return confirm('Remover a permissão de administrador de <?= e($a['nome']) ?>?');">
                            <input type="hidden" name="acao" value="rebaixar">
                            <input type="hidden" name="usuario_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="perigo">Remover permissão<span class="so-leitor"> de <?= e($a['nome']) ?></span></button>
                        </form>
                    <?php else: ?>
                        Único administrador
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <form method="POST" class="formulario" style="margin-top:1rem">
        <input type="hidden" name="acao" value="promover">
        <label for="id_promover">Tornar administrador pelo id da conta</label>
        <input type="number" id="id_promover" name="usuario_id" min="1" required>
        <div class="linha-botoes">
            <button type="submit" class="principal"
                    onclick="return confirm('Dar acesso de administrador a esta conta?');">Tornar administrador</button>
        </div>
    </form>

    <h2>Alunos cadastrados</h2>
    <?php if (!$alunos): ?>
        <p>Nenhum aluno se cadastrou ainda.</p>
    <?php else: ?>
    <div class="tabela-rolavel">
    <table>
        <caption>Lista de alunos</caption>
        <thead>
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nome</th>
                <th scope="col">E-mail</th>
                <th scope="col">Cadastro</th>
                <th scope="col">Reservas ativas</th>
                <th scope="col">Situação</th>
                <th scope="col">Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($alunos as $a): ?>
            <tr>
                <td><?= (int)$a['id'] ?></td>
                <td><?= e($a['nome']) ?></td>
                <td><?= e($a['email']) ?></td>
                <td><?= date("d/m/Y", strtotime($a['criado_em'])) ?></td>
                <td><?= (int)$a['ativas'] ?></td>
                <td>
                    <?php if ($a['bloqueado']): ?>
                        <strong>Bloqueado até <?= date("d/m/Y", strtotime($a['bloqueado_ate'])) ?></strong>
                        <form method="POST" class="em-linha">
                            <input type="hidden" name="acao" value="desbloquear">
                            <input type="hidden" name="usuario_id" value="<?= $a['id'] ?>">
                            <button type="submit">Remover bloqueio</button>
                        </form>
                    <?php else: ?>
                        Liberado
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" class="em-linha"
                          onsubmit="return confirm('Tornar <?= e($a['nome']) ?> administrador?');">
                        <input type="hidden" name="acao" value="promover">
                        <input type="hidden" name="usuario_id" value="<?= $a['id'] ?>">
                        <button type="submit">Tornar administrador<span class="so-leitor"> <?= e($a['nome']) ?></span></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
