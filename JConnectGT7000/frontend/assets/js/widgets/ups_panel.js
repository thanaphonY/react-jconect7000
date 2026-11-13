function renderUpsPanelPreview(container, config) {
  const label = config.label || 'UPS Panel';
  container.innerHTML = `
    <div class="widget-ups-panel border rounded p-2">
      <div class="d-flex justify-content-between mb-2">
        <div class="fw-semibold small">${label}</div>
        <span class="badge bg-secondary">Online</span>
      </div>
      <div class="small text-secondary mb-1">Battery <span class="float-end">78%</span></div>
      <div class="progress mb-2" style="height:0.55rem;"><div class="progress-bar bg-success" style="width:78%"></div></div>
      <div class="small text-secondary mb-1">Load R/S/T</div>
      <div class="progress mb-1" style="height:0.45rem;"><div class="progress-bar bg-primary" style="width:42%"></div></div>
      <div class="progress mb-1" style="height:0.45rem;"><div class="progress-bar bg-primary" style="width:38%"></div></div>
      <div class="progress mb-2" style="height:0.45rem;"><div class="progress-bar bg-primary" style="width:40%"></div></div>
      <div class="d-flex justify-content-between small">
        <span>Mode: <strong>Normal</strong></span>
        <span>Runtime: <strong>45 min</strong></span>
      </div>
    </div>
  `;
}

function clampPercent(value) {
  const num = Number(value);
  if (!Number.isFinite(num)) return null;
  return Math.max(0, Math.min(100, num));
}

function updateWidgetMetricCell(cell, value, datatype) {
  const valueEl = cell.querySelector('.value');
  if (!valueEl) return;
  const resolved = typeof resolveDisplayValue === 'function'
    ? resolveDisplayValue(cell, value, datatype)
    : { text: String(value ?? '--') };
  valueEl.textContent = resolved.text;
}

function updateUpsPanelBars(deviceId, metricKey, value) {
  const cells = document.querySelectorAll(
    `.widget-ups-panel [data-device-id="${deviceId}"][data-metric-key="${metricKey}"][data-ups-bar]`
  );
  const pct = clampPercent(value);
  cells.forEach((cell) => {
    const barKey = cell.dataset.upsBar;
    const panel = cell.closest('.widget-ups-panel');
    const bar = panel?.querySelector(`[data-ups-bar-target="${barKey}"]`);
    if (!bar || pct === null) return;
    bar.style.width = `${pct}%`;
    bar.classList.remove('bg-danger', 'bg-warning', 'bg-success', 'bg-primary');
    if (pct >= 90) bar.classList.add('bg-danger');
    else if (pct >= 75) bar.classList.add('bg-warning');
    else bar.classList.add(barKey === 'capacity' ? 'bg-success' : 'bg-primary');
  });
}

function updateUpsPanelCells(deviceId, metricKey, value, datatype) {
  const cells = document.querySelectorAll(
    `.widget-ups-panel [data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );
  cells.forEach((cell) => updateWidgetMetricCell(cell, value, datatype));
  updateUpsPanelBars(deviceId, metricKey, value);
}
