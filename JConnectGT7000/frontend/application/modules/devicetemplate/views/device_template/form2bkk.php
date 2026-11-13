<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h1><?= isset($template) ? lang('edit') : lang('add') ?> <?php echo lang(' device_template') ?> </h1>
        <a href="<?= base_url('devicetemplate') ?>" class="btn btn-secondary">
          <i class="bi bi-arrow-left"></i> <?php echo lang('back') ?>
        </a>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <form method="post" enctype="multipart/form-data">
        <div class="card card-primary">
          <div class="card-header">
            <h5 class="mb-0"><?php echo lang('general_information') ?> </h5>
          </div>
          <div class="card-body">
            <label for="image" class="form-label"><?php echo lang('image') ?></label>
            
            <?php if (!empty($template->image)): ?>
              <div class="mt-2">
                <img src="<?= base_url('uploads/device_templates/' . $template->image) ?>" alt="Current Image" style="max-width: 200px;">
              </div>
            <?php endif; ?>
            <input type="file" class="form-control" name="image" id="image" accept="image/*">
            <div class="mb-3">
              <label for="name" class="form-label"> <?php echo lang('template_name') ?></label>
              <input type="text" class="form-control" name="name" id="name" required
                value="<?= isset($template) ? htmlspecialchars($template->name) : '' ?>">
            </div>

            <div class="mb-3">
              <label for="description" class="form-label"><?php echo lang('description') ?></label>
              <textarea class="form-control" name="description" id="description" rows="3"><?= isset($template) ? htmlspecialchars($template->description) : '' ?></textarea>
            </div>

            <div class="mb-3">
              <label for="protocol" class="form-label"><?php echo lang('protocol') ?></label>
              <select class="form-select" name="protocol" id="protocol" required>
                <option value="">-- Select Protocol --</option>
                <?php
                $protocols = ['modbus_tcp', 'modbus_rtu', 'snmp', 'mqtt', 'bacnet'];
                foreach ($protocols as $p) {
                  $selected = isset($template) && $template->protocol === $p ? 'selected' : '';
                  echo "<option value='$p' $selected>$p</option>";
                }
                ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Communication Settings -->
        <div class="card mt-3">
          <div class="card-header bg-info">
            <h5 class="mb-0 text-white"><?php echo lang('communication_config') ?></h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="communication_config" class="form-label"><?php echo lang('communication_config') ?> </label>
              <textarea class="form-control" name="communication_config" id="communication_config" rows="5"><?= isset($template) ? json_encode(json_decode($template->communication_config), JSON_PRETTY_PRINT) : '{}' ?></textarea>
            </div>
          </div>
        </div>

        <!-- Device Information -->
        <div class="card mt-3">
          <div class="card-header bg-secondary">
            <h5 class="mb-0 text-white"><?php echo lang('device_information') ?> </h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label"><?php echo lang('device_information') ?> </label>
              <div id="device-info-fields"></div>
              <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addDeviceInfoField()"><?php echo lang('add_field') ?></button>
              <input type="hidden" name="device_information" id="device_information_json">
            </div>
          </div>
        </div>

        <!-- Metrics -->
        <div class="card mt-3">
          <div class="card-header bg-success">
            <h5 class="mb-0 text-white"><?php echo lang('metrics_definition') ?></h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label"><?php echo lang('metrics') ?></label>
              <div id="metrics-container"></div>
              <button type="button" class="btn btn-outline-primary mt-2" onclick="addMetric()"><?php echo lang('add_metrics') ?></button>
              <input type="hidden" name="metrics" id="metrics_json">
            </div>
          </div>
        </div>

        <script>
          let metrics = [];

          const protocolFields = {
            modbus_tcp: ['address', 'type'],
            modbus_rtu: ['address', 'type'],
            snmp: ['oid', 'type'],
            mqtt: ['topic', 'json_path'],
            bacnet: ['object_id', 'property_id']
          };

          function renderMetrics() {
            const container = document.getElementById('metrics-container');
            const globalProtocol = document.getElementById('protocol')?.value || 'modbus_tcp';
            container.innerHTML = '';

            metrics.forEach((m, i) => {
              m.protocol = globalProtocol;
              const dynamicFields = protocolFields[globalProtocol] || [];

              let html = `
      <div class="border rounded p-3 mb-3" data-index="${i}">
        <div class="row mb-2">
          <div class="col-md-4"><input class="form-control" placeholder="Key" value="${m.key || ''}" onchange="updateMetric(${i}, 'key', this.value)"></div>
          <div class="col-md-4"><input class="form-control" placeholder="Label" value="${m.label || ''}" onchange="updateMetric(${i}, 'label', this.value)"></div>
          <div class="col-md-4"><input class="form-control" placeholder="Unit" value="${m.unit || ''}" onchange="updateMetric(${i}, 'unit', this.value)"></div>
        </div>

        <div class="row mb-2">
          <div class="col-md-4">
            <input class="form-control" placeholder="Datatype (int/float)" value="${m.datatype || ''}" onchange="updateMetric(${i}, 'datatype', this.value)">
          </div>
          <div class="col-md-4">
            <input class="form-control" placeholder="Scale" value="${m.scale || 1}" onchange="updateMetric(${i}, 'scale', parseFloat(this.value))">
          </div>
        </div>
    `;

              if (
                (globalProtocol === 'modbus_tcp' || globalProtocol === 'modbus_rtu') ||
                (globalProtocol === 'snmp' && m.type !== 'trap') ||
                globalProtocol === 'bacnet'
              ) {
                html += `
        <div class="row mb-2">
          <div class="col-md-4">
            <input class="form-control metric-pull-rate" placeholder="Pull rate (ms)" value="${m.pull_rate || 10000}" onchange="updateMetric(${i}, 'pull_rate', parseInt(this.value))">
          </div>
        </div>
      `;
              }

              dynamicFields.forEach(field => {
                html += `
        <div class="row mb-2">
          <div class="col-md-6">
            <input class="form-control" placeholder="${field}" value="${m[field] || ''}" onchange="updateMetric(${i}, '${field}', this.value)">
          </div>
        </div>
      `;
              });

              

              html += `
        <div class="text-end">
          <button type="button" class="btn btn-danger btn-sm" onclick="removeMetric(${i})">Remove</button>
        </div>
      </div>
    `;

              container.innerHTML += html;
            });

            updateMetricsJSON();
          }

          function updateMetric(index, field, value) {
            metrics[index][field] = value;
            updateMetricsJSON();
          }

          function removeMetric(index) {
            metrics.splice(index, 1);
            renderMetrics();
          }

          function addMetric() {
            const globalProtocol = document.getElementById('protocol')?.value || 'modbus_tcp';
            const defaultFields = {
              modbus_tcp: {
                address: '',
                type: 'holding_register'
              },
              modbus_rtu: {
                address: '',
                type: 'holding_register'
              },
              snmp: {
                oid: '',
                type: 'snmp_get'
              },
              mqtt: {
                topic: '',
                json_path: ''
              },
              bacnet: {
                object_id: '',
                property_id: ''
              }
            };

            const newMetric = {
              key: '',
              label: '',
              unit: '',
              protocol: globalProtocol,
              datatype: 'int',
              scale: 1,
              pull_rate: (globalProtocol === 'mqtt' || globalProtocol === 'snmp') ? 0 : 10000,
              ...defaultFields[globalProtocol]
            };

            metrics.push(newMetric);
            renderMetrics();
          }

          function updateMetricsJSON() {
            document.getElementById('metrics_json').value = JSON.stringify(metrics, null, 2);
          }

          document.addEventListener('DOMContentLoaded', () => {
            metrics = <?= isset($template) && $template->metrics ? json_encode(json_decode($template->metrics), JSON_UNESCAPED_UNICODE) : '[]' ?>;
            renderMetrics();
            document.getElementById('protocol').addEventListener('change', () => {
              renderMetrics();
            });
          });
        </script>


        <!-- Dashboard Layout -->
        <div class="card mt-3">
          <div class="card-header bg-dark">
            <h5 class="mb-0 text-white"> <?php echo lang('default_dashboard_layout') ?></h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="default_dashboard_layout" class="form-label"><?php echo lang('dashboard_layout') ?></label>
              <textarea class="form-control" name="default_dashboard_layout" id="default_dashboard_layout" rows="5"><?= isset($template) ? json_encode(json_decode($template->default_dashboard_layout), JSON_PRETTY_PRINT) : '{}' ?></textarea>
            </div>
          </div>
        </div>

        <!-- Status and Actions -->
        <div class="card mt-3">
          <div class="card-body">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                <?= isset($template) && $template->is_active ? 'checked' : '' ?>>
              <label class="form-check-label" for="is_active"><?php echo lang('is_active') ?></label>
            </div>
            <div class="text-end">
              <button type="submit" class="btn btn-success">
                <i class="bi bi-save"></i> <?php echo lang('save') ?>
              </button>
            </div>
          </div>
        </div>

      </form>
    </div>
  </section>
