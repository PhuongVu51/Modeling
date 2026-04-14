<?php
session_start();
require_once 'db_connect.php';
if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Not logged in']); exit; }

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$target_id = $data['target_user_id'] ?? 0;
$action = $data['action'] ?? '';

if ($target_id && in_array($action, ['like', 'pass'])) {
    $is_like = ($action === 'like') ? 1 : 0;
    
    // Lưu hành động quẹt vào database (để lần sau không hiện lại người này nữa)
    $stmt = $conn->prepare("INSERT IGNORE INTO likes (user_id, liked_user_id, is_like) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $user_id, $target_id, $is_like);
    $stmt->execute();

    // Nếu thả tim, kiểm tra xem người kia đã thả tim mình chưa
    if ($is_like) {
        $stmt2 = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND liked_user_id = ? AND is_like = 1");
        $stmt2->bind_param("ii", $target_id, $user_id);
        $stmt2->execute();
        
        // NẾU CÓ: TẠO MATCH!
        if ($stmt2->get_result()->num_rows > 0) {
            $u1 = min($user_id, $target_id);
            $u2 = max($user_id, $target_id);
            $stmt3 = $conn->prepare("INSERT IGNORE INTO matches (user1_id, user2_id) VALUES (?, ?)");
            $stmt3->bind_param("ii", $u1, $u2);
            $stmt3->execute();
            
            echo json_encode(['is_match' => true]);
            exit;
        }
    }
    echo json_encode(['is_match' => false]);
}
?>