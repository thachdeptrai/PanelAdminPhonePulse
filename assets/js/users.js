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
  