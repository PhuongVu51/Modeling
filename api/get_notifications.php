<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode([]);
    exit;
}

// Lấy các thông báo chưa đọc của user này
$stmt = $conn->prepare("SELECT id, type, message, reference_id, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
while ($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode($notifications);
?>