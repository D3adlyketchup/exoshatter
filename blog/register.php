<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || strlen($username) > 50) {
        $errors[] = 'Please provide a username (max 50 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (empty($errors)) {
        // Check for existing username/email
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username' => $username, 'email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32)); // 64-char hex token

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, verification_token, is_verified)
             VALUES (:username, :email, :password_hash, :token, 0)'
        );

        try {
            $stmt->execute([
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $passwordHash,
                'token'         => $token,
            ]);

            $verifyLink = sprintf(
                '%s://%s/blog/verify.php?token=%s',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
                $_SERVER['HTTP_HOST'] ?? 'exoshatter.com',
                $token
            );

            $subject = 'Verify your Exoshatter Blog account';
            $body = "Hi {$username},\n\nPlease verify your account by clicking the link below:\n{$verifyLink}\n\nIf you didn't sign up, you can ignore this email.";
            $headers = 'From: no-reply@exoshatter.com';

            // mail() requires a configured MTA on the web host. If it's not
            // set up yet, this call may fail silently — see fallback below.
            @mail($email, $subject, $body, $headers);

            $success = 'Account created! Check your email for a verification link.';

            // Fallback for local testing when outbound mail isn't configured yet.
            if (getenv('EXOBLOG_DEBUG') === '1') {
                $success .= " (debug link: {$verifyLink})";
            }
        } catch (PDOException $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

require __DIR__ . '/header.php';
?>
<div class="form-card">
    <h1>Create an account</h1>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <p class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="/blog/register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="50" required
               value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="255" required
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required>

        <button type="submit">Register</button>
    </form>

    <p>Already have an account? <a href="/blog/login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/footer.php'; ?>
