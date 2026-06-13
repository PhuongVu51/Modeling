<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

$dd_id = $_GET['double_date_id'] ?? 0;
$last_id = $_GET['last_id'] ?? 0; // Chỉ lấy các tin nhắn mới (sau tin cuối cùng đang hiện trên màn hình)

if (!$dd_id) { echo json_encode([]); exit; }

$stmt = $conn->prepare("
    SELECT gm.id, gm.sender_id, gm.message_text, gm.created_at, p.nickname, p.full_name, p.avatar
    FROM group_messages gm
    JOIN profiles p ON gm.sender_id = p.user_id
    WHERE gm.double_date_id = ? AND gm.id > ?
    ORDER BY gm.created_at ASC
");
$stmt->bind_param("ii", $dd_id, $last_id);
$stmt->execute();
$res = $stmt->get_result();

$messages = [];
while ($row = $res->fetch_assoc()) {
    $row['display_name'] = !empty($row['nickname']) ? $row['nickname'] : $row['full_name'];
    $row['time'] = date("h:i A", strtotime($row['created_at']));
    $messages[] = $row;
}

echo json_encode($messages);
?>