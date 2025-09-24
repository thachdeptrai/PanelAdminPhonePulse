<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

if (!isAdmin()) {
    header('Location: ../dang_nhap');
    exit;
}

$product_id = $_GET['id'] ?? '';

try {
    $productObjectId = new ObjectId($product_id);
} catch (Exception $e) {
    header('Location: ../products');
    echo '<script>alert("ID sản phẩm không hợp lệ!");</script>';
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$user = $mongoDB->users->findOne(['_id' => new ObjectId($user_id)]);

// Get product with category info
$product = $mongoDB->Product->aggregate([
    ['$match' => ['_id' => new ObjectId($product_id)]],
    ['$lookup' => [
        'from' => 'Category',
        'localField' => 'category_id',
        'foreignField' => '_id',
        'as' => 'category'
    ]],
    ['$unwind' => ['path' => '$category', 'preserveNullAndEmptyArrays' => true]]
])->toArray();
$product = $product[0] ?? null;

if (!$product) {
    echo '<script>alert("Sản phẩm không tồn tại!"); window.location.href="../products";</script>';
    exit;
}

// Get product images
$images = $mongoDB->ProductImage->find([
    'product_id' => new ObjectId($product_id)
], [
    'sort' => ['modified_date' => 1]
]);
$images = iterator_to_array($images);

$variantsCursor = $mongoDB->Variant->aggregate([
    ['$match' => ['product_id' => new ObjectId($product_id)]],
    ['$lookup' => [
        'from' => 'Color',
        'localField' => 'color_id',
        'foreignField' => '_id',
        'as' => 'color'
    ]],
    ['$unwind' => ['path' => '$color', 'preserveNullAndEmptyArrays' => true]],
    ['$lookup' => [
        'from' => 'Size',
        'localField' => 'size_id',
        'foreignField' => '_id',
        'as' => 'size'
    ]],
    ['$unwind' => ['path' => '$size', 'preserveNullAndEmptyArrays' => true]],
    ['$project' => [
        '_id' => 1,
        'product_id' => 1,
        'color_id' => 1,
        'size_id' => 1,
        'quantity' => 1,
        'price' => 1,
        'color_name' => '$color.color_name',
        'size_name' => '$size.size_name',
        'storage' => '$size.storage'
    ]],
    ['$sort' => ['color_name' => 1, 'size_name' => 1]]
]);

$variants = iterator_to_array($variantsCursor);

// ✅ ép kiểu ObjectId thành string để dùng JS cho chắc ăn
$variants = array_map(function ($v) {
    return [
        '_id' => (string)$v['_id'],
        'product_id' => (string)$v['product_id'],
        'color_id' => isset($v['color_id']) ? (string)$v['color_id'] : '',
        'size_id' => isset($v['size_id']) ? (string)$v['size_id'] : '',
        'color_name' => $v['color_name'] ?? '',
        'size_name' => $v['size_name'] ?? '',
        'storage' => $v['storage'] ?? '',
        'quantity' => $v['quantity'] ?? 0,
        'price' => $v['price'] ?? 0,
    ];
}, $variants);


// Get all colors
$colors = $mongoDB->Color->find([], ['sort' => ['color_name' => 1]]);
$colors = iterator_to_array($colors);

// Get all sizes
$sizes = $mongoDB->Size->find([], ['sort' => ['size_name' => 1]]);
$sizes = iterator_to_array($sizes);

// Get all categories
$categories = $mongoDB->Category->find();
$categories = iterator_to_array($categories);

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']) ?> | Phonepulse Admin</title>
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
                <a href="../products" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-semibold"><?php echo htmlspecialchars($product['product_name']) ?></h1>
            </div>
            <div class="flex items-center space-x-4">
                <button onclick="openEditModal()" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg font-medium">
                    Chỉnh sửa
                </button>
                <button onclick="deleteProduct('<?php echo (string)$product['_id'] ?>')" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg font-medium">
                    Xóa sản phẩm
                </button>
                <button onclick="openAddColorModal()" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg font-medium">
            🎨 Thêm màu sắc
            </button>
            <button onclick="openAddSizeModal()" class="bg-pink-600 hover:bg-pink-700 px-4 py-2 rounded-lg font-medium">
            📏 Thêm kích thước
            </button>
            </div>
        </header>

        <!-- Content -->
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Product Images Viewer -->
<div class="space-y-4">
    <h2 class="text-xl font-semibold text-white">📸 Hình ảnh sản phẩm</h2>

    <?php if (empty($images)): ?>
        <div class="bg-white rounded-lg p-8 text-center border border-gray-300">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p class="mt-2 text-gray-500">Chưa có hình ảnh</p>
        </div>
    <?php else: ?>
        <!-- Main Display Image -->
        <div id="mainImageContainer" class="w-full aspect-video rounded-lg overflow-hidden border border-gray-300 bg-white">
            <img id="mainImage"
                 src="<?php echo htmlspecialchars($images[0]['image_url']) ?>"
                 alt="Ảnh chính"
                 class="w-full h-full object-contain p-2 transition" />
        </div>

        <!-- Thumbnails Grid -->
        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-3">
            <?php foreach ($images as $image): ?>
                <div class="relative group cursor-pointer">
                    <img onclick="changeMainImage('<?php echo htmlspecialchars($image['image_url']) ?>')"
                         src="<?php echo htmlspecialchars($image['image_url']) ?>"
                         alt="Thumbnail"
                         class="h-16 w-full object-contain p-1 rounded-lg border border-gray-300 bg-white hover:border-primary transition" />

                    <!-- Delete Button -->
                    <button onclick="deleteImage('<?php echo (string)$image['_id']; ?>')"
                        class="absolute top-0 right-0 bg-black/60 text-red-400 hover:text-red-300 p-1 rounded-bl-lg hidden group-hover:block transition"
                        title="Xoá ảnh">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- Add Image Button -->
    <button onclick="openImageModal()"
            class="w-full bg-dark-light hover:bg-gray-700 border-2 border-dashed border-gray-600 rounded-lg p-4 text-center transition">
        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        <p class="mt-2 text-gray-400">➕ Thêm hình ảnh</p>
    </button>
</div>
                <!-- Product Info -->
                <div class="space-y-6">
                <div class="bg-dark-light rounded-2xl p-6 shadow-lg text-white">
    <h2 class="text-xl font-semibold mb-5">Thông tin sản phẩm</h2>

    <div class="space-y-5 text-sm">
        <div>
            <label class="block text-gray-400 mb-1">Tên sản phẩm</label>
            <p class="text-lg font-semibold"><?php echo htmlspecialchars($product['product_name']) ?></p>
        </div>

        <div>
            <label class="block text-gray-400 mb-1">Danh mục</label>
            <p><?php echo htmlspecialchars($product['category_name'] ?? 'Chưa phân loại') ?></p>
        </div>

        <div>
              <label class="block text-gray-400 mb-1">Các tính năng chính:</label>
            <p class=" whitespace-pre-line p-3 rounded-lg leading-relaxed">
                <?php echo nl2br(htmlspecialchars($product['description'] ?? 'Chưa có mô tả')); ?>
            </p>
        </div>

        <div>
            <label class="block text-gray-400 mb-1">Biến thể</label>

            <?php if (! empty($variants)): ?>
                <ul class="space-y-2">
                    <?php foreach ($variants as $variant): ?>
                        <li class="bg-dark border border-gray-700 rounded-lg p-3">
                            <div class="flex flex-wrap justify-between items-center">
                                <div class="space-y-1">
                                    <p>
                                        <span class="font-medium text-primary"><?php echo strtoupper($product['product_name']) ?></span>
                                        <span class="text-gray-400">(
                                            <?php echo htmlspecialchars($variant['size_name']) ?> -
                                            <?php echo htmlspecialchars($variant['storage']) ?> -
                                            <?php echo htmlspecialchars($variant['color_name']) ?>
                                        )</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-primary font-semibold text-lg">
                                        <?php echo number_format($variant['price'], 0, ',', '.') ?>đ
                                    </p>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-400">Chưa có biến thể nào</p>
            <?php endif; ?>
        </div>
    </div>
</div>


                    <div class="bg-dark-light rounded-2xl p-6 shadow-lg">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-xl font-semibold text-white">Biến thể sản phẩm</h2>
        <button onclick="openVariantModal()" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            + Thêm biến thể
        </button>
    </div>

    <?php if (empty($variants)): ?>
        <div class="text-center py-8 text-gray-400">
            <svg class="mx-auto h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4v10l8 4 8-4V7z"/>
            </svg>
            <p class="mt-2 text-sm">Chưa có biến thể nào</p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($variants as $variant): ?>
                <div class="flex items-center justify-between bg-dark border border-gray-700 rounded-lg p-3 hover:shadow-md transition">
                    <div class="flex items-center space-x-4 text-sm text-white">
                        <!-- Màu -->
                        <div class="flex items-center space-x-2">
                            <div class="w-5 h-5 rounded-full border border-white" style="background-color:                                                                                                           <?php echo $variant['hex_code'] ?? '#ccc' ?>;"></div>
                            <span><?php echo htmlspecialchars($variant['color_name']) ?></span>
                        </div>

                        <!-- Size -->
                        <div class="px-2 py-1 bg-gray-800 text-xs rounded-md font-medium">
                            <?php echo htmlspecialchars($variant['size_name']) ?>
                        </div>

                        <!-- Storage -->
                        <div class="text-gray-400 italic">
                            <?php echo htmlspecialchars($variant['storage']) ?>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button onclick="editVariant('<?php echo(string) $variant['_id'] ?>')"
                                class="text-blue-400 hover:text-blue-300" title="Chỉnh sửa">
                            ✎
                        </button>
                        <button onclick="deleteVariant('<?php echo(string) $variant['_id'] ?>')"
                                class="text-red-400 hover:text-red-300" title="Xóa">
                            🗑
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
<div id="editModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm transition-all duration-300">
    <div class="bg-gradient-to-br from-[#1f1f1f] to-[#2c2c2c] border border-gray-800 shadow-xl rounded-2xl p-8 w-full max-w-3xl max-h-[90vh] overflow-y-auto animate-fade-in">

        <!-- Modal Header -->
        <div class="flex justify-between items-center mb-6 border-b border-gray-700 pb-4">
            <h2 class="text-2xl font-bold text-white tracking-wide">🛠️ Chỉnh sửa sản phẩm</h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-red-500 transition">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
       
        <!-- Form Start -->
        <form id="editForm" class="space-y-6">

            <input type="hidden" name="id" value="<?php echo(string) $product['_id'] ?>">

            <!-- Product Name + Category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-400 mb-1">Tên sản phẩm</label>
                    <input type="text" name="product_name"
                           value="<?php echo htmlspecialchars($product['product_name']) ?>"
                           required
                           class="w-full rounded-xl bg-dark text-black border border-gray-600 px-4 py-2 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-400 mb-1">Danh mục</label>
                    <select name="category_id" required
                            class="w-full rounded-xl bg-dark text-black border border-gray-600 px-4 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition">
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['_id'] ?>"<?php echo $category['_id'] == $product['category_id'] ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Variants and Pricing -->
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-400 mb-2">📦 Biến thể & Giá bán</label>

                <div class="space-y-3">
                    <?php foreach ($variants as $variant): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg border border-gray-700 bg-dark hover:border-primary transition-all">
                            <div class="text-sm text-black space-x-1">
                                <span class="text-white font-semibold uppercase"><?php echo htmlspecialchars($variant['size_name'] ?? '---') ?></span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($variant['storage'] ?? '---') ?>GB</span>
                                <span class="text-gray-300 italic">|                                                                     <?php echo htmlspecialchars($variant['color_name'] ?? '---') ?></span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 text-sm">Giá:</span>
                                <input type="number" name="price[<?php echo $variant['_id'] ?>]"
                                       value="<?php echo $variant['price'] ?>"
                                       required min="0"
                                       class="w-32 rounded-md bg-dark text-black border border-gray-600 px-3 py-1.5 text-right focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition" />
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-semibold text-gray-400 mb-1">✍️ Mô tả sản phẩm</label>
                <textarea name="description" rows="4"
                          class="w-full rounded-xl bg-dark text-black border border-gray-600 px-4 py-2 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition"
                          placeholder="Nhập mô tả sản phẩm..."><?php echo htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-4 pt-3 border-t border-gray-700 mt-4">
                <button type="button"
                        onclick="closeEditModal()"
                        class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition">
                    ❌ Hủy
                </button>
                <button type="submit"
                        class="px-6 py-2 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition duration-200">
                    💾 Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Add Image Modal -->
<div id="imageModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm transition-all">
    <div class="bg-dark-light border border-gray-700 shadow-xl rounded-2xl p-6 w-full max-w-md">
        <!-- Modal Header -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-white">🖼️ Thêm hình ảnh</h2>
            <button onclick="closeImageModal()" class="text-gray-400 hover:text-red-400 transition">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Form -->
        <form id="imageForm" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="product_id" value="<?php echo $product['_id'] ?>">

            <!-- File Upload -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">📁 Chọn ảnh từ thiết bị</label>
                <input type="file" name="images[]" multiple accept="image/*"
                       class="w-full bg-dark border border-gray-600 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-primary" />
            </div>

            <!-- OR Divider -->
            <div class="flex items-center justify-center gap-2 text-gray-400">
                <hr class="flex-grow border-gray-600" />
                <span class="text-sm italic">hoặc</span>
                <hr class="flex-grow border-gray-600" />
            </div>

            <!-- Image URL -->
            <div>
            <input type="hidden" id="imageProductId" name="product_id" value="<?php echo $product['_id'] ?? '' ?>">

                <label class="block text-sm font-medium text-gray-300 mb-2">🌐 Dán liên kết hình ảnh</label>
                <input type="url" name="image_url" placeholder="https://example.com/image.jpg"
                       class="w-full bg-dark border border-gray-600 text-black rounded-lg px-3 py-2 focus:outline-none focus:border-primary" />
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeImageModal()"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">
                    ❌ Hủy
                </button>
                <button type="submit"
                        class="px-5 py-2 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg shadow transition">
                    📤 Tải lên
                </button>
            </div>
        </form>
    </div>
</div>


 <!-- Add Variant Modal -->
<div id="variantModal" class="modal hidden fixed inset-0 flex items-center justify-center z-50 bg-black/50">
    <div class="bg-[#1e1e2f] rounded-2xl p-6 w-full max-w-md shadow-xl">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-white">➕ Thêm biến thể mới</h2>
            <button onclick="closeVariantModal()" class="text-gray-400 hover:text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="variantForm" class="space-y-5 text-sm text-white">
            <input type="hidden" name="product_id" value="<?php echo $product['_id'] ?>">

            <!-- Color -->
            <div>
                <label class="block font-medium mb-1">🎨 Màu sắc</label>
                <select name="color_id" required
                        class="w-full bg-[#2a2a3b] border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                    <option value="">-- Chọn màu --</option>
                    <?php foreach ($colors as $color): ?>
                        <option value="<?php echo $color['_id'] ?>">
                            <?php echo htmlspecialchars($color['color_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Size -->
            <div>
                <label class="block font-medium mb-1">📏 Kích thước</label>
                <select name="size_id" required
                        class="w-full bg-[#2a2a3b] border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                    <option value="">-- Chọn dung lượng --</option>
                    <?php foreach ($sizes as $size): ?>
                        <option value="<?php echo $size['_id'] ?>">
                            <?php echo htmlspecialchars($size['size_name']) ?> -<?php echo htmlspecialchars($size['storage']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Price -->
            <div>
                <label class="block font-medium mb-1">💵 Giá bán</label>
                <input type="number" name="price" required min="0" placeholder="Nhập giá (VNĐ)"
                       class="w-full bg-[#2a2a3b] text-white border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary placeholder-gray-400">
            </div>

            <!-- Quantity -->
            <div>
                <label class="block font-medium mb-1">🔢 Số lượng</label>
                <input type="number" name="quantity" required min="0" placeholder="Nhập số lượng"
                       class="w-full bg-[#2a2a3b] text-white border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary placeholder-gray-400">
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="closeVariantModal()"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    Hủy
                </button>
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold">
                    ✅ Thêm mới
                </button>
            </div>
        </form>
    </div>
</div>
<div id="colorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
  <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md border border-gray-300">
    <div class="flex justify-between items-center border-b pb-3 mb-4">
      <h2 class="text-xl font-bold text-gray-800">🎨 Thêm màu sắc</h2>
      <button onclick="closeColorModal()" class="text-gray-400 hover:text-red-500">❌</button>
    </div>
    <form id="colorForm" class="space-y-4">
      <input type="text" name="color_name" id="colorName" placeholder="Tên màu sắc" required
             class="w-full rounded-lg border border-gray-300 px-4 py-2 bg-gray-100 text-gray-900 focus:border-purple-500 focus:ring-2 focus:ring-purple-300" />
      <div class="flex justify-end gap-2">
        <button type="button" onclick="closeColorModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
          Hủy
        </button>
        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg font-medium">
          ✅ Thêm
        </button>
      </div>
    </form>
  </div>
</div>
<div id="sizeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
  <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md border border-gray-300">
    <div class="flex justify-between items-center border-b pb-3 mb-4">
      <h2 class="text-xl font-bold text-gray-800">📏 Thêm kích thước</h2>
      <button onclick="closeSizeModal()" class="text-gray-400 hover:text-red-500">❌</button>
    </div>
    <form id="sizeForm" class="space-y-4">
      <input type="text" name="size_name" id="sizeName" placeholder="ví dụ: Pro, Pro Max" required
             class="w-full rounded-lg border border-gray-300 px-4 py-2 bg-gray-100 text-gray-900 focus:border-pink-500 focus:ring-2 focus:ring-pink-300" />
      <input type="text" name="storage" id="sizeStorage" placeholder="Ví dụ 1TB , 64GB"
             class="w-full rounded-lg border border-gray-300 px-4 py-2 bg-gray-100 text-gray-900" />
      <div class="flex justify-end gap-2">
        <button type="button" onclick="closeSizeModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
          Hủy
        </button>
        <button type="submit" class="bg-pink-600 hover:bg-pink-700 text-white px-5 py-2 rounded-lg font-medium">
          ✅ Thêm
        </button>
      </div>
    </form>
  </div>
</div>

   <!-- Edit Variant Modal -->
<div id="editVariantModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
    <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-md shadow-xl">
        <!-- Header -->
        <div class="flex justify-between items-center mb-5">
            <h2 class="text-xl font-bold text-white">🎨 Chỉnh sửa biến thể</h2>
            <button onclick="closeEditVariantModal()" class="text-gray-400 hover:text-red-400 transition">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Form -->
        <form id="editVariantForm" class="space-y-4 text-white">
            <input type="hidden" name="variant_id" id="editVariantId">

            <!-- Màu sắc -->
            <div>
                <label class="block text-sm font-medium mb-2">Màu sắc</label>
                <select name="color_id" id="editColorId" required
                        class="w-full bg-gray-800 text-white border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary transition">
                    <option value="">Chọn màu</option>
                    <?php foreach ($colors as $color): ?>
                        <option value="<?php echo (string) $color['_id'] ?>">
                            <?php echo htmlspecialchars($color['color_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Kích thước -->
            <div>
                <label class="block text-sm font-medium mb-2">Kích thước</label>
                <select name="size_id" id="editSizeId" required
                        class="w-full bg-gray-800 text-white border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary transition">
                    <option value="">Chọn kích thước</option>
                    <?php foreach ($sizes as $size): ?>
                        <option value="<?php echo (string)$size['_id'] ?>">
                            <?php echo htmlspecialchars($size['size_name']) ?> -<?php echo htmlspecialchars($size['storage']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Giá -->
            <div>
                <label class="block text-sm font-medium mb-2">Giá (VNĐ)</label>
                <input type="number" name="price" id="editPrice" required min="0"
                       class="w-full bg-gray-800 text-white border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary transition">
            </div>

            <!-- Số lượng -->
            <div>
                <label class="block text-sm font-medium mb-2">Số lượng</label>
                <input type="number" name="quantity" id="editQuantity" required min="0"
                       class="w-full bg-gray-800 text-white border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary transition">
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-4">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white px-5 py-2 rounded-xl font-semibold transition shadow-md hover:shadow-lg">
                    💾 Cập nhật
                </button>
                <button type="button" onclick="closeEditVariantModal()"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded-xl font-medium transition">
                    Hủy
                </button>
            </div>
        </form>
    </div>
</div>

</body>
<script>
        // Modal functions
        function openEditModal() {
            document.getElementById('editModal').classList.remove('hidden');
             document.getElementById('editModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        function openImageModal() {
            document.getElementById('imageModal').classList.remove('hidden');
             document.getElementById('imageModal').classList.add('flex');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
            document.getElementById('imageModal').classList.remove('flex');
        }

        function openVariantModal() {
             document.getElementById('variantModal').classList.remove('hidden');
             document.getElementById('variantModal').classList.add('flex');
        }

        function closeVariantModal() {
            document.getElementById('variantModal').classList.add('hidden');
            document.getElementById('variantModal').classList.remove('flex');
        }


        function openEditVariantModal() {
            document.getElementById('editVariantModal').classList.remove('hidden');
            document.getElementById('editVariantModal').classList.add('flex');
        }

        function closeEditVariantModal() {
            document.getElementById('editVariantModal').classList.remove('flex');
            document.getElementById('editVariantModal').classList.add('hidden');
        }

        // Edit product form submission
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('/ajax/update_product', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cập nhật sản phẩm thành công!');
                    location.reload();
                } else {
                    alert('Lỗi update_product: ' + data.message);
                }
            })
            .catch(async error => {
                console.error('LỖI KHI FETCH:', error);
                alert('Có lỗi xảy ra khi gửi request!');
            });
        });

        // Add image form submission
        document.getElementById('imageForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('/ajax/add_product_images', {
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
                console.error('Error:', error);
                alert('Có lỗi xảy ra!');
            });
        });

        // Add variant form submission
        document.getElementById('variantForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('/ajax/add_variant', {
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
                console.error('Error:', error);
                alert('Có lỗi xảy ra!');
            });
        });

        // Edit variant form submission
        document.getElementById('editVariantForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('/ajax/update_variant.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);       // hoặc toast, hoặc console.log
                    location.reload();         // 🧠 Đây là dòng bạn cần thêm
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Lỗi khi cập nhật:', error);
                alert('Đã xảy ra lỗi khi gửi yêu cầu');
            });
        });

        // Delete functions
        function deleteProduct(productId) {
            if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
                fetch('/ajax/delete_product', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: productId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Xóa sản phẩm thành công!');
                        window.location.href = '../products';
                    } else {
                        alert('Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra!');
                });
            }
        }

        function deleteImage(imageId) {
            if (confirm('Bạn có chắc chắn muốn xóa hình ảnh này?')) {
                fetch('/ajax/delete_image', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: imageId })
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
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra!');
                });
            }
        }

        function deleteVariant(variantId) {
            if (confirm('Bạn có chắc chắn muốn xóa biến thể này?')) {
                fetch('/ajax/delete_variant', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: variantId })
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
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra!');
                });
            }
        }

        function editVariant(variantId) {
        const variants = <?php echo json_encode($variants); ?>;
        const variant = variants.find(v => v.id == variantId || v._id == variantId || (v._id && v._id.$oid == variantId));

        console.log(variant); // In toàn bộ object để debug

        if (variant) {
            console.log("🧪 Gán color_id:", variant.color_id);
        console.log("🧪 Gán size_id:", variant.size_id);
            document.getElementById('editVariantId').value = variantId;
            document.getElementById('editColorId').value = variant.color_id;
            document.getElementById('editSizeId').value = variant.size_id;
            document.getElementById('editPrice').value = variant.price;
            document.getElementById('editQuantity').value = variant.quantity;

            openEditVariantModal();
        }
    }


        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal') && e.target.classList.contains('active')) {
                e.target.classList.remove('active');
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    activeModal.classList.remove('active');
                }
            }
        });
