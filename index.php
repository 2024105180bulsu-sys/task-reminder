<?php
require_once __DIR__ . '/includes/functions.php';

$acc = current_account();
if (!$acc) {
    redirect('login.php');
}
redirect($acc['role'] === 'Admin' ? 'admin/dashboard.php' : 'dashboard.php');
