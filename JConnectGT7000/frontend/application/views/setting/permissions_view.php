<div class="card clearfix">
  <div class="card-header">
    <h5 class="box-title"><?php echo lang('permissionSetting'); ?></h5>
  </div>
  <div class="card-body">

    <div class="row">
      <div class="col-md-4">
        <div class="box-header my-header">
          <h5 class="box-title">
            <?php echo lang('user_role'); ?>
            <span class="float-end">
              <?php if (CheckPermission('setting', "all_update")) { ?>
                <button class="btn btn-success btn-sm ad-u-type-btn btn-sm" type="button" title="<?php echo lang('add_new'); ?>"><i class="bi bi-plus-circle-fill"></i></button>
              <?php } ?>

            </span>
          </h5>
          <hr>
        </div>
        <form method="post" action="<?php echo base_url(); ?>setting/updateRole">


          <div class="form-group form-float m-t-30 ia_user_role_div">
            <?php
            $permissiona = getAllDataByTable('ia_permission');
            if (is_array($permissiona)) {
              usort($permissiona, function ($a, $b) {
                return strcasecmp((string) ($a->user_type ?? ''), (string) ($b->user_type ?? ''));
              });
            }
            $i = 0;
            foreach ($permissiona as $perkey => $value) {
              $user_type = isset($value->user_type) ? $value->user_type : '';
              if ($user_type != 'admin') {
            ?>
                <div class="row mb-2 role-entry ">

                  <div class="col-md-7 ia-sel-role <?= $i <= 0 ? 'active' : '' ?>" rel="<?= str_replace(' ', '-', $user_type); ?>">
                    <span class="role-span"><?= ucfirst($user_type) ?></span>
                    <div class="form-line d-none">
                      <input type="text" class="form-control inp-field  input-group input-group-sm"
                        data-old-id="<?= $value->id ?>"
                        name="user_type_name[<?= $value->id ?>]"
                        value="<?= $user_type ?>">
                    </div>
                  </div>
                  <div class="col-md-5">
                    <?php if (CheckPermission('setting', "all_update")) { ?>
                      <button class="btn btn-success btn-sm ia-edit-btn btn-sm" type="button" title="<?php echo lang('edit'); ?>"><i class="bi bi-pencil-square"></i></button>
                      <button class="btn btn-danger btn-sm rm-u-type-btn btn-sm" type="button" title="<?php echo lang('remove'); ?>"><i class="bi bi-x-circle-fill"></i></button>
                    <?php } ?>

                  </div>
                </div>
            <?php
                $i++;
              }
            }
            ?>
            <?php if (CheckPermission('setting', "all_update")) { ?>
              <button class="btn btn-primary btn-sm"><?php echo lang('save_role'); ?></button>
            <?php } ?>

          </div>
        </form>
      </div>
      <div class="col-md-8">
        <div class="box-header my-header">
          <h5 class="box-title"><?php echo lang('permissionSetting'); ?></h5>
          <hr>
        </div>
        <form class="form-horizontal" action="<?php echo base_url() . 'setting/permission' ?>" method="post">
          <?php
          $permission = getAllDataByTable('ia_permission');
          if (is_array($permission)) {
            usort($permission, function ($a, $b) {
              return strcasecmp((string) ($a->user_type ?? ''), (string) ($b->user_type ?? ''));
            });
          }
          $setPermission = array();
          $own_create = '';
          $own_read = '';
          $own_update = '';
          $own_delete = '';
          $all_create = '';
          $all_read = '';
          $all_update = '';
          $all_delete = '';
          $i = 0;
          $permission = isset($permission) && is_array($permission) && !empty($permission) ? $permission : array();
          if (isset($permission[1])) {
            foreach ($permission as $perkey => $value) {
              $id = isset($value->id) ? $value->id : '';
              $user_type = isset($value->user_type) ? $value->user_type : '';
              $data = isset($value->data) ? json_decode($value->data) : '';
              if ($user_type == 'admin') {
              } else {
          ?>
                <div class="ia-permisssion-div ia-permission-<?php echo str_replace(' ', '-', $user_type);
                                                              if ($i > 0) {
                                                                echo " d-none";
                                                              } ?> ">
                  <div class="">
                    <h4 class="mb-2">
                      <?php echo lang('permissions_for') . ': ' . ucfirst($user_type); ?>
                    </h4>
                  </div>
                  <?php /*if ($i == 0) {echo "in";}*/ ?>
                  <div id="collapse_<?php echo $id; ?>" class="accordion-collapse collapse show">
                    <div class="card  border-secondary-body table-responsive">
                      <table class="table table-bordered text-center align-middle">
                        <thead>
                          <tr class="showRolesPermission">
                            <th scope="col"><?php echo lang('modules'); ?></th>
                            <th scope="col"><?php echo lang('create'); ?></th>
                            <th scope="col"><?php echo lang('read'); ?></th>
                            <th scope="col"><?php echo lang('update'); ?></th>
                            <th scope="col"><?php echo lang('delete'); ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          if (isset($data) && !empty($data)) {
                            foreach ($data as $perkey => $valueR) {
                              $perkey = isset($perkey) ? $perkey : '';
                              $valueR = isset($valueR) ? $valueR : '';
                              if (isset($valueR)) {
                                $setPermissionCheck = $valueR;
                                $own_create = isset($setPermissionCheck->own_create) ? $setPermissionCheck->own_create : '';
                                $own_read = isset($setPermissionCheck->own_read) ? $setPermissionCheck->own_read : '';
                                $own_update = isset($setPermissionCheck->own_update) ? $setPermissionCheck->own_update : '';
                                $own_delete = isset($setPermissionCheck->own_delete) ? $setPermissionCheck->own_delete : '';
                                $all_create = isset($setPermissionCheck->all_create) ? $setPermissionCheck->all_create : '';
                                $all_read = isset($setPermissionCheck->all_read) ? $setPermissionCheck->all_read : '';
                                $all_update = isset($setPermissionCheck->all_update) ? $setPermissionCheck->all_update : '';
                                $all_delete = isset($setPermissionCheck->all_delete) ? $setPermissionCheck->all_delete : '';
                              } else {
                                $setPermissionCheck = array();
                                $own_create = '';
                                $own_read = '';
                                $own_update = '';
                                $own_delete = '';
                                $all_create = '';
                                $all_read = '';
                                $all_update = '';
                                $all_delete = '';
                              }
                          ?>
                              <tr>
                                <th scope="col" colspan="5" class="showRolesPermission text-center"><?php echo ucfirst(str_replace('_', ' ', $perkey)); ?>
                                  <?php
                                  //$perkey = str_replace(' ', '_SPACE_', $perkey);
                                  $user_type = str_replace(' ', '_SPACE_', $user_type);
                                  ?>
                                  <input type="hidden" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>]" value="<?php echo $perkey; ?>" />
                                </th>
                              </tr>
                              <tr>
                                <th scope="row" class="thfont"><?php echo lang('own_entries'); ?><input type="checkbox" class="float-end sell_all"></th>
                                <td><input type="checkbox" class="chk_create" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>][own_create]" value="1" <?php if ($own_create == 1) {
                                                                                                                                                                      echo "checked";
                                                                                                                                                                    } ?> /></td>
                                <td><input type="checkbox" class="chk_read" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>][own_read]" value="1" <?php if ($own_read == 1) {
                                                                                                                                                                  echo "checked";
                                                                                                                                                                } ?> /></td>
                                <td><input type="checkbox" class="chk_update" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>][own_update]" value="1" <?php if ($own_update == 1) {
                                                                                                                                                                      echo "checked";
                                                                                                                                                                    } ?> /></td>
                                <td><input type="checkbox" class="chk_delete" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>][own_delete]" value="1" <?php if ($own_delete == 1) {
                                                                                                                                                                      echo "checked";
                                                                                                                                                                    } ?> /></td>
                              </tr>
                              <tr>
                                <th scope="row" class="thfont"><?php echo lang('all_entries'); ?><input type="checkbox" class="float-end sell_all"></th>
                                <td>-</td>
                                <td><input type="checkbox" class="chk_read" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>][all_read]" value="1" <?php if ($all_read == 1) {
                                                                                                                                                                  echo "checked";
                                                                                                                                                                } ?> /></td>
                                <td><input type="checkbox" class="chk_update" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>][all_update]" value="1" <?php if ($all_update == 1) {
                                                                                                                                                                      echo "checked";
                                                                                                                                                                    } ?> /></td>
                                <td><input type="checkbox" class="chk_delete" name="data[<?php echo $user_type; ?>][<?php echo $perkey; ?>][all_delete]" value="1" <?php if ($all_delete == 1) {
                                                                                                                                                                      echo "checked";
                                                                                                                                                                    } ?> /></td>
                              </tr>
                              <?php }
                          } else {
                            $blanckModule1 = getRowByTableColomId('ia_permission', 'admin', 'user_type', 'data');
                            if (isset($blanckModule1) && $blanckModule1 != '') {
                              foreach (json_decode($blanckModule1) as $key1 => $value1) {
                              ?>
                                <tr>
                                  <th scope="col" colspan="5" class="showRolesPermission text-center"><?php echo ucfirst(str_replace('_', ' ', $key1)); ?>
                                    <?php
                                    //$key1 = str_replace(' ', '_SPACE_', $key1);
                                    $user_type = str_replace(' ', '_SPACE_', $user_type);
                                    ?>
                                    <input type="hidden" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>]" value="<?php echo $key1; ?>" />
                                  </th>
                                </tr>
                                <tr>
                                  <th scope="row" class="thfont"><?php echo lang('own_entries'); ?></th>
                                  <td><input type="checkbox" class="chk_create" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>][own_create]" value="1" /></td>
                                  <td><input type="checkbox" class="chk_read" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>][own_read]" value="1" /></td>
                                  <td><input type="checkbox" class="chk_update" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>][own_update]" value="1" /></td>
                                  <td><input type="checkbox" class="chk_delete" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>][own_delete]" value="1" /></td>
                                </tr>
                                <tr>
                                  <th scope="row" class="thfont"><?php echo lang('all_entries'); ?></th>
                                  <td>-</td>
                                  <td><input type="checkbox" class="chk_read" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>][all_read]" value="1" /></td>
                                  <td><input type="checkbox" class="chk_update" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>][all_update]" value="1" /></td>
                                  <td><input type="checkbox" class="chk_delete" name="data[<?php echo $user_type; ?>][<?php echo $key1; ?>][all_delete]" value="1" /></td>
                                </tr>
                          <?php
                              }
                            }
                          }
                          ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
            <?php
                $i++;
              }
            }
            ?>
            <div class="row">
              <div class="col-md-12 m-b-20">
                <?php if (CheckPermission('setting', "all_update")) { ?>
                  <input type="submit" name="save" value="<?php echo lang('save_permission'); ?>" class="btn btn-wide btn-primary btn-sm waves-effect margin-top-20" />
                <?php } ?>

              </div>
            </div>
          <?php } ?>
        </form>

      </div>
    </div>
  </div>
