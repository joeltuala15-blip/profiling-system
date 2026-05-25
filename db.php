<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db_name = 'barangay_db';

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>














