<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');



























































































function validateSession()
{
	global $settings, $sc;


	is_not_guest();


	$refreshTime = AJAX ? 4200 : 3600;


	if (!empty($settings['securityDisable']) || (!empty($_SESSION['admin_time']) && $_SESSION['admin_time'] + $refreshTime >= time()))
		return;

	loadSource('Subs-Auth');


	if (isset($_POST['admin_hash_pass']) && strlen($_POST['admin_hash_pass']) == 40)
	{
		checkSession();

		$good_password = in_array(true, call_hook('verify_password', array(we::$user['username'], $_POST['admin_hash_pass'], true)), true);

		if ($good_password || $_POST['admin_hash_pass'] == sha1(we::$user['passwd'] . $sc))
		{
			$_SESSION['admin_time'] = time();
			unset($_SESSION['request_referer']);
			return;
		}
	}

	if (isset($_POST['admin_pass']))
	{
		checkSession();

		$good_password = in_array(true, call_hook('verify_password', array(we::$user['username'], $_POST['admin_pass'], false)), true);


		if ($good_password || sha1(strtolower(we::$user['username']) . $_POST['admin_pass']) == we::$user['passwd'])
		{
			$_SESSION['admin_time'] = time();
			unset($_SESSION['request_referer']);
			return;
		}
	}


	if (empty($_SESSION['request_referer']))
		$_SESSION['request_referer'] = isset($_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : array();
	elseif (empty($_POST))
		unset($_SESSION['request_referer']);


	adminLogin();
}


function is_not_guest($message = '')
{
	global $txt, $context, $settings;


	if (we::$is_member)
		return;



	if (!empty($settings['who_enabled']))
		$_GET['who_warn'] = 1;
	writeLog(true);


	if (AJAX)
		obExit(false);


	if (WEDGE != 'SSI' && empty($context['theme_loaded']))
		loadTheme();


	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];


	loadLanguage('Login');


	if (!wetem::has_layer('default'))
	{
		$_SESSION['login_url'] = SCRIPT . '?' . $_SERVER['QUERY_STRING'];
		redirectexit('action=login');
	}
	else
	{
		loadTemplate('Login');
		wetem::load('kick_guest');
		$context['robot_no_index'] = true;
	}


	$context['kick_message'] = $message;
	$context['page_title'] = $txt['login'];

	obExit();


	trigger_error('Hacking attempt...', E_USER_ERROR);
}


function is_not_banned($forceCheck = false)
{
	global $txt, $settings, $cookiename, $user_settings;


	if (we::$is_admin)
		return;


	if ($forceCheck || !isset($_SESSION['ban']) || empty($settings['banLastUpdated']) || ($_SESSION['ban']['last_checked'] < $settings['banLastUpdated']) || $_SESSION['ban']['id_member'] != we::$id || $_SESSION['ban']['ip'] != we::$user['ip'] || $_SESSION['ban']['ip2'] != we::$user['ip2'] || (isset(we::$user['email'], $_SESSION['ban']['email']) && $_SESSION['ban']['email'] != we::$user['email']))
	{

		$_SESSION['ban'] = array(
			'last_checked' => time(),
			'id_member' => we::$id,
			'ip' => we::$user['ip'],
			'ip2' => we::$user['ip2'],
			'email' => we::$user['email'],
		);

		$flag_is_activated = false;

		$ban_list = array();

		if (we::$id)
		{
			$member_check = check_banned_member(we::$id);
			if (!empty($member_check))
				$ban_list = array_merge($ban_list, $member_check);
		}


		if (strlen(we::$user['email']) != 0)
		{
			$email_check = isBannedEmail(we::$user['email'], '', true);
			if (!empty($email_check))
				$ban_list = array_merge($ban_list, $email_check);
		}


		foreach (array('ip', 'ip2') as $ip_number)
		{
			$bans = check_banned_ip(we::$user[$ip_number]);
			if (is_array($bans))
				$ban_list = array_merge($ban_list, $bans);
		}

		foreach ($ban_list as $ban)
			if ($ban['hard'])
			{
				$_SESSION['ban']['cannot_access']['ids'][] = $ban['id'];
				if (!empty($ban['msg']))
					$_SESSION['ban']['cannot_access']['reason'] = $ban['msg'];
				$flag_is_activated = 'hard';
			}
			elseif ($flag_is_activated == false)
				$flag_is_activated = 'soft';

		if (!empty($_SESSION['ban']['cannot_access']['ids']))
		{
			$_SESSION['ban']['cannot_access']['ids'] = array_unique($_SESSION['ban']['cannot_access']['ids']);
			if (!isset($_SESSION['ban']['cannot_access']['reason']))
				$_SESSION['ban']['cannot_access']['reason'] = '';
		}



		if (we::$id)
		{
			$update = 0;
			if ($user_settings['is_activated'] >= 20)
			{
				if ($flag_is_activated != 'hard')
					$update = $flag_is_activated == 'soft' ? -10 : -20;
			}
			elseif ($user_settings['is_activated'] >= 10)
			{
				if ($flag_is_activated == 'hard')
					$update = 10;
				elseif (!$flag_is_activated)
					$update = -10;
			}
			elseif ($flag_is_activated)
			{

				$update = $flag_is_activated == 'soft' ? 10 : 20;
			}
			if (!empty($update))
			{
				updateMemberData(we::$id, array('is_activated' => $user_settings['is_activated'] + $update));
				updateStats('member');
			}
		}


		if ($flag_is_activated == 'soft')
			$_SESSION['ban']['soft'] = true;
	}


	if (!isset($_SESSION['ban']['cannot_access']) && !empty($_COOKIE[$cookiename . '_']))
	{
		$bans = explode(',', $_COOKIE[$cookiename . '_']);
		foreach ($bans as $key => $value)
			$bans[$key] = (int) $value;
		$request = wesql::query('
			SELECT id_ban, extra
			FROM {db_prefix}bans
			WHERE id_ban IN ({array_int:bans})',
			array(
				'bans' => $bans,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
			$_SESSION['ban']['cannot_access']['reason'] = !empty($extra['message']) ? $extra['message'] : '';
		}
		wesql::free_result($request);


		if (!isset($_SESSION['ban']['cannot_access']))
		{
			loadSource('Subs-Auth');
			$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));
			setcookie($cookiename . '_', '', time() - 3600, $cookie_url[1], $cookie_url[0], 0, true);
		}
	}


	if (empty($_SESSION['ban']['cannot_access']))
	{

		if (!empty(we::$user['sanctions']['hard_ban']))
		{
			$_SESSION['ban']['cannot_access'] = array(
				'ids' => array('sanctions'),
			);
			if (we::$user['sanctions']['hard_ban'] != 1)
				$_SESSION['ban']['cannot_access']['expire_time'] = we::$user['sanctions']['hard_ban'];
		}

		elseif (empty($_SESSION['ban']['soft']) && !empty(we::$user['sanctions']['hard_ban']))
			$_SESSION['ban']['soft'] = true;
	}


	if (isset($_SESSION['ban']['cannot_access']))
	{

		if (we::$is_member)
			wesql::query('DELETE FROM {db_prefix}log_online WHERE id_member = {int:current_member}', array('current_member' => we::$id));


		$old_name = isset(we::$user['name']) && we::$user['name'] != '' ? we::$user['name'] : $txt['guest_title'];
		we::$user['name'] = '';
		we::$user['username'] = '';
		we::$is_guest = true;
		we::$is_admin = false;
		we::$cache = array();
		we::$id = 0;
		we::$user['permissions'] = array();


		loadSource('Subs-Auth');
		$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));
		setcookie($cookiename . '_', implode(',', $_SESSION['ban']['cannot_access']['ids']), time() + 3153600, $cookie_url[1], $cookie_url[0], 0, true);


		$_GET['action'] = $_GET['board'] = $_GET['topic'] = '';
		writeLog(true);


		fatal_lang_error('your_ban', false, array($old_name, (empty($_SESSION['ban']['cannot_access']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_access']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never'])));


		trigger_error('Hacking attempt...', E_USER_ERROR);
	}


	if (isset(we::$user['permissions']))
		banPermissions();


	if (!empty($_SESSION['ban']['soft']))
	{
		if (!empty($settings['softban_blankpage']) && mt_rand(0, 100) < (int) $settings['softban_blankpage'])
			die;

		if (!empty($settings['softban_redirect']) && !empty($settings['softban_redirect_url']) && mt_rand(0, 100) < (int) $settings['softban_redirect'])
			redirectexit($settings['softban_redirect_url']);

		if (!empty($settings['softban_delay_max']))
			usleep(mt_rand(!empty($settings['softban_delay_min']) ? $settings['softban_delay_min'] * 1000000 : 0, $settings['softban_delay_max'] * 1000000));

		if (!empty($settings['softban_disableregistration']))
			$settings['registration_method'] = 3;
	}
}


