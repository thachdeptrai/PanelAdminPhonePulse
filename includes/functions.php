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

    // ================================
    // 1. Doanh thu & đơn hàng tháng này
    // ================================
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(final_price), 0) AS revenue,
            COUNT(*) AS orders
        FROM orders
        WHERE created_date >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
    ");
    $row = $stmt->fetch();
    $stats['revenue'] = $row['revenue'];
    $stats['orders'] = $row['orders'];

    // ================================
    // 2. Doanh thu & đơn hàng tháng trước
    // ================================
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(final_price), 0) AS revenue,
            COUNT(*) AS orders
        FROM orders
        WHERE created_date >= DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01'), INTERVAL 1 MONTH)
          AND created_date < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
    ");
    $row = $stmt->fetch();
    $last_revenue = $row['revenue'] ?: 1; // tránh chia 0
    $last_orders = $row['orders'] ?: 1;

    // ================================
    // 3. % thay đổi doanh thu & đơn hàng
    // ================================
    $stats['rev_change'] = round((($stats['revenue'] - $last_revenue) / $last_revenue) * 100, 2) . '%';
    $stats['order_change'] = round((($stats['orders'] - $last_orders) / $last_orders) * 100, 2) . '%';

    // ================================
    // 4. Người dùng hoạt động 30 ngày
    // ================================
    $stmt = $pdo->query("SELECT COUNT(*) AS users FROM users WHERE last_login >= NOW() - INTERVAL 30 DAY");
    $row = $stmt->fetch();
    $stats['users'] = $row['users'] ?? 0;

    // So sánh với 30 ngày trước đó
    $stmt = $pdo->query("
        SELECT
            COUNT(CASE WHEN last_login >= NOW() - INTERVAL 30 DAY THEN 1 END) AS current_month,
            COUNT(CASE WHEN last_login >= NOW() - INTERVAL 60 DAY AND last_login < NOW() - INTERVAL 30 DAY THEN 1 END) AS last_month
        FROM users
    ");
    $row = $stmt->fetch();
    $current = $row['current_month'] ?? 0;
    $last = $row['last_month'] ?: 1;
    $stats['user_change'] = round((($current - $last) / $last) * 100, 2) . '%';

    // ================================
    // 5. Tổng số sản phẩm
    // ================================
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM products");
    $row = $stmt->fetch();
    $stats['product'] = $row['total'] ?? 0;

    // ================================
    // 6. Tổng số danh mục
    // ================================
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM categories");
    $row = $stmt->fetch();
    $stats['categories'] = $row['total'] ?? 0;

    return $stats;
}

