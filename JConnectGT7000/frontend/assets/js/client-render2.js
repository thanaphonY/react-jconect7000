// ช่วย format timestamp ให้สวย
function formatTimestamp(ts) {
  const d = new Date(ts);

  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0'); // เดือนเริ่มจาก 0
  const year = d.getFullYear();

  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');

  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function coerceBool(value) {
  if (typeof value === 'boolean') return value;
  if (value === 1 || value === '1' || value === 't' || value === 'true' || value === 'TRUE' || value === 'True') return true;
  if (value === 0 || value === '0' || value === 'f' || value === 'false' || value === 'FALSE' || value === 'False') return false;
  return null;
}

function formatMetricValue(value, datatype) {
  if (value === null || value === undefined || value === '') return '--';

  if (datatype === 'boolean' || datatype === 'bool') {
    const b = coerceBool(value);
    if (b === null) return String(value);
    return b ? 'True' : 'False';
  }
  if (datatype === 'string') {
    return String(value);
  }
  if (datatype === 'int') {
    const n = parseInt(value, 10);
    return Number.isNaN(n) ? String(value) : String(n);
  }

  // Infer boolean-like values when datatype is missing (e.g. Connection)
  const inferred = coerceBool(value);
  if (inferred !== null && typeof value === 'boolean') {
    return inferred ? 'True' : 'False';
  }

  const num = Number(value);
  if (!Number.isFinite(num)) return String(value);
  return num.toFixed(2);
}

function parseValueMaps(el) {
  const raw = el?.dataset?.valueMaps || el?.dataset?.value_maps || '[]';
  try {
    const maps = typeof raw === 'string' ? JSON.parse(raw) : raw;
    return Array.isArray(maps) ? maps : [];
  } catch (e) {
    return [];
  }
}

function resolveDisplayValue(el, value, datatype) {
  const maps = parseValueMaps(el);
  if (maps.length) {
    const rawStr = String(value).trim();
    const rawNum = Number(value);
    const rawBool = coerceBool(value);
    for (const m of maps) {
      if (m == null || m.label === undefined || m.label === null || m.label === '') continue;
      if (m.value === undefined || m.value === null || m.value === '') continue;
      const mapStr = String(m.value).trim();
      if (rawStr === mapStr) {
        return { text: String(m.label), mapped: true, map: m };
      }
      if (Number.isFinite(rawNum) && Number.isFinite(Number(m.value)) && rawNum === Number(m.value)) {
        return { text: String(m.label), mapped: true, map: m };
      }
      const mapBool = coerceBool(m.value);
      if (rawBool !== null && mapBool !== null && rawBool === mapBool) {
        return { text: String(m.label), mapped: true, map: m };
      }
    }
  }
  return { text: formatMetricValue(value, datatype), mapped: false, map: null };
}

function updateMapLabels(deviceId, metricKey, value, datatype) {
  const mapView = document.querySelectorAll(
    `.map-label[data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );

  mapView.forEach(el => {
    if (el.dataset.show === 'point') return;

    const resolved = resolveDisplayValue(el, value, datatype);
    const val = resolved.text;
    const valueEl = el.querySelector('.value');
    const unitEl = el.querySelector('.unit');

    if (valueEl) {
      valueEl.textContent = val;
      return;
    }

    const textEl = el.querySelector('text');
    if (!textEl) return;

    const show = el.dataset.show || 'label+value';
    const label = (el.dataset.label || textEl.textContent.split(':')[0] || '').trim();
    const unit = (el.dataset.unit || unitEl?.textContent || '').trim();
    let display = '';

    if (show === 'value') {
      display = resolved.mapped ? val : `${val}${unit ? ` ${unit}` : ''}`;
    } else if (show === 'label') {
      display = label;
    } else if (show === 'label+value') {
      display = `${label}: ${val}`;
    } else if (show === 'value+unit') {
      display = resolved.mapped ? val : `${val}${unit ? ` ${unit}` : ''}`;
    } else {
      display = resolved.mapped ? `${label}: ${val}` : `${label}: ${val}${unit ? ` ${unit}` : ''}`;
    }

    textEl.textContent = display;
  });
}

function applyValueBoxIcon(el, value) {
  if (!el) return;
  const showIcon = el.dataset.showIcon === '1';
  const resolved = resolveDisplayValue(el, value, null);
  const map = resolved.map;

  const valueEl = el.querySelector('.value');
  if (valueEl && resolved.mapped && map?.icon_color) {
    valueEl.style.color = map.icon_color;
  } else if (valueEl) {
    valueEl.style.color = '';
  }

  if (!showIcon) return;

  let iconEl = el.querySelector('.widget-value-box-icon');
  if (!iconEl) {
    iconEl = document.createElement('div');
    iconEl.className = 'widget-value-box-icon bi';
    iconEl.setAttribute('aria-hidden', 'true');
    el.insertBefore(iconEl, el.firstChild);
    el.classList.add('has-icon');
  }

  const iconClass = (el.dataset.icon || 'bi-circle').replace(/^bi-?/, '');
  iconEl.className = 'widget-value-box-icon bi bi-' + iconClass;

  iconEl.style.color = map?.icon_color || '';
  iconEl.classList.remove('effect-spin', 'effect-pulse', 'effect-blink');
  const effect = map?.icon_effect;
  if (effect && effect !== 'none') {
    iconEl.classList.add('effect-' + effect);
  }
}

function applyValueBoxLink(el) {
  if (!el) return;
  const url = (el.dataset.linkUrl || '').trim();
  const isClickable = url !== '';
  el.classList.toggle('is-clickable', isClickable);

  if (!isClickable) {
    el.removeAttribute('role');
    el.removeAttribute('tabindex');
    if (el._valueBoxLinkHandler) {
      el.removeEventListener('click', el._valueBoxLinkHandler);
      el.removeEventListener('keydown', el._valueBoxLinkKeyHandler);
      el._valueBoxLinkHandler = null;
      el._valueBoxLinkKeyHandler = null;
    }
    return;
  }

  el.setAttribute('role', 'link');
  el.setAttribute('tabindex', '0');

  if (!el._valueBoxLinkHandler) {
    el._valueBoxLinkHandler = () => window.location.href = el.dataset.linkUrl;
    el._valueBoxLinkKeyHandler = (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        window.location.href = el.dataset.linkUrl;
      }
    };
    el.addEventListener('click', el._valueBoxLinkHandler);
    el.addEventListener('keydown', el._valueBoxLinkKeyHandler);
  }
}

// โหลดค่าครั้งแรก
function updateTableRows(deviceId, metricKey, value, timestamp, datatype) {
  const rows = document.querySelectorAll(
    `.widget-table-row[data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );

  rows.forEach(row => {
    const valueCell = row.querySelector('.metric-value');
    const timeCell = row.querySelector('.metric-time');
    const resolved = resolveDisplayValue(row, value, datatype);

    if (valueCell) valueCell.textContent = resolved.text;
    if (timeCell && timestamp) timeCell.textContent = formatTimestamp(timestamp);
  });
}

function fetchValueBox(deviceId, metricKey) {
  fetch(`/device/metrics_latest?device_id=${deviceId}&metric_key=${metricKey}`)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        // ใช้ class selector แทน id
        const selector = `.valuebox-${deviceId}-${metricKey}`;
        const elements = document.querySelectorAll(selector);

        elements.forEach(el => {
          const v = el.querySelector('.value');
          const t = el.querySelector('.time');
          const u = el.dataset.unit || '';
          const resolved = resolveDisplayValue(el, data.value, data.datatype);

          if (v) {
            if (resolved.mapped) {
              v.innerHTML = resolved.text;
            } else {
              v.innerHTML = `${resolved.text}${u ? ` <span class="unit">${u}</span>` : ''}`;
            }
          }
          if (t) t.innerHTML = formatTimestamp(data.timestamp);
          applyValueBoxIcon(el, data.value);
          applyValueBoxLink(el);
        });

        updateMapLabels(deviceId, metricKey, data.value, data.datatype);

        updateTableRows(deviceId, metricKey, data.value, data.timestamp, data.datatype);
        if (typeof updateStatusGridCells === 'function') {
          updateStatusGridCells(deviceId, metricKey, data.value, data.timestamp, data.datatype);
        }
        if (typeof updateUpsPanelCells === 'function') {
          updateUpsPanelCells(deviceId, metricKey, data.value, data.datatype);
        }
        if (typeof updatePowerMeterCells === 'function') {
          updatePowerMeterCells(deviceId, metricKey, data.value, data.datatype);
        }
        if (typeof updateHeatmapCells === 'function') {
          updateHeatmapCells(deviceId, metricKey, data.value, data.datatype);
        }
        if (typeof updateCoolingPanelCells === 'function') {
          updateCoolingPanelCells(deviceId, metricKey, data.value, data.datatype);
        }
        if (typeof updateZoneOverviewRows === 'function') {
          updateZoneOverviewRows(deviceId, metricKey, data.value, data.timestamp, data.datatype);
        }
        if (window.applySvgWidgetMetric) {
          window.applySvgWidgetMetric(deviceId, metricKey, data.value);
        }

      }
    })
    .catch(err => console.warn('[fetchValueBox] error:', err));
}

