<?php
$commands = isset($commands) && is_array($commands) ? $commands : [];
$logs = isset($logs) && is_array($logs) ? $logs : [];
$schedules = isset($schedules) && is_array($schedules) ? $schedules : [];
$sequences = isset($sequences) && is_array($sequences) ? $sequences : [];
$rules = isset($rules) && is_array($rules) ? $rules : [];
$groups = isset($groups) && is_array($groups) ? $groups : [];
$ungroupedCount = (int) ($ungrouped_count ?? 0);
$currentGroup = isset($current_group) ? $current_group : null;
$devices = isset($devices) ? $devices : [];
$engineOk = !empty($engine['ok']);
$canCreate = !empty($can_create);
$canUpdate = !empty($can_update);
$canDelete = !empty($can_delete);
$kindLabels = [
  'building' => lang('control_kind_building'),
  'room' => lang('control_kind_room'),
  'system' => lang('control_kind_system'),
  'generic' => lang('control_kind_other'),
];
$currentGroupId = $currentGroup ? (int) ($currentGroup['id'] ?? 0) : null;
$groupQuery = $currentGroup === null ? '' : ('?group=' . (int) $currentGroupId);
$activeTab = strtolower(trim((string) ($this->input->get('tab') ?? 'overview')));
$controlTabs = ['overview', 'flow', 'commands', 'schedules', 'sequences', 'rules', 'logs'];
if (!in_array($activeTab, $controlTabs, true)) {
  $activeTab = 'overview';
}
$commandNames = [];
foreach ($commands as $c) {
  $commandNames[(int) ($c['id'] ?? 0)] = (string) ($c['name'] ?? '');
}
$sequenceNames = [];
foreach ($sequences as $s) {
  $sequenceNames[(int) ($s['id'] ?? 0)] = (string) ($s['name'] ?? '');
}
$deviceNames = [];
foreach ($devices as $device) {
  $deviceNames[(int) $device->id] = (string) $device->name;
}
if (!function_exists('control_seq_summary')) {
  function control_seq_summary($seq, $commandNames) {
    $steps = $seq['steps'] ?? [];
    if (is_string($steps)) {
      $decoded = json_decode($steps, true);
      $steps = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($steps) || !$steps) {
      return lang('control_no_steps');
    }
    $parts = [];
    foreach ($steps as $s) {
      if (!is_array($s)) {
        continue;
      }
      $cid = (int) ($s['command_id'] ?? 0);
      $name = $commandNames[$cid] ?? ('#' . $cid);
      $action = (($s['action'] ?? 'on') === 'off') ? lang('control_off') : lang('control_on');
      $wait = strtolower((string) ($s['wait'] ?? 'none'));
      $bit = $name . ' ' . $action;
      if ($wait === 'on' || $wait === 'off') {
        $bit .= ' · ' . lang('control_wait') . ' ' . ($wait === 'off' ? lang('control_off') : lang('control_on'));
      }
      $parts[] = $bit;
    }
    return $parts ? implode(' → ', $parts) : lang('control_no_steps');
  }
}
if (!function_exists('control_rule_decode')) {
  function control_rule_decode($raw) {
    if (is_array($raw)) {
      return $raw;
    }
    if (is_string($raw) && $raw !== '') {
      $decoded = json_decode($raw, true);
      return is_array($decoded) ? $decoded : [];
    }
    return [];
  }
}
if (!function_exists('control_rule_cond_summary')) {
  function control_rule_cond_summary($rule, $commandNames, $deviceNames) {
    $c = control_rule_decode($rule['conditions'] ?? []);
    $items = isset($c['items']) && is_array($c['items']) ? $c['items'] : [];
    if (!$items) {
      return lang('control_no_conditions');
    }
    $match = strtolower((string) ($c['match'] ?? 'all')) === 'any' ? lang('control_any') : lang('control_all');
    $parts = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $type = strtolower((string) ($item['type'] ?? ''));
      if ($type === 'command_status') {
        $cid = (int) ($item['command_id'] ?? 0);
        $name = $commandNames[$cid] ?? ('#' . $cid);
        $state = strtolower((string) ($item['state'] ?? 'off')) === 'on' ? lang('control_on') : lang('control_off');
        $parts[] = $name . ' ' . lang('control_is') . ' ' . $state;
      } elseif ($type === 'alarm') {
        $did = (int) ($item['device_id'] ?? 0);
        $dname = $deviceNames[$did] ?? sprintf(lang('control_device_n'), $did);
        $key = trim((string) ($item['metric_key'] ?? ''));
        $parts[] = $key !== '' ? (lang('control_alarm_prefix') . ' ' . $dname . ' · ' . $key) : sprintf(lang('control_any_alarm_on'), $dname);
      } elseif ($type === 'metric') {
        $did = (int) ($item['device_id'] ?? 0);
        $dname = $deviceNames[$did] ?? sprintf(lang('control_device_n'), $did);
        $key = (string) ($item['key'] ?? $item['metric_key'] ?? '');
        $op = (string) ($item['op'] ?? '>');
        $val = (string) ($item['value'] ?? '');
        $parts[] = $dname . ' ' . $key . ' ' . $op . ' ' . $val;
      }
    }
    return $match . ': ' . ($parts ? implode('; ', $parts) : lang('control_no_conditions'));
  }
}
if (!function_exists('control_rule_action_summary')) {
  function control_rule_action_summary($rule, $commandNames, $sequenceNames) {
    $a = control_rule_decode($rule['action'] ?? []);
    $kind = strtolower((string) ($a['kind'] ?? $a['type'] ?? ''));
    if ($kind === 'sequence') {
      $sid = (int) ($a['sequence_id'] ?? 0);
      $name = $sequenceNames[$sid] ?? ('#' . $sid);
      return lang('control_sequence') . ' ' . $name;
    }
    $cmds = isset($a['commands']) && is_array($a['commands']) ? $a['commands'] : [];
    $parts = [];
    foreach ($cmds as $c) {
      if (!is_array($c)) {
        continue;
      }
      $cid = (int) ($c['command_id'] ?? 0);
      $name = $commandNames[$cid] ?? ('#' . $cid);
      $action = strtolower((string) ($c['action'] ?? 'on')) === 'off' ? lang('control_off') : lang('control_on');
      $parts[] = $name . ' ' . $action;
    }
    return $parts ? implode(', ', $parts) : lang('control_no_action');
  }
}
if (!function_exists('control_log_value_label')) {
  function control_log_value_label($value) {
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['1', 'on', 'true'], true)) {
      return 'ON';
    }
    if (in_array($v, ['0', 'off', 'false'], true)) {
      return 'OFF';
    }
    return $value === null || $value === '' ? '-' : (string) $value;
  }
}
if (!function_exists('control_flow_model')) {
  function control_flow_model($commands, $sequences, $rules, $schedules) {
    $cmds = [];
    foreach ($commands as $c) {
      $cmds[] = [
        'id' => (int) ($c['id'] ?? 0),
        'name' => (string) ($c['name'] ?? ''),
        'device_name' => (string) ($c['device_name'] ?? ''),
        'enabled' => !empty($c['enabled']),
      ];
    }
    $seqs = [];
    foreach ($sequences as $seq) {
      $steps = $seq['steps'] ?? [];
      if (is_string($steps)) {
        $decoded = json_decode($steps, true);
        $steps = is_array($decoded) ? $decoded : [];
      }
      $norm = [];
      if (is_array($steps)) {
        foreach ($steps as $s) {
          if (!is_array($s)) {
            continue;
          }
          $norm[] = [
            'command_id' => (int) ($s['command_id'] ?? 0),
            'action' => strtolower((string) ($s['action'] ?? 'on')) === 'off' ? 'off' : 'on',
            'wait' => strtolower((string) ($s['wait'] ?? 'none')),
            'timeout_sec' => (int) ($s['timeout_sec'] ?? 0),
            'delay_sec' => (int) ($s['delay_sec'] ?? 0),
          ];
        }
      }
      $seqs[] = [
        'id' => (int) ($seq['id'] ?? 0),
        'name' => (string) ($seq['name'] ?? ''),
        'enabled' => !empty($seq['enabled']),
        'loop_enabled' => !empty($seq['loop']) || !empty($seq['loop_enabled']),
        'loop_count' => (int) ($seq['loop_count'] ?? 0),
        'loop_delay_sec' => (int) ($seq['loop_delay_sec'] ?? 0),
        'steps' => $norm,
      ];
    }
    $ruleList = [];
    foreach ($rules as $rule) {
      $c = control_rule_decode($rule['conditions'] ?? []);
      $a = control_rule_decode($rule['action'] ?? []);
      $items = isset($c['items']) && is_array($c['items']) ? $c['items'] : [];
      $normItems = [];
      foreach ($items as $item) {
        if (!is_array($item)) {
          continue;
        }
        $normItems[] = [
          'type' => strtolower((string) ($item['type'] ?? '')),
          'command_id' => (int) ($item['command_id'] ?? 0),
          'state' => strtolower((string) ($item['state'] ?? 'off')),
          'device_id' => (int) ($item['device_id'] ?? 0),
          'metric_key' => (string) ($item['metric_key'] ?? $item['key'] ?? ''),
          'key' => (string) ($item['key'] ?? $item['metric_key'] ?? ''),
          'op' => (string) ($item['op'] ?? '>'),
          'value' => (string) ($item['value'] ?? ''),
        ];
      }
      $kind = strtolower((string) ($a['kind'] ?? $a['type'] ?? ''));
      $actionCmds = [];
      if ($kind !== 'sequence') {
        $rawCmds = isset($a['commands']) && is_array($a['commands']) ? $a['commands'] : [];
        foreach ($rawCmds as $rc) {
          if (!is_array($rc)) {
            continue;
          }
          $actionCmds[] = [
            'command_id' => (int) ($rc['command_id'] ?? 0),
            'action' => strtolower((string) ($rc['action'] ?? 'on')) === 'off' ? 'off' : 'on',
          ];
        }
      }
      $ruleList[] = [
        'id' => (int) ($rule['id'] ?? 0),
        'name' => (string) ($rule['name'] ?? ''),
        'enabled' => !empty($rule['enabled']),
        'match' => strtolower((string) ($c['match'] ?? 'all')) === 'any' ? 'any' : 'all',
        'conditions' => $normItems,
        'action_kind' => $kind === 'sequence' ? 'sequence' : 'commands',
        'sequence_id' => (int) ($a['sequence_id'] ?? 0),
        'commands' => $actionCmds,
      ];
    }
    $schList = [];
    foreach ($schedules as $sch) {
      $schList[] = [
        'id' => (int) ($sch['id'] ?? 0),
        'name' => (string) ($sch['name'] ?? ''),
        'enabled' => !empty($sch['enabled']),
        'command_id' => (int) ($sch['command_id'] ?? 0),
        'action' => strtolower((string) ($sch['action'] ?? 'on')) === 'off' ? 'off' : 'on',
        'cron' => (string) ($sch['cron'] ?? ''),
      ];
    }
    return [
      'commands' => $cmds,
      'sequences' => $seqs,
      'rules' => $ruleList,
      'schedules' => $schList,
    ];
  }
}
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <?php if ($currentGroup): ?>
          <h4 class="mb-1"><?= htmlspecialchars($currentGroup['name'] ?? '') ?></h4>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= base_url('control') ?>"><?php echo lang('control'); ?></a></li>
              <?php if (!empty($currentGroup['kind']) && ($currentGroup['kind'] ?? '') !== 'generic'): ?>
                <li class="breadcrumb-item"><?= htmlspecialchars($kindLabels[$currentGroup['kind']] ?? $currentGroup['kind']) ?></li>
              <?php endif; ?>
              <li class="breadcrumb-item active" aria-current="page">
                <?= htmlspecialchars(!empty($currentGroup['description']) ? $currentGroup['description'] : ($currentGroup['name'] ?? '')) ?>
              </li>
            </ol>
          </nav>
        <?php else: ?>
          <h4 class="mb-1"><?php echo lang('control'); ?></h4>
          <p class="text-muted small mb-0"><?= lang('control_pick_group') ?></p>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home'); ?></a></li>
              <li class="breadcrumb-item active" aria-current="page"><?php echo lang('control'); ?></li>
            </ol>
          </nav>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <?php if ($engineOk): ?>
          <span class="badge text-bg-success"><?= lang('control_engine_online') ?></span>
        <?php else: ?>
          <span class="badge text-bg-danger"><?= lang('control_engine_offline') ?></span>
        <?php endif; ?>
        <?php if ($currentGroup): ?>
          <?php if ($currentGroupId > 0 && $canUpdate): ?>
            <button class="btn btn-outline-secondary" type="button" onclick='openGroup(<?= json_encode($currentGroup, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><?= lang('control_edit_group') ?></button>
          <?php endif; ?>
        <?php elseif ($canCreate): ?>
          <button class="btn btn-primary" type="button" onclick="openGroup()" <?php echo $engineOk ? '' : 'disabled'; ?>>
            <i class="bi bi-plus-circle"></i> <?= lang('control_new_group') ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <?php if (!$engineOk): ?>
      <div class="alert alert-warning"><?= lang('control_engine_hint') ?></div>
    <?php endif; ?>

    <?php if (!$currentGroup): ?>
      <?php
        $kindIcons = [
          'building' => 'bi-building',
          'room' => 'bi-door-open',
          'system' => 'bi-diagram-3',
          'generic' => 'bi-grid',
        ];
        $kindOrder = ['building', 'room', 'system', 'generic'];
        $groupsByKind = [];
        foreach ($groups as $g) {
          $k = strtolower((string) ($g['kind'] ?? 'generic'));
          if (!in_array($k, $kindOrder, true)) {
            $k = 'generic';
          }
          $groupsByKind[$k][] = $g;
        }
      ?>
      <?php foreach ($kindOrder as $kindKey): ?>
        <?php if (empty($groupsByKind[$kindKey])) continue; ?>
        <div class="ctrl-group-section">
          <div class="ctrl-group-section-h">
            <i class="bi <?= htmlspecialchars($kindIcons[$kindKey]) ?>"></i>
            <?= htmlspecialchars($kindLabels[$kindKey] ?? $kindKey) ?>
            <span class="ctrl-group-section-n"><?= count($groupsByKind[$kindKey]) ?></span>
          </div>
          <div class="row g-3">
            <?php foreach ($groupsByKind[$kindKey] as $g): ?>
              <?php
                $gId = (int) ($g['id'] ?? 0);
                $gName = (string) ($g['name'] ?? '');
                $gDesc = trim((string) ($g['description'] ?? ''));
                $gCount = (int) ($g['command_count'] ?? 0);
                if ($gDesc !== '' && strcasecmp($gDesc, $gName) === 0) {
                  $gDesc = '';
                }
              ?>
              <div class="col-sm-6 col-xl-4 col-xxl-3">
                <div class="card ctrl-group-card h-100 is-<?= htmlspecialchars($kindKey) ?><?= $gCount === 0 ? ' is-empty' : '' ?>">
                  <div class="card-body d-flex flex-column">
                    <div class="ctrl-group-top">
                      <span class="ctrl-group-icon" aria-hidden="true"><i class="bi <?= htmlspecialchars($kindIcons[$kindKey]) ?>"></i></span>
                      <span class="ctrl-group-kind"><?= htmlspecialchars($kindLabels[$kindKey] ?? $kindKey) ?></span>
                      <span class="ctrl-group-count<?= $gCount === 0 ? ' is-zero' : '' ?>"><?= $gCount > 0 ? sprintf(lang('control_command_count'), $gCount) : lang('control_no_commands_yet') ?></span>
                    </div>
                    <h5 class="ctrl-group-name"><?= htmlspecialchars($gName) ?></h5>
                    <?php if ($gDesc !== ''): ?>
                      <p class="ctrl-group-desc"><?= htmlspecialchars($gDesc) ?></p>
                    <?php else: ?>
                      <p class="ctrl-group-desc is-blank">&nbsp;</p>
                    <?php endif; ?>
                    <div class="ctrl-group-actions mt-auto">
                      <a class="btn btn-primary btn-sm" href="<?= base_url('control?group=' . $gId) ?>"><?= lang('control_open') ?></a>
                      <?php if ($canUpdate): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick='openGroup(<?= json_encode($g, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><?= lang('control_edit') ?></button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ($ungroupedCount > 0): ?>
        <div class="ctrl-group-section">
          <div class="ctrl-group-section-h">
            <i class="bi bi-collection"></i>
            <?= lang('control_ungrouped') ?>
          </div>
          <div class="row g-3">
            <div class="col-sm-6 col-xl-4 col-xxl-3">
              <div class="card ctrl-group-card h-100 is-ungrouped">
                <div class="card-body d-flex flex-column">
                  <div class="ctrl-group-top">
                    <span class="ctrl-group-icon" aria-hidden="true"><i class="bi bi-collection"></i></span>
                    <span class="ctrl-group-kind"><?= lang('control_ungrouped') ?></span>
                    <span class="ctrl-group-count"><?= sprintf(lang('control_command_count'), $ungroupedCount) ?></span>
                  </div>
                  <h5 class="ctrl-group-name"><?= lang('control_ungrouped') ?></h5>
                  <p class="ctrl-group-desc"><?= lang('control_ungrouped_desc') ?></p>
                  <div class="ctrl-group-actions mt-auto">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('control?group=0') ?>"><?= lang('control_open') ?></a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <?php if (count($groups) === 0 && $ungroupedCount === 0): ?>
        <div class="card ctrl-group-empty">
          <div class="card-body text-center text-muted py-5">
            <div class="mb-2"><i class="bi bi-diagram-3 fs-1"></i></div>
            <?= lang('control_no_groups') ?>
          </div>
        </div>
      <?php endif; ?>
    <?php else: ?>

    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item"><button class="nav-link<?= $activeTab === 'overview' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button"><?= lang('control_tab_overview') ?></button></li>
      <li class="nav-item"><button class="nav-link<?= $activeTab === 'flow' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-flow" type="button"><?= lang('control_tab_flow') ?></button></li>
      <li class="nav-item"><button class="nav-link<?= $activeTab === 'commands' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-commands" type="button"><?= lang('control_tab_commands') ?></button></li>
      <li class="nav-item"><button class="nav-link<?= $activeTab === 'schedules' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-schedules" type="button"><?= lang('control_tab_schedules') ?></button></li>
      <li class="nav-item"><button class="nav-link<?= $activeTab === 'sequences' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-sequences" type="button"><?= lang('control_tab_sequences') ?></button></li>
      <li class="nav-item"><button class="nav-link<?= $activeTab === 'rules' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-rules" type="button"><?= lang('control_tab_rules') ?></button></li>
      <li class="nav-item"><button class="nav-link<?= $activeTab === 'logs' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button"><?= lang('control_tab_logs') ?></button></li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade<?= $activeTab === 'overview' ? ' show active' : '' ?>" id="tab-overview">
        <div class="row g-3 mb-3" id="ov-summary">
          <div class="col-6 col-lg-3">
            <div class="card ov-kpi is-on"><div class="card-body py-3">
              <div class="small text-muted"><?= lang('control_running') ?></div>
              <div class="fs-3 fw-semibold" id="ov-count-on">—</div>
            </div></div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="card ov-kpi is-off"><div class="card-body py-3">
              <div class="small text-muted"><?= lang('control_stopped') ?></div>
              <div class="fs-3 fw-semibold" id="ov-count-off">—</div>
            </div></div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="card ov-kpi is-mismatch"><div class="card-body py-3">
              <div class="small text-muted"><?= lang('control_checking') ?></div>
              <div class="fs-3 fw-semibold" id="ov-count-mismatch">—</div>
            </div></div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="card ov-kpi is-disabled"><div class="card-body py-3">
              <div class="small text-muted"><?= lang('control_disabled') ?></div>
              <div class="fs-3 fw-semibold" id="ov-count-disabled">—</div>
            </div></div>
          </div>
        </div>
        <div class="d-flex justify-content-end mb-2">
          <span class="small text-muted" id="ov-updated"><?= lang('control_waiting') ?></span>
        </div>
        <div id="ov-sequences" class="d-flex flex-column gap-3 mb-3" style="display:none;"></div>
        <div class="row g-3" id="ov-grid">
          <?php
            $overviewCommands = $commands;
            usort($overviewCommands, function ($a, $b) {
              return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
          ?>
          <?php foreach ($overviewCommands as $row): ?>
            <?php
              $cmdName = (string) ($row['name'] ?? '');
              $cmdDevice = (string) ($row['device_name'] ?? ('#' . ($row['device_id'] ?? '')));
              $cmdId = (int) ($row['id'] ?? 0);
              $cmdEnabled = !empty($row['enabled']);
            ?>
            <div class="col-md-6 col-xl-4">
              <div class="card ov-card h-100 is-unknown" data-ov-id="<?= $cmdId ?>">
                <div class="card-body d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                      <h5 class="mb-0 text-truncate"><?= htmlspecialchars($cmdName) ?></h5>
                      <div class="small text-muted text-truncate"><?= htmlspecialchars($cmdDevice) ?></div>
                    </div>
                    <span class="ov-badge">—</span>
                  </div>
                  <div class="ov-status flex-grow-1 my-3">
                    <span class="ov-lamp"></span>
                    <div class="min-w-0">
                      <div class="ov-state-label">—</div>
                      <div class="small ov-command"><?= lang('control_command_dash') ?></div>
                      <div class="small text-muted ov-meta"></div>
                    </div>
                  </div>
                  <div class="d-flex gap-2 mt-auto">
                    <?php if ($canUpdate): ?>
                    <button type="button" class="btn btn-outline-success flex-fill ov-btn-on" <?= $cmdEnabled ? '' : 'disabled' ?>
                      onclick='overviewExecute(<?= $cmdId ?>, "1", <?= json_encode($cmdName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><?= lang('control_on') ?></button>
                    <button type="button" class="btn btn-outline-secondary flex-fill ov-btn-off" <?= $cmdEnabled ? '' : 'disabled' ?>
                      onclick='overviewExecute(<?= $cmdId ?>, "0", <?= json_encode($cmdName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><?= lang('control_off') ?></button>
                    <button type="button" class="btn btn-outline-primary flex-fill ov-btn-auto" <?= $cmdEnabled ? '' : 'disabled' ?>
                      onclick='overviewAuto(<?= $cmdId ?>, <?= json_encode($cmdName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><?= lang('control_auto') ?></button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (count($commands) === 0): ?>
            <div class="col-12">
              <div class="card ov-empty">
                <div class="card-body text-center text-muted py-5">
                  <?= lang('control_no_commands_group') ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div id="ov-rules" class="d-flex flex-column gap-3 mt-3" style="display:none;"></div>
      </div>

      <div class="tab-pane fade<?= $activeTab === 'flow' ? ' show active' : '' ?>" id="tab-flow">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="mb-1"><?= lang('control_priority') ?></h5>
            <p class="text-muted small mb-3"><?= lang('control_priority_hint') ?></p>
            <div class="fc-priority" aria-label="<?= htmlspecialchars(lang('control_priority')) ?>">
              <div class="fc-pri is-1">
                <div class="fc-rank"><?= lang('control_pri_1') ?></div>
                <div class="fc-title"><?= lang('control_pri_manual') ?></div>
                <p class="fc-desc"><?= lang('control_pri_manual_desc') ?></p>
              </div>
              <div class="fc-pri-arrow" aria-hidden="true">→</div>
              <div class="fc-pri is-2">
                <div class="fc-rank"><?= lang('control_pri_2') ?></div>
                <div class="fc-title"><?= lang('control_pri_rule') ?></div>
                <p class="fc-desc"><?= lang('control_pri_rule_desc') ?></p>
              </div>
              <div class="fc-pri-arrow" aria-hidden="true">→</div>
              <div class="fc-pri is-3">
                <div class="fc-rank"><?= lang('control_pri_3') ?></div>
                <div class="fc-title"><?= lang('control_pri_seq') ?></div>
                <p class="fc-desc"><?= lang('control_pri_seq_desc') ?></p>
              </div>
              <div class="fc-pri-arrow" aria-hidden="true">→</div>
              <div class="fc-pri is-4">
                <div class="fc-rank"><?= lang('control_pri_4') ?></div>
                <div class="fc-title"><?= lang('control_pri_sch') ?></div>
                <p class="fc-desc"><?= lang('control_pri_sch_desc') ?></p>
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
              <div>
                <h5 class="mb-1"><?= lang('control_this_group') ?></h5>
                <p class="text-muted small mb-0"><?= lang('control_this_group_hint') ?></p>
              </div>
              <span class="small text-muted" id="flow-updated"></span>
            </div>
            <div class="fc-legend small text-muted mb-3">
              <span class="fc-leg is-on"><?= lang('control_on') ?></span>
              <span class="fc-leg is-off"><?= lang('control_off') ?></span>
              <span class="fc-leg is-mismatch"><?= lang('control_checking') ?></span>
              <span class="fc-leg is-manual"><?= lang('control_legend_manual') ?></span>
              <span class="fc-leg is-latched"><?= lang('control_legend_latched') ?></span>
              <span class="fc-leg is-running"><?= lang('control_legend_seq_running') ?></span>
            </div>
            <div id="flow-map"></div>
          </div>
        </div>
      </div>

      <div class="tab-pane fade<?= $activeTab === 'commands' ? ' show active' : '' ?>" id="tab-commands">
        <div class="card">
          <div class="card-body table-responsive">
            <div class="d-flex justify-content-end mb-3">
              <?php if ($canCreate): ?>
              <button class="btn btn-primary" type="button" onclick="openCommand()" <?php echo $engineOk ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> <?= lang('control_new_command') ?>
              </button>
              <?php endif; ?>
            </div>
              <table class="table table-striped table-bordered align-middle" id="ctrl-commands-table">
                <thead class="table-light">
                  <tr>
                    <th><?= lang('control_name') ?></th>
                    <th><?= lang('control_device') ?></th>
                    <th><?= lang('control_feedback') ?></th>
                    <th><?= lang('control_status') ?></th>
                    <th class="text-end"><?= lang('control_actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($commands as $row): ?>
                    <?php
                      $cmdName = (string) ($row['name'] ?? '');
                      $cmdDevice = (string) ($row['device_name'] ?? ('#' . ($row['device_id'] ?? '')));
                      $confirmOn = sprintf(lang('control_confirm_on'), $cmdName, $cmdDevice);
                      $confirmOff = sprintf(lang('control_confirm_off'), $cmdName, $cmdDevice);
                    ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($cmdName) ?></strong>
                        <div class="small text-muted"><?= htmlspecialchars($row['description'] ?? '') ?></div>
                      </td>
                      <td><?= htmlspecialchars($cmdDevice) ?></td>
                      <td><code><?= htmlspecialchars($row['feedback_metric_key'] ?? '-') ?></code></td>
                      <td>
                        <?php if (!empty($row['enabled'])): ?>
                          <span class="badge text-bg-success"><?= lang('control_enabled') ?></span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary"><?= lang('control_disabled_badge') ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if ($canUpdate): ?>
                        <form class="d-inline" method="post" action="<?= base_url('control/execute') ?>" onsubmit="return confirm(<?= json_encode($confirmOn, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)">
                          <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                          <input type="hidden" name="name" value="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES) ?>">
                          <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
                          <input type="hidden" name="value" value="1">
                          <button class="btn btn-outline-success btn-sm" type="submit" <?= empty($row['enabled']) ? 'disabled' : '' ?>><?= lang('control_on') ?></button>
                        </form>
                        <form class="d-inline" method="post" action="<?= base_url('control/execute') ?>" onsubmit="return confirm(<?= json_encode($confirmOff, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)">
                          <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                          <input type="hidden" name="name" value="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES) ?>">
                          <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
                          <input type="hidden" name="value" value="0">
                          <button class="btn btn-outline-secondary btn-sm" type="submit" <?= empty($row['enabled']) ? 'disabled' : '' ?>><?= lang('control_off') ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($canUpdate): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick='openCommand(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><?= lang('control_edit') ?></button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <form class="d-inline" method="post" action="<?= base_url('control/delete') ?>" onsubmit="return confirm(<?= json_encode(lang('control_confirm_delete_command'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)">
                          <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                          <input type="hidden" name="name" value="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES) ?>">
                          <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
                          <button class="btn btn-outline-danger btn-sm" type="submit"><?= lang('control_delete') ?></button>
                        </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
          </div>
        </div>
      </div>

      <div class="tab-pane fade<?= $activeTab === 'schedules' ? ' show active' : '' ?>" id="tab-schedules">
        <div class="card">
          <div class="card-body table-responsive">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
              <p class="text-muted small mb-0"><?= lang('control_schedule_hint') ?></p>
              <?php if ($canCreate): ?>
              <button class="btn btn-primary flex-shrink-0" type="button" onclick="openSchedule()" <?php echo $engineOk ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> <?= lang('control_new_schedule') ?>
              </button>
              <?php endif; ?>
            </div>
              <table class="table table-striped table-bordered align-middle" id="ctrl-schedules-table">
                <thead class="table-light">
                  <tr>
                    <th><?= lang('control_name') ?></th>
                    <th><?= lang('control_command') ?></th>
                    <th><?= lang('control_when') ?></th>
                    <th><?= lang('control_action') ?></th>
                    <th><?= lang('control_last_run') ?></th>
                    <th><?= lang('control_status') ?></th>
                    <th class="text-end"><?= lang('control_actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($schedules as $sch): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($sch['name'] ?? '') ?></strong></td>
                      <td>
                        <?= htmlspecialchars($sch['command_name'] ?? ('#' . ($sch['command_id'] ?? ''))) ?>
                        <div class="small text-muted"><?= htmlspecialchars($sch['device_name'] ?? '') ?></div>
                      </td>
                      <td><code><?= htmlspecialchars($sch['cron'] ?? '') ?></code></td>
                      <td><span class="badge text-bg-light border"><?= htmlspecialchars((($sch['action'] ?? '') === 'off') ? lang('control_off') : lang('control_on')) ?></span></td>
                      <td class="small"><?= htmlspecialchars($sch['last_run_at'] ?? '—') ?></td>
                      <td>
                        <?php if (!empty($sch['enabled'])): ?>
                          <span class="badge text-bg-success"><?= lang('control_enabled') ?></span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary"><?= lang('control_disabled_badge') ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php $schOn = !empty($sch['enabled']); ?>
                        <?php if ($canUpdate): ?>
                        <form class="d-inline" method="post" action="<?= base_url('control/toggle_schedule') ?>">
                          <input type="hidden" name="id" value="<?= (int) ($sch['id'] ?? 0) ?>">
                          <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
                          <input type="hidden" name="command_id" value="<?= (int) ($sch['command_id'] ?? 0) ?>">
                          <input type="hidden" name="name" value="<?= htmlspecialchars((string) ($sch['name'] ?? ''), ENT_QUOTES) ?>">
                          <input type="hidden" name="cron" value="<?= htmlspecialchars((string) ($sch['cron'] ?? ''), ENT_QUOTES) ?>">
                          <input type="hidden" name="action" value="<?= htmlspecialchars((string) ($sch['action'] ?? 'on'), ENT_QUOTES) ?>">
                          <input type="hidden" name="enabled" value="<?= $schOn ? '0' : '1' ?>">
                          <button class="btn btn-sm <?= $schOn ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                            <?= $schOn ? lang('control_deactivate') : lang('control_activate') ?>
                          </button>
                        </form>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick='openSchedule(<?= json_encode($sch, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><?= lang('control_edit') ?></button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <form class="d-inline" method="post" action="<?= base_url('control/delete_schedule') ?>" onsubmit="return confirm(<?= json_encode(lang('control_confirm_delete_schedule'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)">
                          <input type="hidden" name="id" value="<?= (int) ($sch['id'] ?? 0) ?>">
                          <input type="hidden" name="name" value="<?= htmlspecialchars((string) ($sch['name'] ?? ''), ENT_QUOTES) ?>">
                          <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
                          <button class="btn btn-outline-danger btn-sm" type="submit"><?= lang('control_delete') ?></button>
                        </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
          </div>
        </div>
      </div>

      <div class="tab-pane fade<?= $activeTab === 'sequences' ? ' show active' : '' ?>" id="tab-sequences">
        <div class="card">
          <div class="card-body table-responsive">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
              <p class="text-muted small mb-0"><?= lang('control_sequence_hint') ?></p>
              <?php if ($canCreate): ?>
              <button class="btn btn-primary flex-shrink-0" type="button" onclick="openSequence()" <?php echo $engineOk ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> <?= lang('control_new_sequence') ?>
              </button>
              <?php endif; ?>
            </div>
              <table class="table table-striped table-bordered align-middle" id="ctrl-sequences-table">
                <thead class="table-light">
                  <tr>
                    <th><?= lang('control_name') ?></th>
                    <th><?= lang('control_steps') ?></th>
                    <th><?= lang('control_last_run') ?></th>
                    <th><?= lang('control_result') ?></th>
                    <th><?= lang('control_status') ?></th>
                    <th class="text-end"><?= lang('control_actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sequences as $seq): ?>
                    <?php
                      $seqName = (string) ($seq['name'] ?? '');
                      $seqResult = strtolower((string) ($seq['last_result'] ?? ''));
                      $seqRunning = !empty($seq['running']) || $seqResult === 'running';
                      $seqLoop = !empty($seq['loop']) || !empty($seq['loop_enabled']);
                      $seqPaused = !empty($seq['paused']);
                    ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($seqName) ?></strong>
                        <?php if ($seqLoop): ?>
                          <span class="badge text-bg-light border ms-1"><?= lang('control_loop') ?><?= ((int) ($seq['loop_count'] ?? 0)) > 0 ? ' ×' . (int) $seq['loop_count'] : '' ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="small"><?= htmlspecialchars(control_seq_summary($seq, $commandNames)) ?></td>
                      <td class="small"><?= htmlspecialchars($seq['last_run_at'] ?? '—') ?></td>
                      <td>
                        <?php if ($seqPaused): ?>
                          <span class="badge text-bg-warning"><?= lang('control_paused') ?></span>
                        <?php elseif ($seqRunning): ?>
                          <span class="badge text-bg-info"><?= lang('control_seq_running') ?></span>
                        <?php elseif ($seqResult === 'ok'): ?>
                          <span class="badge text-bg-success"><?= lang('control_seq_ok') ?></span>
                        <?php elseif ($seqResult === 'stopped'): ?>
                          <span class="badge text-bg-secondary"><?= lang('control_seq_stopped') ?></span>
                        <?php elseif ($seqResult === 'failed'): ?>
                          <span class="badge text-bg-danger"><?= lang('control_seq_failed') ?></span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($seq['enabled'])): ?>
                          <span class="badge text-bg-success"><?= lang('control_enabled') ?></span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary"><?= lang('control_disabled_badge') ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if ($canUpdate): ?>
                        <?php if ($seqRunning): ?>
                          <?php if ($seqPaused): ?>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick='resumeSequence(<?= (int) ($seq['id'] ?? 0) ?>, <?= json_encode($seqName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><?= lang('control_resume') ?></button>
                          <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick='pauseSequence(<?= (int) ($seq['id'] ?? 0) ?>, <?= json_encode($seqName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><?= lang('control_pause') ?></button>
                          <?php endif; ?>
                          <button type="button" class="btn btn-outline-warning btn-sm" onclick='stopSequence(<?= (int) ($seq['id'] ?? 0) ?>, <?= json_encode($seqName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><?= lang('control_stop') ?></button>
                        <?php else: ?>
                          <button type="button" class="btn btn-outline-success btn-sm" <?= empty($seq['enabled']) ? 'disabled' : '' ?> onclick='runSequence(<?= (int) ($seq['id'] ?? 0) ?>, <?= json_encode($seqName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= $seqLoop ? 'true' : 'false' ?>)'><?= lang('control_run') ?></button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick='openSequence(<?= json_encode($seq, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><?= lang('control_edit') ?></button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <form class="d-inline" method="post" action="<?= base_url('control/delete_sequence') ?>" onsubmit="return confirm(<?= json_encode(lang('control_confirm_delete_sequence'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)">
                          <input type="hidden" name="id" value="<?= (int) ($seq['id'] ?? 0) ?>">
                          <input type="hidden" name="name" value="<?= htmlspecialchars($seqName, ENT_QUOTES) ?>">
                          <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
                          <button class="btn btn-outline-danger btn-sm" type="submit" <?= $seqRunning ? 'disabled' : '' ?>><?= lang('control_delete') ?></button>
                        </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
          </div>
        </div>
      </div>

      <div class="tab-pane fade<?= $activeTab === 'rules' ? ' show active' : '' ?>" id="tab-rules">
        <div class="card">
          <div class="card-body table-responsive">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
              <p class="text-muted small mb-0"><?= lang('control_rule_hint') ?></p>
              <?php if ($canCreate): ?>
              <button class="btn btn-primary flex-shrink-0" type="button" onclick="openRule()" <?php echo $engineOk ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> <?= lang('control_new_rule') ?>
              </button>
              <?php endif; ?>
            </div>
              <table class="table table-striped table-bordered align-middle" id="ctrl-rules-table">
                <thead class="table-light">
                  <tr>
                    <th><?= lang('control_name') ?></th>
                    <th><?= lang('control_when') ?></th>
                    <th><?= lang('control_then') ?></th>
                    <th><?= lang('control_last_triggered') ?></th>
                    <th><?= lang('control_result') ?></th>
                    <th><?= lang('control_status') ?></th>
                    <th class="text-end"><?= lang('control_actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rules as $rule): ?>
                    <?php
                      $ruleName = (string) ($rule['name'] ?? '');
                      $ruleResult = strtolower((string) ($rule['last_result'] ?? ''));
                    ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($ruleName) ?></strong></td>
                      <td class="small"><?= htmlspecialchars(control_rule_cond_summary($rule, $commandNames, $deviceNames)) ?></td>
                      <td class="small"><?= htmlspecialchars(control_rule_action_summary($rule, $commandNames, $sequenceNames)) ?></td>
                      <td class="small"><?= htmlspecialchars($rule['last_triggered_at'] ?? $rule['last_fired_at'] ?? '—') ?></td>
                      <td>
                        <?php if ($ruleResult === 'triggered' || $ruleResult === 'fired'): ?>
                          <span class="badge text-bg-success"><?= lang('control_triggered') ?></span>
                        <?php elseif ($ruleResult === 'failed'): ?>
                          <span class="badge text-bg-danger"><?= lang('control_failed') ?></span>
                          <?php if (!empty($rule['last_error'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars($rule['last_error']) ?></div>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($rule['enabled'])): ?>
                          <span class="badge text-bg-success"><?= lang('control_enabled') ?></span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary"><?= lang('control_disabled_badge') ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if ($canUpdate): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick='openRule(<?= json_encode($rule, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><?= lang('control_edit') ?></button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <form class="d-inline" method="post" action="<?= base_url('control/delete_rule') ?>" onsubmit="return confirm(<?= json_encode(lang('control_confirm_delete_rule'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)">
                          <input type="hidden" name="id" value="<?= (int) ($rule['id'] ?? 0) ?>">
                          <input type="hidden" name="name" value="<?= htmlspecialchars($ruleName, ENT_QUOTES) ?>">
                          <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
                          <button class="btn btn-outline-danger btn-sm" type="submit"><?= lang('control_delete') ?></button>
                        </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
          </div>
        </div>
      </div>

      <div class="tab-pane fade<?= $activeTab === 'logs' ? ' show active' : '' ?>" id="tab-logs">
        <div class="card">
          <div class="card-body table-responsive">
              <table class="table table-striped table-bordered align-middle" id="ctrl-logs-table">
                <thead class="table-light">
                  <tr>
                    <th><?= lang('control_time') ?></th>
                    <th><?= lang('control_command') ?></th>
                    <th><?= lang('control_device') ?></th>
                    <th><?= lang('control_source') ?></th>
                    <th><?= lang('control_value') ?></th>
                    <th><?= lang('control_result') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($logs as $log): ?>
                    <tr>
                      <td class="small"><?= htmlspecialchars($log['created_at'] ?? '') ?></td>
                      <td><?= htmlspecialchars($log['command_name'] ?? ('#' . ($log['command_id'] ?? ''))) ?></td>
                      <td><?= htmlspecialchars($log['device_name'] ?? '-') ?></td>
                      <td>
                        <span class="badge text-bg-light border"><?= htmlspecialchars($log['source'] ?? '') ?></span>
                        <?php if (!empty($log['sequence_name'])): ?>
                          <div class="small text-muted"><?= htmlspecialchars($log['sequence_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($log['rule_name'])): ?>
                          <div class="small text-muted"><?= htmlspecialchars($log['rule_name']) ?></div>
                        <?php endif; ?>
                      </td>
                      <?php
                        $valueLabel = control_log_value_label($log['requested_value'] ?? '');
                        $valueKey = strtolower($valueLabel);
                      ?>
                      <td>
                        <?php if ($valueKey === 'on'): ?>
                          <span class="badge text-bg-success">ON</span>
                        <?php elseif ($valueKey === 'off'): ?>
                          <span class="badge text-bg-secondary">OFF</span>
                        <?php elseif ($valueLabel === '-'): ?>
                          -
                        <?php else: ?>
                          <code><?= htmlspecialchars($valueLabel) ?></code>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="badge text-bg-info"><?= htmlspecialchars($log['result'] ?? '') ?></span>
                        <?php if (!empty($log['error'])): ?>
                          <div class="small text-muted"><?= htmlspecialchars($log['error']) ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="command-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="<?= base_url('control/save') ?>" id="command-form">
      <div class="modal-header">
        <h5 class="modal-title"><?= lang('control_command_modal') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="cmd-id" value="">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_name') ?></label>
            <input class="form-control" name="name" id="cmd-name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_group') ?></label>
            <select class="form-select" name="group_id" id="cmd-group">
              <option value=""><?= lang('control_ungrouped') ?></option>
              <?php foreach ($groups as $g): ?>
                <option value="<?= (int) ($g['id'] ?? 0) ?>" <?= ($currentGroupId > 0 && (int) $currentGroupId === (int) ($g['id'] ?? 0)) ? 'selected' : '' ?>>
                  <?= htmlspecialchars(($g['name'] ?? '') . ' (' . ($kindLabels[$g['kind'] ?? ''] ?? ($g['kind'] ?? '')) . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_device') ?></label>
            <select class="form-select" name="device_id" id="cmd-device" required>
              <option value=""><?= lang('control_select_device') ?></option>
              <?php foreach ($devices as $device): ?>
                <option value="<?= (int) $device->id ?>"><?= htmlspecialchars($device->name) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><?= lang('control_device_hint') ?></div>
          </div>
          <div class="col-12">
            <label class="form-label"><?= lang('control_description') ?></label>
            <input class="form-control" name="description" id="cmd-description">
          </div>
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_write_via') ?></label>
            <select class="form-select" id="cmd-write-via">
              <option value=""><?= lang('control_write_via_same') ?></option>
              <?php foreach ($devices as $device): ?>
                <option value="<?= (int) $device->id ?>"><?= htmlspecialchars($device->name) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><?= lang('control_write_via_hint') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_write_actuator') ?></label>
            <select class="form-select" id="cmd-write-target">
              <option value=""><?= lang('control_select_device_first') ?></option>
            </select>
            <div class="form-text" id="cmd-write-hint"><?= lang('control_write_hint') ?></div>
          </div>
          <input type="hidden" name="protocol" id="cmd-protocol" value="snmp_set">
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_feedback_metric') ?></label>
            <select class="form-select" name="feedback_metric_key" id="cmd-feedback">
              <option value=""><?= lang('control_select_device_first') ?></option>
            </select>
            <div class="form-text" id="cmd-feedback-hint"><?= lang('control_feedback_metric_hint') ?></div>
          </div>
          <div class="col-12" id="cmd-endpoint-note" style="display:none;">
            <div class="alert alert-info py-2 mb-0 small"></div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3 bg-body-secondary cmd-config-panel">
              <div class="fw-semibold mb-2"><?= lang('control_set_value') ?></div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label"><?= lang('control_on') ?></label>
                  <input class="form-control" name="on_value" id="cmd-on-value" placeholder="e.g. 3">
                </div>
                <div class="col-md-6">
                  <label class="form-label"><?= lang('control_off') ?></label>
                  <input class="form-control" name="off_value" id="cmd-off-value" placeholder="e.g. 0">
                </div>
              </div>
              <div class="form-text mb-0"><?= lang('control_write_values_hint') ?></div>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3 bg-body-secondary cmd-config-panel">
              <div class="fw-semibold mb-2"><?= lang('control_feedback_map') ?></div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label"><?= lang('control_on_when') ?></label>
                  <input class="form-control" name="feedback_on" id="cmd-fb-on" placeholder="e.g. 1, 3">
                </div>
                <div class="col-md-6">
                  <label class="form-label"><?= lang('control_on_label') ?></label>
                  <input class="form-control" name="feedback_on_label" id="cmd-fb-on-label" placeholder="<?= htmlspecialchars(lang('control_on')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label"><?= lang('control_off_when') ?></label>
                  <input class="form-control" name="feedback_off" id="cmd-fb-off" placeholder="e.g. 0, 1">
                </div>
                <div class="col-md-6">
                  <label class="form-label"><?= lang('control_off_label') ?></label>
                  <input class="form-control" name="feedback_off_label" id="cmd-fb-off-label" placeholder="<?= htmlspecialchars(lang('control_off')) ?>">
                </div>
              </div>
              <div class="form-text mb-0" id="cmd-fb-latest"><?= lang('control_feedback_hint') ?></div>
            </div>
          </div>
          <div class="col-12">
            <a class="small text-muted" data-bs-toggle="collapse" href="#cmd-write-advanced"><?= lang('control_advanced_json') ?></a>
            <div class="collapse mt-2" id="cmd-write-advanced">
              <label class="form-label"><?= lang('control_write_config') ?></label>
              <textarea class="form-control font-monospace" name="write_config_json" id="cmd-write" rows="5" placeholder='{"oid":"1.3.6.1.4.1.12020.1.3.1.1.1"}'></textarea>
            </div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="enabled" id="cmd-enabled" value="1" checked>
              <label class="form-check-label" for="cmd-enabled"><?= lang('control_enabled') ?></label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('control_cancel') ?></button>
        <button class="btn btn-primary" type="submit"><?= lang('control_save') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="schedule-modal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= base_url('control/save_schedule') ?>">
      <div class="modal-header">
        <h5 class="modal-title"><?= lang('control_schedule_modal') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="sch-id" value="">
        <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
        <div class="mb-3">
          <label class="form-label"><?= lang('control_name') ?></label>
          <input class="form-control" name="name" id="sch-name" placeholder="e.g. CRAC 01 weekday start">
        </div>
        <div class="mb-3">
          <label class="form-label"><?= lang('control_command') ?></label>
          <select class="form-select" name="command_id" id="sch-command" required>
            <option value=""><?= lang('control_select_command') ?></option>
            <?php foreach ($commands as $cmd): ?>
              <option value="<?= (int) ($cmd['id'] ?? 0) ?>"><?= htmlspecialchars(($cmd['name'] ?? '') . ' — ' . ($cmd['device_name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label"><?= lang('control_time_bkk') ?></label>
            <input class="form-control" type="time" name="time" id="sch-time" value="08:00" required>
          </div>
          <div class="col-6">
            <label class="form-label"><?= lang('control_action') ?></label>
            <select class="form-select" name="action" id="sch-action">
              <option value="on"><?= lang('control_on') ?></option>
              <option value="off"><?= lang('control_off') ?></option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= lang('control_days') ?></label>
          <div class="d-flex flex-wrap gap-3">
            <?php
              $dowLabels = explode(',', lang('control_days_short'));
              foreach ([0, 1, 2, 3, 4, 5, 6] as $n):
                $label = $dowLabels[$n] ?? $n;
            ?>
              <label class="form-check-label">
                <input class="form-check-input sch-day" type="checkbox" name="days[]" value="<?= $n ?>" checked>
                <?= htmlspecialchars($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="enabled" id="sch-enabled" value="1" checked>
          <label class="form-check-label" for="sch-enabled"><?= lang('control_enabled') ?></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('control_cancel') ?></button>
        <button class="btn btn-primary" type="submit"><?= lang('control_save') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="sequence-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="<?= base_url('control/save_sequence') ?>" id="sequence-form">
      <div class="modal-header">
        <h5 class="modal-title"><?= lang('control_sequence_modal') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="seq-id" value="">
        <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
        <input type="hidden" name="steps_json" id="seq-steps-json" value="[]">
        <div class="mb-3">
          <label class="form-label"><?= lang('control_name') ?></label>
          <input class="form-control" name="name" id="seq-name" required placeholder="e.g. Start chilled water">
        </div>
        <div class="mb-2 d-flex justify-content-between align-items-center">
          <label class="form-label mb-0"><?= lang('control_steps') ?></label>
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="addSeqStep()"><?= lang('control_add_step') ?></button>
        </div>
        <div id="seq-steps" class="d-flex flex-column gap-2 mb-3"></div>
        <p class="form-text mb-3"><?= lang('control_seq_wait_hint') ?></p>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="loop" id="seq-loop" value="1" onchange="toggleSeqLoop()">
          <label class="form-check-label" for="seq-loop"><?= lang('control_loop_checkbox') ?></label>
        </div>
        <div class="row g-3 mb-3" id="seq-loop-opts" style="display:none;">
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_repeat_count') ?></label>
            <input class="form-control" type="number" name="loop_count" id="seq-loop-count" min="0" max="9999" value="0">
            <div class="form-text"><?= lang('control_loop_until_hint') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label"><?= lang('control_delay_restart') ?></label>
            <input type="hidden" name="loop_delay_sec" id="seq-loop-delay-sec" value="0">
            <div class="input-group">
              <input class="form-control" type="number" id="seq-loop-delay" min="0" step="any" value="0">
              <select class="form-select" id="seq-loop-delay-unit" style="max-width: 8rem;">
                <option value="s"><?= lang('control_sec') ?></option>
                <option value="min"><?= lang('control_min') ?></option>
                <option value="h"><?= lang('control_hour') ?></option>
              </select>
            </div>
          </div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="enabled" id="seq-enabled" value="1" checked>
          <label class="form-check-label" for="seq-enabled"><?= lang('control_enabled') ?></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('control_cancel') ?></button>
        <button class="btn btn-primary" type="submit"><?= lang('control_save') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="rule-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="<?= base_url('control/save_rule') ?>" id="rule-form">
      <div class="modal-header">
        <h5 class="modal-title"><?= lang('control_rule_modal') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="rule-id" value="">
        <input type="hidden" name="group_id" value="<?= $currentGroupId !== null ? (int) $currentGroupId : '' ?>">
        <input type="hidden" name="conditions_json" id="rule-conditions-json" value="{}">
        <input type="hidden" name="action_json" id="rule-action-json" value="{}">
        <input type="hidden" name="cooldown_sec" id="rule-cooldown-sec" value="300">
        <div class="mb-3">
          <label class="form-label"><?= lang('control_name') ?></label>
          <input class="form-control" name="name" id="rule-name" required placeholder="e.g. Both ACs off → start standby">
        </div>
        <div class="mb-2 d-flex justify-content-between align-items-center">
          <label class="form-label mb-0"><?= lang('control_when') ?></label>
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRuleCond()"><?= lang('control_add_condition') ?></button>
        </div>
        <div class="mb-2">
          <select class="form-select form-select-sm" id="rule-match" style="max-width: 16rem;">
            <option value="all"><?= lang('control_match_all_opt') ?></option>
            <option value="any"><?= lang('control_match_any_opt') ?></option>
          </select>
        </div>
        <div id="rule-conds" class="d-flex flex-column gap-2 mb-3"></div>
        <p class="form-text mb-3"><?= lang('control_rule_examples') ?></p>
        <div class="mb-2">
          <label class="form-label"><?= lang('control_then') ?></label>
          <div class="d-flex gap-3 mb-2">
            <label class="form-check">
              <input class="form-check-input" type="radio" name="rule_action_kind" id="rule-kind-seq" value="sequence" onchange="toggleRuleAction()">
              <?= lang('control_sequence') ?>
            </label>
            <label class="form-check">
              <input class="form-check-input" type="radio" name="rule_action_kind" id="rule-kind-cmd" value="command" checked onchange="toggleRuleAction()">
              <?= lang('control_commands_plural') ?>
            </label>
          </div>
        </div>
        <div id="rule-action-seq" class="mb-3" style="display:none;">
          <select class="form-select" id="rule-sequence">
            <option value=""><?= lang('control_select_sequence') ?></option>
            <?php foreach ($sequences as $seq): ?>
              <option value="<?= (int) ($seq['id'] ?? 0) ?>"><?= htmlspecialchars($seq['name'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="rule-action-cmd" class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted"><?= lang('control_trig_commands') ?></span>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRuleCmd()"><?= lang('control_add_command') ?></button>
          </div>
          <div id="rule-cmds" class="d-flex flex-column gap-2"></div>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= lang('control_cooldown_after') ?></label>
          <div class="input-group" style="max-width: 18rem;">
            <input class="form-control" type="number" id="rule-cooldown" min="0" step="any" value="5">
            <select class="form-select" id="rule-cooldown-unit" style="max-width: 8rem;">
              <option value="s"><?= lang('control_sec') ?></option>
              <option value="min" selected><?= lang('control_min') ?></option>
              <option value="h"><?= lang('control_hour') ?></option>
            </select>
          </div>
          <div class="form-text"><?= lang('control_cooldown_hint') ?></div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="enabled" id="rule-enabled" value="1" checked>
          <label class="form-check-label" for="rule-enabled"><?= lang('control_enabled') ?></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('control_cancel') ?></button>
        <button class="btn btn-primary" type="submit"><?= lang('control_save') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="group-modal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= base_url('control/save_group') ?>">
      <div class="modal-header">
        <h5 class="modal-title"><?= lang('control_group_modal') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="grp-id" value="">
        <div class="mb-3">
          <label class="form-label"><?= lang('control_name') ?></label>
          <input class="form-control" name="name" id="grp-name" required placeholder="e.g. AB1, Server Room, Chilled water">
        </div>
        <div class="mb-3">
          <label class="form-label"><?= lang('control_type') ?></label>
          <select class="form-select" name="kind" id="grp-kind">
            <option value="building"><?= lang('control_kind_building') ?></option>
            <option value="room"><?= lang('control_kind_room') ?></option>
            <option value="system"><?= lang('control_kind_system') ?></option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= lang('control_description') ?></label>
          <input class="form-control" name="description" id="grp-description">
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger" id="grp-delete-btn" style="display:none;" onclick="deleteGroup()"><?= lang('control_delete') ?></button>
        <div class="d-flex gap-2 ms-auto">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('control_cancel') ?></button>
          <button class="btn btn-primary" type="submit"><?= lang('control_save') ?></button>
        </div>
      </div>
    </form>
  </div>
</div>
<form id="grp-delete-form" method="post" action="<?= base_url('control/delete_group') ?>" class="d-none">
  <input type="hidden" name="id" id="grp-delete-id" value="">
  <input type="hidden" name="name" id="grp-delete-name" value="">
</form>

<style>
.ov-kpi { border-left: 4px solid #adb5bd; }
.ov-kpi.is-on { border-left-color: #198754; }
.ov-kpi.is-off { border-left-color: #6c757d; }
.ov-kpi.is-unknown,
.ov-kpi.is-mismatch { border-left-color: #ffc107; }
.ov-kpi.is-disabled { border-left-color: #adb5bd; }
.ctrl-group-section { margin-bottom: 1.5rem; }
.ctrl-group-section-h {
  display: flex;
  align-items: center;
  gap: .45rem;
  font-size: .8rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--bs-secondary-color);
  margin-bottom: .75rem;
}
.ctrl-group-section-h .bi { font-size: 1rem; }
.ctrl-group-section-n {
  min-width: 1.35rem;
  height: 1.35rem;
  padding: 0 .35rem;
  border-radius: 999px;
  background: var(--bs-secondary-bg);
  color: var(--bs-body-color);
  font-size: .72rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.ctrl-group-card {
  border: 1px solid var(--bs-border-color);
  border-left: 5px solid #6c757d;
}
.ctrl-group-card.is-building { border-left-color: #0d6efd; }
.ctrl-group-card.is-room { border-left-color: #20c997; }
.ctrl-group-card.is-system { border-left-color: #fd7e14; }
.ctrl-group-card.is-generic,
.ctrl-group-card.is-ungrouped { border-left-color: #6c757d; }
.ctrl-group-card.is-empty { opacity: .88; }
.ctrl-group-top {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-bottom: .85rem;
}
.ctrl-group-icon {
  width: 2.1rem;
  height: 2.1rem;
  border-radius: .55rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: var(--bs-secondary-bg);
  color: var(--bs-secondary-color);
}
.ctrl-group-card.is-building .ctrl-group-icon { background: rgba(13,110,253,.12); color: #0d6efd; }
.ctrl-group-card.is-room .ctrl-group-icon { background: rgba(32,201,151,.14); color: #0ca678; }
.ctrl-group-card.is-system .ctrl-group-icon { background: rgba(253,126,20,.14); color: #fd7e14; }
.ctrl-group-kind {
  font-size: .75rem;
  font-weight: 600;
  color: var(--bs-secondary-color);
}
.ctrl-group-count {
  margin-left: auto;
  font-size: .72rem;
  font-weight: 600;
  padding: .2rem .55rem;
  border-radius: 999px;
  background: var(--bs-secondary-bg);
  color: var(--bs-body-color);
  white-space: nowrap;
}
.ctrl-group-count.is-zero { font-weight: 500; color: var(--bs-secondary-color); }
.ctrl-group-name {
  font-size: 1.15rem;
  font-weight: 650;
  margin-bottom: .25rem;
  line-height: 1.25;
}
.ctrl-group-desc {
  min-height: 2.4em;
  font-size: .875rem;
  color: var(--bs-secondary-color);
  margin-bottom: 1rem;
}
.ctrl-group-desc.is-blank { visibility: hidden; }
.ctrl-group-actions { display: flex; gap: .5rem; }
.ctrl-group-empty { border-style: dashed; }
[data-bs-theme="dark"] .ctrl-group-card.is-building .ctrl-group-icon { color: #6ea8fe; }
[data-bs-theme="dark"] .ctrl-group-card.is-room .ctrl-group-icon { color: #63e6be; }
[data-bs-theme="dark"] .ctrl-group-card.is-system .ctrl-group-icon { color: #ffc078; }
.ov-card { border: 1px solid var(--bs-border-color); border-left: 6px solid #adb5bd; transition: border-color .2s, background .2s; overflow: hidden; }
.ov-card.is-on { border-left-color: #198754; background: rgba(25,135,84,.08); }
.ov-card.is-off { border-left-color: #6c757d; background: rgba(108,117,125,.08); }
.ov-card.is-unknown,
.ov-card.is-mismatch { border-left-color: #ffc107; background: rgba(255,193,7,.1); }
.ov-card.is-disabled { border-left-color: #adb5bd; opacity: .72; }
.ov-badge {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .02em;
  padding: .28rem .55rem;
  border-radius: 999px;
  white-space: nowrap;
  background: var(--bs-secondary-bg);
  color: var(--bs-secondary-color);
}
.ov-card.is-on .ov-badge { background: rgba(25,135,84,.18); color: #146c43; }
.ov-card.is-off .ov-badge { background: rgba(108,117,125,.2); color: var(--bs-body-color); }
.ov-card.is-mismatch .ov-badge,
.ov-card.is-unknown .ov-badge { background: rgba(255,193,7,.28); color: #997404; }
.ov-status { display: flex; align-items: center; gap: 12px; }
.ov-lamp { width: 18px; height: 18px; border-radius: 50%; background: #adb5bd; flex-shrink: 0; box-shadow: 0 0 0 4px rgba(173,181,189,.25); }
.ov-card.is-on .ov-lamp { background: #198754; box-shadow: 0 0 12px rgba(25,135,84,.55), 0 0 0 4px rgba(25,135,84,.18); }
.ov-card.is-off .ov-lamp { background: #6c757d; box-shadow: 0 0 0 4px rgba(108,117,125,.2); }
.ov-card.is-unknown .ov-lamp,
.ov-card.is-mismatch .ov-lamp { background: #ffc107; box-shadow: 0 0 0 4px rgba(255,193,7,.28); }
.ov-state-label { font-size: 1.75rem; font-weight: 700; letter-spacing: .02em; line-height: 1.15; }
.ov-command { color: var(--bs-secondary-color); margin-top: .15rem; }
.ov-card.is-mismatch .ov-command { color: #997404; }
.ov-meta.is-warn { color: #c79100 !important; }
.ov-empty { border: 1px dashed var(--bs-border-color); }
.ov-card .ov-btn-on.active { background-color: #198754; border-color: #198754; color: #fff; }
.ov-card .ov-btn-off.active { background-color: #6c757d; border-color: #6c757d; color: #fff; }
.ov-card .ov-btn-auto.active { background-color: #0d6efd; border-color: #0d6efd; color: #fff; }
.seq-step {
  border: 1px solid var(--bs-border-color);
  background: var(--bs-secondary-bg);
  border-radius: .5rem;
  padding: .75rem;
}
.seq-step .seq-step-num {
  font-weight: 600;
  font-size: .8rem;
  color: var(--bs-secondary-color);
}
.rule-cond,
.rule-cmd {
  border: 1px solid var(--bs-border-color);
  background: var(--bs-secondary-bg);
  border-radius: .5rem;
  padding: .75rem;
}
.cmd-config-panel {
  background-color: var(--bs-secondary-bg) !important;
  border-color: var(--bs-border-color) !important;
  color: var(--bs-body-color);
}
.cmd-config-panel .form-text,
.cmd-config-panel code {
  color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .ov-card.is-on { background: rgba(25, 135, 84, 0.16); }
[data-bs-theme="dark"] .ov-card.is-off { background: rgba(108, 117, 125, 0.18); }
[data-bs-theme="dark"] .ov-card.is-unknown,
[data-bs-theme="dark"] .ov-card.is-mismatch { background: rgba(255, 193, 7, 0.12); }
[data-bs-theme="dark"] .ov-card.is-on .ov-badge { color: #75b798; }
[data-bs-theme="dark"] .ov-card.is-mismatch .ov-badge,
[data-bs-theme="dark"] .ov-card.is-unknown .ov-badge { color: #ffda6a; }
[data-bs-theme="dark"] .ov-card.is-mismatch .ov-command { color: #ffda6a; }
.ov-seq-card { border-left: 4px solid #0d6efd; }
.ov-seq-card.is-held { border-left-color: #fd7e14; }
.ov-seq-card.is-paused { border-left-color: #ffc107; }
.ov-flow {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  gap: .5rem .35rem;
}
.ov-flow-node {
  min-width: 8.5rem;
  max-width: 12rem;
  flex: 1 1 8.5rem;
  border: 1px solid var(--bs-border-color);
  background: var(--bs-secondary-bg);
  border-radius: .65rem;
  padding: .55rem .7rem;
  opacity: .55;
}
.ov-flow-node.is-done { opacity: .85; border-color: #198754; }
.ov-flow-node.is-current {
  opacity: 1;
  border-color: #0d6efd;
  box-shadow: 0 0 0 3px rgba(13, 110, 253, .2);
  background: rgba(13, 110, 253, .08);
}
.ov-flow-node.ov-flow-loop.is-current {
  border-color: #6f42c1;
  box-shadow: 0 0 0 3px rgba(111, 66, 193, .2);
  background: rgba(111, 66, 193, .1);
}
.ov-flow-node.is-held {
  opacity: 1;
  border-color: #fd7e14;
  box-shadow: 0 0 0 3px rgba(253, 126, 20, .22);
  background: rgba(253, 126, 20, .1);
}
.fc-pipe:not(.is-running):not(.is-paused) .ov-flow-node { opacity: 1; }
.ov-flow-idx {
  font-size: .72rem;
  font-weight: 700;
  color: var(--bs-secondary-color);
  margin-bottom: .15rem;
}
.ov-flow-name { font-weight: 600; line-height: 1.2; }
.ov-flow-sub { font-size: .75rem; color: var(--bs-secondary-color); margin-top: .2rem; }
.ov-flow-arrow {
  display: flex;
  align-items: center;
  color: var(--bs-secondary-color);
  font-weight: 600;
  padding: 0 .1rem;
  flex: 0 0 auto;
}
.ov-flow-node.is-current .ov-countdown {
  margin-top: .35rem;
  font-size: 1.35rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  letter-spacing: .04em;
  color: #0d6efd;
  line-height: 1.1;
}
.ov-seq-card .ov-countdown-inline {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  color: #0d6efd;
}
[data-bs-theme="dark"] .ov-flow-node.is-current .ov-countdown,
[data-bs-theme="dark"] .ov-seq-card .ov-countdown-inline { color: #6ea8fe; }
.ov-rule-card { border-left: 4px solid #fd7e14; }
.ov-rule-card.is-last { border-left-color: #adb5bd; }
.ov-rule-card .ov-rule-kicker {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--bs-secondary-color);
  margin-bottom: .2rem;
}
.ov-rule-card .ov-rule-badge {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .02em;
  padding: .28rem .55rem;
  border-radius: 999px;
  white-space: nowrap;
  background: rgba(253, 126, 20, .18);
  color: #c65d0a;
}
.ov-rule-card.is-cooldown .ov-rule-badge,
.ov-rule-card.is-last .ov-rule-badge {
  background: rgba(108, 117, 125, .2);
  color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .ov-rule-card .ov-rule-badge { color: #ffb86a; }
[data-bs-theme="dark"] .ov-rule-card.is-last .ov-rule-badge { color: #adb5bd; }
.fc-priority {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  gap: .45rem;
}
.fc-pri {
  flex: 1 1 10rem;
  min-width: 9rem;
  border: 1px solid var(--bs-border-color);
  border-radius: .75rem;
  padding: .7rem .85rem;
  background: var(--bs-secondary-bg);
}
.fc-pri .fc-rank {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--bs-secondary-color);
}
.fc-pri .fc-title { font-weight: 700; margin: .15rem 0 .25rem; }
.fc-pri .fc-desc { font-size: .78rem; color: var(--bs-secondary-color); margin: 0; line-height: 1.35; }
.fc-pri.is-1 { border-top: 3px solid #198754; }
.fc-pri.is-2 { border-top: 3px solid #fd7e14; }
.fc-pri.is-3 { border-top: 3px solid #0d6efd; }
.fc-pri.is-4 { border-top: 3px solid #6c757d; }
.fc-pri-arrow {
  display: flex;
  align-items: center;
  font-weight: 700;
  color: var(--bs-secondary-color);
  flex: 0 0 auto;
}
@media (max-width: 768px) {
  .fc-pri-arrow { display: none; }
}
.fc-node {
  min-width: 8.5rem;
  max-width: 14rem;
  flex: 1 1 8.5rem;
  border: 1px solid var(--bs-border-color);
  background: var(--bs-body-bg);
  border-radius: .65rem;
  padding: .55rem .7rem;
}
.fc-node .fc-k {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .03em;
  text-transform: uppercase;
  color: var(--bs-secondary-color);
  margin-bottom: .1rem;
}
.fc-node .fc-n { font-weight: 600; line-height: 1.2; }
.fc-node .fc-s { font-size: .75rem; color: var(--bs-secondary-color); margin-top: .15rem; }
.fc-node.is-1 { border-left: 3px solid #198754; }
.fc-node.is-cmd { border-left: 3px solid #6f42c1; }
.fc-node.is-dev { border-left: 3px solid #20c997; }
.fc-node.is-cond { border-style: solid; }
.fc-node.is-rule { border-left: 3px solid #fd7e14; }
.fc-node.is-seq { border-left: 3px solid #0d6efd; }
.fc-node.is-sch { border-left: 3px solid #6c757d; }
.fc-node.is-on { border-color: #198754; background: rgba(25,135,84,.08); }
.fc-node.is-off { border-color: #6c757d; background: rgba(108,117,125,.08); }
.fc-node.is-mismatch,
.fc-node.is-unknown { border-color: #ffc107; background: rgba(255,193,7,.1); }
.fc-node.is-disabled { opacity: .55; }
.fc-node.is-manual { box-shadow: 0 0 0 2px rgba(25, 135, 84, .28); }
.fc-node.is-latched,
.fc-pipe.is-latched .fc-pipe-rule {
  border-color: #fd7e14;
  box-shadow: 0 0 0 3px rgba(253, 126, 20, .2);
}
.fc-node.is-running,
.fc-pipe.is-running {
  border-color: #0d6efd;
  box-shadow: 0 0 0 3px rgba(13, 110, 253, .18);
}
.fc-node.is-paused { border-color: #ffc107; box-shadow: 0 0 0 3px rgba(255, 193, 7, .22); }
.fc-join {
  align-self: center;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .03em;
  text-transform: uppercase;
  padding: .28rem .55rem;
  border: 1px solid var(--bs-border-color);
  border-radius: 999px;
  background: var(--bs-secondary-bg);
  color: var(--bs-secondary-color);
  flex: 0 0 auto;
}
.fc-section-h {
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--bs-secondary-color);
  margin: 1rem 0 .5rem;
}
.fc-section-h:first-child { margin-top: 0; }
.fc-split {
  border: 0;
  border-top: 1px solid var(--bs-border-color);
  margin: 1.35rem 0 1.1rem;
  opacity: 1;
}
.fc-pipe {
  border: 1px solid var(--bs-border-color);
  border-radius: .75rem;
  padding: .85rem 1rem;
  margin-bottom: .75rem;
  background: var(--bs-body-bg);
}
.fc-pipe-h {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: .75rem;
  margin-bottom: .65rem;
}
.fc-pipe-h strong { font-size: .95rem; }
.fc-pipe.is-disabled { opacity: .62; }
.fc-io {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 2rem minmax(7.5rem, 11rem) 2rem minmax(0, 1.15fr);
  gap: .5rem;
  align-items: stretch;
}
.fc-io-col {
  min-width: 0;
  border-radius: .6rem;
  padding: .7rem .8rem .8rem;
  background: var(--bs-secondary-bg);
}
.fc-io-col.is-in { box-shadow: inset 4px 0 0 #6c757d; }
.fc-io-col.is-if { box-shadow: inset 4px 0 0 #fd7e14; }
.fc-io-col.is-out { box-shadow: inset 4px 0 0 #0d6efd; }
.fc-io-label {
  font-size: .8rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  margin-bottom: .55rem;
  color: var(--bs-body-color);
}
.fc-io-col.is-in .fc-io-label { color: #6c757d; }
.fc-io-col.is-if .fc-io-label { color: #fd7e14; }
.fc-io-col.is-out .fc-io-label { color: #0d6efd; }
[data-bs-theme="dark"] .fc-io-col.is-in .fc-io-label { color: #adb5bd; }
[data-bs-theme="dark"] .fc-io-col.is-if .fc-io-label { color: #ffb86a; }
[data-bs-theme="dark"] .fc-io-col.is-out .fc-io-label { color: #6ea8fe; }
.fc-io-stack {
  display: flex;
  flex-direction: column;
  gap: .4rem;
  align-items: stretch;
}
.fc-io-col .fc-node {
  max-width: none;
  width: 100%;
  flex: none;
  background: var(--bs-body-bg);
}
.fc-node.is-action.is-on .fc-k { color: #198754; }
.fc-node.is-action.is-off .fc-k { color: #6c757d; }
[data-bs-theme="dark"] .fc-node.is-action.is-off .fc-k { color: #adb5bd; }
.fc-io-col .fc-join { align-self: center; }
.fc-io-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--bs-secondary-color);
}
.fc-match {
  text-align: center;
  padding: .35rem .25rem .15rem;
}
.fc-match-val {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.1;
  color: #fd7e14;
}
.fc-match-s {
  font-size: .75rem;
  color: var(--bs-secondary-color);
  margin-top: .3rem;
}
@media (max-width: 992px) {
  .fc-io { grid-template-columns: 1fr; }
  .fc-io-arrow { transform: rotate(90deg); padding: .1rem 0; font-size: 1.1rem; }
}
.fc-legend { display: flex; flex-wrap: wrap; gap: .5rem 1rem; }
.fc-leg { display: inline-flex; align-items: center; gap: .35rem; }
.fc-leg::before {
  content: '';
  width: .7rem;
  height: .7rem;
  border-radius: .2rem;
  border: 1px solid var(--bs-border-color);
  background: var(--bs-secondary-bg);
}
.fc-leg.is-on::before { background: rgba(25,135,84,.35); border-color: #198754; }
.fc-leg.is-off::before { background: rgba(108,117,125,.35); border-color: #6c757d; }
.fc-leg.is-mismatch::before { background: rgba(255,193,7,.45); border-color: #ffc107; }
.fc-leg.is-manual::before { box-shadow: 0 0 0 2px rgba(25,135,84,.45); border-color: #198754; }
.fc-leg.is-latched::before { background: rgba(253,126,20,.35); border-color: #fd7e14; }
.fc-leg.is-running::before { background: rgba(13,110,253,.3); border-color: #0d6efd; }
.fc-in { font-size: .75rem; color: var(--bs-secondary-color); margin-top: .2rem; }
[data-bs-theme="dark"] .fc-node.is-on { background: rgba(25, 135, 84, 0.16); }
[data-bs-theme="dark"] .fc-node.is-off { background: rgba(108, 117, 125, 0.18); }
[data-bs-theme="dark"] .fc-node.is-mismatch,
[data-bs-theme="dark"] .fc-node.is-unknown { background: rgba(255, 193, 7, 0.12); }
.tab-content .dataTables_wrapper { width: 100%; }
</style>

<script>
var cmdWriteTargets = [];
var pendingFeedback = '';
var pendingWriteKey = '';
var pendingWriteVia = '';
var currentGroupId = <?= json_encode($currentGroupId) ?>;
var metricsUrl = <?= json_encode(base_url('control/device_metrics')) ?>;
var overviewUrl = <?= json_encode(base_url('control/overview_status') . $groupQuery) ?>;
var executeUrl = <?= json_encode(base_url('control/execute')) ?>;
var executeSequenceUrl = <?= json_encode(base_url('control/execute_sequence')) ?>;
var stopSequenceUrl = <?= json_encode(base_url('control/stop_sequence')) ?>;
var pauseSequenceUrl = <?= json_encode(base_url('control/pause_sequence')) ?>;
var resumeSequenceUrl = <?= json_encode(base_url('control/resume_sequence')) ?>;
var canCreate = <?= $canCreate ? 'true' : 'false' ?>;
var canUpdate = <?= $canUpdate ? 'true' : 'false' ?>;
var canDelete = <?= $canDelete ? 'true' : 'false' ?>;
var seqCommands = <?= json_encode(array_values(array_map(function ($c) {
  return [
    'id' => (int) ($c['id'] ?? 0),
    'name' => (string) ($c['name'] ?? ''),
    'device_name' => (string) ($c['device_name'] ?? ''),
  ];
}, $commands)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
var I18N = <?= json_encode((function () {
  $out = [];
  $lang = get_instance()->lang->language;
  if (!is_array($lang)) {
    return $out;
  }
  foreach ($lang as $k => $v) {
    if (is_string($k) && strpos($k, 'control') === 0) {
      $out[$k] = $v;
    }
  }
  return $out;
})(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function L(k) { return (I18N && I18N[k] != null) ? I18N[k] : k; }
function Lf(k) {
  var s = L(k);
  var args = Array.prototype.slice.call(arguments, 1);
  return s.replace(/%s/g, function () { return args.length ? args.shift() : ''; });
}
function Laction(a) { return a === 'off' ? L('control_off') : L('control_on'); }
function Lunit(u) { return u === 'h' ? L('control_hour') : (u === 'min' ? L('control_min') : L('control_sec')); }
function Ldays() { return String(L('control_days_short') || '').split(','); }
var flowModel = <?= json_encode(control_flow_model($commands, $sequences, $rules, $schedules), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
var ruleDevices = <?= json_encode(array_values(array_map(function ($d) {
  return [
    'id' => (int) $d->id,
    'name' => (string) $d->name,
  ];
}, is_array($devices) ? $devices : [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
var latestMetricUrl = <?= json_encode(base_url('device/metrics_latest')) ?>;
var overviewTimer = null;
var overviewCountdownTimer = null;

function parseList(s) {
  return String(s || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean);
}

function listToField(v) {
  if (Array.isArray(v)) return v.join(', ');
  return v == null ? '' : String(v);
}

function coerceSetValue(s) {
  var t = String(s == null ? '' : s).trim();
  if (t === '') return null;
  if (t !== '' && !isNaN(Number(t))) return Number(t);
  return t;
}

function readWriteConfig() {
  try { return JSON.parse(document.getElementById('cmd-write').value || '{}') || {}; } catch (e) { return {}; }
}

function writeWriteConfig(cfg) {
  document.getElementById('cmd-write').value = JSON.stringify(cfg, null, 2);
}

function syncConfigFromFields() {
  var cfg = readWriteConfig();
  var on = coerceSetValue(document.getElementById('cmd-on-value').value);
  var off = coerceSetValue(document.getElementById('cmd-off-value').value);
  if (on == null) delete cfg.on_value; else cfg.on_value = on;
  if (off == null) delete cfg.off_value; else cfg.off_value = off;
  var fbOn = parseList(document.getElementById('cmd-fb-on').value);
  var fbOff = parseList(document.getElementById('cmd-fb-off').value);
  if (fbOn.length) cfg.feedback_on = fbOn; else delete cfg.feedback_on;
  if (fbOff.length) cfg.feedback_off = fbOff; else delete cfg.feedback_off;
  var onLabel = String(document.getElementById('cmd-fb-on-label').value || '').trim();
  var offLabel = String(document.getElementById('cmd-fb-off-label').value || '').trim();
  if (onLabel) cfg.feedback_on_label = onLabel; else delete cfg.feedback_on_label;
  if (offLabel) cfg.feedback_off_label = offLabel; else delete cfg.feedback_off_label;
  writeWriteConfig(cfg);
}

function syncFieldsFromConfig(cfg) {
  cfg = cfg || readWriteConfig();
  document.getElementById('cmd-on-value').value = cfg.on_value != null ? String(cfg.on_value) : '';
  document.getElementById('cmd-off-value').value = cfg.off_value != null ? String(cfg.off_value) : '';
  document.getElementById('cmd-fb-on').value = listToField(cfg.feedback_on);
  document.getElementById('cmd-fb-off').value = listToField(cfg.feedback_off);
  document.getElementById('cmd-fb-on-label').value = cfg.feedback_on_label != null ? String(cfg.feedback_on_label) : '';
  document.getElementById('cmd-fb-off-label').value = cfg.feedback_off_label != null ? String(cfg.feedback_off_label) : '';
}

function fillSelect(sel, items, placeholder, selectedKey) {
  sel.innerHTML = '';
  var empty = document.createElement('option');
  empty.value = '';
  empty.textContent = placeholder;
  sel.appendChild(empty);
  (items || []).forEach(function (item) {
    var opt = document.createElement('option');
    opt.value = item.key;
    var extra = item.from_gateway ? ' ← ' + item.from_gateway : (item.hint ? ' · ' + item.hint : '');
    opt.textContent = (item.label || item.key) + (item.key !== item.label ? ' (' + item.key + ')' : '') + extra;
    if (selectedKey && item.key === selectedKey) opt.selected = true;
    sel.appendChild(opt);
  });
}

function applyWriteTarget(key, mergeJson) {
  var target = cmdWriteTargets.find(function (t) { return t.key === key; });
  if (!target) return;
  document.getElementById('cmd-protocol').value = target.protocol || 'snmp_set';
  var prev = readWriteConfig();
  var next = Object.assign({}, prev, target.write_config || {});
  if (mergeJson) {
    if (prev.on_value != null) next.on_value = prev.on_value;
    if (prev.off_value != null) next.off_value = prev.off_value;
  }
  if (prev.feedback_on != null) next.feedback_on = prev.feedback_on;
  if (prev.feedback_off != null) next.feedback_off = prev.feedback_off;
  if (prev.feedback_on_label != null) next.feedback_on_label = prev.feedback_on_label;
  if (prev.feedback_off_label != null) next.feedback_off_label = prev.feedback_off_label;
  writeWriteConfig(next);
  syncFieldsFromConfig(next);
}

function loadDeviceContext() {
  var deviceId = document.getElementById('cmd-device').value;
  var viaId = document.getElementById('cmd-write-via').value;
  var feedbackSel = document.getElementById('cmd-feedback');
  var writeSel = document.getElementById('cmd-write-target');
  var note = document.getElementById('cmd-endpoint-note');
  note.style.display = 'none';
  cmdWriteTargets = [];

  if (!deviceId) {
    fillSelect(feedbackSel, [], L('control_select_device_first'), '');
    fillSelect(writeSel, [], L('control_select_device_first'), '');
    return;
  }

  fillSelect(feedbackSel, [], L('control_loading'), '');
  fillSelect(writeSel, [], L('control_loading'), '');

  var url = metricsUrl.replace(/\/?$/, '/') + encodeURIComponent(deviceId);
  if (viaId) url += '?write_device_id=' + encodeURIComponent(viaId);

  fetch(url, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.ok || !data.device) {
        fillSelect(feedbackSel, [], L('control_no_metrics'), '');
        fillSelect(writeSel, [], L('control_no_write_targets'), '');
        return;
      }
      var d = data.device;
      cmdWriteTargets = d.write_targets || [];
      fillSelect(feedbackSel, d.feedback_metrics || [], L('control_optional_status'), pendingFeedback);
      fillSelect(writeSel, cmdWriteTargets, L('control_select_actuator'), pendingWriteKey);

      if (!viaId && d.default_write_via_id) {
        document.getElementById('cmd-write-via').value = String(d.default_write_via_id);
      }

      var viaName = d.write_via && d.write_via.name ? d.write_via.name : '';
      if (d.role === 'endpoint' && d.gateway) {
        note.style.display = '';
        note.querySelector('.alert').textContent =
          d.name + ' is an endpoint of "' + d.gateway.name +
          '". ON/OFF writes a dry-contact DO there. Feedback is a metric on this CRAC.';
      } else if (d.write_via && d.write_via.cross_device) {
        note.style.display = '';
        note.querySelector('.alert').textContent =
          d.name + ' is read over its own protocol. ON/OFF writes a dry-contact DO on "' + viaName + '".';
      } else if (!cmdWriteTargets.length) {
        note.style.display = '';
        note.querySelector('.alert').textContent =
          'No DO on this device. For STULZ remote start/stop, set Write via to the J1000 (e.g. AB1 - EMS 05) then pick a DO.';
      }

      if (pendingWriteKey) {
        applyWriteTarget(pendingWriteKey, true);
      } else {
        syncFieldsFromConfig();
      }
      pendingFeedback = '';
      pendingWriteKey = '';
      pendingWriteVia = '';
      loadFeedbackLatest();
    })
    .catch(function () {
      fillSelect(feedbackSel, [], L('control_load_metrics_failed'), '');
      fillSelect(writeSel, [], L('control_load_actuators_failed'), '');
    });
}

document.getElementById('cmd-device').addEventListener('change', function () {
  pendingFeedback = '';
  pendingWriteKey = '';
  pendingWriteVia = '';
  document.getElementById('cmd-write-via').value = '';
  loadDeviceContext();
});

document.getElementById('cmd-write-via').addEventListener('change', function () {
  pendingFeedback = document.getElementById('cmd-feedback').value;
  pendingWriteKey = '';
  loadDeviceContext();
});

document.getElementById('cmd-write-target').addEventListener('change', function () {
  applyWriteTarget(this.value, false);
});

document.getElementById('cmd-feedback').addEventListener('change', loadFeedbackLatest);

['cmd-on-value', 'cmd-off-value', 'cmd-fb-on', 'cmd-fb-off', 'cmd-fb-on-label', 'cmd-fb-off-label'].forEach(function (id) {
  document.getElementById(id).addEventListener('change', syncConfigFromFields);
  document.getElementById(id).addEventListener('blur', syncConfigFromFields);
});

document.getElementById('command-form').addEventListener('submit', function () {
  syncConfigFromFields();
});

function loadFeedbackLatest() {
  var hint = document.getElementById('cmd-fb-latest');
  var deviceId = document.getElementById('cmd-device').value;
  var key = document.getElementById('cmd-feedback').value;
  var base = L('control_feedback_hint');
  if (!deviceId || !key) {
    hint.textContent = base;
    return;
  }
  hint.textContent = base + ' ' + L('control_loading_latest');
  fetch(latestMetricUrl + '?device_id=' + encodeURIComponent(deviceId) + '&metric_key=' + encodeURIComponent(key), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data && data.status === 'success' && data.value != null) {
        hint.textContent = base + ' ' + Lf('control_latest_sample', data.value);
      } else {
        hint.textContent = base + ' ' + L('control_no_sample');
      }
    })
    .catch(function () {
      hint.textContent = base;
    });
}

function openCommand(row) {
  document.getElementById('cmd-id').value = row && row.id ? row.id : '';
  document.getElementById('cmd-name').value = row && row.name ? row.name : '';
  document.getElementById('cmd-description').value = row && row.description ? row.description : '';
  document.getElementById('cmd-device').value = row && row.device_id ? String(row.device_id) : '';
  document.getElementById('cmd-protocol').value = row && row.protocol ? row.protocol : 'snmp_set';
  document.getElementById('cmd-enabled').checked = !row || row.enabled === true || row.enabled === 't' || row.enabled === 1;
  var cfg = row && row.write_config ? row.write_config : {};
  if (typeof cfg === 'string') {
    try { cfg = JSON.parse(cfg); } catch (e) { cfg = {}; }
    document.getElementById('cmd-write').value = row.write_config;
  } else {
    document.getElementById('cmd-write').value = JSON.stringify(cfg, null, 2);
  }
  syncFieldsFromConfig(cfg);
  pendingFeedback = row && row.feedback_metric_key ? row.feedback_metric_key : '';
  pendingWriteKey = cfg && cfg.metric_key ? String(cfg.metric_key) : '';
  pendingWriteVia = cfg && cfg.gateway_device_id ? String(cfg.gateway_device_id) : '';
  document.getElementById('cmd-write-via').value = pendingWriteVia;
  var gid = row && row.group_id != null ? Number(row.group_id) : Number(currentGroupId);
  document.getElementById('cmd-group').value = gid > 0 ? String(gid) : '';
  loadDeviceContext();
  var modal = new bootstrap.Modal(document.getElementById('command-modal'));
  modal.show();
}

function parseCron(expr) {
  var parts = String(expr || '').trim().split(/\s+/);
  if (parts.length < 5) return { time: '08:00', days: [0, 1, 2, 3, 4, 5, 6] };
  var minute = String(parts[0]).padStart(2, '0');
  var hour = String(parts[1]).padStart(2, '0');
  var dow = parts[4];
  var days = dow === '*' ? [0, 1, 2, 3, 4, 5, 6] : dow.split(',').map(function (n) { return parseInt(n, 10); }).filter(function (n) { return n >= 0 && n <= 6; });
  return { time: hour + ':' + minute, days: days };
}

function openSchedule(row) {
  document.getElementById('sch-id').value = row && row.id ? row.id : '';
  document.getElementById('sch-name').value = row && row.name ? row.name : '';
  document.getElementById('sch-command').value = row && row.command_id ? String(row.command_id) : '';
  document.getElementById('sch-action').value = row && row.action === 'off' ? 'off' : 'on';
  document.getElementById('sch-enabled').checked = !row || row.enabled === true || row.enabled === 't' || row.enabled === 1;
  var parsed = parseCron(row && row.cron);
  document.getElementById('sch-time').value = parsed.time;
  document.querySelectorAll('.sch-day').forEach(function (el) {
    el.checked = parsed.days.indexOf(parseInt(el.value, 10)) >= 0;
  });
  var modal = new bootstrap.Modal(document.getElementById('schedule-modal'));
  modal.show();
}

function seqCommandOptions(selected) {
  var html = '<option value="">' + L('control_select_command') + '</option>';
  seqCommands.forEach(function (c) {
    var sel = String(c.id) === String(selected) ? ' selected' : '';
    var label = c.name + (c.device_name ? ' — ' + c.device_name : '');
    html += '<option value="' + c.id + '"' + sel + '>' + label.replace(/</g, '&lt;') + '</option>';
  });
  return html;
}

function seqDurationParts(sec, preferredUnit) {
  var n = parseInt(sec, 10) || 0;
  if (preferredUnit === 'h' || preferredUnit === 'min' || preferredUnit === 's') {
    if (preferredUnit === 'h') return { value: n ? n / 3600 : '', unit: 'h' };
    if (preferredUnit === 'min') return { value: n ? n / 60 : '', unit: 'min' };
    return { value: n || '', unit: 's' };
  }
  if (n >= 3600 && n % 3600 === 0) return { value: n / 3600, unit: 'h' };
  if (n >= 60 && n % 60 === 0) return { value: n / 60, unit: 'min' };
  return { value: n || '', unit: 's' };
}

function seqToSeconds(value, unit) {
  var n = parseFloat(value);
  if (!isFinite(n) || n < 0) n = 0;
  var sec = unit === 'h' ? n * 3600 : (unit === 'min' ? n * 60 : n);
  return Math.min(86400, Math.max(0, Math.round(sec)));
}

function seqDelayUnitOptions(selected) {
  return ['s', 'min', 'h'].map(function (u) {
    return '<option value="' + u + '"' + (u === selected ? ' selected' : '') + '>' + Lunit(u) + '</option>';
  }).join('');
}

function addSeqStep(step) {
  var wrap = document.getElementById('seq-steps');
  if (!wrap) return;
  var n = wrap.children.length + 1;
  var s = step || {};
  var wait = s.wait === 'on' || s.wait === 'off' ? s.wait : 'none';
  var timeout = s.timeout_sec != null && s.timeout_sec !== '' ? s.timeout_sec : (wait === 'none' ? '' : 30);
  var delayParts = seqDurationParts(s.delay_sec, s.delay_unit);
  var el = document.createElement('div');
  el.className = 'seq-step';
  el.innerHTML =
    '<div class="d-flex justify-content-between align-items-center mb-2">' +
      '<span class="seq-step-num">' + L('control_step') + ' ' + n + '</span>' +
      '<button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSeqStep(this)">' + L('control_remove') + '</button>' +
    '</div>' +
    '<div class="row g-2">' +
      '<div class="col-md-6"><label class="form-label small mb-1">' + L('control_command') + '</label>' +
        '<select class="form-select form-select-sm seq-command" required>' + seqCommandOptions(s.command_id) + '</select></div>' +
      '<div class="col-6 col-md-3"><label class="form-label small mb-1">' + L('control_action') + '</label>' +
        '<select class="form-select form-select-sm seq-action">' +
          '<option value="on"' + (s.action === 'off' ? '' : ' selected') + '>' + L('control_on') + '</option>' +
          '<option value="off"' + (s.action === 'off' ? ' selected' : '') + '>' + L('control_off') + '</option>' +
        '</select></div>' +
      '<div class="col-6 col-md-3"><label class="form-label small mb-1">' + L('control_wait') + '</label>' +
        '<select class="form-select form-select-sm seq-wait">' +
          '<option value="none"' + (wait === 'none' ? ' selected' : '') + '>' + L('control_none') + '</option>' +
          '<option value="on"' + (wait === 'on' ? ' selected' : '') + '>' + L('control_until_on') + '</option>' +
          '<option value="off"' + (wait === 'off' ? ' selected' : '') + '>' + L('control_until_off') + '</option>' +
        '</select></div>' +
      '<div class="col-6 col-md-3"><label class="form-label small mb-1">' + L('control_timeout_s') + '</label>' +
        '<input class="form-control form-control-sm seq-timeout" type="number" min="0" max="300" placeholder="30" value="' + timeout + '"></div>' +
      '<div class="col-12 col-md-6"><label class="form-label small mb-1">' + L('control_delay_after') + '</label>' +
        '<div class="input-group input-group-sm">' +
          '<input class="form-control seq-delay" type="number" min="0" step="any" placeholder="0" value="' + delayParts.value + '">' +
          '<select class="form-select seq-delay-unit" style="max-width: 6.5rem;">' + seqDelayUnitOptions(delayParts.unit) + '</select>' +
        '</div></div>' +
    '</div>';
  wrap.appendChild(el);
  renumberSeqSteps();
}

function removeSeqStep(btn) {
  var wrap = document.getElementById('seq-steps');
  var row = btn && btn.closest ? btn.closest('.seq-step') : null;
  if (row && wrap) row.remove();
  if (wrap && !wrap.children.length) addSeqStep();
  renumberSeqSteps();
}

function renumberSeqSteps() {
  document.querySelectorAll('#seq-steps .seq-step-num').forEach(function (el, i) {
    el.textContent = L('control_step') + ' ' + (i + 1);
  });
}

function collectSeqSteps() {
  var steps = [];
  document.querySelectorAll('#seq-steps .seq-step').forEach(function (row) {
    var commandId = parseInt((row.querySelector('.seq-command') || {}).value, 10);
    if (!commandId) return;
    var waitEl = row.querySelector('.seq-wait');
    var timeoutEl = row.querySelector('.seq-timeout');
    var delayEl = row.querySelector('.seq-delay');
    var delayUnitEl = row.querySelector('.seq-delay-unit');
    var delayUnit = delayUnitEl && delayUnitEl.value ? delayUnitEl.value : 's';
    steps.push({
      command_id: commandId,
      action: (row.querySelector('.seq-action') || {}).value === 'off' ? 'off' : 'on',
      wait: waitEl && (waitEl.value === 'on' || waitEl.value === 'off') ? waitEl.value : 'none',
      timeout_sec: timeoutEl && timeoutEl.value !== '' ? parseInt(timeoutEl.value, 10) || 0 : 0,
      delay_sec: seqToSeconds(delayEl ? delayEl.value : 0, delayUnit),
      delay_unit: delayUnit
    });
  });
  return steps;
}

function openSequence(row) {
  document.getElementById('seq-id').value = row && row.id ? row.id : '';
  document.getElementById('seq-name').value = row && row.name ? row.name : '';
  document.getElementById('seq-enabled').checked = !row || row.enabled === true || row.enabled === 't' || row.enabled === 1;
  document.getElementById('seq-loop').checked = !!(row && (row.loop === true || row.loop === 't' || row.loop === 1 || row.loop === '1'));
  document.getElementById('seq-loop-count').value = row && row.loop_count != null ? String(row.loop_count) : '0';
  var loopDelay = seqDurationParts(row && row.loop_delay_sec, null);
  document.getElementById('seq-loop-delay').value = loopDelay.value === '' ? '0' : String(loopDelay.value);
  document.getElementById('seq-loop-delay-unit').value = loopDelay.unit;
  document.getElementById('seq-loop-delay-sec').value = String(seqToSeconds(loopDelay.value, loopDelay.unit));
  toggleSeqLoop();
  var wrap = document.getElementById('seq-steps');
  wrap.innerHTML = '';
  var steps = row && row.steps ? row.steps : [];
  if (typeof steps === 'string') {
    try { steps = JSON.parse(steps); } catch (e) { steps = []; }
  }
  if (!Array.isArray(steps) || !steps.length) {
    addSeqStep();
  } else {
    steps.forEach(function (s) { addSeqStep(s); });
  }
  var modal = new bootstrap.Modal(document.getElementById('sequence-modal'));
  modal.show();
}

function toggleSeqLoop() {
  var on = document.getElementById('seq-loop') && document.getElementById('seq-loop').checked;
  var opts = document.getElementById('seq-loop-opts');
  if (opts) opts.style.display = on ? '' : 'none';
}

function runSequence(id, name, loop) {
  var msg = loop
    ? Lf('control_confirm_run_loop', name)
    : Lf('control_confirm_run', name);
  if (!confirm(msg)) return;
  var body = 'id=' + encodeURIComponent(id);
  if (name) body += '&name=' + encodeURIComponent(name);
  if (currentGroupId != null && currentGroupId !== '') body += '&group_id=' + encodeURIComponent(currentGroupId);
  fetch(executeSequenceUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.ok) {
        alert((data && data.error) ? data.error : L('control_run_failed'));
        return;
      }
      location.reload();
    })
    .catch(function () {
      alert(L('control_run_failed'));
    });
}

function seqPost(url, id, name, failLabel, reload) {
  var body = 'id=' + encodeURIComponent(id);
  if (name) body += '&name=' + encodeURIComponent(name);
  if (currentGroupId != null && currentGroupId !== '') body += '&group_id=' + encodeURIComponent(currentGroupId);
  fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.ok) {
        alert((data && data.error) ? data.error : failLabel);
        return;
      }
      if (reload) location.reload();
      else loadOverview();
    })
    .catch(function () {
      alert(failLabel);
    });
}

function stopSequence(id, name) {
  if (!confirm(Lf('control_confirm_stop', name))) return;
  seqPost(stopSequenceUrl, id, name, L('control_stop_failed'), true);
}

function pauseSequence(id, name) {
  if (!confirm(Lf('control_confirm_pause', name))) return;
  seqPost(pauseSequenceUrl, id, name, L('control_pause_failed'), true);
}

function resumeSequence(id, name) {
  seqPost(resumeSequenceUrl, id, name, L('control_resume_failed'), true);
}

function pauseSequenceLive(id, name) {
  seqPost(pauseSequenceUrl, id, name, L('control_pause_failed'), false);
}

function resumeSequenceLive(id, name) {
  seqPost(resumeSequenceUrl, id, name, L('control_resume_failed'), false);
}

function stopSequenceLive(id, name) {
  if (!confirm(Lf('control_confirm_stop', name))) return;
  seqPost(stopSequenceUrl, id, name, L('control_stop_failed'), false);
}

var seqForm = document.getElementById('sequence-form');
if (seqForm) {
  seqForm.addEventListener('submit', function (ev) {
    var steps = collectSeqSteps();
    if (!steps.length) {
      ev.preventDefault();
      alert(L('control_need_step'));
      return;
    }
    document.getElementById('seq-steps-json').value = JSON.stringify(steps);
    var loopDelayEl = document.getElementById('seq-loop-delay');
    var loopUnitEl = document.getElementById('seq-loop-delay-unit');
    var loopSecEl = document.getElementById('seq-loop-delay-sec');
    if (loopSecEl) {
      loopSecEl.value = String(seqToSeconds(loopDelayEl ? loopDelayEl.value : 0, loopUnitEl ? loopUnitEl.value : 's'));
    }
  });
}

function ruleDeviceOptions(selected) {
  var html = '<option value="">' + L('control_select_device') + '</option>';
  ruleDevices.forEach(function (d) {
    var sel = String(d.id) === String(selected) ? ' selected' : '';
    html += '<option value="' + d.id + '"' + sel + '>' + String(d.name || '').replace(/</g, '&lt;') + '</option>';
  });
  return html;
}

function ruleOpOptions(selected) {
  return ['>', '>=', '<', '<=', '==', '!='].map(function (op) {
    return '<option value="' + op + '"' + (op === selected ? ' selected' : '') + '>' + op + '</option>';
  }).join('');
}

function addRuleCond(item) {
  var wrap = document.getElementById('rule-conds');
  if (!wrap) return;
  var it = item || {};
  var type = it.type === 'alarm' || it.type === 'metric' ? it.type : 'command_status';
  var el = document.createElement('div');
  el.className = 'rule-cond';
  el.innerHTML =
    '<div class="d-flex justify-content-between align-items-center mb-2">' +
      '<select class="form-select form-select-sm rule-cond-type" style="max-width: 14rem;" onchange="toggleRuleCondType(this)">' +
        '<option value="command_status"' + (type === 'command_status' ? ' selected' : '') + '>' + L('control_command_status') + '</option>' +
        '<option value="alarm"' + (type === 'alarm' ? ' selected' : '') + '>' + L('control_active_alarm') + '</option>' +
        '<option value="metric"' + (type === 'metric' ? ' selected' : '') + '>' + L('control_metric_value') + '</option>' +
      '</select>' +
      '<button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRuleCond(this)">' + L('control_remove') + '</button>' +
    '</div>' +
    '<div class="rule-cond-fields"></div>';
  wrap.appendChild(el);
  fillRuleCondFields(el, it);
}

function removeRuleCond(btn) {
  var wrap = document.getElementById('rule-conds');
  var row = btn && btn.closest ? btn.closest('.rule-cond') : null;
  if (row && wrap) row.remove();
  if (wrap && !wrap.children.length) addRuleCond();
}

function toggleRuleCondType(sel) {
  var row = sel && sel.closest ? sel.closest('.rule-cond') : null;
  if (row) fillRuleCondFields(row, { type: sel.value });
}

function fillRuleCondFields(row, item) {
  item = item || {};
  var type = (row.querySelector('.rule-cond-type') || {}).value || 'command_status';
  var host = row.querySelector('.rule-cond-fields');
  if (!host) return;
  if (type === 'command_status') {
    host.innerHTML =
      '<div class="row g-2">' +
        '<div class="col-md-8"><label class="form-label small mb-1">' + L('control_command') + '</label>' +
          '<select class="form-select form-select-sm rule-cond-command">' + seqCommandOptions(item.command_id) + '</select></div>' +
        '<div class="col-md-4"><label class="form-label small mb-1">' + L('control_is_label') + '</label>' +
          '<select class="form-select form-select-sm rule-cond-state">' +
            '<option value="off"' + (item.state === 'on' ? '' : ' selected') + '>' + L('control_off') + '</option>' +
            '<option value="on"' + (item.state === 'on' ? ' selected' : '') + '>' + L('control_on') + '</option>' +
          '</select></div>' +
      '</div>';
    return;
  }
  if (type === 'alarm') {
    host.innerHTML =
      '<div class="row g-2">' +
        '<div class="col-md-6"><label class="form-label small mb-1">' + L('control_device') + '</label>' +
          '<select class="form-select form-select-sm rule-cond-device">' + ruleDeviceOptions(item.device_id) + '</select></div>' +
        '<div class="col-md-6"><label class="form-label small mb-1">' + L('control_metric_key_opt') + '</label>' +
          '<input class="form-control form-control-sm rule-cond-mkey" placeholder="e.g. Overload" value="' + String(item.metric_key || '').replace(/"/g, '&quot;') + '">' +
          '<div class="form-text">' + L('control_alarm_empty_hint') + '</div></div>' +
      '</div>';
    return;
  }
  host.innerHTML =
    '<div class="row g-2">' +
      '<div class="col-md-4"><label class="form-label small mb-1">' + L('control_device') + '</label>' +
        '<select class="form-select form-select-sm rule-cond-device" onchange="loadRuleMetricKeys(this)">' + ruleDeviceOptions(item.device_id) + '</select></div>' +
      '<div class="col-md-4"><label class="form-label small mb-1">' + L('control_metric') + '</label>' +
        '<select class="form-select form-select-sm rule-cond-key"></select></div>' +
      '<div class="col-4 col-md-2"><label class="form-label small mb-1">' + L('control_op') + '</label>' +
        '<select class="form-select form-select-sm rule-cond-op">' + ruleOpOptions(item.op || '>') + '</select></div>' +
      '<div class="col-8 col-md-2"><label class="form-label small mb-1">' + L('control_value') + '</label>' +
        '<input class="form-control form-control-sm rule-cond-value" value="' + String(item.value != null ? item.value : '').replace(/"/g, '&quot;') + '"></div>' +
    '</div>';
  var deviceSel = row.querySelector('.rule-cond-device');
  if (deviceSel) loadRuleMetricKeys(deviceSel, item.key || item.metric_key || '');
}

function loadRuleMetricKeys(sel, selectedKey) {
  var row = sel && sel.closest ? sel.closest('.rule-cond') : null;
  var keySel = row ? row.querySelector('.rule-cond-key') : null;
  if (!keySel) return;
  var deviceId = sel.value;
  keySel.innerHTML = '<option value="">' + (deviceId ? L('control_loading') : L('control_select_device')) + '</option>';
  if (!deviceId) return;
  fetch(metricsUrl.replace(/\/?$/, '/') + encodeURIComponent(deviceId), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var metrics = (data && data.ok && data.device && data.device.feedback_metrics) ? data.device.feedback_metrics : [];
      var html = '<option value="">' + L('control_select_metric') + '</option>';
      metrics.forEach(function (m) {
        var selAttr = String(m.key) === String(selectedKey) ? ' selected' : '';
        html += '<option value="' + String(m.key).replace(/"/g, '&quot;') + '"' + selAttr + '>' +
          String(m.label || m.key).replace(/</g, '&lt;') + '</option>';
      });
      if (selectedKey && metrics.every(function (m) { return String(m.key) !== String(selectedKey); })) {
        html += '<option value="' + String(selectedKey).replace(/"/g, '&quot;') + '" selected>' + String(selectedKey).replace(/</g, '&lt;') + '</option>';
      }
      keySel.innerHTML = html;
    })
    .catch(function () {
      keySel.innerHTML = '<option value="">' + L('control_load_failed') + '</option>';
    });
}

function addRuleCmd(item) {
  var wrap = document.getElementById('rule-cmds');
  if (!wrap) return;
  var it = item || {};
  var el = document.createElement('div');
  el.className = 'rule-cmd';
  el.innerHTML =
    '<div class="row g-2 align-items-end">' +
      '<div class="col-md-7"><label class="form-label small mb-1">' + L('control_command') + '</label>' +
        '<select class="form-select form-select-sm rule-cmd-id">' + seqCommandOptions(it.command_id) + '</select></div>' +
      '<div class="col-md-3"><label class="form-label small mb-1">' + L('control_action') + '</label>' +
        '<select class="form-select form-select-sm rule-cmd-action">' +
          '<option value="on"' + (it.action === 'off' ? '' : ' selected') + '>' + L('control_on') + '</option>' +
          '<option value="off"' + (it.action === 'off' ? ' selected' : '') + '>' + L('control_off') + '</option>' +
        '</select></div>' +
      '<div class="col-md-2 text-end">' +
        '<button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRuleCmd(this)">' + L('control_remove') + '</button></div>' +
    '</div>';
  wrap.appendChild(el);
}

function removeRuleCmd(btn) {
  var wrap = document.getElementById('rule-cmds');
  var row = btn && btn.closest ? btn.closest('.rule-cmd') : null;
  if (row && wrap) row.remove();
  if (wrap && !wrap.children.length) addRuleCmd();
}

function toggleRuleAction() {
  var seq = document.getElementById('rule-kind-seq') && document.getElementById('rule-kind-seq').checked;
  var seqBox = document.getElementById('rule-action-seq');
  var cmdBox = document.getElementById('rule-action-cmd');
  if (seqBox) seqBox.style.display = seq ? '' : 'none';
  if (cmdBox) cmdBox.style.display = seq ? 'none' : '';
}

function collectRuleConditions() {
  var items = [];
  document.querySelectorAll('#rule-conds .rule-cond').forEach(function (row) {
    var type = (row.querySelector('.rule-cond-type') || {}).value || 'command_status';
    if (type === 'command_status') {
      var commandId = parseInt((row.querySelector('.rule-cond-command') || {}).value, 10);
      if (!commandId) return;
      items.push({
        type: 'command_status',
        command_id: commandId,
        state: (row.querySelector('.rule-cond-state') || {}).value === 'on' ? 'on' : 'off'
      });
      return;
    }
    if (type === 'alarm') {
      var deviceId = parseInt((row.querySelector('.rule-cond-device') || {}).value, 10);
      if (!deviceId) return;
      var key = String((row.querySelector('.rule-cond-mkey') || {}).value || '').trim();
      items.push({ type: 'alarm', device_id: deviceId, metric_key: key || null });
      return;
    }
    var did = parseInt((row.querySelector('.rule-cond-device') || {}).value, 10);
    var keyEl = row.querySelector('.rule-cond-key');
    var keyVal = keyEl ? String(keyEl.value || '').trim() : '';
    if (!did || !keyVal) return;
    items.push({
      type: 'metric',
      device_id: did,
      key: keyVal,
      op: (row.querySelector('.rule-cond-op') || {}).value || '>',
      value: (row.querySelector('.rule-cond-value') || {}).value
    });
  });
  return {
    match: (document.getElementById('rule-match') || {}).value === 'any' ? 'any' : 'all',
    items: items
  };
}

function collectRuleAction() {
  if (document.getElementById('rule-kind-seq') && document.getElementById('rule-kind-seq').checked) {
    return {
      kind: 'sequence',
      sequence_id: parseInt((document.getElementById('rule-sequence') || {}).value, 10) || 0
    };
  }
  var commands = [];
  document.querySelectorAll('#rule-cmds .rule-cmd').forEach(function (row) {
    var commandId = parseInt((row.querySelector('.rule-cmd-id') || {}).value, 10);
    if (!commandId) return;
    commands.push({
      command_id: commandId,
      action: (row.querySelector('.rule-cmd-action') || {}).value === 'off' ? 'off' : 'on'
    });
  });
  return { kind: 'command', commands: commands };
}

function openRule(row) {
  document.getElementById('rule-id').value = row && row.id ? row.id : '';
  document.getElementById('rule-name').value = row && row.name ? row.name : '';
  document.getElementById('rule-enabled').checked = !row || row.enabled === true || row.enabled === 't' || row.enabled === 1;
  var conds = row && row.conditions ? row.conditions : {};
  if (typeof conds === 'string') {
    try { conds = JSON.parse(conds); } catch (e) { conds = {}; }
  }
  document.getElementById('rule-match').value = conds && conds.match === 'any' ? 'any' : 'all';
  var wrap = document.getElementById('rule-conds');
  wrap.innerHTML = '';
  var items = conds && Array.isArray(conds.items) ? conds.items : [];
  if (!items.length) addRuleCond();
  else items.forEach(function (it) { addRuleCond(it); });

  var action = row && row.action ? row.action : {};
  if (typeof action === 'string') {
    try { action = JSON.parse(action); } catch (e) { action = {}; }
  }
  var kind = action && (action.kind || action.type) === 'sequence' ? 'sequence' : 'command';
  document.getElementById('rule-kind-seq').checked = kind === 'sequence';
  document.getElementById('rule-kind-cmd').checked = kind !== 'sequence';
  document.getElementById('rule-sequence').value = action && action.sequence_id ? String(action.sequence_id) : '';
  var cmdWrap = document.getElementById('rule-cmds');
  cmdWrap.innerHTML = '';
  var cmds = action && Array.isArray(action.commands) ? action.commands : [];
  if (!cmds.length) addRuleCmd();
  else cmds.forEach(function (c) { addRuleCmd(c); });
  toggleRuleAction();

  var cooldown = seqDurationParts(row && row.cooldown_sec != null ? row.cooldown_sec : 300, null);
  if (!row) cooldown = { value: 5, unit: 'min' };
  document.getElementById('rule-cooldown').value = cooldown.value === '' ? '0' : String(cooldown.value);
  document.getElementById('rule-cooldown-unit').value = cooldown.unit;
  document.getElementById('rule-cooldown-sec').value = String(seqToSeconds(cooldown.value, cooldown.unit));

  var modal = new bootstrap.Modal(document.getElementById('rule-modal'));
  modal.show();
}

var ruleForm = document.getElementById('rule-form');
if (ruleForm) {
  ruleForm.addEventListener('submit', function (ev) {
    var conditions = collectRuleConditions();
    if (!conditions.items.length) {
      ev.preventDefault();
      alert(L('control_need_cond'));
      return;
    }
    var action = collectRuleAction();
    if (action.kind === 'sequence' && !action.sequence_id) {
      ev.preventDefault();
      alert(L('control_need_seq'));
      return;
    }
    if (action.kind === 'command' && (!action.commands || !action.commands.length)) {
      ev.preventDefault();
      alert(L('control_need_cmd'));
      return;
    }
    document.getElementById('rule-conditions-json').value = JSON.stringify(conditions);
    document.getElementById('rule-action-json').value = JSON.stringify(action);
    var cooldownEl = document.getElementById('rule-cooldown');
    var unitEl = document.getElementById('rule-cooldown-unit');
    var secEl = document.getElementById('rule-cooldown-sec');
    if (secEl) {
      secEl.value = String(seqToSeconds(cooldownEl ? cooldownEl.value : 0, unitEl ? unitEl.value : 's'));
    }
  });
}

function openGroup(row) {
  document.getElementById('grp-id').value = row && row.id ? row.id : '';
  document.getElementById('grp-name').value = row && row.name ? row.name : '';
  document.getElementById('grp-kind').value = row && row.kind && ['building', 'room', 'system'].indexOf(row.kind) >= 0 ? row.kind : 'building';
  document.getElementById('grp-description').value = row && row.description ? row.description : '';
  document.getElementById('grp-delete-btn').style.display = (row && row.id && canDelete) ? '' : 'none';
  var modal = new bootstrap.Modal(document.getElementById('group-modal'));
  modal.show();
}

function deleteGroup() {
  var id = document.getElementById('grp-id').value;
  if (!id) return;
  if (!confirm(L('control_confirm_delete_group'))) return;
  document.getElementById('grp-delete-id').value = id;
  var nameEl = document.getElementById('grp-delete-name');
  if (nameEl) nameEl.value = document.getElementById('grp-name').value || '';
  document.getElementById('grp-delete-form').submit();
}

function ovAge(ts) {
  if (!ts) return '';
  var t = Date.parse(ts);
  if (!t) return String(ts);
  var sec = Math.round((Date.now() - t) / 1000);
  if (sec < 10) return L('control_just_now');
  if (sec < 60) return Lf('control_ago_s', sec);
  if (sec < 3600) return Lf('control_ago_m', Math.round(sec / 60));
  if (sec < 86400) return Lf('control_ago_h', Math.round(sec / 3600));
  return Lf('control_ago_d', Math.round(sec / 86400));
}

function sortOverviewGrid(items) {
  var grid = document.getElementById('ov-grid');
  if (!grid) return;
  var sorted = (items || []).slice().sort(function (a, b) {
    return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
  });
  sorted.forEach(function (item) {
    var card = grid.querySelector('[data-ov-id="' + item.id + '"]');
    var col = card ? card.closest('.col-md-6') : null;
    if (col) grid.appendChild(col);
  });
}

function applyOverview(data) {
  var sum = (data && data.summary) || {};
  ['on', 'off', 'mismatch', 'disabled'].forEach(function (k) {
    var el = document.getElementById('ov-count-' + k);
    if (el) el.textContent = sum[k] != null ? String(sum[k]) : '0';
  });
  var updated = document.getElementById('ov-updated');
  if (updated) updated.textContent = L('control_updated') + ' ' + new Date().toLocaleTimeString();
  (data.items || []).forEach(function (item) {
    var card = document.querySelector('[data-ov-id="' + item.id + '"]');
    if (!card) return;
    var tone = item.card_tone || 'mismatch';
    card.classList.remove('is-on', 'is-off', 'is-unknown', 'is-mismatch', 'is-disabled', 'is-stale');
    card.classList.add('is-' + tone);
    if (item.stale) card.classList.add('is-stale');

    var badge = card.querySelector('.ov-badge');
    if (badge) {
      badge.textContent = tone === 'on' || tone === 'off' ? L('control_ok') : (tone === 'mismatch' ? L('control_check') : (tone === 'disabled' ? L('control_off') : '—'));
    }

    var label = card.querySelector('.ov-state-label');
    if (label) {
      var text = item.state_label || '—';
      if (item.state === 'unknown' && item.value_display && item.value_display !== '—' && (text === 'Unknown' || text === '—')) {
        text = item.value_display;
      }
      label.textContent = text;
    }

    var cmdLine = card.querySelector('.ov-command');
    if (cmdLine) {
      var cmdText = L('control_command_dash');
      if (item.command_state === 'on' || item.command_state === 'off') {
        var parts = [L('control_command') + ' ' + (item.command_label || Laction(item.command_state))];
        var act = item.last_action || {};
        if (item.hold_source === 'manual') parts.push(L('control_manual'));
        else if (act.sequence_name) parts.push(act.sequence_name);
        else if (act.rule_name) parts.push(act.rule_name);
        else if (act.source && act.source !== 'manual') parts.push(act.source);
        if (act.result && act.result !== 'written') parts.push(act.result);
        if (act.at) parts.push(ovAge(act.at));
        cmdText = parts.join(' · ');
      }
      cmdLine.textContent = cmdText;
    }

    var meta = card.querySelector('.ov-meta');
    if (meta) {
      meta.classList.toggle('is-warn', !!item.stale);
      if (!item.timestamp) {
        meta.textContent = L('control_waiting_status');
      } else if (item.stale) {
        meta.textContent = L('control_no_new_data') + ' · ' + ovAge(item.timestamp);
      } else {
        meta.textContent = L('control_updated') + ' ' + ovAge(item.timestamp);
      }
    }

    var onBtn = card.querySelector('.ov-btn-on');
    var offBtn = card.querySelector('.ov-btn-off');
    var autoBtn = card.querySelector('.ov-btn-auto');
    if (onBtn) onBtn.classList.toggle('active', item.command_state === 'on');
    if (offBtn) offBtn.classList.toggle('active', item.command_state === 'off');
    if (autoBtn) autoBtn.classList.toggle('active', item.hold_source !== 'manual');
  });
  sortOverviewGrid(data.items || []);
  applyOverviewSequences(data.sequences || []);
  applyOverviewRules(data.rules || []);
  applyFlowLive(data);
}

function ovEscape(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/"/g, '&quot;');
}

function flowFindCmd(id) {
  id = Number(id) || 0;
  var list = (flowModel && flowModel.commands) || [];
  for (var i = 0; i < list.length; i++) {
    if (list[i].id === id) return list[i];
  }
  return null;
}

function flowCmdName(id) {
  var c = flowFindCmd(id);
  return c ? c.name : ('#' + id);
}

function flowSeqName(id) {
  id = Number(id) || 0;
  var list = (flowModel && flowModel.sequences) || [];
  for (var i = 0; i < list.length; i++) {
    if (list[i].id === id) return list[i].name;
  }
  return '#' + id;
}

function flowDeviceName(id) {
  id = Number(id) || 0;
  for (var i = 0; i < (ruleDevices || []).length; i++) {
    if (ruleDevices[i].id === id) return ruleDevices[i].name;
  }
  return Lf('control_device_n', id);
}

function flowArrow() {
  return '<div class="ov-flow-arrow" aria-hidden="true">→</div>';
}

function flowNode(opts) {
  var cls = ['fc-node'].concat(opts.cls || []);
  if (opts.disabled) cls.push('is-disabled');
  var attrs = '';
  if (opts.cmdId) attrs += ' data-flow-cmd="' + opts.cmdId + '"';
  if (opts.seqId) attrs += ' data-flow-seq="' + opts.seqId + '"';
  if (opts.ruleId) attrs += ' data-flow-rule="' + opts.ruleId + '"';
  return '<div class="' + cls.join(' ') + '"' + attrs + '>' +
    (opts.k ? '<div class="fc-k">' + ovEscape(opts.k) + '</div>' : '') +
    '<div class="fc-n">' + ovEscape(opts.n || '') + '</div>' +
    (opts.s ? '<div class="fc-s' + (opts.live ? ' fc-live' : '') + '">' + ovEscape(opts.s) + '</div>' : (opts.live ? '<div class="fc-s fc-live"></div>' : '')) +
    (opts.inHtml || '') +
  '</div>';
}

function flowCmdNode(id, action, extraCls) {
  var c = flowFindCmd(id);
  var bits = [];
  if (action) bits.push(Laction(action));
  if (c && c.device_name) bits.push(c.device_name);
  return flowNode({
    cls: ['is-cmd'].concat(extraCls || []),
    cmdId: id,
    disabled: c && !c.enabled,
    k: L('control_command_word'),
    n: c ? c.name : ('#' + id),
    s: bits.join(' · '),
    live: true
  });
}

function flowCondNode(item) {
  if (item.type === 'command_status') {
    return flowNode({
      cls: ['is-cond', 'is-cmd'],
      cmdId: item.command_id,
      k: L('control_command_word'),
      n: flowCmdName(item.command_id) + ' ' + L('control_is') + ' ' + Laction(item.state),
      live: true
    });
  }
  if (item.type === 'alarm') {
    return flowNode({
      cls: ['is-cond', 'is-rule'],
      k: L('control_alarm') + ' · ' + flowDeviceName(item.device_id),
      n: item.metric_key ? item.metric_key : L('control_any_alarm')
    });
  }
  if (item.type === 'metric') {
    return flowNode({
      cls: ['is-cond', 'is-rule'],
      k: L('control_metric') + ' · ' + flowDeviceName(item.device_id),
      n: (item.key || item.metric_key || '') + ' ' + (item.op || '>') + ' ' + (item.value || '')
    });
  }
  return flowNode({ cls: ['is-cond'], k: L('control_input'), n: L('control_no_condition') });
}

function flowCronLabel(cron) {
  var p = parseCron(cron);
  var names = Ldays();
  var dayText = (p.days && p.days.length === 7) ? L('control_every_day') : (p.days || []).map(function (d) { return names[d] || d; }).join(', ');
  return (p.time || '') + (dayText ? ' · ' + dayText : '');
}

function flowSeqSteps(seq, live) {
  return seqFlowDiagramHtml(seq, live);
}

function flowFindSeq(id) {
  id = Number(id) || 0;
  var list = (flowModel && flowModel.sequences) || [];
  for (var i = 0; i < list.length; i++) {
    if (list[i].id === id) return list[i];
  }
  return null;
}

function flowActionNode(id, action, extra) {
  extra = extra || {};
  var c = flowFindCmd(id);
  var on = action !== 'off';
  var cls = ['is-cmd', 'is-action', on ? 'is-on' : 'is-off'].concat(extra.cls || []);
  return flowNode({
    cls: cls,
    cmdId: id,
    seqId: extra.seqId,
    disabled: c && !c.enabled,
    k: extra.k || (on ? L('control_set_on') : L('control_set_off')),
    n: c ? c.name : ('#' + id),
    s: extra.s != null ? extra.s : (c && c.device_name ? c.device_name : '')
  });
}

function flowRuleThenHeader(rule) {
  if (rule.action_kind === 'sequence') {
    var seq = flowFindSeq(rule.sequence_id);
    return flowNode({
      cls: ['is-seq'],
      seqId: seq ? seq.id : rule.sequence_id,
      k: L('control_seq_word'),
      n: seq ? seq.name : flowSeqName(rule.sequence_id),
      s: seq ? Lf((seq.steps || []).length === 1 ? 'control_write_one' : 'control_write_many', (seq.steps || []).length) : ''
    });
  }
  var cmds = rule.commands || [];
  if (!cmds.length) return flowNode({ cls: ['is-cmd'], k: L('control_command_word'), n: L('control_no_action') });
  var parts = [];
  cmds.forEach(function (c) {
    parts.push(flowActionNode(c.command_id, c.action));
  });
  return '<div class="fc-io-stack">' + parts.join('') + '</div>';
}

function flowRulePipe(rule) {
  var conds = rule.conditions || [];
  var inputInner;
  if (!conds.length) {
    inputInner = flowNode({ cls: ['is-cond'], k: L('control_input'), n: L('control_no_conditions') });
  } else {
    var bits = [];
    conds.forEach(function (item, i) {
      if (i) bits.push('<div class="fc-join">' + (rule.match === 'any' ? L('control_or') : L('control_and')) + '</div>');
      bits.push(flowCondNode(item));
    });
    inputInner = bits.join('');
  }
  var any = rule.match === 'any';
  return '<div class="fc-pipe' + (rule.enabled ? '' : ' is-disabled') + '" data-flow-rule-pipe="' + rule.id + '">' +
    '<div class="fc-pipe-h"><strong>' + ovEscape(rule.name || L('control_pri_rule')) + '</strong>' +
      '<span class="small text-muted">' + (rule.enabled ? L('control_pri_rule') : L('control_rule_disabled')) + '</span></div>' +
    '<div class="fc-io">' +
      '<div class="fc-io-col is-in"><div class="fc-io-label">' + L('control_input') + '</div><div class="fc-io-stack">' + inputInner + '</div></div>' +
      '<div class="fc-io-arrow" aria-hidden="true">→</div>' +
      '<div class="fc-io-col is-if"><div class="fc-io-label">' + L('control_condition') + '</div>' +
        '<div class="fc-match" data-flow-rule="' + rule.id + '">' +
          '<div class="fc-match-val">' + (any ? L('control_any') : L('control_all')) + '</div>' +
          '<div class="fc-match-s">' + (any ? L('control_any_true') : L('control_all_true')) + '</div>' +
        '</div>' +
      '</div>' +
      '<div class="fc-io-arrow" aria-hidden="true">→</div>' +
      '<div class="fc-io-col is-out"><div class="fc-io-label">' + L('control_output') + '</div>' + flowRuleThenHeader(rule) + '</div>' +
    '</div></div>';
}

function flowSeqPipe(seq) {
  return '<div class="fc-pipe' + (seq.enabled ? '' : ' is-disabled') + '" data-flow-seq-pipe="' + seq.id + '">' +
    '<div class="fc-pipe-h"><strong>' + ovEscape(seq.name || L('control_seq_word')) + '</strong>' +
      '<span class="small text-muted">' + (seq.enabled ? L('control_seq_word') : L('control_seq_disabled')) + '</span></div>' +
    flowSeqSteps(seq) +
  '</div>';
}

function flowSchPipe(sch) {
  return '<div class="fc-pipe' + (sch.enabled ? '' : ' is-disabled') + '">' +
    '<div class="fc-pipe-h"><strong>' + ovEscape(sch.name || L('control_schedule')) + '</strong>' +
      '<span class="small text-muted">' + (sch.enabled ? L('control_schedule') : L('control_sch_disabled')) + '</span></div>' +
    '<div class="ov-flow">' +
      flowNode({ cls: ['is-sch'], k: L('control_when_cron'), n: flowCronLabel(sch.cron), s: sch.cron || '' }) +
      flowArrow() +
      flowActionNode(sch.command_id, sch.action) +
    '</div></div>';
}

function renderFlowMap() {
  var host = document.getElementById('flow-map');
  if (!host || !flowModel) return;
  var html = [];
  var rules = flowModel.rules || [];
  var seqs = flowModel.sequences || [];
  var schs = flowModel.schedules || [];
  if (!rules.length && !seqs.length && !schs.length) {
    host.innerHTML = '<div class="text-muted text-center py-4">' + L('control_no_chart') + '</div>';
    return;
  }
  if (rules.length) {
    html.push('<div class="fc-section-h">' + L('control_section_rules') + '</div>');
    rules.forEach(function (rule) { html.push(flowRulePipe(rule)); });
  }
  if (seqs.length) {
    if (html.length) html.push('<hr class="fc-split">');
    html.push('<div class="fc-section-h">' + L('control_section_sequences') + '</div>');
    seqs.forEach(function (seq) { html.push(flowSeqPipe(seq)); });
  }
  if (schs.length) {
    if (html.length) html.push('<hr class="fc-split">');
    html.push('<div class="fc-section-h">' + L('control_section_schedules') + '</div>');
    schs.forEach(function (sch) { html.push(flowSchPipe(sch)); });
  }
  host.innerHTML = html.join('');
}

function applyFlowLive(data) {
  if (!document.getElementById('flow-map')) return;
  var items = {};
  (data.items || []).forEach(function (it) { items[it.id] = it; });
  document.querySelectorAll('[data-flow-cmd]').forEach(function (el) {
    if (el.classList.contains('is-action') || el.closest('.fc-io-col.is-out')) return;
    var it = items[Number(el.getAttribute('data-flow-cmd'))];
    el.classList.remove('is-on', 'is-off', 'is-mismatch', 'is-unknown', 'is-manual');
    if (!it) return;
    var tone = it.card_tone || 'unknown';
    el.classList.add('is-' + tone);
    if (it.hold_source === 'manual') el.classList.add('is-manual');
    var live = el.querySelector('.fc-live');
    if (live) {
      var bits = [];
      if (it.state_label) bits.push(it.state_label);
      else if (it.command_state === 'on' || it.command_state === 'off') bits.push(Laction(it.command_state));
      if (it.hold_source === 'manual') bits.push(L('control_manual'));
      if (bits.length) live.textContent = bits.join(' · ');
    }
  });
  var seqLive = {};
  (data.sequences || []).forEach(function (s) { seqLive[s.id] = s; });
  document.querySelectorAll('[data-flow-seq-pipe]').forEach(function (el) {
    var id = Number(el.getAttribute('data-flow-seq-pipe'));
    var model = flowFindSeq(id);
    var s = seqLive[id];
    el.classList.remove('is-running', 'is-paused');
    var label = el.querySelector('.fc-pipe-h .small');
    var disabled = el.classList.contains('is-disabled');
    var live = (s && s.running) ? s : null;
    if (live) {
      el.classList.add(live.paused ? 'is-paused' : 'is-running');
      if (label) label.textContent = live.paused ? L('control_seq_paused') : L('control_seq_running_label');
    } else if (label) {
      label.textContent = disabled ? L('control_seq_disabled') : L('control_seq_word');
    }
    if (model) {
      var next = seqFlowDiagramHtml(model, live || {});
      var old = el.querySelector('.ov-flow');
      if (old) old.outerHTML = next;
    }
  });
  tickSequenceCountdowns();
  var ruleLive = {};
  (data.rules || []).forEach(function (r) { ruleLive[r.id] = r; });
  document.querySelectorAll('[data-flow-rule-pipe]').forEach(function (el) {
    var r = ruleLive[Number(el.getAttribute('data-flow-rule-pipe'))];
    el.classList.remove('is-latched', 'is-running');
    var label = el.querySelector('.fc-pipe-h .small');
    if (!r) {
      if (label && !el.classList.contains('is-disabled')) label.textContent = L('control_pri_rule');
      return;
    }
    var live = !!r.live || !!r.latched || !!r.matched;
    if (r.latched) el.classList.add('is-latched');
    else if (live) el.classList.add('is-running');
    if (label) {
      if (el.classList.contains('is-disabled')) { /* keep disabled label */ }
      else if (r.latched) label.textContent = L('control_rule_latched');
      else if (live) label.textContent = L('control_rule_matched');
      else label.textContent = L('control_pri_rule');
    }
  });
  var updated = document.getElementById('flow-updated');
  if (updated) updated.textContent = L('control_updated') + ' ' + new Date().toLocaleTimeString();
}

function ovDelayLabel(sec) {
  sec = parseInt(sec, 10) || 0;
  if (sec >= 3600 && sec % 3600 === 0) return (sec / 3600) + 'h';
  if (sec >= 60 && sec % 60 === 0) return (sec / 60) + 'm';
  if (sec >= 60) return Math.round(sec / 60) + 'm';
  return sec + 's';
}

function seqCountdownLabel(msLeft) {
  var sec = Math.max(0, Math.ceil(msLeft / 1000));
  var h = Math.floor(sec / 3600);
  var m = Math.floor((sec % 3600) / 60);
  var s = sec % 60;
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  if (h > 0) return h + ':' + pad(m) + ':' + pad(s);
  return m + ':' + pad(s);
}

function seqDeadlineFromProgress(p) {
  if (!p) return 0;
  if (p.remaining_sec != null && p.remaining_sec !== '') {
    var rem = Number(p.remaining_sec);
    if (isFinite(rem) && rem >= 0) return Date.now() + rem * 1000;
  }
  return Number(p.deadline_at) || 0;
}

function seqFlowDiagramHtml(seq, live) {
  seq = seq || {};
  live = live || {};
  var p = live.progress || seq.progress || {};
  var cur = Number(p.step_index) || 0;
  var phase = p.phase || '';
  var paused = !!live.paused || !!seq.paused || phase === 'paused';
  var running = !!live.running || !!seq.running;
  var deadline = (running && !paused && (phase === 'wait' || phase === 'delay' || phase === 'loop_delay'))
    ? seqDeadlineFromProgress(p)
    : 0;
  var nodes = [];
  (seq.steps || []).forEach(function (step, i) {
    var idx = Number(step.index) || (i + 1);
    var state = 'pending';
    if (running) {
      if (cur > idx) state = 'done';
      else if (cur === idx && phase === 'loop_delay') state = 'done';
      else if (cur === idx && phase === 'held') state = 'held';
      else if (cur === idx && paused) state = 'held';
      else if (cur === idx || (phase === 'starting' && idx === 1)) state = 'current';
    }
    var bits = [Laction(step.action)];
    if (step.wait === 'on' || step.wait === 'off') bits.push(L('control_wait') + ' ' + Laction(step.wait));
    if (step.delay_sec > 0) bits.push(L('control_delay') + ' ' + ovDelayLabel(step.delay_sec));
    var name = step.command_name || flowCmdName(step.command_id);
    var nodeCount = (state === 'current' && deadline && (phase === 'wait' || phase === 'delay'))
      ? '<div class="ov-countdown" data-deadline="' + deadline + '"></div>'
      : '';
    nodes.push(
      '<div class="ov-flow-node is-' + state + '">' +
        '<div class="ov-flow-idx">' + L('control_step') + ' ' + idx + '</div>' +
        '<div class="ov-flow-name">' + ovEscape(name) + '</div>' +
        '<div class="ov-flow-sub">' + ovEscape(bits.join(' · ')) + '</div>' +
        nodeCount +
      '</div>'
    );
  });
  if (seq.loop || seq.loop_enabled) {
    var loopState = running && phase === 'loop_delay' ? 'current' : 'pending';
    var loopCount = (loopState === 'current' && deadline)
      ? '<div class="ov-countdown" data-deadline="' + deadline + '"></div>'
      : '';
    nodes.push(
      '<div class="ov-flow-node ov-flow-loop is-' + loopState + '">' +
        '<div class="ov-flow-idx">↻</div>' +
        '<div class="ov-flow-name">' + L('control_loop') + '</div>' +
        '<div class="ov-flow-sub">' + L('control_loop_back') + '</div>' +
        loopCount +
      '</div>'
    );
  }
  if (!nodes.length) return '<div class="text-muted small">' + L('control_no_steps') + '</div>';
  var flow = [];
  nodes.forEach(function (html, i) {
    if (i) flow.push('<div class="ov-flow-arrow" aria-hidden="true">→</div>');
    flow.push(html);
  });
  return '<div class="ov-flow">' + flow.join('') + '</div>';
}

function tickSequenceCountdowns() {
  document.querySelectorAll('[data-deadline]').forEach(function (el) {
    var t = parseInt(el.getAttribute('data-deadline'), 10);
    if (!t) {
      el.textContent = '';
      return;
    }
    el.textContent = seqCountdownLabel(t - Date.now());
  });
}

function seqProgressText(seq) {
  var p = seq.progress || {};
  var phase = p.phase || '';
  var cycle = p.cycle ? (L('control_cycle') + ' ' + p.cycle) : L('control_running');
  if (phase === 'stopping') return L('control_stopping');
  if (phase === 'paused' || seq.paused) return cycle + ' · ' + L('control_paused') + (p.command_name ? ' · ' + p.command_name : '');
  if (phase === 'held') return cycle + ' · ' + L('control_held_by') + ' ' + (p.blocked_by || L('control_pri_rule')) + (p.command_name ? ' · ' + p.command_name + ' ' + Laction(p.action) : '');
  if (phase === 'loop_delay') return cycle + ' · ' + L('control_looping');
  if (phase === 'wait') return cycle + ' · ' + L('control_waiting_for') + ' ' + Laction(p.wait || p.action) + (p.command_name ? ' · ' + p.command_name : '');
  if (phase === 'delay') return cycle + ' · ' + L('control_delay_after') + ' ' + (p.command_name || L('control_step'));
  if (phase === 'write') return cycle + ' · ' + L('control_writing') + ' ' + Laction(p.action) + (p.command_name ? ' · ' + p.command_name : '');
  if (phase === 'starting') return L('control_starting');
  return cycle;
}

function applyOverviewSequences(list) {
  var host = document.getElementById('ov-sequences');
  if (!host) return;
  var running = (list || []).filter(function (s) { return s && s.running; });
  if (!running.length) {
    host.innerHTML = '';
    host.style.display = 'none';
    return;
  }
  host.style.display = 'flex';
  host.innerHTML = running.map(function (seq) {
    var p = seq.progress || {};
    var phase = p.phase || '';
    var paused = !!seq.paused || phase === 'paused';
    var deadline = (!paused && (phase === 'wait' || phase === 'delay' || phase === 'loop_delay')) ? seqDeadlineFromProgress(p) : 0;
    var countHtml = deadline
      ? ' <span class="ov-countdown ov-countdown-inline" data-deadline="' + deadline + '"></span>'
      : '';
    var stopArg = JSON.stringify(seq.name || 'sequence');
    var cardClass = 'card ov-seq-card';
    if (paused) cardClass += ' is-paused';
    else if (phase === 'held') cardClass += ' is-held';
    var actions = '';
    if (canUpdate) {
      actions = paused
        ? '<button type="button" class="btn btn-outline-success btn-sm flex-shrink-0" onclick=\'resumeSequenceLive(' + Number(seq.id) + ', ' + stopArg + ')\'>' + L('control_resume') + '</button>' +
          '<button type="button" class="btn btn-outline-warning btn-sm flex-shrink-0" onclick=\'stopSequenceLive(' + Number(seq.id) + ', ' + stopArg + ')\'>' + L('control_stop') + '</button>'
        : '<button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0" onclick=\'pauseSequenceLive(' + Number(seq.id) + ', ' + stopArg + ')\'>' + L('control_pause') + '</button>' +
          '<button type="button" class="btn btn-outline-warning btn-sm flex-shrink-0" onclick=\'stopSequenceLive(' + Number(seq.id) + ', ' + stopArg + ')\'>' + L('control_stop') + '</button>';
    }
    return '<div class="' + cardClass + '">' +
      '<div class="card-body">' +
        '<div class="d-flex justify-content-between align-items-start gap-2 mb-3">' +
          '<div class="min-w-0">' +
            '<h5 class="mb-1 text-truncate">' + ovEscape(seq.name || L('control_seq_word')) + '</h5>' +
            '<div class="small text-muted">' + ovEscape(seqProgressText(seq)) + countHtml + '</div>' +
          '</div>' +
          '<div class="d-flex gap-2 flex-shrink-0">' + actions + '</div>' +
        '</div>' +
        seqFlowDiagramHtml(seq) +
      '</div></div>';
  }).join('');
  tickSequenceCountdowns();
}

function applyOverviewRules(list) {
  var host = document.getElementById('ov-rules');
  if (!host) return;
  var rules = (list || []).slice(0, 5);
  if (!rules.length) {
    host.innerHTML = '';
    host.style.display = 'none';
    return;
  }
  host.style.display = 'flex';
  var cards = rules.map(function (rule) {
    var live = !!rule.live || !!rule.latched || !!rule.matched;
    var rawStatus = rule.status || (live ? 'Active' : 'Last active');
    var tone = (!live || rawStatus === 'Last active') ? ' is-last' : (rawStatus === 'Cooldown' ? ' is-cooldown' : '');
    var statusMap = { Active: L('control_active'), 'Last active': L('control_last_active'), Cooldown: L('control_cooldown') };
    var status = statusMap[rawStatus] || rawStatus;
    var meta = [];
    var triggeredAt = rule.last_triggered_at || rule.last_fired_at;
    if (triggeredAt) meta.push(L('control_triggered_ago') + ' ' + ovAge(triggeredAt));
    if (rule.last_result && rule.last_result !== 'triggered' && rule.last_result !== 'fired') meta.push(rule.last_result);
    if (rule.cooldown_remaining_sec > 0 && live) meta.push(L('control_cooldown') + ' ' + ovDelayLabel(rule.cooldown_remaining_sec));
    if (rule.skipped) meta.push(rule.skipped);
    return '<div class="card ov-rule-card' + tone + '">' +
      '<div class="card-body py-3">' +
        '<div class="d-flex justify-content-between align-items-start gap-2 mb-1">' +
          '<div class="min-w-0">' +
            '<h5 class="mb-1 text-truncate">' + ovEscape(rule.name || L('control_pri_rule')) + '</h5>' +
            (meta.length ? '<div class="small text-muted">' + ovEscape(meta.join(' · ')) + '</div>' : '') +
          '</div>' +
          '<span class="ov-rule-badge flex-shrink-0">' + ovEscape(status) + '</span>' +
        '</div>' +
        '<div class="small"><span class="text-muted">' + L('control_when_label') + '</span> ' + ovEscape(rule.when || '—') + '</div>' +
        '<div class="small mt-1"><span class="text-muted">' + L('control_then_label') + '</span> ' + ovEscape(rule.then || '—') + '</div>' +
      '</div></div>';
  }).join('');
  host.innerHTML =
    '<div class="ov-rule-kicker">' + L('control_last_rule_active') + '</div>' +
    cards;
}

function loadOverview() {
  if (!overviewUrl) return;
  fetch(overviewUrl, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data && data.ok) applyOverview(data);
    })
    .catch(function () {});
}

function startOverviewPoll() {
  loadOverview();
  if (overviewTimer) clearInterval(overviewTimer);
  overviewTimer = setInterval(loadOverview, 5000);
  if (overviewCountdownTimer) clearInterval(overviewCountdownTimer);
  overviewCountdownTimer = setInterval(tickSequenceCountdowns, 250);
}

function stopOverviewPoll() {
  if (overviewTimer) {
    clearInterval(overviewTimer);
    overviewTimer = null;
  }
  if (overviewCountdownTimer) {
    clearInterval(overviewCountdownTimer);
    overviewCountdownTimer = null;
  }
}

function overviewExecute(id, value, name) {
  if (!canUpdate) return;
  var action = String(value) === '0' ? 'OFF' : 'ON';
  if (!confirm(Lf(String(value) === '0' ? 'control_send_off' : 'control_send_on', name))) return;
  var body = 'id=' + encodeURIComponent(id) + '&value=' + encodeURIComponent(value);
  if (name) body += '&name=' + encodeURIComponent(name);
  if (currentGroupId != null && currentGroupId !== '') body += '&group_id=' + encodeURIComponent(currentGroupId);
  fetch(executeUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.ok) {
        alert((data && data.error) ? data.error : L('control_execute_failed'));
      }
      loadOverview();
    })
    .catch(function () {
      alert(L('control_execute_failed'));
    });
}

function overviewAuto(id, name) {
  if (!canUpdate) return;
  if (!confirm(Lf('control_return_auto', name))) return;
  var body = 'id=' + encodeURIComponent(id) + '&value=auto';
  if (name) body += '&name=' + encodeURIComponent(name);
  if (currentGroupId != null && currentGroupId !== '') body += '&group_id=' + encodeURIComponent(currentGroupId);
  fetch(executeUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: body
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.ok) {
        alert((data && data.error) ? data.error : L('control_auto_failed'));
      }
      loadOverview();
    })
    .catch(function () {
      alert(L('control_auto_failed'));
    });
}

function initControlDataTables() {
  if (!window.jQuery || !$.fn || typeof $.fn.DataTable !== 'function') return;
  var lang = {
    search: L('control_dt_search'),
    lengthMenu: L('control_dt_length'),
    info: L('control_dt_info'),
    infoEmpty: L('control_dt_info_empty'),
    infoFiltered: L('control_dt_info_filtered'),
    emptyTable: L('control_dt_empty'),
    zeroRecords: L('control_dt_zero'),
    paginate: {
      first: L('control_dt_first'),
      last: L('control_dt_last'),
      next: L('control_dt_next'),
      previous: L('control_dt_previous')
    }
  };
  function opts(emptyKey, extra) {
    return $.extend(true, {
      responsive: true,
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100],
      language: $.extend({}, lang, { emptyTable: L(emptyKey) })
    }, extra || {});
  }
  [
    ['#ctrl-commands-table', 'control_no_commands', { order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }] }],
    ['#ctrl-schedules-table', 'control_no_schedules', { order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }] }],
    ['#ctrl-sequences-table', 'control_no_sequences', { order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }] }],
    ['#ctrl-rules-table', 'control_no_rules', { order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: -1 }] }],
    ['#ctrl-logs-table', 'control_no_logs', { order: [[0, 'desc']] }]
  ].forEach(function (item) {
    var sel = item[0];
    if (!$(sel).length || $.fn.DataTable.isDataTable(sel)) return;
    $(sel).DataTable(opts(item[1], item[2]));
  });
}

function adjustControlDataTables() {
  if (!window.jQuery || !$.fn || !$.fn.DataTable) return;
  $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
}

if (document.getElementById('tab-overview')) {
  function controlActiveTab() {
    var btn = document.querySelector('.nav-tabs .nav-link.active');
    var t = btn && btn.getAttribute('data-bs-target');
    if (!t || t.indexOf('#tab-') !== 0) return '';
    return t.slice(5);
  }
  function controlRememberTab(tab) {
    var url = new URL(location.href);
    if (tab && tab !== 'overview') url.searchParams.set('tab', tab);
    else url.searchParams.delete('tab');
    history.replaceState({}, '', url);
  }
  renderFlowMap();
  var initialTab = controlActiveTab() || 'overview';
  if (initialTab === 'overview' || initialTab === 'flow') startOverviewPoll();
  document.querySelectorAll('form[method="post"]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var tab = controlActiveTab();
      if (!tab) return;
      var existing = form.querySelector('input[name="tab"]');
      if (existing) {
        existing.value = tab;
        return;
      }
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'tab';
      inp.value = tab;
      form.appendChild(inp);
    });
  });
  document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
    btn.addEventListener('shown.bs.tab', function (ev) {
      var target = ev.target && ev.target.getAttribute('data-bs-target');
      var tab = (target || '').replace('#tab-', '');
      controlRememberTab(tab);
      if (target === '#tab-overview' || target === '#tab-flow') startOverviewPoll();
      else stopOverviewPoll();
      adjustControlDataTables();
    });
  });
}

$(function () {
  initControlDataTables();
  setTimeout(adjustControlDataTables, 0);
});
</script>
