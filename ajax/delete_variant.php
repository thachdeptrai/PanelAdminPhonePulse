
<?php
include '../includes/config.php';
include '../includes/functions.php';

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
    $input = json_decode(file_get_contents('php://input'), true);
    $variant_id = $input['id'] ?? 0;

    if (!$variant_id) {
        echo json_encode(['success' => false, 'message' => 'ID biến thể không hợp lệ']);
        exit;
    }

    $pdo->beginTransaction();

    // Delete related cart items
    $deleteCartStmt = $pdo->prepare("DELETE FROM cart WHERE variant_id = ?");
    $deleteCartStmt->execute([$variant_id]);

    // Delete related order items
    $deleteOrderItemsStmt = $pdo->prepare("DELETE FROM order_items WHERE variant_id = ?");
    $deleteOrderItemsStmt->execute([$variant_id]);

    // Delete variant
    $deleteVariantStmt = $pdo->prepare("DELETE FROM variants WHERE id = ?");
    $result = $deleteVariantStmt->execute([$variant_id]);

    if ($result) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Xóa biến thể thành công']);
    } else {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Không thể xóa biến thể']);
    }
} catch (Exception $e) {
    $pdo->rollback();
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
?>
