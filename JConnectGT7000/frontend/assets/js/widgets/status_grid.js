function renderStatusGridPreview(container, config) {
  const items = config.items || [];
  const columns = Math.max(1, Math.min(6, parseInt(config.columns, 10) || 3));
  if (!items.length) {
    container.innerHTML = '<div class="text-muted small text-center p-2">Status Grid (no items configured)</div>';
    return;
  }

  const cells = items.map((item) => {
    const icon = (item.icon || 'bi-circle').replace(/^bi-?/, '');
    const maps = Array.isArray(item.value_maps) ? item.value_maps : [];
    const previewValue = maps[0]?.label || item.state_off || 'Off';
    return `
      <div class="status-grid-cell" style="display:flex;flex-direction:row;align-items:center;gap:0.55rem;text-align:left;padding:0.45rem 0.55rem;">
        <div class="status-grid-dot"></div>
        <div class="status-grid-icon bi bi-${icon}" style="font-size:1.6rem;line-height:1;flex-shrink:0;"></div>
        <div class="status-grid-cell-body">
          <div class="status-grid-label">${item.label || item.metric_key || '-'}</div>
          <div class="status-grid-value">${previewValue}</div>
        </div>
      </div>
    `;
  }).join('');

  container.innerHTML = `
    <div class="widget-status-grid border rounded p-2"${config.link_url ? ` style="cursor:pointer;" title="${String(config.link_url).replace(/"/g, '&quot;')}"` : ''}>
      ${config.label ? `<div class="status-grid-title small text-secondary mb-2">${config.label}</div>` : ''}
      <div class="status-grid-body" style="display:grid;grid-template-columns:repeat(${columns}, minmax(0,1fr));gap:0.5rem;">
        ${cells}
      </div>
    </div>
  `;
}

function statusGridConfigPanel() {
  const panel = document.createElement('div');
  panel.innerHTML = `
    <div id="status-grid-settings" class="widget-type-section mb-4" data-widget-type="status_grid">
      <hr class="border-light">
      <div class="row g-2 mb-2">
        <div class="col-sm-4">
          <label class="form-label small">Columns</label>
          <input type="number" id="cfg-columns" class="form-control form-control-sm" min="1" max="6" value="3">
        </div>
      </div>
      <label class="form-label text-white">Status Items</label>
      <div id="status-grid-item-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" onclick="addStatusGridItem()">+ Add Item</button>
    </div>
  `;
  document.getElementById('widget-config-form').appendChild(panel);
}

function escapeAttr(value) {
  return String(value ?? '').replace(/"/g, '&quot;');
}

function buildStatusGridIconSelectHtml(selected = 'bi-circle') {
  const options = (typeof WIDGET_ICON_OPTIONS !== 'undefined' && Array.isArray(WIDGET_ICON_OPTIONS))
    ? WIDGET_ICON_OPTIONS
    : [{ label: 'Circle (default)', value: 'bi-circle' }];
  const selectedVal = selected || 'bi-circle';
  const normalize = (typeof normalizeBiIconClass === 'function')
    ? normalizeBiIconClass
    : (name) => {
        const n = String(name || 'bi-circle').trim();
        if (n.startsWith('bi bi-')) return n;
        if (n.startsWith('bi-')) return 'bi ' + n;
        return 'bi bi-' + n;
      };
  const getLabel = (typeof getWidgetIconLabel === 'function')
    ? getWidgetIconLabel
    : (value) => (options.find((o) => o.value === value)?.label || value);
  const optionsHtml = (typeof buildIconSelectOptionsHtml === 'function')
    ? buildIconSelectOptionsHtml(options, selectedVal)
    : options.map((o) => `
        <button type="button" class="widget-icon-select-option${selectedVal === o.value ? ' active' : ''}"
          data-value="${escapeAttr(o.value)}" data-label="${escapeAttr(o.label)}" title="${escapeAttr(o.label)}">
          <i class="${normalize(o.value)}"></i>
          <span>${o.label}</span>
        </button>
      `).join('');

  return `
    <div class="widget-icon-select" data-field-key="icon">
      <input type="hidden" name="items[][icon]" data-field="icon" value="${escapeAttr(selectedVal)}">
      <button type="button" class="widget-icon-select-toggle form-select form-select-sm">
        <i class="${normalize(selectedVal)}"></i>
        <span>${getLabel(selectedVal, options)}</span>
      </button>
      <div class="widget-icon-select-menu">
        <div class="widget-icon-select-grid">
          ${optionsHtml}
        </div>
      </div>
    </div>
  `;
}

function mountStatusGridIconSelect(rowEl, selected = 'bi-circle') {
  const mount = rowEl?.querySelector('.status-grid-icon-mount');
  if (!mount) return;
  mount.innerHTML = buildStatusGridIconSelectHtml(selected || 'bi-circle');
  if (typeof initWidgetIconSelectors === 'function') {
    initWidgetIconSelectors(mount);
  }
}

function setStatusGridItemIcon(rowEl, iconValue) {
  const selected = iconValue || 'bi-circle';
  const mount = rowEl?.querySelector('.status-grid-icon-mount');
  if (!mount) return;

  const wrapper = mount.querySelector('.widget-icon-select');
  if (!wrapper) {
    mountStatusGridIconSelect(rowEl, selected);
    return;
  }

  const hidden = wrapper.querySelector('input[name="items[][icon]"]');
  const toggleIcon = wrapper.querySelector('.widget-icon-select-toggle i');
  const toggleLabel = wrapper.querySelector('.widget-icon-select-toggle span');
  const normalize = (typeof normalizeBiIconClass === 'function')
    ? normalizeBiIconClass
    : (name) => 'bi ' + String(name || 'bi-circle').replace(/^bi\s+/, '');
  const getLabel = (typeof getWidgetIconLabel === 'function')
    ? getWidgetIconLabel
    : (value) => value;

  if (hidden) hidden.value = selected;
  if (toggleIcon) toggleIcon.className = normalize(selected);
  if (toggleLabel) toggleLabel.textContent = getLabel(selected);

  wrapper.querySelectorAll('.widget-icon-select-option').forEach((el) => {
    el.classList.toggle('active', el.dataset.value === selected);
  });
}

function collectStatusItemValueMaps(itemEl) {
  const rows = [];
  itemEl.querySelectorAll('.status-item-value-maps .value-map-item').forEach((mapEl) => {
    const value = mapEl.querySelector('[name="items[][value_maps][][value]"]')?.value ?? '';
    const label = mapEl.querySelector('[name="items[][value_maps][][label]"]')?.value ?? '';
    const icon_color = mapEl.querySelector('[name="items[][value_maps][][icon_color]"]')?.value ?? '';
    const icon_effect = mapEl.querySelector('[name="items[][value_maps][][icon_effect]"]')?.value ?? '';
    if (value === '' && label === '') return;
    const row = { value, label };
    if (icon_color) row.icon_color = icon_color;
    if (icon_effect && icon_effect !== 'none') row.icon_effect = icon_effect;
    rows.push(row);
  });
  return rows;
}

function addStatusGridValueMapRow(listEl, map = {}) {
  if (!listEl) return null;
  const value = map.value ?? '';
  const label = map.label ?? '';
  const iconColor = map.icon_color || '#ffffff';
  const iconEffect = map.icon_effect || 'none';

  const row = document.createElement('div');
  row.className = 'value-map-item border border-secondary rounded p-2 mb-2';
  row.innerHTML = `
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Value</label>
        <input type="text" name="items[][value_maps][][value]" class="form-control form-control-sm" placeholder="e.g. 1" value="${escapeAttr(value)}">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Display As</label>
        <input type="text" name="items[][value_maps][][label]" class="form-control form-control-sm" placeholder="e.g. Running" value="${escapeAttr(label)}">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Color</label>
        <input type="color" name="items[][value_maps][][icon_color]" class="form-control form-control-color form-control-sm" value="${escapeAttr(iconColor)}">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Effect</label>
        <select name="items[][value_maps][][icon_effect]" class="form-select form-select-sm">
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
  row.querySelectorAll('input, select').forEach((input) => {
    input.addEventListener('input', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
    input.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  });
  listEl.appendChild(row);
  return row;
}

