function renderPowerMeterPreview(container, config) {
  const label = config.label || 'Power Meter';
  container.innerHTML = `
    <div class="widget-power-meter border rounded p-2">
      <div class="d-flex justify-content-between mb-2">
        <div class="fw-semibold small">${label}</div>
        <span class="badge bg-secondary">Online</span>
      </div>
      <div class="text-center mb-2 py-1 rounded bg-body-secondary">
        <div class="small text-secondary">Active Power</div>
        <div class="fs-5 fw-bold">8.50 <span class="small">kW</span></div>
      </div>
      <div class="row small fw-semibold text-secondary mb-1">
        <div class="col-3">Phase</div>
        <div class="col-4 text-end">Voltage</div>
        <div class="col-5 text-end">Current</div>
      </div>
      ${['R', 'S', 'T'].map((phase) => `
        <div class="row small mb-1">
          <div class="col-3 text-secondary">${phase}</div>
          <div class="col-4 text-end">23${phase === 'S' ? '1' : '0'} V</div>
          <div class="col-5 text-end">12.${phase === 'T' ? '1' : phase === 'S' ? '8' : '5'} A</div>
        </div>
      `).join('')}
    </div>
  `;
}

function updateWidgetMetricCell(cell, value, datatype) {
  const valueEl = cell.querySelector('.value');
  if (!valueEl) return;
  const resolved = typeof resolveDisplayValue === 'function'
    ? resolveDisplayValue(cell, value, datatype)
    : { text: String(value ?? '--') };
  valueEl.textContent = resolved.text;
}

function updatePowerMeterCells(deviceId, metricKey, value, datatype) {
  const cells = document.querySelectorAll(
    `.widget-power-meter [data-device-id="${deviceId}"][data-metric-key="${metricKey}"]`
  );
  cells.forEach((cell) => updateWidgetMetricCell(cell, value, datatype));
}
