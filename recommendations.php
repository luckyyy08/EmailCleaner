<?php
require_once 'config.php';
if(!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
$user = getUser($pdo);

// 1. Find useless OTPs (older than 24 hours)
$stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(size_bytes) as total_size FROM email_logs WHERE user_id = ? AND status = 'scanned' AND (subject LIKE '%OTP%' OR subject LIKE '%Verification%' OR subject LIKE '%code%') AND date_received < (NOW() - INTERVAL 1 DAY)");
$stmt->execute([$user['id']]);
$old_otps = $stmt->fetch();

// 2. Find high frequency senders
$stmt = $pdo->prepare("SELECT sender, COUNT(*) as count, SUM(size_bytes) as total_size, MAX(unsubscribe_url) as unsubscribe_url FROM email_logs WHERE user_id = ? AND status = 'scanned' GROUP BY sender HAVING count > 10 ORDER BY count DESC LIMIT 3");
$stmt->execute([$user['id']]);
$top_senders = $stmt->fetchAll();

// 3. Find Large Attachments (> 5MB)
$stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(size_bytes) as total_size FROM email_logs WHERE user_id = ? AND status = 'scanned' AND size_bytes > 5242880");
$stmt->execute([$user['id']]);
$large_attachments = $stmt->fetch();

function formatBytes($bytes, $precision = 1) {
    $units = array('B', 'KB', 'MB', 'GB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Cleanup - CleanBox AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-arrow-left me-2"></i>Back to Dashboard</a>
    </div>
</nav>

<div class="container">
    <h2 class="fw-bold mb-4"><i class="bi bi-stars text-warning me-2"></i>Smart AI Recommendations</h2>
    
    <div class="row g-4">
        <!-- Suggestion 1: Old Promotions -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm recommendation-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                            <i class="bi bi-calendar-event fs-4"></i>
                        </div>
                        <h4 class="card-title fw-bold mb-0">Old Promotions</h4>
                    </div>
                    <?php 
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(size_bytes) as total_size FROM email_logs WHERE user_id = ? AND category = 'promotions' AND status = 'scanned' AND date_received < (NOW() - INTERVAL 30 DAY)");
                    $stmt->execute([$user['id']]);
                    $old_promos = $stmt->fetch();
                    ?>
                    <p class="text-muted mb-4">You have <strong><?php echo $old_promos['count']; ?></strong> promotional emails older than 30 days.</p>
                    <div class="d-grid">
                        <button class="btn btn-primary action-btn" data-action="delete_old_promos">Review & Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suggestion 2: Repeated Marketing -->
        <?php foreach($top_senders as $sender): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="badge bg-warning text-dark mb-2">Medium Impact</span>
                        <h5 class="fw-bold">Marketing Pattern</h5>
                    </div>
                    <p class="text-muted mb-4">You have <strong><?php echo $sender['count']; ?></strong> emails from "<?php echo htmlspecialchars($sender['sender']); ?>". You rarely interact with them.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-danger action-btn" data-action="delete_by_sender" data-sender="<?php echo urlencode($sender['sender']); ?>">
                            <i class="bi bi-trash me-2"></i>Delete All Emails
                        </button>
                        
                        <?php if($sender['unsubscribe_url']): ?>
                            <a href="<?php echo htmlspecialchars($sender['unsubscribe_url']); ?>" target="_blank" class="btn btn-outline-warning">
                                <i class="bi bi-envelope-x me-2"></i>Unsubscribe Now
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary disabled">
                                <i class="bi bi-slash-circle me-2"></i>No Unsubscribe Link
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Suggestion 3: Large Attachments -->
        <?php if($large_attachments['count'] > 0): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 border-start border-primary border-5">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="badge bg-primary mb-2">High Space Gain</span>
                        <h5 class="fw-bold">Heavy Emails</h5>
                    </div>
                    <p class="text-muted mb-4">You have <strong><?php echo $large_attachments['count']; ?></strong> emails larger than 5MB. Deleting these will free up ~<strong><?php echo formatBytes($large_attachments['total_size']); ?></strong> instantly.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary action-btn" data-action="delete_large_emails">Clean up Large Files</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(empty($top_senders) && $old_promos['count'] == 0 && $large_attachments['count'] == 0): ?>
        <div class="col-12 text-center py-5">
            <i class="bi bi-check2-circle text-success fs-1 mb-3 d-block"></i>
            <h4>All Clear!</h4>
            <p class="text-muted">No smart recommendations at this time. Your inbox looks healthy.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
