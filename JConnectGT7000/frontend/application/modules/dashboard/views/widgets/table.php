<?php
$cfg = $widget->config ?? (object)[];
$items = $cfg->items ?? [];
if (is_string($items)) {
    $decoded = json_decode($items);
    $items = is_array($decoded) ? $decoded : [];
}
$tableId = 'table_' . $widget_id;
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%; overflow: hidden;');
?>
<div class="widget-table <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column" style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>">
  <?php if (!empty($cfg->label)): ?>
    <div class="small text-muted mb-1"><?= htmlspecialchars($cfg->label) ?></div>
  <?php endif; ?>
  <div class="flex-grow-1" style="overflow-y: auto;">
    <table class="table table-sm table-hover align-middle mb-0" id="<?= $tableId ?>">
      <thead>
        <tr class="table-light">
          <th scope="col">Label</th>
          <th scope="col">Value</th>
          <th scope="col">Unit</th>
          <th scope="col">Last Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="4" class="text-muted text-center small">No metrics configured</td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item):
            $itemObj = is_array($item) ? (object)$item : $item;
            $deviceId = $itemObj->device_id ?? '';
            $metricKey = $itemObj->metric_key ?? '';
            $label = $itemObj->label ?? $metricKey;
            $unit = $itemObj->unit ?? '';
          ?>
            <tr class="widget-table-row cardalarm_<?= $deviceId ?>_<?= $metricKey ?>"
                data-device-id="<?= htmlspecialchars($deviceId) ?>"
                data-metric-key="<?= htmlspecialchars($metricKey) ?>"
                data-unit="<?= htmlspecialchars($unit) ?>">
              <td><?= htmlspecialchars($label) ?></td>
              <td class="metric-value">--</td>
              <td class="metric-unit"><?= htmlspecialchars($unit) ?></td>
              <td class="metric-time text-muted small">--</td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
