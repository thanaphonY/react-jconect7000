<?php
$cfg = $widget->config ?? (object)[];
$items = $cfg->items ?? [];
if (is_string($items)) {
    $decoded = json_decode($items);
    $items = is_array($decoded) ? $decoded : [];
}

$zoneName = $cfg->zone_name ?? ($cfg->label ?? 'Zone');
$zoneSubtitle = $cfg->zone_subtitle ?? '';
$icon = $cfg->icon ?? 'bi-building';
$showPrimary = ($cfg->show_primary ?? '1') !== '0';

$primaryDeviceId = $cfg->primary_device_id ?? '';
$primaryMetricKey = $cfg->primary_metric_key ?? '';
$primaryLabel = $cfg->primary_label ?? '';
$primaryUnit = $cfg->primary_unit ?? '';

$valueMaps = [];
if (isset($cfg->value_maps)) {
    if (is_array($cfg->value_maps)) {
        $valueMaps = $cfg->value_maps;
    } elseif (is_string($cfg->value_maps) && $cfg->value_maps !== '') {
        $decoded = json_decode($cfg->value_maps, true);
        if (is_array($decoded)) {
            $valueMaps = $decoded;
        }
    }
}
$valueMapsJson = htmlspecialchars(json_encode(array_values($valueMaps)), ENT_QUOTES, 'UTF-8');

$alarmDevices = $cfg->alarm_devices ?? [];
if (is_string($alarmDevices)) {
    $decoded = json_decode($alarmDevices);
    $alarmDevices = is_array($decoded) ? $decoded : [];
}

$alarmDeviceIds = [];
$collectDeviceId = function ($id) use (&$alarmDeviceIds) {
    $id = trim((string)$id);
    if ($id !== '' && !in_array($id, $alarmDeviceIds, true)) {
        $alarmDeviceIds[] = $id;
    }
};

if ($primaryDeviceId) {
    $collectDeviceId($primaryDeviceId);
}
foreach ($items as $item) {
    $itemObj = is_array($item) ? (object)$item : $item;
    $collectDeviceId($itemObj->device_id ?? '');
}
foreach ($alarmDevices as $entry) {
    $entryObj = is_array($entry) ? (object)$entry : $entry;
    $collectDeviceId($entryObj->device_id ?? '');
}

$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg, '');
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%;');
$cardId = 'zone-overview-' . $widget_id;

$linkUrl = trim($cfg->link_url ?? '');
$linkHref = '';
if ($linkUrl !== '') {
    if (preg_match('#^https?://#i', $linkUrl)) {
        $linkHref = $linkUrl;
    } else {
        $linkHref = base_url(ltrim($linkUrl, '/'));
    }
}
?>
<div id="<?= $cardId ?>"
     class="widget-zone-overview zone-overview--normal<?= $linkHref ? ' zone-overview--clickable' : '' ?> <?= $borderClass ?> <?= $bgClass ?>"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-zone-name="<?= htmlspecialchars($zoneName) ?>"
     data-alarm-devices="<?= htmlspecialchars(json_encode(array_values($alarmDeviceIds))) ?>"
     <?php if ($linkHref): ?>
     data-link-url="<?= htmlspecialchars($linkHref) ?>"
     role="link"
     tabindex="0"
     title="<?= htmlspecialchars('Open: ' . $linkUrl) ?>"
     <?php endif; ?>>

  <div class="zone-overview-header">
    <span class="zone-overview-name"><?= htmlspecialchars($zoneName) ?></span>
    <?php if ($icon): ?>
      <i class="bi <?= htmlspecialchars($icon) ?> zone-overview-icon"></i>
    <?php endif; ?>
  </div>

  <div class="zone-overview-body">
    <div class="zone-overview-primary">
      <?php if ($showPrimary && $primaryDeviceId && $primaryMetricKey): ?>
        <div class="zone-primary-metric cardalarm_<?= $primaryDeviceId ?>_<?= $primaryMetricKey ?> valuebox-<?= $primaryDeviceId ?>-<?= $primaryMetricKey ?>"
             data-device-id="<?= htmlspecialchars($primaryDeviceId) ?>"
             data-metric-key="<?= htmlspecialchars($primaryMetricKey) ?>"
             data-unit="<?= htmlspecialchars($primaryUnit) ?>"
             data-value-maps="<?= $valueMapsJson ?>">
          <div class="zone-primary-value value">--</div>
          <div class="zone-primary-label"><?= htmlspecialchars($primaryLabel ?: $primaryMetricKey) ?></div>
        </div>
      <?php else: ?>
        <div class="zone-alarm-summary">
          <div class="zone-alarm-count" data-alarm-count>0</div>
          <div class="zone-alarm-label">Active Alarms</div>
        </div>
      <?php endif; ?>
      <?php foreach ($alarmDeviceIds as $devId): ?>
        <span class="zone-alarm-tracker cardalarm_<?= htmlspecialchars($devId) ?>_" aria-hidden="true" style="display:none"></span>
      <?php endforeach; ?>
    </div>

    <div class="zone-overview-metrics">
      <?php if (empty($items)): ?>
        <div class="zone-metric-empty text-center small">No metrics configured</div>
      <?php else: ?>
        <?php foreach ($items as $item):
          $itemObj = is_array($item) ? (object)$item : $item;
          $deviceId = $itemObj->device_id ?? '';
          $metricKey = $itemObj->metric_key ?? '';
          $label = $itemObj->label ?? $metricKey;
          $unit = $itemObj->unit ?? '';
          $itemIcon = $itemObj->icon ?? 'bi-dot';
        ?>
          <div class="zone-metric-row cardalarm_<?= $deviceId ?>_<?= $metricKey ?>"
               data-device-id="<?= htmlspecialchars($deviceId) ?>"
               data-metric-key="<?= htmlspecialchars($metricKey) ?>"
               data-unit="<?= htmlspecialchars($unit) ?>">
            <?php if ($itemIcon): ?>
              <i class="bi <?= htmlspecialchars($itemIcon) ?> zone-metric-icon"></i>
            <?php endif; ?>
            <span class="zone-metric-label"><?= htmlspecialchars($label) ?></span>
            <span class="zone-metric-value metric-value">--</span>
            <?php if ($unit): ?>
              <span class="zone-metric-unit metric-unit"><?= htmlspecialchars($unit) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($zoneSubtitle): ?>
    <div class="zone-overview-footer"><?= htmlspecialchars($zoneSubtitle) ?></div>
  <?php endif; ?>
