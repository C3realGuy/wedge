<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');

loadSource(array(
	'Subs-BBC',
	'Subs-Cache',
	'Subs-Template',
	'Class-Skeleton',
));

















function updateStats($type, $parameter1 = null, $parameter2 = null)
{
	global $settings;

	if ($type === 'member')
	{
		$changes = array(
			'memberlist_updated' => time(),
		);


		if (is_numeric($parameter1))
		{
			$changes['latestMember'] = $parameter1;
			$changes['latestRealName'] = $parameter2;

			updateSettings(array('totalMembers' => true), true);
		}


		else
		{

			$result = wesql::query('
				SELECT COUNT(*), MAX(id_member)
				FROM {db_prefix}members
				WHERE is_activated = {int:is_activated}',
				array(
					'is_activated' => 1,
				)
			);
			list ($changes['totalMembers'], $changes['latestMember']) = wesql::fetch_row($result);
			wesql::free_result($result);


			$result = wesql::query('
				SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => (int) $changes['latestMember'],
				)
			);
			list ($changes['latestRealName']) = wesql::fetch_row($result);
			wesql::free_result($result);


			if ((!empty($settings['registration_method']) && $settings['registration_method'] == 2) || !empty($settings['approveAccountDeletion']))
			{

				$result = wesql::query('
					SELECT COUNT(*)
					FROM {db_prefix}members
					WHERE is_activated IN ({array_int:activation_status})',
					array(
						'activation_status' => array(3, 4),
					)
				);
				list ($changes['unapprovedMembers']) = wesql::fetch_row($result);
				wesql::free_result($result);
			}
		}

		updateSettings($changes);
	}
	elseif ($type === 'message')
	{
		if ($parameter1 === true && $parameter2 !== null)
			updateSettings(array('totalMessages' => true, 'maxMsgID' => $parameter2), true);
		else
		{

			$result = wesql::query('
				SELECT SUM(num_posts + unapproved_posts) AS total_messages, MAX(id_last_msg) AS max_msg_id
				FROM {db_prefix}boards
				WHERE redirect = {string:blank_redirect}' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
					AND id_board != {int:recycle_board}' : ''),
				array(
					'recycle_board' => isset($settings['recycle_board']) ? $settings['recycle_board'] : 0,
					'blank_redirect' => '',
				)
			);
			$row = wesql::fetch_assoc($result);
			wesql::free_result($result);

			updateSettings(array(
				'totalMessages' => $row['total_messages'] === null ? 0 : $row['total_messages'],
				'maxMsgID' => $row['max_msg_id'] === null ? 0 : $row['max_msg_id']
			));
		}
	}
	elseif ($type === 'subject')
	{

		wesql::query('
			DELETE FROM {db_prefix}log_search_subjects
			WHERE id_topic = {int:id_topic}',
			array(
				'id_topic' => $parameter1,
			)
		);
		wesql::query('
			DELETE FROM {db_prefix}pretty_topic_urls
			WHERE id_topic = {int:id_topic}',
			array(
				'id_topic' => $parameter1,
			)
		);
		if (!empty($settings['pretty_enable_cache']) && is_numeric($parameter1) && $parameter1 > 0)
			wesql::query('
				DELETE FROM {db_prefix}pretty_urls_cache
				WHERE url_id LIKE {string:topic_search}',
				array(
					'topic_search' => '%topic=' . $parameter1 . '%',
				)
			);


		if ($parameter2 !== null)
		{
			loadSource('Subs-PrettyUrls');
			pretty_update_topic($parameter2, $parameter1);

			$parameter1 = (int) $parameter1;
			$parameter2 = text2words($parameter2);

			$inserts = array();
			foreach ($parameter2 as $word)
				$inserts[] = array($word, $parameter1);

			if (!empty($inserts))
				wesql::insert('ignore',
					'{db_prefix}log_search_subjects',
					array('word' => 'string', 'id_topic' => 'int'),
					$inserts
				);
		}
	}
	elseif ($type === 'topic')
	{
		if ($parameter1 === true)
			updateSettings(array('totalTopics' => true), true);
		else
		{


			$result = wesql::query('
				SELECT SUM(num_topics + unapproved_topics) AS total_topics
				FROM {db_prefix}boards' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
				WHERE id_board != {int:recycle_board}' : ''),
				array(
					'recycle_board' => !empty($settings['recycle_board']) ? $settings['recycle_board'] : 0,
				)
			);
			$row = wesql::fetch_assoc($result);
			wesql::free_result($result);

			updateSettings(array('totalTopics' => $row['total_topics'] === null ? 0 : $row['total_topics']));
		}
	}
	elseif ($type === 'postgroups')
	{

		if ($parameter2 !== null && !in_array('posts', $parameter2))
			return;

		if (($postgroups = cache_get_data('updateStats:postgroups', 360)) === null)
		{

			$request = wesql::query('
				SELECT id_group, min_posts
				FROM {db_prefix}membergroups
				WHERE min_posts != {int:min_posts}',
				array(
					'min_posts' => -1,
				)
			);
			$postgroups = array();
			while ($row = wesql::fetch_assoc($request))
				$postgroups[$row['id_group']] = $row['min_posts'];
			wesql::free_result($request);


			arsort($postgroups);

			cache_put_data('updateStats:postgroups', $postgroups, 360);
		}


		if (empty($postgroups))
			return;


		$conditions = '';
		foreach ($postgroups as $id => $min_posts)
		{
			$conditions .= '
					WHEN posts >= ' . $min_posts . (!empty($lastMin) ? ' AND posts <= ' . $lastMin : '') . ' THEN ' . $id;
			$lastMin = $min_posts;
		}


		wesql::query('
			UPDATE {db_prefix}members
			SET id_post_group = CASE ' . $conditions . '
					ELSE 0
				END' . ($parameter1 != null ? '
			WHERE ' . (is_array($parameter1) ? 'id_member IN ({array_int:members})' : 'id_member = {int:members}') : ''),
			array(
				'members' => $parameter1,
			)
		);


		if (wesql::affected_rows() > 0)
			cache_put_data('member-colors', null, 5000);
	}
	else
		trigger_error('updateStats(): Invalid statistic type \'' . $type . '\'', E_USER_NOTICE);
}









function updateMyData($data)
{
	if (empty($data) || !is_array($data))
		return;

	foreach ($data as $key => $val)
	{
		we::$user['data'][$key] = $val;
		if ($val === '')
			unset(we::$user['data'][$key]);
	}


	updateMemberData(
		MID,
		array(
			'data' => serialize(we::$user['data'])
		)
	);
}














