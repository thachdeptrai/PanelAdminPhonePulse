<?php
include '../includes/config.php';
include '../includes/functions.php';

if (!isAdmin()) {
    header('Location: dang_nhap');
    exit;
}

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: dang_nhap");
    exit();
}

// Lấy dữ liệu người dùng
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Lấy thống kê dashboard
$stats = getDashboardStats();

// Lấy đơn hàng gần đây
$recentOrdersStmt = $pdo->query("SELECT o.mongo_id, o.final_price, o.status, u.name AS customer_name
                                  FROM orders o
                                  LEFT JOIN users u ON o.user_id = u.mongo_id
                                  ORDER BY o.created_date DESC LIMIT 5");
$recentOrders = $recentOrdersStmt->fetchAll();

// Lấy hoạt động gần đây
$activityStmt = $pdo->query("SELECT u.name AS user_name, o.mongo_id, o.created_date
                              FROM orders o
                              LEFT JOIN users u ON o.user_id = u.mongo_id
                              ORDER BY o.created_date DESC LIMIT 5");
$activities = $activityStmt->fetchAll();

// Tính doanh thu theo danh mục từ items_json
$ordersStmt = $pdo->query("SELECT items_json FROM orders");
$categoryRevenue = [];

while ($row = $ordersStmt->fetch()) {
    $items = json_decode($row['items_json'], true);
    foreach ($items as $item) {
        $variantId = $item['variantId'] ?? null;
        $quantity = $item['quantity'] ?? 0;
        if (!$variantId || $quantity <= 0) continue;

        // Lấy variant và product theo mongo_id
        $variantStmt = $pdo->prepare("SELECT price, product_id FROM variants WHERE mongo_id = ? LIMIT 1");
        $variantStmt->execute([$variantId]);
        $variant = $variantStmt->fetch();

        if ($variant) {
            $productStmt = $pdo->prepare("SELECT category_id FROM products WHERE mongo_id = ? LIMIT 1");
            $productStmt->execute([$variant['product_id']]);
            $product = $productStmt->fetch();

            if ($product) {
                $catStmt = $pdo->prepare("SELECT name FROM categories WHERE mongo_id = ? LIMIT 1");
                $catStmt->execute([$product['category_id']]);
                $category = $catStmt->fetchColumn();

                if ($category) {
                    $categoryRevenue[$category] = ($categoryRevenue[$category] ?? 0) + $variant['price'] * $quantity;
                }
            }
        }
    }
}

// Xuất ra biến JS cho biểu đồ danh mục
$categoryLabels = json_encode(array_keys($categoryRevenue));
$categoryValues = json_encode(array_values($categoryRevenue));

// Tổng hợp
$totalRevenue = $stats['revenue'] ?? 0;
$totalOrders = $stats['orders'] ?? 0;
$activeUsers = $stats['users'] ?? 0;
$totalProducts = $stats['product'] ?? 0;

$revChange = $stats['rev_change'] ?? '0%';
$orderChange = $stats['order_change'] ?? '0%';
$userChange = $stats['user_change'] ?? '0%';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Phonepulse Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
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
                            <?php echo strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="hidden md:inline"><?php echo htmlspecialchars(explode('@', $user['name'])[0]) ?></span>
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
<?php
    $totalRevenue     = $stats['revenue'] ?? 0;
    $totalOrders      = $stats['orders'] ?? 0;
    $activeUsers      = $stats['users'] ?? 0;
    $totalProducts    = $stats['product'] ?? 0;

    $revChange        = $stats['rev_change'] ?? '0%';
    $orderChange      = $stats['order_change'] ?? '0%';
    $userChange       = $stats['user_change'] ?? '0%';
?>

  <!-- Các thẻ thống kê -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

<!-- Tổng doanh thu -->
<div class="card p-6 rounded-lg">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-gray-400">Tổng Doanh Thu</p>
      <p class="text-2xl font-semibold mt-1"><?= number_format($totalRevenue, 0, ',', '.') ?>₫</p>
      <p class="text-green-400 text-sm mt-2 flex items-center">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
        </svg>
        <span class="ml-1"><?= $revChange ?> so với tháng trước</span>
      </p>
    </div>
    <div class="w-12 h-12 ...">
      <!-- Icon -->
    </div>
  </div>
</div>

<!-- Tổng số đơn hàng -->
<div class="card p-6 rounded-lg">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-gray-400">Tổng Số Đơn Hàng</p>
      <p class="text-2xl font-semibold mt-1"><?= number_format($totalOrders, 0, ',', '.') ?></p>
      <p class="text-green-400 text-sm mt-2 flex items-center">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
        </svg>
        <span class="ml-1"><?= $orderChange ?> so với tháng trước</span>
      </p>
    </div>
    <div class="w-12 h-12 ...">
      <!-- Icon -->
    </div>
  </div>
</div>

<!-- Người dùng hoạt động -->
<div class="card p-6 rounded-lg">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-gray-400">Người Dùng Hoạt Động</p>
      <p class="text-2xl font-semibold mt-1"><?= number_format($activeUsers, 0, ',', '.') ?></p>
      <p class="text-green-400 text-sm mt-2 flex items-center">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
        </svg>
        <span class="ml-1"><?= $userChange ?> so với tháng trước</span>
      </p>
    </div>
    <div class="w-12 h-12 ...">
      <!-- Icon -->
    </div>
  </div>
</div>

<!-- Sản phẩm -->
<div class="card p-6 rounded-lg">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-gray-400">Sản Phẩm</p>
      <p class="text-2xl font-semibold mt-1"><?= number_format($totalProducts, 0, ',', '.') ?></p>
      <p class="text-green-400 text-sm mt-2 flex items-center">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
        </svg>
        <span class="ml-1"><?= $revChange ?> so với tháng trước</span>
      </p>
    </div>
    <div class="w-12 h-12 ...">
      <!-- Icon -->
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
          <?php foreach ($recentOrders as $order): ?>
          <tr>
              <td>#<?= substr($order['mongo_id'], -6) ?></td>
              <td><?= htmlspecialchars($order['customer_name']) ?></td>
              <td><span class="badge bg-<?= $order['status'] === 'shipped' ? 'green' : ($order['status'] === 'cancelled' ? 'red' : 'yellow') ?>-500"><?= ucfirst($order['status']) ?></span></td>
              <td><?= number_format($order['final_price'], 0, ',', '.') ?>đ</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Hoạt động gần đây -->
    <div class="card p-6 rounded-lg">
      <h2 class="text-lg font-semibold mb-4">Hoạt Động Gần Đây</h2>
      <div class="space-y-4">
        <div class="flex items-start">
          <?php foreach ($activities as $act): ?>
          <div class="flex items-start">
              <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white mr-3">
                  <svg ...></svg>
              </div>
              <div>
                  <p class="text-sm"><span class="font-medium"><?= htmlspecialchars($act['user_name']) ?></span> đã đặt đơn hàng <span class="font-medium">#<?= substr($act['mongo_id'], -6) ?></span></p>
                  <p class="text-xs text-gray-400 mt-1"><?= date('H:i d/m/Y', strtotime($act['created_date'])) ?></p>
              </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</main>
    </div>
    
<script>
// Gửi dữ liệu doanh thu danh mục cho biểu đồ JS
const categoryLabels = <?= $categoryLabels ?>;
const categoryValues = <?= $categoryValues ?>;


// Vẽ biểu đồ doanh số theo danh mục
window.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('categoryChart')?.getContext('2d');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'Doanh số (VNĐ)',
                    data: categoryValues,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => value.toLocaleString('vi-VN') + 'đ'
                        }
                    }
                }
            }
        });
    }
});
async function fetchRevenueData(type) {
    const res = await fetch(`/ajax/get_revenue_data.php?type=${type}`);
    const data = await res.json();
    return {
        labels: data.map(item => item.label),
        values: data.map(item => item.total)
    };
}

