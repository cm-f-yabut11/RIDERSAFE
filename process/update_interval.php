<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit();
}
if ($_SESSION['account_type'] !== 'rider') {
    echo json_encode(['ok' => false, 'error' => 'Not a rider account']);
    exit();
}

$rider_id = (int)$_SESSION['user_id'];
// Accept total seconds (1s minimum, 86400s maximum = 24 hours)
// ping_interval column now stores SECONDS for sub-minute precision
$interval = isset($_POST['ping_interval']) ? (int)$_POST['ping_interval'] : 0;

if ($interval < 1 || $interval > 86400) {
    echo json_encode(['ok' => false, 'error' => 'Interval must be between 1 second and 24 hours.']);
    exit();
}

// Check if row exists first
$check = $conn->prepare('SELECT id FROM rider_settings WHERE rider_id = ?');
$check->bind_param('i', $rider_id);
$check->execute();
$exists = $check->get_result()->fetch_assoc();

if ($exists) {
    // Always update even if same value — forces last_ping_time reset so countdown is fresh
    $stmt = $conn->prepare('UPDATE rider_settings SET ping_interval = ?, last_ping_time = NULL WHERE rider_id = ?');
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param('ii', $interval, $rider_id);
    $stmt->execute();
} else {
    $ins = $conn->prepare('INSERT INTO rider_settings (rider_id, ping_interval, auto_grace_minutes, system_active) VALUES (?, ?, 5, 0)');
    if (!$ins) {
        echo json_encode(['ok' => false, 'error' => 'Insert prepare failed: ' . $conn->error]);
        exit();
    }
    $ins->bind_param('ii', $rider_id, $interval);
    $ins->execute();
}

echo json_encode(['ok' => true, 'ping_interval' => $interval]);
exit();
