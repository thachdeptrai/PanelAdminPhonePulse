<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

$user_id_raw = $_SESSION['user_id'] ?? null;
if (!$user_id_raw) {
    header('Location: dang_nhap');
    exit;
}

$currentUser = $mongoDB->users->findOne(['_id' => new ObjectId($user_id_raw)]);

// Tìm kiếm & phân trang
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role   = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 10;
$skip   = ($page - 1) * $limit;

// Xây dựng bộ lọc
$filter = [];

if ($search) {
    $regex = new Regex($search, 'i');
    $filter['$or'] = [
        ['name'  => $regex],
        ['email' => $regex],
        ['phone' => $regex]
    ];
}

if ($role !== '') {
    $filter['role'] = $role;
}

if ($status !== '') {
    $filter['status'] = ($status === 'true') ? true : false;
}

$options = [
    'sort'  => ['created_date' => -1],
    'limit' => $limit,
    'skip'  => $skip
];

$cursor = $mongoDB->users->find($filter, $options);
$users = iterator_to_array($cursor);

$totalUsers = $mongoDB->users->countDocuments($filter);
$totalPages = ceil($totalUsers / $limit);

// Thống kê
$allUsersCursor = $mongoDB->users->find();
$stats = [
    'total_users'   => 0,
    'active_users'  => 0,
    'inactive_users'=> 0,
    'today_users'   => 0
];
$today = (new DateTime())->format('Y-m-d');

foreach ($allUsersCursor as $user) {
    $stats['total_users']++;
    if (!empty($user['status'])) {
        $stats['active_users']++;
    } else {
        $stats['inactive_users']++;
    }
    if (!empty($user['created_date'])) {
        $created = is_object($user['created_date']) ? $user['created_date']->toDateTime()->format('Y-m-d') : date('Y-m-d', strtotime($user['created_date']));
        if ($created === $today) {
            $stats['today_users']++;
        }
    }
}

// Base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host;

