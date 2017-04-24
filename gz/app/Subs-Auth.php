<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');











































































function setLoginCookie($cookie_length, $id, $password = '')
{
	global $cookiename, $settings, $aliases;


	$_SESSION['mc']['time'] = 0;


	$cookie_state = (empty($settings['localCookies']) ? 0 : 1) | (empty($settings['globalCookies']) ? 0 : 2);
	if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
	{
		$array = @unserialize($_COOKIE[$cookiename]);


		if (isset($array[3]) && $array[3] != $cookie_state)
		{
			$cookie_url = url_parts($array[3] & 1 > 0, $array[3] & 2 > 0);
			setcookie($cookiename, serialize(array(0, '', 0)), time() - 3600, $cookie_url[1], $cookie_url[0], !empty($settings['secureCookies']), true);
		}
	}


	$data = serialize(empty($id) ? array(0, '', 0) : array($id, $password, time() + $cookie_length, $cookie_state));
	$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));


	setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0], !empty($settings['secureCookies']), true);


	if (empty($id) && !empty($settings['globalCookies']))
		setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], '', !empty($settings['secureCookies']), true);


	if (!empty($aliases))
	{
		foreach (explode(',', $aliases) as $alias)
		{

			$alias = strtr(trim($alias), array('http://' => '', 'https://' => ''));
			$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']), 'http://' . $alias);

			if ($cookie_url[0] == '')
				$cookie_url[0] = strtok($alias, '/');

			setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0], !empty($settings['secureCookies']), true);
		}
	}

	$_COOKIE[$cookiename] = $data;


	if (!isset($_SESSION['login_' . $cookiename]) || $_SESSION['login_' . $cookiename] !== $data)
	{

		$oldSessionData = $_SESSION;
		$_SESSION = array();
		session_destroy();


		loadSession();
		session_regenerate_id();
		$_SESSION = $oldSessionData;

		$_SESSION['login_' . $cookiename] = $data;
	}
}


