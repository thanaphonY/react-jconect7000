const ZONE_OVERVIEW_ICONS = [
  { value: 'bi-dot', label: 'Default' },
  { value: 'bi-building', label: 'Building' },
  { value: 'bi-house-door', label: 'House' },
  { value: 'bi-hdd-rack', label: 'Server Rack' },
  { value: 'bi-server', label: 'Server' },
  { value: 'bi-cpu', label: 'Device / CPU' },
  { value: 'bi-thermometer-half', label: 'Temperature' },
  { value: 'bi-moisture', label: 'Humidity' },
  { value: 'bi-droplet', label: 'Water / Liquid' },
  { value: 'bi-wind', label: 'Airflow' },
  { value: 'bi-fan', label: 'Fan / Cooling' },
  { value: 'bi-fire', label: 'Heat / Fire' },
  // Electrical (closest Bootstrap Icons — no dedicated AC/DC/Sine/Zeta glyphs)
  { value: 'bi-lightning-charge', label: 'Voltage / Energy' },
  { value: 'bi-lightning-fill', label: 'Voltage (solid)' },
  { value: 'bi-plug', label: 'AC / Plug' },
  { value: 'bi-outlet', label: 'Outlet' },
  { value: 'bi-power', label: 'Power' },
  { value: 'bi-battery', label: 'DC / Battery' },
  { value: 'bi-battery-charging', label: 'DC Charging' },
  { value: 'bi-battery-full', label: 'Battery Full' },
  { value: 'bi-activity', label: 'Waveform ≈ Sine' },
  { value: 'bi-soundwave', label: 'Soundwave / AC wave' },
  { value: 'bi-plus-slash-minus', label: '± / Polarity' },
  { value: 'bi-arrow-left-right', label: 'Bidirectional / Transfer' },
  { value: 'bi-thunderbolt', label: 'Thunderbolt' },
  { value: 'bi-ev-station', label: 'EV / Charging Station' },
  { value: 'bi-speedometer2', label: 'Gauge / Frequency' },
  { value: 'bi-graph-up', label: 'Trend / Load' },
  { value: 'bi-broadcast', label: 'Antenna / Signal' },
  { value: 'bi-reception-4', label: 'Signal Strength' },
  { value: 'bi-wifi', label: 'WiFi' },
  { value: 'bi-toggle-on', label: 'On / Status' },
  { value: 'bi-gear-wide-connected', label: 'Machine / Gear' },
  { value: 'bi-shield-check', label: 'Status OK' },
  { value: 'bi-exclamation-triangle', label: 'Warning' },
  { value: 'bi-radioactive', label: 'Hazard' }
];

function renderZoneOverviewPreview(container, config) {
  const items = config.items || [];
  const zoneName = config.zone_name || config.label || 'Zone';
  const subtitle = config.zone_subtitle || '';
  const icon = config.icon || 'bi-building';
  const showPrimary = (config.show_primary ?? '1') !== '0';
  const maps = Array.isArray(config.value_maps) ? config.value_maps : [];
  const firstMap = maps[0] || {};
  const previewValue = firstMap.label || '--';
  const previewColor = firstMap.icon_color ? ` style="color:${firstMap.icon_color}"` : '';

  const metricRows = items.length
    ? items.map(item => `
        <div class="zone-metric-row">
          <i class="bi ${item.icon || 'bi-dot'} zone-metric-icon"></i>
          <span class="zone-metric-label">${item.label || item.metric_key || '-'}</span>
          <span class="zone-metric-value">--</span>
          <span class="zone-metric-unit">${item.unit || ''}</span>
        </div>
      `).join('')
    : '<div class="zone-metric-empty small">No metrics configured</div>';

  const primaryHtml = showPrimary && config.primary_device_id && config.primary_metric_key
    ? `
      <div class="zone-primary-value"${previewColor}>${previewValue}</div>
      <div class="zone-primary-label">${config.primary_label || config.primary_metric_key || 'Primary'}</div>
    `
    : `
      <div class="zone-alarm-count">0</div>
      <div class="zone-alarm-label">Active Alarms</div>
    `;

  container.innerHTML = `
    <div class="widget-zone-overview zone-overview--normal" style="min-height: 140px;">
      <div class="zone-overview-header">
        <span class="zone-overview-name">${zoneName}</span>
        <i class="bi ${icon} zone-overview-icon"></i>
      </div>
      <div class="zone-overview-body">
        <div class="zone-overview-primary">${primaryHtml}</div>
        <div class="zone-overview-metrics">${metricRows}</div>
      </div>
      ${subtitle ? `<div class="zone-overview-footer">${subtitle}</div>` : ''}
    </div>
  `;
}

