<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><?= lang('reports') ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang('reports') ?></li>
          </ol>
        </nav>
      </div>
      <div class="col text-end">
        <a href="<?= base_url('reports/schedules') ?>" class="btn btn-outline-secondary me-2">
          <i class="bi bi-clock"></i> <?= lang('manage_schedules') ?>
        </a>
        <a href="<?= base_url('reports/files') ?>" class="btn btn-outline-secondary me-2">
          <i class="bi bi-folder2-open"></i> <?= lang('file_manager') ?>
        </a>
        <a href="<?= base_url('reports/builder') ?>" class="btn btn-primary">
          <i class="bi bi-plus-lg"></i> <?= lang('create_new_template') ?>
        </a>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div id="alertContainer"></div>
    <div class="card shadow-sm">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> <?= lang('reports_templates') ?></h5>
      </div>
      <div class="card-body">
        <?php if (empty($templates)): ?>
          <p class="text-muted mb-0"><?= lang('no_templates_yet') ?></p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th><?= lang('name') ?></th>
                  <th><?= lang('description') ?></th>
                  <th><?= lang('updated_at') ?></th>
                  <th width="180"><?= lang('actions') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($templates as $t): ?>
                  <tr>
                    <td><?= htmlspecialchars($t->name) ?></td>
                    <td><?= htmlspecialchars($t->description ?: '-') ?></td>
                    <td><?= $t->updated_at ? date('d/m/Y H:i', strtotime($t->updated_at)) : '-' ?></td>
                    <td>
                      <a href="<?= base_url('reports/run/' . $t->id) ?>" class="btn btn-sm btn-outline-primary" title="<?= lang('run_report') ?>">
                        <i class="bi bi-play"></i>
                      </a>
                      <a href="<?= base_url('reports/builder/' . $t->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= lang('edit') ?>">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-template" data-id="<?= (int)$t->id ?>" data-name="<?= htmlspecialchars($t->name) ?>"  title="<?= lang('delete') ?>">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  $('.btn-delete-template').on('click', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    var confirmMsg = '<?= addslashes(lang('delete_template_confirm')) ?>'.replace('%s', name);
    if (!confirm(confirmMsg)) return;
    $.post('<?= base_url('reports/delete_template/') ?>' + id)
      .done(function(res) {
        if (res.success) {
          window.location.href = res.redirect || '<?= base_url('reports') ?>';
        } else {
          alert(res.message || '<?= addslashes(lang('delete_failed')) ?>');
        }
      })
      .fail(function() { alert('<?= addslashes(lang('error_occurred')) ?>'); });
  });
});
</script>