// Render ค่า real-time ที่ได้จาก WS
function renderMetric(metric) {
  // ใช้ class แทน id
  const selector = `.valuebox-${metric.device_id}-${metric.metric_key}`;
  const elements = document.querySelectorAll(selector);

  elements.forEach(el => {
    const v = el.querySelector('.value');
    const t = el.querySelector('.time');
    const u = el.dataset.unit || '';
    const resolved = resolveDisplayValue(el, metric.value, metric.datatype);

    if (v) {
      if (resolved.mapped) {
        v.innerHTML = resolved.text;
      } else {
        v.innerHTML = `${resolved.text}${u ? ` <span class="unit">${u}</span>` : ''}`;
      }
    }
    if (t && metric.timestamp) t.innerHTML = formatTimestamp(metric.timestamp);
    applyValueBoxIcon(el, metric.value);
    applyValueBoxLink(el);
  });

  updateTableRows(metric.device_id, metric.metric_key, metric.value, metric.timestamp, metric.datatype);
  if (typeof updateStatusGridCells === 'function') {
    updateStatusGridCells(metric.device_id, metric.metric_key, metric.value, metric.timestamp, metric.datatype);
  }
  if (typeof updateUpsPanelCells === 'function') {
    updateUpsPanelCells(metric.device_id, metric.metric_key, metric.value, metric.datatype);
  }
  if (typeof updatePowerMeterCells === 'function') {
    updatePowerMeterCells(metric.device_id, metric.metric_key, metric.value, metric.datatype);
  }
  if (typeof updateHeatmapCells === 'function') {
    updateHeatmapCells(metric.device_id, metric.metric_key, metric.value, metric.datatype);
  }
  if (typeof updateCoolingPanelCells === 'function') {
    updateCoolingPanelCells(metric.device_id, metric.metric_key, metric.value, metric.datatype);
  }
  if (typeof updateZoneOverviewRows === 'function') {
    updateZoneOverviewRows(metric.device_id, metric.metric_key, metric.value, metric.timestamp, metric.datatype);
  }
  if (window.applySvgWidgetMetric) {
    window.applySvgWidgetMetric(metric.device_id, metric.metric_key, metric.value);
  }

  // Map

  /*
  <g class="map-label" data-device-id="541" data-metric-key="mode" data-show="label+value" transform="translate(201.328125, 240.3725)" style="cursor: pointer;" onclick="window.open('/device/view/541', '_blank')">
    <rect x="0" y="0" width="100" height="28" rx="6" ry="6" fill="#222" opacity="0.85" class="svgalarm_541_mode"></rect>
      <svg width="100px" height="28px">
      <text x="50%" y="50%" alignment-baseline="middle" text-anchor="middle" fill="#fff" font-size="14">New Label: 0.00</text>
    </svg>
  </g>

*/
  updateMapLabels(metric.device_id, metric.metric_key, metric.value, metric.datatype);

  // Gauge 
  const gaugeSelector = `.gauge-${metric.device_id}-${metric.metric_key}`;
  const gaugeEls = document.querySelectorAll(gaugeSelector);

  gaugeEls.forEach(gaugeEl => {
    if (window.renderGaugeWidget) {
      window.renderGaugeWidget(gaugeEl, metric.value);
    }
  });

  const levelBarSelector = `.level-bar-${metric.device_id}-${metric.metric_key}`;
  const levelBarEls = document.querySelectorAll(levelBarSelector);

  levelBarEls.forEach(levelBarEl => {
    if (window.renderLevelBarWidget) {
      window.renderLevelBarWidget(levelBarEl, metric.value);
    }
  });
}

