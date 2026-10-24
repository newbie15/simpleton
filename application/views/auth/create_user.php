<?php if ($message) { ?>
<div class="callout callout-danger" id="infoMessage">
  <h4><i class="fa fa-warning"></i> Warning !</h4>
  <p><?php echo $message;?></p>
</div>
<?php } ?>

<div class="box box-default">
  <div class="box-header with-border">
    <h3 class="box-title"><?php echo lang('create_user_subheading');?></h3>
    <div class="box-tools pull-right">
      <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
    </div>
  </div><!-- /.box-header -->
  <?php echo form_open("auth/create_user", 'class="form-horizontal" role="form"');?>
  <div class="box-body">

    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="first_name" class="col-sm-4 control-label"><?php echo lang('create_user_fname_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($first_name, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label for="last_name" class="col-sm-4 control-label"><?php echo lang('create_user_lname_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($last_name, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
    </div><!-- /.row -->

    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="npk" class="col-sm-4 control-label">NPK <small class="text-muted">(opsional)</small></label>
          <div class="col-sm-8">
            <?php echo form_input($npk, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <?php if($identity_column!=='email') { ?>
        <div class="form-group">
          <label for="identity" class="col-sm-4 control-label">Username login</label>
          <div class="col-sm-8">
            <?php echo form_error('identity'); echo form_input($identity, '', 'class="form-control"');?>
          </div>
        </div>
        <?php } else { ?>
        <div class="form-group">
          <label for="company" class="col-sm-4 control-label"><?php echo lang('create_user_company_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($company, '', 'class="form-control"');?>
          </div>
        </div>
        <?php } ?>
      </div>
    </div><!-- /.row -->

    <?php if($identity_column!=='email') { ?>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="company" class="col-sm-4 control-label"><?php echo lang('create_user_company_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($company, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label for="email" class="col-sm-4 control-label"><?php echo lang('create_user_email_label');?> <small class="text-muted">(opsional)</small></label>
          <div class="col-sm-8">
            <?php echo form_input($email, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
    </div><!-- /.row -->
    <?php } else { ?>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="email" class="col-sm-4 control-label"><?php echo lang('create_user_email_label');?> <small class="text-muted">(opsional)</small></label>
          <div class="col-sm-8">
            <?php echo form_input($email, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label for="phone" class="col-sm-4 control-label"><?php echo lang('create_user_phone_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($phone, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
    </div><!-- /.row -->
    <?php } ?>

    <?php if($identity_column!=='email') { ?>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="phone" class="col-sm-4 control-label"><?php echo lang('create_user_phone_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($phone, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <!-- spacer agar password tetap 2 kolom di baris berikutnya -->
      </div>
    </div><!-- /.row -->
    <?php } ?>

    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="password" class="col-sm-4 control-label"><?php echo lang('create_user_password_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($password, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label for="password_confirm" class="col-sm-4 control-label"><?php echo lang('create_user_password_confirm_label');?></label>
          <div class="col-sm-8">
            <?php echo form_input($password_confirm, '', 'class="form-control"');?>
          </div>
        </div>
      </div>
    </div><!-- /.row -->

  </div><!-- /.box-body -->
  <div class="box-footer">
    <div class="col-sm-offset-2 col-sm-10" style="padding-left:0;">
      <?php echo form_submit('submit', lang('create_user_submit_btn'), 'class="btn btn-primary"');?>
      <a href="<?php echo site_url('auth'); ?>" class="btn btn-default" onclick="if (document.referrer) { history.back(); return false; }"><i class="fa fa-times"></i> Cancel</a>
    </div>
  </div><!-- /.box-footer -->
  <?php echo form_close(); ?>
</div><!-- /.box -->