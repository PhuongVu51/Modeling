<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];

// Get current user info
$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$is_pro = isset($current_user['is_pro']) ? $current_user['is_pro'] : false;

// Get user's current interests for shared interest matching
$stmt_my_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? LIMIT 3");
$stmt_my_int->bind_param("i", $user_id);
$stmt_my_int->execute();
$my_interests_res = $stmt_my_int->get_result();
$my_interest_names = [];
while ($r = $my_interests_res->fetch_assoc()) { $my_interest_names[] = $r['name']; }

// Get all matches (people the current user has matched with) for stories row
$stmt_matches = $conn->prepare("
    SELECT p.user_id, p.full_name, p.nickname, p.avatar
    FROM matches m 
    JOIN profiles p ON (p.user_id = m.user1_id OR p.user_id = m.user2_id) 
    WHERE (m.user1_id = ? OR m.user2_id = ?) 
      AND p.user_id != ?
      AND (m.is_blind = 0 OR m.is_revealed = 1)
    ORDER BY m.created_at DESC
    LIMIT 8
");
$stmt_matches->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_matches->execute();
$story_matches = $stmt_matches->get_result()->fetch_all(MYSQLI_ASSOC);

// If no matches, get some other users for stories
if (empty($story_matches)) {
    $stmt_stories = $conn->prepare("
        SELECT p.user_id, p.full_name, p.nickname, p.avatar 
        FROM profiles p 
        WHERE p.user_id != ? 
        LIMIT 6
    ");
    $stmt_stories->bind_param("i", $user_id);
    $stmt_stories->execute();
    $story_matches = $stmt_stories->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Determine online status (pseudo-random based on user_id for demo)
foreach ($story_matches as &$sm) {
    $sm['display_name'] = !empty($sm['nickname']) ? $sm['nickname'] : explode(' ', $sm['full_name'])[0];
    $sm['is_online'] = ($sm['user_id'] % 3 !== 0); // Simple pseudo-online logic
}
unset($sm);

// Get posts feed - posts from other users
$stmt_posts = $conn->prepare("
    SELECT 
        po.id, po.caption, po.photo, po.mood_tag, po.shared_interest,
        po.likes_count, po.comments_count, po.created_at, po.user_id,
        p.full_name, p.nickname, p.avatar,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = po.id AND pl.user_id = ?) as user_liked
    FROM posts po
    JOIN profiles p ON po.user_id = p.user_id
    WHERE po.user_id != ?
    ORDER BY po.created_at DESC
    LIMIT 20
");
$stmt_posts->bind_param("ii", $user_id, $user_id);
$stmt_posts->execute();
$posts_raw = $stmt_posts->get_result()->fetch_all(MYSQLI_ASSOC);

// Process posts
$posts = [];
foreach ($posts_raw as $p) {
    $p['display_name'] = !empty($p['nickname']) ? $p['nickname'] : $p['full_name'];
    $time_diff = time() - strtotime($p['created_at']);
    if ($time_diff < 3600) $p['time_ago'] = round($time_diff / 60) . ' minutes ago';
    elseif ($time_diff < 86400) $p['time_ago'] = round($time_diff / 3600) . ' hours ago';
    else $p['time_ago'] = round($time_diff / 86400) . ' days ago';
    $posts[] = $p;
}

// If no posts in DB yet, create demo posts so the page isn't empty
if (empty($posts)) {
    // Seed 2 demo posts from first two other users
    $seed_users = $conn->query("SELECT p.user_id, p.full_name, p.nickname, p.avatar FROM profiles p WHERE p.user_id != $user_id LIMIT 2")->fetch_all(MYSQLI_ASSOC);
    foreach ($seed_users as $idx => $su) {
        $captions = [
            "Finally made it to the coast! The energy here is exactly what I needed.\nNothing beats a Pacific sunset. 🌊",
            "Exploring the city today, found this hidden gem of a cafe. The coffee is unreal ☕"
        ];
        $shared = !empty($my_interest_names) ? $my_interest_names[array_rand($my_interest_names)] : 'Travel';
        $moods = ['Chill', 'Happy', 'Excited', 'Peaceful'];
        $cap = $captions[$idx] ?? "Having an amazing day! Life is beautiful.";
        $mood = $moods[$idx % count($moods)];
        $conn->query("INSERT INTO posts (user_id, caption, photo, mood_tag, shared_interest, likes_count, comments_count, created_at) VALUES ({$su['user_id']}, " . $conn->real_escape_string(json_encode($cap)) . ", NULL, '$mood', '$shared', " . rand(80,200) . ", " . rand(5,30) . ", DATE_SUB(NOW(), INTERVAL " . rand(1,5) . " HOUR))");
    }
    // Re-fetch
    $stmt_posts->execute();
    $posts_raw2 = $stmt_posts->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($posts_raw2 as $p) {
        $p['display_name'] = !empty($p['nickname']) ? $p['nickname'] : $p['full_name'];
        $time_diff = time() - strtotime($p['created_at']);
        if ($time_diff < 3600) $p['time_ago'] = round($time_diff / 60) . ' minutes ago';
        elseif ($time_diff < 86400) $p['time_ago'] = round($time_diff / 3600) . ' hours ago';
        else $p['time_ago'] = round($time_diff / 86400) . ' days ago';
        $posts[] = $p;
    }
}

// Get date spots
$date_spots = $conn->query("SELECT * FROM date_spots ORDER BY sync_rate DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

// Get 2 recent matches for the Messages button avatars
$recent_match_avatars = array_slice($story_matches, 0, 2);

// Handle post like via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like_post') {
    $post_id = (int)$_POST['post_id'];
    $check = $conn->prepare("SELECT 1 FROM post_likes WHERE user_id = ? AND post_id = ?");
    $check->bind_param("ii", $user_id, $post_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $conn->prepare("DELETE FROM post_likes WHERE user_id = ? AND post_id = ?")->execute() || null;
        $del = $conn->prepare("DELETE FROM post_likes WHERE user_id = ? AND post_id = ?");
        $del->bind_param("ii", $user_id, $post_id);
        $del->execute();
        $conn->query("UPDATE posts SET likes_count = likes_count - 1 WHERE id = $post_id AND likes_count > 0");
        echo json_encode(['liked' => false]);
    } else {
        $ins = $conn->prepare("INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)");
        $ins->bind_param("ii", $user_id, $post_id);
        $ins->execute();
        $conn->query("UPDATE posts SET likes_count = likes_count + 1 WHERE id = $post_id");
        echo json_encode(['liked' => true]);
    }
    exit();
}

$my_avatar_url = !empty($current_user['avatar'])
    ? '../uploads/' . htmlspecialchars($current_user['avatar'])
    : 'https://ui-avatars.com/api/?name=' . urlencode($current_user['full_name'] ?? 'U') . '&background=e83e8c&color=fff&size=128';

$my_display = htmlspecialchars($current_user['nickname'] ?? $current_user['full_name'] ?? 'You');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore – SoulSync</title>
    <meta name="description" content="Explore posts, stories and date spots from your SoulSync community.">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <style>
        /* ── EXPLORE PAGE RESET ── */
        body.dashboard-body { overflow: hidden; }
        .explore-wrapper {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 0;
            height: calc(100vh - 80px);
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ── LEFT FEED ── */
        .explore-feed {
            overflow-y: auto;
            padding: 28px 28px 28px 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .explore-feed::-webkit-scrollbar { width: 4px; }
        .explore-feed::-webkit-scrollbar-thumb { background: #f0f0f0; border-radius: 10px; }

        .explore-page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--y2k-pink);
            margin-bottom: 4px;
        }

        /* ── STORIES ROW ── */
        .stories-row {
            display: flex;
            gap: 22px;
            overflow-x: auto;
            padding-bottom: 4px;
            align-items: flex-start;
        }
        .stories-row::-webkit-scrollbar { display: none; }
        .story-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            cursor: pointer;
            text-decoration: none;
        }
        .story-avatar-ring {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(135deg, #ff4b82, #800040);
            position: relative;
            transition: transform 0.2s;
        }
        .story-avatar-ring:hover { transform: scale(1.05); }
        .story-avatar-ring img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2.5px solid #fff;
        }
        .story-online-dot {
            position: absolute;
            bottom: 3px;
            right: 3px;
            width: 13px;
            height: 13px;
            background: #2ecc71;
            border: 2px solid #fff;
            border-radius: 50%;
        }
        .story-name {
            font-size: 0.72rem;
            font-weight: 700;
            color: #444;
            text-align: center;
            max-width: 68px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── POST COMPOSER ── */
        .post-composer {
            background: #fff;
            border-radius: 16px;
            padding: 14px 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .composer-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .composer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .composer-input {
            flex: 1;
            background: #fdf0f6;
            border: none;
            border-radius: 50px;
            padding: 10px 18px;
            font-size: 0.9rem;
            color: #aaa;
            cursor: pointer;
            outline: none;
            font-family: 'Public Sans', sans-serif;
        }
        .composer-input:focus { color: #333; }
        .composer-actions {
            display: flex;
            gap: 0;
            border-top: 1px solid #f5f5f5;
            padding-top: 10px;
        }
        .composer-action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 700;
            color: #666;
            transition: background 0.2s;
            border: none;
            background: none;
            font-family: 'Public Sans', sans-serif;
        }
        .composer-action-btn:hover { background: #fdf0f6; color: var(--y2k-pink); }
        .composer-action-btn i { font-size: 1rem; }
        .composer-action-btn.photo-btn i { color: #45b26b; }
        .composer-action-btn.feeling-btn i { color: #f6a623; }

        /* ── POST CARD ── */
        .post-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .post-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 18px 12px;
        }
        .post-user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffe5f0;
            flex-shrink: 0;
        }
        .post-user-info { flex: 1; }
        .post-user-name {
            font-size: 0.95rem;
            font-weight: 800;
            color: #222;
            margin: 0;
        }
        .post-time-ago {
            font-size: 0.75rem;
            color: #aaa;
            font-weight: 600;
        }
        .post-menu-btn {
            background: none;
            border: none;
            color: #bbb;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .post-menu-btn:hover { background: #f5f5f5; color: #555; }

        /* ── POST IMAGE ── */
        .post-image-wrap {
            position: relative;
            width: 100%;
            max-height: 480px;
            overflow: hidden;
            background: #111;
        }
        .post-image-wrap img {
            width: 100%;
            height: 480px;
            object-fit: cover;
            display: block;
        }
        .post-mood-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 0.78rem;
            font-weight: 800;
            color: #333;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .post-shared-interest {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(10px);
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #333;
        }
        .shared-interest-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #a78bfa, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* ── POST ACTIONS ── */
        .post-actions {
            display: flex;
            align-items: center;
            padding: 12px 18px 8px;
            gap: 18px;
        }
        .post-action-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            color: #888;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 0;
            transition: color 0.2s;
            font-family: 'Public Sans', sans-serif;
        }
        .post-action-btn i { font-size: 1.1rem; transition: transform 0.2s; }
        .post-action-btn:hover { color: var(--y2k-pink); }
        .post-action-btn:hover i { transform: scale(1.15); }
        .post-action-btn.liked { color: var(--y2k-pink); }
        .post-action-btn.liked i { color: var(--y2k-pink); }
        .post-save-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: #bbb;
            font-size: 1.1rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .post-save-btn:hover { color: var(--y2k-pink); }

        /* ── POST CAPTION ── */
        .post-caption {
            padding: 4px 18px 16px;
        }
        .post-caption p {
            font-size: 0.88rem;
            color: #444;
            line-height: 1.6;
            margin: 0;
        }

        /* ── ASK ABOUT BTN ── */
        .btn-ask-about {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: calc(100% - 36px);
            margin: 0 18px 18px;
            padding: 14px;
            background: linear-gradient(135deg, #ff4b82, #e83e8c);
            color: #fff;
            border: none;
            border-radius: 50px;
            font-size: 0.92rem;
            font-weight: 800;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.2s;
            font-family: 'Public Sans', sans-serif;
        }
        .btn-ask-about:hover { opacity: 0.9; transform: translateY(-1px); }

        /* ── RIGHT SIDEBAR ── */
        .explore-sidebar-right {
            padding: 28px 0 28px 24px;
            border-left: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
        }
        .explore-sidebar-right::-webkit-scrollbar { width: 3px; }
        .explore-sidebar-right::-webkit-scrollbar-thumb { background: #f0f0f0; border-radius: 10px; }

        /* Search */
        .explore-search {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1.5px solid #ffd6e7;
            border-radius: 50px;
            padding: 10px 16px;
            gap: 10px;
        }
        .explore-search input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 0.85rem;
            color: #555;
            background: transparent;
            font-family: 'Public Sans', sans-serif;
        }
        .explore-search input::placeholder { color: #ccc; }
        .explore-search i { color: var(--y2k-pink); font-size: 0.95rem; }

        /* Suggested header */
        .sidebar-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar-section-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--y2k-pink);
        }
        .sidebar-see-all {
            font-size: 0.8rem;
            font-weight: 700;
            color: #888;
            text-decoration: none;
            transition: color 0.2s;
        }
        .sidebar-see-all:hover { color: var(--y2k-pink); }

        /* Date spots */
        .date-spots-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .date-spots-label span {
            font-size: 0.7rem;
            font-weight: 800;
            color: #bbb;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .date-spot-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.15s;
            border-radius: 8px;
        }
        .date-spot-item:last-child { border-bottom: none; }
        .date-spot-item:hover { background: #fdf0f6; padding-left: 6px; }
        .date-spot-thumb {
            width: 54px;
            height: 54px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            position: relative;
        }
        .date-spot-thumb-wrap {
            position: relative;
            width: 54px;
            height: 54px;
            flex-shrink: 0;
        }
        .date-spot-thumb-wrap img {
            width: 54px;
            height: 54px;
            border-radius: 10px;
            object-fit: cover;
        }
        .sync-badge {
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--y2k-pink);
            color: #fff;
            font-size: 0.58rem;
            font-weight: 800;
            padding: 2px 5px;
            border-radius: 10px;
            white-space: nowrap;
        }
        .date-spot-info { flex: 1; }
        .date-spot-name {
            font-size: 0.82rem;
            font-weight: 800;
            color: #222;
            margin: 0 0 3px;
            line-height: 1.3;
        }
        .date-spot-desc {
            font-size: 0.72rem;
            color: #aaa;
            line-height: 1.4;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Messages Button */
        .sidebar-messages-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #fff;
            border: 2px solid var(--y2k-pink);
            border-radius: 50px;
            padding: 14px 20px;
            color: var(--y2k-pink);
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s;
            margin-top: auto;
        }
        .sidebar-messages-btn:hover {
            background: var(--y2k-pink);
            color: #fff;
        }
        .sidebar-messages-btn i { font-size: 1.1rem; }
        .msg-avatars-stack {
            display: flex;
        }
        .msg-avatars-stack img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid #fff;
            object-fit: cover;
            margin-left: -8px;
        }
        .msg-avatars-stack img:first-child { margin-left: 0; }

        /* ── EMPTY FEED ── */
        .empty-feed-hint {
            text-align: center;
            padding: 60px 20px;
            color: #ccc;
        }
        .empty-feed-hint i { font-size: 3rem; margin-bottom: 16px; color: #eee; }
        .empty-feed-hint p { font-size: 0.95rem; font-weight: 600; }

        /* Post image placeholder gradient */
        .post-img-placeholder {
            width: 100%;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }
    </style>
</head>
<body class="dashboard-body">

    <?php include 'header.php'; ?>

    <div class="explore-wrapper">

        <!-- ══ LEFT: MAIN FEED ══ -->
        <div class="explore-feed" id="explore-feed">

            <h1 class="explore-page-title">Explore</h1>

            <!-- Stories Row -->
            <div class="stories-row">
                <?php foreach ($story_matches as $s): 
                    $s_avatar = !empty($s['avatar'])
                        ? '../uploads/' . htmlspecialchars($s['avatar'])
                        : 'https://ui-avatars.com/api/?name=' . urlencode($s['display_name']) . '&background=e83e8c&color=fff&size=128';
                ?>
                <a href="view_profile.php?id=<?= $s['user_id'] ?>" class="story-item">
                    <div class="story-avatar-ring">
                        <img src="<?= $s_avatar ?>" alt="<?= htmlspecialchars($s['display_name']) ?>" onerror="this.src='https://ui-avatars.com/api/?name=U&background=random'">
                        <?php if ($s['is_online']): ?>
                            <span class="story-online-dot"></span>
                        <?php endif; ?>
                    </div>
                    <span class="story-name"><?= htmlspecialchars($s['display_name']) ?></span>
                </a>
                <?php endforeach; ?>

                <?php if (empty($story_matches)): ?>
                <!-- Placeholder stories when no data -->
                <?php $demo_names = ['Long', 'Ph Vu', 'Hiep', 'My', 'John', 'Sarah'];
                      $demo_colors = ['e83e8c','ff6b9d','c0392b','8e44ad','2980b9','27ae60'];
                      foreach ($demo_names as $di => $dn): ?>
                <div class="story-item">
                    <div class="story-avatar-ring">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($dn) ?>&background=<?= $demo_colors[$di] ?>&color=fff&size=128" alt="<?= $dn ?>">
                        <?php if ($di % 3 !== 2): ?><span class="story-online-dot"></span><?php endif; ?>
                    </div>
                    <span class="story-name"><?= $dn ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Post Composer -->
            <div class="post-composer">
                <div class="composer-top">
                    <img src="<?= $my_avatar_url ?>" alt="Me" class="composer-avatar" onerror="this.src='https://ui-avatars.com/api/?name=U&background=e83e8c&color=fff'">
                    <input type="text" class="composer-input" id="composer-input" placeholder="What's on your mind?" onclick="openPostModal()">
                </div>
                <div class="composer-actions">
                    <button class="composer-action-btn photo-btn" onclick="openPostModal()">
                        <i class="fa-solid fa-image"></i> Photo/video
                    </button>
                    <button class="composer-action-btn feeling-btn" onclick="openPostModal()">
                        <i class="fa-solid fa-face-smile"></i> Feeling/activity
                    </button>
                </div>
            </div>

            <!-- Posts Feed -->
            <?php if (empty($posts)): ?>
            <div class="empty-feed-hint">
                <i class="fa-solid fa-rss"></i>
                <p>No posts yet. Match with someone and start exploring!</p>
            </div>
            <?php else: ?>
                <?php foreach ($posts as $post):
                    $p_avatar = !empty($post['avatar'])
                        ? '../uploads/' . htmlspecialchars($post['avatar'])
                        : 'https://ui-avatars.com/api/?name=' . urlencode($post['display_name']) . '&background=random&color=fff&size=128';
                    $shared_int = $post['shared_interest'] ?? (!empty($my_interest_names) ? $my_interest_names[0] : 'Travel');
                ?>
                <div class="post-card" id="post-<?= $post['id'] ?>">
                    <!-- Post Header -->
                    <div class="post-card-header">
                        <a href="view_profile.php?id=<?= $post['user_id'] ?>">
                            <img src="<?= $p_avatar ?>" alt="<?= htmlspecialchars($post['display_name']) ?>" class="post-user-avatar" onerror="this.src='https://ui-avatars.com/api/?name=U&background=random'">
                        </a>
                        <div class="post-user-info">
                            <p class="post-user-name">
                                <a href="view_profile.php?id=<?= $post['user_id'] ?>" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($post['display_name']) ?>
                                </a>
                            </p>
                            <span class="post-time-ago"><?= htmlspecialchars($post['time_ago']) ?></span>
                        </div>
                        <button class="post-menu-btn" title="More options">
                            <i class="fa-solid fa-ellipsis"></i>
                        </button>
                    </div>

                    <!-- Post Image (or gradient placeholder) -->
                    <div class="post-image-wrap">
                        <?php if (!empty($post['photo'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($post['photo']) ?>" alt="Post photo" onerror="this.parentElement.style.background='linear-gradient(135deg,#1a1a2e,#16213e,#0f3460)'">
                        <?php else:
                            $gradients = [
                                'linear-gradient(160deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
                                'linear-gradient(160deg, #0d1b2a 0%, #1b263b 60%, #415a77 100%)',
                                'linear-gradient(160deg, #2d1b69 0%, #11998e 100%)',
                                'linear-gradient(160deg, #3a1c71 0%, #d76d77 50%, #ffaf7b 100%)',
                            ];
                            $gi = $post['id'] % count($gradients);
                            $emojis = ['🌅', '🌊', '🌆', '🌸', '⛰️', '🌃'];
                            $ei = $post['id'] % count($emojis);
                        ?>
                            <div class="post-img-placeholder" style="background: <?= $gradients[$gi] ?>">
                                <?= $emojis[$ei] ?>
                            </div>
                        <?php endif; ?>

                        <!-- Mood badge -->
                        <?php if (!empty($post['mood_tag'])): ?>
                        <div class="post-mood-badge">
                            <?php
                            $mood_icons = ['Chill'=>'🛋️','Happy'=>'😄','Excited'=>'🎉','Peaceful'=>'🌿','Sad'=>'😢','Romantic'=>'💕'];
                            echo $mood_icons[$post['mood_tag']] ?? '✨';
                            ?> <?= htmlspecialchars($post['mood_tag']) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Shared interest pill -->
                        <div class="post-shared-interest">
                            <div class="shared-interest-icon">
                                <i class="fa-solid fa-star" style="color:#fff; font-size:0.7rem;"></i>
                            </div>
                            You both enjoy <?= htmlspecialchars($shared_int) ?> 
                            <?php $travel_emojis = ['Travel'=>'🌊','Music'=>'🎵','Coffee'=>'☕','Reading'=>'📚','Gym'=>'💪','Pets'=>'🐾','Movies'=>'🎬','Cooking'=>'👨‍🍳','Gaming'=>'🎮','Art'=>'🎨','Photography'=>'📷','Dancing'=>'💃','Foodie'=>'🍜','Sports'=>'⚽','Karaoke'=>'🎤'];
                            echo $travel_emojis[$shared_int] ?? '✨'; ?>
                        </div>
                    </div>

                    <!-- Post Actions -->
                    <div class="post-actions">
                        <button class="post-action-btn like-btn <?= $post['user_liked'] ? 'liked' : '' ?>"
                                id="like-btn-<?= $post['id'] ?>"
                                onclick="toggleLike(<?= $post['id'] ?>, this)">
                            <i class="<?= $post['user_liked'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                            <span id="likes-count-<?= $post['id'] ?>"><?= number_format($post['likes_count']) ?></span>
                        </button>

                        <button class="post-action-btn">
                            <i class="fa-regular fa-comment"></i>
                            <span><?= number_format($post['comments_count']) ?></span>
                        </button>

                        <button class="post-action-btn" onclick="sharePost(<?= $post['id'] ?>)">
                            <i class="fa-solid fa-share-nodes"></i>
                        </button>

                        <button class="post-save-btn" title="Save post">
                            <i class="fa-regular fa-bookmark"></i>
                        </button>
                    </div>

                    <!-- Caption -->
                    <?php if (!empty($post['caption'])): ?>
                    <div class="post-caption">
                        <p><?= nl2br(htmlspecialchars(json_decode($post['caption']) ?? $post['caption'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Ask About Button -->
                    <button class="btn-ask-about" onclick="askAboutPost(<?= $post['id'] ?>, '<?= htmlspecialchars(addslashes($post['display_name'])) ?>')">
                        <i class="fa-solid fa-robot"></i>
                        Ask about this
                    </button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div><!-- /explore-feed -->


        <!-- ══ RIGHT: SIDEBAR ══ -->
        <aside class="explore-sidebar-right">

            <!-- Search -->
            <div class="explore-search">
                <input type="text" placeholder="Find your friend ..." id="friend-search-input">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <!-- Date Spots -->
            <div>
                <div class="sidebar-section-header" style="margin-bottom:10px;">
                    <span class="sidebar-section-title">Suggested for you</span>
                    <a href="date_spots.php" class="sidebar-see-all">See all</a>
                </div>

                <div class="date-spots-label">
                    <span>Date Spot</span>
                    <a href="date_spots.php" style="color:inherit; text-decoration:none;"><span>View More</span></a>
                </div>

                <div class="date-spots-list">
                    <?php
                    $spot_images = [
                        '../image/venue_1.png',
                        '../image/venue_2.png',
                        '../image/venue_3.png',
                        '../image/venue_4.png',
                    ];
                    foreach ($date_spots as $si => $spot): ?>
                    <a href="date_spot_detail.php?id=<?= $spot['id'] ?>" class="date-spot-item" style="text-decoration:none;">
                        <div class="date-spot-thumb-wrap">
                            <img src="<?= $spot_images[$si] ?? $spot_images[0] ?>" alt="<?= htmlspecialchars($spot['name']) ?>" onerror="this.src='../image/venue_1.png'">
                            <span class="sync-badge"><?= $spot['sync_rate'] ?>% SYNC</span>
                        </div>
                        <div class="date-spot-info">
                            <p class="date-spot-name"><?= htmlspecialchars($spot['name']) ?></p>
                            <p class="date-spot-desc"><?= htmlspecialchars($spot['description']) ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>

                    <?php if (empty($date_spots)): ?>
                    <!-- Static fallback -->
                    <a href="date_spots.php" class="date-spot-item" style="text-decoration:none;">
                        <div class="date-spot-thumb-wrap">
                            <img src="../image/venue_1.png" alt="West Lake">
                            <span class="sync-badge">96% SYNC</span>
                        </div>
                        <div class="date-spot-info">
                            <p class="date-spot-name">West Lake (Trich Sai / Ve Ho area)</p>
                            <p class="date-spot-desc">Enjoy a sunset walk, then grab a salted coffee from nearby street vendors.</p>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages Button -->
            <a href="messages.php" class="sidebar-messages-btn" style="margin-top: auto;">
                <i class="fa-solid fa-paper-plane"></i>
                Messages
                <?php if (!empty($recent_match_avatars)): ?>
                <div class="msg-avatars-stack">
                    <?php foreach ($recent_match_avatars as $rma):
                        $rma_av = !empty($rma['avatar'])
                            ? '../uploads/' . htmlspecialchars($rma['avatar'])
                            : 'https://ui-avatars.com/api/?name=' . urlencode($rma['display_name']) . '&background=random&color=fff&size=64';
                    ?>
                    <img src="<?= $rma_av ?>" alt="<?= htmlspecialchars($rma['display_name']) ?>" onerror="this.src='https://ui-avatars.com/api/?name=U&background=random&color=fff'">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </a>

        </aside>

    </div><!-- /explore-wrapper -->

    <!-- ══ POST COMPOSER MODAL ══ -->
    <div id="post-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;" onclick="closePostModalOnOverlay(event)">
        <div style="background:#fff; border-radius:20px; padding:30px; width:100%; max-width:540px; box-shadow:0 30px 60px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="font-size:1.2rem; color:#333; margin:0;">Create Post</h3>
                <button onclick="closePostModal()" style="background:none; border:none; font-size:1.5rem; color:#aaa; cursor:pointer;">&times;</button>
            </div>
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <img src="<?= $my_avatar_url ?>" style="width:44px; height:44px; border-radius:50%; object-fit:cover;" onerror="this.src='https://ui-avatars.com/api/?name=U&background=e83e8c&color=fff'">
                <strong style="font-size:0.95rem;"><?= $my_display ?></strong>
            </div>
            <textarea id="post-text" placeholder="What's on your mind, <?= $my_display ?>?" style="width:100%; height:120px; border:none; resize:none; font-size:1rem; outline:none; color:#333; font-family:'Public Sans',sans-serif;" oninput="this.style.height='auto'; this.style.height=this.scrollHeight+'px'"></textarea>
            <div style="display:flex; gap:10px; margin-bottom:16px;">
                <select id="post-mood" style="border:1.5px solid #eee; border-radius:10px; padding:8px 12px; font-size:0.85rem; outline:none; font-family:'Public Sans',sans-serif;">
                    <option value="">😊 Feeling/Activity</option>
                    <option value="Chill">🛋️ Chill</option>
                    <option value="Happy">😄 Happy</option>
                    <option value="Excited">🎉 Excited</option>
                    <option value="Peaceful">🌿 Peaceful</option>
                    <option value="Romantic">💕 Romantic</option>
                </select>
            </div>
            <button onclick="submitPost()" style="width:100%; padding:14px; background:linear-gradient(135deg,#ff4b82,#800040); color:#fff; border:none; border-radius:12px; font-size:1rem; font-weight:800; cursor:pointer; font-family:'Public Sans',sans-serif;">
                Post
            </button>
        </div>
    </div>

    <div id="toast-msg" style="display:none; position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:12px 24px; border-radius:50px; font-weight:700; z-index:99999; font-size:0.9rem;"></div>

    <script>
    // ── LIKE TOGGLE ──
    function toggleLike(postId, btn) {
        fetch('explore.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=like_post&post_id=${postId}`
        })
        .then(r => r.json())
        .then(data => {
            const countEl = document.getElementById(`likes-count-${postId}`);
            const icon = btn.querySelector('i');
            let count = parseInt(countEl.textContent.replace(/,/g, ''));
            if (data.liked) {
                btn.classList.add('liked');
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
                countEl.textContent = (count + 1).toLocaleString();
                icon.style.transform = 'scale(1.3)';
                setTimeout(() => icon.style.transform = '', 300);
            } else {
                btn.classList.remove('liked');
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
                countEl.textContent = Math.max(0, count - 1).toLocaleString();
            }
        });
    }

    // ── SHARE ──
    function sharePost(postId) {
        const url = window.location.origin + window.location.pathname + '?highlight=' + postId;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => showToast('🔗 Link copied!'));
        } else {
            showToast('🔗 Link: ' + url);
        }
    }

    // ── ASK ABOUT ──
    function askAboutPost(postId, name) {
        window.location.href = `messages.php?ai_ask=1&about_user=${encodeURIComponent(name)}`;
    }

    // ── POST MODAL ──
    function openPostModal() {
        const modal = document.getElementById('post-modal-overlay');
        modal.style.display = 'flex';
        setTimeout(() => document.getElementById('post-text').focus(), 100);
    }
    function closePostModal() {
        document.getElementById('post-modal-overlay').style.display = 'none';
    }
    function closePostModalOnOverlay(e) {
        if (e.target === document.getElementById('post-modal-overlay')) closePostModal();
    }

    function submitPost() {
        const text = document.getElementById('post-text').value.trim();
        const mood = document.getElementById('post-mood').value;
        if (!text) { showToast('⚠️ Please write something first!'); return; }
        fetch('../api/api_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=create_post&caption=${encodeURIComponent(text)}&mood_tag=${encodeURIComponent(mood)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('✅ Post shared!');
                closePostModal();
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('❌ ' + (data.message || 'Error posting'));
            }
        })
        .catch(() => {
            // Optimistic UI: just reload
            showToast('✅ Post shared!');
            closePostModal();
            setTimeout(() => location.reload(), 1200);
        });
    }

    // ── TOAST ──
    function showToast(msg) {
        const t = document.getElementById('toast-msg');
        t.textContent = msg;
        t.style.display = 'block';
        setTimeout(() => { t.style.display = 'none'; }, 2800);
    }

    // ── FRIEND SEARCH (client-side filter) ──
    document.getElementById('friend-search-input').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.story-item').forEach(item => {
            const name = item.querySelector('.story-name').textContent.toLowerCase();
            item.style.display = name.includes(q) ? 'flex' : 'none';
        });
    });
    </script>

</body>
</html>
