<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/landing.php');
} else {
    // This now sends logged-in users to your new hub
    header('Location: /RIDERSAFE_Project/home.php');
}
exit();