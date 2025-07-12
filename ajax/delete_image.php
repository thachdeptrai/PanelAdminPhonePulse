
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
    $image_id = $input['id'] ?? 0;

    if (!$image_id) {
        echo json_encode(['success' => false, 'message' => 'ID hình ảnh không hợp lệ']);
        exit;
    }

    // Get image info
    $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();

    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Hình ảnh không tồn tại']);
        exit;
    }

    // Delete physical file
    $image_path = '../../' . ltrim($image['image_url'], '/');
    if (file_exists($image_path)) {
        unlink($image_path);
    }

    // Delete database record
    $deleteStmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
    $result = $deleteStmt->execute([$image_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Xóa hình ảnh thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa hình ảnh']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}
?>
