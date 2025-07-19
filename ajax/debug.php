<?php
// DEBUG: Kiểm tra từng bước để tìm nguyên nhân

echo "<h3>🔍 DEBUG TOP SẢN PHẨM</h3>";

// BƯỚC 1: Kiểm tra có đơn hàng shipped không
echo "<h4>1. Kiểm tra đơn hàng shipped:</h4>";
$totalOrdersCheck = $pdo->query("SELECT COUNT(*) FROM orders WHERE shipping_status = 'shipped'");
$totalShippedOrders = $totalOrdersCheck->fetchColumn();
echo "- Tổng đơn hàng shipped: <strong>$totalShippedOrders</strong><br>";

if ($totalShippedOrders == 0) {
    echo "❌ Không có đơn hàng nào có shipping_status = 'shipped'<br>";
    
    // Kiểm tra các giá trị shipping_status có trong DB
    echo "<h4>Các giá trị shipping_status hiện có:</h4>";
    $statusCheck = $pdo->query("SELECT DISTINCT shipping_status, COUNT(*) as count FROM orders GROUP BY shipping_status");
    while ($status = $statusCheck->fetch()) {
        echo "- '{$status['shipping_status']}': {$status['count']} đơn<br>";
    }
    exit;
}

// BƯỚC 2: Lấy mẫu đơn hàng để kiểm tra
echo "<h4>2. Kiểm tra cấu trúc items_json:</h4>";
$sampleOrder = $pdo->query("SELECT id, items_json FROM orders WHERE shipping_status = 'shipped' LIMIT 1")->fetch();
if ($sampleOrder) {
    echo "- Đơn hàng ID: {$sampleOrder['id']}<br>";
    echo "- items_json: <pre>" . htmlspecialchars($sampleOrder['items_json']) . "</pre>";
    
    $sampleItems = json_decode($sampleOrder['items_json'], true);
    if (!$sampleItems) {
        echo "❌ items_json không parse được hoặc rỗng<br>";
    } else {
        echo "✅ items_json parse thành công, có " . count($sampleItems) . " items<br>";
        foreach ($sampleItems as $index => $item) {
            echo "Item $index:<br>";
            echo "- variantId: " . ($item['variantId'] ?? 'KHÔNG CÓ') . "<br>";
            echo "- quantity: " . ($item['quantity'] ?? 'KHÔNG CÓ') . "<br>";  
            echo "- price: " . ($item['price'] ?? 'KHÔNG CÓ') . "<br><br>";
        }
    }
}

// BƯỚC 3: Kiểm tra variant tồn tại
echo "<h4>3. Kiểm tra variants:</h4>";
if ($sampleItems && !empty($sampleItems)) {
    $firstItem = $sampleItems[0];
    $variantId = $firstItem['variantId'] ?? null;
    
    if ($variantId) {
        $variantCheck = $pdo->prepare("SELECT * FROM variants WHERE mongo_id = ? LIMIT 1");
        $variantCheck->execute([$variantId]);
        $variant = $variantCheck->fetch();
        
        if ($variant) {
            echo "✅ Variant tồn tại:<br>";
            echo "- ID: {$variant['id']}<br>";
            echo "- mongo_id: {$variant['mongo_id']}<br>";
            echo "- product_id: {$variant['product_id']}<br>";
            echo "- price: {$variant['price']}<br>";
        } else {
            echo "❌ Variant không tồn tại với mongo_id: $variantId<br>";
        }
    }
}

// BƯỚC 4: Kiểm tra products
echo "<h4>4. Kiểm tra products:</h4>";
if (isset($variant) && $variant) {
    $productCheck = $pdo->prepare("SELECT * FROM products WHERE mongo_id = ? LIMIT 1");
    $productCheck->execute([$variant['product_id']]);
    $product = $productCheck->fetch();
    
    if ($product) {
        echo "✅ Product tồn tại:<br>";
        echo "- ID: {$product['id']}<br>";
        echo "- mongo_id: {$product['mongo_id']}<br>";
        echo "- product_name: {$product['product_name']}<br>";
        echo "- category_id: {$product['category_id']}<br>";
    } else {
        echo "❌ Product không tồn tại với mongo_id: {$variant['product_id']}<br>";
    }
}

