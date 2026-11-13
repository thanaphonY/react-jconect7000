const gaugeCharts = new WeakMap();
const DEFAULT_GAUGE_COLOR = '#22c55e';

function debounce(fn, delay = 100) {
  let timer;
  return function(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

function parseGaugeRange(el) {
  const min = parseFloat(el.dataset.min);
  const max = parseFloat(el.dataset.max);
  return {
    min: Number.isFinite(min) ? min : 0,
    max: Number.isFinite(max) ? max : 100
  };
}

function parseGaugeColorRanges(source) {
  if (!source) return [];

  let raw = source;
  if (source instanceof HTMLElement) {
    raw = source.dataset.colorRanges || '[]';
  } else if (typeof source === 'string') {
    raw = source;
  } else if (Array.isArray(source)) {
    return source
      .map(r => ({
        from: parseFloat(r.from),
        to: parseFloat(r.to),
        color: r.color || DEFAULT_GAUGE_COLOR
      }))
      .filter(r => Number.isFinite(r.from) && Number.isFinite(r.to) && r.to > r.from);
  }

  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map(r => ({
        from: parseFloat(r.from),
        to: parseFloat(r.to),
        color: r.color || DEFAULT_GAUGE_COLOR
      }))
      .filter(r => Number.isFinite(r.from) && Number.isFinite(r.to) && r.to > r.from)
      .sort((a, b) => a.from - b.from);
  } catch (e) {
    return [];
  }
}

function getGaugeBoundaryLabels(min, max, ranges) {
  const values = new Set([min, max]);
  if (!ranges.length) return [...values].sort((a, b) => a - b);

  const sorted = [...ranges].sort((a, b) => a.from - b.from);
  sorted.forEach((r, i) => {
    if (i > 0 && Number.isFinite(r.from)) values.add(r.from);
    if (i < sorted.length - 1 && Number.isFinite(r.to)) values.add(r.to);
  });

  return [...values].sort((a, b) => a - b);
}

function isDarkGaugeContainer(container) {
  if (document.documentElement.getAttribute('data-bs-theme') === 'dark') return true;
  return !!container.closest('.bg-dark, [data-bs-theme="dark"]');
}

function getGaugeMetrics(container, min = 0, max = 100) {
  const chartEl = container.querySelector('.gauge-chart');
  const width = chartEl?.clientWidth || container.clientWidth || 200;
  const height = chartEl?.clientHeight || container.clientHeight || 140;
  const size = Math.min(width, height);
  const dark = isDarkGaugeContainer(container);
  const isWide = width > height * 1.75;

  let centerY = isWide ? '90%' : '84%';
  if (height < 95) centerY = isWide ? '86%' : '80%';
  else if (height < 150) centerY = isWide ? '88%' : '82%';

  return {
    width,
    height,
    min,
    max,
    centerY,
    outerRadius: isWide ? '108%' : (width < 200 ? '94%' : '98%'),
    innerRadius: isWide ? '96%' : (width < 200 ? '84%' : '88%'),
    outerRingWidth: 3,
    progressWidth: Math.max(9, Math.min(16, Math.round(size * 0.06))),
    axisLabelFontSize: Math.max(8, Math.min(11, Math.round(size * 0.045))),
    axisLabelDistance: isWide ? 6 : 4,
    detailFontSize: Math.max(12, Math.min(19, Math.round(size * 0.078))),
    unitFontSize: Math.max(9, Math.min(13, Math.round(size * 0.05))),
    trackColor: dark ? '#3f4654' : '#cbd5e1',
    axisLabelColor: dark ? '#cbd5e1' : '#64748b',
    textColor: getComputedStyle(container).color || (dark ? '#f8fafc' : '#1e293b'),
    unit: container.dataset.unit || '',
    detailOffsetCenter: isWide ? '12%' : '18%'
  };
}

function formatGaugeAxisLabel(value, min, max) {
  const num = parseFloat(value);
  if (!Number.isFinite(num)) return '';

  const span = Math.abs(max - min) || 1;
  const rounded = Math.abs(num - Math.round(num)) < 0.01 ? Math.round(num) : num;

  if (span <= 10) {
    return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
  }

  return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(0);
}

function clampGaugeValue(value, min, max) {
  const num = parseFloat(value);
  if (!Number.isFinite(num)) return null;
  return Math.max(min, Math.min(max, num));
}

function formatGaugeValue(value) {
  const num = parseFloat(value);
  if (!Number.isFinite(num)) return '--';
  return Number.isInteger(num) ? String(num) : num.toFixed(2);
}

function getColorForValue(value, min, max, ranges) {
  const num = parseFloat(value);
  if (!Number.isFinite(num) || !ranges.length) return DEFAULT_GAUGE_COLOR;

  for (let i = 0; i < ranges.length; i++) {
    const r = ranges[i];
    const isLast = i === ranges.length - 1;
    if (num >= r.from && (isLast ? num <= r.to : num < r.to)) {
      return r.color;
    }
  }

  return DEFAULT_GAUGE_COLOR;
}

function buildAxisLineColors(min, max, ranges) {
  if (!ranges.length) return [[1, '#dee2e6']];

  const span = max - min || 1;
  const segments = [];

  ranges.forEach(r => {
    const endRatio = Math.max(0, Math.min(1, (r.to - min) / span));
    if (endRatio <= 0) return;
    if (segments.length && endRatio <= segments[segments.length - 1][0]) return;
    segments.push([endRatio, r.color]);
  });

  if (!segments.length) return [[1, '#dee2e6']];
  segments[segments.length - 1][0] = 1;
  return segments;
}

function buildGaugeDetailFormatter(display, metrics) {
  const unit = metrics.unit || '';
  const formatted = formatGaugeValue(display);
  if (formatted === '--') return '--';
  if (!unit) return `{val|${formatted}}`;
  if (unit === '%') return `{val|${formatted}${unit}}`;
  return `{val|${formatted}}{unit| ${unit}}`;
}

function buildGaugeOption(value, min, max, ranges, metrics) {
  const m = metrics || {};
  const clamped = clampGaugeValue(value, min, max);
  const display = clamped !== null ? clamped : min;
  const activeColor = getColorForValue(display, min, max, ranges);
  const boundaryLabels = getGaugeBoundaryLabels(min, max, ranges);
  const tolerance = Math.abs(max - min) * 0.005 || 0.5;

  return {
    animation: true,
    animationDuration: 400,
    series: [
      {
        type: 'gauge',
        min,
        max,
        startAngle: 180,
        endAngle: 0,
        radius: m.outerRadius || '90%',
        center: ['50%', m.centerY || '66%'],
        pointer: { show: false },
        progress: { show: false },
        axisLine: {
          lineStyle: {
            width: m.outerRingWidth || 3,
            color: buildAxisLineColors(min, max, ranges)
          }
        },
        axisTick: { show: false },
        splitLine: { show: false },
        axisLabel: {
          show: true,
          distance: m.axisLabelDistance || 2,
          fontSize: m.axisLabelFontSize || 10,
          color: m.axisLabelColor || '#94a3b8',
          hideOverlap: true,
          formatter: (val) => {
            const num = parseFloat(val);
            const match = boundaryLabels.find(v => Math.abs(v - num) <= tolerance);
            return match !== undefined ? formatGaugeAxisLabel(match, min, max) : '';
          }
        },
        detail: { show: false },
        data: [{ value: display }]
      },
      {
        type: 'gauge',
        min,
        max,
        startAngle: 180,
        endAngle: 0,
        radius: m.innerRadius || '82%',
        center: ['50%', m.centerY || '66%'],
        pointer: { show: false },
        anchor: { show: false },
        progress: {
          show: true,
          width: m.progressWidth || 12,
          roundCap: true,
          itemStyle: { color: activeColor }
        },
        axisLine: {
          roundCap: true,
          lineStyle: {
            width: m.progressWidth || 12,
            color: [[1, m.trackColor || '#3f4654']]
          }
        },
        axisTick: { show: false },
        splitLine: { show: false },
        axisLabel: { show: false },
        detail: {
          show: true,
          offsetCenter: [0, m.detailOffsetCenter || '18%'],
          formatter: () => buildGaugeDetailFormatter(display, m),
          rich: {
            val: {
              fontSize: m.detailFontSize || 16,
              fontWeight: 600,
              color: m.textColor || '#f8fafc',
              lineHeight: m.detailFontSize || 16
            },
            unit: {
              fontSize: m.unitFontSize || 11,
              fontWeight: 400,
              color: m.axisLabelColor || '#94a3b8',
              padding: [0, 0, 0, 1]
            }
          },
          valueAnimation: true
        },
        data: [{ value: display }]
      }
    ]
  };
}

function resizeGaugeChart(container) {
  const chartEl = container.querySelector('.gauge-chart');
  const chart = gaugeCharts.get(container);
  if (!chart || !chartEl) return;

  if (chartEl.clientWidth > 0 && chartEl.clientHeight > 0) {
    chart.resize({
      width: chartEl.clientWidth,
      height: chartEl.clientHeight
    });
  } else {
    chart.resize();
  }

  if (container._gaugeLastValue !== undefined) {
    const { min, max } = parseGaugeRange(container);
    const ranges = parseGaugeColorRanges(container);
    chart.setOption(
      buildGaugeOption(container._gaugeLastValue, min, max, ranges, getGaugeMetrics(container, min, max)),
      true
    );
  }
}

function waitForGaugeLayout(container, callback, attempts = 20) {
  const chartEl = container.querySelector('.gauge-chart');
  if (chartEl && chartEl.clientWidth > 0 && chartEl.clientHeight > 0) {
    callback();
    return;
  }
  if (attempts <= 0) {
    callback();
    return;
  }
  requestAnimationFrame(() => waitForGaugeLayout(container, callback, attempts - 1));
}

function bindGaugeResize(container) {
  if (container._gaugeResizeBound) return;
  container._gaugeResizeBound = true;

  const debouncedResize = debounce(() => resizeGaugeChart(container), 80);
  const ro = new ResizeObserver(debouncedResize);

  ro.observe(container);

  const chartEl = container.querySelector('.gauge-chart');
  if (chartEl) ro.observe(chartEl);

  const gridItem = container.closest('.grid-stack-item');
  if (gridItem) ro.observe(gridItem);

  window.addEventListener('resize', debouncedResize);
  container._gaugeResizeHandler = debouncedResize;
}

function ensureGaugeChart(container) {
  const chartEl = container.querySelector('.gauge-chart');
  if (!chartEl || !window.echarts) return null;

  let chart = gaugeCharts.get(container);
  if (!chart) {
    chart = echarts.init(chartEl, null, {
      renderer: 'canvas',
      devicePixelRatio: Math.min(window.devicePixelRatio || 1, 2)
    });
    gaugeCharts.set(container, chart);
    bindGaugeResize(container);
  }

  return chart;
}

function updateGaugeDisplay(container, value) {
  container._gaugeLastValue = value;

  const { min, max } = parseGaugeRange(container);
  const ranges = parseGaugeColorRanges(container);
  const metrics = getGaugeMetrics(container, min, max);
  const chart = ensureGaugeChart(container);

  if (chart) {
    chart.setOption(buildGaugeOption(value, min, max, ranges, metrics), true);
  }
}

function fetchGaugeValue(container) {
  const deviceId = container.dataset.deviceId;
  const metricKey = container.dataset.metricKey;
  if (!deviceId || !metricKey) return;

  fetch(`/device/metrics_latest?device_id=${deviceId}&metric_key=${metricKey}`)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        updateGaugeDisplay(container, data.value);
      }
    })
    .catch(err => console.warn('[gauge] fetch error:', err));
}

