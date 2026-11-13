<?php
$stats = !empty($tree) ? site_tree_count_nodes($tree) : array('total' => 0, 'Site' => 0);
$siteRows = !empty($tree) ? site_tree_collect($tree, array('Site')) : array();
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-diagram-3"></i> Site Management</h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo base_url(); ?>">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Site Management</li>
          </ol>
        </nav>
        <?php if (!empty($tree)) { ?>
          <p class="text-body-secondary small mb-0 mt-1"><?php echo (int) $stats['total']; ?> nodes · <?php echo (int) $stats['Site']; ?> sites</p>
        <?php } ?>
      </div>
      <div class="col text-end">
        <?php if (empty($regional)) { ?>
          <a class="btn btn-outline-primary" href="<?php echo base_url('site/access'); ?>">
            <i class="bi bi-people"></i> User scope
          </a>
        <?php } ?>
        <a class="btn btn-primary<?php echo empty($regional) ? ' ms-2' : ''; ?>" href="<?php echo base_url('site/form'); ?>">
          <i class="bi bi-plus-lg"></i> Add node
        </a>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid" id="site-management">
    <div class="card">
      <div class="card-body">
        <?php if (empty($tree)) { ?>
          <div class="text-center text-body-secondary py-5">
            <i class="bi bi-diagram-3 fs-1 d-block mb-2 opacity-50"></i>
            No sites are assigned to this user.
          </div>
        <?php } else { ?>
          <div class="site-mgmt-toolbar row g-2 align-items-end mb-3">
            <div class="col-md-5">
              <label class="form-label small mb-1" for="site-tree-search">Search</label>
              <input type="search" class="form-control form-control-sm" id="site-tree-search" placeholder="Name or type…" autocomplete="off">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1" for="site-type-filter">Type</label>
              <select class="form-select form-select-sm" id="site-type-filter">
                <option value="">All types</option>
                <option value="Main">Main</option>
                <option value="Region">Region</option>
                <option value="Site">Site</option>
                <option value="Building">Building</option>
                <option value="Room">Room</option>
              </select>
            </div>
            <div class="col-md-4 d-flex flex-wrap gap-2 justify-content-md-end">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="site-expand-all">Expand all</button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="site-collapse-all">Collapse</button>
              <span class="small text-body-secondary align-self-center" id="site-match-count"></span>
            </div>
          </div>
          <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link active" data-site-view="tree" role="tab">Hierarchy</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link" data-site-view="sites" role="tab">Sites (<?php echo count($siteRows); ?>)</button>
            </li>
          </ul>
          <div id="site-tree-panel" class="site-tree-scroll">
            <ul class="site-tree-root">
              <?php site_render_admin_tree($tree, 0, '', isset($alarms) ? $alarms : array(), isset($devices) ? $devices : array()); ?>
            </ul>
          </div>
          <div id="site-sites-panel" class="d-none site-tree-scroll">
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0" id="site-sites-table">
                <thead>
                  <tr>
                    <th>Site</th>
                    <th>Path</th>
                    <th class="text-end" style="width:120px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($siteRows as $row) {
                    $node = $row['node'];
                    $id = (int) $node->id;
                    ?>
                    <tr data-node-type="Site" data-search="<?php echo html_escape($row['search']); ?>">
                      <td class="fw-semibold"><?php echo html_escape($node->name); ?></td>
                      <td class="text-body-secondary small site-table-path" title="<?php echo html_escape($row['path']); ?>">
                        <?php
                        $pathDisplay = isset($row['parent_path']) ? $row['parent_path'] : site_tree_ancestor_path($row['path'], $node->name);
                        echo $pathDisplay !== '' ? html_escape($pathDisplay) : '—';
                        ?>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo base_url('site/form/' . $id); ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a class="btn btn-outline-info btn-sm" href="<?php echo base_url('site/view/' . $id); ?>" title="Open"><i class="bi bi-box-arrow-up-right"></i></a>
                      </td>
                    </tr>
                  <?php } ?>
                  <?php if (!$siteRows) { ?>
                    <tr><td colspan="3" class="text-body-secondary">No Site nodes yet.</td></tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>
<script src="<?php echo iaBase(); ?>assets/js/site-management.js"></script>
