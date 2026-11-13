<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-person-gear"></i> Scope for <?php echo html_escape($user->name); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo base_url(); ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo base_url('site'); ?>">Site Management</a></li>
            <li class="breadcrumb-item"><a href="<?php echo base_url('site/access'); ?>">User scope</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo html_escape($user->name); ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid" id="site-management">
    <div class="card">
      <div class="card-body">
        <form method="post" action="<?php echo base_url('site/access_save'); ?>">
          <input type="hidden" name="ia_users_id" value="<?php echo (int) $user->ia_users_id; ?>">
          <div class="mb-3">
            <label class="form-check">
              <input class="form-check-input" type="radio" name="access_level" value="global" <?php echo ($user->access_level ?? 'global') !== 'regional' ? 'checked' : ''; ?>>
              Global
            </label>
            <label class="form-check">
              <input class="form-check-input" type="radio" name="access_level" value="regional" <?php echo ($user->access_level ?? '') === 'regional' ? 'checked' : ''; ?>>
              Regional
            </label>
          </div>
          <div class="mb-3 border rounded p-3" style="max-height: 360px; overflow-y: auto;">
            <?php foreach ($nodes as $node) { ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="node_ids[]" value="<?php echo (int) $node->id; ?>" id="scope-<?php echo (int) $node->id; ?>" <?php echo isset($selected[(int) $node->id]) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="scope-<?php echo (int) $node->id; ?>">
                  <?php echo html_escape($node->name); ?>
                  <span class="badge <?php echo site_node_type_badge_class($node->node_type); ?> ms-1"><?php echo html_escape($node->node_type); ?></span>
                </label>
              </div>
            <?php } ?>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Save scope</button>
            <a class="btn btn-outline-primary" href="<?php echo base_url('site/access'); ?>">Back</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
