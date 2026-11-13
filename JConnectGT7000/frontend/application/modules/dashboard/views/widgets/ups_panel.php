<?php
$cfg = $widget->config ?? (object)[];
$deviceId = $cfg->device_id ?? '';
$label = $cfg->label ?? 'UPS';
$keys = [
    'capacity' => $cfg->capacity_key ?? 'Batterry Capacity',
    'load_r' => $cfg->load_r_key ?? 'Output Percent Load R',
    'load_s' => $cfg->load_s_key ?? 'Output Percent Load S',
    'load_t' => $cfg->load_t_key ?? 'Output Percent Load T',
    'mode' => $cfg->mode_key ?? 'UPS Output Mode',
    'runtime' => $cfg->runtime_key ?? 'Runtime Remaining',
    'voltage' => $cfg->voltage_key ?? 'Batterry Voltage',
    'connection' => $cfg->connection_key ?? 'Connection',
];
$widgetDomId = 'ups-panel-' . $widget_id;
$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%; overflow: hidden;');
?>
<div id="<?= htmlspecialchars($widgetDomId) ?>"
     class="widget-ups-panel <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-id="<?= htmlspecialchars($deviceId) ?>">
  <div class="ups-panel-header d-flex justify-content-between align-items-center mb-2">
    <div class="ups-panel-title fw-semibold small"><?= htmlspecialchars($label) ?></div>
    <div class="ups-connection badge text-bg-secondary cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['connection']) ?>"
         data-device-id="<?= htmlspecialchars($deviceId) ?>"
         data-metric-key="<?= htmlspecialchars($keys['connection']) ?>">
      <span class="value">--</span>
    </div>
  </div>

  <div class="ups-panel-section mb-2">
    <div class="d-flex justify-content-between small text-secondary mb-1">
      <span>Battery</span>
      <span class="cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['capacity']) ?>"
            data-device-id="<?= htmlspecialchars($deviceId) ?>"
            data-metric-key="<?= htmlspecialchars($keys['capacity']) ?>"
            data-ups-bar="capacity">
        <span class="value">--</span><span class="unit">%</span>
      </span>
    </div>
    <div class="progress ups-progress" style="height: 0.55rem;">
      <div class="progress-bar bg-success ups-progress-bar" data-ups-bar-target="capacity" style="width: 0%;"></div>
    </div>
    <div class="small text-muted mt-1">
      Voltage:
      <span class="cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['voltage']) ?>"
            data-device-id="<?= htmlspecialchars($deviceId) ?>"
            data-metric-key="<?= htmlspecialchars($keys['voltage']) ?>">
        <span class="value">--</span> V
      </span>
    </div>
  </div>

  <div class="ups-panel-section mb-2">
    <div class="small text-secondary mb-1">Load</div>
    <?php foreach (['r' => 'R', 's' => 'S', 't' => 'T'] as $phase => $labelPhase):
      $metricKey = $keys['load_' . $phase];
    ?>
      <div class="d-flex justify-content-between align-items-center small mb-1">
        <span>Phase <?= $labelPhase ?></span>
        <span class="cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($metricKey) ?>"
              data-device-id="<?= htmlspecialchars($deviceId) ?>"
              data-metric-key="<?= htmlspecialchars($metricKey) ?>"
              data-ups-bar="load_<?= $phase ?>">
          <span class="value">--</span><span class="unit">%</span>
        </span>
      </div>
      <div class="progress ups-progress mb-1" style="height: 0.45rem;">
        <div class="progress-bar bg-primary ups-progress-bar" data-ups-bar-target="load_<?= $phase ?>" style="width: 0%;"></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="ups-panel-footer mt-auto d-flex justify-content-between small">
    <div>
      Mode:
      <span class="fw-semibold cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['mode']) ?>"
            data-device-id="<?= htmlspecialchars($deviceId) ?>"
            data-metric-key="<?= htmlspecialchars($keys['mode']) ?>">
        <span class="value">--</span>
      </span>
    </div>
    <div>
      Runtime:
      <span class="fw-semibold cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($keys['runtime']) ?>"
            data-device-id="<?= htmlspecialchars($deviceId) ?>"
            data-metric-key="<?= htmlspecialchars($keys['runtime']) ?>">
        <span class="value">--</span>
      </span>
    </div>
  </div>
</div>

<style>
.widget-ups-panel {
  box-sizing: border-box;
}

.widget-ups-panel .ups-progress {
  background: var(--bs-secondary-bg);
}

.widget-ups-panel .value {
  font-variant-numeric: tabular-nums;
}
</style>
