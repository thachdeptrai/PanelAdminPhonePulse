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

// Lấy địa chỉ giao hàng từ DB
$stmt = $pdo->prepare("SELECT shipping_address FROM orders WHERE mongo_id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
$address = $order['shipping_address'] ?? '';

$now = date('Y-m-d H:i:s');

// Chuẩn bị SQL động
$extraSql = "";
$extraParams = [];

if ($shipping === 'shipping') {
    $estimatedDays = estimateShippingDaysAI($address); // 🧠 Dùng AI tính ngày
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
    header("Location: /order_detail?id=$orderId&msg=updated");
    $logMessage = sprintf(
        "Order ID %s updated successfully. Status: %s, Shipping: %s, Payment: %s",
        $orderId,
        $status,
        $shipping,
        $payment
    );
    error_log($logMessage);
        
    exit;
} else {
    echo "❌ Cập nhật thất bại.";
}
