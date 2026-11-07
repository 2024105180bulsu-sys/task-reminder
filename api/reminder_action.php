<?php
/**
 * POST api/reminder_action.php  {reminder_id, action: acknowledge|dismiss}
 * FR-003: High/Critical (REQUIRED) reminders can only be silenced by explicit acknowledgment;
 *         dismiss requests for them are rejected server-side.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$acc = current_account();
if (!$acc || $acc['role'] !== 'User') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sent  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? '');
if (!hash_equals(csrf_token(), (string) $sent)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'Session expired']);
    exit;
}

$rid    = (int) ($input['reminder_id'] ?? 0);
$action = (string) ($input['action'] ?? '');
$uid    = (int) $acc['id'];

$st = db()->prepare(
    'SELECT r.*, t.title, t.priority_level, p.acknowledgment_mode
       FROM reminder r JOIN task t ON t.task_id = r.task_id
       JOIN notification_profile p ON p.profile_id = r.profile_id
      WHERE r.reminder_id = ? AND t.user_id = ?'
);
$st->execute([$rid, $uid]);
$r = $st->fetch();
if (!$r) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Reminder not found']);
    exit;
}
$now = now_str();

if ($action === 'acknowledge') {
    if ($r['acknowledgment_mode'] === 'NOT_REQUESTED') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'This profile does not request acknowledgment. Dismiss it instead.']);
        exit;
    }
    db()->prepare('UPDATE reminder SET is_acknowledged = 1, acknowledged_at = ? WHERE reminder_id = ?')->execute([$now, $rid]);
    log_activity('User', $uid, (int) $r['task_id'], 'REMINDER_ACKNOWLEDGED',
        sprintf('Acknowledged %s reminder for "%s"', $r['priority_level'], $r['title']));
    echo json_encode(['ok' => true, 'state' => 'acknowledged', 'at' => fmt_time($now)]);
    exit;
}

if ($action === 'dismiss') {
    if ($r['acknowledgment_mode'] === 'REQUIRED') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Acknowledge this alert to continue.']);
        exit;
    }
    db()->prepare('UPDATE reminder SET is_dismissed = 1, dismissed_at = ? WHERE reminder_id = ?')->execute([$now, $rid]);
    log_activity('User', $uid, (int) $r['task_id'], 'REMINDER_DISMISSED',
        sprintf('Dismissed %s reminder for "%s"', $r['priority_level'], $r['title']));
    echo json_encode(['ok' => true, 'state' => 'dismissed', 'at' => fmt_time($now)]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
