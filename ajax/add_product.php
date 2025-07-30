<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

try {
    $name        = trim($_POST['product_name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $image_url   = trim($_POST['image_url'] ?? '');

    if (empty($name) || empty($category_id)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit;
    }

    try {
        $category_oid = new ObjectId($category_id);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'ID danh mục không hợp lệ']);
        exit;
    }

    $createdAt = new UTCDateTime();
    $productInsert = [
        'product_name'  => $name,
        'category_id'   => $category_oid,
        'description'   => $description,
        'created_date'  => $createdAt,
        'modified_date' => $createdAt
    ];

    $insertResult = $mongoDB->Product->insertOne($productInsert);
    $productId = $insertResult->getInsertedId();

    // === ẢNH ===
    $upload_dir = '../uploads/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $finalImageUrl = '';
    $validExt = ['jpg','jpeg','png','webp','gif'];

    // Ưu tiên ảnh upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $validExt)) {
            echo json_encode(['success' => false, 'message' => 'Định dạng ảnh không hợp lệ']);
            exit;
        }

        $newName = uniqid('product_') . '.' . $ext;
        $targetPath = $upload_dir . $newName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $finalImageUrl = '/uploads/products/' . $newName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể lưu ảnh upload']);
            exit;
        }

    } elseif (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
        $urlExt = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($urlExt, $validExt)) {
            echo json_encode(['success' => false, 'message' => 'Định dạng ảnh từ URL không hợp lệ']);
            exit;
        }
        $finalImageUrl = $image_url;
    }

    // Lưu ảnh vào collection ProductImage
    if (!empty($finalImageUrl)) {
        $mongoDB->ProductImage->insertOne([
            'product_id'  => $productId,
            'image_url'   => $finalImageUrl,
            'created_at'  => $createdAt,
            'updated_at'  => $createdAt
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Thêm sản phẩm thành công!',
        'product_id' => (string)$productId
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
