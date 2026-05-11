<?php
require_once 'config.php';
if(!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
$user = getUser($pdo);

// Fetch real stats from history
$stmt = $pdo->prepare("SELECT SUM(emails_affected) as total_emails, SUM(space_freed_bytes) as total_space FROM cleanup_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$totals = $stmt->fetch();

// Fetch full history
$stmt = $pdo->prepare("SELECT * FROM cleanup_history WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$full_history = $stmt->fetchAll();

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
    <title>Reports - CleanBox AI</title>
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
                <h2 class="fw-bold mb-0">Analytics & Reports</h2>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4">
                        <div class="text-primary mb-2"><i class="bi bi-trash3-fill fs-1"></i></div>
                        <h3 class="fw-bold display-6"><?php echo number_format($totals['total_emails'] ?? 0); ?></h3>
                        <p class="text-muted mb-0">Total Emails Deleted</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4">
                        <div class="text-success mb-2"><i class="bi bi-hdd-fill fs-1"></i></div>
                        <h3 class="fw-bold display-6"><?php echo formatBytes($totals['total_space'] ?? 0); ?></h3>
                        <p class="text-muted mb-0">Total Space Recovered</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4">
                        <div class="text-warning mb-2"><i class="bi bi-envelope-x-fill fs-1"></i></div>
                        <h3 class="fw-bold display-6">0</h3>
                        <p class="text-muted mb-0">Unsubscribed Lists</p>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Space Recovery Over Time</h5>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="spaceChart"></canvas>
                    </div>
                </div>
            </div>

            <?php
            // Fetch data for the chart (last 7 days)
            $chart_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $stmt = $pdo->prepare("SELECT SUM(space_freed_bytes) as total FROM cleanup_history WHERE user_id = ? AND DATE(created_at) = ?");
                $stmt->execute([$user['id'], $date]);
                $row = $stmt->fetch();
                $chart_data[] = [
                    'label' => date('M d', strtotime($date)),
                    'value' => round(($row['total'] ?? 0) / (1024 * 1024), 2) // Convert to MB
                ];
            }
            $labels = json_encode(array_column($chart_data, 'label'));
            $values = json_encode(array_column($chart_data, 'value'));
            ?>

            <div class="card border-0 shadow-sm rounded-4 mb-5">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Detailed Cleanup History</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th class="text-center">Emails</th>
                                    <th class="text-end">Space Saved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($full_history)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No history yet.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($full_history as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['emails_affected']); ?></td>
                                        <td class="text-end text-success fw-bold">+<?php echo formatBytes($row['space_freed_bytes']); ?></td>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('spaceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $labels; ?>,
            datasets: [{
                label: 'Space Recovered (MB)',
                data: <?php echo $values; ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#888' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#888' }
                }
            }
        }
    });
});
</script>
</body>
</html>
