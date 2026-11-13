<?php
$cfg = $widget->config ?? (object)[];
$label = $cfg->label ?? 'Active Alarms';
$showTotal = ($cfg->show_total ?? '1') !== '0';
$deviceFilter = $cfg->device_filter ?? [];
if (is_string($deviceFilter)) {
    $decoded = json_decode($deviceFilter, true);
    $deviceFilter = is_array($decoded) ? $decoded : [];
}
$filterJson = htmlspecialchars(json_encode(array_values($deviceFilter)), ENT_QUOTES, 'UTF-8');
$widgetDomId = 'alarm-summary-' . $widget_id;
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%;');
?>
<div id="<?= htmlspecialchars($widgetDomId) ?>"
     class="widget-alarm-summary <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-filter="<?= $filterJson ?>">
  <div class="alarm-summary-title small text-secondary mb-2"><?= htmlspecialchars($label) ?></div>
  <div class="alarm-summary-body flex-grow-1 d-flex flex-column justify-content-center gap-2">
    <?php if ($showTotal): ?>
      <div class="alarm-summary-total d-flex align-items-center justify-content-between px-1">
        <span class="small">Total</span>
        <span class="alarm-summary-count badge rounded-pill bg-secondary" data-severity="total">0</span>
      </div>
    <?php endif; ?>
    <div class="alarm-summary-row d-flex align-items-center justify-content-between px-1">
      <span class="small"><span class="alarm-dot alarm-dot-critical"></span> Critical</span>
      <span class="alarm-summary-count badge rounded-pill bg-danger" data-severity="critical">0</span>
    </div>
    <div class="alarm-summary-row d-flex align-items-center justify-content-between px-1">
      <span class="small"><span class="alarm-dot alarm-dot-warning"></span> Warning</span>
      <span class="alarm-summary-count badge rounded-pill bg-warning text-dark" data-severity="warning">0</span>
    </div>
    <div class="alarm-summary-row d-flex align-items-center justify-content-between px-1">
      <span class="small"><span class="alarm-dot alarm-dot-info"></span> Info</span>
      <span class="alarm-summary-count badge rounded-pill bg-info text-dark" data-severity="info">0</span>
    </div>
  </div>
</div>

<style>
.widget-alarm-summary .alarm-dot {
  display: inline-block;
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  margin-right: 0.35rem;
  vertical-align: middle;
}

.widget-alarm-summary .alarm-dot-critical { background: #dc3545; }
.widget-alarm-summary .alarm-dot-warning { background: #ffc107; }
.widget-alarm-summary .alarm-dot-info { background: #0dcaf0; }

.widget-alarm-summary .alarm-summary-count {
  min-width: 1.75rem;
  font-size: 0.8rem;
}
</style>
