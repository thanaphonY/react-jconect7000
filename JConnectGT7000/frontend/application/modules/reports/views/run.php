<?php defined('BASEPATH') or exit('No direct script access allowed');
$export_inputs = isset($export_inputs) ? $export_inputs : [];
$template = isset($template) ? $template : null;
$filters_config = isset($filters_config) ? $filters_config : [];
$data_source = isset($data_source) ? $data_source : 'device_data';
$filter_devices = [];
$filter_metrics = [];
if (!empty($filters_config['selected_device_metrics'])) {
  $seen = [];
  foreach ($filters_config['selected_device_metrics'] as $dm) {
    $id = isset($dm['device_id']) ? $dm['device_id'] : null;
    $name = isset($dm['device_name']) ? $dm['device_name'] : '';
    if ($id && !isset($seen['d'][$id])) {
      $seen['d'][$id] = true;
      $filter_devices[] = ['id' => $id, 'name' => $name];
    }
    $m = isset($dm['metric']) ? $dm['metric'] : '';
    if ($m !== '' && !isset($seen['m'][$m])) {
      $seen['m'][$m] = true;
      $filter_metrics[] = $m;
    }
  }
} else {
  $filter_devices = isset($filters_config['selected_devices_detail']) ? $filters_config['selected_devices_detail'] : [];
  $filter_metrics = isset($filters_config['selected_metrics']) ? $filters_config['selected_metrics'] : [];
}
if (!is_array($filter_devices)) $filter_devices = [];
if (!is_array($filter_metrics)) $filter_metrics = [];

