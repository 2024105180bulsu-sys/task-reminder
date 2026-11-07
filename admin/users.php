<?php
/* Admin · User management (extended feature) — administrator assigns User accounts */
require_once __DIR__ . '/../includes/layout.php';
$acc = require_role('Admin');
$errors = [];
$form = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form['name']  = trim((string) ($_POST['name'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if ($form['name'] === '' || mb_strlen($form['name']) > 100) { $errors['name'] = 'Enter a name (up to 100 characters).'; }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL))      { $errors['email'] = 'Enter a valid email address.'; }
    if (strlen($pass) < 6)                                        { $errors['password'] = 'Use at least 6 characters.'; }
    if (!$errors) {
        $chk = db()->prepare('SELECT (SELECT COUNT(*) FROM standard_user WHERE email = ?) + (SELECT COUNT(*) FROM system_administrator WHERE email = ?)');
        $chk->execute([$form['email'], $form['email']]);
        if ((int) $chk->fetchColumn() > 0) {
            $errors['email'] = 'That email already has an account.';
        }
    }
    if (!$errors) {
        db()->prepare('INSERT INTO standard_user (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$form['name'], $form['email'], password_hash($pass, PASSWORD_BCRYPT), now_str()]);
        log_activity('Admin', (int) $acc['id'], null, 'USER_CREATED', 'Created Standard User ' . $form['name'] . ' <' . $form['email'] . '>');
        flash('success', '<b>User created</b> · ' . h($form['name']) . ' can now sign in.');
        redirect('admin/users.php');
    }
}

$users = db()->query(
    "SELECT u.user_id, u.name, u.email, u.created_at,
            COUNT(t.task_id) AS tasks,
            SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) AS pending
       FROM standard_user u LEFT JOIN task t ON t.user_id = u.user_id
      GROUP BY u.user_id, u.name, u.email, u.created_at ORDER BY u.name"
)->fetchAll();

page_start('Users', ['sub' => 'ADMINISTRATOR ONLY', 'back' => url('admin/dashboard.php'), 'wide' => true, 'screen' => 'Admin / Users']);
?>
<div class="two-col">
<section class="card">
  <h3 class="h-section">Standard Users</h3>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Email</th><th class="num">Tasks</th><th class="num">Pending</th><th>Created</th></tr></thead>
    <tbody><?php foreach ($users as $u): ?>
      <tr><td class="strong"><?= h($u['name']) ?></td><td><?= h($u['email']) ?></td><td class="num"><?= (int) $u['tasks'] ?></td><td class="num"><?= (int) $u['pending'] ?></td><td><?= h(fmt_dt($u['created_at'])) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<section class="card">
  <h3 class="h-section">Add a Standard User</h3>
  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <label class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><span class="label">Name</span>
      <input class="input" name="name" value="<?= h($form['name']) ?>" maxlength="100"><span class="field-msg"><?= h($errors['name'] ?? '') ?></span></label>
    <label class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><span class="label">Work email</span>
      <input class="input" type="email" name="email" value="<?= h($form['email']) ?>"><span class="field-msg"><?= h($errors['email'] ?? '') ?></span></label>
    <label class="field<?= isset($errors['password']) ? ' has-error' : '' ?>"><span class="label">Temporary password</span>
      <input class="input" type="password" name="password" autocomplete="new-password"><span class="field-msg"><?= h($errors['password'] ?? '') ?></span></label>
    <button class="btn btn-primary btn-block" type="submit"><?= icon('plus') ?> Create user</button>
  </form>
</section>
</div>
<?php page_end(); ?>
