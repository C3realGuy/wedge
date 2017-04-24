<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');





function add_js()
{
	global $context, $footer_coding;

	if (empty($footer_coding))
	{
		$footer_coding = true;
		$context['footer_js'] .= '
<script>';
	}
	$args = func_get_args();
	$context['footer_js'] .= implode('', $args);
}




function add_js_unique($code)
{
	static $uniques = array();

	if (isset($uniques[$code]))
		return;
	$uniques[$code] = true;

	add_js($code);
}





function add_js_inline()
{
	global $context;

	$args = func_get_args();
	$context['footer_js_inline'] .= implode('', $args);
}









function add_js_file($files = array(), $is_direct_url = false, $is_out_of_flow = false, $ignore_files = array())
{
	global $context, $settings, $footer_coding;
	static $done_files = array();

	if (!is_array($files))
		$files = (array) $files;


	$files = array_diff(array_keys(array_flip($files)), $done_files);
	if (empty($files))
		return;

	$done_files = array_merge($done_files, $files);
	if ($is_direct_url || strpos($files[0], '://') !== false || strpos($files[0], '//') === 0)
	{
		if (!empty($footer_coding))
		{
			$footer_coding = false;
			$context['footer_js'] .= '
</script>';
		}
		$context['footer_js'] .= '
<script src="' . implode('"></script>
<script src="', $files) . '"></script>';
		return;
	}

	$id = '';
	$latest_date = 0;

	$full_path = array();
	foreach ($files as $fid => $file)
	{
		if (file_exists($add = ROOT_DIR . '/core/javascript/' . $file))
			$files[$fid] = ROOT_DIR . '/core/javascript/' . $file;
		elseif (!file_exists($add = $file))
		{
			unset($files[$fid]);
			continue;
		}



		if (!isset($ignore_files[$file]))
			$id .= str_replace('/', '_', substr(strpos($file, '/') !== false ? substr(strrchr($file, '/'), 1) : $file, 0, strpos($file, '.min.js') !== false ? -7 : -3)) . '-';

		$latest_date = max($latest_date, filemtime($add));
	}


	if (empty($files))
		return;


	$id .= (we::$is_member && !we::$is_admin ? 'm-' : '');
	$id .= (we::$is_admin ? 'a-' : '');
	$id = !empty($settings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;
	$latest_date %= 1000000;

	$lang_name = !empty($settings['js_lang'][$id]) && !empty(we::$user['language']) && we::$user['language'] != $settings['language'] ? we::$user['language'] . '-' : '';
	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.1]') ? '.jgz' : '.js.gz') : '.js';


	$is_jquery = count($files) == 1 && reset($files) == 'jquery-' . $context['jquery_version'] . '.min.js';
	$final_name = $is_jquery ? $context['jquery_version'] : $id . $lang_name . $latest_date;
	if (!file_exists(CACHE_DIR . '/js/' . $final_name . $ext))
	{
		wedge_cache_js($id, $lang_name, $latest_date, $ext, $files, $can_gzip, true);
		if ($is_jquery)
			@rename(CACHE_DIR . '/js/' . $id . $lang_name . $latest_date . $ext, CACHE_DIR . '/js/' . $final_name . $ext);
	}

	$final_script = CACHE . '/js/' . $final_name . $ext;


	if ($is_out_of_flow)
		return $final_script;

	if (!empty($footer_coding))
	{
		$footer_coding = false;
		$context['footer_js'] .= '
</script>';
	}
	$context['footer_js'] .= '
<script src="' . $final_script . '"></script>';
}










function add_plugin_js_file($plugin_name, $files = array(), $is_direct_url = false, $is_out_of_flow = false)
{
	global $context, $settings, $footer_coding;
	static $done_files = array();

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	if (!is_array($files))
		$files = (array) $files;


	if ($is_direct_url)
	{
		foreach ($files as $k => $v)
			$files[$k] = $context['plugins_url'][$plugin_name] . '/' . $v;
		return add_js_file($files, true, $is_out_of_flow);
	}


	if (empty($done_files[$plugin_name]))
		$done_files[$plugin_name] = array_flip(array_flip($files));
	else
	{
		$files = array_diff(array_keys(array_flip($files)), $done_files[$plugin_name]);
		$done_files[$plugin_name] = array_merge($done_files[$plugin_name], $files);
	}

	$id = '';
	$latest_date = 0;

	foreach ($files as $k => &$file)
	{
		$file = $context['plugins_dir'][$plugin_name] . '/' . $file;
		if (!file_exists($file))
			unset($files[$k]);


		$id .= str_replace('/', '_', substr(strrchr($file, '/'), 1, strpos($file, '.min.js') !== false ? -7 : -3)) . '-';
		$latest_date = max($latest_date, filemtime($file));
	}

	if (empty($files))
		return;

	$id = substr(strrchr($context['plugins_dir'][$plugin_name], '/'), 1) . '-' . $id;
	$id .= (we::$is_member && !we::$is_admin ? 'm-' : '');
	$id .= (we::$is_admin ? 'a-' : '');
	$id = !empty($settings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;
	$latest_date %= 1000000;

	$lang_name = !empty($settings['js_lang'][$id]) && !empty(we::$user['language']) && we::$user['language'] != $settings['language'] ? we::$user['language'] . '-' : '';
	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.1]') ? '.jgz' : '.js.gz') : '.js';

	if (!file_exists(CACHE_DIR . '/js/' . $id . $lang_name . $latest_date . $ext))
		wedge_cache_js($id, $lang_name, $latest_date, $ext, $files, $can_gzip, true);

	$final_script = CACHE . '/js/' . $id . $lang_name . $latest_date . $ext;


	if ($is_out_of_flow)
		return $final_script;

	if (!empty($footer_coding))
	{
		$footer_coding = false;
		$context['footer_js'] .= '
</script>';
	}
	$context['footer_js'] .= '
<script src="' . $final_script . '"></script>';
}






function add_css()
{
	global $context;

	if (empty($context['header_css']))
		$context['header_css'] = '';

	$args = func_get_args();
	$context['header_css'] .= ($args[0][0] !== "\n" ? "\n" : '') . implode('', $args);
}









