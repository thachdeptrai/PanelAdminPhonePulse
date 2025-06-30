<?php
    require_once '../includes/config.php';
    require_once '../includes/functions.php';

    $pageTitle   = 'Quản lý Users';
    $message     = '';
    $messageType = '';

    // Xử lý xóa user
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id   = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE mongo_id = ?");
        $stmt->execute([$id]);
        $rowCount = $stmt->rowCount();

        if ($rowCount > 0) {
            $message     = 'Xóa user thành công!';
            $messageType = 'success';
        } else {
            $message     = 'User không tồn tại!';
            $messageType = 'danger';
        }
    }

    // Xử lý thêm user
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        $errors = [];
    
        if (empty($_POST['name'])) $errors[] = 'Tên không được trống';
    
        if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ';
        }
    
        $password = $_POST['password'] ?? '';
        if (
            empty($password) || 
            !(
                strlen($password) >= 6 &&
                preg_match('/[A-Z]/', $password) &&
                preg_match('/[0-9]/', $password) &&
                preg_match('/[\W_]/', $password)
            )
        ) {
            $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự, 1 ký tự hoa, 1 số và 1 ký tự đặc biệt';
        }
    
        if (empty($_POST['phone']) || !preg_match('/^0\d{9}$/', $_POST['phone'])) {
            $errors[] = 'Số điện thoại không hợp lệ (phải có 10 chữ số và bắt đầu bằng 0)';
        }
    
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, status, created_date)
                                VALUES (?, ?, ?, ?, ?, NOW())");
            $success = $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                $_POST['phone'] ?? '',
                password_hash($password, PASSWORD_DEFAULT),
                $_POST['status'],
            ]);
            
            if ($success) {
                $message     = 'Thêm user thành công!';
                $messageType = 'success';
            } else {
                $message     = 'Lỗi khi thêm user!';
                $messageType = 'danger';
            }
        } else {
            $message     = implode('<br>', $errors);
            $messageType = 'danger';
        }
    }
    

    // Xử lý cập nhật user
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
        $errors = [];
        if (empty($_POST['name'])) {
            $errors[] = 'Tên không được trống';
        }

        if (empty($_POST['email']) || ! filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ';
        }

        if (empty($errors)) {
            $params = [
                $_POST['name'],
                $_POST['email'],
                $_POST['phone'] ?? '',
                $_POST['status'],
                $_POST['userid'],
            ];

            $passwordUpdate = '';
            if (! empty($_POST['password'])) {
                if (strlen($_POST['password']) >= 6) {
                    $passwordUpdate = ', password = ?';
                    array_splice($params, 4, 0, [password_hash($_POST['password'], PASSWORD_DEFAULT)]);
                } else {
                    $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
                }
            }

            if (empty($errors)) {
                $sql = "UPDATE users SET
                        name = ?,
                        email = ?,
                        phone = ?,
                        status = ?
                        $passwordUpdate
                        WHERE mongo_id = ?";

                $stmt    = $pdo->prepare($sql);
                $success = $stmt->execute($params);

                if ($success) {
                    $message     = 'Cập nhật user thành công!';
                    $messageType = 'success';
                } else {
                    $message     = 'Lỗi khi cập nhật user!';
                    $messageType = 'danger';
                }
            }
        }

        if (! empty($errors)) {
            $message     = implode('<br>', $errors);
            $messageType = 'danger';
        }
    }

    // View user
    $viewUser = null;
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'view') {
        $id   = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mongo_id = ?");
        $stmt->execute([$id]);
        $viewUser = $stmt->fetch();

        if (! $viewUser) {
            $message     = 'User không tồn tại!';
            $messageType = 'danger';
        }
    }

    // Edit user
    $editUser = null;
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
        $id   = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mongo_id = ?");
        $stmt->execute([$id]);
        $editUser = $stmt->fetch();

        if (! $editUser) {
            $message     = 'User không tồn tại!';
            $messageType = 'danger';
        }
    }

    // Xử lý tìm kiếm
    $searchTerm      = isset($_GET['search']) ? trim($_GET['search']) : '';
    $searchCondition = '';
    $searchParams    = [];

    if (! empty($searchTerm)) {
        $searchCondition = "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?";
        $searchParams    = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
    }

    // Lấy danh sách users phân trang
    $page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit  = 10;
    $offset = ($page - 1) * $limit;

    $sql  = "SELECT SQL_CALC_FOUND_ROWS * FROM users $searchCondition ORDER BY created_date DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($sql);

    foreach ($searchParams as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    $totalUsers = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quản lý Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      color: #fff;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .main-container {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      margin: 20px auto;
      padding: 30px;
    }

    .card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }

    .card-header {
      background: rgba(255, 255, 255, 0.1);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 15px 15px 0 0 !important;
    }

    .table {
      color: #fff;
      background: transparent;
    }

    .table th {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.2);
      color: #fff;
      font-weight: 600;
    }

    .table td {
      border-color: rgba(255, 255, 255, 0.1);
      vertical-align: middle;
    }

    .table tbody tr:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .btn {
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .btn-primary {
      background: linear-gradient(45deg, #667eea, #764ba2);
      border: none;
    }

    .btn-outline-primary, .btn-outline-warning, .btn-outline-danger {
      border-width: 2px;
      color: #fff;
    }

    .btn-outline-primary {
      border-color: #17a2b8;
    }
    .btn-outline-primary:hover {
      background: #17a2b8;
      border-color: #17a2b8;
    }

    .btn-outline-warning {
      border-color: #ffc107;
    }
    .btn-outline-warning:hover {
      background: #ffc107;
      border-color: #ffc107;
      color: #000;
    }

    .btn-outline-danger {
      border-color: #dc3545;
    }
    .btn-outline-danger:hover {
      background: #dc3545;
      border-color: #dc3545;
    }

    .modal-content {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 15px;
      color: #fff;
    }

    .modal-header {
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .modal-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .form-control, .form-select {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 10px;
      color: #fff;
    }

    .form-control:focus, .form-select:focus {
      background: rgba(255, 255, 255, 0.15);
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
      color: #fff;
    }

    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.7);
    }

    .btn-back {
      position: fixed;
      top: 20px;
      left: 20px;
      background: linear-gradient(45deg, #28a745, #20c997);
      color: #fff;
      padding: 12px 20px;
      border: none;
      border-radius: 25px;
      z-index: 1000;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .btn-back:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
      color: #fff;
    }

    .badge {
      padding: 8px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
    }

    .alert {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 15px;
      color: #fff;
    }

    .page-link {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
      border-radius: 10px;
      margin: 0 2px;
    }

    .page-link:hover {
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
    }

    .page-item.active .page-link {
      background: linear-gradient(45deg, #667eea, #764ba2);
      border-color: #667eea;
    }

    .btn-close {
      filter: invert(1);
    }

    h1, h5 {
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }
  </style>
</head>
<body>
  <a href="trang_chu" class="btn-back">
    <i class="fas fa-arrow-left me-2"></i>Trang chủ
  </a>

  <div class="container py-5">
    <div class="main-container">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
          <i class="fas fa-users me-3"></i>Quản lý Users
        </h1>
        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addUserModal">
          <i class="fas fa-plus me-2"></i>Thêm User
        </button>
      </div>

      <!-- Alerts -->
      <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>

      <!-- Users Table -->
      <div class="card">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col-md-6">
              <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Danh sách Users
                <span class="badge bg-primary"><?php echo number_format($totalUsers); ?></span>
              </h5>
            </div>
            <div class="col-md-6">
              <form class="d-flex" method="GET">
                <input type="text" class="form-control me-2" placeholder="Tìm kiếm tên, email, phone..."
                       name="search" value="<?php echo e($searchTerm); ?>">
                <button class="btn btn-outline-light" type="submit">
                  <i class="fas fa-search"></i>
                </button>
                <?php if ($searchTerm): ?>
                <a href="?" class="btn btn-outline-secondary ms-2">
                  <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>

        <div class="card-body p-0">
          <?php if (empty($users)): ?>
          <div class="text-center py-5">
            <i class="fas fa-user-slash fa-3x mb-3 opacity-50"></i>
            <h5>Không có dữ liệu</h5>
            <p class="text-muted">
              <?php echo $searchTerm ? 'Không tìm thấy user nào với từ khóa "' . e($searchTerm) . '"' : 'Chưa có user nào trong hệ thống'; ?>
            </p>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="text-center">
                <tr>
                  <th><i class="fas fa-id-card me-1"></i>ID</th>
                  <th><i class="fas fa-user me-1"></i>Tên</th>
                  <th><i class="fas fa-envelope me-1"></i>Email</th>
                  <th><i class="fas fa-phone me-1"></i>Phone</th>
                  <th><i class="fas fa-toggle-on me-1"></i>Trạng thái</th>
                  <th><i class="fas fa-calendar me-1"></i>Ngày tạo</th>
                  <th><i class="fas fa-cogs me-1"></i>Thao tác</th>
                </tr>
              </thead>
              <tbody class="text-center">
                <?php foreach ($users as $user): ?>
                <tr>
                  <td><strong><?php echo e($user['mongo_id']); ?></strong></td>
                  <td><?php echo e($user['name']); ?></td>
                  <td><?php echo e($user['email']); ?></td>
                  <td><?php echo e($user['phone'] ?: '-'); ?></td>
                  <td>
                    <?php if ($user['status']): ?>
                      <span class="badge bg-success">
                        <i class="fas fa-check me-1"></i>Hoạt động
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary">
                        <i class="fas fa-pause me-1"></i>Tạm dừng
                      </span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo date('d/m/Y', strtotime($user['created_date'])); ?></td>
                  <td>
                    <div class="btn-group" role="group">
                      <a href="?action=view&id=<?php echo $user['mongo_id']; ?>"
                         class="btn btn-sm btn-outline-primary"
                         title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="?action=edit&id=<?php echo $user['mongo_id']; ?>"
                         class="btn btn-sm btn-outline-warning"
                         title="Chỉnh sửa">
                        <i class="fas fa-edit"></i>
                      </a>
                      <button type="button"
                              class="btn btn-sm btn-outline-danger"
                              title="Xóa"
                              onclick="confirmDelete(<?php echo $user['mongo_id']; ?>, '<?php echo addslashes($user['name']); ?>')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
          <nav aria-label="Phân trang">
            <ul class="pagination justify-content-center mb-0">
              <!-- Previous -->
              <li class="page-item                                   <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>"
                   aria-label="Trang trước">
                  <i class="fas fa-chevron-left"></i>
                </a>
              </li>

              <!-- Page numbers -->
              <?php
                  $startPage = max(1, $page - 2);
                  $endPage   = min($totalPages, $page + 2);

              if ($startPage > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?page=1<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
<?php endif; ?>

              <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
              <li class="page-item<?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>">
                  <?php echo $i; ?>
                </a>
              </li>
              <?php endfor; ?>

              <?php if ($endPage < $totalPages): ?>
<?php if ($endPage < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>">
                    <?php echo $totalPages; ?>
                  </a>
                </li>
              <?php endif; ?>

              <!-- Next -->
              <li class="page-item                                   <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>"
                   aria-label="Trang sau">
                  <i class="fas fa-chevron-right"></i>
                </a>
              </li>
            </ul>
          </nav>

          <div class="text-center mt-3">
            <small class="text-muted">
              Hiển thị                           <?php echo($page - 1) * $limit + 1; ?> -<?php echo min($page * $limit, $totalUsers); ?>
              trong tổng số<?php echo number_format($totalUsers); ?> user
            </small>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Add User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-user-plus me-2"></i>Thêm User Mới
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="addUserForm">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-user me-1"></i>Tên đầy đủ *
                </label>
                <input type="text" class="form-control" name="name" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-envelope me-1"></i>Email *
                </label>
                <input type="email" class="form-control" name="email" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-phone me-1"></i>Số điện thoại
                </label>
                <input type="text" class="form-control" name="phone">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-toggle-on me-1"></i>Trạng thái *
                </label>
                <select class="form-select" name="status" required>
                  <option value="1">Hoạt động</option>
                  <option value="0">Tạm dừng</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">
                <i class="fas fa-lock me-1"></i>Mật khẩu *
              </label>
              <input type="password" class="form-control" name="password" required minlength="6"
                     placeholder="Tối thiểu 6 ký tự">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>Hủy
            </button>
            <button type="submit" name="add_user" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Thêm User
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <?php if ($editUser): ?>
  <div class="modal fade show" id="editUserModal" tabindex="-1" style="display: block;" aria-modal="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-user-edit me-2"></i>Chỉnh sửa User
          </h5>
          <a href="?" class="btn-close"></a>
        </div>
        <form method="POST" id="editUserForm">
          <input type="hidden" name="userid" value="<?php echo e($editUser['mongo_id']); ?>">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-user me-1"></i>Tên đầy đủ *
                </label>
                <input type="text" class="form-control" name="name" value="<?php echo e($editUser['name']); ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-envelope me-1"></i>Email *
                </label>
                <input type="email" class="form-control" name="email" value="<?php echo e($editUser['email']); ?>" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-phone me-1"></i>Số điện thoại
                </label>
                <input type="text" class="form-control" name="phone" value="<?php echo e($editUser['phone']); ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="fas fa-toggle-on me-1"></i>Trạng thái *
                </label>
                <select class="form-select" name="status" required>
                  <option value="1"                                    <?php echo $editUser['status'] ? 'selected' : ''; ?>>Hoạt động</option>
                  <option value="0"                                    <?php echo ! $editUser['status'] ? 'selected' : ''; ?>>Tạm dừng</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">
                <i class="fas fa-lock me-1"></i>Mật khẩu mới
              </label>
              <input type="password" class="form-control" name="password"
                     placeholder="Để trống nếu không muốn đổi mật khẩu">
              <div class="form-text text-light">
                <i class="fas fa-info-circle me-1"></i>Chỉ nhập mật khẩu mới nếu muốn thay đổi
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <a href="?" class="btn btn-secondary">
              <i class="fas fa-times me-1"></i>Hủy
            </a>
            <button type="submit" name="update_user" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Cập nhật User
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="modal-backdrop fade show"></div>
  <?php endif; ?>

  <!-- View User Modal -->
  <?php if ($viewUser): ?>
  <div class="modal fade show" id="viewUserModal" tabindex="-1" style="display: block;" aria-modal="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-user me-2"></i>Chi tiết User
          </h5>
          <a href="?" class="btn-close"></a>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-4">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="card-title">
                    <i class="fas fa-id-card me-2 text-primary"></i>Thông tin cơ bản
                  </h6>
                  <hr>
                  <p><strong>ID:</strong>                                          <?php echo e($viewUser['mongo_id']); ?></p>
                  <p><strong>Tên:</strong>                                            <?php echo e($viewUser['name']); ?></p>
                  <p><strong>Email:</strong>                                             <?php echo e($viewUser['email']); ?></p>
                  <p><strong>Phone:</strong>                                             <?php echo e($viewUser['phone'] ?: 'Chưa có'); ?></p>
                </div>
              </div>
            </div>
            <div class="col-md-6 mb-4">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="card-title">
                    <i class="fas fa-info-circle me-2 text-info"></i>Thông tin khác
                  </h6>
                  <hr>
                  <p>
                    <strong>Trạng thái:</strong>
                    <?php if ($viewUser['status']): ?>
                      <span class="badge bg-success">
                        <i class="fas fa-check me-1"></i>Hoạt động
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary">
                        <i class="fas fa-pause me-1"></i>Tạm dừng
                      </span>
                    <?php endif; ?>
                  </p>
                  <p><strong>Ngày tạo:</strong>                                                   <?php echo date('d/m/Y H:i:s', strtotime($viewUser['created_date'])); ?></p>
                  <p><strong>Ngày cập nhật:</strong>                                                          <?php echo $viewUser['updated_date'] ? date('d/m/Y H:i:s', strtotime($viewUser['updated_date'])) : 'Chưa cập nhật'; ?></p>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-title">
                    <i class="fas fa-chart-line me-2 text-success"></i>Thống kê hoạt động
                  </h6>
                  <hr>
                  <div class="row text-center">
                    <div class="col-md-3">
                      <div class="p-3">
                        <i class="fas fa-sign-in-alt fa-2x text-primary mb-2"></i>
                        <h5>--</h5>
                        <small>Lần đăng nhập cuối</small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="p-3">
                        <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                        <h5>--</h5>
                        <small>Tổng đăng nhập</small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="p-3">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h5>--</h5>
                        <small>Thời gian online</small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="p-3">
                        <i class="fas fa-star fa-2x text-info mb-2"></i>
                        <h5>--</h5>
                        <small>Điểm hoạt động</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <a href="?" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i>Đóng
          </a>
          <a href="?action=edit&id=<?php echo $viewUser['mongo_id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit me-1"></i>Chỉnh sửa
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="modal-backdrop fade show"></div>
  <?php endif; ?>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-exclamation-triangle text-danger me-2"></i>Xác nhận xóa
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-center">
            <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
            <h5>Bạn có chắc chắn muốn xóa user này?</h5>
            <p class="text-muted mb-4">
              User: <strong id="deleteUserName"></strong>
            </p>
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>Hủy
          </button>
          <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
            <i class="fas fa-trash me-1"></i>Xóa User
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Delete confirmation
    function confirmDelete(userId, userName) {
      document.getElementById('deleteUserName').textContent = userName;
      document.getElementById('confirmDeleteBtn').href = '?action=delete&id=' + userId;

      const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      deleteModal.show();
    }

    // Auto hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(function(alert) {
        setTimeout(function() {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }, 5000);
      });
    });

    // Form validation
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
      const password = this.querySelector('input[name="password"]').value;
      if (password.length < 6) {
        e.preventDefault();
        alert('Mật khẩu phải có ít nhất 6 ký tự!');
        return false;
      }
    });

    // Edit form validation
    const editForm = document.getElementById('editUserForm');
    if (editForm) {
      editForm.addEventListener('submit', function(e) {
        const password = this.querySelector('input[name="password"]').value;
        if (password && password.length < 6) {
          e.preventDefault();
          alert('Mật khẩu phải có ít nhất 6 ký tự!');
          return false;
        }
      });
    }

    // Search form enhancement
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
      searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
          this.form.submit();
        }
      });
    }

    // Auto focus on modals
    document.addEventListener('shown.bs.modal', function(e) {
      const firstInput = e.target.querySelector('input[type="text"], input[type="email"]');
      if (firstInput) {
        firstInput.focus();
      }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl + N: New user
      if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        const addModal = new bootstrap.Modal(document.getElementById('addUserModal'));
        addModal.show();
      }

      // ESC: Close modals
      if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(function(modal) {
          const bsModal = bootstrap.Modal.getInstance(modal);
          if (bsModal) {
            bsModal.hide();
          }
        });
      }
    });

    // Enhanced table interactions
    document.querySelectorAll('tbody tr').forEach(function(row) {
      row.addEventListener('click', function(e) {
        if (e.target.closest('.btn-group')) return;

        // Highlight selected row
        document.querySelectorAll('tbody tr').forEach(r => r.classList.remove('table-active'));
        this.classList.add('table-active');
      });
    });

    // Smooth scrolling for pagination
    document.querySelectorAll('.pagination a').forEach(function(link) {
      link.addEventListener('click', function(e) {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      });
    });

    // Status badge animation
    document.querySelectorAll('.badge').forEach(function(badge) {
      badge.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.1)';
      });

      badge.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
      });
    });

    // Real-time search suggestion (if needed)
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length >= 2) {
          searchTimeout = setTimeout(() => {
            // Here you could implement AJAX search suggestions
            console.log('Searching for:', query);
          }, 500);
        }
      });
    }

    // Add loading states for forms
    document.querySelectorAll('form').forEach(function(form) {
      form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
          const originalText = submitBtn.innerHTML;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xử lý...';
          submitBtn.disabled = true;

          // Re-enable after 3 seconds as fallback
          setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
          }, 3000);
        }
      });
    });
  </script>

  <style>
    /* Additional responsive styles */
    @media (max-width: 768px) {
      .main-container {
        margin: 10px;
        padding: 20px;
      }

      .btn-back {
        position: relative;
        top: auto;
        left: auto;
        margin-bottom: 20px;
        display: block;
        width: fit-content;
      }

      .table-responsive {
        font-size: 0.85rem;
      }

      .btn-group .btn {
        padding: 0.25rem 0.4rem;
      }

      .modal-dialog {
        margin: 10px;
      }

      .pagination {
        font-size: 0.85rem;
      }
    }

    /* Loading animation */
    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }

    .loading {
      animation: pulse 1.5s infinite;
    }

    /* Transition effects */
    .card, .btn, .badge, .alert {
      transition: all 0.3s ease;
    }

    .table tbody tr {
      transition: background-color 0.2s ease;
    }

    .table-active {
      background-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
    }
  </style>
</body>
</html>