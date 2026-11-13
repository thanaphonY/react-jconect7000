
<!--begin::App Content Header-->
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5> <?php echo lang('notification'); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            
          </ol>
        </nav>
      </div>
      <div class="col text-end">
        <a href="<?= base_url('notification_manager/add') ?>" class="btn btn-primary">
          <i class="bi bi-plus-circle"></i> <?php echo lang('add_chanel'); ?>
        </a>
      </div>
    </div>
  </div>
</div>
<!--end::App Content Header-->


<!--begin::App Content-->
<div class="app-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-body table-responsive">
            <table class="table table-striped table-bordered">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th><?php echo lang('name'); ?></th>
                  <th><?php echo lang('type'); ?></th>
                  <th><?php echo lang('status'); ?></th>
                  <th><?php echo lang('description'); ?></th>
                  <th><?php echo lang('action'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($channels as $i => $c): ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= $c->name ?></td>
                    <td><?= strtoupper($c->type) ?></td>
                    <td>
                      <span class="badge <?= $c->is_active == 't' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $c->is_active=='t' ? 'Active' : 'Deactive' ?>
                      </span>
                    </td>
                    <td><?= $c->description ?></td>
                    <td>
                      <a href="<?= base_url('notification_manager/edit/' . $c->id) ?>" class="btn btn-sm btn-warning"><?php echo lang('edit'); ?></a>
                      <a href="<?= base_url('notification_manager/delete/' . $c->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this channel?')"><?php echo lang('delete'); ?></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!--end::App Content-->