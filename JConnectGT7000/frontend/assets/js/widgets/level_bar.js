const DEFAULT_LEVEL_BAR_COLOR = '#0d6efd';

function ensureLevelBarStyles() {
  if (document.getElementById('level-bar-widget-styles')) return;
  const s = document.createElement('style');
  s.id = 'level-bar-widget-styles';
  s.textContent = `
    .widget-level-bar {
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      width: 100%;
      height: 100%;
      min-height: 0;
      padding: 0.25rem 0.4rem;
      gap: 0.2rem;
    }
    .widget-level-bar .level-bar-label {
      font-size: clamp(0.65rem, 2.2cqi, 0.875rem);
      line-height: 1.15;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 0 0 auto;
    }
    .widget-level-bar .level-bar-value {
      font-size: clamp(0.8rem, 3cqi, 1.05rem);
      font-weight: 700;
      line-height: 1.2;
      flex: 0 0 auto;
      text-align: center;
    }
    .widget-level-bar .level-bar-value .unit {
      font-size: 0.75em;
      font-weight: 500;
      opacity: 0.75;
      margin-left: 0.2rem;
    }
    .widget-level-bar .level-bar-body {
      flex: 1 1 auto;
      min-height: 0;
      display: flex;
      gap: 0.4rem;
    }
    .widget-level-bar.is-vertical .level-bar-body {
      flex-direction: row;
      align-items: stretch;
    }
    .widget-level-bar.is-horizontal .level-bar-body {
      flex-direction: column;
    }
    .widget-level-bar .level-bar-track {
      position: relative;
      flex: 1 1 auto;
      min-width: 1.15rem;
      min-height: 1.15rem;
      border-radius: 8px;
      overflow: hidden;
      background: color-mix(in srgb, var(--bs-secondary-color, #6c757d) 18%, transparent);
      border: 1px solid color-mix(in srgb, var(--bs-border-color, #adb5bd) 80%, transparent);
      box-shadow: inset 0 1px 3px rgba(0,0,0,.18);
    }
    .widget-level-bar .level-bar-fill {
      position: absolute;
      background: ${DEFAULT_LEVEL_BAR_COLOR};
      transition: height .35s ease, width .35s ease, background-color .25s ease;
    }
    .widget-level-bar.is-vertical .level-bar-fill {
      left: 0;
      right: 0;
      bottom: 0;
      height: 0%;
    }
    .widget-level-bar.is-horizontal .level-bar-fill {
      top: 0;
      bottom: 0;
      left: 0;
      width: 0%;
    }
    .widget-level-bar .level-bar-ticks {
      display: flex;
      justify-content: space-between;
      color: var(--bs-secondary-color, #6c757d);
      font-size: 0.68rem;
      line-height: 1;
      flex: 0 0 auto;
      user-select: none;
    }
    .widget-level-bar.is-vertical .level-bar-ticks {
      flex-direction: column-reverse;
      min-width: 2.1rem;
      text-align: right;
      padding: 0.1rem 0;
    }
    .widget-level-bar.is-horizontal .level-bar-ticks {
      flex-direction: row;
      min-height: 0.85rem;
      width: 100%;
    }
  `;
  document.head.appendChild(s);
}

function parseLevelBarRange(el) {
  const min = parseFloat(el.dataset.min);
  const max = parseFloat(el.dataset.max);
  return {
    min: Number.isFinite(min) ? min : 0,
    max: Number.isFinite(max) ? max : 100
  };
}

function parseLevelBarColorRanges(source) {
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
        color: r.color || DEFAULT_LEVEL_BAR_COLOR
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
        color: r.color || DEFAULT_LEVEL_BAR_COLOR
      }))
      .filter(r => Number.isFinite(r.from) && Number.isFinite(r.to) && r.to > r.from)
      .sort((a, b) => a.from - b.from);
  } catch (e) {
    return [];
  }
}

function getLevelBarColor(value, ranges) {
  const num = Number(value);
  if (!Number.isFinite(num) || !ranges.length) return DEFAULT_LEVEL_BAR_COLOR;
  for (const r of ranges) {
    if (num >= r.from && num <= r.to) return r.color || DEFAULT_LEVEL_BAR_COLOR;
  }
  if (num < ranges[0].from) return ranges[0].color || DEFAULT_LEVEL_BAR_COLOR;
  return ranges[ranges.length - 1].color || DEFAULT_LEVEL_BAR_COLOR;
}

function formatLevelBarTick(n) {
  if (!Number.isFinite(n)) return '';
  if (Math.abs(n) >= 100 || Number.isInteger(n)) return String(Math.round(n));
  return n.toFixed(1);
}

function formatLevelBarValue(value) {
  if (value === null || value === undefined || value === '') return '--';
  const num = Number(value);
  if (!Number.isFinite(num)) return String(value);
  return Number.isInteger(num) ? String(num) : num.toFixed(2);
}

function buildLevelBarTicks(min, max) {
  const ticks = [];
  for (let i = 0; i < 5; i++) {
    ticks.push(min + ((max - min) * i) / 4);
  }
  return ticks;
}

function percentFromRange(value, min, max) {
  const num = Number(value);
  if (!Number.isFinite(num) || max === min) return 0;
  return Math.max(0, Math.min(100, ((num - min) / (max - min)) * 100));
}

