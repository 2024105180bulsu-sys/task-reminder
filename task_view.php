<?php
/* Screen 14 · Reminder details — FR-003 status, FR-004 complete / edit / delete, FR-005 test delivery */
require_once __DIR__ . '/includes/layout.php';
$acc  = require_role('User');
$id   = (int) ($_GET['id'] ?? 0);
$task = find_user_task($id, (int) $acc['id']);
if (!$task) {
    flash('error', 'That task could not be found.');
    redirect('dashboard.php');
}
$done = $task['status'] === 'Completed';

$st = db()->prepare('SELECT * FROM activity_log WHERE task_id = ? ORDER BY created_at DESC, log_id DESC LIMIT 12');
$st->execute([$id]);
$activity = $st->fetchAll();

$rs = reminder_status($task);
page_start('Reminder details', ['back' => url('dashboard.php'), 'poll' => true, 'screen' => '14 / Reminder details']);
?>
<div class="chip-row">
  <span class="chip <?= $done ? 'chip-done' : 'chip-pending' ?>"><?= $done ? 'Completed' : 'Pending' ?></span>
  <?= priority_chip($task['priority_level']) ?>
</div>
<h2 class="h-title-lg"><?= h($task['title']) ?></h2>

<dl class="card kv-list">
  <div><dt>Due date</dt><dd><?= h(date('j M Y', strtotime($task['due_date']))) ?></dd></div>
  <div><dt>Due time</dt><dd><?= h(fmt_time($task['due_date'])) ?></dd></div>
  <div><dt>Reminder time</dt><dd><?= h(fmt_dt($task['scheduled_time'])) ?></dd></div>
  <div><dt>Reminder status</dt><dd><?= h($rs) ?></dd></div>
  <div><dt>Alert profile</dt><dd><?= h($task['audio_tone']) ?> · <?= h($task['vibration_pattern']) ?></dd></div>
  <?php if ($done): ?><div><dt>Completed</dt><dd><?= h(fmt_dt($task['completed_at'])) ?></dd></div><?php endif; ?>
</dl>

<?php if (!$done): ?>
<!-- B-04: Edit and Delete entry points for FR-004 -->
<div class="btn-row">
  <a class="btn btn-soft" href="<?= h(url('task_form.php?id=' . $id)) ?>"><?= icon('edit') ?> Edit</a>
  <button type="button" class="btn btn-danger-soft" data-confirm="delete" data-task="<?= $id ?>" data-title="<?= h($task['title']) ?>"><?= icon('trash') ?> Delete</button>
</div>
<?php if (!$task['is_sent'] || (!$task['is_acknowledged'] && !$task['is_dismissed'])): ?>
<form method="post" action="<?= h(url('task_action.php')) ?>" class="test-alert">
  <?= csrf_field() ?>
  <input type="hidden" name="task_id" value="<?= $id ?>">
  <input type="hidden" name="action" value="send_now">
  <!-- B-02: alerts are reached from a separate "send reminder now" control, never from a task row -->
  <button class="btn btn-outline btn-block" type="submit"><?= icon('send') ?> Send this reminder now (test)</button>
  <p class="muted small center">Moves the reminder time to now. The alert appears within 5 seconds.</p>
</form>
<?php endif; ?>
<?php endif; ?>

<h3 class="h-section">Activity</h3>
<ol class="timeline">
  <?php foreach ($activity as $a): ?>
    <li class="done"><span class="tl-dot"><?= icon('check', 14) ?></span>
      <div><b><?= h(ucfirst(strtolower(str_replace('_', ' ', $a['action'])))) ?></b>
      <span class="muted small"><?= h(fmt_relative_day($a['created_at'])) ?> · <?= h($a['details']) ?></span></div></li>
  <?php endforeach; ?>
  <?php if (!$activity): ?><li class="muted small">No activity recorded yet.</li><?php endif; ?>
</ol>

<?php if (!$done && $task['is_sent'] && !$task['is_acknowledged'] && !$task['is_dismissed'] && $task['acknowledgment_mode'] === 'REQUIRED'): ?>
  <div class="banner banner-error"><?= icon('alert') ?><div><b>Alert waiting for acknowledgment</b><span>This <?= h($task['priority_level']) ?> alert must be acknowledged before the task can be edited, completed or deleted (FR-003).</span></div></div>
<?php endif; ?>
<?php if (!$done && $task['is_acknowledged']): ?>
  <div class="banner banner-info"><?= icon('info') ?><div><b>Acknowledged is not completed</b><span>The alert is handled. The task is still pending.</span></div></div>
<?php endif; ?>

<div class="sticky-cta">
  <?php if ($done): ?>
    <form method="post" action="<?= h(url('task_action.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="task_id" value="<?= $id ?>"><input type="hidden" name="action" value="reopen">
      <button class="btn btn-soft btn-block" type="submit"><?= icon('undo') ?> Reopen task</button>
    </form>
  <?php else: ?>
    <!-- B-05: completion now asks for confirmation first -->
    <button type="button" class="btn btn-primary btn-block" data-confirm="complete" data-task="<?= $id ?>" data-title="<?= h($task['title']) ?>"><?= icon('check') ?> Mark completed</button>
    <p class="muted small center">Only mark completed after the delivery is done.</p>
  <?php endif; ?>
</div>
<?php page_end(); ?>
