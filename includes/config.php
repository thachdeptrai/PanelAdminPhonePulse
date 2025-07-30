<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload
use MongoDB\Client;
// $host = 'localhost';
// $db   = 'phonepulse';
// $user = 'root';
// $pass = ''; // nếu có mật khẩu thì điền vào đây
// // $charset = 'utf8mb4';

// $dsn = "mysql:host=$host;dbname=$db";
// $options = [
//     PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // báo lỗi
//     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch dạng array
//     PDO::ATTR_EMULATE_PREPARES   => false,                  // dùng prepare thật
// ];

// try {
//     $pdo = new PDO($dsn, $user, $pass, $options);
// } catch (\PDOException $e) {
//     die('Kết nối database thất bại: ' . $e->getMessage());
// }

// Load env nếu cần
// require_once __DIR__ . '/dotenv_loader.php';


$MONGO_URI = 'mongodb://localhost:27017'; // hoặc dùng getenv('MONGO_URI')
$MONGO_DB_NAME = 'PhonePulse';         // ✏️ Thay bằng tên DB thật

// Khởi tạo Mongo Client 1 lần duy nhất
$mongoClient = new Client($MONGO_URI);

// Lấy database 1 lần duy nhất
$mongoDB = $mongoClient->selectDatabase($MONGO_DB_NAME);
$mongo = $mongoClient->selectDatabase($MONGO_DB_NAME);

// // Đặt tên site
// define('SITE_NAME', 'PhonePulse Admin');
// // Đặt URL của API
// define('API_URL', 'http://localhost/phonepulse/api/');
// // Đặt URL của trang admin
// define('ADMIN_URL', 'http://localhost/phonepulse/admin/');
// // Đặt URL của trang client 
// define('CLIENT_URL', 'http://localhost/phonepulse/client/');
// // Đặt URL của trang dashboard
// define('DASHBOARD_URL', ADMIN_URL . 'pages/dashboard.php');
// // Đặt URL của trang login
// define('LOGIN_URL', ADMIN_URL . 'login.php');
// // Đặt URL của trang logout
// define('LOGOUT_URL', ADMIN_URL . 'logout.php');
// // Đặt URL của trang users
// define('USERS_URL', ADMIN_URL . 'pages/users.php');
// // Đặt URL của trang settings
// define('SETTINGS_URL', ADMIN_URL . 'pages/settings.php');
// // Đặt URL của trang dashboard stats
// define('DASHBOARD_STATS_URL', API_URL . 'dashboard/stats');
// // Đặt URL của trang admin assets
// define('ADMIN_ASSETS_URL', ADMIN_URL . 'assets/');
// // Đặt URL của trang client assets
// define('CLIENT_ASSETS_URL', CLIENT_URL . 'assets/');
// // Đặt URL của trang admin assets CSS
// define('ADMIN_CSS_URL', ADMIN_ASSETS_URL . 'css/');
// // Đặt URL của trang admin assets JS
// define('ADMIN_JS_URL', ADMIN_ASSETS_URL . 'js/');
// // Đặt URL của trang client assets CSS
// define('CLIENT_CSS_URL', CLIENT_ASSETS_URL . 'css/');