function zoneOverviewEscapeAttr(value) {
  return String(value ?? '').replace(/"/g, '&quot;');
}

function zoneOverviewNormalizeIcon(name) {
  if (typeof normalizeBiIconClass === 'function') {
    return normalizeBiIconClass(name);
  }
  const n = String(name || 'bi-dot').trim();
  if (n.startsWith('bi bi-')) return n;
  if (n.startsWith('bi-')) return 'bi ' + n;
  return 'bi bi-' + n;
}

function zoneOverviewIconLabel(value) {
  return ZONE_OVERVIEW_ICONS.find(o => o.value === value)?.label || value || 'Default';
}

function buildZoneOverviewIconSelectHtml(selected = 'bi-dot', { name = 'items[][icon]', id = '', field = 'icon' } = {}) {
  const selectedVal = selected || 'bi-dot';
  const options = [...ZONE_OVERVIEW_ICONS];
  if (selectedVal && !options.some(o => o.value === selectedVal)) {
    options.unshift({ value: selectedVal, label: selectedVal });
  }

  const optionsHtml = (typeof buildIconSelectOptionsHtml === 'function')
    ? buildIconSelectOptionsHtml(options, selectedVal)
    : options.map(o => `
        <button type="button" class="widget-icon-select-option${selectedVal === o.value ? ' active' : ''}"
          data-value="${zoneOverviewEscapeAttr(o.value)}" data-label="${zoneOverviewEscapeAttr(o.label)}" title="${zoneOverviewEscapeAttr(o.label)}">
          <i class="${zoneOverviewNormalizeIcon(o.value)}"></i>
          <span>${o.label}</span>
        </button>
      `).join('');

  const idAttr = id ? ` id="${zoneOverviewEscapeAttr(id)}"` : '';

  return `
    <div class="widget-icon-select" data-field-key="${zoneOverviewEscapeAttr(field)}">
      <input type="hidden"${idAttr} name="${zoneOverviewEscapeAttr(name)}" data-field="${zoneOverviewEscapeAttr(field)}" value="${zoneOverviewEscapeAttr(selectedVal)}">
      <button type="button" class="widget-icon-select-toggle form-select form-select-sm">
        <i class="${zoneOverviewNormalizeIcon(selectedVal)}"></i>
        <span>${zoneOverviewIconLabel(selectedVal)}</span>
      </button>
      <div class="widget-icon-select-menu">
        <div class="widget-icon-select-grid">
          ${optionsHtml}
        </div>
      </div>
    </div>
  `;
}

function mountZoneOverviewIconSelect(mountEl, selected = 'bi-dot', opts = {}) {
  if (!mountEl) return;
  mountEl.innerHTML = buildZoneOverviewIconSelectHtml(selected || 'bi-dot', opts);
  if (typeof initWidgetIconSelectors === 'function') {
    initWidgetIconSelectors(mountEl);
  }
}

function setZoneOverviewIconSelectValue(mountEl, iconValue, opts = {}) {
  const selected = iconValue || 'bi-dot';
  if (!mountEl) return;

  const wrapper = mountEl.querySelector('.widget-icon-select');
  if (!wrapper) {
    mountZoneOverviewIconSelect(mountEl, selected, opts);
    return;
  }

  const hidden = wrapper.querySelector('input[type="hidden"]');
  const toggleIcon = wrapper.querySelector('.widget-icon-select-toggle i');
  const toggleLabel = wrapper.querySelector('.widget-icon-select-toggle span');

  if (hidden) hidden.value = selected;
  if (toggleIcon) toggleIcon.className = zoneOverviewNormalizeIcon(selected);
  if (toggleLabel) toggleLabel.textContent = zoneOverviewIconLabel(selected);

  wrapper.querySelectorAll('.widget-icon-select-option').forEach((el) => {
    el.classList.toggle('active', el.dataset.value === selected);
  });
}

function zoneOverviewConfigPanel() {
  const panel = document.createElement('div');
  panel.innerHTML = `
    <div id="zone-overview-settings" class="widget-type-section mb-4" data-widget-type="zone_overview">
      <hr class="border-light">
      <div class="text-secondary small mb-3">
        Set <strong>Click URL</strong> above to navigate when the card is clicked.
        Examples: <code>dashboard/view/5</code> or <code>home/index/3</code>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label small">Card Icon</label>
          <div id="zone-card-icon-mount" class="zone-overview-icon-mount"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label small">Show Primary KPI (left)</label>
          <select id="cfg-show_primary" class="form-select form-select-sm" data-field="show_primary">
            <option value="1">Yes — show metric</option>
            <option value="0">No — show alarm count</option>
          </select>
        </div>
      </div>

      <div id="zone-primary-settings" class="border border-secondary rounded p-2 mb-3">
        <label class="form-label small text-white-50 mb-2">Primary KPI (left side)</label>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small">Label</label>
            <input type="text" id="cfg-primary_label" class="form-control form-control-sm" data-field="primary_label" placeholder="e.g. Used Power">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Unit</label>
            <input type="text" id="cfg-primary_unit" class="form-control form-control-sm" data-field="primary_unit" placeholder="e.g. %">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Device</label>
            <select id="cfg-primary_device_id" class="form-select form-select-sm device-select" data-field="primary_device_id">
              <option value="">Select Device</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Metric</label>
            <select id="cfg-primary_metric_key" class="form-select form-select-sm metric-select" data-field="primary_metric_key">
              <option value="">Select Metric</option>
            </select>
          </div>
        </div>
        <div id="zone-primary-value-maps"></div>
      </div>

      <label class="form-label text-white">Metrics (right side)</label>
      <div class="text-secondary small mb-2">Add metrics to display on each card. You can choose label, device, metric, unit and icon per row.</div>
      <div id="zone-overview-metric-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" onclick="addZoneOverviewMetric()">+ Add Metric</button>

      <hr class="border-secondary my-3">
      <label class="form-label text-white">Alarm Monitor Devices</label>
      <div class="text-secondary small mb-2">Devices to count for Active Alarms (left side). Auto-includes devices from metrics above.</div>
      <div id="zone-alarm-device-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" onclick="addZoneAlarmDevice()">+ Add Alarm Device</button>
    </div>
  `;
  document.getElementById('widget-config-form').appendChild(panel);

  mountZoneOverviewIconSelect(
    document.getElementById('zone-card-icon-mount'),
    'bi-building',
    { name: 'icon', id: 'cfg-icon', field: 'icon' }
  );

  const primaryDevice = document.getElementById('cfg-primary_device_id');
  const primaryMetric = document.getElementById('cfg-primary_metric_key');
  if (primaryDevice) {
    loadDeviceOptions(primaryDevice);
    primaryDevice.addEventListener('change', () => {
      loadMetricOptions(primaryDevice.value, primaryMetric);
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  }
  if (primaryMetric) {
    primaryMetric.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  }

  const showPrimary = document.getElementById('cfg-show_primary');
  const primarySettings = document.getElementById('zone-primary-settings');
  const togglePrimary = () => {
    if (primarySettings) {
      primarySettings.style.display = showPrimary?.value === '0' ? 'none' : 'block';
    }
    if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
  };
  showPrimary?.addEventListener('change', togglePrimary);
  togglePrimary();
}

function appendZoneOverviewMetricRow() {
  const tmpl = document.getElementById('zone-overview-metric-template');
  if (!tmpl) return null;

  const wrapper = document.createElement('div');
  wrapper.innerHTML = tmpl.innerHTML;
  const newItem = wrapper.firstElementChild;
  document.getElementById('zone-overview-metric-list').appendChild(newItem);
  bindZoneOverviewMetricRow(newItem);
  return newItem;
}

function bindZoneOverviewMetricRow(rowEl) {
  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');
  const iconMount = rowEl.querySelector('.zone-overview-icon-mount');

  if (iconMount && !iconMount.dataset.iconsReady) {
    iconMount.dataset.iconsReady = '1';
    const current = iconMount.querySelector('[name="items[][icon]"]')?.value || 'bi-dot';
    mountZoneOverviewIconSelect(iconMount, current, { name: 'items[][icon]', field: 'icon' });
  }

  if (deviceSelect && !deviceSelect.dataset.bound) {
    deviceSelect.dataset.bound = '1';
    deviceSelect.addEventListener('change', () => {
      loadMetricOptions(deviceSelect.value, metricSelect);
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  }

  if (metricSelect && !metricSelect.dataset.bound) {
    metricSelect.dataset.bound = '1';
    metricSelect.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  }

  rowEl.querySelectorAll('input, select').forEach(el => {
    if (el.dataset.previewBound) return;
    el.dataset.previewBound = '1';
    el.addEventListener('input', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
    el.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  });
}

async function fillZoneOverviewMetricRow(rowEl, item = {}) {
  if (!rowEl) return;

  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');
  const labelInput = rowEl.querySelector('[name="items[][label]"]');
  const unitInput = rowEl.querySelector('[name="items[][unit]"]');
  const iconMount = rowEl.querySelector('.zone-overview-icon-mount');

  if (labelInput && item.label !== undefined) labelInput.value = item.label || '';
  if (unitInput && item.unit !== undefined) unitInput.value = item.unit || '';
  if (iconMount) {
    setZoneOverviewIconSelectValue(
      iconMount,
      item.icon || 'bi-dot',
      { name: 'items[][icon]', field: 'icon' }
    );
    iconMount.dataset.iconsReady = '1';
  }

  if (!deviceSelect) return;

  await loadDeviceOptions(deviceSelect, item.device_id || '');
  deviceSelect.value = item.device_id || '';

  if (item.device_id) {
    await loadMetricOptions(item.device_id, metricSelect, item.metric_key || '');
    if (item.metric_key && metricSelect) {
      metricSelect.value = item.metric_key;
    }
  }
}

async function addZoneOverviewMetric(prefill) {
  const rowEl = appendZoneOverviewMetricRow();
  if (!rowEl) return;

  await fillZoneOverviewMetricRow(rowEl, prefill || {});

  if (typeof updatePreviewFromForm === 'function') {
    updatePreviewFromForm();
  }
}

async function duplicateZoneOverviewMetric(btn) {
  const source = btn.closest('.series-item');
  if (!source) return;

  const deviceId = source.querySelector('[name="items[][device_id]"]')?.value || '';
  if (!deviceId) {
    alert('Please select a device first.');
    return;
  }

  const rowEl = appendZoneOverviewMetricRow();
  await fillZoneOverviewMetricRow(rowEl, {
    device_id: deviceId,
    label: source.querySelector('[name="items[][label]"]')?.value || '',
    unit: source.querySelector('[name="items[][unit]"]')?.value || '',
    icon: source.querySelector('[name="items[][icon]"]')?.value || 'bi-dot'
  });

  if (typeof updatePreviewFromForm === 'function') {
    updatePreviewFromForm();
  }
}

function collectZoneOverviewConfig() {
  const form = document.getElementById('widget-config-form');
  const config = {
    zone_name: form.querySelector('#cfg-zone_name')?.value || '',
    zone_subtitle: form.querySelector('#cfg-zone_subtitle')?.value || '',
    link_url: form.querySelector('#cfg-link_url')?.value || '',
    label: form.querySelector('#cfg-zone_name')?.value || '',
    icon: form.querySelector('#cfg-icon')?.value || 'bi-building',
    show_primary: form.querySelector('#cfg-show_primary')?.value ?? '1',
    primary_label: form.querySelector('#cfg-primary_label')?.value || '',
    primary_unit: form.querySelector('#cfg-primary_unit')?.value || '',
    primary_device_id: form.querySelector('#cfg-primary_device_id')?.value || '',
    primary_metric_key: form.querySelector('#cfg-primary_metric_key')?.value || '',
    value_maps: (typeof collectValueMaps === 'function') ? collectValueMaps() : [],
    items: [],
    alarm_devices: []
  };

  form.querySelectorAll('#zone-overview-metric-list .series-item').forEach(item => {
    config.items.push({
      label: item.querySelector('[name="items[][label]"]')?.value || '',
      unit: item.querySelector('[name="items[][unit]"]')?.value || '',
      icon: item.querySelector('[name="items[][icon]"]')?.value || 'bi-dot',
      device_id: item.querySelector('[name="items[][device_id]"]')?.value || '',
      metric_key: item.querySelector('[name="items[][metric_key]"]')?.value || ''
    });
  });

  form.querySelectorAll('#zone-alarm-device-list .alarm-device-item').forEach(item => {
    const deviceId = item.querySelector('[name="alarm_devices[][device_id]"]')?.value || '';
    if (deviceId) {
      config.alarm_devices.push({ device_id: deviceId });
    }
  });

  return config;
}

async function populateZoneOverviewForm(dataset) {
  const cardIconMount = document.getElementById('zone-card-icon-mount');
  if (cardIconMount) {
    setZoneOverviewIconSelectValue(
      cardIconMount,
      dataset.icon || 'bi-building',
      { name: 'icon', id: 'cfg-icon', field: 'icon' }
    );
  } else {
    const iconEl = document.getElementById('cfg-icon');
    if (iconEl && dataset.icon) iconEl.value = dataset.icon;
  }

  const showPrimaryEl = document.getElementById('cfg-show_primary');
  if (showPrimaryEl) {
    showPrimaryEl.value = dataset.show_primary ?? '1';
    showPrimaryEl.dispatchEvent(new Event('change'));
  }

  const primaryDevice = document.getElementById('cfg-primary_device_id');
  const primaryMetric = document.getElementById('cfg-primary_metric_key');
  if (primaryDevice) {
    await loadDeviceOptions(primaryDevice, dataset.primary_device_id || '');
    primaryDevice.value = dataset.primary_device_id || '';
    if (dataset.primary_device_id) {
      await loadMetricOptions(dataset.primary_device_id, primaryMetric, dataset.primary_metric_key || '');
      if (primaryMetric) primaryMetric.value = dataset.primary_metric_key || '';
    }
  }

  const primaryLabel = document.getElementById('cfg-primary_label');
  const primaryUnit = document.getElementById('cfg-primary_unit');
  if (primaryLabel) primaryLabel.value = dataset.primary_label || '';
  if (primaryUnit) primaryUnit.value = dataset.primary_unit || '';

  let valueMaps = [];
  try {
    const rawMaps = dataset.value_maps || dataset.valueMaps || '[]';
    valueMaps = typeof rawMaps === 'string'
      ? JSON.parse(rawMaps || '[]')
      : (rawMaps || []);
  } catch (e) {
    valueMaps = [];
  }
  if (!valueMaps.length && dataset.config) {
    try {
      const cfg = typeof dataset.config === 'string' ? JSON.parse(dataset.config) : dataset.config;
      if (Array.isArray(cfg?.value_maps)) valueMaps = cfg.value_maps;
    } catch (e) {}
  }
  if (typeof renderValueMapsPanel === 'function') {
    renderValueMapsPanel(valueMaps, document.getElementById('zone-primary-value-maps') || document.getElementById('zone-primary-settings'));
  }

  let items = [];
  try {
    items = JSON.parse(dataset.items || '[]');
  } catch (e) {
    items = [];
  }

  for (const item of items) {
    await addZoneOverviewMetric(item);
  }

  let alarmDevices = [];
  try {
    alarmDevices = JSON.parse(dataset.alarm_devices || '[]');
  } catch (e) {
    alarmDevices = [];
  }
  if (!alarmDevices.length && dataset.config) {
    try {
      const cfg = JSON.parse(dataset.config);
      alarmDevices = cfg.alarm_devices || [];
    } catch (e) {}
  }
  for (const entry of alarmDevices) {
    await addZoneAlarmDevice(entry);
  }

  if (typeof updatePreviewFromForm === 'function') {
    updatePreviewFromForm();
  }
}

function updateZoneOverviewRows(deviceId, metricKey, value, timestamp, datatype) {
  const rows = document.querySelectorAll(
    `.zone-metric-row[data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );

  rows.forEach(row => {
    const valueCell = row.querySelector('.metric-value');
    const resolved = resolveDisplayValue(row, value, datatype);
    if (valueCell) valueCell.textContent = resolved.text;
  });

  const primaryEls = document.querySelectorAll(
    `.zone-primary-metric[data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );
  primaryEls.forEach(el => {
    const valueCell = el.querySelector('.value');
    const unit = el.dataset.unit || '';
    const resolved = resolveDisplayValue(el, value, datatype);
    if (valueCell) {
      valueCell.classList.remove('effect-spin', 'effect-pulse', 'effect-blink');
      if (resolved.mapped) {
        valueCell.textContent = resolved.text;
        valueCell.style.color = resolved.map?.icon_color || '';
        const effect = resolved.map?.icon_effect;
        if (effect && effect !== 'none') {
          valueCell.classList.add('effect-' + effect);
        }
      } else {
        valueCell.textContent = `${resolved.text}${unit ? unit : ''}`;
        valueCell.style.color = '';
      }
    }
  });
}

function updateZoneOverviewCardStatus(alarms = null) {
  const isActiveAlarm = (a) => a.active == 't' || a.active === true;
  const isUnacknowledged = (a) => a.acknowledged == 'f' || a.acknowledged === false;

  document.querySelectorAll('.widget-zone-overview').forEach(card => {
    let alarmCount = 0;

    if (alarms && Array.isArray(alarms)) {
      let deviceIds = [];
      try {
        deviceIds = JSON.parse(card.dataset.alarmDevices || '[]');
      } catch (e) {
        deviceIds = [];
      }

      const activeAlarms = alarms.filter(a => isActiveAlarm(a) && isUnacknowledged(a));

      if (deviceIds.length > 0) {
        alarmCount = activeAlarms.filter(a =>
          deviceIds.includes(String(a.device_id))
        ).length;
      } else {
        alarmCount = card.querySelectorAll('.zone-metric-row.widget-alarm, .zone-primary-metric.widget-alarm, .zone-alarm-tracker.widget-alarm').length;
      }
    } else {
      alarmCount = card.querySelectorAll('.zone-metric-row.widget-alarm, .zone-primary-metric.widget-alarm, .zone-alarm-tracker.widget-alarm').length;
    }

    card.classList.remove('zone-overview--normal', 'zone-overview--warning', 'zone-overview--critical');
    if (alarmCount > 0) {
      card.classList.add('zone-overview--critical');
    } else {
      card.classList.add('zone-overview--normal');
    }

    const countEl = card.querySelector('[data-alarm-count]');
    if (countEl) countEl.textContent = String(alarmCount);
  });
}

window.updateZoneOverviewCardStatus = updateZoneOverviewCardStatus;

function initZoneOverviewLinks() {
  document.querySelectorAll('.widget-zone-overview[data-link-url]').forEach(card => {
    if (card.dataset.linkBound === '1') return;
    card.dataset.linkBound = '1';

    const url = card.dataset.linkUrl;
    if (!url) return;

    card.classList.add('zone-overview--clickable');

    const navigate = () => {
      window.location.href = url;
    };

    card.addEventListener('click', navigate);
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        navigate();
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', initZoneOverviewLinks);

function appendZoneAlarmDeviceRow() {
  const tmpl = document.getElementById('zone-alarm-device-template');
  if (!tmpl) return null;

  const wrapper = document.createElement('div');
  wrapper.innerHTML = tmpl.innerHTML;
  const newItem = wrapper.firstElementChild;
  document.getElementById('zone-alarm-device-list').appendChild(newItem);
  bindZoneAlarmDeviceRow(newItem);
  return newItem;
}

function bindZoneAlarmDeviceRow(rowEl) {
  const deviceSelect = rowEl.querySelector('.device-select');
  if (deviceSelect && !deviceSelect.dataset.bound) {
    deviceSelect.dataset.bound = '1';
    deviceSelect.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  }
}

async function fillZoneAlarmDeviceRow(rowEl, entry = {}) {
  if (!rowEl) return;
  const deviceSelect = rowEl.querySelector('.device-select');
  if (!deviceSelect) return;
  await loadDeviceOptions(deviceSelect, entry.device_id || '');
  deviceSelect.value = entry.device_id || '';
}

async function addZoneAlarmDevice(prefill) {
  const rowEl = appendZoneAlarmDeviceRow();
  if (!rowEl) return;
  await fillZoneAlarmDeviceRow(rowEl, prefill || {});
  if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
}
