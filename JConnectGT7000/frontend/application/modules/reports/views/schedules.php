<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-clock"></i> <?= lang('reports_schedules') ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>"><?= lang('reports') ?></a></li>
            <li class="breadcrumb-item active"><?= lang('reports_schedules') ?></li>
          </ol>
        </nav>
      </div>
      <div class="col text-end">
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary me-2">
          <i class="bi bi-arrow-left"></i> <?= lang('back_to_templates') ?>
        </a>
        <button type="button" class="btn btn-primary" id="btnAddSchedule">
          <i class="bi bi-plus-lg"></i> <?= lang('add_schedule') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div id="alertContainer"></div>
    <div class="card shadow-sm">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calendar3"></i> <?= lang('report_schedules') ?></h5>
      </div>
      <div class="card-body">
        <?php if (empty($schedules)): ?>
          <p class="text-muted mb-0"><?= lang('no_schedules_yet') ?></p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th><?= lang('template') ?></th>
                  <th><?= lang('schedule_name') ?></th>
                  <th><?= lang('type') ?></th>
                  <th><?= lang('time') ?></th>
                  <th><?= lang('format') ?></th>
                  <th><?= lang('next_run') ?></th>
                  <th><?= lang('status') ?></th>
                  <th width="100"><?= lang('actions') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($schedules as $s):
                  $config = is_string($s->schedule_config) ? json_decode($s->schedule_config, true) : (array)($s->schedule_config ?? []);
                  $time = $config['time'] ?? '08:00';
                  $dataJson = htmlspecialchars(json_encode([
                    'id' => (int)$s->id,
                    'template_id' => (int)$s->template_id,
                    'name' => $s->name,
                    'schedule_type' => $s->schedule_type,
                    'schedule_time' => $time,
                    'schedule_weekday' => (int)($config['weekday'] ?? 1),
                    'schedule_day' => (int)($config['day'] ?? 1),
                    'export_format' => $s->export_format,
                    'recipients' => is_string($s->recipients) ? json_decode($s->recipients, true) : [],
                    'is_active' => (bool)$s->is_active,
                  ]), ENT_QUOTES, 'UTF-8');
                ?>
                  <tr>
                    <td><?= htmlspecialchars($s->template_name ?: '-') ?></td>
                    <td><?= htmlspecialchars($s->name) ?></td>
                    <td><?= htmlspecialchars(ucfirst($s->schedule_type)) ?></td>
                    <td><?= htmlspecialchars($time) ?></td>
                    <td><span class="badge bg-secondary"><?= strtoupper($s->export_format) ?></span></td>
                    <td><?= $s->next_run_at ? date('d/m/Y H:i', strtotime($s->next_run_at)) : '-' ?></td>
                    <td>
                      <?php if ($s->is_active): ?>
                        <span class="badge bg-success"><?= lang('active') ?></span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><?= lang('inactive') ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-schedule" data-schedule="<?= $dataJson ?>" title="<?= lang('edit') ?>">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-schedule" data-id="<?= (int)$s->id ?>" data-name="<?= htmlspecialchars($s->name) ?>" title="<?= lang('delete') ?>">
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

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="scheduleModalTitle"><?= lang('add_schedule_modal') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="scheduleForm">
          <input type="hidden" name="id" id="scheduleId">
          <input type="hidden" name="template_id" id="scheduleTemplateIdHidden">
          <div class="mb-2">
            <label class="form-label small"><?= lang('template') ?> <span class="text-danger">*</span></label>
            <select id="scheduleTemplateId" class="form-select form-select-sm" required>
              <option value=""><?= lang('select_template') ?></option>
              <?php foreach ($templates as $t): ?>
                <option value="<?= (int)$t->id ?>"><?= htmlspecialchars($t->name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small"><?= lang('schedule_name') ?> <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="name" required placeholder="<?= lang('schedule_name_placeholder') ?>">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small"><?= lang('type') ?></label>
              <select name="schedule_type" id="scheduleType" class="form-select form-select-sm">
                <option value="daily"><?= lang('daily') ?></option>
                <option value="weekly"><?= lang('weekly') ?></option>
                <option value="monthly"><?= lang('monthly') ?></option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small"><?= lang('time') ?></label>
              <input type="time" name="schedule_time" class="form-control form-control-sm" value="08:00">
            </div>
          </div>
          <div id="scheduleWeeklyRow" class="mb-2 d-none">
            <label class="form-label small"><?= lang('day_of_week') ?></label>
            <select name="schedule_weekday" class="form-select form-select-sm">
              <option value="1"><?= lang('monday') ?></option>
              <option value="2"><?= lang('tuesday') ?></option>
              <option value="3"><?= lang('wednesday') ?></option>
              <option value="4"><?= lang('thursday') ?></option>
              <option value="5"><?= lang('friday') ?></option>
              <option value="6"><?= lang('saturday') ?></option>
              <option value="7"><?= lang('sunday') ?></option>
            </select>
          </div>
          <div id="scheduleMonthlyRow" class="mb-2 d-none">
            <label class="form-label small"><?= lang('day_of_month') ?></label>
            <input type="number" name="schedule_day" class="form-control form-control-sm" min="1" max="28" value="1">
          </div>
          <div class="mb-2">
            <label class="form-label small"><?= lang('export_format') ?></label>
            <select name="export_format" class="form-select form-select-sm">
              <option value="pdf">PDF</option>
              <option value="excel">Excel</option>
              <option value="csv">CSV</option>
            </select>
          </div>
          <!--
          <div class="mb-2">
            <label class="form-label small">Recipients (email, one per line)</label>
            <textarea name="recipients" class="form-control form-control-sm" rows="2" placeholder="user@example.com"></textarea>
          </div> -->
          <input type="hidden" name="recipients" id="scheduleRecipients" value="">
          <div class="form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="scheduleActive" value="1" checked>
            <label class="form-check-label small" for="scheduleActive"><?= lang('active') ?></label>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= lang('close') ?></button>
        <button type="button" class="btn btn-primary btn-sm" id="btnSaveSchedule"><?= lang('save_schedule') ?></button>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  var scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));

  function toggleScheduleExtras() {
    var t = $('#scheduleType').val();
    $('#scheduleWeeklyRow').toggleClass('d-none', t !== 'weekly');
    $('#scheduleMonthlyRow').toggleClass('d-none', t !== 'monthly');
  }
  $('#scheduleType').on('change', toggleScheduleExtras);

  $('#btnAddSchedule').on('click', function() {
    $('#scheduleModalTitle').text('<?= addslashes(lang('add_schedule_modal')) ?>');
    $('#scheduleId').val('');
    $('#scheduleForm')[0].reset();
    $('#scheduleTemplateId').prop('disabled', false).attr('name', 'template_id');
    $('#scheduleTemplateIdHidden').removeAttr('name').val('');
    $('#scheduleForm input[name="schedule_time"]').val('08:00');
    toggleScheduleExtras();
    scheduleModal.show();
  });

  $(document).on('click', '.btn-edit-schedule', function() {
    var d = $(this).data('schedule');
    if (!d) return;
    $('#scheduleModalTitle').text('<?= addslashes(lang('edit_schedule_modal')) ?>');
    $('#scheduleId').val(d.id);
    $('#scheduleTemplateId').val(d.template_id).prop('disabled', true).removeAttr('name');
    $('#scheduleTemplateIdHidden').val(d.template_id).attr('name', 'template_id');
    $('#scheduleForm input[name="name"]').val(d.name);
    $('#scheduleType').val(d.schedule_type || 'daily');
    $('#scheduleForm input[name="schedule_time"]').val(d.schedule_time || '08:00');
    $('#scheduleForm select[name="schedule_weekday"]').val(d.schedule_weekday || 1);
    $('#scheduleForm input[name="schedule_day"]').val(d.schedule_day || 1);
    $('#scheduleForm select[name="export_format"]').val(d.export_format || 'pdf');
    $('#scheduleForm textarea[name="recipients"]').val(Array.isArray(d.recipients) ? d.recipients.join('\n') : '');
    $('#scheduleActive').prop('checked', d.is_active !== false);
    toggleScheduleExtras();
    scheduleModal.show();
  });

  $('#btnSaveSchedule').on('click', function() {
    var $f = $('#scheduleForm');
    if (!$f[0].checkValidity()) {
      $f[0].reportValidity();
      return;
    }
    $.post('<?= base_url('reports/schedule_save') ?>', $f.serialize()).done(function(res) {
      if (res.success) {
        window.location.reload();
      } else {
        alert(res.message || '<?= addslashes(lang('save_failed')) ?>');
      }
    }).fail(function() {
      alert('An error occurred');
    });
  });

  $(document).on('click', '.btn-delete-schedule', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    var confirmMsg = '<?= addslashes(lang('delete_schedule_confirm')) ?>'.replace('%s', name);
    if (!confirm(confirmMsg)) return;
    $.post('<?= base_url('reports/schedule_delete/') ?>' + id).done(function(res) {
      if (res.success) window.location.reload();
      else alert(res.message || '<?= addslashes(lang('delete_failed')) ?>');
    }).fail(function() {
      alert('An error occurred');
    });
  });
});
</script>
