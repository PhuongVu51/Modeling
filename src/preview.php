<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];

// 1. LẤY THÔNG TIN CỦA CHÍNH MÌNH TỪ DB
$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

$age = date_diff(date_create($current_user['dob']), date_create('today'))->y;
// Ưu tiên hiển thị Nickname
$display_name = !empty($current_user['nickname']) ? $current_user['nickname'] : $current_user['full_name'];

// 2. LẤY SỞ THÍCH CỦA MÌNH
$stmt_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ?");
$stmt_int->bind_param("i", $user_id);
$stmt_int->execute();
$interests_result = $stmt_int->get_result();
$my_interests = [];
while($row = $interests_result->fetch_assoc()) { $my_interests[] = $row['name']; }

// Lấy 2 sở thích đầu làm Vibe
$vibe_title = "Mysterious Vibe";
if(count($my_interests) > 0) {
    $vibe_title = implode(" & ", array_slice($my_interests, 0, 2));
}

// 3. GOM TOÀN BỘ ẢNH VÀO MẢNG
$my_photos = [];
if (!empty($current_user['avatar'])) $my_photos[] = $current_user['avatar'];
for ($i = 1; $i <= 6; $i++) {
    if (!empty($current_user["photo_$i"])) $my_photos[] = $current_user["photo_$i"];
}
if (empty($my_photos)) $my_photos[] = 'default';

// 4. XỬ LÝ TEXT TRỐNG
$height = !empty($current_user['height']) ? $current_user['height'] : 'Not specified';
$edu = !empty($current_user['education']) ? $current_user['education'] : 'Not specified';
$drink = !empty($current_user['drinking']) ? $current_user['drinking'] : 'Not specified';
$pets = !empty($current_user['pets']) ? $current_user['pets'] : 'Not specified';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Preview Profile - SoulSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
</head>
<body class="dashboard-body">

    <?php include 'header.php'; ?>

    <main class="preview-container">
        <h2 class="preview-title">This is how others see you</h2>
        
        <div class="expanded-card" style="margin-bottom: 0;">
            
            <div class="expanded-photo">
                <?php foreach($my_photos as $index => $photo): ?>
                    <img src="../uploads/<?= htmlspecialchars($photo) ?>" class="card-img <?= $index === 0 ? 'active' : '' ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random'">
                <?php endforeach; ?>
                
                <div class="badge-container">
                    <span class="badge-match" style="margin:0; background:#111;">YOUR PROFILE</span>
                    <span class="badge-compat" style="margin:0; background:rgba(255,255,255,0.9); color:#e83e8c; font-weight:700; border: 1px solid #fff;">Looking Good!</span>
                </div>

                <?php if(count($my_photos) > 1): ?>
                <div class="carousel-nav">
                    <button class="btn-carousel" onclick="changePhoto(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="btn-carousel" onclick="changePhoto(1)"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
                <?php endif; ?>
            </div>

            <div class="expanded-info">
                <h2><?= htmlspecialchars($display_name) ?>, <?= $age ?></h2>
                <p class="distance"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($current_user['location'] ?? 'Unknown Location') ?></p>
                
                <p class="bio-text">"<?= htmlspecialchars($current_user['bio']) ?>"</p>
                
                <div class="expanded-tags">
                    <?php foreach(array_slice($my_interests, 0, 4) as $int): ?>
                        <span><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($int) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="info-vibe-box">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                        <h4 style="margin:0; color:#5d1029; font-size:0.9rem;"><i class="fa-solid fa-wand-magic-sparkles"></i> Current Vibe</h4>
                    </div>
                    <div class="emoji">💗</div>
                    <h4><?= htmlspecialchars($vibe_title) ?></h4>
                    <p>People perceive this profile as peaceful and open-hearted.</p>
                </div>

                <div class="details-list">
                    <h4><i class="fa-solid fa-sliders"></i> Personal Details</h4>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-ruler-vertical"></i> Height</span>
                        <span class="detail-value"><?= htmlspecialchars($height) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-graduation-cap"></i> Education</span>
                        <span class="detail-value"><?= htmlspecialchars($edu) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-wine-glass"></i> Drinking</span>
                        <span class="detail-value"><?= htmlspecialchars($drink) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-paw"></i> Pets</span>
                        <span class="detail-value"><?= htmlspecialchars($pets) ?></span>
                    </div>
                </div>
            </div>

            <div class="expanded-action-bar">
                <button class="act-btn close" onclick="window.location.href='edit_profile.php'" title="Edit Profile"><i class="fa-solid fa-pencil"></i></button>
                <button class="act-btn like" onclick="window.location.href='profile.php'"><i class="fa-solid fa-check" style="font-size:1.3rem;"></i> DONE</button>
                <button class="act-btn star" onclick="window.location.href='home.php'" title="Go to Home"><i class="fa-solid fa-house"></i></button>
            </div>
            
        </div>
    </main>

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