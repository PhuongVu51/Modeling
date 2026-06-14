<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

// Get the filter from query parameter, default to 'all'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// FETCH ALL MATCHES (HIDE BLIND UNREVEALED)
$stmt_matches = $conn->prepare("
    SELECT p.*, m.created_at as match_date, 
           (SELECT COUNT(*) FROM messages WHERE (sender_id = p.user_id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = p.user_id)) as message_count,
           u.created_at as user_created_at
    FROM matches m 
    JOIN profiles p ON (p.user_id = m.user1_id OR p.user_id = m.user2_id) 
    JOIN users u ON p.user_id = u.id
    WHERE (m.user1_id = ? OR m.user2_id = ?) 
      AND p.user_id != ?
      AND (m.is_blind = 0 OR m.is_revealed = 1)
    ORDER BY m.created_at DESC
");
$stmt_matches->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt_matches->execute();
$matches_result = $stmt_matches->get_result();
$all_matches = [];
while($row = $matches_result->fetch_assoc()){
    $all_matches[] = $row;
}

// Calculate Best Matches (Highest message count)
$best_matches = $all_matches;
usort($best_matches, function($a, $b) {
    return $b['message_count'] <=> $a['message_count'];
});
$best_matches = array_slice($best_matches, 0, 2);

// Filter Matches
$filtered_matches = [];
$current_date = new DateTime();
foreach ($all_matches as $m) {
    $match_date = new DateTime($m['match_date']);
    $diff = $current_date->diff($match_date);
    
    if ($filter == 'all') {
        $filtered_matches[] = $m;
    } elseif ($filter == 'new') {
        if ($diff->days <= 7) {
            $filtered_matches[] = $m;
        }
    } elseif ($filter == 'active') {
        if ($m['response_rate'] > 50 || $m['profile_views'] > 100) {
            $filtered_matches[] = $m;
        }
    }
}

// Mock Active Status
function getActiveStatus($user) {
    return rand(0, 1) ? 'Active Now' : 'Offline';
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
    <style>
        .matches-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            width: 100%;
        }
        .header-title-subtitle h1 {
            color: #5d1029;
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        .header-title-subtitle p {
            color: #666;
            font-size: 1rem;
        }
        .plan-date-btn {
            background-color: #ff4b82;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(255, 75, 130, 0.3);
        }
        
        .best-matches-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 15px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        .filter-btn {
            background-color: #fce4ec;
            color: #ff4b82;
            border: none;
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
        }
        .filter-btn.active {
            background-color: #ff4b82;
            color: white;
        }
        
        .section-title {
            color: #5d1029;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .best-matches-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px; 
            width: 100%; 
        }
        .best-match-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            display: flex;
            overflow: hidden;
            flex: 1;
        }
        .best-match-photo {
            width: 40%;
            object-fit: cover;
        }
        .best-match-info {
            padding: 20px;
            width: 60%;
            display: flex;
            flex-direction: column;
        }
        .sync-badge {
            background-color: #e8eaf6;
            color: #3f51b5;
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            align-self: flex-start;
            margin-bottom: 10px;
        }
        .best-match-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        .best-match-bio {
            color: #666;
            font-size: 0.9rem;
            font-style: italic;
            margin-bottom: 15px;
        }
        .best-match-traits {
            list-style: none;
            padding: 0;
            margin: 0 0 15px 0;
            font-size: 0.85rem;
            color: #555;
        }
        .best-match-traits li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .send-icebreaker-btn {
            background-color: #ff4b82;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: auto;
            text-align: center;
            text-decoration: none;
        }
        
        .recent-connections-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px; 
            width: 100%;
        }
        .see-all-link {
            color: #ff4b82;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        .connections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            width: 100%;
        }
        .connection-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .connection-photo-container {
            position: relative;
            height: 200px;
        }
        .connection-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
        }
        .status-active { background-color: #ff4b82; }
        .status-offline { background-color: #fff; color: #333; }
        .new-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #ff4b82;
            color: white;
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        .new-badge::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 10px;
            border-width: 5px 5px 0;
            border-style: solid;
            border-color: #ff4b82 transparent transparent transparent;
        }
        .connection-name {
            position: absolute;
            bottom: 10px;
            left: 15px;
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.8);
        }
        .connection-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .tags-container {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }
        .tag {
            background-color: #fce4ec;
            color: #ff4b82;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .connection-desc {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 15px;
            flex-grow: 1;
        }
        .card-action-btn {
            background-color: transparent;
            color: #ff4b82;
            border: 2px solid #ff4b82;
            padding: 10px;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .card-action-btn:hover {
            background-color: #ff4b82;
            color: white;
        }
        .card-action-btn.filled {
            background-color: #ff4b82;
            color: white;
        }
    </style>
</head>
<body class="dashboard-body" style="overflow-y: auto;">
    <?php include 'header.php'; ?>

    <main class="profile-wrapper" style="max-width: 1000px; margin: 0 auto; padding-bottom: 100px; display: flex; flex-direction: column;">
        
        <div class="matches-header">
            <div class="header-title-subtitle">
                <h1>Your Matches</h1>
                <p>Explore souls vibrating on your frequency.</p>
            </div>
            
            <div style="display: flex; align-items: center; gap: 20px;">
                <button class="plan-date-btn" onclick="openModal()">Plan a Double Date</button>
            </div>
        </div>

        <div class="best-matches-header">
            <?php if ($filter == 'all' && !empty($best_matches)): ?>
                <h2 class="section-title"><i class="fa-solid fa-wand-magic-sparkles" style="color:#ff4b82;"></i> Best Matches for You</h2>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div class="filter-buttons">
                <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">ALL</a>
                <a href="?filter=new" class="filter-btn <?= $filter == 'new' ? 'active' : '' ?>">NEW</a>
                <a href="?filter=active" class="filter-btn <?= $filter == 'active' ? 'active' : '' ?>">ACTIVE</a>
            </div>
        </div>

        <?php if ($filter == 'all' && !empty($best_matches)): ?>
        <div class="best-matches-container">
            <?php foreach($best_matches as $bm): 
                $bm_name = !empty($bm['nickname']) ? $bm['nickname'] : $bm['full_name'];
                $bm_age = date_diff(date_create($bm['dob']), date_create('today'))->y;
                $bm_photo = !empty($bm['avatar']) ? $bm['avatar'] : (!empty($bm['photo_1']) ? $bm['photo_1'] : 'default');
                $match_rate = $bm['match_rate'] > 0 ? $bm['match_rate'] : rand(80, 99); 
            ?>
            <div class="best-match-card">
                <img src="../uploads/<?= htmlspecialchars($bm_photo) ?>" class="best-match-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($bm_name) ?>&background=random'">
                <div class="best-match-info">
                    <span class="sync-badge"><i class="fa-solid fa-bolt"></i> <?= $match_rate ?>% SYNCED</span>
                    <div class="best-match-name"><?= htmlspecialchars($bm_name) ?>, <?= $bm_age ?></div>
                    <div class="best-match-bio">"<?= htmlspecialchars(substr($bm['bio'], 0, 50)) ?>..."</div>
                    <ul class="best-match-traits">
                        <li><i class="fa-solid fa-music" style="color:#ff4b82; width:15px;"></i> You both like similar things</li>
                        <li><i class="fa-solid fa-heart" style="color:#ff4b82; width:15px;"></i> High compatibility</li>
                    </ul>
                    <a href="messages.php?mode=standard&chat_with=<?= $bm['user_id'] ?>" class="send-icebreaker-btn">Send icebreaker</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="recent-connections-header">
            <h2 class="section-title">
                <?php 
                    if ($filter == 'new') echo 'New Matches';
                    elseif ($filter == 'active') echo 'Active Now';
                    else echo 'Recent Connections';
                ?>
            </h2>
            <a href="?filter=all" class="see-all-link">SEE ALL</a>
        </div>

        <?php if(empty($filtered_matches)): ?>
            <div style="text-align:center; padding: 100px 0; color:#999; background: #fff; width: 100%; border-radius: 30px; box-shadow: var(--shadow-soft);">
                <i class="fa-solid fa-heart-crack" style="font-size:4rem; margin-bottom:20px; color:#ffe5f0;"></i>
                <h3>No matches found!</h3>
                <p>Try changing your filter or keep swiping.</p>
            </div>
        <?php else: ?>
            <div class="connections-grid">
                <?php foreach($filtered_matches as $m): 
                    $m_name = !empty($m['nickname']) ? $m['nickname'] : $m['full_name'];
                    $m_age = date_diff(date_create($m['dob']), date_create('today'))->y;
                    $m_photo = !empty($m['avatar']) ? $m['avatar'] : (!empty($m['photo_1']) ? $m['photo_1'] : 'default');
                    
                    $status = getActiveStatus($m);
                    $status_class = $status == 'Active Now' ? 'status-active' : 'status-offline';
                    
                    $match_date = new DateTime($m['match_date']);
                    $diff = $current_date->diff($match_date);
                    $is_new = $diff->days <= 7;
                ?>
                <div class="connection-card">
                    <div class="connection-photo-container">
                        <img src="../uploads/<?= htmlspecialchars($m_photo) ?>" class="connection-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($m_name) ?>&background=random'">
                        <span class="status-badge <?= $status_class ?>"><?= $status ?></span>
                        <?php if($is_new): ?>
                            <span class="new-badge">New</span>
                        <?php endif; ?>
                        <div class="connection-name"><?= htmlspecialchars($m_name) ?>, <?= $m_age ?></div>
                    </div>
                    <div class="connection-info">
                        <div class="tags-container">
                            <span class="tag">High emotional connection</span>
                        </div>
                        <div class="connection-desc">
                           "<?= htmlspecialchars(substr($m['bio'], 0, 60)) ?>..."
                        </div>
                        <a href="messages.php?mode=standard&chat_with=<?= $m['user_id'] ?>" class="card-action-btn <?= $m['message_count'] == 0 ? 'filled' : '' ?>">
                            <?= $m['message_count'] > 0 ? 'Continue a Chat' : 'Spark a chat' ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <div id="doubleDateModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> CREATE PLAN A DOUBLE DATE</div>
            
            <div class="input-group">
                <input type="text" id="friend1" placeholder="Find your friend ..." onkeyup="checkName(this, 1)">
                <i id="icon-1" class="fa-solid fa-magnifying-glass status-icon"></i>
            </div>
            <div class="input-group">
                <input type="text" id="friend2" placeholder="Find your friend ..." onkeyup="checkName(this, 2)">
                <i id="icon-2" class="fa-solid fa-magnifying-glass status-icon"></i>
            </div>
            <div class="input-group">
                <input type="text" id="friend3" placeholder="Find my Soulmate ..." onkeyup="checkName(this, 3)">
                <i id="icon-3" class="fa-solid fa-magnifying-glass status-icon"></i>
            </div>

            <button id="btn-create-group" class="btn-create-group" disabled onclick="submitDoubleDate()">
                <i class="fa-solid fa-heart"></i> Create group chat
            </button>
        </div>
    </div>

    <style>
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4); backdrop-filter: blur(5px);
            display: flex; justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-content {
            background: linear-gradient(135deg, #fff5f8, #ffe5f0);
            padding: 40px; border-radius: 30px; width: 500px; position: relative;
            box-shadow: 0 20px 50px rgba(255, 75, 130, 0.2);
            display: flex; flex-direction: column; align-items: center;
        }
        .close-modal {
            position: absolute; top: -15px; right: -15px; background: #ff4b82; color: white;
            border: none; width: 40px; height: 40px; border-radius: 50%;
            font-size: 1.2rem; cursor: pointer; box-shadow: 0 4px 10px rgba(255,75,130,0.4);
        }
        .modal-badge {
            background: #ff4b82; color: white; padding: 8px 20px; border-radius: 20px;
            font-size: 0.8rem; font-weight: 700; margin-bottom: 30px; letter-spacing: 1px;
        }
        .input-group {
            position: relative; width: 100%; margin-bottom: 20px;
        }
        .input-group input {
            width: 100%; padding: 15px 45px 15px 20px; border-radius: 15px;
            border: 2px solid #ffb3c6; background: white; font-size: 1rem; color: #555;
            outline: none; transition: all 0.3s ease; box-sizing: border-box;
        }
        .input-group input:focus { border-color: #ff4b82; }
        .status-icon {
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            color: #ccc; font-size: 1.2rem; transition: all 0.3s ease;
        }
        .status-icon.valid {
            color: #ff4b82; /* Tích màu hồng khi đúng */
        }
        .btn-create-group {
            margin-top: 10px; padding: 15px 30px; border-radius: 25px; border: none;
            font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease;
            background: #d1d5db; color: #6b7280; /* Màu xám khi chưa đủ 3 người */
        }
        .btn-create-group.active {
            background: #ff4b82; color: white; box-shadow: 0 5px 15px rgba(255,75,130,0.4);
        }
    </style>

    <script>
        let validUsers = {}; // Lưu ID của những người nhập đúng
        let debounceTimer;

        // Bật tắt Modal
        function openModal() { document.getElementById('doubleDateModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('doubleDateModal').style.display = 'none'; }

        // Hàm kiểm tra khi gõ phím
        function checkName(input, index) {
            clearTimeout(debounceTimer);
            const nickname = input.value.trim();
            const icon = document.getElementById('icon-' + index);

            if(nickname.length < 2) {
                resetIcon(icon, index);
                return;
            }

            // Đợi 500ms sau khi ngừng gõ mới gọi API để đỡ lag server
            debounceTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`../api/check_nickname.php?nickname=${encodeURIComponent(nickname)}`);
                    const data = await res.json();

                    if(data.valid) {
                        // Đổi icon thành dấu tích hồng
                        icon.className = "fa-solid fa-circle-check status-icon valid";
                        validUsers[index] = data.user_id;
                    } else {
                        resetIcon(icon, index);
                    }
                    updateSubmitButton();
                } catch(e) { console.error("Lỗi:", e); }
            }, 500);
        }

        function resetIcon(icon, index) {
            icon.className = "fa-solid fa-magnifying-glass status-icon";
            delete validUsers[index];
            updateSubmitButton();
        }

        // Bật nút nếu đủ 3 người
        function updateSubmitButton() {
            const btn = document.getElementById('btn-create-group');
            if(Object.keys(validUsers).length === 3) {
                btn.classList.add('active');
                btn.disabled = false;
            } else {
                btn.classList.remove('active');
                btn.disabled = true;
            }
        }

        // Gửi dữ liệu tạo nhóm
        async function submitDoubleDate() {
            const friendsArray = Object.values(validUsers);
            if(friendsArray.length !== 3) return;

            const btn = document.getElementById('btn-create-group');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';

            try {
                const res = await fetch('../api/create_double_date.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ friends: friendsArray })
                });
                const data = await res.json();

                if(data.success) {
                    // Thành công -> Chuyển sang màn hình chờ ở tin nhắn
                    window.location.href = `messages.php?mode=double_date_waiting&id=${data.double_date_id}`;
                } else {
                    alert(data.message);
                    btn.innerHTML = '<i class="fa-solid fa-heart"></i> Create group chat';
                }
            } catch(e) {
                alert("Đã có lỗi xảy ra!");
            }
        }
    </script>
</body>
</html>