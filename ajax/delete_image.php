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
    $image_id = $input['id'] ?? '';

    if (!$image_id) {
        echo json_encode(['success' => false, 'message' => 'ID hình ảnh không hợp lệ']);
        exit;
    }

    try {
        $objectId = new ObjectId($image_id);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }

    // Lấy thông tin hình ảnh từ MongoDB
    $image = $mongoDB->ProductImage->findOne(['_id' => $objectId]);

    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Hình ảnh không tồn tại']);
        exit;
    }

    // Xóa file vật lý nếu tồn tại
    $image_path = '../../' . ltrim($image['image_url'], '/');
    if (file_exists($image_path)) {
        unlink($image_path);
    }

    // Xóa khỏi MongoDB
    $deleteResult = $mongoDB->ProductImage->deleteOne(['_id' => $objectId]);

    if ($deleteResult->getDeletedCount() > 0) {
      
         $mongoDB->logs->insertOne([
                'admin_id'  => new ObjectId($_SESSION['user_id']),
                'action'    => 'DELETE',
                'module'    => 'PRODUCT_IMAGE',
                'time'      => new MongoDB\BSON\UTCDateTime(),
                'details'   => json_encode([
                    'image_id'    => (string)$objectId,
                    'image_url'   => $image['image_url'] ?? '',
                    'product_id'  => isset($image['product_id']) ? (string)$image['product_id'] : null,
                    'message'     => 'Hình ảnh sản phẩm đã bị xoá',
                    'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'timestamp'   => date('Y-m-d H:i:s')
                ]),
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]);
        echo json_encode(['success' => true, 'message' => 'Xóa hình ảnh thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa hình ảnh']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