function banPermissions()
{
	global $settings, $context;

	$denied_permissions = array();


	if (isset($_SESSION['ban']['cannot_access']))
		we::$user['permissions'] = array();

	elseif (!empty(we::$user['sanctions']))
	{
		if (!empty(we::$user['sanctions']['pm_ban']))
		{
			$denied_permissions[] = 'pm_send';
			we::$user['pm_banned'] = true;
		}
		if (!empty(we::$user['sanctions']['post_ban']))
		{
			$denied_permissions = array_merge($denied_permissions, array(
				'post_new', 'post_reply_own', 'post_reply_any',
				'post_thought',
				'poll_post',
				'poll_add_own', 'poll_add_any',
				'poll_edit_own', 'poll_edit_any',
				'poll_lock_own', 'poll_lock_any',
				'poll_remove_own', 'poll_remove_any',
			));
			we::$user['post_banned'] = true;
		}


		if (!empty($denied_permissions))
			$denied_permissions = array_merge($denied_permissions, array(
				'manage_attachments', 'manage_smileys', 'manage_boards', 'admin_forum', 'manage_permissions',
				'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news',
				'profile_identity_any', 'profile_extra_any', 'profile_title_any',
				'delete_own', 'delete_any', 'delete_replies',
				'pin_topic',
				'merge_any', 'split_any',
				'modify_own', 'modify_any', 'modify_replies',
				'bypass_edit_disable',
				'move_any',
				'send_topic',
				'lock_own', 'lock_any',
				'remove_own', 'remove_any',
			));

		if (!empty(we::$user['sanctions']['moderate']))
			we::$user['post_moderated'] = true;
	}

	call_hook('banned_perms', array(&$denied_permissions));
	if (!empty($denied_permissions))
		we::$user['permissions'] = array_diff(we::$user['permissions'], $denied_permissions);



	if (isset($_SESSION['mc']) && $_SESSION['mc']['time'] > $settings['settings_updated'] && $_SESSION['mc']['id'] == MID)
		we::$user['mod_cache'] = $_SESSION['mc'];
	else
	{
		loadSource('Subs-Auth');
		rebuildModCache();
	}


	if (isset($_SESSION['rc']) && $_SESSION['rc']['time'] > $settings['last_mod_report_action'] && $_SESSION['rc']['id'] == MID)
	{
		$context['open_mod_reports'] = $_SESSION['rc']['reports'];
		$context['closed_mod_reports'] = $_SESSION['rc']['closed'];
	}
	elseif ($_SESSION['mc']['bq'] != '0=1')
	{
		loadSource('ModerationCenter');
		recountOpenReports();
	}
	else
		$context['open_mod_reports'] = 0;
}


function soft_ban($feature)
{
	global $settings;

	if (empty($_SESSION['ban']['soft']))
		return;

	if (!empty($settings['softban_no' . $feature]))
	{
		$chance = (int) $settings['softban_no' . $feature];
		if (mt_rand(0, 100) <= $chance)
			fatal_lang_error('loadavg_' . $feature . '_disabled', false);
	}
}

function check_banned_member($id_member)
{

	if (empty($id_member))
		return array();

	$return_value = array();
	$bans = cache_get_data('bans_id_member', 600);

	if ($bans === null)
	{
		$bans = array();
		$request = query_for_bans('id_member');

		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$ban = array(
				'id' => $row['id_ban'],
				'hard' => $row['hardness'] == 1,
				'message' => !empty($extra['message']) ? $extra['message'] : '',
			);

			if (!empty($row['ban_content']) && (int) $row['ban_content'] > 0)
			{
				$ban['member'] = (int) $row['ban_content'];
				$bans[] = $ban;
			}
		}

		cache_put_data('bans_id_member', $bans, 600);
		wesql::free_result($request);
	}


	foreach ($bans as $ban)
	{
		if ($id_member == $ban['member'])
			$return_value[] = array(
				'id' => $ban['id'],
				'msg' => $ban['message'],
				'hard' => $ban['hard'],
			);
	}

	return $return_value;
}

