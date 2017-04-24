<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');













function parse_bbc_inline($message, $type = 'generic', $bbc_options = array(), $short_list = false)
{
	if ($type === (array) $type)
	{
		$bbc_options = $type;
		$type = 'generic';
	}

	$bbc_options['tags'] = $short_list ?
		array(
			'b', 'u', 'i', 's',
			'email', 'ftp', 'iurl', 'url', 'nobbc',
		) :
		array(
			'b', 'u', 'i', 's', 'tt',
			'email', 'ftp', 'iurl', 'url', 'nobbc',
			'abbr', 'me', 'sub', 'sup', 'time', 'color',

		);

	return parse_bbc($message, $type, $bbc_options);
}























































function parse_bbc($message, $type = 'generic', $bbc_options = array())
{
	global $txt, $context, $settings, $user_profile;
	static $bbc_codes = array(), $bbc_types = array(), $itemcodes = array(), $no_autolink_tags = array();
	static $master_codes = null, $strlower = null, $disabled, $feet = 0;


	if ($message === '')
		return '';


	if ($type === (array) $type)
	{
		$bbc_options = $type;
		$type = 'generic';
	}


	$smileys = !isset($bbc_options['smileys']) ? true : !empty($bbc_options['smileys']);
	$parse_tags = !empty($bbc_options['tags']) ? $bbc_options['tags'] : array();
	$print = !empty($bbc_options['print']);
	$owner = !empty($bbc_options['user']) ? $bbc_options['user'] : 0;
	$cache_id = !empty($bbc_options['cache']) ? $bbc_options['cache'] : '';

	if (empty($settings['enableBBC']) && $message !== false)
	{
		if ($smileys === true)
			parsesmileys($message);

		return $message;
	}

	$strlower = array_combine(range(' ', "\xFF"), str_split(strtolower(implode('', range(' ', "\xFF")))));


	$master_codes = loadBBCodes();


	if (!empty($parse_tags) && !empty($bbc_codes))
	{
		$temp_bbc = $bbc_codes;
		$bbc_codes = array();
	}

	if (empty($parse_tags))
	{
		if (empty($disabled['media']) && stripos($message, '[media') !== false)
		{
			loadSource('media/Subs-Media');
			aeva_protect_bbc($message);
		}
	}


	if (empty($bbc_codes) || $message === false || !empty($parse_tags))
	{
		if (!empty($settings['disabledBBC']))
			foreach (explode(',', strtolower($settings['disabledBBC'])) as $tag)
				$disabled[trim($tag)] = true;

		if (empty($settings['enableEmbeddedFlash']))
			$disabled['flash'] = true;


		if ($message === false)
		{
			if (isset($temp_bbc))
				$bbc_codes = $temp_bbc;
			return $master_codes;
		}


		$itemcodes = array(
			'*' => 'disc',
			'@' => 'disc',
			'+' => 'square',
			'x' => 'square',
			'#' => 'square',
			'o' => 'circle',
			'O' => 'circle',
			'0' => 'circle',
		);

		if (!isset($disabled['li']) && !isset($disabled['list']))
			foreach ($itemcodes as $c => $dummy)
				$bbc_codes[$c] = array();


		$no_autolink_tags = array_flip(array('url', 'iurl', 'ftp', 'email'));


		$bbc_types = array_flip(array('unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals'));


		foreach ($master_codes as $code)
			if (empty($parse_tags) || in_array($code['tag'], $parse_tags))
				$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
	}


	if (empty($parse_tags))
		$message = preg_replace('~(?:<br>|&nbsp;|\s)*(\[noae])?\[(/?)quote\b([^]]*)](\[/noae])?(?:<br>|&nbsp;|\s)*~is', '$1[$2quote$3]$4', $message);






	if ($cache_id != '' && !empty($settings['cache_enable']) && (($settings['cache_enable'] >= 2 && strlen($message) > 1000) || strlen($message) > 2400)
		&& empty($parse_tags) && ($type == 'signature' || (strpos($message, 'http://') === false)))
	{

		$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled)))
					. serialize(we::$browser) . $txt['lang_locale'] . we::$user['time_offset'] . we::$user['time_format']);

		if (($temp = cache_get_data($cache_key, 240)) !== null)
			return $temp;

		$cache_t = microtime(true);
	}

	if ($print)
	{


		foreach	(array('color', 'black', 'blue', 'white', 'red', 'green', 'me', 'php', 'ftp', 'url', 'iurl', 'email', 'flash') as $disable)
			$disabled[$disable] = true;


		if (!isset($_GET['images']))
			$disabled['img'] = true;


	}

	$open_tags = array();
	$message = strtr($message, array("\n" => '<br>'));

	$pos = -1;

	while ($pos !== false)
	{
		$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
		$pos = strpos($message, '[', $pos + 1);


		if ($pos === false || $last_pos > $pos)
			$pos = strlen($message) + 1;


		if ($last_pos < $pos - 1)
		{

			$last_pos = max($last_pos, 0);


			$data = $orig_data = substr($message, $last_pos, $pos - $last_pos);


			if (!empty($settings['enablePostHTML']) && strpos($data, '&lt;') !== false)
			{
				$data = preg_replace('~&lt;a\s+href=(&quot;)?((?:https?://|ftps?://|mailto:)\S+?)\\1&gt;~i', '[url=$2]', $data);
				$data = preg_replace('~&lt;/a&gt;~i', '[/url]', $data);


				$data = str_replace(array('&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'), '[br]', $data);
				$data = str_replace(array('&lt;hr&gt;', '&lt;hr/&gt;', '&lt;hr /&gt;'), '[hr]', $data);


				foreach (array('b', 'u', 'i', 's', 'em', 'pre', 'blockquote') as $tag)
				{
					$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
					$data = strtr($data, array('&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>'));

					if ($diff > 0)
						$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
				}


				preg_match_all('~&lt;img\s+src=(&quot;)?((?:https?://|ftps?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s*/)?&gt;~i', $data, $matches);
				if (!empty($matches[0]))
				{
					$replaces = array();
					foreach ($matches[2] as $match => $imgtag)
					{
						$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);


						if (preg_match('~\baction(?:=|%3d)(?!dlattach|media)~i', $imgtag) === 1)
							$imgtag = preg_replace('~\baction(?:=|%3d)(?!dlattach|media)~i', 'action-', $imgtag);


						if (!empty($settings['max_image_width']) || !empty($settings['max_image_height']))
						{
							list ($width, $height) = url_image_size($imgtag);

							if (!empty($settings['max_image_width']) && $width > $settings['max_image_width'])
							{
								$height = (int) (($settings['max_image_width'] * $height) / $width);
								$width = $settings['max_image_width'];
							}

							if (!empty($settings['max_image_height']) && $height > $settings['max_image_height'])
							{
								$width = (int) (($settings['max_image_height'] * $width) / $height);
								$height = $settings['max_image_height'];
							}


							$replaces[$matches[0][$match]] = '[img width=' . $width . ' height=' . $height . $alt . ']' . $imgtag . '[/img]';
						}
						else
							$replaces[$matches[0][$match]] = '[img' . $alt . ']' . $imgtag . '[/img]';
					}

					$data = strtr($data, $replaces);
				}
			}

			if (!empty($settings['autoLinkUrls']))
			{

				$no_autolink_area = false;
				foreach ($open_tags as $open_tag)
					if (isset($no_autolink_tags[$open_tag['tag']]))
						$no_autolink_area = true;



				if (isset($lastAutoPos) && $pos < $lastAutoPos)
					$no_autolink_area = true;
				$lastAutoPos = $pos;

				if (!$no_autolink_area)
				{

					if (!isset($disabled['url']) && strhas($data, array('://', 'www.')) && strpos($data, '[url') === false)
					{

						$data = strtr($data, array('&#039;' => '\'', '&nbsp;' => "\xC2\xA0", '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<'));


						if (is_string($result = preg_replace(array(
							'`(?<=[\s>.(;\'"]|^)(https?://[\w%@:|-]+(?:\.[\w%-]+)*(?::\d+)?(?:/[\w~%.@,?&;=#+:\'\\\\!(){}-]*)*[/\w~%@?;=#}\\\\-])`i',
							'`(?<=[\s>.(;\'"]|^)(ftps?://[\w%@:|-]+(?:\.[\w%-]+)*(?::\d+)?(?:/[\w~%.@,?&;=#(){}+:\'\\\\-]*)*[/\w~%@?;=#}\\\\-])`i',
							'`(?<=[\s>(\'<]|^)(www(?:\.[\w-]+)+(?::\d+)?(?:/[\w~%.@!,?&;=#(){}+:\'\\\\-]*)*[/\w~%@?;=#}\\\\-])`i'
						), array(
							'[url]$1[/url]',
							'[ftp]$1[/ftp]',
							'[url=http://$1]$1[/url]'
						), $data)))
							$data = $result;

						$data = strtr($data, array('\'' => '&#039;', "\xC2\xA0" => '&nbsp;', '>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;'));
					}


					if (!isset($disabled['email']) && strpos($data, '@') !== false && strpos($data, '[email') === false)
					{
						$data = preg_replace('~(?<=[?\s\x{A0}[\]()*\\\;>]|^)([\w.-]{1,80}@[\w-]+\.[\w-]+[\w-])(?=[?,\s\x{A0}[\]()*\\\]|$|<br>|&nbsp;|&gt;|&lt;|&quot;|&#039;|\.(?:\.|;|&nbsp;|\s|$|<br>))~u', '[email]$1[/email]', $data);
						$data = preg_replace('~(?<=<br>)([\w.-]{1,80}@[\w-]+\.[\w.-]+[\w-])(?=[?.,;\s\x{A0}[\]()*\\\]|$|<br>|&nbsp;|&gt;|&lt;|&quot;|&#039;)~u', '[email]$1[/email]', $data);
					}
				}
			}

			$data = strtr($data, array("\t" => '&nbsp;&nbsp;&nbsp;'));


			if ($data != $orig_data)
			{
				$message = substr($message, 0, $last_pos) . $data . substr($message, $pos);


				$old_pos = strlen($data) + $last_pos;
				$pos = strpos($message, '[', $last_pos);
				$pos = $pos === false ? $old_pos : min($pos, $old_pos);
			}
		}


		if ($pos >= strlen($message) - 1)
			break;

		$tags = $strlower[$message[$pos + 1]];

		if ($tags === '/' && !empty($open_tags))
		{
			$pos2 = strpos($message, ']', $pos + 1);
			if ($pos2 === $pos + 2)
				continue;
			$look_for = strtolower(substr($message, $pos + 2, $pos2 - $pos - 2));

			$to_close = array();
			$block_level = null;
			do
			{
				$tag = array_pop($open_tags);
				if (!$tag)
					break;

				if ($tag['block_level'])
				{

					if ($block_level === false)
					{
						array_push($open_tags, $tag);
						break;
					}


					if ($look_for !== '' && isset($bbc_codes[$look_for[0]]))
					{
						foreach ($bbc_codes[$look_for[0]] as $temp)
							if ($temp['tag'] === $look_for)
							{
								$block_level = $temp['block_level'];
								break;
							}
					}

					if ($block_level !== true)
					{
						$block_level = false;
						array_push($open_tags, $tag);
						break;
					}
				}

				$to_close[] = $tag;
			}
			while ($tag['tag'] != $look_for);


			if ((empty($open_tags) && (empty($tag) || $tag['tag'] !== $look_for)))
			{
				$open_tags = $to_close;
				continue;
			}
			elseif (!empty($to_close) && $tag['tag'] !== $look_for)
			{
				if ($block_level === null && isset($look_for[0], $bbc_codes[$look_for[0]]))
				{
					foreach ($bbc_codes[$look_for[0]] as $temp)
						if ($temp['tag'] === $look_for)
						{
							$block_level = $temp['block_level'];
							break;
						}
				}


				if (!$block_level)
				{
					foreach ($to_close as $tag)
						array_push($open_tags, $tag);
					continue;
				}
			}

			foreach ($to_close as $tag)
			{
				$message = substr($message, 0, $pos) . "\n" . $tag['after'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['after']) + 2;
				$pos2 = $pos - 1;


				if ($tag['block_level'] && substr($message, $pos, 4) === '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 4);
				if (($tag['trim'] === 'outside' || $tag['trim'] === 'both') && preg_match('~^(?:<br>|&nbsp;|\s)+~', substr($message, $pos), $matches) === 1)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));
			}

			if (!empty($to_close))
			{
				$to_close = array();
				$pos--;
			}

			continue;
		}


		if (!isset($bbc_codes[$tags]))
			continue;

		$inside = empty($open_tags) ? null : $open_tags[count($open_tags) - 1];
		$tag = null;
		foreach ($bbc_codes[$tags] as $possible)
		{

			if (strtolower(substr($message, $pos + 1, $possible['len'])) !== $possible['tag'])
				continue;

			$len = $possible['len'];
			$next_c = $message[$pos + 1 + $len];


			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 2 + $len)) !== 1)
				continue;

			elseif (!empty($possible['parameters']))
			{
				if ($next_c !== ' ')
					continue;
			}
			elseif (isset($possible['type']))
			{

				if (isset($bbc_types[$possible['type']]) && $next_c !== '=')
					continue;

				if ($possible['type'] === 'closed' && $next_c !== ']' && substr($message, $pos + 1 + $len, 2) !== '/]' && substr($message, $pos + 1 + $len, 3) !== ' /]')
					continue;

				if ($possible['type'] === 'unparsed_content' && $next_c !== ']')
					continue;
			}

			elseif ($next_c != ']')
				continue;


			if (isset($possible['require_parents']) && ($inside === null || !in_array($inside['tag'], $possible['require_parents'])))
				continue;
			elseif (isset($inside['require_children']) && !in_array($possible['tag'], $inside['require_children']))
				continue;

			elseif (isset($inside['disallow_children']) && in_array($possible['tag'], $inside['disallow_children']))
				continue;

			$pos1 = $pos + 2 + $len;


			if ($possible['tag'] === 'quote')
			{

				$quote_alt = false;


				foreach ($open_tags as $open_quote)
					if ($open_quote['tag'] === 'quote')
						$quote_alt = !$quote_alt;


				if ($quote_alt)
					$possible['before'] = strtr($possible['before'], array('<div class="bbc_quote">' => '<div class="bbc_quote alternate">'));
			}


			if (!empty($possible['parameters']))
			{
				$preg = array();
				foreach ($possible['parameters'] as $p => $info)
					$preg[] = '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . (isset($info['match']) ? $info['match'] : '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . ')' . (empty($info['optional']) ? '' : '?');


				$match = false;
				$orders = bbc_permute($preg);
				foreach ($orders as $p)
				{
					if (preg_match('~^' . implode('', $p) . '\]~i', substr($message, $pos1 - 1), $matches) === 1)
					{
						$match = true;
						break;
					}
				}


				if (!$match)
					continue;

				$params = array();
				for ($i = 1, $n = count($matches); $i < $n; $i += 2)
				{
					$key = strtok(ltrim($matches[$i]), '=');
					if (isset($possible['parameters'][$key]['value']))
						$params['{' . $key . '}'] = strtr($possible['parameters'][$key]['value'], array('$1' => $matches[$i + 1]));
					elseif (isset($possible['parameters'][$key]['validate']))
						$params['{' . $key . '}'] = $possible['parameters'][$key]['validate']($matches[$i + 1]);
					else
						$params['{' . $key . '}'] = $matches[$i + 1];


					$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], array('$' => '&#036;', '{' => '&#123;'));
				}

				foreach ($possible['parameters'] as $p => $info)
					if (!isset($params['{' . $p . '}']))
						$params['{' . $p . '}'] = '';

				$tag = $possible;


				if (isset($tag['before']))
					$tag['before'] = strtr($tag['before'], $params);
				if (isset($tag['after']))
					$tag['after'] = strtr($tag['after'], $params);
				if (isset($tag['content']))
					$tag['content'] = strtr($tag['content'], $params);

				$pos1 += strlen($matches[0]) - 1;
			}
			else
				$tag = $possible;
			break;
		}


		if ($smileys !== false && $tag === null && isset($itemcodes[$message[$pos + 1]]) && $message[$pos + 2] === ']' && !isset($disabled['list']) && !isset($disabled['li']))
		{
			if ($message[$pos + 1] === '0' && !in_array($message[$pos - 1], array(';', ' ', "\t", '>')))
				continue;
			$tag = $itemcodes[$message[$pos + 1]];


			if ($inside === null || ($inside['tag'] != 'list' && $inside['tag'] != 'li'))
			{
				$open_tags[] = array(
					'tag' => 'list',
					'after' => '</ul>',
					'block_level' => true,
					'require_children' => array('li'),
					'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
					'trim' => 'outside',
				);
				$code = '<ul class="bbc_list">';
			}

			elseif ($inside['tag'] === 'li')
			{
				array_pop($open_tags);
				$code = '</li>';
			}
			else
				$code = '';


			$open_tags[] = array(
				'tag' => 'li',
				'after' => '</li>',
				'trim' => 'outside',
				'block_level' => true,
				'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
			);


			$code .= '<li' . ($tag === '' ? '' : ' type="' . $tag . '"') . '>';
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos + 3);
			$pos += strlen($code) + 1;


			$pos2 = strpos($message, '<br>', $pos);
			$pos3 = strpos($message, '[/', $pos);
			if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
			{
				preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($message, $pos2 + 6), $matches);
				$message = substr($message, 0, $pos2) . "\n" . (!empty($matches[0]) && substr($matches[0], -1) === '[' ? '[/li]' : '[/li][/list]') . "\n" . substr($message, $pos2);

				$open_tags[count($open_tags) - 2]['after'] = '</ul>';
			}

			else
			{

				$open_tags[count($open_tags) - 1]['after'] = '';
				$open_tags[count($open_tags) - 2]['after'] = '</li></ul>';
			}

			continue;
		}


		if ($tag === null && $inside !== null && !empty($inside['require_children']))
		{
			array_pop($open_tags);

			$message = substr($message, 0, $pos) . "\n" . $inside['after'] . "\n" . substr($message, $pos);
			$pos += strlen($inside['after']) + 1;
		}


		if ($tag === null)
			continue;


		if (isset($inside['disallow_children']))
			$tag['disallow_children'] = isset($tag['disallow_children']) ? array_unique(array_merge($tag['disallow_children'], $inside['disallow_children'])) : $inside['disallow_children'];


		if (isset($disabled[$tag['tag']]))
		{
			if (!isset($tag['disabled_before']) && !isset($tag['disabled_after']) && !isset($tag['disabled_content']))
			{
				$tag['before'] = !empty($tag['block_level']) ? '<div>' : '';
				$tag['after'] = !empty($tag['block_level']) ? '</div>' : '';
				$tag['content'] = isset($tag['type']) && $tag['type'] === 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
			}
			elseif (isset($tag['disabled_before']) || isset($tag['disabled_after']))
			{
				$tag['before'] = isset($tag['disabled_before']) ? $tag['disabled_before'] : (!empty($tag['block_level']) ? '<div>' : '');
				$tag['after'] = isset($tag['disabled_after']) ? $tag['disabled_after'] : (!empty($tag['block_level']) ? '</div>' : '');
			}
			else
				$tag['content'] = $tag['disabled_content'];
		}


		if (!empty($tag['block_level']) && $tag['tag'] != 'html' && empty($inside['block_level']))
		{
			$n = count($open_tags) - 1;
			while (empty($open_tags[$n]['block_level']) && $n >= 0)
				$n--;


			for ($i = count($open_tags) - 1; $i > $n; $i--)
			{
				$message = substr($message, 0, $pos) . "\n" . $open_tags[$i]['after'] . "\n" . substr($message, $pos);
				$len = strlen($open_tags[$i]['after']) + 2;
				$pos += $len;
				$pos1 += $len;


				if (!empty($open_tags[$i]['block_level']) && substr($message, $pos, 4) === '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 4);
				if (!empty($open_tags[$i]['trim']) && ($tag['trim'] === 'outside' || $tag['trim'] === 'both') && preg_match('~^(?:<br>|&nbsp;|\s)+~', substr($message, $pos), $matches) === 1)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

				array_pop($open_tags);
			}
		}


		if (!isset($tag['type']))
		{

			$open_tags[] = $tag;
			$message = substr($message, 0, $pos) . "\n" . $tag['before'] . "\n" . substr($message, $pos1);
			$pos += strlen($tag['before']) + 1;
		}

		elseif ($tag['type'] === 'unparsed_content')
		{
			$pos2 = stripos($message, '[/' . substr($message, $pos + 1, $tag['len']) . ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			if (!empty($tag['block_level']) && substr($data, 0, 4) === '<br>')
				$data = substr($data, 4);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + $tag['len']);

			$pos += strlen($code) + 1;
			$last_pos = $pos + 1;
		}

		elseif ($tag['type'] === 'unparsed_equals_content')
		{

			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) === '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted ? '&quot;]' : ']', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag['len']) . ']', $pos2);
			if ($pos3 === false)
				continue;

			$data = array(
				substr($message, $pos2 + ($quoted ? 7 : 1), $pos3 - $pos2 - ($quoted ? 7 : 1)),
				substr($message, $pos1, $pos2 - $pos1)
			);

			if (!empty($tag['block_level']) && substr($data[0], 0, 4) === '<br>')
				$data[0] = substr($data[0], 4);


			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag['len']);
			$pos += strlen($code) + 1;
		}

		elseif ($tag['type'] === 'closed')
		{
			if ($tag['tag'] === 'more')
			{
				if (!empty($context['current_topic']))
				{
					$pos2 = strpos($message, ']', $pos);
					$message = '<div class="headline">' . substr($message, 0, $pos) . '</div>' . substr($message, $pos2 + 1);
					$pos = $pos2 + 22;
				}
				else
				{
					$lent = westr::strlen(substr($message, $pos));
					if ($lent > 0)
					{

						$message = '<div class="headline">' . rtrim(substr($message, 0, $pos));
						while (substr($message, -4) === '<br>')
							$message = substr($message, 0, -4);
						$message .= ' <span class="readmore">' . sprintf($txt['readmore'], $lent) . '</span></div>';
						$pos = false;
					}
				}
			}
			else
			{
				$pos2 = strpos($message, ']', $pos);
				$message = substr($message, 0, $pos) . "\n" . $tag['content'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['content']) + 1;
			}
		}

		elseif ($tag['type'] === 'unparsed_commas_content')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag['len']) . ']', $pos2);
			if ($pos3 === false)
				continue;


			$data = explode(',', ',' . substr($message, $pos1, $pos2 - $pos1));
			$data[0] = substr($message, $pos2 + 1, $pos3 - $pos2 - 1);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = $tag['content'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag['len']);
			$pos += strlen($code) + 1;
		}

		elseif ($tag['type'] === 'unparsed_commas')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = explode(',', substr($message, $pos1, $pos2 - $pos1));

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);


			foreach ($data as $k => $d)
				$tag['after'] = strtr($tag['after'], array('$' . ($k + 1) => trim($d)));

			$open_tags[] = $tag;


			$code = $tag['before'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 1);
			$pos += strlen($code) + 1;
		}

		elseif ($tag['type'] === 'unparsed_equals' || $tag['type'] === 'parsed_equals')
		{

			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) === '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted ? '&quot;]' : ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);


			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);


			if ($tag['type'] !== 'unparsed_equals')
				$data = parse_bbc($data, $type, array('smileys' => empty($tag['parsed_tags_allowed']), 'tags' => !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : array()));

			$tag['after'] = strtr($tag['after'], array('$1' => $data));

			$open_tags[] = $tag;

			$code = strtr($tag['before'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + ($quoted ? 7 : 1));
			$pos += strlen($code) + 1;
		}


		if (!empty($tag['block_level']) && substr($message, $pos + 1, 4) === '<br>')
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 5);


		if (($tag['trim'] === 'inside' || $tag['trim'] === 'both') && preg_match('~^(?:<br>|&nbsp;|\s)+~', substr($message, $pos + 1), $matches) === 1)
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 1 + strlen($matches[0]));
	}


	while ($tag = array_pop($open_tags))
		$message .= "\n" . $tag['after'] . "\n";


	if ($smileys)
	{
		$message_parts = explode("\n", $message);
		for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
			parsesmileys($message_parts[$i]);

		$message = implode('', $message_parts);
	}

	else
		$message = strtr($message, array("\n" => ''));

	if ($message !== '' && $message[0] === ' ')
		$message = '&nbsp;' . substr($message, 1);


	$message = strtr($message, array('  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"));

	if (empty($parse_tags))
	{

		if (strlen($message) > 15 && strpos($message, '<a href="') !== false)
		{
			loadSource('media/Aeva-Embed');









			if (!empty($settings['embed_enabled']) && empty($context['embed_disable']) && strhas($message, array('http://', 'https://')) && !$print && $type != 'signature')
				$message = aeva_main($message);


			$message = aeva_reverse_protection($message);


			unset($context['embed_disable'], $context['aeva']['skip']);
		}

		if (empty($disabled['media']) && stripos($message, '[media') !== false)
		{
			loadSource('media/Subs-Media');
			aeva_parse_bbc($message, $cache_id);
		}

		if (strpos($message, '[noembed]') !== false)
			$message = str_replace(array('[noembed]', '[/noembed]'), '', $message);
	}


	if (stripos($message, '[nb]') !== false && ($context['action'] !== 'ajax' || !isset($_GET['sa']) || $_GET['sa'] !== 'wysiwyg') && (empty($parse_tags) || in_array('nb', $parse_tags)))
	{
		preg_match_all('~\[nb]((?>[^[]|\[(?!/?nb])|(?R))+?)\[/nb\]~i', $message, $matches, PREG_SET_ORDER);

		if (count($matches) > 0)
		{
			$f = 0;
			global $addnote, $type_for_footnotes;
			if (is_null($addnote))
				$addnote = array();
			foreach ($matches as $m)
			{
				$my_pos = $end_blockquote = strpos($message, $m[0]);
				$message = substr_replace($message, '<a class="fnotel" id="footlink' . ++$feet . '" href="#footnote' . $feet . '">[' . ++$f . ']</a>', $my_pos, strlen($m[0]));
				$addnote[$feet] = array($feet, $f, $m[1]);

				while ($end_blockquote !== false)
				{
					$end_blockquote = strpos($message, '</blockquote>', $my_pos);
					if ($end_blockquote === false)
						continue;

					$start_blockquote = strpos($message, '<blockquote', $my_pos);
					if ($start_blockquote !== false && $start_blockquote < $end_blockquote)
						$my_pos = $end_blockquote + 1;
					else
					{
						$message = substr_replace($message, '<foot:' . $feet . '>', $end_blockquote, 0);
						break;
					}
				}

				if ($end_blockquote === false)
					$message .= '<foot:' . $feet . '>';
			}

			$type_for_footnotes = $type;
			$message = preg_replace_callback('~(?:<foot:\d+>)+~', 'parse_footnotes', $message);
		}
	}


	call_hook('post_bbc_parse', array(&$message, &$bbc_options, &$type));


	if (!empty($owner) && !empty($user_profile[$owner]))
	{

		if (!empty($user_profile[$owner]['sanctions']['disemvowel']))
			$message = disemvowel($message);
		if (!empty($user_profile[$owner]['sanctions']['scramble']))
			$message = scramble($message);
	}


	if (isset($cache_key, $cache_t) && microtime(true) - $cache_t > .05)
		cache_put_data($cache_key, $message, 240);


	if (!empty($parse_tags))
	{
		if (empty($temp_bbc))
			$bbc_codes = array();
		else
		{
			$bbc_codes = $temp_bbc;
			unset($temp_bbc);
		}
	}

	return $message;
}

function parse_lang_strings($val)
{
	global $txt;

	if (isset($txt[$val[1]]))
		return $txt[$val[1]];
	return '<em>' . $val[1] . '</em>';
}











function parsesmileys(&$message)
{
	global $smileyPregReplace;
	static $smileyPregSearch = '';


	if (we::$user['smiley_set'] === 'none')
		return;


	if (empty($smileyPregSearch))
	{
		global $settings, $context;


		if (empty($settings['smiley_enable']))
		{
			$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:-)', ':edit:');
			$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif', 'edit.gif');
			$smileysdiv = array();
			foreach ($smileysto as $file)
				$smileysdiv[] = array('embed' => true, 'name' => str_replace('.', '_', $file));
		}
		else
		{

			if (($temp = cache_get_data('smiley_parser', 'forever')) === null || !isset($temp[2]) || !is_array($temp[2]))
			{
				$result = wesql::query('
					SELECT code, filename, hidden
					FROM {db_prefix}smileys'
				);
				$smileysfrom = array();
				$smileysto = array();
				$smileysdiv = array();
				while ($row = wesql::fetch_assoc($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = $row['filename'];
					$smileysdiv[] = array(
						'embed' => $row['hidden'] == 0,
						'name' => preg_replace(array('~[^\w]~', '~_+~'), array('_', '_'), $row['filename'])
					);
				}
				wesql::free_result($result);

				cache_put_data('smiley_parser', array($smileysfrom, $smileysto, $smileysdiv), 'forever');
			}
			else
				list ($smileysfrom, $smileysto, $smileysdiv) = $temp;
		}


		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$safe = htmlspecialchars($smileysfrom[$i], ENT_QUOTES);
			$smileyCode = '<i class="smiley ' . $smileysdiv[$i]['name'] . '">' . $safe . '</i>';

			$smileyPregReplace[$smileysfrom[$i]] = $smileyCode;
			$searchParts[] = preg_quote($smileysfrom[$i], '~');

			if ($safe != $smileysfrom[$i])
			{
				$smileyPregReplace[$safe] = $smileyCode;
				$searchParts[] = preg_quote($safe, '~');
			}
		}

		$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
		$context['smiley_gzip'] = $can_gzip;
		$context['smiley_ext'] = $can_gzip ? (we::is('safari[-5.1]') ? '.cgz' : '.css.gz') : '.css';
		if (!isset($settings['smiley_cache']))
			updateSettings(array('smiley_cache' => time() % 1000));

		if (!file_exists(CACHE_DIR . '/css/smileys' . (we::$user['smiley_set'] == 'default' ? '' : '-' . we::$user['smiley_set']) . '-' . $settings['smiley_cache'] . $context['smiley_ext']))
		{


			$cache = array();
			for ($i = 0; $i < $n; $i++)
				$cache[$smileysdiv[$i]['name']] = array('embed' => $smileysdiv[$i]['embed'], 'file' => $smileysto[$i]);
			if (!empty($cache))
			{
				loadSource('Subs-Cache');
				wedge_cache_smileys(we::$user['smiley_set'], $cache);
			}
		}

		$smileyPregSearch = '~(?<=[>:?.\s\x{A0}[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~u';
	}


	$message = preg_replace_callback($smileyPregSearch, 'replace_smileys', $message);
}


