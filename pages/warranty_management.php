<?php
require_once '../includes/config.php'; // có $mongoDB
require_once '../includes/functions.php';
use MongoDB\BSON\ObjectId;

// ✅ Check quyền admin
if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

// ✅ Check session
$user_id_raw = $_SESSION['user_id'] ?? null;
if (!$user_id_raw) {
    header('Location: dang_nhap');
    exit;
}

// ✅ Validate ObjectId
try {
    $user_id = new ObjectId($user_id_raw);
} catch (Exception $e) {
    die("ID phiên không hợp lệ");
}

// ✅ Lấy thông tin user
$user = $mongoDB->users->findOne(['_id' => $user_id]);
if (!$user) {
    die("Không tìm thấy người dùng");
}

// ✅ Xử lý các hành động CRUD
$action = $_GET['action'] ?? '';
$warranty_id = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// ✅ Xử lý thêm/sửa bảo hành
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['orderId'] ?? '');
    $userId = trim($_POST['userId'] ?? '');
    $productId = trim($_POST['productId'] ?? '');
    $variantId = trim($_POST['variantId'] ?? '');
    $warranty_code = trim($_POST['warranty_code'] ?? '');
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $note = trim($_POST['note'] ?? '');

    try {
        if ($action === 'edit' && $warranty_id) {
            // Cập nhật bảo hành
            $result = $mongoDB->warranties->updateOne(
                ['_id' => new ObjectId($warranty_id)],
                ['$set' => [
                    'orderId' => new ObjectId($orderId),
                    'userId' => new ObjectId($userId),
                    'productId' => new ObjectId($productId),
                    'variantId' => !empty($variantId) ? new ObjectId($variantId) : null,
                    'warranty_code' => $warranty_code,
                    'start_date' => new MongoDB\BSON\UTCDateTime(strtotime($start_date) * 1000),
                    'end_date' => new MongoDB\BSON\UTCDateTime(strtotime($end_date) * 1000),
                    'status' => $status,
                    'note' => $note,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            $message = 'Cập nhật bảo hành thành công!';
            $messageType = 'success';
        } else {
            // Thêm bảo hành mới
            $result = $mongoDB->warranties->insertOne([
                'orderId' => new ObjectId($orderId),
                'userId' => new ObjectId($userId),
                'productId' => new ObjectId($productId),
                'variantId' => !empty($variantId) ? new ObjectId($variantId) : null,
                'warranty_code' => $warranty_code,
                'start_date' => new MongoDB\BSON\UTCDateTime(strtotime($start_date) * 1000),
                'end_date' => new MongoDB\BSON\UTCDateTime(strtotime($end_date) * 1000),
                'status' => $status,
                'note' => $note,
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);
            $message = 'Thêm bảo hành thành công!';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Lỗi: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ✅ Xử lý xóa bảo hành
if ($action === 'delete' && $warranty_id) {
    try {
        $mongoDB->warranties->deleteOne(['_id' => new ObjectId($warranty_id)]);
        $message = 'Xóa bảo hành thành công!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Lỗi: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ✅ Lấy dữ liệu để edit
$editWarranty = null;
if ($action === 'edit' && $warranty_id) {
    try {
        $editWarranty = $mongoDB->warranties->findOne(['_id' => new ObjectId($warranty_id)]);
    } catch (Exception $e) {
        $message = 'Không tìm thấy bảo hành!';
        $messageType = 'error';
    }
}

// ✅ Tìm kiếm và bộ lọc
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$filter = [];
$options = ['sort' => ['createdAt' => -1]];

if (!empty($search)) {
    $filter['warranty_code'] = ['$regex' => $search, '$options' => 'i'];
}

if (!empty($statusFilter)) {
    $filter['status'] = $statusFilter;
}

if (!empty($dateFrom) && !empty($dateTo)) {
    $filter['start_date'] = [
        '$gte' => new MongoDB\BSON\UTCDateTime(strtotime($dateFrom) * 1000),
        '$lte' => new MongoDB\BSON\UTCDateTime(strtotime($dateTo . ' 23:59:59') * 1000)
    ];
}

// ✅ Phân trang
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$skip = ($page - 1) * $limit;

$totalWarranties = $mongoDB->warranties->countDocuments($filter);
$totalPages = ceil($totalWarranties / $limit);

$options['limit'] = $limit;
$options['skip'] = $skip;

// ✅ Lấy danh sách bảo hành
$warranties = [];
$cursor = $mongoDB->warranties->find($filter, $options);

foreach ($cursor as $warranty) {
    // Lấy thông tin liên quan
    $order = null;
    $customer = null;
    $product = null;
    $variant = null;
    $color = null;
    $size = null;
    $variantName = '';
    try {
        $order = $mongoDB->orders->findOne(['_id' => $warranty['orderId']]);
        $customer = $mongoDB->users->findOne(['_id' => $warranty['userId']]);
        $product = $mongoDB->Product->findOne(['_id' => $warranty['productId']]);
        if (!empty($warranty['variantId'])) {
            $variant = $mongoDB->Variant->findOne(['_id' => $warranty['variantId']]);
        }
    } catch (Exception $e) {}
    if ($variant) {
        $colorDoc = $mongoDB->Color->findOne(['_id' => $variant['color_id']]);
        $sizeDoc  = $mongoDB->Size->findOne(['_id' => $variant['size_id']]);
    
        $color = $colorDoc['color_name'] ?? '';
        $size  = $sizeDoc['size_name'] ?? '';
    
        $variantName = trim($size . ' - ' . $color);
    }
    
    $warranties[] = [
        'warranty' => $warranty,
        'order' => $order,
        'customer' => $customer,
        'product' => $product,
       'variant'  => [
        'name' => $variantName
       ]
    ];
}

// ✅ Lấy danh sách cho dropdown
$orders = $mongoDB->orders->find([], ['sort' => ['created_date' => -1]]);
$customers = $mongoDB->users->find([], ['sort' => ['name' => 1]]);
$products = $mongoDB->Product->find([], ['sort' => ['product_name' => 1]]);
include '../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Bảo Hành - Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/warranty.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        /* Custom styles cho input với text trắng */
        .input-field {
            background: #1e293b;
            border: 1px solid #374151;
            color: #ffffff;
        }
        
        .input-field::placeholder {
            color: #9ca3af;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #00FF87;
            box-shadow: 0 0 0 2px rgba(0, 255, 135, 0.2);
        }
        
        .select-field {
            background: #1e293b;
            border: 1px solid #374151;
            color: #ffffff;
        }
        
        .select-field:focus {
            outline: none;
            border-color: #00FF87;
            box-shadow: 0 0 0 2px rgba(0, 255, 135, 0.2);
        }
        
        .select-field option {
            background: #1e293b;
            color: #ffffff;
        }
        
        .modal {
            backdrop-filter: blur(10px);
        }
        
        .status-active { @apply bg-green-500 text-white; }
        .status-expired { @apply bg-red-500 text-white; }
        .status-cancelled { @apply bg-gray-500 text-white; }
        
        .btn-primary {
            background: linear-gradient(45deg, #00FF87, #00D4AA);
            border: none;
            color: #000;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #00D4AA, #00FF87);
        }
    </style>
</head>
<body class="flex bg-dark">

    <!-- Main Content -->
    <div class="content-area ml-64 flex-1 min-h-screen">
        <!-- Top Navigation -->
        <header class="bg-dark-light border-b border-dark px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <h1 class="text-2xl font-semibold text-white">Quản Lý Bảo Hành</h1>

            <div class="flex items-center space-x-4">
                <button class="relative text-gray-400 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500"></span>
                </button>

                <div class="relative">
                    <button class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-primary-light flex items-center justify-center text-white">
                            <?php echo strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="hidden md:inline text-white"><?php echo htmlspecialchars(explode('@', $user['name'])[0]) ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="p-6">
            
            <!-- Thông báo -->
            <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-900 text-green-300 border border-green-700' : 'bg-red-900 text-red-300 border border-red-700' ?>">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?php if ($messageType === 'success'): ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        <?php else: ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        <?php endif; ?>
                    </svg>
                    <?= htmlspecialchars($message) ?>
                   
                </div>
            </div>
            <?php endif; ?>

            <!-- Header Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <h2 class="text-xl font-semibold text-white">Danh Sách Bảo Hành</h2>
                <button onclick="openModal()" class="btn-primary px-6 py-2 rounded-lg hover:opacity-90 transition-all">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Thêm Bảo Hành
                </button>
            </div>

            <!-- Bộ lọc và tìm kiếm -->
            <div class="card p-6 rounded-lg mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Tìm theo mã bảo hành</label>
                        <input type="text" name="search" placeholder="Nhập mã bảo hành..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                        <select name="status" class="select-field w-full px-4 py-2 rounded-lg">
                            <option value="">Tất cả</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                            <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Hết hạn</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Từ ngày</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                               class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Đến ngày</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                               class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    
                    <div class="md:col-span-4 flex gap-2">
                        <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Tìm kiếm
                        </button>
                        <a href="?" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all">
                            Xóa bộ lọc
                        </a>
                    </div>
                </form>
            </div>

            <!-- Bảng danh sách -->
            <div class="card rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-dark-light">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Mã Bảo Hành</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Khách Hàng</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Sản Phẩm</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Đơn Hàng</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Ngày Bắt Đầu</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Ngày Hết Hạn</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Trạng Thái</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-gray-300">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-dark-light">
                            <?php if (empty($warranties)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-400">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    Không có bảo hành nào
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($warranties as $item): 
                                    $warranty = $item['warranty'];
                                    $customer = $item['customer'];
                                    $product = $item['product'];
                                    $order = $item['order'];
                                ?>
                                <tr class="hover:bg-dark-light transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm text-primary"><?= htmlspecialchars($warranty['warranty_code']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-white"><?= htmlspecialchars($customer['name'] ?? 'N/A') ?></div>
                                        <div class="text-sm text-gray-400"><?= htmlspecialchars($customer['email'] ?? '') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-white"><?= htmlspecialchars($product['product_name'] ?? 'N/A') ?></div>
                                        <?php if (!empty($item['variant'])): ?>
                                        <div class="text-sm text-gray-400">
                                          <?= htmlspecialchars($item['variant']['name'] ?? '') ?>
                                        </div>
                                    <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-primary">#<?= substr((string)$warranty['orderId'], -6) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-300">
                                        <?= $warranty['start_date']->toDateTime()->format('d/m/Y') ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-300">
                                        <?= $warranty['end_date']->toDateTime()->format('d/m/Y') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full status-<?= $warranty['status'] ?>">
                                            <?php 
                                            $statusText = [
                                                'active' => 'Hoạt động',
                                                'expired' => 'Hết hạn', 
                                                'cancelled' => 'Đã hủy'
                                            ];
                                            echo $statusText[$warranty['status']] ?? $warranty['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2">
                                            <button onclick="editWarranty('<?= (string)$warranty['_id'] ?>')" 
                                                    class="text-blue-400 hover:text-blue-300">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            <button onclick="deleteWarranty('<?= (string)$warranty['_id'] ?>')" 
                                                    class="text-red-400 hover:text-red-300">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                <div class="bg-dark-light px-6 py-4 border-t border-dark">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Hiển thị <?= ($page - 1) * $limit + 1 ?> - <?= min($page * $limit, $totalWarranties) ?> trong <?= $totalWarranties ?> bảo hành
                        </div>
                        <div class="flex space-x-1">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page'), '', '&') ?>" 
                               class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600">Trước</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page'), '', '&') ?>" 
                               class="px-3 py-2 <?= $i === $page ? 'bg-primary text-black' : 'bg-gray-700 text-white hover:bg-gray-600' ?> rounded">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page'), '', '&') ?>" 
                               class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600">Sau</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal thêm/sửa bảo hành -->
    <div id="warrantyModal" class="hidden fixed inset-0 bg-black bg-opacity-75 modal z-50 flex items-center justify-center p-4">
        <div class="bg-dark-light rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
            <form id="warrantyForm" method="POST">
                <div class="p-6 border-b border-dark">
                    <div class="flex justify-between items-center">
                        <h3 id="modalTitle" class="text-xl font-semibold text-white">Thêm Bảo Hành Mới</h3>
                        <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Đơn Hàng *</label>
                            <select name="orderId" required class="select-field w-full px-4 py-2 rounded-lg">
                                <option value="">-- Chọn đơn hàng --</option>
                                <?php foreach ($orders as $order): ?>
                                <option value="<?= (string)$order['_id'] ?>"><?= '#' . substr((string)$order['_id'], -6) ?> - <?= number_format($order['final_price'] ?? 0) ?>đ</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Khách Hàng *</label>
                            <select name="userId" required class="select-field w-full px-4 py-2 rounded-lg">
                                <option value="">-- Chọn khách hàng --</option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?= (string)$customer['_id'] ?>"><?= htmlspecialchars($customer['name']) ?> - <?= htmlspecialchars($customer['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Sản Phẩm *</label>
                            <select name="productId" required class="select-field w-full px-4 py-2 rounded-lg" onchange="loadVariants(this.value)">
                                <option value="">-- Chọn sản phẩm --</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?= (string)$product['_id'] ?>"><?= htmlspecialchars($product['product_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Biến Thể</label>
                            <select name="variantId" id="variantSelect" class="select-field w-full px-4 py-2 rounded-lg">
                                <option value="">-- Chọn biến thể --</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Mã Bảo Hành *</label>
                        <input type="text" name="warranty_code" required placeholder="VD: BH-2025-001" 
                               class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Ngày Bắt Đầu *</label>
                            <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"
                                   class="input-field w-full px-4 py-2 rounded-lg" onchange="updateEndDate()">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Ngày Hết Hạn *</label>
                            <input type="date" name="end_date" required 
                                   class="input-field w-full px-4 py-2 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Trạng Thái</label>
                            <select name="status" class="select-field w-full px-4 py-2 rounded-lg">
                                <option value="active">Hoạt động</option>
                                <option value="expired">Hết hạn</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Ghi Chú</label>
                        <textarea name="note" rows="3" placeholder="Ghi chú về bảo hành..."
                                  class="input-field w-full px-4 py-2 rounded-lg resize-none"></textarea>
                    </div>
                </div>
                
                <div class="p-6 border-t border-dark flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" 
                            class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all">
                        Hủy
                    </button>
                    <button type="submit" 
                            class="btn-primary px-6 py-2 rounded-lg hover:opacity-90 transition-all">
                        <span id="submitText">Thêm Bảo Hành</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function clearEditParams() {
            const url = new URL(window.location.href);
            url.searchParams.delete("action");
            url.searchParams.delete("id");
            window.history.replaceState({}, document.title, url.pathname + (url.search ? "?" + url.search : ""));
        }

    // ================= MODAL MANAGEMENT =================
    function openModal() {
        document.getElementById('warrantyModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Thêm Bảo Hành Mới';
        document.getElementById('submitText').textContent = 'Thêm Bảo Hành';
        document.getElementById('warrantyForm').action = '?action=add';
        
        // Reset form
        document.getElementById('warrantyForm').reset();
        document.querySelector('input[name="start_date"]').value = new Date().toISOString().split('T')[0];
        updateEndDate();
    }

    function closeModal() {
        document.getElementById('warrantyModal').classList.add('hidden');
        document.getElementById('warrantyForm').reset();
        document.getElementById('variantSelect').innerHTML = '<option value="">-- Chọn biến thể --</option>'; clearEditParams();
    }

    // ================= EDIT FUNCTIONALITY =================
    function editWarranty(id) {
        // Redirect to edit page
        window.location.href = '?action=edit&id=' + id;
    }

    <?php if ($action === 'edit' && $editWarranty): ?>
    // Auto-open modal for editing
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('warrantyModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Chỉnh Sửa Bảo Hành';
        document.getElementById('submitText').textContent = 'Cập Nhật';
        document.getElementById('warrantyForm').action = '?action=edit&id=<?= $warranty_id ?>';
        
        // Populate form with existing data
        const form = document.getElementById('warrantyForm');
        form.orderId.value = '<?= (string)$editWarranty['orderId'] ?>';
        form.userId.value = '<?= (string)$editWarranty['userId'] ?>';
        form.productId.value = '<?= (string)$editWarranty['productId'] ?>';
        form.warranty_code.value = '<?= htmlspecialchars($editWarranty['warranty_code']) ?>';
        form.start_date.value = '<?= $editWarranty['start_date']->toDateTime()->format('Y-m-d') ?>';
        form.end_date.value = '<?= $editWarranty['end_date']->toDateTime()->format('Y-m-d') ?>';
        form.status.value = '<?= $editWarranty['status'] ?>';
        form.note.value = '<?= htmlspecialchars($editWarranty['note'] ?? '') ?>';
        
        // Load variants for selected product
        <?php if (!empty($editWarranty['variantId'])): ?>
        loadVariants('<?= (string)$editWarranty['productId'] ?>', '<?= (string)$editWarranty['variantId'] ?>');
        <?php else: ?>
        loadVariants('<?= (string)$editWarranty['productId'] ?>');
        <?php endif; ?>
    });
    <?php endif; ?>

    // ================= DELETE FUNCTIONALITY =================
    function deleteWarranty(id) {
        if (confirm('Bạn có chắc chắn muốn xóa bảo hành này không?')) {
            window.location.href = '?action=delete&id=' + id;
        }
    }

    // ================= VARIANT LOADING =================
    async function loadVariants(productId, selectedVariantId = '') {
    const variantSelect = document.getElementById('variantSelect');
    
    if (!productId) {
        variantSelect.innerHTML = '<option value="">-- Chọn biến thể --</option>';
        return;
    }
    
    try {
        const response = await fetch(`../ajax/get_variant.php?product_id=${productId}`);
        if (!response.ok) throw new Error('Network response was not ok');

        const data = await response.json();

        console.log("👉 API trả về:", data); // debug

        let html = '<option value="">-- Chọn biến thể --</option>';
        
        if (data.success && Array.isArray(data.variants)) {
            data.variants.forEach(variant => {
                const selected = (variant._id === selectedVariantId) ? 'selected' : '';
                html += `<option value="${variant._id}" ${selected}>
                            ${variant.display_name ?? (variant.color_name + " - " + variant.size_name)} 
                            - ${Number(variant.price).toLocaleString()}đ
                         </option>`;
            });
        }
        
        variantSelect.innerHTML = html;
    } catch (error) {
        console.error('Error loading variants:', error);
        variantSelect.innerHTML = '<option value="">Lỗi tải biến thể</option>';
    }
}


    // ================= DATE MANAGEMENT =================
    function updateEndDate() {
        const startDate = document.querySelector('input[name="start_date"]').value;
        if (startDate) {
            const start = new Date(startDate);
            const end = new Date(start);
            end.setFullYear(start.getFullYear() + 1); // +12 months
            
            document.querySelector('input[name="end_date"]').value = end.toISOString().split('T')[0];
        }
    }

    // ================= AUTO-GENERATE WARRANTY CODE =================
    document.querySelector('select[name="orderId"]')?.addEventListener('change', function() {
        if (this.value && !document.querySelector('input[name="warranty_code"]').value) {
            const orderCode = this.options[this.selectedIndex].text.split(' - ')[0].replace('#', '');
            const currentYear = new Date().getFullYear();
            const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            
            document.querySelector('input[name="warranty_code"]').value = `BH-${currentYear}-${orderCode}-${randomNum}`;
        }
    });

    // ================= FORM VALIDATION =================
    document.getElementById('warrantyForm').addEventListener('submit', function(e) {
        const startDate = new Date(this.start_date.value);
        const endDate = new Date(this.end_date.value);
        
        if (endDate <= startDate) {
            e.preventDefault();
            alert('Ngày hết hạn phải sau ngày bắt đầu!');
            return false;
        }
        
        return true;
    });
    function reloadWarranties() {
    location.href = "warranty_management.php"; 
}

    // ================= KEYBOARD SHORTCUTS =================
    document.addEventListener('keydown', function(e) {
        // Escape to close modal
        if (e.key === 'Escape') {
            closeModal();
        }
        
        // Ctrl/Cmd + N to add new warranty
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            openModal();
        }
    });

    // ================= AUTO-UPDATE EXPIRED WARRANTIES =================
    document.addEventListener('DOMContentLoaded', function() {
        // Check for expired warranties and update status
        const today = new Date();
        document.querySelectorAll('tbody tr').forEach(row => {
            const endDateCell = row.cells[5]; // End date column
            if (endDateCell) {
                const endDateText = endDateCell.textContent.trim();
                const [day, month, year] = endDateText.split('/');
                const endDate = new Date(year, month - 1, day);
                
                if (endDate < today) {
                    const statusCell = row.cells[6];
                    const statusSpan = statusCell.querySelector('span');
                    if (statusSpan && statusSpan.textContent.trim() === 'Hoạt động') {
                        statusSpan.className = 'px-2 py-1 text-xs rounded-full status-expired';
                        statusSpan.textContent = 'Hết hạn';
                    }
                }
            }
        });
    });

    // ================= NOTIFICATION AUTO-HIDE =================
    setTimeout(function() {
        const notification = document.querySelector('.mb-6.p-4.rounded-lg');
        if (notification) {
            notification.style.transition = 'opacity 0.5s ease-out';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }
    }, 5000);

    // ================= RESPONSIVE TABLE =================
    function makeTableResponsive() {
        const table = document.querySelector('table');
        if (window.innerWidth < 768) {
            table.classList.add('text-xs');
        } else {
            table.classList.remove('text-xs');
        }
    }

    window.addEventListener('resize', makeTableResponsive);
    makeTableResponsive();
    </script>

</body>
</html>