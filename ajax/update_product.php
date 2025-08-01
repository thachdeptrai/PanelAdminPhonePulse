<?php
// ===== ajax/update_product.php =====
include '../includes/config.php';
include '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

// Kiểm tra quyền admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $id           = $_POST['id'] ?? '';
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id  = $_POST['category_id'] ?? '';
    $description  = trim($_POST['description'] ?? '');

    // Kiểm tra dữ liệu đầu vào
    if (!ObjectId::isValid($id) || !ObjectId::isValid($category_id) || empty($product_name)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin hợp lệ']);
        exit;
    }

    $productId  = new ObjectId($id);
    $categoryId = new ObjectId($category_id);

    // Tiến hành cập nhật
    $updateResult = $mongoDB->Product->updateOne(
        ['_id' => $productId],
        ['$set' => [
            'product_name'   => $product_name,
            'category_id'    => $categoryId,
            'description'    => $description,
            'modified_date'  => new UTCDateTime()
        ]]
    );

    if ($updateResult->getModifiedCount() > 0) {
        $mongoDB->logs->insertOne([
            'admin_id' => new ObjectId($_SESSION['user_id']),
            'action' => 'POST',
            'module' => 'PRODUCT',
            'time' => new MongoDB\BSON\UTCDateTime(),
            'details' => json_encode([
                'product_id' => (string)$id,
                'message' => 'cập nhật product thành công',
                'timestamp' => date('Y-m-d H:i:s')
            ]),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        echo json_encode(['success' => true, 'message' => 'Cập nhật sản phẩm thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không có thay đổi nào được ghi nhận']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>
