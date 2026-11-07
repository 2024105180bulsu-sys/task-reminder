<?php
/* Screens 03 / 04 / 05 · Create or edit reminder (step 1 of 2) — FR-001, FR-004, FR-005 */
require_once __DIR__ . '/includes/layout.php';
$acc = require_role('User');
$profiles = get_profiles();

$editId = (int) ($_GET['id'] ?? $_POST['task_id'] ?? 0);
$data = ['task_id' => 0, 'title' => '', 'due_date' => '', 'reminder_time' => '', 'priority' => ''];
$errors = [];
$submitted = false;

if ($editId) {
    $task = find_user_task($editId, (int) $acc['id']);
    if (!$task) {
        flash('error', 'That task could not be found.');
        redirect('dashboard.php');
    }
    if (has_unacknowledged_required_alert($editId)) {
        flash('error', '<b>Acknowledge the active alert first</b> · ' . h($task['title']) . ' cannot be edited while its ' . h($task['priority_level']) . ' alert is unacknowledged.');
        redirect('task_view.php?id=' . $editId);
    }
    if ($task['status'] === 'Completed') {
        flash('info', 'Reopen the task before editing it.');
        redirect('task_view.php?id=' . $editId);
    }
    $data = [
        'task_id'       => $editId,
        'title'         => $task['title'],
        'due_date'      => dt_input($task['due_date']),
        'reminder_time' => dt_input($task['scheduled_time']),
        'priority'      => $task['priority_level'],
    ];
}

