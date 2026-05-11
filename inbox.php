<?php
require_once 'config.php';
if(!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
$user = getUser($pdo);

$category = isset($_GET['category']) ? $_GET['category'] : 'inbox';
$category_title = ucfirst($category);

$filterSender = isset($_GET['sender']) ? $_GET['sender'] : '';
$filterDate = isset($_GET['date']) ? $_GET['date'] : '';

$query = "SELECT * FROM email_logs WHERE user_id = ? AND category = ? AND status = 'scanned'";
$params = [$user['id'], $category];

if ($filterSender) {
    $query .= " AND sender LIKE ?";
    $params[] = '%' . $filterSender . '%';
}

if ($filterDate) {
    if ($filterDate == '1') $query .= " AND date_received < (NOW() - INTERVAL 30 DAY)";
    elseif ($filterDate == '2') $query .= " AND date_received < (NOW() - INTERVAL 6 MONTH)";
    elseif ($filterDate == '3') $query .= " AND date_received < (NOW() - INTERVAL 1 YEAR)";
}

$query .= " ORDER BY date_received DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
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
    <title><?php echo $category_title; ?> - CleanBox AI</title>
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
                    <h2 class="fw-bold mb-0"><?php echo ucfirst($category); ?></h2>
                    <p class="text-muted">Manage your <?php echo $category; ?> emails below.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-danger" id="bulk-delete-btn">
                        <i class="bi bi-trash3 me-1"></i> Bulk Delete
                    </button>
                    <?php if($category == 'newsletters'): ?>
                    <button class="btn btn-outline-warning" id="bulk-unsubscribe-btn">
                        <i class="bi bi-envelope-x me-1"></i> Bulk Unsubscribe
                    </button>
                    <?php endif; ?>
                </div>
            </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form class="row gy-2 gx-3 align-items-center" method="GET" action="inbox.php">
                <input type="hidden" name="category" value="<?php echo $category; ?>">
                <div class="col-auto">
                    <label class="visually-hidden" for="filterSender">Sender</label>
                    <input type="text" class="form-control" id="filterSender" name="sender" placeholder="Filter by Sender" value="<?php echo htmlspecialchars($filterSender); ?>">
                </div>
                <div class="col-auto">
                    <label class="visually-hidden" for="filterDate">Date</label>
                    <select class="form-select" id="filterDate" name="date">
                        <option value="">Any time</option>
                        <option value="1" <?php echo ($filterDate == '1') ? 'selected' : ''; ?>>Older than 30 days</option>
                        <option value="2" <?php echo ($filterDate == '2') ? 'selected' : ''; ?>>Older than 6 months</option>
                        <option value="3" <?php echo ($filterDate == '3') ? 'selected' : ''; ?>>Older than 1 year</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="inbox.php?category=<?php echo $category; ?>" class="btn btn-link">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Email List -->
    <div class="card border-0 shadow-sm overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4" style="width: 50px;">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                    </th>
                    <th scope="col">Sender</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Size</th>
                    <th scope="col">Date</th>
                    <th scope="col" class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($emails)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">No emails found in this category.</td>
                </tr>
                <?php else: ?>
                    <?php foreach($emails as $email): ?>
                    <tr class="email-row <?php echo $email['is_unread'] ? 'bg-primary-subtle' : ''; ?>">
                        <td class="ps-4">
                            <input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $email['message_id']; ?>">
                        </td>
                        <td class="fw-medium text-truncate" style="max-width: 150px;">
                            <?php if($email['is_unread']): ?>
                                <span class="badge bg-primary rounded-circle p-1 me-1" title="Unread">&nbsp;</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($email['sender']); ?>
                        </td>
                        <td class="text-truncate" style="max-width: 300px;">
                            <?php echo htmlspecialchars($email['subject']); ?>
                            <?php if($email['unsubscribe_url']): ?>
                                <span class="badge bg-info-subtle text-info ms-1 small">Unsubscribe Available</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?php echo formatBytes($email['size_bytes']); ?></td>
                        <td class="text-muted small"><?php echo $email['date_received'] ? date('M d', strtotime($email['date_received'])) : 'Unknown'; ?></td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-light text-danger me-1 delete-single" data-id="<?php echo htmlspecialchars($email['message_id']); ?>" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php if(!empty($email['unsubscribe_url'])): ?>
                                <a href="<?php echo htmlspecialchars($email['unsubscribe_url']); ?>" target="_blank" class="btn btn-sm btn-light text-warning" title="Unsubscribe">
                                    <i class="bi bi-dash-circle"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
