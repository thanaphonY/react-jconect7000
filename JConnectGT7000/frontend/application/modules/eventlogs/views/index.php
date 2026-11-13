<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5> <?php echo lang('evenlogs') ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('evenlogs') ?></li>
          </ol>
        </nav>
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
                <h6 class="card-title">Total Logs</h6>
                <h3 id="totalLogs">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-list-ul fs-1"></i>
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
                <i class="bi bi-people fs-1"></i>
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
                <h6 class="card-title">Actions</h6>
                <h3 id="totalActions">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-activity fs-1"></i>
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
                <h6 class="card-title">Sections</h6>
                <h3 id="totalSections">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-grid fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="row align-items-center">
          <div class="col">
            <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
          </div>
          <!--     
          <div class="col-auto">
            <button type="button" class="btn btn-primary btn-sm" id="applyFilters">
              <i class="bi bi-funnel"></i> Apply Filters
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="clearFilters">
              <i class="bi bi-x-circle"></i> Clear Filters
            </button>

          </div>
-->
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <!-- Date Range Filter -->
          <div class="row">
            <div class="col-md-3">
              <label class="form-label">Date Range</label>
              <select id="dateFilter" class="form-select form-select-sm">
                <option value="today"><?php echo lang('today') ?></option>
                <option value="yesterday"><?php echo lang('yesterday') ?></option>
                <option value="last_7_days">Last 7 Days</option>
                <option value="last_30_days" selected>Last 30 Days</option>
                <option value="custom">Custom Range</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Start Date</label>
              <input type="date" id="startDate" class="form-control form-control-sm" />
            </div>
            <div class="col-md-2">
              <label class="form-label">End Date</label>
              <input type="date" id="endDate" class="form-control form-control-sm" />
            </div>
          </div>

          <div class="row">
            <!-- User Filter -->
            <div class="col-md-2">
              <label class="form-label">User</label>
              <select id="userFilter" class="form-select form-select-sm">
                <option value="all">All Users</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?= $user->user_id ?>"><?= $user->name ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Action Type Filter -->
            <div class="col-md-2">
              <label class="form-label">Action</label>
              <select id="actionFilter" class="form-select form-select-sm">
                <option value="all">All Actions</option>
                <?php foreach ($action_types as $action): ?>
                  <option value="<?= $action->action_type ?>"><?= ucfirst($action->action_type) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Entity Type Filter -->
            <div class="col-md-2">
              <label class="form-label">Section</label>
              <select id="entityFilter" class="form-select form-select-sm">
                <option value="all">All Sections</option>
                <?php foreach ($entity_types as $entity): ?>
                  <option value="<?= $entity->entity_type ?>"><?= ucfirst($entity->entity_type) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Search -->
            <div class="col-md-3">
              <label class="form-label">Search</label>
              <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search messages, actions, sections...">
            </div>

            <!-- Apply Button -->
            <div class="col-md-1">
              <label class="form-label">&nbsp;</label>
              <button id="applyFiltersBtn" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Data Table -->
    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Event Logs</h5>
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
            <button type="button" class="btn btn-info btn-sm" id="previewExport">
              <i class="bi bi-eye"></i> Preview Export
            </button>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover" id="eventlogTable">
            <thead>
              <tr>
                <th>Date Time</th>
                <th>Users</th>
                <th>Action</th>
                <th>Section</th>
                <th>Message</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="eventlogTableBody">
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
          <div class="col-md-12"></div>
          <div class="d-flex justify-content-center">
            <nav aria-label="Event logs pagination">
              <ul class="pagination justify-content-center mb-0" id="pagination">
                <!-- Pagination will be generated here -->
              </ul>
            </nav>
          </div>
        </div>
        <div class="text-center mt-2" id="paginationInfoContainer">
          <small class="text-muted" id="paginationInfo">
            <!-- Pagination info will be shown here -->
          </small>
        </div>
      </div>
    </div>

    <!-- Log Detail Modal -->
    <div class="modal fade" id="logDetailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Log Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="logDetailContent">
              <!-- Content will be loaded here -->
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      let currentPage = 1;
      let totalPages = 1;
      let pageSize = 10;

      $(document).ready(function() {
        dayjs.extend(dayjs_plugin_utc);
        dayjs.extend(dayjs_plugin_timezone);



        loadStatistics();
        loadEventLogs();
        initEventHandlers();

        // Show/hide start/end date on date range change
        $('#dateFilter').on('change', function() {
          if ($(this).val() === 'custom') {
            $('#startDate').closest('.col-md-2').removeClass('d-none');
            $('#endDate').closest('.col-md-2').removeClass('d-none');
          } else {
            $('#startDate').closest('.col-md-2').addClass('d-none');
            $('#endDate').closest('.col-md-2').addClass('d-none');
          }
        });

        // Trigger on page load
        $('#dateFilter').trigger('change');

        // Add search functionality
        $('#searchInput').on('keyup', function(e) {
          if (e.key === 'Enter') {
            loadEventLogs(1);
            loadStatistics();
          }
        });

        // Fix: search button always triggers loadEventLogs
        $('#applyFiltersBtn').on('click', function(e) {
          e.preventDefault();
          loadEventLogs(1);
        });

        // Page size selector change event
        $('#pageSizeSelector').on('change', function() {
          pageSize = parseInt($(this).val());
          loadEventLogs(1); // Reset to first page when changing page size
        });
      });

      function loadEventLogs(page = 1) {
        currentPage = page;
        const range = $('#dateFilter').val();
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        const userFilter = $('#userFilter').val();
        const actionFilter = $('#actionFilter').val();
        const entityFilter = $('#entityFilter').val();
        const search = $('#searchInput').val();

        console.log('Loading event logs:', {
          page,
          range,
          startDate,
          endDate,
          userFilter,
          actionFilter,
          entityFilter,
          search
        });

        // Show loading
        $('#eventlogTableBody').html(`
        <tr>
            <td colspan="6" class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </td>
        </tr>
    `);

        $.ajax({
          url: '<?= base_url("eventlogs/get_logs") ?>',
          type: 'POST',
          dataType: 'json',
          data: {
            page: page,
            pageSize: pageSize,
            range: range,
            startDate: startDate,
            endDate: endDate,
            user_filter: userFilter,
            action_filter: actionFilter,
            entity_filter: entityFilter,
            search: search
          },
          success: function(data) {
            console.log('Event logs response:', data);
            console.log('data.success type:', typeof data.success, 'value:', data.success);
            console.log('data.logs length:', data.logs ? data.logs.length : 'undefined');


            if (data && data.success === true && data.logs) {
              console.log('Rendering event logs...');

              renderEventLogs(data.logs);
              renderPagination(data.totalPages, data.currentPage, data.totalRecords);
            } else {
              console.error('Event logs failed:', data.message || 'Unknown error');
              console.error('data object:', data);
              $('#eventlogTableBody').html(`
                    <tr>
                        <td colspan="6" class="text-center text-danger">
                            Error loading data: ${data.message || 'Unknown error'}
                        </td>
                    </tr>
                `);
            }
          },
          error: function(xhr, status, error) {
            console.error('Event logs AJAX failed:', error);
            console.error('Response:', xhr.responseText);
            $('#eventlogTableBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        Failed to load data: ${error}
                    </td>
                </tr>
            `);
          }
        });
      }

      function renderEventLogs(logs) {
        console.log('renderEventLogs called with:', logs);
        console.log('logs type:', typeof logs);
        console.log('logs length:', logs ? logs.length : 'undefined');

        if (!logs || logs.length === 0) {
          console.log('No logs to render');
          $('#eventlogTableBody').html(`
            <tr>
                <td colspan="7" class="text-center text-muted">
                    No data available
                </td>
            </tr>
        `);
          return;
        }

        console.log('Rendering', logs.length, 'logs');
        let html = '';
        logs.forEach(function(log, index) {
          console.log('Processing log', index, ':', log);
          const status = getStatusFromMetadata(log.metadata);
          const statusClass = status === 'Success' ? 'text-success' : 'text-danger';

          html += `
            <tr>
                <td>${formatDateTime(log.created_at)}</td>
                <td>${log.user_name || 'Unknown'}</td>
                <td><span class="badge ${getActionBadgeClass(log.action_type)}">${log.action_type.charAt(0).toUpperCase() + log.action_type.slice(1)}</span></td>
                <td><span class="badge bg-secondary">${log.entity_type.charAt(0).toUpperCase() + log.entity_type.slice(1)}</span></td>
                <td>
                    ${log.message.length > 50 ? log.message.substring(0, 50) + '...' : log.message}
                    ${log.message.length > 50 ? `<br><a href="#" onclick="viewLogDetail(${log.id})" class="text-primary">View</a>` : ''}
                </td>
                <td>
                    <button type="button" class="btn btn-info btn-sm" onclick="viewLogDetail(${log.id})">
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
        `;
        });

        console.log('Generated HTML length:', html.length);
        $('#eventlogTableBody').html(html);
        console.log('HTML updated in table');
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
            <a class="page-link" href="#" onclick="loadEventLogs(${currentPage - 1})">Previous</a>
        </li>
    `;

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
          html += `<li class="page-item"><a class="page-link" href="#" onclick="loadEventLogs(1)">1</a></li>`;
          if (startPage > 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
          }
        }

        for (let i = startPage; i <= endPage; i++) {
          html += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadEventLogs(${i})">${i}</a>
            </li>
        `;
        }

        if (endPage < totalPages) {
          if (endPage < totalPages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
          }
          html += `<li class="page-item"><a class="page-link" href="#" onclick="loadEventLogs(${totalPages})">${totalPages}</a></li>`;
        }

        // Next button
        html += `
        <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadEventLogs(${currentPage + 1})">Next</a>
        </li>
            `;

        $('#pagination').html(html);
        const startRecord = (currentPage - 1) * pageSize + 1;
        const endRecord = Math.min(currentPage * pageSize, totalRecords);
        $('#paginationInfo').html(`Showing ${startRecord} to ${endRecord} of ${totalRecords} entries`);
      }

      function getStatusFromMetadata(metadata) {
        if (!metadata || metadata === null || metadata === undefined) return 'Success';
        try {
          const meta = typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
          return meta && meta.status === 'fail' ? 'Failed' : 'Success';
        } catch (e) {
          console.warn('Error parsing metadata:', e);
          return 'Success';
        }
      }

      function formatDateTime(dateTime) {
        if (!dateTime) return '-';
        return dayjs.utc(dateTime).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss');
      }

      function getActionBadgeClass(action) {
        switch ((action || '').toLowerCase()) {
          case 'create':
            return 'bg-success text-white';
          case 'update':
            return 'bg-warning text-dark';
          case 'delete':
            return 'bg-danger text-white';
          case 'login':
            return 'bg-info text-white';
          case 'logout':
            return 'bg-secondary text-white';
          default:
            return 'bg-primary text-white';
        }
      }

      function loadStatistics() {
        const range = $('#dateFilter').val();
        console.log('Loading statistics for range:', range);

        $.ajax({
          url: '<?= base_url("eventlogs/get_statistics") ?>?range=' + range,
          type: 'GET',
          dataType: 'json',
          success: function(data) {
            console.log('Statistics response:', data);
            console.log('data.success type:', typeof data.success, 'value:', data.success);
            console.log('data.stats:', data.stats);

            if (data && data.success === true && data.stats) {
              console.log('Updating statistics cards...');
              $('#totalLogs').text(data.stats.total_logs || '0');
              $('#activeUsers').text(data.stats.unique_users || '0');
              $('#totalActions').text(data.stats.unique_actions || '0');
              $('#totalSections').text(data.stats.unique_entities || '0');
              console.log('Statistics updated successfully');
            } else {
              console.error('Statistics failed:', data.message || 'Unknown error');
              console.error('data object:', data);
            }
          },
          error: function(xhr, status, error) {
            console.error('Statistics AJAX failed:', error);
            console.error('Response:', xhr.responseText);
          }
        });
      }

      function initEventHandlers() {
        // Filter change handlers
        $('#dateFilter, #userFilter, #actionFilter, #entityFilter, #statusFilter').on('change', function() {
          loadEventLogs(1);
          loadStatistics();
        });

        // Date range change handler
        $('#dateFilter').on('change', function() {
          if ($(this).val() === 'custom') {
            $('.date-inputs').show();
          } else {
            $('.date-inputs').hide();
          }
        });

        // Search input handler
        $('#searchInput').on('keyup', function(e) {
          if (e.key === 'Enter') {
            loadEventLogs(1);
            loadStatistics();
          }
        });

        // Apply filters button
        $('#applyFiltersBtn').on('click', function() {
          loadEventLogs(1);
          loadStatistics();
        });

        // Clear filters button
        $('#clearFiltersBtn').on('click', function() {
          $('#dateFilter').val('last_30_days');
          $('#userFilter').val('all');
          $('#actionFilter').val('all');
          $('#entityFilter').val('all');
          $('#statusFilter').val('all');
          $('#searchInput').val('');
          $('#startDate, #endDate').val('');
          $('.date-inputs').hide();
          loadEventLogs(1);
          loadStatistics();
        });

        // Preview export button
        $('#previewExport').on('click', function() {
          const range = $('#dateFilter').val();
          const startDate = $('#startDate').val();
          const endDate = $('#endDate').val();
          const userFilter = $('#userFilter').val();
          const actionFilter = $('#actionFilter').val();
          const entityFilter = $('#entityFilter').val();
          const search = $('#searchInput').val();

          const params = new URLSearchParams({
            range: range,
            startDate: startDate,
            endDate: endDate,
            user_filter: userFilter,
            action_filter: actionFilter,
            entity_filter: entityFilter,
            search: search
          });

          window.open('<?= base_url("eventlogs/export_preview") ?>?' + params.toString(), '_blank');
        });
      }

      function viewLogDetail(logId) {
        $.ajax({
          url: '<?= base_url("eventlogs/get_log_detail") ?>/' + logId,
          type: 'GET',
          dataType: 'json',
          success: function(data) {
            if (data && data.success === true) {
              const log = data.data;
              const status = getStatusFromMetadata(log.metadata);
              const statusClass = status === 'Success' ? 'text-success' : 'text-danger';

              let additionalInfo = '';
              let ipAddress = '';
              let userAgent = '';
              if (log.metadata && log.metadata !== null && log.metadata !== undefined) {
                try {
                  const meta = typeof log.metadata === 'string' ? JSON.parse(log.metadata) : log.metadata;
                  if (meta && typeof meta === 'object' && Object.keys(meta).length > 0) {
                    Object.keys(meta).forEach(function(key) {
                      if (key === 'status' || key === 'timestamp') return; // skip
                      if (key === 'ip_address') {
                        ipAddress = meta[key];
                        return;
                      }
                      if (key === 'user_agent') {
                        userAgent = meta[key];
                        return;
                      }
                      let value = meta[key];
                      if (typeof value === 'object' && value !== null) {
                        value = `<pre style="display:inline;white-space:pre-wrap;">${JSON.stringify(value, null, 2)}</pre>`;
                      }
                      additionalInfo += `<strong>${key}:</strong> ${value}<br>`;
                    });
                  } else {
                    additionalInfo = 'No additional information';
                  }
                } catch (e) {
                  additionalInfo = 'No additional information';
                }
              } else {
                additionalInfo = 'No additional information';
              }

              // Build left column details (add ip_address, user_agent if present)
              let leftDetails = `
                  <p><strong>Date Time:</strong> ${formatDateTime(log.created_at)}</p>
                  <p><strong>User:</strong> ${log.user_name || 'Unknown'}</p>
                  <p><strong>Action:</strong> <span class="badge ${getActionBadgeClass(log.action_type)}">${log.action_type.charAt(0).toUpperCase() + log.action_type.slice(1)}</span></p>
                  <p><strong>Section:</strong> <span class="badge bg-secondary">${log.entity_type.charAt(0).toUpperCase() + log.entity_type.slice(1)}</span></p>
                  <p><strong>Status:</strong> <span class="${statusClass}">${status}</span></p>
              `;
              if (ipAddress) leftDetails += `<p><strong>IP Address:</strong> ${ipAddress}</p>`;
              if (userAgent) leftDetails += `<p><strong>User Agent:</strong> <span class='small text-muted'>${userAgent}</span></p>`;

              $('#logDetailContent').html(`
                    <div class="row">
                        <div class="col-md-6">
                            ${leftDetails}
                        </div>
                        <div class="col-md-6" style="overflow:hidden;">
                            <p><strong>Additional Info:</strong></p>
                            <div class="small text-muted" style="overflow-y: auto; max-height: 300px;">
                                ${additionalInfo || 'No additional information'}
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12" >
                            <p><strong>Message:</strong></p>
                            <div class="alert alert-info">${log.message}</div>
                        </div>
                    </div>
                `);

              $('#logDetailModal').modal('show');
            } else {
              alert('Error loading log details: ' + (data.message || 'Unknown error'));
            }
          },
          error: function(xhr, status, error) {
            alert('Failed to load log details: ' + error);
          }
        });
      }

      function exportData(format) {
        const range = $('#dateFilter').val();
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        const userFilter = $('#userFilter').val();
        const actionFilter = $('#actionFilter').val();
        const entityFilter = $('#entityFilter').val();
        const search = $('#searchInput').val();

        const params = new URLSearchParams({
          format: format,
          range: range,
          startDate: startDate,
          endDate: endDate,
          user_filter: userFilter,
          action_filter: actionFilter,
          entity_filter: entityFilter,
          search: search
        });

        window.open('<?= base_url("eventlogs/export_data") ?>?' + params.toString(), '_blank');
      }
    </script>