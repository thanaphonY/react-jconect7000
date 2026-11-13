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
$styleAttr = widget_style_attr($g, 'width: 100%; height: 100%; display: flex; flex-direction: column; overflow: hidden; padding: 0.15rem 0.25rem 0.1rem; gap: 0.1rem;');
$elementId = 'gauge-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId . '-' . $metricKey . '-' . ($widget_id ?? ''));
?>
<div id="<?= htmlspecialchars($elementId) ?>"
     class="widget-gauge gauge-<?= htmlspecialchars($deviceId) ?>-<?= htmlspecialchars($metricKey) ?> <?= $borderClass ?> <?= $bgClass ?> text-center cardalarm_<?= $deviceId ?>_<?= $metricKey ?>"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-id="<?= htmlspecialchars($deviceId) ?>"
     data-metric-key="<?= htmlspecialchars($metricKey) ?>"
     data-min="<?= htmlspecialchars($min) ?>"
     data-max="<?= htmlspecialchars($max) ?>"
     data-unit="<?= htmlspecialchars($unit) ?>"
     data-color-ranges="<?= $colorRangesJson ?>">
  <div class="label small text-secondary flex-shrink-0 gauge-label mb-0"><?= htmlspecialchars($label) ?></div>
  <div class="gauge-chart flex-grow-1"></div>
</div>

<style>
.widget-gauge {
  box-sizing: border-box;
  container-type: inline-size;
  position: relative;
}

.widget-gauge .gauge-label {
  font-size: clamp(0.65rem, 2.2cqi, 0.875rem);
  line-height: 1.1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin: 0;
  padding: 0 0.1rem;
  min-height: 0.9rem;
  flex: 0 0 auto;
}

.widget-gauge .gauge-chart {
  flex: 1 1 auto;
  min-height: 0;
  width: 100%;
  height: 100%;
  position: relative;
  overflow: hidden;
}

.widget-gauge .gauge-chart > div,
.widget-gauge .gauge-chart canvas {
  width: 100% !important;
  height: 100% !important;
}
</style>
