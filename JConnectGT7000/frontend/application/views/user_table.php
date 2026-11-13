<div class="app-content-header py-3">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-people"></i> <?= lang("users"); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang("users"); ?></li>
          </ol>
        </nav>
      </div>
      <div class="col text-end">
        <?php if (CheckPermission( "user", "own_create")): ?>
          <button type="button" class="btn btn-primary modalButtonUser" data-bs-toggle="modal" data-bs-target="#nameModal_user">
            <i class="bi bi-plus-circle"></i> <?= lang('add_user'); ?>
          </button>
        <?php endif; ?>

        <?php if (getSetting('email_invitation') == 1): ?>
          <button type="button" class="btn btn-outline-primary ms-2 InviteUser" data-bs-toggle="modal" data-bs-target="#nameModal_user">
            <i class="bi bi-envelope-plus"></i> <?= lang('invite_users'); ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Total Users</h6>
                <h3 id="totalUsers">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-people fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Active Users</h6>
                <h3 id="activeUsers">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-person-check fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Pending Users</h6>
                <h3 id="pendingUsers">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-person-clock fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">User Types</h6>
                <h3 id="userTypes">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-person-badge fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-header">
        <div class="row align-items-center">
          <div class="col">
            <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="row">
            <!-- User Type Filter -->
            <div class="col-md-3">
              <label class="form-label">User Type</label>
              <select id="userTypeFilter" class="form-select form-select-sm">
                <option value="all">All Types</option>
                <!-- User types will be loaded dynamically from ia_permission table -->
              </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select id="statusFilter" class="form-select form-select-sm">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <!-- Search -->
            <div class="col-md-4">
              <label class="form-label">Search</label>
              <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search names, emails...">
            </div>

            <!-- Apply Button -->
            <div class="col-md-2">
              <label class="form-label">&nbsp;</label>
              <button id="applyFiltersBtn" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Users List</h5>
          <div class="d-flex align-items-center">
            <div class="d-flex align-items-center me-3">
              <label for="pageSizeSelector" class="me-2 mb-0">Show:</label>
              <select class="form-select form-select-sm" id="pageSizeSelector" style="width: auto;">
                <option value="10" selected>10</option>
                <option value="20">20</option>
                <option value="25">25</option>
                <option value="30">30</option>
                <option value="40">40</option>
                <option value="50">50</option>
              </select>
              <span class="ms-2 mb-0">entries per page</span>
            </div>
            <button type="button" class="btn btn-success btn-sm me-2" id="exportExcel">
              <i class="bi bi-file-earmark-excel"></i> Export Excel
            </button>
            <button type="button" class="btn btn-danger btn-sm" id="bulkDelete" style="display: none;">
              <i class="bi bi-trash"></i> Delete Selected
            </button>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover" id="usersTable">
            <thead>
              <tr>
                <th width="50">
                  <input type="checkbox" id="selectAll" class="form-check-input">
                </th>
                <th>Name</th>
                <th>Email</th>
                <th>User Type</th>
                <th>Status</th>
                <th>Created Date</th>
                <th width="120">Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">
              <tr>
                <td colspan="7" class="text-center">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="row align-items-center mt-3">
          <div class="col-md-12">
            <div class="d-flex justify-content-center">
              <nav aria-label="Users pagination">
                <ul class="pagination justify-content-center mb-0" id="pagination">
                  <!-- Pagination will be generated here -->
                </ul>
              </nav>
            </div>
          </div>
        </div>
        <div class="text-center mt-2" id="paginationInfoContainer">
          <small class="text-muted" id="paginationInfo">
            <!-- Pagination info will be shown here -->
          </small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="nameModal_user" tabindex="-1" aria-labelledby="nameModal_userLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="nameModal_userLabel">
          <i class="bi bi-person-plus"></i> <?= lang('user_form'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- ajax content here -->
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
  let currentPage = 1;
  let totalPages = 1;
  let pageSize = 10;

  $(document).ready(function() {
    loadUserTypes();
    loadStatistics();
    loadUsers();
    initEventHandlers();

    // Add search functionality
    $('#searchInput').on('keyup', function(e) {
      if (e.key === 'Enter') {
        loadUsers(1);
        loadStatistics();
      }
    });

    // Apply filters button
    $('#applyFiltersBtn').on('click', function(e) {
      e.preventDefault();
      loadUsers(1);
      loadStatistics();
    });

    // Page size selector change event
    $('#pageSizeSelector').on('change', function() {
      pageSize = parseInt($(this).val());
      loadUsers(1);
    });

    // Export Excel
    $('#exportExcel').on('click', function() {
      window.open('<?= base_url("user/export_data?format=excel") ?>', '_blank');
    });

    // Select all checkbox
    $('#selectAll').on('change', function() {
      $('.user-checkbox').prop('checked', $(this).is(':checked'));
      updateBulkDeleteButton();
    });

    // Bulk delete
    $('#bulkDelete').on('click', function() {
      const selectedIds = $('.user-checkbox:checked').map(function() {
        return $(this).val();
      }).get();
      
      if (selectedIds.length > 0) {
        if (confirm('Are you sure you want to delete ' + selectedIds.length + ' selected users?')) {
          deleteUsers(selectedIds);
        }
      }
    });
  });

  function initEventHandlers() {
    $(".container-fluid").on("click", ".modalButtonUser", function(e) {
      $.ajax({
        url: '<?= base_url("user/getModal") ?>',
        method: 'post',
        data: {
          id: $(this).attr('data-src')
        }
      }).done(function(data) {
        $('#nameModal_user').find('.modal-body').html(data);
        $('#nameModal_user').modal('show');
      });
    });

    // Individual checkbox change
    $(document).on('change', '.user-checkbox', function() {
      updateBulkDeleteButton();
    });
  }

  function loadUserTypes() {
    $.ajax({
      url: '<?= base_url("user/get_user_types") ?>',
      type: 'GET',
      dataType: 'json',
      success: function(data) {
        if (data && data.success === true && data.user_types) {
          const userTypeFilter = $('#userTypeFilter');
          userTypeFilter.find('option:not(:first)').remove(); // Remove existing options except "All Types"
          
          data.user_types.forEach(function(userType) {
            userTypeFilter.append(`<option value="${userType.user_type}">${userType.user_type}</option>`);
          });
        }
      },
      error: function(xhr, status, error) {
        console.error('User types loading failed:', error);
      }
    });
  }

  function loadStatistics() {
    $.ajax({
      url: '<?= base_url("user/get_statistics") ?>',
      type: 'GET',
      dataType: 'json',
      success: function(data) {
        if (data && data.success === true && data.stats) {
          $('#totalUsers').text(data.stats.total_users || 0);
          $('#activeUsers').text(data.stats.active_users || 0);
          $('#pendingUsers').text(data.stats.pending_users || 0);
          $('#userTypes').text(data.stats.user_types || 0);
        }
      },
      error: function(xhr, status, error) {
        console.error('Statistics loading failed:', error);
        $('#totalUsers').text('-');
        $('#activeUsers').text('-');
        $('#pendingUsers').text('-');
        $('#userTypes').text('-');
      }
    });
  }

  function loadUsers(page = 1) {
    currentPage = page;
    const userTypeFilter = $('#userTypeFilter').val();
    const statusFilter = $('#statusFilter').val();
    const search = $('#searchInput').val();

    // Show loading
    $('#usersTableBody').html(`
      <tr>
        <td colspan="7" class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </td>
      </tr>
    `);

    $.ajax({
      url: '<?= base_url("user/get_users") ?>',
      type: 'POST',
      dataType: 'json',
      data: {
        page: page,
        pageSize: pageSize,
        user_type_filter: userTypeFilter,
        status_filter: statusFilter,
        search: search
      },
      success: function(data) {
        if (data && data.success === true && data.users) {
          renderUsers(data.users);
          renderPagination(data.totalPages, data.currentPage, data.totalRecords);
        } else {
          $('#usersTableBody').html(`
            <tr>
              <td colspan="7" class="text-center text-danger">
                Error loading data: ${data.message || 'Unknown error'}
              </td>
            </tr>
          `);
        }
      },
      error: function(xhr, status, error) {
        $('#usersTableBody').html(`
          <tr>
            <td colspan="7" class="text-center text-danger">
              Failed to load data: ${error}
            </td>
          </tr>
        `);
      }
    });
  }

  function renderUsers(users) {
    if (!users || users.length === 0) {
      $('#usersTableBody').html(`
        <tr>
          <td colspan="7" class="text-center text-muted">
            No data available
          </td>
        </tr>
      `);
      return;
    }

    let html = '';
    users.forEach(function(user) {
      html += `
        <tr>
          <td>
            <input type="checkbox" class="form-check-input user-checkbox" value="${user.id}">
          </td>
          <td>
            <div class="d-flex align-items-center">
              <div class="avatar-sm me-3">
                <img src="<?= base_url("assets/images/") ?>${user.profile_pic || 'user.png'}" class="rounded-circle" width="40" height="40">
              </div>
              <div>
                <div class="fw-semibold">${user.name}</div>
              </div>
            </div>
          </td>
          <td>${user.email}</td>
          <td>
            <span class="badge ${getUserTypeBadgeClass(user.user_type)}">${user.user_type}</span>
          </td>
          <td>
            <span class="badge ${getStatusBadgeClass(user.status)}">${user.status}</span>
          </td>
          <td>${formatDate(user.create_date)}</td>
          <td>
            <div class="btn-group btn-group-sm" role="group">
              <button type="button" class="btn btn-info" onclick="editUser(${user.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button type="button" class="btn btn-danger" onclick="deleteUser(${user.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    });

    $('#usersTableBody').html(html);
  }

  function renderPagination(totalPages, currentPage, totalRecords) {
    if (totalPages <= 1) {
      $('#pagination').html('');
      $('#paginationInfo').html(`Showing 1 to ${totalRecords} of ${totalRecords} entries`);
      return;
    }

    let html = '';

    // Previous button
    html += `
      <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadUsers(${currentPage - 1})">Previous</a>
      </li>
    `;

    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(1)">1</a></li>`;
      if (startPage > 2) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      html += `
        <li class="page-item ${i === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" onclick="loadUsers(${i})">${i}</a>
        </li>
      `;
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      }
      html += `<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(${totalPages})">${totalPages}</a></li>`;
    }

    // Next button
    html += `
      <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadUsers(${currentPage + 1})">Next</a>
      </li>
    `;

    $('#pagination').html(html);
    $('#paginationInfo').html(`Showing ${((currentPage - 1) * pageSize) + 1} to ${Math.min(currentPage * pageSize, totalRecords)} of ${totalRecords} entries`);
  }

  function editUser(userId) {
    $.ajax({
      url: '<?= base_url("user/getModal") ?>',
      method: 'post',
      data: { id: userId }
    }).done(function(data) {
      $('#nameModal_user').find('.modal-body').html(data);
      $('#nameModal_user').modal('show');
    });
  }

  function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
      deleteUsers([userId]);
    }
  }

  function deleteUsers(userIds) {
    $.ajax({
      url: '<?= base_url("user/delete/") ?>' + userIds.join('-'),
      method: 'GET',
      success: function(response) {
        loadUsers(currentPage);
        loadStatistics();
        showAlert('Users deleted successfully', 'success');
      },
      error: function() {
        showAlert('Error deleting users', 'error');
      }
    });
  }

  function updateBulkDeleteButton() {
    const checkedCount = $('.user-checkbox:checked').length;
    if (checkedCount > 0) {
      $('#bulkDelete').show().text('Delete Selected (' + checkedCount + ')');
    } else {
      $('#bulkDelete').hide();
    }
  }

  function getUserTypeBadgeClass(userType) {
    switch(userType.toLowerCase()) {
      case 'admin': return 'bg-danger';
      case 'manager': return 'bg-warning text-dark';
      case 'user': return 'bg-info';
      case 'operator': return 'bg-success';
      default: return 'bg-secondary';
    }
  }

  function getStatusBadgeClass(status) {
    switch(status.toLowerCase()) {
      case 'active': return 'bg-success';
      case 'pending': return 'bg-warning text-dark';
      case 'inactive': return 'bg-secondary';
      default: return 'bg-secondary';
    }
  }

  function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
  }

  function showAlert(message, type) {
    // Remove any existing alerts first
    $('.alert').remove();
    
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle';
    
    const alertHtml = `
      <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        <i class="bi ${iconClass} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    `;
    
    // Insert alert at the top of the content area
    $('.app-content').prepend(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
      $('.alert').fadeOut(500, function() {
        $(this).remove();
      });
    }, 5000);
  }
</script>