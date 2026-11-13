<?php
// Bootstrap 5 refactor of index.php settings page
?>
<section class="container py-4">
  <div class="row">
    <div class="col-12">
      <div class="mb-4">
        <h5><?php echo lang("settings"); ?></h5>
      </div>
      <?php $this->load->view('setting/tabs'); ?>

      <div class="tab-content pt-3" id="settingsTabContent">
        <div class="tab-pane fade show active" id="general-setting" role="tabpanel">
          <?php $this->load->view('setting/general_view'); ?>
        </div>
        <div class="tab-pane fade" id="email-setting" role="tabpanel">
          <?php $this->load->view('setting/email_view'); ?>
        </div>
        <div class="tab-pane fade" id="permission-setting" role="tabpanel">
          <?php $this->load->view('setting/permissions_view'); ?>
        </div>
        <div class="tab-pane fade" id="templates-setting" role="tabpanel">
          <?php $this->load->view('templates/index'); ?>
        </div>
   
     
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
  let hash = window.location.hash;
  if (hash) {
    let triggerEl = document.querySelector(`button[data-bs-target="${hash}"]`);
    if (triggerEl) {
      new bootstrap.Tab(triggerEl).show();
    }
  }

  let tabButtons = document.querySelectorAll('button[data-bs-toggle="tab"]');
  tabButtons.forEach(btn => {
    btn.addEventListener('shown.bs.tab', function (e) {
      history.pushState(null, null, e.target.getAttribute("data-bs-target"));
      window.scrollTo(0, 0);
    });
  });
});
</script>
