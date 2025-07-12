<?php
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    echo "Thiếu ID";
    exit;
}

$stmt = $pdo->prepare("DELETE FROM orders WHERE mongo_id = ?");
$success = $stmt->execute([$id]);

if ($success) {
    header("Location: orders.php?msg=deleted");
} else {
    echo "Xóa thất bại.";
}
