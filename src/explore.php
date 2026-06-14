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

// Fetch all interests for the filter dropdown
$interests_result = $conn->query("SELECT * FROM interests ORDER BY name ASC");
$all_interests = [];
while ($row = $interests_result->fetch_assoc()) {
    $all_interests[] = $row;
}

// Get filter inputs
$filter_gender = isset($_GET['gender']) && $_GET['gender'] !== '' ? $_GET['gender'] : '';
$filter_min_age = isset($_GET['min_age']) && $_GET['min_age'] !== '' ? (int)$_GET['min_age'] : 18;
$filter_max_age = isset($_GET['max_age']) && $_GET['max_age'] !== '' ? (int)$_GET['max_age'] : 99;
$filter_interest = isset($_GET['interest']) && $_GET['interest'] !== '' ? (int)$_GET['interest'] : 0;

// Build query
$query = "
    SELECT p.*, u.is_pro
    FROM profiles p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id != ? 
    AND p.user_id NOT IN (SELECT liked_user_id FROM likes WHERE user_id = ?)
";
$params = [$user_id, $user_id];
$types = "ii";

if ($filter_gender !== '' && $filter_gender !== 'Anyone') {
    $query .= " AND p.gender = ?";
    $params[] = $filter_gender;
    $types .= "s";
}

// Calculate dates for age filter
$max_dob = date('Y-m-d', strtotime("-$filter_min_age years"));
$min_dob = date('Y-m-d', strtotime("-$filter_max_age years"));

$query .= " AND p.dob <= ? AND p.dob >= ?";
$params[] = $max_dob;
$params[] = $min_dob;
$types .= "ss";

if ($filter_interest > 0) {
    $query .= " AND p.user_id IN (SELECT user_id FROM user_interests WHERE interest_id = ?)";
    $params[] = $filter_interest;
    $types .= "i";
}

$stmt_others = $conn->prepare($query);
$stmt_others->bind_param($types, ...$params);
$stmt_others->execute();
$others_result = $stmt_others->get_result();

$explore_list = [];
while ($other = $others_result->fetch_assoc()) {
    // Determine age
    $other['display_age'] = date_diff(date_create($other['dob']), date_create('today'))->y;
    $other['display_name'] = !empty($other['nickname']) ? $other['nickname'] : $other['full_name'];
    
    // Primary photo
    $other['primary_photo'] = !empty($other['avatar']) ? $other['avatar'] : (!empty($other['photo_1']) ? $other['photo_1'] : 'default');
    
    // Vibe
    $stmt_vibe = $conn->prepare("SELECT i.name FROM user_interests ui JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ? LIMIT 2");
    $stmt_vibe->bind_param("i", $other['user_id']);
    $stmt_vibe->execute();
    $vibe_res = $stmt_vibe->get_result();
    $vibes = [];
    while($v = $vibe_res->fetch_assoc()){ $vibes[] = $v['name']; }
    $other['vibe_title'] = count($vibes) > 0 ? implode(" & ", $vibes) : "Mysterious Vibe";

    $explore_list[] = $other;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Explore - SoulSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
</head>
<body class="dashboard-body">

    <?php include 'header.php'; ?>

    <div class="dash-container">
        <!-- CỘT TRÁI: BỘ LỌC TÌM KIẾM -->
        <aside class="left-sidebar explore-sidebar">
            <div class="section-header" style="margin-bottom: 20px;">
                <h3 style="color:#22d3ee; margin-bottom:0;">Discover Matches</h3>
            </div>
            
            <form method="GET" action="explore.php" class="filter-form">
                <div class="filter-group">
                    <label>I'm looking for:</label>
                    <select name="gender" class="filter-input">
                        <option value="">Anyone</option>
                        <option value="Female" <?= $filter_gender == 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Male" <?= $filter_gender == 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Other" <?= $filter_gender == 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Age Range:</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="number" name="min_age" value="<?= $filter_min_age ?>" class="filter-input" style="width: 50%;">
                        <span style="color:#999;">-</span>
                        <input type="number" name="max_age" value="<?= $filter_max_age ?>" class="filter-input" style="width: 50%;">
                    </div>
                </div>

                <div class="filter-group">
                    <label>Shared Interest:</label>
                    <select name="interest" class="filter-input">
                        <option value="">Any Interest</option>
                        <?php foreach($all_interests as $int): ?>
                            <option value="<?= $int['id'] ?>" <?= $filter_interest == $int['id'] ? 'selected' : '' ?>><?= htmlspecialchars($int['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; border-radius: 8px;">Apply Filters</button>
                <a href="explore.php" class="btn-secondary" style="display: block; text-align: center; width: 100%; margin-top: 10px; border-radius: 8px; text-decoration: none;">Reset Filters</a>
            </form>
        </aside>

        <!-- MAIN FEED: LƯỚI KHÁM PHÁ -->
        <main class="main-feed explore-main" style="padding: 20px;">
            <?php if(empty($explore_list)): ?>
                <div class="empty-state" style="display:flex; justify-content:center; align-items:center; flex-direction:column; height: 100%;">
                    <i class="fa-solid fa-satellite-dish" style="font-size:3rem; color:#ccc; margin-bottom:15px;"></i>
                    <p style="color:#999; font-weight:600; font-size:1.2rem;">No profiles found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="explore-grid">
                    <?php foreach($explore_list as $profile): ?>
                        <div class="explore-card">
                            <div class="card-image-wrapper">
                                <img src="../uploads/<?= htmlspecialchars($profile['primary_photo']) ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random'" alt="Profile Photo">
                            </div>
                            <div class="card-details">
                                <h3 class="card-name"><?= htmlspecialchars($profile['display_name']) ?>, <?= $profile['display_age'] ?></h3>
                                <p class="card-location"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($profile['location'] ?? 'Unknown Location') ?></p>
                                <div class="card-vibe">
                                    <span><?= htmlspecialchars($profile['vibe_title']) ?></span>
                                </div>
                                <a href="view_profile.php?id=<?= $profile['user_id'] ?>" class="btn-view-profile">View Profile</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>
