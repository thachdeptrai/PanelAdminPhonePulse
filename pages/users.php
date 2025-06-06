<?php
// users.php
require_once 'includes/config.php';
require_once 'api/client.php';

$pageTitle = 'Quản lý Users';
$api = new MongoAPIClient();

// Xử lý các actions
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

// Xóa user
if ($action === 'delete' && isset($_GET['id'])) {
    $result = $api->deleteUser($_GET['id']);
    if ($result['success']) {
        $message = 'Xóa user thành công!';
        $messageType = 'success';
    } else {
        $message = 'Có lỗi xảy ra khi xóa user!';
        $messageType = 'danger';
    }
}

// Lấy danh sách users
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$usersResult = $api->getUsers($page, $limit);
$users = $usersResult['success'] ? $usersResult['data']['users'] : [];
$totalUsers = $usersResult['success'] ? $usersResult['data']['total'] : 0;
$totalPages = ceil($totalUsers / $limit);

include 'includes/header.php';
?>

<div class="row fade-in">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-users"></i>
                Quản lý Users
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i>
                Thêm User
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo e($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="card-title mb-0">Danh sách Users (<?php echo number_format($totalUsers); ?>)</h5>
            </div>
            <div class="col-auto">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Tìm kiếm..." id="searchInput">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Chưa có user nào</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo e($user['_id'] ?? ''); ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-2">
                                        <img src="<?php echo e($user['avatar'] ?? 'assets/images/default-avatar.png'); ?>" 
                                             alt="Avatar" class="rounded-circle" width="32" height="32">
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?php echo e($user['name'] ?? ''); ?></div>
                                        <small class="text-muted"><?php echo e($user['username'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo e($user['email'] ?? ''); ?></td>
                            <td><?php echo e($user['phone'] ?? ''); ?></td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Không hoạt động</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e(date('d/m/Y', strtotime($user['created_at'] ?? ''))); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="viewUser('<?php echo e($user['_id']); ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            onclick="editUser('<?php echo e($user['_id']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteUser('<?php echo e($user['_id']); ?>', '<?php echo e($user['name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sau</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm User Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="userName" class="form-label">Tên</label>
                        <input type="text" class="form-control" id="userName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="userEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="userEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="userPhone" class="form-label">Số điện thoại</label>
                        <input type="tel" class="form-control" id="userPhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="userPassword" class="form-label">Mật khẩu</label>
                        <input type="password" class="form-control" id="userPassword" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="userStatus" class="form-label">Trạng thái</label>
                        <select class="form-select" id="userStatus" name="status">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="loading d-none"></span>
                        Thêm User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chỉnh sửa User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editUserName" class="form-label">Tên</label>
                        <input type="text" class="form-control" id="editUserName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editUserEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editUserEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="editUserPhone" class="form-label">Số điện thoại</label>
                        <input type="tel" class="form-control" id="editUserPhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="editUserStatus" class="form-label">Trạng thái</label>
                        <select class="form-select" id="editUserStatus" name="status">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="loading d-none"></span>
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// JavaScript cho users page
document.addEventListener('DOMContentLoaded', function() {
    // Add user form
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const loading = submitBtn.querySelector('.loading');
        
        submitBtn.disabled = true;
        loading.classList.remove('d-none');
        
        const formData = new FormData(this);
        const userData = Object.fromEntries(formData);
        
        // Call API to create user
        fetch('api/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(userData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Có lỗi xảy ra: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Có lỗi xảy ra: ' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            loading.classList.add('d-none');
        });
    });
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('tbody tr');
        
        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
});

// View user function
function viewUser(userId) {
    fetch(`api/users.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show user details in modal or new page
                console.log('User data:', data.data);
            }
        });
}

// Edit user function
function editUser(userId) {
    fetch(`api/users.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.data;
                document.getElementById('editUserId').value = user._id;
                document.getElementById('editUserName').value = user.name;
                document.getElementById('editUserEmail').value = user.email;
                document.getElementById('editUserPhone').value = user.phone || '';
                document.getElementById('editUserStatus').value = user.status;
                
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            }
        });
}

// Delete user function
function deleteUser(userId, userName) {
    if (confirm(`Bạn có chắc chắn muốn xóa user "${userName}"?`)) {
        fetch(`api/users.php?id=${userId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Có lỗi xảy ra khi xóa user!');
            }
        });
    }
}
</script>