<?php
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$searchSql = $search ? "WHERE o.status LIKE ? OR u.name LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$sql = "SELECT o.*, u.name 
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.mongo_id
        $searchSql
        ORDER BY o.created_date DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.id $searchSql");
$countStmt->execute($params);
$totalOrders = $countStmt->fetch()['total'];
$totalPages = ceil($totalOrders / $limit);
 // Get user data
 $user_id = $_SESSION['user_id'];
 $stmt    = $pdo->prepare("SELECT * FROM users WHERE id = ?");
 $stmt->execute([$user_id]);
 $user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Đơn hàng</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-900 text-white">
    <?php include '../includes/sidebar.php'; ?>

    <div class="ml-64 flex-1 p-6">
        <header class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">📦 Quản lý Đơn hàng</h1>
        </header>

        <!-- Search -->
        <form method="GET" class="mb-4 flex items-center gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm theo tên khách, trạng thái..."
                   class="px-4 py-2 bg-gray-800 text-white rounded-lg w-full max-w-md border border-gray-600 focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-white font-medium">Tìm</button>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto bg-dark-light rounded-lg">
            <table class="w-full table-auto text-sm">
                <thead class="bg-dark border-b border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left">Mã</th>
                        <th class="px-4 py-3 text-left">Khách hàng</th>
                        <th class="px-4 py-3 text-center">Tổng tiền</th>
                        <th class="px-4 py-3 text-center">Trạng thái</th>
                        <th class="px-4 py-3 text-center">Thanh toán</th>
                        <th class="px-4 py-3 text-center">Vận chuyển</th>
                        <th class="px-4 py-3 text-center">Ngày đặt</th>
                        <th class="px-4 py-3 text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-gray-800">
                            <td class="px-4 py-3"><?= htmlspecialchars($order['mongo_id']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($order['name'] ?? 'Không xác định') ?></td>
                            <td class="px-4 py-3 text-center"><?= number_format($order['final_price'], 0, ',', '.') ?>đ</td>
                            <td class="px-4 py-3 text-center"><?= htmlspecialchars($order['status']) ?></td>
                            <td class="px-4 py-3 text-center"><?= htmlspecialchars($order['payment_method']) ?> <br>
                                <span class="text-xs text-gray-400"><?= $order['payment_status'] ?></span>
                            </td>
                            <td class="px-4 py-3 text-center"><?= htmlspecialchars($order['shipping_status']) ?></td>
                            <td class="px-4 py-3 text-center"><?= date('d/m/Y H:i', strtotime($order['created_date'])) ?></td>
                            <td class="px-4 py-3 text-center space-x-2">
                                <a href="order_detail?id=<?= $order['mongo_id'] ?>" class="text-blue-400 hover:text-blue-300">Chi tiết</a>
                                <a href="/ajax/delete_order?id=<?= $order['mongo_id'] ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('Xác nhận xóa đơn hàng?')">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach ?>
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
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="px-3 py-2 bg-gray-700 rounded-lg">← Trước</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                       class="px-3 py-2 rounded-lg <?= $i == $page ? 'bg-blue-600' : 'bg-gray-700' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="px-3 py-2 bg-gray-700 rounded-lg">Tiếp →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