function collectStatusGridConfig() {
  const form = document.getElementById('widget-config-form');
  const config = {
    label: form.querySelector('[id="cfg-label"]')?.value || '',
    link_url: form.querySelector('[id="cfg-link_url"]')?.value || '',
    columns: form.querySelector('[id="cfg-columns"]')?.value || '3',
    show_border: form.querySelector('[id="cfg-show_border"]')?.value ?? '1',
    bg_color: form.querySelector('[id="cfg-bg_color"]')?.value || '',
    bg_opacity: form.querySelector('[id="cfg-bg_opacity"]')?.value || '100',
    items: []
  };

  form.querySelectorAll('#status-grid-item-list .series-item').forEach((item) => {
    config.items.push({
      label: item.querySelector('[name="items[][label]"]')?.value || '',
      device_id: item.querySelector('[name="items[][device_id]"]')?.value || '',
      metric_key: item.querySelector('[name="items[][metric_key]"]')?.value || '',
      icon: item.querySelector('[name="items[][icon]"]')?.value || 'bi-circle',
      state_on: item.querySelector('[name="items[][state_on]"]')?.value || 'On',
      state_off: item.querySelector('[name="items[][state_off]"]')?.value || 'Off',
      color_on: item.querySelector('[name="items[][color_on]"]')?.value || '#22c55e',
      color_off: item.querySelector('[name="items[][color_off]"]')?.value || '#94a3b8',
      value_maps: collectStatusItemValueMaps(item)
    });
  });

  return config;
}

