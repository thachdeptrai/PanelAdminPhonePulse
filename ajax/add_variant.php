
<?php
// ===== ajax/add_variant.php =====
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
    $product_id = $_POST['product_id'] ?? 0;
    $color_id = $_POST['color_id'] ?? '';
    $size_id = $_POST['size_id'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $price = $_POST['price'] ?? 0;

    if (!$product_id || empty($color_id) || empty($size_id) || $quantity < 0 || $price < 0) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit;
    }

    // Check if variant already exists
    $checkStmt = $pdo->prepare("SELECT id FROM variants WHERE product_id = ? AND color_id = ? AND size_id = ?");
    $checkStmt->execute([$product_id, $color_id, $size_id]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Biến thể này đã tồn tại']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO variants (product_id, color_id, size_id, quantity, price, created_date, modified_date)
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

$result = $stmt->execute([$product_id, $color_id, $size_id, $quantity, $price]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Thêm biến thể thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể thêm biến thể']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
?>
