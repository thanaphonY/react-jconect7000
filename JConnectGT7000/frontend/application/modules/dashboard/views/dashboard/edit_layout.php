<?php
$client = "";
if (isset($client_id)) {
  $client = $client_id;
}
?>

<!-- Sidebar Panel ที่จะใช้แทน Modal -->
<div id="widget-config-panel" class="config-sidebar bg-dark text-white" style="display: none;">
  <div class="config-header p-3 d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><?php echo lang('configure_widget'); ?></h5>
    <button class="btn btn-sm btn-light" onclick="closeWidgetConfigPanel()">×</button>
  </div>
  <div class="config-body p-3">

    <form id="widget-config-form" class="row g-3">


    </form>

    <div id="widget-preview-box" class="border rounded p-3 mt-3 bg-body text-body">
      <small class="text-muted"><?php echo lang('widget_preview'); ?></small>
    </div>

  </div>
  <div class="config-footer p-3 text-end">
    <button type="button" class="btn btn-primary btn-sm" onclick="applyWidgetConfig()"><?php echo lang('apply'); ?></button>
    <!-- <button type="button" class="btn btn-warning btn-sm" onclick="applyWidgetConfig(true)"><?php echo lang('apply_exit'); ?></button> -->
    <button type="button" class="btn btn-success btn-sm" onclick="applyWidgetConfig(true,true)"><?php echo lang('save'); ?></button>
  </div>
</div>



<div class="modal fade" id="addWidgetModal" tabindex="-1" aria-labelledby="addWidgetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title" id="addWidgetModalLabel"><?php echo lang('add_widget_to_layout'); ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <select id="widgetSelector" class="form-select">
          <option value=""><?php echo lang('choose_widget'); ?>
            <?php foreach ($widgets as $w): ?>
          <option value="<?= $w->id ?>"><?= htmlspecialchars($w->name) ?> </option>
        <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button onclick="onAddWidget()" class="btn btn-primary"><?php echo lang('add_to_layout'); ?></button>
      </div>
    </div>
  </div>
</div>
<div class="app-content-header">
  <!--begin::Container-->
  <div class="container-fluid">
    <!--begin::Row-->
    <div class="row">
      <div class="col-sm-6">
        <h3 class="mb-0"><?php echo lang('edit_layout'); ?> <?= htmlspecialchars($layout->name) ?></h3>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>"><?php echo lang('dashboard') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('dashboard/view/') . $layout->id ?>"><?= $layout->name ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('edit') ?></li>
          </ol>
        </nav>
      </div>
      <div class="col text-end">
        <div class="mb-3">
          <!--
          <button class="btn btn-secondary btn-sm" onclick="setPreview('desktop')">💻 Desktop</button>
          <button class="btn btn-secondary btn-sm" onclick="setPreview('tablet')">📱 Tablet</button>
          <button class="btn btn-secondary btn-sm" onclick="setPreview('mobile')">📱 Mobile</button>
          -->
          <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addWidgetModal"><?php echo lang('add_widget') ?></button>

          <button class="btn btn-primary btn-sm" onclick="saveLayout()"><?php echo lang('save'); ?></button>

        </div>

      </div>
    </div>
    <!--end::Row-->
  </div>
  <!--end::Container-->
</div>
<!--end::App Content Header-->
<!--begin::App Content-->
<div class="app-content">
  <!--begin::Container-->
  <div class="container-fluid">
    <!--begin::Row-->

    <div class="dashboard-container" id="dashboard-layout-scroll">
      <div class="dashboard-layout-scroll" id="layoutScroll">
        <div class="dashboard-layout-canvas grid-stack" id="layoutCanvas" style="<?= $layout->background_image ? "background-image: url('" . base_url($layout->background_image) . "');" : '' ?>">
        </div>
      </div>

      <!--
      <div class="dashboard-tools">
        <button onclick="setZoom(1)">100%</button>
        <button onclick="setZoom(1.5)">150%</button>
        <button onclick="fitToScreen()">Fit</button>
        <button onclick="resetView()">Reset</button>
      </div>
            -->
    </div>

    <form method="post" action="<?= base_url('dashboard/layoutsave') ?>" id="layoutForm">
      <input type="hidden" name="id" value="<?= $layout->id ?>">
      <input type="hidden" name="layout" id="layoutJson" value='<?= htmlspecialchars($layout_json, ENT_QUOTES, 'UTF-8') ?>'>
    </form>
    <input type="hidden" id="configTargetUid">

    <!--end::Row-->
  </div>
  <!--end::Container-->
</div>

