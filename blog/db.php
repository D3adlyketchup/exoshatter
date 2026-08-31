<?php
/**
 * db.php
 * PDO connection to the MariaDB instance tunneled from Termux.
 *
 * The reverse SSH tunnel (run on the phone) maps the web server's
 * 127.0.0.1:3306 back to the phone's MariaDB, so we always connect
 * to localhost here — never to the phone's LAN IP directly.
 */

declare(strict_types=1);

$host = '127.0.0.1';
$port = '3306';
$db   = 'exoblog';
$user = 'root'; // Prefer a dedicated, less-privileged MariaDB user in production
$pass = getenv('EXOBLOG_DB_PASS') ?: 'MARIADB_PASSWORD'; // See note below

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Don't leak connection details (host, user, password) to visitors.
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Service temporarily unavailable. Please try again later.');
}

/*
 * NOTE on credentials:
 * Hardcoding the DB password in this file is the quickest path to get
 * running, but it means the password sits in plaintext on the web server.
 * Safer options once you're past the initial test:
 *   1. Set EXOBLOG_DB_PASS as an environment variable in your web server's
 *      config (Apache SetEnv / nginx+php-fpm fastcgi_param / systemd unit),
 *      so it's never committed to a file at all.
 *   2. If you must hardcode it, place this file outside the web root and
 *      require() it, and make sure /blog/db.php itself is not directly
 *      web-accessible (deny .php includes via .htaccess, or move it up
 *      a directory).
 */