function add_css_file($original_files = array(), $add_link = true, $is_main = false, $ignore_files = array())
{
	global $settings, $context, $db_show_debug, $files;
	static $cached_files = array(), $paths_done = array();


	$latest_date = 0;
	$hardcoded_css = array();
	$original_files = (array) $original_files;
	foreach ($original_files as $key => $path)
	{
		if (isset($paths_done[$path]))
		{
			unset($original_files[$key]);
			continue;
		}
		if (strpos($path, '/') !== false)
		{
			unset($original_files[$key]);
			if (file_exists($path))
			{
				$hardcoded_css[] = $path;
				$latest_date = max($latest_date, filemtime($path));
			}
		}
		$paths_done[$path] = true;
	}


	if (empty($original_files) && empty($hardcoded_css))
		return false;


	$original_files = array_merge(array('common' => 0), array_flip($original_files));
	$files = array_keys($original_files);


	if (!isset($context['skin_folders']))
		wedge_get_skin_options();

	$fallback_folder = rtrim(SKINS_DIR . '/' . reset($context['css_folders']), '/') . '/';
	$deep_folder = rtrim(SKINS_DIR . '/' . end($context['css_folders']), '/') . '/';
	$found_suffixes = array();
	$found_files = array();
	$css = array();


	we::$cache['global'] = 'global';



	foreach ($context['skin_folders'] as $fold)
	{
		if ($fold === $fallback_folder)
			$fallback_folder = '';

		if (empty($cached_files[$fold]))
			$cached_files[$fold] = array_diff((array) @scandir($fold ? $fold : '', 1), array('.', '..', '.htaccess', 'index.php', 'skin.xml', 'custom.xml'));


		we::$cache['local'] = $fold == $deep_folder ? 'local' : false;

		foreach ($cached_files[$fold] as $file)
		{
			if (substr($file, -4) !== '.css')
				continue;

			$radix = substr($file, 0, strpos($file, '.'));
			if (!isset($original_files[$radix]))
				continue;


			$suffix = substr(strstr($file, '.'), 1, -4);



			if (!empty($suffix) && strpos($suffix, 'replace') !== false)
			{
				$suffix = preg_replace('~[,&| ]*replace[,&| ]*~', '', $suffix);
				foreach ($css as $key => $val)
					if (strpos($val, '/' . $radix . '.' . ($suffix ? $suffix . '.' : '')) !== false)
						unset($css[$key]);
			}


			if (!empty($suffix) && !($found_suffix = we::is($suffix)))
				continue;

			$css[] = $fold . $file;


			if (empty($suffix))
				$found_files[] = $radix;

			else
				$found_suffixes[] = $found_suffix;

			if (!empty($db_show_debug))
				$context['debug']['sheets'][] = $file . ' (' . basename(TEMPLATES) . ')';
			$latest_date = max($latest_date, filemtime($fold . $file));
		}
	}


	we::$cache['local'] = false;


	if (!empty($fallback_folder))
	{
		$not_found = array_flip(array_diff($files, $found_files));
		$fold = $fallback_folder;

		if (empty($cached_files[$fold]))
			$cached_files[$fold] = array_diff((array) @scandir($fold ? $fold : '', 1), array('.', '..', '.htaccess', 'index.php', 'skin.xml', 'custom.xml'));

		foreach ($cached_files[$fold] as $file)
		{
			if (substr($file, -4) !== '.css')
				continue;

			$radix = substr($file, 0, strpos($file, '.'));
			if (!isset($original_files[$radix], $not_found[$radix]))
				continue;

			$css[] = $fold . $file;

			if (!empty($db_show_debug))
				$context['debug']['sheets'][] = $file . ' (' . basename(TEMPLATES) . ')';
			$latest_date = max($latest_date, filemtime($fold . $file));
		}
	}




	usort($css, 'sort_skin_files');

	$folder = end($context['css_folders']);
	$id = $folder;
	$latest_date %= 1000000;

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.1]') ? '.cgz' : '.css.gz') : '.css';


	if (isset($context['skin_available_languages']) && we::$user['language'] !== 'english')
		$found_suffixes[] = we::$user['language'];


	$target_folder = trim($id . '-' . implode('-', array_filter(array_diff($files, (array) 'common', $ignore_files))), '-');


	$final_script = CACHE . '/css/' . wedge_cache_css_files($target_folder . ($target_folder ? '/' : ''), $found_suffixes, $latest_date, array_merge($css, $hardcoded_css), $can_gzip, $ext);

	if ($final_script == CACHE . '/css/')
		return false;

	if ($is_main)
		return $context['cached_css'] = $final_script;


	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

function add_plugin_css_file($plugin_name, $original_files = array(), $add_link = false, $ignore_files = array())
{
	global $context, $settings;

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	if (!is_array($original_files))
		$original_files = (array) $original_files;


	$files = array_keys(array_flip($original_files));
	$basefiles = array();



	foreach ($files as $file)
	{
		if (substr($file, -4) === '.css')
			$file = substr($file, 0, -4);
		$basefiles[] = substr(strrchr($file, '/'), 1);
		$files[] = $file;
	}

	$latest_date = 0;

	foreach ($files as $i => &$file)
	{
		$full_path = $context['plugins_dir'][$plugin_name] . '/' . $file . '.css';
		if (!file_exists($full_path))
		{
			unset($files[$i]);
			continue;
		}

		$file = $full_path;
		$latest_date = max($latest_date, filemtime($full_path));
	}

	$pluginurl = '..' . str_replace(ROOT, '', $context['plugins_url'][$plugin_name]);



	$id = array_filter(array_merge(
		array($context['enabled_plugins'][$plugin_name]),
		$basefiles,
		we::$user['language'] !== 'english' ? (array) we::$user['language'] : array()
	));
	$latest_date %= 1000000;

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.1]') ? '.cgz' : '.css.gz') : '.css';


	$target_folder = trim(str_replace(array('/', ':'), '-', strtolower($plugin_name) . '-' . implode('-', array_filter(array_diff($original_files, (array) 'common', $ignore_files)))), '-');


	$final_script = CACHE . '/css/' . wedge_cache_css_files($target_folder . ($target_folder ? '/' : ''), $id, $latest_date, $files, $can_gzip, $ext, array('$plugindir' => $context['plugins_url'][$plugin_name]));

	if ($final_script == CACHE . '/css/')
		return false;


	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

function sort_skin_files($a, $b)
{
	global $context, $files;

	$c = strrchr($a, '/');
	$d = strrchr($b, '/');

	$i = substr($c, 1, strpos($c, '.') - 1);
	$j = substr($d, 1, strpos($d, '.') - 1);


	if ($i == $j)
	{

		foreach ($context['css_folders'] as $folder)
		{
			$root = SKINS_DIR . ($folder ? '/' . $folder : '');
			$is_a = $a === $root . $c;
			$is_b = $b === $root . $d;
			if ($is_a && !$is_b)
				return -1;
			elseif (!$is_a && $is_b)
				return 1;
		}


		$x = strlen($c);
		$y = strlen($d);
		if ($x > $y)
			return 1;
		if ($x < $y)
			return -1;
		return 0;
	}


	foreach ($files as $file)
		if ($i === $file)
			return -1;
		elseif ($j === $file)
			return 1;
	return 0;
}




function wedge_get_css_filename($add)
{
	global $settings;

	$suffix = array_flip(array_filter(array_map('we::is', is_array($add) ? $add : explode('|', $add))));

	if (isset($suffix['m' . MID]))
		unset($suffix['member'], $suffix['admin']);
	if (isset($suffix['admin']))
		unset($suffix['member']);
	if (isset($suffix[we::$os['os']]))
		$suffix = array(str_replace('dows', '', we::$os['os'] . we::$os['version']) => true) + array_diff_key($suffix, array(we::$os['os'] => 1));

	if (!empty(we::$browser['agent']))
		$suffix = array(we::$browser['agent'] . (!empty(we::$browser['version']) ? we::$browser['version'] : '') => 1) + $suffix;
	$id = implode('-', array_keys($suffix));

	return $id ? (empty($settings['obfuscate_filenames']) ? $id : md5($id)) : '';
}












