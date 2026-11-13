<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-folder2-open"></i> <?= lang('reports_files') ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>"><?= lang('reports') ?></a></li>
            <li class="breadcrumb-item active"><?= lang('reports_files') ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="card shadow-sm">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-download"></i> <?= lang('export_files') ?></h6>
      </div>
      <div class="card-body">
        <form method="get" class="row g-2 mb-3">
          <div class="col-md-3">
            <label class="form-label small"><?= lang('report') ?></label>
            <select name="template_id" class="form-select form-select-sm">
              <option value="">-- <?= lang('all') ?> --</option>
              <?php foreach ($templates as $t): ?>
                <option value="<?= (int)$t->id ?>" <?= ($template_id ?? 0) == $t->id ? 'selected' : '' ?>><?= htmlspecialchars($t->name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small"><?= lang('format') ?></label>
            <select name="format" class="form-select form-select-sm">
              <option value="">-- <?= lang('all') ?> --</option>
              <option value="pdf" <?= ($format ?? '') === 'pdf' ? 'selected' : '' ?>>PDF</option>
              <option value="excel" <?= ($format ?? '') === 'excel' ? 'selected' : '' ?>>Excel</option>
              <option value="csv" <?= ($format ?? '') === 'csv' ? 'selected' : '' ?>>CSV</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small"><?= lang('from') ?></label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small"><?= lang('to') ?></label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to ?? '') ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> <?= lang('filter') ?></button>
          </div>
        </form>

        <?php if (empty($items)): ?>
          <p class="text-muted mb-0"><?= lang('no_files_yet') ?></p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead>
                <tr>
                  <th><?= lang('report') ?></th>
                  <th><?= lang('file_name') ?></th>
                  <th><?= lang('format') ?></th>
                  <th><?= lang('size') ?></th>
                  <th><?= lang('exported_at') ?></th>
                  <th><?= lang('source') ?></th>
                  <th width="120"><?= lang('action') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $f): ?>
                  <tr>
                    <td><?= htmlspecialchars($f['template_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($f['file_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= strtoupper($f['export_format']) ?></span></td>
                    <td><?= number_format($f['file_size'] / 1024, 1) ?> KB</td>
                    <td><?php
                      $dt = new DateTime($f['exported_at'], new DateTimeZone('UTC'));
                      $dt->setTimezone(new DateTimeZone('Asia/Bangkok'));
                      echo $dt->format('d/m/Y H:i');
                    ?></td>
                    <td><?= !empty($f['schedule_id']) ? lang('scheduled') : lang('manual') ?></td>
                    <td>
                      <a href="<?= base_url('reports/download_file/' . $f['id']) ?>" class="btn btn-sm btn-outline-primary" title="<?= lang('download') ?>">
                        <i class="bi bi-download"></i>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-file" data-id="<?= (int)$f['id'] ?>" data-name="<?= htmlspecialchars($f['file_name']) ?>" title="<?= lang('delete') ?>">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php
            $from_row = (($page - 1) * $per_page) + 1;
            $to_row = min($total, $page * $per_page);
            $query = array_filter([
              'template_id' => $template_id ?? '',
              'format' => $format ?? '',
              'date_from' => $date_from ?? '',
              'date_to' => $date_to ?? '',
            ], function ($value) {
              return $value !== null && $value !== '';
            });
            $page_url = function ($n) use ($query) {
              $query['page'] = $n;
              return base_url('reports/files') . '?' . http_build_query($query);
            };
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
          ?>
          <?php if ($total_pages > 1): ?>
            <nav class="mt-3" aria-label="Export files pagination">
              <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <?php if ($page <= 1): ?>
                    <span class="page-link"><?= lang('pager_previous') ?></span>
                  <?php else: ?>
                    <a class="page-link" href="<?= $page_url($page - 1) ?>"><?= lang('pager_previous') ?></a>
                  <?php endif; ?>
                </li>
                <?php if ($start_page > 1): ?>
                  <li class="page-item"><a class="page-link" href="<?= $page_url(1) ?>">1</a></li>
                  <?php if ($start_page > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <?php if ($i === $page): ?>
                      <span class="page-link"><?= $i ?></span>
                    <?php else: ?>
                      <a class="page-link" href="<?= $page_url($i) ?>"><?= $i ?></a>
                    <?php endif; ?>
                  </li>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                  <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                  <li class="page-item"><a class="page-link" href="<?= $page_url($total_pages) ?>"><?= $total_pages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                  <?php if ($page >= $total_pages): ?>
                    <span class="page-link"><?= lang('pager_next') ?></span>
                  <?php else: ?>
                    <a class="page-link" href="<?= $page_url($page + 1) ?>"><?= lang('pager_next') ?></a>
                  <?php endif; ?>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
          <div class="text-center mt-2">
            <small class="text-muted"><?= sprintf(lang('pager_showing'), $from_row, $to_row, $total) ?></small>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  $(document).on('click', '.btn-delete-file', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    var confirmMsg = '<?= addslashes(lang('delete_file_confirm')) ?>'.replace('%s', name);
    if (!confirm(confirmMsg)) return;
    $.post('<?= base_url('reports/delete_file/') ?>' + id).done(function(res) {
      if (res.success) {
        window.location.reload();
      } else {
        alert(res.message || '<?= addslashes(lang('delete_failed')) ?>');
      }
    }).fail(function() {
      alert('<?= addslashes(lang('error_occurred')) ?>');
    });
  });
});
</script>
