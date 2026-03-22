<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'rider') {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$rider_id = (int)$_SESSION['user_id'];
$active   = isset($_POST['active']) ? (int)$_POST['active'] : 0;

if ($active) {
    // FIX: When starting a trip, set last_ping_time to NOW so the timer
    // starts from a real timestamp instead of NULL (which caused 30-min default reset)
    $stmt = $conn->prepare('UPDATE rider_settings SET system_active = 1, last_ping_time = NOW() WHERE rider_id = ?');
    $stmt->bind_param('i', $rider_id);
} else {
    // FIX: When ending a trip, clear last_ping_time
    $stmt = $conn->prepare('UPDATE rider_settings SET system_active = 0, last_ping_time = NULL WHERE rider_id = ?');
    $stmt->bind_param('i', $rider_id);
}
$stmt->execute();

echo json_encode(['ok' => true]);
exit();
