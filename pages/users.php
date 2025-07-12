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

   // Tìm kiếm
$searchTerm      = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParams    = [];

if (!empty($searchTerm)) {
    $searchCondition = "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?";
    $searchParams    = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
}

// Phân trang
$page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

// SQL query (toàn ?)
$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM users $searchCondition ORDER BY created_date DESC LIMIT ?, ?";
$stmt = $pdo->prepare($sql);

// Bind search + offset/limit
$searchParams[] = $offset;
$searchParams[] = $limit;

$stmt->execute($searchParams);
$users = $stmt->fetchAll();

// Tổng số user
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
  <link rel="stylesheet" href="../assets/css/users.css">
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
                  <p><strong>Ngày cập nhật:</strong>                                                          <?php echo $viewUser['modified_date'] ? date('d/m/Y H:i:s', strtotime($viewUser['modified_date'])) : 'Chưa cập nhật'; ?></p>
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
  <script src="../assets/js/users.js"></script>
  <style>
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