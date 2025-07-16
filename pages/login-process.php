<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        header('Location: login.php?erro=1');
        exit;
    }

    // Buscar usuários com mesmo e-mail
    $stmt = $pdo->prepare("SELECT id, nome, senha, tipo_usuario, dojo_id, status FROM usuarios WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usuario_autenticado = null;

    foreach ($usuarios as $usuario) {
        if (password_verify($senha, $usuario['senha'])) {
            $usuario_autenticado = $usuario;
            break;
        }
    }

    if ($usuario_autenticado) {
        if ($usuario_autenticado['status'] !== 'aprovado') {
            header('Location: login.php?erro=3');
            exit;
        }

        // Verificação da mensalidade — SOMENTE ALUNO
        if ($usuario_autenticado['tipo_usuario'] === 'aluno') {
            $stmtAluno = $pdo->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
            $stmtAluno->execute([$usuario_autenticado['id']]);
            $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

            if ($aluno) {
                $aluno_id = $aluno['id'];
                $mes_ano = date('Y-m');
                $diaAtual = (int) date('d');

                $stmtMensalidade = $pdo->prepare("SELECT status FROM mensalidades WHERE aluno_id = ? AND mes_ano = ?");
                $stmtMensalidade->execute([$aluno_id, $mes_ano]);
                $mensalidade = $stmtMensalidade->fetch(PDO::FETCH_ASSOC);

                if ($mensalidade) {
                    $status = trim($mensalidade['status']);

                    if ($status === 'Em Aberto' && $diaAtual <= 20) {
                        // Aviso, mas permite login
                        $_SESSION['mensalidade_em_aberto'] = true;
                    } elseif ($status === 'Em Aberto' && $diaAtual > 20) {
                        // Bloqueia login depois do dia 20
                        header('Location: login.php?erro=mensalidade');
                        exit;
                    }
                }
            }
        }

        // Login autorizado
        $_SESSION['usuario_id'] = $usuario_autenticado['id'];
        $_SESSION['nome'] = $usuario_autenticado['nome'];
        $_SESSION['tipo_usuario'] = $usuario_autenticado['tipo_usuario'];
        $_SESSION['dojo_id'] = $usuario_autenticado['dojo_id'];

        if ($usuario_autenticado['tipo_usuario'] === 'sensei') {
            header('Location: ../pages/painel-sensei.php');
        } else {
            header('Location: ../pages/painel-aluno.php');
        }
        exit;

    } else {
        header('Location: login.php?erro=1');
        exit;
    }
} else {
    header('Location: login.php?erro=2');
    exit;
}
