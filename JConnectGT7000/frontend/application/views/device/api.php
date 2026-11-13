<div class="row clearfix">
  <div class="card">
    <div class="header my-header">
      <div class="pull-right">
        <button type="button" class="btn-sm  btn btn-primary waves-effect " id="addapi"><i class="material-icons">add</i> <?php echo lang('add_api'); ?></button>
      </div>
      <h5 class="box-title"><?php echo lang('api'); ?> </h5>
    </div>
    <div class="body">

      <?php
      if (isset($api) && !empty($api)) {
        foreach ($api as $val) { // Normal Variable
          ?>
          <div class="card" id="<?php echo $val["apikey"]; ?>">
            <div class="row">
              <div class="col-md-3">
                <img style="width: 100%;" src="<?php echo iaBase(); ?>api/qr?qr=<?php echo $val["url"]; ?>" title="<?php echo $val['ch']; ?>:<?php echo $val['state']; ?>" />
              </div>
              <div class="col-md-9">
                <p class="card-text">
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item"><?php echo lang('device_serial'); ?><span class="badge badge-primary badge-pill"><?php echo $device_serial; ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_ch'); ?><span class="badge badge-primary badge-pill"><?php echo $val["ch"]; ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_action'); ?><span class="badge badge-primary badge-pill"><?php echo $val["state"]; ?></span></li>
                  </ul>
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item bg-primary"><span><?php echo lang('api_rest'); ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_apikey'); ?><span class="badge badge-primary badge-pill"><?php echo $val["apikey"]; ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_url'); ?><span class="badge badge-primary badge-pill"><?php echo $val["url"]; ?></span></li>
                  </ul>
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item bg-primary"><span><?php echo lang('api_mqtt'); ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_mqtt_pub'); ?><span class="badge badge-primary badge-pill apidata"><?php echo $val["pubtopic"];; ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_mqtt_msg'); ?><span class="badge badge-primary badge-pill apidata"><?php echo $val["pubmsg"]; ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_mqtt_sub'); ?><span class="badge badge-primary badge-pill apidata"><?php echo $val["subtopic"]; ?></span></li>
                    <li class="list-group-item"><?php echo lang('api_mqtt_return'); ?><span class="badge badge-primary badge-pill apidata"><?php echo $val["submsg"]; ?></span></li>
                  </ul>
                </p>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12 ">
                <div class="sub-btn-wdt col-md-4 col-md-offset-4 m-t-5">
                  <a data-toggle="modal" style="width: 100%" class="mClass btn btn-danger btn-lg waves-effect" style="cursor:pointer;" data-target="#cnfrm_delete" title="<?php echo  lang('delete'); ?>" onclick="setId('<?php echo  $val['apikey']; ?>', 'dashboard/deleteapi')"><?php echo lang('delete'); ?></a>
                </div>
              </div>
            </div>
          </div>


      <?php
        }
      } else {
        echo lang('api_notfound');
      }
      ?>

    </div>
  </div>
</div>
<div class="modal fade" id="custom_api_modal" role="dialog">
  <!-- Modal Crud Start-->
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?php echo lang('add_api'); ?></h3>
        <hr>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>
<!--End Modal Crud -->

<script>
  $(document).ready(function() {


    $('body').on("click", "#addapi", function(e) {
      var loading = '<img src="<?php echo iaBase() ?>assets/images/loading.gif" />';
      $("#custom_api_modal").find(".modal-body").html(loading);
      $("#custom_api_modal").find(".modal-body").attr("style", "text-align: center");
      $.ajax({
        url: "<?php echo base_url() . "dashboard/getModalApi"; ?>",
        method: "post",
        data: {
          id: $(this).attr("data-src"),
          device: "<?php echo $device_serial; ?>"
        }
      }).done(function(data) {
        $("#custom_api_modal").find(".modal-body").removeAttr("style");
        $("#custom_api_modal").find(".modal-body").html(data);
        $("#custom_api_modal").modal("show");
        $(".form_check").each(function() {
          $p_obj = $(this);
          $res = 1;
          if ($p_obj.find(".check_box").hasClass("required")) {
            if ($p_obj.find(".check_box").is(":checked")) {
              $res = "0";
            }
          }
          if ($res == 0) {
            $(".check_box").prop("required", false);
          }
        })
      })
    });
    var url = document.location.toString();
    if (url.match('#')) {
      var tag = url.split('#')[1];
      if (tag == 'api-setting') {
        $('body').off('click', '.yes-btn');
        $('body').on('click', '.yes-btn', function(e) {
          e.preventDefault();
          $.post($(this).attr('href'), function(data) {
            $('#' + data.key).remove();
            $('#cnfrm_delete').modal('hide');
          }, 'json');
        });
      }
    }

    $(function() {
      $('.apidata').click(function(e) {
        e.preventDefault();
        alert($(e).html());
      });
    });


  })
</script>