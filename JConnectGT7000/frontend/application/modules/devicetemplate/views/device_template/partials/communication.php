   <!-- Communication Settings -->
   <div class="card mt-3">
          <div class="card-header bg-info">
            <h5 class="mb-0 text-white"><?php echo lang('communication_configuration') ?></h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label"><?php echo lang('communication_config') ?></label>
              <div id="comm-config-fields"></div>
              <button type="button" class="btn btn-sm btn-outline-info mt-2" id="btn-add-comm-field" onclick="addCommConfigField()"><?php echo lang('add_field') ?></button>
              <input type="hidden" name="communication_config" id="communication_config_json" 
                     value="<?= isset($template) ? htmlspecialchars($template->communication_config) : '{}' ?>">
            </div>
          </div>
        </div>

<script>
let commConfigData = <?= isset($template) && $template->communication_config ? json_encode(json_decode($template->communication_config), JSON_UNESCAPED_UNICODE) : '{}' ?>;
if (!commConfigData || typeof commConfigData !== 'object' || Array.isArray(commConfigData)) {
  commConfigData = {};
}

const RTSP_FIELD_DEFS = [
  { key: 'ip', label: 'IP Address', type: 'text', placeholder: '172.16.180.42' },
  { key: 'port', label: 'REST Port', type: 'number', placeholder: '80' },
  { key: 'rtsp_port', label: 'RTSP Port', type: 'number', placeholder: '554' },
  { key: 'rtsp_url', label: 'RTSP Path', type: 'text', placeholder: '/Streaming/Channels/102' },
  { key: 'snapshot_url', label: 'Snapshot Path', type: 'text', placeholder: '/ISAPI/Streaming/channels/101/picture' },
  { key: 'transport', label: 'Transport', type: 'select', options: [['tcp', 'TCP'], ['udp', 'UDP']] },
  { key: 'username', label: 'Username', type: 'text', placeholder: '' },
  { key: 'password', label: 'Password', type: 'password', placeholder: '' }
];

function getSelectedProtocol() {
  return document.getElementById('protocol')?.value || '';
}

function escAttr(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');
}

function renderCommConfigFields() {
  const container = document.getElementById('comm-config-fields');
  const addBtn = document.getElementById('btn-add-comm-field');
  const protocol = getSelectedProtocol();
  container.innerHTML = '';

  if (protocol === 'rtsp') {
    if (addBtn) addBtn.style.display = 'none';
    renderRtspCommFields(container);
  } else {
    if (addBtn) addBtn.style.display = '';
    renderGenericCommFields(container);
  }

  updateCommConfigJSON();
}

function renderRtspCommFields(container) {
  // Keep protocol marker for dataedge routing
  commConfigData.protocol = 'rtsp';

  RTSP_FIELD_DEFS.forEach(function (def) {
    const value = commConfigData[def.key] != null ? String(commConfigData[def.key]) : '';
    let inputHtml = '';
    if (def.type === 'select') {
      inputHtml = `<select class="form-select" onchange="updateRtspField('${def.key}', this.value)">` +
        (def.options || []).map(function (opt) {
          const selected = value === opt[0] || (!value && opt[0] === 'tcp') ? 'selected' : '';
          return `<option value="${opt[0]}" ${selected}>${opt[1]}</option>`;
        }).join('') +
        `</select>`;
    } else {
      inputHtml = `<input type="${def.type}" class="form-control" value="${escAttr(value)}"
        placeholder="${escAttr(def.placeholder || '')}"
        ${def.type === 'password' ? 'autocomplete="new-password"' : ''}
        oninput="updateRtspField('${def.key}', this.value)">`;
    }
    container.innerHTML += `
      <div class="mb-3">
        <label class="form-label">${def.label}</label>
        ${inputHtml}
      </div>
    `;
  });
}

