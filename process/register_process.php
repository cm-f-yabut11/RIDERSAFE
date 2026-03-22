<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

$fullname     = trim($_POST['fullname'] ?? '');
$email        = trim($_POST['email'] ?? '');
$password     = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$account_type = $_POST['account_type'] ?? '';

if (empty($fullname) || empty($email) || empty($password) || empty($account_type)) {
    header('Location: /RIDERSAFE_Project/register.php?error=' . urlencode('All fields are required.'));
    exit();
}

if ($password !== $password_confirm) {
    header('Location: /RIDERSAFE_Project/register.php?error=' . urlencode('Passwords do not match. Please try again.'));
    exit();
}

if (strlen($password) < 8) {
    header('Location: /RIDERSAFE_Project/register.php?error=' . urlencode('Password must be at least 8 characters.'));
    exit();
}

if (!in_array($account_type, ['rider', 'contact'])) {
    header('Location: /RIDERSAFE_Project/register.php?error=' . urlencode('Invalid account type.'));
    exit();
}

// Check duplicate email
$check = $conn->prepare('SELECT id FROM users WHERE email = ?');
$check->bind_param('s', $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    header('Location: /RIDERSAFE_Project/register.php?error=' . urlencode('That email is already registered. Please login instead.'));
    exit();
}
$check->close();

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (fullname, email, password, account_type) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $fullname, $email, $hashed, $account_type);

if ($stmt->execute()) {
    $user_id = (int)$stmt->insert_id;
    if ($account_type === 'rider') {
        $rs = $conn->prepare('INSERT INTO rider_settings (rider_id, ping_interval, auto_grace_minutes, system_active) VALUES (?, 1800, 5, 0)');
        $rs->bind_param('i', $user_id);
        $rs->execute();
    }
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user_id;
    $_SESSION['account_type'] = $account_type;
    $dest = ($account_type === 'rider')
        ? '/RIDERSAFE_Project/onboarding.php'
        : '/RIDERSAFE_Project/contact_home.php';
    header('Location: ' . $dest);
    exit();
} else {
    header('Location: /RIDERSAFE_Project/register.php?error=' . urlencode('Registration failed. Please try again.'));
    exit();
}