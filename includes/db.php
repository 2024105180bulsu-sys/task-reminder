<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Returns a shared PDO connection to MySQL (XAMPP).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        // Developer override used only for automated screenshots/tests.
        if (getenv('TRS_SQLITE')) {
            $pdo = new PDO('sqlite:' . getenv('TRS_SQLITE'), null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        db_unavailable($e->getMessage());
    }
    return $pdo;
}

function db_unavailable(string $msg): void
{
    $isApi = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false;
    http_response_code(503);
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
        exit;
    }
    $base = BASE_URL;
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database not ready</title><link rel="stylesheet" href="{$base}/assets/css/app.css"></head>
<body class="page-auth"><main class="shell"><div class="screen">
<h1 class="h-display">Database not ready</h1>
<p class="muted">The app could not connect to MySQL database <code>task_reminder_db</code>.</p>
<ol class="steps-list"><li>Open the XAMPP Control Panel and start <b>Apache</b> and <b>MySQL</b>.</li>
<li>Run the installer: <a href="{$base}/setup.php">setup.php</a> (or import <code>database/task_reminder_db.sql</code> in phpMyAdmin).</li></ol>
<div class="banner banner-error"><b>Error</b><span>{$safe}</span></div>
</div></main></body></html>
HTML;
    exit;
}

/** Current timestamp string used for every write (keeps PHP and SQL in sync). */
function now_str(): string
{
    return date('Y-m-d H:i:s');
}
