<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['tipo_usuario'], ['admin', 'sensei'])) {
    header('Location: login.php');
    exit;
}

// Processa o formulário
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_digitada = $_POST['senha_adm'] ?? '';

    $stmt = $pdo->prepare("SELECT senha_adm FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $senha_digitada === $usuario['senha_adm']) {
        $_SESSION['senha_adm_valida'] = true;
        header("Location: usuarios-aprovar.php");
        exit;
    } else {
        $erro = "Senha incorreta.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificar Senha Administrativa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <h2>Digite a Senha Administrativa</h2>

        <?php if ($erro): ?>
            <p style="color: red;"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="senha_adm">Senha Administrativa:</label><br>
            <input type="password" name="senha_adm" id="senha_adm" required><br><br>
            <button type="submit">Validar</button>
        </form>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
