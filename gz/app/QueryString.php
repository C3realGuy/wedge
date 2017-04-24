<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');




function loadConstants()
{
	global $boardurl, $remove_index, $aliases;


	$ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	define('INFINITE', $ajax && !empty($_POST['infinite']));
	define('AJAX', $ajax && !INFINITE);



	$scripturl = $boardurl . (!empty($remove_index) && isset($_COOKIE[session_name()]) ? '/' : '/index.php');

	$is_secure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
	define('PROTOCOL', $is_secure ? 'https://' : 'http://');


	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = PROTOCOL;
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		if (WEDGE === 'SSI' && $detected_url !== $boardurl && substr($boardurl, 0, 6) !== substr($detected_url, 0, 6))
			$boardurl = preg_replace('~^https?://~', PROTOCOL, $boardurl);
		$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}


	if (isset($detected_url) && $detected_url != $boardurl)
	{


		if (!empty($aliases))
			foreach (explode(',', $aliases) as $alias)
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;


		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == $boardurl && (empty($_GET) || count($_GET) == 1) && WEDGE != 'SSI')
		{

			if (empty($_GET))
				redirectexit('wwwRedirect');
			elseif (key($_GET) != 'wwwRedirect')
				redirectexit('wwwRedirect;' . key($_GET) . '=' . current($_GET));
		}


		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;


		if (!empty($do_fix) || preg_match('~^https?://(?:[\d.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{

			$scripturl = strtr($scripturl, array($boardurl => $detected_url));
			if (!empty($_SERVER['REQUEST_URL']))
				$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($boardurl => $detected_url));
			$boardurl = $detected_url;
		}
	}


	define('SCRIPT',		$scripturl);
	define('ROOT',			$boardurl);
	define('TEMPLATES',		ROOT . '/core/html');			define('TEMPLATES_DIR',	ROOT_DIR . '/core/html');
	define('SKINS',			ROOT . '/core/skins');			define('SKINS_DIR',		ROOT_DIR . '/core/skins');
	define('LANGUAGES',		ROOT . '/core/languages');		define('LANGUAGES_DIR',	ROOT_DIR . '/core/languages');
	define('ASSETS',		ROOT . '/assets');				define('ASSETS_DIR',	ROOT_DIR . '/assets');
	define('CACHE',			ROOT . '/gz');					define('CACHE_DIR',		ROOT_DIR . '/gz');
	define('SMILEYS',		ROOT . '/assets/smileys');
	define('AVATARS',		ROOT . '/assets/avatars');


	define('SCRIPT_DIR',	ROOT_DIR);
	define('IMAGES',		ASSETS);
	define('IMAGES_DIR',	ASSETS_DIR);
}

















