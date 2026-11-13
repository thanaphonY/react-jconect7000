<!-- Templates Modal -->
<div class="modal fade" id="nameModal_Templates" tabindex="-1" aria-labelledby="templatesLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="templatesLabel"><?php echo lang('templates'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0"></div>
    </div>
  </div>
</div>

<!-- Templates DataTable -->
<div class="row">
  <div class="card p-4">
    <div class="card-header">
      <h5 class="mb-0"><?php echo lang('templatesSetting'); ?></h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="example_Templates" class="table table-striped table-bordered w-100">
          <thead>
            <tr>
              <th><?php echo lang('template_name'); ?></th>
              <th><?php echo lang('Action'); ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel"><?php echo lang('template_preview'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('close'); ?></button>
      </div>
    </div>
  </div>
</div>

<script>
  function editTemplate(id) {
    $.ajax({
      url: "/templates/modalForm",
      method: "POST",
      data: {
        id: id
      },
      beforeSend: function() {
        // Optionally show loader
        $("#nameModal_Templates .modal-body").html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
      },
      success: function(data) {
        $("#nameModal_Templates .modal-body").html(data);
        const modal = new bootstrap.Modal(document.getElementById('nameModal_Templates'));
        modal.show();
      },
      error: function(xhr) {
        console.error("Error loading template modal:", xhr.responseText);
        $("#nameModal_Templates .modal-body").html('<div class="alert alert-danger">Error loading template form.</div>');
      }
    });
  }

  $(document).ready(function() {
    const url = "";

    $('#example_Templates').DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      language: {
        search: "_INPUT_",
        searchPlaceholder: "<?php echo lang('search'); ?>",
        lengthMenu: "_MENU_",
        paginate: {
          next: "»",
          previous: "«"
        }
      },
      pageLength: 10,
      lengthMenu: [
        [10, 25, 50, 100, 500, -1],
        [10, 25, 50, 100, 500, "<?php echo lang('all'); ?>"]
      ],
      ajax: url + "/templates/ajaxData",
      columnDefs: [{
        targets: -1,
        orderable: false,
        className: 'text-center'
      }],
      drawCallback: function() {
        // Optional: Tooltip/init/rebinding
      }
    });
  });

</script>