function check_banned_ip($ip)
{
	global $settings;
	static $ips = null, $hostnames = null;

	if ($ip == INVALID_IP)
		return false;

	$return_value = array();



	if ($ips === null)
		$ips = cache_get_data('bans_ip', 300);
	if ($ips === null)
	{
		$ips = array();
		$request = query_for_bans('ip_address');
		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$ban = array(
				'id' => $row['id_ban'],
				'hard' => $row['hardness'] == 1,
				'message' => !empty($extra['message']) ? $extra['message'] : '',
			);
			if (strpos($row['ban_content'], '-') !== false)
				$ban['range'] = explode('-', $row['ban_content']);
			else
				$ban['ip'] = $row['ban_content'];

			$ips[] = $ban;
		}
		cache_put_data('bans_ip', $ips, 300);
		wesql::free_result($request);
	}

	foreach ($ips as $ban)
	{
		if (isset($ban['ip']))
		{
			if ($ban['ip'] != $ip)
				continue;
		}
		elseif ($ip < $ban['range'][0] || $ip > $ban['range'][1])
			continue;

		$return_value[] = array(
			'id' => $ban['id'],
			'msg' => $ban['message'],
			'hard' => $ban['hard'],
		);
	}


	if (empty($settings['disableHostnameLookup']))
	{
		$this_hostname = strtolower(host_from_ip(format_ip($ip)));
		if (strlen($this_hostname) > 0)
		{

			if ($hostnames === null)
				$hostnames = cache_get_data('bans_hostname', 480);
			if ($hostnames === null)
			{
				$hostnames = array();
				$request = query_for_bans('hostname');
				while ($row = wesql::fetch_assoc($request))
				{
					$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
					$ban = array(
						'id' => $row['id_ban'],
						'hard' => $row['hardness'] == 1,
						'message' => !empty($extra['message']) ? $extra['message'] : '',
					);
					if (strpos($row['ban_content'], '*.') === 0)
						$ban['match'] = '~' . strtolower(preg_quote(substr($row['ban_content'], 2), '~')) . '$~';
					else
						$ban['content'] = strtolower($row['ban_content']);
					$hostnames[] = $ban;
				}
				cache_put_data('bans_hostname', $hostnames, 480);
				wesql::free_result($request);
			}

			foreach ($hostnames as $ban)
			{
				if (isset($ban['content']))
				{
					if ($ban['content'] != $this_hostname)
						continue;
				}
				elseif (!preg_match($ban['match'], $this_hostname))
					continue;

				$return_value[] = array(
					'id' => $ban['id'],
					'msg' => $ban['message'],
					'hard' => $ban['hard'],
				);
			}
		}
	}

	return $return_value;
}


function isBannedEmail($email, $error, $return = false)
{
	global $txt;


	if (empty($email) || trim($email) == '')
		return;

	$return_value = array();


	$bans = cache_get_data('bans_email', 240);
	if ($bans === null)
	{
		$bans = array();
		$request = query_for_bans('email');

		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$ban = array(
				'id' => $row['id_ban'],
				'hard' => $row['hardness'] == 1,
				'message' => !empty($extra['message']) ? $extra['message'] : '',
			);


			list ($user, $domain) = explode('@', strtolower($row['ban_content']));


			if (!empty($extra['gmail_style']))
			{
				$ban['gmail'] = true;
				if (strpos($user, '+') !== false)
					list ($user) = explode('+', $user);
				if ($domain == 'gmail.com' || $domain == 'googlemail.com')
					$user = str_replace('.', '', $user);
			}

			if ($user === '*')
				$ban['domain'] = $domain;
			elseif ($domain[0] === '*')
				$ban['tld'] = '~' . preg_quote(substr($domain, 1), '~') . '$~';
			elseif (strpos($user, '*') !== false)
			{
				list ($b, $a) = explode('*', $user);
				$ban['match'] = '~' . preg_quote($b, '~') . '.*' . preg_quote($a, '~') . '~';
			}
			else
				$ban['content'] = $user . '@' . $domain;
			$bans[] = $ban;
		}
		cache_put_data('bans_email', $bans, 240);
		wesql::free_result($request);
	}


	$email = strtolower($email);
	list ($user, $domain) = explode('@', $email);

	list ($gmail_user) = explode('+', $user);
	$gmail_user_strict = str_replace('.', '', $gmail_user) . '@' . $domain;
	$gmail_user .= '@' . $domain;


	foreach ($bans as $ban)
	{


		if (isset($ban['content']))
		{
			$content = !empty($ban['gmail']) ? ($domain == 'gmail.com' || $domain == 'googlemail.com' ? $gmail_user_strict : $gmail_user) : $email;
			if ($ban['content'] != $content)
				continue;
		}
		elseif (isset($ban['domain']))
		{
			if ($ban['domain'] != $domain)
				continue;
		}
		elseif (isset($ban['tld']))
		{
			if (!preg_match($ban['tld'], $domain))
				continue;
		}
		else
		{
			if (!preg_match($ban['match'], $email))
				continue;
		}

		if ($return)
			$return_value[] = array(
				'id' => $ban['id'],
				'msg' => $ban['message'],
				'hard' => $ban['hard'],
			);
		else
		{

			if ($ban['hard'])
			{
				$_SESSION['ban']['cannot_access']['reason'] = $ban['message'];
				$_SESSION['ban']['last_checked'] = time();
				$_SESSION['ban']['cannot_access']['ids'][] = $ban['id'];
				fatal_lang_error('your_ban', false, array($txt['guest_title'], $_SESSION['ban']['cannot_access']['reason']));
			}
			else
				fatal_error($error . $ban['message'], false);
		}
	}

	return $return_value;
}

function query_for_bans($type)
{
	return wesql::query('
		SELECT id_ban, hardness, ban_content, extra
		FROM {db_prefix}bans
		WHERE ban_type = {string:type}',
		array(
			'type' => $type,
		)
	);
}


