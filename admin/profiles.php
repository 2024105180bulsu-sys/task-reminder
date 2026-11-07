<?php
/* Screen 17 · Notification profiles (Administrator only) — FR-002, FR-003 */
require_once __DIR__ . '/../includes/layout.php';
$acc = require_role('Admin');
$profiles = get_profiles();

page_start('Notification profiles', ['sub' => 'ADMINISTRATOR ONLY', 'back' => url('admin/dashboard.php'), 'screen' => '17 / Notification profiles']);
?>
<p class="muted">Set alert priorities for hardware deliveries and stock reminders.</p>

<ul class="profile-list">
<?php foreach ($profiles as $tier => $p): $locked = in_array($tier, ['High', 'Critical'], true); ?>
  <li>
    <a class="card profile-card" href="<?= h(url('admin/profile_edit.php?id=' . (int) $p['profile_id'])) ?>">
      <span class="profile-top"><?= priority_chip($tier) ?><span class="task-chev"><?= icon('chev') ?></span></span>
      <span class="profile-line"><?= icon('audio', 18) ?> <?= h($p['audio_tone']) ?> · <?= (int) $p['decibel_limit'] ?> dB</span>
      <span class="profile-line"><?= icon('vibrate', 18) ?> <?= h($p['vibration_pattern']) ?></span>
      <span class="profile-line"><?= icon($locked ? 'lock' : 'shield', 18) ?> Acknowledgment: <?= h(ack_label($p['acknowledgment_mode'])) ?><?= $locked ? ' · Locked' : '' ?></span>
      <span class="muted small">Updated <?= h(fmt_relative_day($p['updated_at'])) ?></span>
    </a>
  </li>
<?php endforeach; ?>
</ul>
<p class="muted small">Audio and vibration are used for every priority. High and Critical cannot be dismissed without acknowledgment.</p>

<div class="sticky-cta">
  <a class="btn btn-soft btn-block" href="<?= h(url('admin/dashboard.php')) ?>">Back to dashboard</a>
</div>
<?php page_end(); ?>
