<section class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="">
          <div class="header">
            <h2>
              <?php echo lang("device"); ?>
            </h2>
          </div>
          <div class="body">
            <div class="row">
              <div class="col-lg-12">
                <div class="tabbable">
                  <div class="left_content">
                    <ul id="myTab4" class="nav nav-tabs">
                    <li class="active">
                        <a href="#info-setting" data-toggle="tab">
                          <i class="fa fa-cogs"></i>
                          <span><?php echo lang("device_info"); ?></span>
                        </a>
                      </li>
                      <li>
                        <a href="#timer-setting" data-toggle="tab">
                          <i class="fa fa-cogs"></i>
                          <span><?php echo lang("device_timer"); ?></span>
                        </a>
                      </li>
                      <li>
                        <a href="#api-setting" data-toggle="tab">
                          <i class="fa fa-envelope-o" aria-hidden="true"></i>
                          <span><?php echo lang("device_api"); ?></span>
                        </a>
                      </li>

                    </ul>
                  </div>
                  <div class="right_content">
                    <div class="tab-content">
                      <div class="tab-pane fade active in" id="info-setting">
                        <?php $this->load->view('device/info'); ?>
                      </div>
                      <div class="tab-pane fade in" id="timer-setting">
                        <?php $this->load->view('device/timer'); ?>
                      </div>
                      <div class="tab-pane fade in" id="api-setting">
                        <?php $this->load->view('device/api'); ?>
                      </div>

                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>


      <!-- /.content-wrapper -->
      <script>
        $(document).ready(function() {

          // Javascript to enable link to tab
          var url = document.location.toString();
          if (url.match('#')) {
            var tag = url.split('#')[1];
            if (tag == 'timer-setting') {
              $('#templates').click();
            }
            $('.nav-tabs a[href="#' + tag + '"]').tab('show');
          }

          // Change hash for page-reload
          $('.nav-tabs a').on('shown.bs.tab', function(e) {
            window.location.hash = e.target.hash;
            $(window).scrollTop(0);
          });

          if ($('li#system_update').hasClass('active')) {
            setTimeout(function() {
              $('li#system_update').trigger('click');
            }, 500);
          }

        })
      </script>
      <!-- /page content -->