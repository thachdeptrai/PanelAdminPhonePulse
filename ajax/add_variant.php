<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

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
    $product_id = $_POST['product_id'] ?? '';
    $color_id   = $_POST['color_id'] ?? '';
    $size_id    = $_POST['size_id'] ?? '';
    $quantity   = (int) ($_POST['quantity'] ?? 0);
    $price      = (float) ($_POST['price'] ?? 0);

    if (!$product_id || !$color_id || !$size_id || $quantity < 0 || $price < 0) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit;
    }

    try {
        $product_oid = new ObjectId($product_id);
        $color_oid   = new ObjectId($color_id);
        $size_oid    = new ObjectId($size_id);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }

    // Kiểm tra biến thể trùng
    $existing = $mongoDB->Variant->findOne([
        'product_id' => $product_oid,
        'color_id'   => $color_oid,
        'size_id'    => $size_oid
    ]);

    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Biến thể này đã tồn tại']);
        exit;
    }

    // Thêm biến thể
    $now = new UTCDateTime();
    $insert = $mongoDB->Variant->insertOne([
        'product_id'    => $product_oid,
        'color_id'      => $color_oid,
        'size_id'       => $size_oid,
        'quantity'      => $quantity,
        'price'         => $price,
        'created_date'  => $now,
        'modified_date' => $now
    ]);

    echo json_encode(['success' => true, 'message' => 'Thêm biến thể thành công']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
