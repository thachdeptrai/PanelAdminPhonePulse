<?php
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? '';
$field = $data['field'] ?? '';
$value = $data['value'] ?? '';

$allowedFields = ['status', 'shipping_status', 'payment_status'];

if (!in_array($field, $allowedFields)) {
    echo json_encode(['success' => false, 'message' => 'Trường không hợp lệ']);
    exit;
}

$stmt = $pdo->prepare("UPDATE orders SET `$field` = ? WHERE mongo_id = ?");
if ($stmt->execute([$value, $id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật']);
}
