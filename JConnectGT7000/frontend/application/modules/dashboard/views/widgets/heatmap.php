<?php
$cfg = $widget->config ?? (object)[];
$items = $cfg->items ?? [];
if (is_string($items)) {
    $decoded = json_decode($items);
    $items = is_array($decoded) ? $decoded : [];
}
$label = $cfg->label ?? '';
$columns = max(1, min(8, (int)($cfg->columns ?? 4)));
$warnAt = is_numeric($cfg->warn_threshold ?? null) ? (float)$cfg->warn_threshold : 25;
$alarmAt = is_numeric($cfg->alarm_threshold ?? null) ? (float)$cfg->alarm_threshold : 30;
$unit = $cfg->unit ?? '°C';
$widgetDomId = 'heatmap-' . $widget_id;
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%; overflow: hidden;');
?>
<div id="<?= htmlspecialchars($widgetDomId) ?>"
     class="widget-heatmap <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-warn-threshold="<?= htmlspecialchars($warnAt) ?>"
     data-alarm-threshold="<?= htmlspecialchars($alarmAt) ?>"
     data-unit="<?= htmlspecialchars($unit) ?>">
  <?php if ($label !== ''): ?>
    <div class="heatmap-title small text-secondary mb-2"><?= htmlspecialchars($label) ?></div>
  <?php endif; ?>
  <div class="heatmap-body flex-grow-1" style="--heatmap-cols: <?= $columns ?>;">
    <?php if (empty($items)): ?>
      <div class="text-muted small text-center py-3">No sensors configured</div>
    <?php else: ?>
      <?php foreach ($items as $item):
        $itemObj = is_array($item) ? (object)$item : $item;
        $deviceId = $itemObj->device_id ?? '';
        $metricKey = $itemObj->metric_key ?? 'Temperature';
        $itemLabel = $itemObj->label ?? $metricKey;
      ?>
        <div class="heatmap-cell cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($metricKey) ?>"
             data-device-id="<?= htmlspecialchars($deviceId) ?>"
             data-metric-key="<?= htmlspecialchars($metricKey) ?>">
          <div class="heatmap-value"><span class="value">--</span></div>
          <div class="heatmap-label"><?= htmlspecialchars($itemLabel) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<style>
.widget-heatmap .heatmap-body {
  display: grid;
  grid-template-columns: repeat(var(--heatmap-cols, 4), minmax(0, 1fr));
  gap: 0.5rem;
  align-content: start;
}

.widget-heatmap .heatmap-cell {
  border-radius: 0.5rem;
  padding: 0.55rem 0.35rem;
  text-align: center;
  background: rgba(34, 197, 94, 0.18);
  border: 1px solid rgba(34, 197, 94, 0.35);
  min-height: 3.5rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.15rem;
}

.widget-heatmap .heatmap-cell.level-warn {
  background: rgba(234, 179, 8, 0.22);
  border-color: rgba(234, 179, 8, 0.45);
}

.widget-heatmap .heatmap-cell.level-alarm {
  background: rgba(239, 68, 68, 0.22);
  border-color: rgba(239, 68, 68, 0.5);
}

.widget-heatmap .heatmap-value {
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.widget-heatmap .heatmap-label {
  font-size: 0.68rem;
  color: var(--bs-secondary-color);
  line-height: 1.15;
  word-break: break-word;
}
</style>
