<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Page_access
{
	private $CI;
	private $pages;
	private $access_cache = array();

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('ion_auth');
		$this->CI->config->load('page_access', TRUE);
		$this->pages = $this->CI->config->item('pages', 'page_access');
	}

	public function pages()
	{
		return $this->pages;
	}

	public function can_access($page_key)
	{
		if (array_key_exists($page_key, $this->access_cache))
		{
			return $this->access_cache[$page_key];
		}

		if (!isset($this->pages[$page_key]) || !$this->CI->ion_auth->logged_in())
		{
			$this->access_cache[$page_key] = FALSE;
			return FALSE;
		}

		if ($this->CI->ion_auth->is_admin())
		{
			$this->access_cache[$page_key] = TRUE;
			return TRUE;
		}
		$is_member = $this->CI->ion_auth->in_group('members');
		if (($page_key === 'member_portal' && !$is_member) ||
			($is_member && $page_key !== 'member_portal'))
		{
			$this->access_cache[$page_key] = FALSE;
			return FALSE;
		}
		if ($page_key === 'users')
		{
			$this->access_cache[$page_key] = FALSE;
			return FALSE;
		}

		$groups = $this->CI->ion_auth->get_users_groups()->result();
		$group_ids = array();
		foreach ($groups as $group)
		{
			$group_ids[] = (int) $group->id;
		}

		if (empty($group_ids))
		{
			$this->access_cache[$page_key] = FALSE;
			return FALSE;
		}
		$this->require_group_pages_table();

		$this->access_cache[$page_key] = $this->CI->db
			->where('page_key', $page_key)
			->where_in('group_id', $group_ids)
			->count_all_results('group_pages') > 0;
		return $this->access_cache[$page_key];
	}

	public function group_pages($group_id)
	{
		$this->require_group_pages_table();
		$rows = $this->CI->db
			->select('page_key')
			->where('group_id', (int) $group_id)
			->get('group_pages')
			->result();

		$selected = array();
		foreach ($rows as $row)
		{
			$selected[] = $row->page_key;
		}

		return $selected;
	}

	public function save_group_pages($group_id, $page_keys)
	{
		$this->require_group_pages_table();
		$valid_keys = array_intersect(array_keys($this->pages), (array) $page_keys);
		$group_id = (int) $group_id;

		$this->CI->db->trans_start();
		$this->CI->db->where('group_id', $group_id)->delete('group_pages');

		foreach ($valid_keys as $page_key)
		{
			$this->CI->db->insert('group_pages', array(
				'group_id' => $group_id,
				'page_key' => $page_key,
			));
		}

		$this->CI->db->trans_complete();
		return $this->CI->db->trans_status();
	}

	private function require_group_pages_table()
	{
		if (!$this->CI->db->table_exists('group_pages'))
		{
			show_error('Skema akses grup belum tersedia. Jalankan application/sql/auth_npk_group_pages.sql.', 500);
		}
	}
}
