<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
require_once '../classes/MailHandler.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$action = $_REQUEST['action'] ?? '';
$user = getUser($pdo);
$token = json_decode($user['access_token'], true);

// Refresh token if expired
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setAccessToken($token);

if ($client->isAccessTokenExpired()) {
    if ($user['refresh_token']) {
        $newToken = $client->fetchAccessTokenWithRefreshToken($user['refresh_token']);
        if (!isset($newToken['error'])) {
            $token = $newToken;
            $access_token_json = json_encode($newToken);
            $expires_in = time() + $newToken['expires_in'];
            
            // Update database
            $stmt = $pdo->prepare("UPDATE users SET access_token = ?, token_expires = ? WHERE id = ?");
            $stmt->execute([$access_token_json, $expires_in, $user['id']]);
        }
    }
}

$handler = new MailHandler($token);

if ($action === 'bulk_delete' || (isset($_POST['ids']) && empty($action))) {
    $ids = $_POST['ids'] ?? [];
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No emails selected.']);
        exit();
    }
    
    // Get size info for logging
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT SUM(size_bytes) as total_size FROM email_logs WHERE message_id IN ($placeholders)");
    $stmt->execute($ids);
    $stats = $stmt->fetch();
    $totalSize = $stats['total_size'] ?? 0;

    $deletedCount = $handler->trashEmails($ids);
    
    if ($deletedCount > 0) {
        // Update database status
        $stmt = $pdo->prepare("UPDATE email_logs SET status = 'deleted' WHERE message_id IN ($placeholders)");
        $stmt->execute($ids);
        
        // Log to history
        $stmt = $pdo->prepare("INSERT INTO cleanup_history (user_id, action_type, emails_affected, space_freed_bytes, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], 'bulk_delete', $deletedCount, $totalSize, "Bulk deleted $deletedCount emails via inbox"]);
        
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully moved $deletedCount emails to trash."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete selected emails.']);
    }
} elseif ($action === 'delete_old_promos') {
    // Get message IDs from database that are promotions
    $stmt = $pdo->prepare("SELECT message_id, size_bytes FROM email_logs WHERE user_id = ? AND category = 'promotions' AND status = 'scanned' LIMIT 50");
    $stmt->execute([$user['id']]);
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo json_encode(['success' => false, 'message' => 'No emails found to delete. Please scan first.']);
        exit();
    }
    
    $ids = array_column($emails, 'message_id');
    $totalSize = array_sum(array_column($emails, 'size_bytes'));
    
    $deletedCount = $handler->trashEmails($ids);
    
    if ($deletedCount > 0) {
        // Update database status
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE email_logs SET status = 'deleted' WHERE message_id IN ($placeholders)");
        $stmt->execute($ids);
        
        // Log to history
        $stmt = $pdo->prepare("INSERT INTO cleanup_history (user_id, action_type, emails_affected, space_freed_bytes, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], 'delete_promos', $deletedCount, $totalSize, "Deleted $deletedCount promotional emails"]);
        
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully moved $deletedCount emails to trash."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete emails.']);
    }
} elseif ($action === 'delete_old_otps') {
    // Get message IDs from database that are OTPs/Verification codes (Refined Sync)
    $otp_query = "(subject LIKE '%OTP%' OR subject LIKE '%Verification%' OR subject LIKE '%code%' OR subject LIKE '%Security%' OR subject LIKE '%Login code%' OR subject LIKE '%Security code%' OR subject LIKE '%Confirm your%')";
    $stmt = $pdo->prepare("SELECT message_id, size_bytes FROM email_logs WHERE user_id = ? AND status = 'scanned' AND $otp_query AND date_received < (NOW() - INTERVAL 1 DAY)");
    $stmt->execute([$user['id']]);
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo json_encode(['success' => false, 'message' => 'No old OTPs found to delete.']);
        exit();
    }
    
    $ids = array_column($emails, 'message_id');
    $totalSize = array_sum(array_column($emails, 'size_bytes'));
    
    $deletedCount = $handler->trashEmails($ids);
    
    if ($deletedCount > 0) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE email_logs SET status = 'deleted' WHERE message_id IN ($placeholders)");
        $stmt->execute($ids);
        
        $stmt = $pdo->prepare("INSERT INTO cleanup_history (user_id, action_type, emails_affected, space_freed_bytes, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], 'delete_otps', $deletedCount, $totalSize, "Deleted $deletedCount expired OTPs & security codes"]);
        
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully moved $deletedCount expired OTPs to trash."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete OTPs.']);
    }
} elseif ($action === 'empty_spam') {
    // Get message IDs from database that are spam
    $stmt = $pdo->prepare("SELECT message_id, size_bytes FROM email_logs WHERE user_id = ? AND category = 'spam' AND status = 'scanned'");
    $stmt->execute([$user['id']]);
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo json_encode(['success' => false, 'message' => 'No spam emails found to delete.']);
        exit();
    }
    
    $ids = array_column($emails, 'message_id');
    $totalSize = array_sum(array_column($emails, 'size_bytes'));
    
    // For spam, we move to trash to comply with current API scopes
    $deletedCount = $handler->trashEmails($ids);
    
    if ($deletedCount > 0) {
        // Update database status
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE email_logs SET status = 'deleted' WHERE message_id IN ($placeholders)");
        $stmt->execute($ids);
        
        // Log to history
        $stmt = $pdo->prepare("INSERT INTO cleanup_history (user_id, action_type, emails_affected, space_freed_bytes, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], 'empty_spam', $deletedCount, $totalSize, "Moved $deletedCount spam emails to trash"]);
        
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully moved spam emails to trash ($deletedCount emails)."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to empty spam folder.']);
    }
} elseif ($action === 'delete_large_emails') {
    // Get message IDs from database that are large (>5MB)
    $stmt = $pdo->prepare("SELECT message_id, size_bytes FROM email_logs WHERE user_id = ? AND status = 'scanned' AND size_bytes > 5242880");
    $stmt->execute([$user['id']]);
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo json_encode(['success' => false, 'message' => 'No large emails found to delete.']);
        exit();
    }
    
    $ids = array_column($emails, 'message_id');
    $totalSize = array_sum(array_column($emails, 'size_bytes'));
    
    $deletedCount = $handler->trashEmails($ids);
    
    if ($deletedCount > 0) {
        // Update database status
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE email_logs SET status = 'deleted' WHERE message_id IN ($placeholders)");
        $stmt->execute($ids);
        
        // Log to history
        $stmt = $pdo->prepare("INSERT INTO cleanup_history (user_id, action_type, emails_affected, space_freed_bytes, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], 'delete_large_emails', $deletedCount, $totalSize, "Deleted $deletedCount emails larger than 5MB"]);
        
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully moved $deletedCount large emails to trash."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete large emails.']);
    }
} elseif ($action === 'delete_by_sender') {
    $sender = $_REQUEST['sender'] ?? '';
    if (empty($sender)) {
        echo json_encode(['success' => false, 'message' => 'Sender not specified.']);
        exit();
    }
    
    // Get message IDs from database for this sender
    $stmt = $pdo->prepare("SELECT message_id, size_bytes FROM email_logs WHERE user_id = ? AND sender = ? AND status = 'scanned'");
    $stmt->execute([$user['id'], $sender]);
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo json_encode(['success' => false, 'message' => 'No emails found from this sender.']);
        exit();
    }
    
    $ids = array_column($emails, 'message_id');
    $totalSize = array_sum(array_column($emails, 'size_bytes'));
    
    $deletedCount = $handler->trashEmails($ids);
    
    if ($deletedCount > 0) {
        // Update database status
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE email_logs SET status = 'deleted' WHERE message_id IN ($placeholders)");
        $stmt->execute($ids);
        
        // Log to history
        $stmt = $pdo->prepare("INSERT INTO cleanup_history (user_id, action_type, emails_affected, space_freed_bytes, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], 'delete_by_sender', $deletedCount, $totalSize, "Deleted $deletedCount emails from $sender"]);
        
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully moved $deletedCount emails from $sender to trash."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete emails from this sender.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
