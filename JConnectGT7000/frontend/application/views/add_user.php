<form role="form" enctype="multipart/form-data" action="<?= base_url('user/addEdit') ?>" method="post">
  <div class="row g-3">

    <div class="col-md-6">
      <div class="mb-3">
        <label class="form-label"><?= lang('name') ?></label>
        <input type="text" name="name" value="<?= $userData->name ?? '' ?>" required class="form-control">
      </div>
    </div>

    <div class="col-md-6">
      <div class="mb-3">
        <label class="form-label"><?= lang('email') ?></label>
        <input type="email" name="email" value="<?= $userData->email ?? '' ?>" required class="form-control">
      </div>
    </div>

    <div class="col-md-6">
      <div class="mb-3">
        <label class="form-label"><?= lang('user_type') ?></label>
        <select name="user_type" class="form-select" required>
          <?php
          $u_type = $userData->user_type ?? '';
          $user_type = getAllDataByTable('ia_permission');
          echo $user_type;
          foreach ($user_type as $option) {
            if (strtolower($option->user_type) === 'admin') continue;
            $sel = strtolower($option->user_type) === strtolower($u_type) ? 'selected' : '';
            echo "<option value='{$option->user_type}' $sel>" . ucfirst($option->user_type) . "</option>";
          }
          ?>
          
        </select>
      </div>
    </div>

    <?php if (isset($userData)) { ?>
    <div class="col-md-12">
      <div class="mb-3">
        <label class="form-label"><?= lang('current_password') ?></label>
        <input type="password" name="currentpassword" class="form-control">
      </div>
    </div>
    <?php } else { ?>
    <div class="col-md-12">
      <div class="mb-3">
        <label class="form-label"><?= lang('Password') ?></label>
        <input type="password" name="password" class="form-control" required>
      </div>
    </div>
    <?php } ?>

    <?php if (isset($userData)) { ?>
    <div class="col-md-6">
      <div class="mb-3">
        <label class="form-label"><?= lang('NewPassword') ?></label>
        <input type="password" name="password" class="form-control">
      </div>
    </div>

    <div class="col-md-6">
      <div class="mb-3">
        <label class="form-label"><?= lang('ConfirmPassword') ?></label>
        <input type="password" name="confirmPassword" class="form-control">
      </div>
    </div>
    <?php } ?>

    <div class="col-md-12">
      <div class="mb-3">
        <label class="form-label"><?= lang('image_upload') ?></label>
        <div class="mb-2">
          <img class="img-thumbnail" src="<?= iaBase(); ?>/assets/images/<?= $userData->profile_pic ?? 'user.png' ?>" width="120" />
        </div>
        <input type="file" class="form-control" name="profile_pic">
      </div>
    </div>

    <?php getCustomFields('user', $userData->ia_users_id ?? null); ?>

    <?php if (!empty($userData->ia_users_id)) { ?>
      <input type="hidden" name="ia_users_id" value="<?= $userData->ia_users_id ?>">
      <input type="hidden" name="fileOld" value="<?= $userData->profile_pic ?? '' ?>">
      <div class="col-12 text-end">
        <button type="submit" name="edit" value="edit" class="btn btn-primary"><?= lang('Update') ?></button>
      </div>
    <?php } else { ?>
      <div class="col-12 text-end">
        <button type="submit" name="submit" value="add" class="btn btn-primary"><?= lang('Add') ?></button>
      </div>
    <?php } ?>

  </div>
</form>