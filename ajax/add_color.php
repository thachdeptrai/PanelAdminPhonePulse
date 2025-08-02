<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$color_name = trim($_POST['color_name'] ?? '');

if ($color_name === '') {
    echo json_encode(['success' => false, 'message' => 'Tên màu không được để trống']);
    exit;
}

try {
    $mongo->Color->insertOne([
        'color_name' => $color_name,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