function wedge_cache_css_files($folder, $ids, $latest_date, $css, $gzip = false, $ext = '.css', $additional_vars = array())
{
	global $css_vars, $context, $time_start;

	$final_folder = substr(CACHE_DIR . '/css/' . $folder, 0, -1);
	$cachekey = 'css_files-' . $folder . implode('-', $ids);



	if (($add = cache_get_data($cachekey, 'forever')) !== null)
	{
		$id = wedge_get_css_filename($add);

		$full_name = ($id ? $id . '-' : '') . $latest_date . $ext;
		$final_file = $final_folder . '/' . $full_name;

		if (file_exists($final_file))
			return $folder . $full_name;
	}

	$sleep_time = microtime(true);
	we::$user['extra_tests'] = array();

	if (!empty($folder) && $folder != '/' && !file_exists($final_folder))
	{
		@mkdir($final_folder, 0755);
		@copy(CACHE_DIR . '/css/index.php', $final_folder . '/index.php');
	}

	$final = '';
	$discard_dir = strlen(ROOT_DIR) + 1;


	loadSource('Class-CSS');

	$plugins = array(
		new wess_dynamic(),
		new wess_if(),
		new wess_mixin(),
		new wess_var(),
		new wess_color(),
		new wess_func(),
		new wess_math(),
		new wess_if(true),
		new wess_nesting(),
		new wess_prefixes(),
	);


	if (we::is('ie[-9]'))
		$plugins[] = new wess_rgba();




	if ($gzip && !we::is('ie6,ie7'))
		$plugins[] = new wess_base64($folder);



	$languages = isset($context['skin_available_languages']) ? $context['skin_available_languages'] : array('english');
	$css_vars = array(
		'$language' => isset(we::$user['language']) && in_array(we::$user['language'], $languages) ? we::$user['language'] : $languages[0],
		'$images_dir' => ASSETS_DIR,
		'$theme_dir' => SKINS_DIR,
		'$root_dir' => ROOT_DIR,
		'$images' => ASSETS,
		'$root' => ROOT,
	);
	if (!empty($additional_vars))
		foreach ($additional_vars as $key => $val)
			$css_vars[$key] = $val;
	if (!empty($context['plugins_dir']))
		foreach ($context['plugins_dir'] as $key => $val)
			$css_vars['$plugins_dir[\'' . $key . '\']'] = str_replace(ROOT_DIR, '', $val);
	if (!empty($context['plugins_url']))
		foreach ($context['plugins_url'] as $key => $val)
			$css_vars['$plugins[\'' . $key . '\']'] = str_replace(ROOT, '', $val);


	$deep_folder = rtrim(SKINS_DIR . '/' . end($context['css_folders']), '/');


	loadSource('Subs-CachePHP');
	foreach ((array) $css as $file)
	{
		$local = apply_plugin_mods($file, '', true);
		if (dirname($file) === $deep_folder && strpos(strtolower($local), 'local') !== false)
			$local = preg_replace('~@(is\h+\([^),]*|(?:else)?if\h+[^\n]*)\blocal\b~i', '@$1true', $local);
		$final .= str_replace('$here', str_replace(ROOT_DIR, ROOT, dirname($file)), $local);
	}
	unset($local);

	if (empty($final))
	{
		cache_put_data($cachekey, '', 'forever');
		return false;
	}


	$final = preg_replace_callback('~\$(context|settings|theme|txt)\[([\'"])(.*?)\2]~', 'wedge_replace_theme_vars', $final);


	$final = str_replace(array("\r\n", "\r"), "\n", $final);
	$final = preg_replace('~/\*(?!!).*?\*/~s', '', $final);


	preg_match_all('~\n?/\*!(.*?)\*/\n?~s', $final, $comments);
	$final = preg_replace('~/\*!.*?\*/~s', '.wedge_comment_placeholder{border:0}', $final);

	$final = preg_replace('~\n\t*//[^\n]*~', "\n", $final);
	$final = preg_replace('~//[ \t][^\n]*~', '', $final);


	$final = preg_replace('~(?<=\s)content\h*:\h*~', 'content:', $final);
	preg_match_all('~(?<=\s)content:(.*?)[\h;]*(?=[}\v])~', $final, $contags);
	$context['reset_content_counter'] = true;
	$final = preg_replace_callback('~(?<=\s)content:.*?[\h;]*(?=[}\v])~', 'wedge_hide_content', $final);

	foreach ($plugins as $plugin)
		$plugin->process($final);

	if (we::$user['extra_tests'])
	{


		preg_match_all('~[bcm][0-9]+|[a-z]+~i', preg_replace('~".*?"~', '', implode(' ', array_flip(array_flip(array_merge($ids, we::$user['extra_tests']))))), $matches);
		$add = array_diff(array_flip(array_flip($matches[0])), array_keys(we::$browser), array('global', 'local', 'true'));
	}


	cache_put_data($cachekey, empty($add) ? $ids : $add, 'forever');


	$id = wedge_get_css_filename(isset($add) ? $add : array());

	$full_name = ($id ? $id . '-' : '') . $latest_date . $ext;
	$final_file = $final_folder . '/' . $full_name;


	if (is_array($files = glob($final_folder . '/' . ($id ? $id . '-*' : '[0-9]*') . $ext, GLOB_NOSORT)))
		foreach ($files as $del)
			if (($id || preg_match('~/\d+\.~', $del)) && strpos($del, (string) $latest_date) === false)
				@unlink($del);


	$final = preg_replace('~\s+final\b~', '', $final);



	$final = preg_replace('~\s\[~', '#wedge-bracket#', $final);
	$final = preg_replace('~\s*([][+:;,>{}\s])\s*~', '$1', $final);
	$final = str_replace('#wedge-bracket#', ' [', $final);



	while (preg_match('~/(?:\.[^.]*|[^.:*?"<>|/][^:*?"<>|/]*)/\.\./~', $final, $relpath))
		$final = str_replace($relpath[0], '/', $final);


	$final = str_replace(
		array('#wedge-quote#', "\n\n", ';;', ';}', "}\n", "\t", ' !important', 'raw-url('),
		array('"', "\n", ';', '}', '}', ' ', '!important', 'url('),
		$final
	);





	$selector = '([abipqsu]|[!+>&#*@:.a-z0-9][^{};,\n"()\~+> ]+?)';
	if (we::is('chrome[12-],firefox[4-],safari[5.2-]') && preg_match_all('~(?:^|})' . $selector . '([>+: ][^,{]+)(?:,' . $selector . '\2)+(?={)~', $final, $matches, PREG_SET_ORDER))
	{
		$magic = we::$browser['webkit'] ? (we::$browser['safari'] && we::$browser['version'] >= 9 ? ':matches' : ':-webkit-any') : ':-moz-any';
		foreach ($matches as $m)
		{

			if (strpos($m[0], ':') !== false && strhas($m[0], array(':before', ':after', ':first-letter', ':first-line', ':selection')))
				continue;
			$final = str_replace(
				$m[0],
				($m[0][0] === '}' ? '}' : '') . $magic . '(' . str_replace(
					array($m[2] . ',', $m[2] . '{', '}'),
					array(',', '', ''),
					$m[0] . '{'
				) . ')' . $m[2],
				$final
			);
		}
	}

	wedge_process_css_replacements($final);


	if (!empty($comments))
		wedge_replace_placeholders('.wedge_comment_placeholder{border:0}', $comments[0], $final);


	if (!empty($contags))
		wedge_replace_numbered_placeholders('content:wedge', $contags[0], $final);

	$final = ltrim($final, "\n");


	if (strpos($final, '{}') !== false)
		$final = preg_replace('~(?<=[{}])[^{}]*{}~', '', $final);

	if ($gzip)
		$final = gzencode($final, 9);

	@file_put_contents($final_file, $final);


	$time_start += microtime(true) - $sleep_time;

	return $folder . $full_name;
}

