const SVG_UPLOAD_BASE = '/uploads/mimics/';
window.__svgElementIds = window.__svgElementIds || [];

function parseSvgBindings(source) {
  let raw = source;
  if (source instanceof HTMLElement) {
    raw = source.dataset.bindings || '[]';
  }
  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return [];
  }
}

function normalizeCompareValue(value) {
  if (value === null || value === undefined) return '';
  if (typeof value === 'boolean') return value ? '1' : '0';
  return String(value).trim();
}

function matchSvgWhen(when, value) {
  const op = (when?.op || 'always').toLowerCase();
  if (op === 'always') return true;

  const left = normalizeCompareValue(value);
  const right = normalizeCompareValue(when?.value);
  const leftNum = Number(left);
  const rightNum = Number(right);
  const bothNumeric = left !== '' && right !== '' && Number.isFinite(leftNum) && Number.isFinite(rightNum);

  switch (op) {
    case 'eq':
      return bothNumeric ? leftNum === rightNum : left === right;
    case 'neq':
      return bothNumeric ? leftNum !== rightNum : left !== right;
    case 'gt':
      return bothNumeric && leftNum > rightNum;
    case 'gte':
      return bothNumeric && leftNum >= rightNum;
    case 'lt':
      return bothNumeric && leftNum < rightNum;
    case 'lte':
      return bothNumeric && leftNum <= rightNum;
    case 'between': {
      const from = Number(when?.from ?? when?.value);
      const to = Number(when?.to ?? when?.value2);
      return Number.isFinite(leftNum) && Number.isFinite(from) && Number.isFinite(to) && leftNum >= from && leftNum <= to;
    }
    case 'in': {
      let values = when?.values;
      if (typeof values === 'string') {
        values = values.split(',').map(v => v.trim()).filter(Boolean);
      }
      values = Array.isArray(values) ? values.map(normalizeCompareValue) : [];
      return values.includes(left) || (Number.isFinite(leftNum) && values.some(v => Number(v) === leftNum));
    }
    default:
      return false;
  }
}

function formatSvgTemplate(template, value, unit, precision) {
  let display = value;
  const num = Number(value);
  if (Number.isFinite(num) && precision !== undefined && precision !== null && precision !== '') {
    display = num.toFixed(Number(precision));
  } else if (Number.isFinite(num) && typeof value !== 'string') {
    display = Number.isInteger(num) ? String(num) : num.toFixed(2);
  } else if (value === null || value === undefined || value === '') {
    display = '--';
  }

  return String(template || '{value}')
    .replace(/\{value\}/g, display)
    .replace(/\{unit\}/g, unit || '')
    .trim();
}

function applySvgAction(el, action, value, unit) {
  if (!el || !action) return;
  const type = action.type;

  if (type === 'setFill') {
    el.setAttribute('fill', action.value || '#0d6efd');
    if (el.style) el.style.fill = action.value || '#0d6efd';
  } else if (type === 'setStroke') {
    el.setAttribute('stroke', action.value || '#0d6efd');
    if (el.style) el.style.stroke = action.value || '#0d6efd';
  } else if (type === 'setClass') {
    const cls = action.value || '';
    if (action.append) {
      if (cls === 'mimic-spin') {
        el.classList.remove('mimic-spin-off');
      }
      el.classList.add(cls);
    } else {
      if (cls === 'mimic-spin-off') {
        el.classList.remove('mimic-spin');
        return;
      }
      el.setAttribute('class', cls);
    }
  } else if (type === 'show') {
    el.style.display = '';
    el.style.visibility = 'visible';
    el.removeAttribute('display');
  } else if (type === 'hide') {
    el.style.display = 'none';
  } else if (type === 'setOpacity') {
    const opacity = action.value ?? 1;
    el.setAttribute('opacity', opacity);
    if (el.style) el.style.opacity = opacity;
  } else if (type === 'blink') {
    if (action.value === false || action.value === '0' || action.value === 0) {
      el.classList.remove('mimic-blink');
    } else {
      el.classList.add('mimic-blink');
    }
  } else if (type === 'setText') {
    const text = formatSvgTemplate(action.template || action.value || '{value}', value, unit, action.precision);
    if (el.tagName && el.tagName.toLowerCase() === 'text') {
      const tspan = el.querySelector('tspan');
      if (tspan) tspan.textContent = text;
      else el.textContent = text;
    } else {
      el.textContent = text;
    }
  }
}

