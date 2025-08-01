<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;

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
    $input = json_decode(file_get_contents('php://input'), true);
    $variant_id = $input['id'] ?? '';

    if (!$variant_id) {
        echo json_encode(['success' => false, 'message' => 'ID biến thể không hợp lệ']);
        exit;
    }

    try {
        $variantOid = new ObjectId($variant_id);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }

    // Xoá variant
    $deleteVariant = $mongoDB->Variant->deleteOne(['_id' => $variantOid]);

    // Xoá liên quan trong cart
    $mongoDB->Cart->deleteMany(['variant_id' => $variantOid]);

    // Xoá liên quan trong order_items
    $mongoDB->OrderItem->deleteMany(['variant_id' => $variantOid]);

    if ($deleteVariant->getDeletedCount() > 0) {
        $mongoDB->logs->insertOne([
            'admin_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id']),
            'action'   => 'DELETE',
            'module'   => 'VARIANT',
            'time'     => new MongoDB\BSON\UTCDateTime(),
            'details'  => json_encode([
                'variant_id' => (string)$variant_id,
                'message'    => 'Xoá biến thể thành công',
                'timestamp'  => date('Y-m-d H:i:s')
            ]),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Xóa biến thể thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy biến thể để xóa']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}

?>
