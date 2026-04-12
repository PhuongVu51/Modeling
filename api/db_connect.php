<?php
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "soul_sync_db";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Kết nối thất bại: " . $conn->connect_error]));
}

$conn->set_charset("utf8");
?>