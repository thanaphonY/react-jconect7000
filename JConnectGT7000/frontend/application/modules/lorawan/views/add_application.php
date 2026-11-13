<div class="container mt-4">
  <h3>Create New Application</h3>

  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php elseif ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?= $this->session->flashdata('success') ?></div>
  <?php endif; ?>

  <form method="post" action="<?= base_url('lorawan/add_application') ?>">
    <div class="form-group">
      <label>Application Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>

    <div class="form-group">
      <label>Description</label>
      <input type="text" name="description" class="form-control">
    </div>

    <div class="form-group">
      <label>Tenant ID</label>
      <input type="text" name="tenant_id" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-success">Create Application</button>
  </form>
</div>
