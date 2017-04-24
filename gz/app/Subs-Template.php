<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');





















function obExit($start = null, $do_finish = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $settings;
	static $start_done = false, $level = 0, $has_fatal_error = false;


	if (++$level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;
	if ($from_fatal_error)
		$has_fatal_error = true;


	trackStats();


	if (!empty($context['flush_mail']))
		AddMailQueue(true);

	$do_start = $start === null ? !$start_done : $start;
	if ($do_finish === null)
		$do_finish = $do_start;


	if ($do_start)
	{

		if (empty($context['canonical_url']) && we::$user['possibly_robot'] && empty($context['robot_no_index']) && strpos(we::$user['url'], ($sn = session_name()) . '=') !== false)
			if (we::$user['url'] != ($correcturl = preg_replace('~(?:\?' . $sn . '=[^&;]*$|\b' . $sn . '=[^&;]*[&;])~', '', we::$user['url'])))
				$context['canonical_url'] = $correcturl;


		if (!defined('WEDGE') || WEDGE != 'SSI')
			ob_start('ob_sessrewrite');


		if (!empty($context['output_buffers']) && is_string($context['output_buffers']))
			$buffers = explode(',', $context['output_buffers']);
		elseif (!empty($context['output_buffers']))
			$buffers = $context['output_buffers'];
		else
			$buffers = array();

		if (isset($settings['hooks']['buffer']))
			$buffers = array_merge($settings['hooks']['buffer'], $buffers);

		if (!empty($buffers))
		{
			foreach ($buffers as $function)
			{
				$fun = explode('|', trim($function));
				$call = strpos($fun[0], '::') !== false ? explode('::', $fun[0]) : $fun[0];


				if (!empty($fun[1]))
				{
					if (!empty($fun[2]))
						loadPluginSource($fun[2], $fun[1]);
					else
						loadSource($fun[1]);
				}
				ob_start($call);
			}
		}


		start_output();
		$start_done = true;
	}

	if ($do_finish)
		wetem::render();


	if (isset($_SERVER['REQUEST_URL']) && !strhas($_SERVER['REQUEST_URL'], array('action=dlattach', 'action=viewremote')))
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];


	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];


	call_hook('exit', array($do_finish));


	if (!$from_index)
	{
		if (!isset($settings['app_error_count']))
			$settings['app_error_count'] = 0;
		if (!empty($context['app_error_count']))
			updateSettings(['app_error_count' => $settings['app_error_count'] + $context['app_error_count']]);
		exit;
	}
}











