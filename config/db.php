<?php
require_once __DIR__ . '/session.php';

$host     = 'localhost';
$user     = 'root';
$password = '';
$database = 'ridersafe_db';

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die('<h2 style="font-family:sans-serif;color:red;padding:40px">Database connection failed: ' . $conn->connect_error . '<br><small>Make sure MySQL is running and ridersafe_db exists.</small></h2>');
}
$conn->set_charset('utf8mb4');
