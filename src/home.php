<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$is_pro = isset($current_user['is_pro']) ? $current_user['is_pro'] : false; 

$stmt_my_int = $conn->prepare("SELECT interest_id FROM user_interests WHERE user_id = ?");
$stmt_my_int->bind_param("i", $user_id);
$stmt_my_int->execute();
$my_interests = array_column($stmt_my_int->get_result()->fetch_all(MYSQLI_ASSOC), 'interest_id');

$stmt_others = $conn->prepare("SELECT * FROM profiles WHERE user_id != ? AND interested_in = ?");
$my_gender = $current_user['gender'];
$stmt_others->bind_param("is", $user_id, $my_gender);
$stmt_others->execute();
$others_result = $stmt_others->get_result();

$suggested_list = [];
while ($other = $others_result->fetch_assoc()) {
    $stmt_ti = $conn->prepare("SELECT interest_id FROM user_interests WHERE user_id = ?");
    $stmt_ti->bind_param("i", $other['user_id']);
    $stmt_ti->execute();
    $their_interests = array_column($stmt_ti->get_result()->fetch_all(MYSQLI_ASSOC), 'interest_id');
    
    $common = array_intersect($my_interests, $their_interests);
    if (count($common) > 0) { 
        $score = 70 + (count($common) * 5); 
        $other['match_rate'] = min(99, $score);
        $suggested_list[] = $other;
    }
}
usort($suggested_list, function($a, $b) { return $b['match_rate'] <=> $a['match_rate']; });
$suggested = $suggested_list[0] ?? null;

$compat_text = 'Potential Match';
$suggested_photos = [];
$suggested_name = 'Unknown';
$suggested_age = 20;
$sug_vibe_title = "Mysterious Vibe";
$sug_height = $sug_edu = $sug_drink = $sug_pets = "-";

if ($suggested) {
    if ($suggested['match_rate'] >= 90) { $compat_text = 'Soulmate Level'; }
    elseif ($suggested['match_rate'] >= 80) { $compat_text = 'High Compatibility'; }
    elseif ($suggested['match_rate'] >= 70) { $compat_text = 'Good Match'; }

    if (!empty($suggested['avatar'])) $suggested_photos[] = $suggested['avatar'];
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($suggested["photo_$i"])) $suggested_photos[] = $suggested["photo_$i"];
    }
    if (empty($suggested_photos)) $suggested_photos[] = 'default';

    $suggested_name = !empty($suggested['nickname']) ? $suggested['nickname'] : $suggested['full_name'];
    $suggested_age = date_diff(date_create($suggested['dob']), date_create('today'))->y;

    $sug_height = !empty($suggested['height']) ? $suggested['height'] : 'Not specified';
    $sug_edu = !empty($suggested['education']) ? $suggested['education'] : 'Not specified';
    $sug_drink = !empty($suggested['drinking']) ? $suggested['drinking'] : 'Not specified';
    $sug_pets = !empty($suggested['pets']) ? $suggested['pets'] : 'Not specified';

    $stmt_sug_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? LIMIT 2");
    $stmt_sug_int->bind_param("i", $suggested['user_id']);
    $stmt_sug_int->execute();
    $sug_vibe_res = $stmt_sug_int->get_result();
    $sug_vibes = [];
    while($v = $sug_vibe_res->fetch_assoc()){ $sug_vibes[] = $v['name']; }
    if(count($sug_vibes) > 0) { $sug_vibe_title = implode(" & ", $sug_vibes); }
}

$messages = []; 
$match_rate_overall = count($messages) > 0 ? "78%" : "0%"; 
$response_rate = count($messages) > 0 ? "92%" : "0%";

