<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-heart-pulse"></i> <?= lang('health_check') ?></h5>
      </div>
      <div class="col text-end">
        <button type="button" class="btn btn-primary" id="btnAddHealth">
          <i class="bi bi-plus-circle"></i> <?= lang('add_health_check') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" href="<?= base_url('notification_manager') ?>"><?= lang('notification_channels_tab') ?></a>
      </li>
      <li class="nav-item">
        <a class="nav-link active" href="<?= base_url('notification_manager/health_checks') ?>"><?= lang('health_check') ?></a>
      </li>
    </ul>

    <div class="card">
      <div class="card-body table-responsive">
        <?php if (empty($checks)): ?>
          <p class="text-muted mb-0"><?= lang('no_health_checks') ?></p>
        <?php else: ?>
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th><?= lang('name') ?></th>
                <th><?= lang('schedule') ?></th>
                <th><?= lang('next_run') ?></th>
                <th><?= lang('last_run') ?></th>
                <th><?= lang('status') ?></th>
                <th width="140"><?= lang('action') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($checks as $check): ?>
                <?php
                  $when = ucfirst($check->schedule_type) . ' ' . $check->schedule_time;
                  $payload = [
                    'id' => (int) $check->id,
                    'name' => $check->name,
                    'schedule_type' => $check->schedule_type,
                    'schedule_time' => $check->schedule_time,
                    'schedule_weekday' => $check->schedule_weekday,
                    'schedule_day' => $check->schedule_day,
                    'channel_ids' => $check->channel_ids,
                    'message_template' => $check->message_template,
                    'is_active' => (bool) $check->is_active,
                  ];
                ?>
                <tr>
                  <td><?= htmlspecialchars($check->name) ?></td>
                  <td><?= htmlspecialchars($when) ?></td>
                  <td><?= htmlspecialchars($check->next_run_label) ?></td>
                  <td><?= htmlspecialchars($check->last_run_label) ?></td>
                  <td>
                    <span class="badge <?= $check->is_active ? 'bg-success' : 'bg-secondary' ?>">
                      <?= $check->is_active ? lang('active') : lang('deactive') ?>
                    </span>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-send-health" data-id="<?= (int) $check->id ?>" title="<?= lang('send_now') ?>">
                      <i class="bi bi-send"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning btn-edit-health" data-health="<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>" title="<?= lang('edit') ?>">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-health" data-id="<?= (int) $check->id ?>" data-name="<?= htmlspecialchars($check->name) ?>" title="<?= lang('delete') ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="healthModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="healthModalTitle"><?= lang('add_health_check') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="healthForm">
          <input type="hidden" name="id" id="healthId">
          <div class="mb-2">
            <label class="form-label small"><?= lang('name') ?></label>
            <input type="text" class="form-control form-control-sm" name="name" required>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label small"><?= lang('schedule') ?></label>
              <select name="schedule_type" id="healthType" class="form-select form-select-sm">
                <option value="daily"><?= lang('daily') ?></option>
                <option value="weekly"><?= lang('weekly') ?></option>
                <option value="monthly"><?= lang('monthly') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small"><?= lang('time') ?></label>
              <input type="time" name="schedule_time" class="form-control form-control-sm" value="08:00">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="healthActive" value="1" checked>
                <label class="form-check-label" for="healthActive"><?= lang('active') ?></label>
              </div>
            </div>
          </div>
          <div id="healthWeekly" class="mb-2 d-none">
            <label class="form-label small"><?= lang('day_of_week') ?></label>
            <select name="schedule_weekday" class="form-select form-select-sm">
              <?php for ($d = 1; $d <= 7; $d++): ?>
                <option value="<?= $d ?>"><?= lang('weekday_' . $d) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div id="healthMonthly" class="mb-2 d-none">
            <label class="form-label small"><?= lang('day_of_month') ?></label>
            <input type="number" name="schedule_day" class="form-control form-control-sm" min="1" max="28" value="1">
          </div>
          <div class="mb-2">
            <label class="form-label small"><?= lang('channels') ?></label>
            <div class="border rounded p-2">
              <?php foreach ($channels as $channel): ?>
                <div class="form-check">
                  <input class="form-check-input health-channel" type="checkbox" name="channel_ids[]" value="<?= (int) $channel->id ?>" id="ch<?= (int) $channel->id ?>">
                  <label class="form-check-label" for="ch<?= (int) $channel->id ?>"><?= htmlspecialchars($channel->name) ?> (<?= strtoupper($channel->type) ?>)</label>
                </div>
              <?php endforeach; ?>
              <?php if (empty($channels)): ?>
                <span class="text-muted small"><?= lang('no_channels') ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="mb-2">
            <div class="d-flex justify-content-between">
              <label class="form-label small mb-0"><?= lang('message_template') ?></label>
              <button type="button" class="btn btn-link btn-sm p-0" id="btnDefaultTemplate"><?= lang('use_default_template') ?></button>
            </div>
            <textarea name="message_template" id="healthTemplate" class="form-control font-monospace" rows="8"></textarea>
            <div class="form-text">{name} {datetime} {status} {alarms_count} {alarms}<br><?= lang('metric_placeholder_hint') ?></div>
          </div>
          <pre id="healthPreview" class="small bg-body-secondary p-2 rounded d-none mb-0"></pre>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnPreviewHealth"><?= lang('preview_message') ?></button>
        <button type="button" class="btn btn-primary" id="btnSaveHealth"><?= lang('save') ?></button>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  var modal = new bootstrap.Modal(document.getElementById('healthModal'));
  var defaultTemplate = <?= json_encode($default_template) ?>;

  function toggleSchedule() {
    var type = $('#healthType').val();
    $('#healthWeekly').toggleClass('d-none', type !== 'weekly');
    $('#healthMonthly').toggleClass('d-none', type !== 'monthly');
  }
  $('#healthType').on('change', toggleSchedule);

  function fillForm(data) {
    $('#healthId').val(data.id || '');
    $('#healthForm input[name="name"]').val(data.name || '');
    $('#healthType').val(data.schedule_type || 'daily');
    $('#healthForm input[name="schedule_time"]').val(data.schedule_time || '08:00');
    $('#healthForm select[name="schedule_weekday"]').val(data.schedule_weekday || 1);
    $('#healthForm input[name="schedule_day"]').val(data.schedule_day || 1);
    $('#healthActive').prop('checked', data.is_active !== false);
    $('#healthTemplate').val(data.message_template || defaultTemplate);
    $('.health-channel').prop('checked', false);
    (data.channel_ids || []).forEach(function(id) {
      $('#ch' + id).prop('checked', true);
    });
    $('#healthPreview').addClass('d-none').text('');
    toggleSchedule();
  }

  $('#btnAddHealth').on('click', function() {
    $('#healthModalTitle').text('<?= addslashes(lang('add_health_check')) ?>');
    fillForm({ is_active: true, message_template: defaultTemplate, schedule_time: '08:00' });
    modal.show();
  });
  $('#btnDefaultTemplate').on('click', function() {
    $('#healthTemplate').val(defaultTemplate);
  });
  $(document).on('click', '.btn-edit-health', function() {
    $('#healthModalTitle').text('<?= addslashes(lang('edit_health_check')) ?>');
    fillForm($(this).data('health') || {});
    modal.show();
  });
  $('#btnSaveHealth').on('click', function() {
    var form = document.getElementById('healthForm');
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    $.post('<?= base_url('notification_manager/health_check_save') ?>', $('#healthForm').serialize()).done(function(res) {
      if (res.success) window.location.reload();
      else alert(res.message || 'Save failed');
    }).fail(function() { alert('An error occurred'); });
  });
  $('#btnPreviewHealth').on('click', function() {
    $.post('<?= base_url('notification_manager/health_check_preview') ?>', $('#healthForm').serialize()).done(function(res) {
      $('#healthPreview').removeClass('d-none').text(res.message || '');
    }).fail(function() { alert('An error occurred'); });
  });
  $(document).on('click', '.btn-send-health', function() {
    var id = $(this).data('id');
    $.post('<?= base_url('notification_manager/health_check_send/') ?>' + id).done(function(res) {
      alert(res.success ? '<?= addslashes(lang('sent')) ?>' : '<?= addslashes(lang('send_failed')) ?>');
    }).fail(function() { alert('An error occurred'); });
  });
  $(document).on('click', '.btn-delete-health', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    if (!confirm('<?= addslashes(lang('delete_health_confirm')) ?>'.replace('%s', name))) return;
    $.post('<?= base_url('notification_manager/health_check_delete/') ?>' + id).done(function(res) {
      if (res.success) window.location.reload();
      else alert('Delete failed');
    });
  });
});
</script>