function replace_smileys($match)
{
	global $smileyPregReplace, $smiley_css_done;

	if (isset($smileyPregReplace[$match[1]]))
	{
		if (empty($smiley_css_done))
		{
			global $context, $settings;

			$smiley_css_done = true;
			$context['header'] .= '
	<link rel="stylesheet" href="' . CACHE . '/css/smileys' . (we::$user['smiley_set'] == 'default' ? '' : '-' . we::$user['smiley_set']) . '-' . $settings['smiley_cache'] . $context['smiley_ext'] . '">';
		}
		return $smileyPregReplace[$match[1]];
	}
	return '';
}


function parse_footnotes($match)
{
	global $addnote, $type_for_footnotes;

	$msg = '<table class="footnotes w100">';
	preg_match_all('~<foot:(\d+)>~', $match[0], $mat);
	foreach ($mat[1] as $note)
	{
		$n =& $addnote[$note];
		$msg .= '<tr><td class="footnum"><a id="footnote' . $n[0] . '" href="#footlink' . $n[0] . '">&nbsp;' . $n[1] . '.&nbsp;</a></td><td class="footnote">'
			 . (stripos($n[2], '[nb]', 1) === false ? $n[2] : parse_bbc($n[2], $type_for_footnotes)) . '</td></tr>';
	}
	return $msg . '</table>';
}









