<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Widgets</h4>
    <a href="<?= base_url('widgets/add') ?>" class="btn btn-primary btn-sm">+ Add Widget</a>
  </div>
  <table class="table table-bordered table-striped table-sm">
    <thead>
      <tr>
        <th>Name</th>
        <th>Type</th>
        <th>Config</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($widgets as $w): ?>
      <tr>
        <td><?= htmlspecialchars($w->name) ?></td>
        <td><?= htmlspecialchars($w->widget_type) ?></td>
        <td><pre class="mb-0 small"><?= json_encode(json_decode($w->config), JSON_PRETTY_PRINT) ?></pre></td>
        <td>
          <a href="<?= base_url('widgets/edit/' . $w->id) ?>" class="btn btn-sm btn-warning">Edit</a>
          <a href="<?= base_url('widgets/delete/' . $w->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this widget?')">Delete</a>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