function cleanRequest()
{
	global $board, $topic, $settings, $context, $action_list;


	unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_FILES']);


	if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
		exit('Invalid request variable.');


	foreach (array_merge(array_keys($_POST), array_keys($_GET), array_keys($_FILES)) as $key)
		if (is_numeric($key))
			exit('Numeric request keys are invalid.');


	foreach ($_COOKIE as $key => $value)
		if (is_numeric($key))
			unset($_COOKIE[$key]);


	if (!isset($_SERVER['QUERY_STRING']))
		$_SERVER['QUERY_STRING'] = getenv('QUERY_STRING');


	if (strpos($_SERVER['QUERY_STRING'], 'http') === 0)
	{
		header('HTTP/1.1 400 Bad Request');
		exit;
	}

	define('INVALID_IP', '00000000000000000000000000000000');

	$supports_semicolon = strpos(ini_get('arg_separator.input'), ';') !== false;


	if (!$supports_semicolon && !empty($_SERVER['QUERY_STRING']))
	{

		$_GET = array();


		$_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], 0, 5) === 'url=/' ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING'];
		$_SERVER['QUERY_STRING'] = preg_replace('~%3b([a-z0-9]+)%3d([^%;]*)~i', ';$1=$2', $_SERVER['QUERY_STRING']);


		parse_str(preg_replace('~&(\w+)(?=&|$)~', '&$1=', strtr($_SERVER['QUERY_STRING'], array(';?' => '&', ';' => '&', '%00' => '', "\0" => ''))), $_GET);
	}
	elseif ($supports_semicolon)
	{

		foreach ($_GET as $k => $v)
		{
			if (is_string($v) && strpos($k, ';') !== false)
			{
				$temp = explode(';', $v);
				$_GET[$k] = $temp[0];

				for ($i = 1, $n = count($temp); $i < $n; $i++)
				{
					@list ($key, $val) = @explode('=', $temp[$i], 2);
					if (!isset($_GET[$key]))
						$_GET[$key] = $val;
				}
			}


			if ($k[0] === '?')
			{
				$_GET[substr($k, 1)] = $v;
				unset($_GET[$k]);
			}
		}
	}


	if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], basename(SCRIPT) . '/') !== false)
	{
		parse_str(substr(preg_replace('~&(\w+)(?=&|$)~', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], basename(SCRIPT)) + strlen(basename(SCRIPT)))), '/', '&')), 1), $temp);
		$_GET += $temp;
	}

	$full_board = array();
	$full_request = $_SERVER['HTTP_HOST'] . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');



	$do_pretty = !empty($settings['pretty_enable_filters']);
	if ($do_pretty)
		$qs = str_replace(substr(ROOT, strpos(ROOT, '://') + 3), '/', $full_request);

	$board = 0;
	if (isset($_GET['board']) && is_numeric($_GET['board']))
		$board = (int) $_GET['board'];
	elseif ($do_pretty)
	{
		$query = wesql::query('
			SELECT id_board, url
			FROM {db_prefix}boards AS b
			WHERE urllen >= {int:len}
			AND url = SUBSTRING({string:url}, 1, urllen)
			ORDER BY urllen DESC LIMIT 1',
			array(
				'url' => rtrim($full_request, '/'),
				'len' => ($len = strpos($full_request, '/')) !== false ? $len : strlen($full_request),
			)
		);

		if (wesql::num_rows($query) > 0)
		{
			$full_board = wesql::fetch_assoc($query);


			$_GET['board'] = $board = $full_board['id_board'];
			$_SERVER['REAL_REQUEST_URI'] = $_SERVER['REQUEST_URI'];
			$_SERVER['REAL_HTTP_HOST'] = $_SERVER['HTTP_HOST'];
			$_SERVER['HTTP_HOST'] = $full_board['url'];
			$_SERVER['REQUEST_URI'] = $ru = str_replace($full_board['url'], '', $full_request);
			$qs = str_replace(substr(ROOT, strpos(ROOT, '://') + 3), '/', $ru);



			if (isset($_GET['topic']))
			{

			}

			elseif (preg_match('~^/(2\d{3}(?:/\d{2}(?:/[0-3]\d)?)?)(?:/p(\d+))?~', $ru, $m))
			{
				$_GET['month'] = str_replace('/', '', $m[1]);
				$_GET['start'] = empty($m[2]) ? 0 : $m[2];
				$_GET['pretty'] = 1;
				$qs = str_replace($m[0], '', $qs);
			}

			elseif (preg_match('~^/(\d+)/(?:[^/]+)(?:/(\d+|msg\d+|from\d+|new)?)~u', $ru, $m))
			{
				$_GET['topic'] = $m[1];
				$_GET['start'] = empty($m[2]) ? 0 : $m[2];
				$_GET['pretty'] = 1;
				$qs = str_replace($m[0], '', $qs);
			}

			elseif (preg_match('~^/(cat|tag)/([^/]+)(?:/p(\d+))?~u', $ru, $m))
			{
				$_GET[$m[1]] = $m[2];
				$_GET['start'] = empty($m[3]) ? 0 : $m[3];
				$_GET['pretty'] = 1;
				$qs = str_replace($m[0], '', $qs);
			}

			elseif (preg_match('~^/p(\d+)~', $ru, $m))
			{
				$_GET['start'] = empty($m[1]) ? 0 : $m[1];
				$_GET['pretty'] = 1;
				$qs = str_replace($m[0], '', $qs);
			}
		}
		else
			unset($_GET['board']);
		wesql::free_result($query);
	}

	if (isset($_POST['admin_pass'], $_POST['user']))
		unset($_POST['user']);

	if ($do_pretty)
	{

		if (preg_match('`/' . (isset($settings['pretty_prefix_profile']) ? $settings['pretty_prefix_profile'] : 'profile/') . '([^/?]*)`', $qs, $m))
		{
			if (empty($m[1]) && empty($_GET['u']))
				$_GET['u'] = 0;
			elseif (empty($_GET['u']))
				$_GET['user'] = urldecode($m[1]);
			$_GET['action'] = 'profile';
		}

		elseif (preg_match('~/category/(\d+)~', $full_request, $m) && (int) $m[1] > 0)
			$_GET['category'] = (int) $m[1];


		if (preg_match('~/*' . (isset($settings['pretty_prefix_action']) ? $settings['pretty_prefix_action'] : 'do/') . '([a-zA-Z0-9]+)~', $qs, $m) && isset($action_list[$m[1]]))
			$_GET['action'] = $m[1];
	}


	call_hook('determine_location', array(&$full_request, &$full_board));



	if (strpos($full_request, '?') === false && in_array(strtolower(strrchr($full_request, '.')), array('.gif', '.jpg', '.jpeg', '.png', '.css', '.js', '.gz', '.cgz', '.jgz')))
	{
		$is_cache_file = in_array(strtolower(strrchr($full_request, '.')), array('.css', '.js', '.gz', '.cgz', '.jgz'));
		if ($is_cache_file)
		{
			$regex = '~/gz(/.+?-)[0-9]+\.(js|js\.gz|css|css\.gz|cgz|jgz)$~';
			if (preg_match($regex, $full_request, $filename))
			{

				$matches = glob(ROOT_DIR . '/' . $filename[1] . '*.' . $filename[2]);
				if (!empty($matches) && preg_match($regex, (string) reset($matches), $new_filename))
				{
					header('HTTP/1.1 301 Moved Permanently');
					header('Location: http://' . str_replace($filename[0], $new_filename[0], $full_request));
					exit;
				}
			}
		}

		loadLanguage('Errors');

		header('HTTP/1.0 404 Not Found');
		header('Content-Type: text/plain; charset=UTF-8');






		if (!empty($settings['enableErrorLogging']) && !empty($settings['enableError404Logging'])
		&& !$is_cache_file
		&& strpos($full_request, '/avatar_') === false
		&& strpos($full_request, '/gz/css/') === false
		&& strpos($full_request, '/gz/js/') === false
		&& strpos($full_request, '/Themes/') === false
		&& strpos($full_request, '/mobiquo/tapatalk') === false
		&& strpos($full_request, '/apple-touch-icon') === false
		&& strpos($full_request, '/favicon.') === false
		&& (!isset($_SERVER['HTTP_REFERER']) || (strpos($_SERVER['HTTP_REFERER'], 'googleusercontent.com') === false)))
		{
			log_error('File not found: ' . $full_request, 'filenotfound', null, null, isset($_SERVER['HTTP_REFERER']) ? str_replace('&amp;', '&', $_SERVER['HTTP_REFERER']) : '');
			loadSource('ManageErrors');
			updateErrorCount();
		}
		exit('404 Not Found');
	}


	$_GET = htmlspecialchars__recursive($_GET);


	$_REQUEST = $_POST + $_GET;


	if (isset($_REQUEST['board']))
	{

		$_REQUEST['board'] = (string) $_REQUEST['board'];


		if (strpos($_REQUEST['board'], '/') !== false && strpos($_REQUEST['board'], $_SERVER['HTTP_HOST']) === false)
			list ($_REQUEST['board'], $_REQUEST['start']) = explode('/', $_REQUEST['board']);

		elseif (strpos($_REQUEST['board'], '.') !== false)
		{
			list ($reqboard, $reqstart) = explode('.', $_REQUEST['board']);
			if (is_numeric($reqboard) && is_numeric($reqstart))
			{
				$_REQUEST['board'] = $reqboard;
				$_REQUEST['start'] = $reqstart;
			}
		}


		if (is_numeric($_REQUEST['board']))
		{
			$board = (int) $_REQUEST['board'];
			if (!isset($_REQUEST['pretty']))
				$context['pretty']['oldschoolquery'] = true;
		}
		else
			$board = 0;

		if (empty($_REQUEST['topic']))
			$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;


		$_GET['board'] = $board;
	}

	else
		$board = 0;


	if (isset($_REQUEST['topic']))
	{

		$_REQUEST['topic'] = (string) $_REQUEST['topic'];


		if (strpos($_REQUEST['topic'], '/') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('/', $_REQUEST['topic']);

		elseif (strpos($_REQUEST['topic'], '.') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('.', $_REQUEST['topic']);


		if (is_numeric($_REQUEST['topic']))
		{
			$topic = (int) $_REQUEST['topic'];
			if (!isset($_REQUEST['pretty']))
				$context['pretty']['oldschoolquery'] = true;
		}
		else
		{
			loadSource('Subs-PrettyUrls');
			$_REQUEST['topic'] = str_replace(array('&#039;', '&#39;', '\\'), array("\x12", "\x12", ''), $_REQUEST['topic']);
			$_REQUEST['topic'] = preg_replace_callback('~([\x80-\xff])~', 'entity_percents', $_REQUEST['topic']);

			$query = wesql::query('
				SELECT p.id_topic, t.id_board
				FROM {db_prefix}pretty_topic_urls AS p
				INNER JOIN {db_prefix}topics AS t ON p.id_topic = t.id_topic
				INNER JOIN {db_prefix}boards AS b ON b.id_board = t.id_board
				WHERE p.pretty_url = {string:pretty}
				AND b.url = {string:url}
				LIMIT 1', array(
					'pretty' => $_REQUEST['topic'],
					'url' => $_SERVER['HTTP_HOST']
				));

			if (wesql::num_rows($query) == 0)
				$topic = 0;
			else
				list ($topic, $board) = wesql::fetch_row($query);
			wesql::free_result($query);


			$context['pretty']['db_count']++;
		}


		$_GET['topic'] = $topic;
	}
	else
		$topic = 0;

	unset($_REQUEST['pretty'], $_GET['pretty']);


	if (empty($_REQUEST['start']) || $_REQUEST['start'] < 0 || (int) $_REQUEST['start'] > 2147473647)
		$_REQUEST['start'] = 0;


	if (isset($_REQUEST['action']))
		$_REQUEST['action'] = (string) $_REQUEST['action'];
	if (isset($_GET['action']))
		$_GET['action'] = (string) $_GET['action'];


	if (!isset($_SERVER['REMOTE_ADDR']))
	{
		$_SERVER['REMOTE_ADDR'] = '';

		$_SERVER['is_cli'] = true;
	}


	if (!empty($settings['reverse_proxy']))
	{

		if (!empty($settings['reverse_proxy_header']) && $settings['reverse_proxy_header'] != 'X-Forwarded-For')
		{
			$header = 'HTTP_' . strtoupper(str_replace('-', '_', $settings['reverse_proxy_header']));
			if (!empty($_SERVER[$header]))
				$_SERVER['HTTP_X_FORWARDED_FOR'] = $_SERVER[$header];
		}
		$context['additional_headers']['x-detected-remote-address'] = $_SERVER['REMOTE_ADDR'];
		if (!empty($settings['reverse_proxy_ips']))
			$reverse_proxies = explode("\n", $settings['reverse_proxy_ips']);
	}


	$_SERVER['REMOTE_ADDR'] = expand_ip($_SERVER['REMOTE_ADDR']);


	$_SERVER['BAN_CHECK_IP'] = $_SERVER['REMOTE_ADDR'];


	$internal_subnet = match_internal_subnets($_SERVER['REMOTE_ADDR']);
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		$_SERVER['HTTP_X_FORWARDED_FOR_ORIGINAL'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
		$_SERVER['HTTP_X_FORWARDED_FOR'] = expand_ip($_SERVER['HTTP_X_FORWARDED_FOR']);
	}
	if (!empty($_SERVER['HTTP_CLIENT_IP']))
		$_SERVER['HTTP_CLIENT_IP'] = expand_ip($_SERVER['HTTP_CLIENT_IP']);

	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_CLIENT_IP']))
	{


		if (!match_internal_subnets($_SERVER['HTTP_CLIENT_IP']) || $internal_subnet)
		{


			if (is_ipv4($_SERVER['HTTP_X_FORWARDED_FOR']) && is_ipv4($_SERVER['HTTP_CLIENT_IP']))
			{
				$xff_octet = substr($_SERVER['HTTP_X_FORWARDED_FOR'], 24, 2);
				if ($xff_octet !== substr($_SERVER['HTTP_CLIENT_IP'], 24, 2) && $xff_octet === substr($_SERVER['HTTP_CLIENT_IP'], -2))
					$_SERVER['HTTP_CLIENT_IP'] = '00000000000000000000ffff' . implode('', array_reverse(str_split(substr($_SERVER['HTTP_CLIENT_IP'], -8), 2)));
			}
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_CLIENT_IP'];
		}
	}
	if (!empty($_SERVER['HTTP_CLIENT_IP']) && (!match_internal_subnets($_SERVER['HTTP_CLIENT_IP']) || $internal_subnet))
	{

		if (is_ipv4($_SERVER['HTTP_CLIENT_IP']) && is_ipv4($_SERVER['REMOTE_ADDR']))
		{
			if (substr($_SERVER['REMOTE_ADDR'], 24, 2) !== substr($_SERVER['HTTP_CLIENT_IP'], 24, 2))
				$_SERVER['HTTP_CLIENT_IP'] = '00000000000000000000ffff' . implode('', array_reverse(str_split(substr($_SERVER['HTTP_CLIENT_IP'], -8), 2)));
		}
		$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_CLIENT_IP'];
	}
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{

		if (strpos($_SERVER['HTTP_X_FORWARDED_FOR_ORIGINAL'], ',') !== false)
		{
			$ips = array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR_ORIGINAL']));


			foreach ($ips as $i => $ip)
			{
				$ip = expand_ip(trim($ip));

				if (match_internal_subnets($ip) && !$internal_subnet)
					continue;


				if (isset($reverse_proxies) && match_cidr($ip, $reverse_proxies))
					continue;


				$_SERVER['BAN_CHECK_IP'] = $ip;
				break;
			}
		}

		elseif (!match_internal_subnets($_SERVER['HTTP_X_FORWARDED_FOR']) || $internal_subnet)
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}


	if (empty($_SERVER['REQUEST_URI']))
		$_SERVER['REQUEST_URL'] = SCRIPT . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
	else
		$_SERVER['REQUEST_URL'] = PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];


	$_SERVER['HTTP_USER_AGENT'] = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars(wesql::unescape_string($_SERVER['HTTP_USER_AGENT']), ENT_QUOTES) : '';









}









