<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('TRSSESSID');
    session_start();
}

/* ------------------------------------------------------------------ */
/*  Output helpers                                                     */
/* ------------------------------------------------------------------ */
function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* ------------------------------------------------------------------ */
/*  Session / authentication                                           */
/* ------------------------------------------------------------------ */
function current_account(): ?array
{
    return $_SESSION['account'] ?? null;
}

function require_role(string $role): array
{
    $acc = current_account();
    if (!$acc) {
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    if ($acc['role'] !== $role) {
        redirect($acc['role'] === 'Admin' ? 'admin/dashboard.php' : 'dashboard.php');
    }
    return $acc;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), (string) $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back and try again.');
    }
}

function flash(string $type, string $message, array $extra = []): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message] + $extra;
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ------------------------------------------------------------------ */
/*  Domain helpers                                                     */
/* ------------------------------------------------------------------ */
const PRIORITIES = ['Low', 'Normal', 'High', 'Critical'];

function priority_class(string $p): string
{
    return 'p-' . strtolower($p);
}

function priority_chip(string $p): string
{
    return '<span class="chip ' . priority_class($p) . '">' . h($p) . '</span>';
}

function ack_label(string $mode): string
{
    return [
        'NOT_REQUESTED' => 'Not requested',
        'OPTIONAL'      => 'Optional',
        'REQUIRED'      => 'Required',
    ][$mode] ?? $mode;
}

function get_profiles(): array
{
    $rows = db()->query('SELECT * FROM notification_profile ORDER BY profile_id')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['priority_tier']] = $r;
    }
    return $out;
}

function log_activity(string $role, ?int $actorId, ?int $taskId, string $action, string $details): void
{
    $st = db()->prepare('INSERT INTO activity_log (actor_role, actor_id, task_id, action, details, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)');
    $st->execute([$role, $actorId, $taskId, $action, mb_substr($details, 0, 255), now_str()]);
}

/** Load a task owned by the given user together with its reminder + profile. */
function find_user_task(int $taskId, int $userId): ?array
{
    $st = db()->prepare(
        'SELECT t.*, r.reminder_id, r.scheduled_time, r.is_sent, r.sent_at, r.is_acknowledged,
                r.acknowledged_at, r.is_dismissed, r.dismissed_at,
                p.audio_tone, p.vibration_pattern, p.acknowledgment_mode, p.decibel_limit
           FROM task t
           LEFT JOIN reminder r ON r.task_id = t.task_id
           LEFT JOIN notification_profile p ON p.profile_id = r.profile_id
          WHERE t.task_id = ? AND t.user_id = ?
          ORDER BY r.reminder_id DESC LIMIT 1'
    );
    $st->execute([$taskId, $userId]);
    $row = $st->fetch();
    return $row ?: null;
}

/** FR-003: true when a sent High/Critical reminder of the task is still waiting for acknowledgment. */
function has_unacknowledged_required_alert(int $taskId): bool
{
    $st = db()->prepare(
        "SELECT COUNT(*) FROM reminder r JOIN notification_profile p ON p.profile_id = r.profile_id
          WHERE r.task_id = ? AND r.is_sent = 1 AND r.is_acknowledged = 0 AND r.is_dismissed = 0
            AND p.acknowledgment_mode = 'REQUIRED'"
    );
    $st->execute([$taskId]);
    return (int) $st->fetchColumn() > 0;
}

/** Human status of a task's reminder. */
function reminder_status(array $t): string
{
    if (empty($t['reminder_id'])) {
        return 'No reminder';
    }
    if ($t['is_acknowledged']) {
        return 'Acknowledged';
    }
    if ($t['is_dismissed']) {
        return 'Dismissed';
    }
    if ($t['is_sent']) {
        return $t['acknowledgment_mode'] === 'REQUIRED' ? 'Sent · awaiting acknowledgment' : 'Sent';
    }
    return 'Scheduled';
}

/* ------------------------------------------------------------------ */
/*  Date formatting                                                    */
/* ------------------------------------------------------------------ */
function fmt_dt(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    return date('j M Y · g:i A', strtotime($dt));
}

function fmt_time(?string $dt): string
{
    return $dt ? date('g:i A', strtotime($dt)) : '—';
}

function fmt_relative_day(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $d = date('Y-m-d', strtotime($dt));
    if ($d === date('Y-m-d')) {
        return 'Today · ' . fmt_time($dt);
    }
    if ($d === date('Y-m-d', strtotime('+1 day'))) {
        return 'Tomorrow · ' . fmt_time($dt);
    }
    if ($d === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday · ' . fmt_time($dt);
    }
    return date('j M · g:i A', strtotime($dt));
}

function greeting(): string
{
    $hr = (int) date('G');
    return $hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $i = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1));
    if (count($parts) > 1) {
        $i .= strtoupper(mb_substr(end($parts), 0, 1));
    } else {
        $i .= strtoupper(mb_substr($parts[0] ?? 'D', 1, 1));
    }
    return $i;
}

/** Value for <input type="datetime-local">. */
function dt_input(?string $dt): string
{
    return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
}

/* ------------------------------------------------------------------ */
/*  Icons (inline SVG, stroke = currentColor)                          */
/* ------------------------------------------------------------------ */
function icon(string $name, int $size = 20): string
{
    $p = [
        'back'     => '<path d="M15 18l-6-6 6-6"/>',
        'chev'     => '<path d="M9 6l6 6-6 6"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'check'    => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'bell'     => '<path d="M6 16V11a6 6 0 1112 0v5l1.5 2h-15L6 16z"/><path d="M10 20a2 2 0 004 0"/>',
        'cal'      => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'audio'    => '<path d="M4 10v4h4l5 4V6L8 10H4z"/><path d="M16 9a4 4 0 010 6"/>',
        'vibrate'  => '<rect x="8" y="4" width="8" height="16" rx="2"/><path d="M4 9v6M20 9v6"/>',
        'shield'   => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16h.01"/>',
        'lock'     => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 118 0v3"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/>',
        'trash'    => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'logout'   => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 5-5 8-5s6.5 1 8 5"/>',
        'sliders'  => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'play'     => '<path d="M8 5l11 7-11 7V5z"/>',
        'undo'     => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'list'     => '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 4-4.5 6.5-4.5s5.5 1 6.5 4.5"/><path d="M16 4.5a3.5 3.5 0 010 7M18 15.5c2 .5 3 2 3.5 4.5"/>',
        'x'        => '<path d="M6 6l12 12M18 6L6 18"/>',
        'send'     => '<path d="M4 12l16-8-6 16-2-6-8-2z"/>',
    ][$name] ?? '';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