</div>


<script>
  let deviceInfoData = <?= isset($template) && $template->device_information ? json_encode(json_decode($template->device_information), JSON_UNESCAPED_UNICODE) : '{"Brand": "ALLTEC", "Model": "AWLD-01"}' ?>;

  function renderDeviceInfoFields() {
    const container = document.getElementById('device-info-fields');
    container.innerHTML = '';

    Object.entries(deviceInfoData).forEach(([key, value], index) => {
      container.innerHTML += `
      <div class="row mb-2" data-index="${index}">
        <div class="col-md-5">
          <input type="text" class="form-control" placeholder="Key" value="${key}" onchange="updateDeviceInfoKey(${index}, this.value)">
        </div>
        <div class="col-md-5">
          <input type="text" class="form-control" placeholder="Value" value="${value}" onchange="updateDeviceInfoValue(${index}, this.value)">
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-danger" onclick="removeDeviceInfoField(${index})">Remove</button>
        </div>
      </div>
    `;
    });

    updateDeviceInfoJSON();
  }

  function updateDeviceInfoKey(index, newKey) {
    const oldKey = Object.keys(deviceInfoData)[index];
    const value = deviceInfoData[oldKey];
    delete deviceInfoData[oldKey];
    deviceInfoData[newKey] = value;
    renderDeviceInfoFields();
  }

  function updateDeviceInfoValue(index, newValue) {
    const key = Object.keys(deviceInfoData)[index];
    deviceInfoData[key] = newValue;
    updateDeviceInfoJSON();
  }

  function removeDeviceInfoField(index) {
    const key = Object.keys(deviceInfoData)[index];
    delete deviceInfoData[key];
    renderDeviceInfoFields();
  }

  function addDeviceInfoField() {
    let newKey = "key" + (Object.keys(deviceInfoData).length + 1);
    while (deviceInfoData[newKey]) newKey += "_";
    deviceInfoData[newKey] = "";
    renderDeviceInfoFields();
  }

  function updateDeviceInfoJSON() {
    document.getElementById('device_information_json').value = JSON.stringify(deviceInfoData);
  }

  document.addEventListener('DOMContentLoaded', renderDeviceInfoFields);
</script>