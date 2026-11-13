<!--begin::App Content Header-->
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h1><?php echo isset($channel) ? lang("edit_notification") : lang("add_notification"); ?></h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('notification_manager') ?>"><?php echo lang("notification_manager");?> </a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo isset($channel) ? lang("edit") : lang("add"); ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>
<!--end::App Content Header-->

<!--begin::App Content-->
<div class="app-content">
  <div class="container-fluid">
    <form method="post">
      <div class="mb-3">
        <label><?php echo lang("name");?></label>
        <input name="name" class="form-control" value="<?= isset($channel) ? $channel->name : '' ?>" required>
      </div>

      <div class="mb-3">
        <label><?php echo lang("type");?></label>
        <select name="type" id="type" class="form-control" required onchange="renderConfigFields()">
          <option value="line" <?= (isset($channel) && $channel->type === 'line') ? 'selected' : '' ?>><?php echo lang("line");?></option>
          <option value="email" <?= (isset($channel) && $channel->type === 'email') ? 'selected' : '' ?>><?php echo lang("email");?></option>
          <option value="telegram" <?= (isset($channel) && $channel->type === 'telegram') ? 'selected' : '' ?>><?php echo lang("telegram");?></option>
          <option value="webhook" <?= (isset($channel) && $channel->type === 'webhook') ? 'selected' : '' ?>><?php echo lang("webhook");?></option>
        </select>
      </div>

      <div class="mb-3">
        <label><?php echo lang("active");?></label>
        <select name="is_active" class="form-control">
          <option value="1" <?= (isset($channel) && $channel->is_active == 't') ? 'selected' : 'selected' ?>><?php echo lang("active");?></option>
          <option value="0" <?= (isset($channel) && $channel->is_active == 'f') ? 'selected' : '' ?>><?php echo lang("deactive");?></option>
        </select>
      </div>

      <div class="mb-3">
        <label><?php echo lang("message_template");?></label>
        <textarea name="message_template" class="form-control" rows="2" placeholder="e.g. Alert: {{metric}} exceeded threshold"><?= isset($channel) ? ($channel->message_template ?? '') : '{{metric}} = {{value}}' ?></textarea>
      </div>

      <div class="mb-3">
        <label><?php echo lang("description");?></label>
        <textarea name="description" class="form-control" rows="2"><?= isset($channel) ? ($channel->description ?? '') : '' ?></textarea>
      </div>

      <div class="mb-3">
        <label><?php echo lang("config");?></label>
        <div id="config-fields"></div>
      </div>

      <button type="submit" class="btn <?= isset($channel) ? 'btn-primary' : 'btn-success' ?> mt-2">
        <?php echo isset($channel) ? lang("update") : lang("save"); ?>
      </button>
    </form>
  </div>
</div>
<!--end::App Content-->

<script>
let channelConfig = {};
<?php if (isset($channel)): ?>
channelConfig = <?= json_encode(json_decode($channel->config, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>;
<?php endif; ?>

function renderConfigFields() {
  const type = document.getElementById('type').value;
  const container = document.getElementById('config-fields');
  container.innerHTML = '';

  if (type === 'line') {
    container.innerHTML = `
      <label>Access Token</label><input name="config[access_token]" class="form-control" value="${channelConfig.access_token || ''}" required>
      <label>User ID</label><input name="config[to]" class="form-control" value="${channelConfig.to || ''}">
    `;
  } else if (type === 'email') {
    container.innerHTML = `
      <label>SMTP Host</label><input name="config[host]" class="form-control" value="${channelConfig.host || ''}" required>
      <label>SMTP Port</label><input name="config[port]" class="form-control" value="${channelConfig.port || ''}" required>
      <label>Secure (e.g. ssl or tls)</label><input name="config[secure]" class="form-control" value="${channelConfig.secure || ''}">
      <label>Sender Name</label><input name="config[sendername]" class="form-control" value="${channelConfig.sendername || ''}">
      <label>Subject</label><input name="config[subject]" class="form-control" value="${channelConfig.subject || ''}">
      <label>Receivers (comma-separated)</label><input name="config[receivers]" class="form-control" value="${channelConfig.receivers || ''}">
    `;
  } else if (type === 'telegram') {
    container.innerHTML = `
      <label>Bot Token</label><input name="config[bot_token]" class="form-control" value="${channelConfig.bot_token || ''}" required>
      <label>Chat/Group ID</label><input name="config[chat_id]" class="form-control" value="${channelConfig.chat_id || ''}" required>
    `;
  } else if (type === 'webhook') {
    container.innerHTML = `
      <label>Webhook URL</label><input name="config[url]" class="form-control" value="${channelConfig.url || ''}" required>
      <label>Header JSON (optional)</label><textarea name="config[headers]" class="form-control" rows="2">${channelConfig.headers || ''}</textarea>
      <label>Payload Template JSON</label><textarea name="config[payload]" class="form-control" rows="3">${channelConfig.payload || '{ "message": "{{message}}" }'}</textarea>
    `;
  }
}

document.addEventListener('DOMContentLoaded', renderConfigFields);
</script> 