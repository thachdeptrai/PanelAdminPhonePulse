<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$size_name = trim($_POST['size_name'] ?? '');
$storage = trim($_POST['storage'] ?? '');

if ($size_name === '') {
    echo json_encode(['success' => false, 'message' => 'Tên size không được để trống']);
    exit;
}

try {
    $mongo->Size->insertOne([
        'size_name' => $size_name,
        'storage' => $storage,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
