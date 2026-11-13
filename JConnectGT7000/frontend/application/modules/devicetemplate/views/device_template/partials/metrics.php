<?php
$templateMetrics = isset($template) && !empty($template->metrics) ? $template->metrics : '[]';
$calcDevices = isset($calc_devices) && is_array($calc_devices) ? $calc_devices : [];
$notificationChannels = isset($channels) && is_array($channels) ? $channels : [];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/jconnect-metric-builder.css') ?>">

<div class="card mt-3">
  <div class="card-header bg-success">
    <h5 class="mb-0 text-white"><?= lang('metrics_definition') ?></h5>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      Define collected, visual, and calculated metrics. The JSON generated here is compatible with Gen3 dataedge.
    </p>
    <div id="metrics-container"></div>
    <input type="hidden" name="metrics" id="metrics_json">
  </div>
</div>

<script src="<?= base_url('assets/js/jconnect-metric-builder.js') ?>?v=20260729-edit-json"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  window.templateMetricBuilder = JConnectMetricBuilder.create({
    containerId: 'metrics-container',
    inputId: 'metrics_json',
    protocolId: 'protocol',
    jsonEditable: true,
    metrics: <?= json_encode($templateMetrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    devices: <?= json_encode($calcDevices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    channels: <?= json_encode($notificationChannels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
  });

  window.addMetric = function () { window.templateMetricBuilder.add('metric'); };
  window.renderMetrics = function () { window.templateMetricBuilder.render(); };
});
</script>
