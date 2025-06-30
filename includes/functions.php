<?php
require_once __DIR__ . '/config.php';
session_start();

// Trả true nếu là admin (role === true)
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
function isAdmin()
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == true;
}

function sanitize_input($data, $type = 'string')
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }

    $data = trim($data);
    $data = stripslashes($data);

    switch ($type) {
        case 'email':
            $data = filter_var($data, FILTER_SANITIZE_EMAIL);
            break;
    }

    return $data;
}
function getDashboardStats()
{
    global $pdo;
    $stats = [];

    // Tổng doanh thu đơn đã hoàn tất
    // $stmt = $pdo->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE status = 'Completed'");
    // $row = $stmt->fetch();
    $stats['revenue'] = $row['revenue'] ?? 0;

    // Tổng số đơn hàng
    // $stmt = $pdo->query("SELECT COUNT(*) AS orders FROM orders");
    // $row = $stmt->fetch();
    $stats['orders'] = $row['orders'] ?? 0;

    // Người dùng hoạt động trong 30 ngày
    $stmt           = $pdo->query("SELECT COUNT(*) AS users FROM users WHERE last_login >= NOW() - INTERVAL 30 DAY");
    $row            = $stmt->fetch();
    $stats['users'] = $row['users'] ?? 0;

    // Phần trăm thay đổi doanh thu theo tháng

    // $stmt = $pdo->query("
    //     SELECT
    //         SUM(CASE WHEN MONTH(order_date) = MONTH(CURDATE()) THEN total_amount ELSE 0 END) AS current_month,
    //         SUM(CASE WHEN MONTH(order_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) THEN total_amount ELSE 0 END) AS last_month
    //     FROM orders
    //     WHERE status = 'Completed'
    // ");
    // $row = $stmt->fetch();
    // $current = $row['current_month'] ?? 0;
    // $last = $row['last_month'] ?? 1; // tránh chia 0

    // $change = (($current - $last) / $last) * 100;
    // $stats['rev_change'] = round($change, 2) . '%';
    // // Phần trăm thay đổi số đơn hàng theo tháng
    // $stmt = $pdo->query("
    //     SELECT
    //         COUNT(CASE WHEN MONTH(order_date) = MONTH(CURDATE()) THEN 1 END) AS current_month,
    //         COUNT(CASE WHEN MONTH(order_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) THEN 1 END) AS last_month
    //     FROM orders
    //     WHERE status = 'Completed'
    // ");
    // $row = $stmt->fetch();
    // $current = $row['current_month'] ?? 0;
    // $last = $row['last_month'] ?? 1; // tránh chia 0
    // $change = (($current - $last) / $last) * 100;
    // $stats['order_change'] = round($change, 2) . '%';
    // Phần trăm thay đổi người dùng hoạt động theo tháng
    $stmt = $pdo->query("
    SELECT
        COUNT(CASE WHEN last_login >= NOW() - INTERVAL 30 DAY THEN 1 END) AS current_month,
        COUNT(CASE WHEN last_login >= NOW() - INTERVAL 60 DAY AND last_login < NOW() - INTERVAL 30 DAY THEN 1 END) AS last_month
    FROM users
");

$row = $stmt->fetch();
$current = $row['current_month'] ?? 0;
$last = $row['last_month'] ?? 0;

if ($last == 0) {
    $last = 1; // Tránh chia cho 0
}
    $change = (($current - $last) / $last) * 100;
    $stats['user_change'] = round($change, 2) . '%';

    // Tổng số sản phẩm
    $stmt              = $pdo->query("SELECT COUNT(*) AS products FROM products");
    $row               = $stmt->fetch();
    $stats['products'] = $row['products'] ?? 0;
    // Tổng số danh mục
    $stmt                = $pdo->query("SELECT COUNT(*) AS categories FROM categories");
    $row                 = $stmt->fetch();
    $stats['categories'] = $row['categories'] ?? 0;

    return $stats;
}