function updateMemberData($members, $data)
{
	global $settings;

	$parameters = array();
	if (is_array($members))
	{
		$condition = 'id_member IN ({array_int:members})';
		$parameters['members'] = $members;
	}
	elseif ($members === null)
		$condition = '1=1';
	else
	{
		$condition = 'id_member = {int:member}';
		$parameters['member'] = $members;
	}

	if (!empty($settings['hooks']['change_member_data']))
	{

		$hook_vars = array(
			'member_name',
			'real_name',
			'email_address',
			'id_group',
			'gender',
			'birthdate',
			'website_title',
			'website_url',
			'location',
			'hide_email',
			'time_format',
			'time_offset',
			'avatar',
			'lngfile',
		);
		$vars_to_integrate = array_intersect($hook_vars, array_keys($data));


		if (count($vars_to_integrate) != 0)
		{

			if ((array) $members === (array) MID)
				$member_names = array(we::$user['username']);
			else
			{
				$member_names = array();
				$request = wesql::query('
					SELECT member_name
					FROM {db_prefix}members
					WHERE ' . $condition,
					$parameters
				);
				while ($row = wesql::fetch_assoc($request))
					$member_names[] = $row['member_name'];
				wesql::free_result($request);
			}

			if (!empty($member_names))
				foreach ($vars_to_integrate as $var)
					call_hook('change_member_data', array($member_names, $var, &$data[$var]));
		}
	}


	$knownInts = array(
		'date_registered', 'posts', 'id_group', 'last_login', 'instant_messages', 'unread_messages',
		'pm_prefs', 'gender', 'hide_email', 'show_online', 'pm_email_notify', 'pm_receive_from',
		'notify_announcements', 'notify_send_body', 'notify_regularity', 'notify_types', 'hey_not', 'hey_pm',
		'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning',
	);
	$knownFloats = array(
		'time_offset',
	);

	$setString = '';
	foreach ($data as $var => $val)
	{
		$type = 'string';
		if (in_array($var, $knownInts))
			$type = 'int';
		elseif (in_array($var, $knownFloats))
			$type = 'float';
		elseif ($var == 'birthdate')
			$type = 'date';


		if ($type == 'int' && ($val === '+' || $val === '-'))
		{
			$val = $var . ' ' . $val . ' 1';
			$type = 'raw';
		}


		if (in_array($var, array('posts', 'instant_messages', 'unread_messages')) && preg_match('~^' . $var . ' (\+ |- |\+ -)([\d]+)~', $val, $match))
		{
			if ($match[1] != '+ ')
				$val = 'CASE WHEN ' . $var . ' <= ' . abs($match[2]) . ' THEN 0 ELSE ' . $val . ' END';
			$type = 'raw';
		}

		$setString .= ' ' . $var . ' = {' . $type . ':p_' . $var . '},';
		$parameters['p_' . $var] = $val;
	}

	wesql::query('
		UPDATE {db_prefix}members
		SET' . substr($setString, 0, -1) . '
		WHERE ' . $condition,
		$parameters
	);

	updateStats('postgroups', $members, array_keys($data));


	if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2 && !empty($members))
	{
		if (!is_array($members))
			$members = array($members);

		foreach ($members as $member)
		{
			if ($settings['cache_enable'] >= 3)
			{
				cache_put_data('member_data-profile-' . $member, null, 120);
				cache_put_data('member_data-normal-' . $member, null, 120);
				cache_put_data('member_data-minimal-' . $member, null, 120);
			}
			cache_put_data('user_settings-' . $member, null, 60);
		}
	}
}









function updateSettings($changeArray, $update = false)
{
	global $settings;

	if (empty($changeArray) || !is_array($changeArray))
		return;

	if (defined('WEDGE_INSTALLER'))
	{
		global $incontext;
		if (empty($incontext['enable_update_settings']))
			return;
	}


	if ($update)
	{
		foreach ($changeArray as $variable => $value)
		{
			wesql::query('
				UPDATE {db_prefix}settings
				SET value = {' . ($value === false || $value === true ? 'raw' : 'string') . ':value}
				WHERE variable = {string:variable}',
				array(
					'value' => $value === true ? 'value + 1' : ($value === false ? 'value - 1' : $value),
					'variable' => $variable,
				)
			);
			$settings[$variable] = $value === true ? $settings[$variable] + 1 : ($value === false ? $settings[$variable] - 1 : $value);
		}


		cache_put_data('settings', null, 'forever');

		return;
	}

	$replaceArray = array();
	foreach ($changeArray as $variable => $value)
	{

		if (isset($settings[$variable]) && $settings[$variable] == $value)
			continue;


		elseif (!isset($settings[$variable]) && empty($value))
			continue;

		$replaceArray[] = array($variable, $value);

		$settings[$variable] = $value;
	}

	if (empty($replaceArray))
		return;

	wesql::insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-65534'),
		$replaceArray
	);


	cache_put_data('settings', null, 'forever');
}










function array_insert($input, $to, $array, $after = false)
{
	$to = array_map('trim', explode(' ', $to));
	$offset = array_search($to[0], array_keys($input), true) + ($after && empty($to[1]) ? 1 : 0);
	if (empty($to[1]))
		return array_merge(array_slice($input, 0, $offset, true), $array, array_slice($input, $offset, null, true));
	return array_merge(array_slice($input, 0, $offset, true), array($to[0] => array_insert($input[array_shift($to)], implode(' ', $to), $array, $after)), array_slice($input, $offset + 1, null, true));
}










function cleanXml($string)
{
	return str_replace(']]>', ']]]]><![CDATA[>', preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19\x{FFFE}\x{FFFF}]~u', '', $string));
}





function get_single_post($id_msg)
{
	$req = wesql::query('
		SELECT
			id_msg, poster_time, id_member, body, smileys_enabled, poster_name, m.approved, m.data
		FROM {db_prefix}messages AS m
		INNER JOIN {db_prefix}topics AS t ON t.id_topic = m.id_topic AND {query_see_topic}
		WHERE id_msg = {int:id_msg}',
		array('id_msg' => $id_msg)
	);
	$row = wesql::fetch_assoc($req);
	wesql::free_result($req);

	if (empty($row['id_msg']))
		return false;
	return parse_bbc($row['body'], 'post', array('smileys' => $row['smileys_enabled'], 'cache' => $row['id_msg'], 'user' => $row['id_member']));
}





function return_raw()
{
	header('Content-Type: text/plain; charset=UTF-8');
	$args = func_get_args();
	exit(implode('', $args));
}


function return_callback($callback, $args = array())
{
	clean_output();
	header('Content-Type: text/plain; charset=UTF-8');
	echo call_user_func_array($callback, $args);
	exit();
}

function return_text()
{
	clean_output();
	header('Content-Type: text/plain; charset=UTF-8');
	$args = func_get_args();
	exit(implode('', $args));
}

function return_xml()
{
	clean_output();
	header('Content-Type: text/xml; charset=UTF-8');
	$args = func_get_args();
	exit('<?xml version="1.0" encoding="UTF-8"?' . '>' . implode('', $args));
}

function return_json($json)
{
	clean_output();
	header('Content-Type: application/json; charset=UTF-8');
	exit(str_replace('\\/', '/', json_encode($json)));
}












function JavaScriptEscape($string, $q = "'")
{
	$xq = $q == '"' ? "\x0f" : "\x10";
	return $xq . str_replace(
		array('\\',   "\n",   'script',   'href=',   '"' . SCRIPT,         "'" . SCRIPT,         $q == '"' ? "'" : '"',       $q),
		array('\\\\', "\\\n", 'scr\\ipt', 'hr\\ef=', '"' . SCRIPT . '"+"', "'" . SCRIPT . "'+'", $q == '"' ? "\x10" : "\x0f", '\\' . $xq),
		$string
	) . $xq;
}






function min_chars()
{
	global $settings;

	if (empty($settings['totalMembers']) || $settings['totalMembers'] > 1000)
		return 'minChars: 3';
	if ($settings['totalMembers'] > 100)
		return 'minChars: 2';
	return 'minChars: 1';
}









function comma_format($number, $override_decimal_count = false)
{
	global $txt;
	static $thousands_separator = null, $decimal_separator = null, $decimal_count = null;


	if (((int) $number) === $number && $number > -1000 && $number < 1000)
		return $number;


	if ($decimal_separator === null)
	{

		if (empty($txt['number_format']) || preg_match('~^1([^\d]*)?234([^\d]*)(0*?)$~', $txt['number_format'], $matches) != 1)
			return $number;


		$thousands_separator = $matches[1];
		$decimal_separator = $matches[2];
		$decimal_count = strlen($matches[3]);
	}


	return number_format($number, is_float($number) ? ($override_decimal_count === false ? $decimal_count : $override_decimal_count) : 0, $decimal_separator, $thousands_separator);
}











function number_context($string, $number, $format_comma = true)
{
	global $txt;

	$cnum = $format_comma ? comma_format($number) : $number;

	if ($txt[$string] !== (array) $txt[$string])
		return sprintf($txt[$string], $cnum);

	if (isset($txt[$string][$number]))
		return sprintf($txt[$string][$number], $cnum);

	return sprintf($txt[$string]['n'], $cnum);
}












