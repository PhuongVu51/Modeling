<?php
// Bắt đầu session để có thể hủy nó
session_start();

// Xóa toàn bộ dữ liệu của session hiện tại
$_SESSION = array();

// Nếu có dùng cookie session thì xóa luôn cho an toàn
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy hoàn toàn phiên đăng nhập
session_destroy();

// Chuyển hướng người dùng về trang đăng nhập (login.html nằm trong thư mục src)
header("Location: ../src/login.html");
exit();
?>