// เชื่อม WebSocket และ subscribe metrics

/*
<g id="mapview-68a044cd00b67-labels">
              <g class="map-label" data-device-id="541" data-metric-key="mode" data-show="label+value" transform="translate(201.328125, 240.3725)" style="cursor: pointer;" onclick="window.open('/device/view/541', '_blank')">
            <rect x="0" y="0" width="100" height="28" rx="6" ry="6" fill="#222" opacity="0.85" class="svgalarm_541_mode"></rect>
            <svg width="100px" height="28px">
              <text x="50%" y="50%" alignment-baseline="middle" text-anchor="middle" fill="#fff" font-size="14">Test: 0.00</text>
            </svg>
          </g>

    
          <g class="map-label" data-device-id="541" data-metric-key="DO1" data-show="point" transform="translate(417.37926136364, 303.535)" style="cursor: pointer;" onclick="window.open('/device/view/541', '_blank')">
            <circle r="5" cx="50" cy="50" fill="green" opacity="1.0" class="svgalarm_541_DO1 svg-alarm"></circle>
          </g>
          </g>



*/
function initWebSocket() {
  const protocol = window.location.protocol === "https:" ? "wss" : "ws";
  const socket = new WebSocket(`${protocol}://${window.location.host}/ws`);

  socket.onopen = () => {
    const widgets = document.querySelectorAll('[data-device-id][data-metric-key]');
    const metrics = new Set();

    widgets.forEach(el => {
      const deviceId = el.dataset.deviceId;
      const metricKey = el.dataset.metricKey;
      const key = `${deviceId}||${metricKey}`;
      metrics.add(key);

      // preload ครั้งแรก
      // ตรวจสอบว่ามี data-show ไหม ถ้ามีและเป็น point ไม่ต้องดึงค่า
      if (!el.dataset.show || el.dataset.show !== "point") {
        fetchValueBox(deviceId, metricKey);
      }
    });

    document.querySelectorAll('.status-grid-cell[data-device-id][data-metric-key]').forEach((el) => {
      const deviceId = el.dataset.deviceId;
      const metricKey = el.dataset.metricKey;
      metrics.add(`${deviceId}||${metricKey}`);
    });

    const metricArray = Array.from(metrics).map(k => {
      const [device_id, metric_key] = k.split("||");
      return { device_id: parseInt(device_id), metric_key };
    });

    if (metricArray.length) {
      socket.send(JSON.stringify({ type: 'subscribe', metrics: metricArray }));
    }
  };

  socket.onmessage = (e) => {
    let batch = [];
    try {
      batch = JSON.parse(e.data);
      if (Array.isArray(batch)) {
        batch.forEach((metric) => {
          if (metric.device_id && metric.metric_key) {
            metric.device_id = parseInt(metric.device_id);
            metric.timestamp = metric.timestamp ? new Date(metric.timestamp) : metric.timestamp;
          }
        });
      }
    } catch (err) {
      console.warn("[WS] parse error:", err);
      return;
    }

    batch.forEach(renderMetric);
  };

  socket.onclose = () => {
    console.warn('[WS] Connection closed. Try reconnecting...');
    setTimeout(initWebSocket, 2000);
  };
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('.widget-value-box').forEach(applyValueBoxLink);
  initWebSocket();
});
