<?php
$userid = "";
if (isset($user_id)) {
  $userid = $user_id;
}
?>

<div class="app-content-header">
  <!--begin::Container-->
  <div class="container-fluid">
    <!--begin::Row-->
    <div class="row">
      <div class="col-sm-6">
        <h5 class="mb-0"><?php echo lang("module"); ?></h5>
      </div>
      <div class="col text-end">
        <?php if (CheckPermission("dashboard", "all_create") || (CheckPermission("dashboard", "own_create"))): ?>
        <a href="<?= base_url('dashboard/add') ?>" class="btn btn-primary">
          <i class="bi bi-plus-circle"></i> <?php echo lang("add_dashboard"); ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <!--end::Row-->
  </div>
  <!--end::Container-->
</div>
<!--end::App Content Header-->
<!--begin::App Content-->
<div class="app-content">
  <!--begin::Container-->
  <div class="container-fluid">
    <!--begin::Row-->
    <div class="row">
      <div class="col-12">
        <!-- Default box -->
        <div class="card">

          <div class="card-body table-responsive">

            <table class="table table-striped table-bordered align-middle" id="dashboardLayoutsTable">
              <thead class="table-light">
                <tr>
                  <th>Order</th>
                  <th><?php echo lang('name') ?></th>
                  <th><?php echo lang('description') ?></th>
                  <th><?php echo lang('action') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($layouts)): ?>
                  <?php $current_user_id = $userid ?? null;
                  $displayIndex = 0;

                  foreach ($layouts as $layout):
                    $created_by = $layout->created_by ?? null;
                  ?>
                  <?php if (CheckPermission("dashboard", "all_read") || (CheckPermission("dashboard", "own_read") && $created_by == $current_user_id)): ?>
                    <?php $displayIndex++; ?>
                    <tr data-layout-id="<?= (int)$layout->id ?>">
                      <td class="dashboard-order-cell">
                        <span class="dashboard-drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                        <span class="dashboard-order-value"><?= $displayIndex ?></span>
                      </td>
                      <td><?= htmlspecialchars($layout->name) ?></td>
                      <td><?= htmlspecialchars($layout->description) ?></td>
                      <td>
                        <a href="<?= base_url('dashboard/view/' . $layout->id) ?>" class="btn btn-sm btn-info"><?php echo lang('view'); ?></a>
                        <?php if (CheckPermission("dashboard", "all_update") || (CheckPermission("dashboard", "own_update") && $created_by == $current_user_id)): ?>
                          <a href="<?= base_url('dashboard/edit/' . $layout->id) ?>" class="btn btn-sm btn-primary"><?php echo lang('edit'); ?></a>
                        <?php endif; ?>
                        <?php if ((int)$layout->id !== (int)($user_home_layout_id ?? 0)): ?>
                          <a href="<?= base_url('dashboard/set_home/' . $layout->id) ?>" class="btn btn-sm btn-success"><?php echo lang('sethome'); ?></a>
                        <?php endif; ?>

                        <?php if (CheckPermission("dashboard", "all_delete") || (CheckPermission("dashboard", "own_delete") && $created_by == $current_user_id)): ?>
                          <a href="<?= base_url('dashboard/delete/' . $layout->id) ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('<?= htmlspecialchars($layout->name) ?>');"><?php echo lang('delete'); ?></a>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>


          </div>

          <!-- /.card-footer-->
        </div>
        <!-- /.card -->
      </div>
    </div>
    <!--end::Row-->
  </div>
  <!--end::Container-->
</div>

<div id="dashboardOrderToast" class="dashboard-order-toast" role="status" aria-live="polite">Saved order</div>


