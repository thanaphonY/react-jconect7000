
function renderTempHumBoxPreview(container, config) {
    container.innerHTML = `
      
  <div class="row gx-3 gy-2 align-items-center text-center h-100" id="tempHumidityLayout">
     <div class="fs-5 text-center mb-1">${config.label || '-'}</div>
    <!-- Temperature -->
    <div class="col item-temp">
      <div class="icon mb-1 text-success fs-5"><i class="bi bi-thermometer-half"></i></div>
      <div class="value fs-5 fw-bold text-center" data-metric-key="Temperature">-°C</div>
    </div>

    <!-- Humidity -->
    <div class="col item-humidity">
      <div class="icon mb-1 text-primary fs-5"><i class="bi bi-moisture"></i></div>
      <div class="value fs-5 fw-bold text-center" data-metric-key="Humidity">-%rH</div>
    </div>

  </div>

    `;
  }

  