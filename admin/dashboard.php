<?php
/* Screen 16 · Admin monitoring dashboard (VIEW 3) — FR-003 acknowledgment tracking, FR-005 delivery status */
require_once __DIR__ . '/../includes/layout.php';
$acc = require_role('Admin');
$pdo = db();
$now = now_str();

$one = function (string $sql, array $p = []) use ($pdo): int {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int) $s->fetchColumn();
};

$stats = [
    'tasks'     => $one('SELECT COUNT(*) FROM task'),
    'pending'   => $one("SELECT COUNT(*) FROM task WHERE status = 'Pending'"),
    'completed' => $one("SELECT COUNT(*) FROM task WHERE status = 'Completed'"),
    'sent'      => $one('SELECT COUNT(*) FROM reminder WHERE is_sent = 1'),
    'acked'     => $one('SELECT COUNT(*) FROM reminder WHERE is_acknowledged = 1'),
    'awaiting'  => $one("SELECT COUNT(*) FROM reminder r JOIN notification_profile p ON p.profile_id = r.profile_id
                         JOIN task t ON t.task_id = r.task_id
                         WHERE r.is_sent = 1 AND r.is_acknowledged = 0 AND r.is_dismissed = 0
                           AND p.acknowledgment_mode = 'REQUIRED' AND t.status = 'Pending'"),
    'overdue'   => $one("SELECT COUNT(*) FROM task WHERE status = 'Pending' AND due_date < ?", [$now]),
];
$ackRate = $stats['sent'] ? round(100 * $stats['acked'] / $stats['sent']) : 0;

// Per-tier breakdown
$tiers = $pdo->query(
    "SELECT p.priority_tier, p.audio_tone, p.vibration_pattern, p.acknowledgment_mode,
            COUNT(r.reminder_id) AS total,
            SUM(CASE WHEN r.is_sent = 1 THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN r.is_acknowledged = 1 THEN 1 ELSE 0 END) AS acked,
            SUM(CASE WHEN r.is_dismissed = 1 THEN 1 ELSE 0 END) AS dismissed
       FROM notification_profile p LEFT JOIN reminder r ON r.profile_id = p.profile_id
      GROUP BY p.profile_id, p.priority_tier, p.audio_tone, p.vibration_pattern, p.acknowledgment_mode
      ORDER BY p.profile_id"
)->fetchAll();

// Task table with filters
$fStatus = in_array($_GET['status'] ?? '', ['Pending', 'Completed'], true) ? $_GET['status'] : '';
$fPri    = in_array($_GET['priority'] ?? '', PRIORITIES, true) ? $_GET['priority'] : '';
$q       = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];
if ($fStatus) { $where[] = 't.status = ?'; $params[] = $fStatus; }
if ($fPri)    { $where[] = 't.priority_level = ?'; $params[] = $fPri; }
if ($q !== '') { $where[] = '(t.title LIKE ? OR u.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql = 'SELECT t.*, u.name AS owner, r.reminder_id, r.scheduled_time, r.is_sent, r.sent_at, r.is_acknowledged,
               r.acknowledged_at, r.is_dismissed, p.acknowledgment_mode
          FROM task t JOIN standard_user u ON u.user_id = t.user_id
          LEFT JOIN reminder r ON r.task_id = t.task_id
          LEFT JOIN notification_profile p ON p.profile_id = r.profile_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY CASE WHEN t.status = 'Pending' THEN 0 ELSE 1 END, t.due_date ASC LIMIT 100";
$st = $pdo->prepare($sql);
$st->execute($params);
$tasks = $st->fetchAll();

$log = $pdo->query('SELECT * FROM activity_log ORDER BY created_at DESC, log_id DESC LIMIT 8')->fetchAll();

page_start('Admin workspace', [
    'sub' => strtoupper(date('l, j F')) . ' · ADMIN VIEW', 'avatar' => true, 'wide' => true, 'screen' => '16 / Admin monitoring dashboard',
]);
?>
<section class="greet">
  <h2 class="h-greet"><?= h(greeting()) ?>, <?= h($acc['name']) ?>.</h2>
  <p class="muted">Monitor deliveries, reminder delivery and acknowledgments.</p>
</section>

<nav class="admin-nav" aria-label="Admin sections">
  <a class="tab is-active" href="<?= h(url('admin/dashboard.php')) ?>"><?= icon('list') ?> Dashboard</a>
  <a class="tab" href="<?= h(url('admin/profiles.php')) ?>"><?= icon('sliders') ?> Notification profiles</a>
  <a class="tab" href="<?= h(url('admin/users.php')) ?>"><?= icon('users') ?> Users</a>
  <a class="tab" href="<?= h(url('admin/audit.php')) ?>"><?= icon('clock') ?> Audit trail</a>
</nav>

<section class="stat-grid" aria-label="Key figures">
  <div class="stat"><span class="stat-label">Total tasks</span><span class="stat-num"><?= $stats['tasks'] ?></span><span class="stat-sub"><?= $stats['pending'] ?> pending · <?= $stats['completed'] ?> completed</span></div>
  <div class="stat"><span class="stat-label">Reminders sent</span><span class="stat-num"><?= $stats['sent'] ?></span><span class="stat-sub"><?= $ackRate ?>% acknowledged</span></div>
  <div class="stat<?= $stats['awaiting'] ? ' stat-alert' : '' ?>"><span class="stat-label">Awaiting acknowledgment</span><span class="stat-num"><?= $stats['awaiting'] ?></span><span class="stat-sub">High &amp; Critical, still ringing</span></div>
  <div class="stat<?= $stats['overdue'] ? ' stat-warn' : '' ?>"><span class="stat-label">Overdue</span><span class="stat-num"><?= $stats['overdue'] ?></span><span class="stat-sub">Pending past due time</span></div>
</section>

<section class="card">
  <div class="section-head"><h3 class="h-section">Delivery by priority tier</h3>
    <a class="btn-link" href="<?= h(url('admin/profiles.php')) ?>">Edit profiles <?= icon('chev', 16) ?></a></div>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Tier</th><th>Audio</th><th>Vibration</th><th>Acknowledgment</th><th class="num">Reminders</th><th class="num">Sent</th><th class="num">Acknowledged</th><th class="num">Dismissed</th></tr></thead>
    <tbody>
    <?php foreach ($tiers as $t): ?>
      <tr><td><?= priority_chip($t['priority_tier']) ?></td><td><?= h($t['audio_tone']) ?></td><td><?= h($t['vibration_pattern']) ?></td>
        <td><?= h(ack_label($t['acknowledgment_mode'])) ?></td><td class="num"><?= (int) $t['total'] ?></td><td class="num"><?= (int) $t['sent'] ?></td>
        <td class="num"><?= (int) $t['acked'] ?></td><td class="num"><?= (int) $t['dismissed'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="card">
  <div class="section-head"><h3 class="h-section">All tasks</h3><span class="muted small"><?= count($tasks) ?> shown</span></div>
  <form class="filters" method="get" role="search">
    <label class="sr-only" for="q">Search</label>
    <input class="input" id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="Search title or user">
    <label class="sr-only" for="fs">Status</label>
    <select class="input" id="fs" name="status"><option value="">All statuses</option>
      <?php foreach (['Pending', 'Completed'] as $s): ?><option <?= $fStatus === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="fp">Priority</label>
    <select class="input" id="fp" name="priority"><option value="">All priorities</option>
      <?php foreach (PRIORITIES as $p): ?><option <?= $fPri === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if ($fStatus || $fPri || $q !== ''): ?><a class="btn btn-ghost" href="<?= h(url('admin/dashboard.php')) ?>">Clear</a><?php endif; ?>
  </form>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Task</th><th>User</th><th>Priority</th><th>Due</th><th>Reminder</th><th>Reminder status</th><th>Task status</th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): $rs = reminder_status($t); ?>
      <tr>
        <td class="strong"><?= h($t['title']) ?></td>
        <td><?= h($t['owner']) ?></td>
        <td><?= priority_chip($t['priority_level']) ?></td>
        <td><?= h(fmt_relative_day($t['due_date'])) ?></td>
        <td><?= h(fmt_relative_day($t['scheduled_time'])) ?></td>
        <td><span class="rs rs-<?= h(strtolower(strtok($rs, ' '))) ?>"><?= h($rs) ?></span></td>
        <td><span class="chip <?= $t['status'] === 'Completed' ? 'chip-done' : 'chip-pending' ?>"><?= h($t['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$tasks): ?><tr><td colspan="7" class="muted center">No tasks match these filters.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="card">
  <div class="section-head"><h3 class="h-section">Recent activity</h3>
    <a class="btn-link" href="<?= h(url('admin/audit.php')) ?>">Full audit trail <?= icon('chev', 16) ?></a></div>
  <ol class="timeline">
    <?php foreach ($log as $a): ?>
      <li class="done"><span class="tl-dot"><?= icon('check', 14) ?></span>
        <div><b><?= h($a['details']) ?></b><span class="muted small"><?= h($a['actor_role']) ?> · <?= h(str_replace('_', ' ', $a['action'])) ?> · <?= h(fmt_relative_day($a['created_at'])) ?></span></div></li>
    <?php endforeach; ?>
  </ol>
</section>
<?php page_end(); ?>
