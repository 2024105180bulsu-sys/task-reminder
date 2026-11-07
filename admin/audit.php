<?php
/* Admin · Audit trail (activity_log) — FR-003 / FR-005 traceability of every reminder event */
require_once __DIR__ . '/../includes/layout.php';
$acc = require_role('Admin');

$actions = db()->query('SELECT DISTINCT action FROM activity_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$fa = in_array($_GET['action'] ?? '', $actions, true) ? $_GET['action'] : '';
$fr = in_array($_GET['role'] ?? '', ['User', 'Admin', 'System'], true) ? $_GET['role'] : '';

$where = [];
$params = [];
if ($fa) { $where[] = 'l.action = ?'; $params[] = $fa; }
if ($fr) { $where[] = 'l.actor_role = ?'; $params[] = $fr; }
$st = db()->prepare(
    "SELECT l.*, CASE WHEN l.actor_role = 'Admin' THEN a.name WHEN l.actor_role = 'User' THEN u.name ELSE 'Scheduler' END AS actor_name
       FROM activity_log l
       LEFT JOIN standard_user u ON l.actor_role = 'User' AND u.user_id = l.actor_id
       LEFT JOIN system_administrator a ON l.actor_role = 'Admin' AND a.admin_id = l.actor_id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.created_at DESC, l.log_id DESC LIMIT 200'
);
$st->execute($params);
$rows = $st->fetchAll();

page_start('Audit trail', ['sub' => 'ADMINISTRATOR ONLY', 'back' => url('admin/dashboard.php'), 'wide' => true, 'screen' => 'Admin / Audit trail']);
?>
<section class="card">
  <form class="filters" method="get">
    <label class="sr-only" for="fa">Action</label>
    <select class="input" id="fa" name="action"><option value="">All actions</option>
      <?php foreach ($actions as $a): ?><option value="<?= h($a) ?>" <?= $fa === $a ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $a)) ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="fr">Actor</label>
    <select class="input" id="fr" name="role"><option value="">All actors</option>
      <?php foreach (['User', 'Admin', 'System'] as $r): ?><option <?= $fr === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if ($fa || $fr): ?><a class="btn btn-ghost" href="<?= h(url('admin/audit.php')) ?>">Clear</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Details</th><th class="num">Task #</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="nowrap"><?= h(fmt_dt($r['created_at'])) ?></td>
        <td><span class="chip chip-role"><?= h($r['actor_role']) ?></span> <?= h($r['actor_name'] ?? '') ?></td>
        <td class="nowrap"><code><?= h($r['action']) ?></code></td><td><?= h($r['details']) ?></td>
        <td class="num"><?= $r['task_id'] ? (int) $r['task_id'] : '—' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted center">No entries.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<?php page_end(); ?>
