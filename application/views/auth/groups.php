<?php if (!empty($message)) { ?>
<div class="alert alert-info" role="status"><?php echo html_escape(strip_tags($message)); ?></div>
<?php } ?>

<div class="row">
  <div class="col-md-12">
    <div class="box">
      <div class="box-header with-border">
        <div class="pull-right">
          <?php echo anchor('auth/create_group', '<i class="fa fa-plus"></i> Tambah Group', array('class' => 'btn btn-primary')); ?>
          <?php echo anchor('auth', '<i class="fa fa-users"></i> Kembali ke User', array('class' => 'btn btn-default')); ?>
        </div>
      </div>
      <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Nama Group</th>
              <th>Deskripsi</th>
              <th>Jumlah User</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($groups)) { ?>
            <tr><td colspan="4" class="text-center">Belum ada group.</td></tr>
            <?php } ?>
            <?php foreach ($groups as $group) { ?>
            <tr>
              <td><?php echo html_escape($group->name); ?></td>
              <td><?php echo html_escape($group->description); ?></td>
              <td><?php echo (int) $group->user_count; ?></td>
              <td>
                <?php echo anchor('auth/edit_group/'.$group->id, 'Edit', array('class' => 'btn btn-xs btn-default')); ?>
                <?php if ($group->name === $this->config->item('admin_group', 'ion_auth')) { ?>
                  <button type="button" class="btn btn-xs btn-danger" disabled="disabled" title="Group admin tidak dapat dihapus">Hapus</button>
                <?php } else { ?>
                  <?php echo form_open('auth/delete_group/'.$group->id, array('class' => 'inline-form', 'style' => 'display:inline', 'onsubmit' => 'return confirm("Hapus group ini? User yang terdaftar akan kehilangan akses melalui group tersebut.");')); ?>
                  <?php echo form_hidden('id', $group->id); ?>
                  <?php echo form_hidden($csrf); ?>
                  <button type="submit" class="btn btn-xs btn-danger">Hapus</button>
                  <?php echo form_close(); ?>
                <?php } ?>
              </td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