function highlight_php_code($code)
{

	$code = un_htmlspecialchars(strtr($code, array('<br>' => "\n", "\t" => 'WEDGE_TAB();', '&#91;' => '[')));

	$oldlevel = error_reporting(0);
	$buffer = str_replace(array("\n", "\r"), '', @highlight_string($code, true));
	error_reporting($oldlevel);


	$buffer = preg_replace('~WEDGE_TAB(?:</(?:font|span)><(?:font color|span style)="[^"]*?">)?\\(\\);~', '<span class="bbc_pre">' . "\t" . '</span>', $buffer);

	return strtr($buffer, array('\'' => '&#039;', '<code>' => '', '</code>' => ''));
}













function bbc_permute($array)
{
	$orders = array($array);

	$n = count($array);
	$p = range(0, $n);
	for ($i = 1; $i < $n; null)
	{
		$p[$i]--;
		$j = $i % 2 != 0 ? $p[$i] : 0;

		$temp = $array[$i];
		$array[$i] = $array[$j];
		$array[$j] = $temp;

		for ($i = 1; $p[$i] === 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}










function &censorText(&$text, $force = false)
{
	global $settings, $options, $txt;
	static $censor_vulgar = null, $censor_proper;

	if ((!empty($options['show_no_censored']) && $settings['allow_no_censored'] && !$force) || empty($settings['censor_vulgar']))
		return $text;


	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $settings['censor_vulgar']);
		$censor_proper = explode("\n", $settings['censor_proper']);


		for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
		{
			$censor_vulgar[$i] = strtr(preg_quote($censor_vulgar[$i], '/'), array('\\\\\\*' => '[*]', '\\*' => '[^\s]*?', '&' => '&amp;'));
			$censor_vulgar[$i] = (empty($settings['censorWholeWord']) ? '/' . $censor_vulgar[$i] . '/' : '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/') . (empty($settings['censorIgnoreCase']) ? '' : 'i') . 'u';

			if (strpos($censor_vulgar[$i], '\'') !== false)
			{
				$censor_proper[count($censor_vulgar)] = $censor_proper[$i];
				$censor_vulgar[count($censor_vulgar)] = strtr($censor_vulgar[$i], array('\'' => '&#039;'));
			}
		}
	}


	$text = preg_replace($censor_vulgar, $censor_proper, $text);
	return $text;
}









