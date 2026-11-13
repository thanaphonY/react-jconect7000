<!--begin::App Content Header-->
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5><?php echo lang('view_device_page') ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"> <?php echo lang('home'); ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('device') ?>"> <?php echo lang('devices') ?> </a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($device->name) ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>
<!--end::App Content Header-->

<style>
    .badge.bg-orange { background-color: var(--bs-orange, #fd7e14) !important; color: #fff !important; }
    #deviceViewTabs .nav-link {
        color: inherit;
    }
</style>

<?php
$viewProtocol = strtolower((string) ($protocol ?? ''));
if ($viewProtocol === '' && !empty($template->protocol)) {
    $viewProtocol = strtolower((string) $template->protocol);
}
if ($viewProtocol === '' && !empty($comm['protocol'])) {
    $viewProtocol = strtolower((string) $comm['protocol']);
}

$viewMetrics = [];
if (!empty($device->metrics)) {
    $decodedMetrics = json_decode($device->metrics, true);
    if (is_array($decodedMetrics)) {
        $viewMetrics = $decodedMetrics;
    }
}

$commDisplay = is_array($comm ?? null) ? $comm : [];
$secretKeys = ['password', 'auth_pass', 'priv_pass', 'community'];
?>

<!--begin::App Content-->
<div class="app-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">

        <?php
          $siteNodeName = '';
          if (!empty($device->site_node_id) && $this->db->table_exists('site_nodes')) {
            $siteNodeRow = $this->db->select('name')->get_where('site_nodes', array('id' => (int) $device->site_node_id))->row();
            $siteNodeName = $siteNodeRow ? $siteNodeRow->name : '';
          }
          $customFields = [];
          if (!empty($device->config)) {
            try {
              $config = json_decode($device->config, true);
              if (is_array($config) && isset($config['custom_fields'])) {
                $customFields = $config['custom_fields'];
              }
            } catch (Exception $e) {
              $customFields = [];
            }
          }
          $deviceImageSrc = !empty($device->image)
            ? base_url('uploads/devices/' . $device->image)
            : base_url('uploads/device_templates/' . ($template->image ?? ''));
        ?>

        <div class="card mb-4">
          <div class="card-header pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="deviceViewTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-dashboard-btn" data-bs-toggle="tab" data-bs-target="#tab-dashboard" type="button" role="tab" aria-controls="tab-dashboard" aria-selected="true">
                  <?php echo lang('dashboard') ?>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-layout-btn" data-bs-toggle="tab" data-bs-target="#tab-layout" type="button" role="tab" aria-controls="tab-layout" aria-selected="false">
                  Layout
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-device-info-btn" data-bs-toggle="tab" data-bs-target="#tab-device-info" type="button" role="tab" aria-controls="tab-device-info" aria-selected="false">
                  <?php echo lang('device_information') ?>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-device-data-btn" data-bs-toggle="tab" data-bs-target="#tab-device-data" type="button" role="tab" aria-controls="tab-device-data" aria-selected="false">
                  <?php echo lang('device_data') ?>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-alarm-log-btn" data-bs-toggle="tab" data-bs-target="#tab-alarm-log" type="button" role="tab" aria-controls="tab-alarm-log" aria-selected="false">
                  <?php echo lang('alarm_logs') ?>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-metrics-btn" data-bs-toggle="tab" data-bs-target="#tab-metrics" type="button" role="tab" aria-controls="tab-metrics" aria-selected="false">
                  <?php echo lang('metrics') ?>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-communication-btn" data-bs-toggle="tab" data-bs-target="#tab-communication" type="button" role="tab" aria-controls="tab-communication" aria-selected="false">
                  <?php echo lang('communication') ?>
                </button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="tab-content" id="deviceViewTabContent">
              <div class="tab-pane fade show active" id="tab-dashboard" role="tabpanel" aria-labelledby="tab-dashboard-btn" tabindex="0">
                <?php include 'device_dashboard_mockup.php'; ?>
              </div>

              <div class="tab-pane fade" id="tab-layout" role="tabpanel" aria-labelledby="tab-layout-btn" tabindex="0">
                <?php include 'device_layout_tab.php'; ?>
              </div>

              <div class="tab-pane fade" id="tab-device-info" role="tabpanel" aria-labelledby="tab-device-info-btn" tabindex="0">
                <?php include 'device_info_tab.php'; ?>
              </div>

              <div class="tab-pane fade" id="tab-device-data" role="tabpanel" aria-labelledby="tab-device-data-btn" tabindex="0">
                <?php include 'device_view_chart.php'; ?>
              </div>

              <div class="tab-pane fade" id="tab-alarm-log" role="tabpanel" aria-labelledby="tab-alarm-log-btn" tabindex="0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div class="d-flex flex-wrap gap-2">
                    <select id="deviceAlarmRange" class="form-select form-select-sm" style="width:auto;">
                      <option value="all"><?php echo lang('all') ?></option>
                      <option value="today"><?php echo lang('today') ?></option>
                      <option value="last_7_days"><?php echo lang('last_7_days') ?></option>
                      <option value="last_30_days" selected><?php echo lang('last_30_days') ?></option>
                    </select>
                    <select id="deviceAlarmStatus" class="form-select form-select-sm" style="width:auto;">
                      <option value="all"><?php echo lang('all_status') ?></option>
                      <option value="active"><?php echo lang('status_active') ?></option>
                      <option value="acknowledged"><?php echo lang('acknowledged') ?></option>
                      <option value="resolved"><?php echo lang('resolved') ?></option>
                    </select>
                  </div>
                  <a class="btn btn-sm btn-outline-primary" href="<?= base_url('alarmlogs') ?>?device_filter=<?= (int) $device->id ?>" target="_blank">
                    <?php echo lang('view_all_alarm_logs') ?>
                  </a>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th><?php echo lang('date_time') ?></th>
                        <th><?php echo lang('metrics') ?></th>
                        <th><?php echo lang('severity') ?></th>
                        <th><?php echo lang('message') ?></th>
                        <th><?php echo lang('status') ?></th>
                      </tr>
                    </thead>
                    <tbody id="deviceAlarmTableBody">
                      <tr>
                        <td colspan="5" class="text-center text-muted py-3"><?php echo lang('loading') ?></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                  <small class="text-muted" id="deviceAlarmSummary">-</small>
                  <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="deviceAlarmPrev" disabled><?php echo lang('previous_page') ?></button>
                    <button type="button" class="btn btn-outline-secondary" id="deviceAlarmNext" disabled><?php echo lang('next_page') ?></button>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-metrics" role="tabpanel" aria-labelledby="tab-metrics-btn" tabindex="0">
                <?php if (empty($viewMetrics)): ?>
                  <p class="text-muted mb-0"><?php echo lang('no_data_found') ?></p>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th><?php echo lang('key') ?></th>
                          <th><?php echo lang('label') ?></th>
                          <th><?php echo lang('unit') ?></th>
                          <th><?php echo lang('data_type') ?></th>
                          <th><?php echo lang('triggers') ?></th>
                          <th><?php echo lang('status') ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($viewMetrics as $m):
                          if (!is_array($m) || empty($m['key'])) continue;
                          $enable = $m['enable'] ?? true;
                          $isEnabled = ($enable === true || $enable === 'true' || $enable === 1 || $enable === '1');
                          $triggers = [];
                          if (!empty($m['triggers']) && is_array($m['triggers'])) {
                            $triggers = $m['triggers'];
                          } elseif (!empty($m['trigger']) && is_array($m['trigger'])) {
                            $triggers = [$m['trigger']];
                          }
                          $triggerCount = count($triggers);
                          $activeTriggerCount = 0;
                          foreach ($triggers as $trigger) {
                            if (!is_array($trigger)) continue;
                            $tEnable = $trigger['enable'] ?? true;
                            if ($tEnable === true || $tEnable === 'true' || $tEnable === 1 || $tEnable === '1') {
                              $activeTriggerCount++;
                            }
                          }
                          $triggerPayload = htmlspecialchars(json_encode([
                            'key' => $m['key'],
                            'label' => $m['label'] ?? $m['key'],
                            'triggers' => $triggers,
                          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        ?>
                          <tr>
                            <td><?= htmlspecialchars($m['key']) ?></td>
                            <td><?= htmlspecialchars($m['label'] ?? $m['key']) ?></td>
                            <td><?= htmlspecialchars($m['unit'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($m['datatype'] ?? '-') ?></td>
                            <td>
                              <?php if ($triggerCount === 0): ?>
                                <span class="text-muted">—</span>
                              <?php else: ?>
                                <button
                                  type="button"
                                  class="btn btn-link p-0 text-decoration-none metric-trigger-detail"
                                  data-metric-triggers="<?= $triggerPayload ?>"
                                  title="<?php echo lang('triggers') ?>"
                                >
                                  <span class="badge text-bg-light border"><?= (int) $triggerCount ?></span>
                                  <?php if ($activeTriggerCount > 0): ?>
                                    <span class="badge text-bg-warning"><?= (int) $activeTriggerCount ?> <?php echo lang('status_active') ?></span>
                                  <?php endif; ?>
                                </button>
                              <?php endif; ?>
                            </td>
                            <td>
                              <?php if ($isEnabled): ?>
                                <span class="badge text-bg-success"><?php echo lang('status_active') ?></span>
                              <?php else: ?>
                                <span class="badge text-bg-secondary"><?php echo lang('status_inactive') ?></span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>

                  <div class="modal fade" id="metricTriggersModal" tabindex="-1" aria-labelledby="metricTriggersModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="metricTriggersModalLabel"><?php echo lang('triggers') ?></h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="metricTriggersModalBody">
                          <p class="text-muted mb-0"><?php echo lang('no_data_found') ?></p>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('close') ?></button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <script>
                  (function () {
                    const modalEl = document.getElementById('metricTriggersModal');
                    const titleEl = document.getElementById('metricTriggersModalLabel');
                    const bodyEl = document.getElementById('metricTriggersModalBody');
                    if (!modalEl || !bodyEl) return;

                    const i18n = {
                      trigger: <?= json_encode(lang('trigger') ?: (lang('triggers') ?: 'Trigger')) ?>,
                      status: <?= json_encode(lang('status') ?: 'Status') ?>,
                      active: <?= json_encode(lang('status_active') ?: 'Active') ?>,
                      inactive: <?= json_encode(lang('status_inactive') ?: 'Inactive') ?>,
                      operator: <?= json_encode(lang('trigger_operator') ?: 'Operator') ?>,
                      value: <?= json_encode(lang('trigger_value') ?: (lang('value') ?: 'Value')) ?>,
                      message: <?= json_encode(lang('trigger_message') ?: 'Message') ?>,
                      severity: <?= json_encode(lang('trigger_severity') ?: (lang('severity') ?: 'Severity')) ?>,
                      clear: <?= json_encode(lang('trigger_clear_condition') ?: 'Clear') ?>,
                      none: '—'
                    };

                    function esc(value) {
                      return String(value == null ? '' : value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                    }

                    function isActive(value) {
                      return value === true || value === 'true' || value === 1 || value === '1' || value == null;
                    }

                    function renderTriggers(payload) {
                      const triggers = Array.isArray(payload.triggers) ? payload.triggers : [];
                      const metricTitle = (payload.label || payload.key || i18n.trigger);
                      if (titleEl) titleEl.textContent = i18n.trigger + ' · ' + metricTitle;

                      if (!triggers.length) {
                        bodyEl.innerHTML = '<p class="text-muted mb-0">' + esc(i18n.none) + '</p>';
                        return;
                      }

                      bodyEl.innerHTML = triggers.map(function (trigger, index) {
                        const clear = trigger && trigger.clear_condition && typeof trigger.clear_condition === 'object'
                          ? trigger.clear_condition
                          : {};
                        const active = isActive(trigger && trigger.enable);
                        return (
                          '<div class="border rounded p-3 mb-3">' +
                            '<div class="d-flex justify-content-between align-items-center mb-2">' +
                              '<strong>' + esc(i18n.trigger) + ' ' + (index + 1) + '</strong>' +
                              '<span class="badge ' + (active ? 'text-bg-warning' : 'text-bg-secondary') + '">' +
                                esc(active ? i18n.active : i18n.inactive) +
                              '</span>' +
                            '</div>' +
                            '<div class="row g-2 small">' +
                              '<div class="col-md-3"><div class="text-muted">' + esc(i18n.operator) + '</div><div>' + esc((trigger && trigger.operator) || i18n.none) + '</div></div>' +
                              '<div class="col-md-3"><div class="text-muted">' + esc(i18n.value) + '</div><div>' + esc(trigger && trigger.value != null && trigger.value !== '' ? trigger.value : i18n.none) + '</div></div>' +
                              '<div class="col-md-6"><div class="text-muted">' + esc(i18n.message) + '</div><div>' + esc((trigger && trigger.message) || i18n.none) + '</div></div>' +
                              '<div class="col-md-3"><div class="text-muted">' + esc(i18n.clear) + ' · ' + esc(i18n.operator) + '</div><div>' + esc(clear.operator || i18n.none) + '</div></div>' +
                              '<div class="col-md-3"><div class="text-muted">' + esc(i18n.clear) + ' · ' + esc(i18n.value) + '</div><div>' + esc(clear.value != null && clear.value !== '' ? clear.value : i18n.none) + '</div></div>' +
                              '<div class="col-md-6"><div class="text-muted">' + esc(i18n.clear) + ' · ' + esc(i18n.message) + '</div><div>' + esc(clear.message || i18n.none) + '</div></div>' +
                              '<div class="col-md-3"><div class="text-muted">' + esc(i18n.severity) + '</div><div>' + esc((trigger && trigger.severity) || i18n.none) + '</div></div>' +
                            '</div>' +
                          '</div>'
                        );
                      }).join('');
                    }

                    document.querySelectorAll('.metric-trigger-detail').forEach(function (btn) {
                      btn.addEventListener('click', function () {
                        let payload = {};
                        try {
                          payload = JSON.parse(this.getAttribute('data-metric-triggers') || '{}');
                        } catch (e) {
                          payload = {};
                        }
                        renderTriggers(payload);
                        if (window.bootstrap && window.bootstrap.Modal) {
                          window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        }
                      });
                    });
                  })();
                  </script>
                <?php endif; ?>
              </div>

              <div class="tab-pane fade" id="tab-communication" role="tabpanel" aria-labelledby="tab-communication-btn" tabindex="0">
                <p class="mb-3">
                  <strong><?php echo lang('protocol') ?>:</strong>
                  <?= htmlspecialchars(strtoupper($viewProtocol ?: '-')) ?>
                </p>
                <?php if (empty($commDisplay)): ?>
                  <p class="text-muted mb-0"><?php echo lang('no_data_found') ?></p>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th><?php echo lang('key') ?></th>
                          <th><?php echo lang('value') ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($commDisplay as $ck => $cv):
                          if (is_array($cv) || is_object($cv)) {
                            $displayVal = json_encode($cv, JSON_UNESCAPED_UNICODE);
                          } else {
                            $displayVal = (string) $cv;
                          }
                          if (in_array(strtolower((string) $ck), $secretKeys, true) && $displayVal !== '') {
                            $displayVal = '********';
                          }
                        ?>
                          <tr>
                            <td class="fw-semibold"><?= htmlspecialchars((string) $ck) ?></td>
                            <td><?= htmlspecialchars($displayVal !== '' ? $displayVal : '-') ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<!--end::App Content-->

<script>
(function () {
  const deviceId = <?= json_encode((int) $device->id) ?>;
  const i18n = {
    loading: <?= json_encode(lang('loading')) ?>,
    noData: <?= json_encode(lang('no_data_found')) ?>,
    error: <?= json_encode(lang('error_loading_data')) ?>,
    pageOf: <?= json_encode(lang('page_of')) ?>
  };

  let alarmPage = 1;
  let alarmTotalPages = 1;
  let alarmLoaded = false;

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatDateTime(value) {
    if (!value) return '-';
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleString('en-GB', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  }

  function severityBadge(severity) {
    const s = String(severity || '').toLowerCase();
    if (s === 'critical' || s === 'high') return 'text-bg-danger';
    if (s === 'warning' || s === 'medium') return 'bg-orange';
    if (s === 'info' || s === 'low') return 'text-bg-info';
    return 'text-bg-secondary';
  }

  function statusBadge(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'active') return 'text-bg-danger';
    if (s === 'acknowledged') return 'text-bg-warning';
    if (s === 'resolved') return 'text-bg-success';
    return 'text-bg-secondary';
  }

  function loadDeviceAlarms(page) {
    alarmPage = page || 1;
    const tbody = document.getElementById('deviceAlarmTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">${escapeHtml(i18n.loading)}</td></tr>`;

    const body = new URLSearchParams();
    body.set('page', String(alarmPage));
    body.set('pageSize', '10');
    body.set('range', document.getElementById('deviceAlarmRange')?.value || 'last_30_days');
    body.set('status_filter', document.getElementById('deviceAlarmStatus')?.value || 'all');
    body.set('device_filter', String(deviceId));
    body.set('severity_filter', 'all');
    body.set('search', '');

    fetch(<?= json_encode(base_url('alarmlogs/get_logs')) ?>, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data || !data.success) {
          tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">${escapeHtml(i18n.error)}</td></tr>`;
          return;
        }

        const logs = data.logs || [];
        alarmTotalPages = Math.max(1, parseInt(data.totalPages, 10) || 1);
        alarmPage = parseInt(data.currentPage, 10) || 1;

        if (!logs.length) {
          tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">${escapeHtml(i18n.noData)}</td></tr>`;
        } else {
          tbody.innerHTML = logs.map((log) => {
            const message = log.message || '-';
            const shortMessage = message.length > 80 ? message.substring(0, 80) + '...' : message;
            return `
              <tr>
                <td class="text-nowrap">${escapeHtml(formatDateTime(log.created_at))}</td>
                <td>${escapeHtml(log.metric_key || '-')}</td>
                <td><span class="badge ${severityBadge(log.severity)}">${escapeHtml(log.severity || '-')}</span></td>
                <td>${escapeHtml(shortMessage)}</td>
                <td><span class="badge ${statusBadge(log.status)}">${escapeHtml(log.status || '-')}</span></td>
              </tr>
            `;
          }).join('');
        }

        const summary = document.getElementById('deviceAlarmSummary');
        if (summary) {
          const total = data.totalRecords || 0;
          summary.textContent = (i18n.pageOf || 'Page {page} / {pages} ({total} records)')
            .replace('{page}', String(alarmPage))
            .replace('{pages}', String(alarmTotalPages))
            .replace('{total}', String(total));
        }

        const prev = document.getElementById('deviceAlarmPrev');
        const next = document.getElementById('deviceAlarmNext');
        if (prev) prev.disabled = alarmPage <= 1;
        if (next) next.disabled = alarmPage >= alarmTotalPages;
        alarmLoaded = true;
      })
      .catch(() => {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">${escapeHtml(i18n.error)}</td></tr>`;
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('deviceAlarmRange')?.addEventListener('change', () => loadDeviceAlarms(1));
    document.getElementById('deviceAlarmStatus')?.addEventListener('change', () => loadDeviceAlarms(1));
    document.getElementById('deviceAlarmPrev')?.addEventListener('click', () => {
      if (alarmPage > 1) loadDeviceAlarms(alarmPage - 1);
    });
    document.getElementById('deviceAlarmNext')?.addEventListener('click', () => {
      if (alarmPage < alarmTotalPages) loadDeviceAlarms(alarmPage + 1);
    });

    document.getElementById('tab-alarm-log-btn')?.addEventListener('shown.bs.tab', function () {
      if (!alarmLoaded) loadDeviceAlarms(1);
    });
    // Fallback if tab event is missed
    document.getElementById('tab-alarm-log-btn')?.addEventListener('click', function () {
      if (!alarmLoaded) {
        setTimeout(() => loadDeviceAlarms(1), 50);
      }
    });
  });
})();
</script>
