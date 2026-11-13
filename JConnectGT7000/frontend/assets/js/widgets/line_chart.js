
// --- Line Chart Widget (Form + Preview + AJAX) ---

function renderLineChartConfigPanel(container, config) {
  container.innerHTML = `
    <div class="text-center">
      <div class="small text-secondary mb-1">${config.label || '--'}</div>
      <canvas id="lineChartCanvas" height="150"></canvas>
    </div>
  `;
  /*
    const ctx = document.getElementById('lineChartCanvas').getContext('2d');
    const datasets = (config.items || []).map((item, idx) => ({
      label: item.label || `Series ${idx + 1}`,
      data: generateDummyData(),
      borderColor: item.color || '#007bff',
      borderWidth: 2,
      fill: false
    }));
  
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['-6h', '-4h', '-2h', 'Now'],
        datasets: datasets
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            min: config.min ?? undefined,
            max: config.max ?? undefined,
            ticks: { precision: 0 }
          }
        }
      }
    });*/
}

function randomColor() {
  const letters = '0123456789ABCDEF';
  let color = '#';
  for (let i = 0; i < 6; i++) {
    color += letters[Math.floor(Math.random() * 16)];
  }
  return color;
}


function addLinechartSeries() {
  const tmpl = document.getElementById('linechart-series-template').innerHTML;
  const wrapper = document.createElement('div');
  wrapper.innerHTML = tmpl;
  const newItem = wrapper.firstElementChild;
  document.getElementById('linechart-series-list').appendChild(newItem);

  // Load device list into the new select
  const deviceSelect = newItem.querySelector('.device-select');
  const metricSelect = newItem.querySelector('.metric-select');
  const colorInput = newItem.querySelector('input[name="items[][color]"]');
  colorInput.value = randomColor();
  if (deviceSelect) {
    loadDeviceOptions(deviceSelect);
    // Load metrics when device changes
    deviceSelect.addEventListener('change', () => {
      loadMetricOptions(deviceSelect.value, metricSelect);
    });
  }

  updatePreviewFromForm();
}

async function loadDeviceOptions(select, selectedId = "") {
  try {
    const response = await fetch('/api/devices');
    const json = await response.json();
    const devices = json.devices || [];

    select.innerHTML = '<option value="">Select Device</option>' +
      devices.map(d =>
        `<option value="${d.id}" ${d.id == selectedId ? "selected" : ""}>${d.name}</option>`
      ).join('');
  } catch (err) {
    console.error('Failed to load devices:', err);
  }
}

async function loadMetricOptions(deviceId, select, selectedKey = "") {
  try {
    const response = await fetch(`/api/metrics/${deviceId}`);
    const json = await response.json();
    const metrics = json.metrics || [];

    select.innerHTML = '<option value="">Select Metric</option>' +
      metrics.map(m =>
        `<option value="${m.key}" data-unit="${m.unit}" data-label="${m.label}" ${m.key == selectedKey ? "selected" : ""}>${m.label || m.key}</option>`
      ).join('');

    select.addEventListener('change', () => {
      const selected = select.options[select.selectedIndex];
      const unit = selected.getAttribute('data-unit');
      const label = selected.getAttribute('data-label');

      const row = select.closest('.series-item');
      if (row) {
        const unitInput = row.querySelector('input[name="items[][unit]"]');
        const labelInput = row.querySelector('input[name="items[][label]"]');

        if (unitInput && unit) {
         if (unitInput.value === '')  // Only set if empty
          unitInput.value = unit;
        }
        if (labelInput && label) {
          if (labelInput.value === '') labelInput.value = label;
        }

      }
    });

  } catch (err) {
    console.error('Failed to load metrics:', err);
  }
}


function handleWidgetTypeChange(type) {
  document.querySelectorAll('.widget-type-section').forEach(section => {
    section.style.display = section.dataset.widgetType === type ? 'block' : 'none';
  });
}
/*
function updatePreviewFromDynamicForm() {
  const previewBox = document.getElementById("widget-preview-box");
  if (!previewBox) return;
  const config = collectLineChartConfig();
  
}*/

// --- Preview Renderer ---
function renderLineChartPreview(container, config) {
  container.innerHTML = `
    <div class="text-center">
      <div class="small text-secondary mb-1">${config.label || '--'}</div>
      <canvas id="lineChartCanvas" height="150"></canvas>
    </div>
  `;

  const ctx = document.getElementById('lineChartCanvas').getContext('2d');
  const datasets = (config.items || []).map((item, idx) => ({
    label: item.label || `Series ${idx + 1}`,
    data: generateDummyData(),
    borderColor: item.color || '#007bff',
    borderWidth: 2,
    fill: false
  }));

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['-6h', '-4h', '-2h', 'Now'],
      datasets: datasets
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          min: config.min ?? undefined,
          max: config.max ?? undefined,
          ticks: { precision: 0 }
        }
      }
    }
  });
}

function generateDummyData() {
  return [10 + Math.random() * 5, 15 + Math.random() * 5, 18 + Math.random() * 5, 20 + Math.random() * 5];
}