function applySvgBinding(svgRoot, binding, value) {
  if (!svgRoot || !binding?.id) return;
  const safeId = window.CSS && CSS.escape
    ? CSS.escape(binding.id)
    : String(binding.id).replace(/([^a-zA-Z0-9_-])/g, '\\$1');
  const el = svgRoot.getElementById(binding.id) || svgRoot.querySelector('#' + safeId);
  if (!el) {
    console.warn('[svg_widget] element not found:', binding.id);
    return;
  }

  const actions = Array.isArray(binding.actions) ? binding.actions : [];
  const unit = binding.unit || '';

  for (const rule of actions) {
    if (matchSvgWhen(rule.when || { op: 'always' }, value)) {
      (rule.do || []).forEach(action => applySvgAction(el, action, value, unit));
      break;
    }
  }
}

function applySvgWidgetMetric(deviceId, metricKey, value) {
  document.querySelectorAll('.widget-svg-widget').forEach(widget => {
    const svg = widget.querySelector('.svg-widget-stage svg');
    if (!svg) return;
    const bindings = parseSvgBindings(widget);
    bindings.forEach(b => {
      if (String(b.device_id) === String(deviceId) && String(b.metric_key) === String(metricKey)) {
        applySvgBinding(svg, b, value);
      }
    });
  });
}

window.applySvgWidgetMetric = applySvgWidgetMetric;

function extractSvgElementIds(svgRootOrText) {
  let doc = null;
  if (typeof svgRootOrText === 'string') {
    const parsed = new DOMParser().parseFromString(svgRootOrText, 'image/svg+xml');
    doc = parsed.documentElement;
  } else {
    doc = svgRootOrText;
  }
  if (!doc) return [];

  const ids = new Set();
  doc.querySelectorAll('[id]').forEach(el => {
    const id = el.getAttribute('id');
    if (id && id.trim()) ids.add(id.trim());
  });
  return Array.from(ids).sort((a, b) => a.localeCompare(b));
}

function setSvgElementIdOptions(ids = []) {
  window.__svgElementIds = ids;
  const datalist = document.getElementById('svg-element-ids');
  if (datalist) {
    datalist.innerHTML = ids.map(id => `<option value="${id.replace(/"/g, '&quot;')}"></option>`).join('');
  }
  document.querySelectorAll('#svg-bindings-list .svg-element-id-select').forEach(select => {
    const current = select.dataset.currentValue || select.value || '';
    const extra = current && !ids.includes(current)
      ? `<option value="${current}">${current}</option>`
      : '';
    select.innerHTML = '<option value="">Select from SVG...</option>' +
      extra +
      ids.map(id => `<option value="${id.replace(/"/g, '&quot;')}">${id}</option>`).join('');
    if (current) select.value = current;
  });
  document.querySelectorAll('#svg-bindings-list .svg-element-id-input').forEach(input => {
    input.setAttribute('list', 'svg-element-ids');
  });
  const countEl = document.getElementById('svg-id-count');
  if (countEl) countEl.textContent = ids.length ? `${ids.length} element id(s) found` : 'No element ids found';
}

