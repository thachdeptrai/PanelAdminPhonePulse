<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu ID']);
    exit;
}

try {
    $objectId = new ObjectId($id);
    $user = $mongoDB->users->findOne(['_id' => $objectId]);

    if ($user) {
        // Chuyển _id thành string để gửi về JSON
        $user['_id'] = (string) $user['_id'];
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy user']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
}
