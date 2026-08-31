<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$stmt = $pdo->query(
    'SELECT p.post_id, p.title, p.body_text, p.footer_text, p.created_at, u.username
     FROM posts p
     JOIN users u ON u.user_id = p.user_id
     ORDER BY p.created_at DESC'
);
$posts = $stmt->fetchAll();

require __DIR__ . '/header.php';
?>
<div class="posts-list">
    <h1>Latest Posts</h1>

    <?php if (empty($posts)): ?>
        <p>No posts yet. <?= isset($_SESSION['user_id']) ? '<a href="/blog/create_post.php">Write the first one</a>.' : 'Check back soon!' ?></p>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
        <article class="post-card">
            <h2><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="post-meta">
                by <?= htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8') ?>
                &middot; <?= htmlspecialchars(date('F j, Y g:i a', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                &middot; #<?= (int)$post['post_id'] ?>
            </p>
            <div class="post-body">
                <?= nl2br(htmlspecialchars($post['body_text'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
            <?php if (!empty($post['footer_text'])): ?>
                <p class="post-footer"><?= htmlspecialchars($post['footer_text'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
