# Task Reminder System — CPE 304L Final Exam (Group 13)

A priority-aware task reminder web application built with **PHP 8 + MySQL (XAMPP)** and vanilla JavaScript. There is no build step.

- **Standard User:** create, edit, complete (confirm + undo) and delete tasks; assign a priority (Low, Normal, High, Critical); schedule reminders; and receive tier-specific alerts (tone, volume, vibration). High and Critical alerts must be acknowledged before the task can be edited, completed or deleted.
- **System Administrator:** monitoring dashboard, notification profile editor, user management and audit trail.

## Requirements

- [XAMPP](https://www.apachefriends.org/) with PHP 7.4 or newer (any current version works)
- MySQL/MariaDB at XAMPP's default settings: user `root`, no password, port `3306`
- A modern browser (Chrome, Edge or Firefox). No internet connection is needed.

## Run it on XAMPP (Windows)

1. Open the **XAMPP Control Panel** and **Start** both **Apache** and **MySQL**.
2. Get the code into `C:\xampp\htdocs\task-reminder\`:
   - **Recommended:** download **`task-reminder.zip`** from this repository's **Releases** page (v1.0) and extract it into `C:\xampp\htdocs\`. It already contains a folder named `task-reminder`.
   - **Or with Git:** open a terminal in `C:\xampp\htdocs\` and run
     `git clone https://github.com/2024105180bulsu-sys/task-reminder.git`

   **Check:** `C:\xampp\htdocs\task-reminder\index.php` must exist (not `...\task-reminder\task-reminder\index.php`).
3. Open <http://localhost/task-reminder/setup.php> and select **Install database**.
   *(Alternative: phpMyAdmin → Import → `database/task_reminder_db.sql` → Go.)*
4. Open <http://localhost/task-reminder/> and sign in:

| Role | Email | Password |
|---|---|---|
| Standard User | `user@trs.com` | `user123` |
| Administrator | `admin@trs.com` | `admin123` |

> **Used the green "Code → Download ZIP" button instead?** That ZIP extracts to `task-reminder-main`. Rename the folder to `task-reminder`, or keep the name and use `http://localhost/task-reminder-main/` instead. Any folder name works.

## Tips

- Click anywhere once after signing in. Browsers block sound until the page has been clicked.
- A **Critical** "Reorder cement stock" alert fires about 2 minutes after installation.
- To see any alert right away, open a task and select **Send this reminder now (test)**.
- To reset the demo data, open `setup.php` again and select **Reset demo database**.
- If MySQL has a password or uses a different port, edit `config/config.php` (`DB_USER`, `DB_PASS`, `DB_PORT`).

## Troubleshooting

| Problem | Fix |
|---|---|
| `localhost/task-reminder/` shows "Not Found" | The folder isn't at `C:\xampp\htdocs\task-reminder\`. Check step 2. |
| "Database not ready" page | Start **MySQL** in XAMPP, then run `setup.php`. |
| No sound on alerts | Click the page once, or the "Tap to enable alert sound" button. |
| Apache or MySQL won't start | Another program is using port 80 or 3306. Close it (often Skype or another MySQL) or change the port in XAMPP. |

## Project structure

```
index.php  login.php  logout.php           entry + authentication
dashboard.php                              02 User workspace
task_form.php                              03/04/05 Create · Validation · Edit
task_review.php                            06 Review & confirm
task_saved.php                             07 Reminder scheduled
task_view.php                              14 Reminder details
task_action.php                            complete / undo / delete / send-now
api/poll.php                               reminder dispatcher (every 5 s)
api/reminder_action.php                    acknowledge / dismiss (FR-003 enforced)
admin/dashboard.php                        16 Monitoring dashboard (View 3)
admin/profiles.php, admin/profile_edit.php 17/18/19 Notification profiles
admin/users.php, admin/audit.php           Users, Audit trail
includes/  config/  assets/  database/     shared code, config, CSS/JS, SQL
setup.php                                  one-click installer
```
