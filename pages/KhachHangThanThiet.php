<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
use MongoDB\BSON\ObjectId;

// Lấy khách hàng thân thiết
$loyalCustomers = [];

try {
    $pipeline = [
        [
            '$match' => [
                'status' => ['$ne' => 'cancelled']
            ]
        ],
        [
            '$group' => [
                '_id' => '$userId',
                'totalOrders' => ['$sum' => 1],
                'totalSpent' => ['$sum' => '$final_price'],
                'avgOrderValue' => ['$avg' => '$final_price']
            ]
        ],
        [
            '$sort' => ['totalSpent' => -1]
        ],
        [
            '$limit' => 5
        ]
    ];
    
    $result = $mongoDB->orders->aggregate($pipeline)->toArray();
    
    foreach ($result as $customer) {
        try {
            $user = $mongoDB->users->findOne(['_id' => new ObjectId($customer['_id'])]);
            if ($user) {
                $loyalCustomers[] = [
                    'name' => $user['name'] ?? 'Unknown',
                    'email' => $user['email'] ?? '',
                    'total_orders' => $customer['totalOrders'],
                    'total_spent' => $customer['totalSpent'],
                    'avg_order' => $customer['avgOrderValue']
                ];
            }
        } catch (Exception $e) {
            continue;
        }
    }
} catch (Exception $e) {
    // Handle error
}

// Tính toán thống kê tổng quan
$totalCustomers = count($loyalCustomers);
$totalRevenue = array_sum(array_column($loyalCustomers, 'total_spent'));
$avgOrderValue = $totalCustomers > 0 ? $totalRevenue / array_sum(array_column($loyalCustomers, 'total_orders')) : 0;

// Định nghĩa màu và icon cho từng hạng
function getRankInfo($index) {
    $ranks = [
        0 => ['color' => 'gold', 'bg' => 'from-yellow-400 to-yellow-600', 'icon' => '👑', 'title' => 'Vàng'],
        1 => ['color' => 'silver', 'bg' => 'from-gray-400 to-gray-600', 'icon' => '🥈', 'title' => 'Bạc'],
        2 => ['color' => 'bronze', 'bg' => 'from-orange-400 to-orange-600', 'icon' => '🥉', 'title' => 'Đồng'],
        3 => ['color' => 'blue', 'bg' => 'from-blue-400 to-blue-600', 'icon' => '⭐', 'title' => 'Kim cương'],
        4 => ['color' => 'purple', 'bg' => 'from-purple-400 to-purple-600', 'icon' => '💎', 'title' => 'Bạch kim']
    ];
    return $ranks[$index] ?? ['color' => 'gray', 'bg' => 'from-gray-400 to-gray-600', 'icon' => '🏆', 'title' => 'VIP'];
}
?>

<style>
.loyal-customer-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(51, 65, 85, 0.6) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.loyal-customer-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
    border-color: rgba(14, 165, 233, 0.5);
}

.loyal-customer-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.6s ease;
}

.loyal-customer-card:hover::before {
    left: 100%;
}

.rank-badge {
    background: linear-gradient(135deg, var(--rank-color-start), var(--rank-color-end));
    animation: pulse 2s infinite;
}

.metric-highlight {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.1) 0%, rgba(56, 189, 248, 0.05) 100%);
    border: 1px solid rgba(14, 165, 233, 0.2);
    transition: all 0.3s ease;
}

.metric-highlight:hover {
    border-color: rgba(14, 165, 233, 0.4);
    background: rgba(14, 165, 233, 0.15);
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.avatar-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.avatar-gradient::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.2) 50%, transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

.loyal-customer-card:hover .avatar-gradient::after {
    transform: translateX(100%);
}
</style>

