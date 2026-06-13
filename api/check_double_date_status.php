<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

$dd_id = $_GET['id'] ?? 0;
if (!$dd_id) { echo json_encode(['success' => false]); exit; }

// Lấy danh sách thành viên và trạng thái của họ
$stmt = $conn->prepare("
    SELECT m.user_id, m.status, p.nickname, p.full_name 
    FROM double_date_members m
    JOIN profiles p ON m.user_id = p.user_id
    WHERE m.double_date_id = ?
");
$stmt->bind_param("i", $dd_id);
$stmt->execute();
$res = $stmt->get_result();

$members = [];
$all_accepted = true;

while ($row = $res->fetch_assoc()) {
    $name = !empty($row['nickname']) ? $row['nickname'] : $row['full_name'];
    $members[] = [
        'user_id' => $row['user_id'],
        'name' => $name,
        'status' => $row['status']
    ];
    if ($row['status'] === 'pending') {
        $all_accepted = false;
    }
}

echo json_encode([
    'success' => true,
    'members' => $members,
    'all_accepted' => $all_accepted
]);
?>