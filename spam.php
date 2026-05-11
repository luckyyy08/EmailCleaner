<?php
require_once 'config.php';
if(!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
$user = getUser($pdo);

// Fetch real spam emails from database
$stmt = $pdo->prepare("SELECT * FROM email_logs WHERE user_id = ? AND category = 'spam' AND status = 'scanned' ORDER BY date_received DESC");
$stmt->execute([$user['id']]);
$emails = $stmt->fetchAll();

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
    <title>Spam/Junk - CleanBox AI</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">Spam & Junk Emails</h2>
                    <p class="text-muted">Emails that Gmail identified as spam or potential junk.</p>
                </div>
                <div>
                    <button class="btn btn-danger btn-lg rounded-pill px-4 shadow-sm" id="empty-spam-btn">
                        <i class="bi bi-trash3-fill me-2"></i> Empty Spam Folder
                    </button>
                </div>
            </div>

            <!-- Email List -->
            <div class="card border-0 shadow-sm overflow-hidden">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-4">Sender</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Size</th>
                            <th scope="col">Date</th>
                            <th scope="col" class="text-end pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($emails)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-check fs-1 d-block mb-2 text-success"></i>
                                No spam emails found! Your inbox is safe.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($emails as $email): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-medium text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($email['sender']); ?></div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 400px;"><?php echo htmlspecialchars($email['subject']); ?></div>
                                </td>
                                <td class="text-muted small"><?php echo formatBytes($email['size_bytes']); ?></td>
                                <td class="text-muted small"><?php echo date('M d', strtotime($email['date_received'])); ?></td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-danger-subtle text-danger">Spam</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
