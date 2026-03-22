<?php
// Thin wrapper that reuses the same logic as rider_page.php contact adding
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'rider') {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']); exit();
}

$user_id = (int)$_SESSION['user_id'];
$cemail  = trim($_POST['contact_email'] ?? '');

if (!$cemail) {
    echo json_encode(['ok' => false, 'message' => 'Please enter an email address.']); exit();
}

$cq = $conn->prepare('SELECT id, fullname, account_type FROM users WHERE email = ? AND id != ?');
$cq->bind_param('si', $cemail, $user_id); $cq->execute();
$cuser = $cq->get_result()->fetch_assoc();

if (!$cuser) {
    echo json_encode(['ok' => false, 'message' => 'No user found with that email.']); exit();
}
if ($cuser['account_type'] !== 'contact') {
    echo json_encode(['ok' => false, 'message' => 'That user is not a Contact account.']); exit();
}

$ck = $conn->prepare('SELECT id FROM contact_links WHERE rider_id = ? AND contact_id = ?');
$ck->bind_param('ii', $user_id, $cuser['id']); $ck->execute(); $ck->store_result();
if ($ck->num_rows > 0) {
    echo json_encode(['ok' => true, 'message' => '✅ ' . $cuser['fullname'] . ' is already linked!']); exit();
}

$ins = $conn->prepare('INSERT INTO contact_links (rider_id, contact_id, status) VALUES (?, ?, \'accepted\')');
$ins->bind_param('ii', $user_id, $cuser['id']); $ins->execute();

echo json_encode(['ok' => true, 'message' => '✅ ' . $cuser['fullname'] . ' added as trusted contact!']);
