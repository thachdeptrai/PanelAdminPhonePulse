<?php
require_once __DIR__ . '/config.php';
session_start();

use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

// Encode HTML an toàn
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
function isValidMongoId($id) {
    return preg_match('/^[a-f\d]{24}$/i', $id);
}

// Check quyền admin
function isAdmin()
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === true;
}

// Làm sạch dữ liệu input
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

// ===============================
// ✅ getDashboardStats dùng MongoDB
// ===============================
function getDashboardStats()
{
    global $mongoDB;
    $stats = [];

    // Thời gian hiện tại
    $now = new DateTime();
    $startOfThisMonth = new UTCDateTime((new DateTime('first day of this month'))->getTimestamp() * 1000);
    $startOfLastMonth = new UTCDateTime((new DateTime('first day of last month'))->getTimestamp() * 1000);
    $startOfNextMonth = new UTCDateTime((new DateTime('first day of next month'))->getTimestamp() * 1000);

    // ========== 1. Doanh thu & đơn hàng tháng này ==========
    $cursor = $mongoDB->orders->aggregate([
        ['$match' => ['created_date' => ['$gte' => $startOfThisMonth]]],
        ['$group' => [
            '_id' => null,
            'revenue' => ['$sum' => '$final_price'],
            'orders' => ['$sum' => 1],
        ]]
    ]);
    $row = $cursor->toArray()[0] ?? ['revenue' => 0, 'orders' => 0];
    $stats['revenue'] = $row['revenue'] ?? 0;
    $stats['orders'] = $row['orders'] ?? 0;

    // ========== 2. Doanh thu & đơn hàng tháng trước ==========
    $cursor = $mongoDB->orders->aggregate([
        ['$match' => [
            'created_date' => [
                '$gte' => $startOfLastMonth,
                '$lt'  => $startOfThisMonth
            ]
        ]],
        ['$group' => [
            '_id' => null,
            'revenue' => ['$sum' => '$final_price'],
            'orders' => ['$sum' => 1],
        ]]
    ]);
    $last = $cursor->toArray()[0] ?? ['revenue' => 0, 'orders' => 0];
    $last_revenue = $last['revenue'] ?: 1;
    $last_orders = $last['orders'] ?: 1;

    // ========== 3. % thay đổi doanh thu & đơn hàng ==========
    $stats['rev_change'] = round((($stats['revenue'] - $last_revenue) / $last_revenue) * 100, 2) . '%';
    $stats['order_change'] = round((($stats['orders'] - $last_orders) / $last_orders) * 100, 2) . '%';

    // ========== 4. Người dùng hoạt động 30 ngày gần nhất ==========
    $now = new DateTime();
    $ts30DaysAgo = new UTCDateTime((new DateTime('-30 days'))->getTimestamp() * 1000);
    $ts60DaysAgo = new UTCDateTime((new DateTime('-60 days'))->getTimestamp() * 1000);

    $activeNow = $mongoDB->users->countDocuments(['modified_date' => ['$gte' => $ts30DaysAgo]]);
    $activeLast = $mongoDB->users->countDocuments([
        'last_login' => [
            '$gte' => $ts60DaysAgo,
            '$lt' => $ts30DaysAgo
        ]
    ]);
    $activeLast = $activeLast ?: 1;
    $stats['users'] = $activeNow;
    $stats['user_change'] = round((($activeNow - $activeLast) / $activeLast) * 100, 2) . '%';

    // ========== 5. Tổng số sản phẩm ==========
    $stats['product'] = $mongoDB->Product->countDocuments([]);

    // ========== 6. Tổng số danh mục ==========
    $stats['categories'] = $mongoDB->Category->countDocuments([]);

    return $stats;
}