function escapestring__recursive($var)
{
	if (!is_array($var))
		return wesql::escape_string($var);


	$new_var = array();


	foreach ($var as $k => $v)
		$new_var[wesql::escape_string($k)] = escapestring__recursive($v);

	return $new_var;
}












function htmlspecialchars__recursive($var, $level = 0)
{
	if (!is_array($var))
		return is_callable('westr::htmlspecialchars') ? westr::htmlspecialchars($var, ENT_QUOTES) : htmlspecialchars($var, ENT_QUOTES);


	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmlspecialchars__recursive($v, $level + 1);

	return $var;
}










function urldecode__recursive($var, $level = 0)
{
	if (!is_array($var))
		return urldecode($var);


	$new_var = array();


	foreach ($var as $k => $v)
		$new_var[urldecode($k)] = $level > 25 ? null : urldecode__recursive($v, $level + 1);

	return $new_var;
}









function unescapestring__recursive($var)
{
	if (!is_array($var))
		return wesql::unescape_string($var);


	$new_var = array();


	foreach ($var as $k => $v)
		$new_var[wesql::unescape_string($k)] = unescapestring__recursive($v);

	return $new_var;
}










function stripslashes__recursive($var, $level = 0)
{
	if (!is_array($var))
		return stripslashes($var);


	$new_var = array();


	foreach ($var as $k => $v)
		$new_var[stripslashes($k)] = $level > 25 ? null : stripslashes__recursive($v, $level + 1);

	return $new_var;
}













