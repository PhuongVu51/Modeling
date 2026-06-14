<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once '../api/db_connect.php';

$user_id = $_SESSION['user_id'];

function handleUpload($input_name, $current_val) {
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
        $filename = time() . '_' . basename($_FILES[$input_name]['name']);
        if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $upload_dir . $filename)) return $filename;
    }
    return $current_val; 
}

// XỬ LÝ LƯU DỮ LIỆU
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CHỐNG LỖI BAY MÀU DATA DO UP ẢNH QUÁ NẶNG
    if (empty($_POST)) {
        echo "<script>alert('LỖI: Ảnh bạn chọn quá nặng vượt giới hạn máy chủ! Hệ thống đã chặn để bảo vệ dữ liệu. Vui lòng chọn ảnh nhẹ hơn.'); window.location.href='edit_profile.php';</script>";
        exit();
    }

    $nick = trim($_POST['nickname'] ?? '');
    $loc = $_POST['location'] ?? ''; 
    $occ = $_POST['occupation'] ?? ''; 
    $comp = $_POST['company'] ?? '';
    $bio = $_POST['bio'] ?? ''; 
    $height = $_POST['height'] ?? ''; 
    $edu = $_POST['education'] ?? '';
    $drink = $_POST['drinking'] ?? ''; 
    $pets = $_POST['pets'] ?? '';

    $p = [];
    for($i=1;$i<=6;$i++) { $p[$i] = handleUpload("photo_$i", $_POST["old_photo_$i"] ?? ''); }

    $sql = "UPDATE profiles SET nickname=?, location=?, occupation=?, company=?, bio=?, height=?, education=?, drinking=?, pets=?, 
            photo_1=?, photo_2=?, photo_3=?, photo_4=?, photo_5=?, photo_6=? WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssssssi", $nick, $loc, $occ, $comp, $bio, $height, $edu, $drink, $pets, $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $user_id);
    $stmt->execute();

    $conn->query("DELETE FROM user_interests WHERE user_id = $user_id");
    if (!empty($_POST['interests'])) {
        $stmt_int = $conn->prepare("INSERT INTO user_interests (user_id, interest_id) VALUES (?, ?)");
        foreach ($_POST['interests'] as $iid) { $stmt_int->bind_param("ii", $user_id, $iid); $stmt_int->execute(); }
    }
    header("Location: profile.php"); exit();
}

