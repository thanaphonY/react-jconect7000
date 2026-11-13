<section class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
          <div class="header">
            <h5>
              <?php echo lang("logger"); ?>
            </h5>
          </div>
          <!-- /.box-header -->






          <div class="body table-responsive">
            <!-- <div class="row">
              <div class='col-sm-3'>

                <div class="form-group nomargin">
                  <div class='input-group date' id='datetimepicker1'>
                    <input type='text' id="min" class="form-control" />
                    <span class="input-group-addon"><span class="glyphicon glyphicon-calendar">Start</span>
                    </span>
                  </div>
                </div>
              </div>
              <div class='col-sm-3'>

                <div class="form-group nomargin">
                  <div class='input-group date' id='datetimepicker2'>
                    
                    <input type='text' id="max" class="form-control" />
                    <span class="input-group-addon"><span class="glyphicon glyphicon-calendar">End</span>
                    </span>
                  </div>
                </div>
              </div>
            </div> -->
            <table id="logstable" class="table table-bordered table-striped table-hover delSelTable">
              <thead>
                <tr>
                  <th><?php echo lang('log_datetime') ?></th>
                  <th><?php echo lang('log_devicename') ?></th>
                  <th><?php echo lang('log_rule') ?></th>
                  <th><?php echo lang('log_msg'); ?></th>
                  <!-- <th><?php echo lang('log_status'); ?></th>
                  <th><?php echo lang('log_tx'); ?></th> -->
                </tr>
              </thead>
              <tbody>
              </tbody>
            </table>
          </div>
          <!-- /.box-body -->
        </div>
        <!-- /.box -->
      </div>
      <!-- /.col -->
    </div>
  </div>
  <!-- /.row -->
</section>
<!-- /.content -->



<script type="text/javascript">
  

  $(document).ready(function() {

   

    // DataTables initialisation
    //var table = $('#logstable').DataTable();

    // Refilter the table
    var url = '<?php echo base_url(); ?>';
        var table = $('#logstable').DataTable({
            dom: 'lfBrtip',
            buttons: [
                'excel'
            ],
            "processing": true,
            "serverSide": true,
            "ajax": url + "logger/logTable",
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
            "iDisplayLength": 10,
            "aLengthMenu": [
                [10, 25, 50, 100, 500, -1],
                [10, 25, 50, 100, 500, "<?php echo lang('all'); ?>"]
            ],
            "order": [[0, 'desc']]
        });

       

    
   
  });
</script>

<style>
  .date {
    border-color: lightgray;
    border-style: solid;
    margin-bottom: unset;
  }

  .nomargin {
    margin-bottom: unset;
  }
</style>