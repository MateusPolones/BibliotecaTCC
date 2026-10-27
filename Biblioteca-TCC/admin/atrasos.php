<?php
require_once "../config/conexao.php";
require_once "../config/auth.php";
require_once "../config/prazo.php";
exigirAdmin();
garantirTabelaConfig($conexao);

// Marca como atrasadas as reservas vencidas
$conexao->query(
    "UPDATE reservas SET status = 'atrasado'
     WHERE status IN ('reservado','retirado') AND data_prevista_devolucao < CURDATE()"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "retirar") {
        $id = (int)$_POST["reserva_id"];
        $stmt = $conexao->prepare("UPDATE reservas SET status='retirado' WHERE id=? AND status='reservado'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['flash'] = ['ok', 'Retirada registrada.'];

    } elseif ($acao === "devolver") {
        $id = (int)$_POST["reserva_id"];
        $conexao->begin_transaction();
        $stmt = $conexao->prepare(
            "UPDATE reservas SET status='devolvido', data_devolucao=CURDATE()
             WHERE id=? AND status IN ('reservado','retirado','atrasado')"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows === 1) {
            $stmt = $conexao->prepare(
                "UPDATE livros SET quantidade_disponivel = quantidade_disponivel + 1
                 WHERE id = (SELECT livro_id FROM reservas WHERE id = ?)"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $conexao->commit();
            $_SESSION['flash'] = ['ok', 'Devolução registrada.'];
        } else {
            $conexao->rollback();
            $_SESSION['flash'] = ['erro', 'Reserva não encontrada ou já finalizada.'];
        }

    } elseif ($acao === "punir") {
        $id   = (int)$_POST["reserva_id"];
        $dias = max(1, min(365, (int)($_POST["dias"] ?? 7)));

        $stmt = $conexao->prepare("SELECT usuario_id FROM reservas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $reserva = $stmt->get_result()->fetch_assoc();

        if ($reserva) {
            $uid = $reserva["usuario_id"];
            $stmt = $conexao->prepare(
                "UPDATE usuarios
                 SET bloqueado_ate = DATE_ADD(GREATEST(CURDATE(), COALESCE(bloqueado_ate, CURDATE())), INTERVAL ? DAY)
                 WHERE id = ?"
            );
            $stmt->bind_param("ii", $dias, $uid);
            $stmt->execute();

            $stmt = $conexao->prepare("SELECT bloqueado_ate FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $fim = $stmt->get_result()->fetch_assoc()["bloqueado_ate"];

            $motivo = "Atraso na devolução";
            $stmt = $conexao->prepare(
                "INSERT INTO punicoes (usuario_id, reserva_id, motivo, data_inicio, data_fim)
                 VALUES (?, ?, ?, CURDATE(), ?)"
            );
            $stmt->bind_param("iiss", $uid, $id, $motivo, $fim);
            $stmt->execute();

            $_SESSION['flash'] = ['ok', "Aluno bloqueado por $dias dia(s)."];
        }

    } elseif ($acao === "definir_prazo") {
        $id   = (int)($_POST["reserva_id"] ?? 0);
        $data = $_POST["data"] ?? "";
        if (!dataValida($conexao, $data)) {
            $_SESSION['flash'] = ['erro', 'Escolha uma data a partir de hoje (no máximo daqui a 1 ano).'];
        } else {
            $stmt = $conexao->prepare(
                "UPDATE reservas
                 SET data_prevista_devolucao = ?, status = IF(status = 'atrasado', 'retirado', status)
                 WHERE id = ? AND status IN ('reservado','retirado','atrasado')"
            );
            $stmt->bind_param("si", $data, $id);
            $stmt->execute();
            $_SESSION['flash'] = ['ok', 'Prazo de devolução definido para ' . date("d/m/Y", strtotime($data)) . '.'];
        }

    } elseif ($acao === "prazo_padrao") {
        $data = $_POST["data"] ?? "";
        if (!dataValida($conexao, $data)) {
            $_SESSION['flash'] = ['erro', 'Escolha uma data a partir de hoje (no máximo daqui a 1 ano).'];
        } else {
            gravarConfig($conexao, 'prazo_padrao_data', $data);
            $_SESSION['flash'] = ['ok', 'Novas reservas deverão ser devolvidas até ' . date("d/m/Y", strtotime($data)) . '.'];
        }

    } elseif ($acao === "prazo_padrao_limpar") {
        apagarConfig($conexao, 'prazo_padrao_data');
        $_SESSION['flash'] = ['ok', 'Prazo padrão voltou a ser de 7 dias após a reserva.'];

    } elseif ($acao === "desbloquear") {
        $uid = (int)$_POST["usuario_id"];
        $stmt = $conexao->prepare("UPDATE usuarios SET bloqueado_ate = NULL WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $_SESSION['flash'] = ['ok', 'Bloqueio removido.'];
    }

    header("Location: atrasos.php");
    exit;
}

$reservas = $conexao->query(
    "SELECT r.id, r.status, r.data_reserva, r.data_prevista_devolucao,
            DATEDIFF(CURDATE(), r.data_prevista_devolucao) AS dias_atraso,
            u.nome, u.email, (u.bloqueado_ate > CURDATE()) AS bloqueado, l.titulo
     FROM reservas r
     JOIN usuarios u ON u.id = r.usuario_id
     JOIN livros l ON l.id = r.livro_id
     WHERE r.status IN ('reservado','retirado','atrasado')
     ORDER BY r.data_prevista_devolucao ASC"
)->fetch_all(MYSQLI_ASSOC);

$bloqueados = $conexao->query(
    "SELECT id, nome, email, bloqueado_ate FROM usuarios WHERE bloqueado_ate > CURDATE() ORDER BY nome"
)->fetch_all(MYSQLI_ASSOC);

$rotulos = ['reservado' => 'Reservado', 'retirado' => 'Retirado', 'atrasado' => 'Atrasado'];

$padraoDefinido = prazoPadraoDefinido($conexao);
$prazoNovo      = prazoPadraoData($conexao);
[$hoje, $maximo] = intervaloPrazo($conexao);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservas e atrasos - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../config/menu.php"; ?>
<main id="conteudo">
    <h1>Reservas e atrasos</h1>
    <?php mostrarFlash(); ?>

    <section class="caixa-prazo" aria-labelledby="t-prazo">
        <h2 id="t-prazo">Prazo padrão das novas reservas</h2>
        <p>
            Uma reserva feita agora deve ser devolvida até <strong><?= date("d/m/Y", strtotime($prazoNovo)) ?></strong>
            <?= $padraoDefinido ? '(data que você escolheu).' : '(padrão de 7 dias; nenhuma data escolhida).' ?>
            As reservas que já existem não mudam.
        </p>
        <form method="POST" class="em-linha">
            <input type="hidden" name="acao" value="prazo_padrao">
            <label for="prazo_padrao">Escolher a data no calendário</label>
            <input type="date" id="prazo_padrao" name="data" min="<?= e($hoje) ?>" max="<?= e($maximo) ?>"
                   value="<?= e($padraoDefinido ?? '') ?>" required>
            <button type="submit" class="principal">Salvar data</button>
        </form>
        <?php if ($padraoDefinido): ?>
            <form method="POST" class="em-linha">
                <input type="hidden" name="acao" value="prazo_padrao_limpar">
                <button type="submit">Voltar ao padrão de 7 dias</button>
            </form>
        <?php endif; ?>
    </section>

    <h2>Reservas ativas</h2>
    <?php if (!$reservas): ?>
        <p>Nenhuma reserva ativa no momento.</p>
    <?php else: ?>
    <div class="tabela-rolavel">
    <table>
        <caption>Livros reservados ou emprestados</caption>
        <thead>
            <tr>
                <th scope="col">Aluno</th>
                <th scope="col">Livro</th>
                <th scope="col">Devolver até</th>
                <th scope="col">Situação</th>
                <th scope="col">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservas as $r): ?>
            <tr>
                <td><?= e($r['nome']) ?><br><small><?= e($r['email']) ?></small></td>
                <td><?= e($r['titulo']) ?></td>
                <td>
                    <?= date("d/m/Y", strtotime($r['data_prevista_devolucao'])) ?>
                    <form method="POST" class="em-linha prazo-reserva">
                        <input type="hidden" name="acao" value="definir_prazo">
                        <input type="hidden" name="reserva_id" value="<?= $r['id'] ?>">
                        <label for="data<?= $r['id'] ?>" class="so-leitor">Nova data de devolução de <?= e($r['titulo']) ?> para <?= e($r['nome']) ?></label>
                        <input type="date" id="data<?= $r['id'] ?>" name="data" min="<?= e($hoje) ?>" max="<?= e($maximo) ?>" required>
                        <button type="submit">Definir prazo</button>
                    </form>
                </td>
                <td>
                    <?= $rotulos[$r['status']] ?>
                    <?php if ($r['dias_atraso'] > 0): ?><br><strong><?= (int)$r['dias_atraso'] ?> dia(s) de atraso</strong><?php endif; ?>
                    <?php if ($r['bloqueado']): ?><br><em>Aluno já bloqueado</em><?php endif; ?>
                </td>
                <td class="acoes">
                    <?php if ($r['status'] === 'reservado'): ?>
                        <form method="POST" class="em-linha">
                            <input type="hidden" name="acao" value="retirar">
                            <input type="hidden" name="reserva_id" value="<?= $r['id'] ?>">
                            <button type="submit">Registrar retirada</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" class="em-linha">
                        <input type="hidden" name="acao" value="devolver">
                        <input type="hidden" name="reserva_id" value="<?= $r['id'] ?>">
                        <button type="submit">Registrar devolução</button>
                    </form>

                    <?php if ($r['dias_atraso'] > 0): ?>
                        <form method="POST" class="em-linha punir" onsubmit="return confirm('Bloquear este aluno?');">
                            <input type="hidden" name="acao" value="punir">
                            <input type="hidden" name="reserva_id" value="<?= $r['id'] ?>">
                            <label for="dias<?= $r['id'] ?>">Dias de bloqueio</label>
                            <input type="number" id="dias<?= $r['id'] ?>" name="dias" min="1" max="365" value="7">
                            <button type="submit" class="perigo">Aplicar punição</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <h2>Alunos bloqueados</h2>
    <?php if (!$bloqueados): ?>
        <p>Nenhum aluno bloqueado.</p>
    <?php else: ?>
    <ul class="lista-bloqueados">
        <?php foreach ($bloqueados as $b): ?>
            <li>
                <span><?= e($b['nome']) ?> (<?= e($b['email']) ?>), bloqueado até <?= date("d/m/Y", strtotime($b['bloqueado_ate'])) ?></span>
                <form method="POST" class="em-linha">
                    <input type="hidden" name="acao" value="desbloquear">
                    <input type="hidden" name="usuario_id" value="<?= $b['id'] ?>">
                    <button type="submit">Remover bloqueio</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</main>
</body>
</html>
