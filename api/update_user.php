<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Lấy dữ liệu từ POST
$idRaw  = $_POST['mongo_id'] ?? null;
$name   = $_POST['name'] ?? '';
$email  = $_POST['email'] ?? '';
$phone  = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$gender  = $_POST['gender'] ?? '';
$role   = (int)($_POST['role'] ?? 0);
$status = (int)($_POST['status'] ?? 1);
$is_verified = (int)($_POST['is_verified'] ?? 0);

if (!$idRaw || !$name || !$email) {
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
    exit;
}

// Convert ObjectId
try {
    $mongoId = new ObjectId($idRaw);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

// Cập nhật dữ liệu
try {
    $result = $mongoDB->users->updateOne(
        ['_id' => $mongoId],
        ['$set' => [
            'name'        => $name,
            'email'       => $email,
            'phone'       => $phone,
            'address'     => $address,
            'gender'      => $gender,
            'role'        => $role,
            'status'      => $status,
            'is_verified' => $is_verified,
            'updated_at'  => new UTCDateTime()
        ]]
    );

    if ($result->getModifiedCount()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không có thay đổi hoặc người dùng không tồn tại']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $e->getMessage()]);
}
