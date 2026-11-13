<?php
// Bootstrap 5 refactor of general_view
?>
<form method="post" enctype="multipart/form-data" action="<?php echo base_url() . 'setting/editSetting' ?>" class="needs-validation" novalidate>
  <div class="card p-4 mb-4">
    <div class="card-header">
      <h5 class="card-title mb-0"><?php echo lang('generalSetting'); ?></h5>
    </div>

    <div class="mb-3">
      <label for="website" class="form-label"><?php echo lang("title"); ?> *</label>
      <input type="text" class="form-control" id="website" name="website" required value="<?php echo isset($result['website']) ? $result['website'] : ''; ?>">
    </div>

    <div class="mb-3">
      <label for="logoSite" class="form-label"><?php echo lang('logo'); ?></label>
      <div class="mb-2">
        <?php (isset($result['logo']) && $result['logo'] != '' ? $logo_src = iaBase() . 'assets/images/' . $result['logo'] : $logo_src = iaBase() . 'assets/images/logo.png'); ?>
        <img class="img-thumbnail" id="logo-holder" src="<?php echo $logo_src; ?>" alt="logoSite" style="max-height: 100px; cursor: pointer;">
      </div>
      <input class="form-control" type="file" name="logo" id="logoSite" accept="image/*">
      <input type="hidden" name="fileOldlogo" value="<?php echo isset($result['logo']) ? $result['logo'] : ''; ?>">
    </div>

    <h5 class="mt-4 mb-3"><?php echo lang('registration_setting'); ?></h5>
    <div class="row mb-3">
      <div class="col-md-6 form-check">
        <input class="form-check-input" type="checkbox" name="register_allowed" id="register_allowed" value="1" <?php if (isset($result['register_allowed']) && $result['register_allowed'] == 1) echo 'checked'; ?>>
        <label class="form-check-label" for="register_allowed"><?php echo lang('allow_signup'); ?></label>
      </div>
      <div class="col-md-6 form-check">
        <input class="form-check-input" type="checkbox" name="admin_approval" id="admin_approval" value="1" <?php if (isset($result['admin_approval']) && $result['admin_approval'] == 1) echo 'checked'; ?>>
        <label class="form-check-label" for="admin_approval"><?php echo lang('admin_approval'); ?></label>
      </div>
    </div>

    <div class="mb-4">
      <label for="user_type" class="form-label"><?php echo lang('default_user_role'); ?> *</label>
      <select name="user_type[]" id="user_type" class="form-select" multiple required>
        <?php $permissiona = getAllDataByTable('ia_permission');
        foreach ($permissiona as $perkey => $value) {
          $user_type = isset($value->user_type) ? $value->user_type : '';
          if ($user_type != 'admin') {
            $old = json_decode($result['user_type']);
        ?>
            <option value="<?php echo $user_type; ?>" <?php if (in_array(strtolower($user_type), array_map('strtolower', $old))) echo 'selected'; ?>><?php echo ucfirst($user_type); ?></option>
        <?php }} ?>
      </select>
    </div>

    <?php if(CheckPermission('setting', "all_update")) { ?>
    <div class="text-center">
      <button type="submit" class="btn btn-primary btn-lg w-50"><?php echo lang('save'); ?></button>
    </div>
    <?php } ?>
  </div>
</form>
