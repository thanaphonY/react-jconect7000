<?php
// This file is part of the LoRaWAN module for a web application.
// application/modules/lorawan/views/index.php
?>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h4>LoRaWAN Management</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">LoRaWAN</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <?php // views/lorawan/index.php (อัปเดตเพิ่ม Edit/Delete แบบ Modal ด้วย)
    ?>
    <?php // views/lorawan/index.php (AJAX Edit Modal)
    ?>
    <div class="container mt-5">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">LoRa Application List</h3>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAppModal">+ Add Application</button>
      </div>

      <?php if ($this->session->flashdata('success')): ?>
        <div class="alert alert-success"> <?= $this->session->flashdata('success') ?> </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header bg-primary text-white">Applications</div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Tenant ID</th>
                <th>Description</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($applications)): ?>
                <?php foreach ($applications as $app): ?>
                  <tr id="row-<?= $app['application_id'] ?>">
                    <td class="app-name"><?= htmlspecialchars($app['name']) ?></td>
                    <td><?= htmlspecialchars($app['tenant_id']) ?></td>
                    <td class="app-description"><?= htmlspecialchars($app['description']) ?></td>
                    <td><?= htmlspecialchars($app['created_at']) ?></td>
                    <td>
                      <button class="btn btn-sm btn-warning edit-btn"
                        data-id="<?= $app['application_id'] ?>"
                        data-name="<?= htmlspecialchars($app['name']) ?>"
                        data-description="<?= htmlspecialchars($app['description']) ?>">
                        Edit
                      </button>
                      <a href="<?= base_url('lorawan/delete_application/' . $app['application_id']) ?>"
                        class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete this application?')">
                        Delete
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">No applications found</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editAppModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form id="editAppForm" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Application</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="application_id" id="editAppId">
            <div class="form-group mb-3">
              <label>Name</label>
              <input type="text" name="name" id="editAppName" class="form-control" required>
            </div>
            <div class="form-group mb-3">
              <label>Description</label>
              <input type="text" name="description" id="editAppDescription" class="form-control">
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Add Application Modal -->
    <div class="modal fade" id="addAppModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post" action="<?= base_url('lorawan/add_application') ?>" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add Application</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="form-group mb-3">
              <label>Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group mb-3">
              <label>Description</label>
              <input type="text" name="description" class="form-control">
            </div>
            <div class="form-group mb-3">
              <label>Tenant ID</label>
              <input type="text" name="tenant_id" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-success">Create</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>


  </div>
</div>



<script>
  $(document).ready(function() {
    document.querySelectorAll('.edit-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('editAppId').value = btn.dataset.id;
        document.getElementById('editAppName').value = btn.dataset.name;
        document.getElementById('editAppDescription').value = btn.dataset.description;
        const modal = new bootstrap.Modal(document.getElementById('editAppModal'));
        modal.show();
      });
    });

    document.getElementById('editAppForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const id = formData.get('application_id');

      fetch('<?= base_url('lorawan/edit_application_ajax') ?>/' + id, {
        method: 'POST',
        body: formData
      }).then(res => res.json()).then(data => {
        if (data.success) {
          const row = document.getElementById('row-' + id);
          row.querySelector('.app-name').innerText = formData.get('name');
          row.querySelector('.app-description').innerText = formData.get('description');
          bootstrap.Modal.getInstance(document.getElementById('editAppModal')).hide();
        } else {
          alert('Update failed');
        }
      });
    });

  });
</script>