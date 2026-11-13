<?php
$cfg = $widget->config ?? (object)[];
$deviceId = $cfg->device_id ?? '';
$label = $cfg->label ?? 'Power Meter';
$keys = [
    'voltage_r' => $cfg->voltage_r_key ?? 'Voltage R',
    'voltage_s' => $cfg->voltage_s_key ?? 'Voltage S',
    'voltage_t' => $cfg->voltage_t_key ?? 'Voltage T',
    'current_r' => $cfg->current_r_key ?? 'Current R',
    'current_s' => $cfg->current_s_key ?? 'Current S',
    'current_t' => $cfg->current_t_key ?? 'Current T',
    'power' => $cfg->power_key ?? 'Active Power Total',
    'connection' => $cfg->connection_key ?? 'Connection',
];
$widgetDomId = 'power-meter-' . $widget_id;
$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%; overflow: hidden;');
?>
<div id="<?= htmlspecialchars($widgetDomId) ?>"
     class="widget-power-meter <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-id="<?= htmlspecialchars($deviceId) ?>">
  <div class="power-meter-header d-flex justify-content-between align-items-center mb-2">
    <div class="power-meter-title fw-semibold small"><?= htmlspecialchars($label) ?></div>
    <div class="power-meter-connection badge text-bg-secondary cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['connection']) ?>"
         data-device-id="<?= htmlspecialchars($deviceId) ?>"
         data-metric-key="<?= htmlspecialchars($keys['connection']) ?>">
      <span class="value">--</span>
    </div>
  </div>

  <div class="power-meter-total text-center mb-2 py-1 rounded bg-body-secondary">
    <div class="small text-secondary">Active Power</div>
    <div class="fs-5 fw-bold cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['power']) ?>"
         data-device-id="<?= htmlspecialchars($deviceId) ?>"
         data-metric-key="<?= htmlspecialchars($keys['power']) ?>">
      <span class="value">--</span> <span class="unit small">kW</span>
    </div>
  </div>

  <div class="power-meter-grid flex-grow-1">
    <div class="row g-2 small fw-semibold text-secondary mb-1">
      <div class="col-3">Phase</div>
      <div class="col-4 text-end">Voltage</div>
      <div class="col-5 text-end">Current</div>
    </div>
    <?php foreach (['r' => 'R', 's' => 'S', 't' => 'T'] as $phase => $phaseLabel):
      $vKey = $keys['voltage_' . $phase];
      $cKey = $keys['current_' . $phase];
    ?>
      <div class="row g-2 small align-items-center mb-1 power-meter-row">
        <div class="col-3 text-secondary"><?= $phaseLabel ?></div>
        <div class="col-4 text-end cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($vKey) ?>"
             data-device-id="<?= htmlspecialchars($deviceId) ?>"
             data-metric-key="<?= htmlspecialchars($vKey) ?>">
          <span class="value">--</span> <span class="unit">V</span>
        </div>
        <div class="col-5 text-end cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($cKey) ?>"
             data-device-id="<?= htmlspecialchars($deviceId) ?>"
             data-metric-key="<?= htmlspecialchars($cKey) ?>">
          <span class="value">--</span> <span class="unit">A</span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<style>
.widget-power-meter {
  box-sizing: border-box;
}

.widget-power-meter .value {
  font-variant-numeric: tabular-nums;
}
</style>
