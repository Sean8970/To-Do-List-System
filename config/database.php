<?php
if(session_status() === PHP_SESSION_NONE) session_start();

// Function to create a password reset token
function createPasswordResetToken($conn, $user_id) {
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $sql = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $token, $expires_at);
        if (mysqli_stmt_execute($stmt)) {
            return $token;
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

// Function to validate a password reset token
function validateResetToken($conn, $token) {
    $sql = "SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() AND is_used = 0";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
        return mysqli_fetch_assoc($result);
    }
    return false;
}

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'to_do_list_system');

// Attempt to connect to MySQL server
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if($conn === false){
    die("ERROR: Could not connect to MySQL server. " . mysqli_connect_error());
}

// Select the database
if (!mysqli_select_db($conn, DB_NAME)) {
    // If database doesn't exist, create it
    if (mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
        mysqli_select_db($conn, DB_NAME);
    } else {
        die("ERROR: Could not create database. " . mysqli_error($conn));
    }
}

// SQL to create users table without profile picture
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// SQL to create tasks table
$sql_tasks = "CREATE TABLE IF NOT EXISTS tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATETIME NOT NULL,
    reminder_time DATETIME,
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    status ENUM('Pending', 'Ongoing', 'Completed') DEFAULT 'Pending',
    category VARCHAR(50),
    is_archived BOOLEAN DEFAULT FALSE,
    archived_date DATETIME DEFAULT NULL,
    completed_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

// SQL to create reminders table
$sql_reminders = "CREATE TABLE IF NOT EXISTS reminders (
    reminder_id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT,
    reminder_time DATETIME NOT NULL,
    is_triggered BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
)";

// SQL to create password_resets table
$sql_password_resets = "CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_token (token),
    INDEX idx_expires (expires_at)
)";

// Execute each SQL statement
$sql_statements = [$sql_users, $sql_tasks, $sql_reminders, $sql_password_resets];
foreach ($sql_statements as $sql) {
    mysqli_query($conn, $sql);
}
?>