function disemvowel($message)
{
	return wedge_post_process($message, '~([aeiou]+)~i', '');
}

function scramble($message)
{
	return wedge_post_process($message, '~(?<=\b[a-z])([a-z]{2,})(?=[a-z]\b)~i', null, 'wedge_callback_str_shuffle');
}










function wedge_post_process($message, $regex_find, $regex_replace = null, $regex_callback = null)
{
	static $iconv = null;

	if ($iconv === null)
		$iconv = function_exists('iconv');

	$parts = preg_split('~(<.+?>)~', $message, null, PREG_SPLIT_DELIM_CAPTURE);
	$inside_script = $inside_cdata = $inside_comment = false;
	foreach ($parts as $id => &$part)
	{
		if (empty($part))
			continue;


		if (stripos($part, '<script') === 0)
		{
			$inside_script = true;
			continue;
		}
		elseif ($inside_script)
		{
			if (stripos($part, '</script') !== false)
				$inside_script = false;

			continue;
		}


		if (stripos($part, '<!--') === 0)
		{
			$inside_comment = true;
			continue;
		}
		elseif ($inside_comment)
		{
			if (stripos($part, '-->') !== false)
				$inside_comment = false;

			continue;
		}


		if (stripos($part, '<![CDATA[') === 0)
		{
			$inside_cdata = true;
			continue;
		}
		elseif ($inside_comment)
		{
			if (stripos($part, ']]>') !== false)
				$inside_cdata = false;

			continue;
		}


		if ($part[0] === '<')
			continue;


		$part = html_entity_decode($part, ENT_QUOTES, 'UTF-8');
		if ($iconv)
			$part = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $part);


		$part = htmlspecialchars(isset($regex_replace) ? preg_replace($regex_find, $regex_replace, $part) : preg_replace_callback($regex_find, $regex_callback, $part), ENT_QUOTES, 'UTF-8');
	}

	return implode('', $parts);
}

