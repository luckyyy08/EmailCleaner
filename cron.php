<?php
// cron.php - Scheduled Auto-Cleanup Script
// Run this via cron job: 0 2 * * * php /path/to/cron.php

require_once __DIR__ . '/config.php';

echo "Starting automated cleanup...\n";

try {
    // Get all users who have auto-cleanup enabled
    $stmt = $pdo->query("SELECT u.id, u.email, s.auto_cleanup_days FROM users u JOIN settings s ON u.id = s.user_id WHERE s.auto_cleanup_enabled = 1");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        echo "Processing user: {$user['email']}\n";
        
        $days = $user['auto_cleanup_days'];
        $userId = $user['id'];

        // Simulated action: Delete promotional emails older than $days for this user
        // In a real app, this would use MailHandler to talk to Gmail API
        
        // Log the action
        $logStmt = $pdo->prepare("INSERT INTO cleanup_history (user_id, action_type, emails_affected, space_freed_bytes, description) VALUES (?, ?, ?, ?, ?)");
        $logStmt->execute([
            $userId,
            'auto_delete_promos',
            rand(10, 50), // mock number
            rand(1024000, 5120000), // mock space
            "Auto-deleted promotional emails older than $days days."
        ]);

        echo " - Cleaned up for user ID {$userId}\n";
    }

    echo "Cleanup completed successfully.\n";

} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
?>
