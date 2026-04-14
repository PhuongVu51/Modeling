<?php
$display_name = htmlspecialchars($current_user['nickname'] ?? $current_user['full_name'] ?? 'User');

if (!empty($current_user['avatar'])) {
    $display_avatar = '../uploads/' . htmlspecialchars($current_user['avatar']);
} else {
    $display_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($display_name) . '&background=e83e8c&color=fff';
}
?>
<nav class="dash-nav">
    <div class="dash-logo">
        <img src="../image/Pink and Neon Y2K Typography Beauty Store Logo (3).png" alt="SoulSync" style="height: 35px; object-fit: contain;">
    </div>
    
    <div class="dash-nav-links">
        <a href="home.php" class="<?= basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : '' ?>">HOME</a>
        <a href="matches.php" class="<?= basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : '' ?>">MATCHES</a>
        <a href="#">MESSAGES</a>
        <a href="#">EXPLORE</a>
        
        <?php if (isset($is_pro) && $is_pro): ?>
            <a href="likes.php" style="color: #ff4b82; font-weight: 800;">
                <i class="fa-solid fa-heart-circle-check"></i> LIKES
            </a>
        <?php endif; ?>
    </div>

    <div class="dash-nav-profile">
        <button class="icon-btn" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
        
        <div class="profile-info">
            <div class="profile-text">
                <span class="name"><?= $display_name ?></span>
                <?php if (isset($is_pro) && $is_pro): ?>
                    <span class="status" style="color: #ff4b82; font-weight: bold;">PRO MEMBER</span>
                <?php else: ?>
                    <span class="status" style="color: #999;">BASIC MEMBER</span>
                <?php endif; ?>
            </div>
            <a href="profile.php">
                <img src="<?= $display_avatar ?>" alt="Avatar" class="profile-avatar" onerror="this.src='https://ui-avatars.com/api/?name=U'">
            </a>
        </div>
    </div>
</nav>

<style>
    .dash-nav { display: flex; justify-content: space-between; align-items: center; padding: 0 35px; height: 80px; background: #fff; border-bottom: 1px solid #f0f0f0; position: sticky; top: 0; z-index: 1000; }
    .dash-nav-links { display: flex; gap: 35px; }
    .dash-nav-links a { text-decoration: none; color: #888; font-weight: 700; font-size: 0.85rem; transition: 0.3s; text-transform: uppercase; }
    .dash-nav-links a.active, .dash-nav-links a:hover { color: #e83e8c; }
    .dash-nav-profile { display: flex; align-items: center; gap: 20px; }
    .profile-info { display: flex; align-items: center; gap: 12px; }
    .profile-text { text-align: right; display: flex; flex-direction: column; }
    .profile-text .name { font-weight: 800; font-size: 0.9rem; color: #333; }
    .profile-text .status { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; }
    .profile-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #fff0f6; cursor: pointer; transition: 0.3s; }
    .profile-avatar:hover { border-color: #e83e8c; }
    .icon-btn { background: none; border: none; font-size: 1.3rem; color: #555; cursor: pointer; display: flex; align-items: center; }
    .icon-btn:hover { color: #e83e8c; }
</style>