function htmltrim__recursive($var, $level = 0)
{
	if (!is_array($var))
		return is_callable('westr::htmltrim') ? westr::htmltrim($var) : trim($var, ' ' . "\t\n\r\x0B" . '\0' . "\xA0");


	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmltrim__recursive($v, $level + 1);

	return $var;
}









function get_http_headers()
{
	if (is_callable('apache_request_headers'))
		return array_change_key_case(apache_request_headers());

	$headers = array();
	foreach ($_SERVER as $key => $value)
		if (strpos($key, 'HTTP_') === 0)
			$headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;

	if (!empty($_SERVER['REAL_HTTP_HOST']) && $_SERVER['REAL_HTTP_HOST'] != $headers['host'])
		$headers['host'] = $_SERVER['REAL_HTTP_HOST'];

	return $headers;
}








function match_internal_subnets($ip)
{

	if (strpos($ip, '000000000000000000000000000000') === 0)
		return true;


	if (is_ipv4($ip))
	{
		$first = substr($ip, 24, 2);

		if ($first === '7f' || $first === 'ff' || $first === '0a' || $first === '00' || ($first === 'c0' && substr($ip, 26, 2) === 'a8'))
			return true;

		if ($first === 'ac')
		{
			$second = hexdec(substr($ip, 26, 2));
			if ($second >= 16 && $second <= 31)
				return true;
		}
	}

	return false;
}