function timeformat($log_time, $show_today = true, $offset_type = false)
{
	global $context, $txt, $settings;
	static $non_twelve_hour, $year_shortcut, $nowtime, $now;


	if (!$offset_type)
		$time = $log_time + (we::$user['time_offset'] + $settings['time_offset']) * 3600;

	else
		$time = $log_time + ($offset_type == 'forum' ? $settings['time_offset'] * 3600 : 0);


	if ($log_time < 0)
		$log_time = 0;

	$format =& we::$user['time_format'];


	if ($show_today === true && $settings['todayMod'] >= 1)
	{

		if (!isset($nowtime))
		{
			$nowtime = forum_time();
			$now = @getdate($nowtime);
		}
		$then = @getdate($time);


		$s = strpos($format, '%S') === false ? '' : ':%S';
		if (!strhas($format, array('%H', '%T')))
		{
			$h = strpos($format, '%l') === false ? '%I' : '%l';
			$today_fmt = $h . ':%M' . $s . ' %p';
		}
		else
			$today_fmt = '%H:%M' . $s;


		if ($then['yday'] == $now['yday'] && $then['year'] == $now['year'])
			return $txt['today'] . timeformat($log_time, $today_fmt, $offset_type);


		if ($settings['todayMod'] == '2' && (($then['yday'] == $now['yday'] - 1 && $then['year'] == $now['year']) || ($now['yday'] == 0 && $then['year'] == $now['year'] - 1) && $then['mon'] == 12 && $then['mday'] == 31))
			return $txt['yesterday'] . timeformat($log_time, $today_fmt, $offset_type);


		if ($then['year'] == $now['year'])
		{
			if ($format === $txt['time_format'])
				$show_today = $txt['time_format_this_year'];
			else
			{


				if (!isset($year_shortcut))
				{
					if (strpos($format, ', %Y') !== false)
						$y = ', %Y';
					elseif (strpos($format, ' %Y') !== false)
						$y = ' %Y';
					elseif (preg_match('~[./-]%Y|%Y[./-]~', $format, $match))
						$y = $match[0];
					$year_shortcut = isset($y) ? $y : false;
				}
				if (!empty($year_shortcut))
					$show_today = str_replace($year_shortcut, '', $format);
			}
		}
	}

	$str = !is_bool($show_today) ? $show_today : $format;

	if (!isset($non_twelve_hour))
		$non_twelve_hour = trim(strftime('%p')) === '';
	if ($non_twelve_hour && strpos($str, '%p') !== false)
		$str = str_replace('%p', strftime('%H', $time) < 12 ? 'am' : 'pm', $str);


	if (empty(we::$user['setlocale']))
		foreach (array('%a' => 'days_short', '%A' => 'days', '%b' => 'months_short', '%B' => 'months') as $token => $text_label)
			if (strpos($str, $token) !== false)
				$str = str_replace($token, $txt[$text_label][(int) strftime($token === '%a' || $token === '%A' ? '%w' : '%m', $time)], $str);


	if ($context['server']['is_windows'] && strpos($str, '%e') !== false)
		$str = str_replace('%e', '%#d', $str);

	if (strpos($str, '%@') !== false)
		$str = str_replace('%@', number_context('day_suffix', (int) strftime('%d', $time), false), $str);


	return strftime($str, $time);
}










function on_timeformat($log_time, $show_today = true, $offset_type = false)
{
	global $txt;

	$ret = timeformat($log_time, $show_today, $offset_type);
	if (strpos($ret, '<strong>') === false)
		return sprintf($txt['on_date'], $ret);

	return $ret;
}









function on_date($time, $upper = false)
{
	global $txt;

	if (strpos($time, '<strong>') === false)
		return $upper ? ucfirst(sprintf($txt['on_date'], $time)) : sprintf($txt['on_date'], $time);

	return $time;
}









function forum_time($use_user_offset = true, $timestamp = null)
{
	global $settings;

	if ($timestamp === null)
		$timestamp = time();

	if ($use_user_offset)
		return $timestamp + ($settings['time_offset'] + we::$user['time_offset']) * 3600;

	return $timestamp + $settings['time_offset'] * 3600;
}









function time_tag($timestamp, $on_time = '')
{
	return '<time datetime="' . date(DATE_W3C, $timestamp) . '">' . ($on_time ?: on_timeformat($timestamp)) . '</time>';
}









function un_htmlspecialchars($string)
{
	return strtr(htmlspecialchars_decode($string, ENT_QUOTES), array('&#039;' => '\'', '&#39;' => '\'', '&nbsp;' => ' '));
}






function strhas($string, $items)
{
	foreach ((array) $items as $item)
		if (strpos($string, $item) !== false)
			return $item;

	return false;
}






function strihas($string, $items)
{
	return strhas(strtolower($string), $items);
}











function shorten_subject($subject, $len)
{

	if (westr::strlen($subject) <= $len)
		return $subject;


	return westr::substr($subject, 0, $len) . '&hellip;';
}




function generic_contacts($str)
{
	global $txt;

	if (strpos($str, '{') === false || strpos($str, '}') === false)
		return $str;

	$type = substr($str, 1, -1);
	return isset($txt['contacts_' . $type]) ? $txt['contacts_' . $type] : '<em>' . $type . '</em>';
}













function writeLog($force = false)
{
	global $user_settings, $context, $settings, $topic, $board;


	if (!empty($settings['display_who_viewing']) && ($topic || $board))
	{

		$force = true;

		if ($topic)
		{
			if (isset($_SESSION['last_topic_id']) && $_SESSION['last_topic_id'] == $topic)
				$force = false;
			$_SESSION['last_topic_id'] = $topic;
		}
	}


	if (!empty(we::$user['possibly_robot']) && !empty($settings['spider_mode']) && $settings['spider_mode'] > 1)
	{
		loadSource('ManageSearchEngines');
		logSpider();
	}


	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= (time() - 8) && !$force)
		return;

	if (!empty($settings['who_enabled']))
	{
		$serialized = $_GET + array('USER_AGENT' => $_SERVER['HTTP_USER_AGENT']);


		if (!isset($context['session_var']))
		{
			$context['session_var'] = $_SESSION['session_var'];
			$context['session_query'] = $context['session_var'] . '=' . $_SESSION['session_value'];
		}

		unset($serialized[$context['session_var']]);
		$serialized = serialize($serialized);
	}
	else
		$serialized = '';


	$session_id = we::$is_guest ? 'ip' . we::$user['ip'] : session_id();


	$do_delete = cache_get_data('log_online-update', 30) < time() - 30;


	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - $settings['lastActive'] * 20)
	{
		if ($do_delete)
		{
			wesql::query('
				DELETE FROM {db_prefix}log_online
				WHERE log_time < {int:log_time}
					AND session != {string:session}',
				array(
					'log_time' => time() - $settings['lastActive'] * 60,
					'session' => $session_id,
				)
			);


			cache_put_data('log_online-update', time(), 30);
		}

		wesql::query('
			UPDATE {db_prefix}log_online
			SET log_time = {int:log_time}, ip = {int:ip}, url = {string:url}
			WHERE session = {string:session}',
			array(
				'log_time' => time(),
				'ip' => get_ip_identifier(we::$user['ip']),
				'url' => $serialized,
				'session' => $session_id,
			)
		);


		if (wesql::affected_rows() == 0)
			$_SESSION['log_time'] = 0;
	}
	else
		$_SESSION['log_time'] = 0;


	if (empty($_SESSION['log_time']))
	{
		if ($do_delete || !empty(we::$id))
			wesql::query('
				DELETE FROM {db_prefix}log_online
				WHERE ' . ($do_delete ? 'log_time < {int:log_time}' : '') . ($do_delete && !empty(we::$id) ? ' OR ' : '') . (empty(we::$id) ? '' : 'id_member = {int:current_member}'),
				array(
					'current_member' => we::$id,
					'log_time' => time() - $settings['lastActive'] * 60,
				)
			);

		wesql::insert($do_delete ? 'ignore' : 'replace',
			'{db_prefix}log_online',
			array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'int', 'url' => 'string'),
			array($session_id, we::$id, empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), get_ip_identifier(we::$user['ip']), $serialized)
		);
	}


	$_SESSION['log_time'] = time();


	if (empty($_SESSION['timeOnlineUpdated']))
		$_SESSION['timeOnlineUpdated'] = time();


	if (WEDGE != 'SSI' && !empty(we::$user['last_login']) && we::$user['last_login'] < time() - 60)
	{

		if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15)
			$_SESSION['timeOnlineUpdated'] = time();

		$user_settings['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		updateMemberData(we::$id, array('last_login' => time(), 'member_ip' => we::$user['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP'], 'total_time_logged_in' => $user_settings['total_time_logged_in']));

		if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
			cache_put_data('user_settings-' . we::$id, $user_settings, 60);

		we::$user['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		$_SESSION['timeOnlineUpdated'] = time();
	}
}













