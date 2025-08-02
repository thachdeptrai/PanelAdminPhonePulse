<?php
require_once '../includes/config.php';
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

$settings = $mongoDB->settings->findOne([]) ?? ['theme_color' => '#0ea5e9'];

// ✅ Định nghĩa hạng khách hàng
function getRankBySpending($totalSpent) {
    if ($totalSpent >= 25000000) return 'platinum';
    if ($totalSpent >= 15000000) return 'diamond'; 
    if ($totalSpent >= 10000000) return 'gold';
    if ($totalSpent >= 5000000) return 'silver';
    if ($totalSpent >= 2000000) return 'bronze';
    return null; // Không đủ điều kiện VIP
}

function getRankInfo($rank) {
    $ranks = [
        'platinum' => ['icon' => '🏆', 'name' => 'Bạch Kim', 'color' => 'platinum', 'min' => 25000000],
        'diamond' => ['icon' => '💎', 'name' => 'Kim Cương', 'color' => 'diamond', 'min' => 15000000],
        'gold' => ['icon' => '👑', 'name' => 'Vàng', 'color' => 'gold', 'min' => 10000000],
        'silver' => ['icon' => '🥈', 'name' => 'Bạc', 'color' => 'silver', 'min' => 5000000],
        'bronze' => ['icon' => '🥉', 'name' => 'Đồng', 'color' => 'bronze', 'min' => 2000000]
    ];
    return $ranks[$rank] ?? null;
}

// ✅ Lấy danh sách khách hàng VIP
$vipCustomers = [];
$rankStats = ['platinum' => 0, 'diamond' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0];
$rankRevenue = ['platinum' => 0, 'diamond' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0];