$time_range_labels = [
  'input_date_range' => 'Use Input date range (enter below)',
  'last_6h' => 'Last 6 Hours',
  'last_12h' => 'Last 12 Hours',
  'last_24h' => 'Last 24 Hours',
  'today' => 'Today',
  'yesterday' => 'Yesterday',
  'last_3d' => 'Last 3 Days',
  'last_7d' => 'Last 7 Days',
  'this_week' => 'This Week',
  'last_week' => 'Last Week',
  'this_month' => 'This Month',
  'last_month' => 'Last Month',
  'last_3m' => 'Last 3 Months'
];
$time_interval_labels = [
  '1m' => '1 Minute',
  '5m' => '5 Minutes',
  '10m' => '10 Minutes',
  '15m' => '15 Minutes',
  '30m' => '30 Minutes',
  '1h' => '1 Hour',
  '4h' => '4 Hours',
  '12h' => '12 Hours',
  '24h' => '24 Hours',
  '1d' => '1 Day'
];
$display_labels = ['min' => 'Min', 'max' => 'Max', 'average' => 'Average', 'first' => 'First', 'last' => 'Last'];
$time_range_val = $filters_config['time_range'] ?? 'last_24h';
$time_interval_val = $filters_config['time_interval'] ?? '1h';
$display_vals = isset($filters_config['display']) && is_array($filters_config['display']) ? $filters_config['display'] : ['min', 'max', 'average'];
$editor = isset($config['editor']) ? $config['editor'] : [];
$report_title = $editor['report_title'] ?? '';
$display_columns = isset($editor['display_columns']) && is_array($editor['display_columns']) ? $editor['display_columns'] : [];
$pdf_orientation = $editor['pdf_orientation'] ?? 'portrait';
$pdf_title = $editor['pdf_title'] ?? '';
$excel_sheet_name = $editor['excel_sheet_name'] ?? 'Sheet1';
$csv_delimiter = $editor['csv_delimiter'] ?? ',';
$table_layout = isset($editor['table_layout']) && $editor['table_layout'] === 'pivot' ? 'pivot' : 'rows';
$sort_order = isset($filters_config['sort_order']) && $filters_config['sort_order'] === 'asc' ? 'asc' : 'desc';
if ($csv_delimiter === 'tab') $csv_delimiter = "\t";
$has_date_range_input = false;
foreach ($export_inputs as $in) {
  if (($in['type'] ?? '') === 'date_range') {
    $has_date_range_input = true;
    break;
  }
}
$show_date_range_for_processing = ($data_source === 'device_data' || $data_source === 'alarm_logs') && $time_range_val === 'input_date_range' && !$has_date_range_input;
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><?= $template ? htmlspecialchars($template->name) : lang('reports_run') ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>"><?= lang('reports_builder') ?></a></li>
            <li class="breadcrumb-item active"><?= $template ? htmlspecialchars($template->name) : 'Run' ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div id="alertContainer"></div>

    <?php if ($data_source === 'device_data' || $data_source === 'alarm_logs'): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-funnel"></i> <?= lang('template_filters') ?></h6>
        </div>
        <div class="card-body py-3">
          <?php if (!empty($filters_config['selected_device_metrics'])): ?>
            <p class="small text-muted mb-2"><?= lang('devices_metrics_used') ?></p>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($filters_config['selected_device_metrics'] as $dm):
                $dname = isset($dm['device_name']) ? $dm['device_name'] : ('ID ' . ($dm['device_id'] ?? ''));
                $mkey = isset($dm['metric']) ? $dm['metric'] : '';
                $mlabel = isset($dm['metric_label']) ? $dm['metric_label'] : $mkey;
                $label = trim($dname) ? $dname . ' – ' . $mlabel : $mlabel;
              ?>
                <span class="badge bg-secondary"><?= htmlspecialchars($label) ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="small text-muted mb-0"><?= lang('use_all_devices_metrics') ?></p>
          <?php endif; ?>
        </div>
      </div>
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-gear"></i> <?= lang('data_processing') ?></h6>
        </div>
        <div class="card-body py-3">
          <div class="row small">
            <div class="col-md-4">
              <span class="text-muted"><?= lang('time_range') ?>:</span>
              <strong><?= htmlspecialchars($time_range_labels[$time_range_val] ?? $time_range_val) ?></strong>
            </div>
            <div class="col-md-4">
              <span class="text-muted"><?= lang('time_interval') ?>:</span>
              <strong><?= htmlspecialchars($time_interval_labels[$time_interval_val] ?? $time_interval_val) ?></strong>
            </div>
            <div class="col-md-4">
              <span class="text-muted"><?= lang('display') ?>:</span>
              <strong><?= implode(', ', array_map(function ($k) use ($display_labels) {
                        return $display_labels[$k] ?? $k;
                      }, $display_vals)) ?></strong>
            </div>
          </div>
        </div>
      </div>
    <?php elseif ($data_source === 'event_logs'): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-funnel"></i> <?= lang('template_filters_event_logs') ?></h6>
        </div>
        <div class="card-body py-3">
          <div class="row small">
            <?php if (!empty($filters_config['action_type'])): ?>
              <div class="col-auto"><span class="badge bg-secondary">Action Type</span></div>
            <?php endif; ?>
            <?php if (!empty($filters_config['entity_type'])): ?>
              <div class="col-auto"><span class="badge bg-secondary">Entity Type</span></div>
            <?php endif; ?>
            <?php if (!empty($filters_config['entity'])): ?>
              <div class="col-auto"><span class="badge bg-secondary">Entity (Device)</span></div>
            <?php endif; ?>
            <?php if (empty($filters_config['action_type']) && empty($filters_config['entity_type']) && empty($filters_config['entity'])): ?>
              <p class="text-muted mb-0">No specific filters – use inputs below</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-input-cursor"></i> Enter data for Export</h6>
      </div>
      <div class="card-body">
        <form id="runForm">
          <input type="hidden" name="template_id" value="<?= $template ? (int)$template->id : '' ?>">
          <?php if ($show_date_range_for_processing): ?>
            <div class="alert alert-info py-2 mb-3">
              <label class="form-label small mb-1">Date range (for Data processing)</label>
              <div class="row g-2">
                <div class="col-md-3">
                  <input type="date" class="form-control form-control-sm" name="filters[start_date]" placeholder="Start date" required>
                </div>
                <div class="col-md-3">
                  <input type="date" class="form-control form-control-sm" name="filters[end_date]" placeholder="End date" required>
                </div>
              </div>
            </div>
          <?php endif; ?>
          <div class="row">
            <?php foreach ($export_inputs as $i => $in):
              $type = $in['type'] ?? 'date';
              $label = $in['label'] ?? $type;
              $required = !empty($in['required']);
            ?>
              <div class="col-md-6 col-lg-4 mb-3">
                <label class="form-label"><?= htmlspecialchars($label) ?> <?= $required ? '<span class="text-danger">*</span>' : '' ?></label>
                <?php if ($type === 'date'): ?>
                  <input type="date" class="form-control" name="filters[date]" <?= $required ? 'required' : '' ?>>
                <?php elseif ($type === 'month'): ?>
                  <input type="month" class="form-control" name="filters[month]" <?= $required ? 'required' : '' ?>>
                <?php elseif ($type === 'year'): ?>
                  <select class="form-select" name="filters[year]" <?= $required ? 'required' : '' ?>>
                    <option value="">-- Select year --</option>
                    <?php for ($y = date('Y'); $y >= date('Y') - 10; $y--): ?>
                      <option value="<?= $y ?>"><?= $y + 543 ?></option>
                    <?php endfor; ?>
                  </select>
                <?php elseif ($type === 'date_range'): ?>
                  <div class="row g-1">
                    <div class="col-6">
                      <input type="date" class="form-control" name="filters[start_date]" placeholder="Start" <?= $required ? 'required' : '' ?>>
                    </div>
                    <div class="col-6">
                      <input type="date" class="form-control" name="filters[end_date]" placeholder="End" <?= $required ? 'required' : '' ?>>
                    </div>
                  </div>
                <?php elseif ($type === 'device'): ?>
                  <select class="form-select select2-device" name="filters[device_id]" <?= $required ? 'required' : '' ?>>
                    <option value="">-- Select device --</option>
                  </select>
                <?php elseif ($type === 'metric'): ?>
                  <select class="form-select select2-metric" name="filters[metric]" <?= $required ? 'required' : '' ?>>
                    <option value="">-- Select Metric --</option>
                  </select>
                <?php elseif ($type === 'room'): ?>
                  <select class="form-select select2-room" name="filters[room]" <?= $required ? 'required' : '' ?>>
                    <option value="">-- Select room --</option>
                  </select>
                <?php elseif ($type === 'action_type'): ?>
                  <select class="form-select select2-action-type" name="filters[action_type]" <?= $required ? 'required' : '' ?>>
                    <option value="">-- Select Action Type --</option>
                  </select>
                <?php elseif ($type === 'entity_type'): ?>
                  <select class="form-select select2-entity-type" name="filters[entity_type]" <?= $required ? 'required' : '' ?>>
                    <option value="">-- Select Entity Type --</option>
                  </select>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (empty($export_inputs)): ?>
            <p class="text-muted"> </p>
          <?php endif; ?>
          <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
            <button type="submit" class="btn btn-primary" id="btnGetData">
              <i class="bi bi-database"></i> Fetch data
            </button>
            <span id="exportButtonsWrap" style="display:none;">
              <button type="button" class="btn btn-outline-success" id="btnExportExcel">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
              </button>
              <button type="button" class="btn btn-outline-secondary" id="btnExportCsv">
                <i class="bi bi-filetype-csv"></i> Export CSV
              </button>
              <button type="button" class="btn btn-outline-danger" id="btnPreviewPdf">
                <i class="bi bi-eye"></i> Preview PDF
              </button>
              <button type="button" class="btn btn-outline-danger" id="btnExportPdf">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
              </button>
              <div class="form-check form-check-inline ms-2">
                <input class="form-check-input" type="checkbox" id="chkSaveToFileManager" value="1">
                <label class="form-check-label small" for="chkSaveToFileManager"><?= lang('save_to_file_manager') ?></label>
              </div>
            </span>
          </div>
        </form>
      </div>
    </div>

    <div class="card mt-4 shadow-sm" id="dataPreviewCard" style="display:none;">
      <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="bi bi-table"></i> <span id="dataPreviewTitle"><?= htmlspecialchars($report_title ?: 'Sample data') ?></span></h6>
        <?php if ($report_title): ?>
          <p class="small mb-0 mt-1 opacity-90">Sample data according to columns defined in Template</p>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div id="dataPreviewContent"></div>
      </div>
    </div>
  </div>