function redirectexit($setLocation = '', $refresh = false, $permanent = false)
{
	global $context, $db_show_debug, $db_cache;


	if (!empty($context['flush_mail']))
		AddMailQueue(true);

	$setLocation = str_replace('<URL>', SCRIPT, $setLocation);

	if ($is_internal = !preg_match('~^(?:http|ftp)s?://~', $setLocation))
		$setLocation = SCRIPT . ($setLocation != '' ? '?' . $setLocation : '');


	if (defined('SID') && SID != '' && (!isset($_COOKIE[session_name()]) || $_COOKIE[session_name()] !== session_id()))
		$setLocation = preg_replace('/^' . preg_quote(SCRIPT, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', SCRIPT . '?' . westr::safe(SID) . ';', $setLocation);

	elseif (isset($_GET['debug']))
		$setLocation = preg_replace('/^' . preg_quote(SCRIPT, '/') . '\\??/', SCRIPT . '?debug;', $setLocation);


	if ($is_internal)
		$setLocation = prettify_urls($setLocation);


	call_hook('redirect', array(&$setLocation, &$refresh));

	if ($permanent)
		header('HTTP/1.1 301 Moved Permanently');


	header(($refresh ? 'Refresh: 0; URL=' : 'Location: ') . str_replace(' ', '%20', $setLocation));


	if (!empty($db_show_debug))
		$_SESSION['debug_redirect'] = $db_cache;

	obExit(false);
}







function prettify_urls($inputs)
{
	global $settings;

	if (empty($settings['pretty_enable_filters']))
		return $inputs;

	loadSource('PrettyUrls-Filters');
	$is_single = !is_array($inputs);
	$inputs = (array) $inputs;
	foreach ($inputs as &$input)
	{
		$url = array(0 => array('url' => str_replace(SCRIPT, '', $input)));
		foreach ($settings['pretty_filters'] as $id => $enabled)
		{
			$func = 'pretty_filter_' . $id;
			if ($enabled)
				$func($url);
			if (isset($url[0]['replacement']))
				break;
		}
		if (isset($url[0]['replacement']))
			$input = $url[0]['replacement'];
		$input = strtr($input, "\x12", '\'');
		$input = preg_replace(array('~;+|=;~', '~\?;~', '~[?;=]#|&amp;#~', '~[?;=#]$|&amp;$~'), array(';', '?', '#', ''), $input);
	}
	return $is_single ? $inputs[0] : $inputs;
}








function logAction($action, $extra = array(), $log_type = 'moderate')
{
	global $settings;

	$log_types = array(
		'moderate' => 1,
		'user' => 2,
		'admin' => 3,
	);


	if (!isset($log_types[$log_type]) || empty($settings['log_enabled_' . $log_type]))
		return false;

	if (!is_array($extra))
		trigger_error('logAction(): data is not an array with action \'' . $action . '\'', E_USER_NOTICE);


	if (isset($extra['topic']))
	{
		if (!is_numeric($extra['topic']))
			trigger_error('logAction(): data\'s topic is not a number', E_USER_NOTICE);
		$topic_id = empty($extra['topic']) ? '0' : (int)$extra['topic'];
		unset($extra['topic']);
	}
	else
		$topic_id = '0';

	if (isset($extra['message']))
	{
		if (!is_numeric($extra['message']))
			trigger_error('logAction(): data\'s message is not a number', E_USER_NOTICE);
		$msg_id = empty($extra['message']) ? '0' : (int)$extra['message'];
		unset($extra['message']);
	}
	else
		$msg_id = '0';


	if (in_array($action, array('move', 'remove', 'split', 'merge')))
	{
		$request = wesql::query('
			SELECT id_report
			FROM {db_prefix}log_reported
			WHERE {raw:column_name} = {int:reported}
			LIMIT 1',
			array(
				'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
				'reported' => !empty($msg_id) ? $msg_id : $topic_id,
		));


		if (wesql::num_rows($request) > 0)
		{
			loadSource('ModerationCenter');
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports();
		}
		wesql::free_result($request);
	}

	if (isset($extra['member']) && !is_numeric($extra['member']))
		trigger_error('logAction(): data\'s member is not a number', E_USER_NOTICE);

	if (isset($extra['board']))
	{
		if (!is_numeric($extra['board']))
			trigger_error('logAction(): data\'s board is not a number', E_USER_NOTICE);
		$board_id = empty($extra['board']) ? '0' : (int)$extra['board'];
		unset($extra['board']);
	}
	else
		$board_id = '0';

	if (isset($extra['board_to']))
	{
		if (!is_numeric($extra['board_to']))
			trigger_error('logAction(): data\'s board_to is not a number', E_USER_NOTICE);
		if (empty($board_id))
		{
			$board_id = empty($extra['board_to']) ? '0' : (int)$extra['board_to'];
			unset($extra['board_to']);
		}
	}

	wesql::insert('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'int', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		array(
			time(), $log_types[$log_type], MID, get_ip_identifier(we::$user['ip']), $action,
			$board_id, $topic_id, $msg_id, serialize($extra),
		)
	);

	return wesql::insert_id();
}









function trackStats($stats = array())
{
	global $settings;
	static $cache_stats = array();

	if (empty($settings['trackStats']))
		return false;
	if (!empty($stats))
		return $cache_stats = array_merge($cache_stats, $stats);
	elseif (empty($cache_stats))
		return false;

	$setStringUpdate = '';
	$insert_keys = array();
	$date = strftime('%Y-%m-%d', forum_time(false));
	$update_parameters = array(
		'current_date' => $date,
	);
	foreach ($cache_stats as $field => $change)
	{
		$setStringUpdate .= '
			' . $field . ' = ' . ($change === '+' ? $field . ' + 1' : '{int:' . $field . '}') . ',';

		if ($change === '+')
			$cache_stats[$field] = 1;
		else
			$update_parameters[$field] = $change;
		$insert_keys[$field] = 'int';
	}

	wesql::query('
		UPDATE {db_prefix}log_activity
		SET' . substr($setStringUpdate, 0, -1) . '
		WHERE date = {date:current_date}',
		$update_parameters
	);
	if (wesql::affected_rows() == 0)
	{
		wesql::insert('ignore',
			'{db_prefix}log_activity',
			array_merge($insert_keys, array('date' => 'date')),
			array_merge($cache_stats, array($date))
		);
	}


	$cache_stats = array();

	return true;
}








function get_unread_numbers($posts, $straight_list = false, $is_boards = false)
{
	$has_unread = $nb_new = array();
	if (we::$is_member)
	{
		if ($straight_list || $is_boards)
			$has_unread = $posts;
		else
			foreach ($posts as $post)
				if (!empty($post['is_new']))
					$has_unread[] = $post['topic'];
	}

	if (empty($has_unread))
		return array();

	$where = $is_boards ? 'id_board' : 'id_topic';
	$request = wesql::query('
		SELECT COUNT(DISTINCT m.id_msg) AS co, m.' . $where . '
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:id_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:id_member})
		WHERE m.' . $where . ' IN ({array_int:has_unread})' . ($is_boards ? '
			AND (m.id_msg > {int:last_visit})' : '') . '
			AND (m.id_msg > IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)))
		GROUP BY m.' . $where,
		array(
			'id_member' => MID,
			'has_unread' => $has_unread,
			'last_visit' => $_SESSION['id_msg_last_visit'],
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$nb_new[$row[$where]] = $row['co'];
	wesql::free_result($request);

	return $nb_new;
}