// Returning from Review → "Edit details" keeps the unsaved draft.
if (isset($_GET['draft']) && !empty($_SESSION['draft'])) {
    $data = $_SESSION['draft'];
    $editId = (int) $data['task_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $submitted = true;
    $data = [
        'task_id'       => $editId,
        'title'         => trim((string) ($_POST['title'] ?? '')),
        'due_date'      => (string) ($_POST['due_date'] ?? ''),
        'reminder_time' => (string) ($_POST['reminder_time'] ?? ''),
        'priority'      => (string) ($_POST['priority'] ?? ''),
    ];

    $len = mb_strlen($data['title']);
    if ($len === 0) {
        $errors['title'] = 'Enter a delivery or stock task.';
    } elseif ($len < 3 || $len > 100) {
        $errors['title'] = 'Use 3 to 100 characters.';
    }

    $due = $data['due_date'] !== '' ? strtotime($data['due_date']) : false;
    $rem = $data['reminder_time'] !== '' ? strtotime($data['reminder_time']) : false;

    if (!$due) {
        $errors['due_date'] = 'Choose a due date and time.';
    } elseif ($due <= time()) {
        $errors['due_date'] = 'Choose a future due date and time.';
    }

    if (!$rem) {
        $errors['reminder_time'] = 'Choose when to be reminded.';
    } elseif ($due && $rem >= $due) {
        $errors['reminder_time'] = 'Set the reminder before the due time.';
    } elseif ($rem < time() - 60) {
        $errors['reminder_time'] = 'Choose a reminder time that has not passed.';
    }

    if (!in_array($data['priority'], PRIORITIES, true)) {
        $errors['priority'] = 'Select a priority level.';
    }

    if (!$errors) {
        $_SESSION['draft'] = $data;
        redirect('task_review.php');
    }
}

$isEdit = $editId > 0;
$hasErrors = (bool) $errors;
page_start($isEdit ? 'Edit reminder' : 'Create reminder', [
    'sub'    => 'STEP 1 OF 2 · DETAILS',
    'back'   => $isEdit ? url('task_view.php?id=' . $editId) : url('dashboard.php'),
    'poll'   => true,
    'screen' => $hasErrors ? '04 / Validation errors' : ($isEdit ? '05 / Edit details' : '03 / Create a task'),
]);
?>
<p class="muted">Plan a delivery, stock check or reorder reminder.</p>

<?php if ($hasErrors): ?>
  <div class="banner banner-error" role="alert" id="error-summary" tabindex="-1">
    <?= icon('alert') ?>
    <div><b>Check the highlighted fields</b><span>Your task has not been saved. Fix <?= count($errors) ?> field<?= count($errors) > 1 ? 's' : '' ?> and select <i>Validate again</i>.</span></div>
  </div>
<?php endif; ?>

<form method="post" class="form" id="task-form" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="task_id" value="<?= (int) $editId ?>">

  <label class="field<?= isset($errors['title']) ? ' has-error' : '' ?>">
    <span class="label">Title</span>
    <input class="input" type="text" name="title" maxlength="100" value="<?= h($data['title']) ?>" placeholder="e.g. Deliver order #1042" data-rule="title"
           aria-invalid="<?= isset($errors['title']) ? 'true' : 'false' ?>" aria-describedby="e-title">
    <span class="field-msg" id="e-title"><?= h($errors['title'] ?? '') ?></span>
  </label>

  <label class="field<?= isset($errors['due_date']) ? ' has-error' : '' ?>">
    <span class="label">Due date &amp; time</span>
    <span class="input-wrap">
      <input class="input" type="datetime-local" name="due_date" value="<?= h($data['due_date']) ?>" data-rule="due"
             aria-invalid="<?= isset($errors['due_date']) ? 'true' : 'false' ?>" aria-describedby="e-due">
      <span class="input-icon"><?= icon('cal') ?></span>
    </span>
    <span class="field-msg" id="e-due"><?= h($errors['due_date'] ?? '') ?></span>
  </label>

  <label class="field<?= isset($errors['reminder_time']) ? ' has-error' : '' ?>">
    <span class="label">Reminder time</span>
    <span class="input-wrap">
      <input class="input" type="datetime-local" name="reminder_time" value="<?= h($data['reminder_time']) ?>" data-rule="rem"
             aria-invalid="<?= isset($errors['reminder_time']) ? 'true' : 'false' ?>" aria-describedby="e-rem">
      <span class="input-icon"><?= icon('bell') ?></span>
    </span>
    <span class="field-msg" id="e-rem"><?= h($errors['reminder_time'] ?? '') ?></span>
    <span class="quick-picks" aria-label="Quick reminder presets">
      <button type="button" class="pill" data-remind-in="1">In 1 min</button>
      <button type="button" class="pill" data-remind-before="30">30 min before due</button>
      <button type="button" class="pill" data-remind-before="60">1 hr before due</button>
    </span>
  </label>

  <fieldset class="field<?= isset($errors['priority']) ? ' has-error' : '' ?>">
    <legend class="label">Priority</legend>
    <div class="segmented" role="radiogroup">
      <?php foreach (PRIORITIES as $p): $pr = $profiles[$p] ?? null; ?>
        <label class="seg seg-<?= strtolower($p) ?>">
          <input type="radio" name="priority" value="<?= h($p) ?>" <?= $data['priority'] === $p ? 'checked' : '' ?>
                 data-ack="<?= h($pr['acknowledgment_mode'] ?? '') ?>"
                 data-audio="<?= h($pr['audio_tone'] ?? '') ?>" data-vib="<?= h($pr['vibration_pattern'] ?? '') ?>">
          <span><?= h($p) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <span class="field-msg" id="e-pri"><?= h($errors['priority'] ?? '') ?></span>
    <span class="hint" id="priority-hint">Set the delivery or stock urgency.</span>
  </fieldset>

  <div class="banner banner-info" id="ready-box" hidden>
    <?= icon('check') ?>
    <div><b>Ready for review</b><span id="ready-text">All four fields are valid.</span></div>
  </div>

  <div class="sticky-cta">
    <!-- B-01: "Validate again" is enabled, primary-styled and submits the corrected form -->
    <button class="btn btn-primary btn-block" type="submit">
      <?= icon('arrow') ?> <?= $hasErrors ? 'Validate again' : ($isEdit ? 'Review changes' : 'Review reminder') ?>
    </button>
    <p class="muted small center">Nothing is saved until you confirm.</p>
  </div>
</form>
<?php page_end(); ?>
