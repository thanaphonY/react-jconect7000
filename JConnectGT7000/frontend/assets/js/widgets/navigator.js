const NAVIGATOR_ICONS = [
  { label: 'Dashboard', value: 'bi-grid-1x2' },
  { label: 'Compass', value: 'bi-compass' },
  { label: 'Map', value: 'bi-map' },
  { label: 'Building', value: 'bi-building' },
  { label: 'House', value: 'bi-house-door' },
  { label: 'Server Rack', value: 'bi-hdd-rack' },
  { label: 'Server', value: 'bi-server' },
  { label: 'Power', value: 'bi-lightning-charge' },
  { label: 'Cooling', value: 'bi-fan' },
  { label: 'Temperature', value: 'bi-thermometer-half' },
  { label: 'Water', value: 'bi-droplet' },
  { label: 'CPU', value: 'bi-cpu' },
  { label: 'Link', value: 'bi-box-arrow-up-right' }
];

function getNavigatorLayouts() {
  return Array.isArray(window.DASHBOARD_LAYOUTS) ? window.DASHBOARD_LAYOUTS : [];
}

function escapeNavigator(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function normalizeNavigatorIcon(name) {
  const n = String(name || 'bi-grid-1x2').trim();
  if (!n) return 'bi-grid-1x2';
  return n.replace(/^bi\s+/, '').replace(/^bi-?/, 'bi-');
}

function navigatorLayoutOptionsHtml(selectedId) {
  const layouts = getNavigatorLayouts();
  const selected = String(selectedId || '');
  const opts = ['<option value="">-- Select Dashboard --</option>'];
  layouts.forEach((layout) => {
    const id = String(layout.id);
    const sel = selected === id ? ' selected' : '';
    opts.push(`<option value="${escapeNavigator(id)}"${sel}>${escapeNavigator(layout.name || ('Dashboard #' + id))}</option>`);
  });
  return opts.join('');
}

function navigatorIconOptionsHtml(selected) {
  const current = normalizeNavigatorIcon(selected);
  return NAVIGATOR_ICONS.map((icon) => {
    const sel = current === icon.value ? ' selected' : '';
    return `<option value="${icon.value}"${sel}>${icon.label}</option>`;
  }).join('');
}

function ensureNavigatorStyles() {
  if (document.getElementById('navigator-widget-styles')) return;
  const s = document.createElement('style');
  s.id = 'navigator-widget-styles';
  s.textContent = `
    .widget-navigator {
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      width: 100%;
      height: 100%;
      min-height: 0;
      padding: 0.4rem 0.5rem;
      gap: 0.4rem;
    }
    .widget-navigator .navigator-title {
      font-size: clamp(0.7rem, 2cqi, 0.9rem);
      line-height: 1.2;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 0 0 auto;
    }
    .widget-navigator .navigator-body {
      flex: 1 1 auto;
      min-height: 0;
      display: grid;
      gap: 0.45rem;
      grid-template-columns: repeat(var(--navigator-cols, 2), minmax(0, 1fr));
      align-content: start;
    }
    .widget-navigator .navigator-link {
      display: flex;
      align-items: center;
      gap: 0.45rem;
      min-height: 2.4rem;
      padding: 0.45rem 0.6rem;
      border-radius: 10px;
      text-decoration: none;
      color: inherit;
      background: color-mix(in srgb, var(--bs-secondary-color, #6c757d) 10%, transparent);
      border: 1px solid color-mix(in srgb, var(--bs-border-color, #adb5bd) 55%, transparent);
      transition: background .15s ease, border-color .15s ease, transform .15s ease;
    }
    .widget-navigator .navigator-link:hover {
      background: color-mix(in srgb, var(--bs-primary, #0d6efd) 16%, transparent);
      border-color: color-mix(in srgb, var(--bs-primary, #0d6efd) 45%, transparent);
      color: inherit;
    }
    .widget-navigator .navigator-link.is-active {
      background: color-mix(in srgb, var(--bs-primary, #0d6efd) 18%, transparent);
      border-color: var(--bs-primary, #0d6efd);
      font-weight: 600;
    }
    .widget-navigator .navigator-icon {
      flex: 0 0 auto;
      font-size: 1.15rem;
      line-height: 1;
    }
    .widget-navigator .navigator-label {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-size: 0.82rem;
    }
  `;
  document.head.appendChild(s);
}

function renderNavigatorPreview(container, config) {
  ensureNavigatorStyles();
  const items = Array.isArray(config.items) ? config.items : [];
  const columns = Math.max(1, Math.min(6, parseInt(config.columns, 10) || 2));
  const showBorder = document.getElementById('cfg-show_border')?.value ?? config.show_border ?? '1';
  const borderClass = showBorder === '0' ? '' : 'border rounded';

  if (!items.length) {
    container.innerHTML = '<div class="text-muted small text-center p-2">Navigator (no dashboards yet)</div>';
    return;
  }

  const links = items.map((item) => {
    const icon = normalizeNavigatorIcon(item.icon);
    const color = item.color || '';
    const colorStyle = color ? ` style="color:${escapeNavigator(color)}"` : '';
    return `
      <div class="navigator-link">
        <i class="navigator-icon bi ${escapeNavigator(icon)}"${colorStyle}></i>
        <span class="navigator-label">${escapeNavigator(item.label || 'Dashboard')}</span>
      </div>
    `;
  }).join('');

  container.innerHTML = `
    <div class="widget-navigator ${borderClass} bg-body text-body" style="min-height: 90px;">
      ${config.label ? `<div class="navigator-title text-secondary">${escapeNavigator(config.label)}</div>` : ''}
      <div class="navigator-body" style="--navigator-cols:${columns}">${links}</div>
    </div>
  `;
}

function navigatorConfigPanel() {
  const panel = document.createElement('div');
  panel.innerHTML = `
    <div id="navigator-settings" class="widget-type-section mb-4" data-widget-type="navigator">
      <hr class="border-light">
      <div class="row g-2 mb-2">
        <div class="col-sm-4">
          <label class="form-label small">Columns</label>
          <input type="number" id="cfg-columns" data-field="columns" class="form-control form-control-sm" min="1" max="6" value="2">
        </div>
      </div>
      <label class="form-label text-white">Dashboards</label>
      <div id="navigator-item-list"></div>
      <button type="button" class="btn btn-outline-light btn-sm mt-2" id="btn-add-navigator-item">+ Add Dashboard</button>
    </div>
  `;
  document.getElementById('widget-config-form').appendChild(panel);
  panel.querySelector('#btn-add-navigator-item')?.addEventListener('click', () => addNavigatorItem());
  panel.querySelector('#cfg-columns')?.addEventListener('input', () => {
    if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
  });
}

function addNavigatorItem(prefill = {}) {
  const list = document.getElementById('navigator-item-list');
  if (!list) return;

  const row = document.createElement('div');
  row.className = 'navigator-item border border-secondary rounded p-2 mb-2';
  row.innerHTML = `
    <div class="row g-2 align-items-end">
      <div class="col-12">
        <label class="form-label small mb-1">Dashboard</label>
        <select name="items[][layout_id]" class="form-select form-select-sm navigator-layout-select">
          ${navigatorLayoutOptionsHtml(prefill.layout_id || '')}
        </select>
      </div>
      <div class="col-7">
        <label class="form-label small mb-1">Label</label>
        <input type="text" name="items[][label]" class="form-control form-control-sm" placeholder="Display name" value="${escapeNavigator(prefill.label || '')}">
      </div>
      <div class="col-5">
        <label class="form-label small mb-1">Icon</label>
        <select name="items[][icon]" class="form-select form-select-sm">
          ${navigatorIconOptionsHtml(prefill.icon || 'bi-grid-1x2')}
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-1">Color</label>
        <input type="color" name="items[][color]" class="form-control form-control-sm form-control-color" value="${escapeNavigator(prefill.color || '#0d6efd')}">
      </div>
      <div class="col-auto">
        <button type="button" class="btn btn-sm btn-outline-danger" title="Remove">&times;</button>
      </div>
    </div>
  `;

  const layoutSelect = row.querySelector('.navigator-layout-select');
  const labelInput = row.querySelector('[name="items[][label]"]');
  layoutSelect?.addEventListener('change', () => {
    const opt = layoutSelect.selectedOptions[0];
    if (opt && opt.value && (!labelInput.value || labelInput.dataset.auto === '1')) {
      labelInput.value = opt.textContent || '';
      labelInput.dataset.auto = '1';
    }
    if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
  });
  labelInput?.addEventListener('input', () => {
    labelInput.dataset.auto = '0';
    if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
  });
  row.querySelectorAll('select, input[type="color"]').forEach((input) => {
    input.addEventListener('input', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
    input.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
    });
  });
  row.querySelector('button')?.addEventListener('click', () => {
    row.remove();
    if (typeof updatePreviewFromForm === 'function') updatePreviewFromForm();
  });

  if (prefill.label) labelInput.dataset.auto = '0';
  list.appendChild(row);
}

