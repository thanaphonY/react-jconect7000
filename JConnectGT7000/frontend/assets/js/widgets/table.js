function renderTablePreview(container, config) {
  const items = config.items || [];
  if (!items.length) {
    container.innerHTML = '<div class="text-muted small text-center p-2">Table (no metrics configured)</div>';
    return;
  }

  const rows = items.map(item => `
    <tr>
      <td>${item.label || item.metric_key || '-'}</td>
      <td>--</td>
      <td>${item.unit || ''}</td>
      <td>--</td>
    </tr>
  `).join('');

  container.innerHTML = `
    <div class="small text-muted mb-1">${config.label || 'Metrics Table'}</div>
    <table class="table table-sm table-bordered mb-0">
      <thead>
        <tr class="table-light">
          <th>Label</th>
          <th>Value</th>
          <th>Unit</th>
          <th>Last Update</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;
}

function appendTableMetricRow() {
  const tmpl = document.getElementById('table-metric-template');
  if (!tmpl) return null;

  const wrapper = document.createElement('div');
  wrapper.innerHTML = tmpl.innerHTML;
  const newItem = wrapper.firstElementChild;
  document.getElementById('table-metric-list').appendChild(newItem);
  bindTableMetricRow(newItem);
  return newItem;
}

function bindTableMetricRow(rowEl) {
  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');

  if (deviceSelect && !deviceSelect.dataset.bound) {
    deviceSelect.dataset.bound = '1';
    deviceSelect.addEventListener('change', () => {
      loadMetricOptions(deviceSelect.value, metricSelect);
    });
  }

  if (metricSelect && !metricSelect.dataset.bound) {
    metricSelect.dataset.bound = '1';
    metricSelect.addEventListener('change', () => {
      if (typeof updatePreviewFromForm === 'function') {
        updatePreviewFromForm();
      }
    });
  }
}

async function fillTableMetricRow(rowEl, item = {}) {
  if (!rowEl) return;

  const deviceSelect = rowEl.querySelector('.device-select');
  const metricSelect = rowEl.querySelector('.metric-select');
  const labelInput = rowEl.querySelector('[name="items[][label]"]');
  const unitInput = rowEl.querySelector('[name="items[][unit]"]');

  if (labelInput && item.label !== undefined) labelInput.value = item.label || '';
  if (unitInput && item.unit !== undefined) unitInput.value = item.unit || '';

  if (!deviceSelect) return;

  await loadDeviceOptions(deviceSelect, item.device_id || '');
  deviceSelect.value = item.device_id || '';

  if (item.device_id) {
    await loadMetricOptions(item.device_id, metricSelect, item.metric_key || '');
    if (item.metric_key && metricSelect) {
      metricSelect.value = item.metric_key;
    }
  }
}

async function addTableMetric(prefill) {
  const rowEl = appendTableMetricRow();
  if (!rowEl) return;

  await fillTableMetricRow(rowEl, prefill || {});

  if (typeof updatePreviewFromForm === 'function') {
    updatePreviewFromForm();
  }
}

async function duplicateTableMetric(btn) {
  const source = btn.closest('.series-item');
  if (!source) return;

  const deviceId = source.querySelector('[name="items[][device_id]"]')?.value || '';
  if (!deviceId) {
    alert('Please select a device first.');
    return;
  }

  const rowEl = appendTableMetricRow();
  await fillTableMetricRow(rowEl, { device_id: deviceId });

  if (typeof updatePreviewFromForm === 'function') {
    updatePreviewFromForm();
  }
}
