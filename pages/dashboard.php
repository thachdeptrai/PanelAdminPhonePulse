<?php
require_once '../includes/config.php'; // có $mongoDB
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

// ✅ Thống kê tổng quan
$stats = getDashboardStats(); // giả định đã migrate qua Mongo

// ✅ Đơn hàng gần đây
$recentOrders = [];
$cursor = $mongoDB->orders->find([], ['sort' => ['created_date' => -1], 'limit' => 5]);
foreach ($cursor as $order) {
    $customer = null;
    if (!empty($order['userId'])) {
        try {
            $customer = $mongoDB->users->findOne(['_id' => new ObjectId($order['userId'])]);
        } catch (Exception $e) {}
    }

    $recentOrders[] = [
        'mongo_id' => (string)$order['_id'],
        'final_price' => $order['final_price'] ?? 0,
        'status' => $order['status'] ?? '',
        'customer_name' => $customer['name'] ?? 'Unknown'
    ];
}

// ✅ Hoạt động gần đây
$activities = [];
$cursor = $mongoDB->orders->find([], ['sort' => ['created_date' => -1], 'limit' => 5]);
foreach ($cursor as $order) {
    $customer = null;
    if (!empty($order['userId'])) {
        try {
            $customer = $mongoDB->users->findOne(['_id' => new ObjectId($order['userId'])]);
        } catch (Exception $e) {}
    }

    $activities[] = [
        'mongo_id' => (string)$order['_id'],
        'created_date' => $order['updatedAt'] ?? '',
        'user_name' => $customer['name']
    ];
}

// ✅ Doanh thu theo danh mục
$categoryRevenue = [];
$orders = $mongoDB->orders->find();

foreach ($orders as $order) {
    $items = $order['items'] ?? [];
    foreach ($items as $item) {
        $variantId = $item['variantId'] ?? null;
        $quantity = $item['quantity'] ?? 0;
        if (!$variantId || $quantity <= 0) continue;

        try {
            $variant = $mongoDB->Variant->findOne(['_id' => new ObjectId($variantId)]);
            if (!$variant) continue;

            $product = $mongoDB->Product->findOne(['_id' => new ObjectId($variant['product_id'])]);
            if (!$product) continue;

            $category = $mongoDB->Category->findOne(['_id' => new ObjectId($product['category_id'])]);
            if (!$category) continue;

            $catName = $category['name'] ?? 'Khác';
            $price = $variant['price'] ?? 0;

            $categoryRevenue[$catName] = ($categoryRevenue[$catName] ?? 0) + $price * $quantity;
        } catch (Exception $e) {
            continue;
        }
    }
}

// ✅ Chuẩn bị dữ liệu cho biểu đồ
$categoryLabels = json_encode(array_keys($categoryRevenue));
$categoryValues = json_encode(array_values($categoryRevenue));

// ✅ Thống kê hiển thị
$totalRevenue = $stats['revenue'] ?? 0;
$totalOrders  = $stats['orders'] ?? 0;
$activeUsers  = $stats['users'] ?? 0;
$totalProducts = $stats['product'] ?? 0;

$revChange   = $stats['rev_change'] ?? '0%';
$orderChange = $stats['order_change'] ?? '0%';
$userChange  = $stats['user_change'] ?? '0%';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['meta_title'] ?? 'Admin Dashboard') ?></title>

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
<?php include '../assets/bieudo.php'; ?>
<?php include '../assets/sanphambanchay.php'; ?>
<?php include 'KhachHangThanThiet.php'; ?>

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
  <br>
  <?php include '../includes/footer.php'; ?>

</main>

    </div>
    
<script>
  const categoryLabels = <?= $categoryLabels ?>;
const categoryValues = <?= $categoryValues ?>;

document.addEventListener('DOMContentLoaded', () => {
  renderCategoryChart();
  initRevenueChart(); // mặc định là "month"

  const rangeSelect = document.getElementById('rangeSelect');
  const customDateRange = document.getElementById('customDateRange');
  const startInput = document.getElementById('startDate');
  const endInput = document.getElementById('endDate');

  // Khi chọn loại khoảng thời gian
  rangeSelect?.addEventListener('change', () => {
    const type = rangeSelect.value;

    if (type === 'custom') {
      customDateRange?.classList.remove('hidden');
      const start = startInput?.value;
      const end = endInput?.value;
      if (start && end) initRevenueChart('custom', start, end);
    } else {
      customDateRange?.classList.add('hidden');
      initRevenueChart(type);
    }
  });

  // Khi chọn ngày custom
  [startInput, endInput].forEach(input => {
    input?.addEventListener('change', () => {
      if (rangeSelect?.value === 'custom') {
        const start = startInput?.value;
        const end = endInput?.value;
        if (start && end) {
          initRevenueChart('custom', start, end);
        }
      }
    });
  });
});

