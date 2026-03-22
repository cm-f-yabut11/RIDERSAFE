<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'rider') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$rider_id = (int)$_SESSION['user_id'];
$lat      = isset($_POST['latitude'])  ? floatval($_POST['latitude'])  : null;
$lng      = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

$status = 'confirmed';
if (isset($_POST['sos'])    && $_POST['sos'])    $status = 'manual_request';
if (isset($_POST['missed']) && $_POST['missed']) $status = 'missed';

// 1. Insert ping record
$stmt = $conn->prepare('INSERT INTO pings (rider_id, latitude, longitude, status) VALUES (?, ?, ?, ?)');
$stmt->bind_param('idds', $rider_id, $lat, $lng, $status);
$stmt->execute();

// 2. Update last_ping_time
$upd = $conn->prepare('UPDATE rider_settings SET last_ping_time = NOW() WHERE rider_id = ?');
$upd->bind_param('i', $rider_id);
$upd->execute();

// 3. FIX: Notify contacts on missed or SOS pings
if ($status === 'missed' || $status === 'manual_request') {
    $rname_q = $conn->prepare('SELECT fullname FROM users WHERE id = ?');
    $rname_q->bind_param('i', $rider_id);
    $rname_q->execute();
    $rname = $rname_q->get_result()->fetch_assoc()['fullname'] ?? 'Your rider';

    if ($status === 'missed') {
        $notif_msg  = "⚠️ {$rname} missed their safety check-in. Please check on them.";
        $notif_type = 'ping_missed';
    } else {
        $notif_msg  = "🚨 EMERGENCY: {$rname} has triggered an SOS alert!";
        $notif_type = 'manual_ping';
    }

    $notif = $conn->prepare("INSERT INTO notifications (user_id, type, message)
                             SELECT contact_id, ?, ?
                             FROM contact_links WHERE rider_id = ? AND status = 'accepted'");
    $notif->bind_param('ssi', $notif_type, $notif_msg, $rider_id);
    $notif->execute();
}

// 4. FIX: Also notify contacts with a "safe" notification when confirmed
if ($status === 'confirmed') {
    $rname_q = $conn->prepare('SELECT fullname FROM users WHERE id = ?');
    $rname_q->bind_param('i', $rider_id);
    $rname_q->execute();
    $rname = $rname_q->get_result()->fetch_assoc()['fullname'] ?? 'Your rider';
    $notif_msg = "✅ {$rname} has confirmed their safety.";
    $notif = $conn->prepare("INSERT INTO notifications (user_id, type, message)
                             SELECT contact_id, 'ping_confirmed', ?
                             FROM contact_links WHERE rider_id = ? AND status = 'accepted'");
    $notif->bind_param('si', $notif_msg, $rider_id);
    $notif->execute();
}

echo json_encode(['ok' => true]);
exit();