function wedge_hide_content()
{
	global $context;
	static $i;

	if (!empty($context['reset_content_counter']))
	{
		$i = 0;
		unset($context['reset_content_counter']);
	}
	return 'content:wedge' . $i++;
}

function wedge_replace_theme_vars($match)
{
	global ${$match[1]};

	return isset(${$match[1]}[$match[3]]) ? '"' . (is_array(${$match[1]}[$match[3]]) ? count(${$match[1]}[$match[3]]) : ${$match[1]}[$match[3]]) . '"' : '""';
}



function wedge_replace_placeholders($str, $arr, &$final, $add_nl = false)
{
	$i = 0;
	$len = strlen($str);
	while (($pos = strpos($final, $str)) !== false)
		$final = substr_replace($final, $add_nl ? "\n" . $arr[$i++] . "\n" : $arr[$i++], $pos, $len);
}


function wedge_replace_numbered_placeholders($str, $arr, &$final)
{
	$len = strlen($str);
	while (($pos = strpos($final, $str)) !== false)
	{
		$index = intval(substr($final, $pos + $len));
		$final = substr_replace($final, $arr[$index], $pos, $len + strlen($index));
	}
}

function wedge_process_css_replacements(&$final)
{
	global $context, $settings;


	$rep = isset($context['ob_replacements']) ? $context['ob_replacements'] : array();
	if (!empty($settings['page_replacements']) && ($extra_pr = unserialize($settings['page_replacements'])) !== false)
		$rep = array_merge($rep, $extra_pr);
	if (!empty($rep))
		$final = str_replace(array_keys($rep), array_values($rep), $final);



	$set_relative = true;
	if (!empty($rep))
		foreach ($rep as $key => $val)
			$set_relative &= strpos($key, 'gz/css') === false;

	if ($set_relative)
	{
		preg_match('~.*://[^/]+~', ROOT, $root_root);
		if (!empty($root_root))
			$final = str_replace('url(' . $root_root[0], 'url(', $final);
	}
}


function dynamic_language_flags()
{
	global $context;

	if (empty($context['languages']) || count($context['languages']) < 2)
		return;

	$rep = '';
	foreach ($context['languages'] as $language)
	{
		$icon = str_replace(ROOT_DIR, '', LANGUAGES_DIR) . $language['folder'] . '/Flag.' . $language['filename'] . '.gif';
		$rep .= '
.flag_' . $language['filename'] . ' mixes .inline-block("")
	background: url($root'. $icon . ') no-repeat 0 center
	padding-left: math(width($root_dir'. $icon . '))px
	min-height: height($root_dir'. $icon . ')px';
	}
	return $rep;
}


