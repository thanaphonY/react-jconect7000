
<style>
  .grid-stack-item-content.preview {
    background: #f9f9f9;
    border: 1px dashed #ccc;
    padding: 10px;
    overflow: hidden;
  }

  .value-box {
    font-size: 1.5rem;
    font-weight: bold;
    text-align: center;
    padding: 20px;
  }

  .widget-config-modal .form-group {
    margin-bottom: 1rem;
  }

  .color-picker {
    width: 50px;
    height: 38px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }

  .preview-container {
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  /* --- DARK MODE SUPPORT --- */
  [data-bs-theme="dark"] .card {
    background-color: #23272b;
    color: #f1f1f1;
    border-color: #333;
  }
  [data-bs-theme="dark"] .card-header.bg-success {
    background-color: #17633a !important;
    color: #fff !important;
  }
  [data-bs-theme="dark"] .card-header.bg-info {
    background-color: #17637a !important;
    color: #fff !important;
  }
  [data-bs-theme="dark"] .grid-stack-item-content,
  [data-bs-theme="dark"] .grid-stack-item-content.preview {
    background: #23272b;
    border: 1px dashed #444;
    color: #f1f1f1;
  }
  [data-bs-theme="dark"] .btn-primary {
    background-color: #17637a;
    border-color: #17637a;
  }
  [data-bs-theme="dark"] .btn-primary:hover {
    background-color: #2188b6;
    border-color: #2188b6;
  }
  [data-bs-theme="dark"] .btn-info {
    background-color: #17637a;
    border-color: #17637a;
  }
  [data-bs-theme="dark"] .btn-info:hover {
    background-color: #2188b6;
    border-color: #2188b6;
  }
  [data-bs-theme="dark"] .form-control,
  [data-bs-theme="dark"] .form-select {
    background-color: #23272b;
    color: #f1f1f1;
    border-color: #444;
  }
  [data-bs-theme="dark"] .form-control:focus,
  [data-bs-theme="dark"] .form-select:focus {
    background-color: #23272b;
    color: #fff;
    border-color: #2188b6;
  }
  [data-bs-theme="dark"] .value-box {
    color: #4dd0e1;
  }
</style>

<!-- Widget Config Modal -->
<div class="modal fade" id="widgetConfigModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo lang('configure_widget') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="config-widget-index">
        
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="config-title" class="form-label"><?php echo lang('title') ?></label>
              <input type="text" id="config-title" class="form-control">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="config-metric-key" class="form-label"><?php echo lang('metric_key') ?></label>
              <input type="text" id="config-metric-key" class="form-control">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="config-widget-type" class="form-label"><?php echo lang('widget_type') ?></label>
              <select id="config-widget-type" class="form-select" onchange="onWidgetTypeChange()">
                <option value="value_box"><?php echo lang('value_box') ?></option>
                <option value="gauge"><?php echo lang('gauge') ?></option>
                <option value="gradient_radial_bar">Gradient Radial Bar</option>
                <option value="line_chart"><?php echo lang('line_chart') ?></option>
                <option value="area_chart"><?php echo lang('area_chart') ?></option>
                <option value="bar_chart"><?php echo lang('bar_chart') ?></option>
                <option value="donut_chart"><?php echo lang('donut_chart') ?></option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="config-unit" class="form-label"><?php echo lang('unit') ?></label>
              <input type="text" id="config-unit" class="form-control" placeholder="°C, %, W, etc.">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="config-color" class="form-label"><?php echo lang('color') ?></label>
              <input type="color" id="config-color" class="color-picker" value="#007bff">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="config-gradient-to-color" class="form-label">Gradient To Color</label>
              <input type="color" id="config-gradient-to-color" class="color-picker" value="#4dd0e1">
            </div>
          </div>
        </div>

        <!-- Chart-specific options -->
        <div id="chart-options" style="display: none;">
          <hr>
          <h6>Chart Options</h6>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label for="config-chart-height" class="form-label">Height (px)</label>
                <input type="number" id="config-chart-height" class="form-control" value="200">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="config-min" class="form-label">Min</label>
                <input type="number" id="config-min" class="form-control" placeholder="auto">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="config-max" class="form-label">Max</label>
                <input type="number" id="config-max" class="form-control" placeholder="auto">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="config-show-legend" class="form-label">Show Legend</label>
                <select id="config-show-legend" class="form-select">
                  <option value="true">Yes</option>
                  <option value="false">No</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Live Preview -->
        <hr>
        <h6><?php echo lang('live_preview') ?></h6>
        <div id="config-preview" class="preview-container border rounded p-3">
          <div class="text-muted">Select widget type to see preview</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('close') ?></button>
        <button type="button" class="btn btn-primary" onclick="saveWidgetConfig()"><?php echo lang('save') ?></button>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header bg-success">
    <h5 class="mb-0 text-white"><?php echo lang('dashboard_builder') ?></h5>
  </div>
  <div class="card-body">
    <h6>Builder</h6>
    <div class="grid-stack" id="builder" style="min-height: 400px;"></div>
    <button type="button" class="btn btn-primary btn-sm mt-2" onclick="addWidget()"><?php echo lang('add_widget') ?></button>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header bg-info">
    <h5 class="mb-0 text-white"><?php echo lang('live_preview') ?></h5>
  </div>
  <div class="card-body">
    <div class="grid-stack" id="preview" style="min-height: 400px;"></div>
  </div>
</div>

<input type="hidden" id="default_dashboard_layout" name="default_dashboard_layout"
  value='<?= isset($template) ? json_encode(json_decode($template->default_dashboard_layout)) : "[]" ?>'>

<script>
const builder = GridStack.init({
  cellHeight: 100,
  float: true
}, "#builder");

const preview = GridStack.init({
  cellHeight: 100,
  disableResize: true,
  disableDrag: true,
  float: true
}, "#preview");

let layout = <?= isset($template) && $template->default_dashboard_layout ? $template->default_dashboard_layout : '[]' ?>;

// Initialize layout
document.addEventListener('DOMContentLoaded', function() {
  render();
});

function addWidget() {
  const newWidget = {
    title: "New Widget",
    metric_key: "",
    unit: "",
    type: "value_box",
    color: "#007bff",
    x: 0,
    y: 0,
    w: 4,
    h: 2,
    chart_height: 200,
    show_legend: true
  };
  
  layout.push(newWidget);
  render();
}

function editConfig(index) {
  const widget = layout[index];
  document.getElementById('config-widget-index').value = index;
  document.getElementById('config-title').value = widget.title || '';
  document.getElementById('config-metric-key').value = widget.metric_key || '';
  document.getElementById('config-unit').value = widget.unit || '';
  document.getElementById('config-widget-type').value = widget.type || 'value_box';
  document.getElementById('config-color').value = widget.color || '#007bff';
  document.getElementById('config-gradient-to-color').value = widget.gradient_to_color || '#4dd0e1';
  document.getElementById('config-chart-height').value = widget.chart_height || 200;
  document.getElementById('config-show-legend').value = widget.show_legend !== false ? 'true' : 'false';
  document.getElementById('config-min').value = widget.min ?? '';
  document.getElementById('config-max').value = widget.max ?? '';
  
  onWidgetTypeChange();
  renderConfigPreview();
  
  const modal = new bootstrap.Modal(document.getElementById('widgetConfigModal'));
  modal.show();
}

function onWidgetTypeChange() {
  const widgetType = document.getElementById('config-widget-type').value;
  const chartOptions = document.getElementById('chart-options');
  
  if (widgetType === 'value_box' || widgetType === 'gauge' || widgetType === 'gradient_radial_bar') {
    chartOptions.style.display = 'none';
  } else {
    chartOptions.style.display = 'block';
  }
  
  renderConfigPreview();
}

function renderConfigPreview() {
  const previewContainer = document.getElementById('config-preview');
  const widgetType = document.getElementById('config-widget-type').value;
  const title = document.getElementById('config-title').value || 'Preview';
  const metricKey = document.getElementById('config-metric-key').value || 'value';
  const unit = document.getElementById('config-unit').value || '';
  const color = document.getElementById('config-color').value || '#007bff';
  
  previewContainer.innerHTML = '';
  
  if (widgetType === 'value_box') {
    previewContainer.innerHTML = `
      <div class="value-box" style="color: ${color};">
        <div>${title}</div>
        <div style="font-size: 2rem;">25.5${unit}</div>
        <small>${metricKey}</small>
      </div>
    `;
  } else if (widgetType === 'gauge') {
    const gaugeId = 'gauge-preview-' + Date.now();
    previewContainer.innerHTML = `<div id="${gaugeId}"></div>`;
    
    const gaugeOptions = {
      series: [75],
      chart: {
        height: 200,
        type: 'radialBar',
      },
      plotOptions: {
        radialBar: {
          startAngle: -135,
          endAngle: 135,
          hollow: {
            margin: 15,
            size: '70%',
          },
          track: {
            background: '#e7e7e7',
            strokeWidth: '97%',
            margin: 5,
          },
          dataLabels: {
            name: {
              show: false,
            },
            value: {
              offsetY: 7,
              color: '#111',
              fontSize: '20px',
              show: true,
              formatter: function (val) {
                return val + unit;
              }
            }
          }
        }
      },
      fill: {
        colors: [color]
      },
      stroke: {
        lineCap: 'round'
      },
      labels: [title],
    };
    
    new ApexCharts(document.getElementById(gaugeId), gaugeOptions).render();
  } else if (widgetType === 'gradient_radial_bar') {
    const gradientId = 'gradient-preview-' + Date.now();
    previewContainer.innerHTML = `<div id="${gradientId}"></div>`;
    
    const gradientToColor = document.getElementById('config-gradient-to-color').value || '#4dd0e1';
    const gradientOptions = {
      series: [75],
      chart: {
        height: 200,
        type: 'radialBar',
        toolbar: { show: false }
      },
      plotOptions: {
        radialBar: {
          startAngle: -135,
          endAngle: 225,
          hollow: {
            margin: 15,
            size: '70%',
          },
          track: {
            background: '#e7e7e7',
            strokeWidth: '97%',
            margin: 5,
          },
          dataLabels: {
            name: {
              show: true,
              fontSize: '16px',
              color: '#111',
              offsetY: -10
            },
            value: {
              offsetY: 7,
              color: '#111',
              fontSize: '20px',
              show: true,
              formatter: function (val) {
                return val + unit;
              }
            }
          }
        }
      },
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'dark',
          type: 'horizontal',
          shadeIntensity: 0.5,
          gradientToColors: [gradientToColor],
          inverseColors: true,
          opacityFrom: 1,
          opacityTo: 1,
          stops: [0, 100]
        }
      },
      stroke: {
        lineCap: 'round'
      },
      labels: [title],
    };
    
    new ApexCharts(document.getElementById(gradientId), gradientOptions).render();
  } else {
    // Chart types
    const chartId = 'chart-preview-' + Date.now();
    previewContainer.innerHTML = `<div id="${chartId}"></div>`;
    
    const chartOptions = {
      series: [{
        name: title,
        data: [30, 40, 35, 50, 49, 60, 70, 91, 125]
      }],
      chart: {
        height: 200,
        type: widgetType === 'line_chart' ? 'line' : 
              widgetType === 'area_chart' ? 'area' : 
              widgetType === 'bar_chart' ? 'bar' : 'donut',
        toolbar: {
          show: false
        }
      },
      colors: [color],
      xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep']
      },
      yaxis: {
        title: {
          text: unit
        }
      }
    };
    
    new ApexCharts(document.getElementById(chartId), chartOptions).render();
  }
}

