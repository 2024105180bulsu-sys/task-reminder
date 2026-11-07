# Task Reminder System — CPE 304L Final Exam (Group 13)

A priority-aware task reminder web application. **PHP 8 + MySQL (XAMPP)**, vanilla JS, no build step.

- **Standard User:** create / edit / complete (confirm + undo) / delete tasks, assign priority (Low, Normal, High, Critical), schedule reminders, and receive tier-specific alerts (tone, volume, vibration). High and Critical alerts must be acknowledged.
- **System Administrator:** monitoring dashboard, notification profile editor, user management, audit trail.

## Run it on XAMPP (Windows)

1. Open the **XAMPP Control Panel** and **Start** both **Apache** and **MySQL**.
2. Copy this `task-reminder` folder into `C:\xampp\htdocs\`, so the path is `C:\xampp\htdocs\task-reminder\index.php`.
3. Open <http://localhost/task-reminder/setup.php> and select **Install database**.
   *(Alternative: phpMyAdmin → Import → `database/task_reminder_db.sql` → Go.)*
4. Open <http://localhost/task-reminder/> and sign in:

| Role | Email | Password |
|---|---|---|
| Standard User | `user@trs.com` | `user123` |
| Administrator | `admin@trs.com` | `admin123` |

**Tips**
- Click anywhere once after signing in. Browsers block sound until the page has been clicked.
- A **Critical** "Reorder cement stock" alert fires about 2 minutes after installation.
- To see any alert right away, open a task and select **Send this reminder now (test)**.
- To reset the demo data, open `setup.php` again and select **Reset demo database**.
- If MySQL has a password or uses a different port, edit `config/config.php`.

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