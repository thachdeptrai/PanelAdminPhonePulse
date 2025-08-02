<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die('Không có user_id');
}
try {
    $user = $mongo->users->findOne(['_id' => new ObjectId($user_id)]);
} catch (Exception $e) {
    die('User không tồn tại');
}


$settings = $mongoDB->settings->findOne([]) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'site_name' => $_POST['site_name'] ?? '',
        'hotline' => $_POST['hotline'] ?? '',
        'email' => $_POST['email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'facebook_url' => $_POST['facebook_url'] ?? '',
        'zalo_url' => $_POST['zalo_url'] ?? '',
        'youtube_url' => $_POST['youtube_url'] ?? '',
        'meta_title' => $_POST['meta_title'] ?? '',
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
    ];

    if (isset($settings['_id'])) {
        $mongoDB->settings->updateOne(['_id' => $settings['_id']], ['$set' => $updateData]);
    } else {
        $updateData['created_at'] = new MongoDB\BSON\UTCDateTime();
        $mongoDB->settings->insertOne($updateData);
    }
    header("Location: settings?msg=updated");

    exit;
}
include '../includes/sidebar.php';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cài đặt Website</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">

</head>
<body class="bg-gray-900 text-white flex">
<div class="ml-64 w-full p-6">
    <h1 class="text-2xl font-bold mb-4">⚙️ Cài đặt hệ thống</h1>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="bg-green-700 text-white px-4 py-2 rounded mb-4">Cập nhật cài đặt thành công!</div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm mb-1">Tên website</label>
            <input name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div>
            <label class="block text-sm mb-1">Hotline</label>
            <input name="hotline" value="<?= htmlspecialchars($settings['hotline'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div>
            <label class="block text-sm mb-1">Email</label>
            <input name="email" value="<?= htmlspecialchars($settings['email'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div>
            <label class="block text-sm mb-1">Địa chỉ</label>
            <input name="address" value="<?= htmlspecialchars($settings['address'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div>
            <label class="block text-sm mb-1">Facebook URL</label>
            <input name="facebook_url" value="<?= htmlspecialchars($settings['facebook_url'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div>
            <label class="block text-sm mb-1">Zalo URL</label>
            <input name="zalo_url" value="<?= htmlspecialchars($settings['zalo_url'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div>
            <label class="block text-sm mb-1">YouTube URL</label>
            <input name="youtube_url" value="<?= htmlspecialchars($settings['youtube_url'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm mb-1">Meta Title</label>
            <input name="meta_title" value="<?= htmlspecialchars($settings['meta_title'] ?? '') ?>" class="w-full bg-gray-800 p-2 rounded" />
        </div>

        <div class="md:col-span-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">💾 Lưu cài đặt</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</body>
</html>