function checkSession($type = 'post', $from_action = '', $is_fatal = true)
{
	global $sc, $settings;


	if ($type == 'post')
	{
		$check = isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : null;
		if ($check !== $sc)
			$error = 'session_timeout';
	}


	elseif ($type == 'get')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : null;
		if ($check !== $sc)
			$error = 'session_verify_fail';
	}


	elseif ($type == 'request')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : (isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : null);

		if ($check !== $sc)
			$error = 'session_verify_fail';
	}


	if ((!isset($_SESSION['USER_AGENT']) || $_SESSION['USER_AGENT'] != $_SERVER['HTTP_USER_AGENT']) && empty($settings['disableCheckUA']))
		$error = 'session_verify_fail';


	preventPrefetch();


	if (isset($_SESSION['request_referer']))
		$referrer = $_SESSION['request_referer'];
	else
		$referrer = isset($_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : array();

	if (!empty($referrer['host']))
	{
		if (strpos($_SERVER['HTTP_HOST'], ':') !== false)
			$real_host = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
		else
			$real_host = $_SERVER['HTTP_HOST'];

		$parsed_url = parse_url(ROOT);


		if (!empty($settings['globalCookies']))
		{
			if (preg_match('~(?:[^.]+\.)?([^.]{3,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
				$parsed_url['host'] = $parts[1];

			if (preg_match('~(?:[^.]+\.)?([^.]{3,}\..+)\z~i', $referrer['host'], $parts) == 1)
				$referrer['host'] = $parts[1];

			if (preg_match('~(?:[^.]+\.)?([^.]{3,}\..+)\z~i', $real_host, $parts) == 1)
				$real_host = $parts[1];
		}


		if (isset($parsed_url['host']) && strtolower($referrer['host']) != strtolower($parsed_url['host']) && strtolower($referrer['host']) != strtolower($real_host))
		{
			$error = 'verify_url_fail';
			$log_error = true;
		}
	}


	if (!empty($from_action) && (!isset($_SESSION['old_url']) || preg_match('~[?;&]action=' . $from_action . '([;&]|$)~', $_SESSION['old_url']) == 0))
	{
		$error = 'verify_url_fail';
		$log_error = true;
	}

	if (strtolower($_SERVER['HTTP_USER_AGENT']) == 'hacker')
		fatal_lang_error('no_access', false);


	if (!isset($error))
		return '';

	elseif ($is_fatal)
	{
		if (AJAX)
		{
			while (ob_get_length())
				ob_end_clean();
			header('HTTP/1.1 403 Forbidden - Session timeout');
			exit;
		}
		else
			fatal_lang_error($error, isset($log_error) ? 'user' : false);
	}

	else
		return $error;


	trigger_error('Hacking attempt...', E_USER_ERROR);
}


function checkSubmitOnce($action, $is_fatal = true)
{
	global $context;

	if (!isset($_SESSION['forms']))
		$_SESSION['forms'] = array();


	if ($action == 'register')
	{
		$context['form_sequence_number'] = 0;
		while (empty($context['form_sequence_number']) || in_array($context['form_sequence_number'], $_SESSION['forms']))
			$context['form_sequence_number'] = mt_rand(1, 16000000);
	}

	elseif ($action == 'check')
	{
		if (!isset($_REQUEST['seqnum']))
			return true;
		elseif (!in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		{
			$_SESSION['forms'][] = (int) $_REQUEST['seqnum'];
			return true;
		}
		elseif ($is_fatal)
			fatal_lang_error('error_form_already_submitted', false);
		else
			return false;
	}

	elseif ($action == 'free' && isset($_REQUEST['seqnum']) && in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		$_SESSION['forms'] = array_diff($_SESSION['forms'], array($_REQUEST['seqnum']));
	elseif ($action != 'free')
		trigger_error('checkSubmitOnce(): Invalid action \'' . $action . '\'', E_USER_WARNING);
}


function allowedTo($permissions, $boards = null)
{

	if (empty($permissions))
		return true;


	if (we::$is_admin)
		return true;


	if ($boards === null)
	{

		$perms = isset(we::$user['permissions']) ? array_flip(we::$user['permissions']) : array();


		if (!is_array($permissions))
			return isset($perms[$permissions]);


		$can_do = false;

		foreach ($permissions as $perm)
		{
			$can_do |= isset($perms[$perm]);
			if ($can_do)
				break;
		}
		return $can_do;
	}
	elseif (!is_array($boards))
		$boards = array($boards);

	$request = wesql::query('
		SELECT MIN(bp.add_deny) AS add_deny
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}board_permissions AS bp ON (bp.id_profile = b.id_profile)
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE b.id_board IN ({array_int:board_list})
			AND bp.id_group IN ({array_int:group_list}, {int:moderator_group})
			AND bp.permission {raw:permission_list}
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})
		GROUP BY b.id_board',
		array(
			'current_member' => MID,
			'board_list' => $boards,
			'group_list' => we::$user['groups'],
			'moderator_group' => 3,
			'permission_list' => is_array($permissions) ? 'IN (\'' . implode('\', \'', $permissions) . '\')' : ' = \'' . $permissions . '\'',
		)
	);


	if (wesql::num_rows($request) != count($boards))
		return false;

	$can_do = true;
	while ($row = wesql::fetch_assoc($request))
	{
		$can_do &= !empty($row['add_deny']);

		if (!$can_do)
			break;
	}
	wesql::free_result($request);

	return $can_do;
}



function isAllowedTo($permissions, $boards = null)
{
	global $txt;

	static $heavy_permissions = array(
		'admin_forum',
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'edit_news',
		'moderate_forum',
		'manage_bans',
		'manage_membergroups',
		'manage_permissions',
	);

	$permissions = (array) $permissions;


	if (!allowedTo($permissions, $boards))
	{

		$error_permission = array_shift($permissions);


		if (we::$is_guest)
		{
			loadLanguage('Errors');
			is_not_guest($txt['cannot_' . $error_permission]);
		}



		writeLog(true);

		fatal_lang_error('cannot_' . $error_permission, false);


		trigger_error('Hacking attempt...', E_USER_ERROR);
	}



	if (!allowedTo(array_diff($permissions, $heavy_permissions), $boards))
		validateSession();
}


function boardsAllowedTo($permissions, $check_access = true)
{
	global $settings;


	$groups = array_diff(we::$user['groups'], array(3));

	if (!is_array($permissions))
	{

		if (we::$is_admin)
			return array(0);


		if (we::$is_guest && empty($settings['allow_guestAccess']))
			return array();

		$request = wesql::query('
			SELECT b.id_board, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
				INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
				AND bp.permission IN ({string:permissions})
				AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})' .
				($check_access ? ' AND {query_see_board}' : ''),
			array(
				'current_member' => MID,
				'group_list' => $groups,
				'moderator_group' => 3,
				'permissions' => $permissions,
			)
		);
		$boards = array();
		$deny_boards = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$deny_boards[] = $row['id_board'];
			else
				$boards[] = $row['id_board'];
		}
		wesql::free_result($request);

		$boards = array_unique(array_values(array_diff($boards, $deny_boards)));

		return $boards;
	}
	else
	{

		if (we::$is_admin)
		{
			$final_list = array();
			foreach ($permissions as $perm)
				$final_list[$perm] = array(0);
			return $final_list;
		}


		$final_list = array();
		foreach ($permissions as $perm)
			$final_list[$perm] = array();


		if (we::$is_guest && empty($settings['allow_guestAccess']))
			return $final_list;

		$boards = array();
		$deny_boards = array();
		$request = wesql::query('
			SELECT b.id_board, bp.permission, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
				INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
				AND bp.permission IN ({array_string:permissions})
				AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})' .
				($check_access ? ' AND {query_see_board}' : ''),
			array(
				'current_member' => MID,
				'group_list' => $groups,
				'moderator_group' => 3,
				'permissions' => $permissions,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$deny_boards[$row['permission']][] = $row['id_board'];
			else
				$boards[$row['permission']][] = $row['id_board'];
		}
		wesql::free_result($request);

		foreach ($permissions as $perm)
		{
			if (empty($boards[$perm]))
				$boards[$perm] = array();
			if (empty($deny_boards[$perm]))
				$deny_boards[$perm] = array();

			$final_list[$perm] = array_unique(array_values(array_diff($boards[$perm], $deny_boards[$perm])));
		}

		return $final_list;
	}
}













