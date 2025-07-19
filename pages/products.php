<?php 
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle search and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build search query
$searchCondition = '';
$searchParams = [];

if ($search) {
    $searchCondition = "WHERE p.product_name LIKE ? OR p.description LIKE ? OR c.name LIKE ?";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

// Get products with related data
$sql = "SELECT 
            p.id,p.mongo_id, p.product_name, p.description, 
            c.name as category_name,
            SUM(v.quantity) as total_quantity,
            MAX(v.price) as price,
            GROUP_CONCAT(DISTINCT pi.image_url SEPARATOR ',') as images
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.mongo_id
        LEFT JOIN variants v ON p.mongo_id = v.product_id
        LEFT JOIN product_images pi ON p.mongo_id = pi.product_id
        $searchCondition
        GROUP BY p.mongo_id
        ORDER BY p.created_date DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($searchParams);
$products = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT p.mongo_id) as total
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.mongo_id
             $searchCondition";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($searchParams);
$totalProducts = $countStmt->fetch()['total'];
$totalPages = ceil($totalProducts / $limit);

// Get categories for dropdown
$categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $categoriesStmt->fetchAll();

// Get base URL for AJAX calls
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sản phẩm | Phonepulse Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    </style>
</head>
<body class="flex bg-gray-900 text-white">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="content-area ml-64 flex-1 min-h-screen">
        <!-- Header -->
        <header class="bg-dark-light border-b border-dark px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <h1 class="text-2xl font-semibold">Quản lý Sản phẩm</h1>
            <div class="flex items-center space-x-4">
                <button onclick="openAddModal()" class="bg-primary hover:bg-primary-dark px-4 py-2 rounded-lg font-medium">
                    Thêm sản phẩm
                </button>
            </div>
        </header>

        <!-- Content -->
        <div class="p-6">
            <!-- Search and Filter -->
            <div class="mb-6 flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <form method="GET" class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search) ?>" 
                               placeholder="Tìm kiếm sản phẩm..." 
                               class="w-full bg-dark-light border border-gray-600 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:border-primary">
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <button type="submit" class="absolute right-2 top-1 bg-primary hover:bg-primary-dark px-3 py-1 rounded text-sm">
                            Tìm
                        </button>
                    </form>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-dark-light rounded-lg overflow-hidden">
                <table class="w-full">
                <thead class="bg-dark border-b border-gray-600">
                 <tr>
                    <th class="px-6 py-3">Ảnh</th>
                    <th class="px-6 py-3">Tên</th>
                    <th class="px-6 py-3">Mô tả</th>
                    <th class="px-6 py-3">Tồn kho</th>
                    <th class="px-6 py-3">Giá</th>
                    <th class="px-6 py-3">Hành động</th>
                </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600">
    <?php foreach ($products as $product): ?>
    <tr class="hover:bg-gray-700">
        <td class="px-6 py-4">
            <?php 
            $firstImage = $product['images'] ? explode(',', $product['images'])[0] : 'placeholder.jpg';
            ?>
            <img class="h-16 w-16 object-cover rounded" src="<?php echo htmlspecialchars($firstImage) ?>" alt="">
        </td>
        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($product['product_name']) ?></td>
        <td class="px-6 py-4 text-sm text-gray-400"><?php echo htmlspecialchars(substr($product['description'], 0, 80)) ?>...</td>
        <td class="px-6 py-4 text-center"><?php echo (int) $product['total_quantity'] ?></td>
        <td class="px-6 py-4"><?php echo number_format($product['price'], 0, ',', '.') ?>đ</td>
        <td class="px-6 py-4">
            <div class="flex space-x-2">
                <button onclick="viewProduct('<?php echo $product['mongo_id'] ?>')" class="text-blue-400 hover:text-blue-300 p-1" title="Xem">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </button>
                <button onclick="editProduct('<?php echo $product['mongo_id'] ?>')" class="text-yellow-400 hover:text-yellow-300 p-1" title="Sửa">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </button>
                <button onclick="deleteProduct('<?php echo $product['mongo_id'] ?>')" class="text-red-400 hover:text-red-300 p-1" title="Xóa">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex items-center justify-between">
                <div class="text-sm text-gray-400">
                    Hiển thị <?php echo count($products) ?> trong tổng số <?php echo $totalProducts ?> sản phẩm
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1 ?>&search=<?php echo urlencode($search) ?>" 
                       class="px-3 py-2 bg-dark-light rounded-lg hover:bg-gray-600">Trước</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i ?>&search=<?php echo urlencode($search) ?>" 
                       class="px-3 py-2 <?php echo $i == $page ? 'bg-primary' : 'bg-dark-light hover:bg-gray-600' ?> rounded-lg">
                        <?php echo $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1 ?>&search=<?php echo urlencode($search) ?>" 
                       class="px-3 py-2 bg-dark-light rounded-lg hover:bg-gray-600">Tiếp</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-gray-200">
        <!-- Header -->
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h2 id="modalTitle" class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                🛒 <span>Thêm sản phẩm</span>
            </h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 transition duration-150">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Form -->
        <form id="productForm" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" id="productId" name="id">

            <!-- Tên + Danh mục -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Tên -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên sản phẩm</label>
                    <input type="text" id="productName" name="product_name" required
                           class="w-full rounded-xl border border-gray-300 bg-gray-100 px-4 py-2 text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-400 outline-none transition" />
                </div>

                <!-- Danh mục -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Danh mục</label>
                    <select id="categoryId" name="category_id" required
                            class="w-full rounded-xl border border-gray-300 bg-gray-100 px-4 py-2 text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-400 outline-none transition">
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['mongo_id'] ?>">
                                <?php echo htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Mô tả -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
                <textarea id="productDescription" name="description" rows="4"
                          class="w-full rounded-xl border border-gray-300 bg-gray-100 px-4 py-2 text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-400 outline-none transition"></textarea>
            </div>

            <!-- Nút hành động -->
            <div class="pt-6 flex justify-end gap-3">
                <button type="button" onclick="closeModal()"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-5 py-2 rounded-xl font-medium transition">
                    ❌ Hủy
                </button>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-xl font-semibold shadow-md transition">
                    💾 Lưu sản phẩm
                </button>
            </div>
        </form>
    </div>
