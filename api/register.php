<?php
// Inicia buffer para capturar qualquer output não esperado
ob_start();

// Desativa erros de exibição
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define header JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Inclui conexão
    require_once 'db_connect.php';
    
    // Verifica conexão
    if (!$conn) {
        throw new Exception('Conexão não foi estabelecida');
    }
    
    if ($conn->connect_error) {
        throw new Exception('Erro de conexão: ' . $conn->connect_error);
    }
    
    // Limpa buffer para evitar outputs anteriores
    ob_clean();
    
    // 1. Lấy dữ liệu từ FORM
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $interested_in = trim($_POST['interested_in'] ?? 'Anyone');
    $bio = trim($_POST['bio'] ?? '');
    $interests = trim($_POST['interests'] ?? '');
    
    // Kiểm tra thông tin bắt buộc
    if (empty($email) || empty($password) || empty($phone)) {
        http_response_code(400);
        throw new Exception('Email, mật khẩu và điện thoại là bắt buộc');
    }
    
    // Mã hóa mật khẩu
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    if (!$hashed_password) {
        throw new Exception('Lỗi mã hóa mật khẩu');
    }
    
    // 2. Hàm xử lý upload ảnh
    function handleUpload($fileKey) {
        if(isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0){
            $targetDir = dirname(__FILE__) . "/../uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
            $newName = time() . '_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . $newName)){
                return $newName;
            }
        }
        return null;
    }
    
    $avatar = handleUpload('avatar');
    $p1 = handleUpload('photo1');
    $p2 = handleUpload('photo2');
    $p3 = handleUpload('photo3');
    
    // 3. Lưu vào bảng USERS
    $stmt = $conn->prepare("INSERT INTO users (email, password, phone) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare statement lỗi: ' . $conn->error);
    }
    
    $stmt->bind_param("sss", $email, $hashed_password, $phone);
    
    if(!$stmt->execute()) {
        if (strpos($conn->error, 'Duplicate') !== false) {
            throw new Exception('Email đã được đăng ký');
        } else {
            throw new Exception('Lỗi đăng ký: ' . $stmt->error);
        }
    }
    
    $user_id = $conn->insert_id;
    $stmt->close();
    
    // 4. Lưu vào bảng PROFILES
    $stmt_p = $conn->prepare("INSERT INTO profiles (user_id, full_name, nickname, dob, gender, interested_in, bio, avatar, photo_1, photo_2, photo_3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt_p) {
        throw new Exception('Prepare profile lỗi: ' . $conn->error);
    }
    
    $stmt_p->bind_param("issssssssss", $user_id, $full_name, $nickname, $dob, $gender, $interested_in, $bio, $avatar, $p1, $p2, $p3);
    
    if(!$stmt_p->execute()) {
        throw new Exception('Lỗi lưu hồ sơ: ' . $stmt_p->error);
    }
    
    $stmt_p->close();
    
    // 5. Lưu SỞ THÍCH
    if(!empty($interests)) {
        $int_array = array_filter(explode(',', $interests));
        if (!empty($int_array)) {
            $stmt_i = $conn->prepare("INSERT INTO user_interests (user_id, interest_id) VALUES (?, ?)");
            if ($stmt_i) {
                foreach($int_array as $id) {
                    $int_id = (int)trim($id);
                    $stmt_i->bind_param("ii", $user_id, $int_id);
                    $stmt_i->execute();
                }
                $stmt_i->close();
            }
        }
    }
    
    $conn->close();
    
    // Trả về success
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công!']);
    
} catch (Exception $e) {
    // Xóa buffer để không có output trước
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>