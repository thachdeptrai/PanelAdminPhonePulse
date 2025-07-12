<?php
include '../includes/config.php';
include '../includes/functions.php';
header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$mongoId = $_POST['id'] ?? '';
if (!$mongoId) {
    echo json_encode(['success' => false, 'message' => 'Missing product ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Delete related records
    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$mongoId]);
    $pdo->prepare("DELETE FROM variants WHERE product_id = ?")->execute([$mongoId]);
    $pdo->prepare("DELETE FROM reviews WHERE product_id = ?")->execute([$mongoId]);
    $pdo->prepare("DELETE FROM cart_items WHERE product_id = ?")->execute([$mongoId]);
    $pdo->prepare("DELETE FROM order_items WHERE product_id = ?")->execute([$mongoId]);
    
    // Get product images before deletion to remove files
    $imageStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
    $imageStmt->execute([$mongoId]);
    $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete product images from filesystem
    foreach ($images as $imagePath) {
        $fullPath = '../uploads/products/' . $imagePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
    
    // Delete main product record
    $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $result = $deleteStmt->execute([$mongoId]);
    
    if ($result && $deleteStmt->rowCount() > 0) {
        $pdo->commit();
        
        // Log the deletion
        $logStmt = $pdo->prepare("INSERT INTO logs (admin_id, action, module, time, details, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $logStmt->execute([
            $_SESSION['admin_id'], 
            'DELETE_PRODUCT', 
            'PRODUCT', 
            time(), 
            json_encode([
                'product_id' => $mongoId,
                'action' => 'Product deleted successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ])
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Product deleted successfully'
        ]);
    } else {
        $pdo->rollback();
        echo json_encode([
            'success' => false, 
            'message' => 'Product not found or already deleted'
        ]);
    }
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Delete product error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error deleting product: ' . $e->getMessage()
    ]);
}
?>