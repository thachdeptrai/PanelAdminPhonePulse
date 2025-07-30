<?php
  use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
require_once '../includes/config.php';

// Aggregate từ collection orders để lấy tổng đơn và tổng tiền theo user
$pipeline = [
  ['$match' => ['shipping_status' => 'shipped']],
  ['$group' => [
    '_id' => '$userId',
    'total_orders' => ['$sum' => 1],
    'total_spent' => ['$sum' => '$final_price']
  ]],
  ['$sort' => ['total_spent' => -1]],
  ['$limit' => 3]
];

$cursor = $mongoDB->orders->aggregate($pipeline);
$users = [];

foreach ($cursor as $doc) {
  $userId = $doc['_id'] ?? null;

  // Chỉ lấy nếu user_id là ObjectId hợp lệ
  if (!$userId || !($userId instanceof ObjectId)) continue;

  $user = $mongoDB->users->findOne(['_id' => $userId]);
  if (!$user) continue;

  $users[] = [
    'mongo_id'     => (string) $user['_id'],
    'name'         => $user['name'] ?? 'Không tên',
    'email'        => $user['email'] ?? '',
    'avatar_url'   => $user['avatar_url'] ?? 'assets/avatar-default.png',
    'phone'        => $user['phone'] ?? '',
    'address'      => $user['address'] ?? '',
    'total_orders' => $doc['total_orders'],
    'total_spent'  => $doc['total_spent'],
  ];
}

// Hàm tính cấp độ thân thiết
function getLoyaltyLevel($totalSpent) {
  if ($totalSpent >= 20000000) return '💎 Diamond';
  if ($totalSpent >= 10000000) return '🔶 Platinum';
  if ($totalSpent >= 5000000)  return '🥇 Gold';
  if ($totalSpent >= 2000000)  return '🥈 Silver';
  return '👤 Member';
}
?>
<div class="mt-10">
  <h2 class="text-3xl font-extrabold text-white mb-6 tracking-tight">🏆 Khách Hàng Thân Thiết</h2>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <?php foreach ($users as $user): ?>
      <?php $level = getLoyaltyLevel($user['total_spent']); ?>
      <div class="relative group bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl shadow-2xl p-5 transform transition duration-500 hover:scale-[1.03] hover:shadow-[0_20px_50px_rgba(0,0,0,0.6)] overflow-hidden">
        <!-- Glow background -->
        <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-transparent via-white/5 to-white/10 opacity-0 group-hover:opacity-100 transition duration-700 blur-2xl"></div>

        <!-- Header -->
        <div class="flex items-center gap-4 relative z-10">
          <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/avatar-default.png'); ?>"
               class="w-16 h-16 rounded-full border-2 border-white shadow-lg object-cover" alt="Avatar">
          <div>
            <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($user['name']) ?></h3>
            <p class="text-sm text-slate-300"><?= htmlspecialchars($user['email']) ?></p>
            <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($user['phone']) ?></p>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 mt-6 text-center relative z-10">
          <div>
            <p class="text-xl font-bold text-white"><?= number_format($user['total_orders']) ?></p>
            <p class="text-xs text-slate-400">Đơn hàng</p>
          </div>
          <div>
            <p class="text-xl font-bold text-green-300"><?= number_format($user['total_spent']) ?>₫</p>
            <p class="text-xs text-slate-400">Đã chi</p>
          </div>
        </div>

        <!-- Rank Badge -->
        <div class="mt-6 text-center relative z-10">
          <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold shadow-md 
            <?php
              switch ($level) {
                case '💎 Diamond': echo 'bg-gradient-to-r from-cyan-400 to-blue-600 text-white'; break;
                case '🔶 Platinum': echo 'bg-gradient-to-r from-gray-300 to-gray-500 text-black'; break;
                case '🥇 Gold': echo 'bg-gradient-to-r from-yellow-400 to-orange-500 text-white'; break;
                case '🥈 Silver': echo 'bg-gradient-to-r from-gray-300 to-gray-400 text-black'; break;
                default: echo 'bg-gray-700 text-white';
              }
            ?>">
            <?= $level ?>
          </span>
          <p class="text-xs text-slate-400 mt-1">Cấp độ thân thiết</p>
        </div>

        <!-- Address -->
        <div class="mt-4 text-center text-xs text-slate-300 relative z-10">
          <span class="block truncate">📍 <?= htmlspecialchars($user['address']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<br>
<br>