<?php $this->load->view('include/header'); ?>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5><i class="bi bi-file-earmark-text"></i> Export Preview - Event Logs</h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('eventlogs') ?>">Event Logs</a></li>
            <li class="breadcrumb-item active" aria-current="page">Export Preview</li>
          </ol>
        </nav>
      </div>
      <div class="col-auto">
        <a href="<?= base_url('eventlogs') ?>" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back to Event Logs
        </a>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Total Records</h6>
                <h3><?= count($logs) ?></h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-list-ul fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Unique Users</h6>
                <h3><?= count(array_unique(array_column($logs, 'user_name'))) ?></h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-people fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Action Types</h6>
                <h3><?= count(array_unique(array_column($logs, 'action_type'))) ?></h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-activity fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Sections</h6>
                <h3><?= count(array_unique(array_column($logs, 'entity_type'))) ?></h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-grid fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Applied Filters -->
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-funnel"></i> Applied Filters</h6>
      </div>
      <div class="card-body">
        <?php if (!empty($filters)): ?>
          <?php foreach ($filters as $key => $value): ?>
            <?php if ($value && $value !== 'all'): ?>
              <span class="badge bg-primary me-2 mb-2">
                <i class="bi bi-tag-fill me-1"></i>
                <strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong> 
                <?= $value ?>
              </span>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted">
            <i class="bi bi-info-circle me-2"></i>
            No filters applied - showing all records
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Export Buttons -->
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-download"></i> Export Options</h6>
      </div>
      <div class="card-body">
        <?php 
        $query_string = $this->input->server('QUERY_STRING');
        $separator = $query_string ? '&' : '';
        ?>
        <a href="<?= base_url('eventlogs/export_data?format=csv' . $separator . $query_string) ?>" 
           class="btn btn-success me-2 mb-2">
          <i class="bi bi-file-earmark-csv me-2"></i> Export CSV
        </a>
        <a href="<?= base_url('eventlogs/export_data?format=excel' . $separator . $query_string) ?>" 
           class="btn btn-primary me-2 mb-2">
          <i class="bi bi-file-earmark-excel me-2"></i> Export Excel
        </a>
        <a href="<?= base_url('eventlogs/export_data?format=pdf' . $separator . $query_string) ?>" 
           class="btn btn-danger me-2 mb-2" target="_blank">
          <i class="bi bi-eye me-2"></i> Preview PDF
        </a>
        <a href="<?= base_url('eventlogs/export_data?format=pdf&download=1' . $separator . $query_string) ?>" 
           class="btn btn-outline-danger me-2 mb-2">
          <i class="bi bi-download me-2"></i> Download PDF
        </a>
      </div>
    </div>

    <!-- Data Preview -->
    <div class="card">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-table"></i> Data Preview</h6>
      </div>
      <div class="card-body">
        <?php if (!empty($logs)): ?>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th>Date Time</th>
                  <th>User</th>
                  <th>Action</th>
                  <th>Section</th>
                  <th>Message</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <?php 
                  $metadata = json_decode($log->metadata, true);
                  $actionClass = '';
                  switch (strtolower($log->action_type)) {
                    case 'create': $actionClass = 'bg-success'; break;
                    case 'update': $actionClass = 'bg-warning text-dark'; break;
                    case 'delete': $actionClass = 'bg-danger'; break;
                    case 'login': $actionClass = 'bg-info'; break;
                    case 'logout': $actionClass = 'bg-secondary'; break;
                    default: $actionClass = 'bg-primary'; break;
                  }
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= date('d/m/Y', strtotime($log->created_at)) ?></div>
                      <div class="small text-muted"><?= date('H:i:s', strtotime($log->created_at)) ?></div>
                    </td>
                    <td>
                      <div class="d-flex align-items-center">
                       
                        <span class="fw-semibold"><?= $log->user_name ?></span>
                      </div>
                    </td>
                    <td>
                      <span class="badge <?= $actionClass ?>">
                        <?= ucfirst($log->action_type) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-secondary">
                        <?= ucfirst(str_replace('_', ' ', $log->entity_type)) ?>
                      </span>
                    </td>
                    <td>
                      <div class="fw-medium"><?= $log->message ?></div>
                    </td>
                    <td>
                      <code class="small"><?= $metadata['ip_address'] ?? '-' ?></code>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3">No data found</h5>
            <p class="text-muted">No event logs match the current filters.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('include/footer'); ?> 