function dynamic_group_colors()
{

	if (defined('WEDGE_INSTALLER'))
		return '';

	$bius = array('b', 'i', 'u', 's');
	$rep = '';
	$request = wesql::query('
		SELECT id_group, online_color, format
		FROM {db_prefix}membergroups AS g
		WHERE g.online_color != {string:blank} OR g.format != {string:blank}',
		array(
			'blank' => '',
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$rep .= '
.group' . $row['id_group'];

		if (!empty($row['online_color']))
			$rep .= '
	color: ' . $row['online_color'];

		if (!empty($row['format']))
		{
			$row['format'] = explode('|', $row['format']);

			if (in_array('b', $row['format']))
				$rep .= '
	font-weight: bold';

			if (in_array('i', $row['format']))
				$rep .= '
	font-style: italic';


			$text_decoration = array();
			if (in_array('u', $row['format']))
				$text_decoration[] = 'underline';
			if (in_array('s', $row['format']))
				$text_decoration[] = 'line-through';
			if (!empty($text_decoration))
				$rep .= '
	text-decoration: ' . implode(' ', $text_decoration);


			$row['format'] = array_diff($row['format'], $bius);
			if (!empty($row['format']))
			{

				$row['format'] = explode(';', implode('', $row['format']));
				array_walk($row['format'], 'trim');
				foreach ($row['format'] as $item)
					$rep .= '
	' . $item;
			}
		}
	}

	return $rep;
}


function dynamic_admin_menu_icons()
{
	global $admin_areas, $ina;

	function array_search_key($needle, &$arr)
	{
		global $ina;

		foreach ($arr as $key => &$val)
		{
			if (!is_array($val))
				continue;
			if (isset($val[$needle]))
				$ina[] = array($key, $val[$needle]);
			else
				array_search_key($needle, $val);
		}
	}

	$ina = array();
	array_search_key('icon', $admin_areas);

	$rep = '';
	foreach ($ina as $val)
	{
		$is_abs = isset($val[1]) && ($val[1][0] == '/' || strpos($val[1], '://') !== false);
		$icon = $is_abs ? $val[1] : '/admin/' . $val[1];
		$rep .= '
.admenu_icon_' . $val[0] . ' mixes .inline-block
	background: url('. ($is_abs ? $icon : '$images' . $icon) . ') no-repeat
	width: width('. ($is_abs ? $icon : '$images_dir' . $icon) . ')px
	height: height('. ($is_abs ? $icon : '$images_dir' . $icon) . ')px';
	}
	unset($ina);

	return $rep;
}













function wedge_cache_js($id, &$lang_name, $latest_date, $ext, $js, $gzip = false, $full_path = false)
{
	global $settings, $comments, $txt, $time_start;
	static $closure_failed = false;

	$sleep_time = microtime(true);
	$final = '';
	$dir = $full_path ? '' : ROOT_DIR . '/core/javascript/';
	$no_packing = array();


	if (is_array($files = glob(CACHE_DIR . '/js/' . $id. '*' . $ext, GLOB_NOSORT)))
		foreach ($files as $del)
			if (strpos($del, (string) $latest_date) === false)
				@unlink($del);

	$minify = empty($settings['minify']) ? 'none' : $settings['minify'];

	loadSource('Subs-CachePHP');
	foreach ($js as $file)
	{
		$cont = apply_plugin_mods($dir . $file, '', true);


		if (strpos($file, '.min.js') !== false)
		{
			$no_packing[] = preg_replace('~\n//[^\n]+$~', '', $cont);
			$cont = 'WEDGE_NO_PACKING();';
		}

		elseif (preg_match('~/\* Optimize:\n(.*?)\n\*/~s', $cont, $match))
		{
			$match = explode("\n", $match[1]);
			$search = $replace = array();
			foreach ($match as $variable)
			{
				$pair = explode(' = ', $variable);
				$search[] = $pair[0];
				$replace[] = $pair[1];
			}
			$cont = str_replace($search, $replace, $cont);
			if ($minify == 'none')
				$cont = preg_replace('~/\* Optimize:\n(.*?)\n\*/~s', '', $cont);
		}

		$cont = preg_replace(array('~\bfalse\b~', '~\btrue\b~'), array('!1', '!0'), $cont);
		$final .= $cont;
	}




	if (preg_match_all('~@language\h+([^\n;]+)[\n;]~i', $final, $languages))
	{

		$langstring = implode(',', $languages[1]);
		$langlist = serialize($langs = array_map('trim', explode(',', $langstring)));
		if (strpos($langstring, ':') !== false)
		{
			foreach ($langs as $i => $lng)
				if (strpos($lng, ':') !== false && count($exp = explode(':', $lng)) == 3)
				{
					loadPluginLanguage($exp[0] . ':' . $exp[1], $exp[2]);
					unset($langs[$i]);
				}
		}
		loadLanguage($langs);
		$final = str_replace($languages[0], '', $final);

		if (!isset($settings['js_lang'][$id]) || $settings['js_lang'][$id] != $langlist)
		{
			$use_update = !empty($settings['js_lang']);
			$settings['js_lang'][$id] = $langlist;
			$save = $settings['js_lang'];
			updateSettings(array('js_lang' => serialize($settings['js_lang'])), $use_update);
			$settings['js_lang'] = $save;

			$lang_name = !empty(we::$user['language']) && we::$user['language'] != $settings['language'] ? we::$user['language'] . '-' : '';
		}







		if (preg_match_all('~\$txt\[([\'"])(.*?)\1]~i', $final, $strings, PREG_SET_ORDER))
			foreach ($strings as $str)
				if (isset($txt[$str[2]]))
					$final = str_replace(
						$str[0],
						strtr(
							westr::entity_to_js_code(
								westr::utf8_to_entity(
									is_array($txt[$str[2]]) ?
										html_entity_decode(
											str_replace("\xe2\x80\xaf", "\xc2\xa0", json_encode($txt[$str[2]])),
											ENT_NOQUOTES,
											'UTF-8'
										)
									:
									JavaScriptEscape(
										html_entity_decode(
											str_replace("\xe2\x80\xaf", "\xc2\xa0", $txt[$str[2]]),
											ENT_NOQUOTES,
											'UTF-8'
										)
									)
								)
							),
							"\x0f\x10",
							'"\''
						),
						$final
					);
				else
					$final = str_replace($str[0], "'Error'", $final);
	}

	elseif (!empty($settings['js_lang'][$id]))
	{
		unset($settings['js_lang'][$id]);
		$save = $settings['js_lang'];
		updateSettings(array('js_lang' => serialize($settings['js_lang'])), true);
		$settings['js_lang'] = $save;
		$lang_name = '';
	}



	$final = preg_replace_callback(
		'~@if\s*\(?(guest|member|admin)\s*\)?\s*({((?:(?>[^{}]+)|(?-2))*)})(?:\s*@else\s*({((?:(?>[^{}]+)|(?-2))*)}))?~i',
		function ($match) { return !empty(we::$is[$match[1]]) ? $match[3] : (isset($match[5]) ? $match[5] : ''); },
		$final
	);

	if (!$closure_failed && !is_callable('curl_exec') && !preg_match('~1|yes|on|true~i', ini_get('allow_url_fopen')))
		$closure_failed = true;


	if ($minify === 'closure' && !$closure_failed)
	{

		preg_match_all('~/\*!\n.*?\*/~s', $final, $comments);
		if (!empty($comments[0]))
			$final = str_replace($comments[0], 'WEDGE_COMMENT();', $final);


		$data = 'output_info=compiled_code&output_format=json&js_code=' . urlencode(preg_replace('~/\*.*?\*/~s', '', $final));

		if (is_callable('curl_init'))
		{

			$ch = curl_init('http://closure-compiler.appspot.com/compile');

			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, 1);


			$packed_js = curl_exec($ch);


			curl_close($ch);
		}
		else
		{
			$packed_js = file_get_contents(
				'http://closure-compiler.appspot.com/compile',
				false, stream_context_create(
					array('http' => array(
						'method' => 'POST',
						'header' => 'Content-type: application/x-www-form-urlencoded',
						'content' => $data,
						'max_redirects' => 0,
						'timeout' => 15,
					))
				)
			);
		}

		$packed_js = json_decode($packed_js);

		if (!empty($packed_js->errors) || !empty($packed_js->serverErrors))
		{
			log_error('Google Closure Compiler - ' . print_r(empty($packed_js->errors) ? $packed_js->serverErrors : $packed_js->errors, true));
			$closure_failed = true;
		}
		elseif (!empty($packed_js->compiledCode))
			$final = $packed_js->compiledCode;
		else
			$closure_failed = true;

		unset($packed_js, $data);

		if (!empty($comments[0]))
			wedge_replace_placeholders('WEDGE_COMMENT();', $comments[0], $final, true);
	}

	if ($minify === 'packer' || $closure_failed)
	{

		preg_match_all('~/\*!\n.*?\*/~s', $final, $comments);
		if (!empty($comments[0]))
			$final = str_replace($comments[0], 'WEDGE_COMMENT();', $final);

		$final = preg_replace('~/\*.*?\*/~s', '', $final);
		$final = preg_replace('~\s//[^\n]*~', '', $final);

		loadSource('Class-Packer');
		$packer = new Packer;
		$final = $packer->pack($final);

		if (!empty($comments[0]))
			wedge_replace_placeholders('WEDGE_COMMENT();', $comments[0], $final, true);






































	}
	elseif ($minify === 'jsmin')
	{
		loadSource('Class-JSMin');
		$final = JSMin::minify($final);
	}


	if (!empty($no_packing))
		wedge_replace_placeholders('WEDGE_NO_PACKING();', $no_packing, $final, true);



	$final = preg_replace(
		array('~/\*!(?:[^*]|\*[^/])*?@package Wedge.*?\*/~s', '~(^|\n)\n/\*~', '~\*/\n~'),
		array("/*!\n * @package Wedge\n * @copyright René-Gilles Deberdt, wedge.org\n * @license http://wedge.org/license/\n */", '$1/*', '*/'),
		$final
	);

	if ($gzip)
		$final = gzencode($final, 9);

	@file_put_contents(CACHE_DIR . '/js/' . $id . $lang_name . $latest_date . $ext, $final);


	$time_start += microtime(true) - $sleep_time;
}







function wedge_cache_smileys($set, $smileys)
{
	global $context, $settings;

	$final_gzip = $final_raw = '';
	$path = ASSETS_DIR . '/smileys/' . $set . '/';
	$url = SMILEYS . '/' . $set . '/';


	clean_cache($context['smiley_ext'], 'smileys', CACHE_DIR . '/css');

	foreach ($smileys as $name => $smiley)
	{
		$filename = $path . $smiley['file'];
		$cur_url = $url;
		if (!file_exists($filename))
		{
			if (!file_exists($tmp = ASSETS_DIR . '/smileys/default/' . $smiley['file']))
				continue;
			$filename = $tmp;
			$cur_url = SMILEYS . '/default/';
		}

		if (($smiley['embed'] && filesize($filename) > 4096) || !$context['smiley_gzip'])
			$smiley['embed'] = false;
		list ($width, $height) = getimagesize($filename);
		$ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
		$stream = 'final_' . ($smiley['embed'] ? 'gzip' : 'raw');
		$$stream .= '.' . $name . '{width:' . $width . 'px;height:' . $height . 'px;background:url('
				. ($smiley['embed'] ? 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($filename)) : $cur_url . $smiley['file']) . ')}';
	}


	$final = '.smiley{display:inline-block;vertical-align:middle;text-indent:100%;white-space:nowrap;overflow:hidden}' . $final_raw . $final_gzip;
	unset($final_raw, $final_gzip);

	wedge_process_css_replacements($final);

	if ($context['smiley_gzip'])
		$final = gzencode($final, 9);

	file_put_contents(CACHE_DIR . '/css/smileys' . ($set == 'default' ? '' : '-' . $set) . '-' . $settings['smiley_cache'] . $context['smiley_ext'], $final);
}




