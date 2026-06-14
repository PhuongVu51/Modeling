<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$notif_id = $data['notification_id'] ?? 0;
$action = $data['action'] ?? ''; 
$user_id = $_SESSION['user_id'] ?? 0;

if (!$notif_id || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT reference_id FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new Exception("Notification not found");
    $double_date_id = $res->fetch_assoc()['reference_id'];

    $status = ($action === 'accept') ? 'accepted' : 'rejected';
    $stmt_update = $conn->prepare("UPDATE double_date_members SET status = ? WHERE double_date_id = ? AND user_id = ?");
    $stmt_update->bind_param("sii", $status, $double_date_id, $user_id);
    $stmt_update->execute();

    $stmt_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt_read->bind_param("i", $notif_id);
    $stmt_read->execute();

    // KIỂM TRA XEM CÒN AI PENDING KHÔNG
    $stmt_check = $conn->prepare("SELECT COUNT(*) as pending_count FROM double_date_members WHERE double_date_id = ? AND status = 'pending'");
    $stmt_check->bind_param("i", $double_date_id);
    $stmt_check->execute();
    $pending_count = $stmt_check->get_result()->fetch_assoc()['pending_count'];

    // NẾU KHÔNG CÒN AI PENDING -> CHUYỂN PHÒNG THÀNH ACTIVE
    if ($pending_count == 0) {
        $stmt_activate = $conn->prepare("UPDATE double_dates SET status = 'active' WHERE id = ?");
        $stmt_activate->bind_param("i", $double_date_id);
        $stmt_activate->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'double_date_id' => $double_date_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>