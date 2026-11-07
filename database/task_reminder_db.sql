-- =====================================================================
--  Task Reminder System  |  CPE 304L Final Exam  |  Group 13
--  Database: task_reminder_db  (MySQL 5.7+ / MariaDB 10.4+ via XAMPP)
--
--  Import: phpMyAdmin > Import > choose this file > Go
--     or : open http://localhost/task-reminder/setup.php (one click)
--
--  Demo accounts
--     Standard User : user@trs.com   / user123
--     Administrator : admin@trs.com  / admin123
-- =====================================================================

SET time_zone = '+08:00';
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS task_reminder_db;
CREATE DATABASE task_reminder_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE task_reminder_db;

-- ---------------------------------------------------------------------
--  system_administrator  (UML: SystemAdministrator)
-- ---------------------------------------------------------------------
CREATE TABLE system_administrator (
  admin_id       INT          NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(100) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  standard_user  (UML: StandardUser)
-- ---------------------------------------------------------------------
CREATE TABLE standard_user (
  user_id        INT          NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(100) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  notification_profile  (UML: NotificationProfile)  - one per tier
--  acknowledgment_mode refines the former requires_acknowledgment BOOLEAN
--  into NOT_REQUESTED / OPTIONAL / REQUIRED (FR-003).
-- ---------------------------------------------------------------------
CREATE TABLE notification_profile (
  profile_id          INT          NOT NULL AUTO_INCREMENT,
  admin_id            INT          NOT NULL,
  priority_tier       ENUM('Low','Normal','High','Critical') NOT NULL,
  audio_tone          VARCHAR(50)  NOT NULL,
  decibel_limit       INT          NOT NULL,
  vibration_pattern   VARCHAR(50)  NOT NULL,
  acknowledgment_mode ENUM('NOT_REQUESTED','OPTIONAL','REQUIRED') NOT NULL,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (profile_id),
  UNIQUE KEY uq_profile_tier (priority_tier),
  CONSTRAINT fk_profile_admin FOREIGN KEY (admin_id)
    REFERENCES system_administrator (admin_id),
  CONSTRAINT chk_decibel CHECK (decibel_limit BETWEEN 30 AND 110)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  task  (UML: Task)
-- ---------------------------------------------------------------------
CREATE TABLE task (
  task_id        INT          NOT NULL AUTO_INCREMENT,
  user_id        INT          NOT NULL,
  title          VARCHAR(100) NOT NULL,
  due_date       DATETIME     NOT NULL,
  priority_level ENUM('Low','Normal','High','Critical') NOT NULL,
  status         ENUM('Pending','Completed') NOT NULL DEFAULT 'Pending',
  completed_at   DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (task_id),
  KEY idx_task_user (user_id),
  CONSTRAINT fk_task_user FOREIGN KEY (user_id)
    REFERENCES standard_user (user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  reminder  (UML: Reminder)  - composed by Task, formatted by Profile
-- ---------------------------------------------------------------------
CREATE TABLE reminder (
  reminder_id      INT        NOT NULL AUTO_INCREMENT,
  task_id          INT        NOT NULL,
  profile_id       INT        NOT NULL,
  scheduled_time   DATETIME   NOT NULL,
  is_sent          TINYINT(1) NOT NULL DEFAULT 0,
  sent_at          DATETIME   NULL,
  is_acknowledged  TINYINT(1) NOT NULL DEFAULT 0,
  acknowledged_at  DATETIME   NULL,
  is_dismissed     TINYINT(1) NOT NULL DEFAULT 0,
  dismissed_at     DATETIME   NULL,
  -- closed_by_completion = 1: closed by "Mark completed", so Undo may restore it
  closed_by_completion TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (reminder_id),
  KEY idx_reminder_due (is_sent, scheduled_time),
  CONSTRAINT fk_reminder_task FOREIGN KEY (task_id)
    REFERENCES task (task_id) ON DELETE CASCADE,
  CONSTRAINT fk_reminder_profile FOREIGN KEY (profile_id)
    REFERENCES notification_profile (profile_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  activity_log  (new: audit trail for the Admin monitoring dashboard)
-- ---------------------------------------------------------------------
CREATE TABLE activity_log (
  log_id      INT          NOT NULL AUTO_INCREMENT,
  actor_role  ENUM('User','Admin','System') NOT NULL,
  actor_id    INT          NULL,
  task_id     INT          NULL,
  action      VARCHAR(40)  NOT NULL,
  details     VARCHAR(255) NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_log_task (task_id),
  KEY idx_log_time (created_at)
) ENGINE=InnoDB;

-- =====================================================================
--  Seed data (times are relative to the moment you import)
-- =====================================================================
INSERT INTO system_administrator (admin_id, name, email, password_hash) VALUES
 (1, 'Admin', 'admin@trs.com', '$2y$10$OmcmjrshbRAPbYaFFlYP7ubeLAFwnMd6T5V5zlAQ1JnuR0el/Sy/G');

INSERT INTO standard_user (user_id, name, email, password_hash) VALUES
 (1, 'User', 'user@trs.com', '$2y$10$NPJpp8ZVzdotl1NHmAHhF.EChE6aTvqzeFXzdR.HUSz4HOe4R1Lxm'),
 (2, 'Daren', 'daren@trs.com', '$2y$10$NPJpp8ZVzdotl1NHmAHhF.EChE6aTvqzeFXzdR.HUSz4HOe4R1Lxm');

INSERT INTO notification_profile
 (profile_id, admin_id, priority_tier, audio_tone, decibel_limit, vibration_pattern, acknowledgment_mode) VALUES
 (1, 1, 'Low',      'Soft chime',  50, 'Short pulse',     'NOT_REQUESTED'),
 (2, 1, 'Normal',   'Gentle bell', 60, 'Double pulse',    'OPTIONAL'),
 (3, 1, 'High',     'Clear chime', 75, 'Repeating pulse', 'REQUIRED'),
 (4, 1, 'Critical', 'Urgent tone', 90, 'Rapid pulse',     'REQUIRED');

INSERT INTO task (task_id, user_id, title, due_date, priority_level, status, completed_at, created_at, updated_at) VALUES
 (1, 1, 'Deliver order #1042',      NOW() + INTERVAL 3 HOUR,  'High',     'Pending',   NULL, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR),
 (2, 1, 'Reorder cement stock',     NOW() + INTERVAL 4 HOUR,  'Critical', 'Pending',   NULL, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR),
 (3, 1, 'Check fastener stock',     NOW() + INTERVAL 1 DAY,   'Normal',   'Pending',   NULL, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR),
 (4, 1, 'Count paint stock',        NOW() + INTERVAL 5 HOUR,  'Low',      'Pending',   NULL, NOW() - INTERVAL 30 MINUTE, NOW() - INTERVAL 30 MINUTE),
 (5, 1, 'Receive plywood shipment', NOW() - INTERVAL 1 DAY,   'Normal',   'Completed', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY),
 (6, 2, 'Restock PVC pipes',        NOW() + INTERVAL 6 HOUR,  'High',     'Pending',   NULL, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 3 HOUR);

INSERT INTO reminder (reminder_id, task_id, profile_id, scheduled_time, is_sent, sent_at, is_acknowledged, acknowledged_at, is_dismissed, dismissed_at) VALUES
 (1, 1, 3, NOW() + INTERVAL 150 MINUTE, 0, NULL, 0, NULL, 0, NULL),
 (2, 2, 4, NOW() + INTERVAL 2 MINUTE,   0, NULL, 0, NULL, 0, NULL),
 (3, 3, 2, NOW() + INTERVAL 1430 MINUTE,0, NULL, 0, NULL, 0, NULL),
 (4, 4, 1, NOW() + INTERVAL 270 MINUTE, 0, NULL, 0, NULL, 0, NULL),
 (5, 5, 2, NOW() - INTERVAL 1500 MINUTE,1, NOW() - INTERVAL 1500 MINUTE, 1, NOW() - INTERVAL 1495 MINUTE, 0, NULL),
 (6, 6, 3, NOW() + INTERVAL 330 MINUTE, 0, NULL, 0, NULL, 0, NULL);

INSERT INTO activity_log (actor_role, actor_id, task_id, action, details, created_at) VALUES
 ('Admin',  1, NULL, 'PROFILE_UPDATED',    'Seeded four notification profiles (Low, Normal, High, Critical)', NOW() - INTERVAL 3 DAY),
 ('User',   1, 5,    'TASK_CREATED',       'Created "Receive plywood shipment" (Normal)', NOW() - INTERVAL 2 DAY),
 ('System', NULL, 5, 'REMINDER_SENT',      'Normal reminder sent: Gentle bell, Double pulse', NOW() - INTERVAL 1500 MINUTE),
 ('User',   1, 5,    'REMINDER_ACKNOWLEDGED','Acknowledged reminder for "Receive plywood shipment"', NOW() - INTERVAL 1495 MINUTE),
 ('User',   1, 5,    'TASK_COMPLETED',     'Marked "Receive plywood shipment" as completed', NOW() - INTERVAL 1 DAY),
 ('User',   2, 6,    'TASK_CREATED',       'Created "Restock PVC pipes" (High)', NOW() - INTERVAL 3 HOUR),
 ('User',   1, 1,    'TASK_CREATED',       'Created "Deliver order #1042" (High)', NOW() - INTERVAL 2 HOUR),
 ('User',   1, 2,    'TASK_CREATED',       'Created "Reorder cement stock" (Critical)', NOW() - INTERVAL 1 HOUR),
 ('User',   1, 3,    'TASK_CREATED',       'Created "Check fastener stock" (Normal)', NOW() - INTERVAL 1 HOUR),
 ('User',   1, 4,    'TASK_CREATED',       'Created "Count paint stock" (Low)', NOW() - INTERVAL 30 MINUTE);