function wedge_callback_str_shuffle($match)
{
	return str_shuffle($match[0]);
}

function loadBBCodes()
{
	global $txt;


	$noopener = we::is('chrome[49-],opera[36-],firefox[52-]') ? 'noopener' : 'noreferrer';

	$bbcodes = [[
		'tag' => 'abbr',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'quoted' => 'optional',
		'before' => '<abbr title="$1">',
		'after' => '</abbr>',
		'disabled_after' => '($1)',
	],
	[
		'tag' => 'anchor',
		'len' => '6',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'before' => '<span id="post_$1">',
		'after' => '</span>',
		'test' => '#?([A-Za-z][A-Za-z0-9_-]*)]',
	],
	[
		'tag' => 'b',
		'len' => '1',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<strong>',
		'after' => '</strong>',
	],
	[
		'tag' => 'bdo',
		'len' => '3',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'before' => '<bdo dir="$1">',
		'after' => '</bdo>',
		'test' => '(rtl|ltr)]',
	],
	[
		'tag' => 'br',
		'len' => '2',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'closed',
		'content' => '<br>',
	],
	[
		'tag' => 'center',
		'len' => '6',
		'block_level' => true,
		'trim' => 'none',
		'before' => '<div class="center">',
		'after' => '</div>',
	],
	[
		'tag' => 'code',
		'len' => '4',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_code',
		'content' => '<div class="bbc_code"><header>' . $txt['code'] . ': <a href="#" onclick="return weSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></header>',
	],
	[
		'tag' => 'code',
		'len' => '4',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'unparsed_equals_content',
		'validate' => 'bbc_validate_code_equals',
		'content' => '<div class="bbc_code"><header>' . $txt['code'] . ': ($2) <a href="#" onclick="return weSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></header>',
	],
	[
		'tag' => 'color',
		'len' => '5',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'before' => '<span style="color: $1" class="bbc_color">',
		'after' => '</span>',
		'test' => '(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))]',
	],
	[
		'tag' => 'email',
		'len' => '5',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_email',
		'content' => '<a href="mailto:$1" class="bbc_email">$1</a>',
	],
	[
		'tag' => 'email',
		'len' => '5',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
		'before' => '<a href="mailto:$1" class="bbc_email">',
		'after' => '</a>',
		'disabled_after' => '($1)',
	],
	[
		'tag' => 'flash',
		'len' => '5',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_commas_content',
		'validate' => 'bbc_validate_flash',
		'content' => '<object width="$2" height="$3" data="$1"><param name="movie" value="$1"><param name="play" value="true"><param name="loop" value="true"><param name="quality" value="high"><param name="allowscriptaccess" value="never"><embed src="$1" type="application/x-shockwave-flash" allowscriptaccess="never" width="$2" height="$3"></object>',
		'disabled_content' => '<a href="$1" target="_blank" class="new_win">$1</a>',
		'test' => '\d+,\d+]',
	],
	[
		'tag' => 'font',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'before' => '<span style="font-family: $1" class="bbc_font">',
		'after' => '</span>',
		'test' => '[A-Za-z0-9_,\s-]+?]',
	],
	[
		'tag' => 'ftp',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_ftp_content',
		'content' => '<a href="$1" class="bbc_ftp new_win" target="_blank">$1</a>',
	],
	[
		'tag' => 'ftp',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'validate' => 'bbc_validate_ftp_equals',
		'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
		'before' => '<a href="$1" class="bbc_ftp new_win" target="_blank">',
		'after' => '</a>',
		'disabled_after' => '($1)',
	],
	[
		'tag' => 'html',
		'len' => '4',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'content' => '$1',
		'disabled_content' => '$1',
	],
	[
		'tag' => 'hr',
		'len' => '2',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'closed',
		'content' => '<hr>',
	],
	[
		'tag' => 'i',
		'len' => '1',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<em>',
		'after' => '</em>',
	],
	[
		'tag' => 'img',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'parameters' =>	[
			'alt' => [ 'optional' => true ],
			'align' => [
				'optional' => true,
				'value' => ' $1',
				'match' => '(right|left|center)',
			],
			'width' => [
				'optional' => true,
				'value' => ' width="$1"',
				'match' => '(\d+)',
			],
			'height' => [
				'optional' => true,
				'value' => ' height="$1"',
				'match' => '(\d+)',
			],
		],
		'validate' => 'bbc_validate_img_1',
		'content' => '<img src="$1" alt="{alt}"{width}{height} class="bbc_img resized{align}">',
		'disabled_content' => '($1)',
	],
	[
		'tag' => 'img',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_img_2',
		'content' => '<img src="$1" class="bbc_img">',
		'disabled_content' => '($1)',
	],
	[
		'tag' => 'iurl',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_iurl',
		'content' => '<a href="$1" class="bbc_link">$1</a>',
	],
	[
		'tag' => 'iurl',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'validate' => 'bbc_validate_iurl_equals',
		'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
		'before' => '<a href="$1" class="bbc_link">',
		'after' => '</a>',
		'disabled_after' => '($1)',
	],
	[
		'tag' => 'left',
		'len' => '4',
		'block_level' => true,
		'trim' => 'none',
		'before' => '<div class="left">',
		'after' => '</div>',
	],
	[
		'tag' => 'li',
		'len' => '2',
		'block_level' => true,
		'trim' => 'outside',
		'require_parents' => ['list'],
		'before' => '<li>',
		'after' => '</li>',
		'disabled_before' => '',
		'disabled_after' => '<br>',
	],
	[
		'tag' => 'list',
		'len' => '4',
		'block_level' => true,
		'trim' => 'inside',
		'require_children' => ['li', 'list'],
		'before' => '<ul class="bbc_list">',
		'after' => '</ul>',
	],
	[
		'tag' => 'list',
		'len' => '4',
		'block_level' => true,
		'trim' => 'inside',
		'parameters' => [
			'type' => [
				'match' => '(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)',
			],
		],
		'require_children' => ['li', 'list'],
		'before' => '<ul class="bbc_list" style="list-style-type: {type}">',
		'after' => '</ul>',
	],
	[
		'tag' => 'ltr',
		'len' => '3',
		'block_level' => true,
		'trim' => 'none',
		'before' => '<div dir="ltr">',
		'after' => '</div>',
	],
	[
		'tag' => 'me',
		'len' => '2',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'quoted' => 'optional',
		'before' => '<div class="meaction">* $1&nbsp;',
		'after' => '</div>',
		'disabled_before' => '/me',
	],
	[
		'tag' => 'media',
		'len' => '5',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'closed',
		'content' => '',
	],
	[
		'tag' => 'mergedate',
		'len' => '9',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_mergedate',
		'content' => '<div class="mergedate">' . $txt['search_date_posted'] . ' $1</div>',
	],
	[
		'tag' => 'more',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'closed',
		'content' => '',
	],
	[
		'tag' => 'nobbc',
		'len' => '5',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'content' => '$1',
	],
	[
		'tag' => 'php',
		'len' => '3',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_php',
		'content' => '<div class="php_code"><code>$1</code></div>',
		'disabled_content' => '$1',
	],
	[
		'tag' => 'pre',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<span class="bbc_pre">',
		'after' => '</span>',
	],
	[
		'tag' => 'quote',
		'len' => '5',
		'block_level' => true,
		'trim' => 'none',
		'before' => '<div class="bbc_quote"><header>' . $txt['quote_noun'] . '</header><div><blockquote>',
		'after' => '</blockquote></div></div>',
	],
	[
		'tag' => 'quote',
		'len' => '5',
		'block_level' => true,
		'trim' => 'none',
		'parameters' => [
			'author' => [
				'match' => '(.{1,192}?)',
				'quoted' => true,
			],
		],
		'before' => '<div class="bbc_quote"><header>' . $txt['quote_from'] . ' {author}</header><div><blockquote>',
		'after' => '</blockquote></div></div>',
	],
	[
		'tag' => 'quote',
		'len' => '5',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'parsed_equals',
		'quoted' => 'optional',
		'parsed_tags_allowed' => ['url', 'iurl', 'ftp'],
		'before' => '<div class="bbc_quote"><header>' . $txt['quote_from'] . ' $1</header><div><blockquote>',
		'after' => '</blockquote></div></div>',
	],
	[
		'tag' => 'quote',
		'len' => '5',
		'block_level' => true,
		'trim' => 'none',
		'parameters' => [
			'author' => ['match' => '([^<>]{1,192}?)'],
			'link' => ['match' => '(topic=[\dmsg#./]{1,40}(?:;start=[\dmsg#./]{1,40})?|action=profile;u=\d+|msg=\d+)'],
			'date' => ['match' => '(\d+)', 'validate' => 'on_timeformat'],
		],
		'before' => '<div class="bbc_quote"><header>' . $txt['quote_from'] . ' {author} <a href="<URL>?{link}">{date}</a></header><div><blockquote>',
		'after' => '</blockquote></div></div>',
	],
	[
		'tag' => 'quote',
		'len' => '5',
		'block_level' => true,
		'trim' => 'none',
		'parameters' => [
			'author' => [
				'match' => '(.{1,192}?)',
			],
		],
		'before' => '<div class="bbc_quote"><header>' . $txt['quote_from'] . ' {author}</header><div><blockquote>',
		'after' => '</blockquote></div></div>',
	],
	[
		'tag' => 'right',
		'len' => '5',
		'block_level' => true,
		'trim' => 'none',
		'before' => '<div class="right">',
		'after' => '</div>',
	],
	[
		'tag' => 'rtl',
		'len' => '3',
		'block_level' => true,
		'trim' => 'none',
		'before' => '<div dir="rtl">',
		'after' => '</div>',
	],
	[
		'tag' => 's',
		'len' => '1',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<del>',
		'after' => '</del>',
	],
	[
		'tag' => 'size',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'before' => '<span style="font-size: $1" class="bbc_size">',
		'after' => '</span>',
		'test' => '([1-9]\d?p[xt]|small(?:er)?|large[r]?|x[x]?-(?:small|large)|medium|(0\.[1-9]|[1-9](\.\d\d?)?)?em)]',
	],
	[
		'tag' => 'size',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'validate' => function (&$tag, &$data) {
			$sizes = array(1 => 8, 10, 12, 14, 18, 24, 36);
			$data = $sizes[$data] . 'pt';
		},
		'before' => '<span style="font-size: $1" class="bbc_size">',
		'after' => '</span>',
		'test' => '[1-7]]',
	],
	[
		'tag' => 'spoiler',
		'len' => '7',
		'block_level' => true,
		'trim' => 'none',
		'before' => '<div class="spoiler"><header><input type="button" value="' . $txt['spoiler'] . '" onclick="$(this.parentNode.parentNode.lastChild).toggle(); return false;">' . $txt['click_for_spoiler'] . '</header><blockquote>',
		'after' => '</blockquote></div>',
	],
	[
		'tag' => 'spoiler',
		'len' => '7',
		'block_level' => true,
		'trim' => 'none',
		'type' => 'parsed_equals',
		'quoted' => 'optional',
		'before' => '<div class="spoiler"><header><input type="button" value="$1" onclick="$(this.parentNode.parentNode.lastChild).toggle(); return false;">' . $txt['click_for_spoiler'] . '</header><blockquote>',
		'after' => '</blockquote></div>',
	],
	[
		'tag' => 'sub',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<sub>',
		'after' => '</sub>',
	],
	[
		'tag' => 'sup',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<sup>',
		'after' => '</sup>',
	],
	[
		'tag' => 'table',
		'len' => '5',
		'block_level' => true,
		'trim' => 'inside',
		'require_children' => ['tr'],
		'before' => '<table class="bbc_table">',
		'after' => '</table>',
	],
	[
		'tag' => 'td',
		'len' => '2',
		'block_level' => true,
		'trim' => 'outside',
		'require_parents' => ['tr'],
		'before' => '<td>',
		'after' => '</td>',
		'disabled_before' => '',
		'disabled_after' => '',
	],
	[
		'tag' => 'time',
		'len' => '4',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => function (&$tag, &$data) {
			if (is_numeric($data))
				$data = timeformat($data);
			else
				$tag['content'] = '[time]$1[/time]';
		},
		'content' => '$1',
	],
	[
		'tag' => 'tr',
		'len' => '2',
		'block_level' => true,
		'trim' => 'both',
		'require_children' => ['td'],
		'require_parents' => ['table'],
		'before' => '<tr>',
		'after' => '</tr>',
		'disabled_before' => '',
		'disabled_after' => '',
	],
	[
		'tag' => 'tt',
		'len' => '2',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<span class="bbc_tt">',
		'after' => '</span>',
	],
	[
		'tag' => 'u',
		'len' => '1',
		'block_level' => false,
		'trim' => 'none',
		'before' => '<span class="bbc_u">',
		'after' => '</span>',
	],
	[
		'tag' => 'url',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_content',
		'validate' => 'bbc_validate_url_content',
		'content' => '<a href="$1" class="bbc_link" rel="nofollow ' . $noopener . '" target="_blank">$1</a>',
	],
	[
		'tag' => 'url',
		'len' => '3',
		'block_level' => false,
		'trim' => 'none',
		'type' => 'unparsed_equals',
		'validate' => 'bbc_validate_url_equals',
		'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
		'before' => '<a href="$1" class="bbc_link" rel="nofollow ' . $noopener . '" target="_blank">',
		'after' => '</a>',
		'disabled_after' => '($1)',
	]];

	$field_list = array(
		'before_code' => 'before',
		'after_code' => 'after',
		'content' => 'content',
		'disabled_before' => 'disabled_before',
		'disabled_after' => 'disabled_after',
		'disabled_content' => 'disabled_content',
		'test' => 'test',
	);
	$explode_list = array('disallow_children', 'require_children', 'require_parents', 'parsed_tags_allowed');



	$result = wesql::query('
		SELECT id_bbcode, tag, len, bbctype, before_code, after_code, content, disabled_before,
			disabled_after, disabled_content, block_level, test, disallow_children,
			require_parents, require_children, parsed_tags_allowed, quoted, params, trim_wspace,
			validate_func
		FROM {db_prefix}bbcode WHERE id_plugin != {string:empty}', array('empty' => '')
	);

	while ($row = wesql::fetch_assoc($result))
	{
		$bbcode = array(
			'tag' => $row['tag'],
			'len' => $row['len'],
			'block_level' => !empty($row['block_level']),
			'trim' => $row['trim_wspace'],
		);
		if ($row['bbctype'] !== 'parsed')
			$bbcode['type'] = $row['bbctype'];
		if (!empty($row['params']))
			$bbcode['parameters'] = unserialize($row['params']);
		if (!empty($row['validate_func']))
			$bbcode['validate'] = create_function('&$tag, &$data, $disabled', $row['validate_func']);
		if ($row['quoted'] !== 'none')
			$bbcode['quoted'] = $row['quoted'];

		foreach ($explode_list as $field)
			if (!empty($row[$field]))
				$bbcode[$field] = explode(',', $row[$field]);


		foreach ($field_list as $db_field => $bbc_field)
			if (!empty($row[$db_field]))
				$bbcode[$bbc_field] = preg_replace_callback('~{{(\w+)}}~', 'parse_lang_strings', trim($row[$db_field]));

		$bbcodes[] = $bbcode;
	}
	wesql::free_result($result);

	return $bbcodes;
}