function spamProtection($error_type)
{
	global $settings;


	$timeOverrides = array(
		'login' => 2,
		'register' => 2,
		'sendtopc' => $settings['spamWaitTime'] * 4,
		'sendmail' => $settings['spamWaitTime'] * 5,
		'report' => $settings['spamWaitTime'] * 4,
		'search' => isset($settings['search_floodcontrol_time']) ? $settings['search_floodcontrol_time'] : 1,
	);

	$timeLimit = isset($timeOverrides[$error_type]) ? $timeOverrides[$error_type] : $settings['spamWaitTime'];


	if (!$timeLimit)
		return false;


	if (allowedTo('moderate_board') && $timeLimit > 2)
		$timeLimit = 2;


	wesql::query('
		DELETE FROM {db_prefix}log_floodcontrol
		WHERE log_time < {int:log_time}
			AND log_type = {string:log_type}',
		array(
			'log_time' => time() - $timeLimit,
			'log_type' => $error_type,
		)
	);


	wesql::insert('replace',
		'{db_prefix}log_floodcontrol',
		array('ip' => 'int', 'log_time' => 'int', 'log_type' => 'string'),
		array(get_ip_identifier(we::$user['ip']), time(), $error_type)
	);


	if (wesql::affected_rows() != 1)
	{

		fatal_lang_error($error_type . 'WaitTime_broken', false, array($timeLimit));
		return true;
	}


	return false;
}