let revenueChart;

async function initRevenueChart(type = 'month') {
    const { labels, values } = await fetchRevenueData(type);
    const ctx = document.getElementById('revenueChart').getContext('2d');

    if (revenueChart) revenueChart.destroy();

    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(108, 92, 231, 0.3)');
    gradient.addColorStop(1, 'rgba(108, 92, 231, 0)');

    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: values,
                borderColor: '#a78bfa',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#c084fc',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#fff',
                    bodyColor: '#e5e7eb',
                    padding: 10,
                    borderColor: '#6c5ce7',
                    borderWidth: 1
                },
                legend: {
                    display: true,
                    labels: {
                        color: '#cbd5e1',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#cbd5e1',
                        callback: (value) => value.toLocaleString() + 'đ'
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.05)'
                    }
                },
                x: {
                    ticks: {
                        color: '#cbd5e1'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

initRevenueChart();

document.querySelector('select').addEventListener('change', (e) => {
    const value = e.target.value;
    let type = 'month';
    if (value === '7 ngày qua') type = '7days';
    else if (value === '3 tháng gần nhất') type = '3months';
    else if (value === 'Năm nay') type = 'year';
    initRevenueChart(type);
});

</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
</body>

<!-- <script src="../assets/js/dashboard.js"></script> -->
</html>

