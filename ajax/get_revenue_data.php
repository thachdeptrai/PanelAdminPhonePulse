<?php
include '../includes/config.php';

$type = $_GET['type'] ?? 'month'; // '7days', 'month', '3months', 'year'

switch ($type) {
    case '7days':
        $stmt = $pdo->query("
            SELECT DATE(created_date) AS label, SUM(final_price) AS total
            FROM orders
            WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_date)
            ORDER BY DATE(created_date)
        ");
        break;

    case '3months':
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_date, '%Y-%m') AS label, SUM(final_price) AS total
            FROM orders
            WHERE created_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY label
            ORDER BY label
        ");
        break;

    case 'year':
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_date, '%Y-%m') AS label, SUM(final_price) AS total
            FROM orders
            WHERE YEAR(created_date) = YEAR(CURDATE())
            GROUP BY label
            ORDER BY label
        ");
        break;

    default: // 'month'
        $stmt = $pdo->query("
            SELECT DATE(created_date) AS label, SUM(final_price) AS total
            FROM orders
            WHERE MONTH(created_date) = MONTH(CURDATE()) AND YEAR(created_date) = YEAR(CURDATE())
            GROUP BY DATE(created_date)
            ORDER BY DATE(created_date)
        ");
        break;
}

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);