function url_parts($local, $global, $force_url = '')
{

	$parsed_url = parse_url(empty($url) ? ROOT : $force_url);


	if (empty($parsed_url['path']) || !$local)
		$parsed_url['path'] = '';


	if ($global && preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^.]+\.)?([^.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			$parsed_url['host'] = '.' . $parts[1];


	elseif (!$local && !$global)
		$parsed_url['host'] = '';


	elseif (!isset($parsed_url['host']) || strpos($parsed_url['host'], '.') === false)
		$parsed_url['host'] = '';

	return array($parsed_url['host'], $parsed_url['path'] . '/');
}


function KickGuest()
{
	global $txt, $context;

	loadLanguage('Login');
	loadTemplate('Login');


	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	wetem::load('kick_guest');
	$context['page_title'] = $txt['login'];
}

function Reagree()
{
	global $txt, $context, $settings;

	loadLanguage(array('Agreement', 'Login'));
	loadTemplate('Login');

	$context['agreement'] = parse_bbc($txt['registration_agreement_body'], 'agreement', array('cache' => 'agreement_' . we::$user['language']));
	$context['agree_type'] = !empty($settings['agreement_force']) ? $txt['registration_reagreement_force'] : $txt['registration_reagreement_postonly'];

	wetem::load('reagreement');
	$context['page_title'] = $txt['registration_agreement'];
}


function InMaintenance()
{
	global $txt, $mtitle, $mmessage, $context;

	loadLanguage('Login');
	loadTemplate('Login');


	header('HTTP/1.1 503 Service Temporarily Unavailable');


	wetem::load('maintenance');
	$context['title'] = westr::safe($mtitle);
	$context['description'] =& $mmessage;
	$context['page_title'] = $txt['maintain_mode'];
}

function adminLogin()
{
	global $context, $txt;

	if (AJAX)
		return_raw('<script>location = "', we::$user['url'], '";</script>');

	loadLanguage('Admin');
	loadTemplate('Login');


	if (isset($_POST['admin_hash_pass']) || isset($_POST['admin_pass']))
	{
		$txt['security_wrong'] = sprintf($txt['security_wrong'], isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $txt['unknown'], $_SERVER['HTTP_USER_AGENT'], format_ip(we::$user['ip']));
		log_error($txt['security_wrong'], 'critical');

		unset($_POST['admin_hash_pass'], $_POST['admin_pass']);

		$context['incorrect_password'] = true;
	}


	$context['post_data'] = '';


	$_POST[$context['session_var']] = $context['session_id'];
	foreach ($_POST as $k => $v)
		$context['post_data'] .= adminLogin_outputPostVars($k, $v);


	wetem::load('admin_login');


	if (!isset($context['page_title']))
		$context['page_title'] = $txt['login'];

	obExit();


	trigger_error('Hacking attempt...', E_USER_ERROR);
}

function adminLogin_outputPostVars($k, $v)
{
	if (!is_array($v))
		return '
<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . strtr($v, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;')) . '">';
	else
	{
		$ret = '';
		foreach ($v as $k2 => $v2)
			$ret .= adminLogin_outputPostVars($k . '[' . $k2 . ']', $v2);

		return $ret;
	}
}


function findMembers($names, $use_wildcards = false, $buddies_only = false, $max = 500)
{

	if (!is_array($names))
		$names = explode(',', $names);

	$maybe_email = false;
	foreach ($names as $i => $name)
	{

		$names[$i] = trim(westr::strtolower($name));

		$maybe_email |= strpos($name, '@') !== false;


		if ($use_wildcards)
			$names[$i] = strtr($names[$i], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '\'' => '&#039;'));
		else
			$names[$i] = strtr($names[$i], array('\'' => '&#039;'));
	}


	$comparison = $use_wildcards ? 'LIKE' : '=';


	$results = array();


	$email_condition = allowedTo('moderate_forum') ? '' : 'hide_email = 0 AND ';

	if ($use_wildcards || $maybe_email)
		$email_condition = '
			OR (' . $email_condition . 'email_address ' . $comparison . ' \'' . implode('\') OR (' . $email_condition . ' email_address ' . $comparison . ' \'', $names) . '\')';
	else
		$email_condition = '';


	$member_name = 'member_name';
	$real_name = 'real_name';


	$request = wesql::query('
		SELECT id_member, member_name, real_name, email_address, hide_email
		FROM {db_prefix}members
		WHERE ({raw:member_name_search}
			OR {raw:real_name_search} {raw:email_condition})
			' . ($buddies_only ? 'AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11, 21)
		LIMIT {int:limit}',
		array(
			'buddy_list' => we::$user['buddies'],
			'member_name_search' => $member_name . ' ' . $comparison . ' \'' . implode('\' OR ' . $member_name . ' ' . $comparison . ' \'', $names) . '\'',
			'real_name_search' => $real_name . ' ' . $comparison . ' \'' . implode('\' OR ' . $real_name . ' ' . $comparison . ' \'', $names) . '\'',
			'email_condition' => $email_condition,
			'limit' => $max,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$results[$row['id_member']] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'username' => $row['member_name'],
			'email' => showEmailAddress(!empty($row['hide_email']), $row['id_member']) === 'yes_permission_override' ? $row['email_address'] : '',
			'href' => '<URL>?action=profile;u=' . $row['id_member'],
			'link' => '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
		);
	}
	wesql::free_result($request);


	return $results;
}


function resetPassword($memID, $username = null)
{
	global $settings;


	loadLanguage('Login');
	loadSource('Subs-Post');


	$request = wesql::query('
		SELECT member_name, real_name, email_address, lngfile
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}',
		array(
			'id_member' => $memID,
		)
	);
	list ($user, $display_user, $email, $lngfile) = wesql::fetch_row($request);
	wesql::free_result($request);

	if ($username !== null)
	{
		$old_user = $user;
		$user = trim($username);
	}


	$newPassword = substr(preg_replace('/\W/', '', md5(mt_rand())), 0, 10);
	$newPassword_sha1 = sha1(strtolower($user) . $newPassword);


	if ($username !== null)
	{
		validateUsername($memID, $user);


		updateMemberData($memID, array('member_name' => $user, 'passwd' => $newPassword_sha1));
	}
	else
		updateMemberData($memID, array('passwd' => $newPassword_sha1));

	call_hook('reset_pass', array($old_user, $user, $newPassword));

	$replacements = array(
		'USERNAME' => $user,
		'REALNAME' => $display_user,
		'PASSWORD' => $newPassword,
	);

	$emaildata = loadEmailTemplate('change_password', $replacements, empty($lngfile) || empty($settings['userLanguage']) ? $settings['language'] : $lngfile);


	sendmail($email, $emaildata['subject'], $emaildata['body'], null, null, false, 0);
}


