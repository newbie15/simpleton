<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

	const DEFAULT_TELEGRAM_PIN = '123456';

	function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library(array('ion_auth','form_validation'));
		$this->load->library('page_access');
		$this->load->helper(array('url','language'));

		$this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));

		$this->lang->load('auth');
	}

	// redirect if needed, otherwise display the user list
	function index()
	{

		if (!$this->ion_auth->logged_in())
		{
			// redirect them to the login page
			redirect('auth/login', 'refresh');
		}
		elseif (!$this->ion_auth->is_admin()) // remove this elseif if you want to enable this for non-admins
		{
			// redirect them to the home page because they must be an administrator to view this
			return show_error('You must be an administrator to view this page.');
		}
		else
		{
			$this->_ensure_default_telegram_pins();
			// set the flash data error message if there is one
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			$this->data['csrf'] = $this->_get_csrf_nonce();

            ////////Config Halaman////////
            $data['judul_besar'] = 'Manage User';
            $data['judul_kecil'] = 'List';
			$data['m_auth_index'] = TRUE;
			$data['output'] = array(
				'message' => $this->data['message'],
				'csrf' => $this->data['csrf'],
				'sync_token' => $this->_sync_karyawan_token(),
			);
			$data['view_file'] = 'auth/index';
			$this->load->view('auth/main', $data, FALSE);
		}
	}

	function users_datatable()
	{
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			show_error('You must be an administrator to view this page.', 403);
		}

		$tables = $this->config->item('tables', 'ion_auth');
		$users_table = $tables['users'];
		$users_groups_table = $tables['users_groups'];
		$groups_table = $tables['groups'];
		$has_npk = $this->db->field_exists('npk', $users_table);
		$draw = max(0, (int) $this->input->get('draw'));
		$start = max(0, (int) $this->input->get('start'));
		$length = (int) $this->input->get('length');
		if ($length < 1)
		{
			$length = 10;
		}
		$length = min($length, 100);

		$search_data = $this->input->get('search');
		$search = is_array($search_data) && isset($search_data['value'])
			? trim(substr($search_data['value'], 0, 100))
			: '';

		$total = (int) $this->db->count_all($users_table);
		$filtered = $total;
		if ($search !== '')
		{
			$this->db->from($users_table);
			$this->_apply_user_search($search, $users_table, $users_groups_table, $groups_table, $has_npk);
			$count_row = $this->db->select('COUNT(DISTINCT '.$users_table.'.id) AS total', FALSE)
				->get()
				->row();
			$filtered = $count_row ? (int) $count_row->total : 0;
		}

		$columns = array(
			0 => 'first_name',
			1 => 'last_name',
			2 => $has_npk ? 'npk' : 'first_name',
			3 => 'email',
			5 => 'active',
			6 => 'id',
		);
		$order_data = $this->input->get('order');
		$order = is_array($order_data) && isset($order_data[0]) ? $order_data[0] : array();
		$order_column = isset($order['column']) ? (int) $order['column'] : 0;
		$order_direction = isset($order['dir']) && strtolower($order['dir']) === 'desc' ? 'DESC' : 'ASC';
		$order_by = isset($columns[$order_column]) ? $columns[$order_column] : 'first_name';

		$select = array(
			$users_table.'.id',
			$users_table.'.first_name',
			$users_table.'.last_name',
			$users_table.'.email',
			$users_table.'.active',
		);
		$select[] = $has_npk ? $users_table.'.npk' : "'' AS npk";

		$this->db->select($select)->from($users_table);
		if ($search !== '')
		{
			$this->_apply_user_search($search, $users_table, $users_groups_table, $groups_table, $has_npk);
		}
		$this->db->order_by($users_table.'.'.$order_by, $order_direction);
		$this->db->order_by($users_table.'.id', 'ASC');
		$users = $this->db->limit($length, $start)->get()->result_array();

		$user_ids = array();
		foreach ($users as $user)
		{
			$user_ids[] = (int) $user['id'];
		}

		$user_groups = array();
		if (!empty($user_ids))
		{
			$group_rows = $this->db
				->select($users_groups_table.'.user_id, '.$groups_table.'.id, '.$groups_table.'.name')
				->from($users_groups_table)
				->join($groups_table, $groups_table.'.id = '.$users_groups_table.'.group_id', 'inner')
				->where_in($users_groups_table.'.user_id', $user_ids)
				->order_by($groups_table.'.name', 'ASC')
				->get()
				->result_array();
			foreach ($group_rows as $group)
			{
				$user_id = (int) $group['user_id'];
				if (!isset($user_groups[$user_id]))
				{
					$user_groups[$user_id] = array();
				}
				$user_groups[$user_id][] = array(
					'id' => (int) $group['id'],
					'name' => $group['name'],
				);
			}
		}

		foreach ($users as &$user)
		{
			$user['groups'] = isset($user_groups[(int) $user['id']]) ? $user_groups[(int) $user['id']] : array();
			$user['active'] = (bool) $user['active'];
		}
		unset($user);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'draw' => $draw,
				'recordsTotal' => $total,
				'recordsFiltered' => $filtered,
				'data' => $users,
			)));
	}

	function sync_karyawan()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			$this->_sync_json_response(array('error' => 'Metode permintaan tidak diizinkan.'), 405);
			return;
		}
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			$this->_sync_json_response(array('error' => 'Anda harus menjadi administrator untuk menyinkronkan karyawan.'), 403);
			return;
		}
		$expected_token = $this->session->userdata('sync_karyawan_token');
		$posted_token = $this->input->post('sync_token', TRUE);
		if (!is_string($expected_token) || $expected_token === '' ||
			!is_string($posted_token) || $posted_token !== $expected_token)
		{
			$this->_sync_json_response(array('error' => $this->lang->line('error_csrf')), 403);
			return;
		}

		$action = $this->input->post('action', TRUE);
		$tables = $this->config->item('tables', 'ion_auth');
		$users_table = $tables['users'];
		$groups_table = $tables['groups'];
		if (!$this->db->table_exists('karyawan') ||
			!$this->db->field_exists('id', 'karyawan') ||
			!$this->db->field_exists('npk', 'karyawan') ||
			!$this->db->field_exists('nama', 'karyawan') ||
			!$this->db->field_exists('username', $users_table) ||
			!$this->db->field_exists('npk', $users_table) ||
			!$this->db->field_exists('first_name', $users_table) ||
			!$this->db->field_exists('last_name', $users_table))
		{
			$this->_sync_json_response(array('error' => 'Skema karyawan atau users belum siap untuk sinkronisasi.'), 500);
			return;
		}

		$member_group = $this->db->get_where($groups_table, array('name' => 'members'))->row();
		if (!$member_group)
		{
			$this->_sync_json_response(array('error' => 'Group members tidak ditemukan. Buat group tersebut sebelum sinkronisasi.'), 500);
			return;
		}

		if ($action === 'start')
		{
			$total = (int) $this->db->count_all('karyawan');
			$job = array(
				'last_id' => 0,
				'total' => $total,
				'processed' => 0,
				'created' => 0,
				'skipped' => 0,
			);
			$this->session->set_userdata('sync_karyawan_job', $job);
			if ($total === 0)
			{
				$this->session->unset_userdata('sync_karyawan_job');
				$this->session->unset_userdata('sync_karyawan_token');
			}
			$this->_sync_json_response(array(
				'total' => $total,
				'processed' => 0,
				'created' => 0,
				'skipped' => 0,
				'complete' => $total === 0,
			));
			return;
		}

		if ($action !== 'batch')
		{
			$this->_sync_json_response(array('error' => 'Aksi sinkronisasi tidak dikenal.'), 400);
			return;
		}

		$job = $this->session->userdata('sync_karyawan_job');
		if (!is_array($job) || !isset($job['last_id'], $job['total'], $job['processed'], $job['created'], $job['skipped']))
		{
			$this->_sync_json_response(array('error' => 'Sesi sinkronisasi tidak ditemukan. Mulai ulang proses sinkronisasi.'), 409);
			return;
		}

		$batch_size = 10;
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$employee_query = $this->db
			->select('id, npk, nama')
			->from('karyawan')
			->where('id >', (int) $job['last_id'])
			->order_by('id', 'ASC')
			->limit($batch_size)
			->get();
		if ($employee_query === FALSE)
		{
			$error = $this->db->error();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal membaca batch karyawan untuk sinkronisasi users: '.$error['code'].' '.$error['message']);
			$this->_sync_json_response(array('error' => 'Batch karyawan gagal dibaca. Silakan coba lagi.'), 500);
			return;
		}
		$employees = $employee_query->result();
		if (empty($employees))
		{
			$this->db->db_debug = $previous_db_debug;
			$this->session->unset_userdata('sync_karyawan_job');
			$this->session->unset_userdata('sync_karyawan_token');
			$this->_sync_json_response(array(
				'total' => (int) $job['total'],
				'processed' => (int) $job['processed'],
				'created' => (int) $job['created'],
				'skipped' => (int) $job['skipped'],
				'complete' => TRUE,
			));
			return;
		}

		if (!$this->db->trans_begin())
		{
			$this->db->db_debug = $previous_db_debug;
			$this->_sync_json_response(array('error' => 'Database tidak dapat memulai batch sinkronisasi.'), 500);
			return;
		}

		$created = 0;
		$skipped = 0;
		$seen_npk = array();
		$string_length = function ($value) {
			if (function_exists('mb_strlen'))
			{
				return mb_strlen($value, 'UTF-8');
			}
			return function_exists('iconv_strlen') ? iconv_strlen($value, 'UTF-8') : strlen($value);
		};
		foreach ($employees as $employee)
		{
			$job['last_id'] = (int) $employee->id;
			$job['processed']++;
			$npk = trim((string) $employee->npk);
			$name = trim(preg_replace('/\s+/', ' ', (string) $employee->nama));
			$name_parts = preg_split('/\s+/', $name, 2);
			$first_name = isset($name_parts[0]) ? $name_parts[0] : '';
			$last_name = isset($name_parts[1]) ? $name_parts[1] : '';
			$first_name_length = $string_length($first_name);
			$last_name_length = $string_length($last_name);
			if ($npk === '' || strlen($npk) > 32 || $name === '' ||
				$first_name_length === FALSE || $first_name_length > 50 ||
				$last_name_length === FALSE || $last_name_length > 50 ||
				isset($seen_npk[$npk]))
			{
				$skipped++;
				continue;
			}
			$seen_npk[$npk] = TRUE;

			$existing_user_query = $this->db
				->from($users_table)
				->group_start()
				->where('npk', $npk)
				->or_where('username', $npk)
				->group_end()
				->limit(1)
				->get();
			if ($existing_user_query === FALSE)
			{
				$error = $this->db->error();
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				log_message('error', 'Gagal memeriksa akun saat sinkronisasi karyawan: '.$error['code'].' '.$error['message']);
				$this->_sync_json_response(array('error' => 'Pemeriksaan akun gagal pada batch ini. Tidak ada akun dari batch ini yang disimpan.'), 500);
				return;
			}
			$existing_user = $existing_user_query->row();
			if ($existing_user)
			{
				$skipped++;
				continue;
			}

			$additional_data = array(
				'npk' => $npk,
				'first_name' => $first_name,
				'last_name' => $last_name
			);
			if ($this->db->field_exists('telegram_pin', $users_table))
			{
				$additional_data['telegram_pin'] = self::DEFAULT_TELEGRAM_PIN;
			}
			$user_id = $this->ion_auth->register(
				$npk,
				$npk,
				NULL,
				$additional_data,
				array((int) $member_group->id)
			);
			if (!$user_id || $this->db->trans_status() === FALSE)
			{
				$error = $this->db->error();
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				log_message('error', 'Gagal membuat akun member saat sinkronisasi karyawan: '.$error['code'].' '.$error['message']);
				$this->_sync_json_response(array('error' => 'Pembuatan akun gagal pada batch ini. Tidak ada akun dari batch ini yang disimpan.'), 500);
				return;
			}
			$created++;
		}

		if ($this->db->trans_status() === FALSE || !$this->db->trans_commit())
		{
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menyelesaikan sinkronisasi karyawan: '.$error['code'].' '.$error['message']);
			$this->_sync_json_response(array('error' => 'Batch sinkronisasi gagal disimpan. Silakan coba lagi.'), 500);
			return;
		}
		$this->db->db_debug = $previous_db_debug;

		$job['created'] += $created;
		$job['skipped'] += $skipped;
		$this->session->set_userdata('sync_karyawan_job', $job);
		$complete = count($employees) < $batch_size;
		if ($complete)
		{
			$this->session->unset_userdata('sync_karyawan_job');
			$this->session->unset_userdata('sync_karyawan_token');
		}
		$this->_sync_json_response(array(
			'total' => (int) $job['total'],
			'processed' => (int) $job['processed'],
			'created' => (int) $job['created'],
			'skipped' => (int) $job['skipped'],
			'complete' => $complete,
		));
	}

	private function _sync_json_response($data, $status = 200)
	{
		$this->output
			->set_status_header($status)
			->set_content_type('application/json')
			->set_output(json_encode($data));
	}

	private function _apply_user_search($search, $users_table, $users_groups_table, $groups_table, $has_npk)
	{
		$this->db->group_start()
			->like($users_table.'.first_name', $search)
			->or_like($users_table.'.last_name', $search)
			->or_like($users_table.'.email', $search);
		if ($has_npk)
		{
			$this->db->or_like($users_table.'.npk', $search);
		}
		$group_search = 'EXISTS (SELECT 1 FROM '.$users_groups_table.' AS user_group_search'
			.' INNER JOIN '.$groups_table.' AS group_search ON group_search.id = user_group_search.group_id'
			.' WHERE user_group_search.user_id = '.$users_table.'.id'
			.' AND group_search.name LIKE '.$this->db->escape('%'.$search.'%').')';
		$this->db->or_where($group_search, NULL, FALSE)
			->group_end();
	}

	function groups()
	{
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			show_error('You must be an administrator to view this page.', 403);
		}

		$groups = $this->ion_auth->groups()->result();
		$user_counts = $this->db
			->select('group_id, COUNT(DISTINCT user_id) AS user_count')
			->group_by('group_id')
			->get($this->config->item('tables', 'ion_auth')['users_groups'])
			->result();
		$counts_by_group = array();
		foreach ($user_counts as $count)
		{
			$counts_by_group[(int) $count->group_id] = (int) $count->user_count;
		}
		foreach ($groups as $group)
		{
			$group->user_count = isset($counts_by_group[(int) $group->id]) ? $counts_by_group[(int) $group->id] : 0;
		}

		$data['judul_besar'] = 'Manage Groups';
		$data['judul_kecil'] = 'List';
		$data['m_auth_index'] = TRUE;
		$data['output'] = array(
			'groups' => $groups,
			'message' => $this->session->flashdata('message'),
			'csrf' => $this->_get_csrf_nonce(),
		);
		$data['view_file'] = 'auth/groups';
		$this->load->view('auth/main', $data, FALSE);
	}

	// log the user in
	function login()
	{
		$this->data['title'] = "Login";

		//validate form input
		$this->form_validation->set_rules('identity', 'Identity', 'required');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if ($this->form_validation->run() == true)
		{
			// check to see if the user is logging in
			// check for "remember me"
			$remember = (bool) $this->input->post('remember');
			$identity = trim($this->input->post('identity'));
			$tables = $this->config->item('tables', 'ion_auth');
			$user = $this->db->select('username')
				->where('username', $identity)
				->limit(1)
				->get($tables['users'])
				->row();
			if (!$user)
			{
				$user = $this->db->select('username')
					->where('email', $identity)
					->limit(1)
					->get($tables['users'])
					->row();
			}
			if (!$user && $this->db->field_exists('npk', $tables['users']))
			{
				$user = $this->db->select('username')
					->where('npk', $identity)
					->limit(1)
					->get($tables['users'])
					->row();
			}
			$login_identity = $user ? $user->username : $identity;

			if ($this->ion_auth->login($login_identity, $this->input->post('password'), $remember))
			{
				//if the login is successful
				//redirect them back to the home page
				$this->session->set_flashdata('message', $this->ion_auth->messages());
				if ($this->ion_auth->in_group('members') && !$this->ion_auth->is_admin())
				{
					redirect('main/member', 'refresh');
				}
				else
				{
					redirect('/', 'refresh');
				}
			}
			else
			{
				// if the login was un-successful
				// redirect them back to the login page
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect('auth/login', 'refresh'); // use redirects instead of loading views for compatibility with MY_Controller libraries
			}
		}
		else
		{
			// the user is not logging in so display the login page
			// set the flash data error message if there is one
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');

			$this->data['identity'] = array('name' => 'identity',
				'id'    => 'identity',
				'type'  => 'text',
				'value' => $this->form_validation->set_value('identity'),
			);
			$this->data['password'] = array('name' => 'password',
				'id'   => 'password',
				'type' => 'password',
			);

			$this->_render_page('auth/login', $this->data);
		}
	}

	// log the user out
	function logout()
	{
		$this->data['title'] = "Logout";

		// log the user out
		$logout = $this->ion_auth->logout();

		// redirect them to the login page
		$this->session->set_flashdata('message', $this->ion_auth->messages());
		redirect('auth/login', 'refresh');
	}

	// change password
	function change_password()
	{
		$this->form_validation->set_rules('old', $this->lang->line('change_password_validation_old_password_label'), 'required');
		$this->form_validation->set_rules('new', $this->lang->line('change_password_validation_new_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[new_confirm]');
		$this->form_validation->set_rules('new_confirm', $this->lang->line('change_password_validation_new_password_confirm_label'), 'required');

		if (!$this->ion_auth->logged_in())
		{
			redirect('auth/login', 'refresh');
		}

		$user = $this->ion_auth->user()->row();

		if ($this->form_validation->run() == false)
		{
			// display the form
			// set the flash data error message if there is one
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');

			$this->data['min_password_length'] = $this->config->item('min_password_length', 'ion_auth');
			$this->data['old_password'] = array(
				'name' => 'old',
				'id'   => 'old',
				'type' => 'password',
			);
			$this->data['new_password'] = array(
				'name'    => 'new',
				'id'      => 'new',
				'type'    => 'password',
				'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
			);
			$this->data['new_password_confirm'] = array(
				'name'    => 'new_confirm',
				'id'      => 'new_confirm',
				'type'    => 'password',
				'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
			);
			$this->data['user_id'] = array(
				'name'  => 'user_id',
				'id'    => 'user_id',
				'type'  => 'hidden',
				'value' => $user->id,
			);

            ////////Config Halaman////////
            $data['judul_besar'] = 'Manage User';
            $data['judul_kecil'] = 'Change Password';
			$data['m_change_password'] = TRUE;
			$data['output'] = $this->data;
			$data['view_file'] = 'auth/change_password';
			$this->load->view('auth/main', $data, FALSE);
		}
		else
		{
			$identity = $this->session->userdata('identity');

			$change = $this->ion_auth->change_password($identity, $this->input->post('old'), $this->input->post('new'));

			if ($change)
			{
				//if the password was successfully changed
				$this->session->set_flashdata('message', $this->ion_auth->messages());
				$this->logout();
			}
			else
			{
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect('auth/change_password', 'refresh');
			}
		}
	}

	// forgot password
	function forgot_password()
	{
		// setting validation rules by checking wheather identity is username or email
		if($this->config->item('identity', 'ion_auth') != 'email' )
		{
		   $this->form_validation->set_rules('identity', $this->lang->line('forgot_password_identity_label'), 'required');
		}
		else
		{
		   $this->form_validation->set_rules('identity', $this->lang->line('forgot_password_validation_email_label'), 'required|valid_email');
		}


		if ($this->form_validation->run() == false)
		{
			$this->data['type'] = $this->config->item('identity','ion_auth');
			// setup the input
			$this->data['identity'] = array('name' => 'identity',
				'id' => 'identity',
			);

			if ( $this->config->item('identity', 'ion_auth') != 'email' ){
				$this->data['identity_label'] = $this->lang->line('forgot_password_identity_label');
			}
			else
			{
				$this->data['identity_label'] = $this->lang->line('forgot_password_email_identity_label');
			}

			// set any errors and display the form
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			$this->_render_page('auth/forgot_password', $this->data);
		}
		else
		{
			$identity_value = trim($this->input->post('identity'));
			$tables = $this->config->item('tables', 'ion_auth');
			$identity = $this->db
				->group_start()
					->where('username', $identity_value)
					->or_where('email', $identity_value)
					->or_where('npk', $identity_value)
				->group_end()
				->limit(1)
				->get($tables['users'])
				->row();

			if(empty($identity)) {

	            		if($this->config->item('identity', 'ion_auth') != 'email')
		            	{
		            		$this->ion_auth->set_error('forgot_password_identity_not_found');
		            	}
		            	else
		            	{
		            	   $this->ion_auth->set_error('forgot_password_email_not_found');
		            	}

		                $this->session->set_flashdata('message', $this->ion_auth->errors());
                		redirect("auth/forgot_password", 'refresh');
            		}

			if (empty($identity->email))
			{
				$this->ion_auth->set_error('forgot_password_email_not_found');
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect('auth/forgot_password', 'refresh');
			}

			// run the forgotten password method to email an activation code to the user
			$forgotten = $this->ion_auth->forgotten_password($identity->{$this->config->item('identity', 'ion_auth')});

			if ($forgotten)
			{
				// if there were no errors
				$this->session->set_flashdata('message', $this->ion_auth->messages());
				redirect("auth/login", 'refresh'); //we should display a confirmation page here instead of the login page
			}
			else
			{
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect("auth/forgot_password", 'refresh');
			}
		}
	}

	// reset password - final step for forgotten password
	public function reset_password($code = NULL)
	{
		if (!$code)
		{
			show_404();
		}

		$user = $this->ion_auth->forgotten_password_check($code);

		if ($user)
		{
			// if the code is valid then display the password reset form

			$this->form_validation->set_rules('new', $this->lang->line('reset_password_validation_new_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[new_confirm]');
			$this->form_validation->set_rules('new_confirm', $this->lang->line('reset_password_validation_new_password_confirm_label'), 'required');

			if ($this->form_validation->run() == false)
			{
				// display the form

				// set the flash data error message if there is one
				$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');

				$this->data['min_password_length'] = $this->config->item('min_password_length', 'ion_auth');
				$this->data['new_password'] = array(
					'name' => 'new',
					'id'   => 'new',
					'type' => 'password',
					'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
				);
				$this->data['new_password_confirm'] = array(
					'name'    => 'new_confirm',
					'id'      => 'new_confirm',
					'type'    => 'password',
					'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
				);
				$this->data['user_id'] = array(
					'name'  => 'user_id',
					'id'    => 'user_id',
					'type'  => 'hidden',
					'value' => $user->id,
				);
				$this->data['csrf'] = $this->_get_csrf_nonce();
				$this->data['code'] = $code;

				// render
				$this->_render_page('auth/reset_password', $this->data);
			}
			else
			{
				// do we have a valid request?
				if ($this->_valid_csrf_nonce() === FALSE || $user->id != $this->input->post('user_id'))
				{

					// something fishy might be up
					$this->ion_auth->clear_forgotten_password_code($code);

					show_error($this->lang->line('error_csrf'));

				}
				else
				{
					// finally change the password
					$identity = $user->{$this->config->item('identity', 'ion_auth')};

					$change = $this->ion_auth->reset_password($identity, $this->input->post('new'));

					if ($change)
					{
						// if the password was successfully changed
						$this->session->set_flashdata('message', $this->ion_auth->messages());
						redirect("auth/login", 'refresh');
					}
					else
					{
						$this->session->set_flashdata('message', $this->ion_auth->errors());
						redirect('auth/reset_password/' . $code, 'refresh');
					}
				}
			}
		}
		else
		{
			// if the code is invalid then send them back to the forgot password page
			$this->session->set_flashdata('message', $this->ion_auth->errors());
			redirect("auth/forgot_password", 'refresh');
		}
	}


	// activate the user
	function activate($id, $code=false)
	{
		if ($code !== false)
		{
			$activation = $this->ion_auth->activate($id, $code);
		}
		else
		{
			if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
			{
				show_error('You must be an administrator to activate a user.', 403);
			}

			$id = (int) $id;
			$user = $this->ion_auth->user($id)->row();
			if (!$user)
			{
				show_404();
			}

			if ($this->input->method(TRUE) === 'POST')
			{
				if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id'))
				{
					show_error($this->lang->line('error_csrf'));
				}

				if ($this->input->post('confirm') === 'yes')
				{
					$activation = $this->ion_auth->activate($id);
				}
				else
				{
					redirect('auth', 'refresh');
				}
			}
			else
			{
				$this->data['user'] = $user;
				$this->data['csrf'] = $this->_get_csrf_nonce();
				$data['judul_besar'] = 'Aktifkan User';
				$data['judul_kecil'] = 'Konfirmasi perubahan status';
				$data['m_auth_index'] = TRUE;
				$data['output'] = $this->data;
				$data['view_file'] = 'auth/activate_user';
				$this->load->view('auth/main', $data, FALSE);
				return;
			}
		}

		if ($activation)
		{
			// redirect them to the auth page
			$this->session->set_flashdata('message', $this->ion_auth->messages());
			redirect("auth", 'refresh');
		}
		else
		{
			// redirect them to the forgot password page
			$this->session->set_flashdata('message', $this->ion_auth->errors());
			redirect("auth/forgot_password", 'refresh');
		}
	}

	// deactivate the user
	function deactivate($id = NULL)
	{
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			// redirect them to the home page because they must be an administrator to view this
			return show_error('You must be an administrator to view this page.');
		}

		$id = (int) $id;

		$this->load->library('form_validation');
		$this->form_validation->set_rules('confirm', $this->lang->line('deactivate_validation_confirm_label'), 'required');
		$this->form_validation->set_rules('id', $this->lang->line('deactivate_validation_user_id_label'), 'required|alpha_numeric');

		if ($this->form_validation->run() == FALSE)
		{
			// insert csrf check
			$this->data['csrf'] = $this->_get_csrf_nonce();
			$this->data['user'] = $this->ion_auth->user($id)->row();

			$this->_render_page('auth/deactivate_user', $this->data);
		}
		else
		{
			// do we really want to deactivate?
			if ($this->input->post('confirm') == 'yes')
			{
				// do we have a valid request?
				if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id'))
				{
					show_error($this->lang->line('error_csrf'));
				}

				// do we have the right userlevel?
				if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin())
				{
					$this->ion_auth->deactivate($id);
				}
			}

			// redirect them back to the auth page
			redirect('auth', 'refresh');
		}
	}

	// create a new user
	function create_user()
    {
        $this->data['title'] = "Create User";

        if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
        {
            redirect('auth', 'refresh');
        }

        $tables = $this->config->item('tables','ion_auth');
        $identity_column = $this->config->item('identity','ion_auth');
        $this->data['identity_column'] = $identity_column;

        // validate form input
        $this->form_validation->set_rules('first_name', $this->lang->line('create_user_validation_fname_label'), 'required');
        $this->form_validation->set_rules('last_name', $this->lang->line('create_user_validation_lname_label'), 'required');
        $this->form_validation->set_rules('npk', 'NPK', 'trim|callback_npk_not_taken[0]');
        $this->form_validation->set_rules('telegram_pin', 'PIN Telegram', 'trim|numeric|exact_length[6]');
        if($identity_column!=='email')
        {
            $this->form_validation->set_rules('identity',$this->lang->line('create_user_validation_identity_label'),'required|trim|is_unique['.$tables['users'].'.'.$identity_column.']');
            $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'trim|valid_email|callback_email_not_taken');
        }
        else
        {
            $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'required|valid_email|is_unique[' . $tables['users'] . '.email]');
        }
        $this->form_validation->set_rules('phone', $this->lang->line('create_user_validation_phone_label'), 'trim');
        $this->form_validation->set_rules('company', $this->lang->line('create_user_validation_company_label'), 'trim');
        $this->form_validation->set_rules('password', $this->lang->line('create_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
        $this->form_validation->set_rules('password_confirm', $this->lang->line('create_user_validation_password_confirm_label'), 'required');

        if ($this->form_validation->run() == true)
        {
            $email    = strtolower(trim($this->input->post('email')));
            $email    = $email === '' ? NULL : $email;
            $identity = ($identity_column==='email') ? $email : trim($this->input->post('identity'));
            $password = $this->input->post('password');

            $additional_data = array(
                'first_name' => $this->input->post('first_name'),
                'last_name'  => $this->input->post('last_name'),
                'company'    => $this->input->post('company'),
                'phone'      => $this->input->post('phone'),
                'npk'        => trim($this->input->post('npk')) !== '' ? trim($this->input->post('npk')) : NULL,
            );
            if ($this->db->field_exists('telegram_pin', $tables['users']))
            {
                $pin = trim($this->input->post('telegram_pin'));
                $additional_data['telegram_pin'] = $pin !== '' ? $pin : self::DEFAULT_TELEGRAM_PIN;
            }
        }
        if ($this->form_validation->run() == true && $this->ion_auth->register($identity, $password, $email, $additional_data))
        {
            // check to see if we are creating the user
            // redirect them back to the admin page
            $this->session->set_flashdata('message', $this->ion_auth->messages());
            redirect("auth", 'refresh');
        }
        else
        {
            // display the create user form
            // set the flash data error message if there is one
            $this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));

            $this->data['first_name'] = array(
                'name'  => 'first_name',
                'id'    => 'first_name',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('first_name'),
            );
            $this->data['last_name'] = array(
                'name'  => 'last_name',
                'id'    => 'last_name',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('last_name'),
            );
            $this->data['npk'] = array(
                'name'  => 'npk',
                'id'    => 'npk',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('npk'),
            );
            $this->data['telegram_pin'] = array(
                'name'  => 'telegram_pin',
                'id'    => 'telegram_pin',
                'type'  => 'text',
                'maxlength' => 6,
                'value' => $this->form_validation->set_value('telegram_pin', self::DEFAULT_TELEGRAM_PIN),
            );
            $this->data['identity'] = array(
                'name'  => 'identity',
                'id'    => 'identity',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('identity'),
            );
            $this->data['email'] = array(
                'name'  => 'email',
                'id'    => 'email',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('email'),
            );
            $this->data['company'] = array(
                'name'  => 'company',
                'id'    => 'company',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('company'),
            );
            $this->data['phone'] = array(
                'name'  => 'phone',
                'id'    => 'phone',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('phone'),
            );
            $this->data['password'] = array(
                'name'  => 'password',
                'id'    => 'password',
                'type'  => 'password',
                'value' => $this->form_validation->set_value('password'),
            );
            $this->data['password_confirm'] = array(
                'name'  => 'password_confirm',
                'id'    => 'password_confirm',
                'type'  => 'password',
                'value' => $this->form_validation->set_value('password_confirm'),
            );

            ////////Config Halaman////////
            $data['judul_besar'] = 'Manage User';
            $data['judul_kecil'] = 'Add User';
			$data['output'] = $this->data;
			$data['view_file'] = 'auth/create_user';
			$this->load->view('auth/main', $data, FALSE);
        }
    }

	// edit a user
	function edit_user($id)
	{
		$this->data['title'] = "Edit User";

		if (!$this->ion_auth->logged_in() || (!$this->ion_auth->is_admin() && !($this->ion_auth->user()->row()->id == $id)))
		{
			redirect('auth', 'refresh');
		}

		$user = $this->ion_auth->user($id)->row();
		$groups=$this->ion_auth->groups()->result_array();
		$currentGroups = $this->ion_auth->get_users_groups($id)->result();

		// validate form input
		$this->form_validation->set_rules('first_name', $this->lang->line('edit_user_validation_fname_label'), 'required');
		$this->form_validation->set_rules('last_name', $this->lang->line('edit_user_validation_lname_label'), 'required');
		$this->form_validation->set_rules('npk', 'NPK', 'trim|callback_npk_not_taken[' . (int) $id . ']');
		if ($this->ion_auth->is_admin())
		{
			$this->form_validation->set_rules('telegram_pin', 'PIN Telegram', 'trim|numeric|exact_length[6]');
		}
		$this->form_validation->set_rules('phone', $this->lang->line('edit_user_validation_phone_label'), 'required');
		$this->form_validation->set_rules('company', $this->lang->line('edit_user_validation_company_label'), 'required');

		if (isset($_POST) && !empty($_POST))
		{
			// do we have a valid request?
			if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id'))
			{
				show_error($this->lang->line('error_csrf'));
			}

			// update the password if it was posted
			if ($this->input->post('password'))
			{
				$this->form_validation->set_rules('old_password', $this->lang->line('change_password_validation_old_password_label'), 'required|callback_valid_password');
				$this->form_validation->set_rules('password', $this->lang->line('edit_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
				$this->form_validation->set_rules('password_confirm', $this->lang->line('edit_user_validation_password_confirm_label'), 'required');
			}

			if ($this->form_validation->run() === TRUE)
			{
				$data = array(
					'first_name' => $this->input->post('first_name'),
					'last_name'  => $this->input->post('last_name'),
					'company'    => $this->input->post('company'),
					'phone'      => $this->input->post('phone'),
				);
				$tables = $this->config->item('tables', 'ion_auth');
				if ($this->db->field_exists('npk', $tables['users']))
				{
					$data['npk'] = trim($this->input->post('npk')) !== '' ? trim($this->input->post('npk')) : NULL;
				}

				if ($this->ion_auth->is_admin() && $this->db->field_exists('telegram_pin', $tables['users']))
				{
					$pin = trim($this->input->post('telegram_pin'));
					$data['telegram_pin'] = $pin !== '' ? $pin : self::DEFAULT_TELEGRAM_PIN;
				}

				// update the password if it was posted
				if ($this->input->post('password'))
				{
					$data['password'] = $this->input->post('password');
				}



				// Only allow updating groups if user is admin
				if ($this->ion_auth->is_admin())
				{
					//Update the groups user belongs to
					$groupData = $this->input->post('groups');

					if (isset($groupData) && !empty($groupData)) {

						$this->ion_auth->remove_from_group('', $id);

						foreach ($groupData as $grp) {
							$this->ion_auth->add_to_group($grp, $id);
						}

					}
				}

			// check to see if we are updating the user
			   if($this->ion_auth->update($user->id, $data))
			    {
			    	// redirect them back to the admin page if admin, or to the base url if non admin
				    $this->session->set_flashdata('message', $this->ion_auth->messages() );
				    if ($this->ion_auth->is_admin())
					{
						redirect('auth', 'refresh');
					}
					else
					{
						redirect('/', 'refresh');
					}

			    }
			    else
			    {
			    	// redirect them back to the admin page if admin, or to the base url if non admin
				    $this->session->set_flashdata('message', $this->ion_auth->errors() );
				    if ($this->ion_auth->is_admin())
					{
						redirect('auth', 'refresh');
					}
					else
					{
						redirect('/', 'refresh');
					}

			    }

			}
		}

		// display the edit user form
		$this->data['csrf'] = $this->_get_csrf_nonce();

		// set the flash data error message if there is one
		$this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));

		// pass the user to the view
		$this->data['user'] = $user;
		$this->data['groups'] = $groups;
		$this->data['currentGroups'] = $currentGroups;

		$this->data['first_name'] = array(
			'name'  => 'first_name',
			'id'    => 'first_name',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('first_name', $user->first_name),
		);
		$this->data['last_name'] = array(
			'name'  => 'last_name',
			'id'    => 'last_name',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('last_name', $user->last_name),
		);
		$this->data['npk'] = array(
			'name'  => 'npk',
			'id'    => 'npk',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('npk', isset($user->npk) ? $user->npk : ''),
		);
		$this->data['telegram_pin'] = array(
			'name'  => 'telegram_pin',
			'id'    => 'telegram_pin',
			'type'  => 'text',
			'maxlength' => 6,
			'value' => $this->form_validation->set_value('telegram_pin', !empty($user->telegram_pin) ? $user->telegram_pin : self::DEFAULT_TELEGRAM_PIN),
		);
		$this->data['company'] = array(
			'name'  => 'company',
			'id'    => 'company',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('company', $user->company),
		);
		$this->data['phone'] = array(
			'name'  => 'phone',
			'id'    => 'phone',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('phone', $user->phone),
		);
		$this->data['old_password'] = array(
			'name' => 'old_password',
			'id'   => 'old_password',
			'type' => 'password'
		);
		$this->data['password'] = array(
			'name' => 'password',
			'id'   => 'password',
			'type' => 'password'
		);
		$this->data['password_confirm'] = array(
			'name' => 'password_confirm',
			'id'   => 'password_confirm',
			'type' => 'password'
		);

		 ////////Config Halaman////////
		$data['judul_besar'] = 'Edit User';
		$data['judul_kecil'] = 'List';
		$data['m_auth_index'] = TRUE;
		$data['output'] = $this->data;
		$data['view_file'] = 'auth/edit_user';
		$this->load->view('auth/main', $data, FALSE);
	}

	// create a new group
	function create_group()
	{
		$this->data['title'] = $this->lang->line('create_group_title');

		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			redirect('auth', 'refresh');
		}

		if ($this->input->method(TRUE) === 'POST' && $this->_valid_csrf_nonce() === FALSE)
		{
			show_error($this->lang->line('error_csrf'));
		}

		// validate form input
		$this->form_validation->set_rules('group_name', $this->lang->line('create_group_validation_name_label'), 'required|alpha_dash');

		if ($this->form_validation->run() == TRUE)
		{
			$new_group_id = $this->ion_auth->create_group($this->input->post('group_name'), $this->input->post('description'));
			if($new_group_id)
			{
				$this->session->set_flashdata('message', $this->ion_auth->messages());
				redirect('auth/groups', 'refresh');
			}
		}

		$this->data['message'] = validation_errors()
			? validation_errors()
			: ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message'));
		$this->data['group_name'] = array(
			'name'  => 'group_name',
			'id'    => 'group_name',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('group_name'),
		);
		$this->data['description'] = array(
			'name'  => 'description',
			'id'    => 'description',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('description'),
		);
		$this->data['csrf'] = $this->_get_csrf_nonce();

		$data['judul_besar'] = 'Manage Groups';
		$data['judul_kecil'] = 'Tambah Group';
		$data['m_auth_index'] = TRUE;
		$data['output'] = $this->data;
		$data['view_file'] = 'auth/create_group';
		$this->load->view('auth/main', $data, FALSE);
	}

	// edit a group
	function edit_group($id)
	{
		// bail if no group id given
		if(!$id || empty($id))
		{
			redirect('auth', 'refresh');
		}

		$this->data['title'] = $this->lang->line('edit_group_title');

		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			redirect('auth', 'refresh');
		}

		$group = $this->ion_auth->group($id)->row();
		if (!$group)
		{
			show_404();
		}

		// validate form input
		$this->form_validation->set_rules('group_name', $this->lang->line('edit_group_validation_name_label'), 'required|alpha_dash');

		if (isset($_POST) && !empty($_POST))
		{
			if ($this->_valid_csrf_nonce() === FALSE)
			{
				show_error($this->lang->line('error_csrf'));
			}

			if ($this->form_validation->run() === TRUE)
			{
				$group_update = $this->ion_auth->update_group($id, $this->input->post('group_name'), $this->input->post('group_description'));

				if($group_update)
				{
					if ($this->page_access->save_group_pages($id, $this->input->post('pages')))
					{
						$this->session->set_flashdata('message', $this->lang->line('edit_group_saved'));
					}
					else
					{
						$this->session->set_flashdata('message', 'Akses halaman gagal disimpan.');
					}
				}
				else
				{
					$this->session->set_flashdata('message', $this->ion_auth->errors());
				}
				redirect('auth/groups', 'refresh');
			}
		}

		// set the flash data error message if there is one
		$this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));

		// pass the user to the view
		$this->data['group'] = $group;
		$this->data['pages'] = $this->page_access->pages();
		$this->data['group_pages'] = $this->page_access->group_pages($id);
		$this->data['csrf'] = $this->_get_csrf_nonce();

		$readonly = $this->config->item('admin_group', 'ion_auth') === $group->name ? 'readonly' : '';

		$this->data['group_name'] = array(
			'name'    => 'group_name',
			'id'      => 'group_name',
			'type'    => 'text',
			'value'   => $this->form_validation->set_value('group_name', $group->name),
			$readonly => $readonly,
		);
		$this->data['group_description'] = array(
			'name'  => 'group_description',
			'id'    => 'group_description',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('group_description', $group->description),
		);

		 ////////Config Halaman////////
		$data['judul_besar'] = 'Edit Group';
		$data['judul_kecil'] = 'List';
		$data['m_auth_index'] = TRUE;
		$data['output'] = $this->data;
		$data['view_file'] = 'auth/edit_group';
		$this->load->view('auth/main', $data, FALSE);
	}

	function delete_group($id = NULL)
	{
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			show_error('You must be an administrator to delete a group.', 403);
		}
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_error('Method not allowed.', 405);
		}

		$id = (int) $id;
		$group = $this->ion_auth->group($id)->row();
		if (!$group)
		{
			show_404();
		}
		if ($this->_valid_csrf_nonce() === FALSE || $id !== (int) $this->input->post('id'))
		{
			show_error($this->lang->line('error_csrf'));
		}

		if ($this->ion_auth->delete_group($id))
		{
			$this->session->set_flashdata('message', 'Group berhasil dihapus. Anggota group tersebut tidak lagi memiliki akses melalui group ini.');
		}
		else
		{
			$this->session->set_flashdata('message', $this->ion_auth->errors());
		}
		redirect('auth/groups', 'refresh');
	}


	function npk_not_taken($npk, $user_id)
	{
		$npk = trim($npk);
		if ($npk === '')
		{
			return TRUE;
		}

		$tables = $this->config->item('tables', 'ion_auth');
		if (!$this->db->field_exists('npk', $tables['users']))
		{
			$this->form_validation->set_message('npk_not_taken', 'Jalankan application/sql/auth_npk_group_pages.sql untuk mengaktifkan NPK.');
			return FALSE;
		}

		$this->db->where('npk', $npk);
		$this->db->where('id !=', (int) $user_id);
		if ($this->db->count_all_results($tables['users']) > 0)
		{
			$this->form_validation->set_message('npk_not_taken', 'NPK sudah digunakan oleh user lain.');
			return FALSE;
		}

		return TRUE;
	}

	function email_not_taken($email)
	{
		$email = strtolower(trim($email));
		if ($email === '')
		{
			return TRUE;
		}

		$tables = $this->config->item('tables', 'ion_auth');
		$this->db->where('email', $email);
		if ($this->db->count_all_results($tables['users']) > 0)
		{
			$this->form_validation->set_message('email_not_taken', 'Email sudah digunakan oleh user lain.');
			return FALSE;
		}

		return TRUE;
	}

	function _get_csrf_nonce()
	{
		$this->load->helper('string');
		$key   = random_string('alnum', 8);
		$value = random_string('alnum', 20);
		$this->session->set_flashdata('csrfkey', $key);
		$this->session->set_flashdata('csrfvalue', $value);

		return array($key => $value);
	}

	function _valid_csrf_nonce()
	{
		if ($this->input->post($this->session->flashdata('csrfkey')) !== FALSE &&
			$this->input->post($this->session->flashdata('csrfkey')) == $this->session->flashdata('csrfvalue'))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	private function _sync_karyawan_token()
	{
		$token = $this->session->userdata('sync_karyawan_token');
		if (!is_string($token) || $token === '')
		{
			$this->load->helper('string');
			$token = random_string('alnum', 32);
			$this->session->set_userdata('sync_karyawan_token', $token);
		}
		return $token;
	}

	private function _ensure_default_telegram_pins()
	{
		$tables = $this->config->item('tables', 'ion_auth');
		$users_table = $tables['users'];
		if (!$this->db->field_exists('telegram_pin', $users_table))
		{
			return;
		}

		$updated = $this->db
			->where('telegram_pin IS NULL', NULL, FALSE)
			->or_where('TRIM(telegram_pin) =', '')
			->update($users_table, array('telegram_pin' => self::DEFAULT_TELEGRAM_PIN));
		if ($updated === FALSE)
		{
			$error = $this->db->error();
			log_message('error', 'Gagal mengisi PIN Telegram default: '.$error['code'].' '.$error['message']);
			show_error('PIN Telegram default gagal diperbarui.', 500);
		}
	}

		function _render_page($view, $data=null, $returnhtml=false)//I think this makes more sense
		{
			$this->viewdata = (empty($data)) ? $this->data: $data;
			$view_html = $this->load->view($view, $this->viewdata, $returnhtml);
			if ($returnhtml) return $view_html;//This will return html on 3rd argument being true
		}

		// callback untuk validasi password lama
		function valid_password($password = '')
		{
			if (empty($password)) {
				$this->form_validation->set_message('valid_password', 'Password lama harus diisi jika akan mengubah password');
				return FALSE;
			}
			$user = $this->ion_auth->user()->row();
			$identity = $user->{$this->config->item('identity', 'ion_auth')};
			// check against the password in the db
			$result = $this->ion_auth->login($identity, $password, FALSE, FALSE);
			if (!$result)
			{
				$this->form_validation->set_message('valid_password', 'Password lama tidak sesuai');
				return FALSE;
			}
			return TRUE;
		}
	}
