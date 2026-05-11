<?php
require_once 'config.php';
if(!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
$user = getUser($pdo);

// Get total scanned emails count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM email_logs WHERE user_id = ? AND status = 'scanned'");
$stmt->execute([$user['id']]);
$total_scanned = $stmt->fetch()['total'];

// Get individual category stats for badges
$stats = [
    'promotions' => 0,
    'social' => 0,
    'spam' => 0,
    'otps' => 0
];

$stmt = $pdo->prepare("SELECT category, COUNT(*) as count FROM email_logs WHERE user_id = ? AND status = 'scanned' GROUP BY category");
$stmt->execute([$user['id']]);
while($row = $stmt->fetch()) {
    $cat = $row['category'];
    if (array_key_exists($cat, $stats)) {
        $stats[$cat] = $row['count'];
    }
}

// Special count for OTPs (Useless OTPs older than 24h)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_logs WHERE user_id = ? AND status = 'scanned' AND (subject LIKE '%OTP%' OR subject LIKE '%Verification%' OR subject LIKE '%code%' OR subject LIKE '%Security%') AND date_received < (NOW() - INTERVAL 1 DAY)");
$stmt->execute([$user['id']]);
$otp_row = $stmt->fetch();
$stats['otps'] = $otp_row['count'] ?? 0;
$stmt = $pdo->prepare("SELECT SUM(space_freed_bytes) as total_freed FROM cleanup_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$bytes_saved = $stmt->fetch()['total_freed'] ?? 0;

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Final stats merge for dashboard
$stats['scanned'] = $total_scanned;
$stats['space_saved'] = formatBytes($bytes_saved);

// Fetch recent activity
$stmt = $pdo->prepare("SELECT * FROM cleanup_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$history = $stmt->fetchAll();


?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CleanBox AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-body-tertiary">

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="dashboard.php">
            <i class="bi bi-envelope-check-fill fs-4 me-2 text-primary"></i>
            CleanBox AI
        </a>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-light btn-sm" id="theme-toggle">
                <i class="bi bi-sun-fill"></i>
            </button>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="" width="32" height="32" class="rounded-circle me-2">
                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2 fw-bold">Overview</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="share-btn">
                            <i class="bi bi-share me-1"></i> Share
                        </button>
                        <a href="api/export.php" class="btn btn-sm btn-outline-secondary" id="export-btn">
                            <i class="bi bi-download me-1"></i> Export
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-primary-subtle text-primary me-3">
                                <i class="bi bi-envelope-open"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Emails Scanned</h6>
                                <h3 class="fw-bold mb-0" id="stat-scanned"><?php echo number_format($stats['scanned']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-warning-subtle text-warning me-3">
                                <i class="bi bi-tags"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Promotions Found</h6>
                                <h3 class="fw-bold mb-0" id="stat-promotions"><?php echo number_format($stats['promotions']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-danger-subtle text-danger me-3">
                                <i class="bi bi-shield-x"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Spam/Junk</h6>
                                <h3 class="fw-bold mb-0" id="stat-spam"><?php echo number_format($stats['spam']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-success-subtle text-success me-3">
                                <i class="bi bi-hdd"></i>
                            </div>
                            <div>
                                <h6 class="card-title text-muted mb-0">Space Recovered</h6>
                                <h3 class="fw-bold mb-0" id="stat-saved"><?php echo $stats['space_saved']; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scan Configuration (Replaced AI Recommendations) -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="fw-bold mb-0"><i class="bi bi-gear-fill me-2"></i>Scan Configuration</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Scan Limit (Max Emails)</label>
                                    <select class="form-select form-select-lg rounded-3" id="scan-limit">
                                        <option value="50">Quick Scan (50 emails)</option>
                                        <option value="100" selected>Standard Scan (100 emails)</option>
                                        <option value="500">Deep Scan (500 emails)</option>
                                        <option value="1000">Full Scan (1000 emails)</option>
                                    </select>
                                    <div class="form-text mt-2">Fewer emails mean faster scanning results.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Categories to Scan</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="promotions" id="cat-promos" checked>
                                            <label class="form-check-label" for="cat-promos">Promotions</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="social" id="cat-social" checked>
                                            <label class="form-check-label" for="cat-social">Social</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="spam" id="cat-spam" checked>
                                            <label class="form-check-label" for="cat-spam">Spam</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <button id="scan-inbox-btn" class="btn btn-primary btn-lg rounded-pill px-5 py-3 shadow mb-3">
                                        <i class="bi bi-search me-2"></i> Start Scanning Now
                                    </button>
                                    
                                    <!-- Progress Bar -->
                                    <div id="scan-progress-container" class="d-none">
                                        <div class="progress rounded-pill mb-2" style="height: 10px;">
                                            <div id="scan-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div id="scan-status-text" class="small text-muted fw-bold">Starting...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <h4 class="fw-bold mb-3">Recent Inbox Scans</h4>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Emails Scanned</th>
                                    <th>Action Taken</th>
                                    <th class="pe-4 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No recent activity found. Click "Scan Inbox" to get started.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($history as $item): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td class="text-success fw-bold">+<?php echo formatBytes($item['space_freed_bytes']); ?></td>
                                        <td><span class="badge bg-success-subtle text-success">Completed</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
