<?php























































loadSource('media/Class-Media');

function aeva_allowed_types($flat = false, $see_all = false)
{
	$ext = aeva_extList();
	$allowed_types = array(
		'im' => array_keys($ext['image']),
		'au' => array_keys($ext['audio']),
		'vi' => array_keys($ext['video']),
		'do' => array_keys($ext['doc']),
		'zi' => array('zipm')
	);

	if (!$see_all)
	{
		if (!aeva_allowedTo('add_images'))
			unset($allowed_types['im']);
		if (!aeva_allowedTo('add_audios'))
			unset($allowed_types['au']);
		if (!aeva_allowedTo('add_videos'))
			unset($allowed_types['vi']);
		if (!aeva_allowedTo('add_docs'))
			unset($allowed_types['do']);
		if (!aeva_allowedTo(array('add_images', 'add_audios', 'add_videos', 'add_docs'), true))
			unset($allowed_types['zi']);
	}

	if (!$flat)
		return $allowed_types;

	$allowed_types_flat = array();
	foreach ($allowed_types as $all)
		foreach ($all as $v)
			$allowed_types_flat[] = $v;

	return $allowed_types_flat;
}

function aeva_allowedTo($perms, $single_true = false)
{
	global $context;

	if (empty($perms))
		return false;
	if (allowedTo('media_manage'))
		return true;
	if (!is_array($perms))
		return !in_array($perms, $context['aeva_album_permissions']) ? allowedTo('media_' . $perms) : isset($context['aeva_album']) && in_array($perms, $context['aeva_album']['permissions']);

	$tests = array();
	foreach ($perms as $perm)
		$tests[] = !in_array($perm, $context['aeva_album_permissions']) ? allowedTo('media_' . $perm) : isset($context['aeva_album']) && in_array($perm, $context['aeva_album']['permissions']);

	return $single_true ? in_array(true, $tests) : !in_array(false, $tests);
}

function aeva_get_num_files($path)
{

	if (!is_readable($path))
		return false;
	if (!is_dir($path))
		return false;

	$files = scandir($path);

	return $files === false ? 0 : count(array_diff($files, array('.', '..')));
}

function aeva_get_dir_map($path)
{
	if (!is_readable($path))
		return false;
	if (!is_dir($path))
		return false;


	$dirname_stack[] = array($path, null, 'root');
	$dirs = array();
	$i = 0;
	do
	{
		list ($dirname, $parent, $foldername) = array_shift($dirname_stack);
		$dirs[$i] = array(
			'dirname' => $dirname,
			'fname' => $foldername,
			'parent' => $parent,
			'files' => array(),
			'folders' => array(),
		);
		$files = scandir($dirname);
		foreach ($files as $file)
		{
			if ($file[0] !== '.' && is_readable($dirname . '/' . $file))
			{
				if (is_dir($dirname . '/' . $file))
				{
					$dirname_stack[] = array($dirname . '/' . $file, $i, $file);
					$dirs[$i]['folders'][] = array($file, $dirname . '/' . $file);
				}
				else
					$dirs[$i]['files'][] = array($file, filesize($dirname . '/' . $file), $dirname . '/' . $file);
			}
		}
		$i++;
	}
	while (count($dirname_stack) > 0);


	$child_level_index = array(0 => 0);
	foreach ($dirs as $dir => $data)
	{
		if (isset($child_level_index[$dir['parent']]))
			continue;
		elseif (isset($child_level_index[$data['parent']]))
			$child_level_index[$dir] = $child_level_index[$data['parent']] + 1;
		else
			$child_level_index[$dir] = 1;
	}


	foreach ($dirs as $dir => $data)
	{
		$dirs[$dir]['child_level'] = isset($child_level_index[$data['parent']]) ? $child_level_index[$data['parent']] : 0;
	}


	$dirpath_index = array();
	foreach ($dirs as $dir => $data)
	{
		$dirpath_index[$data['dirname']] = $dir;
	}
	foreach ($dirs as $dir => $data)
	{
		foreach ($data['folders'] as $folder => $folderdata)
		{
			$dirs[$dir]['folders'][$folder][2] = $dirpath_index[$folderdata[1]];
		}
	}


	foreach ($dirs as $dir => $data)
	{

		if (!is_null($data['parent']))
			continue;

		$_list[] = $dir;
		aeva_get_dir_list_subfolders($dirs, $data, $_list);
	}

	return array($dirs, $_list);
}

