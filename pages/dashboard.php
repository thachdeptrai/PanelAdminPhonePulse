<?php
// pages/dashboard.php
require_once('../includes/config.php');
require_once('../api/client.php');
include('../includes/header.php');

$api = new MongoAPIClient();
$statsResult = $api->getDashboardStats();
$stats = $statsResult['success'] ? $statsResult['data'] : [
    'total_users' => 0,
    'active_users' => 0,
    'total_orders' => 0,
    'revenue' => 0
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <h2>Dashboard</h2>
      <ul>
        <li><a href="pages/dashboard.php"><i class="fas fa-home"></i> Trang chính</a></li>
        <li><a href="pages/products.php"><i class="fas fa-box"></i> Quản lý Sản phẩm</a></li>
        <li><a href="pages/users.php"><i class="fas fa-users"></i> Quản lý Users</a></li>
        <li><a href="pages/revenue.php"><i class="fas fa-chart-line"></i> Quản lý Doanh thu</a></li>
        <li><a href="pages/categories.php"><i class="fas fa-tags"></i> Quản lý Danh mục</a></li>
        <li><a href="pages/vouchers.php"><i class="fas fa-ticket-alt"></i> Quản lý Voucher</a></li>
        <li><a href="pages/notifications.php"><i class="fas fa-bell"></i> Gửi Thông báo</a></li>
        <li><a href="pages/support.php"><i class="fas fa-headset"></i> Chăm sóc KH</a></li>
      </ul>
    </aside>
    <main class="content">
      <header>
        <h1>Thống kê tổng quan</h1>
      </header>
      <section class="stats">
        <div class="card blue">
          <h3>Tổng Users</h3>
          <p>1200</p>
        </div>
        <div class="card green">
          <h3>Users hoạt động</h3>
          <p>840</p>
        </div>
        <div class="card yellow">
          <h3>Tổng Orders</h3>
          <p>350</p>
        </div>
        <div class="card cyan">
          <h3>Doanh thu</h3>
          <p>120.000.000đ</p>
        </div>
      </section>
      <section class="chart">
        <canvas id="dashboardChart"></canvas>
      </section>
    </main>
  </div>
  <script src="assets/js/dashboard.js"></script>
</body>
</html>

<?php include('../includes/footer.php'); ?>

 