<?php
/**
 * Layout tab: map view of this device — a background image (floor plan, rack photo…) with
 * metric labels / points placed on it. Same item format as the dashboard map_view widget,
 * stored per device in devices.dashboard_layout → map_view.
 * Expects $device, $viewMetrics, $latest_metrics from view.php; values go through device_display_helper.
 */
$layoutData = json_decode((string) ($device->dashboard_layout ?? ''), true);
$layoutMap = is_array($layoutData) && isset($layoutData['map_view']) && is_array($layoutData['map_view'])
  ? $layoutData['map_view']
  : ['background' => '', 'items' => []];

$layoutMetrics = [];
foreach ((array) $viewMetrics as $m) {
  if (!is_array($m) || empty($m['key'])) {
    continue;
  }
  $layoutMetrics[] = [
    'key' => (string) $m['key'],
    'label' => (string) ($m['label'] ?? $m['key']),
    'unit' => (string) ($m['unit'] ?? ''),
  ];
}

// Same live-or-simulated values as the Dashboard tab.
$layoutValues = device_display_values((array) ($latest_metrics ?? []));
?>
<style>
  #dly {
    --dly-border: var(--bs-border-color-translucent);
    --dly-subtle: var(--bs-tertiary-bg);
    --dly-muted: var(--bs-secondary-color);
  }
  /* Toolbar floats over the top of the map so it takes no vertical space. */
  #dly .dly-toolbar {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    right: 0.5rem;
    z-index: 2;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.4rem;
    pointer-events: none;
  }
  #dly .dly-toolbar__group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem;
    border: 1px solid var(--dly-border);
    border-radius: 0.5rem;
    background: rgba(var(--bs-body-bg-rgb), 0.82);
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
    pointer-events: auto;
  }
  #dly .dly-hint {
    padding: 0 0.35rem;
    font-size: 0.75rem;
    color: var(--dly-muted);
  }
  #dly .dly-card {
    height: 100%;
    border: 1px solid var(--dly-border);
    border-radius: 0.65rem;
    background: var(--bs-body-bg);
    overflow: hidden;
  }
  #dly .dly-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.55rem 0.9rem;
    border-bottom: 1px solid var(--dly-border);
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--dly-muted);
  }
  #dly .dly-card__body {
    padding: 0.8rem 0.9rem;
  }
  #dly .dly-canvas {
    position: relative;
    background: var(--dly-subtle);
  }
  #dly .dly-canvas svg {
    display: block;
    width: 100%;
    margin: 0 auto;
    height: auto;
    aspect-ratio: 1000 / 600;
    max-height: calc(100vh - 255px);
    min-height: 320px;
    user-select: none;
    touch-action: none;
  }
  #dly.dly--edit .dly-canvas svg { cursor: crosshair; }
  #dly .dly-item { cursor: default; }
  #dly.dly--edit .dly-item { cursor: move; }
  #dly .dly-item__bg {
    fill: rgba(17, 20, 24, 0.82);
    stroke: rgba(255, 255, 255, 0.18);
    stroke-width: 1;
  }
  #dly .dly-item__text {
    fill: #f1f3f5;
    font-size: 15px;
    font-weight: 600;
    dominant-baseline: central;
  }
  #dly .dly-item__value { fill: #4dd0e1; }
  #dly .dly-item__unit { fill: #adb5bd; font-weight: 500; }
  #dly .dly-item--selected .dly-item__bg { stroke: var(--bs-primary); stroke-width: 2.5; }
  #dly .dly-item--selected .dly-item__dot { stroke: var(--bs-primary); stroke-width: 3; }
  #dly .dly-item__dot { fill: #2fb344; stroke: #fff; stroke-width: 2; }
  #dly .dly-item__dot--none { fill: #6c757d; }

  #dly .dly-list {
    margin: 0;
    padding: 0;
    list-style: none;
    max-height: 420px;
    overflow: auto;
  }
  #dly .dly-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.9rem;
    border-bottom: 1px solid var(--dly-border);
    font-size: 0.85rem;
    cursor: pointer;
  }
  #dly .dly-list li:last-child { border-bottom: 0; }
  #dly .dly-list li:hover { background: var(--dly-subtle); }
  #dly .dly-list li.is-selected { background: var(--bs-primary-bg-subtle); }
  #dly .dly-list__value {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  #dly .dly-empty {
    padding: 1.25rem 0.9rem;
    font-size: 0.85rem;
    text-align: center;
    color: var(--dly-muted);
  }
  #dly .dly-form .form-label {
    margin-bottom: 0.2rem;
    font-size: 0.78rem;
    color: var(--dly-muted);
  }
