<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5><?php echo lang('alarm_logs'); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('alarm_logs') ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<style>
  .badge.bg-orange { background-color: var(--bs-orange, #fd7e14) !important; color: #fff !important; }
</style>

<div class="app-content">
  <div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Total Alarms</h6>
                <h3 id="totalAlarms">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-exclamation-triangle fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-danger text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Active Alarms</h6>
                <h3 id="activeAlarms">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-exclamation-circle fs-1"></i>
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
                <h6 class="card-title">Alarm Levels</h6>
                <h3 id="severityLevels">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-layers fs-1"></i>
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
                <h6 class="card-title">Devices</h6>
                <h3 id="deviceCount">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-cpu fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card">
      <div class="card-header">
        <div class="row align-items-center">
          <div class="col">
            <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <!-- Date Range Filter -->
          <div class="row">
            <div class="col-md-3">
              <label class="form-label">Date Range</label>
              <select id="dateFilter" class="form-select form-select-sm">
                <option value="all">All</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last_7_days">Last 7 Days</option>
                <option value="last_30_days" selected>Last 30 Days</option>
                <option value="custom">Custom Range</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Start Date</label>
              <input type="date" id="startDate" class="form-control form-control-sm d-none">
            </div>
            <div class="col-md-2">
              <label class="form-label">End Date</label>
              <input type="date" id="endDate" class="form-control form-control-sm d-none">
            </div>
          </div>

          <div class="row">
            <!-- Device Filter -->
            <div class="col-md-2">
              <label class="form-label">Device</label>
              <select id="deviceFilter" class="form-select form-select-sm">
                <option value="all">All Devices</option>
                <?php foreach ($devices as $device): ?>
                  <option value="<?= $device->id ?>"><?= $device->name ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Severity Filter -->
            <div class="col-md-2">
              <label class="form-label">Alarm Level</label>
              <select id="severityFilter" class="form-select form-select-sm">
                <option value="all">All Severities</option>
                <?php foreach ($severities as $severity): ?>
                  <option value="<?= $severity->severity ?>"><?= ucfirst($severity->severity) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-2">
              <label class="form-label">Status</label>
              <select id="statusFilter" class="form-select form-select-sm">
                <option value="all">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                  <option value="<?= $status->status ?>"><?= ucfirst($status->status) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Search -->
            <div class="col-md-3">
              <label class="form-label">Search</label>
              <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search devices, metrics, messages...">
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
          <h6 class="mb-0"><i class="bi bi-list-ul"></i> Alarm Logs</h6>
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

           <!-- <div class="dropdown me-2">
              <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="bulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-gear"></i> Bulk Actions
              </button>
              <ul class="dropdown-menu" aria-labelledby="bulkActionsDropdown">
                <li><a class="dropdown-item" href="#" onclick="bulkAcknowledge()">
                    <i class="bi bi-check-circle text-warning"></i> Acknowledge Selected
                  </a></li>
                <li><a class="dropdown-item" href="#" onclick="bulkResolve()">
                    <i class="bi bi-check-circle-fill text-success"></i> Resolve Selected
                  </a></li>
              </ul>
            </div>-->
            <div class="d-flex align-items-center me-3">
              <button id="previewExportBtn" class="btn btn-info btn-sm">
                <i class="bi bi-eye"></i> Preview Export
              </button>
            </div>


          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover" id="alarmTable">
            <thead>
              <tr>
                <th>
                  <input type="checkbox" id="selectAll" class="form-check-input">
                </th>
                <th>Date Time</th>
                <th>Device</th>
                <th>Key</th>
                <th>Alarm Level</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody id="alarmTableBody">
              <tr>
                <td colspan="6" class="text-center">
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
              <nav aria-label="Alarm logs pagination">
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

<!-- Snapshot Preview Modal -->
<div class="modal fade" id="snapshotPreviewModal" tabindex="-1" aria-labelledby="snapshotPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-transparent border-0 shadow-none">
      <div class="modal-body p-0 position-relative text-center">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close" style="z-index:2;filter:drop-shadow(0 0 2px #000)"></button>
        <img id="snapshotPreviewImage" src="" alt="Alarm snapshot" class="img-fluid rounded shadow-lg" style="max-height:85vh;max-width:100%" onerror="this.classList.add('d-none'); document.getElementById('snapshotPreviewError').classList.remove('d-none')">
        <div id="snapshotPreviewError" class="text-white bg-dark rounded p-4 d-none">Snapshot file not found</div>
      </div>
    </div>
  </div>
</div>

<!-- Alarm Detail Modal -->
<div class="modal fade" id="alarmDetailModal" tabindex="-1" aria-labelledby="alarmDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="alarmDetailModalLabel">
          <i class="bi bi-exclamation-triangle text-warning"></i> Alarm Details
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <!-- Alarm Information -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Alarm Information</h6>
              </div>
              <div class="card-body" id="alarmInfo">
                <!-- Alarm info will be loaded here -->
              </div>
            </div>
          </div>

          <!-- Device Information -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-cpu"></i> Device Information</h6>
              </div>
              <div class="card-body" id="deviceInfo">
                <!-- Device info will be loaded here -->
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-3">
          <!-- Related Alarms -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Related Alarms</h6>
              </div>
              <div class="card-body" id="relatedAlarms">
                <!-- Related alarms will be loaded here -->
              </div>
            </div>
          </div>

          <!-- Dispatch History -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-send"></i> Notification History</h6>
              </div>
              <div class="card-body" id="dispatchHistory">
                <!-- Dispatch history will be loaded here -->
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning" id="acknowledgeBtn" onclick="acknowledgeAlarm()">
          <i class="bi bi-check-circle"></i> Acknowledge
        </button>
        <button type="button" class="btn btn-success" id="resolveBtn" onclick="resolveAlarm()">
          <i class="bi bi-check-circle-fill"></i> Resolve
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Acknowledge Modal -->
<div class="modal fade" id="acknowledgeModal" tabindex="-1" aria-labelledby="acknowledgeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="acknowledgeModalLabel">
          <i class="bi bi-check-circle text-warning"></i> Acknowledge Alarm
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="acknowledgeForm">
          <input type="hidden" id="acknowledgeAlarmId">
          <div class="mb-3">
            <label for="acknowledgeNote" class="form-label">Note (Optional)</label>
            <textarea class="form-control" id="acknowledgeNote" rows="3" placeholder="Add a note about this acknowledgment..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="submitAcknowledge()">
          <i class="bi bi-check-circle"></i> Acknowledge
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resolveModalLabel">
          <i class="bi bi-check-circle-fill text-success"></i> Resolve Alarm
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="resolveForm">
          <input type="hidden" id="resolveAlarmId">
          <div class="mb-3">
            <label for="resolveNote" class="form-label">Resolution Note</label>
            <textarea class="form-control" id="resolveNote" rows="3" placeholder="Describe how this alarm was resolved..." required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitResolve()">
          <i class="bi bi-check-circle-fill"></i> Resolve
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Acknowledge Modal -->
<div class="modal fade" id="bulkAcknowledgeModal" tabindex="-1" aria-labelledby="bulkAcknowledgeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bulkAcknowledgeModalLabel">
          <i class="bi bi-check-circle text-warning"></i> Bulk Acknowledge Alarms
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="bulkAcknowledgeForm">
          <div class="mb-3">
            <label for="bulkAcknowledgeNote" class="form-label">Note (Optional)</label>
            <textarea class="form-control" id="bulkAcknowledgeNote" rows="3" placeholder="Add a note for all acknowledged alarms..."></textarea>
          </div>
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <span id="bulkAcknowledgeCount">0</span> alarms will be acknowledged.
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="submitBulkAcknowledge()">
          <i class="bi bi-check-circle"></i> Acknowledge All
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Resolve Modal -->
<div class="modal fade" id="bulkResolveModal" tabindex="-1" aria-labelledby="bulkResolveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bulkResolveModalLabel">
          <i class="bi bi-check-circle-fill text-success"></i> Bulk Resolve Alarms
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="bulkResolveForm">
          <div class="mb-3">
            <label for="bulkResolveNote" class="form-label">Resolution Note</label>
            <textarea class="form-control" id="bulkResolveNote" rows="3" placeholder="Describe how these alarms were resolved..." required></textarea>
          </div>
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <span id="bulkResolveCount">0</span> alarms will be resolved.
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitBulkResolve()">
          <i class="bi bi-check-circle-fill"></i> Resolve All
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  let currentPage = 1;
  let totalPages = 1;
  let pageSize = 10;
  let currentAlarmId = null;

  $(document).ready(function() {
    dayjs.extend(dayjs_plugin_utc);
    dayjs.extend(dayjs_plugin_timezone);

    loadStatistics();
    loadAlarmLogs();
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
        loadAlarmLogs(1);
        loadStatistics();
      }
    });

    // Apply filters button
    $('#applyFiltersBtn').on('click', function(e) {
      e.preventDefault();
      loadAlarmLogs(1);
      loadStatistics();
    });

    // Page size selector change event
    $('#pageSizeSelector').on('change', function() {
      pageSize = parseInt($(this).val());
      loadAlarmLogs(1);
    });

    // Preview export button
    $('#previewExportBtn').on('click', function() {
      const params = new URLSearchParams();
      params.append('range', $('#dateFilter').val());
      params.append('startDate', $('#startDate').val());
      params.append('endDate', $('#endDate').val());
      params.append('device_filter', $('#deviceFilter').val());
      params.append('severity_filter', $('#severityFilter').val());
      params.append('status_filter', $('#statusFilter').val());
      params.append('search', $('#searchInput').val());

      window.open('<?= base_url("alarmlogs/export_preview") ?>?' + params.toString(), '_blank');
    });

    // Select all checkbox
    $('#selectAll').on('change', function() {
      $('.alarm-checkbox').prop('checked', $(this).is(':checked'));
    });
  });

  function loadStatistics() {
    const range = $('#dateFilter').val();
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    const deviceFilter = $('#deviceFilter').val();
    const severityFilter = $('#severityFilter').val();
    const statusFilter = $('#statusFilter').val();
    const search = $('#searchInput').val();

    const params = new URLSearchParams({
      range: range || 'last_30_days',
      startDate: startDate,
      endDate: endDate,
      device_filter: deviceFilter,
      severity_filter: severityFilter,
      status_filter: statusFilter,
      search: search
    });

    $.ajax({
      url: '<?= base_url("alarmlogs/get_statistics") ?>?' + params.toString(),
      type: 'GET',
      dataType: 'json',
      success: function(data) {
        if (data && data.success === true && data.stats) {
          $('#totalAlarms').text(data.stats.total_alarms || 0);
          $('#activeAlarms').text(data.stats.active_alarms || 0);
          $('#severityLevels').text(data.stats.severity_breakdown ? data.stats.severity_breakdown.length : 0);
          $('#deviceCount').text(data.stats.device_breakdown ? data.stats.device_breakdown.length : 0);
        }
      },
      error: function(xhr, status, error) {
        console.error('Statistics loading failed:', error);
      }
    });
  }

  function loadAlarmLogs(page = 1) {
    currentPage = page;
    const range = $('#dateFilter').val();
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    const deviceFilter = $('#deviceFilter').val();
    const severityFilter = $('#severityFilter').val();
    const statusFilter = $('#statusFilter').val();
    const search = $('#searchInput').val();

    // Show loading
    $('#alarmTableBody').html(`
      <tr>
        <td colspan="9" class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </td>
      </tr>
    `);

    $.ajax({
      url: '<?= base_url("alarmlogs/get_logs") ?>',
      type: 'POST',
      dataType: 'json',
      data: {
        page: page,
        pageSize: pageSize,
        range: range || 'last_30_days',
        startDate: startDate,
        endDate: endDate,
        device_filter: deviceFilter,
        severity_filter: severityFilter,
        status_filter: statusFilter,
        search: search
      },
      success: function(data) {
        if (data && data.success === true && data.logs) {
          renderAlarmLogs(data.logs);
          renderPagination(data.totalPages, data.currentPage, data.totalRecords);
        } else {
          $('#alarmTableBody').html(`
            <tr>
              <td colspan="6" class="text-center text-danger">
                Error loading data: ${data.message || 'Unknown error'}
              </td>
            </tr>
          `);
        }
      },
      error: function(xhr, status, error) {
        $('#alarmTableBody').html(`
          <tr>
            <td colspan="6" class="text-center text-danger">
              Failed to load data: ${error}
            </td>
          </tr>
        `);
      }
    });
  }

  function renderAlarmLogs(logs) {
    if (!logs || logs.length === 0) {
      $('#alarmTableBody').html(`
        <tr>
          <td colspan="6" class="text-center text-muted">
            No data available
          </td>
        </tr>
      `);
      return;
    }

    let html = '';
    logs.forEach(function(log) {
      const message = log.message || '-';
      const shortMessage = message.length > 50 ? message.substring(0, 50) + '...' : message;
      const snapshotHtml = log.snapshot_path ? renderSnapshotCell(log.snapshot_path) : '';
      html += `
        <tr>
          <td>
            <input type="checkbox" class="form-check-input alarm-checkbox" value="${log.id}">
          </td>
          <td>${formatDateTime(log.created_at)}</td>
          <td>${escapeHtml(log.device_name || 'Unknown')}</td>
          <td>${escapeHtml(log.metric_key || '-')}</td>
          <td><span class="badge ${getSeverityBadgeClass(log.severity)}">${escapeHtml(log.severity || 'Unknown')}</span></td>
          <td>
            <div>${escapeHtml(shortMessage)}</div>
            ${snapshotHtml}
            ${message.length > 50 ? `<div class="mt-1"><a href="#" onclick="viewAlarmDetail(${log.id}); return false;" class="text-primary small">View details</a></div>` : ''}
          </td>
        </tr>
      `;
    });

    $('#alarmTableBody').html(html);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderSnapshotCell(snapshotPath) {
    const href = escapeHtml(snapshotPath);
    const dataPath = escapeHtml(snapshotPath || '');
    return `
      <div class="mt-2">
        <img src="${href}" data-snapshot="${dataPath}" alt="Snapshot" role="button" class="alarm-snapshot-thumb border rounded shadow-sm" style="height:72px;width:auto;max-width:140px;object-fit:cover;cursor:pointer" loading="lazy" title="Click to enlarge" onerror="this.style.display='none'">
      </div>
    `;
  }

  function viewSnapshot(snapshotPath) {
    if (!snapshotPath) return;
    const img = document.getElementById('snapshotPreviewImage');
    const err = document.getElementById('snapshotPreviewError');
    if (!img) return;
    img.classList.remove('d-none');
    err.classList.add('d-none');
    img.src = snapshotPath;
    showBsModal('snapshotPreviewModal');
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
        <a class="page-link" href="#" onclick="loadAlarmLogs(${currentPage - 1})">Previous</a>
      </li>
    `;

    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAlarmLogs(1)">1</a></li>`;
      if (startPage > 2) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      html += `
        <li class="page-item ${i === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" onclick="loadAlarmLogs(${i})">${i}</a>
        </li>
      `;
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      }
      html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAlarmLogs(${totalPages})">${totalPages}</a></li>`;
    }

    // Next button
    html += `
      <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadAlarmLogs(${currentPage + 1})">Next</a>
      </li>
    `;

    $('#pagination').html(html);
    const startRecord = (currentPage - 1) * pageSize + 1;
    const endRecord = Math.min(currentPage * pageSize, totalRecords);
    $('#paginationInfo').html(`Showing ${startRecord} to ${endRecord} of ${totalRecords} entries`);
  }

  function formatDateTime(dateTime) {
    if (!dateTime) return '-';
    return dayjs.utc(dateTime).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss');
  }

  function getSeverityBadgeClass(severity) {
    switch ((severity || '').toLowerCase()) {
      case 'critical':
        return 'bg-danger text-white';
      case 'major':
      case 'high':
        return 'bg-orange text-white';
      case 'warning':
      case 'medium':
        return 'bg-warning text-dark';
      case 'minor':
      case 'low':
        return 'bg-info text-white';
      case 'info':
        return 'bg-secondary text-white';
      default:
        return 'bg-secondary text-white';
    }
  }

  function getStatusBadgeClass(status) {
    switch ((status || '').toLowerCase()) {
      case 'active':
        return 'bg-danger text-white';
      case 'acknowledged':
        return 'bg-warning text-dark';
      case 'resolved':
        return 'bg-success text-white';
      case 'queued':
        return 'bg-info text-white';
      default:
        return 'bg-secondary text-white';
    }
  }

  function renderSnapshotDetail(snapshotPath, cameraDeviceId) {
    const href = escapeHtml(snapshotPath);
    const dataPath = escapeHtml(snapshotPath || '');
    const cameraLabel = cameraDeviceId ? `Camera #${escapeHtml(cameraDeviceId)}` : '';
    return `
      <div>
        ${cameraLabel ? `<div class="text-muted small mb-2">${cameraLabel}</div>` : ''}
        <img src="${href}" data-snapshot="${dataPath}" alt="Alarm snapshot" role="button" class="alarm-snapshot-thumb img-fluid border rounded shadow-sm" style="max-height:120px;cursor:pointer" title="Click to enlarge" onerror="this.classList.add('d-none'); this.insertAdjacentHTML('afterend', '<div class=\\'text-muted small\\'>Snapshot file not found</div>')">
      </div>
    `;
  }

  function viewAlarmDetail(alarmId) {
    currentAlarmId = alarmId;

    $.ajax({
      url: '<?= base_url("alarmlogs/get_alarm_detail") ?>',
      type: 'GET',
      dataType: 'json',
      data: {
        alarm_id: alarmId
      },
      success: function(data) {
        if (data && data.success === true) {
          renderAlarmDetail(data);
          $('#alarmDetailModal').modal('show');
        } else {
          alert('Error loading alarm details: ' + (data.message || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        alert('Failed to load alarm details: ' + error);
      }
    });
  }

  function renderAlarmDetail(data) {
    const alarm = data.alarm;

    // Render alarm information
    $('#alarmInfo').html(`
      <table class="table table-sm">
        <tr><td><strong>ID:</strong></td><td>${alarm.id}</td></tr>
        <tr><td><strong>Created:</strong></td><td>${formatDateTime(alarm.created_at)}</td></tr>
        <tr><td><strong>Updated:</strong></td><td>${alarm.updated_at ? formatDateTime(alarm.updated_at) : '-'}</td></tr>
        <tr><td><strong>Metric:</strong></td><td><code>${alarm.metric_key}</code></td></tr>
        <tr><td><strong>Value:</strong></td><td><code>${alarm.value}</code></td></tr>
        <tr><td><strong>Severity:</strong></td><td><span class="badge ${getSeverityBadgeClass(alarm.severity)}">${alarm.severity}</span></td></tr>
        <tr><td><strong>Status:</strong></td><td><span class="badge ${getStatusBadgeClass(alarm.status)}">${alarm.status}</span></td></tr>
        <tr><td><strong>Message:</strong></td><td>${escapeHtml(alarm.message)}</td></tr>
        ${alarm.note ? `<tr><td><strong>Note:</strong></td><td>${escapeHtml(alarm.note)}</td></tr>` : ''}
        ${alarm.snapshot_path ? `<tr><td><strong>Snapshot:</strong></td><td>${renderSnapshotDetail(alarm.snapshot_path, alarm.snapshot_camera_device_id)}</td></tr>` : ''}
      </table>
    `);

    // Render device information
    let deviceInfo = '<p class="text-muted">No device information available</p>';
    if (alarm.device_name) {
      deviceInfo = `
        <table class="table table-sm">
          <tr><td><strong>Name:</strong></td><td>${alarm.device_name}</td></tr>
          <tr><td><strong>ID:</strong></td><td>${alarm.device_id}</td></tr>
        </table>
      `;
    }
    $('#deviceInfo').html(deviceInfo);

    // Render related alarms
    let relatedAlarmsHtml = '<p class="text-muted">No related alarms found</p>';
    if (data.related_alarms && data.related_alarms.length > 0) {
      relatedAlarmsHtml = '<div class="list-group list-group-flush">';
      data.related_alarms.forEach(function(related) {
        relatedAlarmsHtml += `
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <small class="text-muted">${formatDateTime(related.created_at)}</small><br>
              <span class="badge ${getSeverityBadgeClass(related.severity)}">${related.severity}</span>
              <span class="badge ${getStatusBadgeClass(related.status)}">${related.status}</span>
            </div>
            <button class="btn btn-sm btn-outline-primary" onclick="viewAlarmDetail(${related.id})">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        `;
      });
      relatedAlarmsHtml += '</div>';
    }
    $('#relatedAlarms').html(relatedAlarmsHtml);

    // Render dispatch history
    let dispatchHistoryHtml = '<p class="text-muted">No notification history found</p>';
    if (data.dispatch_history && data.dispatch_history.length > 0) {
      dispatchHistoryHtml = '<div class="list-group list-group-flush">';
      data.dispatch_history.forEach(function(dispatch) {
        dispatchHistoryHtml += `
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <strong>${dispatch.channel_name || 'Unknown Channel'}</strong><br>
                <small class="text-muted">${formatDateTime(dispatch.created_at)}</small>
              </div>
              <span class="badge ${dispatch.status === 'success' ? 'bg-success' : 'bg-danger'}">${dispatch.status}</span>
            </div>
            ${dispatch.error ? `<small class="text-danger">${dispatch.error}</small>` : ''}
          </div>
        `;
      });
      dispatchHistoryHtml += '</div>';
    }
    $('#dispatchHistory').html(dispatchHistoryHtml);

    // Update action buttons visibility
    $('#acknowledgeBtn').toggle(alarm.status === 'active');
    $('#resolveBtn').toggle(alarm.status === 'acknowledged');
  }

  function acknowledgeAlarm(alarmId) {
    currentAlarmId = alarmId;
    $('#acknowledgeAlarmId').val(alarmId);
    $('#acknowledgeNote').val('');
    $('#acknowledgeModal').modal('show');
  }

  function resolveAlarm(alarmId) {
    currentAlarmId = alarmId;
    $('#resolveAlarmId').val(alarmId);
    $('#resolveNote').val('');
    $('#resolveModal').modal('show');
  }

  function submitAcknowledge() {
    const alarmId = $('#acknowledgeAlarmId').val();
    const note = $('#acknowledgeNote').val();

    $.ajax({
      url: '<?= base_url("alarmlogs/acknowledge") ?>',
      type: 'POST',
      dataType: 'json',
      data: {
        alarm_id: alarmId,
        note: note
      },
      success: function(data) {
        if (data && data.success === true) {
          $('#acknowledgeModal').modal('hide');
          $('#alarmDetailModal').modal('hide');
          loadAlarmLogs(currentPage);
          loadStatistics();
          showToast('success', 'Alarm acknowledged successfully');
        } else {
          showToast('error', 'Failed to acknowledge alarm: ' + (data.message || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        showToast('error', 'Failed to acknowledge alarm: ' + error);
      }
    });
  }

  function submitResolve() {
    const alarmId = $('#resolveAlarmId').val();
    const note = $('#resolveNote').val();

    if (!note.trim()) {
      showToast('error', 'Resolution note is required');
      return;
    }

    $.ajax({
      url: '<?= base_url("alarmlogs/resolve") ?>',
      type: 'POST',
      dataType: 'json',
      data: {
        alarm_id: alarmId,
        resolution_note: note
      },
      success: function(data) {
        if (data && data.success === true) {
          $('#resolveModal').modal('hide');
          $('#alarmDetailModal').modal('hide');
          loadAlarmLogs(currentPage);
          loadStatistics();
          showToast('success', 'Alarm resolved successfully');
        } else {
          showToast('error', 'Failed to resolve alarm: ' + (data.message || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        showToast('error', 'Failed to resolve alarm: ' + error);
      }
    });
  }

  function bulkAcknowledge() {
    const selectedAlarms = $('.alarm-checkbox:checked').map(function() {
      return $(this).val();
    }).get();

    if (selectedAlarms.length === 0) {
      showToast('warning', 'Please select alarms to acknowledge');
      return;
    }

    $('#bulkAcknowledgeCount').text(selectedAlarms.length);
    $('#bulkAcknowledgeNote').val('');
    $('#bulkAcknowledgeModal').modal('show');
  }

  function bulkResolve() {
    const selectedAlarms = $('.alarm-checkbox:checked').map(function() {
      return $(this).val();
    }).get();

    if (selectedAlarms.length === 0) {
      showToast('warning', 'Please select alarms to resolve');
      return;
    }

    $('#bulkResolveCount').text(selectedAlarms.length);
    $('#bulkResolveNote').val('');
    $('#bulkResolveModal').modal('show');
  }

  function submitBulkAcknowledge() {
    const selectedAlarms = $('.alarm-checkbox:checked').map(function() {
      return $(this).val();
    }).get();
    const note = $('#bulkAcknowledgeNote').val();

    $.ajax({
      url: '<?= base_url("alarmlogs/bulk_acknowledge") ?>',
      type: 'POST',
      dataType: 'json',
      data: {
        alarm_ids: selectedAlarms,
        note: note
      },
      success: function(data) {
        if (data && data.success === true) {
          $('#bulkAcknowledgeModal').modal('hide');
          loadAlarmLogs(currentPage);
          loadStatistics();
          showToast('success', data.message);
        } else {
          showToast('error', 'Failed to acknowledge alarms: ' + (data.message || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        showToast('error', 'Failed to acknowledge alarms: ' + error);
      }
    });
  }

  function submitBulkResolve() {
    const selectedAlarms = $('.alarm-checkbox:checked').map(function() {
      return $(this).val();
    }).get();
    const note = $('#bulkResolveNote').val();

    if (!note.trim()) {
      showToast('error', 'Resolution note is required');
      return;
    }

    $.ajax({
      url: '<?= base_url("alarmlogs/bulk_resolve") ?>',
      type: 'POST',
      dataType: 'json',
      data: {
        alarm_ids: selectedAlarms,
        resolution_note: note
      },
      success: function(data) {
        if (data && data.success === true) {
          $('#bulkResolveModal').modal('hide');
          loadAlarmLogs(currentPage);
          loadStatistics();
          showToast('success', data.message);
        } else {
          showToast('error', 'Failed to resolve alarms: ' + (data.message || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        showToast('error', 'Failed to resolve alarms: ' + error);
      }
    });
  }

  function showToast(type, message) {
    // Simple toast implementation - you can replace with your preferred toast library
    const toastClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-warning';
    const toastHtml = `
      <div class="alert ${toastClass} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    `;
    $('body').append(toastHtml);

    // Auto remove after 5 seconds
    setTimeout(function() {
      $('.alert').fadeOut();
    }, 5000);
  }

  function initEventHandlers() {
    $(document).on('click', '.alarm-snapshot-thumb', function(e) {
      e.preventDefault();
      e.stopPropagation();
      viewSnapshot(this.getAttribute('data-snapshot') || this.getAttribute('src'));
    });
  }

  function showBsModal(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    if (window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(el).show();
      return;
    }
    if (window.jQuery) {
      jQuery(el).modal('show');
    }
  }

  function hideBsModal(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    if (window.bootstrap && bootstrap.Modal) {
      const instance = bootstrap.Modal.getInstance(el);
      if (instance) instance.hide();
      return;
    }
    if (window.jQuery) {
      jQuery(el).modal('hide');
    }
  }
</script>