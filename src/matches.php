<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

// FETCH TOÀN BỘ NGƯỜI ĐÃ MATCH
$stmt_matches = $conn->prepare("
    SELECT p.*, m.created_at as match_date 
    FROM matches m 
    JOIN profiles p ON (p.user_id = m.user1_id OR p.user_id = m.user2_id) 
    WHERE (m.user1_id = ? OR m.user2_id = ?) AND p.user_id != ?
    ORDER BY m.created_at DESC
");
$stmt_matches->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_matches->execute();
$matches_result = $stmt_matches->get_result();
$matches = [];
while($row = $matches_result->fetch_assoc()){
    $matches[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SoulSync - Matches</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
</head>
<body class="dashboard-body" style="overflow-y: auto;">
    <?php include 'header.php'; ?>

    <main class="profile-wrapper" style="align-items: center; max-width: 1000px; padding-bottom: 100px;">
        <h1 style="color:#5d1029; margin-bottom:20px; font-size: 2.5rem;"><i class="fa-solid fa-heart" style="color:#ff4b82;"></i> Your Matches</h1>
        
        <?php if(empty($matches)): ?>
            <div style="text-align:center; padding: 100px 0; color:#999; background: #fff; width: 100%; border-radius: 30px; box-shadow: var(--shadow-soft);">
                <i class="fa-solid fa-heart-crack" style="font-size:4rem; margin-bottom:20px; color:#ffe5f0;"></i>
                <h3>No matches yet!</h3>
                <p>Keep swiping to find your soulmate.</p>
                <button onclick="window.location.href='home.php'" class="btn-save-changes" style="margin-top:20px; font-size:1rem;">Go Swipe</button>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 50px; width: 100%;">
                <?php foreach($matches as $m): 
                    $m_name = !empty($m['nickname']) ? $m['nickname'] : $m['full_name'];
                    $m_age = date_diff(date_create($m['dob']), date_create('today'))->y;
                    $m_photos = [];
                    if (!empty($m['avatar'])) $m_photos[] = $m['avatar'];
                    for ($i = 1; $i <= 6; $i++) {
                        if (!empty($m["photo_$i"])) $m_photos[] = $m["photo_$i"];
                    }
                    if (empty($m_photos)) $m_photos[] = 'default';
                ?>
                    <div class="expanded-card" style="position: relative; box-shadow: 0 15px 35px rgba(0,0,0,0.1); height: 500px;">
                        <div class="expanded-photo">
                            <?php foreach($m_photos as $index => $photo): ?>
                                <img src="../uploads/<?= htmlspecialchars($photo) ?>" class="card-img <?= $index === 0 ? 'active' : '' ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random'">
                            <?php endforeach; ?>
                            
                            <div class="badge-container">
                                <span class="badge-match" style="margin:0;"><i class="fa-solid fa-fire"></i> IT'S A MATCH</span>
                            </div>

                            <?php if(count($m_photos) > 1): ?>
                            <div class="carousel-nav">
                                <button class="btn-carousel" onclick="changePhoto(event, -1)"><i class="fa-solid fa-chevron-left"></i></button>
                                <button class="btn-carousel" onclick="changePhoto(event, 1)"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="expanded-info">
                            <h2><?= htmlspecialchars($m_name) ?>, <?= $m_age ?></h2>
                            <p class="distance"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($m['location'] ?? 'Unknown Location') ?></p>
                            <p class="bio-text">"<?= htmlspecialchars($m['bio']) ?>"</p>
                            
                            <div class="info-vibe-box" style="margin-top:auto; padding:0; border:none; background:transparent;">
                                <button class="btn-save-changes" style="width:100%; padding:15px; font-size:1.1rem; border-radius:15px;"><i class="fa-solid fa-comment-dots"></i> Send Message</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function changePhotoAction(container, direction) {
            const images = container.querySelectorAll('.card-img');
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
        function changePhoto(event, direction) {
            const container = event.currentTarget.closest('.expanded-photo');
            changePhotoAction(container, direction);
        }
    </script>
</body>
</html>