<script>
  window.DASHBOARD_LAYOUTS = <?= $dashboard_layouts_json ?? '[]' ?>;

  const SHOW_BORDER_FIELD = {
    key: "show_border",
    label: "Show Border",
    type: "select",
    class: "col-sm-6",
    options: [
      { label: "Show", value: "1", default: true },
      { label: "Hide", value: "0" }
    ]
  };

  const WIDGET_BG_COLOR_FIELD = {
    key: "bg_color",
    label: "Background Color",
    type: "widget_color",
    class: "col-sm-6"
  };

  const WIDGET_BG_OPACITY_FIELD = {
    key: "bg_opacity",
    label: "Background Opacity (%)",
    type: "number",
    class: "col-sm-6",
    value: 100,
    min: 0,
    max: 100
  };

  const WIDGET_ICON_OPTIONS = [
    { label: "Circle (default)", value: "bi-circle" },
    { label: "Dot", value: "bi-dot" },
    // Status / control
    { label: "Fan / Cooling", value: "bi-fan" },
    { label: "Arrow Repeat (spin)", value: "bi-arrow-repeat" },
    { label: "Play", value: "bi-play-circle" },
    { label: "Pause", value: "bi-pause-circle" },
    { label: "Stop", value: "bi-stop-circle" },
    { label: "Record / Active", value: "bi-record-circle" },
    { label: "Check / OK", value: "bi-check-circle" },
    { label: "Error / X", value: "bi-x-circle" },
    { label: "Warning", value: "bi-exclamation-triangle" },
    { label: "Shield OK", value: "bi-shield-check" },
    { label: "Shield Alert", value: "bi-shield-exclamation" },
    { label: "Bell / Alarm", value: "bi-bell" },
    { label: "Toggle On", value: "bi-toggle-on" },
    { label: "Toggle Off", value: "bi-toggle-off" },
    { label: "Hourglass / Waiting", value: "bi-hourglass-split" },
    // Environment
    { label: "Temperature", value: "bi-thermometer-half" },
    { label: "Humidity", value: "bi-moisture" },
    { label: "Water / Liquid", value: "bi-droplet" },
    { label: "Airflow", value: "bi-wind" },
    { label: "Heat / Fire", value: "bi-fire" },
    // Electrical
    { label: "Power", value: "bi-power" },
    { label: "Voltage / Energy", value: "bi-lightning-charge" },
    { label: "Voltage (solid)", value: "bi-lightning-fill" },
    { label: "AC / Plug", value: "bi-plug" },
    { label: "Outlet", value: "bi-outlet" },
    { label: "DC / Battery", value: "bi-battery" },
    { label: "DC Charging", value: "bi-battery-charging" },
    { label: "Battery Full", value: "bi-battery-full" },
    { label: "Waveform ≈ Sine", value: "bi-activity" },
    { label: "Soundwave / AC wave", value: "bi-soundwave" },
    { label: "± / Polarity", value: "bi-plus-slash-minus" },
    { label: "Bidirectional / Transfer", value: "bi-arrow-left-right" },
    { label: "Thunderbolt", value: "bi-thunderbolt" },
    { label: "EV / Charging Station", value: "bi-ev-station" },
    { label: "Gauge / Frequency", value: "bi-speedometer2" },
    { label: "Trend / Load", value: "bi-graph-up" },
    // Facility / network
    { label: "CPU / Device", value: "bi-cpu" },
    { label: "Server Rack", value: "bi-hdd-rack" },
    { label: "Server", value: "bi-server" },
    { label: "Building", value: "bi-building" },
    { label: "House", value: "bi-house-door" },
    { label: "Machine / Gear", value: "bi-gear-wide-connected" },
    { label: "Antenna / Signal", value: "bi-broadcast" },
    { label: "Signal Strength", value: "bi-reception-4" },
    { label: "WiFi", value: "bi-wifi" },
    { label: "WiFi Off", value: "bi-wifi-off" },
    { label: "Hazard", value: "bi-radioactive" },
    { label: "Compass / Navigate", value: "bi-compass" },
    { label: "Map", value: "bi-map" },
    { label: "Dashboard Grid", value: "bi-grid-1x2" }
  ];

  function normalizeBiIconClass(name) {
    const n = String(name || 'bi-circle').trim();
    if (!n) return 'bi bi-circle';
    if (n.startsWith('bi bi-')) return n;
    if (n.startsWith('bi-')) return 'bi ' + n;
    return 'bi bi-' + n;
  }

  function getWidgetIconLabel(value, options = WIDGET_ICON_OPTIONS) {
    const found = options.find(o => o.value === value);
    return found ? found.label : value;
  }

  function buildIconSelectOptionsHtml(options, selected) {
    let html = options.map(o => `
      <button type="button" class="widget-icon-select-option${selected === o.value ? ' active' : ''}"
        data-value="${o.value}" data-label="${String(o.label).replace(/"/g, '&quot;')}" title="${String(o.label).replace(/"/g, '&quot;')}">
        <i class="${normalizeBiIconClass(o.value)}"></i>
        <span>${o.label}</span>
      </button>
    `).join('');
    if (selected && !options.some(o => o.value === selected)) {
      html = `
      <button type="button" class="widget-icon-select-option active"
        data-value="${String(selected).replace(/"/g, '&quot;')}" data-label="${String(selected).replace(/"/g, '&quot;')}" title="${String(selected).replace(/"/g, '&quot;')}">
        <i class="${normalizeBiIconClass(selected)}"></i>
        <span>${String(selected).replace(/</g, '&lt;')}</span>
      </button>` + html;
    }
    return html;
  }

  function closeAllWidgetIconMenus(except) {
    document.querySelectorAll('.widget-icon-select.open').forEach(el => {
      if (except && el === except) return;
      el.classList.remove('open', 'drop-up');
    });
  }

  function positionWidgetIconMenu(wrapper) {
    const menu = wrapper.querySelector('.widget-icon-select-menu');
    if (!menu) return;

    wrapper.classList.remove('drop-up');
    menu.style.left = '';
    menu.style.right = '0';

    const rect = menu.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.top;
    const spaceAbove = rect.top - (wrapper.getBoundingClientRect().height || 0);

    if (spaceBelow < Math.min(280, rect.height || 280) && spaceAbove > spaceBelow) {
      wrapper.classList.add('drop-up');
    }

    const after = menu.getBoundingClientRect();
    if (after.left < 8) {
      menu.style.right = 'auto';
      menu.style.left = '0';
    }
  }

  function initWidgetIconSelectors(root = document) {
    root.querySelectorAll('.widget-icon-select').forEach(wrapper => {
      if (wrapper.dataset.bound === '1') return;
      wrapper.dataset.bound = '1';

      const toggle = wrapper.querySelector('.widget-icon-select-toggle');
      const hidden = wrapper.querySelector('input[type="hidden"][data-field]');

      toggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = !wrapper.classList.contains('open');
        closeAllWidgetIconMenus();
        wrapper.classList.toggle('open', willOpen);
        if (willOpen) {
          requestAnimationFrame(() => positionWidgetIconMenu(wrapper));
        } else {
          wrapper.classList.remove('drop-up');
        }
      });

      wrapper.querySelectorAll('.widget-icon-select-option').forEach(option => {
        option.addEventListener('click', (e) => {
          e.stopPropagation();
          selectWidgetIconOption(wrapper, option);
        });
      });

      hidden?.addEventListener('change', updatePreviewFromForm);
    });
  }

  function selectWidgetIconOption(wrapper, optionEl) {
    const hidden = wrapper.querySelector('input[type="hidden"][data-field]');
    const toggleIcon = wrapper.querySelector('.widget-icon-select-toggle i');
    const toggleLabel = wrapper.querySelector('.widget-icon-select-toggle span');
    const value = optionEl.dataset.value || 'bi-circle';
    const label = optionEl.dataset.label || getWidgetIconLabel(value);

    if (hidden) hidden.value = value;
    if (toggleIcon) toggleIcon.className = normalizeBiIconClass(value);
    if (toggleLabel) toggleLabel.textContent = label;

    wrapper.querySelectorAll('.widget-icon-select-option').forEach(el => {
      el.classList.toggle('active', el === optionEl);
    });

    wrapper.classList.remove('open', 'drop-up');
    hidden?.dispatchEvent(new Event('change', { bubbles: true }));
    updatePreviewFromForm();
  }

  if (!window.__widgetIconSelectDocBound) {
    window.__widgetIconSelectDocBound = true;
    document.addEventListener('click', () => closeAllWidgetIconMenus());
  }

  function withShowBorder(fields) {
    if (!Array.isArray(fields)) return fields;
    if (fields.some(f => f && f.key === 'show_border')) return fields;
    return fields.concat(SHOW_BORDER_FIELD);
  }

  function withWidgetAppearance(fields) {
    if (!Array.isArray(fields)) return fields;
    fields = withShowBorder(fields);
    if (!fields.some(f => f && f.key === 'bg_color')) fields = fields.concat(WIDGET_BG_COLOR_FIELD);
    if (!fields.some(f => f && f.key === 'bg_opacity')) fields = fields.concat(WIDGET_BG_OPACITY_FIELD);
    return fields;
  }

  const WidgetFieldMap = {
    value_box: [{
        key: "label",
        label: "Label",
        type: "text",
        class: "col-sm-12"
      },
      {
        key: "device_id",
        label: "Device ID",
        type: "select"
      },
      {
        key: "metric_key",
        label: "Metric Key",
        type: "select"
      },
      {
        key: "unit",
        label: "Unit",
        type: "text"
      },
      {
        key: "show_icon",
        label: "Show Icon",
        type: "select",
        class: "col-sm-6",
        options: [
          { label: "Hide", value: "0", default: true },
          { label: "Show (left side)", value: "1" }
        ]
      },
      {
        key: "icon",
        label: "Icon",
        type: "icon_select",
        class: "col-sm-6",
        default: "bi-circle",
        options: WIDGET_ICON_OPTIONS
      },
      {
        key: "link_url",
        label: "Click URL",
        type: "text",
        class: "col-sm-12"
      },
      {
        key: "value_maps",
        label: "Value Maps",
        type: "value_maps",
        class: "col-sm-12"
      }
    ],
    temp_hum_sensor: [{
        key: "label",
        label: "Label",
        type: "text"
      },
      {
        key: "device_id",
        label: "Device",
        type: "select"
      },{},
      {
        key: "temp_key",
        label: "Temperure Metric",
        type: "select"
      },
      {
        key: "hum_key",
        label: "Humidity Metric",
        type: "select"
      }
    ],
    ratio: [{
        key: "label",
        label: "Label",
        type: "text",
        class: "col-sm-12"
      }, {},
      {
        key: "label1",
        label: "Label 1",
        type: "text"
      },
      {
        key: "device_id1",
        label: "Device ID 1",
        type: "select"
      },
      {
        key: "metric_key1",
        label: "Metric Key 1",
        type: "select"
      },
      {
        key: "unit1",
        label: "Unit1",
        type: "text"
      }, {},
      {
        key: "label2",
        label: "Label 2",
        type: "text"
      },
      {
        key: "device_id2",
        label: "Device ID 2",
        type: "select"
      },
      {
        key: "metric_key2",
        label: "Metric Key 2",
        type: "select"
      },
      {
        key: "unit2",
        label: "Unit2",
        type: "text"
      }
    ],
    gauge: [{
        key: "label",
        label: "Label",
        type: "text"
      },
      {
        key: "device_id",
        label: "Device ID",
        type: "select"
      },
      {
        key: "metric_key",
        label: "Metric Key",
        type: "select"
      },
      {
        key: "unit",
        label: "Unit",
        type: "text"
      },
      {
        key: "min",
        label: "Min",
        type: "number"
      },
      {
        key: "max",
        label: "Max",
        type: "number"
      }
    ],
    level_bar: [{
        key: "label",
        label: "Label",
        type: "text"
      },
      {
        key: "device_id",
        label: "Device ID",
        type: "select"
      },
      {
        key: "metric_key",
        label: "Metric Key",
        type: "select"
      },
      {
        key: "unit",
        label: "Unit",
        type: "text"
      },
      {
        key: "min",
        label: "Min",
        type: "number"
      },
      {
        key: "max",
        label: "Max",
        type: "number"
      },
      {
        key: "orientation",
        label: "Orientation",
        type: "select",
        class: "col-sm-6",
        options: [
          { label: "Vertical", value: "vertical", default: true },
          { label: "Horizontal", value: "horizontal" }
        ]
      }
    ],
    camera_stream: [{
        key: "label",
        label: "Label",
        type: "text",
        class: "col-sm-12"
      },
      {
        key: "device_id",
        label: "Camera Device",
        type: "select"
      }
    ],
    line_chart: [{
      key: "label",
      label: "Chart Label",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "show_border",
      label: "Show Border",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "Show", value: "1", default: true },
        { label: "Hide", value: "0" }
      ]
    }, {
      key: "bg_color",
      label: "Background Color",
      type: "widget_color",
      class: "col-sm-6"
    }, {
      key: "bg_opacity",
      label: "Background Opacity (%)",
      type: "number",
      class: "col-sm-6",
      value: 100,
      min: 0,
      max: 100
    }, {
      key: "yTitle",
      label: "Y-Axis Title",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "yUnit",
      label: "Y-Axis Unit",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "yMin",
      label: "Y-Axis Min",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "yMax",
      label: "Y-Axis Max",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "xTitle",
      label: "X-Axis Title",
      type: "text",
      class: "col-sm-6"
    }],
    bar_chart: [{
      key: "label",
      label: "Chart Label",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "show_border",
      label: "Show Border",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "Show", value: "1", default: true },
        { label: "Hide", value: "0" }
      ]
    }, {
      key: "bg_color",
      label: "Background Color",
      type: "widget_color",
      class: "col-sm-6"
    }, {
      key: "bg_opacity",
      label: "Background Opacity (%)",
      type: "number",
      class: "col-sm-6",
      value: 100,
      min: 0,
      max: 100
    }, {
      key: "yTitle",
      label: "Y-Axis Title",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "yUnit",
      label: "Y-Axis Unit",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "yMin",
      label: "Y-Axis Min",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "yMax",
      label: "Y-Axis Max",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "sort_order",
      label: "Sort Order",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "Descending", value: "desc", default: true },
        { label: "Ascending", value: "asc" },
        { label: "As Added", value: "none" }
      ]
    }, {
      key: "orientation",
      label: "Orientation",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "Horizontal", value: "horizontal", default: true },
        { label: "Vertical", value: "vertical" }
      ]
    }, {
      key: "limit",
      label: "Max Bars",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "setpoint_value",
      label: "Set Point",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "setpoint_color",
      label: "Set Point Line Color",
      type: "widget_color",
      class: "col-sm-6"
    }, {
      key: "bar_color_over",
      label: "Over Set Point Color",
      type: "widget_color",
      class: "col-sm-6"
    }, {
      key: "bar_color_under",
      label: "Under Set Point Color",
      type: "widget_color",
      class: "col-sm-6"
    }],
    line_chart_dynamic: [{
      key: "label",
      label: "Chart Label",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "yTitle",
      label: "Y-Axis Title",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "yUnit",
      label: "Y-Axis Unit",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "yMin",
      label: "Y-Axis Min",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "yMax",
      label: "Y-Axis Max",
      type: "number",
      class: "col-sm-6"
    }, {
      key: "xTitle",
      label: "X-Axis Title",
      type: "text",
      class: "col-sm-6"
    },{},
    { 
      key: "time_range",
      label: "Time Range",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "Last 6 Hours", value: "6h" },
        { label: "Last 12 Hours", value: "12h" },
        { label: "Last 24 Hours", value: "24h" , default: true},
        { label: "Last 7 Days", value: "7d" },
        { label: "Last 30 Days", value: "30d" },
      ]
    },
    { 
      key: "time_interval",
      label: "Time Interval",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "1 Minute", value: "1m" },
        { label: "5 Minutes", value: "5m" },
        { label: "10 Minutes", value: "10m" },
        { label: "15 Minutes", value: "15m" },
        { label: "30 Minutes", value: "30m" },
        { label: "1 Hour", value: "1h", default: true },
        { label: "4 Hours", value: "4h" },
        { label: "12 Hours", value: "12h" },
        { label: "24 Hours", value: "24h" },
        { label: "1 Day", value: "1d" },
      ]
    },{// Limit Data point select
      key: "limit",
      label: "Limit Data Point",
      type: "number",
      class: "col-sm-6",
      value: 24
    },
    { // Display Min max avg select
      key: "display",
      label: "Display",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "Min", value: "min" },
        { label: "Max", value: "max" },
        { label: "Average", value: "avg" , default: true},
        { label: "First", value: "first" },
        { label: "Last", value: "last" }
      ]
    },
    {
      key: "chart_height_pct",
      label: "Chart Height (%)",
      type: "number",
      class: "col-sm-6",
      value: 60
    }
  ],
    table: [{
      key: "label",
      label: "Table Label",
      type: "text",
      class: "col-sm-12"
    }],
    status_grid: [{
      key: "label",
      label: "Grid Label",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "link_url",
      label: "Click URL",
      type: "text",
      class: "col-sm-12"
    }],
    navigator: [{
      key: "label",
      label: "Title",
      type: "text",
      class: "col-sm-12"
    }],
    alarm_summary: [{
      key: "label",
      label: "Title",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "show_total",
      label: "Show Total",
      type: "select",
      class: "col-sm-6",
      options: [
        { label: "Show", value: "1", default: true },
        { label: "Hide", value: "0" }
      ]
    }],
    ups_panel: [{
      key: "label",
      label: "Panel Title",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "device_id",
      label: "UPS Device",
      type: "select"
    }, {
      key: "capacity_key",
      label: "Battery Capacity Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "voltage_key",
      label: "Battery Voltage Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "load_r_key",
      label: "Load R Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "load_s_key",
      label: "Load S Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "load_t_key",
      label: "Load T Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "mode_key",
      label: "Output Mode Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "runtime_key",
      label: "Runtime Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "connection_key",
      label: "Connection Key",
      type: "text",
      class: "col-sm-6"
    }],
    power_meter: [{
      key: "label",
      label: "Meter Title",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "device_id",
      label: "Power Meter Device",
      type: "select"
    }, {
      key: "power_key",
      label: "Active Power Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "connection_key",
      label: "Connection Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "voltage_r_key",
      label: "Voltage R Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "voltage_s_key",
      label: "Voltage S Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "voltage_t_key",
      label: "Voltage T Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "current_r_key",
      label: "Current R Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "current_s_key",
      label: "Current S Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "current_t_key",
      label: "Current T Key",
      type: "text",
      class: "col-sm-4"
    }],
    heatmap: [{
      key: "label",
      label: "Heatmap Title",
      type: "text",
      class: "col-sm-12"
    }],
    cooling_panel: [{
      key: "label",
      label: "Panel Title",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "device_id",
      label: "Cooling Device",
      type: "select"
    }, {
      key: "connection_key",
      label: "Connection Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "running_key",
      label: "Running Status Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "alarm_key",
      label: "Alarm Key",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "running_on",
      label: "Running Label",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "running_off",
      label: "Stopped Label",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "alarm_on",
      label: "Alarm Label",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "alarm_off",
      label: "Normal Label",
      type: "text",
      class: "col-sm-6"
    }, {
      key: "supply_temp_key",
      label: "Supply Temp Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "return_temp_key",
      label: "Return Temp Key",
      type: "text",
      class: "col-sm-4"
    }, {
      key: "setpoint_key",
      label: "Setpoint Key",
      type: "text",
      class: "col-sm-4"
    }],
    zone_overview: [{
      key: "zone_name",
      label: "Card Name (Building/Room)",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "zone_subtitle",
      label: "Subtitle / Footer",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "link_url",
      label: "Click URL (link to dashboard)",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "value_maps",
      label: "Value Maps",
      type: "value_maps",
      class: "col-sm-12"
    }],
    svg_widget: [{
      key: "label",
      label: "Label",
      type: "text",
      class: "col-sm-12"
    }, {
      key: "svg_file",
      label: "SVG File",
      type: "hidden",
      class: "d-none"
    }],
    map_view: [{
        key: "background",
        label: "Background Image Path",
        type: "hidden",
        class: "d-none"
      }
      // config.items จะจัดการภายในแบบพิเศษ ไม่ต้อง define ที่นี่
    ],value_box2: [{
        key: "label",
        label: "Label",
        type: "text",
        class: "col-sm-6"
      },
      {
        key: "spector",
        label: "Spector",
        type: "text",
        class: "col-sm-6"
      },{},
      {
        key: "label1",
        label: "Label 1",
        type: "text"
      },
      {
        key: "device_id1",
        label: "Device ID 1",
        type: "select"
      },
      {
        key: "metric_key1",
        label: "Metric Key 1",
        type: "select"
      },
      {
        key: "unit1",
        label: "Unit1",
        type: "text"
      },{},
      {
        key: "label2",
        label: "Label 2",
        type: "text"
      },
      {
        key: "device_id2",
        label: "Device ID 2",
        type: "select"
      },
      {
        key: "metric_key2",
        label: "Metric Key 2",
        type: "select"
      },
      {
        key: "unit2",
        label: "Unit2",
        type: "text"
      }
    ],
    value_box3: [{
        key: "label",
        label: "Label",
        type: "text",
        class: "col-sm-6"
      },
      {
        key: "spector",
        label: "Spector",
        type: "text",
        class: "col-sm-6"
      },
      {
        key: "label1",
        label: "Label 1",
        type: "text"
      },
      {
        key: "device_id1",
        label: "Device ID 1",
        type: "select"
      },
      {
        key: "metric_key1",
        label: "Metric Key 1",
        type: "select"
      },
      {
        key: "unit1",
        label: "Unit1",
        type: "text"
      }, {},
      {
        key: "label2",
        label: "Label 2",
        type: "text"
      },
      {
        key: "device_id2",
        label: "Device ID 2",
        type: "select"
      },
      {
        key: "metric_key2",
        label: "Metric Key 2",
        type: "select"
      },
      {
        key: "unit2",
        label: "Unit2",
        type: "text"
      }, {},
      {
        key: "label3",
        label: "Label 3",
        type: "text"
      },
      {
        key: "device_id3",
        label: "Device ID 3",
        type: "select"
      },
      {
        key: "metric_key3",
        label: "Metric Key 3",
        type: "select"
      },
      {
        key: "unit3",
        label: "Unit3",
        type: "text"
      }
    ]
  };

  Object.keys(WidgetFieldMap).forEach(function(type) {
    WidgetFieldMap[type] = withWidgetAppearance(WidgetFieldMap[type]);
  });

  const widgetHandlers = {
      value_box: {
        renderPreview: renderValueBoxPreview
      },
    gauge: {
      renderPreview: renderGaugePreview
    },
    level_bar: {
      renderPreview: renderLevelBarPreview
    },
    value_box2: {
      renderPreview: renderValueBox2Preview
    },
    map_view: {
      renderPreview: renderMapViewEditor
    },
    ratio: {
      renderPreview: renderRationPreview
    },
    temp_hum_sensor: {
      renderPreview: renderTempHumBoxPreview
    },
    line_chart: {
      renderPreview: renderLineChartConfigPanel
    },
    bar_chart: {
      renderPreview: renderBarChartPreview
    },
    line_chart_dynamic: {
      renderPreview: renderLineChartDynamicConfigPanel
    },
    table: {
      renderPreview: renderTablePreview
    },
    status_grid: {
      renderPreview: renderStatusGridPreview
    },
    navigator: {
      renderPreview: renderNavigatorPreview
    },
    alarm_summary: {
      renderPreview: renderAlarmSummaryPreview
    },
    ups_panel: {
      renderPreview: renderUpsPanelPreview
    },
    power_meter: {
      renderPreview: renderPowerMeterPreview
    },
    heatmap: {
      renderPreview: renderHeatmapPreview
    },
    cooling_panel: {
      renderPreview: renderCoolingPanelPreview
    },
    zone_overview: {
      renderPreview: renderZoneOverviewPreview
    },
    svg_widget: {
      renderPreview: renderSvgWidgetPreview
    },
    camera_stream: {
      renderPreview: function () {
        return '<div class="p-2 text-muted small">Camera live stream (RTSP → HLS buffer)</div>';
      }
    }
  };

  function openWidgetConfigPanel() {
    document.getElementById("widget-config-panel").style.display = "flex";
  }

  function closeWidgetConfigPanel(save = false, saveAndExit = false) {
    document.getElementById("widget-config-panel").style.display = "none";
    if (save) {
      saveLayout();
    }
  }

  const api = {
    devices: '/api/devices',
    metrics: id => `/api/metrics/${id}`
  };

  async function fetchDevices() {
    const res = await fetch(api.devices);
    const data = await res.json();
    return data.devices || [];
  }

  async function fetchMetrics(deviceId) {
    const res = await fetch(api.metrics(deviceId));
    const data = await res.json();
    return data.metrics || [];
  }

  function generateFormField(field, val = '') {
    if (Object.keys(field).length === 0) return '<hr>';

    if (field.type === 'select') {
      return `
      <div class="${field.class || 'col-sm-6'} mb-0 mt-0">
        <label class="form-label small">${field.label}</label>
        <select id="cfg-${field.key}" class="form-select form-select-sm" data-field="${field.key}">
          ${(field.options || []).map(option => `<option value="${option.value}" ${val === option.value || (option.default && !val) ? 'selected' : ''}>${option.label}</option>`).join('')}
        </select>
      </div>
    `;
    }else if (field.type === 'number') {
      const min = field.min !== undefined ? ` min="${field.min}"` : '';
      const max = field.max !== undefined ? ` max="${field.max}"` : '';
      return `
      <div class="${field.class || 'col-sm-6'} mb-0 mt-0">
        <label class="form-label small">${field.label}</label>
        <input type="number" 
               class="form-control form-control-sm " 
               id="cfg-${field.key}" 
               data-field="${field.key}" 
               value="${val !== '' && val !== undefined ? val : (field.value ?? '')}"${min}${max}>
      </div>
    `;
    } else if (field.type === 'widget_color') {
      return `
      <div class="${field.class || 'col-sm-6'} mb-0 mt-0">
        <label class="form-label small">${field.label}</label>
        <div class="input-group input-group-sm">
          <input type="color" class="form-control form-control-color" id="cfg-${field.key}-picker" value="${val || '#ffffff'}">
          <input type="text" class="form-control" id="cfg-${field.key}" data-field="${field.key}" value="${val || ''}" placeholder="Theme default">
          <button type="button" class="btn btn-outline-secondary" title="Clear" onclick="document.getElementById('cfg-${field.key}').value='';">×</button>
        </div>
      </div>
    `;
    } else if (field.type === 'icon_select') {
      const options = field.options || WIDGET_ICON_OPTIONS;
      const selected = val || field.default || 'bi-circle';
      const iconClass = normalizeBiIconClass(selected);
      const label = getWidgetIconLabel(selected, options);
      return `
      <div class="${field.class || 'col-sm-6'} mb-0 mt-0">
        <label class="form-label small">${field.label}</label>
        <div class="widget-icon-select" data-field-key="${field.key}">
          <input type="hidden" id="cfg-${field.key}" data-field="${field.key}" value="${String(selected).replace(/"/g, '&quot;')}">
          <button type="button" class="widget-icon-select-toggle form-select form-select-sm">
            <i class="${iconClass}"></i>
            <span>${label}</span>
          </button>
          <div class="widget-icon-select-menu">
            <div class="widget-icon-select-grid">
              ${buildIconSelectOptionsHtml(options, selected)}
            </div>
          </div>
        </div>
      </div>
    `;
    }
    else {
      return `
      <div class="${field.class || 'col-sm-6'} mb-0 mt-0">
        <label class="form-label small">${field.label}</label>
        <input type="${field.type || 'text'}" 
               class="form-control form-control-sm " 
               id="cfg-${field.key}" 
               data-field="${field.key}" 
               value="${val || ''}">
      </div>
    `;
    }
  }

  let grid = GridStack.init({
    cellHeight: 50,
    float: true,
    disableOneColumnMode: false,
    column: 16,
    margin: 5
  });

  const items = <?= $layout_json ?>;

  items.forEach(item => {
    const fullData = {
      ...item,
      ...(item.config || {})
    };
    addWidget(fullData);
  });

  function onAddWidget() {
    const id = document.getElementById('widgetSelector').value;
    if (!id) return alert(" <?php echo lang('please_select_a_widget') ?>");
    addWidgetFromSelection(parseInt(id));
    const modal = bootstrap.Modal.getInstance(document.getElementById('addWidgetModal'));
    if (modal) modal.hide();
  }

  function addWidgetFromSelection(widgetId) {
    fetch(`/widgets/get_widget/${widgetId}`)
      .then(res => res.json())
      .then(data => {
        const w = data.widget;
        const cfg = w.config || {};

        const widgetData = {
          widget_id: w.id,
          widget_type: w.widget_type,
          ...cfg,
          label: cfg.label || w.name,
          device_id: cfg.device_id || '',
          metric_key: cfg.metric_key || '',
          unit: cfg.unit || '',
          min: cfg.min ?? '',
          max: cfg.max ?? '',
          time_range: cfg.time_range || '',
          x: 0,
          y: grid.getRow() || 0,
          w: 3,
          h: 2
        };

        const el = addWidget(widgetData);
        if (el) {
          requestAnimationFrame(() => {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          });
        }
      })
      .catch(err => {

        alert(" <?php echo lang('failed_to_load_widget') ?>");
      });
  }

  function generateUID() {
    return 'uid-' + Math.random().toString(36).substr(2, 9);
  }

  function addWidget(data = {}) {
    const el = document.createElement('div');
    el.classList.add('grid-stack-item');

    const uid = generateUID();
    el.dataset.uid = uid;
    el.dataset.widget_id = data.widget_id || 0;
    el.dataset.widget_type = data.widget_type || '';

    // Dynamic dataset assignment
    const fields = WidgetFieldMap[data.widget_type] || [];
    fields.forEach(f => {
      if (!f.key) return;
      if (f.key === 'value_maps') {
        el.dataset.value_maps = typeof data.value_maps === 'string'
          ? data.value_maps
          : JSON.stringify(data.value_maps || []);
        return;
      }
      el.dataset[f.key] = data[f.key] ?? '';
    });

    if (data.widget_type === 'map_view') {
      el.dataset.items = JSON.stringify(data.items || []);
      console.log("addWidget widget data", el.dataset.items);
    }
    if (data.widget_type === 'line_chart') {
      el.dataset.items = JSON.stringify(data.items || []);
      console.log("addWidget widget data Linechart", el.dataset.items);
    }
    if (data.widget_type === 'bar_chart') {
      el.dataset.items = JSON.stringify(data.items || []);
      console.log("addWidget widget data BarChart", el.dataset.items);
    }
    if (data.widget_type === 'line_chart_dynamic') {
      el.dataset.items = JSON.stringify(data.items || []);
      console.log("addWidget widget data Line_chart_dynamic", el.dataset.items);
    }
    if (data.widget_type === 'table') {
      el.dataset.items = JSON.stringify(data.items || []);
    }
    if (data.widget_type === 'status_grid') {
      el.dataset.items = JSON.stringify(data.items || []);
      el.dataset.columns = data.columns || '3';
      el.dataset.link_url = data.link_url || '';
    }
    if (data.widget_type === 'navigator') {
      el.dataset.items = JSON.stringify(data.items || []);
      el.dataset.columns = data.columns || '2';
    }
    if (data.widget_type === 'heatmap') {
      el.dataset.items = JSON.stringify(data.items || []);
      el.dataset.columns = data.columns || '4';
      el.dataset.warn_threshold = data.warn_threshold ?? '25';
      el.dataset.alarm_threshold = data.alarm_threshold ?? '30';
      el.dataset.unit = data.unit || '°C';
    }
    if (data.widget_type === 'zone_overview') {
      el.dataset.items = JSON.stringify(data.items || []);
      el.dataset.alarm_devices = JSON.stringify(data.alarm_devices || []);
      el.dataset.zone_name = data.zone_name || data.label || '';
      el.dataset.zone_subtitle = data.zone_subtitle || '';
      el.dataset.link_url = data.link_url || '';
      el.dataset.icon = data.icon || 'bi-building';
      el.dataset.show_primary = data.show_primary ?? '1';
      el.dataset.primary_label = data.primary_label || '';
      el.dataset.primary_unit = data.primary_unit || '';
      el.dataset.primary_device_id = data.primary_device_id || '';
      el.dataset.primary_metric_key = data.primary_metric_key || '';
      el.dataset.value_maps = typeof data.value_maps === 'string'
        ? data.value_maps
        : JSON.stringify(data.value_maps || []);
    }
    if (data.widget_type === 'gauge' || data.widget_type === 'level_bar') {
      el.dataset.color_ranges = JSON.stringify(data.color_ranges || []);
    }
    if (data.widget_type === 'svg_widget') {
      el.dataset.bindings = JSON.stringify(data.bindings || []);
      el.dataset.svg_file = data.svg_file || '';
      el.dataset.show_border = data.show_border ?? '1';
    }

    el.innerHTML = `
    <div class="grid-stack-item-content d-flex flex-row p-1 justify-content-between">
      <div class="d-flex flex-column p-1 flex-grow-1" style="min-width: 100px;">
        <strong><?php echo lang('widget') ?></strong>
        <div class="text-muted small mb-1">${data.label || ''}</div>
        <div class="text-secondary small">ID: ${data.widget_id}</div>
      </div>
      <div class="d-flex flex-column gap-1 flex-shrink-0 align-items-stretch">
        <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0" title="<?php echo lang('configure_button') ?>" onclick="openConfigureModal('${uid}')">
          <i class="bi bi-gear"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0" title="<?php echo lang('duplicate') ?>" onclick="duplicateWidget(this)">
          <i class="bi bi-copy"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-0" title="<?php echo lang('remove') ?>" onclick="removeWidget(this)">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>
  `;

    grid.el.appendChild(el);
    const defaultWidgetSizes = {
      heatmap: [8, 4],
      bar_chart: [8, 4],
      zone_overview: [4, 3],
      status_grid: [6, 3],
      ups_panel: [4, 4],
      power_meter: [4, 3],
      cooling_panel: [4, 3],
      alarm_summary: [3, 2],
      level_bar: data.orientation === 'horizontal' ? [4, 2] : [2, 4],
      navigator: [8, 2]
    };
    const [defaultW, defaultH] = defaultWidgetSizes[data.widget_type] || [data.w || 3, data.h || 2];
    grid.makeWidget(el, {
      x: data.x ?? 0,
      y: data.y ?? 0,
      w: data.w || defaultW,
      h: data.h || defaultH
    });
    return el;
  }

  function updateAttr(input, attr) {
    const item = input.closest('.grid-stack-item');
    if (item) item.dataset[attr] = input.value;
  }

  function removeWidget(btn) {
    const el = btn.closest('.grid-stack-item');
    grid.removeWidget(el);
  }

  function duplicateWidget(btn) {
    const el = btn.closest('.grid-stack-item');
    if (!el) return;
    const node = grid.engine.nodes.find(n => n.el === el);
    if (!node) return;

    const data = {
      widget_id: parseInt(el.dataset.widget_id || 0, 10),
      widget_type: el.dataset.widget_type || '',
      x: (Number(node.x) || 0) + 1,
      y: (Number(node.y) || 0) + 1,
      w: node.w,
      h: node.h
    };

    Object.keys(el.dataset).forEach(key => {
      if (['uid', 'widget_id', 'widget_type', 'config'].includes(key)) return;
      const raw = el.dataset[key];
      if (typeof raw === 'string' && (raw.startsWith('[') || raw.startsWith('{'))) {
        try {
          data[key] = JSON.parse(raw);
          return;
        } catch (e) {}
      }
      data[key] = raw;
    });

    addWidget(data);
  }

  function saveLayout() {
    let result = [];

    grid.engine.nodes.forEach(node => {
      let el = node.el;
      const type = el.dataset.widget_type;
      const fields = WidgetFieldMap[type] || [];
      let config = {};
      if (el.dataset.config) {
        try {
          config = JSON.parse(el.dataset.config);
        } catch (e) {
          config = {};
        }
      }

      fields.forEach(f => {
        if (!f.key) return;
        if (f.key === 'value_maps') {
          try {
            config.value_maps = JSON.parse(el.dataset.value_maps || '[]');
          } catch (e) {
            config.value_maps = [];
          }
          return;
        }
        config[f.key] = el.dataset[f.key] || '';
      });

      if (type == "map_view") {
        //config.items =  JSON.parse(el.dataset.items || []);
        config.items = JSON.parse(el.dataset.items || []);
        //alert(`map_view config.itemsconfig\n${config.items}`);
      }

        if (type == "line_chart") {
          config.items = JSON.parse(el.dataset.items || []);
          //alert(`map_view config.itemsconfig\n${JSON.stringify(config.items)}`);
        }
        if (type == "bar_chart") {
          config.items = JSON.parse(el.dataset.items || []);
        }
        if (type == "line_chart_dynamic") {
          config.items = JSON.parse(el.dataset.items || []);
          //alert(`saveLayout widget data Line_chart_dynamic\n${JSON.stringify(config)}`);
          //console.log("saveLayout widget data Line_chart_dynamic", config.items);
        }
        if (type == "table") {
          config.items = JSON.parse(el.dataset.items || []);
        }
        if (type == "status_grid") {
          config.items = JSON.parse(el.dataset.items || '[]');
          config.columns = el.dataset.columns || '3';
          config.link_url = el.dataset.link_url || '';
        }
        if (type == "navigator") {
          config.items = JSON.parse(el.dataset.items || '[]');
          config.columns = el.dataset.columns || '2';
        }
        if (type == "heatmap") {
          config.items = JSON.parse(el.dataset.items || '[]');
          config.columns = el.dataset.columns || '4';
          config.warn_threshold = el.dataset.warn_threshold ?? '25';
          config.alarm_threshold = el.dataset.alarm_threshold ?? '30';
          config.unit = el.dataset.unit || '°C';
        }
        if (type == "zone_overview") {
          config.items = JSON.parse(el.dataset.items || '[]');
          try {
            config.alarm_devices = JSON.parse(el.dataset.alarm_devices || '[]');
          } catch (e) {
            config.alarm_devices = [];
          }
          config.zone_name = el.dataset.zone_name || config.label || '';
          config.zone_subtitle = el.dataset.zone_subtitle || '';
          config.link_url = el.dataset.link_url || '';
          config.icon = el.dataset.icon || 'bi-building';
          config.show_primary = el.dataset.show_primary ?? '1';
          config.primary_label = el.dataset.primary_label || '';
          config.primary_unit = el.dataset.primary_unit || '';
          config.primary_device_id = el.dataset.primary_device_id || '';
          config.primary_metric_key = el.dataset.primary_metric_key || '';
          try {
            config.value_maps = JSON.parse(el.dataset.value_maps || '[]');
          } catch (e) {
            config.value_maps = [];
          }
        }
        if (type == "gauge" || type == "level_bar") {
          try {
            config.color_ranges = JSON.parse(el.dataset.color_ranges || '[]');
          } catch (e) {
            config.color_ranges = [];
          }
        }
        if (type == "svg_widget") {
          try {
            config.bindings = JSON.parse(el.dataset.bindings || '[]');
          } catch (e) {
            config.bindings = [];
          }
        }

      result.push({
        widget_id: parseInt(el.dataset.widget_id || 0),
        widget_type: type,
        x: node.x,
        y: node.y,
        w: node.w,
        h: node.h,
        config: config
      });
    });

    document.getElementById('layoutJson').value = JSON.stringify(result);
    document.getElementById('layoutForm').submit();
  }

  let zoomLevel = 1;

  function setZoom(level) {
    zoomLevel = level;
    document.getElementById('layoutCanvas').style.transform = `scale(${zoomLevel})`;
  }

  function fitToScreen() {
    const canvas = document.getElementById('layoutCanvas');
    const scroll = document.getElementById('layoutScroll');

    const scaleX = scroll.clientWidth / canvas.offsetWidth;
    const scaleY = scroll.clientHeight / canvas.offsetHeight;
    zoomLevel = Math.min(scaleX, scaleY);

    canvas.style.transform = `scale(${zoomLevel})`;
    centerCanvas();
  }

  function centerCanvas() {
    const scroll = document.getElementById('layoutScroll');
    const canvas = document.getElementById('layoutCanvas');

    const canvasWidth = canvas.offsetWidth * zoomLevel;
    const canvasHeight = canvas.offsetHeight * zoomLevel;

    scroll.scrollLeft = (canvasWidth - scroll.clientWidth) / 2;
    scroll.scrollTop = (canvasHeight - scroll.clientHeight) / 2;
  }

  function resetView() {
    zoomLevel = 1;
    document.getElementById('layoutCanvas').style.transform = 'scale(1)';
    centerCanvas();
  }

  window.addEventListener('load', function() {
    //fitToScreen();
    setZoom(1);
  });

  function setPreview(mode) {
    const wrapper = document.getElementById('dashboard-container');
    if (mode === 'desktop') {
      wrapper.style.width = '100%';
      wrapper.style.height = '700px';
    } else if (mode === 'tablet') {
      wrapper.style.width = '768px';
      wrapper.style.height = '1000px';
    } else if (mode === 'mobile') {
      wrapper.style.width = '375px';
      wrapper.style.height = '1200px';
    }
  }

  async function openConfigureModal(uid) {
    const el = document.querySelector(`.grid-stack-item[data-uid="${uid}"]`);
    if (!el) return;

    const type = el.dataset.widget_type;
    const fields = WidgetFieldMap[type] || [];
    document.getElementById("widget-config-form").innerHTML = "";
    const container = document.getElementById("widget-config-form");
    const preview = document.getElementById("widget-preview-box");

    openWidgetConfigPanel();

    // Clear form
    container.innerHTML = `<input type="hidden" id="configTargetUid" value="${uid}">`;

    const dataset = el.dataset;
    let datasetConfig = {};
    try {
      datasetConfig = dataset.config ? JSON.parse(dataset.config) : {};
    } catch (e) {
      datasetConfig = {};
    }

    for (const f of fields) {
      if (f.key === 'device_id') {
        const devices = await fetchDevices();
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'} mb-0 mt-0">
          <label class="form-label small">Device</label>
          <select id="cfg-device_id" class="form-select form-select-sm">
            <option value="">-- Select Device --</option>
            ${devices.map(d => `<option value="${d.id}" ${dataset.device_id == d.id ? 'selected' : ''}>${d.name}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'metric_key') {
        const metrics = await fetchMetrics(dataset.device_id);
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'}  mb-0 mt-0">
          <label class="form-label small">Metric</label>
          <select id="cfg-metric_key" class="form-select form-select-sm">
            <option value="">-- Select Metric --</option>
            ${metrics.map(m => `<option value="${m.key}" data-meta='${JSON.stringify(m)}' ${dataset.metric_key === m.key ? 'selected' : ''}>${m.label || m.key}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'temp_key') {
        const metrics = await fetchMetrics(dataset.device_id);
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'}  mb-0 mt-0">
          <label class="form-label small">Temperature Metric</label>
          <select id="cfg-temp_key" class="form-select form-select-sm">
            <option value="">-- Select Metric --</option>
            ${metrics.map(m => `<option value="${m.key}" data-meta='${JSON.stringify(m)}' ${dataset.temp_key === m.key ? 'selected' : ''}>${m.label || m.key}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'hum_key') {
        const metrics = await fetchMetrics(dataset.device_id);
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'}  mb-0 mt-0">
          <label class="form-label small">Humidity Metric</label>
          <select id="cfg-hum_key" class="form-select form-select-sm">
            <option value="">-- Select Metric --</option>
            ${metrics.map(m => `<option value="${m.key}" data-meta='${JSON.stringify(m)}' ${dataset.hum_key === m.key ? 'selected' : ''}>${m.label || m.key}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'device_id1') {
        const devices = await fetchDevices();
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'} mb-0 mt-0">
          <label class="form-label small">Device 1</label>
          <select id="cfg-device_id1" class="form-select form-select-sm">
            <option value="">-- Select Device --</option>
            ${devices.map(d => `<option value="${d.id}" ${dataset.device_id1 == d.id ? 'selected' : ''}>${d.name}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'metric_key1') {
        const metrics = await fetchMetrics(dataset.device_id1);
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'}  mb-0 mt-0">
          <label class="form-label small">Metric 1</label>
          <select id="cfg-metric_key1" class="form-select form-select-sm">
            <option value="">-- Select Metric --</option> 
            ${metrics.map(m => `<option value="${m.key}" data-meta='${JSON.stringify(m)}' ${dataset.metric_key1 === m.key ? 'selected' : ''}>${m.label || m.key}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'device_id2') {
        const devices = await fetchDevices();
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'} mb-0 mt-0">
          <label class="form-label small">Device 2</label>
          <select id="cfg-device_id2" class="form-select form-select-sm">
            <option value="">-- Select Device --</option>
            ${devices.map(d => `<option value="${d.id}" ${dataset.device_id2 == d.id ? 'selected' : ''}>${d.name}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'metric_key2') {
        const metrics = await fetchMetrics(dataset.device_id2);
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'}  mb-0 mt-0">
          <label class="form-label small">Metric 2</label>
          <select id="cfg-metric_key2" class="form-select form-select-sm">
            <option value="">-- Select Metric --</option>
            ${metrics.map(m => `<option value="${m.key}" data-meta='${JSON.stringify(m)}' ${dataset.metric_key2 === m.key ? 'selected' : ''}>${m.label || m.key}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'device_id3') {
        const devices = await fetchDevices();
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'} mb-0 mt-0">
          <label class="form-label small">Device 3</label>
          <select id="cfg-device_id3" class="form-select form-select-sm">
            <option value="">-- Select Device --</option>
            ${devices.map(d => `<option value="${d.id}" ${dataset.device_id3 == d.id ? 'selected' : ''}>${d.name}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.key === 'metric_key3') {
        const metrics = await fetchMetrics(dataset.device_id3);
        container.innerHTML += `
        <div class="${fields.class || 'col-sm-6'}  mb-0 mt-0">
          <label class="form-label small">Metric 3</label>
          <select id="cfg-metric_key3" class="form-select form-select-sm">
            <option value="">-- Select Metric --</option>
            ${metrics.map(m => `<option value="${m.key}" data-meta='${JSON.stringify(m)}' ${dataset.metric_key3 === m.key ? 'selected' : ''}>${m.label || m.key}</option>`).join('')}
          </select>
        </div>
      `;
      } else if (f.type === 'value_maps') {
        // Rendered after the loop via renderValueMapsPanel()
      } else if (type === 'map_view' && f.key === 'show_border') {
        // Rendered below Map Image upload
      } else if (type === 'svg_widget' && (f.key === 'show_border' || f.key === 'svg_file')) {
        // Rendered in svg_widget panel below
      } else {
        const fieldValue = (dataset[f.key] !== undefined && dataset[f.key] !== '')
          ? dataset[f.key]
          : (datasetConfig[f.key] ?? '');
        container.innerHTML += generateFormField(f, fieldValue);
      }

    }


    if (type === 'map_view') {
      container.innerHTML += `
        <div class="col-sm-12 mb-0 mt-0">
          <label class="form-label small">Map Image</label>
          <input type="file" id="cfg-backgroundfile" accept="${MAP_IMAGE_ACCEPT}" class="form-control form-control-sm mb-2">
          <div class="text-secondary small mb-2">Supported: JPEG, PNG, BMP, TIFF</div>
          <img id="preview-map-image" src="" style="max-width: 100%; height: auto; display: none;" class="border rounded mt-2">
        </div>
        `;
      const showBorderField = (WidgetFieldMap.map_view || []).find(f => f.key === 'show_border');
      if (showBorderField) {
        container.innerHTML += generateFormField(showBorderField, dataset.show_border);
      }
      // Load items from config
      const config = dataset.config ? JSON.parse(dataset.config) : {};


      let background = dataset.background ? `${MAP_UPLOAD_BASE}${dataset.background}` : '';
      let items_map = [];
      try {
        items_map = JSON.parse(dataset.items || '[]');
      } catch {}
      console.log("openConfigureModal items", items_map);
      document.getElementById('cfg-backgroundfile')?.addEventListener('change', async function() {
        const file = this.files?.[0];
        if (!file) return;

        const validation = validateMapImageFile(file);
        if (!validation.ok) {
          alert(validation.message);
          this.value = '';
          return;
        }

        const formData = new FormData();
        formData.append('image', file);

        const res = await fetch('/api/upload_map_image', {
          method: 'POST',
          body: formData,
        });

        const json = await res.json();
        //console.log("upload_map_image", json);
        if (json.status === 'success') {
          document.getElementById('cfg-background').value = json.filename;
          document.getElementById('preview-map-image').src = MAP_UPLOAD_BASE + json.filename;
          //document.getElementById('preview-map-image').style.display = 'block';
          background = MAP_UPLOAD_BASE + json.filename;
          initMapEditor(background, items_map);
        } else {
          alert('<?php echo lang('upload_failed') ?> ' + json.message);
        }
      });
      setTimeout(() => {
        initMapEditor(background, items_map);
      }, 200);
    }

    if (type === 'svg_widget') {
      container.innerHTML += `
        <div class="col-sm-12 mb-2 mt-0">
          <label class="form-label small">SVG File</label>
          <input type="hidden" id="cfg-svg_file" data-field="svg_file" value="${dataset.svg_file || ''}">
          <input type="file" id="cfg-svgfile" accept=".svg,image/svg+xml" class="form-control form-control-sm mb-2">
          <div class="text-secondary small mb-2">Upload .svg (max 2MB). Elements need unique id attributes.</div>
          <div id="svg-file-name" class="small text-info mb-2">${dataset.svg_file ? dataset.svg_file : 'No file selected'}</div>
        </div>
      `;
      const showBorderField = (WidgetFieldMap.svg_widget || []).find(f => f.key === 'show_border');
      if (showBorderField) {
        container.innerHTML += generateFormField(showBorderField, dataset.show_border);
      }

      let svgBindings = [];
      try {
        svgBindings = JSON.parse(dataset.bindings || '[]');
      } catch (e) {
        svgBindings = [];
      }
      renderSvgWidgetConfigPanel(svgBindings);

      document.getElementById('cfg-svgfile')?.addEventListener('change', async function() {
        const file = this.files?.[0];
        if (!file) return;
        if (!/\.svg$/i.test(file.name)) {
          alert('Only SVG files are allowed');
          this.value = '';
          return;
        }
        const formData = new FormData();
        formData.append('svg', file);
        try {
          const res = await fetch('/api/upload_svg_widget', { method: 'POST', body: formData });
          let json = null;
          try {
            json = await res.json();
          } catch (e) {
            if (!res.ok) {
              throw new Error(`Upload failed (${res.status})`);
            }
            throw new Error('Invalid server response');
          }
          if (!res.ok || json.status !== 'success') {
            throw new Error(json?.message || `Upload failed (${res.status})`);
          }
          document.getElementById('cfg-svg_file').value = json.filename;
          document.getElementById('svg-file-name').textContent = json.filename;
          if (typeof refreshSvgElementIdsFromFile === 'function') {
            refreshSvgElementIdsFromFile(json.filename);
          }
          updatePreviewFromForm();
        } catch (err) {
          console.error('[svg_widget] upload failed:', err);
          alert(err?.message || 'Upload failed');
        }
      });
    }


    if (type === 'line_chart' || type === 'line_chart_dynamic') {
      console.log("populateLineChartForm dataset", type);
      const config = {
        'label': dataset.label,
        'yMin': dataset.yMin==0 ? 0 : (dataset.yMin ? dataset.yMin : ''),
        'yMax': dataset.yMax ?? '',
        'yUnit': dataset.yUnit ?? '',
        'yTitle': dataset.yTitle ?? '',
        'xTitle': dataset.xTitle ?? '',
        'time_range': dataset.time_range ?? '',
        'time_interval': dataset.time_interval ?? '',
        'limit': dataset.limit ?? '',
        'display': dataset.display ?? '',
        'chart_height_pct': dataset.chart_height_pct ?? '60',
        'item': JSON.parse(dataset.items || '[]'),
      }
      let items = [];
      try {
        items = JSON.parse(dataset.items || '[]');
      } catch {}

      lineChartConfigPanel(type);
      populateLineChartForm(type, items || '[]');

    }

    if (type === 'table') {
      let items = [];
      try {
        items = JSON.parse(dataset.items || '[]');
      } catch {}
      tableConfigPanel();
      populateTableForm(items || []);
    }

    if (type === 'status_grid') {
      let items = [];
      try {
        items = JSON.parse(dataset.items || '[]');
      } catch {}
      statusGridConfigPanel();
      populateStatusGridForm(items || [], dataset.columns || '3');
    }

    if (type === 'navigator') {
      let items = [];
      try {
        items = JSON.parse(dataset.items || '[]');
      } catch {}
      navigatorConfigPanel();
      populateNavigatorForm(items || [], dataset.columns || '2');
    }

    if (type === 'heatmap') {
      let items = [];
      try {
        items = JSON.parse(dataset.items || '[]');
      } catch {}
      heatmapConfigPanel();
      populateHeatmapForm(items || [], {
        columns: dataset.columns || '4',
        warn_threshold: dataset.warn_threshold ?? '25',
        alarm_threshold: dataset.alarm_threshold ?? '30',
        unit: dataset.unit || '°C'
      });
    }

    if (type === 'bar_chart') {
      let items = [];
      try {
        items = JSON.parse(dataset.items || '[]');
      } catch {}
      barChartConfigPanel();
      populateBarChartForm(items || []);
    }

    if (type === 'zone_overview') {
      zoneOverviewConfigPanel();
      populateZoneOverviewForm(dataset);
    }

    if (type === 'value_box') {
      let valueMaps = [];
      try {
        valueMaps = JSON.parse(dataset.value_maps || '[]');
      } catch (e) {
        valueMaps = [];
      }
      renderValueMapsPanel(valueMaps);
    }

    if (type === 'gauge' || type === 'level_bar') {
      let colorRanges = [];
      try {
        colorRanges = JSON.parse(dataset.color_ranges || '[]');
      } catch (e) {
        colorRanges = [];
      }
      renderGaugeColorRangesPanel(colorRanges);
    }
    // Metric auto-fill handler (dynamic version)
    setTimeout(() => {
      const metricSelect = document.getElementById("cfg-metric_key");
      metricSelect?.addEventListener("change", function() {
        const option = this.selectedOptions[0];
        const meta = option?.dataset.meta ? JSON.parse(option.dataset.meta) : {};
        const type = el.dataset.widget_type;
        const fields = WidgetFieldMap[type] || [];

        fields.forEach(f => {
          const input = document.getElementById(`cfg-${f.key}`);
          if (input && typeof meta[f.key] !== 'undefined') {
            input.value = meta[f.key];
          }
        });

        updatePreviewFromForm();
      });

      // Re-bind for preview update
      const inputs = container.querySelectorAll("input, select");
      inputs.forEach(i => i.addEventListener("input", updatePreviewFromForm));
      initWidgetIconSelectors(container);
    }, 200);
    updatePreviewFromForm();
  }

  async function populateTableForm(items) {
    for (const item of items || []) {
      await addTableMetric(item);
    }
    updatePreviewFromForm();
  }

  function tableConfigPanel() {
    const panel = document.createElement('div');
    panel.innerHTML = `
    <div id="table-settings" class="widget-type-section mb-4" data-widget-type="table">
      <hr class="border-light">
      <label class="form-label text-white">Metrics</label>
      <div id="table-metric-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" onclick="addTableMetric()">+ Add Metric</button>
    </div>
    `;
    document.getElementById('widget-config-form').appendChild(panel);
  }

  function collectTableConfig() {
    const form = document.getElementById("widget-config-form");
    const config = {
      label: form.querySelector('[id="cfg-label"]')?.value || "",
      items: []
    };

    form.querySelectorAll("#table-metric-list .series-item").forEach(item => {
      config.items.push({
        label: item.querySelector('[name="items[][label]"]')?.value || "",
        unit: item.querySelector('[name="items[][unit]"]')?.value || "",
        device_id: item.querySelector('[name="items[][device_id]"]')?.value || "",
        metric_key: item.querySelector('[name="items[][metric_key]"]')?.value || ""
      });
    });

    return config;
  }

  async function populateLineChartForm(type, items) {
    const form = document.getElementById("widget-config-form");
    console.log("populateLineChartForm", items);

    for (const item of items || []) {
      if (type === 'line_chart') {
        addLinechartSeries();
      } else if (type === 'line_chart_dynamic') {
        addLinechartDynamicSeries();
      }
      const lastItem = form.querySelectorAll(".series-item:last-child")[0];

      lastItem.querySelector('[name="items[][label]"]').value = item.label || "";
      lastItem.querySelector('[name="items[][unit]"]').value = item.unit || "";
      lastItem.querySelector('[name="items[][color]"]').value = item.color || "";

      const deviceSelect = lastItem.querySelector('[name="items[][device_id]"]');
      const metricSelect = lastItem.querySelector('[name="items[][metric_key]"]');

      await loadDeviceOptions(deviceSelect, item.device_id);
      deviceSelect.value = item.device_id || "";

      await loadMetricOptions(item.device_id, metricSelect, item.metric_key);
      metricSelect.value = item.metric_key || "";
    }
    updatePreviewFromForm();
  }



  async function populateBarChartForm(items) {
    const form = document.getElementById("widget-config-form");

    for (const item of items || []) {
      addBarChartSeries();
      const lastItem = form.querySelectorAll("#barchart-series-list .series-item:last-child")[0];
      if (!lastItem) continue;

      lastItem.querySelector('[name="items[][label]"]').value = item.label || "";
      lastItem.querySelector('[name="items[][unit]"]').value = item.unit || "";
      lastItem.querySelector('[name="items[][color]"]').value = item.color || "";

      const deviceSelect = lastItem.querySelector('[name="items[][device_id]"]');
      const metricSelect = lastItem.querySelector('[name="items[][metric_key]"]');

      await loadDeviceOptions(deviceSelect, item.device_id);
      deviceSelect.value = item.device_id || "";

      await loadMetricOptions(item.device_id, metricSelect, item.metric_key);
      metricSelect.value = item.metric_key || "";
    }
    updatePreviewFromForm();
  }

  function initMapEditor(backgroundUrl = '', initialItems = []) {
    let mapItems = [];
    const svg = document.getElementById('map-edit-area');
    svg.innerHTML = ''; // clear
    mapItems = [...initialItems];

    // ตั้งพื้นหลังด้วย foreignObject
    //svg.style.backgroundImage = `url('${backgroundUrl}')`;
    svg.setAttribute("data-bg", backgroundUrl);
    svg.setAttribute("data-items", JSON.stringify(mapItems));

    appendMapBackground(svg, backgroundUrl);

    mapItems.forEach((item, idx) => {
      addSvgLabel(item, idx);
    });

    svg.ondblclick = (e) => {
      const rect = svg.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width;
      const y = (e.clientY - rect.top) / rect.height;

      const newItem = {
        label: "New Label",
        x,
        y,
        device_id: "",
        metric_key: "",
        show: "",
        unit: "",
        url: ""
      };

      const idx = mapItems.length;
      mapItems.push(newItem);
      svg.setAttribute("data-items", JSON.stringify(mapItems));
      console.log("initMapEditor newItem", newItem);
      addSvgLabel(newItem, idx);
    };
  }

  function applyItemChanges() {
    if (selectedItemIndex === null) return;

    const svg = document.getElementById('map-edit-area');
    const mapItems = JSON.parse(svg.getAttribute("data-items") || '[]');

    const item = mapItems[selectedItemIndex];
    item.label = document.getElementById("input-label").value;
    item.device_id = document.getElementById("input-device").value;
    item.metric_key = document.getElementById("input-metric").value;
    item.show = document.getElementById("input-show").value;
    item.url = document.getElementById("input-url").value;
    item.unit = document.getElementById("input-unit").value;

    mapItems[selectedItemIndex] = item;
    svg.setAttribute("data-items", JSON.stringify(mapItems));

    renderMapEditor(); // redraw
  }

  function deleteSelectedItem() {
    if (selectedItemIndex === null) return;
    const svg = document.getElementById('map-edit-area');
    const mapItems = JSON.parse(svg.getAttribute("data-items") || '[]');
    mapItems.splice(selectedItemIndex, 1);
    svg.setAttribute("data-items", JSON.stringify(mapItems));
    selectedItemIndex = null;
    document.getElementById("map-config-panel").style.display = "none";
    renderMapEditor(); // redraw
  }

  async function selectMapItem(index) {

    
    const svg = document.getElementById('map-edit-area');
    const mapItems = JSON.parse(svg.getAttribute("data-items") || '[]');
    const item = mapItems[index];
    selectedItemIndex = index;
    console.log("selectMapItem", item);


    document.getElementById("map-config-panel").style.display = "block";

    // เติมค่าในฟอร์ม
    document.getElementById("input-label").value = item.label;
    document.getElementById("input-device").value = item.device_id;
    document.getElementById("input-metric").value = item.metric_key;
    document.getElementById("input-show").value = item.show;
    document.getElementById("input-url").value = item.url;
    document.getElementById("input-unit").value = item.unit;

    // 1. โหลด Devices
    const deviceSelect = document.getElementById("input-device");
    const deviceRes = await fetch("/api/devices");
    const deviceData = await deviceRes.json();
    deviceSelect.innerHTML = `<option value="">-- Select Device --</option>`;
    deviceData.devices.forEach(d => {
      const opt = document.createElement("option");
      opt.value = d.id;
      opt.textContent = d.name;
      if (d.id == item.device_id) opt.selected = true;
      deviceSelect.appendChild(opt);
    });

    // 2. โหลด Metrics (เฉพาะ device_id นั้น)
    const metricSelect = document.getElementById("input-metric");
    if (item.device_id) {
      const metricRes = await fetch(`/api/metrics/${item.device_id}`);
      const metricData = await metricRes.json();
      metricSelect.innerHTML = `<option value="">-- Select Metric --</option>`;
      metricData.metrics.forEach(m => {
        const opt = document.createElement("option");
        opt.value = m.key;
        opt.textContent = m.label || m.key;
        if (m.key === item.metric_key) opt.selected = true;
        metricSelect.appendChild(opt);
      });
    }

    // ⚡ หากเลือก device ใหม่ → update metrics
    deviceSelect.onchange = async function() {
      const deviceId = this.value;
      const res = await fetch(`/api/metrics/${deviceId}`);
      const json = await res.json();
      metricSelect.innerHTML = `<option value="">-- Select Metric --</option>`;
      json.metrics.forEach(m => {
        const opt = document.createElement("option");
        opt.value = m.key;
        opt.textContent = m.label || m.key;
        metricSelect.appendChild(opt);
      });
    };
  }


  function renderMapEditor() {
    const svg = document.getElementById("map-edit-area");
    const mapItems = JSON.parse(svg.getAttribute("data-items") || '[]');
    svg.innerHTML = ''; // Clear


    const backgroundUrl = svg.getAttribute("data-bg") || '';
    appendMapBackground(svg, backgroundUrl);

    mapItems.forEach((item, idx) => {
      const g = document.createElementNS("http://www.w3.org/2000/svg", "g");
      g.classList.add("map-marker");
      g.setAttribute("data-index", idx);
      g.setAttribute("transform", `translate(${item.x}, ${item.y})`);
      g.style.cursor = "move";
      let rect, text;

      if (item.show === "point") {
        rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
        rect.setAttribute("width", 10);
        rect.setAttribute("height", 10);
        rect.setAttribute("rx", 6);
        rect.setAttribute("ry", 6);
        rect.setAttribute("fill", "#2222");
        rect.setAttribute("opacity", "1.0");

        text = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        text.setAttribute("r", 5);
        text.setAttribute("cx", 50);
        text.setAttribute("cy", 50);
        text.setAttribute("fill", "green");
        g.appendChild(text);
      } else {

        // Background rectangle
        rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
        rect.setAttribute("width", 100);
        rect.setAttribute("height", 24);
        rect.setAttribute("rx", 4);
        rect.setAttribute("fill", "#333");

        // Text
        text = document.createElementNS("http://www.w3.org/2000/svg", "text");
        text.setAttribute("x", 50);
        text.setAttribute("y", 16);
        text.setAttribute("text-anchor", "middle");
        text.setAttribute("fill", "#fff");
        text.setAttribute("font-size", "12");
        text.textContent = item.label || "Label";
        g.appendChild(rect);
        g.appendChild(text);
      }


      svg.appendChild(g);

      makeSvgDraggable(g);

      g.addEventListener("click", () => {
        selectMapItem(idx);
      });
    });
  }

  function addSvgLabel(item, idx) {
    const svgNS = "http://www.w3.org/2000/svg";
    const svg = document.getElementById("map-edit-area");
    const svgRect = svg.getBoundingClientRect();

    const group = document.createElementNS(svgNS, "g");
    group.setAttribute("class", "map-label");
    group.setAttribute("data-index", idx);
    group.setAttribute("data-device-id", item.device_id || "");
    group.setAttribute("data-metric-key", item.metric_key || "");
    group.setAttribute("data-show", item.show || "label");
    group.setAttribute("data-unit", item.unit || "");
    group.setAttribute("data-url", item.url || "");
    group.setAttribute("transform", `translate(${item.x}, ${item.y})`);
    group.style.cursor = "move";

    let rect, text;
    if (item.show === "point") {

      rect = document.createElementNS(svgNS, "rect");
      rect.setAttribute("width", 10);
      rect.setAttribute("height", 10);
      rect.setAttribute("rx", 6);
      rect.setAttribute("ry", 6);
      rect.setAttribute("fill", "#2222");
      rect.setAttribute("opacity", "1.0");

      text = document.createElementNS(svgNS, "circle");
      text.setAttribute("r", 5);
      text.setAttribute("cx", 50);
      text.setAttribute("cy", 50);
      text.setAttribute("fill", "green");
      group.appendChild(text);

    } else {
      rect = document.createElementNS(svgNS, "rect");
      rect.setAttribute("x", 0);
      rect.setAttribute("y", 0);
      rect.setAttribute("rx", 6);
      rect.setAttribute("ry", 6);
      rect.setAttribute("width", 120);
      rect.setAttribute("height", 30);
      rect.setAttribute("fill", "#222");
      rect.setAttribute("opacity", "0.85");

      text = document.createElementNS(svgNS, "text");
      text.setAttribute("x", 10);
      text.setAttribute("y", 20);
      text.setAttribute("fill", "#fff");
      text.setAttribute("font-size", "14");
      text.textContent = item.label || "--";

      group.appendChild(rect);
      group.appendChild(text);
    }

    group.addEventListener("click", () => {
      selectMapItem(idx);
    });


    svg.appendChild(group);

    makeSvgDraggable(group);
  }



  function makeDraggable(el) {
    const mapArea = document.getElementById('map-edit-area');

    el.onmousedown = function(e) {
      e.preventDefault();

      const areaRect = mapArea.getBoundingClientRect();
      const elRect = el.getBoundingClientRect();

      const shiftX = e.clientX - elRect.left;
      const shiftY = e.clientY - elRect.top;

      function moveAt(clientX, clientY) {
        const x = clientX - areaRect.left - shiftX;
        const y = clientY - areaRect.top - shiftY;

        el.style.left = `${x}px`;
        el.style.top = `${y}px`;
      }

      function onMouseMove(e) {
        moveAt(e.clientX, e.clientY);
      }

      document.addEventListener('mousemove', onMouseMove);

      el.onmouseup = function() {
        document.removeEventListener('mousemove', onMouseMove);
        el.onmouseup = null;

        const idx = parseInt(el.dataset.index);
        const areaRect = mapArea.getBoundingClientRect();
        mapItems[idx].x = (parseFloat(el.style.left) / mapArea.clientWidth);
        mapItems[idx].y = (parseFloat(el.style.top) / mapArea.clientHeight);
      };
    };

    el.ondragstart = () => false;
  }


  function makeSvgDraggable(el) {
    const svg = document.getElementById("map-edit-area");
    let offsetX = 0,
      offsetY = 0,
      dragging = false;

    el.addEventListener("mousedown", function(e) {
      const CTM = svg.getScreenCTM();
      const point = svg.createSVGPoint();
      point.x = e.clientX;
      point.y = e.clientY;
      const transformed = point.matrixTransform(CTM.inverse());

      const currentTransform = el.getAttribute("transform")?.match(/translate\(([^,]+),\s*([^)]+)\)/);
      const currentX = parseFloat(currentTransform?.[1] || 0);
      const currentY = parseFloat(currentTransform?.[2] || 0);

      offsetX = transformed.x - currentX;
      offsetY = transformed.y - currentY;
      dragging = true;

      function onMouseMove(ev) {
        if (!dragging) return;

        let mapItems = JSON.parse(svg.getAttribute("data-items") || '[]');

        const pt = svg.createSVGPoint();
        pt.x = ev.clientX;
        pt.y = ev.clientY;
        const local = pt.matrixTransform(CTM.inverse());

        const x = local.x - offsetX;
        const y = local.y - offsetY;

        el.setAttribute("transform", `translate(${x}, ${y})`);

        const idx = parseInt(el.dataset.index);
        const w = svg.width.baseVal.value;
        const h = svg.height.baseVal.value;
        mapItems[idx].x = x;
        mapItems[idx].y = y;
        svg.setAttribute("data-items", JSON.stringify(mapItems));
      }

      function onMouseUp() {
        dragging = false;
        document.removeEventListener("mousemove", onMouseMove);
        document.removeEventListener("mouseup", onMouseUp);
      }

      document.addEventListener("mousemove", onMouseMove);
      document.addEventListener("mouseup", onMouseUp);

    });
  }


  function lineChartConfigPanel(type) {
    const panel = document.createElement('div');
    panel.innerHTML = `
    <div id="linechart-settings" class="widget-type-section mb-4" data-widget-type="${type}">
           <hr class="border-light">

      <label class="form-label text-white">Series</label>
      <div id="linechart-series-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" onclick="addLinechartSeries()">+ Add Series</button>
    </div>
    `;
    document.getElementById('widget-config-form').appendChild(panel);
  }

  function barChartConfigPanel() {
    const panel = document.createElement('div');
    panel.innerHTML = `
    <div id="barchart-settings" class="widget-type-section mb-4" data-widget-type="bar_chart">
           <hr class="border-light">

      <label class="form-label text-white">Series</label>
      <div id="barchart-series-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" onclick="addBarChartSeries()">+ Add Series</button>
    </div>
    `;
    document.getElementById('widget-config-form').appendChild(panel);
  }

  function updatePreviewFromForm() {
    const uid = document.getElementById("configTargetUid")?.value;
    const el = document.querySelector(`.grid-stack-item[data-uid="${uid}"]`);
    if (!el) return;

    const type = el.dataset.widget_type;
    let config = {};

    if (type === 'zone_overview' && typeof collectZoneOverviewConfig === 'function') {
      config = collectZoneOverviewConfig();
    } else if (type === 'bar_chart' && typeof collectBarChartConfig === 'function') {
      config = collectBarChartConfig();
    } else if (type === 'table' && typeof collectTableConfig === 'function') {
      config = collectTableConfig();
    } else if (type === 'status_grid' && typeof collectStatusGridConfig === 'function') {
      config = collectStatusGridConfig();
    } else if (type === 'navigator' && typeof collectNavigatorConfig === 'function') {
      config = collectNavigatorConfig();
    } else if (type === 'heatmap' && typeof collectHeatmapConfig === 'function') {
      config = collectHeatmapConfig();
    } else {
      const inputs = document.querySelectorAll("#widget-config-form input, #widget-config-form select, #widget-config-form textarea");
      inputs.forEach(i => {
        const entry = readWidgetConfigInput(i);
        if (!entry) return;
        config[entry.key] = entry.value;
      });
      if (type === 'value_box' && typeof collectValueMaps === 'function') {
        config.value_maps = collectValueMaps();
      }
    }

    // map_view editor is stateful (background image + markers on #map-edit-area).
    // Rebuilding the shell without initMapEditor drops the image (e.g. toggling show_border).
    if (type === 'map_view' && document.getElementById('map-edit-area')) {
      return;
    }

    const previewBox = document.getElementById("widget-preview-box");
    previewBox.innerHTML = ""; // clear

    widgetHandlers[type]?.renderPreview?.(previewBox, config);
  }



  function isWidgetConfigInput(input) {
    if (!input || !input.id) return false;
    if (input.id === 'configTargetUid') return false;
    if (input.id.endsWith('-picker')) return false;
    if (input.type === 'file') return false;
    return input.id.startsWith('cfg-') || !!input.dataset.field;
  }

  function readWidgetConfigInput(input) {
    if (!isWidgetConfigInput(input)) return null;
    const key = input.dataset.field || input.id.replace(/^cfg-/, '');
    if (!key || key.includes('-picker')) return null;
    return { key, value: input.value };
  }

  function applyWidgetConfig(exit = false, save = false) {
    try {
    const uid = document.getElementById("configTargetUid")?.value;
    let el = document.querySelector(`.grid-stack-item[data-uid="${uid}"]`);
    if (!el) return;

    const type = el.dataset.widget_type;

    const inputs = document.querySelectorAll("#widget-config-form input, #widget-config-form select, #widget-config-form textarea");
    inputs.forEach(i => {
      const entry = readWidgetConfigInput(i);
      if (!entry) return;
      el.dataset[entry.key] = entry.value;

      // update label in preview
      if (entry.key === "label") {
        const labelEl = el.querySelector(".text-muted");
        if (labelEl) labelEl.textContent = entry.value;
      }
    });

    if (type === 'map_view') {

      const bg = document.getElementById("cfg-background")?.value || '';
      const svg = document.getElementById('map-edit-area');
      const mapItems = svg ? JSON.parse(svg.getAttribute("data-items") || '[]') : JSON.parse(el.dataset.items || '[]');
      const showBorder = document.getElementById("cfg-show_border")?.value ?? '1';
      el.dataset.background = bg;
      el.dataset.show_border = showBorder;
      el.dataset.config = JSON.stringify({
        background: bg,
        items: mapItems,
        show_border: showBorder
      });
      el.dataset.items = JSON.stringify(mapItems);
      console.log("mapItems", mapItems);
    }

    if (type === 'line_chart' || type === 'line_chart_dynamic') {

      const config = collectLineChartConfig();
      el.dataset.config = JSON.stringify(config);
      el.dataset.items = JSON.stringify(config.items);
      //alert(`applyWidgetConfig ${JSON.stringify(config)}`);
      console.log("line chart item", el.dataset.config);

    }

    if (type === 'bar_chart') {
      const config = collectBarChartConfig();
      el.dataset.config = JSON.stringify(config);
      el.dataset.items = JSON.stringify(config.items);
      console.log("bar chart item", el.dataset.config);
    }

    if (type === 'table') {
      const config = collectTableConfig();
      el.dataset.config = JSON.stringify(config);
      el.dataset.items = JSON.stringify(config.items);
    }

    if (type === 'status_grid') {
      const config = collectStatusGridConfig();
      el.dataset.config = JSON.stringify(config);
      el.dataset.items = JSON.stringify(config.items);
      el.dataset.columns = config.columns || '3';
      el.dataset.link_url = config.link_url || '';
      el.dataset.show_border = config.show_border ?? '1';
      el.dataset.bg_color = config.bg_color || '';
      el.dataset.bg_opacity = config.bg_opacity || '100';
    }

    if (type === 'navigator') {
      const config = collectNavigatorConfig();
      el.dataset.config = JSON.stringify(config);
      el.dataset.items = JSON.stringify(config.items);
      el.dataset.columns = config.columns || '2';
      el.dataset.show_border = config.show_border ?? '1';
      el.dataset.bg_color = config.bg_color || '';
      el.dataset.bg_opacity = config.bg_opacity || '100';
      const labelEl = el.querySelector('.text-muted');
      if (labelEl) labelEl.textContent = config.label || '';
    }

    if (type === 'heatmap') {
      const config = collectHeatmapConfig();
      el.dataset.config = JSON.stringify(config);
      el.dataset.items = JSON.stringify(config.items);
      el.dataset.columns = config.columns || '4';
      el.dataset.warn_threshold = config.warn_threshold ?? '25';
      el.dataset.alarm_threshold = config.alarm_threshold ?? '30';
      el.dataset.unit = config.unit || '°C';
    }

    if (type === 'zone_overview') {
      if (typeof collectZoneOverviewConfig !== 'function') {
        throw new Error('Zone overview config helper is not loaded');
      }
      const config = collectZoneOverviewConfig();
      el.dataset.config = JSON.stringify(config);
      el.dataset.items = JSON.stringify(config.items);
      el.dataset.alarm_devices = JSON.stringify(config.alarm_devices || []);
      el.dataset.zone_name = config.zone_name;
      el.dataset.zone_subtitle = config.zone_subtitle;
      el.dataset.link_url = config.link_url || '';
      el.dataset.icon = config.icon;
      el.dataset.show_primary = config.show_primary;
      el.dataset.primary_label = config.primary_label;
      el.dataset.primary_unit = config.primary_unit;
      el.dataset.primary_device_id = config.primary_device_id;
      el.dataset.primary_metric_key = config.primary_metric_key;
      el.dataset.value_maps = JSON.stringify(config.value_maps || []);
      const labelEl = el.querySelector('.text-muted');
      if (labelEl) labelEl.textContent = config.zone_name || config.label || '';
    }

    if (type === 'value_box') {
      el.dataset.value_maps = JSON.stringify(collectValueMaps());
    }

    if (type === 'gauge' || type === 'level_bar') {
      el.dataset.color_ranges = JSON.stringify(collectGaugeColorRanges());
    }

    if (type === 'svg_widget') {
      const svgFile = document.getElementById('cfg-svg_file')?.value || '';
      const showBorder = document.getElementById('cfg-show_border')?.value ?? '1';
      const bindings = collectSvgBindings();
      el.dataset.svg_file = svgFile;
      el.dataset.show_border = showBorder;
      el.dataset.bindings = JSON.stringify(bindings);
      el.dataset.config = JSON.stringify({
        label: document.getElementById('cfg-label')?.value || '',
        svg_file: svgFile,
        show_border: showBorder,
        bindings
      });
    }

    bootstrap.Modal.getInstance(document.getElementById("configureWidgetModal"))?.hide();
    if (exit) {
      closeWidgetConfigPanel(save);
    }
    } catch (err) {
      console.error('applyWidgetConfig failed:', err);
      alert('Failed to save widget config: ' + (err.message || err));
    }
  }

  function collectValueMaps() {
    const rows = [];
    document.querySelectorAll('#value-maps-list .value-map-item').forEach((item) => {
      const value = item.querySelector('[name="value_maps[][value]"]')?.value ?? '';
      const label = item.querySelector('[name="value_maps[][label]"]')?.value ?? '';
      const icon_color = item.querySelector('[name="value_maps[][icon_color]"]')?.value ?? '';
      const icon_effect = item.querySelector('[name="value_maps[][icon_effect]"]')?.value ?? '';
      if (value === '' && label === '') return;
      const row = { value, label };
      if (icon_color) row.icon_color = icon_color;
      if (icon_effect && icon_effect !== 'none') row.icon_effect = icon_effect;
      rows.push(row);
    });
    return rows;
  }

  function renderValueMapsPanel(maps = [], mountEl = null) {
    const panel = document.createElement('div');
    panel.className = mountEl ? 'mt-2' : 'col-12 mt-2';
    panel.innerHTML = `
      <hr class="border-secondary">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label small mb-0">Value Maps</label>
        <button type="button" class="btn btn-outline-light btn-sm" id="btn-add-value-map">+ Add Map</button>
      </div>
      <div class="text-secondary small mb-2">${mountEl
        ? 'Map raw values to display text on the Primary KPI. Optionally set color and effect per value.'
        : 'Map raw values to display text. Optionally set icon color and effect per value (when Show Icon is enabled).'}</div>
      <div id="value-maps-list"></div>
    `;
    (mountEl || document.getElementById('widget-config-form')).appendChild(panel);

    const list = panel.querySelector('#value-maps-list');
    (maps || []).forEach((m) => addValueMapRow(m.value ?? '', m.label ?? '', list, m.icon_color ?? '', m.icon_effect ?? ''));

    panel.querySelector('#btn-add-value-map')?.addEventListener('click', () => {
      addValueMapRow('', '', list);
    });
  }

  function addValueMapRow(value = '', label = '', listEl = null, iconColor = '', iconEffect = '') {
    const list = listEl || document.getElementById('value-maps-list');
    if (!list) return;

    const row = document.createElement('div');
    row.className = 'value-map-item border border-secondary rounded p-2 mb-2';
    row.innerHTML = `
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
          <label class="form-label small mb-1">Value</label>
          <input type="text" name="value_maps[][value]" class="form-control form-control-sm" placeholder="e.g. 0" value="${String(value).replace(/"/g, '&quot;')}">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small mb-1">Display As</label>
          <input type="text" name="value_maps[][label]" class="form-control form-control-sm" placeholder="e.g. Running" value="${String(label).replace(/"/g, '&quot;')}">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small mb-1">Icon Color</label>
          <input type="color" name="value_maps[][icon_color]" class="form-control form-control-color form-control-sm" value="${iconColor || '#ffffff'}">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small mb-1">Icon Effect</label>
          <select name="value_maps[][icon_effect]" class="form-select form-select-sm">
            <option value="none" ${!iconEffect || iconEffect === 'none' ? 'selected' : ''}>None</option>
            <option value="spin" ${iconEffect === 'spin' ? 'selected' : ''}>Spin</option>
            <option value="pulse" ${iconEffect === 'pulse' ? 'selected' : ''}>Pulse</option>
            <option value="blink" ${iconEffect === 'blink' ? 'selected' : ''}>Blink</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-sm btn-outline-danger" title="Remove">&times;</button>
        </div>
      </div>
    `;
    row.querySelector('button')?.addEventListener('click', () => {
      row.remove();
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
    row.querySelectorAll('input, select').forEach((el) => {
      el.addEventListener('input', () => {
        if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
      });
      el.addEventListener('change', () => {
        if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
      });
    });
    list.appendChild(row);
  }

  function collectLineChartConfig() {
    const form = document.getElementById("widget-config-form");

    const config = {
      label: form.querySelector('[id="cfg-label"]')?.value || "",
      yMin: form.querySelector('[id="cfg-label"]')?.value || "",
      yMax: parseFloat(form.querySelector('[id="cfg-yMax"]')?.value) || undefined,
      items: []
    };

    form.querySelectorAll(".series-item").forEach(item => {
      config.items.push({
        label: item.querySelector('[name="items[][label]"]')?.value || "",
        unit: item.querySelector('[name="items[][unit]"]')?.value || "",
        color: item.querySelector('[name="items[][color]"]')?.value || "",
        device_id: item.querySelector('[name="items[][device_id]"]')?.value || "",
        metric_key: item.querySelector('[name="items[][metric_key]"]')?.value || ""
      });
    });

    return config;
  }

  document.addEventListener('input', function(e) {
    if (!e.target.id || !e.target.id.startsWith('cfg-')) return;
    if (e.target.id.endsWith('-picker')) {
      const text = document.getElementById(e.target.id.replace('-picker', ''));
      if (text) text.value = e.target.value;
      return;
    }
    if (e.target.id === 'cfg-bg_color' && /^#[0-9a-fA-F]{6}$/i.test(e.target.value)) {
      const picker = document.getElementById('cfg-bg_color-picker');
      if (picker) picker.value = e.target.value;
    }
  });

  document.addEventListener("change", function(e) {
    if (e.target.id === "cfg-device_id") {
      const deviceId = e.target.value;
      fetch(`/api/metrics/${deviceId}`)
        .then(res => res.json())
        .then(data => {
          const select = document.getElementById("cfg-metric_key");
          if (!select) return;
          select.innerHTML = ""; // reset
          select.append(new Option("-- Select Metric --", ""));
          (data.metrics || []).forEach(m => {
            const opt = new Option(m.label || m.key, m.key);
            opt.dataset.metric = JSON.stringify(m); // สำหรับ auto-fill
            select.append(opt);
          });
        });
    }
  });

  document.addEventListener("change", function(e) {
    if (e.target.id === "cfg-device_id") {
      const deviceId = e.target.value;
      fetch(`/api/metrics/${deviceId}`)
        .then(res => res.json())
        .then(data => {
          const select = document.getElementById("cfg-temp_key");
          if (!select) return;
          select.innerHTML = ""; // reset
          select.append(new Option("-- Select Metric --", ""));
          (data.metrics || []).forEach(m => {
            const opt = new Option(m.label || m.key, m.key);
            opt.dataset.metric = JSON.stringify(m); // สำหรับ auto-fill
            select.append(opt);
          });
        });
    }
  });

  document.addEventListener("change", function(e) {
    if (e.target.id === "cfg-device_id") {
      const deviceId = e.target.value;
      fetch(`/api/metrics/${deviceId}`)
        .then(res => res.json())
        .then(data => {
          const select = document.getElementById("cfg-hum_key");
          if (!select) return;
          select.innerHTML = ""; // reset
          select.append(new Option("-- Select Metric --", ""));
          (data.metrics || []).forEach(m => {
            const opt = new Option(m.label || m.key, m.key);
            opt.dataset.metric = JSON.stringify(m); // สำหรับ auto-fill
            select.append(opt);
          });
        });
    }
  });

  document.addEventListener("change", function(e) {
    if (e.target.id === "cfg-device_id1") {
      const deviceId = e.target.value;
      fetch(`/api/metrics/${deviceId}`)
        .then(res => res.json())
        .then(data => {
          const select = document.getElementById("cfg-metric_key1");
          if (!select) return;
          select.innerHTML = ""; // reset
          select.append(new Option("-- Select Metric --", ""));
          (data.metrics || []).forEach(m => {
            const opt = new Option(m.label || m.key, m.key);
            opt.dataset.metric = JSON.stringify(m); // สำหรับ auto-fill
            select.append(opt);
          });
        });
    }
  });

  document.addEventListener("change", function(e) {
    if (e.target.id === "cfg-device_id2") {
      const deviceId = e.target.value;
      fetch(`/api/metrics/${deviceId}`)
        .then(res => res.json())
        .then(data => {
          const select = document.getElementById("cfg-metric_key2");
          if (!select) return;
          select.innerHTML = ""; // reset
          select.append(new Option("-- Select Metric --", ""));
          (data.metrics || []).forEach(m => {
            const opt = new Option(m.label || m.key, m.key);
            opt.dataset.metric = JSON.stringify(m); // สำหรับ auto-fill
            select.append(opt);
          });
        });
    }
  });

  document.addEventListener("change", function(e) {
    if (e.target.id === "cfg-device_id3") {
      const deviceId = e.target.value;
      fetch(`/api/metrics/${deviceId}`)
        .then(res => res.json())
        .then(data => {
          const select = document.getElementById("cfg-metric_key3");
          if (!select) return;
          select.innerHTML = ""; // reset
          select.append(new Option("-- Select Metric --", ""));
          (data.metrics || []).forEach(m => {
            const opt = new Option(m.label || m.key, m.key);
            opt.dataset.metric = JSON.stringify(m); // สำหรับ auto-fill
            select.append(opt);
          });
        });
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeWidgetConfigPanel();
    }
  });
</script>
<script type="text/template" id="svg-binding-template">
  <div class="svg-binding-item series-item border rounded p-2 mb-2">
    <div class="row mb-2 g-2">
      <div class="col-md-3">
        <label class="form-label small">Element ID</label>
        <input type="text" name="bindings[][id]" class="form-control form-control-sm svg-element-id-input" list="svg-element-ids" placeholder="e.g. src-a-bus">
        <select class="form-select form-select-sm mt-1 svg-element-id-select">
          <option value="">Select from SVG...</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Mode</label>
        <select name="bindings[][mode]" class="form-select form-select-sm">
          <option value="fill">Fill</option>
          <option value="stroke">Stroke</option>
          <option value="show">Show / Hide</option>
          <option value="text">Text</option>
          <option value="blink">Blink</option>
          <option value="spin">Spin</option>
          <option value="opacity">Opacity</option>
          <option value="class">Class</option>
        </select>
      </div>
      <div class="col-md-2" data-mode-field="fill,stroke,show,blink,spin,opacity,class">
        <label class="form-label small">Operator</label>
        <select name="bindings[][op]" class="form-select form-select-sm">
          <option value="eq">=</option>
          <option value="neq">≠</option>
          <option value="gt">&gt;</option>
          <option value="gte">≥</option>
          <option value="lt">&lt;</option>
          <option value="lte">≤</option>
          <option value="between">between</option>
          <option value="in">in (csv)</option>
          <option value="always">always</option>
        </select>
      </div>
      <div class="col-md-2" data-field="match_value" data-mode-field="fill,stroke,show,blink,opacity,class">
        <label class="form-label small">Match Value</label>
        <input type="text" name="bindings[][match_value]" class="form-control form-control-sm" value="1" placeholder="1 or a,b">
      </div>
      <div class="col-md-2" data-mode-field="fill,stroke,show,blink,opacity,class" data-op-field="between">
        <label class="form-label small">From</label>
        <input type="text" name="bindings[][match_from]" class="form-control form-control-sm" placeholder="min">
      </div>
      <div class="col-md-2" data-mode-field="fill,stroke,show,blink,opacity,class" data-op-field="between">
        <label class="form-label small">To</label>
        <input type="text" name="bindings[][match_to]" class="form-control form-control-sm" placeholder="max">
      </div>
      <div class="col-auto d-flex align-items-end gap-1">
        <button type="button" class="btn btn-sm btn-outline-light" title="Duplicate (keep device)" onclick="duplicateSvgBinding(this)">Duplicate</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.svg-binding-item').remove(); updatePreviewFromForm();">×</button>
      </div>
    </div>
    <div class="row mb-2 g-2" data-mode-field="fill,stroke,blink">
      <div class="col-auto">
        <label class="form-label small">Active Color</label>
        <input type="color" name="bindings[][active_color]" class="form-control form-control-sm form-control-color" value="#28a745">
      </div>
      <div class="col-auto">
        <label class="form-label small">Inactive Color</label>
        <input type="color" name="bindings[][inactive_color]" class="form-control form-control-sm form-control-color" value="#6c757d">
      </div>
    </div>
    <div class="row mb-2 g-2" data-mode-field="text">
      <div class="col-md-4">
        <label class="form-label small">Template</label>
        <input type="text" name="bindings[][text_template]" class="form-control form-control-sm" value="{value} {unit}">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Precision</label>
        <input type="number" name="bindings[][precision]" class="form-control form-control-sm" value="2">
      </div>
    </div>
    <div class="row mb-2 g-2" data-mode-field="opacity">
      <div class="col-md-2">
        <label class="form-label small">Opacity On</label>
        <input type="number" step="0.05" min="0" max="1" name="bindings[][opacity_on]" class="form-control form-control-sm" value="1">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Opacity Off</label>
        <input type="number" step="0.05" min="0" max="1" name="bindings[][opacity_off]" class="form-control form-control-sm" value="0.35">
      </div>
    </div>
    <div class="row mb-2 g-2" data-mode-field="class">
      <div class="col-md-4">
        <label class="form-label small">CSS Class</label>
        <input type="text" name="bindings[][class_name]" class="form-control form-control-sm" value="mimic-active" placeholder="mimic-active">
      </div>
    </div>
    <div class="row mb-1 g-2">
      <div class="col">
        <label class="form-label small">Device</label>
        <select name="bindings[][device_id]" class="form-select form-select-sm device-select">
          <option value="">Select Device</option>
        </select>
      </div>
      <div class="col">
        <label class="form-label small">Metric</label>
        <select name="bindings[][metric_key]" class="form-select form-select-sm metric-select">
          <option value="">Select Metric</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Unit</label>
        <input type="text" name="bindings[][unit]" class="form-control form-control-sm">
      </div>
    </div>
  </div>
</script>
<script type="text/template" id="zone-alarm-device-template">
  <div class="alarm-device-item border rounded p-2 mb-2">
    <div class="row g-2 align-items-end">
      <div class="col">
        <label class="form-label small">Device</label>
        <select name="alarm_devices[][device_id]" class="form-select form-select-sm device-select">
          <option value="">Select Device</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.alarm-device-item').remove(); updatePreviewFromForm();">×</button>
      </div>
    </div>
  </div>
</script>
<script type="text/template" id="zone-overview-metric-template">
  <div class="series-item border rounded p-2 mb-2">
    <div class="row mb-2 g-2">
      <div class="col-md-4">
        <label class="form-label small">Label</label>
        <input type="text" name="items[][label]" class="form-control form-control-sm" placeholder="e.g. Temperature">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Unit</label>
        <input type="text" name="items[][unit]" class="form-control form-control-sm" placeholder="e.g. °C">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Icon</label>
        <div class="zone-overview-icon-mount"></div>
      </div>
      <div class="col-md-2 d-flex align-items-end gap-1">
        <button type="button" class="btn btn-sm btn-outline-light" title="Duplicate" onclick="duplicateZoneOverviewMetric(this)">Dup</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.series-item').remove(); updatePreviewFromForm();">×</button>
      </div>
    </div>
    <div class="row mb-1 g-2">
      <div class="col-md-6">
        <label class="form-label small">Device</label>
        <select name="items[][device_id]" class="form-select form-select-sm device-select">
          <option value="">Select Device</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small">Metric</label>
        <select name="items[][metric_key]" class="form-select form-select-sm metric-select">
          <option value="">Select Metric</option>
        </select>
      </div>
    </div>
  </div>
</script>
<script type="text/template" id="heatmap-item-template">
  <div class="series-item border rounded p-2 mb-2">
    <div class="row mb-2 g-2">
      <div class="col-md-5">
        <label class="form-label small">Label</label>
        <input type="text" name="items[][label]" class="form-control form-control-sm">
      </div>
      <div class="col-md-7 d-flex align-items-end">
        <button type="button" class="btn btn-sm btn-danger ms-auto" onclick="this.closest('.series-item').remove(); updatePreviewFromForm();">×</button>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label small">Device</label>
        <select name="items[][device_id]" class="form-select form-select-sm device-select">
          <option value="">Select Device</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small">Metric</label>
        <select name="items[][metric_key]" class="form-select form-select-sm metric-select">
          <option value="">Select Metric</option>
        </select>
      </div>
    </div>
  </div>
</script>
<script type="text/template" id="status-grid-item-template">
  <div class="series-item border rounded p-2 mb-2">
    <div class="row mb-2 g-2">
      <div class="col-md-4">
        <label class="form-label small">Label</label>
        <input type="text" name="items[][label]" class="form-control form-control-sm">
      </div>
      <div class="col-md-4">
        <label class="form-label small">Icon</label>
        <div class="status-grid-icon-mount"></div>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <button type="button" class="btn btn-sm btn-danger ms-auto" onclick="this.closest('.series-item').remove(); updatePreviewFromForm();">×</button>
      </div>
    </div>
    <div class="row mb-2 g-2">
      <div class="col-md-6">
        <label class="form-label small">Device</label>
        <select name="items[][device_id]" class="form-select form-select-sm device-select">
          <option value="">Select Device</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small">Metric</label>
        <select name="items[][metric_key]" class="form-select form-select-sm metric-select">
          <option value="">Select Metric</option>
        </select>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label small">On Label</label>
        <input type="text" name="items[][state_on]" class="form-control form-control-sm" value="On">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Off Label</label>
        <input type="text" name="items[][state_off]" class="form-control form-control-sm" value="Off">
      </div>
      <div class="col-md-3">
        <label class="form-label small">On Color</label>
        <input type="color" name="items[][color_on]" class="form-control form-control-sm form-control-color" value="#22c55e">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Off Color</label>
        <input type="color" name="items[][color_off]" class="form-control form-control-sm form-control-color" value="#94a3b8">
      </div>
    </div>
    <div class="mt-2 status-item-value-maps-wrap">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <label class="form-label small mb-0">Value Maps</label>
        <button type="button" class="btn btn-outline-light btn-sm btn-add-status-value-map">+ Add Map</button>
      </div>
      <div class="text-secondary small mb-1">Map raw values to display text (like Value Box). When set, these override On/Off labels.</div>
      <div class="status-item-value-maps"></div>
    </div>
  </div>
</script>
<script type="text/template" id="table-metric-template">
  <div class="series-item border rounded p-2 mb-2">
    <div class="row mb-2">
      <div class="col">
        <label class="form-label small">Label</label>
        <input type="text" name="items[][label]" class="form-control form-control-sm">
      </div>
      <div class="col">
        <label class="form-label small">Unit</label>
        <input type="text" name="items[][unit]" class="form-control form-control-sm">
      </div>
      <div class="col-auto d-flex align-items-end gap-1">
        <button type="button" class="btn btn-sm btn-outline-light" title="Duplicate (keep device)" onclick="duplicateTableMetric(this)">Duplicate</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.series-item').remove(); updatePreviewFromForm();">×</button>
      </div>
    </div>
    <div class="row mb-1">
      <div class="col">
        <label class="form-label small">Device</label>
        <select name="items[][device_id]" class="form-select form-select-sm device-select">
          <option value="">Select Device</option>
        </select>
      </div>
      <div class="col">
        <label class="form-label small">Metric</label>
        <select name="items[][metric_key]" class="form-select form-select-sm metric-select">
          <option value="">Select Metric</option>
        </select>
      </div>
    </div>
  </div>
</script>
<script type="text/template" id="linechart-series-template">
  <div class="series-item border rounded p-2 mb-2">
        <div class="row mb-2">
          <div class="col">
            <label class="form-label small">Label</label>
            <input type="text" name="items[][label]" class="form-control form-control-sm">
          </div>
          <div class="col">
            <label class="form-label small">Unit</label>
            <input type="text" name="items[][unit]" class="form-control form-control-sm">
          </div>
          <div class="col">
            <label class="form-label small">Color</label>
            <input type="color" name="items[][color]" class="form-control form-control-sm">
          </div>
          <div class="col-auto d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.series-item').remove()">×</button>
          </div>
        </div>
        <div class="row mb-1">
          <div class="col">
            <label class="form-label small">Device ID</label>
            <select name="items[][device_id]" class="form-select form-select-sm device-select">
              <option value="">Select Device</option>
            </select>
          </div>
          <div class="col">
            <label class="form-label small">Metric Key</label>
            <select name="items[][metric_key]" class="form-select form-select-sm metric-select">
              <option value="">Select Metric</option>
            </select>
          </div>
        </div>
      </div>
</script>
<script type="text/template" id="barchart-series-template">
  <div class="series-item border rounded p-2 mb-2">
        <div class="row mb-2">
          <div class="col">
            <label class="form-label small">Label</label>
            <input type="text" name="items[][label]" class="form-control form-control-sm">
          </div>
          <div class="col">
            <label class="form-label small">Unit</label>
            <input type="text" name="items[][unit]" class="form-control form-control-sm">
          </div>
          <div class="col">
            <label class="form-label small">Color</label>
            <input type="color" name="items[][color]" class="form-control form-control-sm">
          </div>
          <div class="col-auto d-flex align-items-end gap-1">
            <button type="button" class="btn btn-sm btn-outline-light" onclick="duplicateBarChartSeries(this)">Dup</button>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.series-item').remove(); updatePreviewFromForm();">×</button>
          </div>
        </div>
        <div class="row mb-1">
          <div class="col">
            <label class="form-label small">Device ID</label>
            <select name="items[][device_id]" class="form-select form-select-sm device-select">
              <option value="">Select Device</option>
            </select>
          </div>
          <div class="col">
            <label class="form-label small">Metric Key</label>
            <select name="items[][metric_key]" class="form-select form-select-sm metric-select">
              <option value="">Select Metric</option>
            </select>
          </div>
        </div>
      </div>
</script>
<style type="text/css">
  .map-label-draggable {
    cursor: move;
    user-select: none;
  }

  .dashboard-container {

    height: calc(100vh - 200px);
    overflow: hidden;
    position: relative;
    border: 1px solid #eee;
  }

  .dashboard-layout-scroll {
    width: 100%;
    height: 100%;
    overflow: auto;
    position: relative;
  }

  .dashboard-layout-canvas {
    width: 1200px;
    height: 400px;
    position: relative;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    transform-origin: top left;
    transition: transform 0.2s ease;
  }

  .dashboard-tools {
    position: fixed;
    left: 0;
    bottom: 0;
    width: 100%;
    background-color: #888;
    color: white;
    align-items: flex-end;
    text-align: right;
  }

  .grid-stack .grid-stack-item-content {

    border: 1px solid #ddd;
    padding: 8px;
    font-size: 0.85rem;
  }

  .widget-label:hover {
    transform: scale(1.05);
  }

  .widget-alarm {
    background-color: #dc3545;
    color: white;
    animation: blink 1s infinite;
  }

  @keyframes blink {
    50% {
      opacity: 0.3;
    }
  }

  #widget-preview-box {
    background-color: rgba(245, 245, 245, 0.9);
    border: 1px dashed #ccc;
    min-height: 100px;
  }

  .widget-icon-select {
    position: relative;
  }

  .widget-icon-select-toggle {
    width: 100%;
    display: flex !important;
    align-items: center;
    gap: 0.5rem;
    text-align: left;
    background-color: var(--bs-body-bg, #fff);
    color: var(--bs-body-color, #212529);
  }

  .widget-icon-select-toggle i {
    font-size: 1.15rem;
    line-height: 1;
    flex-shrink: 0;
  }

  .widget-icon-select-toggle span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .widget-icon-select-menu {
    display: none;
    position: absolute;
    z-index: 1300;
    top: calc(100% + 4px);
    right: 0;
    left: auto;
    width: min(320px, calc(100vw - 48px));
    min-width: 280px;
    max-height: 280px;
    overflow: auto;
    padding: 0.5rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.375rem;
    background: var(--bs-body-bg, #fff);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  }

  .widget-icon-select.open .widget-icon-select-menu {
    display: block;
  }

  .widget-icon-select.drop-up .widget-icon-select-menu {
    top: auto;
    bottom: calc(100% + 4px);
  }

  .widget-icon-select-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
  }

  .widget-icon-select-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 0.3rem;
    min-height: 4.25rem;
    padding: 0.45rem 0.3rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.375rem;
    background: transparent;
    color: inherit;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;
  }

  .widget-icon-select-option i {
    font-size: 1.4rem;
    line-height: 1;
  }

  .widget-icon-select-option span {
    font-size: 0.68rem;
    line-height: 1.2;
    text-align: center;
    word-break: break-word;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .widget-icon-select-option:hover,
  .widget-icon-select-option.active {
    background: rgba(13, 110, 253, 0.12);
    border-color: rgba(13, 110, 253, 0.45);
  }

  .dark-mode .widget-icon-select-menu,
  .config-sidebar .widget-icon-select-menu {
    background: #2b3035;
    border-color: #495057;
  }

  .dark-mode .widget-icon-select-toggle,
  .config-sidebar .widget-icon-select-toggle {
    background-color: #212529;
    color: #f8f9fa;
    border-color: #495057;
  }

  .dark-mode #widget-preview-box {
    background-color: rgba(60, 60, 60, 0.9);
  }

  .map-marker {
    cursor: pointer;
    user-select: none;
  }

  .config-sidebar {
    position: fixed;
    top: 0;
    right: 0;
    width: 770px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    z-index: 1050;
    box-shadow: -4px 0 10px rgba(0, 0, 0, 0.3);
    background-color: #212529;
    transition: all 0.3s ease;
    overflow: hidden;
  }

  .config-sidebar .config-header,
  .config-sidebar .config-footer {
    flex: 0 0 auto;
  }

  .config-sidebar .config-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
  }

  /* 👉 เต็มจอสำหรับมือถือและแท็บเล็ต */
  @media (max-width: 768px) {
    .config-sidebar {
      width: 100%;
    }
  }
</style>