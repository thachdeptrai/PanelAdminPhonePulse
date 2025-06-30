<?php
    include '../includes/config.php';
    include '../includes/functions.php';
    $stats = getDashboardStats();
    if (! isAdmin()) {
        header('Location: dang_nhap');
        exit;
    }
    // Check if user is logged in
    if (! isset($_SESSION['user_id'])) {
        header("Location: dang_nhap");
        exit();
    }

    // Get user data
    $user_id = $_SESSION['user_id'];
    $stmt    = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Phonepulse Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary: #6c5ce7;
            --primary-light: #a29bfe;
            --dark: #1e293b;
            --darker: #0f172a;
            --dark-light: #334155;
            --glow: rgba(108, 92, 231, 0.6);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--darker);
            color: white;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar-item.active {
            background: var(--dark-light);
            border-left: 4px solid var(--primary);
        }

        .sidebar-item:hover:not(.active) {
            background: var(--dark-light);
        }

        .card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .notification-dot {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .content-area {
            transition: all 0.3s ease;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: var(--dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 h-screen bg-dark fixed flex flex-col">
        <div class="p-4 border-b border-dark-light flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-primary-light flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                </div>
                <span class="font-semibold">Phonepulse</span>
            </div>
            <button class="text-gray-400 md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="p-4 border-b border-dark-light">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                    <?php echo strtoupper(substr($user['email'], 0, 1)) ?>
                </div>
                <div>
                    <div class="font-medium"><?php echo htmlspecialchars($user['email']) ?></div>
                    <div class="text-xs text-gray-400"><?php echo $user['role'] === 1 ? 'Administrator' : 'Editor' ?></div>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            <ul class="space-y-1 px-2">
                <li>
                    <a href="trang_chu" class="sidebar-item active flex items-center px-4 py-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="users" class="sidebar-item flex items-center px-4 py-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Quản lý User
                    </a>
                </li>
                <li>
                    <a href="products" class="sidebar-item flex items-center px-4 py-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        Danh mục Sản phẩm
                    </a>
                </li>
                <li>
                    <a href="orders" class="sidebar-item flex items-center px-4 py-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        Đơn hàng
                    </a>
                </li>
                <li>
                    <a href="settings" class="sidebar-item flex items-center px-4 py-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                        Settings
                    </a>
                </li>
                <?php if ($user['role'] == 1): ?>
                <li>
                    <a href="admin-tools" class="sidebar-item flex items-center px-4 py-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                        Công cụ Admin
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="p-4 border-t border-dark-light">
            <a href="dang_xuat" class="flex items-center text-red-400 hover:text-red-300">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content-area ml-64 flex-1 min-h-screen">
        <!-- Top Navigation -->
        <header class="bg-dark-light border-b border-dark px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <h1 class="text-2xl font-semibold">Dashboard Overview</h1>

            <div class="flex items-center space-x-4">
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
                            <?php echo strtoupper(substr($user['email'], 0, 1)) ?>
                        </div>
                        <span class="hidden md:inline"><?php echo htmlspecialchars(explode('@', $user['email'])[0]) ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div class="dropdown-content mt-2 w-48 rounded-md shadow-lg py-1 z-50">
                        <a href="profile" class="block px-4 py-2 text-sm hover:bg-dark-light">Profile</a>
                        <a href="settings" class="block px-4 py-2 text-sm hover:bg-dark-light">Settings</a>
                        <div class="border-t border-gray-700"></div>
                        <a href="dang_xuat" class="block px-4 py-2 text-sm text-red-400 hover:bg-dark-light">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
    <!-- Nội dung chính -->
<main class="p-6">

  <!-- Các thẻ thống kê -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

    <!-- Tổng doanh thu -->
    <div class="card p-6 rounded-lg">
      <div class="flex items-center justify-between">
        <div>
            <?php $totalRevenue = $stats['revenue'] ?? 0?>
          <p class="text-gray-400">Tổng Doanh Thu</p>
          <p class="text-2xl font-semibold mt-1"><?php echo number_format($totalRevenue, 0, ',', '.') ?></p>
          <p class="text-green-400 text-sm mt-2 flex items-center">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
          </svg>
            <span class="ml-1"><?php echo $stats['rev_change'] ?? '0%' ?> so với tháng trước</span>
          </p>
        </div>
        <div class="w-12 h-12 ...">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"></line>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
        </div>
      </div>
    </div>

    <!-- Tổng số đơn hàng -->
    <div class="card p-6 rounded-lg">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-400">Tổng Số Đơn Hàng</p>
          <p class="text-2xl font-semibold mt-1"><?php echo number_format($stats['orders'] ?? 0, 0, ',', '.') ?>₫</p>
          <p class="text-green-400 text-sm mt-2 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
            <polyline points="16 7 22 7 22 13"></polyline>
          </svg>
          <span class="ml-1"><?php echo $stats['rev_change'] ?? '0%' ?> so với tháng trước</span>
          </p>
        </div>
        <div class="w-12 h-12 ...">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
         <circle cx="9" cy="21" r="1"></circle>
         <circle cx="20" cy="21" r="1"></circle>
         <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
         </svg>
        </div>
      </div>
    </div>

    <!-- Người dùng hoạt động -->
    <div class="card p-6 rounded-lg">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-400">Người Dùng Hoạt Động</p>
          <p class="text-2xl font-semibold mt-1"><?php echo number_format($stats['users'] ?? 0, 0, ',', '.') ?></p>
          <p class="text-green-400 text-sm mt-2 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
            <polyline points="16 7 22 7 22 13"></polyline>
          </svg>
            <span class="ml-1"><?php echo $stats['user_change'] ?? '1%' ?> so với tháng trước</span>
          </p>
        </div>
        <div class="w-12 h-12 ...">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
        </div>
      </div>
    </div>

    <!-- Sản phẩm -->
    <div class="card p-6 rounded-lg">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-400">Sản Phẩm</p>
          <p class="text-2xl font-semibold mt-1"><?php echo number_format($stats['product'] ?? 0, 0, ',', '.') ?></p>
          <p class="text-green-400 text-sm mt-2 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                                    <polyline points="16 7 22 7 22 13"></polyline>
                                </svg>
         <span class="ml-1"><?php echo $stats['rev_change'] ?? '0%' ?> so với tháng trước</span>
          </p>
        </div>
        <div class="w-12 h-12 ...">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- Hàng biểu đồ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Biểu đồ doanh thu -->
    <div class="card p-6 rounded-lg lg:col-span-2">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Tổng Quan Doanh Thu</h2>
        <select class="bg-[#1e293b] text-white text-sm px-3 py-2 rounded border border-gray-600 focus:outline-none focus:border-primary">
  <option>7 ngày qua</option>
  <option selected>Tháng này</option>
  <option>3 tháng gần nhất</option>
  <option>Năm nay</option>
</select>

      </div>
      <div class="h-80">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>

    <!-- Doanh số theo danh mục -->
    <div class="card p-6 rounded-lg">
      <h2 class="text-lg font-semibold mb-4">Doanh Số Theo Danh Mục</h2>
      <div class="h-80">
        <canvas id="categoryChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Đơn hàng & Hoạt động gần đây -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Đơn hàng gần đây -->
    <div class="card p-6 rounded-lg">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Đơn Hàng Gần Đây</h2>
        <a href="orders" class="text-sm text-primary hover:underline">Xem tất cả</a>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr class="text-left text-gray-400 text-sm border-b border-dark-light">
              <th>Mã Đơn</th>
              <th>Khách Hàng</th>
              <th>Trạng Thái</th>
              <th>Số Tiền</th>
            </tr>
          </thead>
          <tbody>
            <!-- Lặp hiển thị đơn hàng ở đây -->
            <!-- Ví dụ -->
            <tr><td>#PH-4826</td><td>John Smith</td><td><span class="...">Hoàn tất</span></td><td>$128.99</td></tr>
            ...
          </tbody>
        </table>
      </div>
    </div>

    <!-- Hoạt động gần đây -->
    <div class="card p-6 rounded-lg">
      <h2 class="text-lg font-semibold mb-4">Hoạt Động Gần Đây</h2>
      <div class="space-y-4">
        <div class="flex items-start">
          <div class="..."><svg ...></svg></div>
          <div>
            <p class="text-sm"><span class="font-medium">John Smith</span> đã đặt đơn hàng mới <span class="font-medium">#PH-4826</span></p>
            <p class="text-xs text-gray-400 mt-1">2 giờ trước</p>
          </div>
        </div>
        ...
      </div>
    </div>
  </div>

</main>
    </div>

    <!-- Chart.js Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Revenue',
                    data: [6500, 7900, 8300, 9500, 11200, 10400, 12800],
                    borderColor: '#6c5ce7',
                    backgroundColor: 'rgba(108, 92, 231, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Smartphones', 'Other'],
                datasets: [{
                    data: [90, 10],
                    backgroundColor: [
                        '#6c5ce7',
                        '#7c3aed'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            padding: 20
                        }
                    }
                }
            }
        });

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.querySelector('.md\\:hidden');
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content-area');

            mobileMenuButton.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
                content.classList.toggle('ml-0');
                content.classList.toggle('ml-64');
            });
        });
    </script>
</body>
</html>

