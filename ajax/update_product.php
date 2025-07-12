<?php
include '../includes/config.php';
include '../includes/functions.php';

header('Content-Type: application/json');

// Kiểm tra quyền admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Chỉ chấp nhận phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $id           = $_POST['id'] ?? 0;
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id  = $_POST['category_id'] ?? '';
    $description  = trim($_POST['description'] ?? '');

    // Kiểm tra dữ liệu đầu vào
    if (!$id || empty($product_name) || empty($category_id)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit;
    }

    // Cập nhật dữ liệu
    $stmt = $pdo->prepare("UPDATE products SET product_name = ?, category_id = ?, description = ?, modified_date = NOW() WHERE id = ?");
    $result = $stmt->execute([$product_name, $category_id, $description, $id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật sản phẩm thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể cập nhật sản phẩm']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
?>
