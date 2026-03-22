<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']); exit();
}

$user_id = (int)$_SESSION['user_id'];

// All related data is CASCADE-deleted by the FK constraints in the DB schema.
// (pings, notifications, rider_settings, contact_links, button_customization, login_logs)
$del = $conn->prepare('DELETE FROM users WHERE id = ?');
$del->bind_param('i', $user_id);

if ($del->execute()) {
    // Destroy the session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $conn->error]);
}
