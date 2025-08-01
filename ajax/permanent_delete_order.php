<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    echo "Thiếu ID";
    exit;
}

// Validate ObjectId
try {
    $objectId = new ObjectId($id);
} catch (Exception $e) {
    echo "ID không hợp lệ";
    exit;
}

// Xóa đơn hàng
$deleteResult = $mongoDB->orders->deleteOne(['_id' => $objectId]);

if ($deleteResult->getDeletedCount() > 0) {
    header("Location: ../pages/orders?msg=deleted");
    echo json_encode(['success' => true, 'message' => 'orders deleted successfully']);
    exit;
} else {
    echo "Xóa thất bại.";
}
