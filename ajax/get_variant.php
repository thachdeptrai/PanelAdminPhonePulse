<?php
include '../includes/config.php';
include '../includes/functions.php';

use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

// 🛡️ Kiểm tra quyền truy cập
if (! isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// 🔐 Chỉ cho phép GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $variant_id = $_GET['id'] ?? '';
    $product_id = $_GET['product_id'] ?? '';

    if (empty($product_id)) {
        echo json_encode(['success' => true, 'variants' => []]);
        exit;
    }
    if (! empty($variant_id)) {
        if (! isValidMongoId($variant_id)) {
            echo json_encode(['success' => false, 'message' => 'ID biến thể không hợp lệ']);
            exit;
        }
        $variantObjectId = new ObjectId($variant_id);

        // 📦 Lấy biến thể và join color + size
        $result = $mongoDB->Variant->aggregate([
            ['$match' => ['_id' => $variantObjectId]],
            ['$lookup' => [
                'from'         => 'Color',
                'localField'   => 'color_id',
                'foreignField' => '_id',
                'as'           => 'color',
            ]],
            ['$lookup' => [
                'from'         => 'Size',
                'localField'   => 'size_id',
                'foreignField' => '_id',
                'as'           => 'size',
            ]],
            ['$unwind' => ['path' => '$color', 'preserveNullAndEmptyArrays' => true]],
            ['$unwind' => ['path' => '$size', 'preserveNullAndEmptyArrays' => true]],
            ['$project' => [
                '_id'        => ['$toString' => '$_id'],
                'product_id' => ['$toString' => '$product_id'],
                'color_id'   => ['$toString' => '$color_id'],
                'size_id'    => ['$toString' => '$size_id'],
                'quantity'   => 1,
                'price'      => 1,
                'color_name' => '$color.color_name',
                'color_hex'  => '$color.hex_code',
                'size_name'  => '$size.size_name',
                'size_value' => '$size.storage',
            ]],
        ]);

        $variant = iterator_to_array($result);

        if (! empty($variant)) {
            echo json_encode(['success' => true, 'variant' => $variant[0]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Biến thể không tồn tại']);
        }
        exit;
    }
    if (! empty($product_id)) {
        if (!isValidMongoId($product_id)) {
            echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
            exit;
        }
        $productObjectId = new ObjectId($product_id);
        $result          = $mongoDB->Variant->aggregate([
            ['$match' => ['product_id' => $productObjectId]],
            ['$lookup' => [
                'from'         => 'Color',
                'localField'   => 'color_id',
                'foreignField' => '_id',
                'as'           => 'color',
            ]],
            ['$lookup' => [
                'from'         => 'Size',
                'localField'   => 'size_id',
                'foreignField' => '_id',
                'as'           => 'size',
            ]],
            ['$unwind' => ['path' => '$color', 'preserveNullAndEmptyArrays' => true]],
            ['$unwind' => ['path' => '$size', 'preserveNullAndEmptyArrays' => true]],
            ['$project' => [
                '_id'          => ['$toString' => '$_id'],
                'product_id'   => ['$toString' => '$product_id'],
                'color_id'     => ['$toString' => '$color_id'],
                'size_id'      => ['$toString' => '$size_id'],
                'quantity'     => 1,
                'price'        => 1,
                'color_name'   => '$color.color_name',
                'size_name'    => '$size.size_name',
                'size_value'   => '$size.storage',
                'display_name' => [
                    '$concat' => [
                        ['$ifNull' => ['$color.color_name', 'No Color']],
                        ' - ',
                        ['$ifNull' => ['$size.size_name', 'No Size']]
                    ]
                ]
            ]],
            ['$sort' => ['color_name' => 1, 'size_name' => 1]],
        ]);

        $variants = iterator_to_array($result);

        echo json_encode(['success' => true, 'variants' => $variants]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
