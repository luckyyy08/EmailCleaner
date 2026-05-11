<?php
require_once 'config.php';
if(!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
$user = getUser($pdo);

// Fetch expired OTPs (older than 24 hours) with refined search
$otp_query = "(subject LIKE '%OTP%' OR subject LIKE '%Verification%' OR subject LIKE '%code%' OR subject LIKE '%Security%' OR subject LIKE '%Login code%' OR subject LIKE '%Security code%' OR subject LIKE '%Confirm your%')";
$stmt = $pdo->prepare("SELECT * FROM email_logs WHERE user_id = ? AND status = 'scanned' AND $otp_query AND date_received < (NOW() - INTERVAL 1 DAY) ORDER BY date_received DESC");
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
    <title>OTP Cleanup - CleanBox AI</title>
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
                    <h2 class="fw-bold mb-0">OTP & Verification Codes</h2>
                    <p class="text-muted">Expired codes and security alerts that are no longer needed.</p>
                </div>
                <div>
                    <button class="btn btn-warning btn-lg rounded-pill px-4 shadow-sm" id="empty-otps-btn">
                        <i class="bi bi-shield-lock-fill me-2"></i> Clean All OTPs
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
                            <th scope="col" class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($emails)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle-fill fs-1 d-block mb-2 text-success"></i>
                                No expired OTPs found!
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
                                    <button class="btn btn-sm btn-outline-danger delete-single" data-id="<?php echo $email['message_id']; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
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
<script>
    // Specific logic for cleaning ALL OTPs
    document.getElementById('empty-otps-btn')?.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: 'Clean All OTPs?',
            text: 'This will move all expired verification codes to trash.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f1c40f',
            confirmButtonText: 'Yes, Clean All'
        });

        if (result.isConfirmed) {
            const btn = document.getElementById('empty-otps-btn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Cleaning...';
            
            try {
                const res = await fetch('api/delete.php?action=delete_old_otps');
                const data = await res.json();
                if (data.success) {
                    Swal.fire('Cleaned!', data.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-shield-lock-fill me-2"></i> Clean All OTPs';
                }
            } catch (e) {
                Swal.fire('Error', 'Failed to clean OTPs', 'error');
                btn.disabled = false;
            }
        }
    });
</script>
</body>
</html>