function aeva_get_dir_list_subfolders($dirs, $data, &$_list)
{
	foreach ($data['folders'] as $folder)
	{
		$_list[] = $folder[2];
		if (!empty($dirs[$folder[2]]['folders']))
			aeva_get_dir_list_subfolders($dirs, $dirs[$folder[2]], $_list);
	}
}


function aeva_embedObject($obj, $id_file, $cur_width = 0, $cur_height = 0, $desc = '', $type = null)
{
	global $galurl, $context, $amSettings, $cookiename;
	static $player = -1;

	if (empty($type))
		$type = $obj->media_type();

	$output = '';
	$pwid = !empty($context['aeva_override_player_width']) ? $context['aeva_override_player_width'] : (!empty($amSettings['audio_player_width']) ? min($amSettings['max_preview_width'], max(100, (int) $amSettings['audio_player_width'])) : 400);
	$preview_image = $galurl . 'sa=media;in=' . $id_file . (!empty($context['aeva_has_preview']) || $type == 'image' ? ';preview' : ';thumb');
	$show_audio_preview = $type == 'audio' && $context['action'] === 'media';
	$increm = $show_audio_preview && !empty($context['aeva_has_preview']) ? '' : ';v';

	if ($show_audio_preview)
		$output .= '
		<div class="centered" style="width: ' . max($cur_width, $pwid) . 'px">
		<img src="' . $preview_image . '"' . ($cur_width > 0 && $cur_height > 0 ? ' width="' . $cur_width . '" height="' . $cur_height . '"' : '') . ' class="center" style="padding-bottom: 8px">';

	if ($type == 'image')
	{
		$output .= '
		' . (!empty($context['aeva_has_preview']) ? '<a href="' . $galurl . 'sa=media;in=' . $id_file . '" title="' . westr::htmlspecialchars($desc) . '"' . ($amSettings['use_zoom'] ? ' class="zoom"' : '') . '>' : '')
		. '<img src="' . $preview_image . '" width="' . $cur_width . '" height="' . $cur_height . '">'
		. (!empty($context['aeva_has_preview']) ? '</a>' : '');
	}
	elseif ($type == 'doc')
	{
		$width = empty($cur_width) ? 48 : $cur_width;
		$height = empty($cur_height) ? 52 : $cur_height;
		$output .= '
		<a href="' . $galurl . 'sa=media;in=' . $id_file . ';dl" title="' . westr::htmlspecialchars($desc) . '">'
		. '<img src="' . $preview_image . '" width="' . $width . '" height="' . $height . '"></a>';
	}
	else
	{
		$mime = $obj->getMimeType($obj->src);
		$player = $player + 1 ?: '';

		$qt = false;
		$width = empty($cur_width) ? 640 : $cur_width;
		$height = empty($cur_height) ? 360 : $cur_height;

		if ($type == 'audio' && !we::is('ie[-8]'))
		{
			add_js_file('player.js');
			add_js("\n\t" . 'spectrum("#player");');
		}

		switch ($mime)
		{
			case 'audio/mpeg':
			case 'audio/mp4a-latm':
			case 'audio/ogg':
				$width = $pwid;
				$height = $show_audio_preview ? 40 : 80;
				if (we::is('ie[-8]'))
					break;

			case 'video/x-flv':
			case 'video/x-m4v':
			case 'video/mp4':
			case 'video/3gpp':
			case 'video/webm':
			case 'video/x-matroska':
				$output .= init_videojs();

				add_js('
	videojs("player' . $player . '");
	if ("localStorage" in window)
		videojs("player' . $player . '").volume(localStorage.getItem("volume") || 1).onVolumeChange = function (val) { localStorage.setItem("volume", val); };');

				$tag = $type == 'audio' ? 'audio' : 'video';

				$output .= '
		<' . $tag . ' width="' . $width . '" height="' . $height . '"' . ($type == 'audio' ? ' loop ' : ' ') . 'controls class="video-js vjs-default-skin" id="player' . $player . '"'
			. ($show_audio_preview ? '' : ' poster="' . $preview_image . '"') . '>
			<source src="' . $galurl . 'sa=media;in=' . $id_file . $increm . '" type="' . $mime . '" />
		</' . $tag . '>';

				return $show_audio_preview ? $output . '
		</div>' : $output;

			case 'video/quicktime':
				if (we::is('ie'))
					$output .= '
		<object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab" width="' . $width . '" height="' . ($height + 15) . '">
			<param name="src" value="' . $galurl . 'sa=media;in=' . $id_file . ';v">
			<param name="wmode" value="transparent">
			<param name="controller" value="true">
			<param name="autoplay" value="false">
			<param name="loop" value="false">';

				$output .='
			<embed src="' . $galurl . 'sa=media;in=' . $id_file . ';v" width="' . $width . '" height="' . ($height + 15) . '" type="' . $mime . '"
				pluginspage="http://www.apple.com/quicktime/download/" controller="true" autoplay="false" loop="false" wmode="transparent">';

				if (we::is('ie'))
					$output .='
		</object>';

				return $output;

			case 'video/mpeg':
			case 'video/x-msvideo':
			case 'video/x-ms-wmv':
				$class_id = 'CLSID:05589FA1-C356-11CE-BF01-00AA0055595A';

				if (isset($_COOKIE[$cookiename]))
					$upcook = ';upcook=' . urlencode(base64_encode($_COOKIE[$cookiename]));
			break;
		}

		if (!isset($class_id))
			$class_id = 'CLSID:22D6F312-B0F6-11D0-94AB-0080C74C7E95';

		if (we::is('ie'))
			$output .= '
		<object classid="' . $class_id . '" width="' . $width . '" height="' . $height . '">
			<param name="wmode" value="transparent">
			<param name="ShowDisplay" value="0">
			<param name="ShowControls" value="1">
			<param name="AutoStart" value="0">
			<param name="AutoRewind" value="-1">
			<param name="Volume" value="0">
			<param name="FileName" value="' . $galurl . 'sa=media;in=' . $id_file . ';v">';

		$output .= '
			<embed src="' . $galurl . 'sa=media;in=' . $id_file . ';v' . (isset($upcook) ? $upcook : '')
			. '" width="' . $width . '" height="' . ($height+42) . '" type="' . $mime . '" controller="true" autoplay="false" autostart="0" loop="false" wmode="transparent">';

		if (we::is('ie'))
			$output .= '
		</object>';
	}

	return $show_audio_preview ? $output . '
		</div>' : $output;
}

function aeva_initZoom($autosize, $peralbum = array())
{
	static $done = false;

	if ($done)
		return;
	$done = true;

	loadLanguage('Media');
	add_css_file('zoom');
	add_js_file('zoomedia.js');
	add_js('
	$("a.zoom").zoomedia({
		outline: "', empty($peralbum) || !in_array($peralbum['outline'], array('drop-shadow', 'white', 'black')) ? 'glass' : $peralbum['outline'], '"
	});');
	return;


























































}





function aeva_getEncryptedFilename($name, $id, $check_for_encrypted = false, $both = false)
{
	global $amSettings;

	if ($id < 5)
		return $both ? array($name, $name) : $name;



	$clean_name = strtr(
		$name,
		"\x8a\x8e\x9a\x9e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xe0\xe1\xe2\xe3\xe4\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xff",
		'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy'
	);

	$clean_name = strtr($clean_name, array("\xde" => 'TH', "\xfe" => 'th', "\xd0" => 'DH', "\xf0" => 'dh', "\xdf" => 'ss', "\x8c" => 'OE', "\x9c" => 'oe', "\xc6" => 'AE', "\xe6" => 'ae', "\xb5" => 'u'));


	$clean_name = preg_replace(array('/\s/', '/[^\w.-]/'), array('_', ''), $clean_name);
	$ext = aeva_getExt($name);



	$enc_name = $id . '_' . strtr($clean_name, '.', '_') . md5($clean_name) . '_ext' . $ext;
	$clean_name = substr(sha1($id), 0, 2) . sha1($id . $clean_name) . '.' . $ext;

	return $both ? array($clean_name, $enc_name) : (!$check_for_encrypted || empty($amSettings['clear_thumbnames']) ? $enc_name : $clean_name);
}


function aeva_getPHPSize($size)
{
	if (preg_match('/^([\d\.]+)([gmk])?$/i', ini_get($size), $m))
	{
		$value = $m[1];
		if (isset($m[2]))
		{
			switch (strtolower($m[2]))
			{
				case 'g': $value *= 1024;
				case 'm': $value *= 1024;
				case 'k': $value *= 1024;
			}
		}
	}
	return isset($value) ? $value : 0;
}

function aeva_getTags($taglist)
{
	return aeva_splitTags(str_replace('&quot;', '"', $taglist));
}

function aeva_splitTags($string, $separator = ',')
{
	$elements = explode($separator, $string);
	for ($i = 0; $i < count($elements); $i++)
	{
		$nquotes = substr_count($elements[$i], '"');
		if ($nquotes % 2 == 1)
			for ($j = $i+1; $j < count($elements); $j++)
				if (substr_count($elements[$j], '"') % 2 == 1)
				{
					array_splice($elements, $i, $j-$i+1, implode($separator, array_slice($elements, $i, $j-$i+1)));
					break;
				}
		if ($nquotes > 0)
			$elements[$i] = str_replace('""', '"', $elements[$i]);
		$elements[$i] = westr::htmlspecialchars(trim($elements[$i], '" '));
	}
	return $elements;
}

function aeva_string($str, $is_filename = true, $limit = 255, $ellipsis = true, $check_multibyte = false, $cut_long_words = false, $hard_limit = 0)
{
	global $settings;

	if (!empty($settings['embed_enabled']) && function_exists('aeva_onposting'))
		$str = aeva_onposting($str);

	if ($limit === 0 || westr::strlen($str) <= $limit)
	{
		if ($cut_long_words)
		{
			$cw = is_int($cut_long_words) ? round($cut_long_words / 2) + 1 : round($limit / 3) + 1;
			$str = preg_replace('~(\w{'.$cw.'})(\w+)~u', '$1&shy;$2', $str);
		}
		return $str;
	}

	$ext = $is_filename ? strrchr($str, '.') : '';
	$base = !empty($ext) ? substr($str, 0, -strlen($ext)) : $str;
	return westr::cut($base, $limit, $check_multibyte, $cut_long_words, $ellipsis, false, $hard_limit) . $ext;
}

function aeva_entities2utf($mixed)
{
	if (function_exists('mb_decode_numericentity'))
		return mb_decode_numericentity($mixed, array(0x80, 0x2ffff, 0, 0xffff), 'UTF-8');

	$mixed = preg_replace('/&#(\d+);/me', 'aeva_utf8_chr($1)', $mixed);
	$mixed = preg_replace('/&#x(\d+);/me', 'aeva_utf8_chr(0x$1)', $mixed);
	return $mixed;
}

function aeva_utf8_chr($code)
{
	if ($code < 128) return chr($code);
	if ($code < 2048) return chr(($code >> 6) + 192) . chr(($code & 63) + 128);
	if ($code < 65536) return chr(($code >> 12) + 224) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
	if ($code < 2097152) return chr($code >> 18 + 240) . chr((($code >> 12) & 63) + 128) . chr(($code >> 6) & 63 + 128) . chr($code & 63 + 128);
}
