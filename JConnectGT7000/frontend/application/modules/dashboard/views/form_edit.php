<?php
$client = "";
if (isset($client_id)) {
  $client = $client_id;
}
?>

<div class="app-content-header">
  <!--begin::Container-->
  <div class="container-fluid">
    <!--begin::Row-->
    <div class="row">
      <div class="col">
        <h5><?php echo lang('add_news_dashboard_page'); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url("dashboard") ?>"><?php echo lang('dashboard') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"> <?php echo lang('add_news_dashboard_page') ?> </li>
          </ol>
        </nav>
      </div>
      <div class="col text-end">

      </div>
    </div>
    <!--end::Row-->
  </div>
  <!--end::Container-->
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

          <div class="card-body">

            <form method="post" action="<?= base_url('dashboard/update/' . $layout->id) ?>">
              <div class="form-group">
                <label for="name"><?php echo lang('layout_name'); ?></label>
                <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($layout->name) ?>" required>
              </div>

              <div class="form-group">
                <label for="description"><?php echo lang('description'); ?></label>
                <textarea name="description" id="description" class="form-control"><?= htmlspecialchars($layout->description) ?></textarea>
              </div>

              <div class="form-group mt-2">
                <label for="sort_order">Order</label>
                <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" step="1" value="<?= htmlspecialchars((string)($layout->sort_order ?? '')) ?>" placeholder="0">
                <small class="text-muted">Lower number appears first in dashboard lists and the top dropdown.</small>
              </div>

              <button type="submit" class="btn btn-primary"><?php echo lang('save'); ?></button>
              <a href="<?= base_url('dashboard') ?>" class="btn btn-secondary"><?php echo lang('back'); ?></a>
            </form>


          </div>

          <!-- /.card-footer-->
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