try {
    // Pipeline để lấy thống kê khách hàng
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
                'avgOrderValue' => ['$avg' => '$final_price'],
                'firstOrder' => ['$min' => '$created_date'],
                'lastOrder' => ['$max' => '$created_date']
            ]
        ],
        [
            '$match' => [
                'totalSpent' => ['$gte' => 2000000] // Chỉ lấy khách hàng chi tiêu >= 2M
            ]
        ],
        [
            '$sort' => ['totalSpent' => -1]
        ]
    ];
    
    $result = $mongoDB->orders->aggregate($pipeline)->toArray();
    
    foreach ($result as $customerData) {
        try {
            $user = $mongoDB->users->findOne(['_id' => new ObjectId($customerData['_id'])]);
            if ($user) {
                $rank = getRankBySpending($customerData['totalSpent']);
                if ($rank) {
                    $vipCustomers[] = [
                        'id' => (string)$user['_id'],
                        'name' => $user['name'] ?? 'Unknown',
                        'email' => $user['email'] ?? '',
                        'phone' => $user['phone'] ?? 'Chưa cập nhật',
                        'total_spent' => $customerData['totalSpent'],
                        'total_orders' => $customerData['totalOrders'],
                        'avg_order' => $customerData['avgOrderValue'],
                        'join_date' => $customerData['firstOrder']->toDateTime()->format('Y-m-d'),
                        'last_order' => $customerData['lastOrder']->toDateTime()->format('Y-m-d'),
                        'rank' => $rank
                    ];
                    
                    // Thống kê theo hạng
                    $rankStats[$rank]++;
                    $rankRevenue[$rank] += $customerData['totalSpent'];
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
} catch (Exception $e) {
    error_log("VIP customers query error: " . $e->getMessage());
}

// ✅ Thống kê tổng quan
$totalVipCustomers = count($vipCustomers);
$totalVipRevenue = array_sum($rankRevenue);
$avgVipSpending = $totalVipCustomers > 0 ? $totalVipRevenue / $totalVipCustomers : 0;

// ✅ Chuẩn bị dữ liệu cho JavaScript
$vipCustomersJson = json_encode($vipCustomers);
$rankStatsJson = json_encode($rankStats);
$rankRevenueJson = json_encode($rankRevenue);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['meta_title'] ?? 'VIP Customers') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
        }
        
        .glass-card {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.5);
        }
        
        .customer-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(51, 65, 85, 0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .customer-card:hover {
            transform: translateY(-5px);
            border-color: rgba(14, 165, 233, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        
        .customer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .customer-card:hover::before {
            left: 100%;
        }
        
        .rank-badge {
            border-radius: 0 1rem 0 1rem;
        }
        
        .rank-platinum .rank-badge { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .rank-diamond .rank-badge { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .rank-gold .rank-badge { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .rank-silver .rank-badge { background: linear-gradient(135deg, #94a3b8, #64748b); }
        .rank-bronze .rank-badge { background: linear-gradient(135deg, #f97316, #ea580c); }
        
        .stats-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(51, 65, 85, 0.6) 100%);
            border: 1px solid rgba(51, 65, 85, 0.4);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            border-color: rgba(14, 165, 233, 0.6);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .filter-btn {
            background: rgba(51, 65, 85, 0.5);
            border: 1px solid rgba(51, 65, 85, 0.6);
            color: #cbd5e1;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background: rgba(51, 65, 85, 0.8);
            border-color: rgba(14, 165, 233, 0.5);
            color: #f1f5f9;
        }
        
        .filter-active {
            background: linear-gradient(135deg, #0ea5e9, #38bdf8) !important;
            border-color: #0ea5e9 !important;
            color: white !important;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.3), transparent);
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.6), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        .sidebar-item.active {
            background: linear-gradient(135deg, #0ea5e9, #38bdf8);
            color: white;
        }
        
        .content-area {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="content-area ml-64 flex-1 min-h-screen">
        <!-- Top Navigation -->
        <header class="glass-card border-b border-dark-light px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center space-x-4">
                <div class="w-2 h-8 bg-gradient-to-b from-primary to-primary-light rounded-full"></div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-primary to-primary-light bg-clip-text text-transparent">
                    VIP Customers Management
                </h1>
            </div>
            <div class="flex items-center space-x-4">
                <div class="dropdown relative">
                    <button class="flex items-center space-x-3 text-white hover:text-primary transition-colors">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary to-primary-light flex items-center justify-center text-white font-semibold">
                            <?php echo strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="hidden md:inline font-medium"><?php echo htmlspecialchars(explode('@', $user['name'])[0]) ?></span>
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6">
            <!-- Header Section -->
            <div class="glass-card rounded-xl p-8 mb-8">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between">
                    <div class="mb-6 lg:mb-0">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="w-16 h-16 bg-gradient-to-r from-primary to-primary-light rounded-2xl flex items-center justify-center">
                                <span class="text-2xl text-6xl">👑</span>
                            </div>
                            <div>
                                <h1 class="text-4xl font-bold bg-white bg-clip-text text-transparent">
                                    Khách Hàng VIP
                                </h1>
                                <p class="text-slate-400 text-lg mt-1">Quản lý khách hàng thân thiết</p>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="flex items-center space-x-6 text-sm">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                                <span class="text-slate-300">Tổng doanh thu: <span class="text-green-400 font-semibold"><?= number_format($totalVipRevenue, 0, ',', '.') ?>₫</span></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-primary rounded-full"></div>
                                <span class="text-slate-300">TB chi tiêu: <span class="text-primary font-semibold"><?= number_format($avgVipSpending, 0, ',', '.') ?>₫</span></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search & Export -->
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Tìm kiếm khách hàng..." 
                                   class="bg-dark-light/50 border border-dark-light rounded-xl px-4 py-3 pl-12 text-white placeholder-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:bg-dark-light/80 transition-all w-80">
                            <svg class="absolute left-4 top-3.5 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <button onclick="exportToExcel()" class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 px-6 py-3 rounded-xl text-white font-semibold transition-all duration-300 hover:scale-105 flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Xuất Excel</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="stats-card rounded-xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="text-2xl">🏆</span>
                                <p class="text-platinum font-semibold">Bạch Kim</p>
                            </div>
                            <p class="text-3xl font-bold text-white mb-1" id="platinumCount"><?= $rankStats['platinum'] ?></p>
                            <p class="text-slate-400 text-sm">≥ 25M VNĐ</p>
                        </div>
                        <div class="text-right">
                            <p class="text-platinum text-lg font-semibold"><?= number_format($rankRevenue['platinum'], 0, ',', '.') ?>₫</p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card rounded-xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="text-2xl">💎</span>
                                <p class="text-diamond font-semibold">Kim Cương</p>
                            </div>
                            <p class="text-3xl font-bold text-white mb-1" id="diamondCount"><?= $rankStats['diamond'] ?></p>
                            <p class="text-slate-400 text-sm">15M - 25M VNĐ</p>
                        </div>
                        <div class="text-right">
                            <p class="text-diamond text-lg font-semibold"><?= number_format($rankRevenue['diamond'], 0, ',', '.') ?>₫</p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card rounded-xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="text-2xl">👑</span>
                                <p class="text-gold font-semibold">Vàng</p>
                            </div>
                            <p class="text-3xl font-bold text-white mb-1" id="goldCount"><?= $rankStats['gold'] ?></p>
                            <p class="text-slate-400 text-sm">10M - 15M VNĐ</p>
                        </div>
                        <div class="text-right">
                            <p class="text-gold text-lg font-semibold"><?= number_format($rankRevenue['gold'], 0, ',', '.') ?>₫</p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card rounded-xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="text-2xl">🥈</span>
                                <p class="text-silver font-semibold">Bạc</p>
                            </div>
                            <p class="text-3xl font-bold text-white mb-1" id="silverCount"><?= $rankStats['silver'] ?></p>
                            <p class="text-slate-400 text-sm">5M - 10M VNĐ</p>
                        </div>
                        <div class="text-right">
                            <p class="text-silver text-lg font-semibold"><?= number_format($rankRevenue['silver'], 0, ',', '.') ?>₫</p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card rounded-xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="text-2xl">🥉</span>
                                <p class="text-bronze font-semibold">Đồng</p>
                            </div>
                            <p class="text-3xl font-bold text-white mb-1" id="bronzeCount"><?= $rankStats['bronze'] ?></p>
                            <p class="text-slate-400 text-sm">2M - 5M VNĐ</p>
                        </div>
                        <div class="text-right">
                            <p class="text-bronze text-lg font-semibold"><?= number_format($rankRevenue['bronze'], 0, ',', '.') ?>₫</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Revenue by Rank Chart -->
                <div class="glass-card rounded-xl p-6">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-8 h-8 bg-gradient-to-r from-primary to-primary-light rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white">Doanh Thu Theo Hạng</h3>
                    </div>
                    <div class="h-64">
                        <canvas id="rankRevenueChart"></canvas>
                    </div>
                </div>
                
                <!-- Customer Distribution Chart -->
                <div class="glass-card rounded-xl p-6">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-8 h-8 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white">Phân Bố Khách Hàng VIP</h3>
                    </div>
                    <div class="h-64">
                        <canvas id="customerDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="glass-card rounded-xl p-6 mb-8">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 bg-gradient-to-r from-primary to-primary-light rounded-lg flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Bộ lọc:</h3>
                    </div>
                    
                    <div class="flex flex-wrap gap-3">
                        <button class="filter-btn filter-active px-4 py-2 rounded-lg font-medium transition-all" data-rank="all">
                            Tất cả
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg font-medium transition-all" data-rank="platinum">
                            🏆 Bạch Kim
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg font-medium transition-all" data-rank="diamond">
                            💎 Kim Cương
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg font-medium transition-all" data-rank="gold">
                            👑 Vàng
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg font-medium transition-all" data-rank="silver">
                            🥈 Bạc  
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg font-medium transition-all" data-rank="bronze">
                            🥉 Đồng
                        </button>
                    </div>
                    
                    <!-- Sort Options -->
                    <div class="ml-auto flex items-center space-x-3">
                        <select id="sortBy" class="bg-dark-light/50 border border-dark-light rounded-lg px-4 py-2 text-black focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            <option value="spending">Sắp xếp theo chi tiêu</option>
                            <option value="orders">Sắp xếp theo số đơn</option>
                            <option value="name">Sắp xếp theo tên</option>
                            <option value="date">Sắp xếp theo ngày tham gia</option>
                        </select>
                        
                        <button id="sortOrder" class="bg-dark-light/50 border border-dark-light rounded-lg px-3 py-2 text-black hover:bg-dark-light hover:border-primary transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Customer Grid -->
            <div id="customerGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                <!-- Customers will be rendered by JavaScript -->
            </div>

            <!-- Load More Button -->
            <div class="text-center">
                <button id="loadMoreBtn" class="bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary px-8 py-4 rounded-xl text-white font-semibold transition-all duration-300 hover:scale-105 shadow-lg flex items-center space-x-2 mx-auto">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                    <span>Xem thêm khách hàng</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Data from PHP
        const vipCustomers = <?= $vipCustomersJson ?>;
        const rankStats = <?= $rankStatsJson ?>;
        const rankRevenue = <?= $rankRevenueJson ?>;

        // Rank configuration
        const rankConfig = {
            platinum: { icon: "🏆", name: "Bạch Kim", color: "platinum", gradient: "from-platinum to-purple-600" },
            diamond: { icon: "💎", name: "Kim Cương", color: "diamond", gradient: "from-diamond to-blue-600" },
            gold: { icon: "👑", name: "Vàng", color: "gold", gradient: "from-gold to-yellow-500" },
            silver: { icon: "🥈", name: "Bạc", color: "silver", gradient: "from-silver to-slate-500" },
            bronze: { icon: "🥉", name: "Đồng", color: "bronze", gradient: "from-bronze to-orange-600" }
        };

        let currentFilter = 'all';
        let currentSort = 'spending';
        let sortAscending = false;
        let displayedCustomers = 12;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            renderCustomers();
            initCharts();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('filter-active'));
                    this.classList.add('filter-active');
                    currentFilter = this.dataset.rank;
                    displayedCustomers = 12; // Reset pagination
                    renderCustomers();
                });
            });

            // Sort functionality
            document.getElementById('sortBy').addEventListener('change', function() {
                currentSort = this.value;
                renderCustomers();
            });

            document.getElementById('sortOrder').addEventListener('click', function() {
                sortAscending = !sortAscending;
                this.innerHTML = sortAscending ? 
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16m0-16L3 8m4-4l4 4m6 0v12m0 0l-4-4m4 4l4-4"></path></svg>' :
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>';
                renderCustomers();
            });

            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                displayedCustomers = 12; // Reset pagination
                renderCustomers();
            });

            // Load more
            document.getElementById('loadMoreBtn').addEventListener('click', function() {
                displayedCustomers += 12;
                renderCustomers();
            });
        }

        function renderCustomers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            let filteredCustomers = vipCustomers.filter(customer => {
                const matchesSearch = customer.name.toLowerCase().includes(searchTerm) || 
                                    customer.email.toLowerCase().includes(searchTerm) ||
                                    customer.phone.includes(searchTerm);
                const matchesFilter = currentFilter === 'all' || customer.rank === currentFilter;
                return matchesSearch && matchesFilter;
            });

            // Sort customers
            filteredCustomers.sort((a, b) => {
                let aVal, bVal;
                switch(currentSort) {
                    case 'spending':
                        aVal = a.total_spent;
                        bVal = b.total_spent;
                        break;
                    case 'orders':
                        aVal = a.total_orders;
                        bVal = b.total_orders;
                        break;
                    case 'name':
                        aVal = a.name;
                        bVal = b.name;
                        break;
                    case 'date':
                        aVal = new Date(a.join_date);
                        bVal = new Date(b.join_date);
                        break;
                }
                
                if (typeof aVal === 'string') {
                    return sortAscending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                }
                return sortAscending ? aVal - bVal : bVal - aVal;
            });

            const customersToShow = filteredCustomers.slice(0, displayedCustomers);
            const grid = document.getElementById('customerGrid');
            
            grid.innerHTML = customersToShow.map(customer => createCustomerCard(customer)).join('');

            // Show/hide load more button
            document.getElementById('loadMoreBtn').style.display = 
                filteredCustomers.length > displayedCustomers ? 'block' : 'none';
        }

        function createCustomerCard(customer) {
            const rank = rankConfig[customer.rank];
            const loyaltyPercentage = Math.min(100, (customer.total_spent / 30000000) * 100);
            
            return `
                <div class="customer-card rank-${customer.rank} rounded-xl p-6 animate-fade-in-up hover:cursor-pointer" onclick="showCustomerDetails('${customer.id}')">
                    <!-- Rank Badge -->
                    <div class="rank-badge absolute top-0 right-0 px-3 py-2 text-white font-bold text-sm flex items-center space-x-1">
                        <span>${rank.icon}</span>
                        <span>${rank.name}</span>
                    </div>

                    <!-- Customer Info -->
                    <div class="mt-6">
                        <!-- Avatar & Basic Info -->
                        <div class="flex items-start space-x-4 mb-6">
                            <div class="w-14 h-14 bg-gradient-to-r ${rank.gradient} rounded-xl flex items-center justify-center text-white text-xl font-bold shadow-lg flex-shrink-0">
                                ${customer.name.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-white mb-1 truncate">${customer.name}</h3>
                                <p class="text-slate-300 text-sm mb-1 truncate">${customer.email}</p>
                                <p class="text-slate-400 text-sm">${customer.phone}</p>
                            </div>
                        </div>

                        <!-- Total Spent - Prominent Display -->
                        <div class="bg-gradient-to-r from-green-500/10 to-emerald-500/10 rounded-lg p-4 border border-green-500/20 mb-4">
                            <div class="text-center">
                                <p class="text-slate-400 text-sm mb-1">Tổng chi tiêu</p>
                                <p class="text-green-400 font-bold text-2xl">${customer.total_spent.toLocaleString('vi-VN')}₫</p>
                            </div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="bg-primary/10 rounded-lg p-3 border border-primary/20 text-center">
                                <p class="text-primary font-bold text-lg">${customer.total_orders}</p>
                                <p class="text-slate-400 text-xs">Đơn hàng</p>
                            </div>
                            <div class="bg-purple-500/10 rounded-lg p-3 border border-purple-500/20 text-center">
                                <p class="text-purple-400 font-bold text-lg">${Math.round(customer.avg_order).toLocaleString('vi-VN')}₫</p>
                                <p class="text-slate-400 text-xs">TB/Đơn</p>
                            </div>
                        </div>

                        <!-- Loyalty Progress -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-slate-400 text-sm">Mức độ trung thành</span>
                                <span class="text-white text-sm font-medium">${Math.round(loyaltyPercentage)}%</span>
                            </div>
                            <div class="w-full bg-slate-700/50 rounded-full h-2 overflow-hidden">
                                <div class="bg-gradient-to-r ${rank.gradient} h-2 rounded-full transition-all duration-1000 progress-bar" 
                                     style="width: ${loyaltyPercentage}%"></div>
                            </div>
                        </div>

                        <!-- Last Activity -->
                        <div class="pt-3 border-t border-slate-700/50">
                            <div class="flex items-center justify-between">
                                <div class="text-center flex-1">
                                    <p class="text-slate-400 text-xs">Tham gia</p>
                                    <p class="text-white text-sm font-medium">${formatDate(customer.join_date)}</p>
                                </div>
                                <div class="w-px h-8 bg-slate-700/50 mx-3"></div>
                                <div class="text-center flex-1">
                                    <p class="text-slate-400 text-xs">Đơn cuối</p>
                                    <p class="text-white text-sm font-medium">${formatDate(customer.last_order)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hover Effect Indicator -->
                    <div class="absolute bottom-4 right-4 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                </div>
            `;
        }

        function showCustomerDetails(customerId) {
            // Find customer
            const customer = vipCustomers.find(c => c.id === customerId);
            if (!customer) return;
            
            // Show modal
            showCustomerModal(customer);
        }

        function showCustomerModal(customer) {
            const rank = rankConfig[customer.rank];
            const loyaltyPercentage = Math.min(100, (customer.total_spent / 30000000) * 100);
            
            // Create modal HTML
            const modalHTML = `
                <div id="customerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                    <div class="glass-card rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-fade-in-up">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between p-6 border-b border-slate-700/50">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-gradient-to-r ${rank.gradient} rounded-2xl flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                                    ${customer.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white">${customer.name}</h2>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="text-${rank.color} text-lg">${rank.icon}</span>
                                        <span class="text-${rank.color} font-semibold">${rank.name}</span>
                                    </div>
                                </div>
                            </div>
                            <button onclick="closeCustomerModal()" class="text-slate-400 hover:text-white transition-colors p-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-6 space-y-6">
                            <!-- Contact Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <h3 class="text-lg font-semibold text-white mb-3">📞 Thông tin liên hệ</h3>
                                    <div class="space-y-3">
                                        <div class="flex items-center space-x-3 p-3 bg-slate-800/50 rounded-lg">
                                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-slate-400 text-sm">Email</p>
                                                <p class="text-white font-medium">${customer.email}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-3 p-3 bg-slate-800/50 rounded-lg">
                                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-slate-400 text-sm">Điện thoại</p>
                                                <p class="text-white font-medium">${customer.phone}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <h3 class="text-lg font-semibold text-white mb-3">📅 Thông tin thời gian</h3>
                                    <div class="space-y-3">
                                        <div class="flex items-center space-x-3 p-3 bg-slate-800/50 rounded-lg">
                                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-slate-400 text-sm">Ngày tham gia</p>
                                                <p class="text-white font-medium">${formatDate(customer.join_date)}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-3 p-3 bg-slate-800/50 rounded-lg">
                                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-slate-400 text-sm">Đơn hàng gần nhất</p>
                                                <p class="text-white font-medium">${formatDate(customer.last_order)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics -->
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-4">📊 Thống kê chi tiêu</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="bg-gradient-to-r from-green-500/10 to-emerald-500/10 border border-green-500/20 rounded-xl p-4 text-center">
                                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                        </div>
                                        <p class="text-slate-400 text-sm mb-1">Tổng chi tiêu</p>
                                        <p class="text-green-400 font-bold text-xl">${customer.total_spent.toLocaleString('vi-VN')}₫</p>
                                    </div>

                                    <div class="bg-gradient-to-r from-primary/10 to-blue-500/10 border border-primary/20 rounded-xl p-4 text-center">
                                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-blue-500 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                            </svg>
                                        </div>
                                        <p class="text-slate-400 text-sm mb-1">Số đơn hàng</p>
                                        <p class="text-primary font-bold text-xl">${customer.total_orders}</p>
                                    </div>

                                    <div class="bg-gradient-to-r from-purple-500/10 to-pink-500/10 border border-purple-500/20 rounded-xl p-4 text-center">
                                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                        </div>
                                        <p class="text-slate-400 text-sm mb-1">Trung bình/đơn</p>
                                        <p class="text-purple-400 font-bold text-xl">${Math.round(customer.avg_order).toLocaleString('vi-VN')}₫</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Loyalty Progress -->
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-4">⭐ Mức độ trung thành</h3>
                                <div class="bg-slate-800/50 rounded-xl p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="text-slate-300">Tiến độ lên hạng</span>
                                        <span class="text-white font-bold text-lg">${Math.round(loyaltyPercentage)}%</span>
                                    </div>
                                    <div class="w-full bg-slate-700 rounded-full h-4 overflow-hidden mb-4">
                                        <div class="bg-gradient-to-r ${rank.gradient} h-4 rounded-full transition-all duration-1000 relative overflow-hidden" 
                                             style="width: ${loyaltyPercentage}%">
                                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse"></div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-slate-400">Hạng hiện tại: <span class="text-${rank.color} font-semibold">${rank.name}</span></span>
                                        <span class="text-slate-400">Mục tiêu: 30M VNĐ</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-slate-700/50">
                                <button onclick="closeCustomerModal()" class="flex-1 bg-slate-700 hover:bg-slate-600 px-6 py-3 rounded-xl text-white font-semibold transition-all duration-300 flex items-center justify-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span>Đóng</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeCustomerModal() {
            const modal = document.getElementById('customerModal');
            if (modal) {
                modal.style.opacity = '0';
                modal.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    modal.remove();
                    document.body.style.overflow = 'auto';
                }, 200);
            }
        }
            // Close modal when clicking outside
            document.addEventListener('click', function(e) {
            if (e.target.id === 'customerModal') {
                closeCustomerModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCustomerModal();
            }
        });
        function initCharts() {
            // Rank Revenue Chart
            const rankCtx = document.getElementById('rankRevenueChart').getContext('2d');
            new Chart(rankCtx, {
                type: 'doughnut',
                data: {
                    labels: ['🏆 Bạch Kim', '💎 Kim Cương', '👑 Vàng', '🥈 Bạc', '🥉 Đồng'],
                    datasets: [{
                        data: [
                            rankRevenue.platinum,
                            rankRevenue.diamond,
                            rankRevenue.gold,
                            rankRevenue.silver,
                            rankRevenue.bronze
                        ],
                        backgroundColor: [
                            '#8b5cf6',
                            '#3b82f6', 
                            '#fbbf24',
                            '#94a3b8',
                            '#f97316'
                        ],
                        borderWidth: 0,
                        hoverBorderWidth: 3,
                        hoverBorderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { 
                                color: '#cbd5e1', 
                                padding: 20,
                                font: { size: 12 },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            titleColor: '#f1f5f9',
                            bodyColor: '#cbd5e1',
                            borderColor: '#0ea5e9',
                            borderWidth: 1,
                            callbacks: {
                                label: function(ctx) {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((ctx.parsed / total) * 100).toFixed(1);
                                    return ctx.label + ': ' + ctx.parsed.toLocaleString('vi-VN') + '₫ (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Customer Distribution Chart
            const distributionCtx = document.getElementById('customerDistributionChart').getContext('2d');
            new Chart(distributionCtx, {
                type: 'bar',
                data: {
                    labels: ['🏆 Bạch Kim', '💎 Kim Cương', '👑 Vàng', '🥈 Bạc', '🥉 Đồng'],
                    datasets: [{
                        label: 'Số lượng khách hàng',
                        data: [
                            rankStats.platinum,
                            rankStats.diamond,
                            rankStats.gold,
                            rankStats.silver,
                            rankStats.bronze
                        ],
                        backgroundColor: [
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(59, 130, 246, 0.8)', 
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(148, 163, 184, 0.8)',
                            'rgba(249, 115, 22, 0.8)'
                        ],
                        borderColor: [
                            '#8b5cf6',
                            '#3b82f6', 
                            '#fbbf24',
                            '#94a3b8',
                            '#f97316'
                        ],
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            titleColor: '#f1f5f9',
                            bodyColor: '#cbd5e1',
                            borderColor: '#0ea5e9',
                            borderWidth: 1,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { 
                                color: '#cbd5e1',
                                stepSize: 1,
                                font: { size: 11 }
                            },
                            grid: { 
                                color: 'rgba(203, 213, 225, 0.1)',
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: { 
                                color: '#cbd5e1',
                                font: { size: 11 }
                            },
                            grid: { 
                                display: false 
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        function exportToExcel() {
            // Create CSV content
            const headers = ['Tên', 'Email', 'Điện thoại', 'Hạng', 'Tổng chi tiêu', 'Số đơn hàng', 'Trung bình/đơn', 'Ngày tham gia', 'Đơn hàng cuối'];
            const csvContent = [
            headers.join(','),
            ...vipCustomers.map(customer => [
                `"${customer.name}"`,
                `"${customer.email}"`,
                `="` +"0"+customer.phone + `"`, // <<-- cưỡng bức text
                `"${rankConfig[customer.rank].name}"`,
                customer.total_spent,
                customer.total_orders,
                Math.round(customer.avg_order),
                customer.join_date,
                customer.last_order
            ].join(','))
            ].join('\n');

            // Create and download file
            const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `vip-customers-${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Add smooth scroll animations
        function addScrollAnimations() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            }, {
                threshold: 0.1
            });

            document.querySelectorAll('.customer-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                observer.observe(card);
            });
        }

        // Call animation function after rendering
        setTimeout(addScrollAnimations, 100);

        // Add active state to current page in sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            
            sidebarItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && (href === currentPage || href.includes('vip') || href.includes('customer'))) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>