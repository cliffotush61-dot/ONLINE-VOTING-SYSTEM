-- Online Voting System Database Schema
CREATE DATABASE IF NOT EXISTS online_voting_system;
USE online_voting_system;

-- Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample departments
INSERT IGNORE INTO departments (name) VALUES 
('Computer Science'),
('Business Administration'),
('Engineering'),
('Medicine'),
('Law'),
('Arts'),
('Science'),
('Education'),
('Social Sciences'),
('Agriculture');

-- Students Table
CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reg_number VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    department_id INT NOT NULL,
    has_voted TINYINT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin credentials
INSERT IGNORE INTO admins (username, password) VALUES 
('admin', '$2y$10$vyhZg0JtIgEoyzwLPebiAuIhOEL5NiLc0HFpJ1qZ/8VlCY5ingIZe');

-- Candidates Table
CREATE TABLE IF NOT EXISTS candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL UNIQUE,
    position VARCHAR(50) NOT NULL,
    gender VARCHAR(20),
    manifesto LONGTEXT,
    photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Votes Table
CREATE TABLE IF NOT EXISTS votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voter_id INT NOT NULL,
    candidate_id INT NOT NULL,
    timestamp DATETIME,
    vote_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES students(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id)
);

-- Audit Log Table
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action TEXT NOT NULL,
    action_hash VARCHAR(255),
    timestamp DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (created_at)
);

-- Create indexes for performance
CREATE INDEX idx_student_reg ON students(reg_number);
CREATE INDEX idx_student_voted ON students(has_voted);
CREATE INDEX idx_candidate_position ON candidates(position);
CREATE INDEX idx_vote_voter ON votes(voter_id);
CREATE INDEX idx_vote_candidate ON votes(candidate_id);
