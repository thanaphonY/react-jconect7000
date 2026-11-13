
<form method="post" enctype="multipart/form-data" action="<?php echo base_url() . 'setting/editSetting' ?>" data-parsley-validate class="form-horizontal form-label-left demo-form2">


    <div class="card clearfix">
        <div class="header">
          <h5 class="box-title"><?php echo lang('generalSetting'); ?> </h5>
        </div>


        <div class="col-md-12 m-t-20">
          <div class="col-md-12">
            <div class="form-group form-float">
                <div class="form-line">
                    <input type="text" id="" class="form-control" name="website" required="" value="<?php echo isset($result['website']) ? $result['website'] : ''; ?>">
                    <label class="form-label"><?php echo lang("title"); ?> *</label>
                </div>
            </div>
          </div>
        </div>

          <div class="col-md-12 m-b-20">
            <div class="col-md-12">
              <div class="form-group">
                <span for="exampleInputFile" class="l-color"><?php echo lang('logo'); ?> </span>
              </div>
              <div class="form-group pic_size col-sm-4 col-xs-4 text-center m-t-10" id="logo-holder">


                <?php (isset($result['logo']) && $result['logo'] != '' ? $logo_src = iaBase() . 'assets/images/' . $result['logo'] : $logo_src = iaBase() . 'assets/images/logo.png');?>

                  <img class="thumb-image logo setpropileam ia-sel-image" title="Click to change logo" src="<?php echo $logo_src; ?>"  alt="logoSite">

              </div>
              <button class="btn btn-xs btn-warning rm-logo-img" type="button"><i class="material-icons">close</i></button>
              <div class="blind">
                <input type="file" class="upload" name="logo" id="logoSite" value="" accept="image/*">
                <input type="hidden" name="fileOldlogo" value="<?php echo isset($result['logo']) ? $result['logo'] : ""; ?>">
              </div>
            </div>
          </div>



      <div class="row">
        <div class="col-md-12">
          <div class="col-md-12">
            <h5 class="box-title"><?php echo lang('registration_setting'); ?> </h5>
          </div>
        </div>
      </div>

       <div class="col-md-6 m-t-10">
         <div class="col-md-6">
               <div class="form-group form-float ">
          <input type="checkbox" name="register_allowed" id="register_allowed" <?php if (isset($result['register_allowed']) && $result['register_allowed'] == 1) {echo 'checked="checked"';}?> value="1" />
          <label for="register_allowed"><?php echo lang('allow_signup'); ?></label>
               </div>
             </div>

             <div class="col-md-6">
               <div class="form-group form-float">
          <input type="checkbox" name="admin_approval" id="admin_approval" <?php if (isset($result['admin_approval']) && $result['admin_approval'] == 1) {echo 'checked="checked"';}?> value="1" />
          <label for="admin_approval"><?php echo lang('admin_approval'); ?></label>
               </div>
             </div>
       </div>
    <div class="col-md-12">
      <div class="col-md-12">
        <div class="form-group form-float m-t-20">
          <div class="form-line">
          <label class="form-label "><?php echo lang('default_user_role'); ?> *</label>
            <select name="user_type[]" class="form-control m-t-10" multiple="multiple">
              <?php $permissiona = getAllDataByTable('ia_permission');
foreach ($permissiona as $perkey => $value) {
    $user_type = isset($value->user_type) ? $value->user_type : '';
    if ($user_type != 'admin') {
        $old = json_decode($result['user_type']);
        ?>
                    <option value="<?php echo $user_type; ?>" <?php if (in_array(strtolower($user_type), array_map('strtolower', $old))) {echo 'selected';}?>><?php echo ucfirst($user_type); ?></option>
                <?php }}?>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12 ">
          <div class="sub-btn-wdt col-md-4 col-md-offset-4 m-t-5">
            <?php if(CheckPermission('setting', "all_update")){ ?>
            <input type="submit" value="<?php echo lang('save'); ?>" style="width: 100%" class="btn btn-primary btn-lg waves-effect">
            <?php } ?>
          </div>
        </div>
      </div>
    </div>


</form>
