<?php
$startDate = $_GET['start_date'] ?? null;
$endDate   = $_GET['end_date'] ?? null;
$quick     = $_GET['quick'] ?? null;

if ($quick) {
  $today = date('Y-m-d');
  switch ($quick) {
    case 'today':
      $startDate = $endDate = $today;
      break;
    case '3days':
      $startDate = date('Y-m-d', strtotime('-2 days'));
      $endDate = $today;
      break;
    case '7days':
      $startDate = date('Y-m-d', strtotime('-6 days'));
      $endDate = $today;
      break;
    case '1month':
      $startDate = date('Y-m-d', strtotime('-1 month'));
      $endDate = $today;
      break;
    case '3months':
      $startDate = date('Y-m-d', strtotime('-3 months'));
      $endDate = $today;
      break;
    case '1year':
      $startDate = date('Y-m-d', strtotime('-1 year'));
      $endDate = $today;
      break;
    case 'all':
      $startDate = $endDate = null;
      break;
  }
}

$params = [];
$where  = "WHERE o.shipping_status = 'shipped'";

if ($startDate && $endDate) {
  $where .= " AND DATE(o.created_date) BETWEEN ? AND ?";
  $params[] = $startDate;
  $params[] = $endDate;
}

$ordersStmt = $pdo->prepare("SELECT o.items_json, o.final_price, o.created_date FROM orders o $where ORDER BY o.created_date DESC");
$ordersStmt->execute($params);

$topSelling  = [];
$totalOrders = 0;

$productCache  = [];
$variantCache  = [];
$categoryCache = [];

while ($row = $ordersStmt->fetch()) {
  $items = json_decode($row['items_json'], true);
  if (!$items) continue;

  $totalOrders++;

  foreach ($items as $item) {
    $variantId = $item['variantId'] ?? null;
    $quantity  = intval($item['quantity'] ?? 0);
    if (!$variantId || $quantity <= 0) continue;

    if (!isset($variantCache[$variantId])) {
      $stmt = $pdo->prepare("SELECT product_id, price as variant_price FROM variants WHERE mongo_id = ? LIMIT 1");
      $stmt->execute([$variantId]);
      $variantCache[$variantId] = $stmt->fetch();
    }

    $variant = $variantCache[$variantId];
    if (!$variant) continue;
    $price = floatval($variant['variant_price']);
    $productId = $variant['product_id'];

    if (!isset($productCache[$productId])) {
      $stmt = $pdo->prepare("SELECT product_name, category_id FROM products WHERE mongo_id = ? LIMIT 1");
      $stmt->execute([$productId]);
      $productCache[$productId] = $stmt->fetch();
    }

    $product = $productCache[$productId];
    if (!$product) continue;

    if (!isset($productCache[$productId]['image'])) {
      $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY modified_date DESC LIMIT 1");
      $stmt->execute([$productId]);
      $imageResult = $stmt->fetch();
      $product['image'] = $imageResult['image_url'] ?? 'default-product.png';
      $productCache[$productId]['image'] = $product['image'];
    } else {
      $product['image'] = $productCache[$productId]['image'];
    }

    $categoryId = $product['category_id'];
    if ($categoryId && !isset($categoryCache[$categoryId])) {
      $stmt = $pdo->prepare("SELECT name FROM categories WHERE mongo_id = ? LIMIT 1");
      $stmt->execute([$categoryId]);
      $result = $stmt->fetch();
      $categoryCache[$categoryId] = $result ? $result['name'] : 'Chưa phân loại';
    }

    $productKey = $product['product_name'];
    if (!isset($topSelling[$productKey])) {
      $topSelling[$productKey] = [
        'name' => $product['product_name'],
        'category' => $categoryId ? ($categoryCache[$categoryId] ?? 'Chưa phân loại') : 'Chưa phân loại',
        'image' => $product['image'],
        'total_quantity' => 0,
        'total_revenue' => 0,
        'order_count' => 0,
        'avg_price' => 0,
        'variant_price' => $variant['variant_price'] ?? 0,
      ];
    }

    $topSelling[$productKey]['total_quantity'] += $quantity;
    $topSelling[$productKey]['total_revenue'] += ($price * $quantity);
    $topSelling[$productKey]['order_count']++;
    $topSelling[$productKey]['avg_price'] = $topSelling[$productKey]['total_revenue'] / $topSelling[$productKey]['total_quantity'];
  }
}

uasort($topSelling, function ($a, $b) {
  return $b['total_quantity'] <=> $a['total_quantity'];
});

$topSellingProducts = array_slice($topSelling, 0, 10, true);
?>

