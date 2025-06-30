<?php
session_start(); // Bắt buộc, để sử dụng session

// Xoá toàn bộ session
session_unset();    // Xoá tất cả các biến session
session_destroy();  // Huỷ toàn bộ session

// Xoá cookie phiên nếu có dùng (tuỳ hệ thống)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect về login
header("Location: dang_nhap");
exit;
?>