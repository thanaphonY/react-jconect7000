<div class="row clearfix">
  <div class="card">
    <div class="header my-header">
      <div class="pull-right">
        <button type="button" class="btn-sm  btn btn-primary waves-effect modalButton"><i class="material-icons">add</i> <?php echo lang('add_timer'); ?></button>
      </div>
      <h5 class="box-title"><?php echo lang('timer'); ?> </h5>
    </div>
    <div class="body">
      <table id="custom-timer" class="cell-border table table-striped table1 table-bordered table-hover dataTable" style="width: 100%;">
        <thead>
          <tr>
            <th><?php echo lang('timer_id'); ?></th>
            <th><?php echo lang('timer_ch'); ?></th>
            <th><?php echo lang('timer_type'); ?></th>
            <th><?php echo lang('timer_action'); ?></th>
            <th><?php echo lang('timer_datetime'); ?></th>
            <th><?php echo lang('tools'); ?></th>
          </tr>
        </thead>
        <tbody>

        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="modal fade" id="custom_fields_modal" role="dialog">
  <!-- Modal Crud Start-->
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?php echo lang('add_timer'); ?></h3>
        <hr>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>
<!--End Modal Crud -->

<script>
  $(document).ready(function() {
    $('#custom-timer').dataTable({
      "dom": 'lfrtip',
      "processing": true,
      "serverSide": true,
      "ajax": {
        url: '<?php echo iaBase(); ?>dashboard/timerData/<?php echo $device_serial; ?>'
      },
      "sPaginationType": "full_numbers",
      "language": {
        "search": "_INPUT_",
        "searchPlaceholder": "<?php echo lang('search'); ?>",
        "paginate": {
          "next": '<i class="material-icons">keyboard_arrow_right</i>',
          "previous": '<i class="material-icons">keyboard_arrow_left</i>',
          "first": '<i class="material-icons">first_page</i>',
          "last": '<i class="material-icons">last_page</i>'
        }
      },
    });

    $('body').on("click", ".modalButton", function(e) {
      var loading = '<img src="<?php echo iaBase() ?>assets/images/loading.gif" />';
      $("#custom_fields_modal").find(".modal-body").html(loading);
      $("#custom_fields_modal").find(".modal-body").attr("style", "text-align: center");
      $.ajax({
        url: "<?php echo base_url() . "dashboard/getModal"; ?>",
        method: "post",
        data: {
          id: $(this).attr("data-src"),
          device: "<?php echo $device_serial; ?>"
        }
      }).done(function(data) {
        $("#custom_fields_modal").find(".modal-body").removeAttr("style");
        $("#custom_fields_modal").find(".modal-body").html(data);
        $("#custom_fields_modal").modal("show");
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
      if (tag == 'timer-setting') {
        $('body').off('click', '.yes-btn');
        $('body').on('click', '.yes-btn', function(e) {
          e.preventDefault();
          $.post($(this).attr('href'), function(data) {
            $('tbody').find('tr').each(function() {
              $ob = $(this);
              $dom_val = $ob.find('td').first().text();
              $('#cnfrm_delete').modal('hide');
              $.each(data, function(index, val) {
                if ($dom_val == val) {
                  $ob.fadeOut('slow');
                }
              });
            });
          }, 'json');
        });
      }
    }
  })
</script>