function theme_base_css()
{
	global $context, $settings;


	$one_month_ago = time() - 30 * 24 * 3600;
	if (empty($settings['last_cache_purge']) || $settings['last_cache_purge'] < $one_month_ago)
	{
		clean_cache('css', $one_month_ago);
		clean_cache('js', $one_month_ago);
		updateSettings(array('last_cache_purge' => time()));
	}



	if (empty($context['cached_css']))
	{
		$context['main_css_files']['extra'] = false;
		$context['main_css_files']['custom'] = false;

		add_css_file(
			array_keys($context['main_css_files']),
			false, true,
			array_keys(array_diff($context['main_css_files'], array_filter($context['main_css_files'])))
		);
	}

	if (!empty($context['header_css']))
	{

		if (strpos($context['header_css'], '$behavior') !== false)
			$context['header_css'] = str_replace('$behavior', strpos(ROOT, '://' . we::$user['host']) !== false ? ROOT
				: preg_replace('~(?<=://)([^/]+)~', we::$user['host'], ROOT), $context['header_css']);
	}

	return '
	<link rel="stylesheet" href="' . $context['cached_css'] . '">';
}







function theme_base_js($indenting = 0)
{
	global $context;

	$tab = str_repeat("\t", $indenting);
	return (!empty($context['remote_js_files']) ? '
' . $tab . '<script src="' . implode('"></script>
' . $tab . '<script src="', $context['remote_js_files']) . '"></script>
	<script>window.$||document.write(\'<script src="' . add_js_file('jquery-' . $context['jquery_version'] . '.min.js', false, true) . '"><\/script>\')</script>' : '') . '
' . $tab . '<script src="' . add_js_file(
		array_keys($context['main_js_files']), false, true,
		array_diff($context['main_js_files'], array_filter($context['main_js_files']))
	) . '"></script>';
}






function wedge_get_extension($file)
{
	$ext = substr(strrchr($file, '.'), 1);
	if ($ext === 'gz')
		return substr(strrchr(substr($file, 0, -3), '.'), 1) . '.gz';
	return $ext;
}

function wedge_get_skeleton_operations($set, $op, $required_vars = array())
{
	global $context;

	if (strpos($set, '<' . $op) === false || !preg_match_all('~<' . $op . '(?:\s+[a-z]+="[^"]+")*\s*/?>~', $set, $matches, PREG_SET_ORDER))
		return;

	foreach ($matches as $match)
	{
		preg_match_all('~\s([a-z]+)="([^"]+)"~', $match[0], $v);
		$pos_id = array_search('id', $v[1], true);
		$id = $pos_id !== false ? $v[2][$pos_id] : 'main';
		$match_all = true;
		$arr = array($op);
		foreach ($required_vars as $var)
		{
			$match_all &= ($pos = array_search($var, $v[1], true)) !== false;
			if (!$match_all)
				continue 2;
			$arr[] = $v[2][$pos];
		}

		$context['skeleton_ops'][$id][$op . $arr[1]] = $arr;
	}
}


function wedge_skin_conditions(&$str)
{
	if (strpos($str, '<if') === false || !preg_match_all('~(?<=\n)(\t*)<if\b([^>]+)>(.*?)</if>~s', $str, $ifs, PREG_SET_ORDER))
		return;

	foreach ($ifs as $if)
	{
		$exe = array_merge(explode('<else>', $if[3]), array(''));
		$str = str_replace($if[0], str_replace("\n" . $if[1], "\n" . substr($if[1], 0, -1), $exe[(int) !we::is(trim($if[2]))]), $str);
	}
}





function wedge_parse_skin_tags(&$file, $name, $params = array())
{
	global $board_info;

	$tags = array();
	if (strpos($file, '</' . $name . '>') === false)
		return $tags;
	$params = (array) $params;


	if (!preg_match_all('~<' . $name . '\b([^>]*)>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</' . $name . '>~s', $file, $matches, PREG_SET_ORDER))
		return $tags;

	$empty_list = array();
	foreach ($params as $param)
		$empty_list[$param] = '';
	foreach ($matches as $match)
	{
		$item = $empty_list;
		$item['value'] = $match[2];

		if (empty($match[1]))
		{
			$tags[] = $item;
			continue;
		}

		elseif (strpos($match[1], 'for="') !== false && preg_match('~\bfor="([^"]*)"~', $match[1], $val) && !we::is($val[1]))
			continue;
		elseif (strpos($match[1], 'board-type="') !== false && (!isset($board_info, $board_info['type']) || (preg_match('~\bboard-type="([^"]*)"~', $match[1], $val) && $board_info['type'] != $val[1])))
			continue;

		elseif (strpos($match[1], 'url-') !== false && preg_match_all('~\burl-([a-z]+)="([^"]*)"~', $match[1], $url_bits, PREG_SET_ORDER))
			foreach ($url_bits as $bit)
				if (!isset($_GET[$bit[1]]) || ($_GET[$bit[1]] != $bit[2] && $bit[2] != '*'))
					continue 2;


		foreach ($params as $param)
			if (preg_match('~\b' . $param . '="([^"]*)"~', $match[1], $val))
				$item[$param] = $val[1];
		$tags[] = $item;
	}
	return $tags;
}




