<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acc = current_account();
    if ($acc) {
        log_activity($acc['role'], (int) $acc['id'], null, 'SIGNED_OUT', $acc['name'] . ' signed out');
    }
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'You have signed out.');
}
redirect('login.php');
