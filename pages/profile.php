<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: dang_nhap");
    exit;
}

$userId = new ObjectId($_SESSION['user_id']);
$user = $mongoDB->users->findOne(['_id' => $userId]);
if (!$user) die("Không tìm thấy người dùng");

// Lấy màu theme từ settings
$settings = $mongoDB->settings->findOne([]) ?? ['theme_color' => '#0ea5e9'];
$themeColor = $settings['theme_color'] ?? '#0ea5e9';

$success = false;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateData = [
            'name'       => trim($_POST['name'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'phone'      => trim($_POST['phone'] ?? ''),
            'address'    => trim($_POST['address'] ?? ''),
            'updated_at' => new UTCDateTime(),
        ];

        // Validate email
        if (!filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email không hợp lệ");
        }

        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                throw new Exception("Mật khẩu phải có ít nhất 6 ký tự");
            }
            $updateData['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }

        $mongoDB->users->updateOne(['_id' => $userId], ['$set' => $updateData]);
        $user = $mongoDB->users->findOne(['_id' => $userId]); // Reload
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy thống kê user
$totalUsers = $mongoDB->users->countDocuments();
$lastLogin = $user['last_login'] ?? null;
$memberSince = $user['created_date'] ?? null;

include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        :root {
            --theme-color: <?= $themeColor ?>;
            --theme-rgb: <?= implode(',', sscanf($themeColor, "#%02x%02x%02x")) ?>;
        }
        .text-theme { color: var(--theme-color); }
        .bg-theme { background-color: var(--theme-color); }
        .border-theme { border-color: var(--theme-color); }
        .ring-theme { --tw-ring-color: var(--theme-color); }
        .shadow-theme { box-shadow: 0 4px 14px 0 rgba(var(--theme-rgb), 0.25); }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .admin-input {
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid rgba(75, 85, 99, 0.5);
            transition: all 0.3s ease;
        }
        
        .admin-input:focus {
            background: rgba(17, 24, 39, 0.9);
            border-color: var(--theme-color);
            box-shadow: 0 0 0 3px rgba(var(--theme-rgb), 0.1);
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(var(--theme-rgb), 0.1) 0%, rgba(var(--theme-rgb), 0.05) 100%);
        }
        
        .profile-avatar {
            background: linear-gradient(135deg, var(--theme-color) 0%, rgba(var(--theme-rgb), 0.7) 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-slate-900 text-white font-sans min-h-screen">

<div class="ml-64 p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-white mb-2">
                        <i class="fas fa-user-shield text-theme mr-3"></i>Admin Profile
                    </h1>
                    <p class="text-gray-400">Manage your account settings and preferences</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm text-gray-400">Last updated</p>
                        <p class="text-white font-medium"><?= date('M d, Y H:i') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stat-card glass-card rounded-xl p-6">
                <div class="flex items-center">
                    <div class="profile-avatar w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-user text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Account Status</p>
                        <p class="text-white font-bold text-lg">Active Admin</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card glass-card rounded-xl p-6">
                <div class="flex items-center">
                    <div class="bg-green-500 w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Total Users</p>
                        <p class="text-white font-bold text-lg"><?= number_format($totalUsers) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card glass-card rounded-xl p-6">
                <div class="flex items-center">
                    <div class="bg-blue-500 w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-calendar-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-300 text-sm">Member Since</p>
                        <p class="text-white font-bold text-lg">
                            <?= $memberSince ? $memberSince->toDateTime()->format('M Y') : 'N/A' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Form -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-2xl p-8 shadow-2xl">
                    <div class="flex items-center mb-6">
                        <i class="fas fa-edit text-theme text-2xl mr-3"></i>
                        <h2 class="text-2xl font-bold text-white">Profile Information</h2>
                    </div>

                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                    <i class="fas fa-user mr-2 text-theme"></i>
                                    Full Name
                                </label>
                                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" 
                                       class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                       required>
                            </div>
                            
                            <div>
                                <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                    <i class="fas fa-envelope mr-2 text-theme"></i>
                                    Email Address
                                </label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                       class="admin-input w-full text-white p-4 rounded-xl focus:outline-none" 
                                       required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                    <i class="fas fa-phone mr-2 text-theme"></i>
                                    Phone Number
                                </label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                       class="admin-input w-full text-white p-4 rounded-xl focus:outline-none">
                            </div>
                            
                            <div>
                                <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                    <i class="fas fa-map-marker-alt mr-2 text-theme"></i>
                                    Address
                                </label>
                                <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" 
                                       class="admin-input w-full text-white p-4 rounded-xl focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center mb-3 text-sm font-medium text-gray-300">
                                <i class="fas fa-lock mr-2 text-theme"></i>
                                New Password
                            </label>
                            <input type="password" name="password" placeholder="Leave blank to keep current password" 
                                   class="admin-input w-full text-white p-4 rounded-xl focus:outline-none">
                            <p class="text-gray-400 text-xs mt-2">Minimum 6 characters required</p>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-700">
                            <div class="flex items-center text-gray-400 text-sm">
                                <i class="fas fa-info-circle mr-2"></i>
                                All changes are saved immediately
                            </div>
                            <button type="submit" class="bg-theme hover:opacity-90 transition-all duration-300 text-white px-8 py-4 rounded-xl font-semibold shadow-theme flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Profile Overview -->
            <div class="space-y-6">
                <!-- Avatar Card -->
                <div class="glass-card rounded-2xl p-6 text-center">
                    <div class="profile-avatar w-24 h-24 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-user text-white text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1"><?= htmlspecialchars($user['name'] ?? 'Admin User') ?></h3>
                    <p class="text-gray-400 text-sm mb-4"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                    <div class="bg-theme/20 text-theme px-3 py-1 rounded-full text-xs font-medium inline-block">
                        <i class="fas fa-crown mr-1"></i>Administrator
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-bolt text-theme mr-2"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <button class="w-full bg-gray-700/50 hover:bg-gray-600/50 transition-colors text-white p-3 rounded-lg text-left flex items-center">
                            <i class="fas fa-key mr-3 text-yellow-400"></i>
                            Change Password
                        </button>
                        <button class="w-full bg-gray-700/50 hover:bg-gray-600/50 transition-colors text-white p-3 rounded-lg text-left flex items-center">
                            <i class="fas fa-shield-alt mr-3 text-green-400"></i>
                            Security Settings
                        </button>
                        <button class="w-full bg-gray-700/50 hover:bg-gray-600/50 transition-colors text-white p-3 rounded-lg text-left flex items-center">
                            <i class="fas fa-download mr-3 text-blue-400"></i>
                            Export Data
                        </button>
                    </div>
                </div>

                <!-- Activity Summary -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-chart-line text-theme mr-2"></i>
                        Activity
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Last Login</span>
                            <span class="text-white"><?= $lastLogin ? $lastLogin->toDateTime()->format('M d, H:i') : 'Never' ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Profile Updated</span>
                            <span class="text-white">Today</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Account Status</span>
                            <span class="text-green-400 flex items-center">
                                <i class="fas fa-circle text-xs mr-1"></i>Active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<?php if ($success): ?>
<script>
    Toastify({
        text: "✅ Profile updated successfully!",
        duration: 4000,
        gravity: "top",
        position: "right",
        backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
        className: "rounded-lg"
    }).showToast();
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
    Toastify({
        text: "❌ <?= htmlspecialchars($error) ?>",
        duration: 4000,
        gravity: "top",
        position: "right",
        backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
        className: "rounded-lg"
    }).showToast();
</script>
<?php endif; ?>

</body>
</html>