function saveWidgetConfig() {
  const index = parseInt(document.getElementById('config-widget-index').value);
  const widget = layout[index];
  
  widget.title = document.getElementById('config-title').value;
  widget.metric_key = document.getElementById('config-metric-key').value;
  widget.unit = document.getElementById('config-unit').value;
  widget.type = document.getElementById('config-widget-type').value;
  widget.color = document.getElementById('config-color').value;
  widget.gradient_to_color = document.getElementById('config-gradient-to-color').value;
  widget.chart_height = parseInt(document.getElementById('config-chart-height').value) || 200;
  widget.show_legend = document.getElementById('config-show-legend').value === 'true';
  widget.min = document.getElementById('config-min').value !== '' ? parseFloat(document.getElementById('config-min').value) : undefined;
  widget.max = document.getElementById('config-max').value !== '' ? parseFloat(document.getElementById('config-max').value) : undefined;
  
  render();
  
  const modal = bootstrap.Modal.getInstance(document.getElementById('widgetConfigModal'));
  modal.hide();
}

function removeWidget(index) {
  layout.splice(index, 1);
  render();
}

function render() {
  builder.removeAll();
  preview.removeAll();
  
  layout.forEach((widget, index) => {
    // Builder widget
    const builderEl = document.createElement("div");
    builderEl.setAttribute("gs-w", widget.w);
    builderEl.setAttribute("gs-h", widget.h);
    builderEl.setAttribute("gs-x", widget.x);
    builderEl.setAttribute("gs-y", widget.y);

    builderEl.innerHTML = `
      <div class="grid-stack-item-content border rounded p-2">
        <div class="d-flex justify-content-between align-items-center">
          <strong>${widget.title || 'Untitled'}</strong>
          <div>
            <button type="button" class="btn btn-sm btn-secondary me-1" onclick="editConfig(${index})">⚙</button>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeWidget(${index})">×</button>
          </div>
        </div>
        <small>${widget.type} - ${widget.metric_key || 'No metric'}</small>
      </div>`;

    builder.el.appendChild(builderEl);
    builder.makeWidget(builderEl);
    
    // Preview widget
    const previewEl = document.createElement("div");
    previewEl.setAttribute("gs-w", widget.w);
    previewEl.setAttribute("gs-h", widget.h);
    previewEl.setAttribute("gs-x", widget.x);
    previewEl.setAttribute("gs-y", widget.y);

    const previewId = 'preview-' + index + '-' + Date.now();
    previewEl.innerHTML = `<div class="grid-stack-item-content preview" id="${previewId}"></div>`;
    
    preview.el.appendChild(previewEl);
    preview.makeWidget(previewEl);
    
    // Render preview content
    renderWidgetPreview(widget, previewId);
  });

  updateJSON();
}

