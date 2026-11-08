<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$mysql_bin = dirname(dirname(rtrim(FCPATH, '/\\'))).DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR;
$config['database_backup'] = array(
	'directory' => dirname(dirname(rtrim(FCPATH, '/\\'))).DIRECTORY_SEPARATOR.'simpleton-private-backups',
	'dump_binary' => $mysql_bin.'mysqldump.exe',
	'restore_binary' => $mysql_bin.'mysql.exe'
);
