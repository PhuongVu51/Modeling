<?php
require 'api/db_connect.php';

// Fetch users
$users = $conn->query("SELECT id FROM users LIMIT 2")->fetch_all(MYSQLI_ASSOC);
if (count($users) < 2) {
    die("Need at least 2 users");
}

$sender_id = $users[0]['id'];
$receiver_id = $users[1]['id'];
$message_text = "DATE_SPOT_TAG:" . json_encode([
    'name' => 'Lighthouse Sky Bar',
    'image' => '../image/lighthouseskybar.jpg',
    'likes' => '96',
    'map_url' => 'https://maps.google.com/'
]);

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $sender_id, $receiver_id, $message_text);

if ($stmt->execute()) {
    echo "Success\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}
