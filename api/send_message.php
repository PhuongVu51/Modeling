<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$sender_id = $_SESSION['user_id'];
$receiver_id = $data['receiver_id'] ?? 0;
$message_text = trim($data['message_text'] ?? '');

if ($receiver_id > 0 && !empty($message_text)) {
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message_text);
    
    if ($stmt->execute()) {
        
        // --- LOGIC TÍNH CHUỖI LỬA (STREAK) ---
        $u1 = min($sender_id, $receiver_id);
        $u2 = max($sender_id, $receiver_id);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $stmt_streak = $conn->prepare("SELECT streak_count, last_interact_date FROM matches WHERE user1_id = ? AND user2_id = ?");
        $stmt_streak->bind_param("ii", $u1, $u2);
        $stmt_streak->execute();
        $res = $stmt_streak->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $last_date = $row['last_interact_date'];
            $streak = $row['streak_count'];

            if ($last_date == $today) {
                // Đã nhắn hôm nay rồi thì giữ nguyên chuỗi
                $new_streak = $streak; 
            } elseif ($last_date == $yesterday) {
                // Hôm qua có nhắn, hôm nay nhắn tiếp -> Tăng chuỗi!
                $new_streak = $streak + 1; 
            } else {
                // Cách ngày không nhắn -> Mất chuỗi, đếm lại từ 1
                $new_streak = 1; 
            }

            // Lưu lại vào database
            $update_match = $conn->prepare("UPDATE matches SET streak_count = ?, last_interact_date = ? WHERE user1_id = ? AND user2_id = ?");
            $update_match->bind_param("isii", $new_streak, $today, $u1, $u2);
            $update_match->execute();
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'DB Insert Failed']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>