<script type="text/javascript">
  $(document).ready(function() {
    if ($.fn.DataTable && $('#dashboardLayoutsTable').length) {
      const table = $('#dashboardLayoutsTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: false,
        columnDefs: [
          { orderable: false, targets: -1 },
          { className: 'text-center', targets: 0 }
        ],
        language: {
          search: <?= json_encode(lang('dt_search'), JSON_UNESCAPED_UNICODE) ?>,
          lengthMenu: <?= json_encode(lang('dt_length'), JSON_UNESCAPED_UNICODE) ?>,
          info: <?= json_encode(lang('dt_info'), JSON_UNESCAPED_UNICODE) ?>,
          infoEmpty: <?= json_encode(lang('dt_info_empty'), JSON_UNESCAPED_UNICODE) ?>,
          infoFiltered: <?= json_encode(lang('dt_info_filtered'), JSON_UNESCAPED_UNICODE) ?>,
          emptyTable: <?= json_encode(lang('no_layout_found'), JSON_UNESCAPED_UNICODE) ?>,
          zeroRecords: <?= json_encode(lang('dt_zero'), JSON_UNESCAPED_UNICODE) ?>,
          paginate: {
            first: <?= json_encode(lang('dt_first'), JSON_UNESCAPED_UNICODE) ?>,
            last: <?= json_encode(lang('dt_last'), JSON_UNESCAPED_UNICODE) ?>,
            next: <?= json_encode(lang('dt_next'), JSON_UNESCAPED_UNICODE) ?>,
            previous: <?= json_encode(lang('dt_previous'), JSON_UNESCAPED_UNICODE) ?>
          }
        }
      });

      function renumberVisibleRows() {
        const pageInfo = table.page.info();
        const startIndex = pageInfo?.start || 0;
        $('#dashboardLayoutsTable tbody tr').each(function(index) {
          $(this).find('.dashboard-order-value').text(startIndex + index + 1);
        });
      }

      let toastTimer = null;
      function showOrderToast(message = 'Saved order') {
        const toast = document.getElementById('dashboardOrderToast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
          toast.classList.remove('show');
        }, 1800);
      }

      function bindRowSorting() {
        const $tbody = $('#dashboardLayoutsTable tbody');
        if (!$tbody.length || typeof $tbody.sortable !== 'function') return;

        if ($tbody.data('ui-sortable')) {
          $tbody.sortable('destroy');
        }

        $tbody.sortable({
          items: '> tr[data-layout-id]',
          axis: 'y',
          handle: '.dashboard-drag-handle',
          tolerance: 'pointer',
          forcePlaceholderSize: true,
          helper: function(e, tr) {
            tr.children().each(function() {
              $(this).width($(this).outerWidth());
            });
            return tr;
          },
          start: function(e, ui) {
            ui.placeholder.height(ui.item.outerHeight());
          },
          update: function() {
            const order = $tbody.children('tr[data-layout-id]').map(function() {
              return Number(this.getAttribute('data-layout-id'));
            }).get().filter(function(id) { return id > 0; });

            if (!order.length) return;

            const body = new URLSearchParams();
            order.forEach(function(id) { body.append('order[]', String(id)); });

            fetch('<?= base_url('dashboard/update_sort_order') ?>', {
              method: 'POST',
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: body
            })
              .then(res => res.json())
              .then(json => {
                if (json.status !== 'success') {
                  throw new Error(json.message || 'Unable to save order');
                }
                const settings = table.settings()[0];
                const indexById = {};
                settings.aoData.forEach(function(row, index) {
                  const id = Number(row.nTr && row.nTr.dataset.layoutId);
                  if (id) indexById[id] = index;
                });
                const nextOrder = order
                  .map(function(id) { return indexById[Number(id)]; })
                  .filter(function(index) { return index !== undefined; });
                if (nextOrder.length === settings.aiDisplayMaster.length) {
                  settings.aiDisplayMaster = nextOrder.slice();
                  settings.aiDisplay = nextOrder.slice();
                }
                renumberVisibleRows();
                showOrderToast();
              })
              .catch(err => {
                console.error(err);
                alert('Unable to save dashboard order');
                window.location.reload();
              });
          }
        });

        renumberVisibleRows();
      }

      bindRowSorting();
      table.on('draw', bindRowSorting);
    }
  });

  function confirmDelete(layoutName) {
    if (confirm('<?php echo lang("confirm_delete_layout"); ?>')) {
      return true;
    }
    return false;
  }
</script>

<style type="text/css">
.dashboard-order-cell {
  white-space: nowrap;
}

.dashboard-drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  color: var(--bs-secondary-color, #6c757d);
  margin-right: 0.35rem;
  touch-action: none;
}

.dashboard-drag-handle:active {
  cursor: grabbing;
}

#dashboardLayoutsTable tbody tr.ui-sortable-helper,
#dashboardLayoutsTable tbody tr.ui-sortable-helper td {
  transform: none;
  box-shadow: none;
  background: var(--bs-body-bg, #fff);
}

#dashboardLayoutsTable tbody tr.ui-sortable-placeholder {
  visibility: visible !important;
}

.dashboard-order-toast {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 1080;
  padding: 0.55rem 0.8rem;
  border-radius: 0.5rem;
  background: rgba(25, 135, 84, 0.95);
  color: #fff;
  font-size: 0.875rem;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.2);
  opacity: 0;
  pointer-events: none;
  transform: translateY(8px);
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.dashboard-order-toast.show {
  opacity: 1;
  transform: translateY(0);
}

</style>