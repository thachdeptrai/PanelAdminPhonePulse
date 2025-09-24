
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
use MongoDB\BSON\ObjectId;


// ✅ Check quyền admin
if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

// ✅ Check session
$user_id_raw = $_SESSION['user_id'] ?? null;
if (!$user_id_raw) {
    header('Location: dang_nhap');
    exit;
}
$userId = new ObjectId($_SESSION['user_id']);
$user = $mongoDB->users->findOne(['_id' => $userId]);
if (!$user) die("Không tìm thấy người dùng");
// ✅ Validate ObjectId
try {
    $user_id = new ObjectId($user_id_raw);
} catch (Exception $e) {
    die("ID phiên không hợp lệ");
}

// ✅ Lấy thông tin user
$user = $mongoDB->users->findOne(['_id' => $user_id]);
if (!$user) {
    die("Không tìm thấy người dùng");
}

// ✅ Xử lý các actions (chỉ delete và update_status, send sẽ qua API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete') {
        $notif_id = $_POST['notification_id'] ?? '';
        try {
            $result = $mongoDB->Notification->deleteOne(['_id' => new ObjectId($notif_id)]);
            if ($result->getDeletedCount() > 0) {
                $success_message = "Đã xóa thông báo thành công!";
            } else {
                $error_message = "Không tìm thấy thông báo để xóa!";
            }
        } catch (Exception $e) {
            $error_message = "Lỗi xóa thông báo: " . $e->getMessage();
        }
    }
    
    if ($action === 'update_status') {
        $notif_id = $_POST['notification_id'] ?? '';
        $new_status = $_POST['status'] ?? 'pending';
        try {
            $result = $mongoDB->Notification->updateOne(
                ['_id' => new ObjectId($notif_id)],
                ['$set' => ['status' => $new_status]]
            );
            if ($result->getModifiedCount() > 0) {
                $success_message = "Đã cập nhật trạng thái thành công!";
            } else {
                $error_message = "Không tìm thấy thông báo để cập nhật!";
            }
        } catch (Exception $e) {
            $error_message = "Lỗi cập nhật trạng thái: " . $e->getMessage();
        }
    }
    
    if ($action === 'resend') {
        $notif_id = $_POST['notification_id'] ?? '';
        try {
            $notification = $mongoDB->Notification->findOne(['_id' => new ObjectId($notif_id)]);
            if ($notification) {
                // Reset status to pending
                $mongoDB->Notification->updateOne(
                    ['_id' => new ObjectId($notif_id)],
                    ['$set' => ['status' => 'pending']]
                );
                
                // Prepare data for API call
                $apiData = [
                    'notification_id' => $notif_id,
                    'resend' => true
                ];
                
                $success_message = "Thông báo đã được đặt lại trạng thái để gửi lại!";
            }
        } catch (Exception $e) {
            $error_message = "Lỗi gửi lại thông báo: " . $e->getMessage();
        }
    }
}

// ✅ Lấy danh sách notifications với phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$skip = ($page - 1) * $limit;

$notifications = [];
$cursor = $mongoDB->Notification->find(
    [],
    [
        'sort' => ['createdAt' => -1],
        'limit' => $limit,
        'skip' => $skip
    ]
);

foreach ($cursor as $notification) {
    $notifications[] = $notification;
}

// ✅ Tổng số notifications để phân trang
$totalNotifications = $mongoDB->Notification->countDocuments();
$totalPages = ceil($totalNotifications / $limit);

// ✅ Thống kê notifications
$stats = [
    'total' => $mongoDB->Notification->countDocuments(),
    'pending' => $mongoDB->Notification->countDocuments(['status' => 'pending']),
    'sent' => $mongoDB->Notification->countDocuments(['status' => 'sent']),
    'failed' => $mongoDB->Notification->countDocuments(['status' => 'failed'])
];

