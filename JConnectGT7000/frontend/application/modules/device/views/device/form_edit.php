<?php
$template_id = isset($template_id) ? $template_id : null;
$template = isset($template) ? $template : null;
$device = isset($device) ? $device : null;
$metrics = isset($metrics) ? $metrics : ($device->metrics ?? ($template->metrics ?? '[]'));


$templateinfo = json_decode($template->device_information ?? '{}', true);
$Brand = $templateinfo['Brand'] ?? '-';
$Model = $templateinfo['Model'] ?? '-';
$Image = $templateinfo['image'] ?? '-';

//var_dump($device)
?>
<!--begin::App Content Header-->
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5> <?php echo lang("edit_device_page") ?> </h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home'); ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('device') ?>"><?php echo lang('devices'); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('edit'); ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<style>
  /* Metrics styles - Dark Theme Support */
  .metric-item {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    background-color: var(--bs-body-bg);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
  }

  .metric-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }

  .metric-item.border-success {
    border-color: #198754;
  }

  .metric-item.border-secondary {
    border-color: #6c757d;
  }

  /* Dark theme for metric headers */
  [data-bs-theme="dark"] .metric-item .bg-light {
    background-color: rgba(255, 255, 255, 0.05) !important;
    border-bottom-color: var(--bs-border-color) !important;
  }

  /* Dark theme for strong text */
  [data-bs-theme="dark"] strong {
    color: var(--bs-body-color);
  }

  /* Dark theme for small text */
  [data-bs-theme="dark"] small {
    color: rgba(255, 255, 255, 0.6);
  }
</style>

<!--begin::App Content-->
<div class="app-content">
  <div class="container-fluid">
    <form method="post" enctype="multipart/form-data" id="wizardForm" action="<?= base_url('device/save/' . $device->id) ?>">
      <input type="hidden" name="device_template_id" value="<?= $device->device_template_id ?>">
      <div class="card">
        <div class="card-header">
          <ul class="nav nav-pills" id="wizardTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#step1" type="button"><?php echo lang("device_info") ?></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#step2" type="button"><?php echo lang("communication") ?></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#step3" type="button"><?php echo lang("metrics") ?></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#step4" type="button"><?php echo lang("confirm") ?></button></li>
          </ul>
        </div>
        <div class="card-body tab-content">
          <div class="tab-pane fade show active" id="step1"><?php include 'partials/device_info_edit.php'; ?></div>
          <div class="tab-pane fade" id="step2"><?php include 'partials/communication_edit.php'; ?></div>
          <div class="tab-pane fade" id="step3"><?php include 'partials/metrics_edit.php'; ?></div>
          <div class="tab-pane fade" id="step4"><?php include 'partials/confirm_edit.php'; ?></div>
        </div>
        <div class="card-footer text-end">
          <button type="submit" class="btn btn-primary"><?php echo lang("update_device") ?></button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php
// Decode ซ้อนเผื่อกรณี metrics เป็น string ซ้อน JSON string
$rawMetrics = $device->metrics;

if (is_string($rawMetrics)) {
  $decoded = json_decode($rawMetrics, true);
  if (is_string($decoded)) {
    $decoded = json_decode($decoded, true); // decode ซ้ำอีกครั้ง
  }
} else {
  $decoded = $rawMetrics;
}
?>


<?php
$rawComm = $device->communication_config;

if (is_string($rawMetrics)) {
  $decodedComm = json_decode($rawComm, true);
  if (is_string($decodedComm)) {
    $decodedComm = json_decode($decodedComm, true); // decode ซ้ำอีกครั้ง
  }
} else {
  $decodedComm = $rawComm;
}
?>