</div>

<style>
.widget-zone-overview {
  display: flex;
  flex-direction: column;
  container-type: size;
  border-radius: 6px;
  overflow: hidden;
  color: #fff;
  padding: clamp(8px, 4cqh, 12px) clamp(10px, 4cqw, 14px);
  transition: background-color 0.35s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  font-size: clamp(0.65rem, 5.5cqh, 0.8rem);
}

.widget-zone-overview.zone-overview--normal {
  background: linear-gradient(135deg, #1a7f37 0%, #146c32 100%);
}

.widget-zone-overview.zone-overview--warning {
  background: linear-gradient(135deg, #d4a017 0%, #b8860b 100%);
  color: #1a1a1a;
}

.widget-zone-overview.zone-overview--critical {
  background: linear-gradient(135deg, #c0392b 0%, #962d22 100%);
}

.zone-overview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: clamp(4px, 3cqh, 8px);
  font-size: clamp(0.75rem, 7cqh, 0.9rem);
  font-weight: 600;
  opacity: 0.95;
  flex-shrink: 0;
}

.zone-overview-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.zone-overview-icon {
  font-size: clamp(0.85rem, 7.5cqh, 1rem);
  opacity: 0.85;
  flex-shrink: 0;
}

.zone-overview-body {
  display: flex;
  flex: 1;
  gap: clamp(6px, 3cqw, 12px);
  align-items: center;
  min-height: 0;
  overflow: hidden;
}

.zone-overview-primary {
  flex: 0 0 40%;
  text-align: center;
  padding-right: clamp(4px, 2cqw, 8px);
  border-right: 1px solid rgba(255, 255, 255, 0.25);
  min-width: 0;
}

.zone-overview--warning .zone-overview-primary {
  border-right-color: rgba(0, 0, 0, 0.15);
}

.zone-primary-value,
.zone-alarm-count {
  font-size: clamp(1rem, 16cqh, 1.65rem);
  font-weight: 600;
  line-height: 1.1;
  word-break: break-word;
}

.zone-primary-value .unit {
  font-size: 0.55em;
  font-weight: 600;
}

.zone-primary-value.effect-spin {
  animation: zone-primary-spin 2s linear infinite;
  display: inline-block;
}

.zone-primary-value.effect-pulse {
  animation: zone-primary-pulse 1.2s ease-in-out infinite;
}

.zone-primary-value.effect-blink {
  animation: zone-primary-blink 1s step-start infinite;
}

@keyframes zone-primary-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes zone-primary-pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.08); opacity: 0.85; }
}

@keyframes zone-primary-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.25; }
}

.zone-primary-label,
.zone-alarm-label {
  font-size: clamp(0.6rem, 5.5cqh, 0.72rem);
  opacity: 0.85;
  margin-top: clamp(2px, 1.5cqh, 4px);
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.zone-overview-metrics {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: clamp(2px, 1.5cqh, 4px);
  overflow: hidden;
  min-width: 0;
  justify-content: center;
  align-items: stretch;
  text-align: left;
}

.zone-metric-row {
  display: flex;
  align-items: center;
  gap: clamp(3px, 1.5cqw, 6px);
  font-size: clamp(0.62rem, 5.5cqh, 0.75rem);
  line-height: 1.25;
  white-space: nowrap;
  width: 100%;
  justify-content: flex-start;
}

.zone-metric-icon {
  font-size: clamp(0.7rem, 6cqh, 0.82rem);
  opacity: 0.8;
  flex-shrink: 0;
  width: 1em;
  text-align: center;
}

.zone-metric-label {
  flex: 0 1 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  opacity: 0.9;
  min-width: 0;
  text-align: left;
}

.zone-metric-value {
  font-weight: 600;
  flex-shrink: 0;
  font-size: 1.05em;
  margin-left: auto;
  text-align: right;
}

.zone-metric-unit {
  opacity: 0.8;
  font-size: 0.9em;
  flex-shrink: 0;
}

.zone-metric-empty {
  opacity: 0.7;
  padding: 4px 0;
  font-size: clamp(0.6rem, 5cqh, 0.7rem);
}

.zone-overview-footer {
  margin-top: clamp(4px, 2.5cqh, 8px);
  font-size: clamp(0.58rem, 5cqh, 0.68rem);
  opacity: 0.8;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  padding-top: clamp(3px, 2cqh, 6px);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex-shrink: 0;
}

.zone-overview--warning .zone-overview-footer {
  border-top-color: rgba(0, 0, 0, 0.12);
}

.widget-zone-overview.zone-overview--clickable {
  cursor: pointer;
  transition: background-color 0.35s ease, filter 0.2s ease, box-shadow 0.2s ease;
}

.widget-zone-overview.zone-overview--clickable:hover {
  filter: brightness(1.08);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22);
}

.widget-zone-overview.zone-overview--clickable:focus-visible {
  outline: 2px solid rgba(255, 255, 255, 0.85);
  outline-offset: 2px;
}
</style>
