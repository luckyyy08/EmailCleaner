<?php
$servername = "localhost";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS email_cleaner";
    $conn->exec($sql);
    echo "Database created successfully<br>";
    
    $conn->exec("USE email_cleaner");
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `google_id` varchar(255) DEFAULT NULL,
      `email` varchar(255) NOT NULL,
      `name` varchar(255) DEFAULT NULL,
      `picture` varchar(255) DEFAULT NULL,
      `access_token` text,
      `refresh_token` text,
      `token_expires` int(11) DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`)
    )";
    $conn->exec($sql);
    echo "Table users created successfully<br>";

    // Create email_logs table
    $sql = "CREATE TABLE IF NOT EXISTS `email_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `message_id` varchar(255) NOT NULL,
      `sender` varchar(255) NOT NULL,
      `subject` varchar(500) DEFAULT NULL,
      `category` varchar(50) DEFAULT 'inbox',
      `size_bytes` int(11) DEFAULT 0,
      `date_received` datetime DEFAULT NULL,
      `is_read` tinyint(1) DEFAULT 0,
      `is_starred` tinyint(1) DEFAULT 0,
      `status` varchar(50) DEFAULT 'scanned',
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`)
    )";
    $conn->exec($sql);
    echo "Table email_logs created successfully<br>";

    // Create cleanup_history table
    $sql = "CREATE TABLE IF NOT EXISTS `cleanup_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `action_type` varchar(50) NOT NULL,
      `emails_affected` int(11) DEFAULT 0,
      `space_freed_bytes` int(11) DEFAULT 0,
      `description` text,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`)
    )";
    $conn->exec($sql);
    echo "Table cleanup_history created successfully<br>";

    // Create sender_rules table
    $sql = "CREATE TABLE IF NOT EXISTS `sender_rules` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `sender_email` varchar(255) NOT NULL,
      `action` varchar(50) NOT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`)
    )";
    $conn->exec($sql);
    echo "Table sender_rules created successfully<br>";

    // Create settings table
    $sql = "CREATE TABLE IF NOT EXISTS `settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `auto_cleanup_enabled` tinyint(1) DEFAULT 0,
      `auto_cleanup_days` int(11) DEFAULT 30,
      `notify_before_cleanup` tinyint(1) DEFAULT 1,
      `dark_mode` tinyint(1) DEFAULT 0,
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_id` (`user_id`)
    )";
    $conn->exec($sql);
    echo "Table settings created successfully<br>";

} catch(PDOException $e) {
    echo $sql . "<br>" . $e->getMessage();
}

$conn = null;
?>
