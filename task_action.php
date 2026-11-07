<?php
/* Form handler: complete / reopen (undo) / delete / send_now — FR-004, FR-005 */
require_once __DIR__ . '/includes/functions.php';
$acc = require_role('User');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
csrf_check();

$uid    = (int) $acc['id'];
$id     = (int) ($_POST['task_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$task   = find_user_task($id, $uid);
if (!$task) {
    flash('error', 'That task could not be found.');
    redirect('dashboard.php');
}
$pdo   = db();
$now   = now_str();
$title = $task['title'];

switch ($action) {
    case 'complete':
        if ($task['status'] !== 'Completed') {
            // FR-003: an active High/Critical alert must be acknowledged first; completing cannot silence it.
            if (has_unacknowledged_required_alert($id)) {
                log_activity('User', $uid, $id, 'COMPLETION_BLOCKED', 'Completion of "' . $title . '" refused: active alert not acknowledged');
                flash('error', '<b>Acknowledge the active alert first</b> · ' . h($title) . ' has a ' . h($task['priority_level']) . ' alert that must be acknowledged before the task can be completed.');
                redirect('task_view.php?id=' . $id);
            }
            $pdo->prepare("UPDATE task SET status = 'Completed', completed_at = ?, updated_at = ? WHERE task_id = ? AND user_id = ?")
                ->execute([$now, $now, $id, $uid]);
            // Optional (Normal) and Low alerts still showing are closed by completion.
            // closed_by_completion marks them so Undo restores only alerts this completion closed.
            $pdo->prepare('UPDATE reminder SET is_dismissed = 1, dismissed_at = ?, closed_by_completion = 1 WHERE task_id = ? AND is_sent = 1 AND is_acknowledged = 0 AND is_dismissed = 0')
                ->execute([$now, $id]);
            log_activity('User', $uid, $id, 'TASK_COMPLETED', 'Marked "' . $title . '" as completed');
        }
        // B-05: toast with Undo
        flash('success', '<b>Completion recorded</b> · ' . h($title), ['undo_task' => $id]);
        redirect('dashboard.php');

    case 'reopen':
        // Undo: restore any alert that was closed by the completion itself, so it rings again.
        $pdo->prepare('UPDATE reminder SET is_dismissed = 0, dismissed_at = NULL, closed_by_completion = 0
                       WHERE task_id = ? AND closed_by_completion = 1')
            ->execute([$id]);
        $pdo->prepare("UPDATE task SET status = 'Pending', completed_at = NULL, updated_at = ? WHERE task_id = ? AND user_id = ?")
            ->execute([$now, $id, $uid]);
        log_activity('User', $uid, $id, 'TASK_REOPENED', 'Reopened "' . $title . '" (undo completion)');
        flash('info', '<b>Task reopened</b> · ' . h($title) . ' is pending again.');
        redirect('task_view.php?id=' . $id);

    case 'delete':
        if (has_unacknowledged_required_alert($id)) {
            flash('error', '<b>Acknowledge the active alert first</b> · ' . h($title) . ' cannot be deleted while its ' . h($task['priority_level']) . ' alert is unacknowledged.');
            redirect('task_view.php?id=' . $id);
        }
        log_activity('User', $uid, $id, 'TASK_DELETED', 'Deleted "' . $title . '" (' . $task['priority_level'] . ')');
        $pdo->prepare('DELETE FROM task WHERE task_id = ? AND user_id = ?')->execute([$id, $uid]);
        flash('info', '<b>Task deleted</b> · ' . h($title));
        redirect('dashboard.php');

    case 'send_now':
        if ($task['reminder_id'] && $task['status'] === 'Pending') {
            $pdo->prepare('UPDATE reminder SET scheduled_time = ?, is_sent = 0, sent_at = NULL, is_acknowledged = 0,
                           acknowledged_at = NULL, is_dismissed = 0, dismissed_at = NULL, closed_by_completion = 0 WHERE reminder_id = ?')
                ->execute([$now, $task['reminder_id']]);
            log_activity('User', $uid, $id, 'REMINDER_RESCHEDULED', 'Reminder for "' . $title . '" moved to now (test)');
            flash('info', '<b>Reminder queued</b> · the ' . h($task['priority_level']) . ' alert will appear in a few seconds.');
        }
        redirect('task_view.php?id=' . $id);
}
redirect('dashboard.php');
