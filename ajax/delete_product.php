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

$input = json_decode(file_get_contents('php://input'), true);
$mongoId = $input['id'] ?? '';
if (!$mongoId) {
    echo json_encode(['success' => false, 'message' => 'Missing product ID']);
    exit;
}

try {
    $objectId = new ObjectId($mongoId);

    // Get all image URLs before deletion
    $images = $mongoDB->ProductImage->find(['product_id' => $objectId]);
    foreach ($images as $img) {
        if (isset($img['image_url'])) {
            $path = '../' . ltrim($img['image_url'], '/');
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // Delete related documents
    $mongoDB->ProductImage->deleteMany(['product_id' => $objectId]);
    $mongoDB->Variant->deleteMany(['product_id' => $objectId]);
    $mongoDB->Review->deleteMany(['product_id' => $objectId]);
    $mongoDB->CartItem->deleteMany(['product_id' => $objectId]);
    $mongoDB->OrderItem->deleteMany(['product_id' => $objectId]);

    // Delete main product
    $result = $mongoDB->Product->deleteOne(['_id' => $objectId]);

    if ($result->getDeletedCount() > 0) {
        $mongoDB->logs->insertOne([
            'admin_id' => new ObjectId($_SESSION['user_id']),
            'action' => 'DELETE',
            'module' => 'PRODUCT',
            'time' => new MongoDB\BSON\UTCDateTime(),
            'details' => json_encode([
                'product_id' => (string)$mongoId,
                'message' => 'Xoá sản phẩm thành công',
                'timestamp' => date('Y-m-d H:i:s')
            ]),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]);

        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found or already deleted']);
    }

} catch (Exception $e) {
    error_log("Delete product error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . $e->getMessage()]);
}
