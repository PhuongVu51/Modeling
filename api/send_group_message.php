<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$dd_id = $data['double_date_id'] ?? 0;
$message = $data['message_text'] ?? '';
$sender_id = $_SESSION['user_id'] ?? 0;

if (!$dd_id || empty(trim($message)) || !$sender_id) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO group_messages (double_date_id, sender_id, message_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $dd_id, $sender_id, $message);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>