<?php
require_once 'config.php';
if(!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
$user = getUser($pdo);

// Initialize some mock settings for UI demonstration
// In a real app, these would come from a 'user_settings' table
$settings = [
    'auto_cleanup' => 'enabled',
    'cleanup_days' => 30,
    'notify_success' => true,
    'language' => 'Marathi',
    'scan_limit' => 100
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CleanBox AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-body-tertiary">

<?php include 'navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <h2 class="fw-bold mb-0">Control Center & Settings</h2>
                <button class="btn btn-primary rounded-pill px-4" onclick="saveSettings()">
                    <i class="bi bi-save me-2"></i> Save All Changes
                </button>
            </div>
            
            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-lg-8">
                    
                    <!-- 1. Auto-Cleanup Rules -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                                    <i class="bi bi-magic fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0">Auto-Cleanup Rules</h5>
                                    <p class="text-muted small mb-0">Automate your inbox maintenance.</p>
                                </div>
                            </div>
                            
                            <div class="form-check form-switch mb-4 fs-5">
                                <input class="form-check-input" type="checkbox" id="autoCleanup" checked>
                                <label class="form-check-label fs-6" for="autoCleanup">Enable Periodic Auto-Cleanup</label>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Delete Promotions older than:</label>
                                    <select class="form-select rounded-3">
                                        <option value="7">7 Days</option>
                                        <option value="15">15 Days</option>
                                        <option value="30" selected>30 Days</option>
                                        <option value="90">90 Days</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Delete Social Emails older than:</label>
                                    <select class="form-select rounded-3">
                                        <option value="30" selected>30 Days</option>
                                        <option value="60">60 Days</option>
                                        <option value="never">Never Delete</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Notification & Alerts -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Notifications & Alerts</h5>
                            <div class="list-group list-group-flush">
                                <div class="list-group-item px-0 border-0 d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-1">Browser Notifications</h6>
                                        <p class="text-muted small mb-0">Alert me when a scan is completed.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" checked>
                                    </div>
                                </div>
                                <div class="list-group-item px-0 border-0 d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-1">Storage Alerts</h6>
                                        <p class="text-muted small mb-0">Notify me if my Gmail storage exceeds 85%.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" checked>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Language & Display -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Language & Region</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Application Language</label>
                                    <select class="form-select rounded-3">
                                        <option value="en">English (US)</option>
                                        <option value="mr" selected>Marathi (मराठी)</option>
                                        <option value="hi">Hindi (हिंदी)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select rounded-3">
                                        <option value="IST">India (GMT+5:30)</option>
                                        <option value="UTC">UTC (GMT+0)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Profile Card -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-center">
                        <div class="bg-primary py-4"></div>
                        <div class="card-body px-4 pb-4" style="margin-top: -45px;">
                            <img src="<?php echo htmlspecialchars($user['picture']); ?>" width="90" height="90" class="rounded-circle border border-4 border-white shadow-sm mb-3">
                            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                            <p class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></p>
                            <hr>
                            <div class="row text-center">
                                <div class="col-6 border-end">
                                    <h6 class="fw-bold mb-0">Member Since</h6>
                                    <small class="text-muted"><?php echo date('M Y', strtotime($user['created_at'])); ?></small>
                                </div>
                                <div class="col-6">
                                    <h6 class="fw-bold mb-0">Last Scan</h6>
                                    <small class="text-muted">Today</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="card border-0 shadow-sm rounded-4 border-start border-danger border-5">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h5>
                            <p class="small text-muted mb-4">Actions here are permanent and cannot be undone.</p>
                            <button class="btn btn-outline-danger w-100 mb-3" onclick="confirmReset()">
                                <i class="bi bi-arrow-counterclockwise me-2"></i> Reset Scan Database
                            </button>
                            <button class="btn btn-danger w-100">
                                <i class="bi bi-trash me-2"></i> Deactivate Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
    function saveSettings() {
        Swal.fire({
            title: 'Settings Saved!',
            text: 'Your preferences have been updated successfully.',
            icon: 'success',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }

    function confirmReset() {
        Swal.fire({
            title: 'Are you sure?',
            text: "This will clear your scan history and reports. Your actual emails will NOT be affected.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, reset it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Reset!', 'Your data has been cleared.', 'success');
            }
        });
    }
</script>
</body>
</html>
