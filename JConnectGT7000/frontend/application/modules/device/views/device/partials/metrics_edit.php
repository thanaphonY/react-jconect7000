<!-- Step 3: Metrics for Edit Device -->

<h5><?php echo lang('device_metrics') ?></h5>
<div id="metrics-container">
  <div class="text-muted py-3">Open this tab to load the metrics editor.</div>
</div>
<?php
  $metricsJsonValue = '[]';
  if (isset($device) && $device->metrics !== null && $device->metrics !== '') {
    $rawMetricsField = $device->metrics;
    if (is_string($rawMetricsField)) {
      $decodedMetricsField = json_decode($rawMetricsField, true);
      if (is_string($decodedMetricsField)) {
        $decodedMetricsField = json_decode($decodedMetricsField, true);
      }
    } else {
      $decodedMetricsField = $rawMetricsField;
    }
    if (is_array($decodedMetricsField)) {
      $encodedMetrics = json_encode($decodedMetricsField, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($encodedMetrics !== false) {
        $metricsJsonValue = $encodedMetrics;
      }
    }
  }

  $snapTriggers = [];
  if (isset($device) && !empty($device->config)) {
    $cfgSnap = is_string($device->config) ? json_decode($device->config, true) : (array) $device->config;
    if (is_array($cfgSnap) && !empty($cfgSnap['snapshot_triggers'])) {
      $snapTriggers = $cfgSnap['snapshot_triggers'];
    }
  }
?>
<input type="hidden" name="metrics" id="metrics_json" value="<?= htmlspecialchars($metricsJsonValue, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="snapshot_triggers_json" id="snapshot_triggers_json"
       value="<?= htmlspecialchars(json_encode($snapTriggers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">
<div class="d-flex justify-content-between mt-3">
  <button type="button" class="btn btn-secondary" onclick="goToStep(2)"><?php echo lang('back') ?></button>
  <button type="button" class="btn btn-primary" onclick="goToStep(4)"><?php echo lang('next') ?></button>
</div>
