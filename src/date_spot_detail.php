<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];
$spot_id = (int)($_GET['id'] ?? 0);

if ($spot_id <= 0) {
    header("Location: explore.php");
    exit();
}

// Fetch the spot
$stmt = $conn->prepare("SELECT * FROM date_spots WHERE id = ?");
$stmt->bind_param("i", $spot_id);
$stmt->execute();
$spot = $stmt->get_result()->fetch_assoc();

if (!$spot) {
    header("Location: explore.php");
    exit();
}

// Get current user info
$stmt2 = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$current_user = $stmt2->get_result()->fetch_assoc();
$is_pro = isset($current_user['is_pro']) ? $current_user['is_pro'] : false;

// Get all matches for the suggest modal
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
$all_matches = $stmt_matches->get_result()->fetch_all(MYSQLI_ASSOC);

// Map IDs to venue images & rich data (supplement DB data with curated content)
$venue_data = [
    1 => [
        'name'     => 'Lighthouse Sky Bar',
        'description' => 'A premier rooftop bar offering a stunning panoramic view of Hanoi\'s Old Quarter and Chuong Duong Bridge. Enjoy the sunset with premium cocktails.',
        'image'    => '../image/venue_1.png',
        'type'     => 'ROOFTOP BAR',
        'price'    => '250k – 500k',
        'price_color' => '#8d7076',
        'quote'    => '"View Old Quarter & Chuong Duong Bridge"',
        'location' => 'Hoan Kiem, Hanoi',
        'tags'     => ['💗 Perfect for romantic nights', '☕ Great for deep conversations', '🎲 Easy first date spot'],
        'open_hours' => '5:00 PM – 1:00 AM',
        'highlights' => [
            'Panoramic 360° rooftop view of Hanoi\'s Old Quarter',
            'Premium cocktail menu crafted by award-winning mixologists',
            'Sunset hour special: 2-for-1 cocktails from 5–7 PM',
            'Reservation recommended for weekend evenings',
        ],
        'map_url'  => 'https://maps.google.com/?q=Lighthouse+Sky+Bar+Hanoi',
        'tab'      => 'first_date',
        'vibe'     => 'Romantic',
    ],
    2 => [
        'name'     => 'Sky Walk Lotte',
        'description' => 'Located on the 65th floor of the Lotte Center, this observation deck features glass-floor panels and breathtaking 360-degree views of the city. A perfect spot for golden hour photography and exciting moments.',
        'image'    => '../image/venue_2.png',
        'type'     => 'CITY VIEW EXPERIENCE',
        'price'    => '150k – 300k',
        'price_color' => '#ff4d8d',
        'quote'    => '"See Hanoi from above and share the moment"',
        'location' => 'Lieu Giai, Ba Dinh, Hanoi',
        'tags'     => ['🏙️ 65th floor view', '📸 Instagram-worthy', '💫 Exciting first date'],
        'open_hours' => '9:00 AM – 10:00 PM',
        'highlights' => [
            'Located on the 65th floor of Lotte Tower – Hanoi\'s tallest skywalk',
            'Glass-floor panels for a thrilling aerial perspective',
            'Perfect for golden-hour photography',
            'Easily accessible; family-friendly environment',
        ],
        'map_url'  => 'https://maps.google.com/?q=Sky+Walk+Lotte+Hanoi',
        'tab'      => 'first_date',
        'vibe'     => 'Adventurous',
    ],
    3 => [
        'name'     => 'The Alchemist',
        'description' => 'A hidden speakeasy tucked behind a lush garden entrance. It features low-lighting, intimate booths, and craft cocktails made with locally-sourced Vietnamese herbs—ideal for deep, honest conversations.',
        'image'    => '../image/venue_3.png',
        'type'     => 'COCKTAIL BAR & SPEAKEASY',
        'price'    => '150k – 300k',
        'price_color' => '#ff4d8d',
        'quote'    => '"Signature cocktails in a cozy, hidden atmosphere"',
        'location' => 'West Lake, Tay Ho, Hanoi',
        'tags'     => ['🍸 Experimental drinks', '🤫 Hidden gem vibe', '✨ Aesthetic interior'],
        'open_hours' => '6:00 PM – 2:00 AM',
        'highlights' => [
            'A hidden speakeasy tucked behind a lush garden entrance',
            'Craft cocktails with locally-sourced Vietnamese herbs',
            'Low-lighting and intimate booths ideal for deep conversations',
            'Live acoustic music on Friday & Saturday nights',
        ],
        'map_url'  => 'https://maps.google.com/?q=The+Alchemist+Bar+Hanoi',
        'tab'      => 'romantic',
        'vibe'     => 'Intimate',
    ],
    4 => [
        'name'     => 'Complex 01',
        'description' => 'A vibrant creative space hosting monthly themed art workshops like pottery and painting. It offers an open courtyard with interactive installations, making it the perfect icebreaker for a first date.',
        'image'    => '../image/venue_4.png',
        'type'     => 'CREATIVE SPACE',
        'price'    => '150k – 300k',
        'price_color' => '#ff4d8d',
        'quote'    => '"Create something together, break the ice naturally"',
        'location' => 'Tay Son, Dong Da, Hanoi',
        'tags'     => ['🎨 Art workshops', '🫶 Interactive activities', '✨ Unique first date'],
        'open_hours' => '10:00 AM – 10:00 PM',
        'highlights' => [
            'Monthly themed art workshops: pottery, painting, ceramics & more',
            'Open courtyard with creative installations and live art',
            'Perfect icebreaker for first dates – create something together',
            'Affordable packages starting from 150k/person',
        ],
        'map_url'  => 'https://maps.google.com/?q=Complex+01+Hanoi',
        'tab'      => 'first_date',
        'vibe'     => 'Fun & Creative',
    ],
];

