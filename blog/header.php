<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);
$currentUsername = $_SESSION['username'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exoshatter Blog</title>
    <link rel="stylesheet" href="/blog/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="/blog/index.php" class="brand">
            <img src="/img/exologo.png" alt="Exoshatter" class="logo">
            <span>Exoshatter Blog</span>
        </a>
        <nav class="site-nav">
            <a href="/blog/index.php">Home</a>
            <?php if ($isLoggedIn): ?>
                <a href="/blog/create_post.php">New Post</a>
                <span class="nav-user">Hi, <?= htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8') ?></span>
                <a href="/blog/logout.php">Logout</a>
            <?php else: ?>
                <a href="/blog/login.php">Login</a>
                <a href="/blog/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="site-main">
