<section class="container-fluid py-3" id="api-management">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">API Management</h5>
  </div>

  <?php if (empty($ready)) { ?>
    <div class="alert alert-warning">API tables are not installed. Apply Database/migrations/006_api_clients.sql.</div>
  <?php } else { ?>
    <?php if (!empty($new_token)) { ?>
      <div class="alert alert-success">
        <div class="fw-semibold mb-1">Copy this key now. It will not be shown again.</div>
        <code id="new-api-token"><?php echo html_escape($new_token); ?></code>
        <button type="button" class="btn btn-sm btn-outline-success ms-2" id="copy-api-token">Copy</button>
      </div>
    <?php } ?>

    <div class="card mb-3">
      <div class="card-header">New key</div>
      <div class="card-body">
        <form method="post" action="<?php echo base_url('site/api_create'); ?>" class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label" for="api-name">Name</label>
            <input class="form-control" id="api-name" name="name" required maxlength="200">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="api-node">Scope</label>
            <select class="form-select" id="api-node" name="site_node_id">
              <option value="0">Entire system</option>
              <?php foreach ($nodes as $node) { ?>
                <option value="<?php echo (int) $node->id; ?>"><?php echo html_escape($node->name . ' (' . $node->node_type . ')'); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="api-expires">Expires</label>
            <input class="form-control" id="api-expires" name="expires_at" type="datetime-local">
          </div>
          <div class="col-md-2">
            <label class="form-label" for="api-rate">Requests / minute</label>
            <input class="form-control" id="api-rate" name="rate_limit_per_minute" type="number" min="1" value="60" required>
          </div>
          <div class="col-md-1">
            <button class="btn btn-primary w-100" type="submit">Create</button>
          </div>
        </form>
      </div>
    </div>

    <?php foreach ($clients as $client) { ?>
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between gap-3">
            <div>
              <div class="fw-semibold"><?php echo html_escape($client->name); ?></div>
              <div class="text-body-secondary small">
                <?php echo html_escape($client->token_prefix); ?>…
                · <?php echo $client->node_name ? html_escape($client->node_name . ' (' . $client->node_type . ')') : 'Entire system'; ?>
                · <?php echo $client->active === 't' || $client->active === true || $client->active === '1' ? 'Active' : 'Revoked'; ?>
                · Expires <?php echo $client->expires_at ? html_escape($client->expires_at) : 'never'; ?>
                · Last used <?php echo $client->last_used_at ? html_escape($client->last_used_at) : 'never'; ?>
              </div>
              <div class="small mt-1">
                This hour <?php echo (int) $client->count_hour; ?>
                · Today <?php echo (int) $client->count_day; ?>
                · This month <?php echo (int) $client->count_month; ?>
              </div>
            </div>
            <?php if ($client->active === 't' || $client->active === true || $client->active === '1') { ?>
              <form method="post" action="<?php echo base_url('site/api_revoke'); ?>" onsubmit="return confirm('Revoke this key?');">
                <input type="hidden" name="id" value="<?php echo (int) $client->id; ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit">Revoke</button>
              </form>
            <?php } ?>
          </div>
          <form method="post" action="<?php echo base_url('site/api_update'); ?>" class="row g-2 align-items-end mt-2">
            <input type="hidden" name="id" value="<?php echo (int) $client->id; ?>">
            <div class="col-md-4">
              <label class="form-label">Expires</label>
              <input class="form-control" name="expires_at" type="datetime-local" value="<?php echo $client->expires_at ? html_escape(date('Y-m-d\TH:i', strtotime($client->expires_at))) : ''; ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Requests / minute</label>
              <input class="form-control" name="rate_limit_per_minute" type="number" min="1" value="<?php echo (int) $client->rate_limit_per_minute; ?>" required>
            </div>
            <div class="col-md-2">
              <button class="btn btn-outline-primary" type="submit">Save</button>
            </div>
          </form>
          <?php if (!empty($client->recent_logs)) { ?>
            <div class="table-responsive mt-3">
              <table class="table table-sm mb-0">
                <thead><tr><th>Time</th><th>Request</th><th>Status</th><th>IP</th></tr></thead>
                <tbody>
                  <?php foreach ($client->recent_logs as $log) { ?>
                    <tr>
                      <td><?php echo html_escape($log->created_at); ?></td>
                      <td><?php echo html_escape($log->method . ' ' . $log->path); ?></td>
                      <td><?php echo (int) $log->status_code; ?><?php echo $log->error_code ? ' ' . html_escape($log->error_code) : ''; ?></td>
                      <td><?php echo html_escape($log->client_ip); ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } ?>
        </div>
      </div>
    <?php } ?>
    <?php if (!$clients) { ?><p class="text-body-secondary">No keys yet.</p><?php } ?>
  <?php } ?>
</section>
<script>
  var copyBtn = document.getElementById('copy-api-token');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var text = document.getElementById('new-api-token').textContent;
      navigator.clipboard.writeText(text);
    });
  }
</script>
