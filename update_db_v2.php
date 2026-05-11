<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE email_logs ADD COLUMN is_unread TINYINT(1) DEFAULT 1");
} catch(Exception $e) {}

try {
    $pdo->exec("ALTER TABLE email_logs ADD COLUMN unsubscribe_url TEXT NULL");
} catch(Exception $e) {}

echo "Database updated successfully!";
?>
