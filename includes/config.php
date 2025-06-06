<?php
// includes/config.php
session_start();

// Cấu hình cơ bản
define('BASE_URL', 'http://localhost/');
define('SITE_NAME', 'Admin Panel');

// Cấu hình MongoDB API
define('MONGO_API_BASE_URL', 'http://localhost:5000/api'); // Thay đổi theo API của bạn
// define('API_KEY', ''); // Nếu có API key

// Cấu hình timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bật error reporting cho development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Hàm kiểm tra đăng nhập
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Hàm redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Hàm escape HTML
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>