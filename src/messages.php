<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'standard';

$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$is_waiting_blind = $current_user['is_waiting_blind']; // Xem có đang xếp hàng không

// FETCH TOÀN BỘ MATCHES
$stmt_matches = $conn->prepare("
    SELECT p.*, m.created_at as match_date, m.streak_count, m.last_interact_date, m.is_blind, m.is_revealed 
    FROM matches m 
    JOIN profiles p ON (p.user_id = m.user1_id OR p.user_id = m.user2_id) 
    WHERE (m.user1_id = ? OR m.user2_id = ?) AND p.user_id != ?
    ORDER BY m.created_at DESC
");
$stmt_matches->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_matches->execute();
$matches_result = $stmt_matches->get_result();

$standard_matches = [];
$blind_matches = [];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$blind_counter = 1; 

while($row = $matches_result->fetch_assoc()){
    $row['display_name'] = !empty($row['nickname']) ? $row['nickname'] : $row['full_name'];
    $row['age'] = date_diff(date_create($row['dob']), date_create('today'))->y;
    
    if (empty($row['last_interact_date'])) {
        $row['display_streak'] = $row['streak_count'];
    } elseif ($row['last_interact_date'] != $today && $row['last_interact_date'] != $yesterday) {
        $row['display_streak'] = 0;
    } else {
        $row['display_streak'] = $row['streak_count'];
    }
    
    // BẢO MẬT TUYỆT ĐỐI: Phân loại rạch ròi 2 bên
    if ($row['is_blind'] == 1 && $row['is_revealed'] == 0) {
        $row['blind_name'] = "Mystery Soul #" . $blind_counter++;
        $blind_matches[] = $row;
    } else {
        $standard_matches[] = $row;
    }
}

$active_matches = ($mode === 'blind') ? $blind_matches : $standard_matches;

$chat_with_id = isset($_GET['chat_with']) ? intval($_GET['chat_with']) : 0;
$chat_partner = null;
$chat_history = [];
$connection_percent = 0;

if ($chat_with_id > 0) {
    foreach ($active_matches as $m) {
        if ($m['user_id'] == $chat_with_id) {
            $chat_partner = $m;
            break;
        }
    }
    if ($chat_partner) {
        $stmt_msg = $conn->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ");
        $stmt_msg->bind_param("iiii", $user_id, $chat_with_id, $chat_with_id, $user_id);
        $stmt_msg->execute();
        $history_res = $stmt_msg->get_result();
        while($msg = $history_res->fetch_assoc()) {
            $chat_history[] = $msg;
        }

        if ($mode === 'blind') {
            $connection_percent = min(100, ($chat_partner['display_streak'] / 5) * 100);
        }
    }
}

// Lookup for old date spot message strings to render as tags
$spot_lookup = [
    'Lighthouse Sky Bar' => [
        'name' => 'Lighthouse Sky Bar',
        'image' => '../image/lighthouseskybar.jpg',
        'likes' => '96',
        'map_url' => 'https://maps.google.com/?q=Lighthouse+Sky+Bar+Hanoi'
    ],
    'Sky Walk Lotte' => [
        'name' => 'Sky Walk Lotte',
        'image' => '../image/lotteobservationdeck.jpg',
        'likes' => '92',
        'map_url' => 'https://maps.google.com/?q=Sky+Walk+Lotte+Hanoi'
    ],
    'Sky Walk Observation Deck (Lotte Lieu Giai)' => [
        'name' => 'Sky Walk Lotte',
        'image' => '../image/lotteobservationdeck.jpg',
        'likes' => '92',
        'map_url' => 'https://maps.google.com/?q=Sky+Walk+Lotte+Hanoi'
    ],
    'The Alchemist' => [
        'name' => 'The Alchemist',
        'image' => '../image/thealchemist.jpg',
        'likes' => '93',
        'map_url' => 'https://maps.google.com/?q=The+Alchemist+Bar+Hanoi'
    ],
    'Complex 01' => [
        'name' => 'Complex 01',
        'image' => '../image/complex01.jpg',
        'likes' => '89',
        'map_url' => 'https://maps.google.com/?q=Complex+01+Hanoi'
    ],
    'Complex 01 (Tay Son)' => [
        'name' => 'Complex 01',
        'image' => '../image/complex01.jpg',
        'likes' => '89',
        'map_url' => 'https://maps.google.com/?q=Complex+01+Hanoi'
    ],
    'West Lake' => [
        'name' => 'West Lake',
        'image' => '../image/date_spot_1.png',
        'likes' => '96',
        'map_url' => 'https://maps.google.com/?q=West+Lake+Hanoi'
    ],
    'West Lake (Trich Sai / Ve Ho area)' => [
        'name' => 'West Lake',
        'image' => '../image/date_spot_1.png',
        'likes' => '96',
        'map_url' => 'https://maps.google.com/?q=West+Lake+Hanoi'
    ],
    'Hoan Kiem Walking Street' => [
        'name' => 'Hoan Kiem Walking Street',
        'image' => '../image/date_spot_2.png',
        'likes' => '94',
        'map_url' => 'https://maps.google.com/?q=Hoan+Kiem+Walking+Street+Hanoi'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SoulSync - Messages</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <style>
        /* Thêm animation cho nút xoay vòng lúc chờ ghép đôi */
        @keyframes spinPulse { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .btn-waiting { background: #3d2f50 !important; color: #ffb3d1 !important; }
        .btn-waiting i { animation: spinPulse 1.5s linear infinite; }

        /* ── ICEBREAKER BUTTON ── */
        .btn-icebreaker {
            width: 42px; height: 42px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #ff4d8d, #a855f7);
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 15px rgba(168,85,247,0.35);
        }
        .btn-icebreaker:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(168,85,247,0.5); }
        .btn-icebreaker.loading i { animation: spinPulse 1s linear infinite; }

        /* ── ICEBREAKER MODAL ── */
        .icebreaker-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(6px);
        }
        .icebreaker-overlay.open { display: flex; }
        .icebreaker-modal {
            background: #fff;
            border-radius: 28px;
            padding: 36px;
            max-width: 440px;
            width: 92%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.25);
            animation: icebreakerIn 0.35s cubic-bezier(0.175,0.885,0.32,1.275);
            text-align: center;
        }
        @keyframes icebreakerIn {
            from { opacity:0; transform: scale(0.85) translateY(30px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .icebreaker-modal .modal-icon {
            font-size: 3rem;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #ff4d8d, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .icebreaker-modal h3 {
            font-family: 'Public Sans', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #1a1a2e;
            margin: 0 0 6px;
        }
        .icebreaker-modal .modal-sub {
            font-family: 'Public Sans', sans-serif;
            font-size: 13px;
            color: #888;
            margin: 0 0 24px;
        }

        .icebreaker-suggestions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            min-height: 120px;
            justify-content: center;
        }
        .icebreaker-suggestion {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #f8f5ff;
            border: 2px solid #ede8f5;
            border-radius: 16px;
            cursor: pointer;
            text-align: left;
            font-family: 'Public Sans', sans-serif;
            font-size: 14px;
            color: #333;
            transition: all 0.2s;
        }
        .icebreaker-suggestion:hover {
            border-color: #a855f7;
            background: #f3eeff;
            transform: translateX(4px);
        }
        .icebreaker-suggestion .suggestion-icon {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff4d8d, #a855f7);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            color: #fff;
            font-size: 14px;
        }

        .icebreaker-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 30px 0;
        }
        .icebreaker-loading .spinner {
            width: 40px; height: 40px;
            border: 4px solid #ede8f5;
            border-top-color: #a855f7;
            border-radius: 50%;
            animation: spinPulse 0.8s linear infinite;
        }
        .icebreaker-loading span {
            font-family: 'Public Sans', sans-serif;
            font-size: 13px;
            color: #888;
        }

        .icebreaker-actions {
            display: flex;
            gap: 10px;
        }
        .btn-icebreaker-refresh {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #a855f7, #ff4d8d);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-family: 'Public Sans', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-icebreaker-refresh:hover { opacity: 0.9; }
        .btn-icebreaker-close {
            flex: 1;
            padding: 12px;
            background: #f5f5f5;
            color: #555;
            border: none;
            border-radius: 14px;
            font-family: 'Public Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .icebreaker-error {
            color: #e74c3c;
            font-size: 13px;
            font-family: 'Public Sans', sans-serif;
            padding: 20px 0;
        }
    </style>
</head>
<body class="dashboard-body" style="overflow: hidden; background: <?= $mode === 'blind' ? '#1f182b' : '#fbfbfb' ?>;">

    <?php include 'header.php'; ?>

    <div class="messenger-wrapper <?= $mode === 'blind' ? 'blind-wrapper' : '' ?>">
        
        <aside class="msg-sidebar <?= $mode === 'blind' ? 'blind-sidebar' : '' ?>">
            <div class="mode-toggle">
                <button class="<?= $mode === 'standard' ? 'active' : '' ?>" onclick="window.location.href='messages.php?mode=standard'">Standard Mode</button>
                <button class="<?= $mode === 'blind' ? 'active' : '' ?>" onclick="window.location.href='messages.php?mode=blind'">Blind Mode</button>
            </div>

            <?php if($mode === 'standard'): ?>
                <div class="matches-section">
                    <h3>Matches</h3>
                    <div class="matches-scroll">
                        <?php if(empty($standard_matches)): ?>
                            <p style="font-size:0.8rem; color:#999;">No matches yet.</p>
                        <?php else: ?>
                            <?php foreach($standard_matches as $m): ?>
                                <a href="messages.php?mode=standard&chat_with=<?= $m['user_id'] ?>" class="match-avatar-col">
                                    <div class="match-avatar-wrap">
                                        <img src="../uploads/<?= htmlspecialchars($m['avatar'] ?: 'default.jpg') ?>" onerror="this.src='https://ui-avatars.com/api/?name=User'">
                                        <div class="online-dot"></div>
                                    </div>
                                    <span><?= htmlspecialchars(explode(' ', $m['display_name'])[0]) ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 20px;">
                    <div style="background:rgba(255,255,255,0.05); border-radius:15px; padding:15px; margin-bottom:15px; display:flex; align-items:center; gap:10px; color:#fff; border: 1px solid rgba(255,75,130,0.3);">
                        <i class="fa-solid fa-mask" style="color:var(--y2k-hot-pink);"></i> <strong>Blind Chats</strong>
                    </div>
                    <div style="padding:15px; display:flex; align-items:center; gap:10px; color:#888; cursor:pointer;" onclick="window.location.href='messages.php?mode=standard'">
                        <i class="fa-solid fa-eye"></i> Revealed
                    </div>
                </div>
            <?php endif; ?>

            <div class="msg-list-section">
                <h3>Messages</h3>
                <div class="msg-search <?= $mode === 'blind' ? 'blind-search' : '' ?>">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search messages">
                </div>

                <div class="chat-threads">
                    <?php foreach($active_matches as $m): ?>
                        <a href="messages.php?mode=<?= $mode ?>&chat_with=<?= $m['user_id'] ?>" class="chat-thread <?= ($chat_with_id == $m['user_id']) ? 'active' : '' ?>">
                            <?php if($mode === 'blind'): ?>
                                <div class="thread-avatar blind-avatar-placeholder" style="background:var(--y2k-hot-pink); border:none;"></div>
                            <?php else: ?>
                                <img src="../uploads/<?= htmlspecialchars($m['avatar'] ?: 'default.jpg') ?>" class="thread-avatar" onerror="this.src='https://ui-avatars.com/api/?name=User'">
                            <?php endif; ?>
                            
                            <div class="thread-info">
                                <div class="thread-top">
                                    <strong>
                                        <?= $mode === 'blind' ? $m['blind_name'] : htmlspecialchars($m['display_name']) ?> 
                                        <?php if($m['display_streak'] >= 3): ?>
                                            <span style="color:#ff4b82; font-size:0.85rem; font-weight:800; margin-left:5px;"><i class="fa-solid fa-fire"></i> <?= $m['display_streak'] ?></span>
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div class="thread-preview">Tap to view conversation...</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if($mode === 'blind'): ?>
                    <?php if($is_waiting_blind): ?>
                        <button id="btnBlindDate" class="btn-new-blind btn-waiting" onclick="cancelBlindDate()">
                            <i class="fa-solid fa-spinner"></i> Scanning souls... (Cancel)
                        </button>
                    <?php else: ?>
                        <button id="btnBlindDate" class="btn-new-blind" onclick="startNewBlindDate()">
                            <i class="fa-solid fa-circle-plus"></i> New Blind Date
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </aside>

        <?php if ($chat_partner): ?>
            <?php if($mode === 'blind'): ?>
            <main class="blind-main">
                <div class="blind-chat-area">
                    <div class="blind-chat-header">
                        <div class="blind-avatar-placeholder"></div>
                        <div class="chat-header-text" style="margin-left: 15px;">
                            <h2 style="color:#fff; font-size:1.2rem; margin-bottom:0; display:flex; align-items:center; gap:10px;">
                                <?= $chat_partner['blind_name'] ?>
                                <span style="background:#fff; color:var(--y2k-hot-pink); font-size:0.6rem; padding:3px 8px; border-radius:50px;">SOUL SYNC</span>
                            </h2>
                            <p style="color:rgba(255,255,255,0.7); font-size:0.75rem;"><span style="background:#ffb3d1; color:#a82253; padding:2px 6px; border-radius:5px; font-weight:bold; margin-right:5px;">PLAYFUL</span> Typing...</p>
                        </div>
                        <div class="connection-bar-wrap">
                            <span class="conn-text">CONNECTION</span>
                            <div class="conn-bar-bg"><div class="conn-bar-fill" style="width: <?= $connection_percent ?>%;"></div></div>
                            <span class="conn-text"><?= $connection_percent ?>%</span>
                        </div>
                    </div>
                    <div class="chat-history" id="chatHistory">
                        <div class="chat-date-divider"><span style="background:#16111f; color:#888; border-color:rgba(255,255,255,0.1);">TODAY</span></div>
                        <?php foreach($chat_history as $msg): $is_me = ($msg['sender_id'] == $user_id); 
                            $raw_msg = $msg['message_text'];
                            $is_ds_tag = (strpos($raw_msg, 'DATE_SPOT_TAG:') === 0);
                            $ds_data = null;
                            if ($is_ds_tag) {
                                $ds_data = json_decode(substr($raw_msg, 14), true);
                            } else {
                                $old_prefix = "I found a great date spot! Let's go to ";
                                if (strpos($raw_msg, $old_prefix) === 0) {
                                    $extracted = trim(str_replace($old_prefix, "", $raw_msg));
                                    $extracted = trim(str_replace("💌", "", $extracted));
                                    $found_spot = null;
                                    foreach ($spot_lookup as $key => $val) {
                                        if (stripos($extracted, $key) !== false || stripos($key, $extracted) !== false) {
                                            $found_spot = $val;
                                            break;
                                        }
                                    }
                                    if ($found_spot) {
                                        $is_ds_tag = true;
                                        $ds_data = $found_spot;
                                    }
                                }
                            }
                        ?>
                            <div class="msg-row <?= $is_me ? 'me' : 'them' ?>">
                                <?php if(!$is_me): ?><div class="blind-avatar-placeholder" style="width:35px; height:35px; font-size:0.8rem;"></div><?php endif; ?>
                                <div class="msg-bubble-wrap">
                                    <div class="msg-bubble" <?php if($is_ds_tag) echo 'style="padding:0; background:transparent; box-shadow:none; border:none;"'; ?>>
                                        <?php if($is_ds_tag && $ds_data): ?>
                                            <div style="background:#fff; border-radius:16px; overflow:hidden; border:1px solid #ffe6f0; width:220px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
                                                <img src="<?= htmlspecialchars($ds_data['image']) ?>" style="width:100%; height:120px; object-fit:cover;">
                                                <div style="padding:12px;">
                                                    <div style="font-family:'Public Sans', sans-serif; font-weight:800; font-size:15px; color:#1a1a2e; margin-bottom:4px;"><?= htmlspecialchars($ds_data['name']) ?></div>
                                                    <div style="color:#ff4d8d; font-size:13px; font-weight:700; margin-bottom:12px;">❤️ <?= htmlspecialchars($ds_data['likes']) ?> Likes</div>
                                                    <a href="<?= htmlspecialchars($ds_data['map_url']) ?>" target="_blank" style="display:block; text-align:center; background:linear-gradient(135deg, #ff4d8d, #ff7eb3); color:#fff; padding:8px; border-radius:8px; font-size:13px; font-weight:bold; text-decoration:none;">View on Map</a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <?= htmlspecialchars($raw_msg) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="msg-meta"><?= date("h:i A", strtotime($msg['created_at'])) ?> <?php if($is_me): ?><i class="fa-solid fa-check-double"></i><?php endif; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chat-input-wrapper">
                        <div class="ai-prompts">
                            <span onclick="insertPrompt('Ask about hobbies')"><i class="fa-solid fa-wand-magic-sparkles"></i> Ask about hobbies</span>
                        </div>
                        <div class="chat-input-box">
                            <input type="text" id="msgInput" placeholder="Whisper your soul's truth..." onkeypress="handleKeyPress(event)">
                            <button class="btn-send" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>
                <div class="blind-right-bar">
                    <p class="sparks-title">MATCH IDENTITY</p>
                    <div class="identity-card">
                        <i class="fa-solid fa-lock"></i><h4>Identity Encrypted</h4>
                        <p>The face behind the mask will be revealed at 100% connection level.</p>
                        <?php if($connection_percent < 100): ?>
                            <button class="btn-unlock-info"><?= 100 - $connection_percent ?>% more to Unlock</button>
                        <?php else: ?>
                            <button class="btn-unlock-info" style="background:var(--y2k-gradient); color:#fff; border:none;" onclick="showRevealModal()">READY TO REVEAL</button>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            <?php else: ?>
            <main class="msg-main">
                <div class="chat-header">
                    <div class="chat-header-info">
                        <img src="../uploads/<?= htmlspecialchars($chat_partner['avatar'] ?: 'default.jpg') ?>" onerror="this.src='https://ui-avatars.com/api/?name=User'">
                        <div class="chat-header-text">
                            <h2 style="display: flex; align-items: center; gap: 8px;">
                                <?= htmlspecialchars($chat_partner['display_name']) ?>, <?= $chat_partner['age'] ?> 
                                <?php if($chat_partner['display_streak'] >= 3): ?>
                                    <span style="color:#ff4b82; font-size:1rem; font-weight:800;"><i class="fa-solid fa-fire"></i> <?= $chat_partner['display_streak'] ?></span>
                                <?php endif; ?>
                            </h2>
                            <p><i class="fa-solid fa-circle" style="font-size:0.5rem; margin-right:5px;"></i> Active now</p>
                        </div>
                    </div>
                    <div class="chat-header-actions"><i class="fa-solid fa-video"></i><i class="fa-solid fa-phone"></i></div>
                </div>
                <div class="chat-history" id="chatHistory">
                    <?php foreach($chat_history as $msg): $is_me = ($msg['sender_id'] == $user_id); 
                        $raw_msg = $msg['message_text'];
                        $is_ds_tag = (strpos($raw_msg, 'DATE_SPOT_TAG:') === 0);
                        $ds_data = null;
                        if ($is_ds_tag) {
                            $ds_data = json_decode(substr($raw_msg, 14), true);
                        } else {
                            $old_prefix = "I found a great date spot! Let's go to ";
                            if (strpos($raw_msg, $old_prefix) === 0) {
                                $extracted = trim(str_replace($old_prefix, "", $raw_msg));
                                $extracted = trim(str_replace("💌", "", $extracted));
                                $found_spot = null;
                                foreach ($spot_lookup as $key => $val) {
                                    if (stripos($extracted, $key) !== false || stripos($key, $extracted) !== false) {
                                        $found_spot = $val;
                                        break;
                                    }
                                }
                                if ($found_spot) {
                                    $is_ds_tag = true;
                                    $ds_data = $found_spot;
                                }
                            }
                        }
                    ?>
                        <div class="msg-row <?= $is_me ? 'me' : 'them' ?>">
                            <?php if(!$is_me): ?><img src="../uploads/<?= htmlspecialchars($chat_partner['avatar'] ?: 'default.jpg') ?>" onerror="this.src='https://ui-avatars.com/api/?name=U'"><?php endif; ?>
                            <div class="msg-bubble-wrap">
                                <div class="msg-bubble" <?php if($is_ds_tag) echo 'style="padding:0; background:transparent; box-shadow:none; border:none;"'; ?>>
                                    <?php if($is_ds_tag && $ds_data): ?>
                                        <div style="background:#fff; border-radius:16px; overflow:hidden; border:1px solid #ffe6f0; width:220px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
                                            <img src="<?= htmlspecialchars($ds_data['image']) ?>" style="width:100%; height:120px; object-fit:cover;">
                                            <div style="padding:12px;">
                                                <div style="font-family:'Public Sans', sans-serif; font-weight:800; font-size:15px; color:#1a1a2e; margin-bottom:4px;"><?= htmlspecialchars($ds_data['name']) ?></div>
                                                <div style="color:#ff4d8d; font-size:13px; font-weight:700; margin-bottom:12px;">❤️ <?= htmlspecialchars($ds_data['likes']) ?> Likes</div>
                                                <a href="<?= htmlspecialchars($ds_data['map_url']) ?>" target="_blank" style="display:block; text-align:center; background:linear-gradient(135deg, #ff4d8d, #ff7eb3); color:#fff; padding:8px; border-radius:8px; font-size:13px; font-weight:bold; text-decoration:none;">View on Map</a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?= htmlspecialchars($raw_msg) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="msg-meta"><?= date("h:i A", strtotime($msg['created_at'])) ?> <?php if($is_me): ?><i class="fa-solid fa-check-double"></i><?php endif; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chat-input-wrapper">
                    <div class="chat-input-box">
                        <button class="btn-icebreaker" id="btnIcebreaker" onclick="openIcebreakerModal()" title="Need an icebreaker?">
                            <i class="fa-solid fa-robot"></i>
                        </button>
                        <input type="text" id="msgInput" placeholder="Type a message..." onkeypress="handleKeyPress(event)">
                        <button class="btn-send" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </div>
            </main>

            <?php endif; ?>

        <?php else: ?>
            <main class="msg-main empty-chat" style="<?= $mode === 'blind' ? 'background:#251c33; color:#666;' : '' ?>">
                <i class="fa-regular fa-comments"></i>
                <h2>Your Messages</h2>
                <p>Select a match from the sidebar to start syncing souls.</p>
            </main>
        <?php endif; ?>

    </div>

    <div id="revealModal" class="reveal-modal-overlay">
        <div class="reveal-modal">
            <div class="reveal-badge"><i class="fa-solid fa-bolt"></i> CONNECTION PEAK REACHED</div>
            <h2>Amazing! Connection Level reached 100%.</h2>
            <p>The masks are ready to fall. Make your final decision.</p>
            <div class="reveal-actions">
                <button class="btn-reveal-yes" onclick="confirmReveal()"><i class="fa-solid fa-heart"></i> Reveal Identity</button>
                <button class="btn-reveal-no" onclick="closeRevealModal()"><i class="fa-solid fa-eye-slash"></i> Stay Anonymous</button>
            </div>
        </div>
    </div>

    <script>
        const chatHistory = document.getElementById('chatHistory');
        if (chatHistory) chatHistory.scrollTop = chatHistory.scrollHeight;

        function insertPrompt(text) { document.getElementById('msgInput').value = text; document.getElementById('msgInput').focus(); }
        function handleKeyPress(e) { if (e.key === 'Enter') sendMessage(); }

        function sendMessage() {
            const input = document.getElementById('msgInput');
            const text = input.value.trim();
            if (!text) return;
            const receiverId = <?= $chat_partner ? $chat_partner['user_id'] : 0 ?>;
            if (receiverId === 0) return;

            const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const msgHtml = `<div class="msg-row me"><div class="msg-bubble-wrap"><div class="msg-bubble">${text}</div><div class="msg-meta">${timeNow} <i class="fa-solid fa-check"></i></div></div></div>`;
            chatHistory.insertAdjacentHTML('beforeend', msgHtml);
            chatHistory.scrollTop = chatHistory.scrollHeight; 
            input.value = ''; 

            fetch('../api/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ receiver_id: receiverId, message_text: text })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const lastMsgMeta = chatHistory.lastElementChild.querySelector('.msg-meta i');
                    if(lastMsgMeta) { lastMsgMeta.className = 'fa-solid fa-check-double'; }
                    setTimeout(() => window.location.reload(), 1000);
                }
            });
        }

        // ==========================================
        // LOGIC TÌM KIẾM BLIND DATE THỰC TẾ
        // ==========================================
        let pollInterval;

        function startNewBlindDate() {
            const btn = document.getElementById('btnBlindDate');
            // Đổi giao diện nút thành Loading
            btn.innerHTML = '<i class="fa-solid fa-spinner"></i> Scanning souls... (Cancel)';
            btn.className = 'btn-new-blind btn-waiting';
            btn.onclick = cancelBlindDate;

            fetch('../api/blind_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'new_blind_date' })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'matched') {
                    // Nếu may mắn có người đang đợi sẵn -> Bốc luôn!
                    window.location.href = 'messages.php?mode=blind&chat_with=' + data.target_id;
                } else if(data.status === 'waiting') {
                    // Nếu chưa có ai -> Bắt đầu vòng lặp hỏi Server liên tục mỗi 3 giây
                    pollInterval = setInterval(checkIfMatched, 3000);
                }
            });
        }

        // Hỏi server xem mình đã được ghép chưa
        function checkIfMatched() {
            fetch('../api/blind_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'check_waiting' })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'matched') {
                    // Có người khác vừa bấm tìm và ghép trúng mình -> Reload trang để hiện thẻ chat!
                    clearInterval(pollInterval);
                    window.location.reload();
                }
            });
        }

        function cancelBlindDate() {
            clearInterval(pollInterval);
            fetch('../api/blind_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancel_waiting' })
            })
            .then(() => {
                const btn = document.getElementById('btnBlindDate');
                btn.innerHTML = '<i class="fa-solid fa-circle-plus"></i> New Blind Date';
                btn.className = 'btn-new-blind';
                btn.onclick = startNewBlindDate;
            });
        }

        // XỬ LÝ LỘT MẶT NẠ
        function showRevealModal() { document.getElementById('revealModal').classList.add('active'); }
        function closeRevealModal() { document.getElementById('revealModal').classList.remove('active'); }
        function confirmReveal() {
            const partnerId = <?= $chat_partner ? $chat_partner['user_id'] : 0 ?>;
            fetch('../api/blind_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reveal', target_id: partnerId })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    window.location.href = 'messages.php?mode=standard&chat_with=' + partnerId;
                }
            });
        }
        <?php if($mode === 'blind' && $connection_percent >= 100): ?>
            setTimeout(showRevealModal, 1000);
        <?php endif; ?>

        // ==========================================
        // ICEBREAKER AI (DIFY.AI INTEGRATION)
        // ==========================================
        function openIcebreakerModal() {
            document.getElementById('icebreakerOverlay').classList.add('open');
            fetchIcebreakers();
        }

        function closeIcebreakerModal() {
            document.getElementById('icebreakerOverlay').classList.remove('open');
        }

        function fetchIcebreakers() {
            const container = document.getElementById('icebreakerSuggestions');
            container.innerHTML = '<div class="icebreaker-loading"><div class="spinner"></div><span>AI is thinking of something clever...</span></div>';

            const partnerId = <?= $chat_partner ? $chat_partner['user_id'] : 0 ?>;
            const partnerName = "<?= $chat_partner ? addslashes(htmlspecialchars($chat_partner['display_name'] ?? '')) : 'your match' ?>";

            fetch('../api/dify_icebreaker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    partner_id: partnerId,
                    partner_name: partnerName
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success' && data.suggestions) {
                    renderSuggestions(data.suggestions);
                } else {
                    container.innerHTML = '<div class="icebreaker-error"><i class="fa-solid fa-exclamation-circle"></i> ' + (data.message || 'Could not fetch suggestions') + '</div>';
                }
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<div class="icebreaker-error"><i class="fa-solid fa-exclamation-circle"></i> Connection error. Please try again.</div>';
            });
        }

        function renderSuggestions(suggestions) {
            const container = document.getElementById('icebreakerSuggestions');
            const icons = ['fa-wand-magic-sparkles', 'fa-heart', 'fa-bolt'];
            container.innerHTML = suggestions.map((s, i) =>
                `<div class="icebreaker-suggestion" onclick="useSuggestion(this)">
                    <div class="suggestion-icon"><i class="fa-solid ${icons[i % 3]}"></i></div>
                    <span>${s}</span>
                </div>`
            ).join('');
        }

        function useSuggestion(el) {
            const text = el.querySelector('span').textContent;
            document.getElementById('msgInput').value = text;
            document.getElementById('msgInput').focus();
            closeIcebreakerModal();
        }
    </script>

    <!-- ── ICEBREAKER MODAL ── -->
    <div class="icebreaker-overlay" id="icebreakerOverlay" onclick="if(event.target===this) closeIcebreakerModal()">
        <div class="icebreaker-modal">
            <div class="modal-icon"><i class="fa-solid fa-robot"></i></div>
            <h3>AI Icebreaker ✨</h3>
            <p class="modal-sub">Powered by Dify.ai — pick a suggestion to send!</p>

            <div class="icebreaker-suggestions" id="icebreakerSuggestions">
                <!-- Filled by JS -->
            </div>

            <div class="icebreaker-actions">
                <button class="btn-icebreaker-refresh" onclick="fetchIcebreakers()">
                    <i class="fa-solid fa-arrows-rotate"></i> New Suggestions
                </button>
                <button class="btn-icebreaker-close" onclick="closeIcebreakerModal()">Close</button>
            </div>
        </div>
    </div>

</body>
</html>