function ob_sessrewrite($buffer)
{
	global $settings, $context, $session_var, $board_info, $is_output_buffer;
	global $txt, $time_start, $db_count, $cached_urls, $use_cache, $members_groups;


	if (!defined('SID') && ($sid = session_id()) != '')
		define('SID', $sid);


	if (SCRIPT == '' || !defined('SID'))
		return $buffer;

	$is_output_buffer = true;
	if (!empty($context['show_load_time']))
	{
		$old_db_count = $db_count;
		$old_load_time = microtime(true);
	}

	if ($skip_it = strpos($buffer, '<ob:ignore>'))
	{
		$skip_buffer = substr($buffer, $skip_it + 11, strpos($buffer, '</ob:ignore>') - $skip_it - 11);
		$buffer = str_replace(array($skip_buffer, '<ob:ignore></ob:ignore>'), array('', '<ob:ignored>'), $buffer);
	}


	$page_title = isset($context['page_title']) ? westr::htmlspecialchars(un_htmlspecialchars(strip_tags($context['page_title'])), ENT_COMPAT, false, false) : '';


	$buffer = str_replace(array('<URL>', '<PROT>', '<PAGE_TITLE>'), array(SCRIPT, PROTOCOL, $page_title), $buffer);


	if (strpos($buffer, "\r\n") !== false)
		$buffer = str_replace("\r\n", "\n", $buffer);

	if (isset($context['meta_description'], $context['meta_description_repl']))
		$buffer = str_replace($context['meta_description'], $context['meta_description_repl'], $buffer);


	$preg_scripturl = preg_quote(SCRIPT, '~');

	call_hook('dynamic_rewrite', array(&$buffer));


	if (!empty($context['header_css']))
		$context['header'] .= "\n\t<style>" . $context['header_css'] . "\n\t</style>";


	if (!empty($context['header']) && wetem::has_layer('html') && ($where = strpos($buffer, "\n</head>")) !== false)
		$buffer = substr_replace($buffer, $context['header'], $where, 0);



	$context['delayed_events'] = array();
	$cut = explode("<!-- JavaScript area -->\n", $buffer);



	if (!empty($cut[1]))
		$buffer = preg_replace_callback('~<[^>]+?\son[a-z]+="[^"]*"[^>]*>~i', 'wedge_event_delayer', $cut[0]) . $cut[1];

	$this_pos = strpos($buffer, '<!-- insert inline events here -->');
	if ($this_pos !== false)
	{
		if (!empty($context['delayed_events']))
		{
			$thing = 'eves = {';
			foreach ($context['delayed_events'] as $eve)
				$thing .= '
		' . $eve[0] . ': ["' . $eve[1] . '", function (e) { ' . $eve[2] . ' }],';
			$thing = substr($thing, 0, -1) . '
	};';
		}
		else
			$thing = 'eves = 1;';

		$buffer = substr_replace($buffer, $thing, $this_pos, 34);
	}

	$buffer = strtr($buffer, "\x0f\x10", '"\'');





	if ((!defined('SKIN_MOBILE') || !SKIN_MOBILE) && strpos($buffer, '<we:msg_') !== false)
	{
		$ex_uid = $ex_area = $area = $one_removed = '';
		$is_forum = isset($board_info['type']) && $board_info['type'] == 'forum';


		preg_match_all('~<we:msg [^>]*id="([^"]+)" class="([^"]+)"[^>]*>(.*?)</we:msg>~s', $buffer, $messages, PREG_SET_ORDER);
		foreach ($messages as $msg)
		{

			if (!$is_forum && strpos($msg[2], 'first-post') !== false)
				continue;


			preg_match('~data-id="(\d+)" class="[^"]*umme~', $msg[3], $uid);
			preg_match('~<we:msg_entry>(.*?)</we:msg_entry>~s', $msg[3], $area);


			if ($ex_uid == $uid)
			{

				$area[0] = str_replace('<we:msg_entry>', '<we:msg_entry class="merged">', $ex_area[0])
					. '<we:msg_entry class="merged' . (empty($msg[2]) ? '' : ' ' . $msg[2]) . '"' . (empty($msg[1]) ? '' : ' id="' . $msg[1] . '"') . '>' . $area[1] . '</we:msg_entry>';

				$buffer = str_replace(array($msg[0], $ex_area[0]), array('<!REMOVED>', $area[0]), $buffer);
				$one_removed = true;
			}
			else
				$ex_uid = $uid;

			$ex_area = $area;
		}

		if ($one_removed)
			$buffer = preg_replace('~\s*<hr[^>]*>\s*<!REMOVED>~', '', $buffer);
	}










	if (!empty($context['macros']) && strpos($buffer, '<we:') !== false)
	{

		while (strpos($buffer, '<we:') !== false)
		{
			$p = 0;
			while (($p = strpos($buffer, '<we:', $p)) !== false)
			{
				$space = strpos($buffer, ' ', $p);
				$gt = strpos($buffer, '>', $p);
				$code = substr($buffer, $p + 4, min($space, $gt) - $p - 4);
				$end_code = strpos($buffer, '</we:' . strtolower($code), $p + 4);
				$next_code = strpos($buffer, '<we:', $p + 4);

				if ($end_code === false)
					$end_code = strlen($buffer);


				if ($next_code !== false && $end_code > $next_code)
				{
					$p += 4;
					continue;
				}


				$macro = isset($context['macros'][$code]) ? $context['macros'][$code] : array('has_if' => false, 'body' => '');
				$body = str_replace('{body}', substr($buffer, $gt + 1, $end_code - $gt - 1), $macro['body']);


				if (strpos($body, '{') !== false)
				{
					preg_match_all('~([a-z][^\s="]*)="([^"]+)"~', substr($buffer, $p, $gt - $p), $params);


					if (!empty($params))
						foreach ($params[1] as $id => $param)
							$body = str_replace('{' . $param . '}', strpos($params[2][$id], 'htmlsafe::') === 0 ? html_entity_decode(substr($params[2][$id], 10)) : $params[2][$id], $body);
				}


				if ($macro['has_if'])
					while (preg_match_all('~<if:([^>]+)>((?' . '>[^<]+|<(?!/?if:\\1>))*?)</if:\\1>~i', $body, $ifs, PREG_SET_ORDER))
						foreach ($ifs as $ifi)
							$body = str_replace($ifi[0], !empty($params) && in_array($ifi[1], $params[1]) ? $ifi[2] : '', $body);

				$buffer = str_replace(substr($buffer, $p, $end_code + strlen($code) + 6 - $p), $body, $buffer);
			}
		}
	}

	if (!empty($context['skin_replace']))
	{

		foreach ($context['skin_replace'] as $from => $to)
		{

			if ($to[1])
			{
				$buffer = preg_replace('~' . str_replace('~', '\~', $from) . '~si', $to[0], $buffer);
				continue;
			}
			$to = $to[0];
			preg_match('~<we:nested:(\w+)[ /]*>~i', $from, $nested);


			if (empty($nested))
			{
				$buffer = str_replace($from, $to, $buffer);
				continue;
			}




			$nest = $nested[1];
			$nestlen = strlen($nest);
			$split = strpos($from, $nested[0]);
			$from = str_replace($nested[0], '', $from);
			$opener_code = substr($from, 0, $split);
			$closer_code = substr($from, $split);
			$start = 0;

			while ($start !== false)
			{
				$from_start = strpos($buffer, $opener_code, $start);


				if ($from_start === false)
					break;


				$p = $offset = $from_start + $split;
				$nestlevel = 0;


				while (($test1 = strpos($buffer, '<' . $nest, $p)) !== false && ($buffer[$test1 + $nestlen + 1] !== ' ' && $buffer[$test1 + $nestlen + 1] !== '>'));
				$from_end = strpos($buffer, $closer_code, $p);
				$do_test = $test1 !== false && $from_end !== false && $test1 < $from_end;
				$next_closer = $from_end;

				while ($do_test)
				{
					$next_opener = $p;
					while (($next_opener = strpos($buffer, '<' . $nest, $next_opener)) !== false && ($buffer[$next_opener + $nestlen + 1] !== ' ' && $buffer[$next_opener + $nestlen + 1] !== '>'));
					$next_closer = strpos($buffer, '</' . $nest . '>', $p);

					if ($next_closer === false)
						break;

					if ($next_opener === false)
						$next_opener = $next_closer + 1;

					$p = min($next_opener, $next_closer) + 1;
					$nestlevel += $next_opener < $next_closer ? 1 : -1;

					if ($nestlevel < 0)
						break;
				}


				if ($next_closer !== false)
				{
					$actual_replace = str_replace($nested[0], substr($buffer, $offset, $next_closer - $offset), $to);
					$buffer = substr_replace(
						$buffer,
						$actual_replace,
						$offset - $split,
						strlen($from) + $next_closer - $offset
					);
				}


				$start = $offset - $split + strlen($actual_replace);
				unset($actual_replace);
			}
		}
	}


	$buffer = str_replace('<URL>', SCRIPT, $buffer);


	$pr = isset($context['ob_replacements']) ? $context['ob_replacements'] : array();
	if (!empty($settings['page_replacements']) && ($extra_pr = unserialize($settings['page_replacements'])) !== false)
		$pr = array_merge($pr, $extra_pr);
	if (!empty($pr))
		$buffer = str_replace(array_keys($pr), array_values($pr), $buffer);


	if (($members_groups = cache_get_data('member-groups', 5000)) === null)
	{

		$possible_groups = array();
		$request = wesql::query('
			SELECT g.id_group
			FROM {db_prefix}membergroups AS g
			WHERE g.online_color != {string:blank} OR g.format != {string:blank}',
			array(
				'blank' => '',
			)
		);
		while ($row = wesql::fetch_row($request))
			$possible_groups[$row[0]] = true;
		wesql::free_result($request);


		$ban_group = !empty($settings['ban_group']) && isset($possible_groups[$settings['ban_group']]) ? $settings['ban_group'] : 0;
		$ban_level = 100000;
		if ($ban_group)
		{
			$inf_levels = !empty($settings['infraction_levels']) ? unserialize($settings['infraction_levels']) : array();
			if (!empty($inf_levels['hard_ban']['enabled']))
				$ban_level = $inf_levels['hard_ban']['points'];
		}


		$members_groups = array();
		$request = wesql::query('
			SELECT m.id_member, ' . (!empty($ban_group) ? 'm.is_activated, m.warning, m.data, ' : '') . 'm.id_post_group, m.id_group
			FROM {db_prefix}members AS m',
			array(
				'blank' => '',
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if ($ban_group)
			{
				if ($row['is_activated'] >= 20 || $row['warning'] >= $ban_level)
				{
					$members_groups[$row['id_member']] = $ban_group;
					continue;
				}

				if ($row['warning'] > 0)
				{
					$data = !empty($row['data']) ? unserialize($row['data']) : array();
					if (!empty($data['sanctions']['hard_ban']) && ($data['sanctions']['hard_ban'] == 1 || $data['sanctions']['hard_ban'] > time()))
					{
						$members_groups[$row['id_member']] = $ban_group;
						continue;
					}
				}
			}

			if (isset($possible_groups[$row['id_group']]))
				$members_groups[$row['id_member']] = $row['id_group'];
			elseif (isset($possible_groups[$row['id_post_group']]))
				$members_groups[$row['id_post_group']] = $row['id_post_group'];
		}
		wesql::free_result($request);
		cache_put_data('member-groups', $members_groups, 5000);
	}


	if (!allowedTo('profile_view_any'))
		$buffer = preg_replace(
			'~<a\b[^>]+href="' . $preg_scripturl . '\?(?:[^"]+)?\baction=profile' . (we::$is_member && allowedTo('profile_view_own') ? ';(?:[^"]+;)?u=(?!' . we::$id . ')' : '') . '[^"]*"[^>]*>(.*?)</a>~',
			'$1', $buffer
		);

	else
		$buffer = preg_replace_callback(
			'~<a\b([^>]+href="' . $preg_scripturl . '\?(?:[^"]+)?\baction=profile;(?:[^"]+;)?u=(\d+)"[^>]*)>(.*?)</a>~',
			'wedge_profile_colors', $buffer
		);


	$buffer = preg_replace(
		'~(<a\b[^>]+href="' . $preg_scripturl . '\?(?:[^"]+)?\btopic=\d+[^"]*"[^>]*>)(' . (isset($context['response_prefix']) ? preg_quote($context['response_prefix'], '~') : 'Re:\s') . ')?((?:\[[^]<>]*]\s*)+)(.+?</a>)~',
		'$3 $1$2$4', $buffer
	);


	if (empty($_COOKIE) && SID != '' && empty($context['no_sid_thank_you']) && !we::$browser['possibly_robot'])
	{
		$buffer = preg_replace('~(?<!<link rel="canonical" href=")' . $preg_scripturl . '(?!\?' . preg_quote(SID, '~') . ')(?:\?|(?="))~', SCRIPT . '?' . SID . ';', $buffer);
		$buffer = str_replace('"' . SCRIPT . '?' . SID . ';"', '"' . SCRIPT . '?' . SID . '"', $buffer);
	}

	elseif (isset($_GET['debug']))
		$buffer = preg_replace('~(?<!<link rel="canonical" href=")"' . $preg_scripturl . '\??~', SCRIPT . '?debug;', $buffer);


	if (!empty($settings['pretty_enable_filters']))
	{
		$use_cache = !empty($settings['pretty_enable_cache']);
		$session_var = $context['session_query'];



		$context['pretty']['patterns'][] =  '~(?<=["\'>])' . $preg_scripturl . '([?;&](?:[^"\'#]*?[;&])?(board|topic|action|category)=[^"\'<#]+)~';
		$urls_query = array();
		$uncached_urls = array();


		$context['pretty']['patterns'] = array_flip(array_flip($context['pretty']['patterns']));

		foreach ($context['pretty']['patterns'] as $pattern)
		{
			preg_match_all($pattern, $buffer, $matches);
			foreach ($matches[1] as $match)
			{



				if ($use_cache)
				{
					$match = str_replace(SID ? array(SID, $session_var) : $session_var, '', $match);
					$match = preg_replace(array('~=?;+~', '~\?&amp;~'), array(';', '?'), rtrim($match, '&?;'));
				}
				else
					$match = preg_replace('~=?;+~', ';', rtrim($match, '&?;'));
				$match = str_replace(array('"', '?;'), array('%22', '?'), $match);
				$url_id = $match;
				$urls_query[] = $url_id;
				$uncached_urls[$match] = array(
					'url' => $match,
				);
			}
		}


		if (count($urls_query) != 0)
		{

			$urls_query = array_flip(array_flip($urls_query));


			$cached_urls = array();

			if ($use_cache)
			{
				$query = wesql::query('
					SELECT url_id, replacement
					FROM {db_prefix}pretty_urls_cache
					WHERE url_id IN ({array_string:urls})
						AND log_time > ' . (int) (time() - 86400),
					array(
						'urls' => $urls_query
					)
				);
				while ($row = wesql::fetch_assoc($query))
				{
					$cached_urls[$row['url_id']] = $row['replacement'];
					unset($uncached_urls[$row['url_id']]);
				}
				wesql::free_result($query);
			}


			if (count($uncached_urls) != 0)
			{

				loadSource('PrettyUrls-Filters');

				foreach (array_filter($settings['pretty_filters']) as $id => $dummy)
				{
					$func = 'pretty_filter_' . $id;
					$func($uncached_urls);
				}


				$cache_data = array();
				foreach ($uncached_urls as $url_id => $url)
				{
					if (!isset($url['replacement']))
						$url['replacement'] = $url['url'];
					$url['replacement'] = str_replace("\x12", "'", $url['replacement']);
					$url['replacement'] = preg_replace(array('~"~', '~=?;+~', '~\?;~'), array('%22', ';', '?'), rtrim($url['replacement'], '&?;'));
					$cached_urls[$url_id] = $url['replacement'];
					if ($use_cache && strlen($url_id) < 256)
						$cache_data[] = array($url_id, $url['replacement']);
				}


				if ($use_cache && count($cache_data) > 0)
					wesql::insert('replace',
						'{db_prefix}pretty_urls_cache',
						array('url_id' => 'string', 'replacement' => 'string'),
						$cache_data
					);
			}


			foreach ($context['pretty']['patterns'] as $pattern)
				$buffer = preg_replace_callback($pattern, 'pretty_buffer_callback', $buffer);
		}
	}

	if (!empty($context['debugging_info']))
		$buffer = substr_replace($buffer, $context['debugging_info'], strrpos($buffer, '</ul>') + 5, 0);


	if (!empty($context['show_load_time']))
	{
		$new_load_time = microtime(true);
		$loadTime = $txt['page_created'] . sprintf($txt['seconds_with_' . ($db_count > 1 ? 'queries' : 'query')], $new_load_time - $time_start, $db_count);
		$queriesDiff = $db_count - $old_db_count;
		if (we::$is_admin)
			$loadTime .= ' (' . $txt['dynamic_replacements'] . ': ' . sprintf($txt['seconds_with_' . ($queriesDiff > 1 ? 'queries' : 'query')], $new_load_time - $old_load_time, $queriesDiff) . ')';
		$buffer = str_replace('<!-- insert stats here -->', $loadTime, $buffer);
	}


	if (strpos($buffer, '<inden@zi=') !== false)
	{

		preg_match_all('~(?:<textarea\b.*?</textarea>|<pre\b.*?</pre>)~s', $buffer, $protect);
		if (!empty($protect))
			$buffer = str_replace($protect[0], "\x18", $buffer);

		$max_loops = 100;
		while (strpos($buffer, '<inden@zi=') !== false && $max_loops-- > 0)
			$buffer = preg_replace_callback('~<inden@zi=([^=>]+)=(-?\d+)>(.*?)</inden@zi=\\1>~s', 'wedge_indenazi', $buffer);

		if (!empty($protect))
			foreach ($protect[0] as $item)
				$buffer = preg_replace("~\x18~", $item, $buffer, 1);
	}


	if (!empty($settings['minify_html']))
		$buffer = preg_replace("~\n\t+~", "\n", $buffer);



	if (we::$user['language'] != 'english' && we::is('ie && windows[-5.2],mobile'))
		$buffer = str_replace("\xe2\x80\xaf", "\xc2\xa0", $buffer);


	if (empty($context['no_strip_domain']))
	{
		$buffer = preg_replace('~(<[^>]+\s(?:href|src|action)=")' . preg_quote(we::$user['server'], '~') . '/(?!/)~', '$1/', $buffer);


		$strip_protocol = '(<[^>]+\s(?:href|src|action)=")' . preg_quote(substr(we::$user['server'], 0, strpos(we::$user['server'], '://')), '~') . '://';
		if (we::$browser['ie8down'])
			$buffer = preg_replace('~' . $strip_protocol . '((?:[^.]|\.(?!css))*?")~', '$1//$2', $buffer);
		else
			$buffer = preg_replace('~' . $strip_protocol . '~', '$1//', $buffer);
	}



	if (isset(we::$ua) && strpos(strtolower(we::$ua), 'validator') !== false)
		$buffer = preg_replace('~<img\s((?:[^a>]|a(?!lt\b))+)>~', '<img alt $1>', $buffer);

	if ($skip_it)
		$buffer = str_replace('<ob:ignored>', $skip_buffer, $buffer);


	return preg_replace("~\s</script>\s*<script>|\s<script>\s*</script>~", '', $buffer);
}