function bbc_validate_code(&$tag, &$data, &$disabled)
{
	if (isset($disabled['code']))
		return;

	if (we::is('gecko,opera'))
		$tag['content'] .= '<span class="bbc_pre"><code>$1</code></span></div>';
	else
		$tag['content'] .= '<code>$1</code></div>';
	$php_parts = preg_split('~(&lt;\?php|\?&gt;)~', $data, -1, PREG_SPLIT_DELIM_CAPTURE);
	for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)
	{

		if ($php_parts[$php_i] != '&lt;?php')
			continue;
		$php_string = '';
		while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != '?&gt;')
		{
			$php_string .= $php_parts[$php_i];
			$php_parts[$php_i++] = '';
		}
		$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);
	}

	$data = str_replace("<span class=\"bbc_pre\">\t</span>", "\t", implode('', $php_parts));

	if (!we::is('gecko'))
		$data = str_replace("\t", "<span class=\"bbc_pre\">\t</span>", $data);

	if (we::is('ie'))
		$data = str_replace('<br>', '&#13;', $data);
}

function bbc_validate_code_equals(&$tag, &$data, &$disabled)
{
	if (isset($disabled['code']))
		return;

	if (we::is('gecko,opera'))
		$tag['content'] .= '<span class="bbc_pre"><code>$1</code></span></div>';
	else
		$tag['content'] .= '<code>$1</code></div>';
	$php_parts = preg_split('~(&lt;\?php|\?&gt;)~', $data[0], -1, PREG_SPLIT_DELIM_CAPTURE);
	for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)
	{

		if ($php_parts[$php_i] != '&lt;?php')
			continue;
		$php_string = '';
		while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != '?&gt;')
		{
			$php_string .= $php_parts[$php_i];
			$php_parts[$php_i++] = '';
		}
		$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);
	}

	$data[0] = str_replace("<span class=\"bbc_pre\">\t</span>", "\t", implode('', $php_parts));

	if (!we::is('gecko'))
		$data[0] = str_replace("\t", "<span class=\"bbc_pre\">\t</span>", $data[0]);

	if (we::is('ie'))
		$data[0] = str_replace('<br>', '&#13;', $data[0]);
}