$stmt = $conn->prepare("SELECT * FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

$all_interests = $conn->query("SELECT * FROM interests")->fetch_all(MYSQLI_ASSOC);
$stmt_my_int = $conn->prepare("SELECT interest_id FROM user_interests WHERE user_id = ?");
$stmt_my_int->bind_param("i", $user_id); $stmt_my_int->execute();
$my_interests_ids = array_column($stmt_my_int->get_result()->fetch_all(MYSQLI_ASSOC), 'interest_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - SoulSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
</head>
<body class="dashboard-body">
    <?php include 'header.php'; ?>
    <main class="profile-wrapper">
        <div class="edit-header-bar">
            <h1 style="font-size:1.6rem; color:#5d1029;"><a href="profile.php" style="color:#e83e8c; text-decoration:none;"><i class="fa-solid fa-angle-left"></i></a> Edit Profile</h1>
            <a href="preview.php" class="btn-outline-preview">Preview</a>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="edit-layout">
                <div>
                    <div class="edit-panel">
                        <h3><i class="fa-regular fa-user"></i> Basic Info</h3>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Real Name (For verification only)</label>
                            <input type="text" class="edit-input" value="<?= htmlspecialchars($current_user['full_name'] ?? '') ?>" disabled style="background:#f5f5f5; cursor:not-allowed;">
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom: 15px;">
                            <div class="form-group">
                                <label style="color:var(--y2k-pink);">Nickname (Display Name)</label>
                                <input type="text" name="nickname" class="edit-input" placeholder="Your Nickname" value="<?= htmlspecialchars($current_user['nickname'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Birthday</label>
                                <input type="text" class="edit-input" value="<?= htmlspecialchars($current_user['dob'] ?? '') ?>" disabled style="background:#f5f5f5; cursor:not-allowed;">
                            </div>
                        </div>

                        <div class="form-group"><label>Location</label><input type="text" name="location" class="edit-input" value="<?= htmlspecialchars($current_user['location'] ?? '') ?>"></div>
                        <div class="form-group"><label>Occupation</label><input type="text" name="occupation" class="edit-input" value="<?= htmlspecialchars($current_user['occupation'] ?? '') ?>"></div>
                        <div class="form-group"><label>Company</label><input type="text" name="company" class="edit-input" value="<?= htmlspecialchars($current_user['company'] ?? '') ?>"></div>
                    </div>
                    <div class="edit-panel"><h3><i class="fa-solid fa-pen-nib"></i> About Me</h3><textarea name="bio" class="edit-input" style="height: 120px; resize:none;"><?= htmlspecialchars($current_user['bio'] ?? '') ?></textarea></div>
                </div>
                <div>
                    <div class="edit-photo-grid" style="margin-bottom:30px;">
                        <?php for ($i = 1; $i <= 6; $i++): $pk = "photo_$i"; ?>
                            <div class="photo-slot">
                                <input type="hidden" name="old_<?= $pk ?>" value="<?= htmlspecialchars($current_user[$pk] ?? '') ?>">
                                <?php if(!empty($current_user[$pk])): ?><img src="../uploads/<?= htmlspecialchars($current_user[$pk]) ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;"><?php endif; ?>
                                <label><input type="file" name="<?= $pk ?>" accept="image/*" style="display:none;" onchange="previewImage(event)"><span class="btn-add-img"><i class="fa-solid fa-plus"></i></span></label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="edit-panel">
                        <h3><i class="fa-solid fa-sliders"></i> Personal Details</h3>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px;">
                            <label><i class="fa-solid fa-ruler-vertical"></i> Height</label>
                            <input type="text" name="height" class="personal-detail-input" value="<?= htmlspecialchars($current_user['height'] ?? '') ?>">
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding:15px 0;">
                            <label><i class="fa-solid fa-graduation-cap"></i> Education</label>
                            <input type="text" name="education" class="personal-detail-input" value="<?= htmlspecialchars($current_user['education'] ?? '') ?>">
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding:15px 0;">
                            <label><i class="fa-solid fa-wine-glass"></i> Drinking</label>
                            <select name="drinking" class="personal-detail-input" style="direction:rtl;">
                                <option value="Socially" <?= ($current_user['drinking']??'') == 'Socially' ? 'selected':'' ?>>Socially</option>
                                <option value="Yes" <?= ($current_user['drinking']??'') == 'Yes' ? 'selected':'' ?>>Yes</option>
                                <option value="No" <?= ($current_user['drinking']??'') == 'No' ? 'selected':'' ?>>No</option>
                            </select>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:15px;">
                            <label><i class="fa-solid fa-paw"></i> Pets</label>
                            <input type="text" name="pets" class="personal-detail-input" value="<?= htmlspecialchars($current_user['pets'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="edit-panel" style="grid-column: 1 / -1;">
                    <div style="display:flex; justify-content:space-between;"><h3><i class="fa-solid fa-sparkles"></i> Interests</h3></div>
                    <div class="edit-interests-container">
                        <?php foreach($all_interests as $int): $ck = in_array($int['id'], $my_interests_ids) ? 'checked' : ''; ?>
                            <input type="checkbox" class="interest-edit-check" name="interests[]" value="<?= $int['id'] ?>" id="int_<?= $int['id'] ?>" <?= $ck ?>>
                            <label for="int_<?= $int['id'] ?>" class="interest-edit-label"><?= htmlspecialchars($int['name']) ?> <span class="icon-add"></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="save-btn-container" style="text-align:center; margin-top:30px;"><button type="submit" class="btn-save-changes" style="background:var(--y2k-hot-pink); color:#fff; padding:15px 50px; border-radius:50px; font-weight:800; border:none; cursor:pointer; box-shadow: 0 10px 20px rgba(255, 75, 130, 0.2);">Save Changes</button></div>
        </form>
    </main>
    <script>
        function previewImage(event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // KIỂM TRA DUNG LƯỢNG ẢNH BẰNG JAVASCRIPT (Giới hạn 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ảnh này nặng quá 5MB rồi mày ơi! Chọn ảnh nhẹ hơn để không bị mất thông tin nhé!');
                    input.value = ''; // Xóa file vừa chọn
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const slot = input.closest('.photo-slot');
                    let img = slot.querySelector('img');
                    if (!img) { 
                        img = document.createElement('img'); 
                        img.style = "width:100%;height:100%;object-fit:cover;position:absolute;"; 
                        slot.prepend(img); 
                    }
                    img.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>