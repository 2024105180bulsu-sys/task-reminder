<?php
/**
 * One-click installer for XAMPP: creates task_reminder_db and loads demo data
 * from database/task_reminder_db.sql. Re-running it resets the demo data.
 */
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name('TRSSESSID');
    session_start();
}
$base = BASE_URL;
$msg = null;
$ok = false;
$installed = false;

try {
    $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $installed = (bool) $server->query("SHOW DATABASES LIKE 'task_reminder_db'")->fetchColumn();
} catch (PDOException $e) {
    $server = null;
    $msg = 'Cannot reach MySQL at ' . DB_HOST . ':' . DB_PORT . '. Start MySQL in the XAMPP Control Panel, then reload this page. (' . $e->getMessage() . ')';
}

if ($server && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = file_get_contents(__DIR__ . '/database/task_reminder_db.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);           // strip comments
        $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)));
        foreach ($statements as $stmt) {
            $server->exec($stmt);
        }
        $ok = true;
        $installed = true;
        $_SESSION = [];
        $msg = 'Database task_reminder_db created with ' . count($statements) . ' statements. Demo data loaded.';
    } catch (PDOException $e) {
        $msg = 'Installation failed: ' . $e->getMessage();
    }
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup · Task Reminder System</title>
<link rel="stylesheet" href="<?= $h($base) ?>/assets/css/app.css?v=3"></head>
<body><main class="shell"><div class="screen">
  <header class="page-head"><div class="page-head-text"><h1 class="h-page">Setup</h1><p class="overline">TASK REMINDER SYSTEM · XAMPP INSTALLER</p></div></header>

  <?php if ($msg): ?>
    <div class="banner <?= $ok ? 'banner-success' : 'banner-error' ?>" role="status"><div><b><?= $ok ? 'Done' : 'Problem' ?></b><span><?= $h($msg) ?></span></div></div>
  <?php endif; ?>

  <section class="card">
    <h2 class="h-section">Status</h2>
    <dl class="kv-list">
      <div><dt>MySQL server</dt><dd><?= $server ? 'Connected' : 'Not reachable' ?></dd></div>
      <div><dt>Database</dt><dd><?= $installed ? 'task_reminder_db found' : 'Not installed' ?></dd></div>
      <div><dt>Time zone</dt><dd><?= $h(date_default_timezone_get()) ?> · <?= $h(date('j M Y g:i A')) ?></dd></div>
    </dl>
  </section>

  <?php if ($server): ?>
  <form method="post" class="form">
    <?php if ($installed): ?><p class="muted small">Running setup again <b>deletes all tasks and logs</b> and restores the demo data.</p><?php endif; ?>
    <button class="btn btn-primary btn-block" type="submit"><?= $installed ? 'Reset demo database' : 'Install database' ?></button>
  </form>
  <?php endif; ?>

  <?php if ($installed): ?>
  <section class="card">
    <h2 class="h-section">Demo accounts</h2>
    <dl class="kv-list">
      <div><dt>Standard User</dt><dd><code>user@trs.com</code> / <code>user123</code></dd></div>
      <div><dt>Administrator</dt><dd><code>admin@trs.com</code> / <code>admin123</code></dd></div>
    </dl>
    <p class="muted small">The Critical "Reorder cement stock" reminder is scheduled 2 minutes after install so you can see a live alert.</p>
  </section>
  <a class="btn btn-soft btn-block" href="<?= $h($base) ?>/login.php">Open the app</a>
  <?php endif; ?>
</div></main></body></html>
