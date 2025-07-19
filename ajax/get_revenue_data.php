<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Lấy dữ liệu từ GET
$type  = $_GET['type']  ?? 'month';
$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

try {
    switch ($type) {
        case '7days':
            $stmt = $pdo->query("
                SELECT DATE(created_date) AS label, SUM(final_price) AS total
                FROM orders
                WHERE created_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND shipping_status = 'shipped'
                GROUP BY DATE(created_date)
                ORDER BY DATE(created_date)
            ");
            break;

        case '3months':
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(created_date, '%Y-%m') AS label, SUM(final_price) AS total
                FROM orders
                WHERE created_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                  AND shipping_status = 'shipped'
                GROUP BY label
                ORDER BY label
            ");
            break;

        case 'year':
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(created_date, '%Y-%m') AS label, SUM(final_price) AS total
                FROM orders
                WHERE YEAR(created_date) = YEAR(CURDATE())
                  AND shipping_status = 'shipped'
                GROUP BY label
                ORDER BY label
            ");
            break;

        case 'custom':
            if (!$start || !$end) {
                throw new Exception('Missing start or end date for custom range.');
            }

            // Validate format YYYY-MM-DD
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD.');
            }

            $stmt = $pdo->prepare("
                SELECT DATE(created_date) AS label, SUM(final_price) AS total
                FROM orders
                WHERE DATE(created_date) BETWEEN :start AND :end
                  AND shipping_status = 'shipped'
                GROUP BY DATE(created_date)
                ORDER BY DATE(created_date)
            ");
            $stmt->execute([
                ':start' => $start,
                ':end'   => $end
            ]);
            break;

        default: // 'month'
            $stmt = $pdo->query("
                SELECT DATE(created_date) AS label, SUM(final_price) AS total
                FROM orders
                WHERE MONTH(created_date) = MONTH(CURDATE())
                  AND YEAR(created_date) = YEAR(CURDATE())
                  AND shipping_status = 'shipped'
                GROUP BY DATE(created_date)
                ORDER BY DATE(created_date)
            ");
            break;
    }

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error'   => true,
        'message' => $e->getMessage()
    ]);
}
