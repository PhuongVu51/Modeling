<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Lấy dữ liệu từ form
    $email = $_POST['email'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $phone = $_POST['phone'] ?? '';
    
    $full_name = $_POST['full_name'] ?? '';
    $nickname = $_POST['nickname'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $occupation = $_POST['occupation'] ?? '';
    
    $interested_in = $_POST['interested_in'] ?? '';
    $age_range = $_POST['age_range'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    $selected_interests = isset($_POST['interests']) ? explode(',', $_POST['interests']) : [];

    // Bắt đầu Transaction để đảm bảo dữ liệu lưu đủ các bảng
    $conn->begin_transaction();

    try {
        // 2. Lưu vào bảng users
        $stmt = $conn->prepare("INSERT INTO users (email, password, phone) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $password, $phone);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // 3. Xử lý Upload ảnh
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $photos = ['avatar', 'photo1', 'photo2', 'photo3'];
        $paths = [];

        foreach ($photos as $key) {
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === 0) {
                $ext = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
                $file_name = $user_id . "_" . $key . "_" . time() . "." . $ext;
                $target = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES[$key]['tmp_id'], $target)) {
                    $paths[$key] = $file_name;
                } else {
                    $paths[$key] = null;
                }
            } else {
                $paths[$key] = null;
            }
        }

        // 4. Lưu vào bảng profiles
        $stmt_prof = $conn->prepare("INSERT INTO profiles (user_id, full_name, nickname, dob, gender, occupation, interested_in, age_range, bio, avatar, photo_1, photo_2, photo_3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_prof->bind_param("issssssssssss", $user_id, $full_name, $nickname, $dob, $gender, $occupation, $interested_in, $age_range, $bio, $paths['avatar'], $paths['photo1'], $paths['photo2'], $paths['photo3']);
        $stmt_prof->execute();

        // 5. Lưu sở thích
        if (!empty($selected_interests)) {
            $stmt_int = $conn->prepare("INSERT INTO user_interests (user_id, interest_id) VALUES (?, ?)");
            foreach ($selected_interests as $int_id) {
                $stmt_int->bind_param("ii", $user_id, $int_id);
                $stmt_int->execute();
            }
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Đăng ký thành công!"]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
    }
}
?>