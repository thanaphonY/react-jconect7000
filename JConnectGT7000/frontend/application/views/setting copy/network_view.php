<div class="row card">
  <div class="header">
    <h5><?php echo lang('networkSetting'); ?></h5>
  </div>
</div>
<!-- Ethernet 1 -->
<div class="row card">

  <div class="col-md-12">

    <div class="col-md-5">
      <div class="box-header my-header">
        <h5 class="box-title">
          <?php //echo lang('user_role'); 
          ?>
          Ethernet 1
          <span class="pull-right">
          </span>
        </h5>
        <hr>
      </div>
      <?php
      $eth1 = json_decode($result['eth1']);
      $eth2 = json_decode($result['eth2']);
      $dns = json_decode($result['dns']);
      $route = json_decode($result['route']);
      $j1000 = json_decode($result['j1000gateway']);
      ?>
      <form id="eth1conf" method="post" action="<?php echo base_url(); ?>setting/updateEth1">
        <div class="col-md-12">
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <select class="form-control select2" id="dhcp" name="dhcp" style="width: 100%;">
                <option value="1" <?php echo ($eth1->dhcp == "1") ? "selected=\"selected\"" : ""; ?>>Enable</option>
                <option value="0" <?php echo ($eth1->dhcp == "0") ? "selected=\"selected\"" : ""; ?>>Disable</option>
              </select>
              <label for="" class="form-label">DHCP</label>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="ipv4" class="form-control input-lg" value="<?php echo isset($eth1->ipv4) ? $eth1->ipv4 : ''; ?>" required>
              <label for="" class="form-label">IPv4</label>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="subnet" class="form-control input-lg" value="<?php echo isset($eth1->subnet) ? $eth1->subnet : ''; ?>" required>
              <label for="" class="form-label">Subnet</label>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="gateway" class="form-control input-lg" value="<?php echo isset($eth1->gateway) ? $eth1->gateway : ''; ?>" required>
              <label for="" class="form-label">Gateway</label>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="dns1" class="form-control input-lg" value="<?php echo isset($eth1->dns1) ? $eth1->dns1 : ''; ?>" required>
              <label for="" class="form-label">Primary DNS</label>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="dns2" class="form-control input-lg" value="<?php echo isset($eth1->dns2) ? $eth1->dns2 : ''; ?>" required>
              <label for="" class="form-label">Secondary DNS</label>
            </div>
          </div>
        </div>
        <div class="form-group form-float m-t-30 ia_user_role_div">
          <?php if (CheckPermission('setting', "all_update")) { ?>
            <button class="btn btn-primary"><?php echo lang('save'); ?></button>
          <?php } ?>
        </div>
      </form>
    </div>


  </div>
</div>

<!-- Ethernet 2 -->
<div class="row card">

  <div class="col-md-12">

    <div class="col-md-5">
      <div class="box-header my-header">
        <h5 class="box-title">
          <?php //echo lang('user_role'); 
          ?>
          Ethernet 2
          <span class="pull-right">


          </span>
        </h5>
        <hr>
      </div>
      <form id="eth2conf" method="post" action="<?php echo base_url(); ?>setting/updateEth2">

        <div class="form-group form-float">
          <div class="form-line">
            <input type="text" style="display: none">
            <select class="form-control select2" id="dhcp" name="dhcp" style="width: 100%;">
              <option value="1" <?php echo ($eth2->dhcp == "1") ? "selected=\"selected\"" : ""; ?>>Enable</option>
              <option value="0" <?php echo ($eth2->dhcp == "0") ? "selected=\"selected\"" : ""; ?>>Disable</option>
            </select>
            <label for="" class="form-label">DHCP</label>
          </div>
        </div>
        <div class="form-group form-float">
          <div class="form-line">
            <input type="text" style="display: none">
            <input type="text" name="ipv4" class="form-control input-lg" value="<?php echo isset($eth2->ipv4) ? $eth2->ipv4 : ''; ?>" required>
            <label for="" class="form-label">IPv4</label>
          </div>
        </div>
        <div class="form-group form-float">
          <div class="form-line">
            <input type="text" style="display: none">
            <input type="text" name="subnet" class="form-control input-lg" value="<?php echo isset($eth2->subnet) ? $eth2->subnet : ''; ?>" required>
            <label for="" class="form-label">Subnet</label>
          </div>
        </div>

        <div class="form-group form-float m-t-30 ia_user_role_div">

          <?php if (CheckPermission('setting', "all_update")) { ?>
            <button class="btn btn-primary"><?php echo lang('save'); ?></button>
          <?php } ?>

        </div>

      </form>
    </div>
    <!--
    <div class="col-md-5">
      <div class="box-header my-header">
        <h5 class="box-title">
          <?php //echo lang('user_role'); 
          ?>
          Status
          <span class="pull-right">


          </span>
        </h5>
        <hr>
      </div>
      <form method="post" action="<?php echo base_url(); ?>setting/updateEth2">


        <div class="form-group form-float m-t-30 ia_user_role_div">


        </div>
      </form>
    </div>

          -->

  </div>
