<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'new_blind_date') {
    // LẤY GIỚI TÍNH VÀ GU CỦA MÌNH
    $stmt_me = $conn->prepare("SELECT gender, interested_in FROM profiles WHERE user_id = ?");
    $stmt_me->bind_param("i", $user_id);
    $stmt_me->execute();
    $me = $stmt_me->get_result()->fetch_assoc();
    $my_gender = $me['gender'];
    $my_interested_in = $me['interested_in'];

    // TÌM NGƯỜI ĐANG CHỜ + KHỚP GU 2 CHIỀU
    $stmt = $conn->prepare("
        SELECT user_id FROM profiles 
        WHERE user_id != ? 
        AND is_waiting_blind = 1
        AND (? = 'Anyone' OR gender = ?) 
        AND (interested_in = 'Anyone' OR interested_in = ?)
        AND user_id NOT IN (SELECT user1_id FROM matches WHERE user2_id = ?)
        AND user_id NOT IN (SELECT user2_id FROM matches WHERE user1_id = ?)
        ORDER BY RAND() LIMIT 1
    ");
    // isssii = int, string, string, string, int, int
    $stmt->bind_param("isssii", $user_id, $my_interested_in, $my_interested_in, $my_gender, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // CÓ NGƯỜI ĐỢI! -> TIẾN HÀNH GHÉP ĐÔI
        $target_id = $row['user_id'];
        $u1 = min($user_id, $target_id);
        $u2 = max($user_id, $target_id);
        
        $stmt_match = $conn->prepare("INSERT IGNORE INTO matches (user1_id, user2_id, is_blind, is_revealed) VALUES (?, ?, 1, 0)");
        $stmt_match->bind_param("ii", $u1, $u2);
        $stmt_match->execute();

        // Xóa cả 2 khỏi hàng đợi
        $conn->query("UPDATE profiles SET is_waiting_blind = 0 WHERE user_id IN ($user_id, $target_id)");

        echo json_encode(['status' => 'matched', 'target_id' => $target_id]);
    } else {
        // CHƯA CÓ AI HỢP GU ĐỢI -> Đưa mình vào hàng đợi
        $conn->query("UPDATE profiles SET is_waiting_blind = 1 WHERE user_id = $user_id");
        echo json_encode(['status' => 'waiting']);
    }
} 
elseif ($action === 'check_waiting') {
    $stmt = $conn->prepare("SELECT is_waiting_blind FROM profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $is_waiting = $stmt->get_result()->fetch_assoc()['is_waiting_blind'];
    
    if ($is_waiting == 0) {
        $stmt_last = $conn->prepare("
            SELECT user1_id, user2_id FROM matches 
            WHERE (user1_id = ? OR user2_id = ?) AND is_blind = 1 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt_last->bind_param("ii", $user_id, $user_id);
        $stmt_last->execute();
        $last_match = $stmt_last->get_result()->fetch_assoc();
        
        if ($last_match) {
            $target_id = ($last_match['user1_id'] == $user_id) ? $last_match['user2_id'] : $last_match['user1_id'];
            echo json_encode(['status' => 'matched', 'target_id' => $target_id]);
        } else {
            echo json_encode(['status' => 'still_waiting']);
        }
    } else {
        echo json_encode(['status' => 'still_waiting']);
    }
}
elseif ($action === 'cancel_waiting') {
    $conn->query("UPDATE profiles SET is_waiting_blind = 0 WHERE user_id = $user_id");
    echo json_encode(['status' => 'cancelled']);
}
elseif ($action === 'reveal') {
    $target_id = $data['target_id'] ?? 0;
    $u1 = min($user_id, $target_id);
    $u2 = max($user_id, $target_id);

    $stmt = $conn->prepare("UPDATE matches SET is_revealed = 1 WHERE user1_id = ? AND user2_id = ?");
    $stmt->bind_param("ii", $u1, $u2);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
?>