<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
use MongoDB\BSON\ObjectId;

// Lấy sản phẩm bán chạy
$bestSellingProducts = [];

try {
    // Sử dụng lookup để lấy giá từ variant thay vì từ items
    $pipeline = [
        [
            '$match' => [
                'status' => ['$ne' => 'cancelled']
            ]
        ],
        [
            '$unwind' => '$items'
        ],
        [
            '$lookup' => [
                'from' => 'Variant',
                'localField' => 'items.variantId',
                'foreignField' => '_id',
                'as' => 'variant'
            ]
        ],
        [
            '$unwind' => '$variant'
        ],
        [
            '$group' => [
                '_id' => '$items.variantId',
                'totalQuantity' => ['$sum' => '$items.quantity'],
                'totalRevenue' => ['$sum' => ['$multiply' => ['$items.quantity', '$variant.price']]]
            ]
        ],
        [
            '$sort' => ['totalQuantity' => -1]
        ],
        [
            '$limit' => 5
        ]
    ];
    
    $result = $mongoDB->orders->aggregate($pipeline)->toArray();
    
    foreach ($result as $item) {
        try {
            $variant = $mongoDB->Variant->findOne(['_id' => new ObjectId($item['_id'])]);
            if ($variant) {
                $product = $mongoDB->Product->findOne(['_id' => new ObjectId($variant['product_id'])]);
                if ($product) {
                    $sizeName = '';
                    $sizesto = '';
                    if (!empty($variant['size_id'])) {
                        $size = $mongoDB->Size->findOne(['_id' => new ObjectId($variant['size_id'])]);
                        $sizeName = $size['size_name'] ?? '';
                        $sizesto = $size['storage'] ?? '';
                    }
                    $variantParts = array_filter([$sizeName,$sizesto]);
                    $variantName = implode(' - ', $variantParts);
                    
                    $bestSellingProducts[] = [
                        'product_name' => $product['product_name'],
                        'variant_name' => $variantName,
                        'quantity_sold' => $item['totalQuantity'],
                        'revenue' => $item['totalRevenue'] ?? 0,
                        'price' => $variant['price'] ?? 0
                    ];
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
} catch (Exception $e) {
    // Handle error
}
?>

<!-- Sản phẩm bán chạy với thiết kế cải tiến -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden transition-all duration-300 hover:shadow-xl mb-6">
  <!-- Header với gradient background -->
  <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <div class="bg-white/20 rounded-lg p-2">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
          </svg>
        </div>
        <div>
          <h2 class="text-xl font-bold text-white">Sản Phẩm Bán Chạy</h2>
          <p class="text-blue-100 text-sm">Top 5 sản phẩm có doanh số cao nhất</p>
        </div>
      </div>
      <a href="orders" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105">
        Xem tất cả →
      </a>
    </div>
  </div>

  <!-- Content -->
  <div class="p-6">
    <?php if (empty($bestSellingProducts)): ?>
    <!-- Empty state với animation -->
    <div class="text-center py-12">
      <div class="mx-auto w-24 h-24 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
        </svg>
      </div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Chưa có dữ liệu</h3>
      <p class="text-gray-500 dark:text-gray-400">Dữ liệu sản phẩm bán chạy sẽ hiển thị khi có đơn hàng</p>
    </div>
    <?php else: ?>
    <!-- Table với design cải tiến -->
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                <div class="flex items-center space-x-2">
                  <span>Sản Phẩm</span>
                </div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Biến Thể
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                <div class="flex items-center space-x-1">
                  <span>Đã Bán</span>
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                  </svg>
                </div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Giá
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Doanh Thu
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <?php foreach ($bestSellingProducts as $index => $product): ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 group">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                      <?= $index + 1 ?>
                    </div>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                      <?= htmlspecialchars($product['product_name']) ?>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                  <?= htmlspecialchars($product['variant_name'] ?: 'Mặc định') ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <span class="text-sm font-semibold text-gray-900 dark:text-white">
                    <?= number_format($product['quantity_sold']) ?>
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">đơn vị</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm font-medium text-gray-900 dark:text-white">
                  <?= number_format($product['price'], 0, ',', '.') ?>₫
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <div class="flex-shrink-0 w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                  <span class="text-sm font-bold text-green-600 dark:text-green-400">
                    <?= number_format($product['revenue'], 0, ',', '.') ?>₫
                  </span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Summary footer -->
    <div class="mt-6 bg-gradient-to-r from-gray-50 to-blue-50 dark:from-gray-700 dark:to-gray-600 rounded-lg p-4">
      <div class="flex items-center justify-between text-sm">
        <span class="text-gray-600 dark:text-gray-300">
          Tổng <?= count($bestSellingProducts) ?> sản phẩm bán chạy nhất
        </span>
        <div class="flex items-center space-x-4">
          <?php 
          $totalQuantity = array_sum(array_column($bestSellingProducts, 'quantity_sold'));
          $totalRevenue = array_sum(array_column($bestSellingProducts, 'revenue'));
          ?>
          <span class="text-gray-600 dark:text-gray-300">
            Tổng bán: <span class="font-semibold text-gray-900 dark:text-white"><?= number_format($totalQuantity) ?></span>
          </span>
          <span class="text-gray-600 dark:text-gray-300">
            Doanh thu: <span class="font-semibold text-green-600 dark:text-green-400"><?= number_format($totalRevenue, 0, ',', '.') ?>₫</span>
          </span>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>