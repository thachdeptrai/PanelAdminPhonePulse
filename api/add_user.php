<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$name   = trim($_POST['name'] ?? '');
$email  = trim($_POST['email'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$role   = isset($_POST['role']) && $_POST['role'] == '1' ? true : false;
$status = isset($_POST['status']) && $_POST['status'] == '1' ? true : false;

if (!$name || !$email) {
    echo json_encode(['success' => false, 'message' => 'Tên và Email là bắt buộc']);
    exit;
}

try {
    // Check trùng email
    $exists = $mongo->users->findOne(['email' => $email]);
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'Email đã tồn tại']);
        exit;
    }

    // Insert dữ liệu
    $insertData = [
        'name'         => $name,
        'email'        => $email,
        'phone'        => $phone,
        'role'         => $role,
        'status'       => $status,
        'is_verified'  => false,
        'created_date' => new MongoDB\BSON\UTCDateTime()
    ];

    $mongo->users->insertOne($insertData);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi thêm người dùng']);
}
