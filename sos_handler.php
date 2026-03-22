<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'rider') {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// 1. Get rider name for personalised message
$rname_q = $conn->prepare('SELECT fullname FROM users WHERE id = ?');
$rname_q->bind_param('i', $user_id); $rname_q->execute();
$rider_name = $rname_q->get_result()->fetch_assoc()['fullname'] ?? 'Your rider';

// 2. Capture coordinates
$lat = isset($_POST['latitude'])  ? floatval($_POST['latitude'])  : null;
$lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

// Build message with map link if coords available
if ($lat && $lng) {
    $map_link = "https://maps.google.com/?q={$lat},{$lng}";
    $msg = "🚨 EMERGENCY: {$rider_name} triggered an SOS alert! Last location: {$map_link}";
} else {
    $msg = "🚨 EMERGENCY: {$rider_name} triggered an SOS alert! No GPS location available.";
}

// 3. Record SOS ping with coordinates
$stmt = $conn->prepare("INSERT INTO pings (rider_id, latitude, longitude, status) VALUES (?, ?, ?, 'manual_request')");
$stmt->bind_param("idd", $user_id, $lat, $lng);
$stmt->execute();

// 4. Update last_ping_time
$upd = $conn->prepare("UPDATE rider_settings SET last_ping_time = NOW() WHERE rider_id = ?");
$upd->bind_param("i", $user_id); $upd->execute();

// 5. Notify ALL accepted contacts (INSERT...SELECT gets every one of them)
$notif = $conn->prepare("INSERT INTO notifications (user_id, type, message)
                         SELECT contact_id, 'manual_ping', ?
                         FROM contact_links WHERE rider_id = ? AND status = 'accepted'");
$notif->bind_param("si", $msg, $user_id);
$notif->execute();
$contacts_alerted = $notif->affected_rows;

echo json_encode(['status' => 'success', 'contacts_alerted' => $contacts_alerted]);