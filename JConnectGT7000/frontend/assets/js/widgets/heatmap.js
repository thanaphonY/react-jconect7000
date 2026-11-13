function renderHeatmapPreview(container, config) {
  const items = config.items || [];
  const columns = Math.max(1, Math.min(8, parseInt(config.columns, 10) || 4));
  if (!items.length) {
    container.innerHTML = '<div class="text-muted small text-center p-2">Heatmap (no sensors configured)</div>';
    return;
  }

  const cells = items.map((item, idx) => {
    const level = idx % 5 === 4 ? 'level-alarm' : (idx % 3 === 2 ? 'level-warn' : '');
    const value = idx % 5 === 4 ? '31.2' : (idx % 3 === 2 ? '26.5' : '22.1');
    return `
      <div class="heatmap-cell ${level}">
        <div class="heatmap-value">${value}°C</div>
        <div class="heatmap-label">${item.label || item.metric_key || '-'}</div>
      </div>
    `;
  }).join('');

  container.innerHTML = `
    <div class="widget-heatmap border rounded p-2">
      ${config.label ? `<div class="heatmap-title small text-secondary mb-2">${config.label}</div>` : ''}
      <div class="heatmap-body" style="display:grid;grid-template-columns:repeat(${columns}, minmax(0,1fr));gap:0.5rem;">
        ${cells}
      </div>
    </div>
  `;
}

function heatmapConfigPanel() {
  const panel = document.createElement('div');
  panel.innerHTML = `
    <div id="heatmap-settings" class="widget-type-section mb-4" data-widget-type="heatmap">
      <hr class="border-light">
      <div class="row g-2 mb-2">
        <div class="col-sm-3">
          <label class="form-label small">Columns</label>
          <input type="number" id="cfg-columns" class="form-control form-control-sm" min="1" max="8" value="4">
        </div>
        <div class="col-sm-3">
          <label class="form-label small">Warn ≥</label>
          <input type="number" step="any" id="cfg-warn_threshold" class="form-control form-control-sm" value="25">
        </div>
        <div class="col-sm-3">
          <label class="form-label small">Alarm ≥</label>
          <input type="number" step="any" id="cfg-alarm_threshold" class="form-control form-control-sm" value="30">
        </div>
        <div class="col-sm-3">
          <label class="form-label small">Unit</label>
          <input type="text" id="cfg-unit" class="form-control form-control-sm" value="°C">
        </div>
      </div>
      <label class="form-label text-white">Sensors</label>
      <div id="heatmap-item-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" onclick="addHeatmapItem()">+ Add Sensor</button>
    </div>
  `;
  document.getElementById('widget-config-form').appendChild(panel);
}

function collectHeatmapConfig() {
  const form = document.getElementById('widget-config-form');
  const config = {
    label: form.querySelector('[id="cfg-label"]')?.value || '',
    columns: form.querySelector('[id="cfg-columns"]')?.value || '4',
    warn_threshold: form.querySelector('[id="cfg-warn_threshold"]')?.value || '25',
    alarm_threshold: form.querySelector('[id="cfg-alarm_threshold"]')?.value || '30',
    unit: form.querySelector('[id="cfg-unit"]')?.value || '°C',
    items: []
  };

  form.querySelectorAll('#heatmap-item-list .series-item').forEach((item) => {
    config.items.push({
      label: item.querySelector('[name="items[][label]"]')?.value || '',
      device_id: item.querySelector('[name="items[][device_id]"]')?.value || '',
      metric_key: item.querySelector('[name="items[][metric_key]"]')?.value || 'Temperature'
    });
  });

  return config;
}

function appendHeatmapItemRow() {
  const tmpl = document.getElementById('heatmap-item-template');
  if (!tmpl) return null;
  const wrapper = document.createElement('div');
  wrapper.innerHTML = tmpl.innerHTML;
  const row = wrapper.firstElementChild;
  document.getElementById('heatmap-item-list').appendChild(row);
  bindHeatmapRow(row);
  return row;
}

function bindHeatmapRow(rowEl) {
  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');
  if (deviceSelect && !deviceSelect.dataset.bound) {
    deviceSelect.dataset.bound = '1';
    deviceSelect.addEventListener('change', () => loadMetricOptions(deviceSelect.value, metricSelect));
  }
  rowEl.querySelectorAll('input, select').forEach((input) => {
    if (input.dataset.previewBound) return;
    input.dataset.previewBound = '1';
    input.addEventListener('input', () => updatePreviewFromForm?.());
    input.addEventListener('change', () => updatePreviewFromForm?.());
  });
}

async function fillHeatmapRow(rowEl, item = {}) {
  if (!rowEl) return;
  const labelInput = rowEl.querySelector('[name="items[][label]"]');
  if (labelInput && item.label !== undefined) labelInput.value = item.label;

  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');
  if (!deviceSelect) return;

  await loadDeviceOptions(deviceSelect, item.device_id || '');
  deviceSelect.value = item.device_id || '';
  if (item.device_id) {
    await loadMetricOptions(item.device_id, metricSelect, item.metric_key || 'Temperature');
    if (metricSelect) metricSelect.value = item.metric_key || 'Temperature';
  }
}

async function addHeatmapItem(prefill) {
  const rowEl = appendHeatmapItemRow();
  if (!rowEl) return;
  await fillHeatmapRow(rowEl, prefill || {});
  updatePreviewFromForm?.();
}

async function populateHeatmapForm(items, config = {}) {
  const map = {
    columns: 'cfg-columns',
    warn_threshold: 'cfg-warn_threshold',
    alarm_threshold: 'cfg-alarm_threshold',
    unit: 'cfg-unit'
  };
  Object.entries(map).forEach(([key, id]) => {
    const input = document.getElementById(id);
    if (input && config[key] !== undefined) input.value = config[key];
  });
  for (const item of items || []) {
    await addHeatmapItem(item);
  }
  updatePreviewFromForm?.();
}

function getHeatmapLevel(cell, value) {
  const widget = cell.closest('.widget-heatmap');
  const warnAt = parseFloat(widget?.dataset.warnThreshold ?? '25');
  const alarmAt = parseFloat(widget?.dataset.alarmThreshold ?? '30');
  const num = Number(value);
  if (!Number.isFinite(num)) return 'level-normal';
  if (num >= alarmAt) return 'level-alarm';
  if (num >= warnAt) return 'level-warn';
  return 'level-normal';
}

function updateHeatmapCells(deviceId, metricKey, value, datatype) {
  const cells = document.querySelectorAll(
    `.widget-heatmap .heatmap-cell[data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );
  cells.forEach((cell) => {
    const valueEl = cell.querySelector('.heatmap-value .value');
    if (!valueEl) return;
    const widget = cell.closest('.widget-heatmap');
    const unit = widget?.dataset.unit || '°C';
    const formatted = typeof formatMetricValue === 'function'
      ? formatMetricValue(value, datatype)
      : String(value ?? '--');
    valueEl.textContent = formatted === '--' ? '--' : `${formatted}${unit}`;
    cell.classList.remove('level-normal', 'level-warn', 'level-alarm');
    cell.classList.add(getHeatmapLevel(cell, value));
  });
}
