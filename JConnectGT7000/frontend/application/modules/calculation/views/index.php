<?php
$calculations = isset($calculations) && is_array($calculations) ? $calculations : [];
$calcDevices = isset($calc_devices) && is_array($calc_devices) ? $calc_devices : [];
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Calculate</h4>
        <p class="text-muted mb-0">Create derived metrics from device values or random inputs.</p>
      </div>
      <button class="btn btn-primary" type="button" onclick="openCalculation()">
        <i class="bi bi-plus-circle"></i> New calculation
      </button>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-body">
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input id="calc-search" class="form-control" placeholder="Search device, metric, or formula">
        </div>
        <div class="table-responsive">
          <table class="table align-middle" id="calc-table">
            <thead>
              <tr><th>Device</th><th>Metric</th><th>Formula</th><th>Inputs</th><th>Trigger</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($calculations as $row):
                $metric = $row['metric'];
                $calc = isset($metric['calc']) && is_array($metric['calc']) ? $metric['calc'] : [];
                $inputs = isset($calc['inputs']) && is_array($calc['inputs']) ? $calc['inputs'] : [];
                $trigger = isset($calc['trigger']) && is_array($calc['trigger']) ? $calc['trigger'] : [];
                $searchText = strtolower($row['device_name'] . ' ' . ($metric['key'] ?? '') . ' ' . ($calc['formula'] ?? ''));
              ?>
                <tr data-search="<?= htmlspecialchars($searchText) ?>">
                  <td><strong><?= htmlspecialchars($row['device_name']) ?></strong><div class="small text-muted">#<?= (int) $row['device_id'] ?></div></td>
                  <td><code><?= htmlspecialchars($metric['key'] ?? '-') ?></code><div class="small text-muted"><?= htmlspecialchars($metric['label'] ?? '') ?></div></td>
                  <td><code><?= htmlspecialchars($calc['formula'] ?? '-') ?></code></td>
                  <td>
                    <?php foreach ($inputs as $input): ?>
                      <div class="small"><code><?= htmlspecialchars($input['var'] ?? '?') ?></code> =
                        <?php if (($input['source'] ?? '') === 'random'): ?>
                          random[<?= htmlspecialchars($input['min'] ?? 0) ?>..<?= htmlspecialchars($input['max'] ?? 1) ?>]
                        <?php else: ?>
                          <?= isset($input['deviceId']) ? 'dev' . (int) $input['deviceId'] . ':' : '' ?><?= htmlspecialchars($input['metricKey'] ?? '') ?>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </td>
                  <td><span class="badge text-bg-success"><?= htmlspecialchars($trigger['mode'] ?? 'interval') ?></span></td>
                  <td class="text-end">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick='openCalculation(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>Edit</button>
                    <form class="d-inline" method="post" action="<?= base_url('calculation/delete') ?>" onsubmit="return confirm('Remove this calculation?')">
                      <input type="hidden" name="device_id" value="<?= (int) $row['device_id'] ?>">
                      <input type="hidden" name="key" value="<?= htmlspecialchars($metric['key'] ?? '') ?>">
                      <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (count($calculations) === 0): ?>
                <tr class="calc-empty"><td colspan="6" class="text-center text-muted py-5">No calculations yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="calculation-modal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <form class="modal-content" method="post" action="<?= base_url('calculation/save') ?>" id="calculation-form">
      <div class="modal-header"><h5 class="modal-title">Calculation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="original_device_id" id="original-device-id">
        <input type="hidden" name="original_key" id="original-key">
        <input type="hidden" name="inputs_json" id="inputs-json">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Output device</label><select class="form-select" name="device_id" id="calc-device" required><?= implode('', array_map(function ($d) { return '<option value="' . (int) $d['id'] . '">' . htmlspecialchars($d['name']) . ' (#' . (int) $d['id'] . ')</option>'; }, $calcDevices)) ?></select></div>
          <div class="col-md-3"><label class="form-label">Metric key</label><input class="form-control" name="key" id="calc-key" required></div>
          <div class="col-md-3"><label class="form-label">Label</label><input class="form-control" name="label" id="calc-label"></div>
          <div class="col-md-2"><label class="form-label">Unit</label><input class="form-control" name="unit" id="calc-unit"></div>
          <div class="col-md-2"><label class="form-label">Datatype</label><select class="form-select" name="datatype" id="calc-datatype"><option value="float">float</option><option value="int">int</option></select></div>
          <div class="col-md-10"><label class="form-label">Formula</label><input class="form-control font-monospace" name="formula" id="calc-formula" placeholder="(a + b) / 2" required><div class="form-text">Use the variable names defined in Inputs.</div></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2"><strong>Inputs</strong><button type="button" class="btn btn-outline-primary btn-sm" onclick="addCalcInput()"><i class="bi bi-plus"></i> Add input</button></div>
        <div id="calc-inputs"></div>
        <div class="row g-3 mt-2">
          <div class="col-md-3"><label class="form-label">Trigger</label><select class="form-select" name="trigger_mode" id="trigger-mode" onchange="renderTriggerFields()"><option value="interval">Interval</option><option value="on_pull">On input update</option><option value="schedule">Cron schedule</option></select></div>
          <div class="col-md-3" id="interval-wrap"><label class="form-label">Interval (seconds)</label><input class="form-control" type="number" min="1" name="interval_sec" id="interval-sec" value="30"></div>
          <div class="col-md-6 d-none" id="cron-wrap"><label class="form-label">Cron</label><input class="form-control font-monospace" name="cron" id="calc-cron" placeholder="*/5 * * * *"></div>
          <div class="col-md-3"><label class="form-label">Decimals</label><input class="form-control" type="number" min="0" max="8" name="decimals" id="calc-decimals" value="2"></div>
          <div class="col-md-3"><label class="form-label">Max input age (seconds)</label><input class="form-control" type="number" min="0" name="max_age_sec" id="calc-max-age"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save calculation</button></div>
    </form>
  </div>