function renderGenericCommFields(container) {
  Object.entries(commConfigData).forEach(([key, value], index) => {
    container.innerHTML += `
      <div class="row mb-2" data-index="${index}">
        <div class="col-md-5">
          <input type="text" class="form-control" placeholder="Key" value="${escAttr(key)}" onchange="updateCommConfigKey(${index}, this.value)">
        </div>
        <div class="col-md-5">
          <input type="text" class="form-control" placeholder="Value" value="${escAttr(value)}" onchange="updateCommConfigValue(${index}, this.value)">
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-danger btn-sm" onclick="removeCommConfigField(${index})"><?php echo lang('remove') ?></button>
        </div>
      </div>
    `;
  });
}

function updateRtspField(key, value) {
  commConfigData[key] = value;
  commConfigData.protocol = 'rtsp';
  updateCommConfigJSON();
}

function updateCommConfigKey(index, newKey) {
  const oldKey = Object.keys(commConfigData)[index];
  const value = commConfigData[oldKey];
  delete commConfigData[oldKey];
  commConfigData[newKey] = value;
  renderCommConfigFields();
}

function updateCommConfigValue(index, newValue) {
  const key = Object.keys(commConfigData)[index];
  commConfigData[key] = newValue;
  updateCommConfigJSON();
}

function removeCommConfigField(index) {
  const key = Object.keys(commConfigData)[index];
  delete commConfigData[key];
  renderCommConfigFields();
}

function addCommConfigField() {
  let newKey = "key" + (Object.keys(commConfigData).length + 1);
  while (commConfigData[newKey]) newKey += "_";
  commConfigData[newKey] = "";
  renderCommConfigFields();
}

function updateCommConfigJSON() {
  document.getElementById('communication_config_json').value = JSON.stringify(commConfigData);
}

// Auto-populate common fields based on protocol
function populateCommonFields() {
  const protocol = document.getElementById('protocol').value;
  
  if (protocol === 'modbus_tcp' || protocol === 'modbus_rtu') {
    if (!commConfigData.host) commConfigData.host = '192.168.1.100';
    if (!commConfigData.port) commConfigData.port = '502';
    if (!commConfigData.slave_id) commConfigData.slave_id = '1';
  } else if (protocol === 'snmp') {
    if (!commConfigData.host) commConfigData.host = '192.168.1.100';
    if (!commConfigData.community) commConfigData.community = 'public';
    if (!commConfigData.version) commConfigData.version = '2c';
  } else if (protocol === 'mqtt') {
    if (!commConfigData.broker) commConfigData.broker = 'mqtt://localhost:1883';
    if (!commConfigData.topic) commConfigData.topic = 'device/data';
    if (!commConfigData.username) commConfigData.username = '';
    if (!commConfigData.password) commConfigData.password = '';
  } else if (protocol === 'visual') {
    commConfigData = { protocol: 'visual' };
  } else if (protocol === 'gateway_endpoint') {
    if (!commConfigData.source_kind) commConfigData.source_kind = 'digital_input';
    commConfigData.protocol = 'gateway_endpoint';
  } else if (protocol === 'rtsp') {
    // Reset to RTSP-shaped config (keep existing values when editing)
    const next = {
      protocol: 'rtsp',
      ip: commConfigData.ip || '192.168.1.100',
      port: commConfigData.port || '80',
      rtsp_port: commConfigData.rtsp_port || '554',
      rtsp_url: (/^rtsp:\/\//i.test(String(commConfigData.rtsp_url || ''))
        ? '/Streaming/Channels/102'
        : (commConfigData.rtsp_url || '/Streaming/Channels/102')),
      snapshot_url: (/^https?:\/\//i.test(String(commConfigData.snapshot_url || ''))
        ? '/ISAPI/Streaming/channels/101/picture'
        : (commConfigData.snapshot_url || '/ISAPI/Streaming/channels/101/picture')),
      transport: commConfigData.transport || 'tcp',
      username: commConfigData.username || '',
      password: commConfigData.password || ''
    };
    commConfigData = next;
  }
  
  renderCommConfigFields();
}

document.addEventListener('DOMContentLoaded', function() {
  // If template already RTSP, normalize field shape once
  if (getSelectedProtocol() === 'rtsp') {
    populateCommonFields();
  } else {
    renderCommConfigFields();
  }
  
  // Listen for protocol changes
  document.getElementById('protocol').addEventListener('change', populateCommonFields);
});
</script>
