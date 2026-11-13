<?php
$cfg = $widget->config ?? (object)[];
$deviceId = $cfg->device_id ?? '';
$label = $cfg->label ?? 'Cooling';
$keys = [
    'connection' => $cfg->connection_key ?? 'Connection',
    'running' => $cfg->running_key ?? 'Running Status',
    'alarm' => $cfg->alarm_key ?? 'General Alarm',
    'supply' => $cfg->supply_temp_key ?? 'Evaporator Leaving Water Temperature',
    'return' => $cfg->return_temp_key ?? 'Evaporator Entering Water Temperature',
    'setpoint' => $cfg->setpoint_key ?? 'Active Cool/Heat Setpoint Temperature',
];
$widgetDomId = 'cooling-panel-' . $widget_id;
$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%; overflow: hidden;');
?>
<div id="<?= htmlspecialchars($widgetDomId) ?>"
     class="widget-cooling-panel <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-id="<?= htmlspecialchars($deviceId) ?>">
  <div class="cooling-panel-header d-flex justify-content-between align-items-center mb-2">
    <div class="cooling-panel-title fw-semibold small"><?= htmlspecialchars($label) ?></div>
    <div class="cooling-connection badge text-bg-secondary cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['connection']) ?>"
         data-device-id="<?= htmlspecialchars($deviceId) ?>"
         data-metric-key="<?= htmlspecialchars($keys['connection']) ?>">
      <span class="value">--</span>
    </div>
  </div>

  <div class="row g-2 mb-2">
    <div class="col-6">
      <div class="cooling-stat border rounded p-2 h-100">
        <div class="small text-secondary">Running</div>
        <div class="fw-semibold cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['running']) ?>"
             data-device-id="<?= htmlspecialchars($deviceId) ?>"
             data-metric-key="<?= htmlspecialchars($keys['running']) ?>"
             data-cooling-kind="status"
             data-state-on="<?= htmlspecialchars($cfg->running_on ?? 'Running') ?>"
             data-state-off="<?= htmlspecialchars($cfg->running_off ?? 'Stopped') ?>"
             data-on-class="text-success"
             data-off-class="text-secondary">
          <span class="value">--</span>
        </div>
      </div>
    </div>
    <div class="col-6">
      <div class="cooling-stat border rounded p-2 h-100">
        <div class="small text-secondary">Alarm</div>
        <div class="fw-semibold cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['alarm']) ?>"
             data-device-id="<?= htmlspecialchars($deviceId) ?>"
             data-metric-key="<?= htmlspecialchars($keys['alarm']) ?>"
             data-cooling-kind="status"
             data-state-on="<?= htmlspecialchars($cfg->alarm_on ?? 'Alarm') ?>"
             data-state-off="<?= htmlspecialchars($cfg->alarm_off ?? 'Normal') ?>"
             data-on-class="text-danger"
             data-off-class="text-success">
          <span class="value">--</span>
        </div>
      </div>
    </div>
  </div>

  <div class="cooling-temps mt-auto">
    <div class="d-flex justify-content-between small mb-1">
      <span class="text-secondary">Supply</span>
      <span class="cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['supply']) ?>"
            data-device-id="<?= htmlspecialchars($deviceId) ?>"
            data-metric-key="<?= htmlspecialchars($keys['supply']) ?>">
        <span class="value">--</span> °C
      </span>
    </div>
    <div class="d-flex justify-content-between small mb-1">
      <span class="text-secondary">Return</span>
      <span class="cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['return']) ?>"
            data-device-id="<?= htmlspecialchars($deviceId) ?>"
            data-metric-key="<?= htmlspecialchars($keys['return']) ?>">
        <span class="value">--</span> °C
      </span>
    </div>
    <div class="d-flex justify-content-between small">
      <span class="text-secondary">Setpoint</span>
      <span class="cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['setpoint']) ?>"
            data-device-id="<?= htmlspecialchars($deviceId) ?>"
            data-metric-key="<?= htmlspecialchars($keys['setpoint']) ?>">
        <span class="value">--</span> °C
      </span>
    </div>
  </div>
</div>

<style>
.widget-cooling-panel .cooling-stat .value {
  font-variant-numeric: tabular-nums;
}
</style>