</div>



<!-- Alarm Route -->

<div class="row card">
  <div class="header">
    <h5>Alarm Network Route</h5>
  </div>
  <div class="col-md-12">

    <div class="col-md-5">
      <form method="post" action="<?php echo base_url(); ?>setting/updateRount">
        <div class="col-md-12">

          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <select class="form-control select2" id="line" name="line" style="width: 100%;">
                <option value="0" <?php echo ($route->line == "0") ? "selected=\"selected\"" : ""; ?>>Auto Select</option>
                <option value="1" <?php echo ($route->line == "1") ? "selected=\"selected\"" : ""; ?>>Ethernet 1</option>
                <option value="2" <?php echo ($route->line == "2") ? "selected=\"selected\"" : ""; ?>>ConnectPlus J1000</option>
              </select>
              <label for="" class="form-label">LINE Notify</label>
            </div>
          </div>

          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <select class="form-control select2" id="email" name="email" style="width: 100%;">
                <option value="0" <?php echo ($route->email == "0") ? "selected=\"selected\"" : ""; ?>>Auto Select</option>
                <option value="1" <?php echo ($route->email == "1") ? "selected=\"selected\"" : ""; ?>>Ethernet 1</option>
                <option value="2" <?php echo ($route->email == "2") ? "selected=\"selected\"" : ""; ?>>ConnectPlus J1000</option>
              </select>
              <label for="" class="form-label">Email</label>
            </div>
          </div>

          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <select class="form-control select2" id="sms" name="sms" style="width: 100%;">
                <option value="0" <?php echo ($route->sms == "0") ? "selected=\"selected\"" : ""; ?>>Auto Select</option>
                <option value="2" <?php echo ($route->sms == "2") ? "selected=\"selected\"" : ""; ?>>ConnectPlus J1000</option>
              </select>
              <label for="" class="form-label">SMS</label>
            </div>
          </div>
        </div>

        <div class="form-group form-float m-t-30 ia_user_role_div">
          <?php if (CheckPermission('setting', "all_update")) { ?>
            <button class="btn btn-primary"><?php echo lang('save'); ?></button>
          <?php } ?>
        </div>

      </form>
    </div>


    <div class="col-md-5">
      <div class="box-header my-header">
        <h5 class="box-title">
          <?php //echo lang('user_role'); 
          ?>
          ConnectPlus J1000
          <span class="pull-right">


          </span>
        </h5>
        <hr>
      </div>
      <form id="j1000gateway" method="post" action="<?php echo base_url(); ?>setting/j1000gateway">
        <div class="form-group form-float">
          <div class="form-line">
            <input type="text" style="display: none">
            <input type="text" name="ipv4" class="form-control input-lg" value="<?php echo isset($j1000->ipv4) ? $j1000->ipv4 : ''; ?>" required>
            <label for="" class="form-label">J1000 Gateway IP Address</label>
          </div>
        </div>
        <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="access" class="form-control input-lg" value="<?php echo isset($j1000->access) ? $j1000->access : ''; ?>" required>
              <label for="" class="form-label">Access key</label>
            </div>
          </div>
        <div class="form-group form-float m-t-30 ia_user_role_div">
          <?php if (CheckPermission('setting', "all_update")) { ?>
            <button class="btn btn-primary"><?php echo lang('save'); ?></button>
          <?php } ?>
        </div>

      </form>
    </div>


  </div>
</div>

<script type="text/javascript">
  $(function() {
    $.validator.addMethod('IP4Checker', function(value) {
      return value.match(/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/);
    }, 'Invalid IP address');

    $('#eth1conf').validate({
      rules: {
        ipv4: {
          required: true,
          IP4Checker: true
        },
        subnet: {
          required: true,
          IP4Checker: true
        },
        gateway: {
          required: true,
          IP4Checker: true
        },
        dns1: {
          required: true,
          IP4Checker: true
        },
        dns2: {
          required: true,
          IP4Checker: true
        }
      }
    });
    $('#eth2conf').validate({
      rules: {
        ipv4: {
          required: true,
          IP4Checker: true
        },
        subnet: {
          required: true,
          IP4Checker: true
        }
      }
    });

    $('#j1000gateway').validate({
      rules: {
        ipv4: {
          required: true,
          IP4Checker: true
        }
      }
    });

  });
</script>