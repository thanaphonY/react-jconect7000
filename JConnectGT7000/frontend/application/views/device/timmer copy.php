<div class="card clearfix">
  <div class="header">
    <h5><?php echo lang('device_timmer'); ?> </h5>
    <ul class="header-dropdown m-r--5">
      <button class="btn btn-primary waves-effect add-extn-btn" type="button" data-toggle="modal" data-target="#timmerModal"> <i class="material-icons">add</i> <?php echo lang('timmer_on'); ?> </button>
    </ul>
  </div>
  <div class="col-md-12 m-b-20">
    <div class="col-md-12">
      <div class="form-group">


      </div>
    </div>
  </div>

</div>

<div class="modal fade" id="timmerModal"  role="dialog"><!-- Modal Crud Start-->
	<div class="modal-dialog">
		<div class="modal-content" >
			<div class="modal-header">
			  	<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
			  	<h3 class="modal-title"><?php echo lang('add_timmer'); ?></h3>
			 	<hr>
			</div>
			<div class="modal-body">
        <form>
          <div class="form-group form-float">
            <div class="form-line">
              <select class="form-control" id="" required>
                <option value=""></option>
                <option value="ch1">Channel 1</option>
                <option value="ch2">Channel 2</option>
                <option value="ch3">Channel 3</option>
              </select>
              <label class="form-label" for="">Channel</label>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <label class="form-label" for="">Activity</label>
              <select class="form-control" id="" required>
                <option value=""></option>
                <option value="1">Turn on</option>
                <option value="0">Turn off</option>
              </select>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <label class="form-label" for="">Repeat</label>
              <select class="form-control" id="" required>
                <option value=""></option>
                <option value="0">one time</option>
                <option value="1">Everyday</option>
              </select>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <label class="form-label" for="">Date</label>
              <input class="form-control" type="text" id="date" placeholder="" >
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <label class="form-label" for="">Time</label>
              <input class="form-control" type="text" id="time" placeholder="" required>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Send message</button>
      </div>
    </div>
  </div>
</div>


<script>
  $(function() {
    $("#timmerModal").find(".modal-body").removeAttr("style");
    $('#date').bootstrapMaterialDatePicker({
      time: false,
      minDate: moment(),
    });
    $('#time').bootstrapMaterialDatePicker({
      date: false,
      format: 'HH:mm'
    });
  });
</script>