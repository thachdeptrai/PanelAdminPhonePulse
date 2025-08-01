<div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6 text-sm bg-gray-800 text-gray-300 px-4 py-3 rounded-lg shadow">
    <div class="flex items-center gap-2">
        <span class="text-green-400 text-lg">📞</span>
        <span><?= $settings['hotline'] ?? 'Chưa có' ?></span>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-blue-400 text-lg">📧</span>
        <span><?= $settings['email'] ?? 'Chưa có' ?></span>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-yellow-400 text-lg">📍</span>
        <span><?= $settings['address'] ?? 'Chưa cập nhật' ?></span>
    </div>
</div>
