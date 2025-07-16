<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php'; // importante incluir o banco

include '../includes/header.php'; // incluir apenas aqui no início

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE reset_token = ? AND reset_expira > NOW()");
$stmt->execute([$token]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo "<script>alert('Token inválido ou expirado.'); window.location.href = 'login.php';</script>";
    exit;
}
?>

<div class="login-container">
    <h2>Nova Senha</h2>
    <form action="processar-reset.php" method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="password" name="nova_senha" placeholder="Digite a nova senha" required>
        <button type="submit">Redefinir Senha</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
