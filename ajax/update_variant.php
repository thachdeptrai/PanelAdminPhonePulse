<?php
// ===== ajax/update_variant.php =====
include '../includes/config.php';
include '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $variant_id = $_POST['variant_id'] ?? 0;
    $color_id = $_POST['color_id'] ?? '';
    $size_id = $_POST['size_id'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $price = $_POST['price'] ?? 0;

    if (!$variant_id || empty($color_id) || empty($size_id) || $quantity < 0 || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit;
    }

    // Get current variant to check product_id
    $currentStmt = $pdo->prepare("SELECT product_id FROM variants WHERE id = ?");
    $currentStmt->execute([$variant_id]);
    $current = $currentStmt->fetch();
    
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Biến thể không tồn tại']);
        exit;
    }

    // Check if another variant with same color/size exists
    $checkStmt = $pdo->prepare("SELECT id FROM variants WHERE product_id = ? AND color_id = ? AND size_id = ?  AND id != ?");
    $checkStmt->execute([$current['product_id'], $color_id, $size_id, $variant_id]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Biến thể với màu sắc và kích thước này đã tồn tại']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE variants SET color_id = ?, size_id = ?, quantity = ?,price =?, modified_date = NOW() WHERE id = ?");
    $result = $stmt->execute([$color_id, $size_id, $quantity,$price, $variant_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật biến thể thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể cập nhật biến thể']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
?>