function wedge_event_delayer($match)
{
	global $context;
	static $eve = 1, $dupes = array();

	if ($eve == 1 && INFINITE)
		$eve = 100 * (isset($_GET['start']) ? $_GET['start'] / 15 : 0) + 1;

	$eve_list = array();
	preg_match_all('~\son(\w+)="([^"]+)"~', $match[0], $insides, PREG_SET_ORDER);
	foreach ($insides as $inside)
	{
		$match[0] = str_replace($inside[0], '', $match[0]);
		$dupe = serialize($inside);
		if (!isset($dupes[$dupe]))
		{



			$context['delayed_events'][$eve] = array($eve, $inside[1], str_replace(array('&quot;', '\\\\n'), array('"', '\\n'), $inside[2]));
			$dupes[$dupe] = $eve;
			$eve_list[] = $eve++;
		}
		else
			$eve_list[] = $dupes[$dupe];
	}
	return rtrim($match[0], ' />') . ' data-eve="' . implode(' ', $eve_list) . '">';
}

function wedge_profile_colors($match)
{
	global $members_groups;

	if (!isset($members_groups[$match[2]]) || strpos($match[1], 'bbc_link') !== false)
		return '<a' . $match[1] . '>' . $match[3] . '</a>';

	$pos = strpos($match[1], 'class="');
	if ($pos > 0)
		return '<a' . substr($match[1], 0, $pos + 7) . 'group' . $members_groups[$match[2]] . ' ' . substr($match[1], $pos + 7) . '>' . $match[3] . '</a>';
	else
		return '<a' . $match[1] . ' class="group' . $members_groups[$match[2]] . '">' . $match[3] . '</a>';
}

