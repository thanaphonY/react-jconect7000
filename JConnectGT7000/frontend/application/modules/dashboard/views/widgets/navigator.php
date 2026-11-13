<?php
$cfg = $widget->config ?? (object)[];
$items = $cfg->items ?? [];
if (is_string($items)) {
    $decoded = json_decode($items);
    $items = is_array($decoded) ? $decoded : [];
}

$label = $cfg->label ?? '';
$columns = max(1, min(6, (int)($cfg->columns ?? 2)));
$currentLayoutId = isset($layout) ? (string)$layout->id : '';
$linkBase = ($this->router->class === 'home') ? 'home/index/' : 'dashboard/view/';

$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%;');
$widgetDomId = 'navigator-' . ($widget_id ?? uniqid());
?>
<div id="<?= htmlspecialchars($widgetDomId) ?>"
     class="widget-navigator <?= $borderClass ?> <?= $bgClass ?>"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>; --navigator-cols: <?= $columns ?>;">
  <?php if ($label !== ''): ?>
    <div class="navigator-title text-secondary"><?= htmlspecialchars($label) ?></div>
  <?php endif; ?>
  <div class="navigator-body">
    <?php if (empty($items)): ?>
      <div class="text-muted small text-center py-2">No dashboards configured</div>
    <?php else: ?>
      <?php foreach ($items as $item):
        $itemObj = is_array($item) ? (object)$item : $item;
        $layoutId = trim((string)($itemObj->layout_id ?? ''));
        if ($layoutId === '') continue;
        $itemLabel = trim((string)($itemObj->label ?? ''));
        if ($itemLabel === '') $itemLabel = 'Dashboard';
        $icon = preg_replace('/[^a-zA-Z0-9\-]/', '', (string)($itemObj->icon ?? 'bi-grid-1x2')) ?: 'bi-grid-1x2';
        if (strpos($icon, 'bi-') !== 0) $icon = 'bi-' . $icon;
        $color = trim((string)($itemObj->color ?? ''));
        $href = base_url($linkBase . $layoutId);
        $isActive = $layoutId === $currentLayoutId;
      ?>
        <a class="navigator-link<?= $isActive ? ' is-active' : '' ?>"
           href="<?= htmlspecialchars($href) ?>"
           title="<?= htmlspecialchars($itemLabel) ?>">
          <i class="navigator-icon bi <?= htmlspecialchars($icon) ?>"<?= $color !== '' ? ' style="color:' . htmlspecialchars($color) . '"' : '' ?>></i>
          <span class="navigator-label"><?= htmlspecialchars($itemLabel) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
