// --- Bar Chart Widget (Form + Preview) ---

function renderBarChartPreview(container, config) {
  container.innerHTML = `
    <div class="text-center">
      <div class="small text-secondary mb-1">${config.label || '--'}</div>
      <div style="height: 240px; max-height: 240px; position: relative;">
        <canvas id="barChartPreviewCanvas"></canvas>
      </div>
    </div>
  `;

  const canvas = document.getElementById('barChartPreviewCanvas');
  if (!canvas || typeof Chart === 'undefined') return;

  const ctx = canvas.getContext('2d');
  const items = (config.items || []).slice(0, Math.max(1, Number(config.limit) || 8));
  const labels = items.map((item, idx) => item.label || `Series ${idx + 1}`);
  const values = items.map(() => 10 + Math.random() * 90);
  const indexAxis = config.orientation === 'horizontal' ? 'y' : 'x';
  const setpointValue = config.setpoint_value === '' || config.setpoint_value === undefined ? null : Number(config.setpoint_value);
  const setpointColor = config.setpoint_color || '#f59e0b';
  const overColor = config.bar_color_over || '#ef4444';
  const underColor = config.bar_color_under || '#f59e0b';
  const colors = items.map((item, idx) => {
    const base = item.color || randomColor();
    if (setpointValue !== null && Number(values[idx]) > setpointValue) return overColor;
    if (setpointValue !== null && Number(values[idx]) < setpointValue) return underColor;
    return base;
  });
  const setpointLinePlugin = {
    id: 'setpointLinePreview',
    afterDatasetsDraw(chart, args, pluginOptions) {
      if (!pluginOptions || pluginOptions.value === null || pluginOptions.value === undefined || !Number.isFinite(pluginOptions.value)) return;
      const { ctx, chartArea, scales } = chart;
      const color = pluginOptions.color || '#f59e0b';
      ctx.save();
      ctx.strokeStyle = color;
      ctx.lineWidth = 2;
      ctx.beginPath();
      if (pluginOptions.indexAxis === 'y') {
        const x = scales.x.getPixelForValue(pluginOptions.value);
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
      } else {
        const y = scales.y.getPixelForValue(pluginOptions.value);
        ctx.moveTo(chartArea.left, y);
        ctx.lineTo(chartArea.right, y);
      }
      ctx.stroke();
      ctx.restore();
    }
  };

  const datasets = [{
    label: config.yTitle || config.label || 'Bar Chart',
    indexAxis,
    data: values,
    backgroundColor: colors,
    borderColor: colors,
    borderWidth: 1,
    borderRadius: 6,
    maxBarThickness: config.orientation === 'horizontal' ? 20 : 28,
  }];

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets
    },
    plugins: [setpointLinePlugin],
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      indexAxis,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              const unit = items[context.dataIndex]?.unit || config.yUnit || '';
              const raw = context.raw;
              const value = typeof raw === 'object'
                ? (raw?.x ?? raw?.y ?? '')
                : (raw ?? context.parsed?.y ?? context.parsed?.x ?? '');
              return `${context.label}: ${value}${unit ? ' ' + unit : ''}`;
            }
          }
        },
        setpointLinePreview: {
          value: setpointValue,
          color: setpointColor,
          indexAxis
        }
      },
      scales: {
        x: {
          min: indexAxis === 'y' && config.yMin !== '' && config.yMin !== undefined ? Number(config.yMin) : undefined,
          max: indexAxis === 'y' && config.yMax !== '' && config.yMax !== undefined ? Number(config.yMax) : undefined,
          ticks: { color: '#94a3b8' }
        },
        y: {
          ticks: { color: '#94a3b8' }
        }
      }
    }
  });
}

function addBarChartSeries() {
  const tmpl = document.getElementById('barchart-series-template')?.innerHTML;
  if (!tmpl) return;
  const wrapper = document.createElement('div');
  wrapper.innerHTML = tmpl;
  const newItem = wrapper.firstElementChild;
  document.getElementById('barchart-series-list')?.appendChild(newItem);
  initBarChartSeriesRow(newItem);

  const colorInput = newItem.querySelector('input[name="items[][color]"]');
  if (colorInput && !colorInput.value) colorInput.value = randomColor();

  updatePreviewFromForm();
}

