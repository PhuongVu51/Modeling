<?php
// Kiểm tra dữ liệu người dùng hiện tại (lấy từ home.php)
$display_name = htmlspecialchars($current_user['nickname'] ?? $current_user['full_name'] ?? 'User');

// Đường dẫn ảnh đại diện
if (!empty($current_user['avatar'])) {
    $display_avatar = '../uploads/' . htmlspecialchars($current_user['avatar']);
} else {
    // Nếu không có ảnh, dùng avatar chữ cái đầu mặc định
    $display_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($display_name) . '&background=e83e8c&color=fff';
}
?>
<nav class="dash-nav">
    <div class="dash-logo">
        <img src="../image/Pink and Neon Y2K Typography Beauty Store Logo (3).png" alt="SoulSync" style="height: 35px; object-fit: contain;">
    </div>
    
    <div class="dash-nav-links">
        <a href="home.php" class="<?= basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : '' ?>">HOME</a>
        <a href="#">MATCHES</a>
        <a href="#">MESSAGES</a>
        <a href="#">EXPLORE</a>
    </div>

    <div class="dash-nav-profile">
        <button class="icon-btn" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
        
        <div class="profile-info">
            <div class="profile-text">
                <span class="name"><?= $display_name ?></span>
                <span class="status">PRO MEMBER</span>
            </div>
            <img src="<?= $display_avatar ?>" alt="Avatar" class="profile-avatar" onerror="this.src='https://ui-avatars.com/api/?name=U'">
        </div>
    </div>
</nav>