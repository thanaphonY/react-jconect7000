<div class="container mt-5">
  <h3>Add Gateway</h3>
  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>
  <form method="post" action="<?= base_url('lorawan/gateway/add') ?>">
    <div class="form-group">
      <label>Gateway ID</label>
      <input type="text" name="gateway_id" class="form-control" required>
    </div>

    <div class="form-group">
      <label>Gateway Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>

    <div class="form-group">
      <label>Description</label>
      <input type="text" name="description" class="form-control">
    </div>

    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Latitude</label>
        <input type="text" name="latitude" class="form-control">
      </div>
      <div class="form-group col-md-4">
        <label>Longitude</label>
        <input type="text" name="longitude" class="form-control">
      </div>
      <div class="form-group col-md-4">
        <label>Altitude</label>
        <input type="text" name="altitude" class="form-control">
      </div>
    </div>

    <div class="form-group">
      <label>Network Server ID</label>
      <input type="text" name="network_server_id" class="form-control" required>
    </div>

    <div class="form-group">
      <label>Organization ID</label>
      <input type="text" name="organization_id" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">Create Gateway</button>
  </form>
</div>
