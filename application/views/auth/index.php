<?php if (!empty($message)) { ?>
<div class="alert alert-info" role="status"><?php echo html_escape(strip_tags($message)); ?></div>
<?php } ?>

<div class="row">
  <div class="col-md-12">
    <div class="box">
      <div class="box-header with-border">
        <div class="pull-right">
          <?php echo anchor('auth/create_user', '<span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Tambah User', array('class' => 'btn btn-primary')); ?>
          <?php echo anchor('auth/groups', '<i class="fa fa-users"></i> Kelola Groups', array('class' => 'btn btn-default')); ?>
          <form id="sync-karyawan-form" method="post" action="<?php echo site_url('auth/sync_karyawan'); ?>" data-ajax-url="<?php echo html_escape(site_url('auth/sync_karyawan')); ?>" style="display:inline">
            <input type="hidden" name="sync_token" value="<?php echo html_escape($sync_token); ?>">
            <button id="sync-karyawan-button" class="btn btn-success" type="submit"><i class="fa fa-refresh" aria-hidden="true"></i> Sync Karyawan</button>
          </form>
        </div>
      </div>
      <div class="box-body">
        <div class="alert alert-warning" role="note">
          Sinkronisasi membuat akun aktif dalam group <strong>members</strong>. Username dan password awal menggunakan NPK; minta anggota mengganti password setelah login.
        </div>
        <div id="sync-karyawan-progress" class="alert alert-info" role="status" aria-live="polite" hidden>
          <strong id="sync-karyawan-status">Menyiapkan sinkronisasi...</strong>
          <div class="progress progress-striped active" style="margin: 10px 0 5px;">
            <div id="sync-karyawan-progress-bar" class="progress-bar progress-bar-success" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width: 0%">0%</div>
          </div>
          <div id="sync-karyawan-details"></div>
        </div>
        <div class="table-responsive">
          <table
            id="users-table"
            class="table table-bordered table-striped"
            data-ajax-url="<?php echo html_escape(site_url('auth/users_datatable')); ?>"
            data-edit-url="<?php echo html_escape(site_url('auth/edit_user')); ?>"
            data-activate-url="<?php echo html_escape(site_url('auth/activate')); ?>"
            data-deactivate-url="<?php echo html_escape(site_url('auth/deactivate')); ?>">
            <thead>
              <tr>
                <th><?php echo lang('index_fname_th'); ?></th>
                <th><?php echo lang('index_lname_th'); ?></th>
                <th>NPK</th>
                <th><?php echo lang('index_email_th'); ?></th>
                <th><?php echo lang('index_groups_th'); ?></th>
                <th><?php echo lang('index_status_th'); ?></th>
                <th><?php echo lang('index_action_th'); ?></th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
