<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user = getUser($pdo);
$autoCleanup = isset($_POST['autoCleanup']) ? 1 : 0;
$cleanupDays = isset($_POST['cleanupDays']) ? (int)$_POST['cleanupDays'] : 30;
$notifyBefore = isset($_POST['notifyBefore']) ? 1 : 0;

try {
    $stmt = $pdo->prepare("INSERT INTO settings (user_id, auto_cleanup_enabled, auto_cleanup_days, notify_before_cleanup) 
                           VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                           auto_cleanup_enabled = VALUES(auto_cleanup_enabled), 
                           auto_cleanup_days = VALUES(auto_cleanup_days), 
                           notify_before_cleanup = VALUES(notify_before_cleanup)");
    $stmt->execute([$user['id'], $autoCleanup, $cleanupDays, $notifyBefore]);
    
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
