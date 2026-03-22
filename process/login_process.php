<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header('Location: /RIDERSAFE_Project/login.php?error=' . urlencode('Email and password are required.'));
    exit();
}

$stmt = $conn->prepare('SELECT id, password, account_type FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']      = (int)$user['id'];
        $_SESSION['account_type'] = $user['account_type'];
        $dest = ($user['account_type'] === 'rider')
            ? '/RIDERSAFE_Project/rider_home.php'
            : '/RIDERSAFE_Project/contact_home.php';
        header('Location: ' . $dest);
        exit();
    } else {
        header('Location: /RIDERSAFE_Project/login.php?error=' . urlencode('Incorrect password. Please try again.'));
        exit();
    }
} else {
    header('Location: /RIDERSAFE_Project/login.php?error=' . urlencode('No account found with that email.'));
    exit();
}
