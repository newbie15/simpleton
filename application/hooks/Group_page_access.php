<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Group_page_access
{
	public function check()
	{
		$CI =& get_instance();
		$CI->load->library('page_access');
		$requested_uri = trim($CI->uri->uri_string(), '/');
		if ($CI->ion_auth->logged_in() &&
			$CI->ion_auth->in_group('members') &&
			!$CI->ion_auth->is_admin() &&
			(strncmp($requested_uri, 'main/', 5) === 0 || $requested_uri === 'main') &&
			strcasecmp($requested_uri, 'main/member') !== 0 &&
			strncasecmp($requested_uri, 'main/member/', 12) !== 0 &&
			strcasecmp($requested_uri, 'main/welcome') !== 0)
		{
			show_error('Anggota hanya dapat mengakses portal anggota.', 403);
		}

		$pages = $CI->page_access->pages();
		uasort($pages, function ($left, $right) {
			return strlen($right['uri']) - strlen($left['uri']);
		});

		foreach ($pages as $page_key => $page)
		{
			$page_uris = array_merge(array($page['uri']), isset($page['aliases']) ? $page['aliases'] : array());
			$matches_page = FALSE;
			foreach ($page_uris as $configured_uri) {
				$page_uri = trim($configured_uri, '/');
				if (strcasecmp($requested_uri, $page_uri) === 0 ||
					strncasecmp($requested_uri, $page_uri . '/', strlen($page_uri) + 1) === 0) {
					$matches_page = TRUE;
					break;
				}
			}
			if (!$matches_page) {
				continue;
			}

			if (!$CI->ion_auth->logged_in())
			{
				redirect('auth/login', 'refresh');
			}

			if (!$CI->page_access->can_access($page_key))
			{
				show_error('Anda tidak memiliki akses ke halaman ini.', 403);
			}

			return;
		}
	}
}