<script>
  function goToStep(index) {
    const tabTrigger = new bootstrap.Tab(document.querySelectorAll('#wizardTabs .nav-link')[index - 1]);
    tabTrigger.show();
  }

  var MD5 = function(d) {
    var r = M(V(Y(X(d), 8 * d.length)));
    return r.toLowerCase()
  };

  function M(d) {
    for (var _, m = "0123456789ABCDEF", f = "", r = 0; r < d.length; r++) _ = d.charCodeAt(r), f += m.charAt(_ >>> 4 & 15) + m.charAt(15 & _);
    return f
  }

  function X(d) {
    for (var _ = Array(d.length >> 2), m = 0; m < _.length; m++) _[m] = 0;
    for (m = 0; m < 8 * d.length; m += 8) _[m >> 5] |= (255 & d.charCodeAt(m / 8)) << m % 32;
    return _
  }

  function V(d) {
    for (var _ = "", m = 0; m < 32 * d.length; m += 8) _ += String.fromCharCode(d[m >> 5] >>> m % 32 & 255);
    return _
  }

  function Y(d, _) {
    d[_ >> 5] |= 128 << _ % 32, d[14 + (_ + 64 >>> 9 << 4)] = _;
    for (var m = 1732584193, f = -271733879, r = -1732584194, i = 271733878, n = 0; n < d.length; n += 16) {
      var h = m,
        t = f,
        g = r,
        e = i;
      f = md5_ii(f = md5_ii(f = md5_ii(f = md5_ii(f = md5_hh(f = md5_hh(f = md5_hh(f = md5_hh(f = md5_gg(f = md5_gg(f = md5_gg(f = md5_gg(f = md5_ff(f = md5_ff(f = md5_ff(f = md5_ff(f, r = md5_ff(r, i = md5_ff(i, m = md5_ff(m, f, r, i, d[n + 0], 7, -680876936), f, r, d[n + 1], 12, -389564586), m, f, d[n + 2], 17, 606105819), i, m, d[n + 3], 22, -1044525330), r = md5_ff(r, i = md5_ff(i, m = md5_ff(m, f, r, i, d[n + 4], 7, -176418897), f, r, d[n + 5], 12, 1200080426), m, f, d[n + 6], 17, -1473231341), i, m, d[n + 7], 22, -45705983), r = md5_ff(r, i = md5_ff(i, m = md5_ff(m, f, r, i, d[n + 8], 7, 1770035416), f, r, d[n + 9], 12, -1958414417), m, f, d[n + 10], 17, -42063), i, m, d[n + 11], 22, -1990404162), r = md5_ff(r, i = md5_ff(i, m = md5_ff(m, f, r, i, d[n + 12], 7, 1804603682), f, r, d[n + 13], 12, -40341101), m, f, d[n + 14], 17, -1502002290), i, m, d[n + 15], 22, 1236535329), r = md5_gg(r, i = md5_gg(i, m = md5_gg(m, f, r, i, d[n + 1], 5, -165796510), f, r, d[n + 6], 9, -1069501632), m, f, d[n + 11], 14, 643717713), i, m, d[n + 0], 20, -373897302), r = md5_gg(r, i = md5_gg(i, m = md5_gg(m, f, r, i, d[n + 5], 5, -701558691), f, r, d[n + 10], 9, 38016083), m, f, d[n + 15], 14, -660478335), i, m, d[n + 4], 20, -405537848), r = md5_gg(r, i = md5_gg(i, m = md5_gg(m, f, r, i, d[n + 9], 5, 568446438), f, r, d[n + 14], 9, -1019803690), m, f, d[n + 3], 14, -187363961), i, m, d[n + 8], 20, 1163531501), r = md5_gg(r, i = md5_gg(i, m = md5_gg(m, f, r, i, d[n + 13], 5, -1444681467), f, r, d[n + 2], 9, -51403784), m, f, d[n + 7], 14, 1735328473), i, m, d[n + 12], 20, -1926607734), r = md5_hh(r, i = md5_hh(i, m = md5_hh(m, f, r, i, d[n + 5], 4, -378558), f, r, d[n + 8], 11, -2022574463), m, f, d[n + 11], 16, 1839030562), i, m, d[n + 14], 23, -35309556), r = md5_hh(r, i = md5_hh(i, m = md5_hh(m, f, r, i, d[n + 1], 4, -1530992060), f, r, d[n + 4], 11, 1272893353), m, f, d[n + 7], 16, -155497632), i, m, d[n + 10], 23, -1094730640), r = md5_hh(r, i = md5_hh(i, m = md5_hh(m, f, r, i, d[n + 13], 4, 681279174), f, r, d[n + 0], 11, -358537222), m, f, d[n + 3], 16, -722521979), i, m, d[n + 6], 23, 76029189), r = md5_hh(r, i = md5_hh(i, m = md5_hh(m, f, r, i, d[n + 9], 4, -640364487), f, r, d[n + 12], 11, -421815835), m, f, d[n + 15], 16, 530742520), i, m, d[n + 2], 23, -995338651), r = md5_ii(r, i = md5_ii(i, m = md5_ii(m, f, r, i, d[n + 0], 6, -198630844), f, r, d[n + 7], 10, 1126891415), m, f, d[n + 14], 15, -1416354905), i, m, d[n + 5], 21, -57434055), r = md5_ii(r, i = md5_ii(i, m = md5_ii(m, f, r, i, d[n + 12], 6, 1700485571), f, r, d[n + 3], 10, -1894986606), m, f, d[n + 10], 15, -1051523), i, m, d[n + 1], 21, -2054922799), r = md5_ii(r, i = md5_ii(i, m = md5_ii(m, f, r, i, d[n + 8], 6, 1873313359), f, r, d[n + 15], 10, -30611744), m, f, d[n + 6], 15, -1560198380), i, m, d[n + 13], 21, 1309151649), r = md5_ii(r, i = md5_ii(i, m = md5_ii(m, f, r, i, d[n + 4], 6, -145523070), f, r, d[n + 11], 10, -1120210379), m, f, d[n + 2], 15, 718787259), i, m, d[n + 9], 21, -343485551), m = safe_add(m, h), f = safe_add(f, t), r = safe_add(r, g), i = safe_add(i, e)
    }
    return Array(m, f, r, i)
  }

  function md5_cmn(d, _, m, f, r, i) {
    return safe_add(bit_rol(safe_add(safe_add(_, d), safe_add(f, i)), r), m)
  }

  function md5_ff(d, _, m, f, r, i, n) {
    return md5_cmn(_ & m | ~_ & f, d, _, r, i, n)
  }

  function md5_gg(d, _, m, f, r, i, n) {
    return md5_cmn(_ & f | m & ~f, d, _, r, i, n)
  }

  function md5_hh(d, _, m, f, r, i, n) {
    return md5_cmn(_ ^ m ^ f, d, _, r, i, n)
  }

  function md5_ii(d, _, m, f, r, i, n) {
    return md5_cmn(m ^ (_ | ~f), d, _, r, i, n)
  }

  function safe_add(d, _) {
    var m = (65535 & d) + (65535 & _);
    return (d >> 16) + (_ >> 16) + (m >> 16) << 16 | 65535 & m
  }

  function bit_rol(d, _) {
    return d << _ | d >>> 32 - _
  }

  // ---------------- CUSTOM FIELDS SCRIPT -----------------
  let customFields = <?= json_encode($custom_fields_json ?? '{}', JSON_UNESCAPED_UNICODE) ?>;

  function renderCustomFields() {
    const container = document.getElementById('custom-fields-container');
    container.innerHTML = '';
    console.log("customFields", customFields);

    if (typeof customFields === 'string') {
      try {
        customFields = JSON.parse(customFields);
      } catch (e) {
        customFields = {};
      }
    }

    if (!customFields || Object.keys(customFields).length === 0) {
      container.innerHTML = '<div class="text-muted">No custom fields defined.</div>';
      return;
    }

    Object.entries(customFields).forEach(([key, value], index) => {
      container.innerHTML += `
        <div class="row mb-2">
          <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" placeholder="Field Name" value="${key}" onchange="updateCustomFieldKey(${index}, this.value)"> 
          </div>
          <div class="col-md-6">
            <input type="text" class="form-control form-control-sm" placeholder="Field Value" value="${value}" onchange="updateCustomFieldValue('${key}', this.value)">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeCustomField('${key}')">ลบ</button>
          </div>
        </div>
      `;
    });

    updateCustomFieldsJSON();
  }

  function addCustomField() {
    if (typeof customFields !== 'object') {
      customFields = {};
    }
    const key = `field_${Object.keys(customFields).length + 1}`;
    customFields[key] = '';
    renderCustomFields();
  }

  function updateCustomFieldKey(oldIndex, newKey) {
    const keys = Object.keys(customFields);
    const oldKey = keys[oldIndex];
    console.log(newKey);
    if (oldKey && newKey && oldKey !== newKey) {
      customFields[newKey] = customFields[oldKey];
      delete customFields[oldKey];
      updateCustomFieldsJSON();
    }
    renderCustomFields();
  }

  function updateCustomFieldValue(key, value) {
    customFields[key] = value;
    updateCustomFieldsJSON();
  }

  function removeCustomField(key) {
    delete customFields[key];
    renderCustomFields();
  }

  function updateCustomFieldsJSON() {
    document.getElementById('device_information_json').value = JSON.stringify(customFields, null, 2);
  }

  const protocol = <?= json_encode($protocol ?? 'modbus_tcp') ?>;

  // ---------------- COMMUNICATION SCRIPT -----------------
  let commConfigRaw = <?= json_encode($device->communication_config ?? '{}', JSON_UNESCAPED_UNICODE) ?>;
  let commConfig;

  try {
    commConfig = typeof commConfigRaw === 'string' ? JSON.parse(commConfigRaw) : commConfigRaw;
    if (Array.isArray(commConfig)) {
      commConfig = {}; // force to object
    }
  } catch (e) {
    commConfig = {};
  }

  // Lore Application Server get from ajax
  async function getLoRaAppServer() {
    const res = await fetch('<?= base_url("lorawan/api/get_app_servers") ?>');
    const data = await res.json();
    return data.result || [];
  }

  async function getLoRaDeviceProfile(tenantId) {
    try {
      const res = await fetch('<?= base_url("lorawan/api_get_device_profile/") ?>' + tenantId);
      const data = await res.json();
      return data.result || [];
    } catch (error) {
      console.error('Error fetching device profiles:', error);
      // Fallback: return empty array if API fails
      return [];
    }
  }

  const commFields = {
    modbus_tcp: ['ip', 'port', 'unit_id'],
    modbus_rtu: ['baudrate', 'parity', 'stop_bits', 'data_bits', 'slave_id', 'port'],
    snmp: ['version', 'community', 'host', 'port', 'max_oids_per_request', 'batch_delay_ms', 'security_level', 'auth_protocol', 'auth_pass', 'priv_protocol', 'priv_pass'],
    mqtt: ['broker_url', 'port', 'username', 'password', 'client_id', 'qos', 'use_tls'],
    bacnet: ['device_id', 'ip', 'port'],
    lorawan: ['application_server', 'device_profile_id', 'dev_eui', 'app_eui', 'app_key', 'netskey', 'appskey'],
    rtsp: ['ip', 'port', 'rtsp_port', 'rtsp_url', 'snapshot_url', 'transport', 'username', 'password']
  };

  async function renderCommFields() {
    const container = document.getElementById('comm-fields-container');
    container.innerHTML = '';
    const fields = commFields[protocol] || [];

    console.log("protocol", protocol);
    console.log("commConfig", commConfig);

    let appServers = [];
    let deviceProfiles = [];

    if (fields.includes('application_server')) {
      appServers = await getLoRaAppServer(); // ดึง app server list
      console.log("appServers", appServers);
    }

    const selectedAppServerId = commConfig?.application_server || '';
    const selectedAppServer = appServers.find(s => s.id === selectedAppServerId);
    const tenantId = selectedAppServer?.tenant_id ?? '';

    console.log("selectedAppServerId", selectedAppServerId);
    console.log("selectedAppServer", selectedAppServer);
    console.log("tenantId", tenantId);

    if (fields.includes('device_profile_id') && tenantId) {
      console.log("Fetching device profiles for tenantId:", tenantId);
      const dpResponse = await getLoRaDeviceProfile(tenantId);
      console.log("device_profile_id response:", dpResponse);
      deviceProfiles = dpResponse || [];
    } else {
      console.log("Skipping device profile fetch - tenantId:", tenantId, "fields includes device_profile_id:", fields.includes('device_profile_id'));
    }

    fields.forEach(field => {
      const label = field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      const value = commConfig?.[field] ?? '';

      if (field === 'application_server') {
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">${label}</label>
            <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value); renderCommFields();">
              <option value="">Select Application Server</option>
              ${appServers.map(server => `
                <option value="${server.id}" ${value == server.id ? 'selected' : ''}>${server.name}</option>
              `).join('')}
            </select>
          </div>
        `;
      } else if (field === 'device_profile_id') {
        console.log("Rendering device_profile_id dropdown with profiles:", deviceProfiles);
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">${label}</label>
            <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)">
              <option value="">Select Device Profile</option>
              ${deviceProfiles.map(dp => `
                <option value="${dp.id}" ${value == dp.id ? 'selected' : ''}>${dp.name}</option>
              `).join('')}
            </select>
          </div>
        `;
      } else if (field === 'version' && protocol === 'snmp') {
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">${label}</label>
            <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)">
              <option value="1" ${value === '1' ? 'selected' : ''}>SNMP v1</option>
              <option value="2" ${value === '2' ? 'selected' : ''}>SNMP v2c</option>
              <option value="3" ${value === '3' ? 'selected' : ''}>SNMP v3</option>
            </select>
          </div>
        `;
      } else if (field === 'security_level' && protocol === 'snmp') {
        container.innerHTML += `
          <div class="mb-3" id="security_level_container" style="display: ${commConfig?.version === '3' ? 'block' : 'none'};">
            <label class="form-label">${label}</label>
            <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value); renderCommFields();">
              <option value="noAuthNoPriv" ${value === 'noAuthNoPriv' ? 'selected' : ''}>No Auth No Priv</option>
              <option value="authNoPriv" ${value === 'authNoPriv' ? 'selected' : ''}>Auth No Priv</option>
              <option value="authPriv" ${value === 'authPriv' ? 'selected' : ''}>Auth Priv</option>
            </select>
          </div>
        `;
      } else if ((field === 'auth_protocol' || field === 'priv_protocol') && protocol === 'snmp') {
        const shouldShow = commConfig?.version === '3' &&
          (field === 'auth_protocol' ? commConfig?.security_level !== 'noAuthNoPriv' : commConfig?.security_level === 'authPriv');

        container.innerHTML += `
          <div class="mb-3" style="display: ${shouldShow ? 'block' : 'none'};">
            <label class="form-label">${label}</label>
            <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)">
              <option value="MD5" ${value === 'MD5' ? 'selected' : ''}>MD5</option>
              <option value="SHA" ${value === 'SHA' ? 'selected' : ''}>SHA</option>
              <option value="SHA-224" ${value === 'SHA-224' ? 'selected' : ''}>SHA-224</option>
              <option value="SHA-256" ${value === 'SHA-256' ? 'selected' : ''}>SHA-256</option>
              <option value="SHA-384" ${value === 'SHA-384' ? 'selected' : ''}>SHA-384</option>
              <option value="SHA-512" ${value === 'SHA-512' ? 'selected' : ''}>SHA-512</option>
            </select>
          </div>
        `;
      } else if ((field === 'auth_pass' || field === 'priv_pass') && protocol === 'snmp') {
        const shouldShow = commConfig?.version === '3' &&
          (field === 'auth_pass' ? commConfig?.security_level !== 'noAuthNoPriv' : commConfig?.security_level === 'authPriv');

        container.innerHTML += `
          <div class="mb-3" style="display: ${shouldShow ? 'block' : 'none'};">
            <label class="form-label">${label}</label>
            <input type="password" class="form-control" name="comm_${field}" value="${value}" onchange="updateCommField('${field}', this.value)">
          </div>
        `;
      } else if (protocol === 'snmp' && ['max_oids_per_request', 'batch_delay_ms'].includes(field)) {
        const labels = {
          max_oids_per_request: 'Max OIDs Per Request',
          batch_delay_ms: 'Batch Delay (ms)'
        };
        const placeholders = {
          max_oids_per_request: '10',
          batch_delay_ms: '0'
        };
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">${labels[field]}</label>
            <input type="number" min="0" class="form-control" name="comm_${field}" value="${value}"
              placeholder="${placeholders[field]}"
              onchange="updateCommField('${field}', this.value)"
              oninput="updateCommField('${field}', this.value)">
          </div>
        `;
      } else if (field === 'transport' && protocol === 'rtsp') {
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">Transport</label>
            <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)">
              <option value="tcp" ${value === 'udp' ? '' : 'selected'}>TCP</option>
              <option value="udp" ${value === 'udp' ? 'selected' : ''}>UDP</option>
            </select>
          </div>
        `;
      } else if (protocol === 'rtsp' && field === 'password') {
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="comm_${field}" value="${value}"
              autocomplete="new-password"
              onchange="updateCommField('${field}', this.value)"
              oninput="updateCommField('${field}', this.value)">
          </div>
        `;
      } else if (protocol === 'rtsp' && ['ip', 'port', 'rtsp_port', 'rtsp_url', 'snapshot_url', 'username'].includes(field)) {
        const labels = {
          ip: 'IP Address',
          port: 'REST Port',
          rtsp_port: 'RTSP Port',
          rtsp_url: 'RTSP Path',
          snapshot_url: 'Snapshot Path',
          username: 'Username'
        };
        const placeholders = {
          ip: '172.16.180.42',
          port: '80',
          rtsp_port: '554',
          rtsp_url: '/Streaming/Channels/102',
          snapshot_url: '/ISAPI/Streaming/channels/101/picture',
          username: ''
        };
        const inputType = field === 'port' || field === 'rtsp_port' ? 'number' : 'text';
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">${labels[field] || label}</label>
            <input type="${inputType}" class="form-control" name="comm_${field}" value="${value}"
              placeholder="${placeholders[field] || ''}"
              onchange="updateCommField('${field}', this.value)"
              oninput="updateCommField('${field}', this.value)">
          </div>
        `;
      } else {
        container.innerHTML += `
          <div class="mb-3">
            <label class="form-label">${label}</label>
            <input type="text" class="form-control" name="comm_${field}" value="${value}" onchange="updateCommField('${field}', this.value)">
          </div>
        `;
      }
    });

    if (protocol === 'rtsp') {
      if (typeof commConfig !== 'object' || Array.isArray(commConfig)) commConfig = {};
      commConfig.protocol = 'rtsp';
      if (!commConfig.port) commConfig.port = '80';
      if (!commConfig.rtsp_port) commConfig.rtsp_port = '554';
    }

    updateCommJSON();
  }

  function updateCommField(field, value) {
    if (typeof commConfig !== 'object' || Array.isArray(commConfig)) {
      commConfig = {};
    }
    commConfig[field] = value;
    updateCommJSON();
  }

  function updateCommJSON() {

    document.getElementById('comm_config_json').value = JSON.stringify(commConfig, null, 2);
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderCommFields();
    updateCommJSON();
    renderCustomFields();
    document.getElementById('wizardForm')?.addEventListener('submit', function () {
      if (window.deviceMetricBuilder) {
        window.deviceMetricBuilder.save();
      }
    });
  });
</script>

<link rel="stylesheet" href="<?= base_url('assets/css/jconnect-metric-builder.css') ?>">
<script src="<?= base_url('assets/js/jconnect-metric-builder.js') ?>?v=20260825-edit-perf"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  let metricBuilderReady = false;
  let metricBuilderLoading = false;

  function initMetricBuilder() {
    if (metricBuilderReady || metricBuilderLoading) {
      return;
    }
    metricBuilderLoading = true;

    const container = document.getElementById('metrics-container');
    if (container) {
      container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted mb-0">Loading metrics editor...</p></div>';
    }

    fetch('<?= base_url('device/metric_source_devices_ajax') ?>', { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (devices) {
        window.deviceMetricBuilder = JConnectMetricBuilder.create({
          containerId: 'metrics-container',
          inputId: 'metrics_json',
          snapshotInputId: 'snapshot_triggers_json',
          protocol: <?= json_encode($protocol ?? 'modbus_tcp') ?>,
          metrics: JConnectMetricBuilder.parseMetrics(document.getElementById('metrics_json')?.value || '[]'),
          snapshotTriggers: <?= json_encode($snapTriggers ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
          devices: devices,
          channels: <?= json_encode($channels ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
          roleId: 'device_role',
          gatewayId: 'gateway_device_id',
          snapshotReadOnly: false
        });
        metricBuilderReady = true;
        window.renderMetrics = function () { window.deviceMetricBuilder.render(); };
        window.addMetric = function () { window.deviceMetricBuilder.add('metric'); };
        window.saveMetrics = function () { window.deviceMetricBuilder.save(); };
      })
      .catch(function () {
        if (container) {
          container.innerHTML = '<div class="alert alert-danger mb-0">Failed to load metrics editor. Please refresh and try again.</div>';
        }
      })
      .finally(function () {
        metricBuilderLoading = false;
      });
  }

  const metricsTab = document.querySelector('[data-bs-target="#step3"]');
  if (metricsTab) {
    metricsTab.addEventListener('shown.bs.tab', initMetricBuilder, { once: true });
  }
});
</script>
