<!-- 01 -->

<?php if ($this->router->class === "home") { ?>
  <div id="navbarContent" class="d-none">
    <select class="form-select" name="dashboard_layout" id="dashboard_layout">
      <?php foreach ($all_dashboard_layouts as $layoutAll): ?>
        <option value="<?= $layoutAll->id ?>" <?= $layoutAll->id == $layout->id ? 'selected' : '' ?>><?= $layoutAll->name ?></option>
      <?php endforeach; ?>
    </select>
  </div>
<?php }   ?>

<div id="dashboard_select_content" class="app-content">



</div>


<script>
  $(document).ready(function() {
    // เมื่อโหลดหน้าเว็บ ให้เพิ่ม select ลงใน navbar
    $('#navbarSupportedContent').empty();
    $('#navbarSupportedContent').append($('#dashboard_layout').clone().removeAttr('id').removeClass('form-select'));
    $('#navbarSupportedContent select').on('change', function() {
      const selectedLayout = $(this).val();
	  $('#dashboard_select_content').empty();
	  $.ajax({
		url: '<?= site_url('home/viewDashboard') ?>',
		type: 'POST',
		data: { layout_id: selectedLayout },
		success: function(response) {
		  $('#dashboard_select_content').html(response);
		},
		error: function() {
		  alert('Error loading dashboard layout');
		}
	  });
      
	  
    });
  });
</script>