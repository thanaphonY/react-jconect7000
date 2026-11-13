<?php $this->load->view('include/header'); ?>

<?php
$logs = isset($logs) && is_array($logs) ? $logs : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
?>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5><i class="bi bi-file-earmark-text"></i> Export Preview - Control Logs</h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('controllogs') ?>"><?php echo lang('controllogs') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Export Preview</li>
          </ol>
        </nav>
      </div>
      <div class="col-auto">
        <a href="<?= base_url('controllogs') ?>" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back to Control Logs
        </a>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
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
                <h6 class="card-title">Written</h6>
                <h3><?= count(array_filter($logs, function ($log) { return ($log->result ?? '') === 'written'; })) ?></h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-check-circle fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-danger text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="card-title">Failed</h6>
                <h3><?= count(array_filter($logs, function ($log) { return ($log->result ?? '') === 'failed'; })) ?></h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-x-circle fs-1"></i>
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
                <h6 class="card-title">Devices</h6>
                <h3><?= count(array_unique(array_filter(array_column($logs, 'device_name')))) ?></h3>
              </div>
              <div class="align-self-center">
                <i class="bi bi-cpu fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-funnel"></i> Applied Filters</h6>
      </div>
      <div class="card-body">
        <?php
        $hasFilter = false;
        foreach ($filters as $key => $value):
          if ($value && $value !== 'all'):
            $hasFilter = true;
        ?>
          <span class="badge bg-primary me-2 mb-2">
            <i class="bi bi-tag-fill me-1"></i>
            <strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong>
            <?= htmlspecialchars($value) ?>
          </span>
        <?php
          endif;
        endforeach;
        if (!$hasFilter):
        ?>
          <div class="text-muted">
            <i class="bi bi-info-circle me-2"></i>
            No filters applied - showing all records
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-download"></i> Export Options</h6>
      </div>
      <div class="card-body">
        <?php
        $query_string = $this->input->server('QUERY_STRING');
        $separator = $query_string ? '&' : '';
        ?>
        <a href="<?= base_url('controllogs/export_data?format=csv' . $separator . $query_string) ?>"
           class="btn btn-success me-2 mb-2">
          <i class="bi bi-file-earmark-csv me-2"></i> Export CSV
        </a>
        <a href="<?= base_url('controllogs/export_data?format=excel' . $separator . $query_string) ?>"
           class="btn btn-primary me-2 mb-2">
          <i class="bi bi-file-earmark-excel me-2"></i> Export Excel
        </a>
        <a href="<?= base_url('controllogs/export_data?format=pdf' . $separator . $query_string) ?>"
           class="btn btn-danger me-2 mb-2" target="_blank">
          <i class="bi bi-eye me-2"></i> Preview PDF
        </a>
        <a href="<?= base_url('controllogs/export_data?format=pdf&download=1' . $separator . $query_string) ?>"
           class="btn btn-outline-danger me-2 mb-2">
          <i class="bi bi-download me-2"></i> Download PDF
        </a>
      </div>
    </div>

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
                  <th>Command</th>
                  <th>Device</th>
                  <th>Group</th>
                  <th>Source</th>
                  <th>Value</th>
                  <th>Result</th>
                  <th>User</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <?php
                  $resultClass = ($log->result ?? '') === 'written' ? 'bg-success' : (($log->result ?? '') === 'failed' ? 'bg-danger' : 'bg-secondary');
                  $value = $this->Controllogs_model->format_value_label($log->requested_value ?? '');
                  $valueClass = $value === 'ON' ? 'bg-success' : ($value === 'OFF' ? 'bg-secondary' : 'bg-light text-dark');
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= date('d/m/Y', strtotime($log->created_at)) ?></div>
                      <div class="small text-muted"><?= date('H:i:s', strtotime($log->created_at)) ?></div>
                    </td>
                    <td><?= htmlspecialchars($log->command_name ?: ('#' . ($log->command_id ?? ''))) ?></td>
                    <td><?= htmlspecialchars($log->device_name ?: '-') ?></td>
                    <td><?= htmlspecialchars($log->group_name ?: lang('ungrouped')) ?></td>
                    <td>
                      <span class="badge bg-primary"><?= htmlspecialchars($log->source ?: '-') ?></span>
                      <?php if (!empty($log->sequence_name) || !empty($log->rule_name)): ?>
                        <div class="small text-muted"><?= htmlspecialchars($this->Controllogs_model->format_source_label($log)) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $valueClass ?>"><?= htmlspecialchars($value) ?></span></td>
                    <td>
                      <span class="badge <?= $resultClass ?>"><?= htmlspecialchars($log->result ?: '-') ?></span>
                      <?php if (!empty($log->error)): ?>
                        <div class="small text-muted"><?= htmlspecialchars($log->error) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($log->user_name ?: '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3">No data found</h5>
            <p class="text-muted">No control logs match the current filters.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('include/footer'); ?>
