<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$current_user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: explore.php");
    exit();
}
$target_user_id = (int)$_GET['id'];

if ($target_user_id === $current_user_id) {
    header("Location: preview.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$target_user = $stmt->get_result()->fetch_assoc();

if (!$target_user) {
    header("Location: explore.php");
    exit();
}

$age = date_diff(date_create($target_user['dob']), date_create('today'))->y;
$display_name = !empty($target_user['nickname']) ? $target_user['nickname'] : $target_user['full_name'];

$stmt_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ?");
$stmt_int->bind_param("i", $target_user_id);
$stmt_int->execute();
$interests_result = $stmt_int->get_result();
$target_interests = [];
while($row = $interests_result->fetch_assoc()) { $target_interests[] = $row['name']; }

$vibe_title = "Mysterious Vibe";
if(count($target_interests) > 0) {
    $vibe_title = implode(" & ", array_slice($target_interests, 0, 2));
}

$target_photos = [];
if (!empty($target_user['avatar'])) $target_photos[] = $target_user['avatar'];
for ($i = 1; $i <= 6; $i++) {
    if (!empty($target_user["photo_$i"])) $target_photos[] = $target_user["photo_$i"];
}
if (empty($target_photos)) $target_photos[] = 'default';

$height = !empty($target_user['height']) ? $target_user['height'] : 'Not specified';
$edu = !empty($target_user['education']) ? $target_user['education'] : 'Not specified';
$drink = !empty($target_user['drinking']) ? $target_user['drinking'] : 'Not specified';
$pets = !empty($target_user['pets']) ? $target_user['pets'] : 'Not specified';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($display_name) ?>'s Profile - SoulSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
</head>
<body class="dashboard-body">

    <?php include 'header.php'; ?>

    <main class="preview-container">
        <div style="display: flex; justify-content: space-between; align-items: center; max-width: 500px; margin: 0 auto 20px;">
            <a href="explore.php" class="btn-secondary" style="text-decoration: none; padding: 10px 20px; border-radius: 8px;"><i class="fa-solid fa-arrow-left"></i> Back to Explore</a>
        </div>
        
        <div class="expanded-card swipeable-card" id="card-<?= $target_user_id ?>" style="margin-top: 0;">
            
            <div class="expanded-photo">
                <?php foreach($target_photos as $index => $photo): ?>
                    <img src="../uploads/<?= htmlspecialchars($photo) ?>" class="card-img <?= $index === 0 ? 'active' : '' ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random'">
                <?php endforeach; ?>
                
                <div class="badge-container">
                    <span class="badge-match" style="margin:0; background:#22d3ee; color:#0f172a;">NEW VIBE</span>
                </div>

                <?php if(count($target_photos) > 1): ?>
                <div class="carousel-nav">
                    <button class="btn-carousel" onclick="changePhoto(event, -1)"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="btn-carousel" onclick="changePhoto(event, 1)"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
                <?php endif; ?>
            </div>

            <div class="expanded-info">
                <h2><?= htmlspecialchars($display_name) ?>, <?= $age ?></h2>
                <p class="distance"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($target_user['location'] ?? 'Unknown Location') ?></p>
                
                <p class="bio-text">"<?= htmlspecialchars($target_user['bio']) ?>"</p>
                
                <div class="expanded-tags">
                    <?php foreach(array_slice($target_interests, 0, 4) as $int): ?>
                        <span><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($int) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="info-vibe-box">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                        <h4 style="margin:0; color:#5d1029; font-size:0.9rem;"><i class="fa-solid fa-wand-magic-sparkles"></i> Current Vibe</h4>
                    </div>
                    <div class="emoji">✨</div>
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
                <button class="act-btn close" onclick="swipeAction('left', this)"><i class="fa-solid fa-xmark"></i></button>
                <button class="act-btn like" onclick="swipeAction('right', this)"><i class="fa-solid fa-heart" style="font-size:1.3rem;"></i> LIKE</button>
            </div>
            
        </div>
    </main>

    <div id="toast" class="toast-notification"></div>

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
            const container = event.currentTarget.closest('.expanded-photo') || event.currentTarget.closest('.card-inner');
            changePhotoAction(container, direction);
        }

        function swipeAction(direction, btnElement) {
            const card = btnElement.closest('.swipeable-card');
            if (!card) return;
            
            const targetId = card.id.replace('card-', '');
            const actionType = direction === 'right' ? 'like' : 'pass';
            
            if (direction === 'left') {
                card.classList.add('swipe-left');
            } else {
                card.classList.add('swipe-right');
            }

            fetch('../api/like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ target_user_id: targetId, action: actionType })
            })
            .then(res => res.json())
            .then(data => {
                if(data.is_match) {
                    const toast = document.getElementById('toast');
                    toast.innerHTML = '<i class="fa-solid fa-heart" style="color:#fff;"></i> IT\'S A MATCH! 🎉';
                    toast.style.background = 'var(--y2k-gradient)';
                    toast.classList.add('show');
                    
                    setTimeout(() => { 
                        window.location.href = 'messages.php?mode=standard&chat_with=' + targetId; 
                    }, 2000);
                } else {
                    setTimeout(() => { window.location.href = 'explore.php'; }, 500); 
                }
            });
        }

        document.addEventListener('keydown', function(event) {
            if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

            const topCard = document.querySelector('.expanded-card');
            if (!topCard) return;

            const direction = event.key === 'ArrowLeft' ? -1 : 1;
            changePhotoAction(topCard, direction);
        });
    </script>
</body>
</html>
