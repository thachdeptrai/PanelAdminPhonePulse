<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Tên danh mục không được để trống']);
    exit;
}

try {
    $mongo->Category->insertOne([
        'name' => $name,
        'created_date' => new MongoDB\BSON\UTCDateTime(),
        'modified_date' => new MongoDB\BSON\UTCDateTime()
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
