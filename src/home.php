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

// ==========================================
// TÌM KIẾM NGƯỜI ĐỂ QUẸT (KHỚP GU 2 CHIỀU)
// ==========================================
$my_gender = $current_user['gender'];
$my_interested_in = $current_user['interested_in'];

$stmt_others = $conn->prepare("
    SELECT * FROM profiles 
    WHERE user_id != ? 
    AND (? = 'Anyone' OR gender = ?) 
    AND (interested_in = 'Anyone' OR interested_in = ?)
    AND user_id NOT IN (SELECT liked_user_id FROM likes WHERE user_id = ?)
");
$stmt_others->bind_param("isssi", $user_id, $my_interested_in, $my_interested_in, $my_gender, $user_id);
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
    } else {
        $score = 45 + rand(1, 10); 
    }
    
    $other['match_rate'] = min(99, $score);
    $suggested_list[] = $other;
}
usort($suggested_list, function($a, $b) { return $b['match_rate'] <=> $a['match_rate']; });

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
    
    $sug['height'] = !empty($sug['height']) ? $sug['height'] : 'Not specified';
    $sug['education'] = !empty($sug['education']) ? $sug['education'] : 'Not specified';
    $sug['drinking'] = !empty($sug['drinking']) ? $sug['drinking'] : 'Not specified';
    $sug['pets'] = !empty($sug['pets']) ? $sug['pets'] : 'Not specified';

    $stmt_sug_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? LIMIT 2");
    $stmt_sug_int->bind_param("i", $sug['user_id']);
    $stmt_sug_int->execute();
    $sug_vibe_res = $stmt_sug_int->get_result();
    $sug_vibes = [];
    while($v = $sug_vibe_res->fetch_assoc()){ $sug_vibes[] = $v['name']; }
    $sug['vibe_title'] = count($sug_vibes) > 0 ? implode(" & ", $sug_vibes) : "Mysterious Vibe";
    
    $cards_data[] = $sug;
}

