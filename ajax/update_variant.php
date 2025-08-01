<?php
// ===== ajax/update_variant.php =====
include '../includes/config.php';
include '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

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
    $variant_id = $_POST['variant_id'] ?? '';
    $color_id   = $_POST['color_id'] ?? '';
    $size_id    = $_POST['size_id'] ?? '';
    $quantity   = (int) ($_POST['quantity'] ?? 0);
    $price      = (float) ($_POST['price'] ?? 0);

    if (!isValidMongoId($variant_id) || !isValidMongoId($color_id) || !isValidMongoId($size_id) || $quantity < 0 || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin hợp lệ']);
        exit;
    }
    

    $variantObjectId = new ObjectId($variant_id);
    $colorObjectId = new ObjectId($color_id);
    $sizeObjectId = new ObjectId($size_id);

    // 🔍 Lấy variant hiện tại để biết product_id
    $current = $mongoDB->Variant->findOne(['_id' => $variantObjectId]);

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Biến thể không tồn tại']);
        exit;
    }

    // 🛡️ Kiểm tra trùng color_id + size_id trong cùng product_id, nhưng khác variant hiện tại
    $duplicate = $mongoDB->Variant->findOne([
        'product_id' => $current['product_id'],
        'color_id'   => $colorObjectId,
        'size_id'    => $sizeObjectId,
        '_id'        => ['$ne' => $variantObjectId],
    ]);

    if ($duplicate) {
        echo json_encode(['success' => false, 'message' => 'Biến thể với màu sắc và kích thước này đã tồn tại']);
        exit;
    }

    // ✅ Update biến thể
    $updateResult = $mongoDB->Variant->updateOne(
        ['_id' => $variantObjectId],
        ['$set' => [
            'color_id'      => $colorObjectId,
            'size_id'       => $sizeObjectId,
            'quantity'      => $quantity,
            'price'         => $price,
            'modified_date' => new UTCDateTime()
        ]]
    );

    if ($updateResult->getModifiedCount() > 0) {
        // 📝 Ghi log
    $mongoDB->logs->insertOne([
        'admin_id' => new ObjectId($_SESSION['user_id'])?? null,
        'action'   => 'UPDATE',
        'module'   => 'VARIANT',
        'time'     => new UTCDateTime(),
        'details'  => json_encode([
            'variant_id' => (string) $variant_id,
            'message'    => 'Cập nhật biến thể thành công',
            'timestamp'  => date('Y-m-d H:i:s')
        ]),
        'created_at' => new UTCDateTime(),
        'updated_at' => new UTCDateTime()
    ]);
        echo json_encode(['success' => true, 'message' => 'Cập nhật biến thể thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không có thay đổi nào được ghi nhận']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
?>
