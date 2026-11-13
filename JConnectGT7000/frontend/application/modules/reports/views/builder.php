<?php defined('BASEPATH') or exit('No direct script access allowed');
$template = isset($template) ? $template : null;
$config = $template && isset($template->config) ? (is_string($template->config) ? json_decode($template->config, true) : $template->config) : [];
$data_source = isset($config['data_source']) ? $config['data_source'] : 'device_data';
$filters = isset($config['filters']) ? $config['filters'] : [];
$selected_device_metrics = isset($filters['selected_device_metrics']) ? $filters['selected_device_metrics'] : [];
if (!is_array($selected_device_metrics)) $selected_device_metrics = [];
$export_inputs = isset($config['export_inputs']) ? $config['export_inputs'] : [];
$editor = isset($config['editor']) ? $config['editor'] : [];
$report_title = $editor['report_title'] ?? '';
$display_columns = isset($editor['display_columns']) && is_array($editor['display_columns']) ? $editor['display_columns'] : [];
$pdf_orientation = $editor['pdf_orientation'] ?? 'portrait';
$pdf_title = $editor['pdf_title'] ?? '';
$excel_sheet_name = $editor['excel_sheet_name'] ?? 'Sheet1';
$csv_delimiter = $editor['csv_delimiter'] ?? ',';
$default_columns = [
  ['key' => 'timestamp', 'label' => 'Time'],
  ['key' => 'device_name', 'label' => 'Device Name'],
  ['key' => 'metric_label', 'label' => 'Metric Name'],
  ['key' => 'device_id', 'label' => 'Device ID'],
  ['key' => 'key', 'label' => 'Metric (key)'],
  ['key' => 'value', 'label' => 'Value'],
  ['key' => 'value_min', 'label' => 'Min'],
  ['key' => 'value_max', 'label' => 'Max'],
  ['key' => 'value_avg', 'label' => 'Average'],
  ['key' => 'value_first', 'label' => 'First'],
  ['key' => 'value_last', 'label' => 'Last'],
];
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><?= $template ? lang('edit_template') : lang('create_report_template') ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>"><?= lang('reports_builder') ?></a></li>
            <li class="breadcrumb-item active"><?= $template ? lang('edit') : lang('create_new') ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div id="alertContainer"></div>
    <form id="builderForm">
      <input type="hidden" name="template_id" value="<?= $template ? (int)$template->id : '' ?>">

      <div class="card shadow-sm mb-4">
        <div class="card-header ">
          <h6 class="mb-0"><i class="bi bi-info-circle"></i> <?= lang('template_information') ?></h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="name" class="form-label"><?= lang('template_name') ?> <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" name="name" value="<?= $template ? htmlspecialchars($template->name) : '' ?>" required maxlength="255" placeholder="<?= lang('template_name_placeholder') ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label for="description" class="form-label"><?= lang('description') ?></label>
              <input type="text" class="form-control" id="description" name="description" value="<?= $template ? htmlspecialchars($template->description ?? '') : '' ?>" placeholder="<?= lang('brief_description') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header ">
          <h6 class="mb-0"><i class="bi bi-database"></i> <?= lang('data_source') ?></h6>
        </div>
        <div class="card-body">
          <p class="text-muted small"><?= lang('select_data_source') ?></p>
          <div class="row">
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="data_source" id="ds_device_data" value="device_data" <?= $data_source === 'device_data' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ds_device_data">
                  <i class="bi bi-cpu text-primary"></i> Device Data
                </label>
                <small class="d-block text-muted">Data from device_data (sensor values, metrics)</small>
              </div>
            </div>
            <!--
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="data_source" id="ds_alarm_logs" value="alarm_logs" <?= $data_source === 'alarm_logs' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ds_alarm_logs">
                  <i class="bi bi-exclamation-triangle text-warning"></i> Alarm Logs
                </label>
                <small class="d-block text-muted">Alarm notification logs</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="data_source" id="ds_event_logs" value="event_logs" <?= $data_source === 'event_logs' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ds_event_logs">
                  <i class="bi bi-journal-text text-info"></i> Event Logs
                </label>
                <small class="d-block text-muted">System event logs (create/edit/delete, etc.)</small>
              </div>
            </div> -->
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header ">
          <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters used in Template</h6>
        </div>
        <div class="card-body">
          <!-- Device Data & Alarm Logs -->
          <div id="filterBlockDeviceAlarm" class="filter-block" data-sources="device_data,alarm_logs">
            <p class="text-muted small" id="filterDescDeviceAlarm">Select Device then select Metric for that device. Click Add to add to list (if not added = use all devices/all Metrics)</p>

            <!-- Device + Metric (Metric per Device) + single Add button -->
            <div class="mb-3">
              <label class="form-label">Device - Metric</label>
              <div class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                  <label class="form-label small mb-0">Device</label>
                  <select class="form-select form-select-sm" id="dynamicDeviceSelect" style="min-width:200px;">
                    <option value="">-- Select Device --</option>
                  </select>
                </div>
                <div>
                  <label class="form-label small mb-0">Metric (for selected Device)</label>
                  <select class="form-select form-select-sm" id="dynamicMetricSelect" style="min-width:180px;" disabled>
                    <option value="">-- Select Device first --</option>
                  </select>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="btnAddDeviceMetric"><i class="bi bi-plus-lg"></i> Add</button>
              </div>
              <div id="selectedDeviceMetricsChips" class="d-flex flex-wrap gap-1 mt-2 min-chips-height align-items-center">
                <?php foreach ($selected_device_metrics as $dm):
                  $did = (int)($dm['device_id'] ?? 0);
                  $dname = isset($dm['device_name']) ? $dm['device_name'] : '';
                  $mkey = isset($dm['metric']) ? $dm['metric'] : '';
                  $mlabel = isset($dm['metric_label']) ? $dm['metric_label'] : $mkey;
                  $label = $dname ? $dname . ' - ' . $mlabel : ('ID ' . $did . ' - ' . $mlabel);
                ?>
                  <span class="badge bg-primary d-inline-flex align-items-center gap-1 chip-item" data-device-id="<?= $did ?>" data-device-name="<?= htmlspecialchars($dname) ?>" data-metric="<?= htmlspecialchars($mkey) ?>" data-metric-label="<?= htmlspecialchars($mlabel) ?>">
                    <span class="chip-drag-handle" style="cursor:move;opacity:0.9;" title="ลากเพื่อเรียงลำดับ"><i class="bi bi-grip-vertical"></i></span>
                    <span class="chip-label"><?= htmlspecialchars($label) ?></span>
                    <button type="button" class="btn-close btn-close-white" style="font-size:0.6em;" aria-label="Remove"></button>
                  </span>
                <?php endforeach; ?>
              </div>
                <!-- <p class="form-text small text-muted mt-1 mb-0">ลากไอคอน <i class="bi bi-grip-vertical"></i> เพื่อเรียงลำดับ Device - Metric</p>-->
            </div>
          </div>

          <!-- Event Logs -->
          <div id="filterBlockEvent" class="filter-block" data-sources="event_logs" style="display:none;">
            <p class="text-muted small">Select filters for Event Logs (action type, entity type, time range)</p>
            <div class="row">
              <div class="col-md-3 mb-2">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="filter_action_type" name="filter_action_type" value="1" <?= !empty($filters['action_type']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_action_type">Action Type</label>
                </div>
              </div>
              <div class="col-md-3 mb-2">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="filter_entity_type" name="filter_entity_type" value="1" <?= !empty($filters['entity_type']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_entity_type">Entity Type</label>
                </div>
              </div>
              <div class="col-md-3 mb-2">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="filter_entity" name="filter_entity" value="1" <?= !empty($filters['entity']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_entity">Entity (Device)</label>
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <label for="filter_time_interval_event" class="form-label">Time Interval</label>
                <select class="form-select" id="filter_time_interval_event" name="filter_time_interval">
                  <option value="date" <?= isset($filters['time_interval']) && $filters['time_interval'] === 'date' ? 'selected' : '' ?>>Single Date</option>
                  <option value="month" <?= isset($filters['time_interval']) && $filters['time_interval'] === 'month' ? 'selected' : '' ?>>Month</option>
                  <option value="year" <?= isset($filters['time_interval']) && $filters['time_interval'] === 'year' ? 'selected' : '' ?>>Year</option>
                  <option value="date_range" <?= (empty($filters['time_interval']) || $filters['time_interval'] === 'date_range') ? 'selected' : '' ?>>Date Range</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-gear"></i> Data Processing</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-2">
              <label for="time_range" class="form-label small">Time Range</label>
              <select class="form-select form-select-sm" id="time_range" name="time_range">
                <option value="input_date_range" <?= isset($filters['time_range']) && $filters['time_range'] === 'input_date_range' ? 'selected' : '' ?>>Use Input date range (Date Range at Export)</option>
                <option value="last_6h" <?= isset($filters['time_range']) && $filters['time_range'] === 'last_6h' ? 'selected' : '' ?>>Last 6 Hours</option>
                <option value="last_12h" <?= isset($filters['time_range']) && $filters['time_range'] === 'last_12h' ? 'selected' : '' ?>>Last 12 Hours</option>
                <option value="last_24h" <?= (empty($filters['time_range']) || $filters['time_range'] === 'last_24h') ? 'selected' : '' ?>>Last 24 Hours</option>
                <option value="today" <?= isset($filters['time_range']) && $filters['time_range'] === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="yesterday" <?= isset($filters['time_range']) && $filters['time_range'] === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                <option value="last_3d" <?= isset($filters['time_range']) && $filters['time_range'] === 'last_3d' ? 'selected' : '' ?>>Last 3 Days</option>
                <option value="last_7d" <?= isset($filters['time_range']) && $filters['time_range'] === 'last_7d' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="this_week" <?= isset($filters['time_range']) && $filters['time_range'] === 'this_week' ? 'selected' : '' ?>>This Week</option>
                <option value="last_week" <?= isset($filters['time_range']) && $filters['time_range'] === 'last_week' ? 'selected' : '' ?>>Last Week</option>
                <option value="this_month" <?= isset($filters['time_range']) && $filters['time_range'] === 'this_month' ? 'selected' : '' ?>>This Month</option>
                <option value="last_month" <?= isset($filters['time_range']) && $filters['time_range'] === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                <option value="last_3m" <?= isset($filters['time_range']) && $filters['time_range'] === 'last_3m' ? 'selected' : '' ?>>Last 3 Months</option>
              </select>
              <p class="form-text small text-muted mb-0 mt-1" id="timeRangeInputHint" style="display:none;">Tip: Add an Input of type <strong>Date Range</strong> in the Export Inputs section so users can enter the date range when running the report.</p>
            </div>
            <div class="col-md-4 mb-2">
              <label for="time_interval" class="form-label small">Time Interval</label>
              <select class="form-select form-select-sm" id="time_interval" name="time_interval">
                <option value="1m" <?= isset($filters['time_interval']) && $filters['time_interval'] === '1m' ? 'selected' : '' ?>>1 Minute</option>
                <option value="5m" <?= isset($filters['time_interval']) && $filters['time_interval'] === '5m' ? 'selected' : '' ?>>5 Minutes</option>
                <option value="10m" <?= isset($filters['time_interval']) && $filters['time_interval'] === '10m' ? 'selected' : '' ?>>10 Minutes</option>
                <option value="15m" <?= isset($filters['time_interval']) && $filters['time_interval'] === '15m' ? 'selected' : '' ?>>15 Minutes</option>
                <option value="30m" <?= isset($filters['time_interval']) && $filters['time_interval'] === '30m' ? 'selected' : '' ?>>30 Minutes</option>
                <option value="1h" <?= (empty($filters['time_interval']) || $filters['time_interval'] === '1h') ? 'selected' : '' ?>>1 Hour</option>
                <option value="4h" <?= isset($filters['time_interval']) && $filters['time_interval'] === '4h' ? 'selected' : '' ?>>4 Hours</option>
                <option value="12h" <?= isset($filters['time_interval']) && $filters['time_interval'] === '12h' ? 'selected' : '' ?>>12 Hours</option>
                <option value="24h" <?= isset($filters['time_interval']) && $filters['time_interval'] === '24h' ? 'selected' : '' ?>>24 Hours</option>
                <option value="1d" <?= isset($filters['time_interval']) && $filters['time_interval'] === '1d' ? 'selected' : '' ?>>1 Day</option>
              </select>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label small">Display</label>
              <div class="d-flex flex-wrap gap-2">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="display_min" name="display[]" value="min" <?= in_array('min', $filters['display'] ?? []) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="display_min">Min</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="display_max" name="display[]" value="max" <?= in_array('max', $filters['display'] ?? []) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="display_max">Max</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="display_avg" name="display[]" value="average" <?= in_array('average', $filters['display'] ?? []) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="display_avg">Average</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="display_first" name="display[]" value="first" <?= in_array('first', $filters['display'] ?? []) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="display_first">First</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="display_last" name="display[]" value="last" <?= in_array('last', $filters['display'] ?? []) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="display_last">Last</label>
                </div>
              </div>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-4 mb-2">
              <label class="form-label small">Sort Order</label>
              <select class="form-select form-select-sm" id="sort_order" name="sort_order">
                <option value="desc" <?= (isset($filters['sort_order']) ? $filters['sort_order'] : 'desc') === 'desc' ? 'selected' : '' ?>>Newest first (DESC)</option>
                <option value="asc" <?= (isset($filters['sort_order']) ? $filters['sort_order'] : 'desc') === 'asc' ? 'selected' : '' ?>>Oldest first (ASC)</option>
              </select>
            </div>
          </div>
        </div>
      </div>


<!--
      <div class="card shadow-sm mb-4">
        <div class="card-header  d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-input-cursor"></i> Input at Export (for users to fill when running report)</h6>
          <button type="button" class="btn btn-sm btn-outline-primary" id="addExportInput">
            <i class="bi bi-plus"></i> Add Input
          </button>
        </div>
        <div class="card-body">
          <p class="text-muted small">Define what inputs users must fill when exporting the report (e.g. select date, month, device, etc.)</p>
          <div id="exportInputsList">
            <?php foreach ($export_inputs as $i => $in): ?>
              <div class="export-input-row row g-2 align-items-end mb-2 border-bottom pb-2">
                <div class="col-md-3">
                  <label class="form-label small">Type</label>
                  <select class="form-select form-select-sm" name="export_inputs[<?= $i ?>][type]">
                    <option value="date" <?= ($in['type'] ?? '') === 'date' ? 'selected' : '' ?>>Date</option>
                    <option value="month" <?= ($in['type'] ?? '') === 'month' ? 'selected' : '' ?>>Month</option>
                    <option value="year" <?= ($in['type'] ?? '') === 'year' ? 'selected' : '' ?>>Year</option>
                    <option value="date_range" <?= ($in['type'] ?? '') === 'date_range' ? 'selected' : '' ?>>Date Range</option>
                    <option value="device" <?= ($in['type'] ?? '') === 'device' ? 'selected' : '' ?>>Device</option>
                    <option value="metric" <?= ($in['type'] ?? '') === 'metric' ? 'selected' : '' ?>>Metric</option>
                    <option value="room" <?= ($in['type'] ?? '') === 'room' ? 'selected' : '' ?>>Room</option>
                    <option value="action_type" <?= ($in['type'] ?? '') === 'action_type' ? 'selected' : '' ?>>Action Type (Event)</option>
                    <option value="entity_type" <?= ($in['type'] ?? '') === 'entity_type' ? 'selected' : '' ?>>Entity Type (Event)</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small">Label</label>
                  <input type="text" class="form-control form-control-sm" name="export_inputs[<?= $i ?>][label]" value="<?= htmlspecialchars($in['label'] ?? '') ?>" placeholder="e.g. Date">
                </div>
                <div class="col-md-2">
                  <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="export_inputs[<?= $i ?>][required]" value="1" <?= !empty($in['required']) ? 'checked' : '' ?>>
                    <label class="form-check-label small">Required</label>
                  </div>
                </div>
                <div class="col-md-1">
                  <button type="button" class="btn btn-sm btn-outline-danger remove-export-input"><i class="bi bi-dash-lg"></i></button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="small text-muted mt-2" id="noInputsHint" style="<?= count($export_inputs) > 0 ? 'display:none' : '' ?>">No inputs yet. Click "Add Input" to add.</p>
        </div>
      </div>
            -->
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-pencil-square"></i> Editor for display and Export</h6>
        </div>
        <div class="card-body">
          <p class="text-muted small">Define columns shown in data preview (Run) and export format for PDF, Excel, CSV</p>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="report_title" class="form-label small">Report title (shown on preview and PDF)</label>
              <input type="text" class="form-control form-control-sm" id="report_title" name="editor[report_title]" value="<?= htmlspecialchars($report_title) ?>" placeholder="e.g. Device Data Report">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label small">Table layout</label>
              <select class="form-select form-select-sm" name="editor[table_layout]">
                <option value="rows" <?= ($editor['table_layout'] ?? 'rows') === 'rows' ? 'selected' : '' ?>>Rows per Device-Metric (long format)</option>
                <option value="pivot" <?= ($editor['table_layout'] ?? '') === 'pivot' ? 'selected' : '' ?>>Pivot by time (Metric as columns)</option>
              </select>
              <p class="form-text small text-muted mb-0">Pivot: rows = time, each Metric has Min/Max/Average columns</p>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Display columns (reorderable)</label>
            <div id="displayColumnsList" class="border rounded p-2" style="min-height:2rem;">
              <?php
              $cols = !empty($display_columns) ? $display_columns : $default_columns;
              foreach ($cols as $idx => $col):
                $k = isset($col['key']) ? $col['key'] : '';
                $l = isset($col['label']) ? $col['label'] : $k;
              ?>
                <div class="display-column-row d-flex align-items-center gap-2 mb-2" data-key="<?= htmlspecialchars($k) ?>">
                  <span class="drag-handle text-muted" style="cursor:move;"><i class="bi bi-grip-vertical"></i></span>
                  <input type="text" class="form-control form-control-sm flex-grow-1" name="editor_display_columns[<?= $idx ?>][key]" value="<?= htmlspecialchars($k) ?>" placeholder="key" readonly style="max-width:140px;">
                  <input type="text" class="form-control form-control-sm flex-grow-1" name="editor_display_columns[<?= $idx ?>][label]" value="<?= htmlspecialchars($l) ?>" placeholder="Column name">
                  <button type="button" class="btn btn-sm btn-outline-danger remove-column"><i class="bi bi-dash-lg"></i></button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mt-2">
              <label class="form-label small mb-1">Add column</label>
              <div class="d-flex gap-2 flex-wrap">
                <select class="form-select form-select-sm" id="addColumnSelect" style="max-width:180px;">
                  <?php foreach ($default_columns as $dc): ?>
                    <option value="<?= htmlspecialchars($dc['key']) ?>" data-label="<?= htmlspecialchars($dc['label']) ?>"><?= htmlspecialchars($dc['label']) ?> (<?= htmlspecialchars($dc['key']) ?>)</option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddColumn"><i class="bi bi-plus"></i> Add</button>
              </div>
            </div>
          </div>
          <hr>
          <h6 class="small mb-2">Export</h6>
          <div class="row">
            <div class="col-md-4 mb-2">
              <label class="form-label small">PDF</label>
              <div class="mb-1">
                <select class="form-select form-select-sm" name="editor[pdf_orientation]">
                  <option value="portrait" <?= $pdf_orientation === 'portrait' ? 'selected' : '' ?>>Portrait</option>
                  <option value="landscape" <?= $pdf_orientation === 'landscape' ? 'selected' : '' ?>>Landscape</option>
                </select>
              </div>
              <input type="text" class="form-control form-control-sm" name="editor[pdf_title]" value="<?= htmlspecialchars($pdf_title) ?>" placeholder="PDF title (optional)">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label small">Excel – Sheet name</label>
              <input type="text" class="form-control form-control-sm" name="editor[excel_sheet_name]" value="<?= htmlspecialchars($excel_sheet_name) ?>" placeholder="Sheet1">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label small">CSV – Field delimiter</label>
              <select class="form-select form-select-sm" name="editor[csv_delimiter]">
                <option value="," <?= $csv_delimiter === ',' ? 'selected' : '' ?>>Comma</option>
                <option value=";" <?= $csv_delimiter === ';' ? 'selected' : '' ?>>Semicolon</option>
                <option value="tab" <?= $csv_delimiter === 'tab' ? 'selected' : '' ?>>Tab</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <button type="submit" class="btn btn-primary" id="btnSave">
          <i class="bi bi-check-lg"></i> Save Template
        </button>
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<style>
  .min-chips-height {
    min-height: 1.5rem;
  }

  #selectedDeviceMetricsChips .chip-placeholder {
    min-width: 2rem;
    opacity: 0.6;
    border: 1px dashed rgba(255, 255, 255, 0.5);
  }

  #selectedDevicesChips .badge,
  #selectedMetricsChips .badge {
    font-weight: normal;
  }

  .display-column-placeholder {
    height: 38px;
    margin-bottom: 0.5rem;
    border: 2px dashed #0d6efd;
    background: rgba(13, 110, 253, 0.08);
    border-radius: 0.25rem;
  }

  #displayColumnsList .display-column-row.ui-sortable-helper {
    opacity: 0.9;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }
</style>

<script>
  (function() {
    var rowIndex = <?= count($export_inputs) ?>;

    function toggleFilterByDataSource() {
      var ds = $('input[name="data_source"]:checked').val() || 'device_data';
      $('#filterBlockDeviceAlarm').toggle(ds === 'device_data' || ds === 'alarm_logs');
      $('#filterBlockEvent').toggle(ds === 'event_logs');
      $('#filter_time_interval_event').prop('disabled', ds !== 'event_logs');
      $('#filterDescDeviceAlarm').text(ds === 'alarm_logs' ?
        'Select filters for Alarm Logs (device, Metric key, room, time range)' :
        'Select filters for Device Data (device, Metric, room, time range)');
      $('#labelMetric').text(ds === 'alarm_logs' ? 'Metric key' : 'Metric');
    }
    $('input[name="data_source"]').on('change', toggleFilterByDataSource);
    toggleFilterByDataSource();

    function toggleTimeRangeInputHint() {
      $('#timeRangeInputHint').toggle($('#time_range').val() === 'input_date_range');
    }
    $('#time_range').on('change', toggleTimeRangeInputHint);
    toggleTimeRangeInputHint();

    var devicesList = [];
    $.get('<?= base_url('reports/get_devices') ?>').done(function(res) {
      if (res.success && res.data) {
        devicesList = res.data;
        var $sel = $('#dynamicDeviceSelect');
        $sel.find('option:not(:first)').remove();
        res.data.forEach(function(d) {
          $sel.append($('<option>').val(d.id).attr('data-name', d.name || '').text((d.name || '') + (d.location ? ' - ' + d.location : '')));
        });
      }
    });

    $('#dynamicDeviceSelect').on('change', function() {
      var id = $(this).val();
      var $metricSel = $('#dynamicMetricSelect');
      $metricSel.empty().prop('disabled', true);
      $metricSel.append($('<option value="">-- Select Device first --</option>'));
      if (!id) return;
      $.get('<?= base_url('reports/get_metrics_by_device/') ?>' + id).done(function(res) {
        $metricSel.empty().prop('disabled', false);
        $metricSel.append($('<option value="">-- Select Metric --</option>'));
        if (res.success && res.data && res.data.length) {
          res.data.forEach(function(m) {
            var key = (typeof m === 'object' && m.key !== undefined) ? m.key : m;
            var label = (typeof m === 'object' && m.label !== undefined) ? m.label : m;
            $metricSel.append($('<option>').val(key).attr('data-label', label).text(label));
          });
        }
      });
    });

    $('#btnAddDeviceMetric').on('click', function() {
      var deviceId = $('#dynamicDeviceSelect').val();
      var deviceName = ($('#dynamicDeviceSelect option:selected').attr('data-name') || $('#dynamicDeviceSelect option:selected').text() || '').trim();
      var metricKey = $('#dynamicMetricSelect').val();
      var metricLabel = $('#dynamicMetricSelect option:selected').attr('data-label') || $('#dynamicMetricSelect option:selected').text() || metricKey;
      if (!deviceId || !metricKey) return;
      var exists = false;
      $('#selectedDeviceMetricsChips .badge[data-device-id="' + deviceId + '"]').each(function() {
        if ($(this).attr('data-metric') === metricKey) {
          exists = true;
          return false;
        }
      });
      if (exists) return;
      var label = (deviceName || 'ID ' + deviceId) + ' - ' + metricLabel;
      var nameEsc = (deviceName || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
      var metricEsc = (metricKey || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
      var labelEsc = (label || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
      var chip = '<span class="badge bg-primary d-inline-flex align-items-center gap-1 chip-item" data-device-id="' + deviceId + '" data-device-name="' + nameEsc + '" data-metric="' + metricEsc + '" data-metric-label="' + (metricLabel || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '">' +
        '<span class="chip-drag-handle" style="cursor:move;opacity:0.9;" title="ลากเพื่อเรียงลำดับ"><i class="bi bi-grip-vertical"></i></span>' +
        '<span class="chip-label">' + labelEsc + '</span>' +
        ' <button type="button" class="btn-close btn-close-white" style="font-size:0.6em;" aria-label="Remove"></button></span>';
      $('#selectedDeviceMetricsChips').append(chip);
    });
    $(document).on('click', '#selectedDeviceMetricsChips .btn-close', function() {
      $(this).closest('.badge').remove();
    });

    if ($.fn.sortable) {
      $('#selectedDeviceMetricsChips').sortable({
        items: '.chip-item',
        handle: '.chip-drag-handle',
        placeholder: 'badge bg-secondary chip-item chip-placeholder',
        forcePlaceholderSize: true,
        tolerance: 'pointer'
      });
    }

    function addExportInputRow(data) {
      data = data || {};
      var type = data.type || 'date';
      var label = data.label || '';
      var required = data.required || false;
      var row = '<div class="export-input-row row g-2 align-items-end mb-2 border-bottom pb-2">' +
        '<div class="col-md-3"><label class="form-label small">Type</label>' +
        '<select class="form-select form-select-sm" name="export_inputs[' + rowIndex + '][type]">' +
        '<option value="date"' + (type === 'date' ? ' selected' : '') + '>Date</option>' +
        '<option value="month"' + (type === 'month' ? ' selected' : '') + '>Month</option>' +
        '<option value="year"' + (type === 'year' ? ' selected' : '') + '>Year</option>' +
        '<option value="date_range"' + (type === 'date_range' ? ' selected' : '') + '>Date Range</option>' +
        '<option value="device"' + (type === 'device' ? ' selected' : '') + '>Device</option>' +
        '<option value="metric"' + (type === 'metric' ? ' selected' : '') + '>Metric</option>' +
        '<option value="room"' + (type === 'room' ? ' selected' : '') + '>Room</option>' +
        '<option value="action_type"' + (type === 'action_type' ? ' selected' : '') + '>Action Type (Event)</option>' +
        '<option value="entity_type"' + (type === 'entity_type' ? ' selected' : '') + '>Entity Type (Event)</option>' +
        '</select></div>' +
        '<div class="col-md-4"><label class="form-label small">Label</label>' +
        '<input type="text" class="form-control form-control-sm" name="export_inputs[' + rowIndex + '][label]" value="' + (label || '') + '" placeholder="e.g. Date"></div>' +
        '<div class="col-md-2"><div class="form-check mt-3">' +
        '<input class="form-check-input" type="checkbox" name="export_inputs[' + rowIndex + '][required]" value="1"' + (required ? ' checked' : '') + '>' +
        '<label class="form-check-label small">Required</label></div></div>' +
        '<div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-export-input"><i class="bi bi-dash-lg"></i></button></div>' +
        '</div>';
      $('#exportInputsList').append(row);
      $('#noInputsHint').hide();
      rowIndex++;
      $(document).find('.export-input-row').last().find('.remove-export-input').on('click', function() {
        $(this).closest('.export-input-row').remove();
        if ($('#exportInputsList .export-input-row').length === 0) $('#noInputsHint').show();
      });
    }

    $('#addExportInput').on('click', function() {
      addExportInputRow({
        type: 'date',
        label: 'Date'
      });
    });
    $(document).on('click', '.remove-export-input', function() {
      $(this).closest('.export-input-row').remove();
      if ($('#exportInputsList .export-input-row').length === 0) $('#noInputsHint').show();
    });

    var colIndex = $('#displayColumnsList .display-column-row').length;
    var defaultCols = <?= json_encode($default_columns) ?>;
    $('#btnAddColumn').on('click', function() {
      var $opt = $('#addColumnSelect option:selected');
      var key = $opt.val();
      var label = $opt.attr('data-label') || key;
      var exists = false;
      $('#displayColumnsList .display-column-row').each(function() {
        if ($(this).find('input[name*="[key]"]').val() === key) {
          exists = true;
          return false;
        }
      });
      if (exists) return;
      var row = '<div class="display-column-row d-flex align-items-center gap-2 mb-2" data-key="' + key.replace(/"/g, '&quot;') + '">' +
        '<span class="drag-handle text-muted" style="cursor:move;"><i class="bi bi-grip-vertical"></i></span>' +
        '<input type="text" class="form-control form-control-sm flex-grow-1" name="editor_display_columns[' + colIndex + '][key]" value="' + (key || '').replace(/"/g, '&quot;') + '" placeholder="key" readonly style="max-width:140px;">' +
        '<input type="text" class="form-control form-control-sm flex-grow-1" name="editor_display_columns[' + colIndex + '][label]" value="' + (label || '').replace(/"/g, '&quot;') + '" placeholder="Column name">' +
        '<button type="button" class="btn btn-sm btn-outline-danger remove-column"><i class="bi bi-dash-lg"></i></button></div>';
      $('#displayColumnsList').append(row);
      colIndex++;
    });
    $(document).on('click', '.remove-column', function() {
      $(this).closest('.display-column-row').remove();
    });

    if ($.fn.sortable) {
      $('#displayColumnsList').sortable({
        handle: '.drag-handle',
        placeholder: 'display-column-placeholder',
        tolerance: 'pointer',
        forcePlaceholderSize: true
      });
    }

    $('#builderForm').on('submit', function(e) {
      e.preventDefault();
      var $btn = $('#btnSave').prop('disabled', true);
      var formData = $(this).serializeArray();
      var data = {};
      $.each(formData, function(i, f) {
        if (f.name.indexOf('export_inputs') === 0) {
          var m = f.name.match(/export_inputs\[(\d+)\]\[(\w+)\]/);
          if (m) {
            var idx = m[1];
            if (!data.export_inputs) data.export_inputs = {};
            if (!data.export_inputs[idx]) data.export_inputs[idx] = {};
            data.export_inputs[idx][m[2]] = f.value;
          }
        } else if (f.name === 'display[]') {
          if (!data.display) data.display = [];
          data.display.push(f.value);
        } else if (f.name.indexOf('editor_display_columns') === 0) {
          var m = f.name.match(/editor_display_columns\[(\d+)\]\[(\w+)\]/);
          if (m) {
            var idx = m[1];
            if (!data.editor_display_columns) data.editor_display_columns = {};
            if (!data.editor_display_columns[idx]) data.editor_display_columns[idx] = {};
            data.editor_display_columns[idx][m[2]] = f.value;
          }
        } else if (f.name.indexOf('editor[') === 0) {
          var k = f.name.replace('editor[', '').replace(']', '');
          if (!data.editor) data.editor = {};
          data.editor[k] = f.value;
        } else {
          data[f.name] = f.value;
        }
      });
      var exportInputs = [];
      if (data.export_inputs) {
        $.each(data.export_inputs, function(idx, o) {
          if (o && o.type) exportInputs.push({
            type: o.type,
            label: o.label || o.type,
            required: o.required === '1'
          });
        });
      }
      var ds = (data.data_source === 'alarm_logs' || data.data_source === 'event_logs') ? data.data_source : 'device_data';
      var payload = {
        template_id: data.template_id || '',
        name: data.name,
        description: data.description || '',
        data_source: ds,
        filter_time_interval: data.filter_time_interval || data.filter_time_interval_event || 'date_range',
        export_inputs: exportInputs
      };
      if (ds === 'event_logs') {
        payload.filter_action_type = data.filter_action_type === '1' ? 1 : 0;
        payload.filter_entity_type = data.filter_entity_type === '1' ? 1 : 0;
        payload.filter_entity = data.filter_entity === '1' ? 1 : 0;
      } else {
        var deviceMetrics = [];
        $('#selectedDeviceMetricsChips .badge[data-device-id]').each(function() {
          var id = $(this).attr('data-device-id');
          var name = $(this).attr('data-device-name') || '';
          var metric = $(this).attr('data-metric') || '';
          var metricLabel = $(this).attr('data-metric-label') || metric;
          deviceMetrics.push({
            device_id: id,
            device_name: name,
            metric: metric,
            metric_label: metricLabel
          });
        });
        payload.selected_device_metrics = deviceMetrics;
        payload.time_range = data.time_range || 'last_24h';
        payload.time_interval = data.time_interval || '1h';
        payload.display = (data.display && data.display.length) ? data.display : ['min', 'max', 'average'];
        payload.sort_order = data.sort_order || 'desc';
      }
      var editor = {
        report_title: (data.editor && data.editor.report_title) ? data.editor.report_title : '',
        table_layout: (data.editor && data.editor.table_layout) ? data.editor.table_layout : 'rows',
        pdf_orientation: (data.editor && data.editor.pdf_orientation) ? data.editor.pdf_orientation : 'portrait',
        pdf_title: (data.editor && data.editor.pdf_title) ? data.editor.pdf_title : '',
        excel_sheet_name: (data.editor && data.editor.excel_sheet_name) ? data.editor.excel_sheet_name : 'Sheet1',
        csv_delimiter: (data.editor && data.editor.csv_delimiter) ? data.editor.csv_delimiter : ','
      };
      var displayColumns = [];
      $('#displayColumnsList .display-column-row').each(function() {
        var key = $(this).find('input[name*="[key]"]').val();
        var label = $(this).find('input[name*="[label]"]').val();
        if (key) displayColumns.push({
          key: key,
          label: (label || key).trim()
        });
      });
      editor.display_columns = displayColumns;
      payload.editor = editor;
      payload.editor_display_columns = displayColumns;

      $.ajax({
        url: '<?= base_url('reports/save_template') ?>',
        type: 'POST',
        data: payload,
        dataType: 'json'
      }).done(function(res) {
        if (res.success) {
          if (res.redirect) window.location.href = res.redirect;
          else alert(res.message);
        } else {
          alert(res.message || 'Save failed');
        }
      }).fail(function() {
        alert('An error occurred while saving');
      }).always(function() {
        $btn.prop('disabled', false);
      });
    });
  })();
</script>