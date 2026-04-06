-- Online Voting System Database Schema with Tamper Detection

CREATE DATABASE IF NOT EXISTS `ONLINE_VOTING_SYSTEM`;
USE `ONLINE_VOTING_SYSTEM`;

-- Departments table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_name` VARCHAR(100) UNIQUE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample departments
INSERT IGNORE INTO `departments` (`department_name`) VALUES
('Computer Science'),
('Information Technology'),
('Software Engineering'),
('Mechanical Engineering'),
('Civil Engineering'),
('Business Administration'),
('Finance'),
('Marketing'),
('Human Resources'),
('Law');

-- Students table
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reg_number` VARCHAR(50) UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255),
    `department` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE,
    `has_voted` BOOLEAN DEFAULT FALSE,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Candidates table
CREATE TABLE IF NOT EXISTS `candidates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `reg_number` VARCHAR(50) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `position` ENUM('male_delegate', 'female_delegate', 'departmental_delegate') NOT NULL,
    `gender` ENUM('Male', 'Female') NOT NULL,
    `manifesto` TEXT,
    `photo` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin users table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Votes table with tamper detection (no foreign keys initially)
CREATE TABLE IF NOT EXISTS `votes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `reg_number` VARCHAR(50) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `male_delegate_candidate_id` INT,
    `female_delegate_candidate_id` INT,
    `departmental_delegate_candidate_id` INT,
    `vote_hash` VARCHAR(128) NOT NULL, -- SHA-512 hash for tamper detection
    `salt` VARCHAR(32) NOT NULL, -- Random salt for hashing
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45)
);

-- Audit log table for tamper detection
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50),
    `record_id` INT,
    `user_id` INT,
    `user_type` ENUM('admin', 'student') NOT NULL,
    `old_values` TEXT,
    `new_values` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `action_hash` VARCHAR(128) NOT NULL -- Hash of the action for integrity
);

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO `admins` (`username`, `password_hash`, `email`) VALUES
('admin', '$2y$10$vyhZg0JtIgEoyzwLPebiAuIhOEL5NiLc0HFpJ1qZ/8VlCY5ingIZe', 'admin@school.edu');

-- Add foreign key constraints (after tables exist)
ALTER TABLE `votes` ADD CONSTRAINT `fk_votes_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`);
ALTER TABLE `votes` ADD CONSTRAINT `fk_votes_male` FOREIGN KEY (`male_delegate_candidate_id`) REFERENCES `candidates`(`id`);
ALTER TABLE `votes` ADD CONSTRAINT `fk_votes_female` FOREIGN KEY (`female_delegate_candidate_id`) REFERENCES `candidates`(`id`);
ALTER TABLE `votes` ADD CONSTRAINT `fk_votes_dept` FOREIGN KEY (`departmental_delegate_candidate_id`) REFERENCES `candidates`(`id`);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_votes_student ON votes(student_id);
CREATE INDEX IF NOT EXISTS idx_votes_created_at ON votes(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action);