$vd = $venue_data[$spot_id] ?? [
    'image'      => '../image/venue_1.png',
    'type'       => 'VENUE',
    'price'      => 'See website',
    'price_color'=> '#ff4d8d',
    'quote'      => '"A wonderful place to connect"',
    'location'   => 'Hanoi',
    'tags'       => [],
    'open_hours' => 'Check venue for hours',
    'highlights' => [],
    'map_url'    => 'https://maps.google.com/?q=Hanoi',
    'tab'        => 'first_date',
    'vibe'       => 'Romantic',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vd['name'] ?? $spot['name']) ?> – SoulSync Date Spots</title>
    <meta name="description" content="<?= htmlspecialchars($vd['description'] ?? $spot['description'] ?? '') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <style>
        body.dashboard-body { background: #faf9fa; overflow-y: auto; height: auto; }

        /* ── HERO ── */
        .ds-hero {
            position: relative;
            width: 100%;
            height: 480px;
            overflow: hidden;
        }
        .ds-hero-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transform-origin: center;
            transition: transform 6s ease;
        }
        .ds-hero-img:hover { transform: scale(1.04); }
        .ds-hero-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0.1) 60%, transparent 100%);
            pointer-events: none;
        }

        /* Back button */
        .ds-back-btn {
            position: absolute;
            top: 24px;
            left: 32px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
            border-radius: 50px;
            padding: 8px 20px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
            z-index: 10;
        }
        .ds-back-btn:hover { background: rgba(255,255,255,0.28); }

        /* Hero bottom info */
        .ds-hero-info {
            position: absolute;
            bottom: 32px;
            left: 40px;
            right: 40px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
        }
        .ds-hero-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 40px;
            font-weight: 800;
            color: #fff;
            margin: 0 0 6px;
            line-height: 1.2;
            text-shadow: 0 2px 12px rgba(0,0,0,0.4);
        }
        .ds-hero-type {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 1.4px;
            margin: 0;
        }
        .ds-hero-sync-badge {
            flex-shrink: 0;
            background: linear-gradient(135deg, #ff4d8d, #ff7eb3);
            border-radius: 50px;
            padding: 10px 22px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            white-space: nowrap;
            box-shadow: 0 8px 20px rgba(255,77,141,0.35);
        }

        /* ── CONTENT AREA ── */
        .ds-content {
            max-width: 960px;
            margin: 0 auto;
            padding: 40px 40px 80px;
        }

        /* ── META ROW ── */
        .ds-meta-row {
            display: flex;
            align-items: center;
            gap: 28px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .ds-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: #666;
        }
        .ds-meta-item i { color: var(--y2k-pink); font-size: 15px; }
        .ds-meta-item a { color: #666; text-decoration: none; }
        .ds-meta-item a:hover { color: var(--y2k-pink); }

        /* ── QUOTE ── */
        .ds-quote {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 20px;
            font-weight: 300;
            font-style: italic;
            color: #8f0043;
            border-left: 4px solid #ff4d8d;
            padding: 4px 0 4px 20px;
            margin: 0 0 28px;
            line-height: 1.6;
        }

        /* ── TAGS ── */
        .ds-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 36px;
        }
        .ds-tag {
            background: rgba(255,126,179,0.1);
            border: 1px solid rgba(255,126,179,0.25);
            border-radius: 50px;
            padding: 8px 18px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #a33467;
        }

        /* ── SECTION TITLE ── */
        .ds-section-heading {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 16px;
        }

        /* ── HIGHLIGHTS ── */
        .ds-highlights {
            background: #fff;
            border-radius: 24px;
            padding: 28px 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 32px;
        }
        .ds-highlight-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .ds-highlight-item:last-child { border-bottom: none; }
        .ds-highlight-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255,77,141,0.12), rgba(255,126,179,0.12));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .ds-highlight-icon i { color: #ff4d8d; font-size: 13px; }
        .ds-highlight-text {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            color: #444;
            line-height: 1.6;
            margin: 0;
        }

        /* ── VIBE CARD ── */
        .ds-vibe-card {
            background: linear-gradient(135deg, #fff0f6, #f5e8f8);
            border-radius: 24px;
            padding: 24px 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 32px;
        }
        .ds-vibe-emoji { font-size: 3rem; flex-shrink: 0; }
        .ds-vibe-label {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 0 0 4px;
        }
        .ds-vibe-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #8f0043;
            margin: 0 0 4px;
        }
        .ds-vibe-desc {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 13px;
            color: #888;
            margin: 0;
        }

        /* ── ACTION BUTTONS ── */
        .ds-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
        .btn-ds-primary {
            flex: 1;
            min-width: 180px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 24px;
            background: linear-gradient(135deg, #ff4d8d, #ff7eb3);
            color: #fff;
            border-radius: 16px;
            border: none;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(255,77,141,0.25);
            transition: opacity 0.2s, transform 0.2s;
            text-decoration: none;
        }
        .btn-ds-primary:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-ds-outline {
            flex: 1;
            min-width: 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 24px;
            background: #fff;
            color: #8f0043;
            border: 2px solid #ff4d8d;
            border-radius: 16px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, transform 0.2s;
        }
        .btn-ds-outline:hover { background: #fff0f6; transform: translateY(-2px); }
        .btn-ds-outline.saved {
            background: #fff0f6;
            color: #ff4d8d;
        }
        .btn-ds-outline.saved i { color: #ff4d8d; }

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
            max-width: 440px;
            width: 90%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
            animation: modalIn 0.3s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.88) translateY(24px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .modal-emoji { font-size: 3rem; margin-bottom: 14px; }
        .modal-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #8f0043;
            margin: 0 0 8px;
        }
        .modal-desc {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            color: #666;
            line-height: 1.65;
            margin: 0 0 24px;
        }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-send {
            flex: 1;
            padding: 13px;
            background: linear-gradient(135deg, #ff4d8d, #800040);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-modal-send:hover { opacity: 0.9; }
        .btn-modal-cancel {
            flex: 1;
            padding: 13px;
            background: #f5f5f5;
            color: #555;
            border: none;
            border-radius: 14px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
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
            display: none;
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a2e;
            color: #fff;
            padding: 12px 28px;
            border-radius: 50px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-weight: 700;
            font-size: 14px;
            z-index: 99999;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            white-space: nowrap;
        }

        /* ── OTHER SPOTS ── */
        .other-spots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 12px;
        }
        .other-spot-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .other-spot-card:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(0,0,0,0.1); }
        .other-spot-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }
        .other-spot-card-info {
            padding: 12px 14px 14px;
        }
        .other-spot-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 3px;
        }
        .other-spot-sync {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: var(--y2k-pink);
        }
    </style>
</head>
<body class="dashboard-body">

<?php include 'header.php'; ?>

<!-- ── HERO ── -->
<div class="ds-hero">
    <img src="<?= htmlspecialchars($vd['image']) ?>"
         alt="<?= htmlspecialchars($vd['name'] ?? $spot['name']) ?>"
         class="ds-hero-img"
         onerror="this.src='../image/venue_1.png'">
    <div class="ds-hero-gradient"></div>

    <!-- Back button -->
    <a href="javascript:history.back()" class="ds-back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back
    </a>

    <!-- Hero bottom info overlay -->
    <div class="ds-hero-info">
        <div>
            <h1 class="ds-hero-name"><?= htmlspecialchars($vd['name'] ?? $spot['name']) ?></h1>
            <p class="ds-hero-type"><?= htmlspecialchars($vd['type']) ?></p>
        </div>
        <div class="ds-hero-sync-badge">
            ❤️ <?= $spot['sync_rate'] ?>% SYNC
        </div>
    </div>
</div>

<!-- ── CONTENT ── -->
<div class="ds-content">

    <!-- Meta row -->
    <div class="ds-meta-row">
        <div class="ds-meta-item">
            <i class="fa-solid fa-location-dot"></i>
            <a href="<?= htmlspecialchars($vd['map_url']) ?>" target="_blank"><?= htmlspecialchars($vd['location']) ?></a>
        </div>
        <div class="ds-meta-item">
            <i class="fa-regular fa-clock"></i>
            <?= htmlspecialchars($vd['open_hours']) ?>
        </div>
        <div class="ds-meta-item" style="color:<?= $vd['price_color'] ?>; font-weight:700;">
            <i class="fa-solid fa-tag" style="color:<?= $vd['price_color'] ?>;"></i>
            <?= htmlspecialchars($vd['price']) ?>
        </div>
    </div>

    <!-- Quote -->
    <p class="ds-quote"><?= htmlspecialchars($vd['quote']) ?></p>

    <!-- Tags -->
    <?php if (!empty($vd['tags'])): ?>
    <div class="ds-tags">
        <?php foreach ($vd['tags'] as $tag): ?>
        <span class="ds-tag"><?= htmlspecialchars($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Vibe card -->
    <?php
    $vibe_emojis = ['Romantic'=>'🌹','Adventurous'=>'⛰️','Intimate'=>'🕯️','Fun & Creative'=>'🎨'];
    $vibe_descs  = [
        'Romantic'       => 'Ideal for couples who want a meaningful, unforgettable evening.',
        'Adventurous'    => 'Perfect for thrill-seekers who love sharing exciting new experiences.',
        'Intimate'       => 'A quiet, cozy setting for honest conversations and real connection.',
        'Fun & Creative' => 'Make something together and break the ice without awkward silences.',
    ];
    $vibe_emoji = $vibe_emojis[$vd['vibe']] ?? '✨';
    $vibe_desc  = $vibe_descs[$vd['vibe']]  ?? 'A wonderful setting for two people to connect.';
    ?>
    <div class="ds-vibe-card">
        <div class="ds-vibe-emoji"><?= $vibe_emoji ?></div>
        <div>
            <p class="ds-vibe-label">Vibe</p>
            <p class="ds-vibe-name"><?= htmlspecialchars($vd['vibe']) ?></p>
            <p class="ds-vibe-desc"><?= htmlspecialchars($vibe_desc) ?></p>
        </div>
    </div>

    <!-- Highlights -->
    <?php if (!empty($vd['highlights'])): ?>
    <div class="ds-highlights">
        <h2 class="ds-section-heading">✨ Why You'll Love It</h2>
        <?php foreach ($vd['highlights'] as $hl): ?>
        <div class="ds-highlight-item">
            <div class="ds-highlight-icon"><i class="fa-solid fa-check"></i></div>
            <p class="ds-highlight-text"><?= htmlspecialchars($hl) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Description -->
    <?php 
    $desc_to_show = $vd['description'] ?? $spot['description'] ?? '';
    if (!empty($desc_to_show)): ?>
    <div style="margin-bottom:32px; background:#fff; border-radius:24px; padding:28px 32px; box-shadow:0 4px 20px rgba(0,0,0,0.05);">
        <h2 class="ds-section-heading">📍 About this Spot</h2>
        <p style="font-family:'Be Vietnam Pro',sans-serif; font-size:15px; color:#555; line-height:1.7; margin:0;">
            <?= nl2br(htmlspecialchars($desc_to_show)) ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="ds-actions" style="margin-bottom:48px;">
        <button class="btn-ds-primary" onclick="openSuggestModal()">
            <i class="fa-solid fa-paper-plane"></i> Suggest to Match
        </button>
        <button class="btn-ds-outline" id="save-btn" onclick="toggleSave(this)">
            <i class="fa-regular fa-heart" id="save-icon"></i>
            <span id="save-label">Save Spot</span>
        </button>
        <a href="<?= htmlspecialchars($vd['map_url']) ?>" target="_blank" class="btn-ds-outline">
            <i class="fa-solid fa-map-location-dot"></i> View on Map
        </a>
    </div>

    <!-- Other Spots -->
    <h2 class="ds-section-heading">🗺️ Other Spots You Might Like</h2>
    <?php
    $all_venues = [
        1 => ['name' => 'Lighthouse Sky Bar',    'image' => '../image/venue_1.png', 'sync' => 96],
        2 => ['name' => 'Sky Walk Lotte',         'image' => '../image/venue_2.png', 'sync' => 94],
        3 => ['name' => 'The Alchemist',          'image' => '../image/venue_3.png', 'sync' => 92],
        4 => ['name' => 'Complex 01',             'image' => '../image/venue_4.png', 'sync' => 89],
    ];
    ?>
    <div class="other-spots-grid">
        <?php foreach ($all_venues as $vid => $v):
            if ($vid === $spot_id) continue; // skip current
        ?>
        <a href="date_spot_detail.php?id=<?= $vid ?>" class="other-spot-card">
            <img src="<?= htmlspecialchars($v['image']) ?>" alt="<?= htmlspecialchars($v['name']) ?>" onerror="this.src='../image/venue_1.png'">
            <div class="other-spot-card-info">
                <p class="other-spot-name"><?= htmlspecialchars($v['name']) ?></p>
                <p class="other-spot-sync">❤️ <?= $v['sync'] ?>% SYNC</p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

</div>

<!-- ── SUGGEST MODAL ── -->
<div class="modal-overlay" id="suggest-modal" onclick="closeModalOnOverlay(event)">
    <div class="suggest-modal">
        <div class="modal-emoji">💌</div>
        <h3 class="modal-title">Suggest This Spot?</h3>
        <p class="modal-desc" style="margin-bottom: 16px;">
            Send <strong><?= htmlspecialchars($vd['name'] ?? $spot['name']) ?></strong> to your match as a date idea.
            Who would you like to invite?
        </p>

        <div class="match-select-list">
            <?php foreach ($all_matches as $match): 
                $m_avatar = !empty($match['avatar']) ? '../uploads/' . htmlspecialchars($match['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($match['nickname'] ?? $match['full_name']) . '&background=random&color=fff';
                $m_name = htmlspecialchars($match['nickname'] ?? $match['full_name']);
            ?>
            <label class="match-option">
                <input type="radio" name="suggest_match_id" value="<?= $match['user_id'] ?>">
                <img src="<?= $m_avatar ?>" alt="<?= $m_name ?>">
                <span><?= $m_name ?></span>
            </label>
            <?php endforeach; ?>
            <?php if(empty($all_matches)): ?>
            <p style="text-align:center; color:#888; font-size:14px; margin: 10px 0;">You don't have any matches yet.</p>
            <?php endif; ?>
        </div>

        <div class="modal-actions">
            <button class="btn-modal-send" onclick="sendSuggestion()">
                <i class="fa-solid fa-paper-plane"></i> Send Suggestion
            </button>
            <button class="btn-modal-cancel" onclick="closeSuggestModal()">Not Now</button>
        </div>
    </div>
</div>

<div id="ds-toast"></div>

<script>
const SPOT_ID = <?= $spot_id ?>;
const KEY = `saved_spot_${SPOT_ID}`;
let isSaved = localStorage.getItem(KEY) === '1';

// Apply saved state on load
if (isSaved) applySaved();

function toggleSave(btn) {
    isSaved = !isSaved;
    localStorage.setItem(KEY, isSaved ? '1' : '0');
    if (isSaved) {
        applySaved();
        showToast('💗 Saved to your date spots!');
    } else {
        removeSaved();
        showToast('💔 Removed from saved spots');
    }
}
function applySaved() {
    document.getElementById('save-icon').className = 'fa-solid fa-heart';
    document.getElementById('save-icon').style.color = '#ff4d8d';
    document.getElementById('save-label').textContent = 'Saved ✓';
    document.getElementById('save-btn').classList.add('saved');
}
function removeSaved() {
    document.getElementById('save-icon').className = 'fa-regular fa-heart';
    document.getElementById('save-icon').style.color = '';
    document.getElementById('save-label').textContent = 'Save Spot';
    document.getElementById('save-btn').classList.remove('saved');
}

function openSuggestModal() {
    document.getElementById('suggest-modal').classList.add('open');
}
function closeSuggestModal() {
    document.getElementById('suggest-modal').classList.remove('open');
}
function closeModalOnOverlay(e) {
    if (e.target === document.getElementById('suggest-modal')) closeSuggestModal();
}
function sendSuggestion() {
    closeSuggestModal();
    showToast('💌 Date spot suggestion sent to your match!');
}

function showToast(msg) {
    const t = document.getElementById('ds-toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(t._t);
    t._t = setTimeout(() => t.style.display = 'none', 2800);
}
</script>
</body>
</html>