</div>


    <script>
        // Get base URL dynamically
        const baseUrl = '<?php echo $baseUrl; ?>';
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Thêm sản phẩm';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('productModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        function editProduct(mongoId) {
            // Use AJAX endpoint for getting product data
            fetch(`${baseUrl}/ajax/get_product.php?id=${mongoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = 'Chỉnh sửa sản phẩm';
                        document.getElementById('productId').value = data.product.mongo_id;
                        document.getElementById('productName').value = data.product.product_name;
                        document.getElementById('categoryId').value = data.product.category_id;
                        if (data.variants && data.variants.length > 0) {
                            document.getElementById('productPrice').value = data.variants[0].price;
                        }
                        if (data.variants && data.variants.length > 0) {
                            document.getElementById('productQuantity').value = data.variants[0].quantity;
                        }
                        document.getElementById('productDescription').value = data.product.description;
                        document.getElementById('productModal').classList.remove('hidden');
                    } else {
                        alert('Không thể tải dữ liệu sản phẩm: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi tải dữ liệu sản phẩm');
                });
        }

        function deleteProduct(mongoId) {
            if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
                fetch(`${baseUrl}/ajax/delete_product.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${mongoId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Có lỗi xảy ra: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi xóa sản phẩm');
                });
            }
        }

        function viewProduct(mongoId) {
            // Navigate to product detail page with friendly URL
            window.location.href = `${baseUrl}/product_detail/${mongoId}`;
        }

        // Handle form submission
        document.getElementById('productForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const isUpdate = document.getElementById('productId').value !== '';
            
            const endpoint = isUpdate ? 
                `${baseUrl}/ajax/update_product.php` : 
                `${baseUrl}/ajax/add_product.php`;
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(isUpdate ? 'Cập nhật sản phẩm thành công!' : 'Thêm sản phẩm thành công!');
                    location.reload();
                } else {
                    alert('Có lỗi xảy ra: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi lưu sản phẩm');
            });
        });
    </script>
</body>
</html>