</style>

<div id="dly">
  <div class="row g-3">
    <div class="col-xl-9" data-dly-main>
      <div class="dly-card">
        <div class="dly-canvas">
          <div class="dly-toolbar">
            <div class="dly-toolbar__group">
              <div class="btn-group btn-group-sm" role="group" aria-label="Mode">
                <button type="button" class="btn btn-primary" data-dly-mode="view"><i class="bi bi-eye me-1"></i>View</button>
                <button type="button" class="btn btn-outline-primary" data-dly-mode="edit"><i class="bi bi-pencil-square me-1"></i>Edit</button>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-dly-toggle-panel aria-pressed="true">
                <i class="bi bi-layout-sidebar-inset-reverse me-1"></i><span>Hide items</span>
              </button>
              <span class="dly-hint d-none" data-dly-hint></span>
            </div>
            <div class="dly-toolbar__group d-none" data-dly-edit-only>
              <label class="btn btn-sm btn-outline-secondary mb-0">
                <i class="bi bi-image me-1"></i>Background
                <input type="file" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" class="d-none" data-dly-upload>
              </label>
              <button type="button" class="btn btn-sm btn-outline-danger" data-dly-clear-bg><i class="bi bi-x-lg me-1"></i>Remove background</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-dly-cancel>Cancel</button>
              <button type="button" class="btn btn-sm btn-success" data-dly-save><i class="bi bi-save me-1"></i>Save</button>
            </div>
          </div>
          <svg viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" data-dly-svg></svg>
        </div>
      </div>
    </div>

    <div class="col-xl-3" data-dly-side>
      <!-- Item editor (edit mode) -->
      <div class="dly-card mb-3 d-none" data-dly-form-card>
        <div class="dly-card__head"><span>Item</span><button type="button" class="btn btn-sm btn-outline-danger py-0" data-dly-delete><i class="bi bi-trash"></i></button></div>
        <div class="dly-card__body dly-form">
          <div class="mb-2">
            <label class="form-label">Metric</label>
            <select class="form-select form-select-sm" data-dly-field="metric_key">
              <option value="">— None —</option>
              <?php foreach ($layoutMetrics as $m) { ?>
                <option value="<?= htmlspecialchars($m['key']) ?>" data-label="<?= htmlspecialchars($m['label']) ?>" data-unit="<?= htmlspecialchars($m['unit']) ?>"><?= htmlspecialchars($m['label']) ?> (<?= htmlspecialchars($m['key']) ?>)</option>
              <?php } ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Label</label>
            <input type="text" class="form-control form-control-sm" maxlength="100" data-dly-field="label">
          </div>
          <div class="row g-2">
            <div class="col-5">
              <label class="form-label">Unit</label>
              <input type="text" class="form-control form-control-sm" maxlength="20" data-dly-field="unit">
            </div>
            <div class="col-7">
              <label class="form-label">Show</label>
              <select class="form-select form-select-sm" data-dly-field="show">
                <option value="label+value+unit">Label + Value + Unit</option>
                <option value="label+value">Label + Value</option>
                <option value="value+unit">Value + Unit</option>
                <option value="value">Value</option>
                <option value="label">Label</option>
                <option value="point">Point</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="dly-card">
        <div class="dly-card__head">
          <span>Items <span class="ms-1" data-dly-count>0</span></span>
          <button type="button" class="btn-close btn-close-sm" style="font-size:0.6rem" aria-label="Hide items" data-dly-close-panel></button>
        </div>
        <ul class="dly-list" data-dly-list></ul>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const root = document.getElementById('dly');
  if (!root) return;

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const saveUrl = <?= json_encode(base_url('device/save_layout/' . (int) $device->id)) ?>;
  const valuesUrl = <?= json_encode(base_url('device/layout_values/' . (int) $device->id)) ?>;
  const uploadUrl = <?= json_encode(base_url('api/upload_map_image')) ?>;
  const mapBase = <?= json_encode(base_url('uploads/maps/')) ?>;

  let saved = <?= json_encode($layoutMap, JSON_UNESCAPED_UNICODE) ?>;
  let values = <?= json_encode((object) $layoutValues, JSON_UNESCAPED_UNICODE) ?>;
  let state = clone(saved);
  let mode = 'view';
  let selected = -1;
  let dirty = false;

  const svg = root.querySelector('[data-dly-svg]');
  const list = root.querySelector('[data-dly-list]');
  const count = root.querySelector('[data-dly-count]');
  const formCard = root.querySelector('[data-dly-form-card]');
  const fields = {};
  root.querySelectorAll('[data-dly-field]').forEach(function (el) { fields[el.getAttribute('data-dly-field')] = el; });

  function clone(obj) {
    const c = JSON.parse(JSON.stringify(obj || {}));
    c.background = c.background || '';
    c.items = Array.isArray(c.items) ? c.items : [];
    return c;
  }

  function fmtValue(key) {
    if (!key || !(key in values)) return '--';
    const v = values[key];
    if (typeof v === 'number') return Number.isInteger(v) ? String(v) : v.toFixed(2).replace(/\.?0+$/, '');
    return String(v);
  }

  function el(tag, attrs, text) {
    const node = document.createElementNS(SVG_NS, tag);
    Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function renderBackground() {
    if (state.background) {
      svg.appendChild(el('image', { href: mapBase + encodeURIComponent(state.background), x: 0, y: 0, width: 1000, height: 600, preserveAspectRatio: 'xMidYMid meet' }));
      return;
    }
    svg.appendChild(el('rect', { width: 1000, height: 600, fill: 'rgba(127,127,127,0.08)' }));
    svg.appendChild(el('text', { x: 500, y: 285, 'text-anchor': 'middle', fill: '#8a939b', 'font-size': 22 }, 'No layout image'));
    svg.appendChild(el('text', { x: 500, y: 318, 'text-anchor': 'middle', fill: '#8a939b', 'font-size': 14 },
      mode === 'edit' ? 'Upload a background, then click on the image to add items' : 'Switch to Edit to build the layout'));
  }

  function renderItem(item, index) {
    const g = el('g', { class: 'dly-item' + (index === selected ? ' dly-item--selected' : ''), transform: 'translate(' + item.x + ',' + item.y + ')' });
    g.dataset.index = index;
    const show = item.show || 'label+value+unit';
    const val = fmtValue(item.metric_key);
    const title = el('title', {}, (item.label || item.metric_key || 'Item') + ': ' + val + (item.unit ? ' ' + item.unit : ''));
    g.appendChild(title);

    if (show === 'point') {
      g.appendChild(el('circle', { r: 9, class: 'dly-item__dot' + (val === '--' ? ' dly-item__dot--none' : '') }));
      svg.appendChild(g);
      return;
    }

    const bg = el('rect', { class: 'dly-item__bg', rx: 6, ry: 6 });
    const text = el('text', { class: 'dly-item__text', x: 0, y: 0 });
    const parts = [];
    if (show.indexOf('label') !== -1) parts.push(['', (item.label || item.metric_key || 'Item') + (show === 'label' ? '' : ' ')]);
    if (show.indexOf('value') !== -1) parts.push(['dly-item__value', val]);
    if (show.indexOf('unit') !== -1 && item.unit) parts.push(['dly-item__unit', ' ' + item.unit]);
    parts.forEach(function (p) {
      const span = el('tspan', p[0] ? { class: p[0] } : {}, p[1]);
      text.appendChild(span);
    });
    g.appendChild(bg);
    g.appendChild(text);
    svg.appendChild(g);

    // Center the pill on the item's point once the text has a size.
    const box = text.getBBox();
    const padX = 9, padY = 5;
    text.setAttribute('x', -box.width / 2);
    bg.setAttribute('x', -box.width / 2 - padX);
    bg.setAttribute('y', -box.height / 2 - padY);
    bg.setAttribute('width', box.width + padX * 2);
    bg.setAttribute('height', box.height + padY * 2);
  }

  function renderList() {
    list.innerHTML = '';
    count.textContent = state.items.length;
    if (!state.items.length) {
      list.innerHTML = '<li class="dly-empty" style="cursor:default">' + (mode === 'edit' ? 'Click on the image to add an item' : 'No items yet') + '</li>';
      return;
    }
    state.items.forEach(function (item, index) {
      const li = document.createElement('li');
      if (index === selected) li.className = 'is-selected';
      const name = document.createElement('span');
      name.textContent = item.label || item.metric_key || 'Item ' + (index + 1);
      const value = document.createElement('span');
      value.className = 'dly-list__value';
      value.textContent = fmtValue(item.metric_key) + (item.unit ? ' ' + item.unit : '');
      li.appendChild(name);
      li.appendChild(value);
      li.addEventListener('click', function () {
        if (mode === 'edit') select(index);
      });
      list.appendChild(li);
    });
  }

  function renderForm() {
    const item = state.items[selected];
    formCard.classList.toggle('d-none', mode !== 'edit' || !item);
    if (!item) return;
    fields.metric_key.value = item.metric_key || '';
    fields.label.value = item.label || '';
    fields.unit.value = item.unit || '';
    fields.show.value = item.show || 'label+value+unit';
  }

  function render() {
    svg.innerHTML = '';
    renderBackground();
    state.items.forEach(renderItem);
    renderList();
    renderForm();
  }

  function select(index) {
    selected = index;
    render();
  }

  function markDirty() {
    dirty = true;
  }

  // Items panel can be hidden in View mode; the map then uses the full width.
  const PANEL_KEY = 'dly-items-hidden';
  let panelHidden = false;
  try { panelHidden = localStorage.getItem(PANEL_KEY) === '1'; } catch (e) {}
  const mainCol = root.querySelector('[data-dly-main]');
  const sideCol = root.querySelector('[data-dly-side]');
  const panelBtn = root.querySelector('[data-dly-toggle-panel]');

  function applyPanel() {
    const hidden = panelHidden && mode !== 'edit';
    sideCol.classList.toggle('d-none', hidden);
    mainCol.classList.toggle('col-xl-9', !hidden);
    mainCol.classList.toggle('col-12', hidden);
    panelBtn.classList.toggle('d-none', mode === 'edit');
    panelBtn.setAttribute('aria-pressed', hidden ? 'false' : 'true');
    panelBtn.querySelector('span').textContent = hidden ? 'Show items' : 'Hide items';
    root.querySelector('[data-dly-close-panel]').classList.toggle('d-none', mode === 'edit');
  }

  function setPanelHidden(hidden) {
    panelHidden = hidden;
    try { localStorage.setItem(PANEL_KEY, hidden ? '1' : '0'); } catch (e) {}
    applyPanel();
  }
  panelBtn.addEventListener('click', function () { setPanelHidden(!panelHidden); });
  root.querySelector('[data-dly-close-panel]').addEventListener('click', function () { setPanelHidden(true); });

  function setMode(next) {
    if (mode === 'edit' && next === 'view' && dirty && !confirm('Discard unsaved layout changes?')) return;
    if (next === 'view') {
      state = clone(saved);
      dirty = false;
    }
    mode = next;
    selected = -1;
    root.classList.toggle('dly--edit', mode === 'edit');
    root.querySelectorAll('[data-dly-mode]').forEach(function (b) {
      const on = b.getAttribute('data-dly-mode') === mode;
      b.classList.toggle('btn-primary', on);
      b.classList.toggle('btn-outline-primary', !on);
    });
    root.querySelector('[data-dly-edit-only]').classList.toggle('d-none', mode !== 'edit');
    applyPanel();
    const hint = root.querySelector('[data-dly-hint]');
    hint.textContent = mode === 'edit' ? 'Click to add · drag to move · Delete removes' : '';
    hint.classList.toggle('d-none', mode !== 'edit');
    render();
  }

  function svgPoint(evt) {
    const pt = svg.createSVGPoint();
    pt.x = evt.clientX;
    pt.y = evt.clientY;
    const p = pt.matrixTransform(svg.getScreenCTM().inverse());
    return { x: Math.max(0, Math.min(1000, Math.round(p.x))), y: Math.max(0, Math.min(600, Math.round(p.y))) };
  }

  // Add / select / drag in edit mode.
  let drag = null;
  svg.addEventListener('pointerdown', function (evt) {
    if (mode !== 'edit') return;
    const g = evt.target.closest('.dly-item');
    const p = svgPoint(evt);
    if (g) {
      const index = Number(g.dataset.index);
      const item = state.items[index];
      drag = { index: index, dx: p.x - item.x, dy: p.y - item.y, moved: false };
      svg.setPointerCapture(evt.pointerId);
      if (selected !== index) select(index);
      return;
    }
    state.items.push({ x: p.x, y: p.y, label: 'New item', metric_key: '', unit: '', show: 'label+value+unit' });
    markDirty();
    select(state.items.length - 1);
    fields.metric_key.focus();
  });
  svg.addEventListener('pointermove', function (evt) {
    if (!drag) return;
    const p = svgPoint(evt);
    const item = state.items[drag.index];
    item.x = Math.max(0, Math.min(1000, p.x - drag.dx));
    item.y = Math.max(0, Math.min(600, p.y - drag.dy));
    drag.moved = true;
    const g = svg.querySelector('.dly-item[data-index="' + drag.index + '"]');
    if (g) g.setAttribute('transform', 'translate(' + item.x + ',' + item.y + ')');
  });
  svg.addEventListener('pointerup', function () {
    if (drag && drag.moved) markDirty();
    drag = null;
  });

  // Item form.
  Object.keys(fields).forEach(function (name) {
    fields[name].addEventListener(name === 'label' || name === 'unit' ? 'input' : 'change', function () {
      const item = state.items[selected];
      if (!item) return;
      item[name] = fields[name].value;
      if (name === 'metric_key') {
        const opt = fields.metric_key.selectedOptions[0];
        if (opt && opt.value) {
          if (!item.label || item.label === 'New item') item.label = opt.getAttribute('data-label') || '';
          if (!item.unit) item.unit = opt.getAttribute('data-unit') || '';
        }
      }
      markDirty();
      render();
    });
  });

  function deleteSelected() {
    if (selected < 0) return;
    state.items.splice(selected, 1);
    selected = -1;
    markDirty();
    render();
  }
  root.querySelector('[data-dly-delete]').addEventListener('click', deleteSelected);
  document.addEventListener('keydown', function (evt) {
    if (mode !== 'edit' || selected < 0 || !root.offsetParent) return;
    if ((evt.key === 'Delete' || evt.key === 'Backspace') && !/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName)) {
      evt.preventDefault();
      deleteSelected();
    }
  });

  // Toolbar.
  root.querySelectorAll('[data-dly-mode]').forEach(function (b) {
    b.addEventListener('click', function () { setMode(b.getAttribute('data-dly-mode')); });
  });
  root.querySelector('[data-dly-cancel]').addEventListener('click', function () {
    if (dirty && !confirm('Discard unsaved layout changes?')) return;
    dirty = false;
    setMode('view');
  });
  root.querySelector('[data-dly-clear-bg]').addEventListener('click', function () {
    state.background = '';
    markDirty();
    render();
  });
  root.querySelector('[data-dly-upload]').addEventListener('change', function (evt) {
    const file = evt.target.files[0];
    evt.target.value = '';
    if (!file) return;
    const body = new FormData();
    body.append('image', file);
    fetch(uploadUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.status !== 'success') throw new Error(res.message || 'Upload failed');
        state.background = res.filename;
        markDirty();
        render();
      })
      .catch(function (err) { alert(err.message); });
  });
  root.querySelector('[data-dly-save]').addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    fetch(saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ background: state.background, items: state.items })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.status !== 'success') throw new Error(res.message || 'Save failed');
        saved = clone(res.map_view);
        dirty = false;
        setMode('view');
      })
      .catch(function (err) { alert(err.message); })
      .finally(function () { btn.disabled = false; });
  });

  window.addEventListener('beforeunload', function (evt) {
    if (dirty) {
      evt.preventDefault();
      evt.returnValue = '';
    }
  });

  // Live values while viewing.
  function refreshValues() {
    if (mode !== 'view' || !root.offsetParent || !state.items.length) return;
    fetch(valuesUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        values = data || {};
        if (mode === 'view') render();
      })
      .catch(function () {});
  }
  setInterval(refreshValues, 30000);

  // getBBox needs the SVG on screen, so draw when the tab is first shown too.
  const tabBtn = document.getElementById('tab-layout-btn');
  if (tabBtn) tabBtn.addEventListener('shown.bs.tab', render);
  applyPanel();
  render();
})();
</script>
