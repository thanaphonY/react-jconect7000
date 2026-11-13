<?php
$g = $widget->config ?? (object)[];
$deviceId = $g->device_id ?? $widget->device_id ?? '';
$metricKey = $g->metric_key ?? $widget->metric_key ?? '';
$unit = $g->unit ?? $widget->unit ?? '';
$label = $g->label ?? $widget->label ?? '';
$min = (isset($g->min) && $g->min !== '') ? (float)$g->min : 0;
$max = (isset($g->max) && $g->max !== '') ? (float)$g->max : 100;
if ($max <= $min) {
    $max = $min + 100;
}
$orientation = (($g->orientation ?? 'vertical') === 'horizontal') ? 'horizontal' : 'vertical';

$colorRanges = [];
if (isset($g->color_ranges)) {
    if (is_array($g->color_ranges)) {
        $colorRanges = $g->color_ranges;
    } elseif (is_string($g->color_ranges) && $g->color_ranges !== '') {
        $decoded = json_decode($g->color_ranges, true);
        if (is_array($decoded)) {
            $colorRanges = $decoded;
        }
    }
}
$colorRangesJson = htmlspecialchars(json_encode(array_values($colorRanges)), ENT_QUOTES, 'UTF-8');
$borderClass = widget_border_class($g);
$bgClass = widget_bg_class($g);
$styleAttr = widget_style_attr($g, 'width: 100%; height: 100%;');
$elementId = 'level-bar-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId . '-' . $metricKey . '-' . ($widget_id ?? ''));
$orientClass = $orientation === 'horizontal' ? 'is-horizontal' : 'is-vertical';
?>
<div id="<?= htmlspecialchars($elementId) ?>"
     class="widget-level-bar <?= $orientClass ?> level-bar-<?= htmlspecialchars($deviceId) ?>-<?= htmlspecialchars($metricKey) ?> <?= $borderClass ?> <?= $bgClass ?> cardalarm_<?= $deviceId ?>_<?= $metricKey ?>"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-id="<?= htmlspecialchars($deviceId) ?>"
     data-metric-key="<?= htmlspecialchars($metricKey) ?>"
     data-min="<?= htmlspecialchars($min) ?>"
     data-max="<?= htmlspecialchars($max) ?>"
     data-unit="<?= htmlspecialchars($unit) ?>"
     data-orientation="<?= htmlspecialchars($orientation) ?>"
     data-color-ranges="<?= $colorRangesJson ?>">
  <div class="small text-secondary level-bar-label"><?= htmlspecialchars($label) ?></div>
  <div class="level-bar-body">
    <div class="level-bar-track">
      <div class="level-bar-fill"></div>
    </div>
    <div class="level-bar-ticks"></div>
  </div>
  <div class="level-bar-value">--<?= $unit !== '' ? ' <span class="unit">' . htmlspecialchars($unit) . '</span>' : '' ?></div>
</div>