$stmt_vibe = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? LIMIT 2");
$stmt_vibe->bind_param("i", $user_id);
$stmt_vibe->execute();
$vibe_res = $stmt_vibe->get_result();
$vibes = [];
while($v = $vibe_res->fetch_assoc()){ $vibes[] = $v['name']; }
$your_vibe = count($vibes) > 0 ? implode(" & ", $vibes) : "Mysterious Vibe";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SoulSync - Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
</head>
<body class="dashboard-body">

    <?php include 'header.php'; ?>

    <div class="dash-container">
        <aside class="left-sidebar">
            <div class="conversations-area">
                <div class="section-header">
                    <h3 style="color:#a82253;">Conversations</h3>
                    <span class="count-badge"><?= count($messages) ?></span>
                </div>
                <div class="chat-list">
                    <?php if(empty($messages)): ?>
                        <div style="text-align:center; padding: 40px 0; color:#999; font-size:0.85rem;">
                            <i class="fa-solid fa-ghost" style="font-size:2rem; margin-bottom:10px; color:#e0e0e0;"></i><br>
                            You haven't matched with anyone yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="soul-insights">
                <h3 style="font-size: 0.75rem; color:#999; letter-spacing:1px; margin-bottom:10px;">SOUL INSIGHTS</h3>
                <div class="insight-cards">
                    <div class="insight-card"><span class="label"><?= $match_rate_overall ?></span><span class="sub">MATCH RATE</span></div>
                    <div class="insight-card"><span class="label"><?= $response_rate ?></span><span class="sub">RESPONSE</span></div>
                </div>
                <div class="vibe-box"><p>Your Vibe</p><strong><?= htmlspecialchars($your_vibe) ?></strong></div>
            </div>
        </aside>

        <main class="main-feed">
            <div class="vibe-filter"><button class="btn-vibe"><i class="fa-solid fa-filter"></i> FILTER BY VIBE</button></div>
            
            <?php if ($suggested): ?>
            
            <div class="expanded-card">
                
                <div class="expanded-photo">
                    <?php foreach($suggested_photos as $index => $photo): ?>
                        <img src="../uploads/<?= htmlspecialchars($photo) ?>" class="card-img <?= $index === 0 ? 'active' : '' ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random'">
                    <?php endforeach; ?>
                    
                    <div class="badge-container">
                        <span class="badge-match" style="margin:0;"><?= $suggested['match_rate'] ?>% SOULSYNC</span>
                        <span class="badge-compat" style="margin:0; background:rgba(255,255,255,0.8); color:#e83e8c; font-weight:700; border: 1px solid #fff;"><?= $compat_text ?></span>
                    </div>

                    <?php if(count($suggested_photos) > 1): ?>
                    <div class="carousel-nav">
                        <button class="btn-carousel" onclick="changePhoto(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                        <button class="btn-carousel" onclick="changePhoto(1)"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="expanded-info">
                    <h2><?= htmlspecialchars($suggested_name) ?>, <?= $suggested_age ?></h2>
                    <p class="distance"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($suggested['location'] ?? 'Unknown Location') ?></p>
                    
                    <p class="bio-text">"<?= htmlspecialchars($suggested['bio']) ?>"</p>
                    
                    <div class="expanded-tags">
                        <span><i class="fa-solid fa-mug-hot"></i> Coffee Lovers</span>
                        <span><i class="fa-solid fa-hand-sparkles"></i> Soul Matched</span>
                    </div>

                    <div class="info-vibe-box">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                            <h4 style="margin:0; color:#5d1029; font-size:0.9rem;"><i class="fa-solid fa-wand-magic-sparkles"></i> Current Vibe</h4>
                        </div>
                        <div class="emoji">💗</div>
                        <h4><?= htmlspecialchars($sug_vibe_title) ?></h4>
                        <p>People perceive this profile as peaceful and open-hearted.</p>
                    </div>

                    <div class="details-list">
                        <h4><i class="fa-solid fa-sliders"></i> Personal Details</h4>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fa-solid fa-ruler-vertical"></i> Height</span>
                            <span class="detail-value"><?= htmlspecialchars($sug_height) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fa-solid fa-graduation-cap"></i> Education</span>
                            <span class="detail-value"><?= htmlspecialchars($sug_edu) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fa-solid fa-wine-glass"></i> Drinking</span>
                            <span class="detail-value"><?= htmlspecialchars($sug_drink) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fa-solid fa-paw"></i> Pets</span>
                            <span class="detail-value"><?= htmlspecialchars($sug_pets) ?></span>
                        </div>
                    </div>
                </div>

                <div class="expanded-action-bar">
                    <button class="act-btn close"><i class="fa-solid fa-xmark"></i></button>
                    <button class="act-btn like"><i class="fa-solid fa-heart" style="font-size:1.3rem;"></i> LIKE</button>
                    <button class="act-btn star"><i class="fa-solid fa-star"></i></button>
                </div>
                
            </div>

            <div class="ai-icebreaker-container">
                <div class="icebreaker-tooltip">Need an icebreaker?</div>
                <button class="btn-ai-bot"><i class="fa-solid fa-robot"></i></button>
            </div>
            
            <?php else: ?>
            <div class="swipe-card empty" style="display:flex; justify-content:center; align-items:center; background:#fff; box-shadow: 0 20px 50px rgba(0,0,0,0.05);">
                <p style="color:#999; font-weight:600;"><i class="fa-solid fa-radar"></i> Scanning your area...</p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function changePhoto(direction) {
            const images = document.querySelectorAll('.expanded-photo .card-img');
            if(images.length <= 1) return;
            
            let activeIndex = -1;
            images.forEach((img, index) => {
                if(img.classList.contains('active')) activeIndex = index;
                img.classList.remove('active');
            });
            
            let newIndex = activeIndex + direction;
            if(newIndex >= images.length) newIndex = 0;
            if(newIndex < 0) newIndex = images.length - 1;
            
            images[newIndex].classList.add('active');
        }
    </script>
</body>
</html>