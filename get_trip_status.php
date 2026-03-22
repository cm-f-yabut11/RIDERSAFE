<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['active' => false]); exit();
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT system_active FROM rider_settings WHERE rider_id = ?');
$stmt->bind_param('i', $user_id); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

echo json_encode(['active' => $row ? (bool)$row['system_active'] : false]);