window.renderGaugeWidget = function(target, arg2) {
  const el = typeof target === 'string' ? document.getElementById(target) : target;
  if (!el) return;

  if (typeof arg2 === 'object' && arg2 !== null) {
    if (arg2.min !== undefined && arg2.min !== '') el.dataset.min = arg2.min;
    if (arg2.max !== undefined && arg2.max !== '') el.dataset.max = arg2.max;
    if (arg2.unit !== undefined) el.dataset.unit = arg2.unit;
    if (arg2.device_id !== undefined) el.dataset.deviceId = arg2.device_id;
    if (arg2.metric_key !== undefined) el.dataset.metricKey = arg2.metric_key;
    if (arg2.color_ranges !== undefined) {
      el.dataset.colorRanges = JSON.stringify(arg2.color_ranges || []);
    }

    ensureGaugeChart(el);
    waitForGaugeLayout(el, () => {
      resizeGaugeChart(el);
      const { min } = parseGaugeRange(el);
      updateGaugeDisplay(el, el._gaugeLastValue ?? min);
      fetchGaugeValue(el);
    });
    return;
  }

  updateGaugeDisplay(el, arg2);
};

window.resizeAllGaugeWidgets = function() {
  document.querySelectorAll('.widget-gauge[data-device-id][data-metric-key]').forEach(el => {
    resizeGaugeChart(el);
  });
};

