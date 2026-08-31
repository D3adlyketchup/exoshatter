<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$isSuccess = false;

$token = $_GET['token'] ?? '';

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $message = 'Invalid or missing verification token.';
} else {
    $stmt = $pdo->prepare('SELECT user_id, is_verified FROM users WHERE verification_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        $message = 'This verification link is invalid or has already been used.';
    } elseif ((int)$user['is_verified'] === 1) {
        $message = 'This account is already verified. You can log in now.';
        $isSuccess = true;
    } else {
        $update = $pdo->prepare(
            'UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = :user_id'
        );
        $update->execute(['user_id' => $user['user_id']]);
        $message = 'Your email has been verified! You can now log in.';
        $isSuccess = true;
    }
}

require __DIR__ . '/header.php';
?>
<div class="form-card">
    <h1>Email Verification</h1>
    <p class="alert <?= $isSuccess ? 'alert-success' : 'alert-error' ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php if ($isSuccess): ?>
        <p><a href="/blog/login.php">Go to login</a></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
