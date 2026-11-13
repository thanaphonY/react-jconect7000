<?php
$cfg = $widget->config ?? (object)[];
$items = $cfg->items ?? [];
if (is_string($items)) {
    $decoded = json_decode($items);
    $items = is_array($decoded) ? $decoded : [];
}
$label = $cfg->label ?? '';
$columns = max(1, min(6, (int)($cfg->columns ?? 3)));
$widgetDomId = 'status-grid-' . $widget_id;
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$hasCustomBg = trim((string) ($cfg->bg_color ?? '')) !== '';
// Keep the same chrome look as value_box even when Show Border was left off
if ($borderClass === '' && $hasCustomBg) {
    $borderClass = 'border rounded';
}
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%; overflow: hidden;');

$linkUrl = trim((string)($cfg->link_url ?? ''));
$linkHref = '';
if ($linkUrl !== '') {
    if (preg_match('#^https?://#i', $linkUrl)) {
        $linkHref = $linkUrl;
    } else {
        $linkHref = base_url(ltrim($linkUrl, '/'));
    }
}
?>
<div id="<?= htmlspecialchars($widgetDomId) ?>"
     class="widget-status-grid <?= $borderClass ?> <?= $bgClass ?> <?= $hasCustomBg ? 'has-widget-bg' : '' ?> <?= $linkHref !== '' ? 'is-clickable' : '' ?> p-2 d-flex flex-column"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-columns="<?= $columns ?>"
     <?php if ($linkHref !== ''): ?>
     data-link-url="<?= htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') ?>"
     role="link"
     tabindex="0"
     title="<?= htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') ?>"
     <?php endif; ?>>
  <?php if ($label !== ''): ?>
    <div class="status-grid-title small mb-2"><?= htmlspecialchars($label) ?></div>
  <?php endif; ?>
  <div class="status-grid-body flex-grow-1" style="--status-grid-cols: <?= $columns ?>;">
    <?php if (empty($items)): ?>
      <div class="text-muted small text-center py-3">No status items configured</div>
    <?php else: ?>
      <?php foreach ($items as $item):
        $itemObj = is_array($item) ? (object)$item : $item;
        $deviceId = $itemObj->device_id ?? '';
        $metricKey = $itemObj->metric_key ?? '';
        $itemLabel = $itemObj->label ?? $metricKey;
        $icon = trim($itemObj->icon ?? 'bi-circle') ?: 'bi-circle';
        $icon = preg_replace('/^bi-?/', '', preg_replace('/[^a-zA-Z0-9\-]/', '', $icon)) ?: 'circle';
        $stateOn = $itemObj->state_on ?? 'On';
        $stateOff = $itemObj->state_off ?? 'Off';
        $colorOn = $itemObj->color_on ?? '#22c55e';
        $colorOff = $itemObj->color_off ?? '#94a3b8';
        $valueMaps = $itemObj->value_maps ?? [];
        if (is_string($valueMaps)) {
            $decodedMaps = json_decode($valueMaps, true);
            $valueMaps = is_array($decodedMaps) ? $decodedMaps : [];
        }
        $valueMapsJson = htmlspecialchars(json_encode(array_values($valueMaps)), ENT_QUOTES, 'UTF-8');
      ?>
        <div class="status-grid-cell cardalarm_<?= htmlspecialchars($deviceId) ?>_<?= htmlspecialchars($metricKey) ?>"
             data-device-id="<?= htmlspecialchars($deviceId) ?>"
             data-metric-key="<?= htmlspecialchars($metricKey) ?>"
             data-state-on="<?= htmlspecialchars($stateOn) ?>"
             data-state-off="<?= htmlspecialchars($stateOff) ?>"
             data-color-on="<?= htmlspecialchars($colorOn) ?>"
             data-color-off="<?= htmlspecialchars($colorOff) ?>"
             data-value-maps="<?= $valueMapsJson ?>">
          <div class="status-grid-dot" aria-hidden="true"></div>
          <div class="status-grid-icon bi bi-<?= htmlspecialchars($icon) ?>" aria-hidden="true"></div>
          <div class="status-grid-cell-body">
            <div class="status-grid-label"><?= htmlspecialchars($itemLabel) ?></div>
            <div class="status-grid-value">--</div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<style>
.widget-status-grid {
  height: 100%;
  box-sizing: border-box;
  border-radius: 10px;
  overflow: hidden;
}

.widget-status-grid.border {
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow:
    0 1px 2px rgba(0, 0, 0, 0.35),
    0 8px 24px rgba(0, 0, 0, 0.22);
}

[data-bs-theme="light"] .widget-status-grid.border {
  border-color: rgba(15, 23, 42, 0.12);
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.08);
}

.widget-status-grid.is-clickable {
  cursor: pointer;
}

.widget-status-grid .status-grid-body {
  display: grid;
  grid-template-columns: repeat(var(--status-grid-cols, 3), minmax(0, 1fr));
  gap: 0.5rem;
  align-content: start;
}

.widget-status-grid .status-grid-cell {
  position: relative;
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  padding: 0.45rem 0.55rem;
  text-align: left;
  background: var(--bs-body-bg);
  min-height: 3.75rem;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  gap: 0.55rem;
}

.widget-status-grid .status-grid-title {
  color: var(--bs-secondary-color);
  font-weight: 600;
  text-align: center;
}

/* When widget has a custom background, let it show through the cards */
.widget-status-grid.has-widget-bg .status-grid-title {
  color: #fff;
}

.widget-status-grid.has-widget-bg .status-grid-cell {
  background: color-mix(in srgb, #000 22%, transparent);
  border-color: color-mix(in srgb, #fff 28%, transparent);
}

.widget-status-grid.has-widget-bg .status-grid-label,
.widget-status-grid.has-widget-bg .status-grid-icon {
  color: color-mix(in srgb, #fff 82%, transparent);
}

.widget-status-grid.has-widget-bg .status-grid-value {
  color: #fff;
}

.widget-status-grid .status-grid-dot {
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  background: #94a3b8;
}

.widget-status-grid .status-grid-icon {
  flex-shrink: 0;
  font-size: clamp(1.35rem, 4cqi, 2.1rem);
  line-height: 1;
  color: var(--bs-secondary-color);
}

.widget-status-grid .status-grid-cell-body {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  gap: 0.1rem;
}

.widget-status-grid .status-grid-icon.effect-spin {
  animation: status-grid-icon-spin 2s linear infinite;
}

.widget-status-grid .status-grid-icon.effect-pulse {
  animation: status-grid-icon-pulse 1.2s ease-in-out infinite;
}

.widget-status-grid .status-grid-icon.effect-blink {
  animation: status-grid-icon-blink 1s step-start infinite;
}

@keyframes status-grid-icon-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes status-grid-icon-pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.12); opacity: 0.85; }
}

@keyframes status-grid-icon-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.25; }
}

.widget-status-grid .status-grid-label {
  font-size: 0.72rem;
  color: var(--bs-secondary-color);
  line-height: 1.2;
  word-break: break-word;
}

.widget-status-grid .status-grid-value {
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--bs-body-color);
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  max-width: 100%;
}
</style>