function wedge_get_skin_options($options_only = false)
{
	global $context;

	$skin_options = array();
	$skeleton = $macros = $set = '';


	$root_dir = SKINS_DIR;
	$context['skin_folders'] = array();
	$context['template_folders'] = array();
	$css_folders = array(ltrim(isset($context['skin']) ? $context['skin'] : '', '/'));

	loadSource('Themes');
	$test_folders = wedge_get_skin_list(true);


	for ($i = 0; $i < count($css_folders) && $i < 10; $i++)
	{
		$folder = $css_folders[$i];
		$context['skin_folders'][] = $full_path = $root_dir . ($folder ? '/' . $folder : '') . '/';

		if ($test_folders[$folder]['has_templates'])
			$context['template_folders'][] = $full_path . 'html';


		if ($test_folders[$folder]['type'] === 'replace')
			break;


		if (isset($test_folders[$folder]['parent']))
			$css_folders[] = $test_folders[$folder]['parent'];
	}
	$context['template_folders'][] = TEMPLATES_DIR;
	$context['css_folders'] = array_reverse($css_folders);
	$folder = reset($css_folders);


	foreach (array_reverse($context['skin_folders']) as $fold)
	{

		if (file_exists($fold . 'skeleton.xml'))
			$skeleton .= file_get_contents($fold . 'skeleton.xml');


		if (file_exists($fold . 'macros.xml'))
			$macros .= file_get_contents($fold . 'macros.xml');

		if (file_exists($fold . 'skin.xml'))
			$set = file_get_contents($fold . 'skin.xml');


		if (file_exists($fold . 'custom.xml'))
		{
			$custom = file_get_contents($fold . 'custom.xml');
			$skeleton = $custom . $skeleton;
			$macros = $custom . $macros;
			$set = $custom . $set;
		}
	}



	if (strpos($set, '</options>') !== false && preg_match('~<options>(.*?)</options>~s', $set, $match))
	{
		preg_match_all('~<([\w-]+)>(.*?)</\\1>~s', $match[1], $opts, PREG_SET_ORDER);
		foreach ($opts as $option)
			$skin_options[$option[1]] = trim($option[2]);
	}

	wedge_parse_skin_options($skin_options);

	if ($options_only)
		return;


	$sources = $skeleton ? array(&$set, &$skeleton) : array(&$set);
	foreach ($sources as &$source)
	{
		wedge_skin_conditions($source);


		wedge_get_skeleton_operations($source, 'move', array('block', 'to', 'where'));
		wedge_get_skeleton_operations($source, 'rename', array('block', 'to'));
		wedge_get_skeleton_operations($source, 'remove', array('block'));
	}


	$matches = $skeleton ? wedge_parse_skin_tags($skeleton, 'skeleton', 'id') : array();
	foreach ($matches as $match)
		$context['skeleton'][empty($match['id']) ? 'main' : $match['id']] = $match['value'];


	$matches = wedge_parse_skin_tags($macros, 'macro', 'name');
	foreach ($matches as $match)
		$context['macros'][$match['name']] = array(
			'has_if' => strpos($match['value'], '<if:') !== false,
			'body' => $match['value']
		);

	if (!$set)
		return;

	$matches = wedge_parse_skin_tags($set, 'replace', 'regex');
	foreach ($matches as $match)
		if (preg_match('~<from>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</from>\s*<to>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</to>~', $match['value'], $from_to))
			$context['skin_replace'][trim($from_to[1], "\x00..\x1F")] = array(trim($from_to[2], "\x00..\x1F"), !empty($match['regex']));


	$matches = wedge_parse_skin_tags($set, 'css', 'include');
	foreach ($matches as $match)
	{


		if (!empty($match['include']))
		{
			$includes = array_map('trim', explode(' ', $match['include']));
			$has_external = strpos($match['include'], '://') !== false || strpos($match['include'], '//') === 0;
			foreach ($includes as $val)
			{
				if ($has_external && (strpos($val, '://') !== false || strpos($val, '//') === 0))
					$context['header'] .= '
	<link rel="stylesheet" href="' . $val . '">';
				else
					add_css_file($val);
			}
		}
		if (!empty($match['value']))
			add_css(rtrim($match['value'], "\t"));
	}


	$matches = wedge_parse_skin_tags($set, 'script', 'include');
	foreach ($matches as $match)
	{


		if (!empty($match['include']))
		{
			$includes = array_map('trim', explode(' ', $match['include']));
			$has_here = strpos($match['include'], '$here') !== false;
			foreach ($includes as $val)
				add_js_file($has_here ? str_replace('$here', SKINS_DIR . '/' . $folder, $val) : $val);
		}
		if (!empty($match['value']))
			add_js(rtrim($match['value'], "\t"));
	}


	$matches = wedge_parse_skin_tags($set, 'template', array('name', 'param(?:s|eters)?', 'where'));
	foreach ($matches as $match)
		$context['template_' . ($match['where'] != 'before' && $match['where'] != 'after' ? 'override' : $match['where']) . 's']['template_' . preg_replace('~^template_~', '', $match['name'])] = array($match['param(?:s|eters)?'], $match['value']);

	$matches = wedge_parse_skin_tags($set, 'languages');
	foreach ($matches as $match)
		$context['skin_available_languages'] = array_filter(preg_split('~[\s,]+~', $match['value']));


	call_hook('skin_parser', array(&$set, &$skeleton, &$macros));
}

function wedge_parse_skin_options($skin_options)
{
	if (defined('SKIN_MOBILE'))
		return;


	define('SKIN_SIDEBAR_RIGHT', we::$is['SKIN_SIDEBAR_RIGHT'] = empty($skin_options['sidebar']) || $skin_options['sidebar'] == 'right');
	define('SKIN_SIDEBAR_LEFT', we::$is['SKIN_SIDEBAR_LEFT'] = isset($skin_options['sidebar']) && $skin_options['sidebar'] == 'left');
	unset($skin_options['sidebar']);
	if (!isset($skin_options['mobile']))
		$skin_options['mobile'] = 0;
	if (!isset($skin_options['shortmenu']))
		$skin_options['shortmenu'] = 1;


	foreach ($skin_options as $key => $val)
		define('SKIN_' . strtoupper($key), we::$is['SKIN_' . strtoupper($key)] = !empty($val));
}







function uncache()
{
	global $settings;

	if (empty($settings['cache_enable']) || !allowedTo('admin_forum'))
		return;

	clean_cache();
	clean_cache('css');
	clean_cache('js');
	updateSettings(array('last_cache_purge' => time()));


	redirectexit(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], ROOT) !== false ? $_SERVER['HTTP_REFERER'] : '');
}









function clean_cache($extensions = 'php', $filter = '', $force_folder = '', $remove_folder = false)
{
	global $cache_system;

	$folder = CACHE_DIR . '/keys';
	$is_recursive = false;
	$there_is_another = false;
	if ($extensions === 'css')
	{
		$folder = CACHE_DIR . '/css';
		$extensions = array('css', 'cgz', 'css.gz');
		$is_recursive = true;
	}
	elseif ($extensions === 'js')
	{
		$folder = CACHE_DIR . '/js';
		$extensions = array('js', 'jgz', 'js.gz');
	}
	elseif (!is_array($extensions))
		$extensions = ltrim($extensions, '.');

	if ($force_folder)
		$folder = $force_folder;

	if (!is_dir($folder))
		return;

	$dh = scandir($folder, 1);
	$exts = array_flip((array) $extensions);
	$by_date = '';
	if (is_integer($filter))
	{
		$filter = '';
		$by_date = $filter;
	}
	$filter_is_folder = !$filter || strpos($force_folder, $filter) !== false;


	if ($folder == CACHE_DIR . '/keys')
	{
		cache_get_type();
		if ($cache_system === 'apc')
			apc_clear_cache('user');
		elseif ($cache_system === 'memcached')
			$val = memcache_flush(get_memcached_server());
		elseif ($cache_system === 'xcache' && function_exists('xcache_clear_cache'))
		{
			for ($i = 0; $i < xcache_count(XC_TYPE_VAR); $i++)
				xcache_clear_cache(XC_TYPE_VAR, $i);
		}
		elseif ($cache_system === 'zend' && function_exists('zend_shm_cache_clear'))
			zend_shm_cache_clear('we');
		elseif ($cache_system === 'zend' && ($zend_cache_folder = ini_get('zend_accelerator.output_cache_dir')))
			clean_cache('', '', $zend_cache_folder . '/.php_cache_api');


		clean_cache('php', '', CACHE_DIR . '/app');
		clean_cache('php', '', CACHE_DIR . '/html');
		clean_cache('php', '', CACHE_DIR . '/lang');
	}


	foreach ($dh as $file)
	{
		if ($file[0] === '.' || $file === 'index.php')
			continue;
		$path = $folder . '/' . $file;
		if (is_dir($path))
			$is_recursive && clean_cache($extensions, $filter, $path, true);
		elseif (($by_date && filemtime($path) < $by_date) || !$filter || $filter_is_folder || strpos($path, $filter) !== false)
		{
			if (!$extensions || isset($exts[wedge_get_extension($file)]))
				@unlink($path);
		}

		else
			$there_is_another = true;
	}


	if (!$force_folder && !is_array($extensions))
	{
		@fclose(@fopen(CACHE_DIR . '/cache.lock', 'w'));
		clearstatcache();
	}
	elseif ($remove_folder && !$there_is_another)
	{
		@unlink($folder . '/index.php');
		@rmdir($force_folder);
	}
}

