function populateNavigatorForm(items = [], columns = '2') {
  const colInput = document.getElementById('cfg-columns');
  if (colInput) colInput.value = columns || '2';
  const list = document.getElementById('navigator-item-list');
  if (list) list.innerHTML = '';
  (items || []).forEach((item) => addNavigatorItem(item || {}));
}

function collectNavigatorConfig() {
  const form = document.getElementById('widget-config-form');
  const config = {
    label: form.querySelector('#cfg-label')?.value || '',
    columns: form.querySelector('#cfg-columns')?.value || '2',
    show_border: form.querySelector('#cfg-show_border')?.value ?? '1',
    bg_color: form.querySelector('#cfg-bg_color')?.value || '',
    bg_opacity: form.querySelector('#cfg-bg_opacity')?.value || '100',
    items: []
  };

  form.querySelectorAll('#navigator-item-list .navigator-item').forEach((item) => {
    const layoutId = item.querySelector('[name="items[][layout_id]"]')?.value || '';
    if (!layoutId) return;
    config.items.push({
      layout_id: layoutId,
      label: item.querySelector('[name="items[][label]"]')?.value || '',
      icon: item.querySelector('[name="items[][icon]"]')?.value || 'bi-grid-1x2',
      color: item.querySelector('[name="items[][color]"]')?.value || '#0d6efd'
    });
  });

  return config;
}

document.addEventListener('DOMContentLoaded', () => {
  ensureNavigatorStyles();
});
