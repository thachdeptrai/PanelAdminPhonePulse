<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    echo "Thiếu ID";
    exit;
}

try {
    $objectId = new ObjectId($id);
} catch (Exception $e) {
    echo "ID không hợp lệ";
    exit;
}

// ✅ Cập nhật is_deleted thay vì xoá thật
$updateResult = $mongoDB->orders->updateOne(
    ['_id' => $objectId],
    ['$set' => [
        'is_deleted' => true,
        'deleted_at' => new UTCDateTime()
    ]]
);

// Sau khi update thành công
if ($updateResult->getModifiedCount() > 0) {
    header("Location: ../orders?msg=trashed");
    exit;
} else {
    echo "Chuyển vào thùng rác thất bại.";
}
