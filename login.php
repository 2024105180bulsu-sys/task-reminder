<?php
/* Screen 01 · Sign in  — [Supporting UI Control / Implementation Detail] */
require_once __DIR__ . '/includes/layout.php';

if (current_account()) {
    redirect('index.php');
}

$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = (string) ($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        $error = 'Enter your work email and password.';
    } else {
        $account = null;
        // Administrator accounts first, then standard users (role decides the workspace).
        $st = db()->prepare('SELECT admin_id AS id, name, email, password_hash FROM system_administrator WHERE email = ?');
        $st->execute([$email]);
        if (($row = $st->fetch()) && password_verify($pass, $row['password_hash'])) {
            $account = $row + ['role' => 'Admin'];
        } else {
            $st = db()->prepare('SELECT user_id AS id, name, email, password_hash FROM standard_user WHERE email = ?');
            $st->execute([$email]);
            if (($row = $st->fetch()) && password_verify($pass, $row['password_hash'])) {
                $account = $row + ['role' => 'User'];
            }
        }

        if ($account) {
            session_regenerate_id(true);
            unset($account['password_hash']);
            $_SESSION['account'] = $account;
            log_activity($account['role'], (int) $account['id'], null, 'SIGNED_IN', $account['name'] . ' signed in');
            redirect($account['role'] === 'Admin' ? 'admin/dashboard.php' : 'dashboard.php');
        }
        $error = 'That email and password do not match an account.';
    }
}

page_start('Sign in', ['body' => 'page-auth']);
?>
<div class="auth">
  <p class="brand"><?= h(APP_NAME) ?></p>
  <p class="overline"><?= h(APP_TAGLINE) ?></p>

  <div class="hero-icon" aria-hidden="true"><?= icon('check', 34) ?></div>
  <h2 class="h-display">Deliver on time.<br>Keep stock ready.</h2>
  <p class="muted">Sign in to manage hardware deliveries and stock reminders. Your assigned role opens your workspace.</p>

  <?php if ($error): ?>
    <div class="banner banner-error" role="alert"><?= icon('alert') ?><div><b>Sign in failed</b><span><?= h($error) ?></span></div></div>
  <?php endif; ?>

  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <label class="field">
      <span class="label">Work email</span>
      <span class="input-wrap">
        <input class="input" type="email" name="email" value="<?= h($email) ?>" autocomplete="username" placeholder="name@company.com" required>
        <span class="input-icon"><?= icon('mail') ?></span>
      </span>
    </label>
    <label class="field">
      <span class="label">Password</span>
      <span class="input-wrap">
        <input class="input" type="password" name="password" id="pw" autocomplete="current-password" placeholder="Your password" required>
        <button type="button" class="input-icon input-icon-btn" data-toggle-pw="pw" aria-label="Show password"><?= icon('eye') ?></button>
      </span>
    </label>
    <button class="btn btn-primary btn-block" type="submit"><?= icon('arrow') ?> Sign in</button>
  </form>

  <p class="muted small center"><?= icon('lock', 16) ?> Your credentials are verified before you continue.</p>

  <details class="demo-box">
    <summary>Demo accounts</summary>
    <table class="kv">
      <tr><th>Standard User</th><td><code>user@trs.com</code> / <code>user123</code></td></tr>
      <tr><th>Administrator</th><td><code>admin@trs.com</code> / <code>admin123</code></td></tr>
    </table>
  </details>
  <p class="muted small center">Your administrator assigns your User or Admin role.</p>
</div>
<?php page_end(); ?>