function expand_ip($ip)
{
	static $ip_array = array();
	if (isset($ip_array[$ip]))
		return $ip_array[$ip];


	$contains_v4 = strpos($ip, '.') !== false;
	$contains_v6 = strpos($ip, ':') !== false;

	if ($contains_v4)
	{

		if ($contains_v6)
		{

			if (strpos($ip, '::ffff:') !== 0)
				return INVALID_IP;
			$ip = substr($ip, 7);
		}

		if (!preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $ip))
			return INVALID_IP;


		$ipv6 = '00000000000000000000ffff';
		$ipv4 = explode('.', $ip);
		foreach ($ipv4 as $octet)
			$ipv6 .= str_pad(dechex($octet), 2, '0', STR_PAD_LEFT);
		return $ip_array[$ip] = $ipv6;
	}
	elseif ($contains_v6)
	{
		if (strpos($ip, '::') !== false)
		{
			$pieces = explode('::', $ip);
			if (count($pieces) !== 2)
				return INVALID_IP;


			$before_pieces = explode(':', $pieces[0]);
			$after_pieces = explode(':', $pieces[1]);
			foreach ($before_pieces as $k => $v)
				if ($v == '')
					unset($before_pieces[$k]);
			foreach ($after_pieces as $k => $v)
				if ($v == '')
					unset($after_pieces[$k]);

			$ip = preg_replace('~((?<!\:):$)~', '', $pieces[0] . (count($before_pieces) ? ':' : '') . str_repeat('0:', 8 - (count($before_pieces) + count($after_pieces))) . $pieces[1]);
		}

		$ipv6 = explode(':', $ip);
		foreach ($ipv6 as $k => $v)
			$ipv6[$k] = str_pad($v, 4, '0', STR_PAD_LEFT);
		return $ip_array[$ip] = implode('', $ipv6);
	}


	return INVALID_IP;
}







