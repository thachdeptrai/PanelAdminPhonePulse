<?php
// ===== ajax/update_product.php =====
// Bật error reporting để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/config.php';
include '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Đảm bảo không có output trước header
ob_start();
header('Content-Type: application/json');

// Hàm để đảm bảo chỉ trả về JSON
function sendJsonResponse($data) {
    ob_clean(); // Xóa tất cả output buffer
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

try {
    // Kiểm tra quyền admin
    if (!isAdmin()) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized access']);
    }

    // Chỉ chấp nhận POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed']);
    }
    $id           = $_POST['id'] ?? '';
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id  = $_POST['category_id'] ?? '';
    $description  = trim($_POST['description'] ?? '');

    // Kiểm tra dữ liệu đầu vào
    if (!preg_match('/^[a-f\d]{24}$/i', $id) || !preg_match('/^[a-f\d]{24}$/i', $category_id) || empty($product_name)) {
        sendJsonResponse(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin hợp lệ']);
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
            'time' => new UTCDateTime(),
            'details' => json_encode([
                'product_id' => (string)$id,
                'message' => 'cập nhật product thành công',
                'timestamp' => date('Y-m-d H:i:s')
            ]),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime()
        ]);
        sendJsonResponse(['success' => true, 'message' => 'Cập nhật sản phẩm thành công']);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Không có thay đổi nào được ghi nhận']);
    }

} catch (Exception $e) {
    // Log lỗi để debug
    error_log("Update Product Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    sendJsonResponse(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
} catch (Error $e) {
    // Log lỗi để debug
    error_log("Update Product Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    sendJsonResponse(['success' => false, 'message' => 'Lỗi PHP: ' . $e->getMessage()]);
}
?>
