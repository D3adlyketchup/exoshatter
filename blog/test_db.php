<?php
/**
 * test_db.php
 * Quick connectivity + schema sanity check. Delete this file (or block
 * it with .htaccess) once you've confirmed everything works — it's not
 * meant to stay publicly reachable.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

header('Content-Type: text/plain');

try {
    $version = $pdo->query('SELECT VERSION() AS v')->fetch()['v'];
    echo "Connected to MariaDB. Version: {$version}\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n";

    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $postCount = (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    echo "users: {$userCount} rows\n";
    echo "posts: {$postCount} rows\n";

    echo "\nAll good — the reverse tunnel to Termux is working.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Connection or query failed: " . $e->getMessage() . "\n";
}