function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	global $settings, $cache_block;

	$needs_refresh = empty($settings['cache_enable']) || $settings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time());
	if ($needs_refresh || !empty($cache_block['after_run']))
	{
		if (is_array($file))
			loadPluginSource($file[0], $file[1]);
		else
			loadSource($file);
	}

	if ($needs_refresh)
	{
		$cache_block = call_user_func_array($function, $params);

		if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= $level)
			cache_put_data($key, $cache_block, $cache_block['expires'] - time());
	}


	if (!empty($cache_block['after_run']))
		$cache_block['after_run']($params);

	return $cache_block['data'];
}

















function cache_put_data($key, $val, $ttl = 120)
{
	global $cache_system, $cache_hits, $cache_count;
	global $settings, $db_show_debug;

	if (empty($settings['cache_enable']) && !empty($settings))
		return;

	$st = microtime(true);
	$key = cache_prepare_key($key, $val, 'put');
	if ($ttl === 'forever')
		$ttl = PHP_INT_MAX;

	if ($val !== null)
		$val = serialize($val);

	cache_get_type();


	if ($cache_system === 'memcached')
		memcache_set(get_memcached_server(), $key, $val, 0, $ttl);
	elseif ($cache_system === 'apc')
	{

		if ($val === null)
			apc_delete($key . 'wedge');
		else
			apc_store($key . 'wedge', $val, $ttl === PHP_INT_MAX ? 0 : $ttl);
	}
	elseif ($cache_system === 'zend' && function_exists('zend_shm_cache_store'))
		zend_shm_cache_store('we::' . $key, $val, $ttl);
	elseif ($cache_system === 'zend' && function_exists('output_cache_put'))
		output_cache_put($key, $val);
	elseif ($cache_system === 'xcache')
	{
		if ($val === null)
			xcache_unset($key);
		else
			xcache_set($key, $val, $ttl);
	}

	else
	{
		if ($val === null)
			@unlink(CACHE_DIR . '/keys/' . $key . '.php');
		else
		{
			$cache_data = '<' . '?php if(defined(\'WEDGE\')&&$valid=' . ($ttl === PHP_INT_MAX ? '1' : 'time()<' . (time() + $ttl)) . ')$val=\'' . addcslashes($val, '\\\'') . '\';';


			$dest_locked = CACHE_DIR . '/keys/' . $key . '-' . mt_rand(999, 999999999) . '.php';
			if (file_put_contents($dest_locked, $cache_data) !== strlen($cache_data))
				unlink($desk_locked);
			else
				rename($dest_locked, CACHE_DIR . '/keys/' . $key . '.php');
		}


		if (function_exists('opcache_invalidate'))
			opcache_invalidate(CACHE_DIR . '/keys/' . $key . '.php', true);

		if (function_exists('apc_delete_file'))
			apc_delete_file(CACHE_DIR . '/keys/' . $key . '.php');
	}

	if (!empty($db_show_debug))
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
}










function cache_get_data($orig_key, $ttl = 120, $put_callback = null)
{
	global $cache_system, $cache_hits, $cache_count, $settings, $db_show_debug;

	if (empty($settings['cache_enable']) && !empty($settings))
		return null;

	$st = microtime(true);
	$key = cache_prepare_key($orig_key);
	if ($ttl === 'forever')
		$ttl = PHP_INT_MAX;

	cache_get_type();

	if ($cache_system === 'memcached')
		$val = memcache_get(get_memcached_server(), $key);
	elseif ($cache_system === 'apc')
		$val = apc_fetch($key . 'wedge');
	elseif ($cache_system === 'zend' && function_exists('zend_shm_cache_fetch'))
		zend_shm_cache_fetch('we::' . $key);
	elseif ($cache_system === 'zend' && function_exists('output_cache_get'))
		$val = output_cache_get($key, $ttl);
	elseif ($cache_system === 'xcache')
		$val = xcache_get($key);

	elseif (file_exists(CACHE_DIR . '/keys/' . $key . '.php') && filesize(CACHE_DIR . '/keys/' . $key . '.php') > 10)
		include(CACHE_DIR . '/keys/' . $key . '.php');

	if (!empty($db_show_debug))
	{
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
		$cache_hits[$cache_count]['s'] = isset($val) ? strlen($val) : 0;
	}


	if (!empty($val))
		return unserialize($val);


	if ($put_callback === null)
		return null;

	cache_put_data($orig_key, $new_cache = $put_callback(), $ttl);
	return $new_cache;
}

function cache_prepare_key($key, $val = '', $type = 'get')
{
	global $settings, $cache_hits, $cache_count, $db_show_debug;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (!empty($db_show_debug))
		$cache_hits[$cache_count] = $type == 'get' ? array('k' => $key, 'd' => 'get') : array('k' => $key, 'd' => 'put', 's' => $val === null ? 0 : strlen(serialize($val)));

	if (empty($settings['cache_hash']))
	{
		if (!file_exists(CACHE_DIR . '/cache.lock'))
			@fclose(@fopen(CACHE_DIR . '/cache.lock', 'w'));
		$settings['cache_hash'] = md5(ROOT_DIR . filemtime(CACHE_DIR . '/cache.lock'));
	}

	return $settings['cache_hash'] . '-' . bin2hex($key);
}

function cache_get_type()
{
	global $cache_type, $cache_system, $memcached_servers;

	if (isset($cache_system))
		return;

	if (empty($cache_type))
		$cache_type = 'file';


	if ($cache_type === 'memcached' && !(isset($memcached_servers) && trim($memcached_servers) !== '' && function_exists('memcache_get') && function_exists('memcache_set') && get_memcached_server()))
		$cache_type = 'file';

	elseif ($cache_type === 'apc' && !(function_exists('apc_fetch') && function_exists('apc_store')))
		$cache_type = 'file';

	elseif ($cache_type === 'zend' && !((function_exists('zend_shm_cache_fetch') && function_exists('zend_shm_cache_store')) || (function_exists('output_cache_get') && function_exists('output_cache_put'))))
		$cache_type = 'file';

	elseif ($cache_type === 'xcache' && !(function_exists('xcache_get') && function_exists('xcache_set') && ini_get('xcache.var_size') > 0))
		$cache_type = 'file';

	$cache_system = $cache_type;
}










function get_memcached_server($level = 3)
{
	global $db_persist, $memcached_servers;
	static $memcached = 0;

	if (!$memcached)
	{
		$servers = explode(',', $memcached_servers);
		$server = explode(':', trim($servers[array_rand($servers)]));


		$level = min(count($servers), $level);


		$func = 'memcache_' . (empty($db_persist) ? 'connect' : 'pconnect');
		$memcached = $func($server[0], empty($server[1]) ? 11211 : $server[1]);

		if (!$memcached)
			return $level > 0 ? get_memcached_server($level - 1) : false;
	}

	return $memcached;
}
