<?php
include '../includes/config.php';
include '../includes/functions.php';

header('Content-Type: application/json');

// Kiểm tra quyền admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Chỉ nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

try {
    $name        = trim($_POST['product_name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $image_url   = trim($_POST['image_url'] ?? '');

    // Validate cơ bản
    if (empty($name) || empty($category_id)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit;
    }

    // Thêm sản phẩm
    $stmt = $pdo->prepare("INSERT INTO products (product_name, category_id, description, created_date, modified_date) 
                           VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$name, $category_id, $description]);
    $productId = $pdo->lastInsertId();

    // === XỬ LÝ ẢNH ===
    $finalImageUrl = ''; // ảnh cuối cùng sẽ lưu vào DB

    // Ưu tiên ảnh upload từ máy
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $newName = uniqid('product_') . '.' . $ext;
        $uploadPath = '../uploads/products/' . $newName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $finalImageUrl = '/uploads/products/' . $newName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể lưu ảnh được upload']);
            exit;
        }

    // Nếu không có ảnh upload thì xử lý ảnh từ link
    } elseif (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
        // Validate đuôi ảnh
        $validExt = ['jpg','jpeg','png','webp','gif'];
        $urlExt = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (!in_array($urlExt, $validExt)) {
            echo json_encode(['success' => false, 'message' => 'Định dạng ảnh từ URL không hợp lệ']);
            exit;
        }

        // Dùng trực tiếp link (không tải về)
        $finalImageUrl = $image_url;
    }

    // Ghi vào bảng product_images nếu có ảnh
    if (!empty($finalImageUrl)) {
        $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, created_at, updated_at) 
                               VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$productId, $finalImageUrl]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Thêm sản phẩm thành công!',
        'product_id' => $productId
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
