<?php
/* Screen 07 · Reminder scheduled (VIEW 2 · system feedback) — FR-004, FR-005 */
require_once __DIR__ . '/includes/layout.php';
$acc  = require_role('User');
$id   = (int) ($_GET['id'] ?? 0);
$task = find_user_task($id, (int) $acc['id']);
if (!$task) {
    redirect('dashboard.php');
}
$updated = !empty($_GET['updated']);

page_start('All set', ['poll' => true, 'screen' => '07 / Reminder scheduled']);
?>
<section class="feedback">
  <div class="hero-icon" aria-hidden="true"><?= icon('cal', 32) ?></div>
  <h2 class="h-display center"><?= $updated ? 'Changes saved.' : 'Reminder scheduled.' ?></h2>
  <p class="muted center">Your task is saved. We'll remind you before it is due.</p>
</section>

<article class="card card-summary">
  <span class="chip chip-pending">Pending</span>
  <h2 class="h-title"><?= h($task['title']) ?></h2>
  <dl class="kv-list">
    <div><dt>Priority</dt><dd><?= priority_chip($task['priority_level']) ?></dd></div>
    <div><dt>Due</dt><dd><?= h(fmt_relative_day($task['due_date'])) ?></dd></div>
    <div><dt>Reminder</dt><dd><?= h(fmt_relative_day($task['scheduled_time'])) ?></dd></div>
  </dl>
</article>

<ol class="timeline">
  <li class="done"><span class="tl-dot"><?= icon('check', 14) ?></span><div><b>Task <?= $updated ? 'updated' : 'saved' ?></b><span class="muted small"><?= h(fmt_dt($task['updated_at'])) ?></span></div></li>
  <li class="done"><span class="tl-dot"><?= icon('check', 14) ?></span><div><b>Reminder scheduled</b><span class="muted small"><?= h(fmt_dt($task['scheduled_time'])) ?> · <?= h($task['audio_tone']) ?> + <?= h(strtolower($task['vibration_pattern'])) ?></span></div></li>
</ol>

<div class="sticky-cta">
  <a class="btn btn-primary btn-block" href="<?= h(url('dashboard.php')) ?>">Back to dashboard</a>
  <!-- B-02: "View task" opens Reminder details, not an alert -->
  <a class="btn btn-soft btn-block" href="<?= h(url('task_view.php?id=' . $id)) ?>">View task</a>
</div>
<?php page_end(); ?>
