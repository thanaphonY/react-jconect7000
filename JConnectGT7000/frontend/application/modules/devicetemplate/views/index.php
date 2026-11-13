<?php
// frontend/application/modules/devicetemplate/views/index.php
$client = "";
if (isset($client_id)) {
  $client = $client_id;
}
?>




<div class="app-content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h4 class="m-0"> <?php echo lang('device_template') ?></h4>
      </div>

      <div class="col text-end">
        <a href="<?= base_url('devicetemplate/add') ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg"></i><?php echo lang('add_template') ?>
        </a>
      </div>


    </div>
  </div>
</div>


<!--end::App Content Header-->
<!--begin::App Content-->
<div class="app-content">
  <!--begin::Container-->
  <div class="container-fluid">
    <!--begin::Row-->
    <div class="row">
      <div class="col-12">
        <!-- Default box -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0"><?php echo lang('add_template') ?></h4>

          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th><?php echo lang('name') ?></th>
                    <th><?php echo lang('protocol') ?></th>
                    <th><?php echo lang('active') ?></th>
                    <th><?php echo lang('actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($templates as $idx => $t): ?>
                    <tr>
                      <td><?= $idx + 1 ?></td>
                      <td><?= htmlspecialchars($t->name) ?></td>
                      <td><?= htmlspecialchars($t->protocol) ?></td>
                      <td>
                        <?php if ($t->is_active == 't'): ?>
                          <span class="badge text-bg-success"><?php echo lang('yes') ?></span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary"><?php echo lang('no') ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="<?= base_url('devicetemplate/edit/' . $t->id) ?>" class="btn btn-warning btn-sm">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                        <a href="<?= base_url('devicetemplate/delete/' . $t->id) ?>" class="btn btn-danger btn-sm"
                          onclick="return confirm('Are you sure you want to delete this template?')">
                          <i class="bi bi-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>


        <!-- /.card -->
      </div>
    </div>
    <!--end::Row-->
  </div>
  <!--end::Container-->
</div>


<script type="text/javascript">
  $(document).ready(function() {

  });
</script>

<style type="text/css">


</style>