function appendStatusGridItemRow() {
  const tmpl = document.getElementById('status-grid-item-template');
  if (!tmpl) return null;
  const wrapper = document.createElement('div');
  wrapper.innerHTML = tmpl.innerHTML;
  const row = wrapper.firstElementChild;
  document.getElementById('status-grid-item-list').appendChild(row);
  mountStatusGridIconSelect(row, 'bi-circle');
  bindStatusGridRow(row);
  return row;
}

function bindStatusGridRow(rowEl) {
  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');
  if (deviceSelect && !deviceSelect.dataset.bound) {
    deviceSelect.dataset.bound = '1';
    deviceSelect.addEventListener('change', () => loadMetricOptions(deviceSelect.value, metricSelect));
  }

  const addMapBtn = rowEl.querySelector('.btn-add-status-value-map');
  if (addMapBtn && !addMapBtn.dataset.bound) {
    addMapBtn.dataset.bound = '1';
    addMapBtn.addEventListener('click', () => {
      const list = rowEl.querySelector('.status-item-value-maps');
      addStatusGridValueMapRow(list);
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  }

  rowEl.querySelectorAll('input, select').forEach((input) => {
    if (input.dataset.previewBound) return;
    input.dataset.previewBound = '1';
    input.addEventListener('input', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
    input.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  });
}

async function fillStatusGridRow(rowEl, item = {}) {
  if (!rowEl) return;
  const fields = ['label', 'state_on', 'state_off', 'color_on', 'color_off'];
  fields.forEach((key) => {
    const input = rowEl.querySelector(`[name="items[][${key}]"]`);
    if (input && item[key] !== undefined) input.value = item[key];
  });

  setStatusGridItemIcon(rowEl, item.icon || 'bi-circle');

  const mapsList = rowEl.querySelector('.status-item-value-maps');
  if (mapsList) {
    mapsList.innerHTML = '';
    const maps = Array.isArray(item.value_maps) ? item.value_maps : [];
    maps.forEach((map) => addStatusGridValueMapRow(mapsList, map || {}));
  }

  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');
  if (!deviceSelect) return;

  await loadDeviceOptions(deviceSelect, item.device_id || '');
  deviceSelect.value = item.device_id || '';
  if (item.device_id) {
    await loadMetricOptions(item.device_id, metricSelect, item.metric_key || '');
    if (metricSelect && item.metric_key) metricSelect.value = item.metric_key;
  }
}

async function addStatusGridItem(prefill) {
  const rowEl = appendStatusGridItemRow();
  if (!rowEl) return;
  await fillStatusGridRow(rowEl, prefill || {});
  if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
}

async function populateStatusGridForm(items, columns) {
  const columnsInput = document.getElementById('cfg-columns');
  if (columnsInput && columns) columnsInput.value = columns;
  for (const item of items || []) {
    await addStatusGridItem(item);
  }
  if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
}

function coerceStatusValue(value) {
  if (typeof coerceBool === 'function') {
    return coerceBool(value);
  }
  if (value === true || value === 'true' || value === 'TRUE' || value === 'True' || value === '1' || value === 1 || value === 't') return true;
  if (value === false || value === 'false' || value === 'FALSE' || value === 'False' || value === '0' || value === 0 || value === '' || value === 'f') return false;
  return null;
}

function applyStatusGridIconEffect(iconEl, effect) {
  if (!iconEl) return;
  iconEl.classList.remove('effect-spin', 'effect-pulse', 'effect-blink');
  if (effect && effect !== 'none') {
    iconEl.classList.add('effect-' + effect);
  }
}

function updateStatusGridCell(cell, value, datatype) {
  const valueEl = cell.querySelector('.status-grid-value');
  const dotEl = cell.querySelector('.status-grid-dot');
  const iconEl = cell.querySelector('.status-grid-icon');
  if (!valueEl) return;

  let text = '--';
  let color = cell.dataset.colorOff || '#94a3b8';
  let effect = 'none';

  const mapsRaw = cell.dataset.valueMaps || '[]';
  let maps = [];
  try {
    maps = JSON.parse(mapsRaw);
  } catch (_e) {
    maps = [];
  }

  if (Array.isArray(maps) && maps.length && typeof resolveDisplayValue === 'function') {
    const resolved = resolveDisplayValue({ dataset: { valueMaps: mapsRaw } }, value, datatype);
    text = resolved.text;
    if (resolved.map?.icon_color) color = resolved.map.icon_color;
    if (resolved.map?.icon_effect) effect = resolved.map.icon_effect;
  } else {
    const boolVal = coerceStatusValue(value);
    if (boolVal === true) {
      text = cell.dataset.stateOn || 'On';
      color = cell.dataset.colorOn || '#22c55e';
    } else if (boolVal === false) {
      text = cell.dataset.stateOff || 'Off';
      color = cell.dataset.colorOff || '#94a3b8';
    } else if (value !== null && value !== undefined && value !== '') {
      text = typeof formatMetricValue === 'function'
        ? formatMetricValue(value, datatype)
        : String(value);
      const num = Number(value);
      if (Number.isFinite(num) && num > 0) {
        color = cell.dataset.colorOn || '#22c55e';
      }
    }
  }

  valueEl.textContent = text;
  if (dotEl) dotEl.style.background = color;
  if (iconEl) {
    iconEl.style.color = color;
    applyStatusGridIconEffect(iconEl, effect);
  }
}

function updateStatusGridCells(deviceId, metricKey, value, datatype) {
  const cells = document.querySelectorAll(
    `.status-grid-cell[data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );
  cells.forEach((cell) => updateStatusGridCell(cell, value, datatype));
}

function initStatusGridLinks() {
  document.querySelectorAll('.widget-status-grid[data-link-url]').forEach((el) => {
    const url = (el.dataset.linkUrl || '').trim();
    if (!url || el.dataset.linkBound === '1') return;
    el.dataset.linkBound = '1';
    el.classList.add('is-clickable');
    const navigate = () => {
      window.location.href = url;
    };
    el.addEventListener('click', navigate);
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        navigate();
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', initStatusGridLinks);
