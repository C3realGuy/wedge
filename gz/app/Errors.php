<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');












function log_error($error_message, $error_type = 'general', $file = null, $line = null, $referrer = null)
{
	global $settings, $last_error, $context;
	static $plugin_dir = null;


	if (empty($settings['enableErrorLogging']))
		return $error_message;


	if ($plugin_dir === null)
		$plugin_dir = DIRECTORY_SEPARATOR === '/' ? ROOT_DIR . '/plugins' : str_replace(DIRECTORY_SEPARATOR, '/', ROOT_DIR . '/plugins');


	$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
	$error_message = strtr($error_message, array('&lt;br&gt;' => '<br>', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br>'));
	$error_message = preg_replace('~&lt;a href=&quot;(.*?)&quot;&gt;(.*?)&lt;/a&gt;~', '~<a href="$1">$2</a>~', $error_message);



	if ($file == null)
	{

		$array = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		for ($i = 0, $c = count($array); $i < $c; $i++)
			if (!empty($array[$i]['function']) && in_array($array[$i]['function'], array('fatal_error', 'fatal_lang_error')))
			{
				$found_filename = $array[$i]['file'];
				break;
			}


		if (!isset($found_filename))
			for ($i = 0; $i < $c; $i++)
			{
				if (isset($array[$i]['function']) && $array[$i]['function'] == 'log_error')
				{
					$found_filename = $array[$i]['file'];
					break;
				}
			}

		if (isset($found_filename))
			$file = str_replace('\\', '/', $found_filename);
	}
	else

		$file = str_replace('\\', '/', $file);

	$line = ($line == null) ? 0 : (int) $line;


	if (empty(we::$id))
		we::$id = 0;
	if (empty(we::$user['ip']))
		we::$user['ip'] = '';
	if (empty(we::$user['url']))
	{
		$is_secure = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
		we::$user['url'] = ($is_secure ? 'https://' : 'http://') . (empty($_SERVER['REAL_HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['REAL_HTTP_HOST']) . $_SERVER['REQUEST_URI'];
	}


	$query_string = we::$user['url'];


	if (!empty($referrer))
		$query_string = $referrer;


	$is_short = strpos($query_string, SCRIPT . '?');
	$has_protocol = strpos($query_string, '://') > 0;


	if (($is_short === false && $has_protocol) && isset($_POST['board']) && !isset($_GET['board']))
		$query_string .= ($query_string == '' ? 'board=' : ';board=') . $_POST['board'];

	if ($is_short === 0)
		$query_string = substr($query_string, strlen(SCRIPT));
	if ($is_short === false && !$has_protocol)
		$is_short = 0;
	if ($is_short === 0 && !empty($query_string) && $query_string[0] === '?')
		$is_short = false;


	$sn = session_name();
	$query_string = preg_replace('~(?:\?' . $sn . '=[^&;]*$|\b' . $sn . '=[^&;]*[&;])~', '', $query_string);
	$query_string = htmlspecialchars(($is_short === false ? '' : '?') . $query_string);


	$known_error_types = array(
		'general',
		'critical',
		'database',
		'undefined_vars',
		'mail',
		'password',
		'user',
		'template',
		'debug',
		'filenotfound',
	);


	$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';


	if (!empty($plugin_dir) && strpos($file, $plugin_dir) === 0)
		foreach ($context['plugins_dir'] as $plugin_id => $plugin_path)
		{
			if (strpos($file, $plugin_path) === 0)
			{
				$error_type = $plugin_id;
				break;
			}
		}


	if (strpos($error_message, '2: mail()') === 0)
		$error_type = 'mail';


	if (isset($found_filename))
		$file = '';

	if (!empty(we::$user['possibly_robot']))
		$query_string = 'b:' . $query_string;


	$error_info = array(we::$id, time(), get_ip_identifier(we::$user['ip']), $query_string, $error_message, $error_type, $file, $line);
	if (empty($last_error) || $last_error != $error_info)
	{

		wesql::insert('',
			'{db_prefix}log_errors',
			array('id_member' => 'int', 'log_time' => 'int', 'ip' => 'int', 'url' => 'string-65534', 'message' => 'string-65534', 'error_type' => 'string-255', 'file' => 'string-255', 'line' => 'int'),
			$error_info
		);
		$last_error = $error_info;

		$context['app_error_count']++;
	}


	return $error_message;
}











function fatal_error($error, $log = 'general', $header = 403)
{
	global $txt, $settings;

	issue_http_header($header);


	if (empty($txt))
		exit($error);

	updateOnlineWithError($error, false);
	setup_fatal_error_context($log || (!empty($settings['enableErrorLogging']) && $settings['enableErrorLogging'] == 2) ? log_error($error, $log) : $error);
}


















function fatal_lang_error($error, $log = 'general', $sprintf = array(), $header = 403)
{
	global $txt, $settings, $context;
	static $fatal_error_called = false;

	if (!AJAX)
		issue_http_header($header);


	if (empty($context['theme_loaded']) && empty($fatal_error_called))
	{
		$fatal_error_called = true;
		loadTheme();
	}


	if (empty($context['theme_loaded']) && !isset($txt[$error]))
		exit($error);

	$reload_lang_file = true;

	if ($log || (!empty($settings['enableErrorLogging']) && $settings['enableErrorLogging'] == 2))
	{
		loadLanguage('Errors', $settings['language']);
		$reload_lang_file = !empty(we::$user['language']) && $settings['language'] != we::$user['language'];
		$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
		log_error($error_message, $log);
	}


	if ($reload_lang_file)
	{
		loadLanguage('Errors');
		$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
	}

	updateOnlineWithError($error, true, $sprintf);
	setup_fatal_error_context($error_message);
}












function error_handler($error_level, $error_string, $file, $line)
{
	global $settings, $db_show_debug;


	if (error_reporting() === 0)
		return;

	if (strpos($file, 'eval()') !== false && !empty($settings['current_include_filename']))
	{
		$array = debug_backtrace();
		for ($i = 0; $i < count($array); $i++)
		{
			if ($array[$i]['function'] !== 'execBlock')
				continue;


			if (empty($array[$i]['args']))
				$i++;
			break;
		}

		if (isset($array[$i]) && !empty($array[$i]['args']))
			$file = realpath($settings['current_include_filename']) . ' (' . $array[$i]['args'][0] . ' block - eval?)';
		else
			$file = realpath($settings['current_include_filename']) . ' (eval?)';
	}

	if (!empty($db_show_debug))
	{

		if ($error_level % 255 != E_ERROR)
		{
			$temporary = ob_get_contents();
			if (substr($temporary, -2) == '="')
				echo '"';

			if (strrpos($temporary, '>') < strrpos($temporary, '<'))
				echo '>';
		}


		echo '<br>
<strong>', $error_level % 255 == E_ERROR ? 'Error' : ($error_level % 255 == E_WARNING ? 'Warning' : 'Notice'), '</strong>: ', $error_string, ' in <strong>', basename($file), '</strong> on line <strong>', $line, '</strong><br>';
	}

	$error_type = strpos(strtolower($error_string), 'undefined') !== false ? 'undefined_vars' : 'general';

	$message = log_error(($error_level % 255 == E_ERROR ? 'Error' : ($error_level % 255 == E_WARNING ? 'Warning' : 'Notice')) . " (level $error_level): " . $error_string, $error_type, $file, $line);


	call_hook('output_error', array(&$message, $error_type, $error_level, $file, $line));


	if ($file == 'Unknown')
		return;


	if ($error_level % 255 == E_ERROR)
		obExit(false);
	else
		return;


	if ($error_level % 255 == E_ERROR || $error_level % 255 == E_WARNING)
		fatal_error(allowedTo('admin_forum') ? $message : $error_string, false);

	die('Hacking attempt...');
}
















function setup_fatal_error_context($error_message)
{
	global $context, $txt, $ssi_on_error_method;
	static $level = 0;


	if (++$level > 1)
		return false;


	if (WEDGE != 'SSI' && empty($context['theme_loaded']))
		loadTheme();


	$context['robot_no_index'] = true;

	if (!isset($context['error_title']))
		$context['error_title'] = $txt['error_occurred'];
	$context['error_message'] = isset($context['error_message']) ? $context['error_message'] : $error_message;

	if (empty($context['page_title']))
		$context['page_title'] = $context['error_title'];


	loadTemplate('Errors');
	wetem::load('fatal_error');


	if (WEDGE == 'SSI')
	{
		if (!empty($ssi_on_error_method) && $ssi_on_error_method !== true && is_callable($ssi_on_error_method))
			$ssi_on_error_method();
		elseif (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			execBlock('fatal_error');


		if (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			exit;
	}


	obExit(null, true, false, true);

	trigger_error('Hacking attempt...', E_USER_ERROR);
}








function show_db_error($loadavg = false)
{
	global $mbname, $maintenance, $mtitle, $mmessage, $settings;
	global $db_connection, $webmaster_email, $db_last_error, $db_error_send;


	while (ob_get_length())
		ob_end_clean();


	header('Expires: Wed, 25 Aug 2010 17:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache');


	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Status: 503 Service Temporarily Unavailable');
	header('Retry-After: 3600');

	if ($loadavg == false)
	{

		$settings['cache_enable'] = '1';
		$db_last_error = @filemtime(CACHE_DIR . '/error.lock');

		if ($db_last_error < time() - 3600 * 24 * 3 && empty($maintenance) && !empty($db_error_send))
		{

			@touch(CACHE_DIR . '/error.lock');


			$db_error = @wesql::error($db_connection);
			@mail($webmaster_email, $mbname . ': Wedge Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\nMySQL reported:\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that Wedge could not connect to the database, contact your host if this continues.');
		}
	}

	if (!empty($maintenance))
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="noindex">
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';

	elseif ($loadavg)
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>Temporarily Unavailable</title>
	</head>
	<body>
		<h3>Temporarily Unavailable</h3>
		Due to high stress on the server the forum is temporarily unavailable. Please try again later.
	</body>
</html>';

	else
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>Connection Problems</title>
	</head>
	<body>
		<h3>Connection Problems</h3>
		Sorry, Wedge was unable to connect to the database. This may be caused by the server being busy. Please try again later.
	</body>
</html>';

	exit;
}






function issue_http_header($header)
{


	$codes = array(
		200 => 'OK',
		400 => 'Bad Request',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
	);

	if (!isset($codes[$header]))
		$header = 403;


	if (!empty($_SERVER['SERVER_PROTOCOL']))
		header($_SERVER['SERVER_PROTOCOL'] . ' ' . $header . ' ' . $codes[$header]);
	header('Status: ' . $header . ' ' . $codes[$header]);
}










function updateOnlineWithError($error, $is_lang, $sprintf = array())
{
	global $settings;


	if (empty($settings['who_enabled']))
		return;

	$session_id = we::$is_guest ? 'ip' . we::$user['ip'] : session_id();


	$query = wesql::query('
		SELECT url
		FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => $session_id,
		)
	);
	if (wesql::num_rows($query) != 0)
	{
		list ($url) = wesql::fetch_row($query);
		$url = unserialize($url);

		if ($is_lang)
			$url += array(
				'who_error_lang' => $error,
				'who_error_params' => $sprintf,
			);
		else
			$url += array(
				'who_error_raw' => $error,
			);

		$url = serialize($url);
		wesql::query('
			UPDATE {db_prefix}log_online
			SET url = {string:url}
			WHERE session = {string:session}',
			array(
				'url' => $url,
				'session' => $session_id,
			)
		);
	}
	wesql::free_result($query);
}
