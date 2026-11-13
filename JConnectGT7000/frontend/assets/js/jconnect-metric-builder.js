(function (global) {
  'use strict';

  const OPS = ['', '==', '!=', '>', '>=', '<', '<='];
  const DAYS = [
    [0, 'Sun'], [1, 'Mon'], [2, 'Tue'], [3, 'Wed'],
    [4, 'Thu'], [5, 'Fri'], [6, 'Sat']
  ];
  const SCHEDULE_SLOT_MINUTES = 30;
  const SCHEDULE_SLOT_COUNT = 48;
  const LIST_PAGE_SIZE = 100;
  const UNIT_OPTIONS = ['', '°C', '°F', '%', 'ppm', 'V', 'A', 'W', 'kW', 'Wh', 'kWh', 'Hz', 'rpm', 'bar', 'psi', 'Pa', 'kPa', 'm3/h', 'L/min', 'm/s', 'lux', 'dB'];
  // Modbus decode types must match dataedge/protocols/modbus_tcp.js bufferToValue().
  // Generic types (float/int/number/bool/string/json) kept for non-modbus metrics.
  // dataSaver.js only branches string vs number → DB value (double) — uint32/int32 are safe.
  const DATATYPE_OPTIONS = [
    ['float', 'float'],
    ['int', 'int (= int16 for Modbus)'],
    ['number', 'number'],
    ['bool', 'bool'],
    ['string', 'string'],
    ['json', 'json'],
    ['int16', 'int16'],
    ['uint16', 'uint16'],
    ['int32', 'int32'],
    ['uint32', 'uint32'],
    ['float32', 'float32'],
    ['float32_swap', 'float32_swap'],
    ['float32_fullswap', 'float32_fullswap']
  ];
  const PROTOCOL_FIELDS = {
    modbus_tcp: ['address', 'type', 'function_code', 'byte_order', 'length'],
    modbus_rtu: ['address', 'type', 'function_code', 'byte_order', 'length'],
    modbus_router: ['address', 'type', 'function_code', 'byte_order', 'length'],
    snmp: ['oid'],
    mqtt: ['topic', 'json_path'],
    bacnet_ip: ['object_id', 'property_id'],
    bacnet_mstp: ['object_id', 'property_id'],
    bacnet_router: ['object_id', 'property_id'],
    restful: ['url', 'json_path'],
    webhook: ['key_path', 'json_path'],
    lorawan: [],
    gateway_endpoint: ['source_kind', 'channel'],
    visual: [],
    rtsp: []
  };
  const TYPE_OPTIONS = {
    modbus_tcp: [['modbus_read', 'modbus_read']],
    modbus_rtu: [['modbus_read', 'modbus_read']],
    modbus_router: [['modbus_read', 'modbus_read']],
    snmp: [['snmp_get', 'snmp_get']],
    mqtt: [['mqtt_sub', 'mqtt_sub']],
    bacnet_ip: [['bacnet_read', 'bacnet_read']],
    bacnet_mstp: [['bacnet_read', 'bacnet_read']],
    bacnet_router: [['bacnet_read', 'bacnet_read']],
    restful: [['rest_get', 'rest_get']],
    webhook: [['rest_webhook', 'rest_webhook']],
    gateway_endpoint: [['gateway_mapped', 'gateway_mapped']],
    visual: [['visual_random_number', 'Random number'], ['visual_random_string', 'Random string']],
    rtsp: [['rtsp_probe', 'Stream status'], ['rtsp_snapshot_meta', 'Snapshot meta']]
  };
  const DEFAULT_TYPE = {
    modbus_tcp: 'modbus_read',
    modbus_rtu: 'modbus_read',
    modbus_router: 'modbus_read',
    snmp: 'snmp_get',
    mqtt: 'mqtt_sub',
    bacnet_ip: 'bacnet_read',
    bacnet_mstp: 'bacnet_read',
    bacnet_router: 'bacnet_read',
    restful: 'rest_get',
    webhook: 'rest_webhook',
    gateway_endpoint: 'gateway_mapped',
    visual: 'visual_random_number',
    rtsp: 'rtsp_probe'
  };

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function parseMetrics(value) {
    let current = value;
    for (let i = 0; i < 2 && typeof current === 'string'; i += 1) {
      try { current = JSON.parse(current); } catch (_) { return []; }
    }
    return Array.isArray(current) ? current : [];
  }

  function boolValue(value, fallback) {
    if (value === true || value === 'true' || value === 1 || value === '1') return true;
    if (value === false || value === 'false' || value === 0 || value === '0') return false;
    return fallback;
  }

  function isExplicitlyTrue(value) {
    return value === true || value === 'true' || value === 1 || value === '1';
  }

  function numberOrText(value) {
    if (value === '') return '';
    const number = Number(value);
    return Number.isFinite(number) ? number : value;
  }

  function timeToMinutes(value, fallback) {
    const match = String(value == null ? '' : value).trim().match(/^(\d{1,2}):([0-5]\d)$/);
    if (!match) return fallback;
    const hour = Number(match[1]);
    const minutes = Number(match[2]);
    return hour >= 0 && hour <= 23 ? (hour * 60) + minutes : fallback;
  }

  function minutesToTime(minutes) {
    const safe = Math.max(0, Math.min((23 * 60) + 59, Math.round(minutes)));
    return String(Math.floor(safe / 60)).padStart(2, '0') + ':' + String(safe % 60).padStart(2, '0');
  }

  function emptyScheduleSlots() {
    return DAYS.reduce(function (slots, day) {
      slots[day[0]] = new Set();
      return slots;
    }, {});
  }

  function durationToScheduleSlots(windows) {
    const selected = emptyScheduleSlots();
    (Array.isArray(windows) ? windows : []).forEach(function (windowItem) {
      const startMinutes = timeToMinutes(windowItem.start, 0);
      const endMinutes = timeToMinutes(windowItem.end, (23 * 60) + 59);
      const startSlot = Math.max(0, Math.min(SCHEDULE_SLOT_COUNT - 1, Math.floor(startMinutes / SCHEDULE_SLOT_MINUTES)));
      const endSlotExclusive = Math.max(1, Math.min(SCHEDULE_SLOT_COUNT, Math.ceil((endMinutes + 1) / SCHEDULE_SLOT_MINUTES)));
      const days = Array.isArray(windowItem.days) ? windowItem.days.map(Number) : [];
      days.forEach(function (day) {
        if (!selected[day]) return;
        if (startMinutes <= endMinutes) {
          for (let slot = startSlot; slot < endSlotExclusive; slot += 1) selected[day].add(slot);
        } else {
          for (let slot = startSlot; slot < SCHEDULE_SLOT_COUNT; slot += 1) selected[day].add(slot);
          for (let slot = 0; slot < endSlotExclusive; slot += 1) selected[day].add(slot);
        }
      });
    });
    return selected;
  }

  function scheduleSlotsToDuration(selected) {
    const byRange = {};
    DAYS.forEach(function (day) {
      const slots = Array.from(selected[day[0]] || []).sort(function (a, b) { return a - b; });
      let cursor = 0;
      while (cursor < slots.length) {
        const startSlot = slots[cursor];
        let endSlot = startSlot;
        cursor += 1;
        while (cursor < slots.length && slots[cursor] === endSlot + 1) {
          endSlot = slots[cursor];
          cursor += 1;
        }
        const start = minutesToTime(startSlot * SCHEDULE_SLOT_MINUTES);
        const end = minutesToTime(Math.min((23 * 60) + 59, ((endSlot + 1) * SCHEDULE_SLOT_MINUTES) - 1));
        const key = start + '|' + end;
        if (!byRange[key]) byRange[key] = { days: [], start: start, end: end };
        byRange[key].days.push(day[0]);
      }
    });
    return Object.keys(byRange).map(function (key) { return byRange[key]; });
  }

  function formatScheduleDays(days) {
    const normalized = Array.from(new Set((Array.isArray(days) ? days : []).map(Number))).sort(function (a, b) { return a - b; });
    const value = normalized.join(',');
    if (value === '0,1,2,3,4,5,6') return 'Every day';
    if (value === '1,2,3,4,5') return 'Weekdays';
    if (value === '0,6') return 'Weekend';
    return normalized.map(function (day) {
      const found = DAYS.find(function (option) { return option[0] === day; });
      return found ? found[1] : String(day);
    }).join(', ');
  }

  function optionList(items, current) {
    return items.map(function (item) {
      const value = Array.isArray(item) ? item[0] : item;
      const label = Array.isArray(item) ? item[1] : item;
      return '<option value="' + esc(value) + '"' + (String(current) === String(value) ? ' selected' : '') + '>' + esc(label || '(none)') + '</option>';
    }).join('');
  }

  function input(label, value, attrs) {
    return '<label class="form-label">' + esc(label) + '</label><input class="form-control form-control-sm" value="' + esc(value) + '" ' + (attrs || '') + '>';
  }

  function select(label, items, current, attrs) {
    return '<label class="form-label">' + esc(label) + '</label><select class="form-select form-select-sm" ' + (attrs || '') + '>' + optionList(items, current) + '</select>';
  }

  function textarea(label, value, attrs, placeholder) {
    return '<label class="form-label">' + esc(label) + '</label><textarea class="form-control form-control-sm" rows="5" placeholder="' + esc(placeholder || '') + '" ' + (attrs || '') + '>' + esc(value) + '</textarea>';
  }

  const SEVERITY_OPTIONS = ['info', 'warning', 'minor', 'major', 'critical'];
  const SEVERITY_ALIASES = {
    high: 'major',
    medium: 'minor',
    low: 'minor',
    other: 'info'
  };

  function normalizeSeverity(value) {
    const raw = String(value == null ? '' : value).trim().toLowerCase();
    if (!raw) return 'warning';
    const mapped = SEVERITY_ALIASES[raw] || raw;
    return SEVERITY_OPTIONS.indexOf(mapped) >= 0 ? mapped : 'warning';
  }

  function normalizeMetric(metric, protocol) {
    const m = metric && typeof metric === 'object' ? clone(metric) : {};
    if (m.trigger && !m.triggers) {
      m.triggers = [m.trigger];
      delete m.trigger;
    }
    m.key = m.key || '';
    m.label = m.label || '';
    m.unit = m.unit || '';
    m.description = m.description || '';
    m.protocol = m.protocol || protocol;
    m.datatype = m.datatype || (m.type === 'visual_random_string' ? 'string' : 'float');
    m.enable = boolValue(m.enable, true);
    m.scale = m.scale == null ? 1 : m.scale;
    m.pull_rate = m.pull_rate == null ? 10 : m.pull_rate;
    m.store_policy = m.store_policy || 'always';
    m.triggers = Array.isArray(m.triggers) ? m.triggers : [];
    // Persist severity into JSON (UI used to show "warning" without writing the field).
    m.triggers.forEach(function (trigger) {
      if (!trigger || typeof trigger !== 'object') return;
      trigger.severity = normalizeSeverity(trigger.severity);
    });
    m.normal_condition = m.normal_condition && typeof m.normal_condition === 'object' ? m.normal_condition : {};
    return m;
  }

  function normalizeSnapshotTrigger(rule) {
    const r = rule && typeof rule === 'object' ? clone(rule) : {};
    const hasTriggerIndex = r.source_trigger_index != null && r.source_trigger_index !== '';
    return {
      enable: boolValue(r.enable, true),
      source_device_id: r.source_device_id == null || r.source_device_id === '' ? '' : Number(r.source_device_id),
      source_metric_key: r.source_metric_key || '',
      source_trigger_index: hasTriggerIndex ? Number(r.source_trigger_index) : '',
      operator: hasTriggerIndex ? '' : (r.operator || '=='),
      value: hasTriggerIndex ? '' : (r.value == null ? '' : r.value),
      cooldown_sec: r.cooldown_sec == null || r.cooldown_sec === '' ? 30 : Number(r.cooldown_sec),
      target_camera_device_id: r.target_camera_device_id == null || r.target_camera_device_id === '' ? '' : Number(r.target_camera_device_id)
    };
  }

  function alarmTriggerSummaryLabel(trigger, triggerIndex) {
    const enable = isExplicitlyTrue(trigger.enable) ? 'Active' : 'Inactive';
    const op = trigger.operator || '(none)';
    const val = trigger.value == null || trigger.value === '' ? '-' : trigger.value;
    const clear = trigger.clear_condition && typeof trigger.clear_condition === 'object' ? trigger.clear_condition : {};
    const clearOp = clear.operator || '(none)';
    const clearVal = clear.value == null || clear.value === '' ? '-' : clear.value;
    let label = 'Trigger ' + (triggerIndex + 1) + ': ' + op + ' ' + val;
    if (clearOp !== '(none)' || clearVal !== '-') {
      label += ' · clear ' + clearOp + ' ' + clearVal;
    }
    label += ' (' + enable + ')';
    if (trigger.message) label += ' · ' + trigger.message;
    return label;
  }

  function normalizeSnapshotTriggers(value) {
    if (typeof value === 'string') {
      try { value = JSON.parse(value); } catch (_e) { value = []; }
    }
    if (!Array.isArray(value)) return [];
    return value.map(normalizeSnapshotTrigger);
  }

  function MetricBuilder(options) {
    options = options || {};
    this.container = document.getElementById(options.containerId || 'metrics-container');
    this.input = document.getElementById(options.inputId || 'metrics_json');
    this.protocolElement = document.getElementById(options.protocolId || 'protocol');
    this.roleElement = document.getElementById(options.roleId || 'device_role');
    this.gatewayElement = document.getElementById(options.gatewayId || 'gateway_device_id');
    this.protocol = options.protocol || 'modbus_tcp';
    this.devices = Array.isArray(options.devices) ? options.devices : [];
    this.channels = Array.isArray(options.channels) ? options.channels : [];
    this.readOnly = options.readOnly === true;
    this.snapshotReadOnly = options.snapshotReadOnly === true || (options.snapshotReadOnly == null && this.readOnly);
    this.snapshotInput = document.getElementById(options.snapshotInputId || 'snapshot_triggers_json');
    this.snapshotTriggers = normalizeSnapshotTriggers(options.snapshotTriggers != null ? options.snapshotTriggers : (this.snapshotInput && this.snapshotInput.value));
    this.jsonEditable = options.jsonEditable === true && !this.readOnly;
    this.search = '';
    this.filter = 'all';
    this.listPage = 0;
    this.selectedIndex = 0;
    this.activeTab = 'setup';
    this.scheduleDrag = null;
    this.modalId = 'jmb-json-' + (options.containerId || 'metrics-container').replace(/[^a-z0-9_-]/gi, '');
    this.editModalId = this.modalId + '-edit';
    this.metrics = parseMetrics(options.metrics).map((m) => normalizeMetric(m, this.getProtocol()));
    const self = this;
    function stopScheduleDrag() {
      if (!self.scheduleDrag) return;
      self.scheduleDrag = null;
      self.render();
    }
    global.addEventListener('pointerup', stopScheduleDrag);
    global.addEventListener('pointercancel', stopScheduleDrag);
    this.bindProtocol();
    this.bindRoleContext();
    this.save();
    this.render();
  }

  MetricBuilder.prototype.getProtocol = function () {
    return (this.protocolElement && this.protocolElement.value) || this.protocol || 'modbus_tcp';
  };

  MetricBuilder.prototype.bindProtocol = function () {
    const self = this;
    if (!this.protocolElement || this.readOnly) return;
    this.protocolElement.addEventListener('change', function () {
      const protocol = self.getProtocol();
      self.metrics.forEach(function (metric) {
        if (metric.type !== 'calc') {
          metric.protocol = protocol;
          metric.type = DEFAULT_TYPE[protocol] || metric.type;
        }
      });
      self.render();
    });
  };

  MetricBuilder.prototype.getDeviceRole = function () {
    return this.roleElement ? String(this.roleElement.value || 'native_device').toLowerCase() : 'native_device';
  };

  MetricBuilder.prototype.getGatewayDeviceId = function () {
    return this.gatewayElement ? String(this.gatewayElement.value || '') : '';
  };

  MetricBuilder.prototype.bindRoleContext = function () {
    const self = this;
    if (this.roleElement) {
      this.roleElement.addEventListener('change', function () { self.refreshRoleContext(); });
    }
    if (this.gatewayElement) {
      this.gatewayElement.addEventListener('change', function () { self.refreshRoleContext(); });
    }
  };

  MetricBuilder.prototype.refreshRoleContext = function () {
    const gatewayId = this.getGatewayDeviceId();
    if (this.getDeviceRole() === 'endpoint' && gatewayId) {
      const allowed = this.metricOptionsForDevice(gatewayId, '', true).map(function (option) { return String(option[0]); });
      this.metrics.forEach(function (metric) {
        if (!metric.source || typeof metric.source !== 'object') metric.source = {};
        metric.source.gateway_device_id = Number(gatewayId);
        if (metric.source.gateway_metric_key && !allowed.includes(String(metric.source.gateway_metric_key))) {
          delete metric.source.gateway_metric_key;
        }
      });
      this.save();
    }
    this.render();
  };

  MetricBuilder.prototype.cleanMetrics = function () {
    return this.metrics.map(function (metric) { return clone(metric); });
  };

  MetricBuilder.prototype.saveSnapshotTriggers = function () {
    if (this.snapshotInput) {
      this.snapshotInput.value = JSON.stringify(this.snapshotTriggers || [], null, 2);
    }
  };

  MetricBuilder.prototype.save = function () {
    if (this.input) this.input.value = JSON.stringify(this.cleanMetrics(), null, 2);
    this.saveSnapshotTriggers();
  };

  MetricBuilder.prototype.supportsSnapshotTriggers = function () {
    return this.getProtocol() === 'rtsp';
  };

  MetricBuilder.prototype.addSnapshotTrigger = function () {
    if (this.snapshotReadOnly) return;
    this.snapshotTriggers.push(normalizeSnapshotTrigger({
      enable: true,
      operator: '==',
      value: 1,
      cooldown_sec: 30
    }));
    this.save();
    this.render();
  };

  MetricBuilder.prototype.removeSnapshotTrigger = function (index) {
    if (this.snapshotReadOnly) return;
    this.snapshotTriggers.splice(index, 1);
    this.save();
    this.render();
  };

  MetricBuilder.prototype.updateSnapshotTrigger = function (index, path, value, rerender) {
    if (this.snapshotReadOnly || !this.snapshotTriggers[index]) return;
    this.snapshotTriggers[index][path] = value;
    this.snapshotTriggers[index] = normalizeSnapshotTrigger(this.snapshotTriggers[index]);
    this.save();
    if (rerender) this.render();
  };

  MetricBuilder.prototype.renderSnapshotTriggers = function () {
    if (!this.supportsSnapshotTriggers()) return '';
    const self = this;
    const deviceOptions = [['', 'Select device']].concat(
      this.devices.map(function (device) {
        return [String(device.id), (device.name || ('Device #' + device.id)) + ' (#' + device.id + ')'];
      })
    );
    let html = '<div class="jmb-panel mt-3"><div class="d-flex justify-content-between align-items-center mb-3"><div><div class="jmb-panel-title mb-0">Snapshot Triggers</div><div class="jmb-help">Captures JPEG when the source alarm Rule matches or its Clear condition matches (rising edge), respecting enable + schedule.</div></div>';
    if (!this.snapshotReadOnly) {
      html += '<button type="button" class="btn btn-primary btn-sm" data-jmb-action="add-snapshot-trigger"><i class="bi bi-plus"></i> Add snapshot trigger</button>';
    }
    html += '</div>';
    if (!this.snapshotTriggers.length) {
      html += '<div class="jmb-empty-inline">No snapshot triggers configured.</div></div>';
      return html;
    }
    this.snapshotTriggers.forEach(function (rule, ruleIndex) {
      const metricOptions = self.metricOptionsForDevice(rule.source_device_id, rule.source_metric_key);
      const triggerOptions = self.alarmTriggerOptionsForDeviceMetric(rule.source_device_id, rule.source_metric_key, rule.source_trigger_index);
      const alarmTrigger = self.getSourceAlarmTrigger(rule.source_device_id, rule.source_metric_key, rule.source_trigger_index);
      html += '<div class="jmb-trigger-row"><div class="d-flex justify-content-between align-items-center mb-3"><strong>Snapshot trigger ' + (ruleIndex + 1) + '</strong>';
      if (!self.snapshotReadOnly) {
        html += '<button type="button" class="btn btn-outline-danger btn-sm" data-jmb-action="remove-snapshot-trigger" data-snapshot-index="' + ruleIndex + '"><i class="bi bi-trash"></i> Remove</button>';
      }
      html += '</div><div class="jmb-subsection"><strong class="d-block mb-2">Source</strong><div class="row g-2">';
      html += '<div class="col-md-2">' + select('Enable', [['true', 'Active'], ['false', 'Inactive']], String(boolValue(rule.enable, true)), 'data-jmb-snapshot-path="enable" data-snapshot-index="' + ruleIndex + '" data-boolean="1"') + '</div>';
      html += '<div class="col-md-4">' + select('Source device', deviceOptions, rule.source_device_id == null ? '' : String(rule.source_device_id), 'data-jmb-snapshot-path="source_device_id" data-snapshot-index="' + ruleIndex + '" data-rerender="1"') + '</div>';
      html += '<div class="col-md-3">' + select('Source metric', metricOptions, rule.source_metric_key || '', 'data-jmb-snapshot-path="source_metric_key" data-snapshot-index="' + ruleIndex + '" data-rerender="1"') + '</div>';
      html += '<div class="col-md-3">' + select('Source alarm trigger', triggerOptions, rule.source_trigger_index === '' || rule.source_trigger_index == null ? '' : String(rule.source_trigger_index), 'data-jmb-snapshot-path="source_trigger_index" data-snapshot-index="' + ruleIndex + '" data-rerender="1"') + '</div>';
      html += '<div class="col-md-2">' + input('Cooldown (sec)', rule.cooldown_sec == null ? 30 : rule.cooldown_sec, 'type="number" min="1" data-number="1" data-jmb-snapshot-path="cooldown_sec" data-snapshot-index="' + ruleIndex + '"') + '</div>';
      html += '</div></div>';
      html += self.renderSnapshotTriggerSummary(alarmTrigger);
      html += '</div>';
    });
    return html + '</div>';
  };

  MetricBuilder.prototype.setMetrics = function (value) {
    this.metrics = parseMetrics(value).map((m) => normalizeMetric(m, this.getProtocol()));
    this.selectedIndex = 0;
    this.activeTab = 'setup';
    this.render();
  };

  MetricBuilder.prototype.add = function (kind) {
    if (this.readOnly) return;
    const protocol = this.getProtocol();
    const isCalc = kind === 'calc';
    this.metrics.push(normalizeMetric({
      key: '',
      label: '',
      protocol,
      type: isCalc ? 'calc' : (DEFAULT_TYPE[protocol] || ''),
      datatype: 'float',
      enable: true,
      calc: isCalc ? {
        formula: '',
        inputs: [{ var: 'a', source: 'device', deviceId: '', metricKey: '' }],
        trigger: { mode: 'interval', intervalSec: 30 },
        decimals: 2
      } : undefined,
      visual: protocol === 'visual' && !isCalc ? { min: 0, max: 100, decimals: 2 } : undefined
    }, protocol));
    this.selectedIndex = this.metrics.length - 1;
    this.activeTab = isCalc ? 'logic' : 'setup';
    this.render();
  };

  MetricBuilder.prototype.update = function (index, path, value, rerender, allowReadOnly) {
    if ((this.readOnly && !allowReadOnly) || !this.metrics[index]) return;
    const parts = path.split('.');
    let target = this.metrics[index];
    for (let p = 0; p < parts.length - 1; p += 1) {
      if (!target[parts[p]] || typeof target[parts[p]] !== 'object') target[parts[p]] = {};
      target = target[parts[p]];
    }
    target[parts[parts.length - 1]] = value;
    if (path === 'source.gateway_metric_key') {
      if (!this.metrics[index].source || typeof this.metrics[index].source !== 'object') {
        this.metrics[index].source = {};
      }
      this.metrics[index].source.gateway_device_id = Number(this.getGatewayDeviceId());
    }
    this.save();
    if (rerender) this.render();
  };

  MetricBuilder.prototype.remove = function (index) {
    if (this.readOnly) return;
    this.metrics.splice(index, 1);
    this.selectedIndex = Math.max(0, Math.min(index, this.metrics.length - 1));
    this.render();
  };

  MetricBuilder.prototype.duplicate = function (index) {
    if (this.readOnly) return;
    const copy = clone(this.metrics[index]);
    copy.key = copy.key ? copy.key + '_copy' : '';
    copy.label = copy.label ? copy.label + ' copy' : '';
    this.metrics.splice(index + 1, 0, copy);
    this.selectedIndex = index + 1;
    this.render();
  };

  MetricBuilder.prototype.addCalcInput = function (index) {
    if (this.readOnly) return;
    const metric = this.metrics[index];
    if (!metric.calc) metric.calc = {};
    if (!Array.isArray(metric.calc.inputs)) metric.calc.inputs = [];
    metric.calc.inputs.push({ var: '', source: 'device', deviceId: '', metricKey: '' });
    this.render();
  };

  MetricBuilder.prototype.removeCalcInput = function (index, inputIndex) {
    if (this.readOnly) return;
    this.metrics[index].calc.inputs.splice(inputIndex, 1);
    this.render();
  };

  MetricBuilder.prototype.addTrigger = function (index) {
    if (this.readOnly) return;
    this.metrics[index].triggers.push({
      enable: true,
      operator: '>',
      value: '',
      message: '',
      severity: 'warning',
      repeat_interval: 0,
      duration: [],
      channels: [],
      clear_condition: { operator: '<=', value: '', message: '' }
    });
    this.render();
  };

  MetricBuilder.prototype.removeTrigger = function (index, triggerIndex) {
    if (this.readOnly) return;
    this.metrics[index].triggers.splice(triggerIndex, 1);
    this.render();
  };

  MetricBuilder.prototype.applyScheduleRange = function (index, triggerIndex, day, slot) {
    if (this.readOnly || !this.scheduleDrag || this.scheduleDrag.day !== day) return;
    const trigger = this.metrics[index].triggers[triggerIndex];
    const selected = durationToScheduleSlots(trigger.duration);
    const start = Math.min(this.scheduleDrag.startSlot, slot);
    const end = Math.max(this.scheduleDrag.startSlot, slot);
    for (let cursor = start; cursor <= end; cursor += 1) {
      if (this.scheduleDrag.mode === 'add') selected[day].add(cursor);
      else selected[day].delete(cursor);
    }
    trigger.duration = scheduleSlotsToDuration(selected);
    this.save();
    this.container.querySelectorAll('[data-jmb-schedule-slot][data-index="' + index + '"][data-trigger-index="' + triggerIndex + '"]').forEach(function (element) {
      const active = selected[Number(element.dataset.day)].has(Number(element.dataset.slot));
      element.classList.toggle('active', active);
      element.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  MetricBuilder.prototype.metricOptionsForDevice = function (deviceId, current, activeOnly) {
    const device = this.devices.find((d) => String(d.id) === String(deviceId));
    const metrics = device ? parseMetrics(device.metrics) : this.metrics;
    const options = [['', 'Select metric']];
    metrics.forEach(function (metric) {
      if (activeOnly && !boolValue(metric?.enable, true)) return;
      if (metric && metric.key) options.push([String(metric.key), (metric.label ? metric.label + ' · ' : '') + metric.key]);
    });
    if (!activeOnly && current && !options.some((option) => String(option[0]) === String(current))) {
      options.push([current, current]);
    }
    return options;
  };

  MetricBuilder.prototype.getSourceAlarmTrigger = function (deviceId, metricKey, triggerIndex) {
    const device = this.devices.find((d) => String(d.id) === String(deviceId));
    if (!device || !metricKey || triggerIndex === '' || triggerIndex == null) return null;
    const metrics = parseMetrics(device.metrics);
    const metric = metrics.find(function (item) { return String(item.key) === String(metricKey); });
    if (!metric || !Array.isArray(metric.triggers)) return null;
    return metric.triggers[Number(triggerIndex)] || null;
  };

  MetricBuilder.prototype.alarmTriggerOptionsForDeviceMetric = function (deviceId, metricKey, currentIndex) {
    const device = this.devices.find((d) => String(d.id) === String(deviceId));
    const options = [['', 'Select alarm trigger']];
    if (!device || !metricKey) return options;
    const metrics = parseMetrics(device.metrics);
    const metric = metrics.find(function (item) { return String(item.key) === String(metricKey); });
    if (!metric || !Array.isArray(metric.triggers) || !metric.triggers.length) return options;
    metric.triggers.forEach(function (trigger, triggerIndex) {
      options.push([String(triggerIndex), alarmTriggerSummaryLabel(trigger, triggerIndex)]);
    });
    if (currentIndex !== '' && currentIndex != null && !options.some(function (option) { return String(option[0]) === String(currentIndex); })) {
      options.push([String(currentIndex), 'Trigger ' + (Number(currentIndex) + 1) + ' (missing)']);
    }
    return options;
  };

  MetricBuilder.prototype.renderDurationReadOnly = function (trigger) {
    const windows = Array.isArray(trigger?.duration) ? trigger.duration : [];
    let html = '<div class="jmb-subsection jmb-schedule"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><strong class="d-block">Active schedule</strong><span class="jmb-help">' + (windows.length ? windows.length + ' active window' + (windows.length === 1 ? '' : 's') : 'Always active') + '</span></div></div>';
    html += '<div class="jmb-schedule-scroll"><div class="jmb-schedule-grid"><div class="jmb-schedule-scale"><span></span><span>00:00</span><span>06:00</span><span>12:00</span><span>24:00</span></div>';
    const selected = durationToScheduleSlots(windows);
    DAYS.forEach(function (day) {
      html += '<div class="jmb-schedule-day"><span class="jmb-schedule-day-label">' + day[1] + '</span>';
      for (let slot = 0; slot < SCHEDULE_SLOT_COUNT; slot += 1) {
        const start = minutesToTime(slot * SCHEDULE_SLOT_MINUTES);
        const end = minutesToTime(Math.min((23 * 60) + 59, ((slot + 1) * SCHEDULE_SLOT_MINUTES) - 1));
        const active = selected[day[0]].has(slot);
        html += '<span class="jmb-schedule-slot' + (active ? ' active' : '') + '" aria-hidden="true" title="' + day[1] + ' ' + start + '-' + end + '"></span>';
      }
      html += '</div>';
    });
    html += '</div></div>';
    if (windows.length) {
      html += '<div class="jmb-schedule-summary">';
      windows.slice(0, 4).forEach(function (windowItem) {
        html += '<span>' + esc(formatScheduleDays(windowItem.days)) + ' ' + esc(windowItem.start) + '-' + esc(windowItem.end) + '</span>';
      });
      if (windows.length > 4) html += '<span>+' + (windows.length - 4) + ' more</span>';
      html += '</div>';
    }
    return html + '</div>';
  };

  MetricBuilder.prototype.renderSnapshotTriggerSummary = function (alarmTrigger) {
    if (!alarmTrigger) {
      return '<div class="jmb-empty-inline">Select a source alarm trigger to preview its rule and schedule.</div>';
    }
    const clear = alarmTrigger.clear_condition && typeof alarmTrigger.clear_condition === 'object'
      ? alarmTrigger.clear_condition
      : {};
    let html = '<div class="jmb-subsection"><strong class="d-block mb-2">Source alarm trigger · Rule / Clear</strong><div class="row g-2">';
    html += '<div class="col-md-2"><label class="form-label">Enable</label><div class="form-control-plaintext">' + esc(isExplicitlyTrue(alarmTrigger.enable) ? 'Active' : 'Inactive') + '</div></div>';
    html += '<div class="col-md-2"><label class="form-label">Operator</label><div class="form-control-plaintext">' + esc(alarmTrigger.operator || '(none)') + '</div></div>';
    html += '<div class="col-md-2"><label class="form-label">Value</label><div class="form-control-plaintext">' + esc(alarmTrigger.value == null || alarmTrigger.value === '' ? '-' : alarmTrigger.value) + '</div></div>';
    html += '<div class="col-md-6"><label class="form-label">Message</label><div class="form-control-plaintext">' + esc(alarmTrigger.message || '-') + '</div></div>';
    html += '<div class="col-md-2"><label class="form-label">Clear operator</label><div class="form-control-plaintext">' + esc(clear.operator || '(none)') + '</div></div>';
    html += '<div class="col-md-2"><label class="form-label">Clear value</label><div class="form-control-plaintext">' + esc(clear.value == null || clear.value === '' ? '-' : clear.value) + '</div></div>';
    html += '<div class="col-md-8"><label class="form-label">Clear message</label><div class="form-control-plaintext">' + esc(clear.message || '-') + '</div></div>';
    html += '</div></div>';
    return html + this.renderDurationReadOnly(alarmTrigger);
  };

  MetricBuilder.prototype.renderSetup = function (metric, index) {
    const protocol = metric.protocol || this.getProtocol();
    const typeItems = [['calc', 'Calculate / derived']].concat(TYPE_OPTIONS[protocol] || []);
    const unitListId = this.modalId + '-unit-options';
    if (metric.type && !typeItems.some((item) => String(item[0]) === String(metric.type))) typeItems.push([metric.type, metric.type]);
    let html = '<div class="row g-3">';
    html += '<div class="col-xl-6"><div class="jmb-panel"><div class="jmb-panel-title">Identity</div><div class="row g-2">';
    html += '<div class="col-md-6">' + input('Label', metric.label, 'data-jmb-path="label" data-index="' + index + '"') + '</div>';
    html += '<div class="col-md-6">' + input('Key', metric.key, 'data-jmb-path="key" data-index="' + index + '" placeholder="temperature"') + '</div>';
    html += '<div class="col-md-6">' + input('Unit', metric.unit, 'list="' + unitListId + '" data-jmb-path="unit" data-index="' + index + '" placeholder="°C"') + '<datalist id="' + unitListId + '">' + UNIT_OPTIONS.filter(Boolean).map(function (unit) { return '<option value="' + esc(unit) + '"></option>'; }).join('') + '</datalist></div>';
    html += '<div class="col-md-6">' + select('Enable', [['true', 'Active'], ['false', 'Inactive']], String(boolValue(metric.enable, true)), 'data-jmb-path="enable" data-index="' + index + '" data-boolean="1" data-rerender="1"') + '</div>';
    html += '</div></div></div>';
    html += '<div class="col-xl-6"><div class="jmb-panel"><div class="jmb-panel-title">Type & value</div><div class="row g-2">';
    html += '<div class="col-md-6">' + select('Type (poll / read)', typeItems, metric.type || DEFAULT_TYPE[protocol] || '', 'data-jmb-path="type" data-index="' + index + '" data-rerender="1"') + '</div>';
    html += '<div class="col-md-6">' + select('Datatype', DATATYPE_OPTIONS, metric.datatype, 'data-jmb-path="datatype" data-index="' + index + '"') + '</div>';
    html += '</div></div></div>';
    html += '<div class="col-xl-6"><div class="jmb-panel"><div class="jmb-panel-title">Polling</div><div class="row g-2">';
    html += '<div class="col-md-6">' + input('Pull rate (seconds)', metric.pull_rate, 'type="number" min="0" data-number="1" data-jmb-path="pull_rate" data-index="' + index + '"') + '</div>';
    html += '<div class="col-md-6">' + input('Scale', metric.scale, 'type="number" step="any" data-number="1" data-jmb-path="scale" data-index="' + index + '"') + '</div>';
    html += '</div></div></div>';
    html += '<div class="col-xl-6"><div class="jmb-panel"><div class="jmb-panel-title">Storage</div><div class="row g-2">';
    html += '<div class="col-md-6">' + select('Store policy', [['always', 'Always'], ['on_change', 'On change'], ['interval', 'Interval'], ['none', 'Do not store']], metric.store_policy, 'data-jmb-path="store_policy" data-index="' + index + '" data-rerender="1"') + '</div>';
    if (metric.store_policy === 'interval') html += '<div class="col-md-6">' + input('Store interval (minutes)', metric.interval_minutes == null ? 5 : metric.interval_minutes, 'type="number" min="1" data-number="1" data-jmb-path="interval_minutes" data-index="' + index + '"') + '</div>';
    html += '</div></div></div></div>';
    return html;
  };

  MetricBuilder.prototype.renderVisual = function (metric, index) {
    const visual = metric.visual || (metric.visual = {});
    let html = '<div class="jmb-panel"><div class="jmb-panel-title">Visual generator</div><p class="jmb-help">Dataedge emits synthetic readings from this configuration each poll.</p><div class="row g-2">';
    if (metric.type === 'visual_random_string') {
      html += '<div class="col-md-4">' + input('Length', visual.length == null ? 8 : visual.length, 'type="number" min="1" max="256" data-number="1" data-jmb-path="visual.length" data-index="' + index + '"') + '</div>';
      html += '<div class="col-md-8">' + input('Character set', visual.charset || 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 'data-jmb-path="visual.charset" data-index="' + index + '"') + '</div>';
      html += '<div class="col-12">' + input('Choices (comma separated)', Array.isArray(visual.choices) ? visual.choices.join(', ') : (visual.choices || ''), 'data-jmb-path="visual.choices" data-index="' + index + '" data-list="1"') + '</div>';
    } else {
      html += '<div class="col-md-4">' + input('Min', visual.min == null ? 0 : visual.min, 'type="number" step="any" data-number="1" data-jmb-path="visual.min" data-index="' + index + '"') + '</div>';
      html += '<div class="col-md-4">' + input('Max', visual.max == null ? 100 : visual.max, 'type="number" step="any" data-number="1" data-jmb-path="visual.max" data-index="' + index + '"') + '</div>';
      html += '<div class="col-md-4">' + input('Decimals', visual.decimals == null ? 2 : visual.decimals, 'type="number" min="0" max="8" data-number="1" data-jmb-path="visual.decimals" data-index="' + index + '"') + '</div>';
    }
    return html + '</div></div>';
  };

  MetricBuilder.prototype.renderSource = function (metric, index) {
    const protocol = metric.protocol || this.getProtocol();
    if (this.getDeviceRole() === 'endpoint') {
      const gatewayId = this.getGatewayDeviceId();
      const gateway = this.devices.find(function (device) { return String(device.id) === String(gatewayId); });
      if (!gatewayId || !gateway) {
        return '<div class="jmb-panel"><div class="jmb-panel-title">Parent Gateway Source</div><p class="text-muted mb-0">Select a Parent Gateway in the Information tab before mapping metric sources.</p></div>';
      }
      const source = metric.source && typeof metric.source === 'object' ? metric.source : (metric.source = {});
      let html = '<div class="jmb-panel"><div class="jmb-panel-title">Parent Gateway Source</div>';
      html += '<p class="jmb-help">Map this endpoint metric to a metric collected by ' + esc(gateway.name) + ' (#' + esc(gateway.id) + ').</p>';
      html += '<div class="row g-2"><div class="col-md-8">' + select(
        'Parent Gateway Metric',
        this.metricOptionsForDevice(gatewayId, source.gateway_metric_key || '', true),
        source.gateway_metric_key || '',
        'data-jmb-path="source.gateway_metric_key" data-index="' + index + '" data-jmb-readonly-editable="1" required'
      ) + '</div></div></div>';
      return html;
    }
    if (protocol === 'visual') return this.renderVisual(metric, index);
    if (metric.type === 'calc') return '<div class="jmb-panel"><div class="jmb-panel-title">Source mapping</div><p class="text-muted mb-0">Calculated metric sources are configured in the Logic tab.</p></div>';
    const fields = PROTOCOL_FIELDS[protocol] || [];
    if (!fields.length) return '<div class="jmb-panel"><div class="jmb-panel-title">Source mapping</div><p class="text-muted mb-0">This protocol has no metric-level source fields.</p></div>';
    let html = '<div class="jmb-panel"><div class="jmb-panel-title">Protocol fields (' + esc(protocol) + ')</div><div class="row g-2">';
    fields.forEach(function (field) {
      html += '<div class="col-md-4">' + input(field.replace(/_/g, ' '), metric[field] == null ? '' : metric[field], 'data-smart-number="1" data-jmb-path="' + field + '" data-index="' + index + '"') + '</div>';
    });
    return html + '</div></div>';
  };

  MetricBuilder.prototype.renderLogic = function (metric, index) {
    if (metric.type !== 'calc') {
      return '<div class="jmb-panel"><div class="jmb-panel-title">Calculation</div><p class="text-muted mb-0">Change the metric type to Calculate / derived to configure formula logic.</p></div>';
    }
    const self = this;
    const calc = metric.calc || (metric.calc = {});
    const inputs = Array.isArray(calc.inputs) ? calc.inputs : (calc.inputs = []);
    const trigger = calc.trigger && typeof calc.trigger === 'object' ? calc.trigger : (calc.trigger = { mode: 'interval', intervalSec: 30 });
    let html = '<div class="jmb-panel"><div class="jmb-panel-title">Calculation</div><p class="jmb-help">Use input variables in the formula, for example (a + b) / 2.</p>';
    html += '<div class="mb-3">' + input('Formula', calc.formula || '', 'data-jmb-path="calc.formula" data-index="' + index + '" placeholder="(a + b) / 2"') + '</div>';
    html += '<div class="d-flex justify-content-between align-items-center mb-2"><strong>Inputs (variable → source)</strong>' + (this.readOnly ? '' : '<button type="button" class="btn btn-outline-primary btn-sm" data-jmb-action="add-input" data-index="' + index + '"><i class="bi bi-plus"></i> Add input</button>') + '</div>';
    inputs.forEach(function (sourceInput, inputIndex) {
      const source = sourceInput.source === 'random' ? 'random' : 'device';
      html += '<div class="jmb-input-row"><div class="row g-2 align-items-end">';
      html += '<div class="col-md-2">' + input('Variable', sourceInput.var || '', 'data-jmb-input-path="var" data-index="' + index + '" data-input-index="' + inputIndex + '" placeholder="a"') + '</div>';
      html += '<div class="col-md-2">' + select('Source', [['device', 'Device metric'], ['random', 'Random number']], source, 'data-jmb-input-path="source" data-index="' + index + '" data-input-index="' + inputIndex + '" data-rerender="1"') + '</div>';
      if (source === 'random') {
        html += '<div class="col-md-3">' + input('Minimum', sourceInput.min == null ? 0 : sourceInput.min, 'type="number" step="any" data-number="1" data-jmb-input-path="min" data-index="' + index + '" data-input-index="' + inputIndex + '"') + '</div>';
        html += '<div class="col-md-3">' + input('Maximum', sourceInput.max == null ? 1 : sourceInput.max, 'type="number" step="any" data-number="1" data-jmb-input-path="max" data-index="' + index + '" data-input-index="' + inputIndex + '"') + '</div>';
      } else {
        const deviceOptions = [['', 'This device']].concat(self.devices.map((device) => [String(device.id), device.name + ' (#' + device.id + ')']));
        html += '<div class="col-md-3">' + select('Device', deviceOptions, sourceInput.deviceId == null ? '' : sourceInput.deviceId, 'data-jmb-input-path="deviceId" data-index="' + index + '" data-input-index="' + inputIndex + '" data-rerender="1"') + '</div>';
        html += '<div class="col-md-3">' + select('Metric', self.metricOptionsForDevice(sourceInput.deviceId, sourceInput.metricKey), sourceInput.metricKey || '', 'data-jmb-input-path="metricKey" data-index="' + index + '" data-input-index="' + inputIndex + '"') + '</div>';
      }
      html += '<div class="col-md-2">' + (self.readOnly ? '' : '<button type="button" class="btn btn-outline-danger btn-sm w-100" data-jmb-action="remove-input" data-index="' + index + '" data-input-index="' + inputIndex + '"><i class="bi bi-trash"></i></button>') + '</div></div></div>';
    });
    html += '<div class="row g-2 mt-2">';
    html += '<div class="col-md-3">' + select('Trigger mode', [['interval', 'Interval'], ['on_pull', 'On metric pull'], ['schedule', 'Schedule (cron)']], trigger.mode || 'interval', 'data-jmb-path="calc.trigger.mode" data-index="' + index + '" data-rerender="1"') + '</div>';
    if ((trigger.mode || 'interval') === 'interval') html += '<div class="col-md-3">' + input('Interval (seconds)', trigger.intervalSec == null ? 30 : trigger.intervalSec, 'type="number" min="1" data-number="1" data-jmb-path="calc.trigger.intervalSec" data-index="' + index + '"') + '</div>';
    if (trigger.mode === 'schedule') html += '<div class="col-md-6">' + input('Cron', trigger.cron || '', 'data-jmb-path="calc.trigger.cron" data-index="' + index + '" placeholder="*/5 * * * *"') + '</div>';
    html += '<div class="col-md-3">' + input('Decimals', calc.decimals == null ? 2 : calc.decimals, 'type="number" min="0" max="8" data-number="1" data-jmb-path="calc.decimals" data-index="' + index + '"') + '</div>';
    html += '<div class="col-md-3">' + input('Max input age (seconds)', calc.maxAgeSec == null ? '' : calc.maxAgeSec, 'type="number" min="0" data-number="1" data-jmb-path="calc.maxAgeSec" data-index="' + index + '"') + '</div>';
    return html + '</div></div>';
  };

  MetricBuilder.prototype.renderChannels = function (selectedIds, attrsPrefix) {
    const selected = Array.isArray(selectedIds) ? selectedIds.map(Number) : [];
    if (!this.channels.length) return '<div class="jmb-help">No notification channels configured.</div>';
    return '<div class="jmb-channel-grid">' + this.channels.map(function (channel) {
      const id = Number(channel.id);
      const name = channel.name || ('Channel #' + id);
      const inactive = channel.is_active === false || channel.is_active === 'f';
      return '<label class="form-check jmb-channel"><input class="form-check-input" type="checkbox" value="' + id + '" ' + attrsPrefix + ' ' + (selected.includes(id) ? 'checked' : '') + '><span class="form-check-label">' + esc(name) + (inactive ? ' <small>(Inactive)</small>' : '') + '</span></label>';
    }).join('') + '</div>';
  };

  MetricBuilder.prototype.renderDuration = function (trigger, metricIndex, triggerIndex) {
    const windows = Array.isArray(trigger.duration) ? trigger.duration : (trigger.duration = []);
    const selected = durationToScheduleSlots(windows);
    let html = '<div class="jmb-subsection jmb-schedule"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><strong class="d-block">Active schedule</strong><span class="jmb-help">' + (windows.length ? windows.length + ' active window' + (windows.length === 1 ? '' : 's') : 'Always active') + '</span></div>';
    if (!this.readOnly && windows.length) {
      html += '<button type="button" class="btn btn-outline-secondary btn-sm" data-jmb-action="clear-schedule" data-index="' + metricIndex + '" data-trigger-index="' + triggerIndex + '">Clear</button>';
    }
    html += '</div><div class="jmb-schedule-scroll"><div class="jmb-schedule-grid"><div class="jmb-schedule-scale"><span></span><span>00:00</span><span>06:00</span><span>12:00</span><span>24:00</span></div>';
    DAYS.forEach(function (day) {
      html += '<div class="jmb-schedule-day"><span class="jmb-schedule-day-label">' + day[1] + '</span>';
      for (let slot = 0; slot < SCHEDULE_SLOT_COUNT; slot += 1) {
        const start = minutesToTime(slot * SCHEDULE_SLOT_MINUTES);
        const end = minutesToTime(Math.min((23 * 60) + 59, ((slot + 1) * SCHEDULE_SLOT_MINUTES) - 1));
        const active = selected[day[0]].has(slot);
        html += '<button type="button" class="jmb-schedule-slot' + (active ? ' active' : '') + '" aria-label="' + day[1] + ' ' + start + '-' + end + '" aria-pressed="' + (active ? 'true' : 'false') + '" title="' + day[1] + ' ' + start + '-' + end + '" data-jmb-schedule-slot="1" data-index="' + metricIndex + '" data-trigger-index="' + triggerIndex + '" data-day="' + day[0] + '" data-slot="' + slot + '"></button>';
      }
      html += '</div>';
    });
    html += '</div></div>';
    if (windows.length) {
      html += '<div class="jmb-schedule-summary">';
      windows.slice(0, 4).forEach(function (windowItem) {
        html += '<span>' + esc(formatScheduleDays(windowItem.days)) + ' ' + esc(windowItem.start) + '-' + esc(windowItem.end) + '</span>';
      });
      if (windows.length > 4) html += '<span>+' + (windows.length - 4) + ' more</span>';
      html += '</div>';
    }
    return html + '</div>';
  };

  MetricBuilder.prototype.renderAlarms = function (metric, index) {
    let html = '<div class="jmb-panel"><div class="jmb-panel-title">Normal condition</div><div class="row g-2">';
    html += '<div class="col-md-2">' + select('Operator', OPS, metric.normal_condition.operator || '', 'data-jmb-path="normal_condition.operator" data-index="' + index + '"') + '</div>';
    html += '<div class="col-md-3">' + input('Value', metric.normal_condition.value == null ? '' : metric.normal_condition.value, 'data-smart-number="1" data-jmb-path="normal_condition.value" data-index="' + index + '"') + '</div>';
    html += '<div class="col-md-7">' + input('Message', metric.normal_condition.message || '', 'data-jmb-path="normal_condition.message" data-index="' + index + '"') + '</div></div>';
    html += '<div class="mt-3"><strong class="d-block mb-2">Notify channels when value returns to normal</strong>' + this.renderChannels(metric.normal_condition.channels, 'data-jmb-normal-channel="1" data-index="' + index + '"') + '</div></div>';
    html += '<div class="jmb-panel mt-3"><div class="d-flex justify-content-between align-items-center mb-3"><div class="jmb-panel-title mb-0">Triggers</div>' + (this.readOnly ? '' : '<button type="button" class="btn btn-primary btn-sm" data-jmb-action="add-trigger" data-index="' + index + '"><i class="bi bi-plus"></i> Add trigger</button>') + '</div>';
    if (!metric.triggers.length) html += '<div class="jmb-empty-inline">No alarm triggers configured.</div>';
    metric.triggers.forEach(function (trigger, triggerIndex) {
      const clear = trigger.clear_condition || (trigger.clear_condition = {});
      html += '<div class="jmb-trigger-row"><div class="d-flex justify-content-between align-items-center mb-3"><strong>Trigger ' + (triggerIndex + 1) + '</strong>' + (this.readOnly ? '' : '<button type="button" class="btn btn-outline-danger btn-sm" data-jmb-action="remove-trigger" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"><i class="bi bi-trash"></i> Remove</button>') + '</div>';
      html += '<div class="jmb-subsection"><strong class="d-block mb-2">Rule / Clear</strong><div class="row g-2">';
      html += '<div class="col-md-2">' + select('Enable', [['true', 'Active'], ['false', 'Inactive']], String(isExplicitlyTrue(trigger.enable)), 'data-jmb-trigger-path="enable" data-index="' + index + '" data-trigger-index="' + triggerIndex + '" data-boolean="1"') + '</div>';
      html += '<div class="col-md-2">' + select('Operator', OPS, trigger.operator || '', 'data-jmb-trigger-path="operator" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div>';
      html += '<div class="col-md-2">' + input('Value', trigger.value == null ? '' : trigger.value, 'data-smart-number="1" data-jmb-trigger-path="value" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div>';
      html += '<div class="col-md-6">' + input('Message', trigger.message || '', 'data-jmb-trigger-path="message" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div>';
      html += '<div class="col-md-2">' + select('Clear operator', OPS, clear.operator || '', 'data-jmb-trigger-clear-path="operator" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div>';
      html += '<div class="col-md-2">' + input('Clear value', clear.value == null ? '' : clear.value, 'data-smart-number="1" data-jmb-trigger-clear-path="value" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div>';
      html += '<div class="col-md-8">' + input('Clear message', clear.message || '', 'data-jmb-trigger-clear-path="message" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div></div></div>';
      html += '<div class="jmb-subsection"><strong class="d-block mb-2">Severity & repeat</strong><div class="row g-2"><div class="col-md-4">' + select('Severity', SEVERITY_OPTIONS, normalizeSeverity(trigger.severity), 'data-jmb-trigger-path="severity" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div>';
      html += '<div class="col-md-4">' + input('Repeat interval (seconds)', trigger.repeat_interval == null ? 0 : trigger.repeat_interval, 'type="number" min="0" data-number="1" data-jmb-trigger-path="repeat_interval" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div></div></div>';
      html += this.renderDuration(trigger, index, triggerIndex);
      html += '<div class="jmb-subsection"><strong class="d-block mb-2">Notify channels</strong>' + this.renderChannels(trigger.channels, 'data-jmb-trigger-channel="1" data-index="' + index + '" data-trigger-index="' + triggerIndex + '"') + '</div></div>';
    }, this);
    return html + '</div>';
  };

  MetricBuilder.prototype.renderNotes = function (metric, index) {
    return '<div class="jmb-panel"><div class="jmb-panel-title">Description</div><p class="jmb-help">Describe what this metric measures and its expected operating range.</p>' + textarea('Description', metric.description || '', 'data-jmb-path="description" data-index="' + index + '"', 'Metric description') + '</div>';
  };

  MetricBuilder.prototype.renderDetail = function (metric, index) {
    const protocol = metric.protocol || this.getProtocol();
    const triggerCount = Array.isArray(metric.triggers) ? metric.triggers.length : 0;
    let html = '<section class="jmb-detail"><div class="jmb-detail-header"><div><div class="d-flex align-items-center gap-2 flex-wrap"><h5 class="mb-0">' + esc(metric.label || metric.key || 'Unnamed metric') + '</h5><span class="badge ' + (boolValue(metric.enable, true) ? 'text-bg-success' : 'text-bg-secondary') + '">' + (boolValue(metric.enable, true) ? 'Active' : 'Inactive') + '</span></div>';
    html += '<div class="jmb-badges mt-2"><span class="badge text-bg-light border">' + esc(metric.key || 'no key') + '</span><span class="badge text-bg-secondary">' + esc(metric.type || protocol) + '</span><span class="badge text-bg-light border">' + esc(metric.datatype || '-') + '</span><span class="badge text-bg-light border">' + triggerCount + ' trigger' + (triggerCount === 1 ? '' : 's') + '</span></div></div>';
    if (!this.readOnly) html += '<div class="jmb-actions"><button type="button" class="btn btn-outline-secondary btn-sm" data-jmb-action="duplicate" data-index="' + index + '"><i class="bi bi-copy"></i> Duplicate</button><button type="button" class="btn btn-outline-danger btn-sm" data-jmb-action="remove" data-index="' + index + '"><i class="bi bi-trash"></i> Remove</button></div>';
    html += '</div><div class="jmb-tabs">';
    [['setup', 'sliders', 'Setup'], ['source', 'link-45deg', 'Source'], ['logic', 'calculator', 'Logic'], ['alarms', 'bell', 'Alarms'], ['notes', 'card-text', 'Notes']].forEach(function (tab) {
      html += '<button type="button" class="jmb-tab ' + (this.activeTab === tab[0] ? 'active' : '') + '" data-jmb-action="tab" data-tab="' + tab[0] + '"><i class="bi bi-' + tab[1] + '"></i> ' + tab[2] + '</button>';
    }, this);
    html += '</div><div class="jmb-tab-content">';
    if (this.activeTab === 'setup') html += this.renderSetup(metric, index);
    if (this.activeTab === 'source') html += this.renderSource(metric, index);
    if (this.activeTab === 'logic') html += this.renderLogic(metric, index);
    if (this.activeTab === 'alarms') html += this.renderAlarms(metric, index);
    if (this.activeTab === 'notes') html += this.renderNotes(metric, index);
    return html + '</div></section>';
  };

  MetricBuilder.prototype.visibleRows = function () {
    const query = this.search.trim().toLowerCase();
    return this.metrics.map((metric, index) => ({ metric, index })).filter((row) => {
      const matchesSearch = !query || [row.metric.key, row.metric.label, row.metric.type, row.metric.protocol].join(' ').toLowerCase().includes(query);
      const active = boolValue(row.metric.enable, true);
      const matchesFilter = this.filter === 'all'
        || (this.filter === 'active' && active)
        || (this.filter === 'inactive' && !active)
        || (this.filter === 'triggers' && Array.isArray(row.metric.triggers) && row.metric.triggers.length > 0);
      return matchesSearch && matchesFilter;
    });
  };

  MetricBuilder.prototype.render = function () {
    if (!this.container) return;
    const currentList = this.container.querySelector('.jmb-list');
    const listScrollTop = currentList ? currentList.scrollTop : (this.listScrollTop || 0);
    const pageScrollX = global.scrollX || 0;
    const pageScrollY = global.scrollY || 0;
    const activeCount = this.metrics.filter((metric) => boolValue(metric.enable, true)).length;
    const triggerCount = this.metrics.reduce((sum, metric) => sum + (Array.isArray(metric.triggers) ? metric.triggers.length : 0), 0);
    const calcCount = this.metrics.filter((metric) => metric.type === 'calc').length;
    const rows = this.visibleRows();
    const totalPages = Math.max(1, Math.ceil(rows.length / LIST_PAGE_SIZE));
    if (this.listPage >= totalPages) this.listPage = totalPages - 1;
    if (this.listPage < 0) this.listPage = 0;
    const pageRows = rows.length > LIST_PAGE_SIZE
      ? rows.slice(this.listPage * LIST_PAGE_SIZE, (this.listPage + 1) * LIST_PAGE_SIZE)
      : rows;
    if (rows.length && !rows.some((row) => row.index === this.selectedIndex)) this.selectedIndex = rows[0].index;
    if (!rows.length) this.selectedIndex = -1;

    let html = '<div class="jmb-summary"><div><h6 class="mb-1">Metrics definition</h6><div class="jmb-badges"><span class="badge text-bg-light border">' + this.metrics.length + ' metrics</span><span class="badge text-bg-light border">' + activeCount + ' active</span><span class="badge text-bg-light border">' + triggerCount + ' triggers</span><span class="badge text-bg-light border">' + calcCount + ' calc</span>' + (this.readOnly ? '<span class="badge text-bg-info">Read-only from template</span>' : '') + '</div></div><div class="jmb-actions">';
    html += '<button type="button" class="btn btn-outline-secondary btn-sm" data-jmb-action="view-json"><i class="bi bi-braces"></i> View JSON</button>';
    if (this.jsonEditable) html += '<button type="button" class="btn btn-outline-warning btn-sm" data-jmb-action="edit-json"><i class="bi bi-pencil-square"></i> Edit JSON</button>';
    if (!this.readOnly) html += '<button type="button" class="btn btn-primary btn-sm" data-jmb-action="add"><i class="bi bi-plus"></i> Add metric</button><button type="button" class="btn btn-outline-primary btn-sm" data-jmb-action="add-calc"><i class="bi bi-calculator"></i> Calculate</button>';
    html += '</div></div>';
    html += '<div class="jmb-toolbar"><div class="row g-2"><div class="col-md-8"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="jmb-search" class="form-control" placeholder="Search metrics" value="' + esc(this.search) + '"></div></div><div class="col-md-4">' + select('', [['all', 'All metrics'], ['active', 'Active only'], ['inactive', 'Inactive only'], ['triggers', 'With triggers']], this.filter, 'id="jmb-filter"') + '</div></div></div>';
    html += this.renderSnapshotTriggers();
    if (!rows.length) {
      html += '<div class="jmb-empty"><i class="bi bi-sliders2 fs-1 d-block mb-2"></i>' + (this.metrics.length ? 'No metrics match the current filter.' : 'No metrics yet.') + '</div>';
    } else {
      html += '<div class="jmb-workspace"><aside class="jmb-list"><div class="jmb-list-title"><span>Metric list</span><span>' + rows.length + ' / ' + this.metrics.length + '</span></div>';
      if (rows.length > LIST_PAGE_SIZE) {
        html += '<div class="d-flex justify-content-between align-items-center gap-2 px-2 pb-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-jmb-action="list-prev"' + (this.listPage <= 0 ? ' disabled' : '') + '><i class="bi bi-chevron-left"></i></button><small class="text-muted">Page ' + (this.listPage + 1) + ' / ' + totalPages + '</small><button type="button" class="btn btn-outline-secondary btn-sm" data-jmb-action="list-next"' + (this.listPage >= totalPages - 1 ? ' disabled' : '') + '><i class="bi bi-chevron-right"></i></button></div>';
      }
      pageRows.forEach(function (row) {
        const selected = row.index === this.selectedIndex;
        html += '<button type="button" class="jmb-list-item ' + (selected ? 'active' : '') + '" data-jmb-action="select" data-index="' + row.index + '"><div class="d-flex justify-content-between gap-2"><strong>' + esc(row.metric.label || row.metric.key || 'Unnamed metric') + '</strong><span class="jmb-status-dot ' + (boolValue(row.metric.enable, true) ? 'active' : '') + '"></span></div><small>' + esc(row.metric.key || 'no key') + '</small><div class="jmb-badges mt-2"><span class="badge text-bg-secondary">' + esc(row.metric.type || row.metric.protocol || '-') + '</span><span class="badge text-bg-light border">' + esc(row.metric.datatype || '-') + '</span>' + ((row.metric.triggers || []).length ? '<span class="badge text-bg-light border">' + row.metric.triggers.length + ' triggers</span>' : '') + '</div></button>';
      }, this);
      html += '</aside>' + this.renderDetail(this.metrics[this.selectedIndex], this.selectedIndex) + '</div>';
    }
    html += '<div class="modal fade" id="' + this.modalId + '" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Metrics JSON</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><pre class="jmb-json" data-jmb-json-view="1">Open this dialog to load JSON.</pre></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>';
    if (this.jsonEditable) {
      html += '<div class="modal fade" id="' + this.editModalId + '" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">';
      html += '<div class="modal-header"><h5 class="modal-title">Edit Metrics JSON</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
      html += '<div class="modal-body"><p class="jmb-help mb-2">Edit the complete metrics array. JSON must be valid and the top level must be an array.</p>';
      html += '<textarea class="form-control jmb-json-editor" data-jmb-json-editor="1" spellcheck="false"></textarea>';
      html += '<div class="invalid-feedback" data-jmb-json-error="1"></div></div>';
      html += '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning" data-jmb-action="apply-json"><i class="bi bi-check2"></i> Apply JSON</button></div>';
      html += '</div></div></div>';
    }
    this.container.innerHTML = html;
    const nextList = this.container.querySelector('.jmb-list');
    if (nextList) {
      nextList.scrollTop = listScrollTop;
      this.listScrollTop = listScrollTop;
      nextList.addEventListener('scroll', function () { this.listScrollTop = nextList.scrollTop; }.bind(this), { passive: true });
    }
    if (pageScrollX || pageScrollY) {
      global.requestAnimationFrame(function () { global.scrollTo(pageScrollX, pageScrollY); });
    }
    this.save();
    if (this.readOnly) {
      this.container.querySelectorAll('.jmb-detail input:not([data-jmb-readonly-editable="1"]), .jmb-detail select:not([data-jmb-readonly-editable="1"]), .jmb-detail textarea:not([data-jmb-readonly-editable="1"])').forEach(function (element) { element.disabled = true; });
    }
    this.bindEvents();
  };

  MetricBuilder.prototype.readElementValue = function (element) {
    if (element.dataset.boolean === '1') return element.value === 'true';
    if (element.dataset.number === '1') return element.value === '' ? '' : Number(element.value);
    if (element.dataset.smartNumber === '1') return numberOrText(element.value);
    if (element.dataset.list === '1') return element.value.split(',').map((value) => value.trim()).filter(Boolean);
    return element.value;
  };

  MetricBuilder.prototype.setChannel = function (metricIndex, triggerIndex, channelId, checked, isNormal) {
    if (this.readOnly) return;
    const target = isNormal ? this.metrics[metricIndex].normal_condition : this.metrics[metricIndex].triggers[triggerIndex];
    const channels = Array.isArray(target.channels) ? target.channels.map(Number) : [];
    const id = Number(channelId);
    target.channels = checked ? Array.from(new Set(channels.concat(id))) : channels.filter((value) => value !== id);
    this.save();
  };

  MetricBuilder.prototype.bindEvents = function () {
    const self = this;
    const search = this.container.querySelector('#jmb-search');
    if (search) search.addEventListener('input', function () {
      self.search = this.value;
      self.listPage = 0;
      const position = this.selectionStart;
      self.render();
      const next = self.container.querySelector('#jmb-search');
      if (next) { next.focus(); next.setSelectionRange(position, position); }
    });
    const filter = this.container.querySelector('#jmb-filter');
    if (filter) filter.addEventListener('change', function () { self.filter = this.value; self.listPage = 0; self.render(); });
    this.container.querySelectorAll('[data-jmb-path]').forEach(function (element) {
      if (element.dataset.jmbReadonlyEditable === '1') {
        element.addEventListener('change', function () {
          const index = Number(this.dataset.index);
          if (!self.metrics[index]) return;
          const source = self.metrics[index].source && typeof self.metrics[index].source === 'object'
            ? self.metrics[index].source
            : (self.metrics[index].source = {});
          source.gateway_device_id = Number(self.getGatewayDeviceId());
          source.gateway_metric_key = this.value;
          self.save();
        });
        return;
      }
      element.addEventListener('change', function () {
        self.update(Number(this.dataset.index), this.dataset.jmbPath, self.readElementValue(this), this.dataset.rerender === '1', this.dataset.jmbReadonlyEditable === '1');
      });
    });
    this.container.querySelectorAll('[data-jmb-input-path]').forEach(function (element) {
      element.addEventListener('change', function () {
        if (self.readOnly) return;
        const sourceInput = self.metrics[Number(this.dataset.index)].calc.inputs[Number(this.dataset.inputIndex)];
        sourceInput[this.dataset.jmbInputPath] = self.readElementValue(this);
        if (this.dataset.jmbInputPath === 'source') {
          if (this.value === 'random') {
            Object.assign(sourceInput, { source: 'random', min: 0, max: 1 });
            delete sourceInput.deviceId;
            delete sourceInput.metricKey;
          } else {
            Object.assign(sourceInput, { source: 'device', deviceId: '', metricKey: '' });
            delete sourceInput.min;
            delete sourceInput.max;
          }
        }
        if (this.dataset.jmbInputPath === 'deviceId') sourceInput.metricKey = '';
        self.save();
        if (this.dataset.rerender === '1') self.render();
      });
    });
    this.container.querySelectorAll('[data-jmb-trigger-path]').forEach(function (element) {
      element.addEventListener('change', function () {
        if (self.readOnly) return;
        self.metrics[Number(this.dataset.index)].triggers[Number(this.dataset.triggerIndex)][this.dataset.jmbTriggerPath] = self.readElementValue(this);
        self.save();
      });
    });
    this.container.querySelectorAll('[data-jmb-trigger-clear-path]').forEach(function (element) {
      element.addEventListener('change', function () {
        if (self.readOnly) return;
        const trigger = self.metrics[Number(this.dataset.index)].triggers[Number(this.dataset.triggerIndex)];
        if (!trigger.clear_condition) trigger.clear_condition = {};
        trigger.clear_condition[this.dataset.jmbTriggerClearPath] = self.readElementValue(this);
        self.save();
      });
    });
    this.container.querySelectorAll('[data-jmb-normal-channel]').forEach(function (element) {
      element.addEventListener('change', function () { self.setChannel(Number(this.dataset.index), null, this.value, this.checked, true); });
    });
    this.container.querySelectorAll('[data-jmb-trigger-channel]').forEach(function (element) {
      element.addEventListener('change', function () { self.setChannel(Number(this.dataset.index), Number(this.dataset.triggerIndex), this.value, this.checked, false); });
    });
    this.container.querySelectorAll('[data-jmb-schedule-slot]').forEach(function (element) {
      element.addEventListener('pointerdown', function (event) {
        if (self.readOnly || event.button !== 0) return;
        event.preventDefault();
        const index = Number(this.dataset.index);
        const triggerIndex = Number(this.dataset.triggerIndex);
        const day = Number(this.dataset.day);
        const slot = Number(this.dataset.slot);
        self.scheduleDrag = {
          day: day,
          startSlot: slot,
          mode: this.getAttribute('aria-pressed') === 'true' ? 'remove' : 'add'
        };
        self.applyScheduleRange(index, triggerIndex, day, slot);
      });
      element.addEventListener('pointerenter', function () {
        if (!self.scheduleDrag) return;
        self.applyScheduleRange(Number(this.dataset.index), Number(this.dataset.triggerIndex), Number(this.dataset.day), Number(this.dataset.slot));
      });
    });
    this.container.querySelectorAll('[data-jmb-action]').forEach(function (element) {
      element.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const action = this.dataset.jmbAction;
        const index = Number(this.dataset.index);
        if (action === 'select') { self.selectedIndex = index; self.activeTab = 'setup'; self.render(); }
        if (action === 'tab') { self.activeTab = this.dataset.tab; self.render(); }
        if (action === 'view-json') {
          const modalElement = document.getElementById(self.modalId);
          const jsonView = modalElement ? modalElement.querySelector('[data-jmb-json-view]') : null;
          if (jsonView) jsonView.textContent = JSON.stringify(self.cleanMetrics(), null, 2);
          if (global.bootstrap && global.bootstrap.Modal) global.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
        if (action === 'edit-json') {
          const modalElement = document.getElementById(self.editModalId);
          const editor = modalElement ? modalElement.querySelector('[data-jmb-json-editor]') : null;
          if (editor) editor.value = JSON.stringify(self.cleanMetrics(), null, 2);
          if (global.bootstrap && global.bootstrap.Modal) global.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
        if (action === 'list-prev' && self.listPage > 0) {
          self.listPage -= 1;
          self.render();
        }
        if (action === 'list-next') {
          const nextTotalPages = Math.max(1, Math.ceil(self.visibleRows().length / LIST_PAGE_SIZE));
          if (self.listPage < nextTotalPages - 1) {
            self.listPage += 1;
            self.render();
          }
        }
        if (action === 'apply-json') {
          const modalElement = document.getElementById(self.editModalId);
          const editor = modalElement.querySelector('[data-jmb-json-editor]');
          const errorElement = modalElement.querySelector('[data-jmb-json-error]');
          try {
            const parsed = JSON.parse(editor.value);
            if (!Array.isArray(parsed)) throw new Error('Top-level JSON must be an array.');
            const nextMetrics = parsed.map(function (metric) {
              return normalizeMetric(metric, self.getProtocol());
            });
            editor.classList.remove('is-invalid');
            errorElement.textContent = '';
            const applyMetrics = function () {
              self.metrics = nextMetrics;
              self.selectedIndex = nextMetrics.length ? 0 : -1;
              self.activeTab = 'setup';
              self.render();
            };
            if (global.bootstrap && global.bootstrap.Modal) {
              modalElement.addEventListener('hidden.bs.modal', applyMetrics, { once: true });
              global.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
            } else {
              applyMetrics();
            }
          } catch (error) {
            editor.classList.add('is-invalid');
            errorElement.textContent = error && error.message ? error.message : 'Invalid JSON.';
          }
        }
        if (action === 'add') self.add('metric');
        if (action === 'add-calc') self.add('calc');
        if (action === 'remove') self.remove(index);
        if (action === 'duplicate') self.duplicate(index);
        if (action === 'add-input') self.addCalcInput(index);
        if (action === 'remove-input') self.removeCalcInput(index, Number(this.dataset.inputIndex));
        if (action === 'add-trigger') self.addTrigger(index);
        if (action === 'remove-trigger') self.removeTrigger(index, Number(this.dataset.triggerIndex));
        if (action === 'add-snapshot-trigger') self.addSnapshotTrigger();
        if (action === 'remove-snapshot-trigger') self.removeSnapshotTrigger(Number(this.dataset.snapshotIndex));
        if (action === 'clear-schedule') {
          self.metrics[index].triggers[Number(this.dataset.triggerIndex)].duration = [];
          self.save();
          self.render();
        }
      });
    });

    this.container.querySelectorAll('[data-jmb-snapshot-path]').forEach(function (element) {
      element.addEventListener('change', function () {
        let value = self.readElementValue(this);
        const snapshotIndex = Number(this.dataset.snapshotIndex);
        const path = this.dataset.jmbSnapshotPath;
        if (path === 'source_device_id') {
          value = this.value === '' ? '' : Number(this.value);
          self.updateSnapshotTrigger(snapshotIndex, 'source_metric_key', '', false);
          self.updateSnapshotTrigger(snapshotIndex, 'source_trigger_index', '', false);
        }
        if (path === 'source_metric_key') {
          self.updateSnapshotTrigger(snapshotIndex, 'source_trigger_index', '', false);
        }
        if (path === 'source_trigger_index') {
          value = this.value === '' ? '' : Number(this.value);
        }
        self.updateSnapshotTrigger(
          snapshotIndex,
          path,
          value,
          this.dataset.rerender === '1'
        );
      });
      if (element.tagName === 'INPUT' && element.type !== 'checkbox') {
        element.addEventListener('input', function () {
          self.updateSnapshotTrigger(
            Number(this.dataset.snapshotIndex),
            this.dataset.jmbSnapshotPath,
            self.readElementValue(this),
            false
          );
        });
      }
    });
  };

  global.JConnectMetricBuilder = {
    create: function (options) { return new MetricBuilder(options || {}); },
    parseMetrics
  };
})(window);
