<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false]); exit();
}

$user_id = (int)$_SESSION['user_id'];
$theme   = in_array($_POST['theme'] ?? '', ['dark', 'light']) ? $_POST['theme'] : 'dark';

$stmt = $conn->prepare('UPDATE users SET theme = ? WHERE id = ?');
$stmt->bind_param('si', $theme, $user_id);
$stmt->execute();

echo json_encode(['ok' => true, 'theme' => $theme]);
