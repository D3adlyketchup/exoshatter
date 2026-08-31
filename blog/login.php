<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /blog/index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT user_id, username, password_hash, is_verified FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Always run password_verify (even on a dummy hash) to avoid
        // leaking via response-time whether the email exists.
        $hashToCheck = $user['password_hash'] ?? '$2y$10$invalidsaltinvalidsaltinvalidsaltuv';
        $passwordOk = password_verify($password, $hashToCheck);

        if (!$user || !$passwordOk) {
            $errors[] = 'Invalid email or password.';
        } elseif ((int)$user['is_verified'] !== 1) {
            $errors[] = 'Please verify your email before logging in.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            header('Location: /blog/index.php');
            exit;
        }
    }
}

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

require __DIR__ . '/header.php';
?>
<div class="form-card">
    <h1>Log in</h1>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endforeach; ?>

    <form method="post" action="/blog/login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="255" required
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Log in</button>
    </form>

    <p>Don't have an account? <a href="/blog/register.php">Register</a></p>
</div>
<?php require __DIR__ . '/footer.php'; ?>
