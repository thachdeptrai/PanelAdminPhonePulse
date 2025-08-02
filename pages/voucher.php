<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: dang_nhap");
    exit;
}

$userId = new ObjectId($_SESSION['user_id']);
$user = $mongoDB->users->findOne(['_id' => $userId]);
if (!$user) die("Không tìm thấy người dùng");

// Lấy màu theme từ settings
$settings = $mongoDB->settings->findOne([]) ?? ['theme_color' => '#0ea5e9'];
$themeColor = $settings['theme_color'] ?? '#0ea5e9';

$success = false;
$error = false;
$action = $_GET['action'] ?? 'list';

// Xử lý CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $voucherData = [
                'code' => strtoupper(trim($_POST['code'] ?? '')),
                'description' => trim($_POST['description'] ?? ''),
                'discount_type' => $_POST['discount_type'] ?? 'percent',
                'discount_value' => (float)($_POST['discount_value'] ?? 0),
                'min_order_value' => (float)($_POST['min_order_value'] ?? 0),
                'max_discount' => !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null,
                'quantity' => (int)($_POST['quantity'] ?? 1),
                'used_count' => $action === 'edit' ? (int)($_POST['used_count'] ?? 0) : 0,
                'start_date' => new UTCDateTime(strtotime($_POST['start_date']) * 1000),
                'end_date' => new UTCDateTime(strtotime($_POST['end_date']) * 1000),
                'is_active' => isset($_POST['is_active']),
                'category_applicable' => !empty($_POST['category_applicable']) ? $_POST['category_applicable'] : [],
                'user_limit' => !empty($_POST['user_limit']) ? (int)$_POST['user_limit'] : null,
                'modified_date' => new UTCDateTime(),
                'modified_by' => $userId
            ];

            // Validation
            if (empty($voucherData['code'])) throw new Exception("Mã voucher không được để trống");
            if (strlen($voucherData['code']) < 3) throw new Exception("Mã voucher phải có ít nhất 3 ký tự");
            if ($voucherData['discount_value'] <= 0) throw new Exception("Giá trị giảm phải lớn hơn 0");
            if ($voucherData['discount_type'] === 'percent' && $voucherData['discount_value'] > 100) {
                throw new Exception("Giảm theo % không được vượt quá 100%");
            }
            if ($voucherData['start_date'] >= $voucherData['end_date']) {
                throw new Exception("Ngày bắt đầu phải nhỏ hơn ngày kết thúc");
            }
            if (strtotime($_POST['end_date']) < time()) {
                throw new Exception("Ngày kết thúc không được trong quá khứ");
            }

            if ($action === 'create') {
                // Kiểm tra mã trùng
                $existing = $mongoDB->vouchers->findOne(['code' => $voucherData['code']]);
                if ($existing) throw new Exception("Mã voucher đã tồn tại");
                
                $voucherData['created_date'] = new UTCDateTime();
                $voucherData['created_by'] = $userId;
                $mongoDB->vouchers->insertOne($voucherData);
                $success = "Tạo voucher thành công!";
            } else {
                $voucherId = new ObjectId($_GET['id']);
                // Kiểm tra mã trùng (trừ voucher hiện tại)
                $existing = $mongoDB->vouchers->findOne([
                    'code' => $voucherData['code'],
                    '_id' => ['$ne' => $voucherId]
                ]);
                if ($existing) throw new Exception("Mã voucher đã tồn tại");
                
                $mongoDB->vouchers->updateOne(['_id' => $voucherId], ['$set' => $voucherData]);
                $success = "Cập nhật voucher thành công!";
            }
            $action = 'list'; // Redirect về list
        }

        // Xử lý bulk actions
        if ($action === 'bulk' && !empty($_POST['selected_vouchers'])) {
            $bulkAction = $_POST['bulk_action'];
            $selectedIds = array_map(function($id) { return new ObjectId($id); }, $_POST['selected_vouchers']);
            
            switch ($bulkAction) {
                case 'activate':
                    $mongoDB->vouchers->updateMany(
                        ['_id' => ['$in' => $selectedIds]],
                        ['$set' => ['is_active' => true, 'modified_date' => new UTCDateTime()]]
                    );
                    $success = "Đã kích hoạt " . count($selectedIds) . " vouchers!";
                    break;
                case 'deactivate':
                    $mongoDB->vouchers->updateMany(
                        ['_id' => ['$in' => $selectedIds]],
                        ['$set' => ['is_active' => false, 'modified_date' => new UTCDateTime()]]
                    );
                    $success = "Đã vô hiệu hóa " . count($selectedIds) . " vouchers!";
                    break;
                case 'delete':
                    $mongoDB->vouchers->deleteMany(['_id' => ['$in' => $selectedIds]]);
                    $success = "Đã xóa " . count($selectedIds) . " vouchers!";
                    break;
            }
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Xử lý delete
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $voucherId = new ObjectId($_GET['id']);
        $mongoDB->vouchers->deleteOne(['_id' => $voucherId]);
        $success = "Xóa voucher thành công!";
        $action = 'list';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Xử lý toggle status
if ($action === 'toggle' && isset($_GET['id'])) {
    try {
        $voucherId = new ObjectId($_GET['id']);
        $voucher = $mongoDB->vouchers->findOne(['_id' => $voucherId]);
        if ($voucher) {
            $newStatus = !($voucher['is_active'] ?? true);
            $mongoDB->vouchers->updateOne(
                ['_id' => $voucherId],
                ['$set' => ['is_active' => $newStatus, 'modified_date' => new UTCDateTime()]]
            );
            $success = $newStatus ? "Đã kích hoạt voucher!" : "Đã vô hiệu hóa voucher!";
        }
        $action = 'list';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Xử lý search và filter
$searchQuery = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';

// Build MongoDB query
$mongoQuery = [];
if (!empty($searchQuery)) {
    $mongoQuery['$or'] = [
        ['code' => ['$regex' => $searchQuery, '$options' => 'i']],
        ['description' => ['$regex' => $searchQuery, '$options' => 'i']]
    ];
}

if (!empty($statusFilter)) {
    $now = new UTCDateTime();
    switch ($statusFilter) {
        case 'active':
            $mongoQuery['$and'] = [
                ['start_date' => ['$lte' => $now]],
                ['end_date' => ['$gte' => $now]],
                ['quantity' => ['$gt' => 0]],
                ['is_active' => true]
            ];
            break;
        case 'expired':
            $mongoQuery['end_date'] = ['$lt' => $now];
            break;
        case 'inactive':
            $mongoQuery['is_active'] = false;
            break;
        case 'exhausted':
            $mongoQuery['quantity'] = ['$lte' => 0];
            break;
    }
}

if (!empty($typeFilter)) {
    $mongoQuery['discount_type'] = $typeFilter;
}

// Lấy danh sách vouchers với phân trang
$page = (int)($_GET['page'] ?? 1);
$limit = 15;
$skip = ($page - 1) * $limit;

$totalVouchers = $mongoDB->vouchers->countDocuments($mongoQuery);
$totalPages = ceil($totalVouchers / $limit);

$vouchers = $mongoDB->vouchers->find(
    $mongoQuery,
    [
        'sort' => ['created_date' => -1],
        'limit' => $limit,
        'skip' => $skip
    ]
)->toArray();

// Lấy voucher để edit
$editVoucher = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editVoucher = $mongoDB->vouchers->findOne(['_id' => new ObjectId($_GET['id'])]);
}

// Thống kê chi tiết
$now = new UTCDateTime();
$activeVouchers = $mongoDB->vouchers->countDocuments([
    'start_date' => ['$lte' => $now],
    'end_date' => ['$gte' => $now],
    'quantity' => ['$gt' => 0],
    'is_active' => true
]);

$expiredVouchers = $mongoDB->vouchers->countDocuments([
    'end_date' => ['$lt' => $now]
]);

$inactiveVouchers = $mongoDB->vouchers->countDocuments([
    'is_active' => false
]);

$exhaustedVouchers = $mongoDB->vouchers->countDocuments([
    'quantity' => ['$lte' => 0]
]);

// Lấy danh sách categories (nếu có)
$categories = $mongoDB->categories->find([], ['sort' => ['name' => 1]])->toArray();

include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --theme-color: <?= $themeColor ?>;
            --theme-rgb: <?= implode(',', sscanf($themeColor, "#%02x%02x%02x")) ?>;
        }
        .text-theme { color: var(--theme-color); }
        .bg-theme { background-color: var(--theme-color); }
        .border-theme { border-color: var(--theme-color); }
        .ring-theme { --tw-ring-color: var(--theme-color); }
        .shadow-theme { box-shadow: 0 4px 14px 0 rgba(var(--theme-rgb), 0.25); }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .admin-input {
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid rgba(75, 85, 99, 0.5);
            transition: all 0.3s ease;
        }
        
        .admin-input:focus {
            background: rgba(17, 24, 39, 0.9);
            border-color: var(--theme-color);
            box-shadow: 0 0 0 3px rgba(var(--theme-rgb), 0.1);
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(var(--theme-rgb), 0.1) 0%, rgba(var(--theme-rgb), 0.05) 100%);
        }

        .voucher-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active { background-color: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .status-expired { background-color: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .status-upcoming { background-color: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .status-exhausted { background-color: rgba(107, 114, 128, 0.2); color: #6b7280; }
        .status-inactive { background-color: rgba(156, 163, 175, 0.2); color: #9ca3af; }

        .filter-badge {
            background: rgba(var(--theme-rgb), 0.2);
            color: var(--theme-color);
            border: 1px solid rgba(var(--theme-rgb), 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-slate-900 text-white font-sans min-h-screen">

<div class="ml-64 p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-4xl font-bold text-white mb-2 flex items-center">
                    <i class="fas fa-ticket-alt text-theme mr-3"></i>
                    Voucher Management
                </h1>
                <p class="text-gray-400">Quản lý mã giảm giá và khuyến mãi</p>
            </div>
            <div class="flex space-x-4">
                <?php if ($action === 'list'): ?>
                <a href="?action=create" class="bg-theme hover:opacity-90 transition-all text-white px-6 py-3 rounded-xl font-semibold shadow-theme flex items-center">
                    <i class="fas fa-plus mr-2"></i>Tạo Voucher
                </a>
                <?php else: ?>
                <a href="?" class="bg-gray-600 hover:bg-gray-500 transition-all text-white px-6 py-3 rounded-xl font-semibold flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Quay lại
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($action === 'list'): ?>
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="stat-card glass-card rounded-xl p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-blue-500 w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-ticket-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Tổng Vouchers</p>
                        <p class="text-white font-bold text-2xl"><?= number_format($totalVouchers) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card glass-card rounded-xl p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-green-500 w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Đang Hoạt động</p>
                        <p class="text-white font-bold text-2xl"><?= number_format($activeVouchers) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card glass-card rounded-xl p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-red-500 w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-times-circle text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Đã Hết hạn</p>
                        <p class="text-white font-bold text-2xl"><?= number_format($expiredVouchers) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card glass-card rounded-xl p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-gray-500 w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-pause-circle text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Bị Vô hiệu</p>
                        <p class="text-white font-bold text-2xl"><?= number_format($inactiveVouchers) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card glass-card rounded-xl p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-purple-500 w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-percentage text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Tỷ lệ Active</p>
                        <p class="text-white font-bold text-2xl">
                            <?= $totalVouchers > 0 ? round(($activeVouchers / $totalVouchers) * 100) : 0 ?>%
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="glass-card rounded-2xl p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Tìm kiếm</label>
                    <div class="relative">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                               class="admin-input w-full pl-10 pr-4 py-3 rounded-xl focus:outline-none" 
                               placeholder="Tìm theo mã hoặc mô tả...">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                    <select name="status" class="admin-input w-full py-3 px-4 rounded-xl focus:outline-none">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Hết hạn</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Bị vô hiệu</option>
                        <option value="exhausted" <?= $statusFilter === 'exhausted' ? 'selected' : '' ?>>Hết lượt</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Loại giảm</label>
                    <select name="type" class="admin-input w-full py-3 px-4 rounded-xl focus:outline-none">
                        <option value="">Tất cả loại</option>
                        <option value="percent" <?= $typeFilter === 'percent' ? 'selected' : '' ?>>Phần trăm</option>
                        <option value="amount" <?= $typeFilter === 'amount' ? 'selected' : '' ?>>Cố định</option>
                    </select>
                </div>
                
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-theme hover:opacity-90 transition-all text-white px-6 py-3 rounded-xl font-semibold flex items-center">
                        <i class="fas fa-filter mr-2"></i>Lọc
                    </button>
                    <a href="?" class="bg-gray-600 hover:bg-gray-500 transition-all text-white px-4 py-3 rounded-xl">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($typeFilter)): ?>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="text-sm text-gray-400">Bộ lọc đang áp dụng:</span>
                <?php if (!empty($searchQuery)): ?>
                <span class="filter-badge px-3 py-1 rounded-full text-xs">
                    Tìm kiếm: "<?= htmlspecialchars($searchQuery) ?>"
                </span>
                <?php endif; ?>
                <?php if (!empty($statusFilter)): ?>
                <span class="filter-badge px-3 py-1 rounded-full text-xs">
                    Trạng thái: <?= ucfirst($statusFilter) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($typeFilter)): ?>
                <span class="filter-badge px-3 py-1 rounded-full text-xs">
                    Loại: <?= $typeFilter === 'percent' ? 'Phần trăm' : 'Cố định' ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Bulk Actions -->
        <form method="POST" id="bulkForm">
            <input type="hidden" name="action" value="bulk">
            
            <!-- Vouchers Table -->
            <div class="glass-card rounded-2xl overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-gray-700 flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-list mr-3 text-theme"></i>
                        Danh sách Vouchers
                        <span class="ml-2 text-lg text-gray-400">(<?= number_format($totalVouchers) ?>)</span>
                    </h2>
                    
                    <div class="flex items-center space-x-4">
                        <div class="bulk-actions hidden">
                            <select name="bulk_action" class="admin-input px-3 py-2 rounded-lg text-sm">
                                <option value="">Chọn thao tác...</option>
                                <option value="activate">Kích hoạt</option>
                                <option value="deactivate">Vô hiệu hóa</option>
                                <option value="delete">Xóa</option>
                            </select>
                            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                                Thực hiện
                            </button>
                        </div>
                        
                        <!-- <label class="flex items-center space-x-2 text-sm text-gray-300">
                            <input type="checkbox" id="selectAll" class="rounded">
                            <span>Chọn tất cả</span>
                        </label> -->
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAllHeader" class="rounded">
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Mã Voucher</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Loại giảm</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Giá trị</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Sử dụng</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Thời gian</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($vouchers as $voucher): 
                                $now = new UTCDateTime();
                                $status = 'expired';
                                $statusText = 'Hết hạn';
                                $statusClass = 'status-expired';
                                
                                if (!($voucher['is_active'] ?? true)) {
                                    $status = 'inactive';
                                    $statusText = 'Bị vô hiệu';
                                    $statusClass = 'status-inactive';
                                } elseif ($voucher['quantity'] <= 0) {
                                    $status = 'exhausted';
                                    $statusText = 'Hết lượt';
                                    $statusClass = 'status-exhausted';
                                } elseif ($voucher['start_date'] > $now) {
                                    $status = 'upcoming';
                                    $statusText = 'Sắp diễn ra';
                                    $statusClass = 'status-upcoming';
                                } elseif ($voucher['start_date'] <= $now && $voucher['end_date'] >= $now) {
                                    $status = 'active';
                                    $statusText = 'Đang hoạt động';
                                    $statusClass = 'status-active';
                                }
                            ?>
                            <tr class="hover:bg-gray-800/30 transition-colors fade-in">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="selected_vouchers[]" value="<?= $voucher['_id'] ?>" class="rounded voucher-checkbox">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-mono text-theme font-bold"><?= htmlspecialchars($voucher['code']) ?></div>
                                    <?php if (!empty($voucher['description'])): ?>
                                    <div class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($voucher['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $voucher['discount_type'] === 'percent' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= $voucher['discount_type'] === 'percent' ? 'Phần trăm' : 'Cố định' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-white">
                                    <?php if ($voucher['discount_type'] === 'percent'): ?>
                                        <?= $voucher['discount_value'] ?>%
                                        <?php if ($voucher['max_discount']): ?>
                                            <div class="text-xs text-gray-400">Tối đa: <?= number_format($voucher['max_discount']) ?>đ</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= number_format($voucher['discount_value']) ?>đ
                                    <?php endif; ?>
                                    <?php if ($voucher['min_order_value'] > 0): ?>
                                        <div class="text-xs text-gray-400">Đơn tối thiểu: <?= number_format($voucher['min_order_value']) ?>đ</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-white font-semibold">
                                        <?= number_format($voucher['used_count'] ?? 0) ?> / <?= number_format($voucher['quantity']) ?>
                                    </div>
                                    <div class="w-full bg-gray-700 rounded-full h-2 mt-1">
                                        <?php 
                                        $usagePercent = $voucher['quantity'] > 0 ? (($voucher['used_count'] ?? 0) / $voucher['quantity']) * 100 : 0;
                                        $progressColor = $usagePercent >= 90 ? 'bg-red-500' : ($usagePercent >= 70 ? 'bg-yellow-500' : 'bg-green-500');
                                        ?>
                                        <div class="<?= $progressColor ?> h-2 rounded-full transition-all" style="width: <?= min($usagePercent, 100) ?>%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                    <div><?= $voucher['start_date']->toDateTime()->format('d/m/Y H:i') ?></div>
                                    <div class="text-xs text-gray-500">đến <?= $voucher['end_date']->toDateTime()->format('d/m/Y H:i') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="voucher-status <?= $statusClass ?>">
                                        <i class="fas fa-circle text-xs mr-1"></i>
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="?action=toggle&id=<?= $voucher['_id'] ?>" 
                                           class="<?= ($voucher['is_active'] ?? true) ? 'text-yellow-400 hover:text-yellow-300' : 'text-green-400 hover:text-green-300' ?> transition-colors"
                                           title="<?= ($voucher['is_active'] ?? true) ? 'Vô hiệu hóa' : 'Kích hoạt' ?>">
                                            <i class="fas fa-<?= ($voucher['is_active'] ?? true) ? 'pause' : 'play' ?>"></i>
                                        </a>
                                        <a href="?action=edit&id=<?= $voucher['_id'] ?>" class="text-blue-400 hover:text-blue-300 transition-colors" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" onclick="copyVoucherCode('<?= htmlspecialchars($voucher['code']) ?>')" class="text-purple-400 hover:text-purple-300 transition-colors" title="Sao chép mã">
                                            <i class="fas fa-copy"></i>
                                        </a>
                                        <a href="?action=delete&id=<?= $voucher['_id'] ?>" class="text-red-400 hover:text-red-300 transition-colors" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa voucher này?\n\nMã: <?= htmlspecialchars($voucher['code']) ?>')"
                                           title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-ticket-alt text-6xl mb-4 text-gray-600"></i>
                                        <h3 class="text-xl font-semibold mb-2">Không có voucher nào</h3>
                                        <p class="text-gray-500">
                                            <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($typeFilter)): ?>
                                                Không tìm thấy voucher nào với bộ lọc hiện tại.
                                            <?php else: ?>
                                                Hãy tạo voucher đầu tiên của bạn!
                                            <?php endif; ?>
                                        </p>
                                        <?php if (empty($searchQuery) && empty($statusFilter) && empty($typeFilter)): ?>
                                        <a href="?action=create" class="mt-4 bg-theme hover:opacity-90 transition-all text-white px-6 py-3 rounded-xl font-semibold flex items-center">
                                            <i class="fas fa-plus mr-2"></i>Tạo Voucher Đầu Tiên
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-700">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-400">
                            Hiển thị <?= ($page - 1) * $limit + 1 ?> - <?= min($page * $limit, $totalVouchers) ?> trong tổng số <?= number_format($totalVouchers) ?> vouchers
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="px-3 py-2 <?= $i === $page ? 'bg-theme text-white' : 'bg-gray-700 text-white hover:bg-gray-600' ?> rounded-lg transition-colors">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </form>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <!-- Create/Edit Form -->
        <div class="glass-card rounded-2xl p-8 shadow-2xl fade-in">
            <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                <i class="fas fa-<?= $action === 'create' ? 'plus' : 'edit' ?> text-theme mr-3"></i>
                <?= $action === 'create' ? 'Tạo Voucher Mới' : 'Chỉnh sửa Voucher' ?>
            </h2>

            <form method="POST" class="space-y-8">
                <!-- Basic Information -->
                <div class="bg-gray-800/30 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <i class="fas fa-info-circle text-theme mr-2"></i>
                        Thông tin cơ bản
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-code mr-2 text-theme"></i>
                                Mã Voucher <span class="text-red-400 ml-1">*</span>
                            </label>
                            <input type="text" name="code" value="<?= htmlspecialchars($editVoucher['code'] ?? '') ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none uppercase" 
                                   placeholder="VD: SALE20, NEWUSER, FREESHIP" maxlength="20" required>
                            <p class="text-xs text-gray-400 mt-1">Mã voucher sẽ tự động chuyển thành chữ hoa</p>
                        </div>
                        
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-toggle-on mr-2 text-theme"></i>
                                Trạng thái
                            </label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" <?= ($editVoucher['is_active'] ?? true) ? 'checked' : '' ?> 
                                           class="rounded text-theme focus:ring-theme">
                                    <span class="ml-2 text-gray-300">Kích hoạt voucher</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                            <i class="fas fa-align-left mr-2 text-theme"></i>
                            Mô tả voucher
                        </label>
                        <textarea name="description" rows="3" 
                                  class="admin-input w-full text-white p-4 rounded-xl focus:outline-none resize-none"
                                  placeholder="Mô tả ngắn gọn về voucher này..."><?= htmlspecialchars($editVoucher['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Discount Settings -->
                <div class="bg-gray-800/30 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <i class="fas fa-percentage text-theme mr-2"></i>
                        Cài đặt giảm giá
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-tags mr-2 text-theme"></i>
                                Loại giảm giá <span class="text-red-400 ml-1">*</span>
                            </label>
                            <select name="discount_type" id="discountType" class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" required>
                                <option value="percent" <?= ($editVoucher['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Phần trăm (%)</option>
                                <option value="amount" <?= ($editVoucher['discount_type'] ?? '') === 'amount' ? 'selected' : '' ?>>Số tiền cố định (VNĐ)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-calculator mr-2 text-theme"></i>
                                Giá trị giảm <span class="text-red-400 ml-1">*</span>
                            </label>
                            <input type="number" name="discount_value" id="discountValue" value="<?= $editVoucher['discount_value'] ?? '' ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                   placeholder="20" step="0.01" min="0" required>
                            <p class="text-xs text-gray-400 mt-1" id="discountHint">VD: 20 (giảm 20%)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-shopping-cart mr-2 text-theme"></i>
                                Đơn hàng tối thiểu (VNĐ)
                            </label>
                            <input type="number" name="min_order_value" value="<?= $editVoucher['min_order_value'] ?? 0 ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                   placeholder="0" min="0">
                            <p class="text-xs text-gray-400 mt-1">Để 0 nếu không có yêu cầu tối thiểu</p>
                        </div>
                        
                        <div id="maxDiscountField">
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-limit mr-2 text-theme"></i>
                                Giảm tối đa (VNĐ)
                            </label>
                            <input type="number" name="max_discount" value="<?= $editVoucher['max_discount'] ?? '' ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                   placeholder="100000" min="0">
                            <p class="text-xs text-gray-400 mt-1">Để trống nếu không giới hạn</p>
                        </div>
                    </div>
                </div>

                <!-- Usage & Time Settings -->
                <div class="bg-gray-800/30 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <i class="fas fa-clock text-theme mr-2"></i>
                        Cài đặt sử dụng & thời gian
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-sort-numeric-up mr-2 text-theme"></i>
                                Tổng số lượng <span class="text-red-400 ml-1">*</span>
                            </label>
                            <input type="number" name="quantity" value="<?= $editVoucher['quantity'] ?? 1 ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                   min="1" required>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-chart-line mr-2 text-theme"></i>
                                Đã sử dụng
                            </label>
                            <input type="number" name="used_count" value="<?= $editVoucher['used_count'] ?? 0 ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                   min="0" max="<?= $editVoucher['quantity'] ?? 1 ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-user-friends mr-2 text-theme"></i>
                                Giới hạn/người dùng
                            </label>
                            <input type="number" name="user_limit" value="<?= $editVoucher['user_limit'] ?? '' ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                   placeholder="1" min="1">
                            <p class="text-xs text-gray-400 mt-1">Để trống = không giới hạn</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-calendar-alt mr-2 text-theme"></i>
                                Ngày & giờ bắt đầu <span class="text-red-400 ml-1">*</span>
                            </label>
                            <input type="datetime-local" name="start_date" 
                                   value="<?= $editVoucher ? $editVoucher['start_date']->toDateTime()->format('Y-m-d\TH:i') : date('Y-m-d\TH:i') ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" required>
                        </div>
                        
                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-calendar-times mr-2 text-theme"></i>
                                Ngày & giờ kết thúc <span class="text-red-400 ml-1">*</span>
                            </label>
                            <input type="datetime-local" name="end_date" 
                                   value="<?= $editVoucher ? $editVoucher['end_date']->toDateTime()->format('Y-m-d\TH:i') : date('Y-m-d\TH:i', strtotime('+30 days')) ?>" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" required>
                        </div>
                    </div>
                </div>

                <!-- Category Filter (if categories exist) -->
                <?php if (!empty($categories)): ?>
                <div class="bg-gray-800/30 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <i class="fas fa-filter text-theme mr-2"></i>
                        Áp dụng cho danh mục
                    </h3>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <?php foreach ($categories as $category): ?>
                        <label class="flex items-center p-3 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition-colors cursor-pointer">
                            <input type="checkbox" name="category_applicable[]" value="<?= $category['_id'] ?>"
                                   <?= in_array((string)$category['_id'], $editVoucher['category_applicable'] ?? []) ? 'checked' : '' ?>
                                   class="rounded text-theme focus:ring-theme mr-3">
                            <span class="text-gray-300 text-sm"><?= htmlspecialchars($category['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">Để trống để áp dụng cho tất cả danh mục</p>
                </div>
                <?php endif; ?>

                <!-- Form Actions -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-700">
                    <div class="flex items-center text-gray-400 text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= $action === 'create' ? 'Voucher sẽ được kích hoạt ngay sau khi tạo (nếu được bật)' : 'Thay đổi sẽ có hiệu lực ngay lập tức' ?>
                    </div>
                    <div class="flex space-x-4">
                        <a href="?" class="bg-gray-600 hover:bg-gray-500 transition-all text-white px-6 py-3 rounded-xl font-semibold">
                            Hủy bỏ
                        </a>
                        <button type="submit" class="bg-theme hover:opacity-90 transition-all text-white px-8 py-3 rounded-xl font-semibold shadow-theme flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            <?= $action === 'create' ? 'Tạo Voucher' : 'Cập nhật Voucher' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
<script>
// Form helpers
document.addEventListener('DOMContentLoaded', function() {
    // Discount type change handler
    const discountType = document.getElementById('discountType');
    const discountValue = document.getElementById('discountValue');
    const discountHint = document.getElementById('discountHint');
    const maxDiscountField = document.getElementById('maxDiscountField');
    
    if (discountType && discountValue && discountHint && maxDiscountField) {
        function updateDiscountFields() {
            if (discountType.value === 'percent') {
                discountHint.textContent = 'VD: 20 (giảm 20%)';
                discountValue.setAttribute('max', '100');
                discountValue.setAttribute('placeholder', '20');
                maxDiscountField.style.display = 'block';
            } else {
                discountHint.textContent = 'VD: 50000 (giảm 50,000 VNĐ)';
                discountValue.removeAttribute('max');
                discountValue.setAttribute('placeholder', '50000');
                maxDiscountField.style.display = 'none';
            }
        }
        
        discountType.addEventListener('change', updateDiscountFields);
        updateDiscountFields(); // Initial call
    }
    
    // Bulk selection handlers
    const selectAll = document.getElementById('selectAll');
    const selectAllHeader = document.getElementById('selectAllHeader');
    const voucherCheckboxes = document.querySelectorAll('.voucher-checkbox');
    const bulkActions = document.querySelector('.bulk-actions');
    
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.voucher-checkbox:checked');
        if (checkedBoxes.length > 0) {
            bulkActions?.classList.remove('hidden');
        } else {
            bulkActions?.classList.add('hidden');
        }
        
        // Update select all checkboxes
        const allChecked = voucherCheckboxes.length > 0 && checkedBoxes.length === voucherCheckboxes.length;
        const someChecked = checkedBoxes.length > 0;
        
        if (selectAll) {
            selectAll.checked = allChecked;
            selectAll.indeterminate = someChecked && !allChecked;
        }
        if (selectAllHeader) {
            selectAllHeader.checked = allChecked;
            selectAllHeader.indeterminate = someChecked && !allChecked;
        }
    }
    
    // Select all functionality
    [selectAll, selectAllHeader].forEach(checkbox => {
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                voucherCheckboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                updateBulkActions();
            });
        }
    });
    
    // Individual checkbox handlers
    voucherCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    // Bulk form submission confirmation
    document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('.voucher-checkbox:checked');
        const action = document.querySelector('select[name="bulk_action"]')?.value;
        
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Vui lòng chọn ít nhất một voucher!');
            return;
        }
        
        if (!action) {
            e.preventDefault();
            alert('Vui lòng chọn thao tác muốn thực hiện!');
            return;
        }
        
        const actionText = {
            'activate': 'kích hoạt',
            'deactivate': 'vô hiệu hóa', 
            'delete': 'xóa'
        };
        
        if (!confirm(`Bạn có chắc chắn muốn ${actionText[action]} ${checkedBoxes.length} voucher(s) đã chọn?`)) {
            e.preventDefault();
        }
    });
    
    // Auto-uppercase voucher code input
    const codeInput = document.querySelector('input[name="code"]');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
});

// Copy voucher code function
function copyVoucherCode(code) {
    navigator.clipboard.writeText(code).then(function() {
        Toastify({
            text: `✅ Đã sao chép mã: ${code}`,
            duration: 2000,
            gravity: "top",
            position: "right",
            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
            className: "rounded-lg"
        }).showToast();
    }).catch(function() {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = code;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        Toastify({
            text: `✅ Đã sao chép mã: ${code}`,
            duration: 2000,
            gravity: "top",
            position: "right",
            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
            className: "rounded-lg"
        }).showToast();
    });
}
</script>

<!-- Toast Notifications -->
<?php if ($success): ?>
<script>
    Toastify({
        text: "✅ <?= htmlspecialchars($success) ?>",
        duration: 4000,
        gravity: "top",
        position: "right",
        backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
        className: "rounded-lg"
    }).showToast();
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
    Toastify({
        text: "❌ <?= htmlspecialchars($error) ?>",
        duration: 4000,
        gravity: "top",
        position: "right",
        backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
        className: "rounded-lg"
    }).showToast();
</script>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'bulk_done'): ?>
    <script>
        Toastify({
            text: "✅ Đã thực hiện hành động với các voucher được chọn!",
            duration: 4000,
            gravity: "top",
            position: "right",
            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
            className: "rounded-lg"
        }).showToast();
    </script>
<?php endif; ?>

<?php endif; ?>

</body>
</html>