function wedge_indenazi($match)
{
	if ($match[2] < 0)
		return preg_replace('~(\n\t*?)\t' . ($match[2] < -1 ? '{1,' . -$match[2] . '}' : '') . '(?=[<a-zA-Z0-9])~', '$1', $match[3]);
	return preg_replace('~(\n\t*)(?=[<a-zA-Z0-9])~', '$1' . str_repeat("\t", $match[2]), $match[3]);
}


function pretty_buffer_callback($matches)
{
	global $cached_urls, $use_cache, $session_var;
	static $immediate_cache = array();

	if (isset($immediate_cache[$matches[0]]))
		return $immediate_cache[$matches[0]];

	if ($use_cache)
	{

		$has_sid = SID && strpos($matches[1], SID) !== false;
		$has_sesc = strpos($matches[1], $session_var) !== false;
		$url_id = preg_replace(
			'~=?;+~',
			';',
			str_replace(
				array('"', '?;', SID, $session_var),
				array('%22', '?', '', ''),
				rtrim($matches[1], '&?;')
			)
		);

		$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
		if ($has_sid)
			$replacement .= (strpos($replacement, '?') === false ? '?' : ';') . SID;
		if ($has_sesc)
			$replacement .= (strpos($replacement, '?') === false ? '?' : ';') . $session_var;
	}
	else
	{

		$url_id = str_replace(array('"', '?;'), array('%22', '?'), preg_replace('~=?;+~', ';', rtrim($matches[1], '&?;')));

		$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
	}

	$immediate_cache[$matches[0]] = $replacement;
	if (empty($replacement) || $replacement[0] == '?')
		$replacement = SCRIPT . $replacement;
	return $replacement;
}


