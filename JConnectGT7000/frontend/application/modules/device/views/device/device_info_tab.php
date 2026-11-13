<?php
/**
 * Device Information tab: identity card on the left, detail sections on the right.
 * Expects $device, $template, $siteNodeName, $customFields, $deviceImageSrc, $viewProtocol from view.php.
 */
$infoCategory = null;
if (!empty($device->category_id) && $this->db->table_exists('device_categories')) {
  $infoCategory = $this->db->select('name, icon, color')->get_where('device_categories', array('id' => (int) $device->category_id))->row();
}

$infoTemplateDetails = array();
if (!empty($template->device_information)) {
  $decoded = is_array($template->device_information) ? $template->device_information : json_decode($template->device_information, true);
  if (is_array($decoded)) {
    $infoTemplateDetails = $decoded;
  }
}
// Known template keys get friendly labels; internal ones stay hidden.
$infoTemplateLabels = array(
  'Brand' => 'Brand',
  'Model' => 'Model',
  'Device Type' => 'Device type',
  'dcim_category' => 'DCIM category',
  'dcim_sub_category' => 'DCIM sub-category',
);
$infoTemplateHidden = array('domain', 'pue_role');

$infoDate = function ($value) {
  if (empty($value)) {
    return '-';
  }
  $ts = strtotime((string) $value);
  return $ts ? date('d/m/Y H:i', $ts) : (string) $value;
};
$infoText = function ($value) {
  $value = trim((string) $value);
  return $value !== '' ? htmlspecialchars($value) : '<span class="dvi-empty">-</span>';
};

$infoActive = !empty($device->is_active) && $device->is_active !== 'f';
$infoHasImage = !empty($device->image) || !empty($template->image);
$infoSiteNodeId = (int) ($device->site_node_id ?? 0);

// Map coordinates come from the Site: walk up from the bound node (Room → Building → Site).
$infoMapNode = null;
if ($infoSiteNodeId && $this->db->table_exists('site_nodes')) {
  $nextId = $infoSiteNodeId;
  for ($depth = 0; $nextId && $depth < 10; $depth++) {
    $row = $this->db->select('id, parent_id, name, latitude, longitude')->get_where('site_nodes', array('id' => $nextId))->row();
    if (!$row) {
      break;
    }
    if ($row->latitude !== null && $row->longitude !== null) {
      $infoMapNode = $row;
      break;
    }
    $nextId = (int) $row->parent_id;
  }
}
$infoMapEmbed = '';
$infoMapLink = '';
if ($infoMapNode) {
  $lat = (float) $infoMapNode->latitude;
  $lng = (float) $infoMapNode->longitude;
  $infoMapEmbed = 'https://www.openstreetmap.org/export/embed.html?bbox='
    . rawurlencode(($lng - 0.02) . ',' . ($lat - 0.012) . ',' . ($lng + 0.02) . ',' . ($lat + 0.012))
    . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lng);
  $infoMapLink = 'https://www.openstreetmap.org/?mlat=' . rawurlencode($lat) . '&mlon=' . rawurlencode($lng)
    . '#map=16/' . rawurlencode($lat) . '/' . rawurlencode($lng);
}
?>
<style>
  #dvi {
    --dvi-border: var(--bs-border-color-translucent);
    --dvi-subtle: var(--bs-tertiary-bg);
    --dvi-muted: var(--bs-secondary-color);
  }
  #dvi .dvi-card {
    height: 100%;
    border: 1px solid var(--dvi-border);
    border-radius: 0.65rem;
    background: var(--bs-body-bg);
    overflow: hidden;
  }
  #dvi .dvi-card__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    border-bottom: 1px solid var(--dvi-border);
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--dvi-muted);
  }
  #dvi .dvi-card__body {
    padding: 0.9rem 1rem;
  }

  /* Identity card */
  #dvi .dvi-photo {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 190px;
    padding: 1rem;
    background: var(--dvi-subtle);
    border-bottom: 1px solid var(--dvi-border);
  }
  #dvi .dvi-photo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
  }
  #dvi .dvi-photo i {
    font-size: 3rem;
    color: var(--dvi-muted);
  }
  #dvi .dvi-name {
    margin: 0 0 0.35rem;
    font-size: 1.15rem;
    font-weight: 700;
  }
  #dvi .dvi-sub {
    font-size: 0.85rem;
    color: var(--dvi-muted);
  }
  #dvi .dvi-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin: 0.75rem 0;
  }
  #dvi .dvi-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--dvi-subtle);
    border: 1px solid var(--dvi-border);
  }
  #dvi .dvi-badge--on {
    color: var(--bs-success-text-emphasis);
    background: var(--bs-success-bg-subtle);
    border-color: transparent;
  }
  #dvi .dvi-badge--off {
    color: var(--bs-secondary-text-emphasis);
    background: var(--bs-secondary-bg-subtle);
    border-color: transparent;
  }
  #dvi .dvi-badge--on::before,
  #dvi .dvi-badge--off::before {
    content: "";
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 50%;
    background: currentColor;
  }

  /* Key/value grid */
  #dvi .dvi-kv {
    display: grid;
    grid-template-columns: minmax(120px, 38%) 1fr;
    margin: 0;
  }
  #dvi .dvi-kv dt,
  #dvi .dvi-kv dd {
    margin: 0;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--dvi-border);
    font-size: 0.88rem;
  }
  #dvi .dvi-kv dt {
    font-weight: 500;
    color: var(--dvi-muted);
  }
  #dvi .dvi-kv dd {
    word-break: break-word;
  }
  #dvi .dvi-kv dt:nth-last-child(2),
  #dvi .dvi-kv dd:last-child {
    border-bottom: 0;
  }
  #dvi .dvi-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.82rem;
  }
  #dvi .dvi-empty {
    color: var(--dvi-muted);
  }
  #dvi .dvi-protocol {
    display: inline-block;
    padding: 0.05rem 0.45rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: var(--bs-info-text-emphasis);
    background: var(--bs-info-bg-subtle);
  }
  #dvi .dvi-map {
    position: relative;
    height: 220px;
    margin-top: 0.75rem;
    border: 1px solid var(--dvi-border);
    border-radius: 0.5rem;
    overflow: hidden;
    background: var(--dvi-subtle);
  }
  #dvi .dvi-map iframe {
    width: 100%;
    height: 100%;
    border: 0;
  }
  #dvi .dvi-map-caption {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.4rem;
    font-size: 0.8rem;
    color: var(--dvi-muted);
  }
  #dvi .dvi-map-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    height: 120px;
    margin-top: 0.75rem;
    border: 1px dashed var(--dvi-border);
    border-radius: 0.5rem;
    font-size: 0.85rem;
    color: var(--dvi-muted);
  }
  #dvi .dvi-desc {
    margin: 0;
    font-size: 0.9rem;
    white-space: pre-line;
  }

  @media (max-width: 575.98px) {
    #dvi .dvi-kv { grid-template-columns: 1fr; }
    #dvi .dvi-kv dt { padding-bottom: 0; border-bottom: 0; }
  }
