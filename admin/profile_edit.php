<?php
/* Screens 18 / 19 · Configure delivery + Sound & vibration — FR-002 (NotificationProfile.configureAudio / configureVibration) */
require_once __DIR__ . '/../includes/layout.php';
$acc = require_role('Admin');

const AUDIO_TONES = [
    'Soft chime'  => 'A short, subtle note',
    'Gentle bell' => 'A warm, two-note bell',
    'Clear chime' => 'A crisp, distinct chime',
    'Urgent tone' => 'A fast, alternating alarm',
    'Alarm siren' => 'A rising, siren-like sweep',
];
const VIBRATIONS = [
    'Short pulse'     => 'One brief buzz',
    'Double pulse'    => 'Two quick buzzes',
    'Repeating pulse' => 'Steady repeat until handled',
    'Rapid pulse'     => 'Fast, urgent bursts until handled',
    'Long buzz'       => 'One long, firm buzz',
];

$id = (int) ($_GET['id'] ?? $_POST['profile_id'] ?? 0);
$st = db()->prepare('SELECT * FROM notification_profile WHERE profile_id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    redirect('admin/profiles.php');
}
$locked = in_array($p['priority_tier'], ['High', 'Critical'], true);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $audio = (string) ($_POST['audio_tone'] ?? '');
    $vib   = (string) ($_POST['vibration_pattern'] ?? '');
    $db    = (int) ($_POST['decibel_limit'] ?? 0);
    $ack   = $locked ? 'REQUIRED' : (string) ($_POST['acknowledgment_mode'] ?? '');

    if (!isset(AUDIO_TONES[$audio]))  { $errors[] = 'Choose an audio tone.'; }
    if (!isset(VIBRATIONS[$vib]))     { $errors[] = 'Choose a vibration pattern.'; }
    if ($db < 40 || $db > 100)        { $errors[] = 'Volume must be between 40 and 100 dB.'; }
    if (!$locked && !in_array($ack, ['NOT_REQUESTED', 'OPTIONAL'], true)) { $errors[] = 'Choose an acknowledgment setting.'; }
    // FR-002: every tier must stay distinguishable, so no two tiers may share the same vibration pattern or tone.
    if (!$errors) {
        $dup = db()->prepare('SELECT priority_tier, audio_tone, vibration_pattern FROM notification_profile
                              WHERE profile_id <> ? AND (audio_tone = ? OR vibration_pattern = ?)');
        $dup->execute([$id, $audio, $vib]);
        foreach ($dup->fetchAll() as $o) {
            if ($o['audio_tone'] === $audio)       { $errors[] = 'The ' . $o['priority_tier'] . ' profile already uses "' . $audio . '". Each tier needs its own tone.'; }
            if ($o['vibration_pattern'] === $vib)  { $errors[] = 'The ' . $o['priority_tier'] . ' profile already uses "' . $vib . '". Each tier needs its own vibration pattern.'; }
        }
    }

    if (!$errors) {
        db()->prepare('UPDATE notification_profile SET audio_tone = ?, vibration_pattern = ?, decibel_limit = ?,
                       acknowledgment_mode = ?, admin_id = ?, updated_at = ? WHERE profile_id = ?')
            ->execute([$audio, $vib, $db, $ack, (int) $acc['id'], now_str(), $id]);
        log_activity('Admin', (int) $acc['id'], null, 'PROFILE_UPDATED',
            sprintf('%s profile: %s %d dB, %s, acknowledgment %s', $p['priority_tier'], $audio, $db, $vib, strtolower(ack_label($ack))));
        flash('success', '<b>Configuration saved</b> · ' . h($p['priority_tier']) . ' profile updated. New reminders use it immediately.');
        redirect('admin/profiles.php');
    }
    $p = array_merge($p, ['audio_tone' => $audio, 'vibration_pattern' => $vib, 'decibel_limit' => $db, 'acknowledgment_mode' => $ack]);
}

page_start('Edit ' . $p['priority_tier'] . ' profile', ['sub' => 'ADMINISTRATOR ONLY', 'back' => url('admin/profiles.php'), 'screen' => '18 / Configure delivery']);
?>
<div class="chip-row"><?= priority_chip($p['priority_tier']) ?><span class="chip chip-outline" id="dirty-chip" hidden>Unsaved changes</span></div>

<?php if ($errors): ?>
  <div class="banner banner-error" role="alert"><?= icon('alert') ?><div><b>Check the highlighted settings</b><span><?= h(implode(' ', $errors)) ?></span></div></div>
<?php endif; ?>

<form method="post" class="form" id="profile-form">
  <?= csrf_field() ?>
  <input type="hidden" name="profile_id" value="<?= $id ?>">

  <fieldset class="card fieldset">
    <legend class="h-section"><?= icon('audio') ?> Audio</legend>
    <?php foreach (AUDIO_TONES as $tone => $desc): ?>
      <label class="radio-row">
        <input type="radio" name="audio_tone" value="<?= h($tone) ?>" <?= $p['audio_tone'] === $tone ? 'checked' : '' ?>>
        <span><b><?= h($tone) ?></b><span class="muted small"><?= h($desc) ?></span></span>
        <button type="button" class="icon-btn icon-btn-soft" data-preview-tone="<?= h($tone) ?>" aria-label="Play <?= h($tone) ?>"><?= icon('play', 18) ?></button>
      </label>
    <?php endforeach; ?>
    <label class="field">
      <span class="label">Volume limit <output id="db-out"><?= (int) $p['decibel_limit'] ?></output> dB</span>
      <input type="range" class="range" name="decibel_limit" min="40" max="100" step="5" value="<?= (int) $p['decibel_limit'] ?>" id="db-range">
    </label>
  </fieldset>

  <fieldset class="card fieldset">
    <legend class="h-section"><?= icon('vibrate') ?> Vibration</legend>
    <?php foreach (VIBRATIONS as $v => $desc): ?>
      <label class="radio-row">
        <input type="radio" name="vibration_pattern" value="<?= h($v) ?>" <?= $p['vibration_pattern'] === $v ? 'checked' : '' ?>>
        <span><b><?= h($v) ?></b><span class="muted small"><?= h($desc) ?></span></span>
        <button type="button" class="icon-btn icon-btn-soft" data-preview-vib="<?= h($v) ?>" aria-label="Test <?= h($v) ?>"><?= icon('play', 18) ?></button>
      </label>
    <?php endforeach; ?>
    <p class="muted small">Audio and vibration start together when the reminder is sent. Vibration works on phones that support it.</p>
  </fieldset>

  <fieldset class="card fieldset">
    <legend class="h-section"><?= icon('shield') ?> Acknowledgment</legend>
    <?php if ($locked): ?>
      <label class="radio-row is-locked"><input type="radio" checked disabled><span><b>Required</b><span class="muted small">The alert stays active until the user acknowledges it.</span></span><?= icon('lock', 18) ?></label>
    <?php else: ?>
      <label class="radio-row"><input type="radio" name="acknowledgment_mode" value="NOT_REQUESTED" <?= $p['acknowledgment_mode'] === 'NOT_REQUESTED' ? 'checked' : '' ?>><span><b>Not requested</b><span class="muted small">Dismiss without acknowledgment.</span></span></label>
      <label class="radio-row"><input type="radio" name="acknowledgment_mode" value="OPTIONAL" <?= $p['acknowledgment_mode'] === 'OPTIONAL' ? 'checked' : '' ?>><span><b>Optional</b><span class="muted small">Dismiss or acknowledge the reminder.</span></span></label>
    <?php endif; ?>
  </fieldset>

  <div class="banner banner-info"><?= icon('lock') ?><div><b>Urgent priorities stay protected</b><span>High and Critical always require acknowledgment. That setting cannot be changed (FR-003).</span></div></div>

  <div class="sticky-cta">
    <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Save profile</button>
    <a class="btn btn-soft btn-block" href="<?= h(url('admin/profiles.php')) ?>">Cancel</a>
  </div>
</form>
<?php page_end(); ?>
