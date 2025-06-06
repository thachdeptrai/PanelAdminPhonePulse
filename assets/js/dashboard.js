document.addEventListener("DOMContentLoaded", function() {
  const ctx = document.getElementById("dashboardChart").getContext("2d");
  new Chart(ctx, {
    type: "line",
    data: {
      labels: ["Tháng 1", "Tháng 2", "Tháng 3", "Tháng 4", "Tháng 5"],
      datasets: [
        {
          label: "Users",
          data: [100, 120, 150, 170, 200],
          borderColor: "#3498db",
          backgroundColor: "rgba(52, 152, 219, 0.2)",
          tension: 0.3
        },
        {
          label: "Orders",
          data: [50, 70, 90, 110, 130],
          borderColor: "#2ecc71",
          backgroundColor: "rgba(46, 204, 113, 0.2)",
          tension: 0.3
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: "top"
        }
      }
    }
  });
});
