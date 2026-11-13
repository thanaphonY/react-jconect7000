<?php
$groups = isset($groups) && is_array($groups) ? $groups : [];
$devices = isset($devices) && is_array($devices) ? $devices : [];
$sources = isset($sources) && is_array($sources) ? $sources : [];
$results = isset($results) && is_array($results) ? $results : [];
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5><?php echo lang('controllogs'); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('controllogs') ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title"><?php echo lang('total_logs'); ?></h6>
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
                <h6 class="card-title"><?php echo lang('written'); ?></h6>
                <h3 id="writtenCount">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-check-circle fs-1"></i>
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
                <h6 class="card-title"><?php echo lang('failed'); ?></h6>
                <h3 id="failedCount">-</h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-x-circle fs-1"></i>
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
                <h6 class="card-title"><?php echo lang('devices'); ?></h6>
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

    <div class="card">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-funnel"></i> <?php echo lang('filters'); ?></h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="row">
            <div class="col-md-3">
              <label class="form-label"><?php echo lang('date_time'); ?></label>
              <select id="dateFilter" class="form-select form-select-sm">
                <option value="all"><?php echo lang('all'); ?></option>
                <option value="today"><?php echo lang('today'); ?></option>
                <option value="yesterday"><?php echo lang('yesterday'); ?></option>
                <option value="last_7_days"><?php echo lang('last_7_days'); ?></option>
                <option value="last_30_days" selected><?php echo lang('last_30_days'); ?></option>
                <option value="custom"><?php echo lang('custom_range'); ?></option>
              </select>
            </div>
            <div class="col-md-2 custom-date-col d-none">
              <label class="form-label"><?php echo lang('start_date'); ?></label>
              <input type="date" id="startDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 custom-date-col d-none">
              <label class="form-label"><?php echo lang('end_date'); ?></label>
              <input type="date" id="endDate" class="form-control form-control-sm">
            </div>
          </div>
          <div class="row">
            <div class="col-md-2">
              <label class="form-label"><?php echo lang('group'); ?></label>
              <select id="groupFilter" class="form-select form-select-sm">
                <option value="all"><?php echo lang('all_groups'); ?></option>
                <option value="ungrouped"><?php echo lang('ungrouped'); ?></option>
                <?php foreach ($groups as $group): ?>
                  <option value="<?= (int) $group->id ?>"><?= htmlspecialchars($group->name) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label"><?php echo lang('device'); ?></label>
              <select id="deviceFilter" class="form-select form-select-sm">
                <option value="all"><?php echo lang('all_devices'); ?></option>
                <?php foreach ($devices as $device): ?>
                  <option value="<?= (int) $device->id ?>"><?= htmlspecialchars($device->name) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label"><?php echo lang('source'); ?></label>
              <select id="sourceFilter" class="form-select form-select-sm">
                <option value="all"><?php echo lang('all_sources'); ?></option>
                <?php foreach ($sources as $source): ?>
                  <option value="<?= htmlspecialchars($source->source) ?>"><?= htmlspecialchars(ucfirst($source->source)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label"><?php echo lang('result'); ?></label>
              <select id="resultFilter" class="form-select form-select-sm">
                <option value="all"><?php echo lang('all_results'); ?></option>
                <?php foreach ($results as $result): ?>
                  <option value="<?= htmlspecialchars($result->result) ?>"><?= htmlspecialchars(ucfirst($result->result)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label"><?php echo lang('search'); ?></label>
              <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="<?php echo lang('search_placeholder'); ?>">
            </div>
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

    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-toggles"></i> <?php echo lang('controllogs'); ?></h6>
          <div class="d-flex align-items-center">
            <div class="d-flex align-items-center me-3">
              <label for="pageSizeSelector" class="me-2 mb-0">Show:</label>
              <select class="form-select form-select-sm" id="pageSizeSelector" style="width: auto;">
                <option value="10" selected>10</option>
                <option value="20">20</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
              <span class="ms-2 mb-0">entries per page</span>
            </div>
            <button type="button" class="btn btn-info btn-sm" id="previewExportBtn">
              <i class="bi bi-eye"></i> <?php echo lang('preview_export'); ?>
            </button>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover" id="controlLogTable">
            <thead>
              <tr>
                <th><?php echo lang('date_time'); ?></th>
                <th><?php echo lang('command'); ?></th>
                <th><?php echo lang('device'); ?></th>
                <th><?php echo lang('group'); ?></th>
                <th><?php echo lang('source'); ?></th>
                <th><?php echo lang('value'); ?></th>
                <th><?php echo lang('result'); ?></th>
                <th><?php echo lang('user'); ?></th>
              </tr>
            </thead>
            <tbody id="controlLogTableBody">
              <tr>
                <td colspan="8" class="text-center">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-center mt-3">
          <nav aria-label="Control logs pagination">
            <ul class="pagination justify-content-center mb-0" id="pagination"></ul>
          </nav>
        </div>
        <div class="text-center mt-2">
          <small class="text-muted" id="paginationInfo"></small>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  let currentPage = 1;
  let pageSize = 10;
  const noDataText = <?= json_encode(lang('no_data')) ?>;
  const ungroupedText = <?= json_encode(lang('ungrouped')) ?>;
  const onText = <?= json_encode(lang('value_on')) ?>;
  const offText = <?= json_encode(lang('value_off')) ?>;

  $(document).ready(function() {
    if (window.dayjs) {
      dayjs.extend(dayjs_plugin_utc);
      dayjs.extend(dayjs_plugin_timezone);
    }

    loadStatistics();
    loadControlLogs();

    $('#dateFilter').on('change', function() {
      $('.custom-date-col').toggleClass('d-none', $(this).val() !== 'custom');
    }).trigger('change');

    $('#searchInput').on('keyup', function(e) {
      if (e.key === 'Enter') {
        loadControlLogs(1);
        loadStatistics();
      }
    });

    $('#applyFiltersBtn').on('click', function(e) {
      e.preventDefault();
      loadControlLogs(1);
      loadStatistics();
    });

    $('#pageSizeSelector').on('change', function() {
      pageSize = parseInt($(this).val(), 10);
      loadControlLogs(1);
    });

    $('#previewExportBtn').on('click', function() {
      const params = new URLSearchParams(filterPayload());
      window.open('<?= base_url("controllogs/export_preview") ?>?' + params.toString(), '_blank');
    });
  });

  function filterPayload() {
    return {
      range: $('#dateFilter').val() || 'last_30_days',
      startDate: $('#startDate').val(),
      endDate: $('#endDate').val(),
      group_filter: $('#groupFilter').val(),
      device_filter: $('#deviceFilter').val(),
      source_filter: $('#sourceFilter').val(),
      result_filter: $('#resultFilter').val(),
      search: $('#searchInput').val()
    };
  }

  function loadStatistics() {
    const params = new URLSearchParams(filterPayload());
    $.ajax({
      url: '<?= base_url("controllogs/get_statistics") ?>?' + params.toString(),
      type: 'GET',
      dataType: 'json',
      success: function(data) {
        if (data && data.success && data.stats) {
          $('#totalLogs').text(data.stats.total_logs || 0);
          $('#writtenCount').text(data.stats.written_count || 0);
          $('#failedCount').text(data.stats.failed_count || 0);
          $('#deviceCount').text(data.stats.device_count || 0);
        }
      }
    });
  }

  function loadControlLogs(page = 1) {
    currentPage = page;
    $('#controlLogTableBody').html(`
      <tr>
        <td colspan="8" class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </td>
      </tr>
    `);

    $.ajax({
      url: '<?= base_url("controllogs/get_logs") ?>',
      type: 'POST',
      dataType: 'json',
      data: Object.assign({ page: page, pageSize: pageSize }, filterPayload()),
      success: function(data) {
        if (data && data.success && data.logs) {
          renderLogs(data.logs);
          renderPagination(data.totalPages, data.currentPage, data.totalRecords);
        } else {
          $('#controlLogTableBody').html(`
            <tr><td colspan="8" class="text-center text-danger">${escapeHtml(data.message || 'Error loading data')}</td></tr>
          `);
        }
      },
      error: function(xhr, status, error) {
        $('#controlLogTableBody').html(`
          <tr><td colspan="8" class="text-center text-danger">Failed to load data: ${escapeHtml(error)}</td></tr>
        `);
      }
    });
  }

  function renderLogs(logs) {
    if (!logs || logs.length === 0) {
      $('#controlLogTableBody').html(`
        <tr><td colspan="8" class="text-center text-muted">${escapeHtml(noDataText)}</td></tr>
      `);
      return;
    }

    let html = '';
    logs.forEach(function(log) {
      const extra = [];
      if (log.sequence_name) extra.push(escapeHtml(log.sequence_name));
      if (log.rule_name) extra.push(escapeHtml(log.rule_name));
      html += `
        <tr>
          <td>${formatDateTime(log.created_at)}</td>
          <td>${escapeHtml(log.command_name || ('#' + (log.command_id || '')))}</td>
          <td>${escapeHtml(log.device_name || '-')}</td>
          <td>${escapeHtml(log.group_name || ungroupedText)}</td>
          <td>
            <span class="badge ${sourceBadge(log.source)}">${escapeHtml(log.source || '-')}</span>
            ${extra.length ? `<div class="small text-muted">${extra.join(' / ')}</div>` : ''}
          </td>
          <td>${formatValue(log.requested_value)}</td>
          <td>
            <span class="badge ${resultBadge(log.result)}">${escapeHtml(log.result || '-')}</span>
            ${log.error ? `<div class="small text-muted">${escapeHtml(log.error)}</div>` : ''}
          </td>
          <td>${escapeHtml(log.user_name || '-')}</td>
        </tr>
      `;
    });
    $('#controlLogTableBody').html(html);
  }

  function renderPagination(totalPages, currentPage, totalRecords) {
    const startRecord = totalRecords === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const endRecord = Math.min(currentPage * pageSize, totalRecords);
    $('#paginationInfo').html(`Showing ${startRecord} to ${endRecord} of ${totalRecords} entries`);

    if (totalPages <= 1) {
      $('#pagination').html('');
      return;
    }

    let html = `
      <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadControlLogs(${currentPage - 1}); return false;">Previous</a>
      </li>
    `;
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    if (startPage > 1) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="loadControlLogs(1); return false;">1</a></li>`;
      if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    for (let i = startPage; i <= endPage; i++) {
      html += `
        <li class="page-item ${i === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" onclick="loadControlLogs(${i}); return false;">${i}</a>
        </li>
      `;
    }
    if (endPage < totalPages) {
      if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      html += `<li class="page-item"><a class="page-link" href="#" onclick="loadControlLogs(${totalPages}); return false;">${totalPages}</a></li>`;
    }
    html += `
      <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadControlLogs(${currentPage + 1}); return false;">Next</a>
      </li>
    `;
    $('#pagination').html(html);
  }

  function formatValue(value) {
    const v = String(value == null ? '' : value).trim().toLowerCase();
    if (v === '1' || v === 'on' || v === 'true') {
      return `<span class="badge text-bg-success">${escapeHtml(onText)}</span>`;
    }
    if (v === '0' || v === 'off' || v === 'false') {
      return `<span class="badge text-bg-secondary">${escapeHtml(offText)}</span>`;
    }
    return value ? `<code>${escapeHtml(value)}</code>` : '-';
  }

  function formatDateTime(dateTime) {
    if (!dateTime) return '-';
    if (window.dayjs) {
      return dayjs.utc(dateTime).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss');
    }
    return dateTime;
  }

  function sourceBadge(source) {
    switch ((source || '').toLowerCase()) {
      case 'manual': return 'text-bg-primary';
      case 'schedule': return 'text-bg-info';
      case 'sequence': return 'text-bg-warning';
      case 'rule': return 'text-bg-success';
      default: return 'text-bg-secondary';
    }
  }

  function resultBadge(result) {
    switch ((result || '').toLowerCase()) {
      case 'written': return 'text-bg-success';
      case 'failed': return 'text-bg-danger';
      case 'pending': return 'text-bg-warning';
      default: return 'text-bg-secondary';
    }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
</script>