function updateLevelBarDisplay(container, value) {
  container._levelBarLastValue = value;
  const { min, max } = parseLevelBarRange(container);
  const ranges = parseLevelBarColorRanges(container);
  const pct = percentFromRange(value, min, max);
  const color = getLevelBarColor(value, ranges);
  const unit = container.dataset.unit || '';
  const orientation = container.dataset.orientation === 'horizontal' ? 'horizontal' : 'vertical';

  container.classList.toggle('is-horizontal', orientation === 'horizontal');
  container.classList.toggle('is-vertical', orientation !== 'horizontal');

  const fill = container.querySelector('.level-bar-fill');
  if (fill) {
    fill.style.backgroundColor = color;
    if (orientation === 'horizontal') {
      fill.style.width = pct + '%';
      fill.style.height = '100%';
    } else {
      fill.style.height = pct + '%';
      fill.style.width = '100%';
    }
  }

  const valueEl = container.querySelector('.level-bar-value');
  if (valueEl) {
    const unitHtml = unit ? ` <span class="unit">${unit}</span>` : '';
    valueEl.innerHTML = `${formatLevelBarValue(value)}${unitHtml}`;
  }

  const ticksEl = container.querySelector('.level-bar-ticks');
  if (ticksEl) {
    ticksEl.innerHTML = buildLevelBarTicks(min, max)
      .map(t => `<span>${formatLevelBarTick(t)}</span>`)
      .join('');
  }
}

function fetchLevelBarValue(container) {
  const deviceId = container.dataset.deviceId;
  const metricKey = container.dataset.metricKey;
  if (!deviceId || !metricKey) return;

  fetch(`/device/metrics_latest?device_id=${deviceId}&metric_key=${metricKey}`)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success' || data.value !== undefined) {
        updateLevelBarDisplay(container, data.value);
      }
    })
    .catch(err => console.warn('[level_bar] fetch error:', err));
}

window.renderLevelBarWidget = function(target, arg2) {
  const el = typeof target === 'string' ? document.getElementById(target) : target;
  if (!el) return;
  ensureLevelBarStyles();

  if (typeof arg2 === 'object' && arg2 !== null) {
    if (arg2.min !== undefined && arg2.min !== '') el.dataset.min = arg2.min;
    if (arg2.max !== undefined && arg2.max !== '') el.dataset.max = arg2.max;
    if (arg2.unit !== undefined) el.dataset.unit = arg2.unit;
    if (arg2.orientation !== undefined) el.dataset.orientation = arg2.orientation;
    if (arg2.device_id !== undefined) el.dataset.deviceId = arg2.device_id;
    if (arg2.metric_key !== undefined) el.dataset.metricKey = arg2.metric_key;
    if (arg2.color_ranges !== undefined) {
      el.dataset.colorRanges = JSON.stringify(arg2.color_ranges || []);
    }
    const { min } = parseLevelBarRange(el);
    updateLevelBarDisplay(el, el._levelBarLastValue ?? min);
    fetchLevelBarValue(el);
    return;
  }

  updateLevelBarDisplay(el, arg2);
};

function renderLevelBarPreview(container, config) {
  ensureLevelBarStyles();
  const min = parseFloat(config.min);
  const max = parseFloat(config.max);
  const safeMin = Number.isFinite(min) ? min : 0;
  const safeMax = Number.isFinite(max) ? max : 100;
  const ranges = typeof collectGaugeColorRanges === 'function'
    ? collectGaugeColorRanges()
    : parseLevelBarColorRanges(config.color_ranges || []);
  const sample = safeMin + (safeMax - safeMin) * 0.72;
  const showBorder = document.getElementById('cfg-show_border')?.value ?? config.show_border ?? '1';
  const borderClass = showBorder === '0' ? '' : 'border rounded shadow-sm';
  const orientation = config.orientation === 'horizontal' ? 'horizontal' : 'vertical';
  const previewH = orientation === 'horizontal' ? '110px' : '220px';

  container.innerHTML = `
    <div class="widget-level-bar is-${orientation} ${borderClass} bg-body text-body" style="height:${previewH};">
      <div class="small text-secondary level-bar-label">${config.label || 'Level Bar'}</div>
      <div class="level-bar-body">
        <div class="level-bar-track"><div class="level-bar-fill"></div></div>
        <div class="level-bar-ticks"></div>
      </div>
      <div class="level-bar-value">--</div>
    </div>
  `;

  const previewRoot = container.firstElementChild;
  previewRoot.dataset.min = safeMin;
  previewRoot.dataset.max = safeMax;
  previewRoot.dataset.unit = config.unit || '';
  previewRoot.dataset.orientation = orientation;
  previewRoot.dataset.colorRanges = JSON.stringify(ranges);
  updateLevelBarDisplay(previewRoot, sample);
}

document.addEventListener('DOMContentLoaded', () => {
  ensureLevelBarStyles();
  document.querySelectorAll('.widget-level-bar[data-device-id][data-metric-key]').forEach(el => {
    renderLevelBarWidget(el, {
      device_id: el.dataset.deviceId,
      metric_key: el.dataset.metricKey,
      min: el.dataset.min ?? 0,
      max: el.dataset.max ?? 100,
      unit: el.dataset.unit ?? '',
      orientation: el.dataset.orientation || 'vertical',
      color_ranges: parseLevelBarColorRanges(el)
    });
  });
});
