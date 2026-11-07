<?php
/* Screen 06 · Review reminder (step 2 of 2) — FR-001, FR-002, FR-004, FR-005 */
require_once __DIR__ . '/includes/layout.php';
$acc = require_role('User');
$draft = $_SESSION['draft'] ?? null;
if (!$draft) {
    redirect('task_form.php');
}
$profiles = get_profiles();
$profile  = $profiles[$draft['priority']];
$uid      = (int) $acc['id'];
$editId   = (int) $draft['task_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // FR-003: saving an edit re-arms the reminder, so it must not be used to clear an unacknowledged urgent alert.
    if ($editId && has_unacknowledged_required_alert($editId)) {
        flash('error', '<b>Acknowledge the active alert first</b> · changes were not saved because the task\'s urgent alert is still unacknowledged.');
        redirect('task_view.php?id=' . $editId);
    }
    $pdo = db();
    $now = now_str();
    $due = date('Y-m-d H:i:s', strtotime($draft['due_date']));
    $rem = date('Y-m-d H:i:s', strtotime($draft['reminder_time']));

    $pdo->beginTransaction();
    try {
        if ($editId) {
            $task = find_user_task($editId, $uid);
            if (!$task) {
                throw new RuntimeException('Task not found');
            }
            $pdo->prepare('UPDATE task SET title = ?, due_date = ?, priority_level = ?, updated_at = ? WHERE task_id = ? AND user_id = ?')
                ->execute([$draft['title'], $due, $draft['priority'], $now, $editId, $uid]);

            $rescheduled = $task['scheduled_time'] !== $rem || $task['priority_level'] !== $draft['priority'];
            if ($task['reminder_id']) {
                if ($rescheduled) {
                    // Re-arm the reminder with the new time / tier profile.
                    $pdo->prepare('UPDATE reminder SET profile_id = ?, scheduled_time = ?, is_sent = 0, sent_at = NULL,
                                   is_acknowledged = 0, acknowledged_at = NULL, is_dismissed = 0, dismissed_at = NULL, closed_by_completion = 0
                                   WHERE reminder_id = ?')
                        ->execute([$profile['profile_id'], $rem, $task['reminder_id']]);
                }
            } else {
                $pdo->prepare('INSERT INTO reminder (task_id, profile_id, scheduled_time) VALUES (?, ?, ?)')
                    ->execute([$editId, $profile['profile_id'], $rem]);
            }
            log_activity('User', $uid, $editId, 'TASK_UPDATED', sprintf('Updated "%s" (%s, reminder %s)', $draft['title'], $draft['priority'], fmt_dt($rem)));
            $taskId = $editId;
        } else {
            $pdo->prepare('INSERT INTO task (user_id, title, due_date, priority_level, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, \'Pending\', ?, ?)')
                ->execute([$uid, $draft['title'], $due, $draft['priority'], $now, $now]);
            $taskId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO reminder (task_id, profile_id, scheduled_time) VALUES (?, ?, ?)')
                ->execute([$taskId, $profile['profile_id'], $rem]);
            log_activity('User', $uid, $taskId, 'TASK_CREATED', sprintf('Created "%s" (%s)', $draft['title'], $draft['priority']));
            log_activity('System', null, $taskId, 'REMINDER_SCHEDULED', sprintf('%s reminder scheduled for %s', $draft['priority'], fmt_dt($rem)));
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'The reminder could not be saved. Please try again.');
        redirect('task_review.php');
    }
    unset($_SESSION['draft']);
    redirect('task_saved.php?id=' . $taskId . ($editId ? '&updated=1' : ''));
}

$needsAck = $profile['acknowledgment_mode'] === 'REQUIRED';
page_start('Review reminder', [
    'sub' => 'STEP 2 OF 2 · CONFIRM', 'back' => url('task_form.php?draft=1'), 'poll' => true, 'screen' => '06 / Review & edit',
]);
?>
<p class="muted">A quick check before we take care of the reminder.</p>

<article class="card card-summary">
  <div class="card-top">
    <?= priority_chip($draft['priority']) ?> <span class="muted small">priority</span>
    <span class="chip chip-outline"><?= $editId ? 'Unsaved changes' : 'Not saved yet' ?></span>
  </div>
  <h2 class="h-title"><?= h($draft['title']) ?></h2>
  <dl class="kv-list">
    <div><dt>Due</dt><dd><?= h(fmt_dt($draft['due_date'])) ?></dd></div>
    <div><dt>Remind me</dt><dd><?= h(fmt_dt($draft['reminder_time'])) ?></dd></div>
  </dl>
</article>

<h3 class="h-section">How you'll be reminded</h3>
<ul class="card list-plain">
  <li><?= icon('audio') ?> Audio · <?= h($profile['audio_tone']) ?> <span class="muted small">(<?= (int) $profile['decibel_limit'] ?> dB)</span></li>
  <li><?= icon('vibrate') ?> Vibration · <?= h($profile['vibration_pattern']) ?></li>
  <li><?= icon('shield') ?> Acknowledgment <?= h(strtolower(ack_label($profile['acknowledgment_mode']))) ?></li>
</ul>

<div class="banner banner-info">
  <?= icon('info') ?>
  <div>
    <?php if ($needsAck): ?>
      <b>The alert stays active until you acknowledge</b>
      <span>Acknowledging silences the alert. Your task stays pending until you mark it completed.</span>
    <?php else: ?>
      <b>You can dismiss this alert</b>
      <span>Dismissing returns you to your workspace. Your task stays pending until you mark it completed.</span>
    <?php endif; ?>
  </div>
</div>

<div class="sticky-cta">
  <form method="post">
    <?= csrf_field() ?>
    <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Confirm &amp; schedule</button>
  </form>
  <a class="btn btn-soft btn-block" href="<?= h(url('task_form.php?draft=1')) ?>"><?= icon('edit') ?> Edit details</a>
</div>
<?php page_end(); ?>