function changeMainImage(url) {
    const mainImage = document.getElementById('mainImage');
    if (mainImage) {
        mainImage.src = url;
    }
}

// Color modal
function openAddColorModal() {
    document.getElementById('colorModal').classList.remove('hidden');
    document.getElementById('colorName').value = '';
    document.getElementById('colorStorage').value = '';
}
function closeColorModal() {
    document.getElementById('colorModal').classList.add('hidden');
}

// Size modal
function openAddSizeModal() {
    document.getElementById('sizeModal').classList.remove('hidden');
    document.getElementById('sizeName').value = '';
    document.getElementById('sizeStorage').value = '';
}
function closeSizeModal() {
    document.getElementById('sizeModal').classList.add('hidden');
}

// Submit color
document.getElementById('colorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const color_name = document.getElementById('colorName').value;

    fetch(`../ajax/add_color.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `color_name=${encodeURIComponent(color_name)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Thêm màu sắc thành công!');
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    });
});

// Submit size
document.getElementById('sizeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const size_name = document.getElementById('sizeName').value;
    const storage = document.getElementById('sizeStorage').value;

    fetch(`../ajax/add_size.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `size_name=${encodeURIComponent(size_name)}&storage=${encodeURIComponent(storage)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Thêm kích thước thành công!');
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    });
});

    </script>
</html>