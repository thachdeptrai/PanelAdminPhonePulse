<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

$pageTitle = 'Thùng rác Đơn hàng';

if (!isAdmin()) {
    die('<div class="text-red-500">Truy cập bị từ chối!</div>');
}

$orders = $mongoDB->orders->find([
    'is_deleted' => true
], [
    'sort' => ['deleted_at' => -1]
]);
$user_id_raw = $_SESSION['user_id'] ?? null;
if (!$user_id_raw) {
    header('Location: dang_nhap');
    exit;
}
try {
    $user_id = new ObjectId($user_id_raw);
} catch (Exception $e) {
    die("ID phiên không hợp lệ");
}
$user = $mongoDB->users->findOne(['_id' => $user_id]);
include '../includes/sidebar.php';
$match['is_deleted'] = true;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</head>
<body>
    

<div class="ml-64 p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-white">🗑️ Thùng rác</h1>
        <a href="orders" class="text-blue-400 hover:text-blue-300">← Quay lại danh sách đơn</a>
    </div>

    <div class="bg-gray-800 rounded-lg overflow-x-auto">
        <table class="w-full table-auto text-sm">
            <thead class="bg-gray-900 border-b border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left">Mã đơn</th>
                    <th class="px-4 py-3 text-left">Khách hàng</th>
                    <th class="px-4 py-3 text-center">Tổng tiền</th>
                    <th class="px-4 py-3 text-center">Trạng thái</th>
                    <th class="px-4 py-3 text-center">Thông tin xóa</th>
                    <th class="px-4 py-3 text-center">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-gray-700 transition-colors">
                        <td class="px-4 py-3 font-mono text-blue-400"><?= htmlspecialchars($order['_id']) ?></td>
                        <td class="px-4 py-3">
                            <div><?= htmlspecialchars($user['name'] ?? 'Không xác định') ?></div>
                            <div class="text-xs text-gray-400">
                                <?= htmlspecialchars($user['email'] ?? '') ?>
                                <?= htmlspecialchars($user['phone'] ?? '') ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold text-green-400">
                            <?= number_format($order['final_price'] ?? 0) ?>đ
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs bg-red-600">
                                <?= htmlspecialchars($order['status'] ?? '---') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-400">
                            <?= isset($order['deleted_at']) ? date('d/m/Y H:i', $order['deleted_at']->toDateTime()->getTimestamp()) : '---' ?>
                        </td>
                        <td class="px-4 py-3 text-center space-x-2">
                            <a href="/ajax/restore_order.php?id=<?= $order['_id'] ?>" 
                               class="text-green-400 hover:text-green-300 text-xs"
                               onclick="return confirm('Khôi phục đơn hàng này?')">Khôi phục</a>
                            <a href="/ajax/permanent_delete_order.php?id=<?= $order['_id'] ?>" 
                               class="text-red-400 hover:text-red-300 text-xs"
                               onclick="return confirm('Xóa vĩnh viễn đơn này?')">Xóa vĩnh viễn</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'restored'): ?>
        <script>
            Toastify({
                text: "✅ Khôi phục đơn hàng thành công!",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#10B981",
            }).showToast();
        </script>
        <?php endif; ?>
        <?php if (empty($orders)): ?>
            <div class="px-6 py-8 text-center text-gray-400">
                Không có đơn hàng nào trong thùng rác
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>