<!-- Khách hàng thân thiết với giao diện cải tiến -->
<div class="mb-8">
    <!-- Header với gradient đẹp -->
    <div class="loyal-customer-card rounded-2xl p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-xl flex items-center justify-center text-2xl">
                    👑
                </div>
                <div>
                    <h2 class="text-2xl font-bold bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">
                        Khách Hàng Thân Thiết
                    </h2>
                    <p class="text-gray-400 mt-1">Top khách hàng có doanh thu cao nhất</p>
                </div>
            </div>
            <a href="customers" class="px-6 py-3 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white font-semibold rounded-xl transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-xl">
                Xem tất cả
            </a>
        </div>
    </div>

    <!-- Thống kê tổng quan -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="metric-highlight rounded-xl p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-sm font-medium">Tổng KH VIP</p>
                    <p class="text-2xl font-bold text-white"><?= $totalCustomers ?></p>
                </div>
            </div>
        </div>
        
        <div class="metric-highlight rounded-xl p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-600 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-sm font-medium">Tổng Chi Tiêu</p>
                    <p class="text-2xl font-bold text-green-400"><?= number_format($totalRevenue, 0, ',', '.') ?>₫</p>
                </div>
            </div>
        </div>
        
        <div class="metric-highlight rounded-xl p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-sm font-medium">TB/Đơn hàng</p>
                    <p class="text-2xl font-bold text-purple-400"><?= number_format($avgOrderValue, 0, ',', '.') ?>₫</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách khách hàng -->
    <div class="space-y-4">
        <?php if (empty($loyalCustomers)): ?>
            <div class="loyal-customer-card rounded-xl p-8 text-center">
                <div class="w-20 h-20 bg-gray-600 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-300 mb-2">Chưa có khách hàng thân thiết</h3>
                <p class="text-gray-400">Hãy bắt đầu bán hàng để có khách hàng VIP đầu tiên!</p>
            </div>
        <?php else: ?>
            <?php foreach ($loyalCustomers as $index => $customer): ?>
                <?php $rankInfo = getRankInfo($index); ?>
                <div class="loyal-customer-card rounded-xl p-6" 
                     style="--rank-color-start: <?= $rankInfo['color'] === 'gold' ? '#fbbf24' : ($rankInfo['color'] === 'silver' ? '#94a3b8' : ($rankInfo['color'] === 'bronze' ? '#f97316' : '#3b82f6')) ?>; --rank-color-end: <?= $rankInfo['color'] === 'gold' ? '#f59e0b' : ($rankInfo['color'] === 'silver' ? '#64748b' : ($rankInfo['color'] === 'bronze' ? '#ea580c' : '#1d4ed8')) ?>">
                    
                    <!-- Badge xếp hạng -->
                    <div class="absolute top-0 right-0 rank-badge text-white px-4 py-2 rounded-bl-xl rounded-tr-xl text-sm font-bold flex items-center space-x-1">
                        <span><?= $rankInfo['icon'] ?></span>
                        <span>#<?= $index + 1 ?> <?= $rankInfo['title'] ?></span>
                    </div>

                    <div class="flex items-center justify-between mt-2">
                        <!-- Thông tin khách hàng -->
                        <div class="flex items-center space-x-6">
                            <div class="relative">
                                <div class="avatar-gradient w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                                    <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                                </div>
                                <div class="absolute -top-2 -right-2 w-8 h-8 bg-gradient-to-r <?= $rankInfo['bg'] ?> rounded-full border-2 border-white flex items-center justify-center text-sm">
                                    <?= $rankInfo['icon'] ?>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="text-xl font-bold text-white mb-1"><?= htmlspecialchars($customer['name']) ?></h3>
                                <p class="text-gray-300 font-medium mb-2"><?= htmlspecialchars($customer['email']) ?></p>
                                <div class="flex items-center space-x-4">
                                    <span class="bg-primary/20 text-primary px-3 py-1 rounded-full text-sm font-medium">
                                        <?= number_format($customer['total_orders']) ?> đơn hàng
                                    </span>
                                    <span class="text-gray-400 text-sm">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Khách VIP
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Thống kê chi tiêu -->
                        <div class="text-right">
                            <div class="text-3xl font-bold bg-gradient-to-r from-green-400 to-emerald-500 bg-clip-text text-transparent mb-2">
                                <?= number_format($customer['total_spent'], 0, ',', '.') ?>₫
                            </div>
                            <div class="text-sm text-gray-400 mb-2">Tổng chi tiêu</div>
                            <div class="text-lg font-semibold text-white">
                                <?= number_format($customer['avg_order'], 0, ',', '.') ?>₫
                                <span class="text-sm text-gray-400 font-normal">/đơn</span>
                            </div>
                        </div>
                    </div>

                    <!-- Progress bar doanh thu -->
                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-400">Mức độ trung thành</span>
                            <span class="text-sm font-medium text-white">
                                <?= min(100, round(($customer['total_spent'] / max($loyalCustomers[0]['total_spent'], 1)) * 100)) ?>%
                            </span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-gradient-to-r <?= $rankInfo['bg'] ?> h-2 rounded-full transition-all duration-1000" 
                                 style="width: <?= min(100, round(($customer['total_spent'] / max($loyalCustomers[0]['total_spent'], 1)) * 100)) ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>