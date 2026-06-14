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

// Get user interests for recommendation
$stmt_int = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? LIMIT 5");
$stmt_int->bind_param("i", $user_id);
$stmt_int->execute();
$user_interests = $stmt_int->get_result()->fetch_all(MYSQLI_ASSOC);

// Get 2 recent matches for the recommendation section
$stmt_matches = $conn->prepare("
    SELECT p.user_id, p.full_name, p.nickname, p.avatar
    FROM matches m 
    JOIN profiles p ON (p.user_id = m.user1_id OR p.user_id = m.user2_id) 
    WHERE (m.user1_id = ? OR m.user2_id = ?) AND p.user_id != ?
    AND (m.is_blind = 0 OR m.is_revealed = 1)
    ORDER BY m.created_at DESC
");
$stmt_matches->bind_param("iii", $user_id, $user_id, $user_id);
$stmt_matches->execute();
$rec_matches = $stmt_matches->get_result()->fetch_all(MYSQLI_ASSOC);

// Active filter tab
$active_tab = $_GET['tab'] ?? 'first_date';

// All venue data
$venues = [
    [
        'id' => 1,
        'name' => 'Lighthouse Sky Bar',
        'type' => 'ROOFTOP BAR',
        'price' => '250k – 500k',
        'price_color' => '#8d7076',
        'quote' => '"View Old Quarter & Chuong Duong Bridge"',
        'tags' => ['💗 Perfect for romantic nights', '☕ Great for deep conversations', '🎲 Easy first date spot'],
        'location' => 'Hoan Kiem',
        'image' => '../image/lighthouseskybar.jpg',
        'tab' => ['first_date', 'romantic'],
    ],
    [
        'id' => 2,
        'name' => 'Sky Walk Lotte',
        'type' => 'CITY VIEW EXPERIENCE',
        'price' => '150k – 300k',
        'price_color' => '#ff4d8d',
        'quote' => '"See Hanoi from above and share the moment"',
        'tags' => ['🏙️ 65th floor view', '📸 Instagram-worthy', '💫 Exciting first date'],
        'location' => 'Lieu Giai',
        'image' => '../image/lotteobservationdeck.jpg',
        'tab' => ['first_date', 'deep_talk'],
    ],
    [
        'id' => 3,
        'name' => 'The Alchemist',
        'type' => 'COCKTAIL BAR & SPEAKEASY',
        'price' => '150k – 300k',
        'price_color' => '#ff4d8d',
        'quote' => '"Signature cocktails in a cozy, hidden atmosphere"',
        'tags' => ['🍸 Experimental drinks', '🤫 Hidden gem vibe', '✨ Aesthetic interior'],
        'location' => 'West Lake',
        'image' => '../image/thealchemist.jpg',
        'tab' => ['romantic', 'deep_talk'],
    ],
    [
        'id' => 4,
        'name' => 'Complex 01',
        'type' => 'CREATIVE SPACE',
        'price' => '150k – 300k',
        'price_color' => '#ff4d8d',
        'quote' => '"Create something together, break the ice naturally"',
        'tags' => ['🎨 Art workshops', '🫶 Interactive activities', '✨ Unique first date'],
        'location' => 'Tay Son',
        'image' => '../image/complex01.jpg',
        'tab' => ['first_date', 'romantic', 'deep_talk'],
    ],
];

// Filter by active tab
if ($active_tab === 'saved') {
    $filtered_venues = $venues; // We'll filter these via JS using localStorage
} else {
    $filtered_venues = array_filter($venues, function($v) use ($active_tab) {
        return in_array($active_tab, $v['tab']);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore – SoulSync</title>
    <meta name="description" content="Explore date spots and the perfect places for your moments.">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <style>
        /* ── PAGE SHELL ── */
        body.dashboard-body { overflow-y: auto; height: auto; background: #faf9fa; }
        .datespots-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 48px 44px 80px;
        }

        /* ── HEADER ── */
        .ds-header { margin-bottom: 24px; }
        .ds-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 36px;
            font-weight: 800;
            color: #8f0043;
            line-height: 1.33;
            margin: 0 0 4px;
        }
        .ds-subtitle {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 18px;
            font-weight: 400;
            color: #8f0043;
            margin: 0;
        }

        /* ── FILTER TABS ── */
        .ds-filter-tabs {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .ds-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 32px;
            border-radius: 9999px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 8px 16px rgba(26, 28, 29, 0.06);
        }
        .ds-tab.active {
            background: #ff4d8d;
            color: #fff;
        }
        .ds-tab.inactive {
            background: #fff;
            color: #8f0043;
        }
        .ds-tab.inactive:hover {
            background: #fff0f6;
            transform: translateY(-1px);
        }
        .ds-tab .tab-emoji { font-size: 18px; line-height: 1; }

        /* ── SECTION TITLE ── */
        .ds-section-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #8f0043;
            margin: 0 0 24px;
        }

        /* ── VENUE CARDS ── */
        .venues-list {
            display: flex;
            flex-direction: column;
            gap: 32px;
            margin-bottom: 40px;
        }

        .venue-card {
            background: #fff;
            border-radius: 48px;
            box-shadow: 0 8px 32px rgba(26, 28, 29, 0.06);
            display: flex;
            overflow: hidden;
            height: 320px;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .venue-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(26, 28, 29, 0.12);
        }

        /* Left: Image */
        .venue-image-wrap {
            width: 320px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        .venue-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s ease;
        }
        .venue-card:hover .venue-image-wrap img {
            transform: scale(1.04);
        }
        .venue-image-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 50%);
            pointer-events: none;
        }
        .venue-location-pill {
            position: absolute;
            bottom: 24px;
            left: 24px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-radius: 9999px;
            padding: 4px 12px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: #fff;
            white-space: nowrap;
        }

        /* Right: Content */
        .venue-content {
            flex: 1;
            padding: 32px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
        }
        .venue-content-top { display: flex; flex-direction: column; gap: 8px; }

        .venue-name-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .venue-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #8f0043;
            margin: 0;
            line-height: 1.33;
        }
        .venue-price {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            padding-top: 4px;
        }

        .venue-type {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: #8f0043;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            margin: 0;
        }

        .venue-quote {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 18px;
            font-weight: 300;
            font-style: italic;
            color: #8f0043;
            margin: 4px 0 0;
            line-height: 1.625;
        }

        .venue-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 8px;
        }
        .venue-tag {
            background: rgba(255, 126, 179, 0.1);
            border: 1px solid rgba(255, 126, 179, 0.2);
            border-radius: 9999px;
            padding: 7px 17px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: #a33467;
            white-space: nowrap;
        }

        /* Bottom action row */
        .venue-action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 24px;
            border-top: 1px solid #eeedee;
            margin-top: 4px;
        }
        .venue-actions-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .venue-btn-text {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #8f0043;
            cursor: pointer;
            padding: 0;
            transition: opacity 0.2s;
        }
        .venue-btn-text:hover { opacity: 0.7; }
        .venue-btn-text i { font-size: 16px; }
        .venue-btn-text.saved i { color: #ff4d8d; }

        .btn-suggest {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background: linear-gradient(to right, #ff4d8d, #ff7eb3);
            color: #fff;
            border-radius: 9999px;
            border: none;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(255,77,141,0.2), 0 4px 6px -4px rgba(255,77,141,0.2);
            transition: opacity 0.2s, transform 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-suggest:hover { opacity: 0.9; transform: translateY(-1px); }

        /* ── VIEW MORE ── */
        .view-more-wrap {
            display: flex;
            justify-content: center;
            margin: 8px 0 56px;
        }
        .btn-view-more {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 40px;
            background: linear-gradient(to right, #ff4d8d, #ff7eb3);
            color: #fff;
            border-radius: 9999px;
            border: none;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(255, 77, 141, 0.25);
            transition: opacity 0.2s, transform 0.2s;
        }
        .btn-view-more:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-view-more i { font-size: 16px; }

        /* ── AI RECOMMENDATION SECTION ── */
        .ds-recommendation {
            background: linear-gradient(135deg, #f5e6f0 0%, #ede8f5 100%);
            border-radius: 32px;
            padding: 40px;
            display: flex;
            align-items: flex-start;
            gap: 40px;
        }
        .rec-left { flex: 1; }
        .rec-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: #8f0043;
            margin-bottom: 12px;
        }
        .rec-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
            margin: 0 0 8px;
            line-height: 1.3;
        }
        .rec-desc {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            color: #666;
            margin: 0;
            max-width: 360px;
            line-height: 1.6;
        }

        .rec-venues {
            display: flex;
            gap: 16px;
            flex-shrink: 0;
        }
        .rec-venue-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            width: 200px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .rec-venue-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .rec-venue-img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            display: block;
        }
        .rec-venue-info { padding: 12px 14px 14px; }
        .rec-venue-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 2px;
            line-height: 1.3;
        }
        .rec-venue-meta {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 11px;
            color: #888;
            margin: 0;
        }
        .rec-venue-sync {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: var(--y2k-pink);
            margin: 4px 0 0;
        }

        /* ── SUGGEST MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.open { display: flex; }
        .suggest-modal {
            background: #fff;
            border-radius: 32px;
            padding: 40px;
            max-width: 480px;
            width: 90%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
            animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes modalIn { from { opacity:0; transform: scale(0.9) translateY(20px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .modal-emoji { font-size: 3rem; margin-bottom: 16px; }
        .modal-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 24px; font-weight: 800; color: #8f0043; margin: 0 0 8px; }
        .modal-desc { font-family: 'Be Vietnam Pro', sans-serif; font-size: 15px; color: #666; line-height: 1.6; margin: 0 0 28px; }
        .modal-venue-name { color: #ff4d8d; font-weight: 700; }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-send {
            flex: 1; padding: 14px; background: linear-gradient(135deg, #ff4d8d, #800040);
            color: #fff; border: none; border-radius: 16px; font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 15px; font-weight: 700; cursor: pointer; transition: opacity 0.2s;
        }
        .btn-modal-send:hover { opacity: 0.9; }
        .btn-modal-cancel {
            flex: 1; padding: 14px; background: #f5f5f5; color: #555; border: none;
            border-radius: 16px; font-family: 'Be Vietnam Pro', sans-serif; font-size: 15px; font-weight: 600; cursor: pointer;
        }

        /* Suggest Modal Match List */
        .match-select-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
            max-height: 180px;
            overflow-y: auto;
            padding-right: 8px;
            text-align: left;
        }
        .match-select-list::-webkit-scrollbar { width: 4px; }
        .match-select-list::-webkit-scrollbar-thumb { background: #eee; border-radius: 4px; }
        .match-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border: 2px solid #f0f0f0;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .match-option:hover { border-color: #ff4d8d; background: #fff0f6; }
        .match-option input[type="radio"] {
            accent-color: #ff4d8d;
            transform: scale(1.2);
            margin: 0 4px;
        }
        .match-option img {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
        }
        .match-option span {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 15px; font-weight: 700; color: #333;
        }

        /* ── TOAST ── */
        #ds-toast {
            display: none; position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: #333; color: #fff; padding: 12px 28px; border-radius: 50px;
            font-family: 'Be Vietnam Pro', sans-serif; font-weight: 700; z-index: 99999; font-size: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* Responsive tweaks */
        @media (max-width: 768px) {
            .datespots-page { padding: 24px 20px 60px; }
            .venue-card { flex-direction: column; height: auto; border-radius: 24px; }
            .venue-image-wrap { width: 100%; height: 200px; }
            .ds-recommendation { flex-direction: column; }
            .rec-venues { flex-wrap: wrap; }
        }
    </style>
</head>
<body class="dashboard-body">

<?php include 'header.php'; ?>

<div class="datespots-page">

    <div class="ds-header">
        <h1 class="ds-title">Explore</h1>
        <p class="ds-subtitle">Find the perfect place for your moment</p>
    </div>

    <!-- ── FILTER TABS ── -->
    <div class="ds-filter-tabs">
        <a href="?tab=first_date" class="ds-tab <?= $active_tab === 'first_date' ? 'active' : 'inactive' ?>">
            <span class="tab-emoji">💘</span> First Date
        </a>
        <a href="?tab=romantic" class="ds-tab <?= $active_tab === 'romantic' ? 'active' : 'inactive' ?>">
            <span class="tab-emoji">🌹</span> Romantic
        </a>
        <a href="?tab=deep_talk" class="ds-tab <?= $active_tab === 'deep_talk' ? 'active' : 'inactive' ?>">
            <span class="tab-emoji">☁️</span> Deep Talk
        </a>
        <a href="?tab=saved" class="ds-tab <?= $active_tab === 'saved' ? 'active' : 'inactive' ?>">
            <span class="tab-emoji">💖</span> Saved
        </a>
    </div>

    <!-- ── SECTION TITLE ── -->
    <h2 class="ds-section-title">
        <?php if($active_tab === 'first_date'): ?>💘 First Date Picks
        <?php elseif($active_tab === 'romantic'): ?>🌹 Romantic Picks
        <?php elseif($active_tab === 'saved'): ?>💖 Saved Spots
        <?php else: ?>☁️ Deep Talk Spots<?php endif; ?>
    </h2>

    <!-- ── VENUE CARDS ── -->
    <div class="venues-list" id="venues-list">
        <?php foreach ($filtered_venues as $venue): ?>
        <div class="venue-card" id="venue-<?= $venue['id'] ?>">

            <!-- Image -->
            <a href="date_spot_detail.php?id=<?= $venue['id'] ?>" class="venue-image-wrap" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($venue['image']) ?>" alt="<?= htmlspecialchars($venue['name']) ?>" onerror="this.src='https://images.unsplash.com/photo-1559339352-11d035aa65de?w=400'">
                <div class="venue-image-gradient"></div>
                <div class="venue-location-pill"><?= htmlspecialchars($venue['location']) ?></div>
            </a>

            <!-- Content -->
            <div class="venue-content">
                <div class="venue-content-top">
                    <div class="venue-name-row">
                        <h3 class="venue-name">
                            <a href="date_spot_detail.php?id=<?= $venue['id'] ?>" style="text-decoration:none; color:inherit;"><?= htmlspecialchars($venue['name']) ?></a>
                        </h3>
                        <span class="venue-price" style="color: <?= $venue['price_color'] ?>;"><?= htmlspecialchars($venue['price']) ?></span>
                    </div>
                    <p class="venue-type"><?= htmlspecialchars($venue['type']) ?></p>
                    <p class="venue-quote"><?= htmlspecialchars($venue['quote']) ?></p>
                    <div class="venue-tags">
                        <?php foreach ($venue['tags'] as $tag): ?>
                        <span class="venue-tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="venue-action-row">
                    <div class="venue-actions-left">
                        <button class="venue-btn-text save-btn" id="save-btn-<?= $venue['id'] ?>" onclick="toggleSave(<?= $venue['id'] ?>, this)" title="Save this venue">
                            <i class="fa-regular fa-heart"></i>
                            <span>Save</span>
                        </button>
                        <button class="venue-btn-text" onclick="viewDetails(<?= $venue['id'] ?>)" title="View venue details">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>View Details</span>
                        </button>
                    </div>
                    <button class="btn-suggest" onclick="openSuggestModal(<?= $venue['id'] ?>, '<?= htmlspecialchars(addslashes($venue['name'])) ?>')">
                        Suggest to Match
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($filtered_venues)): ?>
        <div style="text-align:center; padding: 80px 20px; color: #bbb;">
            <div style="font-size:3rem; margin-bottom:16px;">🗺️</div>
            <p style="font-family:'Be Vietnam Pro',sans-serif; font-size:1.1rem; font-weight:600;">No spots found for this category yet.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── VIEW MORE ── -->
    <div class="view-more-wrap">
        <button class="btn-view-more" onclick="showToast('🔜 More venues coming soon!')">
            <i class="fa-solid fa-compass"></i>
            VIEW MORE
        </button>
    </div>

    <!-- ── AI RECOMMENDATION ── -->
    <div class="ds-recommendation">
        <div class="rec-left">
            <p class="rec-badge">✨ Recommended for you</p>
            <h2 class="rec-title">Based on your vibe:<br>Romantic & Calm</h2>
            <p class="rec-desc">We've found two spots that perfectly match your SoulSync profile preferences for intimate settings.</p>
        </div>
        <div class="rec-venues">
            <a href="date_spot_detail.php?id=3" class="rec-venue-card">
                <img src="../image/thealchemist.jpg" alt="The Alchemist" class="rec-venue-img" onerror="this.src='https://images.unsplash.com/photo-1559339352-11d035aa65de?w=400'">
                <div class="rec-venue-info">
                    <p class="rec-venue-name">The Alchemist</p>
                    <p class="rec-venue-meta">
                        Modern Ambiance • Old Quarter<br>
                        <span class="rec-venue-sync">❤️ 93 Likes</span>
                    </p>
                </div>
            </a>
            <a href="date_spot_detail.php?id=1" class="rec-venue-card">
                <img src="../image/lighthouseskybar.jpg" alt="Lighthouse Sky Bar" class="rec-venue-img" onerror="this.src='https://images.unsplash.com/photo-1559339352-11d035aa65de?w=400'">
                <div class="rec-venue-info">
                    <p class="rec-venue-name">Lighthouse Sky Bar</p>
                    <p class="rec-venue-meta">
                        Rooftop Bar • Hoan Kiem<br>
                        <span class="rec-venue-sync">❤️ 90 Likes</span>
                    </p>
                </div>
            </a>
        </div>
    </div>