// --- ApexCharts Dark Mode Support ---
function getApexTheme() {
  const htmlTag = document.documentElement;
  return htmlTag.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
}

// Observe theme changes
const observer = new MutationObserver(function(mutations) {
  mutations.forEach(function(m) {
    if (m.type === 'attributes' && m.attributeName === 'data-bs-theme') {
      render();
    }
  });
});
observer.observe(document.documentElement, { attributes: true });

function renderWidgetPreview(widget, containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  
  if (widget.type === 'value_box') {
    container.innerHTML = `
      <div class="value-box" style="color: ${widget.color || '#4dd0e1'};">
        <div>${widget.title || 'Value'}</div>
        <div style="font-size: 2rem;">25.5${widget.unit || ''}</div>
        <small>${widget.metric_key || 'metric'}</small>
      </div>
    `;
  } else if (widget.type === 'gauge') {
    const gaugeOptions = {
      series: [75],
      chart: {
        height: widget.chart_height || 200,
        type: 'radialBar',
        toolbar: { show: false },
        foreColor: getApexTheme() === 'dark' ? '#f1f1f1' : '#333',
        background: 'transparent',
        theme: { mode: getApexTheme() }
      },
      plotOptions: {
        radialBar: {
          startAngle: -135,
          endAngle: 135,
          hollow: {
            margin: 15,
            size: '70%',
          },
          track: {
            background: getApexTheme() === 'dark' ? '#333' : '#e7e7e7',
            strokeWidth: '97%',
            margin: 5,
          },
          dataLabels: {
            name: { show: false },
            value: {
              offsetY: 7,
              color: getApexTheme() === 'dark' ? '#f1f1f1' : '#111',
              fontSize: '16px',
              show: true,
              formatter: function (val) {
                return val + (widget.unit || '');
              }
            }
          },
          min: widget.min,
          max: widget.max
        }
      },
      fill: { colors: [widget.color || '#4dd0e1'] },
      stroke: { lineCap: 'round' },
      labels: [widget.title || 'Gauge'],
    };
    new ApexCharts(container, gaugeOptions).render();
  } else if (widget.type === 'gradient_radial_bar') {
    const gradientOptions = {
      series: [75],
      chart: {
        height: widget.chart_height || 200,
        type: 'radialBar',
        toolbar: { show: false },
        foreColor: getApexTheme() === 'dark' ? '#f1f1f1' : '#333',
        background: 'transparent',
        theme: { mode: getApexTheme() }
      },
      plotOptions: {
        radialBar: {
          startAngle: -135,
          endAngle: 225,
          hollow: {
            margin: 15,
            size: '70%',
          },
          track: {
            background: getApexTheme() === 'dark' ? '#333' : '#e7e7e7',
            strokeWidth: '97%',
            margin: 5,
          },
          dataLabels: {
            name: {
              show: true,
              fontSize: '16px',
              color: getApexTheme() === 'dark' ? '#f1f1f1' : '#111',
              offsetY: -10
            },
            value: {
              offsetY: 7,
              color: getApexTheme() === 'dark' ? '#f1f1f1' : '#111',
              fontSize: '20px',
              show: true,
              formatter: function (val) {
                return val + (widget.unit || '');
              }
            }
          },
          min: widget.min,
          max: widget.max
        }
      },
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'dark',
          type: 'horizontal',
          shadeIntensity: 0.5,
          gradientToColors: [widget.gradient_to_color || '#4dd0e1'],
          inverseColors: true,
          opacityFrom: 1,
          opacityTo: 1,
          stops: [0, 100]
        }
      },
      stroke: { lineCap: 'round' },
      labels: [widget.title || 'Gradient Radial Bar'],
    };
    new ApexCharts(container, gradientOptions).render();
  } else {
    // Chart types
    const chartOptions = {
      series: [{
        name: widget.title || 'Data',
        data: [30, 40, 35, 50, 49, 60, 70, 91, 125]
      }],
      chart: {
        height: widget.chart_height || 200,
        type: widget.type === 'line_chart' ? 'line' : 
              widget.type === 'area_chart' ? 'area' : 
              widget.type === 'bar_chart' ? 'bar' : 'donut',
        toolbar: { show: false },
        foreColor: getApexTheme() === 'dark' ? '#f1f1f1' : '#333',
        background: 'transparent',
        theme: { mode: getApexTheme() }
      },
      colors: [widget.color || '#4dd0e1'],
      xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
        labels: { style: { colors: getApexTheme() === 'dark' ? '#f1f1f1' : '#333' } }
      },
      yaxis: {
        min: widget.min,
        max: widget.max,
        title: { text: widget.unit || '', style: { color: getApexTheme() === 'dark' ? '#f1f1f1' : '#333' } },
        labels: { style: { colors: getApexTheme() === 'dark' ? '#f1f1f1' : '#333' } }
      },
      legend: {
        show: widget.show_legend !== false,
        labels: { colors: getApexTheme() === 'dark' ? '#f1f1f1' : '#333' }
      }
    };
    new ApexCharts(container, chartOptions).render();
  }
}

function updateJSON() {
  const positions = builder.save();
  layout.forEach((widget, index) => {
    if (positions[index]) {
      widget.x = positions[index].x;
      widget.y = positions[index].y;
      widget.w = positions[index].w;
      widget.h = positions[index].h;
    }
  });
  
  document.getElementById("default_dashboard_layout").value = JSON.stringify(layout);
}

builder.on('change', function() {
  updateJSON();
});
</script>