function format_ip($ip)
{
	static $ip_array = array();

	$ip = strtolower($ip);

	if (strlen($ip) != 32 || !preg_match('~[0-9a-f]{32}~', $ip))
		return '';

	if (isset($ip_array[$ip]))
		return $ip_array[$ip];


	if (is_ipv4($ip))
	{

		$ipv4 = array();
		for ($i = 0; $i <= 3; $i++)
			$ipv4[] = hexdec(substr($ip, 24 + $i * 2, 2));
		return $ip_array[$ip] = implode('.', $ipv4);
	}
	else
	{

		$ipv6 = str_split($ip, 4);
		foreach ($ipv6 as $k => $v)
			$ipv6[$k] = $v === '0000' ? '0' : ltrim($v);
		$ipv6 = implode(':', $ipv6);

		$ipv6 = preg_replace('~(\:0)+\:~', '::', ':' . $ipv6 . ':', 1);

		$ipv6 = preg_replace('~(^\:(?!\:))|((?<!\:):$)~', '', $ipv6);
		return $ip_array[$ip] = $ipv6;
	}
}









function is_ipv4($ip)
{
	return strpos($ip, '00000000000000000000ffff') === 0;
}







function get_ip_identifier($ip)
{
	static $ip_array = array();

	$ip = strtolower($ip);

	if (strlen($ip) != 32 || !preg_match('~[0-9a-f]{32}~', $ip) || $ip == INVALID_IP)
		return 0;

	if (isset($ip_array[$ip]))
		return $ip_array[$ip];

	$query = wesql::query('
		SELECT id_ip
		FROM {db_prefix}log_ips
		WHERE member_ip = {string:ip}',
		array(
			'ip' => $ip,
		)
	);
	if ($row = wesql::fetch_row($query))
	{
		wesql::free_result($query);
		return $ip_array[$ip] = $row[0];
	}


	wesql::free_result($query);
	wesql::insert('ignore',
		'{db_prefix}log_ips',
		array(
			'member_ip' => 'string',
		),
		array(
			$ip,
		)
	);
	return wesql::insert_id();
}
