<?php
$roleConfig = [];
if (isset($device) && !empty($device->config)) {
    $roleConfig = is_string($device->config) ? json_decode($device->config, true) : $device->config;
    if (is_string($roleConfig)) {
        $roleConfig = json_decode($roleConfig, true);
    }
}
if (!is_array($roleConfig)) {
    $roleConfig = [];
}

$roleComm = [];
if (isset($device) && !empty($device->communication_config)) {
    $roleComm = is_string($device->communication_config)
        ? json_decode($device->communication_config, true)
        : $device->communication_config;
}
if (!is_array($roleComm)) {
    $roleComm = [];
}

$deviceRole = strtolower((string) ($roleConfig['role'] ?? 'native_device'));
if (!in_array($deviceRole, ['native_device', 'gateway', 'endpoint'], true)) {
    $deviceRole = 'native_device';
}
$parentGatewayId = (int) (
    $roleConfig['via_gateway']
    ?? ($roleComm['source']['gateway_device_id'] ?? 0)
);
$gatewayDevices = isset($gateway_devices) && is_array($gateway_devices) ? $gateway_devices : [];
?>

<div class="row">
  <div class="col-md-6 mb-3">
    <label for="device_role" class="form-label">Device Role</label>
    <select name="device_role" id="device_role" class="form-select" onchange="toggleDeviceRoleFields()">
      <option value="native_device" <?= $deviceRole === 'native_device' ? 'selected' : '' ?>>Native</option>
      <option value="gateway" <?= $deviceRole === 'gateway' ? 'selected' : '' ?>>Gateway Collector</option>
      <option value="endpoint" <?= $deviceRole === 'endpoint' ? 'selected' : '' ?>>Gateway Endpoint</option>
    </select>
    <div class="form-text">Gateway Collectors can be selected as parent devices for Gateway Endpoints.</div>
  </div>

  <div class="col-md-6 mb-3" id="parent_gateway_field" style="<?= $deviceRole === 'endpoint' ? '' : 'display:none' ?>">
    <label for="gateway_device_id" class="form-label">Parent Gateway <span class="text-danger">*</span></label>
    <select name="gateway_device_id" id="gateway_device_id" class="form-select" onchange="notifyGatewayContextChanged()" <?= $deviceRole === 'endpoint' ? 'required' : '' ?>>
      <option value="">-- Select Gateway --</option>
      <?php foreach ($gatewayDevices as $gateway): ?>
        <option value="<?= (int) $gateway['id'] ?>" <?= $parentGatewayId === (int) $gateway['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($gateway['name']) ?> (#<?= (int) $gateway['id'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <div class="form-text">Links this endpoint to the collector used by dataedge.</div>
  </div>
</div>

<script>
function toggleDeviceRoleFields() {
  const role = document.getElementById('device_role');
  const field = document.getElementById('parent_gateway_field');
  const gateway = document.getElementById('gateway_device_id');
  if (!role || !field || !gateway) return;
  const isEndpoint = role.value === 'endpoint';
  field.style.display = isEndpoint ? '' : 'none';
  gateway.required = isEndpoint;
  if (!isEndpoint) gateway.value = '';
  const directFields = document.getElementById('direct-communication-fields');
  const endpointNotice = document.getElementById('endpoint-communication-notice');
  if (directFields) {
    directFields.style.display = isEndpoint ? 'none' : '';
    directFields.querySelectorAll('input, select, textarea, button').forEach(function (element) {
      element.disabled = isEndpoint;
    });
  }
  if (endpointNotice) endpointNotice.style.display = isEndpoint ? '' : 'none';
}

function notifyGatewayContextChanged() {
  if (window.deviceMetricBuilder && typeof window.deviceMetricBuilder.refreshRoleContext === 'function') {
    window.deviceMetricBuilder.refreshRoleContext();
  }
}

function applyTemplateDeviceRole(templateData) {
  const role = document.getElementById('device_role');
  if (!role || !templateData) return;
  const templateInfo = templateData.device_information || {};
  const suggested = String(templateInfo.role || '').toLowerCase();
  if (String(templateData.protocol || '').toLowerCase() === 'gateway_endpoint') {
    role.value = 'endpoint';
  } else if (suggested === 'gateway') {
    role.value = 'gateway';
  } else {
    role.value = 'native_device';
  }
  toggleDeviceRoleFields();
  notifyGatewayContextChanged();
}

document.addEventListener('DOMContentLoaded', function () {
  toggleDeviceRoleFields();
  const commContainer = document.getElementById('comm-fields-container');
  if (commContainer && window.MutationObserver) {
    new MutationObserver(toggleDeviceRoleFields).observe(commContainer, { childList: true, subtree: true });
  }
});
</script>
