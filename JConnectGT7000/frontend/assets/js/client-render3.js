
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
    for (const m of maps) {
      if (m == null || m.label === undefined || m.label === null || m.label === '') continue;
      if (m.value === undefined || m.value === null || m.value === '') continue;
      const mapStr = String(m.value).trim();
      if (rawStr === mapStr) {
        return { text: String(m.label), mapped: true };
      }
      if (Number.isFinite(rawNum) && Number.isFinite(Number(m.value)) && rawNum === Number(m.value)) {
        return { text: String(m.label), mapped: true };
      }
    }
  }
  return { text: formatMetricValue(value, datatype), mapped: false };
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


function fetchValueBox(deviceId, metricKey) {
  fetch(`/device/metrics_latest?device_id=${deviceId}&metric_key=${metricKey}`)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
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
          applyValueBoxLink(el);
        });

        const mapView = document.querySelectorAll(".map-label[data-device-id='" + deviceId + "'][data-metric-key='" + metricKey + "']");
        mapView.forEach(el => {
          const valueEl = el.querySelector(".value");
          if (valueEl) valueEl.textContent = resolveDisplayValue(el, data.value, data.datatype).text;
        });

      }
    })
    .catch(err => console.warn('[fetchValueBox] error:', err));
}

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
    applyValueBoxLink(el);
  });


  const mapView = document.querySelectorAll(".map-label");
  mapView.forEach(el => {
    if (String(el.dataset.deviceId) === String(metric.device_id) && el.dataset.metricKey === metric.metric_key) {
      const valueEl = el.querySelector(".value");
      if (valueEl) valueEl.textContent = resolveDisplayValue(el, metric.value, metric.datatype).text;
    }
  });

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

    const metricArray = Array.from(metrics).map(k => {
      const [device_id, metric_key] = k.split("||");
      return { device_id: parseInt(device_id), metric_key };
    });

    if (metricArray.length) {
      socket.send(JSON.stringify({ type: 'subscribe', metrics: metricArray }));
      console.log(`[WS] Subscribed to`, metricArray);
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
