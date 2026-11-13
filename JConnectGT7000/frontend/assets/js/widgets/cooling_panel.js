function renderCoolingPanelPreview(container, config) {
  const label = config.label || 'Cooling Panel';
  container.innerHTML = `
    <div class="widget-cooling-panel border rounded p-2">
      <div class="d-flex justify-content-between mb-2">
        <div class="fw-semibold small">${label}</div>
        <span class="badge bg-secondary">Online</span>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6"><div class="border rounded p-2"><div class="small text-secondary">Running</div><div class="fw-semibold">Running</div></div></div>
        <div class="col-6"><div class="border rounded p-2"><div class="small text-secondary">Alarm</div><div class="fw-semibold text-success">Normal</div></div></div>
      </div>
      <div class="small d-flex justify-content-between"><span class="text-secondary">Supply</span><span>7.2 °C</span></div>
      <div class="small d-flex justify-content-between"><span class="text-secondary">Return</span><span>12.5 °C</span></div>
      <div class="small d-flex justify-content-between"><span class="text-secondary">Setpoint</span><span>7.0 °C</span></div>
    </div>
  `;
}

function updateCoolingMetricCell(cell, value, datatype) {
  const valueEl = cell.querySelector('.value');
  if (!valueEl) return;

  if (cell.dataset.coolingKind === 'status') {
    const boolVal = value === true || value === 'true' || value === '1' || value === 1;
    const offVal = value === false || value === 'false' || value === '0' || value === 0 || value === '';
    const onClass = cell.dataset.onClass || 'text-danger';
    const offClass = cell.dataset.offClass || 'text-success';
    valueEl.classList.remove('text-success', 'text-danger', 'text-secondary');
    if (boolVal) {
      valueEl.textContent = cell.dataset.stateOn || 'On';
      onClass.split(' ').forEach((cls) => valueEl.classList.add(cls));
    } else if (offVal) {
      valueEl.textContent = cell.dataset.stateOff || 'Off';
      offClass.split(' ').forEach((cls) => valueEl.classList.add(cls));
    } else if (typeof resolveDisplayValue === 'function') {
      const resolved = resolveDisplayValue(cell, value, datatype);
      valueEl.textContent = resolved.text;
    } else {
      valueEl.textContent = String(value ?? '--');
    }
    return;
  }

  const resolved = typeof resolveDisplayValue === 'function'
    ? resolveDisplayValue(cell, value, datatype)
    : { text: String(value ?? '--') };
  valueEl.textContent = resolved.text;
}

function updateCoolingPanelCells(deviceId, metricKey, value, datatype) {
  const cells = document.querySelectorAll(
    `.widget-cooling-panel [data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );
  cells.forEach((cell) => updateCoolingMetricCell(cell, value, datatype));
}