function bbc_validate_email(&$tag, &$data)
{
	$data = strtr($data, array('<br>' => ''));
}

function bbc_validate_flash(&$tag, &$data, &$disabled)
{
	if (isset($disabled['url']))
		$tag['content'] = '$1';
	elseif (strpos($data[0], 'http://') !== 0 && strpos($data[0], 'https://') !== 0)
		$data[0] = 'http://' . $data[0];
}

function bbc_validate_ftp_content(&$tag, &$data)
{
	$data = strtr($data, array('<br>' => ''));
	if (strpos($data, 'ftp://') !== 0 && strpos($data, 'ftps://') !== 0)
		$data = 'ftp://' . $data;
}

function bbc_validate_ftp_equals(&$tag, &$data)
{
	if (strpos($data, 'ftp://') !== 0 && strpos($data, 'ftps://') !== 0)
		$data = 'ftp://' . $data;
}

function bbc_validate_img_1(&$tag, &$data)
{
	$data = strtr($data, array('<br>' => ''));
	if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
		$data = 'http://' . $data;
	add_js_unique('$("img.resized").click(function () { this.style.width = this.style.height = (this.style.width == "auto" ? null : "auto"); });');
}

function bbc_validate_img_2(&$tag, &$data)
{
	$data = strtr($data, array('<br>' => ''));
	if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
		$data = 'http://' . $data;
}

function bbc_validate_iurl(&$tag, &$data)
{
	$data = strtr($data, array('<br>' => ''));
	if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
		$data = 'http://' . $data;
}

function bbc_validate_iurl_equals(&$tag, &$data)
{
	if (substr($data, 0, 1) == '#')
		$data = '#post_' . substr($data, 1);
	elseif (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
		$data = 'http://' . $data;
}

function bbc_validate_mergedate(&$tag, &$data)
{
	if (is_numeric($data)) $data = timeformat($data);
}

function bbc_validate_php(&$tag, &$data)
{
	$add_begin = substr(trim($data), 0, 5) != '&lt;';
	$data = highlight_php_code($add_begin ? '&lt;?php ' . $data . '?&gt;' : $data);
	if ($add_begin)
		$data = preg_replace(array('~^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)~', '~\?&gt;((?:</(font|span)>)*)$~'), '$1', $data, 2);
}

function bbc_validate_url_content(&$tag, &$data)
{
	$data = strtr($data, array('<br>' => ''));
	if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
		$data = 'http://' . $data;
}

function bbc_validate_url_equals(&$tag, &$data)
{
	if (strpos($data, 'http://') !== 0 && strpos($data, 'https://') !== 0)
		$data = 'http://' . $data;
}