</style>

<div id="dvi" class="row g-3">
  <!-- Identity -->
  <div class="col-lg-4 col-xl-3">
    <div class="dvi-card">
      <div class="dvi-photo">
        <?php if ($infoHasImage) { ?>
          <img src="<?= htmlspecialchars($deviceImageSrc) ?>" alt="<?= htmlspecialchars($device->name) ?>">
        <?php } else { ?>
          <i class="bi bi-hdd-rack"></i>
        <?php } ?>
      </div>
      <div class="dvi-card__body">
        <h5 class="dvi-name"><?= htmlspecialchars($device->name) ?></h5>
        <div class="dvi-sub"><?= htmlspecialchars($template->name ?? '-') ?></div>
        <div class="dvi-badges">
          <span class="dvi-badge <?= $infoActive ? 'dvi-badge--on' : 'dvi-badge--off' ?>"><?= ucfirst($infoActive ? lang('active') : lang('inactive')) ?></span>
          <?php if ($viewProtocol) { ?>
            <span class="dvi-badge"><i class="bi bi-plug"></i><?= htmlspecialchars(strtoupper($viewProtocol)) ?></span>
          <?php } ?>
          <?php if ($infoCategory) { ?>
            <span class="dvi-badge"><?php if (!empty($infoCategory->icon)) { ?><i class="<?= htmlspecialchars($infoCategory->icon) ?>"></i><?php } ?><?= htmlspecialchars($infoCategory->name) ?></span>
          <?php } ?>
        </div>
        <div class="d-grid gap-2">
          <a class="btn btn-sm btn-primary" href="<?= base_url('device/edit/' . (int) $device->id) ?>"><i class="bi bi-pencil-square me-1"></i><?php echo lang('edit') ?></a>
          <?php if ($infoSiteNodeId) { ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('site/view/' . $infoSiteNodeId) ?>"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($siteNodeName ?: 'Site') ?></a>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Details -->
  <div class="col-lg-8 col-xl-9">
    <div class="row g-3">
      <div class="col-xl-6">
        <div class="dvi-card">
          <div class="dvi-card__head"><i class="bi bi-info-circle"></i>General</div>
          <div class="dvi-card__body">
            <dl class="dvi-kv">
              <dt><?php echo lang('name') ?></dt>
              <dd><?= $infoText($device->name) ?></dd>
              <dt><?php echo lang('serial_number') ?></dt>
              <dd class="dvi-mono"><?= $infoText($device->serial_number) ?></dd>
              <dt><?php echo lang('device_template') ?></dt>
              <dd><?= $infoText($template->name ?? '') ?></dd>
              <dt><?php echo lang('protocol') ?></dt>
              <dd><?= $viewProtocol ? '<span class="dvi-protocol">' . htmlspecialchars(strtoupper($viewProtocol)) . '</span>' : '<span class="dvi-empty">-</span>' ?></dd>
              <dt><?php echo lang('status') ?></dt>
              <dd><?= ucfirst($infoActive ? lang('active') : lang('inactive')) ?></dd>
              <dt>Device ID</dt>
              <dd class="dvi-mono"><?= (int) $device->id ?></dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="col-xl-6">
        <div class="dvi-card">
          <div class="dvi-card__head"><i class="bi bi-geo-alt"></i><?php echo lang('location') ?></div>
          <div class="dvi-card__body">
            <dl class="dvi-kv">
              <dt>Site</dt>
              <dd>
                <?php if ($infoSiteNodeId && $siteNodeName !== '') { ?>
                  <a href="<?= base_url('site/view/' . $infoSiteNodeId) ?>"><?= htmlspecialchars($siteNodeName) ?></a>
                <?php } else { ?>
                  <span class="dvi-empty">-</span>
                <?php } ?>
              </dd>
              <dt><?php echo lang('location') ?></dt>
              <dd><?= $infoText($device->location) ?></dd>
              <dt><?php echo lang('created_at') ?></dt>
              <dd><?= htmlspecialchars($infoDate($device->created_at ?? null)) ?></dd>
              <dt><?php echo lang('updated_at') ?></dt>
              <dd><?= htmlspecialchars($infoDate($device->updated_at ?? null)) ?></dd>
            </dl>
            <?php if ($infoMapNode) { ?>
              <div class="dvi-map">
                <iframe title="<?= htmlspecialchars($infoMapNode->name) ?> map" loading="lazy" referrerpolicy="no-referrer"
                  src="<?= htmlspecialchars($infoMapEmbed) ?>"></iframe>
              </div>
              <div class="dvi-map-caption">
                <span><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($infoMapNode->name) ?> · <?= htmlspecialchars($infoMapNode->latitude . ', ' . $infoMapNode->longitude) ?></span>
                <a href="<?= htmlspecialchars($infoMapLink) ?>" target="_blank" rel="noopener">Open map <i class="bi bi-box-arrow-up-right"></i></a>
              </div>
            <?php } else { ?>
              <div class="dvi-map-empty"><i class="bi bi-map"></i>No site coordinates</div>
            <?php } ?>
          </div>
        </div>
      </div>

      <?php
      $infoTemplateRows = array();
      foreach ($infoTemplateDetails as $key => $value) {
        // Skip internal keys and ones the device overrides in its custom fields.
        if (in_array($key, $infoTemplateHidden, true) || isset($customFields[$key]) || is_array($value) || trim((string) $value) === '') {
          continue;
        }
        $label = isset($infoTemplateLabels[$key]) ? $infoTemplateLabels[$key] : ucfirst(str_replace('_', ' ', $key));
        $infoTemplateRows[$label] = $value;
      }
      ?>
      <?php if ($infoTemplateRows) { ?>
        <div class="col-xl-6">
          <div class="dvi-card">
            <div class="dvi-card__head"><i class="bi bi-cpu"></i>Hardware</div>
            <div class="dvi-card__body">
              <dl class="dvi-kv">
                <?php foreach ($infoTemplateRows as $label => $value) { ?>
                  <dt><?= htmlspecialchars($label) ?></dt>
                  <dd><?= $infoText($value) ?></dd>
                <?php } ?>
              </dl>
            </div>
          </div>
        </div>
      <?php } ?>

      <div class="col-xl-6">
        <div class="dvi-card">
          <div class="dvi-card__head"><i class="bi bi-tags"></i><?php echo lang('custom_field') ?></div>
          <div class="dvi-card__body">
            <?php if (!empty($customFields)) { ?>
              <dl class="dvi-kv">
                <?php foreach ($customFields as $key => $value) { ?>
                  <dt><?= htmlspecialchars($key) ?></dt>
                  <dd><?= $infoText(is_array($value) ? json_encode($value) : $value) ?></dd>
                <?php } ?>
              </dl>
            <?php } else { ?>
              <span class="dvi-empty small">-</span>
            <?php } ?>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="dvi-card">
          <div class="dvi-card__head"><i class="bi bi-card-text"></i><?php echo lang('description') ?></div>
          <div class="dvi-card__body">
            <?php if (trim((string) $device->description) !== '') { ?>
              <p class="dvi-desc"><?= htmlspecialchars($device->description) ?></p>
            <?php } else { ?>
              <span class="dvi-empty small">-</span>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
