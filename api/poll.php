<?php
/**
 * GET api/poll.php  — reminder dispatcher (FR-002, FR-005, NFR-001)
 * 1. Marks every due, unsent reminder of the signed-in user as sent (Reminder.dispatchAlert()).
 * 2. Returns all active alerts (sent, not acknowledged, not dismissed, task still pending)
 *    together with the notification profile the client must execute.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$acc = current_account();
if (!$acc || $acc['role'] !== 'User') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in']);
    exit;
}
$uid = (int) $acc['id'];
$pdo = db();
$now = now_str();

// 1. Dispatch due reminders
$due = $pdo->prepare(
    "SELECT r.reminder_id, t.task_id, t.title, t.priority_level, p.audio_tone, p.vibration_pattern
       FROM reminder r
       JOIN task t ON t.task_id = r.task_id
       JOIN notification_profile p ON p.profile_id = r.profile_id
      WHERE t.user_id = ? AND t.status = 'Pending' AND r.is_sent = 0 AND r.scheduled_time <= ?"
);
$due->execute([$uid, $now]);
$mark = $pdo->prepare('UPDATE reminder SET is_sent = 1, sent_at = ? WHERE reminder_id = ? AND is_sent = 0');
foreach ($due->fetchAll() as $r) {
    $mark->execute([$now, $r['reminder_id']]);
    if ($mark->rowCount()) {
        log_activity('System', null, (int) $r['task_id'], 'REMINDER_SENT',
            sprintf('%s reminder sent: %s, %s', $r['priority_level'], $r['audio_tone'], $r['vibration_pattern']));
    }
}

// 2. Active alerts, most urgent first
$st = $pdo->prepare(
    "SELECT r.reminder_id, r.scheduled_time, r.sent_at, t.task_id, t.title, t.due_date, t.status, t.priority_level,
            p.audio_tone, p.decibel_limit, p.vibration_pattern, p.acknowledgment_mode
       FROM reminder r
       JOIN task t ON t.task_id = r.task_id
       JOIN notification_profile p ON p.profile_id = r.profile_id
      WHERE t.user_id = ? AND t.status = 'Pending'
        AND r.is_sent = 1 AND r.is_acknowledged = 0 AND r.is_dismissed = 0
      ORDER BY CASE t.priority_level WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Normal' THEN 3 ELSE 4 END,
               r.sent_at ASC"
);
$st->execute([$uid]);
$alerts = array_map(function ($a) {
    return [
        'reminder_id' => (int) $a['reminder_id'],
        'task_id'     => (int) $a['task_id'],
        'title'       => $a['title'],
        'priority'    => $a['priority_level'],
        'due_label'   => fmt_relative_day($a['due_date']),
        'sent_label'  => fmt_time($a['sent_at']),
        'status'      => $a['status'],
        'profile'     => [
            'audio'     => $a['audio_tone'],
            'decibels'  => (int) $a['decibel_limit'],
            'vibration' => $a['vibration_pattern'],
            'ack'       => $a['acknowledgment_mode'],
        ],
    ];
}, $st->fetchAll());

echo json_encode(['ok' => true, 'server_time' => $now, 'alerts' => $alerts]);
