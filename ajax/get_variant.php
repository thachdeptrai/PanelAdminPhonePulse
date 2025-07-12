
<?php
include '../includes/config.php';
include '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $variant_id = $_GET['id'] ?? 0;

    if (!$variant_id) {
        echo json_encode(['success' => false, 'message' => 'ID biến thể không hợp lệ']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT v.*, c.name as color_name, c.hex_code as color_hex, 
               s.name as size_name, s.value as size_value
        FROM variants v
        LEFT JOIN colors c ON v.color_id = c.mongo_id
        LEFT JOIN sizes s ON v.size_id = s.mongo_id
        WHERE v.id = ?
    ");
    $stmt->execute([$variant_id]);
    $variant = $stmt->fetch();

    if ($variant) {
        echo json_encode(['success' => true, 'variant' => $variant]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Biến thể không tồn tại']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
?>