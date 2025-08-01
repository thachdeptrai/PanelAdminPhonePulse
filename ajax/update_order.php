<?php
include '../includes/config.php';
include '../includes/functions.php';
include '../includes/delivery_ai.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$orderId = $_POST['order_id'] ?? '';
$status = $_POST['status'] ?? '';
$shipping = $_POST['shipping_status'] ?? '';
$payment = $_POST['payment_status'] ?? '';

if (!$orderId || !preg_match('/^[a-f\d]{24}$/i', $orderId)) {
    echo "❌ Thiếu hoặc sai định dạng ID đơn hàng.";
    exit;
}

$objectId = new ObjectId($orderId);

// 🔍 Lấy đơn hàng hiện tại
$order = $mongoDB->orders->findOne(['_id' => $objectId], [
    'projection' => ['status' => 1, 'shipping_status' => 1, 'payment_status' => 1, 'shipping_address' => 1]
]);

if (!$order) {
    echo "❌ Không tìm thấy đơn hàng.";
    exit;
}

// 🧠 Flow hạn chế rollback
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
    header("Location: /order_detail?id=$orderId&type=error&msg=" . urlencode("Không được quay ngược trạng thái giao hàng."));
    exit;
}

if (!isValidFlow($order['payment_status'], $payment, $allowedPaymentFlow)) {
    header("Location: /order_detail?id=$orderId&type=error&msg=" . urlencode("Không được quay ngược trạng thái thanh toán."));
    exit;
}

// 🧠 Xử lý AI nếu chuyển trạng thái sang shipping
$updateFields = [
    'status' => $status,
    'shipping_status' => $shipping,
    'payment_status' => $payment,
    'updatedAt' => new UTCDateTime()
];

if ($shipping === 'shipping') {
    $estimatedDays = estimateShippingDaysAI($order['shipping_address']);
    $updateFields['shipping_date'] = new UTCDateTime();
    $updateFields['delivered_date'] = new UTCDateTime(strtotime("+$estimatedDays days") * 1000);
} elseif ($shipping === 'shipped') {
    $updateFields['delivered_date'] = new UTCDateTime();
} elseif ($shipping === 'not_shipped') {
    $updateFields['shipping_date'] = null;
    $updateFields['delivered_date'] = null;
}

// ✅ Cập nhật MongoDB
$updateResult = $mongoDB->orders->updateOne(
    ['_id' => $objectId],
    ['$set' => $updateFields]
);

if ($updateResult->getModifiedCount() > 0) {
    $mongoDB->logs->insertOne([
        'admin_id' => new ObjectId($_SESSION['admin_id']), // ✅ đúng tên biến session
        'action'   => 'UPDATE',
        'module'   => 'ORDER',
        'time'     => new UTCDateTime(),
        'details'  => json_encode(value: [
            'order_id'  => (string)$orderId, // ✅ chuẩn hơn nếu đang làm với đơn hàng
            'message'   => 'Cập nhật đơn hàng thành công',
            'timestamp' => date('Y-m-d H:i:s')
        ]),
        'created_at' => new UTCDateTime(),
        'updated_at' => new UTCDateTime()
    ]);
    header("Location: /order_detail?id=$orderId&msg=Cập Nhật Thành Công");
    exit;
} else {
    header("Location: /order_detail?id=$orderId&type=error&msg=" . urlencode("Không có thay đổi hoặc cập nhật thất bại."));
}
?>
