<?php
/* Screen 02 · User workspace (VIEW 1 entry) — FR-004 list, FR-001 priority chips, FR-005 reminder times */
require_once __DIR__ . '/includes/layout.php';
$acc = require_role('User');

$st = db()->prepare(
    'SELECT t.*, r.reminder_id, r.scheduled_time, r.is_sent, r.sent_at, r.is_acknowledged, r.acknowledged_at,
            r.is_dismissed, r.dismissed_at, p.acknowledgment_mode
       FROM task t
       LEFT JOIN reminder r ON r.task_id = t.task_id
       LEFT JOIN notification_profile p ON p.profile_id = r.profile_id
      WHERE t.user_id = ?
      ORDER BY t.status DESC, t.due_date ASC'
);
$st->execute([(int) $acc['id']]);
$tasks = $st->fetchAll();

$pending   = array_values(array_filter($tasks, fn($t) => $t['status'] === 'Pending'));
$completed = array_values(array_filter($tasks, fn($t) => $t['status'] === 'Completed'));
$overdue   = count(array_filter($pending, fn($t) => strtotime($t['due_date']) < time()));

page_start('User workspace', ['sub' => strtoupper(date('l, j F')), 'avatar' => true, 'poll' => true, 'screen' => '02 / User workspace']);
?>
<section class="greet">
  <h2 class="h-greet"><?= h(greeting()) ?>, <?= h($acc['name']) ?>.</h2>
  <p class="muted">Your deliveries and stock reminders.</p>
</section>

<!-- B-07: plain counters (not buttons) -->
<section class="counters" aria-label="Task summary">
  <div class="counter"><span class="counter-num"><?= count($pending) ?></span><span class="counter-label">Pending</span></div>
  <div class="counter"><span class="counter-num"><?= count($completed) ?></span><span class="counter-label">Completed</span></div>
  <div class="counter<?= $overdue ? ' counter-warn' : '' ?>"><span class="counter-num"><?= $overdue ?></span><span class="counter-label">Overdue</span></div>
</section>

<section aria-labelledby="pending-h">
  <div class="section-head">
    <h3 id="pending-h" class="h-section">Deliveries &amp; stock</h3>
    <span class="muted small"><?= count($pending) ?> pending</span>
  </div>
  <?php if (!$pending): ?>
    <div class="empty">
      <div class="hero-icon hero-icon-sm" aria-hidden="true"><?= icon('check', 26) ?></div>
      <p><b>All caught up.</b></p>
      <p class="muted small">Create a reminder for your next delivery or stock check.</p>
    </div>
  <?php else: ?>
    <ul class="task-list"><?php foreach ($pending as $t) { task_row($t); } ?></ul>
  <?php endif; ?>
</section>

<?php if ($completed): ?>
<section aria-labelledby="done-h">
  <details class="done-group">
    <summary class="section-head">
      <h3 id="done-h" class="h-section">Completed</h3>
      <span class="muted small"><?= count($completed) ?> done · show</span>
    </summary>
    <ul class="task-list"><?php foreach ($completed as $t) { task_row($t); } ?></ul>
  </details>
</section>
<?php endif; ?>

<div class="sticky-cta">
  <a class="btn btn-primary btn-block" href="<?= h(url('task_form.php')) ?>"><?= icon('plus') ?> Create reminder</a>
  <p class="muted small center">Standard User</p>
</div>
<?php page_end(); ?>
