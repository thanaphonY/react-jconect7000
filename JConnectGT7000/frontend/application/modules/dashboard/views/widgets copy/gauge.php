<?php
// widgets/gauge.php
$g = $widget->config ?? (object)[];
$unit = $g->unit ?? '';
$min = $g->min ?? 0;
$max = $g->max ?? 100;
$label = $widget->label ?? '';
$elementId = "gauge-{$widget->device_id}-{$widget->metric_key}";
?>
<div id="<?= $elementId ?>" 
     class="widget-gauge border rounded bg-body text-body p-2 text-center shadow-sm" 
     style="width: 100%; height: 100%;">
  <div class="label small text-secondary mb-1"><?= htmlspecialchars($label) ?></div>
  <canvas></canvas>
  <div class="value mt-1 fw-bold">-- <span class="unit text-muted small"><?= htmlspecialchars($unit) ?></span></div>
</div>
<script>
window.renderGaugeWidget && window.renderGaugeWidget("<?= $elementId ?>", {
  device_id: <?= $widget->device_id ?>,
  metric_key: "<?= $widget->metric_key ?>",
  min: <?= $min ?>,
  max: <?= $max ?>,
  unit: "<?= $unit ?>"
});
</script>