function add_replacement($from, $to)
{
	global $context;
	if (!isset($context['ob_replacements']))
		$context['ob_replacements'] = array();
	$context['ob_replacements'][$from] = $to;
}






function clean_output($skip_full = false)
{
	global $settings;

	ob_end_clean();
	if (!empty($settings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();


	if (!$skip_full || !empty($settings['pretty_enable_filters']))
		ob_start('ob_sessrewrite');
}









function start_output()
{
	global $settings, $context;

	if (!AJAX)
		setupThemeContext();


	if (empty($context['no_last_modified']))
	{
		header('Expires: Wed, 25 Aug 2010 17:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		if (!AJAX)
			header('Content-Type: text/html; charset=UTF-8');
	}

	header('Content-Type: text/' . (AJAX ? 'xml' : 'html') . '; charset=UTF-8');

	$context['show_load_time'] = !empty($settings['timeLoadPageEnable']) || we::$is_admin;
}







function while_we_re_here()
{
	global $txt, $settings, $context;
	static $checked_security_files = false, $showed_banned = false, $showed_behav_error = false;


	if (AJAX)
		return;



	if (!$showed_behav_error && we::$is_admin && !empty($context['behavior_error']))
	{
		$showed_behav_error = true;
		loadLanguage('Security');

		echo '
			<div class="errorbox">
				<p class="alert">!!</p>
				<h3>', $txt['behavior_admin'], '</h3>
				<p>', $txt[$context['behavior_error'] . '_log'], '</p>
			</div>';
	}
	elseif (!$checked_security_files && we::$is_member && allowedTo('admin_forum'))
	{
		$checked_security_files = true;
		$security_files = array('import.php', 'Settings.php~', 'Settings_bak.php~');

		foreach ($security_files as $i => $security_file)
			if (!file_exists(ROOT_DIR . '/' . $security_file))
				unset($security_files[$i]);

		if (!empty($security_files) || $cache_not_writable = (!empty($settings['cache_enable']) && !is_writable(CACHE_DIR)))
		{
			loadLanguage('Errors');

			echo '
			<div class="errorbox">
				<p class="alert">!!</p>
				<h3>', empty($security_files) ? $txt['cache_writable_head'] : $txt['security_risk'], '</h3>
				<p>';

			foreach ($security_files as $security_file)
			{
				echo '
					', sprintf($txt['not_removed'], $security_file), '<br>';

				if ($security_file == 'Settings.php~' || $security_file == 'Settings_bak.php~')
					echo '
					', sprintf($txt['not_removed_extra'], $security_file, substr($security_file, 0, -1)), '<br>';
			}

			if (!empty($cache_not_writable))
				echo '
					<strong>', $txt['cache_writable'], '</strong><br>';

			echo '
				</p>
			</div>';
		}
	}

	elseif (!$showed_banned && (!empty(we::$user['post_banned']) || !empty(we::$user['pm_banned'])))
	{
		$showed_banned = true;
		$str = !empty(we::$user['post_banned']) ? (!empty(we::$user['pm_banned']) ? $txt['you_are_post_pm_banned'] : $txt['you_are_post_banned']) : $txt['you_are_pm_banned'];
		echo '
			<div class="windowbg wrc alert" style="margin: 2ex; padding: 2ex; border: 2px dashed red">
				', sprintf($str, we::$is_guest ? $txt['guest_title'] : we::$user['name']);

		if (!empty(we::$user['data']['ban_reason']))
			echo '
				<div style="padding-left: 4ex; padding-top: 1ex">', we::$user['data']['ban_reason'], '</div>';

		$expiry = array();
		foreach (array('post_ban', 'pm_ban') as $item)
			if (!empty(we::$user['sanctions'][$item]))
				$expiry[] = we::$user['sanctions'][$item];
		$expiry_time = min($expiry);
		if ($expiry_time != 1)
			echo '
				<div>', sprintf($txt['your_ban_expires'], timeformat($expiry_time, false)), '</div>';
		else
			echo '
				<div>', $txt['your_ban_expires_never'], '</div>';

		echo '
			</div>';
	}
}















function db_debug_junk()
{
	global $txt, $settings, $context;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits;


	$show_debug = $show_debug_query = !empty($db_show_debug) && $context['action'] !== 'viewquery' && $context['action'] !== 'help';


	if (empty($settings['db_show_debug_who']) || $settings['db_show_debug_who'] == 'admin')
		$show_debug &= we::$is_admin;
	elseif ($settings['db_show_debug_who'] == 'mod')
		$show_debug &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who'] == 'regular')
		$show_debug &= we::$is_member;
	else
		$show_debug &= $settings['db_show_debug_who'] == 'any';


	if (empty($settings['db_show_debug_who_log']) || $settings['db_show_debug_who_log'] == 'admin')
		$show_debug_query &= we::$is_admin;
	elseif ($settings['db_show_debug_who_log'] == 'mod')
		$show_debug_query &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who_log'] == 'regular')
		$show_debug_query &= we::$is_member;
	else
		$show_debug_query &= $settings['db_show_debug_who_log'] == 'any';


	if (!$show_debug_query)
	{
		unset($_SESSION['debug'], $db_cache);
		$_SESSION['view_queries'] = 0;
		if (!$show_debug)
			return;
	}

	loadLanguage('Stats');

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;

	$temp = '
	<div id="junk">';

	if ($show_debug)
	{
		if (empty($context['debug']['language_files']))
			$context['debug']['language_files'] = array();
		if (empty($context['debug']['sheets']))
			$context['debug']['sheets'] = array();

		$files = get_included_files();
		$total_size = 0;
		for ($i = 0, $n = count($files); $i < $n; $i++)
		{
			if (file_exists($files[$i]))
				$total_size += filesize($files[$i]);
			$files[$i] = strtr($files[$i], array(ROOT_DIR => '.'));
		}


		foreach ($context['debug']['blocks'] as $name => $count)
			$context['debug']['blocks'][$name] = $count > 1 ? $name . ' (' . $count . 'x)' : $name;

		$show_list_js = "$(this).hide().next().show(); return false;";
		$temp .= sprintf(
			$txt['debug_report'],
			count($context['debug']['templates']),		implode(', ', $context['debug']['templates']),
			count($context['debug']['blocks']),			implode(', ', $context['debug']['blocks']),
			count($context['debug']['language_files']),	implode(', ', $context['debug']['language_files']),
			count($context['debug']['sheets']),			implode(', ', $context['debug']['sheets']),
			count($files), round($total_size / 1024), $show_list_js, implode(', ', $files),
			ceil(memory_get_peak_usage() / 1024)
		);

		if (!empty($settings['cache_enable']) && !empty($cache_hits))
		{
			$entries = array();
			$total_t = 0;
			$total_s = 0;
			foreach ($cache_hits as $cache_hit)
			{
				$entries[] = sprintf($txt['debug_cache_seconds_bytes'], $cache_hit['d'] . ' ' . $cache_hit['k'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
				$total_t += $cache_hit['t'];
				$total_s += $cache_hit['s'];
			}
			$temp .= sprintf($txt['debug_cache_hits'], $cache_count, comma_format($total_t, 5), comma_format($total_s), $show_list_js, implode(', ', $entries));
		}
		$temp .= '<br>';
	}

	$warnings = 0;
	if (!empty($db_cache))
	{
		foreach ($db_cache as $q => $qq)
			if (!empty($qq['w']))
				$warnings += count($qq['w']);

		$_SESSION['debug'] =& $db_cache;
	}

	if ($show_debug_query)
	{
		$temp .= '<a href="' . SCRIPT . '?action=viewquery" target="_blank" class="new_win">' . sprintf($txt['debug_queries_used' . ($warnings == 0 ? '' : '_and_warnings')], $db_count, $warnings) . '</a> - <a href="' . SCRIPT . '?action=viewquery;sa=hide">' . $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'] . '</a>';


		$temp .= ' - <a href="//validator.w3.org/check?uri=referer" target="_blank" class="new_win">' . $txt['html5_validation'] . '</a>';
	}
	else
		$temp .= sprintf($txt['debug_queries_used'], $db_count);

	if ($_SESSION['view_queries'] == 1 && !empty($db_cache))
	{
		$temp .= '<br><br>';

		foreach ($db_cache as $q => $qq)
		{
			$is_select = substr(trim($qq['q']), 0, 6) == 'SELECT' || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($qq['q'])) != 0;

			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($qq['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}

			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($qq['q'])) != 0)
				$is_select = true;


			if (isset($qq['f']))
				$qq['f'] = preg_replace('~^' . preg_quote(ROOT_DIR, '~') . '~', '...', $qq['f']);

			$temp .= '
	<strong>' . ($is_select ? '<a href="' . SCRIPT . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win">' : '') . westr::nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r")))) . ($is_select ? '</a></strong>' : '</strong>') . '<br>
	&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				$temp .= sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t'], $txt['debug_query_which_took_at']))
				$temp .= sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br>';
			elseif (isset($qq['t']))
				$temp .= sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br>';
			$temp .= '<br>';
		}
	}

	$context['debugging_info'] = $temp . '
</div>';
}










function template_include($filename, $once = false, $no_caching = false)
{
	global $settings, $txt;
	static $templates = array();


	ini_set('track_errors', '1');


	if ($once && in_array($filename, $templates))
		return;


	$templates[] = $filename;

	$cache = $no_caching ? $filename : ROOT_DIR . '/gz/html/' . str_replace(array(ROOT_DIR . '/', '/', '..'), array('', '_', 'UP'), $filename);
	$settings['current_include_filename'] = $cache;
	$file_found = file_exists($cache);

	if (!$no_caching && (!$file_found || filemtime($cache) < filemtime($filename)))
	{
		if (!file_exists($filename))
		{
			loadSource('ManageErrors');
			handleTemplateErrors($filename);
		}
		loadSource('Subs-CachePHP');
		cache_source_file($filename, $cache);

		$file_found = file_exists($cache) && eval('?' . '>' . rtrim(file_get_contents($cache))) !== false;
	}
	elseif ($once && $file_found)
		require_once($cache);
	elseif ($file_found)
		require($cache);
	else
	{
		loadSource('ManageErrors');
		handleTemplateErrors($cache);
	}
}











function loadTemplate($template_name, $fatal = true)
{
	global $context, $txt, $db_show_debug;


	if ($template_name === false)
		return true;

	if (!is_string($template_name) && is_callable($template_name))
	{
		loadTemplate('Generic');
		$context['closure'] = $template_name;
		return true;
	}

	$loaded = false;
	foreach ($context['template_folders'] as $template_dir)
	{
		if (file_exists($template_dir . '/' . $template_name . '.template.php'))
		{
			$loaded = true;
			template_include($template_dir . '/' . $template_name . '.template.php', true);
			break;
		}
	}

	if ($loaded)
	{
		if (!empty($db_show_debug))
			$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';


		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}

	elseif ($fatal)
	{
		if ($template_name != 'Errors' && $template_name != 'index')
			fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
		else
			exit(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load core/html/%s.template.php!', (string) $template_name), 'template'));
	}
	else
		return false;
}











function execBlock($block_name, $fatal = false)
{
	global $context, $txt, $db_show_debug;

	if (empty($block_name))
		return;

	if (!empty($db_show_debug))
		$context['debug']['blocks'][$block_name] = isset($context['debug']['blocks'][$block_name]) ? $context['debug']['blocks'][$block_name] + 1 : 1;

	if (strpos($block_name, ':') !== false)
	{
		list ($block_name, $vars) = explode(':', $block_name, 2);
		$vars = array_map('trim', explode(',', $vars));
	}
	else
		$vars = array();


	$theme_function = 'template_' . $block_name;

	if (isset($context['template_befores'][$theme_function]))
	{
		$func =& $context['template_befores'][$theme_function];
		if (is_array($func))
			$func = create_function($func[0], $func[1]);
		call_user_func_array($func, $vars);
	}


	elseif (function_exists($theme_function_before = $theme_function . '_before'))
		call_user_func_array($theme_function_before, $vars);

	if (isset($context['template_overrides'][$theme_function]))
	{
		$func =& $context['template_overrides'][$theme_function];
		if (is_array($func))
			$func = create_function($func[0], $func[1]);
		call_user_func_array($func, $vars);
	}
	elseif (function_exists($theme_function_override = $theme_function . '_override'))
		call_user_func_array($theme_function_override, $vars);
	elseif (function_exists($theme_function))
		call_user_func_array($theme_function, $vars);
	elseif ($fatal === false)
		fatal_lang_error('template_block_error', 'template', array((string) $block_name));
	elseif ($fatal !== 'ignore')
		exit(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['template_block_error'] : 'Unable to load the "%s" template block!', (string) $block_name), 'template'));

	if (isset($context['template_afters'][$theme_function]))
	{
		$func =& $context['template_afters'][$theme_function];
		if (is_array($func))
			$func = create_function($func[0], $func[1]);
		call_user_func_array($func, $vars);
	}
	elseif (function_exists($theme_function_after = $theme_function . '_after'))
		call_user_func_array($theme_function_after, $vars);


	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && $block_name !== 'init' && ob_get_length() > 0 && !AJAX)
		echo '
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold">---- ', $block_name, ' ends ----</div>';
}
