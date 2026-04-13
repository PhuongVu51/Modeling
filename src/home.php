<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.html"); 
    exit(); 
}
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];

// 1. LẤY THÔNG TIN NGƯỜI DÙNG HIỆN TẠI
$stmt = $conn->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

// 2. LẤY SỞ THÍCH ĐỂ MATCHING
$stmt_my_int = $conn->prepare("SELECT interest_id FROM user_interests WHERE user_id = ?");
$stmt_my_int->bind_param("i", $user_id);
$stmt_my_int->execute();
$my_interests = array_column($stmt_my_int->get_result()->fetch_all(MYSQLI_ASSOC), 'interest_id');

// 3. THUẬT TOÁN MATCHING AI
$stmt_others = $conn->prepare("SELECT * FROM profiles WHERE user_id != ?");
$stmt_others->bind_param("i", $user_id);
$stmt_others->execute();
$others_result = $stmt_others->get_result();

$matched_users = [];
while ($other = $others_result->fetch_assoc()) {
    $stmt_ti = $conn->prepare("SELECT interest_id FROM user_interests WHERE user_id = ?");
    $stmt_ti->bind_param("i", $other['user_id']);
    $stmt_ti->execute();
    $their_interests = array_column($stmt_ti->get_result()->fetch_all(MYSQLI_ASSOC), 'interest_id');
    
    $common = count(array_intersect($my_interests, $their_interests));
    $total_my = max(1, count($my_interests));
    $score = 50 + round(($common / $total_my) * 49);
    
    $other['match_rate'] = min(99, $score);
    $other['shared_count'] = $common;
    $matched_users[] = $other;
}

usort($matched_users, function($a, $b) { return $b['match_rate'] <=> $a['match_rate']; });
$suggested = $matched_users[0] ?? null;
$top_picks = array_slice($matched_users, 1, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SoulSync - Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dashboard-body" style="background-color: #ffffff !important;">

    <?php include 'header.php'; ?>

    <div class="dash-container">
        <aside class="left-sidebar">
            <div class="sidebar-section">
                <h3>Conversations</h3>
                <p style="text-align: center; padding: 20px; color: #888; font-size: 0.85rem;">No messages yet.</p>
            </div>
            <div class="sidebar-section">
                <h3>Soul Insights</h3>
                <div class="insight-box">
                    <h4 style="color: #ff4b82;"><?= count($matched_users) > 0 ? "Ready" : "Scanning" ?></h4>
                    <p style="font-size: 0.7rem; color: #666;">Based on your interests.</p>
                </div>
            </div>
        </aside>

        <main class="main-feed">
            <button class="filter-btn"><i class="fa-solid fa-filter"></i> FILTER BY VIBE</button>
            <?php if ($suggested): ?>
                <div class="swipe-card">
                    <img src="../uploads/<?= htmlspecialchars($suggested['avatar']) ?>" class="card-img" onerror="this.src='https://ui-avatars.com/api/?name=User'">
                    <div class="card-overlay">
                        <span class="badge-pink"><?= $suggested['match_rate'] ?>% SOULSYNC</span>
                        <h2><?= htmlspecialchars($suggested['full_name']) ?></h2>
                        <p class="quote">"<?= htmlspecialchars($suggested['bio']) ?>"</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="swipe-card" style="display:flex; align-items:center; justify-content:center; background:#f9f9f9;">
                    <p>No matches found yet.</p>
                </div>
            <?php endif; ?>
        </main>

        <aside class="right-sidebar">
            <div class="sidebar-section">
                <h3>Top Picks</h3>
                <div class="picks-list">
                    <?php foreach ($top_picks as $p): ?>
                        <div class="pick-item">
                            <img src="../uploads/<?= htmlspecialchars($p['avatar']) ?>" style="width:50px; height:50px; border-radius:10px; object-fit:cover;">
                            <div class="pick-info">
                                <h4><?= htmlspecialchars($p['nickname'] ?? $p['full_name']) ?></h4>
                                <span style="color:#ff4b82; font-size:0.8rem; font-weight:bold;"><?= $p['match_rate'] ?>% Sync</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</body>
</html>