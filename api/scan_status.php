<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit();
}

$user = getUser($pdo);
$stmt = $pdo->prepare("SELECT * FROM scan_status WHERE user_id = ?");
$stmt->execute([$user['id']]);
$status = $stmt->fetch();

if ($status) {
    $percent = ($status['total'] > 0) ? round(($status['current'] / $status['total']) * 100) : 0;
    if ($percent > 100) $percent = 100;
    
    echo json_encode([
        'success' => true,
        'status' => $status['status'],
        'current' => $status['current'],
        'total' => $status['total'],
        'percent' => $percent
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>
