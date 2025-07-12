<?php
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$orderId = $_GET['id'] ?? '';
if (!$orderId) {
    echo "Thiếu ID đơn hàng.";
    exit;
}

// Lấy đơn hàng
$stmt = $pdo->prepare("SELECT o.*, u.name, u.email 
                       FROM orders o 
                       LEFT JOIN users u ON o.user_id = u.mongo_id
                       WHERE o.mongo_id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo "Không tìm thấy đơn hàng.";
    exit;
}

// Giải mã items_json
$rawItems = json_decode($order['items_json'], true);
$items = [];

foreach ($rawItems as $item) {
    $productId = $item['productId'];
    $variantId = $item['variantId'];
    $quantity = $item['quantity'];

    // Lấy thông tin sản phẩm
    $stmtProd = $pdo->prepare("SELECT product_name FROM products WHERE mongo_id = ?");
    $stmtProd->execute([$productId]);
    $product = $stmtProd->fetch();

    // Lấy thông tin biến thể
    $stmtVar = $pdo->prepare("
        SELECT v.price, v.color_id, v.size_id, 
               c.color_name AS color_name, s.size_name AS size_name
        FROM variants v
        LEFT JOIN colors c ON v.color_id = c.mongo_id
        LEFT JOIN sizes s ON v.size_id = s.mongo_id
        WHERE v.mongo_id = ?
    ");
    $stmtVar->execute([$variantId]);
    $variant = $stmtVar->fetch();

    if ($product && $variant) {
        $items[] = [
            'product_name' => $product['product_name'],
            'quantity' => $quantity,
            'price' => $variant['price'],
            'color_name' => $variant['color_name'] ?? '-',
            'size_name' => $variant['size_name'] ?? '-',
            'total_price' => $quantity * $variant['price']
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết đơn hàng</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    <div class="max-w-5xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-4">🧾 Chi tiết đơn hàng: <?= $order['mongo_id'] ?></h1>

        <div class="mb-6">
            <p><strong>Khách hàng:</strong> <?= htmlspecialchars($order['name']) ?> (<?= htmlspecialchars($order['email']) ?>)</p>
            <p><strong>Ngày đặt:</strong> <?= date('d/m/Y H:i', strtotime($order['created_date'])) ?></p>
            <p><strong>Địa chỉ giao:</strong> <?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
            <p><strong>Ghi chú:</strong> <?= nl2br(htmlspecialchars($order['note'])) ?></p>
            <p><strong>Trạng thái:</strong> <?= htmlspecialchars($order['status']) ?></p>
            <p><strong>Phương thức thanh toán:</strong> <?= htmlspecialchars($order['payment_method']) ?> (<?= htmlspecialchars($order['payment_status']) ?>)</p>
            <p><strong>Trạng thái vận chuyển:</strong> <?= htmlspecialchars($order['shipping_status']) ?></p>
            <p><strong>Ngày cập nhật:</strong> <?= date('d/m/Y H:i', strtotime($order['modified_date'])) ?></p>
            <p><strong>Ngày vận chuyển:</strong> <?= $order['shipping_date'] ? date('d/m/Y H:i', strtotime($order['shipping_date'])) : 'Chưa vận chuyển' ?></p>
            <?php
                // Nếu đã có ngày giao hàng → hiển thị
                if (!empty($order['delivered_date'])) {
                    $estimatedDeliveryDate = date('d/m/Y', strtotime($order['delivered_date']));
                }
                // Nếu đã bắt đầu giao → tính lại ngày dự kiến theo AI
                elseif ($order['shipping_status'] === 'shipping' && !empty($order['shipping_date'])) {
                    require_once '../includes/delivery_ai.php';
                    $aiDays = estimateShippingDaysAI($order['shipping_address']);
                    $estimatedTimestamp = strtotime("+" . $aiDays . " days", strtotime($order['shipping_date']));
                    $estimatedDeliveryDate = date('d/m/Y', $estimatedTimestamp);
                }
                // Nếu chưa giao
                else {
                    $estimatedDeliveryDate = "Chưa có dự kiến giao";
                }
                ?>
            <p><strong>Ngày nhận hàng dự kiến:</strong> <?= $order['delivered_date'] ? date('d/m/Y H:i', strtotime($order['delivered_date'])) : $estimatedDeliveryDate ?></p>
            <p><strong>Ngày tạo:</strong> <?= date('d/m/Y H:i', strtotime($order['created_date'])) ?></p>
        </div>

        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-700 text-left">
                    <tr>
                        <th class="px-4 py-2">Sản phẩm</th>
                        <th class="px-4 py-2 text-center">Màu</th>
                        <th class="px-4 py-2 text-center">Size</th>
                        <th class="px-4 py-2 text-center">SL</th>
                        <th class="px-4 py-2 text-center">Giá</th>
                        <th class="px-4 py-2 text-center">Thành tiền</th>
                    </tr>
                </thead>
                <?php $total = 0; foreach ($items as $item): ?>
            <tr class="border-t border-gray-700">
                <td class="px-4 py-2"><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="px-4 py-2 text-center"><?= htmlspecialchars($item['color_name']) ?></td>
                <td class="px-4 py-2 text-center"><?= htmlspecialchars($item['size_name']) ?></td>
                <td class="px-4 py-2 text-center"><?= $item['quantity'] ?></td>
                <td class="px-4 py-2 text-center"><?= number_format($item['price'], 0, ',', '.') ?>đ</td>
                <td class="px-4 py-2 text-center"><?= number_format($item['total_price'], 0, ',', '.') ?>đ</td>
            </tr>
            <?php $total += $item['total_price']; endforeach; ?>
            </table>
        </div>

        <div class="mt-6 text-right text-lg">
            <strong>Tổng đơn:</strong> <?= number_format($order['final_price'], 0, ',', '.') ?>đ
        </div>
        <h2 class="text-xl font-bold mt-10 mb-3">🛠️ Cập nhật trạng thái</h2>

        <form action="/ajax/update_order" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-800 p-4 rounded-lg">
            <input type="hidden" name="order_id" value="<?= $order['mongo_id'] ?>">

            <!-- Trạng thái đơn -->
            <div>
                <label class="block text-sm mb-1">Trạng thái đơn</label>
                <select name="status" class="w-full bg-gray-700 text-white p-2 rounded">
                    <?php
                    $statuses = ['pending', 'confirmed', 'cancelled'];
                    foreach ($statuses as $s):
                    ?>
                    <option value="<?= $s ?>" <?= $order['status'] == $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Trạng thái vận chuyển -->
            <div>
                <label class="block text-sm mb-1">Vận chuyển</label>
                <select name="shipping_status" class="w-full bg-gray-700 text-white p-2 rounded">
                    <?php
                    $shippingStatuses = ['not_shipped', 'shipping', 'shipped'];
                    foreach ($shippingStatuses as $ss):
                    ?>
                    <option value="<?= $ss ?>" <?= $order['shipping_status'] == $ss ? 'selected' : '' ?>><?= ucfirst($ss) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Trạng thái thanh toán -->
            <div>
                <label class="block text-sm mb-1">Thanh toán</label>
                <select name="payment_status" class="w-full bg-gray-700 text-white p-2 rounded">
                    <?php
                    $paymentStatuses = ['unpaid', 'paid', 'refunded'];
                    foreach ($paymentStatuses as $ps):
                    ?>
                    <option value="<?= $ps ?>" <?= $order['payment_status'] == $ps ? 'selected' : '' ?>><?= ucfirst($ps) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-span-full text-right mt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-5 py-2 rounded font-medium">
                    💾 Lưu thay đổi
                </button>
            </div>
        </form>
        <div class="mt-8 flex justify-end space-x-4">
            <a href="orders" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg">← Quay lại</a>
        </div>
    </div>
</body>
</html>