function renderGaugePreview(container, config) {
  const min = parseFloat(config.min);
  const max = parseFloat(config.max);
  const safeMin = Number.isFinite(min) ? min : 0;
  const safeMax = Number.isFinite(max) ? max : 100;
  const ranges = typeof collectGaugeColorRanges === 'function'
    ? collectGaugeColorRanges()
    : parseGaugeColorRanges(config.color_ranges || []);
  const sample = safeMin + (safeMax - safeMin) * 0.72;
  const showBorder = document.getElementById('cfg-show_border')?.value ?? config.show_border ?? '1';
  const borderClass = showBorder === '0' ? 'border-0' : 'border rounded shadow-sm';

  container.innerHTML = `
    <div class="widget-gauge ${borderClass} p-2 text-center bg-body text-body" style="height: 180px; display:flex; flex-direction:column; gap: 0.1rem;">
      <div class="small text-secondary gauge-label">${config.label || 'Gauge'}</div>
      <div class="gauge-chart flex-grow-1" style="min-height: 0; width: 100%; height: 100%;"></div>
    </div>
  `;

  const previewRoot = container.firstElementChild;
  previewRoot.dataset.min = safeMin;
  previewRoot.dataset.max = safeMax;
  previewRoot.dataset.unit = config.unit || '';
  previewRoot.dataset.colorRanges = JSON.stringify(ranges);
  updateGaugeDisplay(previewRoot, sample);
}

