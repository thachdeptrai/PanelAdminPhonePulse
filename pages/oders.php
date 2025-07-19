<?php
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$shipping_status_filter = isset($_GET['shipping_status']) ? $_GET['shipping_status'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$price_from = isset($_GET['price_from']) ? (float)$_GET['price_from'] : 0;
$price_to = isset($_GET['price_to']) ? (float)$_GET['price_to'] : 0;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$conditions = [];
$params = [];

// Search condition
if ($search) {
    $conditions[] = "(o.mongo_id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

// Status filter
if ($status_filter) {
    $conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

// Payment method filter
if ($payment_method_filter) {
    $conditions[] = "o.payment_method = ?";
    $params[] = $payment_method_filter;
}

// Shipping status filter
if ($shipping_status_filter) {
    $conditions[] = "o.shipping_status = ?";
    $params[] = $shipping_status_filter;
}

// Date filter
if ($date_filter || ($date_from && $date_to)) {
    switch ($date_filter) {
        case 'today':
            $conditions[] = "DATE(o.created_date) = CURDATE()";
            break;
        case 'yesterday':
            $conditions[] = "DATE(o.created_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case '7days':
            $conditions[] = "o.created_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'this_month':
            $conditions[] = "YEAR(o.created_date) = YEAR(CURDATE()) AND MONTH(o.created_date) = MONTH(CURDATE())";
            break;
        case 'custom':
            if ($date_from && $date_to) {
                $conditions[] = "DATE(o.created_date) BETWEEN ? AND ?";
                $params[] = $date_from;
                $params[] = $date_to;
            }
            break;
    }
}

// Price range filter
if ($price_from > 0) {
    $conditions[] = "o.final_price >= ?";
    $params[] = $price_from;
}
if ($price_to > 0) {
    $conditions[] = "o.final_price <= ?";
    $params[] = $price_to;
}

// Combine conditions
$whereSql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Main query
$sql = "SELECT o.*, u.name, u.email, u.phone 
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.mongo_id
        $whereSql
        ORDER BY o.created_date DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Count query
$countSql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.mongo_id $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = $countStmt->fetch()['total'];
$totalPages = ceil($totalOrders / $limit);

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get filter options from database
$statusOptions = $pdo->query("SELECT DISTINCT status FROM orders WHERE status IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$paymentMethodOptions = $pdo->query("SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$shippingStatusOptions = $pdo->query("SELECT DISTINCT shipping_status FROM orders WHERE shipping_status IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
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
            // Get statistics
            $statsQuery = "SELECT 
                COUNT(*) as total_orders,
                SUM(final_price) as total_revenue,
                AVG(final_price) as avg_order_value,
                COUNT(CASE WHEN o.status = 'confirmed' THEN 1 END) as completed_orders
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.mongo_id 
                $whereSql";
            $statsStmt = $pdo->prepare($statsQuery);
            $statsStmt->execute($params);
            $stats = $statsStmt->fetch();
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
                            <td class="px-4 py-3 font-mono text-blue-400"><?= htmlspecialchars($order['mongo_id']) ?></td>
                            <td class="px-4 py-3">
                                <div><?= htmlspecialchars($order['name'] ?? 'Không xác định') ?></div>
                                <div class="text-xs text-gray-400">
                                    <?= htmlspecialchars($order['email'] ?? '') ?>
                                    <?= htmlspecialchars($order['phone'] ?? '') ?>
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
                                <a href="order_detail?id=<?= $order['mongo_id'] ?>" 
                                   class="text-blue-400 hover:text-blue-300 text-xs">Chi tiết</a>
                                <a href="/ajax/delete_order?id=<?= $order['mongo_id'] ?>" 
                                   class="text-red-400 hover:text-red-300 text-xs" 
                                   onclick="return confirm('Xác nhận xóa đơn hàng?')">Xóa</a>
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