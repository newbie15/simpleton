		<?php if (!empty($message)) { ?>
		  <div class="alert alert-info" role="status"><?php echo html_escape(strip_tags($message)); ?></div>
		<?php } ?>
		<?php $users = isset($users) && is_array($users) ? $users : array(); ?>
		<div class="row">
		    <div class="col-md-12">
		      <div class="box">
		        <div class="box-header with-border">
		          <div class="pull-right">
		            <?php echo anchor('auth/create_user', '<span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Tambah User', array('class' => 'btn btn-primary')); ?>
		            <?php echo anchor('auth/create_group', '<span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Tambah Group', array('class' => 'btn btn-default')); ?>
		          </div>
		        </div><!-- /.box-header -->
		        <div class="box-body">
		          <div class="table-responsive">
		          <table class="table table-bordered table-striped">
		            <tbody><tr>
		              <th><?php echo lang('index_fname_th');?></th>
		              <th><?php echo lang('index_lname_th');?></th>
		              <th>NPK</th>
		              <th><?php echo lang('index_email_th');?></th>
                      <th><?php echo lang('index_groups_th');?></th>
                      <th><?php echo lang('index_status_th');?></th>
                      <th><?php echo lang('index_action_th');?></th>
                    </tr>
                    <?php if (empty($users)) { ?>
                    <tr><td colspan="7" class="text-center">Belum ada user.</td></tr>
                    <?php } ?>
                    <?php foreach ($users as $user):?>
					<tr>
			            <td><?php echo htmlspecialchars($user->first_name,ENT_QUOTES,'UTF-8');?></td>
			            <td><?php echo htmlspecialchars($user->last_name,ENT_QUOTES,'UTF-8');?></td>
			            <td><?php echo htmlspecialchars(isset($user->npk) ? $user->npk : '',ENT_QUOTES,'UTF-8');?></td>
			            <td><?php echo htmlspecialchars(isset($user->email) ? $user->email : '',ENT_QUOTES,'UTF-8');?></td>
						<td>
							<?php foreach ($user->groups as $group):?>
								<?php echo anchor("auth/edit_group/".$group->id, htmlspecialchars($group->name,ENT_QUOTES,'UTF-8')); ?><br />
			                <?php endforeach?>
						</td>
						<td><?php echo ($user->active) ?
                '<span class="label label-success">'.htmlspecialchars(lang('index_active_link'), ENT_QUOTES, 'UTF-8').'</span> '.anchor("auth/deactivate/".$user->id, 'Nonaktifkan')
                :
                '<span class="label label-default">'.htmlspecialchars(lang('index_inactive_link'), ENT_QUOTES, 'UTF-8').'</span> '.anchor("auth/activate/".$user->id, 'Aktifkan'); ?></td>
            <td><?php echo anchor("auth/edit_user/".$user->id, 'Edit', array('class' => 'btn btn-xs btn-default')); ?></td>
					</tr>
				<?php endforeach;?>
                  </tbody></table>
                  </div>
                </div><!-- /.box-body -->
              </div><!-- /.box -->
            </div>
          </div>