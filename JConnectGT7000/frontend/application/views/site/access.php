<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-people"></i> User scope</h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo base_url(); ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo base_url('site'); ?>">Site Management</a></li>
            <li class="breadcrumb-item active" aria-current="page">User scope</li>
          </ol>
        </nav>
      </div>
      <div class="col text-end">
        <a class="btn btn-outline-primary" href="<?php echo base_url('site'); ?>"><i class="bi bi-arrow-left"></i> Back</a>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid" id="site-management">
    <div class="card">
      <div class="card-body">
        <p class="text-body-secondary">Global users see every node. Regional users see the selected nodes and their children.</p>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr><th>Name</th><th>Type</th><th>Access</th><th>Nodes</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user) { ?>
                <tr>
                  <td><?php echo html_escape($user->name); ?></td>
                  <td><?php echo html_escape($user->user_type); ?></td>
                  <td><span class="badge text-bg-secondary"><?php echo html_escape($user->access_level ?: 'global'); ?></span></td>
                  <td class="text-body-secondary small"><?php echo html_escape(isset($scopes[(int) $user->ia_users_id]) ? implode(', ', $scopes[(int) $user->ia_users_id]) : ''); ?></td>
                  <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="<?php echo base_url('site/access_edit/' . (int) $user->ia_users_id); ?>">Edit</a></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
