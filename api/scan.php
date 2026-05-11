<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
require_once '../classes/MailHandler.php';

header('Content-Type: application/json');
set_time_limit(0); // Allow script to run indefinitely for full scan
ini_set('memory_limit', '512M'); // Increase memory for large scan results

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user = getUser($pdo);
session_write_close(); // Release session lock to allow concurrent status polling
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

$limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 100;
$categories = $_POST['categories'] ?? ['promotions', 'social', 'spam'];

// Initialize Scan Status
$stmt = $pdo->prepare("INSERT INTO scan_status (user_id, total, current, status) VALUES (?, ?, 0, 'scanning') ON DUPLICATE KEY UPDATE total = VALUES(total), current = 0, status = 'scanning'");
$stmt->execute([$user['id'], $limit]);

// Build Gmail query based on selected categories
$queryParts = [];
foreach($categories as $cat) {
    if($cat === 'spam') $queryParts[] = 'label:SPAM';
    else $queryParts[] = 'category:' . $cat;
}
$query = '(' . implode(' OR ', $queryParts) . ') -is:starred -is:important';

$handler = new MailHandler($token);

// --- SYNC STEP: Remove emails that were deleted externally (e.g. via Gmail App) ---
try {
    // Get ALL current message IDs for these categories from Gmail (unlimited IDs, fast)
    $currentGmailIds = $handler->getAllMessageIds($query);
    if (!empty($currentGmailIds)) {
        // Find IDs in our DB that are NOT in Gmail anymore and remove them
        // We do this per category to be safe
        $placeholders = str_repeat('?,', count($currentGmailIds) - 1) . '?';
        $sql = "DELETE FROM email_logs WHERE user_id = ? AND category IN ('promotions', 'social', 'spam') AND message_id NOT IN ($placeholders)";
        $params = array_merge([$user['id']], $currentGmailIds);
        
        // Note: For very large sets, we might need to chunk this, but for common use it's fine
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
} catch (Exception $e) {
    error_log("Sync error: " . $e->getMessage());
}

// Scan with user defined query and limit
$results = $handler->scanInbox($query, $limit, function($current) use ($pdo, $user) {
    // Progress callback (Update DB every email)
    $stmt = $pdo->prepare("UPDATE scan_status SET current = ? WHERE user_id = ?");
    $stmt->execute([$current, $user['id']]);
});

$scannedCount = count($results);
// Reset status
$pdo->prepare("UPDATE scan_status SET status = 'idle', current = 0 WHERE user_id = ?")->execute([$user['id']]);
$promotionsCount = 0;
$spamCount = 0;
$spaceSaved = 0;

foreach ($results as $msg) {
    // Categorization logic based on Gmail labels/headers
    $category = 'promotions'; // default
    if (isset($msg['labels']) && in_array('SPAM', $msg['labels'])) {
        $category = 'spam';
        $spamCount++;
    } else {
        $promotionsCount++;
    }
    
    $spaceSaved += $msg['size'] ?? 0;

    // Log to database
    $stmt = $pdo->prepare("INSERT INTO email_logs (user_id, message_id, sender, subject, category, size_bytes, is_unread, unsubscribe_url, date_received) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE status = 'scanned', is_unread = VALUES(is_unread), unsubscribe_url = VALUES(unsubscribe_url), date_received = VALUES(date_received)");
    $stmt->execute([
        $user['id'],
        $msg['id'],
        $msg['sender'] ?? 'Unknown',
        $msg['subject'] ?? '(No Subject)',
        $category,
        $msg['size'] ?? 0,
        $msg['is_unread'] ? 1 : 0,
        $msg['unsubscribe_url'],
        isset($msg['date']) ? date('Y-m-d H:i:s', strtotime($msg['date'])) : null
    ]);
}

// Convert space to human readable
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

echo json_encode([
    'success' => true,
    'scanned' => $scannedCount,
    'promotions' => $promotionsCount,
    'space_saved' => formatBytes($spaceSaved)
]);
?>
