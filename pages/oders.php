<?php
include '../includes/config.php';
include '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}
// CHỈ để update user_id về ObjectId nếu đang là string
$orders = $mongoDB->orders->find(['user_id' => ['$type' => 'string']]);
foreach ($orders as $order) {
    try {
        $mongoDB->orders->updateOne(
            ['_id' => $order['_id']],
            ['$set' => ['userId' => new MongoDB\BSON\ObjectId($order['userId'])]]
        );
        e("✅ Updated order " . $order['_id'] . "<br>");
    } catch (Exception $e) {
        e( "❌ Failed order " . $order['_id'] . ": " . $e->getMessage() . "<br>");
    }
}
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_method_filter = $_GET['payment_method'] ?? '';
$shipping_status_filter = $_GET['shipping_status'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$price_from = isset($_GET['price_from']) ? (float)$_GET['price_from'] : 0;
$price_to = isset($_GET['price_to']) ? (float)$_GET['price_to'] : 0;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$skip = ($page - 1) * $limit;

$match = [];
$match['is_deleted'] = ['$ne' => true];

if ($search) {
    $regex = new Regex($search, 'i');
    if (ObjectId::isValid($search)) {
        $match['$or'][] = ['_id' => new ObjectId($search)];
    }
    $match['$or'][] = ['user.name' => $regex];
    $match['$or'][] = ['user.email' => $regex];
    $match['$or'][] = ['user.phone' => $regex];
}

if ($status_filter !== '') {
    $match['status'] = $status_filter;
}

if ($payment_method_filter !== '') {
    $match['payment_method'] = $payment_method_filter;
}

if ($shipping_status_filter !== '') {
    $match['shipping_status'] = $shipping_status_filter;
}

if ($date_filter || ($date_from && $date_to)) {
    $fromDate = null;
    $toDate = null;
    switch ($date_filter) {
        case 'today':
            $fromDate = strtotime('today') * 1000;
            $toDate = strtotime('tomorrow') * 1000;
            break;
        case 'yesterday':
            $fromDate = strtotime('yesterday') * 1000;
            $toDate = strtotime('today') * 1000;
            break;
        case '7days':
            $fromDate = strtotime('-7 days') * 1000;
            $toDate = strtotime('now') * 1000;
            break;
        case 'this_month':
            $fromDate = strtotime(date('Y-m-01')) * 1000;
            $toDate = strtotime(date('Y-m-t 23:59:59')) * 1000;
            break;
        case 'custom':
            if ($date_from && $date_to) {
                $fromDate = strtotime($date_from) * 1000;
                $toDate = strtotime($date_to . ' 23:59:59') * 1000;
            }
            break;
    }

    if ($fromDate && $toDate) {
        $match['created_date'] = [
            '$gte' => new UTCDateTime($fromDate),
            '$lte' => new UTCDateTime($toDate),
        ];
    }
}

if ($price_from > 0 || $price_to > 0) {
    $priceCond = [];
    if ($price_from > 0) $priceCond['$gte'] = $price_from;
    if ($price_to > 0) $priceCond['$lte'] = $price_to;
    $match['final_price'] = $priceCond;
}
$pipeline = [
    ['$lookup' => [
        'from' => 'users',
        'localField' => 'userId',
        'foreignField' => '_id',
        'as' => 'user'
    ]],
    ['$unwind' => ['path' => '$user', 'preserveNullAndEmptyArrays' => true]],
];

if (!empty($match)) {
    $pipeline[] = ['$match' => $match];
}

$pipeline[] = ['$sort' => ['created_date' => -1]];
$pipeline[] = ['$skip' => $skip];
$pipeline[] = ['$limit' => $limit];


$ordersCursor = $mongoDB->orders->aggregate($pipeline);
$orders = iterator_to_array($ordersCursor);
$countPipeline = [
  ['$lookup' => [
      'from' => 'users',
      'localField' => 'userId',
      'foreignField' => '_id',
      'as' => 'user'
  ]],
  ['$unwind' => ['path' => '$user', 'preserveNullAndEmptyArrays' => true]],
];
if (!empty($match)) {
    $countPipeline[] = ['$match' => $match];
}
$countPipeline[] = ['$count' => 'total'];


$totalResult = $mongoDB->orders->aggregate($countPipeline)->toArray();
$totalOrders = $totalResult[0]['total'] ?? 0;
$totalPages = ceil($totalOrders / $limit);

$user_id = $_SESSION['user_id'];
$user = $mongoDB->users->findOne(['_id' => new ObjectId($user_id)]);

$statusOptions = $mongoDB->orders->distinct('status');
$paymentMethodOptions = $mongoDB->orders->distinct('payment_method');
$shippingStatusOptions = $mongoDB->orders->distinct('shipping_status');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Đơn hàng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .filter-section { animation: slideDown 0.3s ease-out; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</head>
<body class="flex bg-gray-900 text-white">
    <?php include '../includes/sidebar.php'; ?>

    <div class="ml-64 flex-1 p-6">
        <header class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">📦 Quản lý Đơn hàng</h1>
            <button id="toggleFilters" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-white font-medium">
                🔍 Bộ lọc nâng cao
            </button>
        </header>

        <!-- Advanced Filters -->
        <div id="filtersSection" class="mb-6 bg-gray-800 rounded-lg p-4 hidden filter-section">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                
                <!-- Search -->
                <div class="col-span-full">
                    <label class="block text-sm font-medium mb-2">🔍 Tìm kiếm</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Mã đơn, tên khách, email, số điện thoại..."
                           class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Date Filter -->
                <div>
                    <label class="block text-sm font-medium mb-2">📅 Thời gian tạo đơn</label>
                    <select name="date_filter" id="dateFilter" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                        <option value="">Tất cả</option>
                        <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Hôm nay</option>
                        <option value="yesterday" <?= $date_filter == 'yesterday' ? 'selected' : '' ?>>Hôm qua</option>
                        <option value="7days" <?= $date_filter == '7days' ? 'selected' : '' ?>>7 ngày qua</option>
                        <option value="this_month" <?= $date_filter == 'this_month' ? 'selected' : '' ?>>Tháng này</option>
                        <option value="custom" <?= $date_filter == 'custom' ? 'selected' : '' ?>>Tùy chọn ngày</option>
                    </select>
                </div>

                <!-- Custom Date Range -->
                <div id="customDateRange" class="col-span-2 <?= $date_filter != 'custom' ? 'hidden' : '' ?>">
                    <label class="block text-sm font-medium mb-2">📅 Khoảng ngày</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                               class="flex-1 px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                        <span class="self-center">đến</span>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                               class="flex-1 px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Order Status -->
                <div>
                    <label class="block text-sm font-medium mb-2">🧾 Trạng thái đơn hàng</label>
                    <select name="status" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                        <option value="">Tất cả</option>
                        <?php foreach ($statusOptions as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= $status_filter == $status ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Payment Method -->
                <div>
                    <label class="block text-sm font-medium mb-2">💳 Phương thức thanh toán</label>
                    <select name="payment_method" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                        <option value="">Tất cả</option>
                        <?php foreach ($paymentMethodOptions as $method): ?>
                            <option value="<?= htmlspecialchars($method) ?>" <?= $payment_method_filter == $method ? 'selected' : '' ?>>
                                <?= htmlspecialchars($method) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Shipping Status -->
                <div>
                    <label class="block text-sm font-medium mb-2">📦 Trạng thái vận chuyển</label>
                    <select name="shipping_status" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                        <option value="">Tất cả</option>
                        <?php foreach ($shippingStatusOptions as $shipping): ?>
                            <option value="<?= htmlspecialchars($shipping) ?>" <?= $shipping_status_filter == $shipping ? 'selected' : '' ?>>
                                <?= htmlspecialchars($shipping) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Price Range -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-2">💰 Khoảng giá trị đơn hàng</label>
                    <div class="flex gap-2">
                        <input type="number" name="price_from" value="<?= $price_from > 0 ? $price_from : '' ?>" 
                               placeholder="Từ (VNĐ)"
                               class="flex-1 px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                        <span class="self-center">đến</span>
                        <input type="number" name="price_to" value="<?= $price_to > 0 ? $price_to : '' ?>" 
                               placeholder="Đến (VNĐ)"
                               class="flex-1 px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="col-span-full flex gap-3">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg text-white font-medium">
                        🔍 Áp dụng bộ lọc
                    </button>
                    <a href="?" class="bg-gray-600 hover:bg-gray-700 px-6 py-2 rounded-lg text-white font-medium">
                        🔄 Đặt lại
                    </a>
                </div>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if ($search || $status_filter || $payment_method_filter || $shipping_status_filter || $date_filter || $price_from > 0 || $price_to > 0): ?>
        <div class="mb-4 flex flex-wrap gap-2">
            <span class="text-sm text-gray-400">Bộ lọc đang áp dụng:</span>
            <?php if ($search): ?>
                <span class="bg-blue-600 px-3 py-1 rounded-full text-xs">Tìm kiếm: <?= htmlspecialchars($search) ?></span>
            <?php endif; ?>
            <?php if ($status_filter): ?>
                <span class="bg-green-600 px-3 py-1 rounded-full text-xs">Trạng thái: <?= htmlspecialchars($status_filter) ?></span>
            <?php endif; ?>
            <?php if ($payment_method_filter): ?>
                <span class="bg-purple-600 px-3 py-1 rounded-full text-xs">Thanh toán: <?= htmlspecialchars($payment_method_filter) ?></span>
            <?php endif; ?>
            <?php if ($shipping_status_filter): ?>
                <span class="bg-orange-600 px-3 py-1 rounded-full text-xs">Vận chuyển: <?= htmlspecialchars($shipping_status_filter) ?></span>
            <?php endif; ?>
            <?php if ($date_filter): ?>
                <span class="bg-red-600 px-3 py-1 rounded-full text-xs">
                    Thời gian: <?= 
                        $date_filter == 'today' ? 'Hôm nay' : 
                        ($date_filter == 'yesterday' ? 'Hôm qua' : 
                        ($date_filter == '7days' ? '7 ngày qua' : 
                        ($date_filter == 'this_month' ? 'Tháng này' : 'Tùy chọn'))) 
                    ?>
                </span>
            <?php endif; ?>
            <?php if ($price_from > 0 || $price_to > 0): ?>
                <span class="bg-yellow-600 px-3 py-1 rounded-full text-xs">
                    Giá: <?= $price_from > 0 ? number_format($price_from) : '0' ?>đ - <?= $price_to > 0 ? number_format($price_to) : '∞' ?>đ
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Stats Summary -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <?php
            $match = []; // ← xây dựng bộ lọc y chang đoạn bạn đang dùng bên trên

            // Nếu có bộ lọc thì thêm vào
            if (!empty($search)) {
                $regex = new Regex($search, 'i');
                $match['$or'] = [
                    ['users.name' => $regex],
                    ['users.email' => $regex],
                    ['users.phone' => $regex]
                ];
            }
            
            if ($status_filter !== '') {
                $match['status'] = $status_filter;
            }
            if ($payment_method_filter !== '') {
                $match['payment_method'] = $payment_method_filter;
            }
            if ($shipping_status_filter !== '') {
                $match['shipping_status'] = $shipping_status_filter;
            }
            if ($price_from > 0 || $price_to > 0) {
                $match['final_price'] = [];
                if ($price_from > 0) $match['final_price']['$gte'] = $price_from;
                if ($price_to > 0) $match['final_price']['$lte'] = $price_to;
            }
            if ($date_from && $date_to) {
                $match['created_date'] = [
                    '$gte' => new UTCDateTime(strtotime($date_from) * 1000),
                    '$lte' => new UTCDateTime(strtotime($date_to . ' 23:59:59') * 1000)
                ];
            }
            
            $pipeline = [
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => 'user_id',
                        'foreignField' => '_id',
                        'as' => 'users'
                    ]
                ],
                ['$unwind' => ['path' => '$users', 'preserveNullAndEmptyArrays' => true]],
            ];
            
            // Thêm điều kiện lọc nếu có
            if (!empty($match)) {
                $pipeline[] = ['$match' => $match];
            }
            
            // Thêm stage group để tính toán thống kê
            $pipeline[] = [
                '$group' => [
                    '_id' => null,
                    'total_orders' => ['$sum' => 1],
                    'total_revenue' => ['$sum' => '$final_price'],
                    'avg_order_value' => ['$avg' => '$final_price'],
                    'completed_orders' => [
                        '$sum' => [
                            '$cond' => [
                                ['==', ['$status', 'confirmed']],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ];
            
            // Thực hiện truy vấn
            $result = $mongoDB->orders->aggregate($pipeline)->toArray();
            $stats = $result[0] ?? [
                'total_orders' => 0,
                'total_revenue' => 0,
                'avg_order_value' => 0,
                'completed_orders' => 0
            ];
            ?>
            
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-2xl font-bold text-blue-400"><?= number_format($stats['total_orders']) ?></div>
                <div class="text-sm text-gray-400">Tổng đơn hàng</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-2xl font-bold text-green-400"><?= number_format($stats['total_revenue'] ?? 0) ?>đ</div>
                <div class="text-sm text-gray-400">Tổng doanh thu</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-2xl font-bold text-yellow-400"><?= number_format($stats['avg_order_value'] ?? 0) ?>đ</div>
                <div class="text-sm text-gray-400">Giá trị TB/đơn</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-2xl font-bold text-purple-400"><?= number_format($stats['completed_orders']) ?></div>
                <div class="text-sm text-gray-400">Đơn xác nhận</div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto bg-gray-800 rounded-lg">
            <table class="w-full table-auto text-sm">
                <thead class="bg-gray-900 border-b border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left">Mã đơn</th>
                        <th class="px-4 py-3 text-left">Khách hàng</th>
                        <th class="px-4 py-3 text-center">Tổng tiền</th>
                        <th class="px-4 py-3 text-center">Trạng thái</th>
                        <th class="px-4 py-3 text-center">Thanh toán</th>
                        <th class="px-4 py-3 text-center">Vận chuyển</th>
                        <th class="px-4 py-3 text-center">Ngày đặt</th>
                        <th class="px-4 py-3 text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
              
                    <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-4 py-3 font-mono text-blue-400"><?= htmlspecialchars($order['_id']) ?></td>
                            <td class="px-4 py-3">
                                <div><?= htmlspecialchars($order['user']['name'] ?? 'Không xác định') ?></div>
                                <div class="text-xs text-gray-400">
                                    <?= htmlspecialchars($order['user']['email'] ?? '') ?>
                                    <?= htmlspecialchars($order['user']['phone'] ?? '') ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-green-400">
                                <?= number_format($order['final_price'], 0, ',', '.') ?>đ
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded-full text-xs <?php
                                    switch ($order['status']) {
                                        case 'Chờ xác nhận': echo 'bg-yellow-600'; break;
                                        case 'Đã xác nhận': echo 'bg-blue-600'; break;
                                        case 'Đang giao hàng': echo 'bg-purple-600'; break;
                                        case 'Hoàn thành': echo 'bg-green-600'; break;
                                        case 'Đã hủy': echo 'bg-red-600'; break;
                                        default: echo 'bg-gray-600';
                                    }
                                ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div><?= htmlspecialchars($order['payment_method']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($order['payment_status'] ?? '') ?></div>
                            </td>
                            <td class="px-4 py-3 text-center"><?= htmlspecialchars($order['shipping_status'] ?? '') ?></td>
                            <td class="px-4 py-3 text-center text-xs">
                                <?= date('d/m/Y', strtotime($order['created_date'])) ?><br>
                                <span class="text-gray-400"><?= date('H:i', strtotime($order['created_date'])) ?></span>
                            </td>
                            <td class="px-4 py-3 text-center space-x-2">
                                <a href="order_detail?id=<?= $order['_id'] ?>" 
                                   class="text-blue-400 hover:text-blue-300 text-xs">Chi tiết</a>
                                   <a href="/ajax/delete_order.php?id=<?= $order['_id'] ?>"
                                    class="text-red-400 hover:text-red-300 text-xs"
                                    onclick="return confirm('Xác nhận chuyển đơn hàng vào thùng rác?')">
                                    🗑️ Xoá
                                    </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                                Không tìm thấy đơn hàng nào phù hợp với bộ lọc
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'trashed'): ?>
        <script>
            Toastify({
                text: "Đã chuyển đơn hàng vào thùng rác!",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#10B981",
            }).showToast();
        </script>
        <?php endif; ?>
        <div class="flex justify-end mb-4">
    <a href="orders_trash" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-white font-medium">
        🗑️ Thùng rác
    </a>
</div>
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-between items-center">
            <div class="text-sm text-gray-400">
                Hiển thị <?= count($orders) ?> / <?= $totalOrders ?> đơn hàng
            </div>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                       class="px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm">← Trước</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="px-3 py-2 rounded-lg text-sm <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                       class="px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm">Tiếp →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>

    <script>
        // Toggle filters section
        document.getElementById('toggleFilters').addEventListener('click', function() {
            const filtersSection = document.getElementById('filtersSection');
            filtersSection.classList.toggle('hidden');
        });

        // Show/hide custom date range
        document.getElementById('dateFilter').addEventListener('change', function() {
            const customDateRange = document.getElementById('customDateRange');
            if (this.value === 'custom') {
                customDateRange.classList.remove('hidden');
            } else {
                customDateRange.classList.add('hidden');
            }
        });

        // Auto-submit form when filter changes (optional)
        // Uncomment if you want filters to apply immediately
        const filterInputs = document.querySelectorAll('select[name^="status"], select[name^="payment_method"], select[name^="shipping_status"], select[name^="date_filter"]');
        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>