?>
<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
  <meta charset="UTF-8">
  <title>Quản lý người dùng</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          animation: {
            'fade-in': 'fadeIn 0.5s ease-in-out',
            'slide-up': 'slideUp 0.3s ease-out',
            'scale-in': 'scaleIn 0.2s ease-out',
            'bounce-gentle': 'bounceGentle 0.6s ease-out',
            'shimmer': 'shimmer 2s linear infinite',
            'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0', transform: 'translateY(20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            slideUp: {
              '0%': { opacity: '0', transform: 'translateY(30px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            scaleIn: {
              '0%': { opacity: '0', transform: 'scale(0.9)' },
              '100%': { opacity: '1', transform: 'scale(1)' }
            },
            bounceGentle: {
              '0%, 20%, 50%, 80%, 100%': { transform: 'translateY(0)' },
              '40%': { transform: 'translateY(-10px)' },
              '60%': { transform: 'translateY(-5px)' }
            },
            shimmer: {
              '0%': { backgroundPosition: '-200% 0' },
              '100%': { backgroundPosition: '200% 0' }
            }
          }
        }
      }
    }
  </script>
  <style>
    .glass-effect {
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.05);
    }
    .hover-lift {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .hover-lift:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
  </style>
</head>
<body >
  <!-- Background Effects -->
  <div class="fixed inset-0 overflow-hidden pointer-events-none">
    <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl animate-pulse-slow"></div>
    <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl animate-pulse-slow" style="animation-delay: 1s;"></div>
  </div>

  <!-- Sidebar -->
  <?php include '../includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="p-6 sm:ml-64">
    <div class="max-w-7xl mx-auto animate-fade-in">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-4xl font-bold text-white mb-2 bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
          Quản Lý Người Dùng
        </h1>
        <p class="text-gray-400">Quản lý và theo dõi tất cả người dùng trong hệ thống</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="glass-effect rounded-2xl p-6 border border-gray-700/50 hover-lift animate-slide-up">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Tổng người dùng</p>
              <p class="text-2xl font-bold text-white"><?= number_format($stats['total_users']) ?></p>
            </div>
            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
              <span class="text-white font-bold text-lg">👥</span>
            </div>
          </div>
          <div class="mt-4 flex items-center text-blue-400">
            <span class="text-sm">Tổng số người dùng đã đăng ký</span>
          </div>
        </div>

        <div class="glass-effect rounded-2xl p-6 border border-gray-700/50 hover-lift animate-slide-up" style="animation-delay: 0.1s;">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Đang hoạt động</p>
              <p class="text-2xl font-bold text-white"><?= number_format($stats['active_users']) ?></p>
            </div>
            <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-500 rounded-xl flex items-center justify-center">
              <span class="text-white font-bold text-lg">✅</span>
            </div>
          </div>
          <div class="mt-4 flex items-center text-green-400">
            <span class="text-sm">Tài khoản đang hoạt động bình thường</span>
          </div>
        </div>

        <div class="glass-effect rounded-2xl p-6 border border-gray-700/50 hover-lift animate-slide-up" style="animation-delay: 0.2s;">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Bị khóa</p>
              <p class="text-2xl font-bold text-white"><?= number_format($stats['inactive_users']) ?></p>
            </div>
            <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-pink-500 rounded-xl flex items-center justify-center">
              <span class="text-white font-bold text-lg">🔒</span>
            </div>
          </div>
          <div class="mt-4 flex items-center text-red-400">
            <span class="text-sm">Tài khoản đã bị khóa hoặc vô hiệu hóa</span>
          </div>
        </div>

        <div class="glass-effect rounded-2xl p-6 border border-gray-700/50 hover-lift animate-slide-up" style="animation-delay: 0.3s;">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Mới hôm nay</p>
              <p class="text-2xl font-bold text-white"><?= number_format($stats['today_users']) ?></p>
            </div>
            <div class="w-12 h-12 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center">
              <span class="text-white font-bold text-lg">⭐</span>
            </div>
          </div>
          <div class="mt-4 flex items-center text-yellow-400">
            <span class="text-sm">Người dùng đăng ký trong ngày hôm nay</span>
          </div>
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="glass-effect rounded-2xl p-6 border border-gray-700/50 mb-8 animate-slide-up" style="animation-delay: 0.4s;">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-center justify-between">
          <div class="flex flex-col md:flex-row gap-4 flex-1">
            <div class="relative">
              <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm theo tên, email, số điện thoại..." 
                class="w-full md:w-80 bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 pl-12 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300">
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
            </div>

            <select name="role" class="bg-gray-800/50 border border-gray-600 text-white rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
              <option value="">Tất cả vai trò</option>
              <option value="true" <?= $role == true ? 'selected' : '' ?>>Quản trị viên</option>
              <option value="false" <?= $role == false ? 'selected' : '' ?>>Người dùng</option>
            </select>

            <select name="status" class="bg-gray-800/50 border border-gray-600 text-white rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
              <option value="">Tất cả trạng thái</option>
              <option value="true" <?= $status ==  true ? 'selected' : '' ?>>Đang hoạt động</option>
              <option value="false" <?= $status == false ? 'selected' : '' ?>>Bị khóa</option>
            </select>

            <button type="submit" class="bg-gradient-to-r from-purple-500 to-blue-500 hover:from-purple-600 hover:to-blue-600 text-white px-6 py-3 rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
              Lọc kết quả
            </button>
          </div>

          <button type="button" onclick="openAddModal()" class="bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white px-6 py-3 rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
            Thêm người dùng
          </button>
        </form>
      </div>

      <!-- Users Table -->
      <div class="glass-effect rounded-2xl border border-gray-700/50 overflow-hidden animate-slide-up" style="animation-delay: 0.5s;">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gradient-to-r from-purple-900/50 to-blue-900/50">
              <tr>
                <th class="px-6 py-4 text-left text-gray-300 font-semibold">Người dùng</th>
                <th class="px-6 py-4 text-left text-gray-300 font-semibold">Liên hệ</th>
                <th class="px-6 py-4 text-left text-gray-300 font-semibold">Vai trò</th>
                <th class="px-6 py-4 text-left text-gray-300 font-semibold">Trạng thái</th>
                <th class="px-6 py-4 text-left text-gray-300 font-semibold">Xác thực</th>
                <th class="px-6 py-4 text-left text-gray-300 font-semibold">Ngày tham gia</th>
                <th class="px-6 py-4 text-left text-gray-300 font-semibold">Hành động</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50">
              <?php if (empty($users)): ?>
              <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                  <div class="flex flex-col items-center">
                    <span class="text-4xl mb-2">📭</span>
                    <span>Không tìm thấy người dùng nào</span>
                  </div>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($users as $index => $user): ?>
                <tr class="hover:bg-white/5 transition-all duration-300 animate-slide-up" style="animation-delay: <?= $index * 0.05 ?>s;">
                  <td class="px-6 py-4">
                    <div class="flex items-center">
                      <img src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=6366f1&color=ffffff') ?>" 
                           alt="Avatar" class="w-12 h-12 rounded-full object-cover border-2 border-purple-500/30 mr-4">
                      <div>
                        <div class="font-semibold text-white"><?= htmlspecialchars($user['name']) ?></div>
                        <!-- <div class="text-sm text-gray-400">ID: #<?=(string) $user['_id'] ?></div> -->
                        <?php if ($user['gender']): ?>
                        <div class="text-xs text-gray-500"><?= $user['gender'] === 'male' ? '👨' : ($user['gender'] === 'female' ? '👩' : '👤') ?> <?= ucfirst($user['gender']) ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-white"><?= htmlspecialchars($user['email']) ?></div>
                    <?php if ($user['phone']): ?>
                    <div class="text-sm text-gray-400"><?= htmlspecialchars($user['phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($user['address']): ?>
                    <div class="text-xs text-gray-500 truncate max-w-32"><?= htmlspecialchars($user['address']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= $user['role'] == true ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30' ?>">
                      <?= $user['role'] == true ? 'Quản trị viên' : 'Người dùng' ?>
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= $user['status'] ==true ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30' ?>">
                      <?= $user['status'] == true ? 'Hoạt động' : 'Bị khóa' ?>
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $user['is_verified'] == true ? 'bg-green-500/20 text-green-300' : 'bg-yellow-500/20 text-yellow-300' ?>">
                      <?= $user['is_verified'] == true ? '✓ Đã xác thực' : '⏳ Chưa xác thực' ?>
                    </span>
                  </td>
                  <td class="px-6 py-4">
                  <div class="text-gray-400">
                      <?= $user['created_date']->toDateTime()->format('d/m/Y') ?>
                  </div>
                    <!-- <?php if ($user['last_login']): ?> -->
                    <!-- <div class="text-xs text-gray-500">Đăng nhập: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></div> -->
                    <!-- <?php endif; ?> -->
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center space-x-2">
                      <button onclick="editUser('<?= (string)$user['_id'] ?>')" title="Chỉnh sửa"
                        class="w-9 h-9 bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-300 rounded-lg transition-all duration-300 transform hover:scale-110 flex items-center justify-center">
                        ✏️
                      </button>
                      <button onclick="toggleStatus('<?= (string)$user['_id'] ?>', <?= $user['status'] ?>)" title="<?= $user['status'] == 1 ? 'Khóa tài khoản' : 'Mở khóa tài khoản' ?>"
                        class="w-9 h-9 <?= $user['status'] == true ? 'bg-orange-500/20 hover:bg-orange-500/30 text-orange-300' : 'bg-green-500/20 hover:bg-green-500/30 text-green-300' ?> rounded-lg transition-all duration-300 transform hover:scale-110 flex items-center justify-center">
                        <?= $user['status'] == true ? '🔒' : '🔓' ?>
                      </button>
                      <button onclick="deleteUser('<?= (string)$user['_id'] ?>')" title="Xóa người dùng"
                        class="w-9 h-9 bg-red-500/20 hover:bg-red-500/30 text-red-300 rounded-lg transition-all duration-300 transform hover:scale-110 flex items-center justify-center">
                        🗑️
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-700/50 flex items-center justify-between">
          <div class="text-gray-400 text-sm">
            Hiển thị <?= ($page - 1) * $limit + 1 ?>-<?= min($page * $limit, $totalUsers) ?> trong tổng số <?= number_format($totalUsers) ?> người dùng
          </div>
          <div class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
               class="px-4 py-2 bg-gray-800/50 text-gray-400 rounded-lg hover:bg-gray-700/50 transition-colors duration-300">
              Trước
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
               class="px-4 py-2 <?= $i == $page ? 'bg-purple-500 text-white' : 'bg-gray-800/50 text-gray-400 hover:bg-gray-700/50' ?> rounded-lg transition-colors duration-300">
              <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
               class="px-4 py-2 bg-gray-800/50 text-gray-400 rounded-lg hover:bg-gray-700/50 transition-colors duration-300">
              Sau
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- User Modal -->
  <div id="userModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="glass-effect rounded-2xl border border-gray-700/50 w-full max-w-md transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h3 id="modalTitle" class="text-xl font-bold text-white">Thêm người dùng mới</h3>
          <button onclick="closeModal()" class="text-gray-400 hover:text-white transition-colors duration-300 text-2xl w-8 h-8 flex items-center justify-center">
            ×
          </button>
        </div>

        <form id="userForm" class="space-y-4">
          <input type="hidden" id="mongo_id" name="mongo_id">
          
          <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Họ và tên *</label>
            <input type="text" id="name" name="name" required
              class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
          </div>

          <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Email *</label>
            <input type="email" id="email" name="email" required
              class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
          </div>

          <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Số điện thoại</label>
            <input type="tel" id="phone" name="phone"
              class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
          </div>

          <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Địa chỉ</label>
            <input type="text" id="address" name="address"
              class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-gray-300 text-sm font-medium mb-2">Giới tính</label>
              <select id="gender" name="gender"
                class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
                <option value="">Chọn giới tính</option>
                <option value="male">Nam</option>
                <option value="female">Nữ</option>
                <option value="other">Khác</option>
              </select>
            </div>

            <div>
              <label class="block text-gray-300 text-sm font-medium mb-2">Vai trò *</label>
              <select id="role" name="role" required
                class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
                <option value="0">Người dùng</option>
                <option value="1">Quản trị viên</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-gray-300 text-sm font-medium mb-2">Trạng thái</label>
              <select id="status" name="status" required
                class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
                <option value="1">Hoạt động</option>
                <option value="0">Bị khóa</option>
              </select>
            </div>

            <div>
              <label class="block text-gray-300 text-sm font-medium mb-2">Xác thực</label>
              <select id="isVerified" name="is_verified"
                class="w-full bg-gray-800/50 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all duration-300">
                <option value="0">Chưa xác thực</option>
                <option value="1">Đã xác thực</option>
              </select>
            </div>
          </div>

          <div class="flex gap-3 pt-4">
            <button type="button" onclick="closeModal()" 
              class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded-xl transition-all duration-300">
              Hủy bỏ
            </button>
            <button type="submit"
              class="flex-1 bg-gradient-to-r from-purple-500 to-blue-500 hover:from-purple-600 hover:to-blue-600 text-white py-3 rounded-xl transition-all duration-300 transform hover:scale-105">
              Lưu thay đổi
              </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Toast Notifications -->
  <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

  <script>
    function showModal() {
  const modal = document.getElementById('userModal');
  const content = document.getElementById('modalContent');

  modal.classList.remove('hidden');
  modal.classList.add('flex');

  setTimeout(() => {
    content.classList.remove('scale-95', 'opacity-0');
    content.classList.add('scale-100', 'opacity-100');
  }, 10);
}
function closeModal() {
  const modal = document.getElementById('userModal');
  const content = document.getElementById('modalContent');

  content.classList.add('scale-95', 'opacity-0');
  content.classList.remove('scale-100', 'opacity-100');

  setTimeout(() => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }, 300);
}

   let currentUserId = null;

// Mở modal thêm user
function openAddModal() {
  currentUserId = null;
  document.getElementById('modalTitle').textContent = 'Thêm người dùng mới';
  document.getElementById('userForm').reset();
  document.getElementById('mongo_id').value = '';
  showModal();
}

// Mở modal sửa user
function editUser(userId) {
  currentUserId = userId;

  fetch(`api/get_user.php?id=${userId}`)
    .then(res => res.json())
    .then(data => {
      if (!data.success || !data.user) {
        showToast('Không thể tải dữ liệu người dùng', 'error');
        return;
      }

      const user = data.user;
      document.getElementById('modalTitle').textContent = 'Chỉnh sửa người dùng';
      document.getElementById('mongo_id').value = user._id || '';
      document.getElementById('name').value = user.name || '';
      document.getElementById('email').value = user.email || '';
      document.getElementById('phone').value = user.phone || '';
      document.getElementById('address').value = user.address || '';
      document.getElementById('gender').value = user.gender || '';
      document.getElementById('role').value = user.role ?? 'false';
      document.getElementById('status').value = user.status ?? 'true';
      document.getElementById('isVerified').value = user.is_verified ?? 'false';
      showModal();
    })
    .catch(err => {
      console.error(err);
      showToast('Lỗi khi tải thông tin người dùng', 'error');
    });
}

// Gửi form (thêm hoặc sửa)
document.getElementById('userForm').addEventListener('submit', function (e) {
  e.preventDefault();
  if (!validateForm()) return;

  const formData = new FormData(this);
  const isEdit = !!currentUserId;
  const url = isEdit ? 'api/update_user.php' : 'api/add_user.php';

  const submitBtn = this.querySelector('button[type="submit"]');
  const originalText = submitBtn.textContent;
  submitBtn.textContent = 'Đang xử lý...';
  submitBtn.disabled = true;

  fetch(url, {
    method: 'POST',
    body: formData
  })
    .then(async res => {
      const text = await res.text();
      try {
        const json = JSON.parse(text);
        return json;
      } catch (err) {
        throw new Error('Invalid JSON: ' + text);
      }
    })
    .then(data => {
      if (data.success) {
        showToast(isEdit ? 'Cập nhật thành công!' : 'Thêm người dùng thành công!', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message || 'Lỗi xử lý dữ liệu', 'error');
      }
    })
    .catch(err => {
      console.error(err);
      showToast(err.message || 'Lỗi không xác định', 'error');
    })
    .finally(() => {
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
    });
});

// Xóa user
function deleteUser(userId) {
  if (!confirm('Bạn có chắc muốn xóa người dùng này không?')) return;

  fetch('api/delete_user.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast('Xóa người dùng thành công!', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message || 'Lỗi khi xóa', 'error');
      }
    })
    .catch(err => {
      console.error(err);
      showToast('Lỗi khi xử lý xóa người dùng', 'error');
    });
}
function toggleStatus(userId, currentStatus) {
  console.log('Toggle called with:', userId, currentStatus); // 👈 THÊM DÒNG NÀY

  const newStatus = currentStatus === 1 ? 0 : 1;
  const label = newStatus === 1 ? 'mở khóa' : 'khóa';

  if (!confirm(`Bạn có chắc muốn ${label} tài khoản này không?`)) return;

  fetch('api/toggle_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id: userId,
      status: newStatus
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(`Đã ${label} tài khoản!`, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Lỗi toggle trạng thái', 'error');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Lỗi khi thay đổi trạng thái', 'error');
  });
}


    // Toast notification system
    function showToast(message, type = 'info') {
      const toastContainer = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      
      const bgColor = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-yellow-500'
      }[type] || 'bg-gray-500';
      
      const icon = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
      }[type] || 'ℹ️';
      
      toast.className = `${bgColor} text-white px-6 py-4 rounded-xl shadow-lg transform transition-all duration-300 translate-x-full opacity-0 flex items-center space-x-3 min-w-80`;
      toast.innerHTML = `
        <span class="text-lg">${icon}</span>
        <span class="flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white text-xl leading-none">×</button>
      `;
      
      toastContainer.appendChild(toast);
      
      // Show toast
      setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
      }, 100);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
      }, 5000);
    }

    // Close modal when clicking outside
    document.getElementById('userModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeModal();
      }
    });

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          alert.style.opacity = '0';
          setTimeout(() => alert.remove(), 300);
        }, 5000);
      });
    });

    // Enhanced form validation
    function validateForm() {
      const form = document.getElementById('userForm');
      const inputs = form.querySelectorAll('input[required], select[required]');
      let isValid = true;
      
      inputs.forEach(input => {
        if (!input.value.trim()) {
          input.classList.add('border-red-500');
          isValid = false;
        } else {
          input.classList.remove('border-red-500');
        }
      });
      
      // Email validation
      const email = document.getElementById('email');
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (email.value && !emailRegex.test(email.value)) {
        email.classList.add('border-red-500');
        showToast('Định dạng email không hợp lệ', 'error');
        isValid = false;
      }
      
      // Phone validation
      const phone = document.getElementById('phone');
      const phoneRegex = /^[0-9+\-\s()]+$/;
      if (phone.value && !phoneRegex.test(phone.value)) {
        phone.classList.add('border-red-500');
        showToast('Định dạng số điện thoại không hợp lệ', 'error');
        isValid = false;
      }
      
      return isValid;
    }

    // Add real-time validation
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('#userForm input, #userForm select');
      inputs.forEach(input => {
        input.addEventListener('blur', function() {
          if (this.hasAttribute('required') && !this.value.trim()) {
            this.classList.add('border-red-500');
          } else {
            this.classList.remove('border-red-500');
          }
        });
        
        input.addEventListener('input', function() {
          this.classList.remove('border-red-500');
        });
      });
    });

    // Advanced search functionality
    function handleSearch() {
      const searchInput = document.querySelector('input[name="search"]');
      const form = searchInput.closest('form');
      
      searchInput.addEventListener('input', debounce(function() {
        if (this.value.length >= 3 || this.value.length === 0) {
          form.submit();
        }
      }, 500));
    }

    // Debounce function for search
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // Initialize search functionality
    document.addEventListener('DOMContentLoaded', handleSearch);

    // Export data functionality
    function exportUsers() {
      const params = new URLSearchParams(window.location.search);
      window.open(`api/export_users.php?${params.toString()}`, '_blank');
    }

    // Bulk actions
    function selectAllUsers() {
      const checkboxes = document.querySelectorAll('.user-checkbox');
      const selectAll = document.querySelector('#selectAll');
      
      checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
      });
      
      updateBulkActionsVisibility();
    }

    function updateBulkActionsVisibility() {
      const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
      const bulkActions = document.querySelector('#bulkActions');
      
      if (checkedBoxes.length > 0) {
        bulkActions.classList.remove('hidden');
      } else {
        bulkActions.classList.add('hidden');
      }
    }

    // Print functionality
    function printUserList() {
      window.print();
    }

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
      const tooltipElements = document.querySelectorAll('[title]');
      
      tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
          const tooltip = document.createElement('div');
          tooltip.className = 'absolute bg-gray-900 text-white text-xs rounded px-2 py-1 z-50 pointer-events-none';
          tooltip.textContent = this.getAttribute('title');
          tooltip.style.top = (this.offsetTop - 30) + 'px';
          tooltip.style.left = this.offsetLeft + 'px';
          
          this.removeAttribute('title');
          this.appendChild(tooltip);
          
          this.addEventListener('mouseleave', function() {
            tooltip.remove();
            this.setAttribute('title', tooltip.textContent);
          });
        });
      });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + K for search focus
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('input[name="search"]').focus();
      }
      
      // Ctrl/Cmd + N for new user
      if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        openAddModal();
      }
    });
  </script>

  <!-- Print Styles -->
  <style media="print">
    .no-print, .sidebar, .filters, .actions {
      display: none !important;
    }
    
    body {
      background: white !important;
      color: black !important;
    }
    
    .glass-effect {
      background: white !important;
      backdrop-filter: none !important;
    }
  </style>
</body>
</html>