function initBarChartSeriesRow(row) {
  if (!row) return;

  const deviceSelect = row.querySelector('.device-select');
  const metricSelect = row.querySelector('.metric-select');

  if (deviceSelect) {
    loadDeviceOptions(deviceSelect);
    deviceSelect.addEventListener('change', () => {
      loadMetricOptions(deviceSelect.value, metricSelect);
      updatePreviewFromForm();
    });
  }

  row.querySelectorAll('input, select').forEach(input => {
    input.addEventListener('input', updatePreviewFromForm);
    input.addEventListener('change', updatePreviewFromForm);
  });
}

async function duplicateBarChartSeries(button) {
  const source = button?.closest('.series-item');
  if (!source) return;

  const list = document.getElementById('barchart-series-list');
  if (!list) return;

  const wrapper = document.createElement('div');
  wrapper.innerHTML = document.getElementById('barchart-series-template')?.innerHTML || '';
  const clone = wrapper.firstElementChild;
  if (!clone) return;

  source.insertAdjacentElement('afterend', clone);
  initBarChartSeriesRow(clone);

  const label = source.querySelector('[name="items[][label]"]')?.value || '';
  const unit = source.querySelector('[name="items[][unit]"]')?.value || '';
  const color = source.querySelector('[name="items[][color]"]')?.value || randomColor();
  const deviceId = source.querySelector('[name="items[][device_id]"]')?.value || '';
  const metricKey = source.querySelector('[name="items[][metric_key]"]')?.value || '';

  clone.querySelector('[name="items[][label]"]').value = label;
  clone.querySelector('[name="items[][unit]"]').value = unit;
  clone.querySelector('[name="items[][color]"]').value = color;

  const cloneDeviceSelect = clone.querySelector('[name="items[][device_id]"]');
  const cloneMetricSelect = clone.querySelector('[name="items[][metric_key]"]');

  await loadDeviceOptions(cloneDeviceSelect, deviceId);
  cloneDeviceSelect.value = deviceId;
  await loadMetricOptions(deviceId, cloneMetricSelect, metricKey);
  cloneMetricSelect.value = metricKey;

  updatePreviewFromForm();
}

function collectBarChartConfig() {
  const form = document.getElementById('widget-config-form');
  const config = {
    label: form.querySelector('#cfg-label')?.value || '',
    yTitle: form.querySelector('#cfg-yTitle')?.value || '',
    yUnit: form.querySelector('#cfg-yUnit')?.value || '',
    yMin: form.querySelector('#cfg-yMin')?.value || '',
    yMax: form.querySelector('#cfg-yMax')?.value || '',
    sort_order: form.querySelector('#cfg-sort_order')?.value || 'desc',
    limit: form.querySelector('#cfg-limit')?.value || '',
    orientation: form.querySelector('#cfg-orientation')?.value || 'horizontal',
    setpoint_value: form.querySelector('#cfg-setpoint_value')?.value || '',
    setpoint_color: form.querySelector('#cfg-setpoint_color')?.value || '#f59e0b',
    bar_color_over: form.querySelector('#cfg-bar_color_over')?.value || '#ef4444',
    bar_color_under: form.querySelector('#cfg-bar_color_under')?.value || '#f59e0b',
    items: []
  };

  form.querySelectorAll('#barchart-series-list .series-item').forEach(item => {
    config.items.push({
      label: item.querySelector('[name="items[][label]"]')?.value || '',
      unit: item.querySelector('[name="items[][unit]"]')?.value || '',
      color: item.querySelector('[name="items[][color]"]')?.value || '',
      device_id: item.querySelector('[name="items[][device_id]"]')?.value || '',
      metric_key: item.querySelector('[name="items[][metric_key]"]')?.value || ''
    });
  });

  return config;
}
