<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'create_post') {
    $caption = trim($_POST['caption'] ?? '');
    $mood_tag = trim($_POST['mood_tag'] ?? '');

    if (empty($caption)) {
        echo json_encode(['status' => 'error', 'message' => 'Caption is required']);
        exit();
    }

    // Get a shared interest for this post (pick random from user's interests)
    $res = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? ORDER BY RAND() LIMIT 1");
    $res->bind_param("i", $user_id);
    $res->execute();
    $int_row = $res->get_result()->fetch_assoc();
    $shared_interest = $int_row['name'] ?? 'Travel';

    // Handle photo upload
    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $targetDir = dirname(__FILE__) . '/../uploads/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $newName = 'post_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . $newName)) {
            $photo = $newName;
        }
    }

    $stmt = $conn->prepare("INSERT INTO posts (user_id, caption, photo, mood_tag, shared_interest, likes_count, comments_count) VALUES (?, ?, ?, ?, ?, 0, 0)");
    $stmt->bind_param("issss", $user_id, $caption, $photo, $mood_tag, $shared_interest);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'post_id' => $conn->insert_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create post: ' . $conn->error]);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
