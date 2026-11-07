<?php
require_once __DIR__ . '/functions.php';

/**
 * Page header.
 * $o keys: back (url), sub (subtitle), wide (bool), poll (bool), avatar (bool), body (class), screen (label)
 */
function page_start(string $title, array $o = []): void
{
    $acc    = current_account();
    $wide   = !empty($o['wide']);
    $poll   = !empty($o['poll']) && $acc && $acc['role'] === 'User';
    $flashes = take_flashes();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#2256C7">
<title><?= h($title) ?> · <?= h(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/css/app.css')) ?>?v=3">
</head>
<body class="<?= h($o['body'] ?? '') ?>"
      data-base="<?= h(BASE_URL) ?>"
      data-csrf="<?= h(csrf_token()) ?>"
      data-poll="<?= $poll ? '1' : '0' ?>"
      data-poll-ms="<?= (int) POLL_INTERVAL_MS ?>">
<a class="skip" href="#main">Skip to content</a>
<main id="main" class="shell<?= $wide ? ' shell-wide' : '' ?>">
  <div class="screen">
    <?php if (!empty($o['screen'])): ?><div class="screen-tag"><?= h($o['screen']) ?></div><?php endif; ?>
    <header class="page-head">
      <?php if (!empty($o['back'])): ?>
        <a class="icon-btn" href="<?= h($o['back']) ?>" aria-label="Back"><?= icon('back', 24) ?></a>
      <?php endif; ?>
      <div class="page-head-text">
        <h1 class="h-page"><?= h($title) ?></h1>
        <?php if (!empty($o['sub'])): ?><p class="overline"><?= h($o['sub']) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($o['avatar']) && $acc): ?>
        <button type="button" class="avatar" data-open="profile-sheet" aria-label="Open profile and sign out" aria-haspopup="dialog"><?= h(initials($acc['name'])) ?></button>
      <?php endif; ?>
    </header>
    <?php foreach ($flashes as $f): ?>
      <div class="toast-inline toast-<?= h($f['type']) ?>" role="status" data-autohide>
        <?= icon($f['type'] === 'error' ? 'alert' : ($f['type'] === 'success' ? 'check' : 'info'), 20) ?>
        <span class="toast-msg"><?= $f['message'] /* pre-escaped by caller */ ?></span>
        <?php if (!empty($f['undo_task'])): ?>
          <form method="post" action="<?= h(url('task_action.php')) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="task_id" value="<?= (int) $f['undo_task'] ?>">
            <input type="hidden" name="action" value="reopen">
            <button class="btn-link" type="submit"><?= icon('undo', 18) ?> Undo</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php
}

function page_end(): void
{
    $acc = current_account();
    ?>
  </div>
</main>

<?php if ($acc): ?>
<!-- B-06: profile bottom sheet with Sign out -->
<div class="sheet-backdrop" id="profile-sheet" role="dialog" aria-modal="true" aria-labelledby="profile-title" hidden>
  <div class="sheet">
    <div class="sheet-grip" aria-hidden="true"></div>
    <div class="sheet-head">
      <div class="avatar avatar-lg" aria-hidden="true"><?= h(initials($acc['name'])) ?></div>
      <div>
        <h2 id="profile-title" class="h-section"><?= h($acc['name']) ?></h2>
        <p class="muted small"><?= h($acc['email']) ?></p>
        <span class="chip chip-role"><?= $acc['role'] === 'Admin' ? 'System Administrator' : 'Standard User' ?></span>
      </div>
    </div>
    <div class="sheet-body">
      <p class="muted small">Signing out ends this session. Scheduled reminders stay saved and alert you the next time you sign in.</p>
    </div>
    <form method="post" action="<?= h(url('logout.php')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-danger-soft btn-block" type="submit"><?= icon('logout') ?> Sign out</button>
    </form>
    <button class="btn btn-ghost btn-block" type="button" data-close>Cancel</button>
  </div>
</div>
<?php endif; ?>

<!-- Reusable confirm dialog (B-05: "Mark as completed?") -->
<div class="modal-backdrop" id="confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text" hidden>
  <div class="modal modal-sm">
    <div class="modal-icon" id="confirm-icon"><?= icon('check', 28) ?></div>
    <h2 class="h-section center" id="confirm-title">Mark as completed?</h2>
    <p class="muted center" id="confirm-text"></p>
    <form method="post" id="confirm-form">
      <?= csrf_field() ?>
      <input type="hidden" name="task_id" id="confirm-task">
      <input type="hidden" name="action" id="confirm-action">
      <button class="btn btn-primary btn-block" type="submit" id="confirm-ok">Confirm</button>
    </form>
    <button class="btn btn-ghost btn-block" type="button" data-close>Cancel</button>
  </div>
</div>

<!-- Reminder alert modal (screens 08-12), filled by app.js -->
<div class="modal-backdrop alert-backdrop" id="alert-modal" role="alertdialog" aria-modal="true" aria-labelledby="alert-title" hidden>
  <div class="modal modal-alert" id="alert-card"></div>
</div>

<div class="toast-stack" id="toast-stack" aria-live="polite"></div>

<script src="<?= h(url('assets/js/app.js')) ?>?v=3"></script>
</body>
</html>
    <?php
}

/** Task row used on dashboard (B-02 / B-04: row opens details, checkbox opens confirm). */
function task_row(array $t): void
{
    $done = $t['status'] === 'Completed';
    $meta = $done
        ? 'Completed · ' . fmt_relative_day($t['completed_at'])
        : 'Due ' . fmt_relative_day($t['due_date']) . ' · Reminder ' . fmt_time($t['scheduled_time']);
    $rs = reminder_status($t);
    ?>
  <li class="task-row<?= $done ? ' is-done' : '' ?>">
    <?php if ($done): ?>
      <span class="check check-on" aria-label="Completed"><?= icon('check', 16) ?></span>
    <?php else: ?>
      <button type="button" class="check" aria-label="Mark &quot;<?= h($t['title']) ?>&quot; as completed"
              data-confirm="complete" data-task="<?= (int) $t['task_id'] ?>" data-title="<?= h($t['title']) ?>"></button>
    <?php endif; ?>
    <a class="task-link" href="<?= h(url('task_view.php?id=' . (int) $t['task_id'])) ?>">
      <span class="task-title"><?= h($t['title']) ?></span>
      <span class="task-meta"><?= priority_chip($t['priority_level']) ?><span><?= h($meta) ?></span></span>
      <?php if (!$done && $rs !== 'Scheduled'): ?>
        <span class="task-rs rs-<?= h(strtolower(strtok($rs, ' '))) ?>"><?= h($rs) ?></span>
      <?php endif; ?>
    </a>
    <span class="task-chev" aria-hidden="true"><?= icon('chev', 20) ?></span>
  </li>
    <?php
}