function showEmailAddress($userProfile_hideEmail, $userProfile_id)
{
	return we::$is_guest || we::$user['post_moderated'] ? 'no' : ((we::$is_member && MID == $userProfile_id && !$userProfile_hideEmail) || allowedTo('moderate_forum') ? 'yes_permission_override' : ($userProfile_hideEmail ? 'no' : 'no_through_forum'));
}











function checkUserBehavior()
{
	global $context, $txt, $webmaster_email, $board, $board_info;

	$context['http_headers'] = get_http_headers();

	if (isset($context['additional_headers']))
		$context['http_headers'] = array_merge($context['http_headers'], $context['additional_headers']);



	$whitelist = array(
		'ip' => array(
			'10.0.0.0/8',
			'172.16.0.0/12',
			'192.168.0.0/16',
		),
		'user-agent' => array(
		),
		'action' => array(
		),
	);

	if (!empty($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != 'unknown')
	{
		foreach ($whitelist['ip'] as $item)
		{
			if (strpos($item, '/') === false)
			{
				if ($_SERVER['REMOTE_ADDR'] === $item)
					return true;
			}

			elseif (match_cidr($_SERVER['REMOTE_ADDR'], $item))
				return true;
		}
	}

	if (!empty($context['http_headers']['user-agent']))
	{
		foreach ($whitelist['user-agent'] as $item)
			if ($context['http_headers']['user-agent'] === $item)
				return true;
	}
	else
		$context['http_headers']['user-agent'] = '';

	if (!empty($_GET['action']))
		foreach ($whitelist['action'] as $item)
			if ($_GET['action'] === $item)
				return true;


	$context['behavior_error'] = '';

	if (checkUserRequest_blacklist() || checkUserRequest_request() || checkUserRequest_useragent() || checkUserRequest_post())
	{

		$headers = '';
		$entity = '';

		foreach ($context['http_headers'] as $k => $v)
			if ($k != 'user-agent')
				$headers .= ($headers != '' ? '<br>' : '') . htmlspecialchars($k . '=' . ($k != 'x-forwarded-for' ? $v : format_ip($v)));

		foreach ($_POST as $k => $v)
			$entity .= ($entity != '' ? '<br>' : '') . htmlspecialchars($k . '=' . $v);

		wesql::insert('',
			'{db_prefix}log_intrusion',
			array(
				'id_member' => 'int', 'error_type' => 'string', 'ip' => 'int', 'event_time' => 'int', 'http_method' => 'string',
				'request_uri' => 'string-255', 'protocol' => 'string-10', 'user_agent' => 'string-255', 'headers' => 'string', 'request_entity' => 'string',
			),
			array(
				MID, substr($context['behavior_error'], 6), get_ip_identifier($_SERVER['REMOTE_ADDR']), time(), $_SERVER['REQUEST_METHOD'],
				isset($_SERVER['REAL_REQUEST_URI']) ? $_SERVER['REAL_REQUEST_URI'] : $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL'], $context['http_headers']['user-agent'], $headers, $entity,
			)
		);
		$error_id = wesql::insert_id();

		if (we::$is_admin)
			return false;
		else
		{
			list ($error, $error_blocks) = userBehaviorResponse();
			header('HTTP/1.1 ' . $error . ' Wedge Defenses');
			header('Status: ' . $error . ' Wedge Defenses');


			$context['linktree'] = array();
			$context['open_mod_reports'] = $board = 0;
			$_GET['board'] = $_GET['topic'] = '';
			$board_info = array(
				'moderators' => array(),
				'skin' => '',
			);

			loadPermissions();
			loadTheme();


			loadTemplate('Errors');
			loadLanguage('Security');


			wetem::load('fatal_error');
			$context['no_back_link'] = true;
			$context['robot_no_index'] = true;
			$context['page_title'] = $txt['http_error'] . ' ' . $error;
			$context['error_title'] = $txt['behav_' . $error];
			$context['error_message'] = $txt['behavior_header'] . '<br><br>' . $txt[$context['behavior_error'] . '_desc'];
			foreach ($error_blocks as $block)
				$context['error_message'] .= '<br><br>' . $txt[$block];
			$context['error_message'] .= '<br><br>' . $txt['behavior_footer'];

			$context['error_message'] = str_replace('{email_address}', str_replace('@', '+REMOVEME@REMOVEME.', $webmaster_email), $context['error_message']);
			$context['error_message'] = str_replace('{incident}', '#' . $error_id, $context['error_message']);


			obExit(null, true, false, true);
		}
	}
	else
		return true;
}








function checkUserRequest_blacklist()
{
	global $context, $settings;


	$rules = array(
		'begins_with' => array(

			'autoemailspider',
			'BrowserEmulator/',
			'CherryPicker',
			'Digger',
			'ecollector',
			'EmailCollector',
			'Email Siphon',
			'EmailSiphon',
			'ISC Systems iRc',
			'Microsoft URL',
			'Missigua',
			'Mozilla/4.0+(compatible;+',
			'OmniExplorer',
			'psycheclone',
			'Super Happy Fun ',
			'user',
			'User Agent: ',
			'User-Agent: ',

			'core-project/',
			'Internet Explorer',
			'Winnie Poh',

			'Diamond',
			'MJ12bot',
			'Mozilla/0',
			'Mozilla/1',
			'Mozilla/2',
			'Mozilla/3',
			'MSIE',
			'sqlmap/',
			'Wordpress',
			'"',
			'-',

			'blogsearchbot-martin',
			'Mozilla/4.0(',

			'8484 Boston Project',
			'adwords',
			'ArchiveTeam',
			'Forum Poster',
			'grub crawler',
			'HttpProxy',
			'Jakarta Commons',
			'Java 1.',
			'Java/1.',
			'libwww-perl',
			'LWP',
			'lwp',
			'Movable Type',
			'NutchCVS',
			'Nutscrape/',
			'Opera/9.64(',
			'PussyCat ',
			'PycURL',
			'Python-urllib',
			'TrackBack/',
			'WebSite-X Suite',
			'xpymep',

			'Morfeus',
			'Mozilla/4.0 (Hydra)',
			'Nessus',
			'PMAFind',
			'revolt',
			'w3af',
		),
		'contains' => array(

			'Email Extractor',
			'EMail Exractor',
			'.NET CLR1',
			'Perman Surfer',
			'unspecified.mail',
			'User-agent: ',
			'WebaltBot',
			'Windows XP 5',
			'WISEbot',
			'WISEnutbot',
			'\\)',

			"\r",
			'grub-client',
			'ArchiveBot',
			'hanzoweb',
			'Havij',
			'MSIE 7.0;  Windows NT 5.2',
			'Turing Machine',

			'; Widows ',
			'a href=',
			'compatible ; MSIE',
			'compatible-',
			'DTS Agent',
			'Gecko/2525',
			'Indy Library',
			'MVAClient',
			'Murzillo compatible',
			'.NET CLR 1)',
			'POE-Component-Client',
			'Ubuntu/9.25',
			'Windows NT 5.0;)',
			'Windows NT 5.1;)',
			'WordPress/4.01',
			'Xedant Human Emulator',

			'<sc',
			'ZmEu',
			': ;',
			':;',

			'Forest Lobster',
			'Ming Mong',
			'Netsparker',
			'Nikto/',
		),
		'contains_regex' => array(

			'~^[A-Z]{10}$~',
			'~[bcdfghjklmnpqrstvwxz ]{8,}~',
			'~MSIE.*Windows XP~',
			'~MSIE [2345]~',
		),
	);

	if (empty($settings['allow_jurassic_crap']))
	{
		$rules['begins_with'][] = 'Microsoft Internet Explorer/';
		$rules['contains'] += array(
			'Firebird/',
			'Win95',
			'Win98',
			'WinME',
			'Win 9x 4.90',
			'Windows 3',
			'Windows 95',
			'Windows 98',
			'Windows NT 4',
			'Windows NT;',
		);
	}

	foreach ($rules['begins_with'] as $test)
		if (strpos($context['http_headers']['user-agent'], $test) === 0)
			return $context['behavior_error'] = 'behav_blacklist';

	foreach ($rules['contains'] as $test)
		if (strpos($context['http_headers']['user-agent'], $test) !== false)
			return $context['behavior_error'] = 'behav_blacklist';

	foreach ($rules['contains_regex'] as $test)
		if (preg_match($test, $context['http_headers']['user-agent']))
			return $context['behavior_error'] = 'behav_blacklist';

	return false;
}








function checkUserRequest_request()
{
	global $context, $settings;


	if (isset($context['http_headers']['cf-connecting-ip'], $context['http_headers']['x-detected-remote-address']) && empty($settings['disableHostnameLookup']))
	{

		if (!test_ip_host($context['http_headers']['x-detected-remote-address'], 'cloudflare.com'))
			return $context['behavior_error'] = 'behav_not_cloudflare';
	}


	if (isset($context['http_headers']['expect']) && stripos($context['http_headers']['expect'], '100-continue') !== false && stripos($_SERVER['SERVER_PROTOCOL'], 'HTTP/1.0') !== false)
		return $context['behavior_error'] = 'behav_expect_header';


	if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($context['http_headers']['user-agent']))
		return $context['behavior_error'] = 'behav_no_ua_in_post';


	if (isset($context['http_headers']['content-range']))
		return $context['behavior_error'] = 'behav_content_range';



	if (isset($context['http_headers']['referer']))
	{
		if (empty($context['http_headers']['referer']))
			return $context['behavior_error'] = 'behav_empty_refer';
		elseif (strpos($context['http_headers']['referer'], '://') === false)
			return $context['behavior_error'] = 'behav_invalid_refer';
	}


	if (isset($context['http_headers']['connection']))
	{

		$ka = preg_match_all('~\bkeep-alive\b~i', $context['http_headers']['connection'], $dummy);
		$c = preg_match_all('~\bclose\b~i', $context['http_headers']['connection'], $dummy);
		if (($ka > 0 && $c > 0) || $ka > 1 || $c > 1)
			return $context['behavior_error'] = 'behav_alive_close';


		if (stripos($context['http_headers']['connection'], 'Keep-Alive: ') !== false)
			return $context['behavior_error'] = 'behav_wrong_keep_alive';
	}


	if (!empty($_SERVER['REQUEST_URI']))
	{
		$rogue_chars = array(
			'exact_contains' => array(
				'#',
				';DECLARE%20@',
				'../',
				'..\\',
			),
			'insens_contains' => array(

				'%60information_schema%60',
				'+%2F*%21',
				'+and+%',
				'+and+1%',
				'+and+if',
				'%27--',
				'%27 --',
				'%27%23',
				'%27 %23',
				'benchmark%28',
				'insert+into+',
				'r3dm0v3',
				'select+1+from',
				'union+all+select',
				'union+select',
				'waitfor+delay+',
				'0x31303235343830303536',

				'w00tw00t',
			),
		);
		foreach ($rogue_chars['exact_contains'] as $str)
			if (strpos($_SERVER['REQUEST_URI'], $str) !== false)
				return $context['behavior_error'] = 'behav_rogue_chars';

		$insens = strtolower($_SERVER['REQUEST_URI']);
		foreach ($rogue_chars['insens_contains'] as $str)
			if (strpos($insens, $str) !== false)
				return $context['behavior_error'] = 'behav_rogue_chars';
	}


	if (isset($context['http_headers']['via']) && (stripos($context['http_headers']['via'], 'pinappleproxy') !== false || stripos($context['http_headers']['via'], 'PCNETSERVER') !== false || stripos($context['http_headers']['via'], 'Invisiware') !== false))
		return $context['behavior_error'] = 'behav_banned_via_proxy';


	if (isset($context['http_headers']['x-aaaaaaaaaaaa']) || isset($context['http_headers']['x-aaaaaaaaaa']))
		return $context['behavior_error'] = 'behav_banned_xaa_proxy';



	if (isset($context['http_headers']['cookie']) && strpos($context['http_headers']['cookie'], '$Version=0') !== false && !isset($context['http_headers']['cookie2']) && strpos($context['http_headers']['user-agent'], 'Kindle/') === false)
		return $context['behavior_error'] = 'behav_bot_rfc2965';


	if (empty($settings['performStrictBehavior']))
		return false;




	if (isset($context['http_headers']['proxy-connection']))
		return $context['behavior_error'] = 'behav_proxy_connection';


	if ($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1' && isset($context['http_headers']['pragma']) && strpos($context['http_headers']['pragma'], 'no-cache') !== false && !isset($context['http_headers']['cache-control']))
		return $context['behavior_error'] = 'behav_pragma';



	if (isset($context['http_headers']['te']) && !preg_match('~\bTE\b~', $context['http_headers']['connection']))
		return $context['behavior_error'] = 'behav_te_error';



	if (isset($context['http_headers']['range']) && strpos($context['http_headers']['range'], '=0-') !== false && (strpos($context['http_headers']['user-agent'], 'MovableType') !== 0 && strpos($context['http_headers']['user-agent'], 'URI::Fetch') !== 0 && strpos($context['http_headers']['user-agent'], 'php-openid/') !== 0 && strpos($context['http_headers']['user-agent'], 'facebookexternalhit') !== 0))
		return $context['behavior_error'] = 'behav_invalid_range';

	return false;
}








function checkUserRequest_useragent()
{
	global $context, $settings;

	$ip = $_SERVER['BAN_CHECK_IP'];

	$ua = $context['http_headers']['user-agent'];
	$lua = strtolower($ua);


	if (strhas($ua, array('Opera', 'Lynx', 'Safari')) && !we::$is_member)
	{
		if (!isset($context['http_headers']['accept']))
			return $context['behavior_error'] = 'behav_no_accept';
	}

	elseif (strhas($lua, 'konqueror') && !we::$is_member)
	{
		if (!isset($context['http_headers']['accept']) && (!strhas($lua, 'yahooseeker/cafekelsa') || !match_cidr($ip, '209.73.160.0/19')))
			return $context['behavior_error'] = 'behav_no_accept';
	}

	elseif (strhas($ua, '; MSIE') && !we::$is_member)
	{

		if (strhas($ua, array('Windows ME', 'Windows XP', 'Windows 2000', 'Win32')))
			return $context['behavior_error'] = 'behav_invalid_win';

		elseif (!isset($context['http_headers']['akamai-origin-hop']) && !strhas($ua, 'IEMobile') && @preg_match('~\bTE\b~i', $context['http_headers']['connection']))
			return $context['behavior_error'] = 'behav_te_not_msie';
	}

	elseif (strhas($lua, array('yahoo! slurp', 'yahoo! searchmonkey')))
	{
		if (we::$is_member || !match_cidr($ip, array('202.160.176.0/20', '67.195.0.0/16', '203.209.252.0/24', '72.30.0.0/16', '98.136.0.0/14', '74.6.0.0/16')) || (empty($settings['disableHostnameLookup']) && !test_ip_host($ip, 'crawl.yahoo.net')))
			return $context['behavior_error'] = 'behav_not_yahoobot';
	}

	elseif (strhas($lua, array('bingbot', 'msnbot', 'ms search')))
	{
		if (we::$is_member || (empty($settings['disableHostnameLookup']) && !test_ip_host($ip, 'msn.com')))
			return $context['behavior_error'] = 'behav_not_msnbot';
	}

	elseif (strhas($lua, array('googlebot', 'mediapartners-google', 'google web preview')))
	{
		if (we::$is_member || !match_cidr($ip, array('66.249.64.0/19', '64.233.160.0/19', '72.14.192.0/18', '203.208.32.0/19', '74.125.0.0/16', '216.239.32.0/19', '209.85.128.0/17')) || (empty($settings['disableHostnameLookup']) && !test_ip_host($ip, 'googlebot.com')))
			return $context['behavior_error'] = 'behav_not_googlebot';
	}

	elseif (strhas($lua, 'baidu'))
	{
		if (we::$is_member || !match_cidr($ip, array('119.63.192.0/21', '123.125.71.0/24', '180.76.0.0/16', '220.181.0.0/16')))
			return $context['behavior_error'] = 'behav_not_baidu';
	}

	elseif (strpos($lua, 'mozilla') === 0)
	{

		if (!isset($context['http_headers']['accept']) && !strhas($ua, array('Google Desktop', 'PLAYSTATION 3')))
			return $context['behavior_error'] = 'behav_no_accept';
	}

	return false;
}








function checkUserRequest_post()
{
	global $context, $settings;

	if ($_SERVER['REQUEST_METHOD'] != 'POST')
		return false;


	foreach ($_POST as $key => $value) {
		if (strpos($key, '	document.write') !== false)
			return $context['behavior_error'] = 'behav_rogue_chars';
	}



	if (empty($settings['allow_external_forms']) && isset($context['http_headers']['referer']) && stripos($context['http_headers']['referer'], $context['http_headers']['host']) === false)
		return $context['behavior_error'] = 'behav_offsite_form';

	return false;
}






function userBehaviorResponse()
{
	global $context;
	$error_blocks = array();


	switch ($context['behavior_error'])
	{
		case 'behav_pragma':
		case 'behav_empty_refer':
		case 'behav_invalid_refer':
		case 'behav_invalid_range':
		case 'behav_te_error':
		case 'behav_alive_close':
		case 'behav_wrong_keep_alive':
		case 'behav_proxy_connection':
			$error = 400;
			break;
		case 'behav_pomme':
			$error = 0x29A;
			break;
		case 'behav_expect_header':
			$error = 417;
			break;
		default:
			$error = 403;
	}


	switch ($context['behavior_error'])
	{
		case 'behav_expect_header':
		case 'behav_wrong_keep_alive':
		case 'behav_rogue_chars':
			$error_blocks = array('behavior_malware');
		break;

		case 'behav_te_not_msie':
		case 'behav_not_msnbot':
		case 'behav_not_yahoobot':
		case 'behav_not_googlebot':
		case 'behav_not_baidu':
			$error_blocks = array('behavior_false_ua', 'behavior_misconfigured_privacy');
		break;

		case 'behav_no_ua_in_post':
		case 'behav_invalid_win':
		case 'behav_blacklist':
		case 'behav_not_cloudflare':
			$error_blocks = array('behavior_false_ua', 'behavior_misconfigured_proxy', 'behavior_misconfigured_privacy', 'behavior_malware');
		break;

		case 'behav_pragma':
		case 'behav_empty_refer':
		case 'behav_invalid_refer':
		case 'behav_proxy_connection':
		case 'behav_banned_via_proxy':
		case 'behav_banned_xaa_proxy':
		case 'behav_alive_close':
		case 'behav_no_accept':
		case 'behav_content_range':
		case 'behav_invalid_via':
			$error_blocks = array('behavior_misconfigured_proxy', 'behavior_misconfigured_privacy', 'behavior_malware');
		break;

		case 'behav_offsite_form':
			$error_blocks = array('behavior_misconfigured_privacy', 'behavior_malware');
		break;

		case 'behav_te_error':
			$error_blocks = array('behavior_misconfigured_proxy', 'behavior_misconfigured_privacy', 'behavior_malware', 'behavior_opera_bug');
		break;

		case 'behav_invalid_range':
			$error_blocks = array('behavior_malware', 'behavior_chrome_bug');
		break;
	}

	return array($error, $error_blocks);
}











function test_ip_host($ip, $domain)
{

	return true;

	$host = host_from_ip($ip);
	$host_result = strpos(strrev($host), strrev($domain));
	if ($host_result === false || $host_result > 0)
		return false;
	$addrs = gethostbynamel($host);
	return in_array($ip, $addrs);
}

function get_privacy_type($privacy)
{


	return $privacy == PRIVACY_DEFAULT ? 'public' :
		($privacy == PRIVACY_MEMBERS ? 'members' :
		($privacy < 0 ? 'group' :
		($privacy == PRIVACY_AUTHOR ? 'author' :
		($privacy > 99 ? (isset(we::$user['contacts']['lists'][$privacy][1]) ? 'list_' . we::$user['contacts']['lists'][$privacy][1] : 'list') : ''))));
}

function get_privacy_icon($privacy)
{
	global $txt;

	if (!($type = get_privacy_type($privacy)) || $type === 'public')
		return '';

	return '<div class="privacy_' . $type . '" title="' . (strpos($type, 'list') === 0 ? $txt['privacy_list'] : $txt['privacy_' . $type]) . '"></div>';
}


function get_privacy_widget($privacy, $can_edit = false, $text = '', $area = '')
{
	global $txt;

	if (we::$is_guest)
		return $text;

	$list = array(
		PRIVACY_DEFAULT => 'public',
		PRIVACY_MEMBERS => 'members',
		PRIVACY_AUTHOR => 'author',
	);
	$privacy = explode(',', $privacy);
	$shown_privacy = min($privacy);
	foreach ($privacy as $this_privacy)
	{
		if (isset(we::$user['contacts']['lists'][$this_privacy]))
			$list[$this_privacy] = 'list';
		elseif (isset(we::$user['contacts']['groups'][-$this_privacy]))
			$list[$this_privacy] = 'group';
	}

	$prvlist = '';
	foreach (array('default', 'public', 'members', 'group', 'list', 'author') as $prv)
		$prvlist .= '
		"' . $prv . '": ' . JavaScriptEscape($txt['privacy_' . $prv]) . ',';

	add_js_unique('
	prv_opt = {' . substr($prvlist, 0, -1) . '
	};
	$(".privacy").each(function () {
		$(this).title(
			' . JavaScriptEscape($txt['privacy_bubble']) . '.replace("{PRIVACY}", prv_opt[$(this).find("div").first().attr("class").replace("privacy_", "")])' . ($can_edit ? '
			+ ' . JavaScriptEscape('<br>' . $txt['privacy_can_edit']) : '') . '
		);
	});');

	if ($can_edit)
		add_js_unique('
	$(".prv_sel").change(function (e) {
		show_ajax();
		var that = $(this), v = that.val(), prv, tmp;
		$.get(weUrl("action=profile;u=' . we::$id . ';prv=" + v + ";pa=" + that.parent().find(".privacy").attr("id").slice(3) + ";" + we_sessvar + "=" + we_sessid), function (ret) {
			hide_ajax();
			$.each(ret.split(","), function (index, val) {
				tmp = "";
				if (val == ' . PRIVACY_DEFAULT . ')
					tmp = "public";
				else if (val == ' . PRIVACY_MEMBERS . ')
					tmp = "members";
				else if (val == ' . PRIVACY_AUTHOR . ')
					tmp = "author";
				if (tmp)
				{
					prv = tmp;
					return false;
				}
				prv = val > 0 ? "list" : "group";
			});
			that.siblings(".mime").find(".privacy>div").attr("class", "privacy_" + prv);
		});
	});
	$(".privacy").click(function (e) {
		var p = $(this).parent().siblings("select").data("sb");
		p && p.open();
		return false;
	});');

	return '
		<span class="privacy"' . ($area ? ' id="pa_' . $area . '"' : '') . '><div class="privacy_' . $list[$shown_privacy] . '"></div>' . $text . '</span>' . ($can_edit ? '
		<select class="prv_sel" multiple>' . get_privacy_options(array_flip($privacy)) . '</select>' : '');
}

function get_privacy_options($privacy = array())
{
	global $txt;

	$pr = '<option value="' . PRIVACY_DEFAULT . '"' . (isset($privacy[PRIVACY_DEFAULT]) ? ' selected' : '') . ' class="single"">&lt;div class="privacy_public"&gt;&lt;/div&gt;' . $txt['privacy_public'] . '</option>';
	$pr .= '<option value="' . PRIVACY_MEMBERS . '"' . (isset($privacy[PRIVACY_MEMBERS]) ? ' selected' : '') . ' class="single">&lt;div class="privacy_members"&gt;&lt;/div&gt;' . $txt['privacy_members'] . '</option>';
	$pr .= '<option value="' . PRIVACY_AUTHOR . '"' . (isset($privacy[PRIVACY_AUTHOR]) ? ' selected' : '') . ' class="single">&lt;div class="privacy_author"&gt;&lt;/div&gt;' . $txt['privacy_author'] . '</option>';
	if (!empty(we::$user['contacts']['lists']))
	{
		$pr .= '<optgroup label="' . $txt['privacy_list'] . '">';
		foreach (we::$user['contacts']['lists'] as $id => $p)
			$pr .= '<option value="' . $id . '"' . (isset($privacy[$id]) ? ' selected' : '') . '>&lt;div class="privacy_list_' . $p[1] . '"&gt;&lt;/div&gt;' . generic_contacts($p[0]) . '</option>';
		$pr .= '</optgroup>';
	}
	if (!empty(we::$user['contacts']['groups']))
	{
		$pr .= '<optgroup label="' . $txt['privacy_group'] . '">';
		foreach (we::$user['contacts']['groups'] as $id => $p)
			$pr .= '<option value="-' . $id . '"' . (isset($privacy[-$id]) ? ' selected' : '') . '>&lt;div class="privacy_group"&gt;&lt;/div&gt;' . ($p[1] >= 0 ? '&lt;em&gt;' . $p[0] . '&lt;/em&gt; &lt;small&gt;' . $p[1] . '&lt;/small&gt;' : $p[0]) . '</option>';
		$pr .= '</optgroup>';
	}
	return $pr;
}