</div>

<script>
const calcDevices = <?= json_encode($calcDevices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let calcInputs = [];

function escCalc(value) {
  return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function metricOptions(deviceId, current) {
  const resolvedId = deviceId || document.getElementById('calc-device')?.value;
  const device = calcDevices.find(d => String(d.id) === String(resolvedId));
  const metrics = device && Array.isArray(device.metrics) ? device.metrics : [];
  let html = '<option value="">Select metric</option>';
  metrics.forEach(m => {
    if (m && m.key) html += `<option value="${escCalc(m.key)}" ${String(m.key) === String(current) ? 'selected' : ''}>${escCalc((m.label ? m.label + ' · ' : '') + m.key)}</option>`;
  });
  return html;
}

function renderCalcInputs() {
  const root = document.getElementById('calc-inputs');
  root.innerHTML = '';
  calcInputs.forEach((input, index) => {
    const random = input.source === 'random';
    const deviceOptions = ['<option value="">Same device</option>'].concat(calcDevices.map(d => `<option value="${d.id}" ${String(d.id) === String(input.deviceId) ? 'selected' : ''}>${escCalc(d.name)} (#${d.id})</option>`)).join('');
    root.innerHTML += `<div class="border rounded p-3 mb-2"><div class="row g-2 align-items-end">
      <div class="col-md-2"><label class="form-label">Variable</label><input class="form-control" value="${escCalc(input.var || '')}" onchange="updateCalcInput(${index}, 'var', this.value)"></div>
      <div class="col-md-2"><label class="form-label">Source</label><select class="form-select" onchange="updateCalcInput(${index}, 'source', this.value, true)"><option value="device" ${!random ? 'selected' : ''}>Device metric</option><option value="random" ${random ? 'selected' : ''}>Random</option></select></div>
      ${random
        ? `<div class="col-md-3"><label class="form-label">Minimum</label><input class="form-control" type="number" step="any" value="${escCalc(input.min ?? 0)}" onchange="updateCalcInput(${index}, 'min', Number(this.value))"></div><div class="col-md-3"><label class="form-label">Maximum</label><input class="form-control" type="number" step="any" value="${escCalc(input.max ?? 1)}" onchange="updateCalcInput(${index}, 'max', Number(this.value))"></div>`
        : `<div class="col-md-3"><label class="form-label">Device</label><select class="form-select" onchange="updateCalcInput(${index}, 'deviceId', this.value, true)">${deviceOptions}</select></div><div class="col-md-3"><label class="form-label">Metric</label><select class="form-select" onchange="updateCalcInput(${index}, 'metricKey', this.value)">${metricOptions(input.deviceId, input.metricKey)}</select></div>`}
      <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100" onclick="removeCalcInput(${index})"><i class="bi bi-trash"></i></button></div>
    </div></div>`;
  });
  document.getElementById('inputs-json').value = JSON.stringify(calcInputs);
}

function addCalcInput() {
  calcInputs.push({ var: '', source: 'device', deviceId: '', metricKey: '' });
  renderCalcInputs();
}
function removeCalcInput(index) { calcInputs.splice(index, 1); renderCalcInputs(); }
function updateCalcInput(index, field, value, rerender) {
  calcInputs[index][field] = value;
  if (field === 'source') calcInputs[index] = value === 'random' ? { var: calcInputs[index].var, source: 'random', min: 0, max: 1 } : { var: calcInputs[index].var, source: 'device', deviceId: '', metricKey: '' };
  if (field === 'deviceId') calcInputs[index].metricKey = '';
  document.getElementById('inputs-json').value = JSON.stringify(calcInputs);
  if (rerender) renderCalcInputs();
}

function renderTriggerFields() {
  const mode = document.getElementById('trigger-mode').value;
  document.getElementById('interval-wrap').classList.toggle('d-none', mode !== 'interval');
  document.getElementById('cron-wrap').classList.toggle('d-none', mode !== 'schedule');
}

function openCalculation(row) {
  const metric = row?.metric || {};
  const calc = metric.calc || {};
  document.getElementById('calculation-form').reset();
  document.getElementById('original-device-id').value = row?.device_id || '';
  document.getElementById('original-key').value = metric.key || '';
  document.getElementById('calc-device').value = row?.device_id || (calcDevices[0]?.id || '');
  document.getElementById('calc-key').value = metric.key || '';
  document.getElementById('calc-label').value = metric.label || '';
  document.getElementById('calc-unit').value = metric.unit || '';
  document.getElementById('calc-datatype').value = metric.datatype === 'int' ? 'int' : 'float';
  document.getElementById('calc-formula').value = calc.formula || '';
  document.getElementById('trigger-mode').value = calc.trigger?.mode || 'interval';
  document.getElementById('interval-sec').value = calc.trigger?.intervalSec || 30;
  document.getElementById('calc-cron').value = calc.trigger?.cron || '';
  document.getElementById('calc-decimals').value = calc.decimals ?? 2;
  document.getElementById('calc-max-age').value = calc.maxAgeSec ?? '';
  calcInputs = Array.isArray(calc.inputs) && calc.inputs.length ? JSON.parse(JSON.stringify(calc.inputs)) : [{ var: 'a', source: 'device', deviceId: '', metricKey: '' }];
  renderCalcInputs();
  renderTriggerFields();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('calculation-modal')).show();
}

document.getElementById('calc-search').addEventListener('input', function () {
  const query = this.value.trim().toLowerCase();
  document.querySelectorAll('#calc-table tbody tr[data-search]').forEach(row => { row.style.display = !query || row.dataset.search.includes(query) ? '' : 'none'; });
});
</script>
