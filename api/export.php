<?php
require_once '../config.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$user = getUser($pdo);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=cleanup_report_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Date', 'Action Type', 'Emails Affected', 'Space Freed (Bytes)', 'Description'));

// Fetch the history data
$stmt = $pdo->prepare("SELECT created_at, action_type, emails_affected, space_freed_bytes, description FROM cleanup_history WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