</div>

<script>
  (function() {
    var templateId = <?= $template ? (int)$template->id : 0 ?>;
    var baseUrl = '<?= base_url() ?>';
    var filterDevices = <?= json_encode($filter_devices) ?>;
    var filterMetrics = <?= json_encode($filter_metrics) ?>;
    var selectedDeviceMetricsOrder = <?= json_encode($filters_config['selected_device_metrics'] ?? []) ?>;
    var displayColumns = <?= json_encode($display_columns) ?>;
    var reportTitle = <?= json_encode($report_title) ?>;
    var excelSheetName = <?= json_encode($excel_sheet_name) ?>;
    var csvDelimiter = <?= json_encode($csv_delimiter) ?>;
    var pdfTitle = <?= json_encode($pdf_title ?: $report_title) ?>;
    var pdfOrientation = <?= json_encode($pdf_orientation) ?>;
    var tableLayout = <?= json_encode($table_layout) ?>;
    var sortOrder = <?= json_encode($sort_order) ?>;
    var currentReportData = [];

    function loadDevices() {
      var $sel = $('.select2-device');
      if (filterDevices && filterDevices.length > 0) {
        filterDevices.forEach(function(d) {
          var id = typeof d === 'object' ? (d.id || d) : d;
          var name = typeof d === 'object' ? (d.name || '') : '';
          $sel.append($('<option>').val(id).text(name || ('ID ' + id)));
        });
      } else {
        $.get(baseUrl + 'reports/get_devices').done(function(res) {
          if (res.success && res.data && res.data.length) {
            res.data.forEach(function(d) {
              $sel.append($('<option>').val(d.id).text((d.name || '') + (d.location ? ' - ' + d.location : '')));
            });
          }
        });
      }
    }

    function loadMetrics() {
      var $sel = $('.select2-metric');
      if (filterMetrics && filterMetrics.length > 0) {
        filterMetrics.forEach(function(m) {
          var val = typeof m === 'string' ? m : (m && m.key) ? m.key : m;
          $sel.append($('<option>').val(val).text(val));
        });
      } else {
        $.get(baseUrl + 'reports/get_metrics').done(function(res) {
          if (res.success && res.data && res.data.length) {
            res.data.forEach(function(m) {
              $sel.append($('<option>').val(m).text(m));
            });
          }
        });
      }
    }

    function loadRooms() {
      $.get(baseUrl + 'reports/get_rooms').done(function(res) {
        if (res.success && res.data && res.data.length) {
          var $sel = $('.select2-room');
          res.data.forEach(function(r) {
            $sel.append($('<option>').val(r).text(r));
          });
        }
      });
    }
    if ($('.select2-device').length) loadDevices();
    if ($('.select2-metric').length) loadMetrics();
    if ($('.select2-room').length) loadRooms();

    function loadActionTypes() {
      $.get(baseUrl + 'reports/get_action_types').done(function(res) {
        if (res.success && res.data && res.data.length) {
          var $sel = $('.select2-action-type');
          res.data.forEach(function(a) {
            $sel.append($('<option>').val(a).text(a));
          });
        }
      });
    }

    function loadEntityTypes() {
      $.get(baseUrl + 'reports/get_entity_types').done(function(res) {
        if (res.success && res.data && res.data.length) {
          var $sel = $('.select2-entity-type');
          res.data.forEach(function(e) {
            $sel.append($('<option>').val(e).text(e));
          });
        }
      });
    }
    if ($('.select2-action-type').length) loadActionTypes();
    if ($('.select2-entity-type').length) loadEntityTypes();

    // Use config from builder (displayColumns) to define order/set of value_* columns for Pivot mode
    var pivotValueKeys = ['value_min', 'value_max', 'value_avg', 'value_first', 'value_last'];
    var pivotDefaultLabels = {
      value_min: 'Min',
      value_max: 'Max',
      value_avg: 'Average',
      value_first: 'First',
      value_last: 'Last'
    };

    function getPivotSubColumns() {
      var cols = [];
      if (displayColumns && displayColumns.length > 0) {
        displayColumns.forEach(function(c) {
          if (pivotValueKeys.indexOf(c.key) !== -1) {
            cols.push({
              key: c.key,
              label: c.label || pivotDefaultLabels[c.key] || c.key
            });
          }
        });
      }
      // If Editor has no value_* selected, fallback to Min/Max/Avg in standard order
      if (cols.length === 0) {
        ['value_min', 'value_max', 'value_avg'].forEach(function(k) {
          cols.push({
            key: k,
            label: pivotDefaultLabels[k]
          });
        });
      }
      return cols;
    }

    function getTimeHeaderLabel() {
      if (displayColumns && displayColumns.length) {
        for (var i = 0; i < displayColumns.length; i++) {
          if (displayColumns[i].key === 'timestamp') {
            return displayColumns[i].label || 'Time';
          }
        }
      }
      return 'Time';
    }

    // Use identity field order (device_name, metric_label, device_id, key, metric_key) from Builder
    function getIdentityKeysOrder() {
      var idKeys = ['device_name', 'metric_label', 'device_id', 'key', 'metric_key'];
      if (!displayColumns || !displayColumns.length) return idKeys;
      var seen = {};
      var order = [];
      displayColumns.forEach(function(c) {
        if (idKeys.indexOf(c.key) !== -1 && !seen[c.key]) {
          seen[c.key] = true;
          order.push(c.key);
        }
      });
      return order.length ? order : idKeys;
    }

    function getColumnsForData(data) {
      if (displayColumns && displayColumns.length > 0) {
        return displayColumns.filter(function(c) {
          return data.length === 0 || data[0].hasOwnProperty(c.key);
        });
      }
      var keys = [];
      var seen = {};
      data.forEach(function(row) {
        Object.keys(row).forEach(function(k) {
          if (!seen[k]) {
            seen[k] = true;
            keys.push(k);
          }
        });
      });
      return keys.map(function(k) {
        return {
          key: k,
          label: k
        };
      });
    }

    function buildTableHtml(data) {
      var cols = getColumnsForData(data);
      if (data.length === 0) return '<p class="text-muted mb-0">No data</p>';
      var h = '<div class="table-responsive"><table class="table table-sm table-bordered table-hover mb-0" id="reportTable"><thead><tr>';
      cols.forEach(function(c) {
        h += '<th>' + (c.label || c.key).replace(/</g, '&lt;') + '</th>';
      });
      h += '</tr></thead><tbody>';
      data.forEach(function(row) {
        h += '<tr>';
        cols.forEach(function(c) {
          var v = row[c.key];
          if (v === null || v === undefined) v = '';
          if (typeof v === 'object') v = JSON.stringify(v);
          h += '<td>' + String(v).replace(/</g, '&lt;') + '</td>';
        });
        h += '</tr>';
      });
      h += '</tbody></table></div>';
      return h;
    }

    function buildPivotTableHtml(data) {
      if (!data || data.length === 0) return '<p class="text-muted mb-0">No data</p>';

      // Build metric identifier and header from Device Name / Metric Name / Device ID / Metric key
      var metricInfo = {};
      var seenMetric = {};

      var idOrder = getIdentityKeysOrder();
      data.forEach(function(row) {
        var deviceName = row.device_name !== undefined ? String(row.device_name).trim() : '';
        var metricName = row.metric_label !== undefined ? String(row.metric_label).trim() :
          (row.key !== undefined ? String(row.key).trim() : (row.metric_key !== undefined ? String(row.metric_key).trim() : ''));
        var deviceId = (row.device_id !== undefined && row.device_id !== null) ? String(row.device_id) : '';
        var metricKey = row.key !== undefined ? String(row.key).trim() :
          (row.metric_key !== undefined ? String(row.metric_key).trim() : '');
        var parts = [];
        idOrder.forEach(function(k) {
          if (k === 'device_name' && deviceName) parts.push(deviceName);
          else if (k === 'metric_label' && metricName) parts.push(metricName);
          else if (k === 'device_id' && deviceId) parts.push(deviceId);
          else if ((k === 'key' || k === 'metric_key') && metricKey) parts.push(metricKey);
        });
        var header = parts.join(' ') || metricName || metricKey || deviceName;
        var idParts = [];
        if (deviceId) idParts.push(deviceId);
        if (metricKey) idParts.push(metricKey);
        else if (metricName) idParts.push(metricName);
        var metricId = idParts.join('|') || metricName || metricKey;
        if (!metricId) return;
        if (!seenMetric[metricId]) {
          seenMetric[metricId] = true;
          metricInfo[metricId] = { header: header || metricId };
        }
      });

      // Order columns according to template config (selected_device_metrics)
      var metricOrder = [];
      if (selectedDeviceMetricsOrder && selectedDeviceMetricsOrder.length > 0) {
        selectedDeviceMetricsOrder.forEach(function(dm) {
          var did = dm.device_id !== undefined && dm.device_id !== null ? String(dm.device_id) : '';
          var mkey = (dm.metric !== undefined ? String(dm.metric) : '').trim();
          var mid = (did && mkey) ? (did + '|' + mkey) : (mkey || did);
          if (mid && seenMetric[mid]) metricOrder.push(mid);
        });
      }
      Object.keys(seenMetric).forEach(function(mid) {
        if (metricOrder.indexOf(mid) < 0) metricOrder.push(mid);
      });
      if (metricOrder.length === 0) return buildTableHtml(data);

      // Pick sub-columns in order defined in Editor (displayColumns)
      var subCols = getPivotSubColumns();

      var pivotByTs = {};
      data.forEach(function(row) {
        var ts = row.timestamp !== undefined ? String(row.timestamp) : '';
        var deviceName = row.device_name !== undefined ? String(row.device_name).trim() : '';
        var metricName = row.metric_label !== undefined ? String(row.metric_label).trim() :
          (row.key !== undefined ? String(row.key).trim() : '');
        var deviceId = (row.device_id !== undefined && row.device_id !== null) ? String(row.device_id) : '';
        var metricKey = row.key !== undefined ? String(row.key).trim() :
          (row.metric_key !== undefined ? String(row.metric_key).trim() : '');
        var idParts = [];
        if (deviceId) idParts.push(deviceId);
        if (metricKey) idParts.push(metricKey);
        else if (metricName) idParts.push(metricName);
        var metricId = idParts.join('|') || metricName || metricKey;
        if (!ts || !metricId) return;
        if (!pivotByTs[ts]) pivotByTs[ts] = {};
        if (!pivotByTs[ts][metricId]) pivotByTs[ts][metricId] = {};
        subCols.forEach(function(sc) {
          pivotByTs[ts][metricId][sc.key] = row[sc.key];
        });
      });

      var timestamps = Object.keys(pivotByTs).sort(function(a, b) {
        return (sortOrder === 'asc' ? a.localeCompare(b) : b.localeCompare(a));
      });
      var esc = function(s) {
        return String(s === null || s === undefined ? '' : s).replace(/</g, '&lt;');
      };
      var h = '<div class="table-responsive"><table class="table table-sm table-bordered table-hover mb-0" id="reportTable"><thead><tr>';
      h += '<th rowspan="2" class="align-middle text-center">' + esc(getTimeHeaderLabel()) + '</th>';
      metricOrder.forEach(function(metricId) {
        var header = metricInfo[metricId] ? metricInfo[metricId].header : metricId;
        h += '<th colspan="' + subCols.length + '" class="text-center">' + esc(header) + '</th>';
      });
      h += '</tr><tr>';
      metricOrder.forEach(function() {
        subCols.forEach(function(sc) {
          h += '<th class="text-center small">' + esc(sc.label) + '</th>';
        });
      });
      h += '</tr></thead><tbody>';
      timestamps.forEach(function(ts) {
        h += '<tr><td class="text-center">' + esc(ts) + '</td>';
        metricOrder.forEach(function(metricId) {
          var cell = pivotByTs[ts][metricId] || {};
          subCols.forEach(function(sc) {
            var v = cell[sc.key];
            h += '<td class="text-center">' + esc(v) + '</td>';
          });
        });
        h += '</tr>';
      });
      h += '</tbody></table></div>';
      return h;
    }

    $('#runForm').on('submit', function(e) {
      e.preventDefault();
      var $btn = $('#btnGetData').prop('disabled', true);
      var data = $(this).serialize();
      $.ajax({
        url: baseUrl + 'reports/fetch_data',
        type: 'POST',
        data: data,
        dataType: 'json'
      }).done(function(res) {
        if (res.success && res.data) {
          console.log(res.data);
          currentReportData = res.data;
          $('#dataPreviewCard').show();
          $('#dataPreviewTitle').text(reportTitle || 'Sample data');
          $('#dataPreviewContent').html(tableLayout === 'pivot' ? buildPivotTableHtml(res.data) : buildTableHtml(res.data));
          $('#exportButtonsWrap').show();
        } else {
          currentReportData = [];
          $('#dataPreviewContent').html('<p class="text-danger">' + (res.message || 'No data found') + '</p>');
          $('#dataPreviewCard').show();
        }
      }).fail(function() {
        currentReportData = [];
        $('#dataPreviewContent').html('<p class="text-danger">An error occurred</p>');
        $('#dataPreviewCard').show();
      }).always(function() {
        $btn.prop('disabled', false);
      });
    });

    function getPivotColumnsAndRows() {
      if (!currentReportData || currentReportData.length === 0) return {
        cols: [],
        rows: []
      };

      var metricOrder = [];
      var metricInfo = {};
      var seenMetric = {};

      var idOrder = getIdentityKeysOrder();
      currentReportData.forEach(function(row) {
        var deviceName = row.device_name !== undefined ? String(row.device_name).trim() : '';
        var metricName = row.metric_label !== undefined ? String(row.metric_label).trim() :
          (row.key !== undefined ? String(row.key).trim() : (row.metric_key !== undefined ? String(row.metric_key).trim() : ''));
        var deviceId = (row.device_id !== undefined && row.device_id !== null) ? String(row.device_id) : '';
        var metricKey = row.key !== undefined ? String(row.key).trim() :
          (row.metric_key !== undefined ? String(row.metric_key).trim() : '');
        // Row 1: use values by key defined in Builder (e.g. device_name + metric_label)
        var parts = [];
        idOrder.forEach(function(k) {
          if (k === 'device_name' && deviceName) parts.push(deviceName);
          else if (k === 'metric_label' && metricName) parts.push(metricName);
          else if (k === 'device_id' && deviceId) parts.push(deviceId);
          else if ((k === 'key' || k === 'metric_key') && metricKey) parts.push(metricKey);
        });
        var header = parts.join(' ') || metricName || metricKey || deviceName;
        var idParts = [];
        if (deviceId) idParts.push(deviceId);
        if (metricKey) idParts.push(metricKey);
        else if (metricName) idParts.push(metricName);
        var metricId = idParts.join('|') || metricName || metricKey;
        if (!metricId) return;
        if (!seenMetric[metricId]) {
          seenMetric[metricId] = true;
          metricOrder.push(metricId);
          metricInfo[metricId] = {
            header: header || metricId
          };
        }
      });

      if (metricOrder.length === 0) return {
        cols: [],
        rows: []
      };

      var subCols = getPivotSubColumns();

      var cols = [{
        key: 'timestamp',
        label: 'Time'
      }];
      metricOrder.forEach(function(metricId) {
        var header = metricInfo[metricId] ? metricInfo[metricId].header : metricId;
        subCols.forEach(function(sc) {
          cols.push({
            key: metricId + '_' + sc.key,
            label: header + ' ' + sc.label
          });
        });
      });

      var pivotByTs = {};
      currentReportData.forEach(function(row) {
        var ts = row.timestamp !== undefined ? String(row.timestamp) : '';
        var deviceName = row.device_name !== undefined ? String(row.device_name).trim() : '';
        var metricName = row.metric_label !== undefined ? String(row.metric_label).trim() :
          (row.key !== undefined ? String(row.key).trim() : '');
        var deviceId = (row.device_id !== undefined && row.device_id !== null) ? String(row.device_id) : '';
        var metricKey = row.key !== undefined ? String(row.key).trim() :
          (row.metric_key !== undefined ? String(row.metric_key).trim() : '');
        var idParts = [];
        if (deviceId) idParts.push(deviceId);
        if (metricKey) idParts.push(metricKey);
        else if (metricName) idParts.push(metricName);
        var metricId = idParts.join('|') || metricName || metricKey;
        if (!ts || !metricId) return;
        if (!pivotByTs[ts]) pivotByTs[ts] = {};
        if (!pivotByTs[ts][metricId]) pivotByTs[ts][metricId] = {};
        subCols.forEach(function(sc) {
          pivotByTs[ts][metricId][sc.key] = row[sc.key];
        });
      });

      var timestamps = Object.keys(pivotByTs).sort(function(a, b) {
        return (sortOrder === 'asc' ? a.localeCompare(b) : b.localeCompare(a));
      });
      var rows = timestamps.map(function(ts) {
        var r = [ts];
        metricOrder.forEach(function(metricId) {
          var cell = pivotByTs[ts][metricId] || {};
          subCols.forEach(function(sc) {
            r.push(cell[sc.key]);
          });
        });
        return r;
      });
      return {
        cols: cols,
        rows: rows
      };
    }

    function getColumnsAndRows() {
      if (tableLayout === 'pivot') return getPivotColumnsAndRows();
      var cols = getColumnsForData(currentReportData);
      var rows = currentReportData.map(function(row) {
        return cols.map(function(c) {
          return row[c.key];
        });
      });
      return {
        cols: cols,
        rows: rows
      };
    }

    function doExportSave(format, cb) {
      var saveToFileManager = $('#chkSaveToFileManager').is(':checked');
      var data = $('#runForm').serialize() + '&format=' + encodeURIComponent(format) + '&save_to_file_manager=' + (saveToFileManager ? '1' : '0');
      $.post(baseUrl + 'reports/export_save', data).done(function(res) {
        if (res.success && typeof cb === 'function') cb();
      }).always(function() { if (typeof cb === 'function') cb(); });
    }

    $('#btnExportCsv').on('click', function() {
      var cr = getColumnsAndRows();
      if (cr.rows.length === 0) return;
      doExportSave('csv');
      var delim = csvDelimiter || ',';
      var line = cr.cols.map(function(c) {
        return '"' + (c.label || c.key).replace(/"/g, '""') + '"';
      }).join(delim);
      cr.rows.forEach(function(r) {
        line += '\n' + r.map(function(cell) {
          var s = cell === null || cell === undefined ? '' : String(cell);
          return '"' + s.replace(/"/g, '""') + '"';
        }).join(delim);
      });
      var blob = new Blob(['\ufeff' + line], {
        type: 'text/csv;charset=utf-8'
      });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'report_' + (new Date().toISOString().slice(0, 10)) + '.csv';
      a.click();
      URL.revokeObjectURL(a.href);
    });

    $('#btnExportExcel').on('click', function() {
      var cr = getColumnsAndRows();
      if (cr.rows.length === 0) return;
      doExportSave('excel');
      var html = '<table><thead><tr>' + cr.cols.map(function(c) {
        return '<th>' + (c.label || c.key) + '</th>';
      }).join('') + '</tr></thead><tbody>';
      cr.rows.forEach(function(r) {
        html += '<tr>' + r.map(function(cell) {
          return '<td>' + (cell === null || cell === undefined ? '' : cell) + '</td>';
        }).join('') + '</tr>';
      });
      html += '</tbody></table>';
      var blob = new Blob([html], {
        type: 'application/vnd.ms-excel'
      });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (excelSheetName || 'report') + '_' + (new Date().toISOString().slice(0, 10)) + '.xls';
      a.click();
      URL.revokeObjectURL(a.href);
    });

    function pdfExportFields() {
      var params = new URLSearchParams();
      $('#runForm').serializeArray().forEach(function(field) {
        params.append(field.name, field.value);
      });
      params.append('save_to_file_manager', $('#chkSaveToFileManager').is(':checked') ? '1' : '0');
      return params;
    }

    function submitPdfDownload() {
      if (!$('#reportTable').length) return;
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = baseUrl + 'reports/export_pdf';
      form.style.display = 'none';
      pdfExportFields().forEach(function(value, name) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    }

    $('#btnPreviewPdf').on('click', function() {
      if (!$('#reportTable').length) return;
      var popup = window.open('', '_blank');
      var params = pdfExportFields();
      params.append('preview', '1');
      fetch(baseUrl + 'reports/export_pdf', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: params.toString(),
        credentials: 'same-origin'
      }).then(function(res) {
        if (!res.ok) {
          throw new Error('preview failed');
        }
        return res.blob();
      }).then(function(blob) {
        var url = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
        if (popup && !popup.closed) {
          popup.location = url;
        } else {
          window.open(url, '_blank');
        }
      }).catch(function() {
        if (popup && !popup.closed) {
          popup.close();
        }
      });
    });

    $('#btnExportPdf').on('click', function() {
      submitPdfDownload();
    });
  })();
</script>