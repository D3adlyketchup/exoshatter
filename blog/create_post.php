<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /blog/login.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    }

    $title      = trim($_POST['title'] ?? '');
    $bodyText   = trim($_POST['body_text'] ?? '');
    $footerText = trim($_POST['footer_text'] ?? '');

    if ($title === '' || strlen($title) > 255) {
        $errors[] = 'Please provide a title (max 255 characters).';
    }
    if ($bodyText === '') {
        $errors[] = 'Post body cannot be empty.';
    }
    if ($footerText !== '' && strlen($footerText) > 255) {
        $errors[] = 'Footer text must be 255 characters or fewer.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO posts (user_id, title, body_text, footer_text)
             VALUES (:user_id, :title, :body_text, :footer_text)'
        );
        try {
            $stmt->execute([
                'user_id'     => $_SESSION['user_id'],
                'title'       => $title,
                'body_text'   => $bodyText,
                'footer_text' => $footerText !== '' ? $footerText : null,
            ]);
            $success = true;
        } catch (PDOException $e) {
            error_log('Post creation failed: ' . $e->getMessage());
            $errors[] = 'Could not save your post. Please try again.';
        }
    }
}

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

require __DIR__ . '/header.php';
?>
<div class="form-card">
    <h1>New Post</h1>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <p class="alert alert-success">Post published! <a href="/blog/index.php">View it on the blog</a>.</p>
    <?php endif; ?>

    <form method="post" action="/blog/create_post.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <label for="title">Title</label>
        <input type="text" id="title" name="title" maxlength="255" required
               value="<?= htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label for="body_text">Body</label>
        <textarea id="body_text" name="body_text" rows="10" required><?= htmlspecialchars($_POST['body_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

        <label for="footer_text">Footer (optional)</label>
        <input type="text" id="footer_text" name="footer_text" maxlength="255"
               value="<?= htmlspecialchars($_POST['footer_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <button type="submit">Publish</button>
    </form>
</div>
<?php require __DIR__ . '/footer.php'; ?>
