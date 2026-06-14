<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$friends = $data['friends'] ?? [];
$creator_id = $_SESSION['user_id'];

if(count($friends) !== 3) {
    echo json_encode(['success' => false, 'message' => 'Please select exactly 3 friends.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Tạo phòng Double Date mới
    $stmt = $conn->prepare("INSERT INTO double_dates (creator_id) VALUES (?)");
    $stmt->bind_param("i", $creator_id);
    $stmt->execute();
    $double_date_id = $conn->insert_id;

    // 2. Thêm chủ phòng vào danh sách (mặc định là accepted)
    $stmt_creator = $conn->prepare("INSERT INTO double_date_members (double_date_id, user_id, status) VALUES (?, ?, 'accepted')");
    $stmt_creator->bind_param("ii", $double_date_id, $creator_id);
    $stmt_creator->execute();

    // 3. Lấy tên chủ phòng để làm nội dung thông báo
    $stmt_name = $conn->prepare("SELECT nickname FROM profiles WHERE user_id = ?");
    $stmt_name->bind_param("i", $creator_id);
    $stmt_name->execute();
    $creator_name = $stmt_name->get_result()->fetch_assoc()['nickname'];
    $message = "<b>$creator_name</b> invited you to plan a Double Date!";

    // 4. Thêm 3 người bạn kia vào danh sách (pending) và bắn thông báo
    $stmt_member = $conn->prepare("INSERT INTO double_date_members (double_date_id, user_id, status) VALUES (?, ?, 'pending')");
    $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, reference_id, message) VALUES (?, ?, 'double_date_invite', ?, ?)");
    
    foreach($friends as $friend_id) {
        $stmt_member->bind_param("ii", $double_date_id, $friend_id);
        $stmt_member->execute();

        $stmt_notif->bind_param("iiis", $friend_id, $creator_id, $double_date_id, $message);
        $stmt_notif->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'double_date_id' => $double_date_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}
?>