function preventPrefetch($always = false)
{
	global $settings;

	if ($always || (empty($settings['allow_prefetching']) && isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch'))
	{
		while (ob_get_length())
			ob_end_clean();
		header('HTTP/1.1 403' . ($always ? '' : ' Prefetch') . ' Forbidden');
		exit;
	}
}




function getRePrefix()
{
	global $context;

	if (isset($context['response_prefix']))
		return;

	$context['response_prefix'] = cache_get_data('re_prefix', 'forever', function ()
	{
		global $settings, $txt;

		if ($settings['language'] === we::$user['language'])
			$prefix = $txt['response_prefix'];
		else
		{
			$realtxt = $txt;
			loadLanguage('index', $settings['language'], false);
			$prefix = $txt['response_prefix'];
			$txt = $realtxt;
		}
		return $prefix;
	});
}




function is_valid_email($email)
{
	return filter_var($email, FILTER_VALIDATE_EMAIL);
}













function url_image_size($url)
{

	$url = str_replace(' ', '%20', $url);


	if (($temp = cache_get_data('url_image_size-' . md5($url), 240)) !== null)
		return $temp;
	$t = microtime(true);


	preg_match('~^\w+://(.+?)/(.*)$~', $url, $match);




	if ($url == '' || $url === 'http://' || $url === 'https://')
		return false;

	loadSource('Class-WebGet');
	try
	{
		$weget = new weget($url);
		$weget->addRange(0, 16383);
		$weget->addHeader('Accept', 'image/png,image/gif,image/jpeg;q=0.9,*/*;q=0.8');
		$data = $weget->get();
	}
	catch (Exception $e)
	{

		return false;
	}

	if ($data !== false)
	{

		if (strpos($data, 'GIF8') === 0)
		{

			$width = (ord(substr($data, 7, 1)) << 8) + (ord(substr($data, 6, 1)));
			$height = (ord(substr($data, 9, 1)) << 8) + (ord(substr($data, 8, 1)));

			if (!empty($width))
				$size = array($width, $height);
		}
		elseif (strpos($data, "\x89PNG") === 0)
		{

			$pos = strpos($data, 'IHDR');
			if ($pos >= 12)
			{
				$width = (ord(substr($data, $pos + 4, 1)) << 24) + (ord(substr($data, $pos + 5, 1)) << 16) + (ord(substr($data, $pos + 6, 1)) << 8) + (ord(substr($data, $pos + 7, 1)));
				$height = (ord(substr($data, $pos + 8, 1)) << 24) + (ord(substr($data, $pos + 9, 1)) << 16) + (ord(substr($data, $pos + 10, 1)) << 8) + (ord(substr($data, $pos + 11, 1)));
				if ($width > 0 && $height > 0)
					$size = array($width, $height);
			}
		}
		elseif (strpos($data, "\xFF\xD8") === 0)
		{


			$pos = 2;
			$filelen = strlen($data);
			while ($pos < $filelen)
			{
				$length = (ord(substr($data, $pos + 2, 1)) << 8) + (ord(substr($data, $pos + 3, 1)));
				$block = substr($data, $pos, 2);
				if ($block == "\xFF\xC0" || $block == "\xFF\xC2")
					break;

				$pos += $length + 2;
			}

			if ($pos > 2)
			{

				$width = (ord(substr($data, $pos + 7, 1)) << 8) + (ord(substr($data, $pos + 8, 1)));
				$height = (ord(substr($data, $pos + 5, 1)) << 8) + (ord(substr($data, $pos + 6, 1)));
				if ($width > 0 && $height > 0)
					$size = array($width, $height);
			}
		}
	}


	if (!isset($size))
		$size = false;


	if (microtime(true) - $t > 0.8)
		cache_put_data('url_image_size-' . md5($url), $size, 240);


	return $size;
}















function setupThemeContext($forceload = false)
{
	global $settings, $context, $options, $txt, $maintenance;
	static $loaded = false;



	if ($loaded && !$forceload)
		return;

	$loaded = true;

	$context['in_maintenance'] = !empty($maintenance);
	$context['current_time'] = timeformat(time(), false);


	$context['news_lines'] = cache_quick_get('news_lines', 'ManageNews', 'cache_getNews', array());

	foreach ($context['news_lines'] as $id => &$item)
	{

		switch ($item[0])
		{
			case 'a':
				if (!we::$is_admin && !allowedTo('admin_forum'))
				{
					unset($context['news_lines'][$id]);
					continue;
				}
				break;
			case 's':
				if (!we::$is_admin && !allowedTo(array('moderate_forum', 'moderate_board')))
				{
					unset($context['news_lines'][$id]);
					continue;
				}
				break;
			case 'm':
				if (we::$is_guest)
				{
					unset($context['news_lines'][$id]);
					continue;
				}
				break;
		}

		$item = substr($item, 1);
	}
	$context['fader_news_lines'] = array();

	foreach ($context['news_lines'] as $i => $item)
		$context['fader_news_lines'][$i] = strtr(addslashes($item), array('/' => '\/', '<a href=' => '<a hre" + "f='));

	$context['random_news_line'] = !empty($context['news_lines']) ? $context['news_lines'][array_rand($context['news_lines'])] : '';

	if (we::$is_member)
	{

		we::$user['popup_messages'] = we::$user['unread_messages'] > (isset($_SESSION['unread_messages']) ? $_SESSION['unread_messages'] : 0);
		$_SESSION['unread_messages'] = we::$user['unread_messages'];

		if (allowedTo('moderate_forum'))
			$context['unapproved_members'] = (!empty($settings['registration_method']) && $settings['registration_method'] == 2) || !empty($settings['approveAccountDeletion']) ? $settings['unapprovedMembers'] : 0;

		$avatar =& we::$user['avatar'];


		if ($avatar['url'] == '' && !empty($avatar['id_attach']))
			$avatar['href'] = $avatar['custom_dir'] ? $settings['custom_avatar_url'] . '/' . $avatar['filename'] : '<URL>?action=dlattach;attach=' . $avatar['id_attach'] . ';type=avatar';

		elseif (preg_match('~^(?:https?:)?//~', $avatar['url']))
		{
			$avatar['href'] = $avatar['url'];

			if ($settings['avatar_action_too_large'] == 'option_html_resize' || $settings['avatar_action_too_large'] == 'option_js_resize')
			{
				if (!empty($settings['avatar_max_width_external']))
					$avatar['width'] = $settings['avatar_max_width_external'];
				if (!empty($settings['avatar_max_height_external']))
					$avatar['height'] = $settings['avatar_max_height_external'];
			}
		}

		elseif (strpos($avatar['url'], 'gravatar://') === 0)
		{
			if ($avatar['url'] === 'gravatar://' || empty($settings['gravatarAllowExtraEmail']))
				$avatar['href'] = get_gravatar_url(we::$user['email']);
			else
				$avatar['href'] = get_gravatar_url(substr($avatar['url'], 11));

			if (!empty($settings['avatar_max_width_external']))
				$avatar['width'] = $settings['avatar_max_width_external'];
			if (!empty($settings['avatar_max_height_external']))
				$avatar['height'] = $settings['avatar_max_height_external'];
		}

		elseif ($avatar['url'] != '')
			$avatar['href'] = AVATARS . '/' . htmlspecialchars($avatar['url']);

		$opaque = !empty($avatar['id_attach']) && $avatar['transparent'] ? '' : 'opaque ';

		if (!empty($avatar['href']))
			$avatar['image'] = '<img class="' . $opaque . 'avatar" src="' . $avatar['href'] . '"' . (isset($avatar['width']) ? ' width="' . $avatar['width'] . '"' : '') . (isset($avatar['height']) ? ' height="' . $avatar['height'] . '"' : '') . '>';


		we::$user['total_time_logged_in'] = array(
			'days' => floor(we::$user['total_time_logged_in'] / 86400),
			'hours' => floor((we::$user['total_time_logged_in'] % 86400) / 3600),
			'minutes' => floor((we::$user['total_time_logged_in'] % 3600) / 60)
		);
	}
	else
	{
		we::$user['total_time_logged_in'] = array('days' => 0, 'hours' => 0, 'minutes' => 0);
		we::$user['popup_messages'] = false;

		if (!empty($settings['registration_method']) && $settings['registration_method'] == 1)
			$txt['welcome_guest'] .= $txt['welcome_guest_activate'];


		if (!empty($settings['disableHashTime']) && ($settings['disableHashTime'] == 1 || time() < $settings['disableHashTime']))
			$context['disable_login_hashing'] = true;
	}


	setupMenuContext();


	$context['show_pm_popup'] = we::$user['popup_messages'] && !empty($options['popup_messages']) && $context['action'] !== 'pm';


	if ($settings['avatar_action_too_large'] == 'option_js_resize' && (!empty($settings['avatar_max_width_external']) || !empty($settings['avatar_max_height_external'])))
	{



		if (!empty($context['main_js_files']))
			$context['main_js_files']['avasize.js'] = false;

		add_js('
	var we_avatarMaxSize = [' . (int) $settings['avatar_max_width_external'] . ', ' . (int) $settings['avatar_max_height_external'] . '];
	$(window).on("load", we_avatarResize);');
	}


	$context['common_stats']['latest_member'] = array(
		'id' => $settings['latestMember'],
		'name' => $settings['latestRealName'],
		'href' => '<URL>?action=profile;u=' . $settings['latestMember'],
		'link' => '<a href="<URL>?action=profile;u=' . $settings['latestMember'] . '">' . $settings['latestRealName'] . '</a>',
	);
	$context['common_stats'] = array(
		'total_posts' => comma_format($settings['totalMessages']),
		'total_topics' => comma_format($settings['totalTopics']),
		'total_members' => comma_format($settings['totalMembers']),
		'latest_member' => $context['common_stats']['latest_member'],
	);

	if (!isset($context['page_title']))
		$context['page_title'] = '';
}











function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
{
	global $settings;


	if ($new)
		return sha1(md5($filename . time()) . mt_rand());


	if ($file_hash === '')
	{
		$request = wesql::query('
			SELECT file_hash
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attachment_id,
		));

		if (wesql::num_rows($request) === 0)
			return false;

		list ($file_hash) = wesql::fetch_row($request);
		wesql::free_result($request);
	}


	if (empty($file_hash))
	{
		loadSource('ManageAttachments');
		return getLegacyAttachmentFilename($filename, $attachment_id, $dir, $new);
	}


	if (!empty($settings['currentAttachmentUploadDir']))
	{
		if (!is_array($settings['attachmentUploadDir']))
			$settings['attachmentUploadDir'] = unserialize($settings['attachmentUploadDir']);
		$path = $settings['attachmentUploadDir'][$dir];
	}
	else
		$path = $settings['attachmentUploadDir'];

	return $path . '/' . $attachment_id . '_' . $file_hash . '.ext';
}







function ip2range($fullip)
{

	if ($fullip == 'unknown')
		$fullip = '255.255.255.255';

	$ip_parts = explode('.', $fullip);
	$ip_array = array();

	if (count($ip_parts) != 4)
		return array();

	for ($i = 0; $i < 4; $i++)
	{
		if ($ip_parts[$i] == '*')
			$ip_array[$i] = array('low' => '0', 'high' => '255');
		elseif (preg_match('/^(\d{1,3})\-(\d{1,3})$/', $ip_parts[$i], $range) == 1)
			$ip_array[$i] = array('low' => $range[1], 'high' => $range[2]);
		elseif (is_numeric($ip_parts[$i]))
			$ip_array[$i] = array('low' => $ip_parts[$i], 'high' => $ip_parts[$i]);
	}

	return $ip_array;
}










function match_cidr($ip, $cidr_block)
{
	if (is_array($cidr_block))
	{
		foreach ($cidr_block as $cidr)
			if (match_cidr($ip, $cidr))
				return true;
	}
	else
	{

		if (strpos($cidr_block, '/') === false)
		{
			$cidr_block = expand_ip($cidr_block);
			return $cidr_block == $ip;
		}

		list ($cidr_ip, $mask) = explode('/', $cidr_block);
		$cidr_ip = expand_ip($cidr_ip);

		$mask = (strpos($cidr_block, ':') !== false ? 128 : 32) - $mask;


		if ($mask % 4 == 0)
		{
			$len = 32 - $mask / 4;
			return (substr($cidr_ip, 0, $len) === substr($ip, 0, $len));
		}
		else
		{

			$whole_digits = 32 - ceil($mask / 4);
			if (substr($cidr_ip, 0, $whole_digits) != substr($ip, 0, $whole_digits))
				return false;


			$cidr_ip = hexdec(substr($cidr_ip, $whole_digits, 1));
            $ip = hexdec(substr($ip, $whole_digits, 1));
            $mask = 16 - pow(2, $mask % 4);

			return ($cidr_ip & $mask) == ($ip & $mask);
		}
	}
	return false;
}














function host_from_ip($ip)
{
	global $settings;

	if (($host = cache_get_data('hostlookup-' . $ip, 86400)) !== null)
		return $host;
	$t = microtime(true);

	if (is_callable('dns_get_record') && !isset($host))
	{
		$arpa = '';

		if (preg_match('~\d{2,3}(\.\d{1,3}){3}~', $ip))
			$arpa = implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
		else
		{


			$ipv6 = expand_ip($ip);
			if ($ipv6 != INVALID_IP)
				$arpa = implode('.', str_split(strrev($ipv6))) . '.ip6.arpa';
		}

		if (!empty($arpa))
		{
			$details = @dns_get_record($arpa, DNS_ALL);
			if (is_array($details))
				foreach ($details as $contents)
					if ($contents['type'] == 'PTR' && !empty($contents['target']))
					{
						$host = $contents['target'];
						break;
					}
		}
	}


	if (!isset($host) && (strpos(strtolower(PHP_OS), 'win') === false || strpos(strtolower(PHP_OS), 'darwin') !== false) && mt_rand(0, 1) == 1)
	{
		if (!isset($settings['host_to_dis']))
			$test = @shell_exec('host -W 1 ' . @escapeshellarg($ip));
		else
			$test = @shell_exec('host ' . @escapeshellarg($ip));


		if (strpos($test, 'not found') !== false)
			$host = '';

		elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset($settings['host_to_dis']))
			updateSettings(array('host_to_dis' => 1));

		elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match) == 1)
			$host = $match[1];
	}


	if (!isset($host) && strpos(strtolower(PHP_OS), 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
	{
		$test = @shell_exec('nslookup -timeout=1 ' . @escapeshellarg($ip));
		if (strpos($test, 'Non-existent domain') !== false)
			$host = '';
		elseif (preg_match('~Name:\s+([^\s]+)~', $test, $match) == 1)
			$host = $match[1];
	}


	if (!isset($host) || $host === false)
		$host = @gethostbyaddr($ip);


	if (($ftime = microtime(true) - $t) > 0.5)
	{
		cache_put_data('hostlookup-' . $ip, $host, 86400);

		if ($ftime > 3 && !isset($settings['disableHostnameLookup']))
			updateSettings(array('disableHostnameLookup' => 1));
	}

	return $host;
}











function text2words($text, $max_chars = 20, $encrypt = false)
{

	$words = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', strtr($text, array('<br>' => ' ')));


	$words = un_htmlspecialchars(westr::strtolower($words));


	$words = explode(' ', $words);

	if ($encrypt)
	{
		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));
		$returned_ints = array();
		foreach ($words as $word)
		{
			if (($word = trim($word, '-_\'')) !== '')
			{
				$encrypted = substr(crypt($word, 'uk'), 2, $max_chars);
				$total = 0;
				for ($i = 0; $i < $max_chars; $i++)
					$total += $possible_chars[ord($encrypted{$i})] * pow(63, $i);
				$returned_ints[] = $max_chars == 4 ? min($total, 16777215) : $total;
			}
		}
		return array_unique($returned_ints);
	}
	else
	{

		$returned_words = array();
		foreach ($words as $word)
			if (($word = trim($word, '-_\'')) !== '')
				$returned_words[] = $max_chars === null ? $word : substr($word, 0, $max_chars);


		return array_unique($returned_words);
	}
}










