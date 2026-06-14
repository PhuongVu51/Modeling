<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'standard';

$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$is_waiting_blind = $current_user['is_waiting_blind'];

// 1. LẤY TẤT CẢ PHÒNG DOUBLE DATE MÀ USER THAM GIA
$stmt_dd = $conn->prepare("
    SELECT d.id, d.status,
           (SELECT COUNT(*) FROM double_date_members ddm JOIN profiles p ON ddm.user_id = p.user_id WHERE ddm.double_date_id = d.id AND ddm.status = 'pending') as pending_count
    FROM double_dates d
    JOIN double_date_members m ON d.id = m.double_date_id
    WHERE m.user_id = ?
    ORDER BY d.created_at DESC
");
$stmt_dd->bind_param("i", $user_id);
$stmt_dd->execute();
$dd_result = $stmt_dd->get_result();

$double_dates = [];
while($row = $dd_result->fetch_assoc()) {
    $double_dates[] = $row;
}

$is_group_active = false;
$current_dd_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_group_number = 0;
$total_groups = count($double_dates);

// THUẬT TOÁN ĐÁNH SỐ NHÓM THEO THỨ TỰ TẠO (Thay vì dùng ID Database)
foreach ($double_dates as $index => &$dd) {
    // Vì danh sách đang sắp xếp mới nhất lên đầu (DESC), nên nhóm đầu tiên sẽ có số lớn nhất
    $dd['display_number'] = $total_groups - $index;
    
    // Kiểm tra xem phòng ĐANG MỞ có active hay chưa
    if (strpos($mode, 'double_date') !== false && $dd['id'] == $current_dd_id) {
        $is_group_active = ($dd['pending_count'] == 0);
        $current_group_number = $dd['display_number'];
    }
}
unset($dd);

// 2. LẤY LỊCH SỬ TIN NHẮN NHÓM (NẾU ĐANG Ở TRONG NHÓM)
$group_chat_history = [];
if ($current_dd_id > 0 && strpos($mode, 'double_date') !== false) {
    $stmt_gmsg = $conn->prepare("
        SELECT gm.*, p.nickname, p.full_name, p.avatar 
        FROM group_messages gm 
        JOIN profiles p ON gm.sender_id = p.user_id 
        WHERE gm.double_date_id = ? 
        ORDER BY gm.created_at ASC
    ");
    $stmt_gmsg->bind_param("i", $current_dd_id);
    $stmt_gmsg->execute();
    $res_gmsg = $stmt_gmsg->get_result();
    while($row = $res_gmsg->fetch_assoc()) {
        $group_chat_history[] = $row;
    }
}

$chat_with_id = isset($_GET['chat_with']) ? intval($_GET['chat_with']) : 0;

// 3. ĐÁNH DẤU TIN NHẮN 1:1 LÀ ĐÃ ĐỌC TRƯỚC KHI LOAD
if ($chat_with_id > 0) {
    $stmt_update_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt_update_read->bind_param("ii", $chat_with_id, $user_id);
    $stmt_update_read->execute();
}

// 4. FETCH TOÀN BỘ MATCHES & LẤY SỐ TIN CHƯA ĐỌC
$stmt_matches = $conn->prepare("
    SELECT p.*, m.created_at as match_date, m.streak_count, m.last_interact_date, m.is_blind, m.is_revealed,
    (
        SELECT MAX(created_at) FROM messages 
        WHERE (sender_id = m.user1_id AND receiver_id = m.user2_id) 
           OR (sender_id = m.user2_id AND receiver_id = m.user1_id)
    ) as last_msg_time,
    (
        SELECT COUNT(*) FROM messages 
        WHERE receiver_id = ? AND sender_id = p.user_id AND is_read = 0
    ) as unread_count
    FROM matches m 
    JOIN profiles p ON (p.user_id = m.user1_id OR p.user_id = m.user2_id) 
    WHERE (m.user1_id = ? OR m.user2_id = ?) AND p.user_id != ?
    ORDER BY COALESCE(last_msg_time, m.created_at) DESC
");
$stmt_matches->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
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
    
    if (empty($row['last_interact_date'])) { $row['display_streak'] = $row['streak_count']; } 
    elseif ($row['last_interact_date'] != $today && $row['last_interact_date'] != $yesterday) { $row['display_streak'] = 0; } 
    else { $row['display_streak'] = $row['streak_count']; }
    
    if ($row['is_blind'] == 1 && $row['is_revealed'] == 0) {
        $row['blind_name'] = "Mystery Soul #" . $blind_counter++;
        $blind_matches[] = $row;
    } else {
        $standard_matches[] = $row;
    }
}

$active_matches = ($mode === 'blind') ? $blind_matches : $standard_matches;
$chat_partner = null;
$chat_history = [];
$connection_percent = 0;

if ($chat_with_id > 0) {
    foreach ($active_matches as $m) {
        if ($m['user_id'] == $chat_with_id) { $chat_partner = $m; break; }
    }
    if ($chat_partner) {
        $stmt_msg = $conn->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
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
                <button class="<?= $mode === 'standard' || strpos($mode, 'double_date') !== false ? 'active' : '' ?>" onclick="window.location.href='messages.php?mode=standard'">Standard Mode</button>
                <button class="<?= $mode === 'blind' ? 'active' : '' ?>" onclick="window.location.href='messages.php?mode=blind'">Blind Mode</button>
            </div>

            <?php if($mode === 'standard' || strpos($mode, 'double_date') !== false): ?>
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
                                    <span style="<?= $m['unread_count'] > 0 ? 'font-weight:900; color:#5d1029;' : '' ?>"><?= htmlspecialchars(explode(' ', $m['display_name'])[0]) ?></span>
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
                    
                    <!-- HIỂN THỊ TẤT CẢ NHÓM DOUBLE DATE MÀ USER THAM GIA ĐƯỢC ĐÁNH SỐ LẠI -->
                    <?php if($mode !== 'blind'): ?>
                        <?php foreach($double_dates as $dd): 
                            $dd_active = ($dd['pending_count'] == 0);
                        ?>
                            <a href="messages.php?mode=double_date_waiting&id=<?= $dd['id'] ?>" 
                               class="chat-thread <?= ($current_dd_id == $dd['id'] && strpos($mode, 'double_date') !== false) ? 'active' : '' ?>">
                                <div style="display:flex; margin-right:10px;">
                                    <img src="https://ui-avatars.com/api/?name=G1" style="width:30px; height:30px; border-radius:50%; border:2px solid #fff; z-index:2;">
                                    <img src="https://ui-avatars.com/api/?name=G2" style="width:30px; height:30px; border-radius:50%; border:2px solid #fff; margin-left:-15px; z-index:1;">
                                </div>
                                <div class="thread-info">
                                    <div class="thread-top"><strong>Double date group #<?= $dd['display_number'] ?></strong></div>
                                    <div class="thread-preview" style="color:<?= $dd_active ? '#4CAF50' : '#ff4b82' ?>;">
                                        <?= $dd_active ? 'Group active now' : 'Waiting for members...' ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php foreach($active_matches as $m): 
                        $is_unread = ($m['unread_count'] > 0);
                        $unread_class = $is_unread ? 'unread' : '';
                    ?>
                        <a href="messages.php?mode=<?= $mode ?>&chat_with=<?= $m['user_id'] ?>" 
                           class="chat-thread <?= $unread_class ?> <?= ($chat_with_id == $m['user_id'] && strpos($mode, 'double_date') === false) ? 'active' : '' ?>">
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
                                        <?php if($is_unread): ?><span class="unread-badge"></span><?php endif; ?>
                                    </strong>
                                </div>
                                <div class="thread-preview"><?= $is_unread ? 'Tap to view new message...' : 'Tap to view conversation...' ?></div>
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

        <!-- LUỒNG DOUBLE DATE GROUP CHAT -->
        <?php if ($current_dd_id > 0 && strpos($mode, 'double_date') !== false && $mode !== 'blind'): ?>
            <main class="msg-main" style="position: relative;">
                
                <div class="chat-header">
                    <div class="chat-header-info">
                        <div style="display:flex; margin-right:10px;">
                            <img src="https://ui-avatars.com/api/?name=G1" style="width:40px; height:40px; border-radius:50%; border:2px solid #fff; z-index:2;">
                            <img src="https://ui-avatars.com/api/?name=G2" style="width:40px; height:40px; border-radius:50%; border:2px solid #fff; margin-left:-20px; z-index:1;">
                        </div>
                        <div class="chat-header-text">
                            <h2 style="display: flex; align-items: center; gap: 8px;">Double date group #<?= $current_group_number ?></h2>
                            <p>
                                <i class="fa-solid fa-circle" style="font-size:0.5rem; margin-right:5px; color:<?= $is_group_active ? '#4CAF50' : '#ff4b82' ?>;"></i> 
                                <?= $is_group_active ? 'Group active now' : 'Waiting for members...' ?>
                            </p>
                        </div>
                    </div>
                    <div class="chat-header-actions"><i class="fa-solid fa-video"></i><i class="fa-solid fa-phone"></i><i class="fa-solid fa-circle-info"></i></div>
                </div>

                <!-- ================= DATE PLANNER PROPOSALS ================= -->
                <div style="background: #fdfdfd; border-bottom: 1px solid #f0f0f0; padding: 15px 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: #a0a0a0; letter-spacing: 1px; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-regular fa-calendar-check" style="color: #ff4b82; font-size: 1rem;"></i> DATE PLANNER PROPOSALS
                        </span>
                        <div style="color: #ccc; font-size: 0.9rem;">
                            <i class="fa-solid fa-chevron-left" style="margin-right: 15px; cursor: pointer; transition: 0.3s;"></i>
                            <i class="fa-solid fa-chevron-right" style="cursor: pointer; transition: 0.3s;"></i>
                        </div>
                    </div>
                    <div style="display: flex; gap: 20px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; width: 100%;">
                        
                        <div style="background: white; border-radius: 20px; padding: 15px; display: flex; align-items: center; gap: 15px; flex: 1; min-width: 250px; box-shadow: 0 5px 15px rgba(0,0,0,0.03);">
                            <img src="https://ui-avatars.com/api/?name=Nest&background=8b5a2b&color=fff" style="width: 60px; height: 60px; border-radius: 15px; object-fit: cover;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; font-size: 1rem; color: #333;">The Nest (Rooftop)</h4>
                                <p style="margin: 0; font-size: 0.8rem; color: #888;">Sunset Views & Cocktails</p>
                            </div>
                            <button onclick="voteLocation('The Nest (Rooftop)')" style="background: white; border: 1.5px solid #ffe4eb; color: #ff4b82; padding: 8px 18px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#ff4b82'; this.style.color='white'" onmouseout="this.style.background='white'; this.style.color='#ff4b82'">Vote</button>
                        </div>

                        <div style="background: white; border-radius: 20px; padding: 15px; display: flex; align-items: center; gap: 15px; flex: 1; min-width: 250px; box-shadow: 0 5px 15px rgba(0,0,0,0.03);">
                            <img src="https://ui-avatars.com/api/?name=C01&background=333&color=fff" style="width: 60px; height: 60px; border-radius: 15px; object-fit: cover;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; font-size: 1rem; color: #333;">Complex 01</h4>
                                <p style="margin: 0; font-size: 0.8rem; color: #888;">Artisan Coffee & Vibes</p>
                            </div>
                            <button onclick="voteLocation('Complex 01')" style="background: white; border: 1.5px solid #ffe4eb; color: #ff4b82; padding: 8px 18px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#ff4b82'; this.style.color='white'" onmouseout="this.style.background='white'; this.style.color='#ff4b82'">Vote</button>
                        </div>

                        <div style="background: white; border-radius: 20px; padding: 15px; display: flex; align-items: center; gap: 15px; flex: 1; min-width: 250px; box-shadow: 0 5px 15px rgba(0,0,0,0.03);">
                            <img src="https://ui-avatars.com/api/?name=Lotte&background=0088cc&color=fff" style="width: 60px; height: 60px; border-radius: 15px; object-fit: cover;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; font-size: 1rem; color: #333;">Lotte Center</h4>
                                <p style="margin: 0; font-size: 0.8rem; color: #888;">Observatory & Dinner</p>
                            </div>
                            <button onclick="voteLocation('Lotte Center')" style="background: white; border: 1.5px solid #ffe4eb; color: #ff4b82; padding: 8px 18px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#ff4b82'; this.style.color='white'" onmouseout="this.style.background='white'; this.style.color='#ff4b82'">Vote</button>
                        </div>

                    </div>
                </div>
                <!-- ================= END DATE PLANNER ================= -->
                
                <div class="chat-history" style="flex: 1; background: #fff;">
                    <?php 
                    $last_msg_id = 0;
                    $vote_counts = []; 
                    
                    foreach($group_chat_history as $msg): 
                        $is_me = ($msg['sender_id'] == $user_id);
                        $name = !empty($msg['nickname']) ? $msg['nickname'] : $msg['full_name'];
                        $short_name = explode(' ', $name)[0];
                        $last_msg_id = $msg['id'];

                        // KIỂM TRA TIN NHẮN VOTE VÀ CĂN GIỮA
                        if (strpos($msg['message_text'], '[VOTE] ') === 0) {
                            $loc = htmlspecialchars(substr($msg['message_text'], 7));
                            if (!isset($vote_counts[$loc])) $vote_counts[$loc] = 0;
                            $vote_counts[$loc]++;
                            $current_votes = $vote_counts[$loc];
                            ?>
                            <div style="display: flex; justify-content: center; margin: 15px 0; width: 100%;">
                                <div style="display: inline-block; background: #fff; border: 2px solid #ffe4eb; color: #5d1029; padding: 12px 25px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; box-shadow: 0 5px 15px rgba(255,75,130,0.08); text-align: center;">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px;">
                                        <img src="../uploads/<?= htmlspecialchars($msg['avatar'] ?: 'default.jpg') ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($short_name) ?>'" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                        <span><?= htmlspecialchars($short_name) ?> voted for <strong class="vote-loc-name"><?= $loc ?></strong></span>
                                    </div>
                                    <div style="background: linear-gradient(135deg, #ff4b82, #ff759c); color: white; display: inline-block; padding: 4px 15px; border-radius: 15px; font-size: 0.8rem; letter-spacing: 0.5px;">
                                        <i class="fa-solid fa-fire" style="margin-right: 3px;"></i> Total: <span class="vote-count" data-loc="<?= $loc ?>"><?= $current_votes ?></span> votes
                                    </div>
                                    <div style="font-size: 0.7rem; color: #aaa; margin-top: 5px;"><?= date("h:i A", strtotime($msg['created_at'])) ?></div>
                                </div>
                            </div>
                            <?php
                        } 
                        // TIN NHẮN TEXT BÌNH THƯỜNG
                        else {
                            ?>
                            <div class="msg-row <?= $is_me ? 'me' : 'them' ?>">
                                <?php if(!$is_me): ?>
                                    <div style="text-align: center; margin-right: 10px;">
                                        <img src="../uploads/<?= htmlspecialchars($msg['avatar'] ?: 'default.jpg') ?>" style="width:35px; height:35px; border-radius:50%; object-fit:cover;" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($name) ?>'">
                                        <div style="font-size: 0.6rem; color: #999; margin-top: 2px; font-weight:bold;"><?= htmlspecialchars($short_name) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="msg-bubble-wrap">
                                    <div class="msg-bubble"><?= htmlspecialchars($msg['message_text']) ?></div>
                                    <div class="msg-meta"><?= date("h:i A", strtotime($msg['created_at'])) ?> <?php if($is_me): ?><i class="fa-solid fa-check-double"></i><?php endif; ?></div>
                                </div>
                            </div>
                            <?php
                        }
                    endforeach; 
                    ?>
                </div>
                
                <div class="chat-input-wrapper">
                    <div class="ai-prompts" style="margin-bottom:10px;">
                        <span style="background:#fff0f5; color:#ff4b82; padding:5px 15px; border-radius:15px; font-size:0.8rem; margin-right:10px; cursor:pointer;"><i class="fa-solid fa-wand-magic-sparkles"></i> Ask about hobbies</span>
                        <span style="background:#f5f5f5; color:#666; padding:5px 15px; border-radius:15px; font-size:0.8rem; margin-right:10px; cursor:pointer;">Try: What do you do for fun?</span>
                        <span style="background:#f5f5f5; color:#666; padding:5px 15px; border-radius:15px; font-size:0.8rem; cursor:pointer;">Recommend a spot</span>
                    </div>
                    <div class="chat-input-box" style="display:flex; width:100%; align-items:center; gap:10px;">
                        <i class="fa-solid fa-circle-plus" style="color:#999; font-size:1.5rem;"></i>
                        <div style="flex:1; background:#f5f6f8; border-radius:25px; display:flex; align-items:center; padding:0 15px;">
                            <input type="text" placeholder="Type a message..." style="flex:1; border:none; background:transparent; padding:15px 0; outline:none;" <?= $is_group_active ? '' : 'disabled' ?> id="groupMsgInput">
                            <i class="fa-regular fa-face-smile" style="color:#999; font-size:1.2rem;"></i>
                        </div>
                        <button class="btn-send" style="background:#ff4b82; color:white; border:none; width:45px; height:45px; border-radius:50%;" <?= $is_group_active ? '' : 'disabled' ?> id="groupMsgBtn"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </div>

                <div id="dd-waiting-overlay" style="display: <?= $is_group_active ? 'none' : 'flex' ?>; position: absolute; top:0; left:0; right:0; bottom:0; background: rgba(255,245,248,0.8); backdrop-filter: blur(8px); z-index: 100; justify-content: center; align-items: center;">
                    <div style="background: linear-gradient(135deg, #fff0f5, #ffe4eb); padding: 40px; border-radius: 30px; width: 650px; box-shadow: 0 20px 50px rgba(255,75,130,0.15); text-align: center; position: relative;">
                        <button onclick="window.location.href='messages.php?mode=standard'" style="position: absolute; top: -15px; right: -15px; background: #ff4b82; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; box-shadow: 0 4px 10px rgba(255,75,130,0.4);"><i class="fa-solid fa-xmark"></i></button>
                        <div style="background: #e91e63; color: white; padding: 12px 20px; border-radius: 25px; font-weight: 700; font-size: 0.85rem; margin-bottom: 30px; display: inline-block; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> THE GROUP CHAT WILL BE AVAILABLE ONCE ALL INVITED MEMBERS HAVE JOINED.
                        </div>
                        <div id="dd-status-list" style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="color: #ff4b82;"><i class="fa-solid fa-spinner fa-spin"></i> Loading status...</div>
                        </div>
                    </div>
                </div>

                <script>
                    const ddId = <?= $current_dd_id ?>;
                    const currentUserId = <?= $_SESSION['user_id'] ?>;
                    let lastMsgId = <?= $last_msg_id ?>;
                    const waitingOverlay = document.getElementById('dd-waiting-overlay');
                    const statusList = document.getElementById('dd-status-list');
                    const inputField = document.getElementById('groupMsgInput');
                    const sendBtn = document.getElementById('groupMsgBtn');
                    const chatHistoryGroup = document.querySelector('.msg-main .chat-history');

                    chatHistoryGroup.scrollTop = chatHistoryGroup.scrollHeight;

                    // GỬI TIN NHẮN BÌNH THƯỜNG
                    async function sendGroupMessage() {
                        if(inputField.disabled) return;
                        const text = inputField.value.trim();
                        if (!text) return;

                        inputField.value = ''; 
                        
                        try {
                            const res = await fetch('../api/send_group_message.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({ double_date_id: ddId, message_text: text })
                            });
                            const data = await res.json();
                            if(data.success) fetchNewGroupMessages(); 
                        } catch(e) { console.error(e); }
                    }

                    if (sendBtn && inputField) {
                        sendBtn.onclick = sendGroupMessage;
                        inputField.onkeypress = function(e) { if (e.key === 'Enter') sendGroupMessage(); };
                    }

                    // GỬI TIN NHẮN VOTE
                    async function voteLocation(locationName) {
                        if(inputField.disabled) return;
                        
                        try {
                            const res = await fetch('../api/send_group_message.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({ double_date_id: ddId, message_text: `[VOTE] ${locationName}` })
                            });
                            const data = await res.json();
                            if(data.success) fetchNewGroupMessages(); 
                        } catch(e) { console.error(e); }
                    }

                    // TẢI TIN NHẮN MỚI TỪ SERVER
                    async function fetchNewGroupMessages() {
                        if (waitingOverlay.style.display !== 'none') return;

                        try {
                            const res = await fetch(`../api/get_group_messages.php?double_date_id=${ddId}&last_id=${lastMsgId}`);
                            const messages = await res.json();
                            
                            if (messages.length > 0) {
                                messages.forEach(msg => {
                                    const isMe = (msg.sender_id == currentUserId);
                                    let messageText = msg.message_text;
                                    let html = '';
                                    
                                    if (messageText.startsWith('[VOTE] ')) {
                                        const loc = messageText.replace('[VOTE] ', '');
                                        
                                        let currentVotes = 1;
                                        document.querySelectorAll(`.vote-count[data-loc="${loc}"]`).forEach(() => currentVotes++);
                                        
                                        const avatar = msg.avatar ? msg.avatar : 'default.jpg';
                                        const shortName = msg.display_name.split(' ')[0];
                                        
                                        html = `
                                            <div style="display: flex; justify-content: center; margin: 15px 0; width: 100%;">
                                                <div style="display: inline-block; background: #fff; border: 2px solid #ffe4eb; color: #5d1029; padding: 12px 25px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; box-shadow: 0 5px 15px rgba(255,75,130,0.08); text-align: center;">
                                                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px;">
                                                        <img src="../uploads/${avatar}" onerror="this.src='https://ui-avatars.com/api/?name=${shortName}'" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                                        <span>${shortName} voted for <strong class="vote-loc-name">${loc}</strong></span>
                                                    </div>
                                                    <div style="background: linear-gradient(135deg, #ff4b82, #ff759c); color: white; display: inline-block; padding: 4px 15px; border-radius: 15px; font-size: 0.8rem; letter-spacing: 0.5px;">
                                                        <i class="fa-solid fa-fire" style="margin-right: 3px;"></i> Total: <span class="vote-count" data-loc="${loc}">${currentVotes}</span> votes
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: #aaa; margin-top: 5px;">${msg.time}</div>
                                                </div>
                                            </div>
                                        `;
                                    } else {
                                        if (isMe) {
                                            html = `<div class="msg-row me"><div class="msg-bubble-wrap"><div class="msg-bubble">${messageText}</div><div class="msg-meta">${msg.time} <i class="fa-solid fa-check-double"></i></div></div></div>`;
                                        } else {
                                            const avatar = msg.avatar ? msg.avatar : 'default.jpg';
                                            const shortName = msg.display_name.split(' ')[0];
                                            html = `<div class="msg-row them">
                                                <div style="text-align: center; margin-right: 10px;">
                                                    <img src="../uploads/${avatar}" style="width:35px; height:35px; border-radius:50%; object-fit:cover;" onerror="this.src='https://ui-avatars.com/api/?name=${shortName}'">
                                                    <div style="font-size: 0.6rem; color: #999; margin-top: 2px; font-weight:bold;">${shortName}</div>
                                                </div>
                                                <div class="msg-bubble-wrap"><div class="msg-bubble">${messageText}</div><div class="msg-meta">${msg.time}</div></div>
                                            </div>`;
                                        }
                                    }
                                    
                                    chatHistoryGroup.insertAdjacentHTML('beforeend', html);
                                    lastMsgId = msg.id; 
                                });
                                chatHistoryGroup.scrollTop = chatHistoryGroup.scrollHeight;
                            }
                        } catch(e) { console.error(e); }
                    }

                    // CẬP NHẬT TRẠNG THÁI WAITING
                    async function checkDoubleDateStatus() {
                        if (waitingOverlay.style.display === 'none') return;

                        try {
                            const res = await fetch(`../api/check_double_date_status.php?id=${ddId}`);
                            const data = await res.json();
                            
                            if(data.success) {
                                if(data.all_accepted) {
                                    waitingOverlay.style.display = 'none';
                                    inputField.disabled = false;
                                    sendBtn.disabled = false;
                                    
                                    const previewTexts = document.querySelectorAll('.chat-thread.active .thread-preview');
                                    previewTexts.forEach(el => { el.innerText = 'Group active now'; el.style.color = '#4CAF50'; });
                                    const headerText = document.querySelector('.chat-header-text p');
                                    if (headerText) headerText.innerHTML = '<i class="fa-solid fa-circle" style="font-size:0.5rem; margin-right:5px; color:#4CAF50;"></i> Group active now';
                                } else {
                                    statusList.innerHTML = '';
                                    const creatorId = data.creator_id; 
                                    
                                    data.members.forEach(m => {
                                        if (m.user_id === currentUserId) return; 
                                        
                                        let bg = '#f5f5f5';
                                        let tc = '#a0a0a0';
                                        let text = '';

                                        if (currentUserId === creatorId) {
                                            bg = m.status === 'accepted' ? '#ff759c' : '#f5f5f5';
                                            tc = m.status === 'accepted' ? 'white' : '#a0a0a0';
                                            text = m.status === 'accepted' ? `${m.name} accepted your invitation` : `Waiting for ${m.name} to accept the invitation`; 
                                        } else {
                                            if (m.user_id === creatorId) {
                                                bg = '#ff759c';
                                                tc = 'white';
                                                text = `${m.name} created this group invitation`; 
                                            } else {
                                                bg = m.status === 'accepted' ? '#ff759c' : '#f5f5f5';
                                                tc = m.status === 'accepted' ? 'white' : '#a0a0a0';
                                                text = m.status === 'accepted' ? `${m.name} joined the group` : `Waiting for ${m.name} to join`; 
                                            }
                                        }

                                        statusList.innerHTML += `<div style="background: ${bg}; padding: 18px; border-radius: 25px; font-weight: 700; color: ${tc}; font-size: 1.1rem; text-align: center; transition: all 0.3s ease;">${text}</div>`;
                                    });
                                }
                            }
                        } catch(e) { console.error(e); }
                    }

                    <?php if(!$is_group_active): ?>
                        checkDoubleDateStatus();
                        setInterval(checkDoubleDateStatus, 2000);
                    <?php endif; ?>
                    
                    setInterval(fetchNewGroupMessages, 2000);

                </script>
            </main>

        <!-- LUỒNG CHAT BÌNH THƯỜNG / BLIND -->
        <?php elseif ($chat_partner): ?>
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

    <!-- CÁC MODAL 1:1 -->
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

        function handleKeyPress(e) { if (e.key === 'Enter') sendMessage(); }

        function sendMessage() {
            const input = document.getElementById('msgInput');
            if(!input) return;
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
                }
            });
        }

        // ==========================================
        // LOGIC TÌM KIẾM BLIND DATE 
        // ==========================================
        let pollInterval;

        function startNewBlindDate() {
            const btn = document.getElementById('btnBlindDate');
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
                    window.location.href = 'messages.php?mode=blind&chat_with=' + data.target_id;
                } else if(data.status === 'waiting') {
                    pollInterval = setInterval(checkIfMatched, 3000);
                }
            });
        }

        function checkIfMatched() {
            fetch('../api/blind_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'check_waiting' })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'matched') {
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