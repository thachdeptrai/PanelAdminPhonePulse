<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Đọc JSON body
$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['user_id'] ?? null;
$newStatus = isset($input['status']) ? (int)$input['status'] : null;

if (!$userId || !is_numeric($newStatus)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu hoặc không hợp lệ']);
    exit;
}

try {
    $objectId = new ObjectId($userId);

    $result = $mongoDB->users->updateOne(
        ['_id' => $objectId],
        ['$set' => ['status' => $newStatus]]
    );

    if ($result->getModifiedCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng hoặc không có thay đổi']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
