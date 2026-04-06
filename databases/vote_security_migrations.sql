-- Secure Voting System migrations
-- Apply on an existing database. The app also auto-applies the same structure on localhost.

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_role` ENUM('admin','student') NOT NULL,
    `user_id` INT NULL,
    `username_or_reg` VARCHAR(100) NOT NULL,
    `action_type` VARCHAR(120) NOT NULL,
    `action_description` TEXT NOT NULL,
    `affected_table` VARCHAR(64) NULL,
    `affected_record_id` INT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NOT NULL,
    `previous_hash` CHAR(64) NOT NULL DEFAULT '',
    `record_hash` CHAR(64) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `elections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NOT NULL,
    `status` ENUM('scheduled','open','closed') NOT NULL DEFAULT 'scheduled',
    `manual_override` ENUM('none','force_open','force_close') NOT NULL DEFAULT 'none',
    `live_results_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `record_hash` CHAR(64) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `students`
    ADD COLUMN `record_hash` CHAR(64) NOT NULL DEFAULT '';

ALTER TABLE `students`
    ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `students`
    ADD UNIQUE KEY `uniq_students_reg_number` (`reg_number`);

ALTER TABLE `students`
    ADD UNIQUE KEY `uniq_students_email` (`email`);

ALTER TABLE `candidates`
    ADD COLUMN `record_hash` CHAR(64) NOT NULL DEFAULT '';

ALTER TABLE `candidates`
    ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `votes`
    ADD COLUMN `previous_hash` CHAR(64) NOT NULL DEFAULT '';

ALTER TABLE `votes`
    ADD COLUMN `record_hash` CHAR(64) NOT NULL DEFAULT '';

ALTER TABLE `elections`
    ADD COLUMN `manual_override` ENUM('none','force_open','force_close') NOT NULL DEFAULT 'none';

ALTER TABLE `elections`
    ADD COLUMN `live_results_enabled` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `elections`
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE `elections`
    ADD COLUMN `record_hash` CHAR(64) NOT NULL DEFAULT '';

CREATE INDEX `idx_audit_logs_created_at` ON `audit_logs` (`created_at`);
CREATE INDEX `idx_audit_logs_role` ON `audit_logs` (`user_role`);
CREATE INDEX `idx_audit_logs_action` ON `audit_logs` (`action_type`);
CREATE INDEX `idx_votes_hash` ON `votes` (`record_hash`);
CREATE INDEX `idx_elections_status` ON `elections` (`status`);
