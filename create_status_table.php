<?php
require_once 'config.php';
$sql = "CREATE TABLE IF NOT EXISTS scan_status (
    user_id INT PRIMARY KEY,
    total INT DEFAULT 0,
    current INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'idle',
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$pdo->exec($sql);
echo "Scan status table created successfully!";
?>
