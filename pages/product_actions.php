<?php
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

// Get product ID
$product_id = $_GET['id'] ?? 0;

if (!$product_id) {
    header('Location: products');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get product with category info
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.mongo_id 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products');
    exit;
}

// Get product images
$stmt = $pdo->prepare("SELECT * FROM productimages WHERE product_id = ? ORDER BY created_at");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll();

// Get variants with color and size info
$stmt = $pdo->prepare("
    SELECT v.*, c.name as color_name, c.hex_code as color_hex, 
           s.name as size_name, s.value as size_value
    FROM variants v
    LEFT JOIN colors c ON v.color_id = c.mongo_id
    LEFT JOIN sizes s ON v.size_id = s.mongo_id
    WHERE v.product_id = ?
    ORDER BY c.name, s.name
");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();

// Get all colors and sizes for adding new variants
$colorsStmt = $pdo->query("SELECT * FROM colors ORDER BY name");
$colors = $colorsStmt->fetchAll();

$sizesStmt = $pdo->query("SELECT * FROM sizes ORDER BY name");
$sizes = $sizesStmt->fetchAll();

// Get all categories for edit form
$categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $categoriesStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']) ?> | Phonepulse Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="flex bg-gray-900 text-white">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="content-area ml-64 flex-1 min-h-screen">
        <!-- Header -->
        <header class="bg-dark-light border-b border-dark px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center space-x-4">
                <a href="products" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-semibold"><?php echo htmlspecialchars($product['name']) ?></h1>
            </div>
            <div class="flex items-center space-x-4">
                <button onclick="openEditModal()" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg font-medium">
                    Chỉnh sửa
                </button>
                <button onclick="deleteProduct(<?php echo $product['id'] ?>)" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg font-medium">
                    Xóa sản phẩm
                </button>
            </div>
        </header>

        <!-- Content -->
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Product Images -->
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold">Hình ảnh sản phẩm</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <?php if (empty($images)): ?>
                        <div class="col-span-2 bg-dark-light rounded-lg p-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="mt-2 text-gray-400">Chưa có hình ảnh</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($images as $image): ?>
                        <div class="relative group">
                            <img src="<?php echo htmlspecialchars($image['image_url']) ?>" alt="Product image" 
                                 class="w-full h-48 object-cover rounded-lg">
                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                <button onclick="deleteImage(<?php echo $image['id'] ?>)" 
                                        class="text-red-400 hover:text-red-300" title="Xóa ảnh">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add Image Button -->
                    <button onclick="openImageModal()" class="w-full bg-dark-light hover:bg-gray-700 border-2 border-dashed border-gray-600 rounded-lg p-4 text-center">
                        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <p class="mt-2 text-gray-400">Thêm hình ảnh</p>
                    </button>
                </div>

                <!-- Product Info -->
                <div class="space-y-6">
                    <div class="bg-dark-light rounded-lg p-6">
                        <h2 class="text-xl font-semibold mb-4">Thông tin sản phẩm</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-400">Tên sản phẩm</label>
                                <p class="mt-1 text-lg"><?php echo htmlspecialchars($product['name']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400">Danh mục</label>
                                <p class="mt-1"><?php echo htmlspecialchars($product['category_name'] ?? 'Chưa phân loại') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400">Giá</label>
                                <p class="mt-1 text-lg font-semibold text-primary"><?php echo number_format($product['price'], 0, ',', '.') ?>đ</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400">Trạng thái</label>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $product['status'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?php echo $product['status'] == 1 ? 'Hoạt động' : 'Tạm ngưng' ?>
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400">Mô tả</label>
                                <p class="mt-1 text-gray-300"><?php echo htmlspecialchars($product['description'] ?? 'Chưa có mô tả') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Variants -->
                    <div class="bg-dark-light rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold">Biến thể sản phẩm</h2>
                            <button onclick="openVariantModal()" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg text-sm font-medium">
                                Thêm biến thể
                            </button>
                        </div>
                        
                        <?php if (empty($variants)): ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"></path>
                            </svg>
                            <p class="mt-2 text-gray-400">Chưa có biến thể nào</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($variants as $variant): ?>
                            <div class="flex items-center justify-between p-3 bg-dark border border-gray-600 rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-4 h-4 rounded-full border-2 border-gray-400" 
                                             style="background-color: <?php echo $variant['color_hex'] ?? '#666' ?>"></div>
                                        <span><?php echo htmlspecialchars($variant['color_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        Size: <?php echo htmlspecialchars($variant['size_name'] ?? 'N/A') ?>
                                    </div>
                                    <div class="text-sm">
                                        Số lượng: <span class="font-semibold"><?php echo $variant['quantity'] ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button onclick="editVariant(<?php echo $variant['id'] ?>)" 
                                            class="text-blue-400 hover:text-blue-300" title="Chỉnh sửa">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button onclick="deleteVariant(<?php echo $variant['id'] ?>)" 
                                            class="text-red-400 hover:text-red-300" title="Xóa biến thể">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-dark-light rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">Chỉnh sửa sản phẩm</h2>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="editForm">
                <input type="hidden" name="id" value="<?php echo $product['id'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Tên sản phẩm</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']) ?>" required
                               class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Danh mục</label>
                        <select name="category_id" required class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                            <option value="">Chọn danh mục</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['mongo_id'] ?>" 
                                    <?php echo $category['mongo_id'] == $product['category_id'] ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($category['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Giá (VNĐ)</label>
                        <input type="number" name="price" value="<?php echo $product['price'] ?>" required min="0"
                               class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Trạng thái</label>
                        <select name="status" class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                            <option value="1" <?php echo $product['status'] == 1 ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="0" <?php echo $product['status'] == 0 ? 'selected' : '' ?>>Tạm ngưng</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="block text-sm font-medium mb-2">Mô tả</label>
                    <textarea name="description" rows="4" 
                              class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary"
                              placeholder="Mô tả sản phẩm..."><?php echo htmlspecialchars($product['description'] ?? '') ?></textarea>
                </div>
                
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                    <button type="submit" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg font-medium">
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Image Modal -->
    <div id="imageModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-dark-light rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Thêm hình ảnh</h2>
                <button onclick="closeImageModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="imageForm" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?php echo $product['id'] ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Chọn hình ảnh</label>
                    <input type="file" name="images[]" multiple accept="image/*" required
                           class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                </div>
                
                <div class="flex space-x-4">
                    <button type="submit" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg font-medium">
                        Tải lên
                    </button>
                    <button type="button" onclick="closeImageModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Variant Modal -->
    <div id="variantModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-dark-light rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Thêm biến thể</h2>
                <button onclick="closeVariantModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="variantForm">
                <input type="hidden" name="product_id" value="<?php echo $product['id'] ?>">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Màu sắc</label>
                        <select name="color_id" required class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                            <option value="">Chọn màu</option>
                            <?php foreach ($colors as $color): ?>
                            <option value="<?php echo $color['mongo_id'] ?>" data-hex="<?php echo $color['hex_code'] ?>">
                                <?php echo htmlspecialchars($color['name']) ?>
                            </option>
                           
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Kích thước</label>
                        <select name="size_id" required class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                            <option value="">Chọn kích thước</option>
                            <?php foreach ($sizes as $size): ?>
                            <option value="<?php echo $size['mongo_id'] ?>">
                                <?php echo htmlspecialchars($size['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Số lượng</label>
                        <input type="number" name="quantity" required min="0" value="1"
                               class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                    </div>
                </div>
                
                <div class="flex space-x-4 mt-6">
                    <button type="submit" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg font-medium">
                        Thêm biến thể
                    </button>
                    <button type="button" onclick="closeVariantModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Variant Modal -->
    <div id="editVariantModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-dark-light rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Chỉnh sửa biến thể</h2>
                <button onclick="closeEditVariantModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="editVariantForm">
                <input type="hidden" name="variant_id" id="editVariantId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Màu sắc</label>
                        <select name="color_id" id="editColorId" required class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                            <option value="">Chọn màu</option>
                            <?php foreach ($colors as $color): ?>
                            <option value="<?php echo $color['mongo_id'] ?>" data-hex="<?php echo $color['hex_code'] ?>">
                                <?php echo htmlspecialchars($color['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Kích thước</label>
                        <select name="size_id" id="editSizeId" required class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                            <option value="">Chọn kích thước</option>
                            <?php foreach ($sizes as $size): ?>
                            <option value="<?php echo $size['mongo_id'] ?>">
                                <?php echo htmlspecialchars($size['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Số lượng</label>
                        <input type="number" name="quantity" id="editQuantity" required min="0"
                               class="w-full bg-dark border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                    </div>
                </div>
                
                <div class="flex space-x-4 mt-6">
                    <button type="submit" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg font-medium">
                        Cập nhật
                    </button>
                    <button type="button" onclick="closeEditVariantModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditModal() {
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function openImageModal() {
            document.getElementById('imageModal').classList.add('active');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        function openVariantModal() {
            document.getElementById('variantModal').classList.add('active');
        }

        function closeVariantModal() {
            document.getElementById('variantModal').classList.remove('active');
        }

        function openEditVariantModal() {
            document.getElementById('editVariantModal').classList.add('active');
        }

        function closeEditVariantModal() {
            document.getElementById('editVariantModal').classList.remove('active');
        }

        // Edit product form
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/update_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cập nhật sản phẩm thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                alert('Đã xảy ra lỗi khi cập nhật sản phẩm');
                console.error('Error:', error);
            });
        });

        // Add image form
        document.getElementById('imageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/add_product_images.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Thêm hình ảnh thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                alert('Đã xảy ra lỗi khi thêm hình ảnh');
                console.error('Error:', error);
            });
        });

        // Add variant form
        document.getElementById('variantForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/add_variant.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Thêm biến thể thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                alert('Đã xảy ra lỗi khi thêm biến thể');
                console.error('Error:', error);
            });
        });

        // Edit variant form
        document.getElementById('editVariantForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/update_variant.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cập nhật biến thể thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                alert('Đã xảy ra lỗi khi cập nhật biến thể');
                console.error('Error:', error);
            });
        });

        // Delete functions
        function deleteProduct(productId) {
            if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
                fetch('ajax/delete_product.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: productId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Xóa sản phẩm thành công!');
                        window.location.href = 'products';
                    } else {
                        alert('Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Đã xảy ra lỗi khi xóa sản phẩm');
                    console.error('Error:', error);
                });
            }
        }

        function deleteImage(imageId) {
            if (confirm('Bạn có chắc chắn muốn xóa hình ảnh này?')) {
                fetch('ajax/delete_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: imageId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Xóa hình ảnh thành công!');
                        location.reload();
                    } else {
                        alert('Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Đã xảy ra lỗi khi xóa hình ảnh');
                    console.error('Error:', error);
                });
            }
        }

        function deleteVariant(variantId) {
            if (confirm('Bạn có chắc chắn muốn xóa biến thể này?')) {
                fetch('ajax/delete_variant.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: variantId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Xóa biến thể thành công!');
                        location.reload();
                    } else {
                        alert('Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Đã xảy ra lỗi khi xóa biến thể');
                    console.error('Error:', error);
                });
            }
        }

        // Edit variant function
        function editVariant(variantId) {
            // Fetch variant data
            fetch('ajax/get_variant.php?id=' + variantId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const variant = data.variant;
                    document.getElementById('editVariantId').value = variant.id;
                    document.getElementById('editColorId').value = variant.color_id;
                    document.getElementById('editSizeId').value = variant.size_id;
                    document.getElementById('editQuantity').value = variant.quantity;
                    openEditVariantModal();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                alert('Đã xảy ra lỗi khi tải thông tin biến thể');
                console.error('Error:', error);
            });
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });

        // Handle file input preview
        document.querySelector('input[name="images[]"]').addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length > 5) {
                alert('Bạn chỉ có thể chọn tối đa 5 hình ảnh');
                e.target.value = '';
            }
        });
    </script>
</body>
</html>