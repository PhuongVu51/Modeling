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
        <a href="messages.php" class="<?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>">MESSAGES</a>
        <a href="#">EXPLORE</a>
        
        <?php if (isset($is_pro) && $is_pro): ?>
            <a href="likes.php" style="color: #ff4b82; font-weight: 800;">
                <i class="fa-solid fa-heart-circle-check"></i> LIKES
            </a>
        <?php endif; ?>
    </div>

    <div class="dash-nav-profile">
        
        <div class="notification-wrapper" style="position: relative; display: inline-flex; align-items: center; justify-content: center; margin-right: 15px;">
            <button id="notif-btn" onclick="toggleNotif()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; position:relative; display:flex; align-items:center; padding: 0;">
                <i class="fa-solid fa-bell" style="color: #ff4b82;"></i>
                <span id="notif-badge" style="display:none; position:absolute; top:-5px; right:-5px; background:#ff4b82; color:white; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">0</span>
            </button>

            <div id="notif-dropdown" style="display:none; position:absolute; right:0; top:40px; width:320px; background:white; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); padding:15px; z-index:9999; text-align: left;">
                <h4 style="margin:0 0 10px 0; border-bottom:1px solid #eee; padding-bottom:10px; color:#5d1029; font-size: 1.1rem;">Notifications</h4>
                <div id="notif-list" style="max-height: 300px; overflow-y: auto;">
                </div>
            </div>
        </div>
        
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
    
    /* CSS CHO CHUÔNG THÔNG BÁO */
    .notif-item { background: #fff5f8; padding: 10px; border-radius: 10px; margin-bottom: 10px; font-size: 0.9rem; }
    .notif-actions { display: flex; gap: 10px; margin-top: 10px; }
    .btn-accept { background: #ff4b82; color: white; border: none; padding: 5px 15px; border-radius: 10px; cursor: pointer; font-weight: bold; flex: 1; transition: 0.2s; }
    .btn-accept:hover { opacity: 0.8; }
    .btn-reject { background: #e0e0e0; color: #555; border: none; padding: 5px 15px; border-radius: 10px; cursor: pointer; font-weight: bold; flex: 1; transition: 0.2s; }
    .btn-reject:hover { background: #ccc; }
</style>

<script>
    function toggleNotif() {
        const dropdown = document.getElementById('notif-dropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }

    async function loadNotifications() {
        try {
            const res = await fetch('../api/get_notifications.php');
            const data = await res.json();
            
            const badge = document.getElementById('notif-badge');
            const list = document.getElementById('notif-list');

            if (data.length > 0) {
                badge.style.display = 'block';
                badge.innerText = data.length;
                list.innerHTML = ''; 

                data.forEach(notif => {
                    list.innerHTML += `
                        <div class="notif-item" id="notif-${notif.id}">
                            <div style="color: #333; line-height: 1.4;">${notif.message}</div>
                            ${notif.type === 'double_date_invite' ? `
                                <div class="notif-actions">
                                    <button class="btn-accept" onclick="respondDoubleDate(${notif.id}, 'accept')">Accept</button>
                                    <button class="btn-reject" onclick="respondDoubleDate(${notif.id}, 'reject')">Reject</button>
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
            } else {
                badge.style.display = 'none';
                list.innerHTML = '<p style="color:#999; font-size:0.9rem; text-align:center;">No new notifications.</p>';
            }
        } catch (e) { console.error("Lỗi tải thông báo:", e); }
    }

    async function respondDoubleDate(notifId, action) {
        try {
            const res = await fetch('../api/respond_double_date.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ notification_id: notifId, action: action })
            });
            const data = await res.json();

            if (data.success) {
                const notifEl = document.getElementById(`notif-${notifId}`);
                if (notifEl) notifEl.remove();
                loadNotifications(); 
                if(action === 'accept') {
                    window.location.href = `messages.php?mode=double_date_waiting&id=${data.double_date_id}`;
                }
            }
        } catch (e) { console.error("Lỗi respond:", e); }
    }

    // Chạy load thông báo ngay khi vừa tải trang web xong
    document.addEventListener("DOMContentLoaded", function() {
        loadNotifications();
        setInterval(loadNotifications, 5000); 
    });
</script>