</div>




<script>
  $(document).ready(function() {
    /*$('.ad-u-type-btn').on('click', function () {
      const $ia_obj = $('.ia_user_role_div');
      const $ohtml = $ia_obj.find('.role-entry').first().clone();
      $ohtml.find('.role-label').addClass('d-none');
      $ohtml.find('.role-input').removeClass('d-none');
      $ohtml.find('input').val('')
        .attr('data-old-id', 'new')
        .attr('name', "new_user_name[]")
        .focus();
      $ia_obj.prepend($ohtml);
    });*/

    $('.ad-u-type-btn').on('click', function() {
      const $ia_obj = $('.ia_user_role_div');
      const $template = $ia_obj.find('.role-entry').first().clone();
      $template.find('.role-span').addClass('d-none');
      $template.find('.form-line').removeClass('d-none');
      $template.find('button.btn').addClass('rm-u-type-btn');
      $template.find('input.inp-field').val('')
        .attr('data-old-id', 'new')
        .attr('name', 'new_user_name[]')
        .focus();
      $ia_obj.prepend($template);
    });

    /*
    $('.ad-u-type-btn').on('click', function() {
      $obj = $(this);
      $ia_obj = $('.ia_user_role_div');
      $ohtml = $ia_obj.find('.role-entry').first().clone();
      $ohtml.find('span.role-span').addClass('blind');
      $ohtml.find('div.form-line').removeClass('blind');
      $ohtml.find('button.btn').addClass('rm-u-type-btn');
      $ohtml.find('input.inp-field').val('')
        .attr('data-old-id', 'new')
        .attr('name', "new_user_name[]")
        .focus();

      $ia_obj.prepend($ohtml);
      $.AdminBSB.input.activate();
    });*/


    $('.ia-sel-role').on('click', function() {
      $('.ia-sel-role').removeClass('active');
      $('.ia-permisssion-div').addClass('d-none');
      const rel = $(this).attr('rel');
      $(this).addClass('active');
      $('.ia-permission-' + rel).removeClass('d-none');
    });

    $('body').on('click', '.rm-u-type-btn', function() {
      const $row = $(this).closest('.role-entry');
      const oldId = $row.find('input').attr('data-old-id');
      $('<input>').attr({
        type: 'hidden',
        name: 'rm_user_type[]',
        value: oldId
      }).insertAfter($row);
      $row.remove();
    });
  });