function setupMenuContext()
{
	global $context, $settings, $board_info, $txt;


	$context['allow_search'] = allowedTo('search_posts');
	$context['allow_admin'] = allowedTo(array('admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys'));
	$context['allow_edit_profile'] = we::$is_member && allowedTo(array('profile_view_own', 'profile_view_any', 'profile_identity_own', 'profile_identity_any', 'profile_extra_own', 'profile_extra_any', 'profile_remove_own', 'profile_remove_any', 'moderate_forum', 'manage_membergroups', 'profile_title_own', 'profile_title_any'));
	$context['allow_memberlist'] = allowedTo('view_mlist');
	$context['allow_moderation_center'] = allowedTo('access_mod_center');
	$context['allow_pm'] = !empty($settings['pm_enabled']) && allowedTo('pm_read');
	$context['unapproved_members'] = !empty($context['unapproved_members']) ? $context['unapproved_members'] : 0;


	if (!empty(we::$user['media_unseen']) && we::$user['media_unseen'] == -1)
	{
		loadSource('media/Subs-Media');
		loadMediaSettings();
	}

	$error_count = allowedTo('admin_forum') ? (!empty($settings['app_error_count']) ? $settings['app_error_count'] : '') : '';
	$can_view_unseen = allowedTo('media_access_unseen') && isset(we::$user['media_unseen']) && we::$user['media_unseen'] > 0;
	$can_admin = allowedTo('admin_forum');
	$is_b = !empty($board_info['id']);

	$items = array(
		'site_home' => array(
			'title' => $txt['home'],
			'href' => !empty($settings['home_url']) ? $settings['home_url'] : '',
			'show' => !empty($settings['home_url']),
		),
		'home' => array(
			'title' => !empty($settings['home_url']) ? $txt['community'] : $txt['home'],
			'href' => '<URL>',
			'show' => true,
			'items' => array(
				'root' => array(
					'title' => $context['forum_name'],
					'href' => '<URL>',
					'show' => $is_b || we::is('mobile'),
				),
				'board' => array(
					'title' => $is_b ? $board_info['name'] : '',
					'href' => $is_b ? '<URL>?board=' . $board_info['id'] . '.0' : '',
					'show' => $is_b,
				),
				'',
				'media' => array(
					'title' => isset($txt['media_gallery']) ? $txt['media_gallery'] : 'Media',
					'notice' => $can_view_unseen ? we::$user['media_unseen'] : '',
					'href' => '<URL>?action=media',
					'show' => !empty($settings['media_enabled']) && allowedTo('media_access'),
					'notice' => $can_view_unseen ? we::$user['media_unseen'] : '',
					'icon' => true,
					'items' => array(
						'home' => array(
							'title' => $txt['media_home'],
							'href' => '<URL>?action=media',
							'show' => $can_view_unseen,
						),
						'unseen' => array(
							'title' => $txt['media_unseen'],
							'href' => '<URL>?action=media;sa=unseen',
							'show' => $can_view_unseen,
						),
					),
				),
				'mlist' => array(
					'title' => $txt['members_title'],
					'href' => '<URL>?action=mlist',
					'show' => $context['allow_memberlist'],
					'icon' => true,
					'items' => array(
						'mlist_view' => array(
							'title' => $txt['mlist_menu_view'],
							'href' => '<URL>?action=mlist',
							'show' => true,
						),
						'mlist_search' => array(
							'title' => $txt['mlist_search'],
							'href' => '<URL>?action=mlist;sa=search',
							'show' => true,
						),
					),
				),
			),
		),
		'admin' => array(
			'title' => $txt['admin'],
			'href' => '<URL>?action=' . ($context['allow_admin'] ? 'admin' : 'moderate'),
			'show' => $context['allow_admin'] || $context['allow_moderation_center'],
			'items' => array(
				'featuresettings' => array(
					'title' => $txt['settings_title'],
					'href' => '<URL>?action=admin;area=featuresettings',
					'show' => $can_admin,
				),
				'errorlog' => array(
					'title' => $txt['errlog'],
					'notice' => $error_count,
					'href' => '<URL>?action=admin;area=logs;sa=errorlog',
					'show' => $can_admin && !empty($settings['enableErrorLogging']),
				),
				'permissions' => array(
					'title' => $txt['edit_permissions'],
					'href' => '<URL>?action=admin;area=permissions',
					'show' => allowedTo('manage_permissions'),
				),
				'plugins' => array(
					'title' => $txt['plugin_manager'],
					'href' => '<URL>?action=admin;area=plugins',
					'show' => $can_admin,
				),
				'',
				'modcenter' => array(
					'title' => $txt['moderate'],
					'href' => '<URL>?action=moderate',
					'show' => $context['allow_admin'],
				),
				'modlog' => array(
					'title' => $txt['modlog_view'],
					'href' => '<URL>?action=moderate;area=modlog',
					'show' => !empty($settings['log_enabled_moderate']) && !empty(we::$user['mod_cache']) && we::$user['mod_cache']['bq'] != '0=1',
				),
				'reports' => array(
					'title' => $txt['mc_reported_posts'],
					'href' => '<URL>?action=moderate;area=reports',
					'show' => !empty(we::$user['mod_cache']) && we::$user['mod_cache']['bq'] != '0=1',
					'notice' => $context['open_mod_reports'],
				),
				'poststopics' => array(
					'title' => $txt['mc_unapproved_poststopics'],
					'href' => '<URL>?action=moderate;area=postmod;sa=posts',
					'show' => $settings['postmod_active'] && !empty(we::$user['mod_cache']['ap']),
				),
				'unappmembers' => array(
					'title' => $txt['unapproved_members'],
					'href' => '<URL>?action=admin;area=viewmembers;sa=browse;type=approve',
					'show' => $context['unapproved_members'],
					'notice' => $context['unapproved_members'],
				),
				'',
				'uncache' => array(
					'title' => $txt['admin_uncache'],
					'href' => '<URL>?action=uncache',
					'show' => $can_admin,
				),
			),
		),
		'profile' => array(
			'title' => $txt['profile'],
			'href' => '<URL>?action=profile',
			'show' => $context['allow_edit_profile'],
			'items' => array(
				'summary' => array(
					'title' => $txt['summary'],
					'href' => '<URL>?action=profile',
					'show' => true,
				),
				'showdrafts' => array(
					'title' => $txt['draft_posts'],
					'href' => '<URL>?action=profile;area=showdrafts',
					'show' => allowedTo('save_post_draft') && !empty($settings['masterSavePostDrafts']),
				),
				'account' => array(
					'title' => $txt['account'],
					'href' => '<URL>?action=profile;area=account',
					'show' => allowedTo(array('profile_identity_any', 'profile_identity_own', 'manage_membergroups')),
				),
				'profile' => array(
					'title' => $txt['forumprofile'],
					'href' => '<URL>?action=profile;area=forumprofile',
					'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')),
				),
				'',
				'skin' => array(
					'title' => $txt['change_skin'],
					'href' => '<URL>?action=skin',
					'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')) && (we::$is_member || empty(we::$user['possibly_robot'])),
				),
				'',
				'logout' => array(
					'title' => $txt['logout'],
					'href' => '<URL>?action=logout;' . $context['session_query'],
					'show' => we::$is_member,
					'icon' => true,
				),
			),
		),
		'login' => array(
			'title' => $txt['login'],
			'href' => '<URL>?action=login',
			'show' => we::$is_guest,
			'nofollow' => !empty(we::$user['possibly_robot']),
		),
		'register' => array(
			'title' => $txt['register'],
			'href' => '<URL>?action=register',
			'show' => we::$is_guest && (empty($settings['registration_method']) || $settings['registration_method'] != 3),
			'nofollow' => !empty(we::$user['possibly_robot']),
		),
	);


	if (!empty($error_count) || !empty($items['admin']['items']['reports']['notice']) || !empty($context['unapproved_members']))
		$items['admin']['notice'] = (int) $error_count + (int) $items['admin']['items']['reports']['notice'] + (int) $context['unapproved_members'];



	call_hook('menu_items', array(&$items));


	$menu_items = array();
	foreach ($items as $act => $item)
	{
		if (!empty($item['show']))
		{
			$item['active_item'] = false;
			$was_sep = true;


			if (!empty($item['items']))
			{
				foreach ($item['items'] as $key => $subitem)
				{
					if ((empty($subitem['show']) && !empty($subitem)) || ($was_sep && $subitem === ''))
						unset($item['items'][$key]);
					else
						$was_sep = $subitem === '';


					if (!empty($subitem['items']))
						foreach ($subitem['items'] as $key2 => $subitem2)
							if (empty($subitem2['show']) && !empty($subitem2))
								unset($item['items'][$key]['items'][$key2]);
				}
			}

			$menu_items[$act] = $item;
		}
	}


	if (!SKIN_SHORTMENU)
	{
		$from_now = false;
		foreach ($menu_items['home']['items'] as $title => $entry)
		{
			if ($from_now)
			{
				$menu_items[$title] = $entry;
				unset($menu_items['home']['items'][$title]);
			}
			elseif (empty($entry))
				$from_now = true;
		}
	}


	if (reset($menu_items['home']['items']) === '')
		array_shift($menu_items['home']['items']);
	if (end($menu_items['home']['items']) === '')
		array_pop($menu_items['home']['items']);

	$context['menu_items'] =& $menu_items;


	if (isset($menu_items[$context['action']]))
		$current_action = $context['action'];
	elseif ($context['action'] == 'theme')
		$current_action = isset($_REQUEST['u']) && $_REQUEST['u'] > 0 ? 'profile' : 'admin';
	elseif ($context['action'] == 'register2')
		$current_action = 'register';
	elseif ($context['action'] == 'login2' || (we::$is_guest && $context['action'] == 'reminder'))
		$current_action = 'login';
	elseif ($context['action'] == 'groups' && $context['allow_moderation_center'])
		$current_action = 'admin';
	elseif ($context['action'] == 'moderate' && $context['allow_moderation_center'])
		$current_action = 'admin';


	if (empty($current_action) || empty($menu_items[$current_action]))
		$current_action = 'home';

	$menu_items[$current_action]['active_item'] = true;
}