// BƯỚC 5: Chạy code chính với debug
echo "<h4>5. Chạy code chính với debug:</h4>";

$ordersStmt = $pdo->query("
    SELECT o.items_json, o.final_price, o.created_date 
    FROM orders o 
    WHERE o.shipping_status = 'shipped'
    ORDER BY o.created_date DESC
    LIMIT 5
");

$topSelling = [];
$totalOrders = 0;
$debugCount = 0;

while ($row = $ordersStmt->fetch()) {
    $debugCount++;
    echo "<br><strong>Debug đơn hàng #$debugCount:</strong><br>";
    
    $items = json_decode($row['items_json'], true);
    if (!$items) {
        echo "- ❌ items_json không parse được<br>";
        continue;
    }
    
    $totalOrders++;
    echo "- ✅ Parse được " . count($items) . " items<br>";
    
    foreach ($items as $itemIndex => $item) {
        echo "  Item $itemIndex:<br>";
        
        $variantId = $item['variantId'] ?? null;
        $quantity = intval($item['quantity'] ?? 0);
        $price = floatval($item['price'] ?? 0);
        
        echo "    - variantId: $variantId<br>";
        echo "    - quantity: $quantity<br>";
        echo "    - price: $price<br>";
        
        if (!$variantId || $quantity <= 0) {
            echo "    - ❌ Skip: variantId rỗng hoặc quantity <= 0<br>";
            continue;
        }

        // Kiểm tra variant
        $variantStmt = $pdo->prepare("SELECT product_id, price as variant_price FROM variants WHERE mongo_id = ? LIMIT 1");
        $variantStmt->execute([$variantId]);
        $variant = $variantStmt->fetch();
        
        if (!$variant) {
            echo "    - ❌ Skip: Không tìm thấy variant<br>";
            continue;
        }
        
        echo "    - ✅ Tìm thấy variant, product_id: {$variant['product_id']}<br>";
        
        // Kiểm tra product
        $productStmt = $pdo->prepare("SELECT product_name, category_id FROM products WHERE mongo_id = ? LIMIT 1");
        $productStmt->execute([$variant['product_id']]);
        $product = $productStmt->fetch();
        
        if (!$product) {
            echo "    - ❌ Skip: Không tìm thấy product<br>";
            continue;
        }
        
        echo "    - ✅ Tìm thấy product: {$product['product_name']}<br>";
        
        $productKey = $product['product_name'];
        $revenue = $price * $quantity;
        echo "    - ✅ Tính revenue: $price x $quantity = $revenue<br>";
        
        if (!isset($topSelling[$productKey])) {
            $topSelling[$productKey] = [
                'name' => $product['product_name'],
                'total_quantity' => 0,
                'total_revenue' => 0,
                'order_count' => 0
            ];
        }
        
        $topSelling[$productKey]['total_quantity'] += $quantity;
        $topSelling[$productKey]['total_revenue'] += $revenue;
        $topSelling[$productKey]['order_count']++;
        
        echo "    - ✅ Cập nhật: quantity={$topSelling[$productKey]['total_quantity']}, revenue={$topSelling[$productKey]['total_revenue']}<br>";
    }
}

echo "<h4>6. Kết quả cuối cùng:</h4>";
echo "Tổng đơn hàng xử lý: $totalOrders<br>";
echo "Số sản phẩm tìm được: " . count($topSelling) . "<br><br>";

foreach ($topSelling as $name => $data) {
    echo "<strong>$name:</strong><br>";
    echo "- Số lượng: {$data['total_quantity']}<br>";
    echo "- Doanh thu: " . number_format($data['total_revenue']) . "₫<br>";
    echo "- Số đơn: {$data['order_count']}<br><br>";
}
?>