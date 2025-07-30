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
    $product_id = $_POST['product_id'] ?? null;
    $image_url_input = trim($_POST['image_url'] ?? '');

    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
        exit;
    }

    try {
        $productObjectId = new ObjectId($product_id);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
        exit;
    }

    $upload_dir = '../../uploads/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $uploaded = [];

    // === XỬ LÝ ẢNH ĐƯỢC UPLOAD TỪ MÁY ===
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $key => $name) {
            if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $tmp_name = $_FILES['images']['tmp_name'][$key];
            $file_size = $_FILES['images']['size'][$key];
            $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_ext) || $file_size > $max_size) continue;

            $new_name = uniqid('img_') . '.' . $file_ext;
            $target_path = $upload_dir . $new_name;
            $public_url = '/uploads/products/' . $new_name;

            if (move_uploaded_file($tmp_name, $target_path)) {
                $mongoDB->ProductImage->insertOne([
                    'product_id' => $productObjectId,
                    'image_url' => $public_url,
                    'modified_date' => new UTCDateTime()
                ]);
                $uploaded[] = $public_url;
            }
        }
    }

    // === XỬ LÝ LINK ẢNH (LƯU THẲNG LINK) ===
    if ($image_url_input !== '') {
        if (!filter_var($image_url_input, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'URL không hợp lệ']);
            exit;
        }

        $url_ext = strtolower(pathinfo(parse_url($image_url_input, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($url_ext, $allowed_ext)) {
            echo json_encode(['success' => false, 'message' => 'URL không phải ảnh hợp lệ']);
            exit;
        }

        $mongoDB->ProductImage->insertOne([
            'product_id' => $productObjectId,
            'image_url' => $image_url_input,
            'modified_date' => new UTCDateTime()
        ]);
        $uploaded[] = $image_url_input;
    }

    if (count($uploaded) > 0) {
        echo json_encode(['success' => true, 'message' => 'Đã thêm ' . count($uploaded) . ' hình ảnh thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không có ảnh nào được thêm!']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
