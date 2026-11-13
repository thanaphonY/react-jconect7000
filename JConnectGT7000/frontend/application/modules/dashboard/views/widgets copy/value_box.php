<?php
$v = $widget->config ?? (object)[];
$unit = $widget->unit ?? '';
$label = $widget->label ?? '';
$elementId = "valuebox-{$widget->device_id}-{$widget->metric_key}";
?>
<div id="<?= $elementId ?>" 
     class="widget-value-box border rounded text-center bg-body text-body shadow-sm cardalarm_<?= $widget->device_id ?>_<?= $widget->metric_key ?> <?=$elementId?>" 
     style="width: 100%; height: 100%;"
     data-device-id="<?= $widget->device_id ?>"
     data-metric-key="<?= $widget->metric_key ?>"
     data-unit="<?= $unit ?>">
  <div class="label small  mb-1"><?= htmlspecialchars($label) ?></div>

  <div class="value fw-bold fs-2">-- <span class="unit"><?= htmlspecialchars($unit) ?></span></div>

  <div class="time small  text-muted mb-1">--</div>
  
</div>

<script>



</script>

<style type="text/css">
.widget-value-box {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;
  overflow: hidden;
  text-align: center;
}

.widget-value-box  .value {
  font-size: clamp(1.5rem, 3vw, 2.5rem);
  font-weight: bold;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.widget-value-box .unit {
  font-size: 1rem;
}

.widget-value-box .time {
  font-size: 0.75rem;
  
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  max-height: 1.2em;
  flex-shrink: 1;
}
</style>