function validateUsername($memID, $username)
{
	global $txt;


	if ($username == '')
		fatal_lang_error('need_username', false);


	if (in_array($username, array('_', '|')) || preg_match('~[<>&"\'=\\\\]~', preg_replace('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', '', $username)) != 0 || strhas($username, array('[code', '[/code')))
		fatal_lang_error('error_invalid_characters_username', false);

	if (stristr($username, $txt['guest_title']) !== false)
		fatal_lang_error('username_reserved', true, array($txt['guest_title']));

	loadSource('Subs-Members');
	if (isReservedName($username, $memID, false))
	{
		loadLanguage('Login');
		fatal_error('(' . htmlspecialchars($username) . ') ' . $txt['name_in_use'], false);
	}

	return null;
}


function validatePassword($password, $username, $restrict_in = array())
{
	global $settings;


	if (westr::strlen($password) < (empty($settings['password_strength']) ? 4 : 8))
		return 'short';


	if (empty($settings['password_strength']))
		return null;


	if (preg_match('~\b' . preg_quote($password, '~') . '\b~', implode(' ', $restrict_in)) != 0)
		return 'restricted_words';
	elseif (westr::strpos($password, $username) !== false)
		return 'restricted_words';




	if ($settings['password_strength'] == 1)
		return null;


	$good = preg_match('~(\D\d|\d\D)~', $password) != 0;
	$good &= westr::strtolower($password) != $password;

	return $good ? null : 'chars';
}


function rebuildModCache()
{

	$group_query = allowedTo('manage_membergroups') ? '1=1' : '0=1';

	if ($group_query === '0=1')
	{
		$request = wesql::query('
			SELECT id_group
			FROM {db_prefix}group_moderators
			WHERE id_member = {int:me}',
			array('me' => MID)
		);
		$groups = array();
		while ($row = wesql::fetch_assoc($request))
			$groups[] = $row['id_group'];
		wesql::free_result($request);

		if (!empty($groups))
			$group_query = 'id_group IN (' . implode(',', $groups) . ')';
	}


	$board_query = allowedTo('moderate_forum') ? '1=1' : '0=1';
	$boards_mod = array();

	if (we::$is_member)
	{
		if ($board_query === '0=1' && count($boards = boardsAllowedTo('moderate_board', true)) > 0)
			$board_query = 'id_board IN (' . implode(',', $boards) . ')';


		$request = wesql::query('
			SELECT id_board
			FROM {db_prefix}moderators
			WHERE id_member = {int:me}',
			array('me' => MID)
		);
		while ($row = wesql::fetch_assoc($request))
			$boards_mod[] = $row['id_board'];
		wesql::free_result($request);
	}

	$_SESSION['mc'] = array(
		'time' => time(),

		'id' => MID && we::$user['name'] ? MID : 0,
		'gq' => $group_query,
		'bq' => $board_query,
		'ap' => boardsAllowedTo('approve_posts'),
		'mb' => $boards_mod,
		'mq' => empty($boards_mod) ? '0=1' : 'b.id_board IN (' . implode(',', $boards_mod) . ')',
	);

	we::$user['mod_cache'] = $_SESSION['mc'];
}
