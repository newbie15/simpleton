<div class="box box-default">
  <div class="box-header with-border">
    <h3 class="box-title">Aktifkan akun user</h3>
  </div>
  <div class="box-body">
    <p>Yakin ingin mengaktifkan akun <strong><?php echo html_escape($user->email); ?></strong>?</p>
    <?php echo form_open('auth/activate/'.$user->id); ?>
      <input type="hidden" name="confirm" value="yes">
      <?php echo form_hidden($csrf); ?>
      <?php echo form_hidden(array('id' => $user->id)); ?>
      <button class="btn btn-primary" type="submit">Aktifkan</button>
      <a class="btn btn-default" href="<?php echo site_url('auth'); ?>">Batal</a>
    <?php echo form_close(); ?>
  </div>
</div>
