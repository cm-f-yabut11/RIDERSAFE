<?php
ob_start();
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'rider') {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit();
}

$rider_id      = (int)$_SESSION['user_id'];
$btn_label     = trim($_POST['btn_label']    ?? 'SAFE');
$btn_color     = trim($_POST['btn_color']    ?? '#2ecc8a');
$btn_color2    = trim($_POST['btn_color2']   ?? '');
$btn_gradient  = (isset($_POST['btn_gradient']) && $_POST['btn_gradient'] === '1') ? 1 : 0;
$btn_size      = trim($_POST['btn_size']     ?? 'medium');
$sound_enabled = (isset($_POST['sound_enabled']) && $_POST['sound_enabled'] === '1') ? 1 : 0;
$press_effect  = trim($_POST['press_effect'] ?? 'pulse');

// Sanitize
if (strlen($btn_label) === 0 || strlen($btn_label) > 12) $btn_label = 'SAFE';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $btn_color))      $btn_color = '#2ecc8a';
if ($btn_color2 && !preg_match('/^#[0-9a-fA-F]{6}$/', $btn_color2)) $btn_color2 = '';
if (!in_array($btn_size, ['small','medium','large']))      $btn_size  = 'medium';
if (!in_array($press_effect, ['pulse','pop','shake','ripple','bounce','flash'])) $press_effect = 'pulse';

// Upsert
$stmt = $conn->prepare('
    INSERT INTO button_customization
        (rider_id, btn_label, btn_color, btn_color2, btn_gradient, btn_size, sound_enabled, press_effect)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        btn_label     = VALUES(btn_label),
        btn_color     = VALUES(btn_color),
        btn_color2    = VALUES(btn_color2),
        btn_gradient  = VALUES(btn_gradient),
        btn_size      = VALUES(btn_size),
        sound_enabled = VALUES(sound_enabled),
        press_effect  = VALUES(press_effect),
        updated_at    = CURRENT_TIMESTAMP
');
if (!$stmt) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param('isssisis', $rider_id, $btn_label, $btn_color, $btn_color2, $btn_gradient, $btn_size, $sound_enabled, $press_effect);

if (!$stmt->execute()) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    exit();
}

ob_end_clean();
echo json_encode(['ok' => true]);
exit();
