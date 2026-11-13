<?php
$cfg = $widget->config ?? (object)[];
$label = $cfg->label ?? $widget->label ?? '';
$svgFile = trim($cfg->svg_file ?? '');
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width:100%;height:100%;display:flex;flex-direction:column;overflow:hidden;');

$bindings = [];
if (isset($cfg->bindings)) {
    if (is_array($cfg->bindings)) {
        $bindings = $cfg->bindings;
    } elseif (is_string($cfg->bindings) && $cfg->bindings !== '') {
        $decoded = json_decode($cfg->bindings, true);
        if (is_array($decoded)) {
            $bindings = $decoded;
        }
    }
}

$bindingsJson = htmlspecialchars(json_encode(array_values($bindings)), ENT_QUOTES, 'UTF-8');
$svgUrl = $svgFile !== '' ? '/uploads/mimics/' . rawurlencode($svgFile) : '';
$elementId = 'svgwidget-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', ($widget_id ?? uniqid()));
?>
<div id="<?= htmlspecialchars($elementId) ?>"
     class="widget-svg-widget <?= $borderClass ?> <?= $bgClass ?> shadow-sm"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-svg-url="<?= htmlspecialchars($svgUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-bindings="<?= $bindingsJson ?>">
  <?php if ($label !== ''): ?>
    <div class="svg-widget-label small text-secondary px-2 pt-1 flex-shrink-0"><?= htmlspecialchars($label) ?></div>
  <?php endif; ?>
  <div class="svg-widget-stage flex-grow-1" style="min-height:0;width:100%;height:100%;position:relative;"></div>
  <div class="svg-widget-subs" style="display:none;" aria-hidden="true">
    <?php
    $seen = [];
    foreach ($bindings as $b) {
        $b = is_array($b) ? (object)$b : $b;
        $deviceId = $b->device_id ?? '';
        $metricKey = $b->metric_key ?? '';
        if ($deviceId === '' || $metricKey === '') continue;
        $key = $deviceId . '||' . $metricKey;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        ?>
        <span data-device-id="<?= htmlspecialchars($deviceId) ?>"
              data-metric-key="<?= htmlspecialchars($metricKey) ?>"
              data-svg-widget-sub="1"></span>
    <?php } ?>
  </div>
</div>

<style>
.widget-svg-widget .svg-widget-stage svg {
  width: 100%;
  height: 100%;
  display: block;
}
.widget-svg-widget .svg-widget-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #adb5bd;
  font-size: 0.9rem;
  text-align: center;
  padding: 1rem;
}
.mimic-blink {
  animation: mimic-blink 1s infinite;
}
@keyframes mimic-blink {
  50% { opacity: 0.25; }
}
.mimic-spin {
  animation: mimic-spin 1.2s linear infinite;
  transform-origin: center;
  transform-box: fill-box;
}
@keyframes mimic-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.mimic-active {
  filter: drop-shadow(0 0 2px rgba(40, 167, 69, 0.8));
}
</style>
