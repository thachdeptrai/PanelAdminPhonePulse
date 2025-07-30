<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['mongo_id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu ID']);
    exit;
}

try {
    $result = $mongoDB->users->deleteOne(['_id' => new ObjectId($id)]);

    if ($result->getDeletedCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy user để xóa']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa']);
}
