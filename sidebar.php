<?php
// sidebar.php - Shared sidebar component
$current_page = basename($_SERVER['PHP_SELF']);
$category = $_GET['category'] ?? '';

// Ensure $stats is available if not already fetched
if (!isset($stats)) {
    $stats = ['promotions' => 0, 'social' => 0, 'spam' => 0, 'otps' => 0];
    $stmt = $pdo->prepare("SELECT category, COUNT(*) as count FROM email_logs WHERE user_id = ? AND status = 'scanned' GROUP BY category");
    $stmt->execute([$user['id']]);
    while($row = $stmt->fetch()) {
        if (array_key_exists($row['category'], $stats)) $stats[$row['category']] = $row['count'];
    }
    // OTP Count (Refined Search)
    $otp_query = "(subject LIKE '%OTP%' OR subject LIKE '%Verification%' OR subject LIKE '%code%' OR subject LIKE '%Security%' OR subject LIKE '%Login code%' OR subject LIKE '%Security code%' OR subject LIKE '%Confirm your%')";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_logs WHERE user_id = ? AND status = 'scanned' AND $otp_query AND date_received < (NOW() - INTERVAL 1 DAY)");
    $stmt->execute([$user['id']]);
    $stats['otps'] = $stmt->fetch()['count'] ?? 0;
}
?>
<nav class="col-md-3 col-lg-2 d-md-block bg-body sidebar collapse p-3 shadow-sm border-end">
    <div class="position-sticky">
        <div class="mb-4 px-3 d-md-none">
            <h5 class="fw-bold">CleanBox AI</h5>
        </div>
        <div class="mb-4 px-2">
            <button class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm" id="sidebar-scan-btn">
                <i class="bi bi-search me-2"></i> Scan Inbox
            </button>
        </div>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a class="sidebar-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo ($current_page == 'inbox.php' && $category == 'promotions') ? 'active' : ''; ?>" href="inbox.php?category=promotions">
                    <i class="bi bi-tags"></i> Promotions
                    <?php if($stats['promotions'] > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?php echo $stats['promotions']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo ($current_page == 'inbox.php' && $category == 'newsletters') ? 'active' : ''; ?>" href="inbox.php?category=newsletters">
                    <i class="bi bi-newspaper"></i> Newsletters
                    <?php if($stats['social'] > 0): ?>
                        <span class="badge bg-primary rounded-pill ms-auto"><?php echo $stats['social']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo $current_page == 'spam.php' ? 'active' : ''; ?>" href="spam.php">
                    <i class="bi bi-shield-x"></i> Spam/Junk
                    <?php if($stats['spam'] > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill ms-auto"><?php echo $stats['spam']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo $current_page == 'otps.php' ? 'active' : ''; ?>" href="otps.php">
                    <i class="bi bi-shield-lock"></i> OTP Cleanup
                    <?php if($stats['otps'] > 0): ?>
                        <span class="badge bg-info text-dark rounded-pill ms-auto"><?php echo $stats['otps']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item mt-4">
                <h6 class="sidebar-heading px-3 mb-2 text-muted text-uppercase fs-7">System</h6>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="bi bi-bar-chart"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-sliders"></i> Settings
                </a>
            </li>
        </ul>
    </div>
</nav>