// ================= VẼ BIỂU ĐỒ DOANH SỐ THEO DANH MỤC =================
function renderCategoryChart() {
  const ctx = document.getElementById('categoryChart')?.getContext('2d');
  if (!ctx) {
    console.warn('categoryChart element not found');
    return;
  }

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: categoryLabels,
      datasets: [{
        label: 'Doanh số (VNĐ)',
        data: categoryValues,
        backgroundColor: 'rgba(54, 162, 235, 0.7)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 1,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e293b',
          titleColor: '#fff',
          bodyColor: '#e5e7eb',
          callbacks: {
            label: ctx => new Intl.NumberFormat('vi-VN', {
              style: 'currency',
              currency: 'VND'
            }).format(ctx.parsed.y)
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            color: '#cbd5e1',
            callback: v => v.toLocaleString('vi-VN') + '₫'
          },
          grid: { color: 'rgba(255,255,255,0.05)' }
        },
        x: {
          ticks: { color: '#cbd5e1' },
          grid: { display: false }
        }
      }
    }
  });
}

// ================= FETCH DỮ LIỆU DOANH THU TỪ PHP =================
async function fetchRevenueData(type = 'month', start = '', end = '') {
  try {
    let url = `ajax/get_revenue_data.php?type=${type}`;
    if (type === 'custom' && start && end) {
      url += `&start=${start}&end=${end}`;
    }

    console.log('Fetching data from:', url);

    const res = await fetch(url);
    const data = await res.json();
    
    if (!res.ok || data.error) {
      throw new Error(data.message || 'Failed to fetch revenue data');
    }

    console.log('Revenue data received:', data);

    return {
      labels: data.map(i => i.label),
      values: data.map(i => i.total)
    };
  } catch (error) {
    console.error('Error fetching revenue data:', error);
    return {
      labels: [],
      values: []
    };
  }
}

// ================= VẼ BIỂU ĐỒ DOANH THU =================
let revenueChart;
async function initRevenueChart(type = 'month', start = '', end = '') {
  const { labels, values } = await fetchRevenueData(type, start, end);
  const ctx = document.getElementById('revenueChart')?.getContext('2d');
  
  if (!ctx) {
    console.warn('revenueChart element not found');
    return;
  }

  if (revenueChart) {
    revenueChart.destroy();
  }

  // Format labels dựa trên type
  const formattedLabels = labels.map(date => {
    const d = new Date(date);
    if (type === 'year') {
      return d.toLocaleDateString('vi-VN', {
        month: '2-digit',
        year: 'numeric'
      });
    } else {
      return d.toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit'
      });
    }
  });

  const gradient = ctx.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(0, 255, 135, 0.25)');
  gradient.addColorStop(1, 'rgba(0, 255, 135, 0)');

  revenueChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: formattedLabels,
      datasets: [{
        label: 'Doanh Thu (VND)',
        data: values,
        borderColor: '#00FF87',
        backgroundColor: gradient,
        borderWidth: 3,
        pointBackgroundColor: '#00FF87',
        pointBorderColor: '#fff',
        pointRadius: 3,
        pointHoverRadius: 7,
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 1200,
        easing: 'easeInOutCubic'
      },
      plugins: {
        tooltip: {
          backgroundColor: '#1e293b',
          titleColor: '#fff',
          bodyColor: '#e5e7eb',
          padding: 12,
          borderColor: '#00FF87',
          borderWidth: 1,
          callbacks: {
            label: ctx => `Doanh thu: ${ctx.parsed.y.toLocaleString('vi-VN')}₫`
          }
        },
        legend: {
          display: true,
          labels: {
            color: '#cbd5e1',
            font: { size: 12 }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            color: '#cbd5e1',
            callback: v => v.toLocaleString('vi-VN') + '₫'
          },
          grid: { 
            color: 'rgba(255,255,255,0.05)', 
            drawTicks: false 
          }
        },
        x: {
          ticks: { 
            color: '#cbd5e1',
            maxTicksLimit: 10 // Giới hạn số tick để tránh bị chồng lấp
          },
          grid: { display: false }
        }
      }
    }
  });
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
</body>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- <script src="../assets/js/dashboard.js"></script> -->
</html>

