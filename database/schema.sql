-- RFID Attendance Management System Database Schema
-- Compatible with XAMPP MySQL / MariaDB

CREATE DATABASE IF NOT EXISTS `attendance_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `attendance_db`;

-- 1. Admin Users Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Admin (Username: admin, Password: admin123)
INSERT INTO `admins` (`username`, `password`, `fullname`, `email`) VALUES
('admin', '$2y$10$e8T7W/zKq80/UuV.6O6MvOHPzK.8X6gUqW.xU.Tq/F7qKkP.e', 'System Administrator', 'admin@school.edu')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 2. System Mode & Device Control Table (REGISTRATION vs ATTENDANCE mode)
CREATE TABLE IF NOT EXISTS `system_mode` (
    `id` INT PRIMARY KEY DEFAULT 1,
    `device_mode` ENUM('ATTENDANCE', 'REGISTRATION') DEFAULT 'ATTENDANCE',
    `device_token` VARCHAR(50) DEFAULT '19453d39b4edd4e6',
    `last_scanned_uid` VARCHAR(50) NULL,
    `last_scanned_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default single record
INSERT INTO `system_mode` (`id`, `device_mode`, `device_token`) VALUES
(1, 'ATTENDANCE', '19453d39b4edd4e6')
ON DUPLICATE KEY UPDATE `device_token`='19453d39b4edd4e6';

-- 3. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dept_code` VARCHAR(20) NOT NULL UNIQUE,
    `dept_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `departments` (`dept_code`, `dept_name`, `description`) VALUES
('CSE', 'Computer Science & Engineering', 'Department of Computer Science'),
('EEE', 'Electrical & Electronic Engineering', 'Department of EEE'),
('BBA', 'Business Administration', 'School of Business'),
('ME', 'Mechanical Engineering', 'Department of Mechanical Engineering')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 4. Students Table
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(30) NOT NULL UNIQUE,
    `card_uid` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `dept_id` INT NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`dept_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `students` (`student_id`, `card_uid`, `name`, `email`, `phone`, `dept_id`, `gender`) VALUES
('CSE-2024-001', 'A3B4C5D6', 'Tanvir Ahmed', 'tanvir@student.edu', '01711000001', 1, 'Male'),
('CSE-2024-002', '14253647', 'Nusrat Jahan', 'nusrat@student.edu', '01811000002', 1, 'Female'),
('EEE-2024-010', '98876655', 'Rahim Uddin', 'rahim@student.edu', '01911000003', 2, 'Male'),
('BBA-2024-005', 'D1E2F304', 'Sadia Islam', 'sadia@student.edu', '01611000004', 3, 'Female')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 5. Attendance Table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `in_time` TIME NOT NULL,
    `out_time` TIME NULL,
    `status` ENUM('On Time', 'Late', 'Checked Out') DEFAULT 'On Time',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_student_date` (`student_id`, `date`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Raw RFID Scan Logs Table
CREATE TABLE IF NOT EXISTS `rfid_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `card_uid` VARCHAR(50) NOT NULL,
    `student_name` VARCHAR(100) NULL,
    `scan_type` ENUM('IN', 'OUT', 'REGISTRATION', 'INVALID') NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `scanned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. System Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('late_time_threshold', '09:30:00'),
('telegram_bot_token', ''),
('telegram_chat_id', ''),
('telegram_enabled', '1')
ON DUPLICATE KEY UPDATE `setting_value`=`setting_value`;