</script>


<script>
  $(document).ready(function() {
    // Switch selected role and show permissions
    $('.ia-sel-role').on('click', function() {
      $('.ia-sel-role').removeClass('active');
      $('.ia-permisssion-div').addClass('d-none');

      const rel = $(this).attr('rel');
      $(this).addClass('active');
      $('.ia-permission-' + rel).removeClass('d-none');
    });

    // Enable editing a role
    $(document).on('click', '.ia-edit-btn', function() {
      // ซ่อน input และแสดง span ทุก Role
      $('.role-span').removeClass('d-none');
      $('.form-line').addClass('d-none');

      // แสดง input ของแถวที่คลิก และซ่อน span ของแถวนั้น
      const $row = $(this).closest('.row');
      $row.find('.role-span').addClass('d-none');
      $row.find('.form-line').removeClass('d-none');
      $row.find('.inp-field').focus();
    });

    // Add new role
    /*
  $('.ad-u-type-btn').on('click', function () {
    const $ia_obj = $('.ia_user_role_div');
    const $template = $ia_obj.find('.role-entry').first().clone();
    $template.find('.role-span').addClass('d-none');
    $template.find('.form-line').removeClass('d-none');
    $template.find('input').val('').attr('data-old-id', 'new').attr('name', 'new_user_name[]').focus();
    $ia_obj.prepend($template);
  });
*/
    // Remove a role
    $('body').on('click', '.rm-u-type-btn', function() {
      const $row = $(this).closest('.row');
      const oldId = $row.find('input').attr('data-old-id');
      $('<input>').attr({
        type: 'hidden',
        name: 'rm_user_type[]',
        value: oldId
      }).insertAfter($row);
      $row.remove();
    });
  });
</script>