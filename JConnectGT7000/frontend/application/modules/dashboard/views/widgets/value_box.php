<?php
$v = $widget->config ?? (object)[];
$unit = $widget->unit ?? '';
$label = $widget->label ?? '';
$elementId = "valuebox-{$widget->device_id}-{$widget->metric_key}";

$valueMaps = [];
if (isset($v->value_maps)) {
    if (is_array($v->value_maps)) {
        $valueMaps = $v->value_maps;
    } elseif (is_string($v->value_maps) && $v->value_maps !== '') {
        $decoded = json_decode($v->value_maps, true);
        if (is_array($decoded)) {
            $valueMaps = $decoded;
        }
    }
}
$showIcon = isset($v->show_icon) && ($v->show_icon === '1' || $v->show_icon === 1 || $v->show_icon === true);
$iconClass = preg_replace('/[^a-zA-Z0-9\-]/', '', trim($v->icon ?? 'bi-circle')) ?: 'bi-circle';
$linkUrl = trim($v->link_url ?? '');
$valueMapsJson = htmlspecialchars(json_encode(array_values($valueMaps)), ENT_QUOTES, 'UTF-8');
$borderClass = widget_border_class($v);
$bgClass = widget_bg_class($v);
$styleAttr = widget_style_attr($v, 'width: 100%; height: 100%;');
?>
<div id="<?= $elementId ?>" 
     class="widget-value-box <?= $borderClass ?> <?= $bgClass ?> <?= $showIcon ? 'has-icon' : '' ?> <?= $linkUrl !== '' ? 'is-clickable' : '' ?> text-center cardalarm_<?= $widget->device_id ?>_<?= $widget->metric_key ?> valuebox-<?= $widget->device_id ?>-<?= $widget->metric_key ?> <?=$elementId?>" 
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-id="<?= $widget->device_id ?>"
     data-metric-key="<?= $widget->metric_key ?>"
     data-unit="<?= htmlspecialchars($unit) ?>"
     data-show-icon="<?= $showIcon ? '1' : '0' ?>"
     data-icon="<?= htmlspecialchars($iconClass) ?>"
     data-link-url="<?= htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-value-maps="<?= $valueMapsJson ?>">
  <?php if ($showIcon): ?>
  <div class="widget-value-box-icon bi <?= htmlspecialchars($iconClass) ?>" aria-hidden="true"></div>
  <?php endif; ?>
  <div class="widget-value-box-body">
    <div class="label small mb-1"><?= htmlspecialchars($label) ?></div>
    <div class="value fw-bold fs-5 mt-1">-- <span class="unit"><?= htmlspecialchars($unit) ?></span></div>
    <div class="time small text-muted mb-1">--</div>
  </div>
</div>

<style type="text/css">
.widget-value-box {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;
  overflow: hidden;
  text-align: center;
  box-sizing: border-box;
}

.widget-value-box.is-clickable {
  cursor: pointer;
}

.widget-value-box.has-icon {
  flex-direction: row;
  align-items: center;
  justify-content: center;
  text-align: left;
  gap: 0.5rem;
  padding: 0.35rem 0.5rem;
}

.widget-value-box-icon {
  flex-shrink: 0;
  font-size: clamp(1.4rem, 4cqi, 2.4rem);
  line-height: 1;
  color: var(--bs-body-color);
}

.widget-value-box-icon.effect-spin {
  animation: vb-icon-spin 2s linear infinite;
}

.widget-value-box-icon.effect-pulse {
  animation: vb-icon-pulse 1.2s ease-in-out infinite;
}

.widget-value-box-icon.effect-blink {
  animation: vb-icon-blink 1s step-start infinite;
}

@keyframes vb-icon-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes vb-icon-pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.12); opacity: 0.85; }
}

@keyframes vb-icon-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.25; }
}

.widget-value-box-body {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.widget-value-box.has-icon .widget-value-box-body {
  align-items: flex-start;
  flex: 0 1 auto;
}

.widget-value-box .value {
  font-size: clamp(1.5rem, 3vw, 2.5rem);
  font-weight: bold;
  white-space: nowrap;
  text-overflow: ellipsis;
  text-transform: capitalize;
}

.widget-value-box .unit {
  font-size: 1rem;
}

.widget-value-box .time {
  font-size: 0.75rem;
  margin-top: 6px;
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  max-height: 1.2em;
  flex-shrink: 1;
}
</style>
