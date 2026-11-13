<section class="container py-4">
  <div class="mb-4">
    <h5><?php echo lang("settings"); ?></h5>
  </div>
  <?php $this->load->view('setting/tabs'); ?>
  <div class="pt-3">
  <h5 class="mb-3">Data retention</h5>
  <p class="text-body-secondary">Deletes operational rows older than the selected number of days. Configuration tables are never deleted by this policy.</p>
  <form method="post" action="<?php echo base_url('retention/save'); ?>">
    <table class="table">
      <thead>
        <tr>
          <th>Table</th>
          <th>Enabled</th>
          <th>Days</th>
          <?php if (!empty($preview)) { ?><th>Rows</th><?php } ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($policies as $table => $policy) { ?>
          <tr>
            <td><code><?php echo html_escape($table); ?></code></td>
            <td>
              <input type="checkbox" name="enabled[<?php echo html_escape($table); ?>]" value="1" <?php echo $policy['enabled'] ? 'checked' : ''; ?>>
            </td>
            <td style="max-width: 140px;">
              <input class="form-control" type="number" min="1" name="days[<?php echo html_escape($table); ?>]" value="<?php echo (int) $policy['days']; ?>">
            </td>
            <?php if (!empty($preview)) { ?>
              <td><?php echo isset($preview[$table]) ? (int) $preview[$table] : 0; ?></td>
            <?php } ?>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    <button class="btn btn-primary" type="submit">Save policy</button>
  </form>
  <form class="d-inline" method="post" action="<?php echo base_url('retention/preview'); ?>">
    <button class="btn btn-outline-secondary mt-3" type="submit">Count rows</button>
  </form>
  <form class="d-inline" method="post" action="<?php echo base_url('retention/run'); ?>">
    <button class="btn btn-warning mt-3" type="submit">Run now</button>
  </form>
  <p class="mt-3 mb-0 text-body-secondary">Daily command: <code>php index.php retention purge</code></p>
  </div>
</section>