function addGaugeColorRangeRow(from = '', to = '', color = '#28a745', listEl = null) {
  const list = listEl || document.getElementById('gauge-color-ranges-list');
  if (!list) return;

  const row = document.createElement('div');
  row.className = 'gauge-color-range-item border border-secondary rounded p-2 mb-2';
  row.innerHTML = `
    <div class="row g-2 align-items-end">
      <div class="col">
        <label class="form-label small mb-1">From</label>
        <input type="number" step="any" name="color_ranges[][from]" class="form-control form-control-sm" placeholder="e.g. 0" value="${from}">
      </div>
      <div class="col">
        <label class="form-label small mb-1">To</label>
        <input type="number" step="any" name="color_ranges[][to]" class="form-control form-control-sm" placeholder="e.g. 50" value="${to}">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-1">Color</label>
        <input type="color" name="color_ranges[][color]" class="form-control form-control-sm form-control-color" value="${color || '#28a745'}">
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

  row.querySelectorAll('input').forEach(input => {
    input.addEventListener('input', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  });

  list.appendChild(row);
}

function renderGaugeColorRangesPanel(ranges = []) {
  const panel = document.createElement('div');
  panel.className = 'col-12 mt-2';
  panel.innerHTML = `
    <hr class="border-secondary">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <label class="form-label small mb-0">Color Ranges</label>
      <button type="button" class="btn btn-outline-light btn-sm" id="btn-add-gauge-color-range">+ Add Range</button>
    </div>
    <div class="text-secondary small mb-2">Set color by value range (From – To).</div>
    <div id="gauge-color-ranges-list"></div>
  `;
  document.getElementById('widget-config-form').appendChild(panel);

  const list = panel.querySelector('#gauge-color-ranges-list');
  let initial = ranges.length ? ranges : null;

  if (!initial) {
    const min = parseFloat(document.getElementById('cfg-min')?.value);
    const max = parseFloat(document.getElementById('cfg-max')?.value);
    const safeMin = Number.isFinite(min) ? min : 0;
    const safeMax = Number.isFinite(max) ? max : 100;
    const span = safeMax - safeMin || 100;
    initial = [
      { from: safeMin, to: safeMin + span * 0.5, color: '#28a745' },
      { from: safeMin + span * 0.5, to: safeMin + span * 0.8, color: '#ffc107' },
      { from: safeMin + span * 0.8, to: safeMax, color: '#dc3545' }
    ];
  }

  initial.forEach(r => addGaugeColorRangeRow(r.from ?? '', r.to ?? '', r.color ?? '#28a745', list));

  panel.querySelector('#btn-add-gauge-color-range')?.addEventListener('click', () => {
    addGaugeColorRangeRow('', '', '#0d6efd', list);
  });
}

function collectGaugeColorRanges() {
  const rows = [];
  document.querySelectorAll('#gauge-color-ranges-list .gauge-color-range-item').forEach(item => {
    const from = item.querySelector('[name="color_ranges[][from]"]')?.value ?? '';
    const to = item.querySelector('[name="color_ranges[][to]"]')?.value ?? '';
    const color = item.querySelector('[name="color_ranges[][color]"]')?.value ?? '#0d6efd';
    if (from === '' && to === '') return;
    rows.push({ from, to, color });
  });
  return rows;
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.widget-gauge[data-device-id][data-metric-key]').forEach(el => {
    renderGaugeWidget(el, {
      device_id: el.dataset.deviceId,
      metric_key: el.dataset.metricKey,
      min: el.dataset.min ?? 0,
      max: el.dataset.max ?? 100,
      unit: el.dataset.unit ?? '',
      color_ranges: parseGaugeColorRanges(el)
    });
  });

  window.addEventListener('load', () => {
    setTimeout(() => window.resizeAllGaugeWidgets?.(), 150);
  });
});