</div><!-- /datespots-page -->

<!-- ── SUGGEST MODAL ── -->
<div class="modal-overlay" id="suggest-modal" onclick="closeModalOnOverlay(event)">
    <div class="suggest-modal">
        <div class="modal-emoji" style="text-align:center;">💌</div>
        <h3 class="modal-title" style="text-align:center;">Suggest a Date Spot?</h3>
        <p class="modal-desc" style="text-align:center; margin-bottom: 16px;">
            Send <strong class="modal-venue-name" id="modal-venue-name">this venue</strong> to your match. Who would you like to invite?
        </p>
        
        <div class="match-select-list">
            <?php foreach ($rec_matches as $match): 
                $m_avatar = !empty($match['avatar']) ? '../uploads/' . htmlspecialchars($match['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($match['nickname'] ?? $match['full_name']) . '&background=random&color=fff';
                $m_name = htmlspecialchars($match['nickname'] ?? $match['full_name']);
            ?>
            <label class="match-option">
                <input type="radio" name="suggest_match_id" value="<?= $match['user_id'] ?>">
                <img src="<?= $m_avatar ?>" alt="<?= $m_name ?>">
                <span><?= $m_name ?></span>
            </label>
            <?php endforeach; ?>
            <?php if(empty($rec_matches)): ?>
            <p style="text-align:center; color:#888; font-size:14px; margin: 10px 0;">You don't have any matches yet.</p>
            <?php endif; ?>
        </div>

        <div class="modal-actions">
            <button class="btn-modal-send" onclick="sendSuggestion()">
                <i class="fa-solid fa-paper-plane"></i> Send Suggestion
            </button>
            <button class="btn-modal-cancel" onclick="closeSuggestModal()">Cancel</button>
        </div>
    </div>
</div>

<div id="ds-toast"></div>

<script>
let currentVenueId = null;
let savedVenues = new Set(JSON.parse(localStorage.getItem('saved_venues') || '[]'));
const activeTab = "<?= $active_tab ?>";

// Apply saved state on load
document.addEventListener('DOMContentLoaded', () => {
    savedVenues.forEach(id => {
        const btn = document.getElementById(`save-btn-${id}`);
        if (btn) {
            btn.querySelector('i').classList.replace('fa-regular', 'fa-solid');
            btn.querySelector('i').style.color = '#ff4d8d';
            btn.classList.add('saved');
        }
    });

    if (activeTab === 'saved') {
        const list = document.getElementById('venues-list');
        const cards = list.querySelectorAll('.venue-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const id = card.id.replace('venue-', '');
            // Check both string and int representations
            if (!savedVenues.has(parseInt(id, 10)) && !savedVenues.has(id)) {
                card.style.display = 'none';
            } else {
                visibleCount++;
            }
        });
        
        if (visibleCount === 0) {
            const emptyMsg = document.createElement('div');
            emptyMsg.style.textAlign = 'center';
            emptyMsg.style.padding = '80px 20px';
            emptyMsg.style.color = '#888';
            emptyMsg.innerHTML = '<i class="fa-regular fa-heart" style="font-size:4rem; color:#ddd; margin-bottom:20px;"></i><br><p style="font-family:\'Plus Jakarta Sans\', sans-serif; font-size:20px; font-weight:800; color:#333; margin:0 0 8px;">No saved spots yet</p><p style="font-family:\'Be Vietnam Pro\', sans-serif; font-size:15px; margin:0;">Spots you save will appear here for easy access.</p>';
            list.appendChild(emptyMsg);
        }
    }
});

function toggleSave(venueId, btn) {
    const icon = btn.querySelector('i');
    if (savedVenues.has(venueId)) {
        savedVenues.delete(venueId);
        icon.classList.replace('fa-solid', 'fa-regular');
        icon.style.color = '';
        btn.classList.remove('saved');
        showToast('💔 Removed from saved spots');
        
        // Hide card dynamically if we're on the Saved tab
        if (activeTab === 'saved') {
            const card = document.getElementById('venue-' + venueId);
            if (card) card.style.display = 'none';
        }
    } else {
        savedVenues.add(venueId);
        icon.classList.replace('fa-regular', 'fa-solid');
        icon.style.color = '#ff4d8d';
        btn.classList.add('saved');
        icon.style.transform = 'scale(1.4)';
        setTimeout(() => icon.style.transform = '', 300);
        showToast('💗 Saved to your date spots!');
    }
    localStorage.setItem('saved_venues', JSON.stringify([...savedVenues]));
}

function viewDetails(venueId) {
    window.location.href = 'date_spot_detail.php?id=' + venueId;
}

function openSuggestModal(venueId, venueName) {
    currentVenueId = venueId;
    document.getElementById('modal-venue-name').textContent = venueName;
    document.getElementById('suggest-modal').classList.add('open');
}

function closeSuggestModal() {
    document.getElementById('suggest-modal').classList.remove('open');
    currentVenueId = null;
}

function closeModalOnOverlay(e) {
    if (e.target === document.getElementById('suggest-modal')) closeSuggestModal();
}

function sendSuggestion() {
    const selectedMatch = document.querySelector('input[name="suggest_match_id"]:checked');
    if (!selectedMatch) {
        showToast('⚠️ Please select a match first!');
        return;
    }
    const receiverId = selectedMatch.value;
    const venueName = document.getElementById('modal-venue-name').textContent;
    const messageText = "I found a great date spot! Let's go to " + venueName + " 💌";

    fetch('../api/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            receiver_id: receiverId,
            message_text: messageText
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeSuggestModal();
            showToast('💌 Date spot suggestion sent to your match!');
        } else {
            showToast('❌ Failed to send suggestion.');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('❌ Error sending suggestion.');
    });
}

function highlightCard(venueId) {
    const card = document.getElementById(`venue-${venueId}`);
    if (card) {
        card.style.boxShadow = '0 0 0 3px #ff4d8d, 0 16px 48px rgba(255,77,141,0.2)';
        setTimeout(() => card.style.boxShadow = '', 2000);
    }
}

function showToast(msg) {
    const t = document.getElementById('ds-toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 2800);
}
</script>
</body>
</html>
