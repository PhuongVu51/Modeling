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

// GOM DỮ LIỆU CỦA TOÀN BỘ NGƯỜI GỢI Ý ĐỂ XẾP THÀNH XẤP THẺ
$cards_data = [];
foreach ($suggested_list as $sug) {
    $compat_text = 'Potential Match';
    if ($sug['match_rate'] >= 90) { $compat_text = 'Soulmate Level'; }
    elseif ($sug['match_rate'] >= 80) { $compat_text = 'High Compatibility'; }
    elseif ($sug['match_rate'] >= 70) { $compat_text = 'Good Match'; }

    $photos = [];
    if (!empty($sug['avatar'])) $photos[] = $sug['avatar'];
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($sug["photo_$i"])) $photos[] = $sug["photo_$i"];
    }
    if (empty($photos)) $photos[] = 'default';

    $sug['display_name'] = !empty($sug['nickname']) ? $sug['nickname'] : $sug['full_name'];
    $sug['display_age'] = date_diff(date_create($sug['dob']), date_create('today'))->y;
    $sug['photos'] = $photos;
    $sug['compat_text'] = $compat_text;
    
    // Default info if empty
    $sug['height'] = !empty($sug['height']) ? $sug['height'] : 'Not specified';
    $sug['education'] = !empty($sug['education']) ? $sug['education'] : 'Not specified';
    $sug['drinking'] = !empty($sug['drinking']) ? $sug['drinking'] : 'Not specified';
    $sug['pets'] = !empty($sug['pets']) ? $sug['pets'] : 'Not specified';

    // Vibe cho từng thẻ
    $stmt_sug_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? LIMIT 2");
    $stmt_sug_int->bind_param("i", $sug['user_id']);
    $stmt_sug_int->execute();
    $sug_vibe_res = $stmt_sug_int->get_result();
    $sug_vibes = [];
    while($v = $sug_vibe_res->fetch_assoc()){ $sug_vibes[] = $v['name']; }
    $sug['vibe_title'] = count($sug_vibes) > 0 ? implode(" & ", $sug_vibes) : "Mysterious Vibe";
    
    $cards_data[] = $sug;
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

        <main class="main-feed" style="position: relative;">
            <div class="vibe-filter"><button class="btn-vibe"><i class="fa-solid fa-filter"></i> FILTER BY VIBE</button></div>
            
            <div class="cards-stack">
                <div class="expanded-card empty-state" style="display:flex; justify-content:center; align-items:center; flex-direction:column; z-index: 0;">
                    <i class="fa-solid fa-radar" style="font-size:3rem; color:#ccc; margin-bottom:15px;"></i>
                    <p style="color:#999; font-weight:600; font-size:1.2rem;">No more profiles in your area.</p>
                </div>

                <?php 
                $z_index = 1;
                foreach (array_reverse($cards_data) as $idx => $sug): 
                ?>
                <div class="expanded-card swipeable-card" id="card-<?= $sug['user_id'] ?>" style="z-index: <?= $z_index++; ?>;">
                    
                    <div class="expanded-photo">
                        <?php foreach($sug['photos'] as $pIndex => $photo): ?>
                            <img src="../uploads/<?= htmlspecialchars($photo) ?>" class="card-img <?= $pIndex === 0 ? 'active' : '' ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random'">
                        <?php endforeach; ?>
                        
                        <div class="badge-container">
                            <span class="badge-match" style="margin:0;"><?= $sug['match_rate'] ?>% SOULSYNC</span>
                            <span class="badge-compat" style="margin:0; background:rgba(255,255,255,0.8); color:#e83e8c; font-weight:700; border: 1px solid #fff;"><?= $sug['compat_text'] ?></span>
                        </div>

                        <?php if(count($sug['photos']) > 1): ?>
                        <div class="carousel-nav">
                            <button class="btn-carousel" onclick="changePhoto(event, -1)"><i class="fa-solid fa-chevron-left"></i></button>
                            <button class="btn-carousel" onclick="changePhoto(event, 1)"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="expanded-info">
                        <h2><?= htmlspecialchars($sug['display_name']) ?>, <?= $sug['display_age'] ?></h2>
                        <p class="distance"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($sug['location'] ?? 'Unknown Location') ?></p>
                        
                        <p class="bio-text">"<?= htmlspecialchars($sug['bio']) ?>"</p>
                        
                        <div class="expanded-tags">
                            <span><i class="fa-solid fa-mug-hot"></i> Coffee Lovers</span>
                            <span><i class="fa-solid fa-hand-sparkles"></i> Soul Matched</span>
                        </div>

                        <div class="info-vibe-box">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                                <h4 style="margin:0; color:#5d1029; font-size:0.9rem;"><i class="fa-solid fa-wand-magic-sparkles"></i> Current Vibe</h4>
                            </div>
                            <div class="emoji">💗</div>
                            <h4><?= htmlspecialchars($sug['vibe_title']) ?></h4>
                            <p>People perceive this profile as peaceful and open-hearted.</p>
                        </div>

                        <div class="details-list">
                            <h4><i class="fa-solid fa-sliders"></i> Personal Details</h4>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-ruler-vertical"></i> Height</span>
                                <span class="detail-value"><?= htmlspecialchars($sug['height']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-graduation-cap"></i> Education</span>
                                <span class="detail-value"><?= htmlspecialchars($sug['education']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-wine-glass"></i> Drinking</span>
                                <span class="detail-value"><?= htmlspecialchars($sug['drinking']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fa-solid fa-paw"></i> Pets</span>
                                <span class="detail-value"><?= htmlspecialchars($sug['pets']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="expanded-action-bar">
                        <button class="act-btn close" onclick="swipeAction('left', this)"><i class="fa-solid fa-xmark"></i></button>
                        <button class="act-btn like" onclick="swipeAction('right', this)"><i class="fa-solid fa-heart" style="font-size:1.3rem;"></i> LIKE</button>
                        <button class="act-btn star" onclick="saveFavorite(this)"><i class="fa-solid fa-star"></i></button>
                    </div>

                </div>
                <?php endforeach; ?>

                <div class="ai-icebreaker-container">
                    <div class="icebreaker-tooltip">Need an icebreaker?</div>
                    <button class="btn-ai-bot"><i class="fa-solid fa-robot"></i></button>
                </div>
            </div>
            
        </main>
    </div>

    <div id="toast" class="toast-notification">
        <i class="fa-solid fa-bookmark" style="color:#ff4b82;"></i> Saved to favorites
    </div>

    <script>
        // 1. Hàm lõi xử lý lướt ảnh (Dùng chung cho cả click chuột và bàn phím)
        function changePhotoAction(container, direction) {
            // Tìm tất cả ảnh trong cái thẻ hiện tại
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

        // 2. Gắn vào nút bấm mũi tên trên UI
        function changePhoto(event, direction) {
            const container = event.currentTarget.closest('.expanded-photo') || event.currentTarget.closest('.card-inner');
            changePhotoAction(container, direction);
        }

        // 3. Hàm Quẹt Thẻ (Swipe Left/Right)
        function swipeAction(direction, btnElement) {
            const card = btnElement.closest('.swipeable-card');
            if (!card) return;
            
            if (direction === 'left') {
                card.classList.add('swipe-left');
            } else {
                card.classList.add('swipe-right');
            }

            setTimeout(() => {
                card.style.display = 'none';
            }, 500); 
        }

        // 4. Hàm Lưu Yêu Thích & Hiện Toast
        function saveFavorite(btnElement) {
            btnElement.classList.toggle('saved');
            
            const toast = document.getElementById('toast');
            if (!toast) return; // Bỏ qua nếu ko có thẻ toast (trang preview)

            if (btnElement.classList.contains('saved')) {
                toast.innerHTML = '<i class="fa-solid fa-bookmark" style="color:#ff4b82;"></i> Saved to your list!';
            } else {
                toast.innerHTML = '<i class="fa-regular fa-bookmark"></i> Removed from list';
            }

            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }

        // ==========================================
        // 5. LẮNG NGHE BÀN PHÍM (LEFT / RIGHT ARROW)
        // ==========================================
        document.addEventListener('keydown', function(event) {
            // CHỐNG LỖI: Nếu đang gõ chữ vào ô Chat hoặc Input thì bỏ qua
            if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;

            // Chỉ nhận lệnh từ phím mũi tên Trái / Phải
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

            // Tìm thẻ quẹt ĐANG HIỆN TRÊN CÙNG (bỏ qua mấy thẻ đã bị quẹt ẩn đi)
            const visibleCards = Array.from(document.querySelectorAll('.swipeable-card')).filter(card => card.style.display !== 'none');
            
            let topCard = null;

            if (visibleCards.length > 0) {
                // Tìm thẻ có z-index lớn nhất (nằm trên cùng)
                topCard = visibleCards.reduce((prev, current) => {
                    return (parseInt(prev.style.zIndex) || 0) > (parseInt(current.style.zIndex) || 0) ? prev : current;
                });
            } else {
                // Xử lý cho trang preview.php (chỉ có 1 thẻ, không phải xấp)
                topCard = document.querySelector('.expanded-card, .main-card');
            }

            if (!topCard) return;

            // Mũi tên trái = lùi ảnh (-1), Mũi tên phải = tiến ảnh (1)
            const direction = event.key === 'ArrowLeft' ? -1 : 1;
            changePhotoAction(topCard, direction);
        });
    </script>
</body>
</html>