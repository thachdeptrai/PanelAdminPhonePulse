<?php
http_response_code(404); // Đảm bảo trả mã 404 cho trình duyệt
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>404 - Không tìm thấy trang</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to right, #6a11cb, #2575fc);
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: white;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
    }
    .error-container {
      text-align: center;
      animation: fadeIn 1s ease-in-out;
    }
    .error-code {
      font-size: 100px;
      font-weight: bold;
    }
    .error-msg {
      font-size: 24px;
      margin-bottom: 30px;
    }
    .btn-back {
      background-color: #ffffff;
      color: #2575fc;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: bold;
      transition: all 0.3s ease;
      text-decoration: none;
    }
    .btn-back:hover {
      background-color: #e0e0e0;
      text-decoration: none;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .icon {
      font-size: 50px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <div class="error-container">
    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="error-code">404</div>
    <div class="error-msg">Ôi không! Trang bạn tìm không tồn tại 🤔</div>
    <a href="dang_nhap" class="btn-back">
      <i class="fas fa-home"></i> Về trang chủ
    </a>
  </div>
</body>
</html>