function call_hook($hook, $parameters = array(), $plugin_id = '')
{
	global $settings;

	if (isset($_GET['viewhooks']) && we::$is_admin)
		echo '<div style="display: inline-block; background: ', empty($settings['hooks'][$hook]) ? 'red' : 'green', '; border: 1px solid white; border-radius: 2px; width: 8px; height: 8px" title="', westr::safe($hook), '"></div>';

	if (wetem::$hooks && wetem::$hooks->has($hook))
		wetem::$hooks->render($hook);

	if (empty($settings['hooks'][$hook]))
		return array();

	$results = array();


	foreach ($settings['hooks'][$hook] as $function)
	{
		$fun = explode('|', trim($function));
		$call = strpos($fun[0], '::') !== false ? explode('::', $fun[0]) : $fun[0];


		if (!empty($plugin_id) && !empty($fun[2]) && $plugin_id != $fun[2])
			continue;


		if (!empty($fun[1]))
		{

			if (!empty($fun[2]))
				loadPluginSource($fun[2], $fun[1]);
			else
				loadSource($fun[1]);
		}


		if (is_callable($call))
			$results[$fun[0]] = call_user_func_array($call, $parameters);
		else
			remove_hook($hook, $call, !empty($fun[1]) ? $fun[1] : '');
	}

	return $results;
}

function call_lang_hook($hook, $plugin_id = '')
{
	global $settings;

	if (empty($settings['hooks'][$hook]))
		return false;

	static $lang = null;
	if ($lang === null)
		$lang = isset(we::$user['language']) ? we::$user['language'] : $settings['language'];

	foreach ($settings['hooks'][$hook] as $function)
	{
		$fun = explode('|', trim($function));



		if (!empty($plugin_id) && !empty($fun[2]) && $plugin_id != $fun[2])
			continue;

		if (empty($fun[0]))
		{

			if (!empty($fun[2]))
				loadPluginLanguage($fun[2], $fun[1]);
			else
				loadLanguage($fun[1]);
		}
	}
}












function add_hook($hook, $function, $file = '', $register = true)
{
	global $settings;

	if (!empty($file) && (strpos($file, '|') !== false || !file_exists(APP_DIR . '/' . ($file = trim($file)) . '.php')))
		$file = '';

	$function .= '|' . $file;

	if ($register && !isset($settings['registered_hooks'][$hook]))
		$settings['registered_hooks'][$hook] = array();
	elseif (!$register && !isset($settings['hooks'][$hook]))
		$settings['hooks'][$hook] = array();


	if ((!$register || in_array($function, $settings['registered_hooks'][$hook])) && ($in_hook = in_array($function, $settings['hooks'][$hook])))
		return;


	if (empty($in_hook))
		$settings['hooks'][$hook][] = $function;
	if (!$register)
		return;


	$hooks = $settings['registered_hooks'];
	$hooks[$hook][] = $function;
	updateSettings(array('registered_hooks' => serialize($hooks)));
	$settings['registered_hooks'] = $hooks;
}











function remove_hook($hook, $function, $file = '')
{
	global $settings;

	if (!empty($file) && !file_exists(APP_DIR . '/' . ($file = trim($file)) . '.php'))
		$file = '';

	$function .= '|' . $file;


	if (empty($settings['hooks'][$hook]) || !in_array($function, $settings['hooks'][$hook]))
		return;

	$settings['hooks'][$hook] = array_diff($settings['hooks'][$hook], (array) $function);

	if (empty($settings['registered_hooks'][$hook]) || !in_array($function, $settings['registered_hooks'][$hook]))
		return;


	$hooks = $settings['registered_hooks'];
	$hooks[$hook] = array_diff($hooks[$hook], (array) $function);
	if (empty($hooks[$hook]))
		unset($hooks[$hook]);
	updateSettings(array('registered_hooks' => serialize($hooks)));
	$settings['registered_hooks'] = $hooks;
}




function add_linktree($name, $url = null, $before = null, $after = null, $first = false)
{
	global $context;

	$item = array(
		'name' => $name,
	);
	if ($url !== null)
		$item['url'] = $url;
	if ($before !== null)
		$item['extra_before'] = $before;
	if ($after !== null)
		$item['extra_after'] = $after;

	if ($first)
		array_unshift($context['linktree'], $item);
	else
		$context['linktree'][] = $item;
}






function get_gravatar_url($email_address)
{
	global $settings;
	static $size_string = null;

	if ($size_string === null)
	{
		if (!empty($settings['avatar_max_width_external']))
			$size_string = (int) $settings['avatar_max_width_external'];
		if (!empty($settings['avatar_max_height_external']) && !empty($size_string))
			if ((int) $settings['avatar_max_height_external'] < $size_string)
				$size_string = $settings['avatar_max_height_external'];

		if (!empty($size_string))
			$size_string = 's=' . $size_string;
		else
			$size_string = '';
	}

	return '//gravatar.com/avatar/' . md5(strtolower($email_address)) . '?' . (!empty($settings['gravatarMaxRating']) ? 'r=' . $settings['gravatarMaxRating'] . ($size_string ? '&amp;' : '') : '') . $size_string;
}




function get_default_skin()
{
	global $settings;
	return $settings[we::is('mobile') ? 'theme_skin_guests_mobile' : 'theme_skin_guests'];
}




function valid_filename($str)
{
	return preg_replace('~([\\\\/:*?"<>|]+)~', '-', $str);
}
