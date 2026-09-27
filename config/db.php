<?php
// Config - Database Connection & Global Variables

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP MySQL password is empty
define('DB_NAME', 'attendance_db');
define('DB_PORT', 3306);

// Timezone Setup
date_default_timezone_set('Asia/Dhaka'); // Configured for Bangladesh timezone (GMT+6)

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Return friendly error if database connection fails
    die("Database Connection Error: " . $e->getMessage() . "<br><small>Make sure XAMPP MySQL server is running and database 'attendance_db' is imported.</small>");
}

// Helper Function: Check Auth
function check_admin_auth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: auth/login.php");
        exit();
    }
}

// Helper Function: Sanitize Input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>
