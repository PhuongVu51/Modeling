<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$nickname = $_GET['nickname'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if(empty($nickname)) {
    echo json_encode(['valid' => false]);
    exit;
}

// Tìm user có nickname này (không phân biệt hoa thường) và không phải là chính mình
$stmt = $conn->prepare("SELECT user_id FROM profiles WHERE LOWER(nickname) = LOWER(?) AND user_id != ?");
$stmt->bind_param("si", $nickname, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode(['valid' => true, 'user_id' => $row['user_id']]);
} else {
    echo json_encode(['valid' => false]);
}
?>