// ✅ Lấy danh sách users có FCM token
$usersWithFCM = [];
$userCursor = $mongoDB->users->find(
    ['fcm_token' => ['$exists' => true, '$ne' => '']], 
    ['limit' => 100, 'projection' => ['name' => 1, 'email' => 1, 'fcm_token' => 1]]
);
foreach ($userCursor as $u) {
    $usersWithFCM[] = $u;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['meta_title'] ?? 'Quản Lý Thông Báo') ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #3B82F6;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="content-area ml-64 flex-1 min-h-screen">
        <!-- Top Navigation -->
        <header class="bg-dark-light border-b border-dark px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <h1 class="text-2xl font-semibold">Quản Lý Thông Báo Push</h1>

            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-400">
                    <?= count($usersWithFCM) ?> thiết bị có thể nhận thông báo
                </span>
                
                <button class="relative text-gray-400 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500 notification-dot"></span>
                </button>

                <div class="dropdown relative">
                    <button class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-primary-light flex items-center justify-center text-white">
                            <?php echo strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="hidden md:inline"><?php echo htmlspecialchars(explode('@', $user['name'])[0]) ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div class="dropdown-content mt-2 w-48 rounded-md shadow-lg py-1 z-50">
                        <a href="profile" class="block px-4 py-2 text-sm hover:bg-dark-light">Hồ sơ</a>
                        <a href="settings" class="block px-4 py-2 text-sm hover:bg-dark-light">Cài đặt</a>
                        <div class="border-t border-gray-700"></div>
                        <a href="dang_xuat" class="block px-4 py-2 text-sm text-red-400 hover:bg-dark-light">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="p-6">
            <!-- Thông báo success/error -->
            <div id="alertContainer">
                <?php if (isset($success_message)): ?>
                    <div class="bg-green-600 text-white px-4 py-3 rounded mb-6">
                        <p><?= htmlspecialchars($success_message) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="bg-red-600 text-white px-4 py-3 rounded mb-6">
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Thống kê -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="card p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400">Tổng Thông Báo</p>
                            <p class="text-2xl font-semibold mt-1"><?= number_format($stats['total']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400">Chờ Gửi</p>
                            <p class="text-2xl font-semibold mt-1"><?= number_format($stats['pending']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-500 rounded-lg flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12,6 12,12 16,14"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400">Đã Gửi</p>
                            <p class="text-2xl font-semibold mt-1"><?= number_format($stats['sent']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                                <polyline points="20,6 9,17 4,12"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="card p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400">Thất Bại</p>
                            <p class="text-2xl font-semibold mt-1"><?= number_format($stats['failed']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form tạo thông báo mới -->
            <div class="card p-6 rounded-lg mb-8">
                <h2 class="text-lg font-semibold mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                    Gửi Thông Báo Push Mới
                </h2>
                
                <form id="notificationForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Tiêu đề thông báo</label>
                            <input type="text" id="title" required 
                                   placeholder="Nhập tiêu đề..." 
                                   class="w-full px-3 py-2 bg-dark-light border border-gray-600 text-black rounded-lg focus:border-primary focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Loại gửi</label>
                            <select id="notifType" 
                                    class="w-full px-3 py-2 bg-dark-light border border-gray-600 text-black rounded-lg focus:border-primary focus:outline-none">
                                <option value="broadcast">📢 Broadcast (Gửi tất cả - <?= count($usersWithFCM) ?> thiết bị)</option>
                                <option value="personalized">👤 Cá nhân hóa (Chọn người nhận)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Nội dung thông báo</label>
                        <textarea id="body" rows="3" required 
                                  placeholder="Nhập nội dung thông báo..."
                                  class="w-full px-3 py-2 bg-dark-light border border-gray-600 text-black rounded-lg focus:border-primary focus:outline-none"></textarea>
                    </div>

                    <div id="userSelectionDiv" class="hidden">
                        <label class="block text-sm font-medium mb-2">Chọn người nhận</label>
                        <div class="max-h-40 overflow-y-auto border border-gray-600 rounded-lg p-3 bg-dark-light">
                            <?php foreach ($usersWithFCM as $u): ?>
                                <label class="flex items-center space-x-2 py-1 hover:bg-gray-700 rounded px-2">
                                <input type="checkbox" class="user-checkbox rounded border-gray-600 text-primary focus:ring-primary" value="<?= (string)$u['_id'] ?>" >
                                    <div class="flex-1">
                                        <div class="text-sm font-medium"><?= htmlspecialchars($u['name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($u['email'] ?? 'No email') ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                            
                            <?php if (empty($usersWithFCM)): ?>
                                <p class="text-gray-400 text-sm">Không có người dùng nào có FCM token</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-2 flex items-center justify-between">
                            <div class="text-sm text-gray-400">
                                <span id="selectedCount">0</span> người được chọn
                            </div>
                            <div class="space-x-2">
                                <button type="button" id="selectAll" class="text-sm text-primary hover:underline">Chọn tất cả</button>
                                <button type="button" id="clearAll" class="text-sm text-gray-400 hover:underline">Bỏ chọn</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            💡 Thông báo sẽ được gửi qua Firebase Cloud Messaging
                        </div>
                        <button type="submit" id="sendBtn"
                                class="bg-primary hover:bg-primary-dark px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 relative">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22,2 15,22 11,13 2,9"></polygon>
                            </svg>
                            <span>Gửi Thông Báo</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Danh sách thông báo -->
            <div class="card p-6 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10,9 9,9 8,9"></polyline>
                        </svg>
                        Lịch Sử Thông Báo
                    </h2>
                    <button onclick="refreshNotifications()" 
                            class="text-sm text-gray-400 hover:text-white flex items-center space-x-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                        </svg>
                        <span>Làm mới</span>
                    </button>
                </div>
                
                <div id="notificationsTableContainer" class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="text-left text-gray-400 text-sm border-b border-dark-light">
                                <th class="pb-3">Thông báo</th>
                                <th class="pb-3">Loại</th>
                                <th class="pb-3">Trạng thái</th>
                                <th class="pb-3">Kết quả</th>
                                <th class="pb-3">Thời gian</th>
                                <th class="pb-3">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="notificationTableBody">
                            <?php if (empty($notifications)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-400">
                                        <div class="flex flex-col items-center space-y-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600">
                                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                            </svg>
                                            <p>Chưa có thông báo nào được gửi</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr class="border-b border-dark-light hover:bg-dark-light/50">
                                        <td class="py-4">
                                            <div class="space-y-1">
                                                <div class="font-medium"><?= htmlspecialchars($notification['title']) ?></div>
                                                <div class="text-gray-400 text-sm max-w-xs truncate">
                                                    <?= htmlspecialchars(substr($notification['body'], 0, 80)) ?>
                                                    <?= strlen($notification['body']) > 80 ? '...' : '' ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?= $notification['type'] === 'broadcast' ? 'bg-blue-500 text-blue-100' : 'bg-purple-500 text-purple-100' ?>">
                                                <?= $notification['type'] === 'broadcast' ? '📢 Broadcast' : '👤 Cá nhân' ?>
                                            </span>
                                        </td>
                                        <td class="py-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?php 
                                                $status = $notification['status'] ?? 'pending';
                                                echo $status === 'sent' ? 'bg-green-500 text-green-100' : 
                                                     ($status === 'failed' ? 'bg-red-500 text-red-100' : 'bg-yellow-500 text-yellow-100');
                                                ?>">
                                                <?php
                                                $statusIcons = [
                                                    'sent' => '✅',
                                                    'failed' => '❌',
                                                    'pending' => '⏳'
                                                ];
                                                echo ($statusIcons[$status] ?? '⏳') . ' ' . ucfirst($status);
                                                ?>
                                            </span>
                                        </td>
                                        <td class="py-4">
                                            <?php if (isset($notification['sent_count']) || isset($notification['failed_count'])): ?>
                                                <div class="text-sm space-y-1">
                                                    <?php if (isset($notification['sent_count']) && $notification['sent_count'] > 0): ?>
                                                        <div class="text-green-400">✅ Gửi: <?= number_format($notification['sent_count']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (isset($notification['failed_count']) && $notification['failed_count'] > 0): ?>
                                                        <div class="text-red-400">❌ Lỗi: <?= number_format($notification['failed_count']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (isset($notification['total_tokens'])): ?>
                                                        <div class="text-gray-400">📱 Tổng: <?= number_format($notification['total_tokens']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">Chưa gửi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4">
                                            <div class="text-sm space-y-1">
                                                <div class="text-gray-300">
                                                    <?= date('d/m/Y', $notification['createdAt']->toDateTime()->getTimestamp()) ?>
                                                </div>
                                                <div class="text-gray-400">
                                                    <?= date('H:i:s', $notification['createdAt']->toDateTime()->getTimestamp()) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4">
                                            <div class="flex items-center space-x-2">
                                                <!-- Resend button for failed notifications -->
                                                <?php if ($notification['status'] === 'failed'): ?>
                                                    <button onclick="resendNotification('<?= (string)$notification['_id'] ?>')" 
                                                            class="text-blue-400 hover:text-blue-300 text-sm px-2 py-1 rounded border border-blue-400 hover:bg-blue-400/10">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1">
                                                            <polyline points="23 4 23 10 17 10"></polyline>
                                                            <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"></path>
                                                        </svg>
                                                        Gửi lại
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Delete button -->
                                                <form method="POST" class="inline-block" 
                                                      onsubmit="return confirm('Bạn có chắc muốn xóa thông báo này?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="notification_id" value="<?= (string)$notification['_id'] ?>">
                                                    <button type="submit" 
                                                            class="text-red-400 hover:text-red-300 text-sm p-1 rounded hover:bg-red-400/10">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3,6 5,6 21,6"></polyline>
                                                            <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex items-center justify-between mt-6 pt-6 border-t border-dark-light">
                        <div class="text-sm text-gray-400">
                            Hiển thị <?= count($notifications) ?> trong tổng <?= number_format($totalNotifications) ?> thông báo
                        </div>
                        
                        <div class="flex space-x-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" 
                                   class="px-3 py-2 bg-dark-light border border-gray-600 rounded-lg hover:bg-dark text-sm flex items-center space-x-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="15,18 9,12 15,6"></polyline>
                                    </svg>
                                    <span>Trước</span>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>" 
                                   class="px-3 py-2 <?= $i === $page ? 'bg-primary text-white' : 'bg-dark-light border border-gray-600 hover:bg-dark' ?> rounded-lg text-sm">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" 
                                   class="px-3 py-2 bg-dark-light border border-gray-600 rounded-lg hover:bg-dark text-sm flex items-center space-x-1">
                                    <span>Sau</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="9,18 15,12 9,6"></polyline>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php include '../includes/footer.php'; ?>
        </main>
    </div>
    <script>
    // ======================= Alert =======================
    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alertContainer');
        const alertClass = type === 'success' ? 'bg-green-600' : 'bg-red-600';
        
        const alertHTML = `
            <div class="${alertClass} text-white px-4 py-3 rounded mb-4 shadow-md">
                <p>${message}</p>
            </div>
        `;
        
        alertContainer.innerHTML = alertHTML;

        setTimeout(() => {
            const alert = alertContainer.querySelector('div');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    }

    // ======================= User Selection =======================
    const notifTypeSelect = document.getElementById('notifType');
    const userSelectionDiv = document.getElementById('userSelectionDiv');

    notifTypeSelect.addEventListener('change', () => {
        userSelectionDiv.classList.toggle('hidden', notifTypeSelect.value !== 'personalized');
    });

    // Select all / Clear all
    document.getElementById('selectAll').addEventListener('click', () => {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = true);
        updateSelectedCount();
    });
    document.getElementById('clearAll').addEventListener('click', () => {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        updateSelectedCount();
    });

    function updateSelectedCount() {
        const count = document.querySelectorAll('.user-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = count;
    }
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));
    updateSelectedCount();

    // ======================= Form Submission =======================
    const notificationForm = document.getElementById('notificationForm');
    const sendBtn = document.getElementById('sendBtn');

    notificationForm.addEventListener('submit', async e => {
        e.preventDefault();

        const title = document.getElementById('title').value.trim();
        const body = document.getElementById('body').value.trim();
        const type = notifTypeSelect.value;
        let userIds = [];

        if (!title || !body) {
            showAlert('Vui lòng điền đầy đủ tiêu đề và nội dung!', 'error');
            return;
        }

        if (type === 'personalized') {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkedBoxes.length === 0) {
                showAlert('Vui lòng chọn ít nhất một người nhận!', 'error');
                return;
            }
            userIds = Array.from(checkedBoxes).map(cb => cb.value);
        }

        sendBtn.disabled = true;
        sendBtn.classList.add('opacity-50', 'cursor-not-allowed');

        try {
    const res = await fetch('../ajax/send_notification.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({title, body, type, userIds})
    });

    const text = await res.text(); // dùng text trước, để debug JSON lỗi
    let result;
    try {
        result = JSON.parse(text);
    } catch(e) {
        console.error("Response is not valid JSON:", text);
        showAlert("Lỗi server: " + text, 'error'); // hiển thị nguyên text
        return;
    }

    if (result.success) {
        showAlert(`Thành công! ${result.message}`, 'success');
        notificationForm.reset();
        userSelectionDiv.classList.add('hidden');
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        updateSelectedCount();
        refreshNotificationsTable();
    } else {
        console.error("Server error:", result);
        showAlert('Lỗi server: ' + (result.error || result.message), 'error');
    }
} catch (err) {
    console.error("Fetch error:", err);
    showAlert('Lỗi kết nối! Vui lòng thử lại.', 'error');
} finally {
            sendBtn.disabled = false;
            sendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    });

    // ======================= Resend Notification =======================
    async function resendNotification(notificationId) {
        if (!confirm('Bạn có chắc muốn gửi lại thông báo này?')) return;

        try {
            const res = await fetch('../ajax/send_notification.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({notification_id: notificationId, resend: true})
            });
            const result = await res.json();
            if (result.success) {
                showAlert('Thông báo đã gửi lại thành công!', 'success');
                refreshNotificationsTable();
            } else {
                showAlert('Lỗi: ' + (result.error || 'Không thể gửi lại thông báo'), 'error');
            }
        } catch (err) {
            console.error(err);
            showAlert('Lỗi kết nối! Vui lòng thử lại.', 'error');
        }
    }

    // ======================= Refresh Notifications Table =======================
    async function refreshNotificationsTable() {
        try {
            const res = await fetch('../ajax/get_notifications_table.php');
            const html = await res.text();
            document.getElementById('notificationsTableContainer').innerHTML = html;
        } catch (err) {
            console.error(err);
            showAlert('Không thể tải lại danh sách thông báo.', 'error');
        }
    }
</script>

</body>
</html>