async function refreshSvgElementIdsFromFile(filename) {
  if (!filename) {
    setSvgElementIdOptions([]);
    return [];
  }
  try {
    const res = await fetch(SVG_UPLOAD_BASE + filename, { cache: 'no-cache' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    const ids = extractSvgElementIds(text);
    setSvgElementIdOptions(ids);
    return ids;
  } catch (e) {
    console.warn('[svg_widget] parse ids failed', e);
    setSvgElementIdOptions([]);
    return [];
  }
}

async function loadSvgIntoStage(stage, url) {
  if (!stage) return null;
  if (!url) {
    stage.innerHTML = '<div class="svg-widget-fallback">No SVG loaded<br><span class="small">Upload an SVG in Configure</span></div>';
    return null;
  }

  try {
    const res = await fetch(url, { cache: 'no-cache' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    if (!/<svg[\s>]/i.test(text)) throw new Error('Invalid SVG');

    stage.innerHTML = text;
    const svg = stage.querySelector('svg');
    if (!svg) throw new Error('SVG root missing');

    svg.removeAttribute('width');
    svg.removeAttribute('height');
    svg.style.width = '100%';
    svg.style.height = '100%';
    svg.setAttribute('preserveAspectRatio', svg.getAttribute('preserveAspectRatio') || 'xMidYMid meet');
    return svg;
  } catch (err) {
    console.warn('[svg_widget] load failed:', err);
    stage.innerHTML = '<div class="svg-widget-fallback">Failed to load SVG</div>';
    return null;
  }
}

async function initSvgWidget(widget, force = false) {
  if (!widget) return;
  if (!force && widget.dataset.svgReady === '1') return;
  widget.dataset.svgReady = '1';

  const stage = widget.querySelector('.svg-widget-stage');
  const svg = await loadSvgIntoStage(stage, widget.dataset.svgUrl || '');
  if (!svg) return;

  const bindings = parseSvgBindings(widget);
  const unique = new Map();
  bindings.forEach(b => {
    if (!b.device_id || !b.metric_key) return;
    unique.set(`${b.device_id}||${b.metric_key}`, b);
  });

  for (const b of unique.values()) {
    try {
      const res = await fetch(`/device/metrics_latest?device_id=${encodeURIComponent(b.device_id)}&metric_key=${encodeURIComponent(b.metric_key)}`);
      const data = await res.json();
      if (data.status === 'success') {
        applySvgWidgetMetric(b.device_id, b.metric_key, data.value);
      }
    } catch (e) {
      console.warn('[svg_widget] preload failed', e);
    }
  }
}

function buildWhenClause(op, opts = {}) {
  const operator = op || 'eq';
  if (operator === 'always') return { op: 'always' };
  if (operator === 'between') {
    return { op: 'between', from: opts.match_from ?? opts.match_value ?? '', to: opts.match_to ?? opts.match_value2 ?? '' };
  }
  if (operator === 'in') {
    const values = String(opts.match_value || '')
      .split(',')
      .map(v => v.trim())
      .filter(Boolean);
    return { op: 'in', values };
  }
  return { op: operator, value: opts.match_value ?? '1' };
}

function buildSvgActionsFromMode(mode, opts = {}) {
  const op = opts.op || 'eq';
  const when = buildWhenClause(op, opts);
  const activeColor = opts.active_color || '#28a745';
  const inactiveColor = opts.inactive_color || '#6c757d';
  const precision = opts.precision ?? 2;
  const className = opts.class_name || 'mimic-active';
  const opacityOn = opts.opacity_on ?? '1';
  const opacityOff = opts.opacity_off ?? '0.35';

  if (mode === 'text') {
    return [{
      when: { op: 'always' },
      do: [{ type: 'setText', template: opts.text_template || '{value} {unit}', precision }]
    }];
  }

  if (mode === 'show') {
    return [
      { when, do: [{ type: 'show' }] },
      { when: { op: 'always' }, do: [{ type: 'hide' }] }
    ];
  }

  if (mode === 'blink') {
    return [
      { when, do: [{ type: 'blink', value: true }, { type: 'setFill', value: activeColor }] },
      { when: { op: 'always' }, do: [{ type: 'blink', value: false }, { type: 'setFill', value: inactiveColor }] }
    ];
  }

  if (mode === 'spin') {
    return [
      { when, do: [{ type: 'setClass', value: 'mimic-spin', append: true }, { type: 'setFill', value: activeColor }] },
      { when: { op: 'always' }, do: [{ type: 'setClass', value: 'mimic-spin-off' }, { type: 'setFill', value: inactiveColor }] }
    ];
  }

  if (mode === 'opacity') {
    return [
      { when, do: [{ type: 'setOpacity', value: opacityOn }] },
      { when: { op: 'always' }, do: [{ type: 'setOpacity', value: opacityOff }] }
    ];
  }

  if (mode === 'class') {
    return [
      { when, do: [{ type: 'setClass', value: className }] },
      { when: { op: 'always' }, do: [{ type: 'setClass', value: '' }] }
    ];
  }

  if (mode === 'stroke') {
    return [
      { when, do: [{ type: 'setStroke', value: activeColor }] },
      { when: { op: 'always' }, do: [{ type: 'setStroke', value: inactiveColor }] }
    ];
  }

  // fill (default)
  return [
    { when, do: [{ type: 'setFill', value: activeColor }] },
    { when: { op: 'always' }, do: [{ type: 'setFill', value: inactiveColor }] }
  ];
}

function detectSvgBindingMode(binding) {
  const actions = binding.actions || [];
  if (!actions.length) return 'fill';
  const types = (actions[0]?.do || []).map(d => d.type);
  if (types.includes('setText')) return 'text';
  if (types.includes('blink')) return 'blink';
  if (types.includes('setClass') && actions.some(a => (a.do || []).some(d => d.type === 'setClass' && d.value === 'mimic-spin' && d.append))) return 'spin';
  if (types.includes('setOpacity')) return 'opacity';
  if (types.includes('setClass')) return 'class';
  if (types.includes('show') || types.includes('hide')) return 'show';
  if (types.includes('setStroke')) return 'stroke';
  return 'fill';
}

function extractSvgModeOptions(binding) {
  const mode = detectSvgBindingMode(binding);
  const actions = binding.actions || [];
  const opts = {
    op: 'eq',
    match_value: '1',
    match_from: '',
    match_to: '',
    active_color: '#28a745',
    inactive_color: '#6c757d',
    precision: 2,
    class_name: 'mimic-active',
    opacity_on: '1',
    opacity_off: '0.35',
    text_template: '{value} {unit}'
  };

  const condRule = actions.find(a => a.when && a.when.op && a.when.op !== 'always') || actions[0];
  if (condRule?.when) {
    opts.op = condRule.when.op || 'eq';
    if (opts.op === 'between') {
      opts.match_from = condRule.when.from ?? '';
      opts.match_to = condRule.when.to ?? '';
      opts.match_value = opts.match_from;
    } else if (opts.op === 'in') {
      opts.match_value = Array.isArray(condRule.when.values) ? condRule.when.values.join(',') : (condRule.when.value ?? '');
    } else if (condRule.when.value !== undefined) {
      opts.match_value = condRule.when.value;
    }
  }

  if (mode === 'text') {
    const textAction = actions[0]?.do?.find(d => d.type === 'setText');
    if (textAction?.precision !== undefined) opts.precision = textAction.precision;
    if (textAction?.template) opts.text_template = textAction.template;
    return opts;
  }

  if (mode === 'fill' || mode === 'stroke' || mode === 'blink' || mode === 'spin') {
    const type = mode === 'stroke' ? 'setStroke' : 'setFill';
    const active = condRule?.do?.find(d => d.type === type || d.type === 'setFill');
    const inactive = actions.find(a => a.when?.op === 'always')?.do?.find(d => d.type === type || d.type === 'setFill');
    if (active?.value) opts.active_color = active.value;
    if (inactive?.value) opts.inactive_color = inactive.value;
  }

  if (mode === 'opacity') {
    const on = condRule?.do?.find(d => d.type === 'setOpacity');
    const off = actions.find(a => a.when?.op === 'always')?.do?.find(d => d.type === 'setOpacity');
    if (on?.value !== undefined) opts.opacity_on = on.value;
    if (off?.value !== undefined) opts.opacity_off = off.value;
  }

  if (mode === 'class') {
    const cls = condRule?.do?.find(d => d.type === 'setClass');
    if (cls?.value) opts.class_name = cls.value;
  }

  return opts;
}

function renderSvgWidgetPreview(container, config) {
  const file = config.svg_file || document.getElementById('cfg-svg_file')?.value || '';
  const bindings = typeof collectSvgBindings === 'function'
    ? collectSvgBindings()
    : parseSvgBindings(config.bindings || []);

  container.innerHTML = `
    <div class="widget-svg-widget border rounded p-2 bg-body text-body" style="height:240px;display:flex;flex-direction:column;" data-svg-preview="1">
      <div class="small text-secondary mb-1">${config.label || 'SVG Widget'}</div>
      <div class="svg-widget-stage flex-grow-1" style="min-height:0;"></div>
      <div class="small text-muted mt-1">${bindings.length} binding(s)</div>
    </div>
  `;

  const root = container.firstElementChild;
  root.dataset.svgUrl = file ? SVG_UPLOAD_BASE + file : '';
  root.dataset.bindings = JSON.stringify(bindings);
  initSvgWidget(root, true);
}

function readSvgBindingRowValues(row) {
  return {
    id: row.querySelector('[name="bindings[][id]"]')?.value?.trim() || '',
    device_id: row.querySelector('[name="bindings[][device_id]"]')?.value || '',
    metric_key: row.querySelector('[name="bindings[][metric_key]"]')?.value || '',
    unit: row.querySelector('[name="bindings[][unit]"]')?.value || '',
    mode: row.querySelector('[name="bindings[][mode]"]')?.value || 'fill',
    op: row.querySelector('[name="bindings[][op]"]')?.value || 'eq',
    match_value: row.querySelector('[name="bindings[][match_value]"]')?.value ?? '1',
    match_from: row.querySelector('[name="bindings[][match_from]"]')?.value ?? '',
    match_to: row.querySelector('[name="bindings[][match_to]"]')?.value ?? '',
    active_color: row.querySelector('[name="bindings[][active_color]"]')?.value || '#28a745',
    inactive_color: row.querySelector('[name="bindings[][inactive_color]"]')?.value || '#6c757d',
    precision: row.querySelector('[name="bindings[][precision]"]')?.value ?? 2,
    class_name: row.querySelector('[name="bindings[][class_name]"]')?.value || 'mimic-active',
    opacity_on: row.querySelector('[name="bindings[][opacity_on]"]')?.value ?? '1',
    opacity_off: row.querySelector('[name="bindings[][opacity_off]"]')?.value ?? '0.35',
    text_template: row.querySelector('[name="bindings[][text_template]"]')?.value || '{value} {unit}'
  };
}

function addSvgBindingRow(prefill = {}) {
  const list = document.getElementById('svg-bindings-list');
  const tmpl = document.getElementById('svg-binding-template');
  if (!list || !tmpl) return null;

  const wrap = document.createElement('div');
  wrap.innerHTML = tmpl.innerHTML;
  const row = wrap.firstElementChild;
  list.appendChild(row);

  const mode = prefill.mode || detectSvgBindingMode(prefill);
  const opts = Object.keys(prefill).length && prefill.actions
    ? extractSvgModeOptions(prefill)
    : {
        op: prefill.op || 'eq',
        match_value: prefill.match_value ?? '1',
        match_from: prefill.match_from ?? '',
        match_to: prefill.match_to ?? '',
        active_color: prefill.active_color || '#28a745',
        inactive_color: prefill.inactive_color || '#6c757d',
        precision: prefill.precision ?? 2,
        class_name: prefill.class_name || 'mimic-active',
        opacity_on: prefill.opacity_on ?? '1',
        opacity_off: prefill.opacity_off ?? '0.35',
        text_template: prefill.text_template || '{value} {unit}'
      };

  const idInput = row.querySelector('[name="bindings[][id]"]');
  idInput.classList.add('svg-element-id-input');
  idInput.setAttribute('list', 'svg-element-ids');
  idInput.value = prefill.id || '';
  const idSelect = row.querySelector('.svg-element-id-select');
  if (idSelect) {
    idSelect.dataset.currentValue = prefill.id || '';
    idSelect.innerHTML = '<option value="">Select from SVG...</option>' +
      window.__svgElementIds.map(id => `<option value="${id.replace(/"/g, '&quot;')}">${id}</option>`).join('');
    idSelect.value = prefill.id || '';
    idSelect.addEventListener('change', () => {
      idInput.value = idSelect.value || '';
      idSelect.dataset.currentValue = idSelect.value || '';
    });
    idInput.addEventListener('input', () => {
      idSelect.dataset.currentValue = idInput.value || '';
      if (window.__svgElementIds.includes(idInput.value)) {
        idSelect.value = idInput.value;
      }
    });
  }

  row.querySelector('[name="bindings[][unit]"]').value = prefill.unit || '';
  row.querySelector('[name="bindings[][mode]"]').value = mode;
  row.querySelector('[name="bindings[][op]"]').value = opts.op || 'eq';
  row.querySelector('[name="bindings[][match_value]"]').value = opts.match_value;
  row.querySelector('[name="bindings[][match_from]"]').value = opts.match_from || '';
  row.querySelector('[name="bindings[][match_to]"]').value = opts.match_to || '';
  row.querySelector('[name="bindings[][active_color]"]').value = opts.active_color;
  row.querySelector('[name="bindings[][inactive_color]"]').value = opts.inactive_color;
  row.querySelector('[name="bindings[][precision]"]').value = opts.precision;
  row.querySelector('[name="bindings[][class_name]"]').value = opts.class_name;
  row.querySelector('[name="bindings[][opacity_on]"]').value = opts.opacity_on;
  row.querySelector('[name="bindings[][opacity_off]"]').value = opts.opacity_off;
  row.querySelector('[name="bindings[][text_template]"]').value = opts.text_template;

  const deviceSelect = row.querySelector('.device-select');
  const metricSelect = row.querySelector('.metric-select');

  if (deviceSelect && typeof loadDeviceOptions === 'function') {
    loadDeviceOptions(deviceSelect, prefill.device_id || '').then(() => {
      deviceSelect.value = prefill.device_id || '';
      if (prefill.device_id && typeof loadMetricOptions === 'function') {
        loadMetricOptions(prefill.device_id, metricSelect, prefill.metric_key || '').then(() => {
          metricSelect.value = prefill.metric_key || '';
        });
      }
    });
    deviceSelect.addEventListener('change', () => {
      if (typeof loadMetricOptions === 'function') {
        loadMetricOptions(deviceSelect.value, metricSelect);
      }
    });
  }

  toggleSvgBindingModeFields(row);
  row.querySelector('[name="bindings[][mode]"]')?.addEventListener('change', () => toggleSvgBindingModeFields(row));
  row.querySelector('[name="bindings[][op]"]')?.addEventListener('change', () => toggleSvgBindingModeFields(row));

  return row;
}

function duplicateSvgBinding(btn) {
  const source = btn.closest('.svg-binding-item');
  if (!source) return;
  const values = readSvgBindingRowValues(source);
  if (!values.device_id) {
    alert('Please select a device first.');
    return;
  }
  // Keep device, clear element id so user picks another SVG id
  values.id = '';
  values.metric_key = '';
  addSvgBindingRow(values);
  if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
}

function toggleSvgBindingModeFields(row) {
  const mode = row.querySelector('[name="bindings[][mode]"]')?.value || 'fill';
  const op = row.querySelector('[name="bindings[][op]"]')?.value || 'eq';

  row.querySelectorAll('[data-mode-field]').forEach(el => {
    const modes = (el.dataset.modeField || '').split(',');
    el.style.display = modes.includes(mode) ? '' : 'none';
  });

  row.querySelectorAll('[data-op-field]').forEach(el => {
    const ops = (el.dataset.opField || '').split(',');
    const visible = ops.includes(op);
    // Only show if also allowed by current mode visibility (parent row sections)
    if (el.hasAttribute('data-mode-field')) {
      const modes = (el.dataset.modeField || '').split(',');
      el.style.display = modes.includes(mode) && visible ? '' : 'none';
    } else {
      el.style.display = visible ? '' : 'none';
    }
  });

  // Match value hidden for always / between uses from-to
  const matchValueWrap = row.querySelector('[data-field="match_value"]');
  if (matchValueWrap) {
    matchValueWrap.style.display = (op === 'always' || op === 'between') ? 'none' : '';
  }
}

function collectSvgBindings() {
  const rows = [];
  document.querySelectorAll('#svg-bindings-list .svg-binding-item').forEach(row => {
    const values = readSvgBindingRowValues(row);
    if (!values.id && !values.device_id && !values.metric_key) return;

    rows.push({
      id: values.id,
      device_id: values.device_id,
      metric_key: values.metric_key,
      unit: values.unit,
      actions: buildSvgActionsFromMode(values.mode, values)
    });
  });
  return rows;
}

function simulateSvgBindingValue() {
  const previewRoot = document.querySelector('#widget-preview-box .widget-svg-widget');
  const svg = previewRoot?.querySelector('.svg-widget-stage svg');
  if (!svg) {
    alert('Upload SVG and wait for preview first.');
    return;
  }

  const value = document.getElementById('svg-simulate-value')?.value ?? '';
  const deviceFilter = document.getElementById('svg-simulate-device')?.value || '';
  const metricFilter = document.getElementById('svg-simulate-metric')?.value || '';
  const bindings = collectSvgBindings();

  bindings.forEach(b => {
    if (deviceFilter && String(b.device_id) !== String(deviceFilter)) return;
    if (metricFilter && String(b.metric_key) !== String(metricFilter)) return;
    applySvgBinding(svg, b, value);
  });
}

function refreshSvgSimulateFilters() {
  const deviceSelect = document.getElementById('svg-simulate-device');
  const metricSelect = document.getElementById('svg-simulate-metric');
  if (!deviceSelect || !metricSelect) return;

  const bindings = collectSvgBindings();
  const devices = new Map();
  const metrics = new Set();

  bindings.forEach(b => {
    if (b.device_id) devices.set(String(b.device_id), String(b.device_id));
    if (b.metric_key) metrics.add(String(b.metric_key));
  });

  const prevDevice = deviceSelect.value;
  const prevMetric = metricSelect.value;
  deviceSelect.innerHTML = '<option value="">All devices</option>' +
    Array.from(devices.keys()).map(id => `<option value="${id}">${id}</option>`).join('');
  metricSelect.innerHTML = '<option value="">All metrics</option>' +
    Array.from(metrics).map(m => `<option value="${m}">${m}</option>`).join('');
  deviceSelect.value = prevDevice;
  metricSelect.value = prevMetric;
}

function renderSvgWidgetConfigPanel(bindings = []) {
  const panel = document.createElement('div');
  panel.className = 'col-12 mt-2';
  panel.innerHTML = `
    <hr class="border-secondary">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <label class="form-label small mb-0">Bindings</label>
      <button type="button" class="btn btn-outline-light btn-sm" id="btn-add-svg-binding">+ Add Binding</button>
    </div>
    <div class="text-secondary small mb-1">Map SVG element id → device metric. Modes: Fill / Stroke / Show / Text / Blink / Opacity / Class.</div>
    <div id="svg-id-count" class="text-info small mb-2">Parsing element ids...</div>
    <datalist id="svg-element-ids"></datalist>
    <div id="svg-bindings-list"></div>

    <hr class="border-secondary">
    <div class="mb-2">
      <label class="form-label small mb-1">Simulate Value (preview only)</label>
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small">Value</label>
          <input type="text" id="svg-simulate-value" class="form-control form-control-sm" placeholder="e.g. 1">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Device filter</label>
          <select id="svg-simulate-device" class="form-select form-select-sm"><option value="">All devices</option></select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Metric filter</label>
          <select id="svg-simulate-metric" class="form-select form-select-sm"><option value="">All metrics</option></select>
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-warning btn-sm w-100" id="btn-svg-simulate">Simulate</button>
        </div>
      </div>
      <div class="text-secondary small mt-1">Applies the value to preview bindings without saving.</div>
    </div>
  `;
  document.getElementById('widget-config-form').appendChild(panel);
  panel.querySelector('#btn-add-svg-binding')?.addEventListener('click', () => addSvgBindingRow());
  panel.querySelector('#btn-svg-simulate')?.addEventListener('click', () => {
    refreshSvgSimulateFilters();
    simulateSvgBindingValue();
  });

  (bindings || []).forEach(b => addSvgBindingRow(b));
  refreshSvgSimulateFilters();

  const svgFile = document.getElementById('cfg-svg_file')?.value || '';
  refreshSvgElementIdsFromFile(svgFile);
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.widget-svg-widget').forEach(el => initSvgWidget(el));
});
