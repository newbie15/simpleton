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
        </div>
      </div>
      <div class="box-body">
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
