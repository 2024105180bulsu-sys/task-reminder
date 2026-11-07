<?php
/**
 * Task Reminder System - configuration
 * Edit the DB_* values if your XAMPP MySQL uses a different user/password/port.
 */
date_default_timezone_set('Asia/Manila');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'task_reminder_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default: empty password

define('APP_NAME', 'Task Reminder System');
define('APP_TAGLINE', 'Deliveries & stock reminders');
define('POLL_INTERVAL_MS', 5000); // NFR-001: deliver within 5 seconds

// Base URL of the app (works in /htdocs/task-reminder or any sub-folder).
$__dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if (preg_match('#/(admin|api)$#', $__dir)) {
    $__dir = dirname($__dir);
}
define('BASE_URL', rtrim(str_replace('\\', '/', $__dir), '/'));
unset($__dir);
