<?php
include '../includes/config.php';
include '../includes/functions.php';
include '../includes/delivery_ai.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$orderId = $_POST['order_id'] ?? '';
$status = $_POST['status'] ?? '';
$shipping = $_POST['shipping_status'] ?? '';
$payment = $_POST['payment_status'] ?? '';

if (!$orderId) {
    echo "Thiếu ID đơn hàng";
    exit;
}

// Lấy trạng thái hiện tại từ DB
$stmt = $pdo->prepare("SELECT status, shipping_status, payment_status, shipping_address FROM orders WHERE mongo_id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo "❌ Không tìm thấy đơn hàng.";
    exit;
}

// 🧠 Logic chặn rollback trạng thái (không cho quay ngược)
$allowedStatusFlow = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => [],
    'cancelled' => [],
];

$allowedShippingFlow = [
    'not_shipped' => ['shipping', 'shipped'],
    'shipping'    => ['shipped'],
    'shipped'     => [],
];

$allowedPaymentFlow = [
    'unpaid'   => ['paid', 'refunded'],
    'paid'     => ['refunded'],
    'refunded' => [],
];

function isValidFlow($current, $new, $flow) {
    return $current === $new || in_array($new, $flow[$current] ?? []);
}

if (!isValidFlow($order['status'], $status, $allowedStatusFlow)) {
    header("Location: /order_detail?id=$orderId&type=error&msg=" . urlencode("Không được quay ngược trạng thái đơn hàng."));
    exit;
}

if (!isValidFlow($order['shipping_status'], $shipping, $allowedShippingFlow)) {
    header("Location: /order_detail?id=$orderId&type=error&msg=" . urlencode("Không được quay ngược trạng thái đơn hàng."));
    exit;
}

if (!isValidFlow($order['payment_status'], $payment, $allowedPaymentFlow)) {
    header("Location: /order_detail?id=$orderId&type=error&msg=" . urlencode("Không được quay ngược trạng thái đơn hàng."));
    exit;
}

// Xử lý update AI ngày giao hàng nếu cần
$now = date('Y-m-d H:i:s');
$extraSql = "";
$extraParams = [];

if ($shipping === 'shipping') {
    $estimatedDays = estimateShippingDaysAI($order['shipping_address']);
    $shippingDate = $now;
    $deliveredDate = date('Y-m-d H:i:s', strtotime("+$estimatedDays days"));
    $extraSql = ", shipping_date = ?, delivered_date = ?";
    $extraParams = [$shippingDate, $deliveredDate];

} elseif ($shipping === 'shipped') {
    $deliveredDate = $now;
    $extraSql = ", delivered_date = ?";
    $extraParams = [$deliveredDate];

} elseif ($shipping === 'not_shipped') {
    $extraSql = ", shipping_date = NULL, delivered_date = NULL";
}

// Gộp SQL và Params
$sql = "UPDATE orders SET 
            status = ?, 
            shipping_status = ?, 
            payment_status = ?, 
            modified_date = NOW()
            $extraSql 
        WHERE mongo_id = ?";
$params = array_merge([$status, $shipping, $payment], $extraParams, [$orderId]);

// Thực thi
$stmt = $pdo->prepare($sql);
$success = $stmt->execute($params);

// Kết quả
if ($success) {
    header("Location: /order_detail?id=$orderId&msg=Cập Nhật Thành Công");
    exit;
} else {
    header("Location: /order_detail?id=$orderId&type=error&msg=" . urlencode("Cập nhật thất bại."));
}