<!-- Top bán chạy + Bộ lọc thời gian gộp chung -->
<div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden mt-8 animate-fade-in-up">
  <div class="bg-gradient-to-r from-purple-700 to-indigo-600 p-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
      <div>
        <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2 animate-pulse">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
          </svg>
          Top Sản Phẩm Bán Chạy
        </h2>
        <p class="text-indigo-100 text-sm mt-1 animate-fade-in">
          Dựa trên <strong class="font-bold text-white"><?php echo number_format($totalOrders) ?></strong> đơn hàng đã giao
        </p>
      </div>

      <!-- Bộ lọc thời gian gộp chung -->
      <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end bg-white/20 p-4 rounded-xl shadow-md">
        <div>
          <label class="block text-xs font-semibold text-white mb-1">Từ ngày</label>
          <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate) ?>" class="w-full rounded-lg border border-indigo-300 px-3 py-1.5 bg-white text-gray-800 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-xs font-semibold text-white mb-1">Đến ngày</label>
          <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate) ?>" class="w-full rounded-lg border border-indigo-300 px-3 py-1.5 bg-white text-gray-800 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-lg shadow-lg hover:scale-105 transition-transform duration-300">
            <i class="fa fa-filter mr-1"></i> Lọc theo ngày
          </button>
        </div>
      </form>
    </div>
  </div>
 

  <div class="p-6">
    <?php if (empty($topSellingProducts)): ?>
      <div class="text-center py-20 animate-fade-in">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4 animate-bounce" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-lg font-semibold text-gray-700 dark:text-white">Chưa có dữ liệu</h3>
        <p class="text-gray-500 dark:text-gray-400">Không có sản phẩm nào được bán ra.</p>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto hidden md:block animate-fade-in-up">
        <table class="w-full table-auto text-left">
          <thead>
            <tr class="text-sm font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600">
              <th class="py-3 px-3">#</th>
              <th class="py-3 px-3">Sản phẩm</th>
              <th class="py-3 px-3 text-center">Danh mục</th>
              <th class="py-3 px-3 text-center">Đã bán</th>
              <th class="py-3 px-3 text-center">Doanh thu</th>
              <th class="py-3 px-3 text-center">Giá TB</th>
              <th class="py-3 px-3 text-center">Đơn hàng</th>
            </tr>
          </thead>
          <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700">
            <?php $rank = 1; foreach ($topSellingProducts as $product): ?>
              <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-300">
                <td class="py-4 px-3">
                  <div class="w-8 h-8 flex items-center justify-center rounded-full font-bold 
                    <?php if ($rank == 1): ?>bg-yellow-300 text-yellow-900<?php elseif ($rank == 2): ?>bg-gray-300 text-gray-900<?php elseif ($rank == 3): ?>bg-orange-300 text-orange-900<?php else: ?>bg-indigo-100 text-indigo-900<?php endif; ?>">
                    <?php echo $rank ?>
                  </div>
                </td>
                <td class="py-4 px-3">
                  <div class="flex items-center gap-3">
                    <img src="<?php echo htmlspecialchars($product['image']) ?>" onerror="this.src='default-product.png'" class="w-12 h-12 object-cover rounded-md border border-gray-200 shadow-sm" />
                    <div>
                      <div class="font-medium text-gray-800 dark:text-white truncate">
                        <?php echo htmlspecialchars($product['name']) ?>
                      </div>
                      <div class="text-xs text-gray-500">Đang bán</div>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-3 text-center">
                  <span class="inline-block bg-indigo-100 text-indigo-800 text-xs font-semibold px-3 py-1 rounded-full">
                    <?php echo htmlspecialchars($product['category']) ?>
                  </span>
                </td>
                <td class="py-4 px-3 text-center">
                  <span class="text-lg font-semibold text-gray-800 dark:text-white">
                    <?php echo number_format($product['total_quantity']) ?>
                  </span>
                  <div class="text-xs text-gray-500">sp</div>
                </td>
                <td class="py-4 px-3 text-center">
                  <span class="text-lg font-bold text-green-600">
                    <?php echo number_format($product['total_revenue']) ?>₫
                  </span>
                </td>
                <td class="py-4 px-3 text-center">
                  <span class="text-sm font-medium text-gray-700 dark:text-white">
                    <?php echo number_format($product['avg_price']) ?>₫
                  </span>
                </td>
                <td class="py-4 px-3 text-center">
                  <span class="text-sm font-bold text-purple-600">
                    <?php echo number_format($product['order_count']) ?>
                  </span>
                </td>
              </tr>
              <?php $rank++; endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Summary Footer -->
      <div class="mt-8 p-6 bg-gradient-to-r from-purple-100 to-indigo-100 dark:from-gray-800 dark:to-gray-700 rounded-xl border border-purple-200 dark:border-gray-600 animate-fade-in">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
          <div>
            <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
              <?php echo number_format(array_sum(array_column($topSellingProducts, 'total_quantity'))) ?>
            </div>
            <div class="text-sm text-gray-500">Tổng sản phẩm</div>
          </div>
          <div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400">
              <?php echo number_format(array_sum(array_column($topSellingProducts, 'total_revenue'))) ?>₫
            </div>
            <div class="text-sm text-gray-500">Tổng doanh thu</div>
          </div>
          <div>
            <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400">
              <?php echo number_format(array_sum(array_column($topSellingProducts, 'order_count'))) ?>
            </div>
            <div class="text-sm text-gray-500">Tổng đơn hàng</div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  </div>

<style>
@keyframes fade-in-up {
  0% {
    opacity: 0;
    transform: translateY(20px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fade-in-up {
  animation: fade-in-up 0.6s ease-out both;
}
.animate-fade-in {
  animation: fade-in 1s ease-in both;
}
@keyframes fade-in {
  0% { opacity: 0; }
  100% { opacity: 1; }
}
</style>