// LẤY DANH SÁCH MATCH ĐỂ IN VÀO CỘT CONVERSATIONS (ĐÃ CHẶN BLIND DATE CHƯA REVEAL)
$stmt_matches = $conn->prepare("
    SELECT p.*, m.created_at as match_date 
    FROM matches m 
    JOIN profiles p ON (p.user_id = m.user1_id OR p.user_id = m.user2_id) 
    WHERE (m.user1_id = ? OR m.user2_id = ?) 
      AND p.user_id != ?
      AND (m.is_blind = 0 OR m.is_revealed = 1)
    ORDER BY m.created_at DESC
");
$stmt_matches->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_matches->execute();
$matches_result = $stmt_matches->get_result();
$messages = [];
while($row = $matches_result->fetch_assoc()){
    $messages[] = $row;
}

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
        <!-- CỘT TRÁI -->
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
                    <?php else: ?>
                        <!-- IN RA NHỮNG NGƯỜI ĐÃ MATCH -->
                        <?php foreach($messages as $msg): ?>
                            <div class="chat-item" onclick="window.location.href='messages.php?mode=standard&chat_with=<?= $msg['user_id'] ?>'">
                                <img src="../uploads/<?= htmlspecialchars($msg['avatar']) ?>" class="chat-avatar" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random'">
                                <div class="chat-info">
                                    <div class="chat-top">
                                        <strong><?= htmlspecialchars($msg['nickname'] ?? $msg['full_name']) ?></strong>
                                        <span class="time" style="color:var(--y2k-pink); font-weight:bold;">New</span>
                                    </div>
                                    <div class="preview-text">It's a match! Say hi 👋</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

        <!-- MAIN FEED: STACK THẺ QUẸT CĂN GIỮA -->
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
                    <button class="btn-ai-bot" onclick="openHomeIcebreakerModal()"><i class="fa-solid fa-robot"></i></button>
                </div>
            </div>
        </main>
    </div>

    <!-- Div ẩn chứa Toast Match! -->
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

            // GỌI API ĐỂ MATCH
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
                    
                    // Reload lại trang sau 2 giây để người đó nhảy vào mục trò chuyện
                    setTimeout(() => { 
                        toast.classList.remove('show'); 
                        window.location.reload(); 
                    }, 2000);
                }
            });

            setTimeout(() => { card.style.display = 'none'; }, 500); 
        }

        function saveFavorite(btnElement) {
            btnElement.classList.toggle('saved');
            const toast = document.getElementById('toast');
            if (!toast) return;
            toast.style.background = 'rgba(0, 0, 0, 0.85)';
            if (btnElement.classList.contains('saved')) {
                toast.innerHTML = '<i class="fa-solid fa-bookmark" style="color:#ff4b82;"></i> Saved to your list!';
            } else {
                toast.innerHTML = '<i class="fa-regular fa-bookmark"></i> Removed from list';
            }
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 2500);
        }

        document.addEventListener('keydown', function(event) {
            if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

            const visibleCards = Array.from(document.querySelectorAll('.swipeable-card')).filter(card => card.style.display !== 'none');
            
            let topCard = null;
            if (visibleCards.length > 0) {
                topCard = visibleCards.reduce((prev, current) => {
                    return (parseInt(prev.style.zIndex) || 0) > (parseInt(current.style.zIndex) || 0) ? prev : current;
                });
            } else {
                topCard = document.querySelector('.expanded-card, .main-card');
            }

            if (!topCard) return;

            const direction = event.key === 'ArrowLeft' ? -1 : 1;
            changePhotoAction(topCard, direction);
        });

        // ==========================================
        // ICEBREAKER AI (DIFY.AI INTEGRATION)
        // ==========================================
        function openHomeIcebreakerModal() {
            document.getElementById('homeIcebreakerOverlay').classList.add('open');
            fetchHomeIcebreakers();
        }

        function closeHomeIcebreakerModal() {
            document.getElementById('homeIcebreakerOverlay').classList.remove('open');
        }

        function fetchHomeIcebreakers() {
            const container = document.getElementById('homeIcebreakerSuggestions');
            container.innerHTML = '<div class="icebreaker-loading"><div class="spinner"></div><span>AI is crafting the perfect opener...</span></div>';

            // Get the currently visible card's name if possible
            const visibleCards = Array.from(document.querySelectorAll('.swipeable-card')).filter(c => c.style.display !== 'none');
            let targetName = 'someone new';
            let targetId = 0;

            if (visibleCards.length > 0) {
                const topCard = visibleCards[visibleCards.length - 1];
                const nameEl = topCard.querySelector('.info-name');
                if (nameEl) targetName = nameEl.textContent.split(',')[0].trim();
                targetId = parseInt(topCard.id.replace('card-', '')) || 0;
            }

            fetch('../api/dify_icebreaker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    partner_id: targetId,
                    partner_name: targetName
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success' && data.suggestions) {
                    renderHomeSuggestions(data.suggestions);
                } else {
                    container.innerHTML = '<div class="icebreaker-error"><i class="fa-solid fa-exclamation-circle"></i> ' + (data.message || 'Could not fetch suggestions') + '</div>';
                }
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<div class="icebreaker-error"><i class="fa-solid fa-exclamation-circle"></i> Connection error. Try again.</div>';
            });
        }

        function renderHomeSuggestions(suggestions) {
            const container = document.getElementById('homeIcebreakerSuggestions');
            const icons = ['fa-wand-magic-sparkles', 'fa-heart', 'fa-bolt'];
            container.innerHTML = suggestions.map((s, i) =>
                `<div class="icebreaker-suggestion" onclick="copyHomeSuggestion(this)">
                    <div class="suggestion-icon"><i class="fa-solid ${icons[i % 3]}"></i></div>
                    <span>${s}</span>
                </div>`
            ).join('');
        }

        function copyHomeSuggestion(el) {
            const text = el.querySelector('span').textContent;
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.innerHTML = '<i class="fa-solid fa-copy" style="color:#a855f7;"></i> Copied to clipboard! Use it in chat';
                toast.style.background = 'rgba(0,0,0,0.9)';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 2500);
                closeHomeIcebreakerModal();
            });
        }
    </script>

    <!-- ── ICEBREAKER MODAL (HOME) ── -->
    <style>
        .icebreaker-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 10000;
            justify-content: center; align-items: center;
            backdrop-filter: blur(6px);
        }
        .icebreaker-overlay.open { display: flex; }
        .icebreaker-modal {
            background: #fff; border-radius: 28px; padding: 36px;
            max-width: 440px; width: 92%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.25);
            animation: icebreakerIn 0.35s cubic-bezier(0.175,0.885,0.32,1.275);
            text-align: center;
        }
        @keyframes icebreakerIn {
            from { opacity:0; transform: scale(0.85) translateY(30px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .icebreaker-modal .modal-icon {
            font-size: 3rem; margin-bottom: 12px;
            background: linear-gradient(135deg, #ff4d8d, #a855f7);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .icebreaker-modal h3 {
            font-family: 'Public Sans', sans-serif; font-size: 20px;
            font-weight: 800; color: #1a1a2e; margin: 0 0 6px;
        }
        .icebreaker-modal .modal-sub {
            font-family: 'Public Sans', sans-serif; font-size: 13px;
            color: #888; margin: 0 0 24px;
        }
        .icebreaker-suggestions {
            display: flex; flex-direction: column; gap: 10px;
            margin-bottom: 20px; min-height: 120px; justify-content: center;
        }
        .icebreaker-suggestion {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px; background: #f8f5ff;
            border: 2px solid #ede8f5; border-radius: 16px;
            cursor: pointer; text-align: left;
            font-family: 'Public Sans', sans-serif; font-size: 14px; color: #333;
            transition: all 0.2s;
        }
        .icebreaker-suggestion:hover {
            border-color: #a855f7; background: #f3eeff;
            transform: translateX(4px);
        }
        .icebreaker-suggestion .suggestion-icon {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #ff4d8d, #a855f7);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; color: #fff; font-size: 14px;
        }
        .icebreaker-loading {
            display: flex; flex-direction: column; align-items: center;
            gap: 12px; padding: 30px 0;
        }
        .icebreaker-loading .spinner {
            width: 40px; height: 40px;
            border: 4px solid #ede8f5; border-top-color: #a855f7;
            border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .icebreaker-loading span {
            font-family: 'Public Sans', sans-serif; font-size: 13px; color: #888;
        }
        .icebreaker-actions { display: flex; gap: 10px; }
        .btn-icebreaker-refresh {
            flex: 1; padding: 12px;
            background: linear-gradient(135deg, #a855f7, #ff4d8d);
            color: #fff; border: none; border-radius: 14px;
            font-family: 'Public Sans', sans-serif; font-size: 14px;
            font-weight: 700; cursor: pointer; transition: opacity 0.2s;
        }
        .btn-icebreaker-refresh:hover { opacity: 0.9; }
        .btn-icebreaker-close {
            flex: 1; padding: 12px; background: #f5f5f5; color: #555;
            border: none; border-radius: 14px;
            font-family: 'Public Sans', sans-serif; font-size: 14px;
            font-weight: 600; cursor: pointer;
        }
        .icebreaker-error {
            color: #e74c3c; font-size: 13px;
            font-family: 'Public Sans', sans-serif; padding: 20px 0;
        }
    </style>

    <div class="icebreaker-overlay" id="homeIcebreakerOverlay" onclick="if(event.target===this) closeHomeIcebreakerModal()">
        <div class="icebreaker-modal">
            <div class="modal-icon"><i class="fa-solid fa-robot"></i></div>
            <h3>AI Icebreaker ✨</h3>
            <p class="modal-sub">Powered by Dify.ai — tap a suggestion to copy it!</p>

            <div class="icebreaker-suggestions" id="homeIcebreakerSuggestions">
                <!-- Filled by JS -->
            </div>

            <div class="icebreaker-actions">
                <button class="btn-icebreaker-refresh" onclick="fetchHomeIcebreakers()">
                    <i class="fa-solid fa-arrows-rotate"></i> New Suggestions
                </button>
                <button class="btn-icebreaker-close" onclick="closeHomeIcebreakerModal()">Close</button>
            </div>
        </div>
    </div>

</body>
</html>