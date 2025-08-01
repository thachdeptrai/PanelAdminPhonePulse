<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập']);
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu ID đơn hàng']);
    exit;
}

try {
    $objectId = new ObjectId($id);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

// Kiểm tra đơn hàng có tồn tại và bị đánh dấu xoá không
$order = $mongoDB->orders->findOne(['_id' => $objectId, 'is_deleted' => true]);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại trong thùng rác']);
    exit;
}

// Khôi phục đơn hàng
$updateResult = $mongoDB->orders->updateOne(
    ['_id' => $objectId],
    [
        '$unset' => ['deleted_at' => ''],
        '$set' => ['is_deleted' => false]
    ]
);
if ($updateResult->getModifiedCount() > 0) {
    header("Location: ../orders_trash?msg=restored");
    exit;
} else {
    echo "Khôi phục đơn hàng thất bại.";
}
