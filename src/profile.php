<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

$age = date_diff(date_create($current_user['dob']), date_create('today'))->y;
$stmt_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ?");
$stmt_int->bind_param("i", $user_id); $stmt_int->execute();
$interests_result = $stmt_int->get_result();
$my_interests = [];
while($row = $interests_result->fetch_assoc()) { $my_interests[] = $row['name']; }

$interest_icons = ['Music' => '🎸', 'Travel' => '✈️', 'Coffee' => '☕', 'Reading' => '📚', 'Gym' => '💪', 'Pets' => '🐾', 'Movies' => '🍿', 'Cooking' => '🍳', 'Gaming' => '🎮', 'Art' => '🎨', 'Photography' => '📸', 'Dancing' => '💃', 'Foodie' => '🍜', 'Sports' => '⚽', 'Karaoke' => '🎤'];

// LOGIC HIỂN THỊ TÊN: Ưu tiên Nickname, nếu rỗng thì mới lấy Tên thật
$display_name = !empty($current_user['nickname']) ? $current_user['nickname'] : $current_user['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SoulSync - Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
</head>
<body class="dashboard-body">
    <?php include 'header.php'; ?>
    <main class="profile-wrapper">
        <div class="profile-header-card">
            <div class="avatar-with-edit">
                <img src="../uploads/<?= htmlspecialchars($current_user['avatar']) ?>" class="avatar-huge" onerror="this.src='https://ui-avatars.com/api/?name=U&background=random'">
                <button type="button" class="btn-edit-avatar" onclick="window.location.href='edit_profile.php'"><i class="fa-solid fa-pencil"></i></button>
            </div>
            <div class="profile-info-main">
                <h1><?= htmlspecialchars($display_name) ?>, <?= $age ?></h1>
                <p class="tagline"><?= htmlspecialchars($current_user['location'] ?? 'Unknown') ?> • <?= htmlspecialchars($current_user['occupation'] ?? 'Professional') ?></p>
                <p class="bio-quote">"<?= htmlspecialchars($current_user['bio']) ?>"</p>
            </div>
            <div class="profile-actions"><a href="edit_profile.php" class="btn-edit">Edit Profile</a><a href="preview.php" class="btn-preview">Preview</a></div>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><i class="fa-regular fa-heart" style="position:absolute;right:25px;color:#e83e8c;"></i><h4>Match Rate</h4><div class="number">0%</div><p style="color:#999;font-size:0.8rem;">New account</p></div>
            <div class="stat-card"><i class="fa-regular fa-comment" style="position:absolute;right:25px;color:#e83e8c;"></i><h4>Response Rate</h4><div class="number">0%</div><p style="color:#999;font-size:0.8rem;">No messages</p></div>
            <div class="stat-card"><i class="fa-regular fa-eye" style="position:absolute;right:25px;color:#e83e8c;"></i><h4>Profile Views</h4><div class="number">0</div><p style="color:#999;font-size:0.8rem;">Awaiting visitors</p></div>
        </div>
        <div class="vibe-ai-grid">
            <div class="edit-panel" style="text-align:center;background:#fff5f8;"><h3>💗 Your Current Vibe</h3><div style="font-size:3rem;">🔍</div><p>AI is analyzing your vibes based on your <b><?= count($my_interests) ?> interests</b>.</p></div>
            <div class="ai-feedback-card"><h3><i class="fa-solid fa-lightbulb"></i> AI Feedback</h3><p>Keep swiping and matching to let our AI learn your style! Your profile is <b>60% complete</b>.</p><button onclick="window.location.href='home.php'" style="background:#fff;color:#5d1029;padding:12px;border-radius:12px;font-weight:800;border:none;cursor:pointer;">Start Matching Now</button></div>
        </div>
        <div>
            <h2 style="margin-bottom:20px;color:#5d1029;">Top Interests</h2>
            <div class="tags-container" style="gap:15px;">
                <?php foreach($my_interests as $int): $icon = $interest_icons[$int] ?? '✨'; ?>
                    <span class="interest-pill"><?= $icon ?> <?= htmlspecialchars($int) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="text-align: center; margin-top: 50px;"><button onclick="window.location.href='../api/logout.php'" style="background:var(--y2k-pink); color:#fff; border:none; padding:12px 40px; border-radius:50px; font-weight:800; cursor:pointer;"><i class="fa-solid fa-arrow-right-from-bracket"></i> LOG OUT</button></div>
    </main>
</body>
</html>