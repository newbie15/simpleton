<?php if ($message) { ?>
<div class="alert alert-danger" role="alert">
  <h4><i class="fa fa-warning"></i> Warning!</h4>
  <p><?php echo $message;?></p>
</div>
<?php } ?>

<div class="box box-default">
  <div class="box-header with-border">
    <h3 class="box-title"><?php echo lang('edit_user_subheading');?></h3>
    <div class="box-tools pull-right">
      <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
    </div>
  </div><!-- /.box-header -->

  <div class="box-body">
    <?php echo form_open(uri_string());?>
    
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label><?php echo lang('edit_user_fname_label', 'first_name');?></label>
          <?php echo form_input($first_name, '', 'class="form-control"');?>
        </div>
        <div class="form-group">
          <label><?php echo lang('edit_user_lname_label', 'last_name');?></label>
          <?php echo form_input($last_name, '', 'class="form-control"');?>
        </div>
        <div class="form-group">
          <label for="npk">NPK (opsional)</label>
          <?php echo form_input($npk, '', 'class="form-control"');?>
        </div>
        <?php if ($this->ion_auth->is_admin()) { ?>
        <div class="form-group">
          <label for="telegram_pin">PIN Bot Telegram (default 123456 jika dikosongkan)</label>
          <?php echo form_input($telegram_pin, '', 'class="form-control"');?>
        </div>
        <?php } ?>
      </div><!-- /.col -->
      
      <div class="col-md-6">
        <div class="form-group">
          <label><?php echo lang('edit_user_company_label', 'company');?></label>
          <?php echo form_input($company, '', 'class="form-control"');?>
        </div>
        <div class="form-group">
          <label><?php echo lang('edit_user_phone_label', 'phone');?></label>
          <?php echo form_input($phone, '', 'class="form-control"');?>
        </div>
      </div><!-- /.col -->
    </div><!-- /.row -->

    <div class="alert alert-info" role="alert">
      <small>Kosongkan password jika tidak ingin mengubah</small>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label><?php echo lang('edit_user_validation_old_password_label', 'old_password');?></label>
          <?php echo form_input($old_password, '', 'class="form-control" autocomplete="current-password"');?>
          <small class="form-text text-muted">Masukkan password lama untuk mengubah password</small>
        </div>
      </div><!-- /.col -->

      <div class="col-md-6"></div><!-- /.col -->
    </div><!-- /.row -->

    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label><?php echo lang('edit_user_validation_new_password_label', 'password');?></label>
          <?php echo form_input($password, '', 'class="form-control" autocomplete="new-password"');?>
        </div>
      </div><!-- /.col -->

      <div class="col-md-6">
        <div class="form-group">
          <label><?php echo lang('edit_user_validation_new_password_confirm_label', 'password_confirm');?></label>
          <?php echo form_input($password_confirm, '', 'class="form-control" autocomplete="new-password"');?>
        </div>
      </div><!-- /.col -->
    </div><!-- /.row -->

    <?php if ($this->ion_auth->is_admin()): ?>
    <div class="form-group">
      <label><?php echo lang('edit_user_groups_heading');?></label>
      <div class="form-group">
        <?php foreach ($groups as $group):?>
          <div class="checkbox icheck">
            <label>
            <?php
              $gID = $group['id'];
              $checked = FALSE;
              foreach ($currentGroups as $grp) {
                if ($gID == $grp->id) {
                  $checked = TRUE;
                  break;
                }
              }
            ?>
            <?php echo form_checkbox('groups[]', $group['id'], $checked, 'id="group_'.$group['id'].'"');?>
            <?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8');?>
            </label>
          </div>
        <?php endforeach?>
      </div>
    </div>
    <?php endif ?>

    <?php echo form_hidden('id', $user->id);?>
    <?php echo form_hidden($csrf); ?>
  </div><!-- /.box-body -->

  <div class="box-footer">
    <?php echo form_submit('submit', lang('edit_user_submit_btn'), 'class="btn btn-primary"');?>
    <a href="<?php echo site_url('auth/index'); ?>" class="btn btn-default">Batal</a>
    <?php echo form_close();?>
  </div><!-- /.box-footer -->
</div><!-- /.box -->

<script src="<?= base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js') ?>"></script>
<script src="<?= base_url('assets/adminlte/bootstrap/js/bootstrap.min.js') ?>"></script>
<script src="<?= base_url('assets/adminlte/plugins/iCheck/icheck.min.js') ?>"></script>
<script>
  $(function () {
    $('input').iCheck({
      checkboxClass: 'icheckbox_square-blue',
      radioClass: 'iradio_square-blue',
      increaseArea: '20%'
    });
  });
</script>
