
 <!-- Hàng biểu đồ -->
 <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
  <!-- Biểu đồ doanh thu -->
  <div class="bg-[#1e293b] p-6 rounded-xl lg:col-span-2 shadow-xl border border-gray-700">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
      <h2 class="text-lg font-semibold text-white flex items-center gap-2">
        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M3 3v18h18M21 3L9 15l-4.5-4.5" />
        </svg>
        Tổng Quan Doanh Thu
      </h2>

      <!-- Bộ lọc -->
      <div class="flex items-center gap-3">
        <!-- Icon lịch -->
        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z" />
        </svg>

        <!-- Dropdown -->
        <select id="rangeSelect" class="bg-[#334155] text-white text-sm px-3 py-2 rounded-lg border border-gray-600 focus:outline-none focus:ring focus:ring-blue-500">
          <option value="7days">7 ngày qua</option>
          <option value="month" selected>Tháng này</option>
          <option value="3months">3 tháng gần nhất</option>
          <option value="year">Năm nay</option>
          <option value="custom">Tùy chọn ngày</option>
        </select>

        <!-- Date picker -->
        <div id="customDateRange" class="hidden items-center gap-2">
          <input type="date" id="startDate" class="bg-[#1e293b] text-white text-sm px-3 py-1.5 rounded border border-gray-600 focus:outline-none">
          <span class="text-white">–</span>
          <input type="date" id="endDate" class="bg-[#1e293b] text-white text-sm px-3 py-1.5 rounded border border-gray-600 focus:outline-none">
        </div>
      </div>
    </div>

    <div class="h-80">
      <canvas id="revenueChart"></canvas>
    </div>
  </div>

 <!-- UI hiển thị Doanh Số Theo Danh Mục - Nâng cấp UI đẹp hơn + animation -->
<div class="bg-gradient-to-br from-slate-800 via-slate-900 to-black p-6 rounded-2xl shadow-2xl border border-slate-700 animate-fade-in-up">
  <div class="flex justify-between items-center mb-4">
    <h2 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-3 animate-pulse">
      <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18" />
      </svg>
      Doanh Số Theo Danh Mục
    </h2>
  </div>
  <div class="h-80 relative group overflow-hidden rounded-xl bg-slate-800">
    <canvas id="categoryChart" class="w-full h-full"></canvas>
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-slate-900 to-black opacity-20 pointer-events-none transition-opacity group-hover:opacity-30"></div>
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
@keyframes fade-in {
  0% { opacity: 0; }
  100% { opacity: 1; }
}
.animate-fade-in {
  animation: fade-in 1s ease-in both;
}
.animate-pulse {
  animation: pulse 2s infinite;
}
</style>
</div>
