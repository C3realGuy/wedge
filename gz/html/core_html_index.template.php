<?php


















function template_init()
{
	global $context, $settings;


	if (we::$is_guest && !empty($context['main_js_files']) && empty($context['disable_login_hashing']) && !empty($settings['enable_quick_login']))
		$context['main_js_files']['sha1.js'] = true;





}


function template_html_before()
{
	global $context, $txt, $settings, $topic;




	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', !empty($txt['lang_dictionary']) ? ' lang="' . $txt['lang_dictionary'] . '"' : '', '>
<head>', empty($topic) ? '' : '
	<meta charset="utf-8">';


	if (we::is('ie8down'))
		echo '
	<script src="//html5shiv.googlecode.com/svn/trunk/html5.js"></script>';

	echo theme_base_css(), '
	<!-- Powered by Wedge, © R.-G. Deberdt - http://wedge.org -->
	<title><PAGE_TITLE>', !empty($context['page_indicator']) ? $context['page_indicator'] : '', '</title>';


	if (strpos(str_replace('://', '', ROOT), '/') !== false)
		echo '
	<link rel="shortcut icon" href="', ROOT, '/favicon.ico" type="image/vnd.microsoft.icon">';


	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '">';


	if (!empty($context['allow_search']))
		echo '
	<link rel="search" href="<URL>?action=search">';


	if (!empty($settings['xmlnews_enable']) && (!empty($settings['allow_guestAccess']) || we::$is_member))
		echo '
	<link rel="alternate" href="<URL>?action=feed" type="application/atom+xml" title="', $context['forum_name_html_safe'], '">';


	if (empty($context['robot_no_index']))
	{
		if (!empty($context['links']['prev']))
			echo '
	<link rel="prev" href="', $context['links']['prev'], '">';
		if (!empty($context['links']['next']))
			echo '
	<link rel="next" href="', $context['links']['next'], '">';
	}

	if (!we::is('opera[11-], ie[10-]'))
		echo '
	<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=2,minimum-scale=0.7">';

	if (!empty($context['meta_description']))
		echo '
	<meta name="description" content="', $context['meta_description'], '">';


	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex">';

	if (isset($_SESSION['session_var'], $_GET[$_SESSION['session_var']]))
		echo '
	<meta name="referrer" content="origin">';

	echo '
</head>';
}

function template_body_before()
{
	echo '
<body>';
}


function template_wrapper_before()
{
	echo '
<div id="wedge">';
}


function template_header_before()
{
	echo '
	<div id="header"><div>';
}

function template_top_bar_before()
{
	echo '
		<div id="top_section"><div>';
}

function template_top_bar_after()
{
	echo '
		</div></div>';
}


function template_header_after()
{
	global $context, $settings, $options;

	echo '
		<div id="banner"', empty($options['collapse_header']) ? '' : ' class="hide"', '><div><we:banner title="',
		$context['header_logo_url_html_safe'], '" url="', !empty($settings['home_url']) && !empty($settings['home_link']) ?
		$settings['home_url'] : '<URL>', '">', $context['site_slogan'], '</we:banner>
		</div></div>
	</div></div>';
}

function template_search_box()
{
	global $context, $settings;

	if (we::$is_guest && empty($settings['allow_guestAccess']) || empty($context['allow_search']))
		return;

	echo '
			<form id="search_form" action="<URL>?action=search2" method="post" accept-charset="UTF-8">
				<input type="search" name="search" value="" class="search">';


	if (!empty($context['current_topic']))
			echo '
				<input type="hidden" name="topic" value="', $context['current_topic'], '">';


	if (!empty($context['current_board']))
		echo '
				<input type="hidden" name="brd[', $context['current_board'], ']" value="', $context['current_board'], '">';

	echo '
			</form>';
}

function template_notifications()
{
	global $txt, $context, $user_settings;

	if (isset($context['unread_notifications']))
		echo '
			<div class="notifs notif">
				<span class="note', !empty($user_settings['hey_not']) ? '' : 'void', '">', $context['unread_notifications'], '</span>
				', $txt['notifications'], '
			</div>';
}

function template_pm_notifications()
{
	global $txt, $context, $user_settings;

	if ($context['allow_pm'])
		echo '
			<div class="notifs npm">
				<span class="note', !empty($user_settings['hey_pm']) ? '' : 'void', '">', we::$user['unread_messages'], '</span>
				', $txt['pm_short'], '
			</div>';
}

function template_language_selector()
{
	global $context;

	if (empty($context['languages']) || count($context['languages']) < 2)
		return;

	echo '
			<form id="flags" method="get"><select name="language" onchange="this.form.submit();">';

	foreach ($context['languages'] as $id => $language)
		echo '
				<option value="', $id, '"', we::$user['language'] == $id ? ' selected' : '', '>&lt;span class="flag_', $language['filename'], '"&gt;&lt;/span&gt; &lt;small&gt;', westr::htmlspecialchars($language['name']), '&lt;/small&gt;</option>';

	echo '
			</select></form>';
}

function template_logo_toggler()
{
	global $options;

	if (we::$is_member)
		echo '
			<div id="upshrink"', empty($options['collapse_header']) ? ' class="fold"' : '', '>›</div>';
}

function template_random_news()
{
	global $txt, $context, $settings;


	if (empty($settings['enable_news']) || empty($context['random_news_line']))
		return;

	echo '
	<div id="sitenews">
		<span>', $txt['news'], we::is('ie6,ie7') ? ' > ' : '', '</span>
		', $context['random_news_line'], '
	</div>';
}

function template_sidebar_wrap_before()
{
	echo '
	<we:sidebar>';
}

function template_side_user_before()
{
	global $txt, $context, $settings;

	echo '
	<section>';

	if (we::$is_guest && empty($settings['enable_quick_login']))
		return;

	echo '
		<we:title id="greeting">
			', sprintf($txt['hello_member_ndt'], we::$user['name']), '
		</we:title>
		<div id="userbox">';


	if (we::$is_member)
	{
		echo empty(we::$user['avatar']['image']) ? '
			<ul id="noava">' : '
			<a href="<URL>?action=profile;u=' . we::$id . '">' . we::$user['avatar']['image'] . '</a>
			<ul>', '
				<li><a href="<URL>?action=unread">', $txt['show_unread'], '</a></li>
				<li><a href="<URL>?action=unreadreplies">', $txt['show_unread_replies'], '</a></li>';


		if (!empty($context['unapproved_members']))
			echo '
				<li>', number_context('approve_members_waiting', $context['unapproved_members']), '</li>';

		echo '
			</ul>
			<p class="now">', $context['current_time'], '</p>
		</div>';
	}

	elseif (!empty($settings['enable_quick_login']))
		echo '
			<form id="guest_form" action="<URL>?action=login2" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
				<div class="info">', (empty($settings['registration_method']) || $settings['registration_method'] != 3) ? $txt['login_or_register'] : $txt['please_login'], '</div>
				<input name="user" size="10">
				<input type="password" name="passwrd" size="10">
				<select name="cookielength">
					<option value="60">', $txt['one_hour'], '</option>
					<option value="1440">', $txt['one_day'], '</option>
					<option value="10080">', $txt['one_week'], '</option>
					<option value="43200">', $txt['one_month'], '</option>
					<option value="-1" selected>', $txt['forever'], '</option>
				</select>
				<input type="submit" value="', $txt['login'], '" class="submit"><br>
				<div class="info">', $txt['quick_login_desc'], '</div>
				<input type="hidden" name="hash_passwrd" value="">
			</form>
		</div>';
}

function template_side_user_after()
{
	echo '
	</section>';
}

function template_side_maintenance()
{
	global $context, $txt;


	if ($context['in_maintenance'] && we::$is_admin)
		echo '
	<section>
		<p class="notice">', $txt['maintain_mode_on'], '</p>
	</section>';
}


function template_sidebar_quick_access()
{
	global $txt, $settings;

	if (we::$is_guest && empty($settings['allow_guestAccess']))
		return;

	add_js('
	new JumpTo("jump_to");');

	echo '
	<section>
		<we:title>
			', $txt['jump_to'], '
		</we:title>
		<p id="jump_to"></p>
	</section>';
}



function template_sidebar_feed()
{
	global $txt, $topic, $board, $board_info;

	echo '
	<section>
		<we:title>
			<div class="feed_icon">', $txt['feed'], '</div>
		</we:title>
		<dl id="feed">';


	if (!empty($topic))
		echo '
			<dt>', $txt['feed_current_topic'], '</dt>
			<dd>', sprintf($txt['feed_posts'], '<URL>?topic=' . $topic . ';action=feed'), '</dd>';


	if (!empty($board))
	{
		$feed = '<URL>?board=' . $board_info['id'] . ';action=feed';
		echo '
			<dt>', $board_info['type'] == 'blog' ? $txt['feed_current_blog'] : $txt['feed_current_forum'], '</dt>
			<dd>', sprintf($txt['feed_posts'], $feed), ' / ', sprintf($txt['feed_topics'], $feed . ';sa=news'), '</dd>';
	}


	$feed = '<URL>?action=feed';
	echo '
			<dt>', $txt['feed_everywhere'], '</dt>
			<dd>', sprintf($txt['feed_posts'], $feed), ' / ', sprintf($txt['feed_topics'], $feed . ';sa=news'), '</dd>
		</dl>
	</section>';
}

function template_sidebar_wrap_after()
{
	echo '</we:sidebar>';
}

function template_offside_wrap_before()
{
	echo '<we:offside>';
}

function template_offside_wrap_after()
{
	echo '</we:offside>';
}

function template_content_wrap_before()
{
	global $context;

	if ($context['action'])
		$id = $context['action'];
	elseif (!empty($context['current_topic']))
		$id = 'topic';
	elseif (!empty($context['current_board']))
		$id = 'board';
	if (wetem::has_block('admin_login'))
		$id = 'login';

	echo '
	<div id="content"><div', isset($id) ? ' id="' . $id . '"' : '', '>';
}

function template_main_wrap_before()
{
	echo '
	<div id="main">';
}

function template_main_wrap_after()
{
	echo '
	</div>';
}

function template_content_wrap_after()
{
	echo '
	</div></div>';
}

function template_wrapper_after()
{
	echo '
</div>';
}

function template_body_after()
{
	template_insert_javascript();

	echo '
</body>';
}

function template_insert_javascript()
{
	global $context, $options, $txt, $settings;





	echo $context['footer'], '
<!-- JavaScript area -->';



	if (!empty($context['footer_js_inline']))
		echo '

<script>', $context['footer_js_inline'], '
</script>';




	if (!we::is('ie8down') && isset($_SESSION['js_loaded']))
	{
		$context['header'] .= theme_base_js(1);
		echo '
<script>
	<!-- insert inline events here -->';
	}
	else
	{
		$_SESSION['js_loaded'] = true;
		echo '
<script>
	<!-- insert inline events here -->
</script>', "\n", theme_base_js(), '<script>';
	}

	if (!empty($settings['pm_enabled']))
		echo '
	we_pms = ', we::$user['unread_messages'], ';';

	$groups = $lists = array();
	foreach (we::$user['contacts']['groups'] as $id => $group)
		$groups[] = '"' . $id . '|' . $group[1] . '|' . str_replace('|', ' ', $group[0]) . '"';
	foreach (we::$user['contacts']['lists'] as $id => $clist)
		$lists[] = '"' . $id . '|' . $clist[1] . '|' . str_replace('|', ' ', generic_contacts($clist[0])) . '"';

	echo '
	we_script = "<URL>";
	we_assets = "', ASSETS, '";', '
	we_sessid = "', $context['session_id'], '";
	we_sessvar = "', $context['session_var'], '";', $context['server']['iso_case_folding'] && isset($context['main_js_files']['sha1.js']) ? '
	we_iso_case_folding = 1;' : '', empty($options['collapse_header']) ? '' : '
	we_colhead = 1;', empty($context['current_topic']) ? '' : '
	we_topic = ' . $context['current_topic'] . ';', empty($context['current_board']) ? '' : '
	we_board = ' . $context['current_board'] . ';', '
	we_groups = [' . implode(', ', $groups) . '];
	we_lists = [' . implode(', ', $lists) . '];', $context['show_pm_popup'] ? '

	ask(' . JavaScriptEscape($txt['show_personal_messages'], '"') . ', function (yes) { yes && window.open(weUrl("action=pm")); });' : '';




	echo $context['footer_js'], '
</script>';
}

function template_html_after()
{
	echo '</html>';
}


function template_linktree($position = 'top', $force_show = false)
{
	global $context;


	if ($position === 'bottom' && empty($context['bottom_linktree']) && !$force_show)
		return;


	if ($position === 'bottom')
		echo '
	<div id="linktree_bt">';
	else
		echo '
	<div id="linktree" itemtype="http://schema.org/WebPage" itemscope>';


	if (!empty($context['linktree']) && ($linksize = count($context['linktree'])) !== 1 && (empty($context['dont_default_linktree']) || $force_show))
	{
		$needs_fix = we::is('ie6,ie7');

		if ($position === 'bottom')
			echo '
		<ul>';
		else
			echo '
		<ul itemprop="breadcrumb">';


		$num = 0;
		foreach ($context['linktree'] as &$tree)
		{
			echo '<li', ++$num == $linksize ? ' class="last"' : '', '>';


			if (isset($tree['extra_before']))
				echo $tree['extra_before'];


			echo isset($tree['url']) ? '<a href="' . $tree['url'] . '">' . $tree['name'] . '</a>' : $tree['name'];


			if (isset($tree['extra_after']))
				echo $tree['extra_after'];

			echo '</li>';
			if ($needs_fix)
				echo ' > ';
		}
		echo '</ul>
	</div>';
	}
	else
		echo '</div>';
}


function template_menu()
{
	global $context;

	echo '
	<div id="navi">';

	template_menu_recursive($context['menu_items']);

	echo '
	</div>';
}


function template_menu_recursive($oitem, $parent = '')
{
	echo '<ul', $parent ? '' : ' id="main_menu" class="menu"', '>';

	foreach ($oitem as $act => $item)
	{
		if (empty($item))
		{
			echo '<li class="sep"><a><hr></a></li>';
			continue;
		}
		$class = (!empty($item['active_item']) ? ' chosen' : '') . (empty($item['items']) ? ' nodrop' : '') . (!empty($item['items']) && $parent ? ' subsection' : '');
		echo '<li', $class ? ' class="' . substr($class, 1) . '"' : '', '>';

		echo $parent ? '' : '<span id="m_' . $act . '"></span><h4>',
			'<a href="', $item['href'], '"', !empty($item['nofollow']) ? ' rel="nofollow"' : '', '>',
			empty($item['icon']) || !$parent ? '' : '<span id="m_' . $parent . '_' . $act . '"></span>', $item['title'],
			!empty($item['notice']) ? '<span class="note' . ($parent ? '' : 'warn') . '">' . $item['notice'] . '</span>' : '', '</a>', $parent ? '' : '</h4>';

		if (!empty($item['items']))
			template_menu_recursive($item['items'], $act);

		echo '</li>';
	}

	echo '</ul>';
}

function template_mini_menu($menu, $class)
{
	global $context, $txt;

	if (empty($context['mini_menu'][$menu]))
		return;

	$js = '
	$(".' . $class . '").mime({';

	foreach ($context['mini_menu'][$menu] as $post => $linklist)
		$js .= '
		' . $post . ': ["' . implode('", "', $linklist) . '"],';

	$js = substr($js, 0, -1) . '
	}, {';

	foreach ($context['mini_menu_items'][$menu] as $key => $pmi)
	{
		if (!isset($context['mini_menu_items_show'][$menu][$key]))
			continue;
		$js .= '
		' . $key . ': [';
		foreach ($pmi as $type => $item)
			if ($type === 'caption')
				$js .= (isset($txt[$item]) ? JavaScriptEscape($txt[$item], '"') : '""') . ', '
					. (isset($txt[$item . '_desc']) ? JavaScriptEscape($txt[$item . '_desc'], '"') : '""') . ', ';
			else
				$js .= '"' . $item . '", ';
		$js = substr($js, 0, -2) . '],';
	}

	add_js(substr($js, 0, -1) . '
	});');
}


function template_footer()
{
	global $context, $txt;


	echo '
	<div id="footer"><div><ul>
		<li id="copyright">', $txt['copyright'], '</li>
		<li class="links">
			<a id="site_credits" href="<URL>?action=credits">', $txt['site_credits'], '</a>',
			empty($context['custom_credits']) ? '' : '
			' . $context['custom_credits'], '
		</li>';


	if ($context['show_load_time'])
		echo '
		<li class="stats"><!-- insert stats here --></li>';

	echo '
	</ul></div></div>';
}
















function template_page_index($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show_prevnext = true)
{
	global $settings, $options, $topicinfo, $txt, $context;


	$start = (int) $start;
	$start_invalid = $start < 0;


	if ($start_invalid)
		$start = 0;

	elseif ($start >= $max_value)
		$start = max(0, (int) $max_value - (((int) $max_value % (int) $num_per_page) == 0 ? $num_per_page : ((int) $max_value % (int) $num_per_page)));

	else
		$start = max(0, (int) $start - ((int) $start % (int) $num_per_page));

	$base_link = '<a href="' . ($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d') . '">%2$s</a> ';
	$pageindex = '';


	$contiguous = isset($settings['compactTopicPagesContiguous']) ? $settings['compactTopicPagesContiguous'] >> 1 : 2;


	if ($show_prevnext && $start >= $num_per_page)
	{

		if (!empty($options['view_newest_first']) && !empty($topicinfo['new_from']) && $topicinfo['new_from'] <= $topicinfo['id_last_msg'])
			$pageindex .= '<div class="note next_page">' . $txt['new_short'] . '</div> ';

		$pageindex .= sprintf($base_link, $start - $num_per_page, $txt['previous_next_back']);
	}


	if ($start > $num_per_page * $contiguous)
		$pageindex .= sprintf($base_link, 0, '1');


	if ($start > $num_per_page * ($contiguous + 1))
	{
		$base_page = $flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d';
		$pageindex .= '<a data-href="' . $base_page . '" onclick="expandPages(this, ' . $num_per_page . ', ' . ($start - $num_per_page * $contiguous) . ', ' . $num_per_page . ');">&hellip;</a> ';
	}


	for ($nCont = $contiguous; $nCont >= 1; $nCont--)
		if ($start >= $num_per_page * $nCont)
		{
			$tmpStart = $start - $num_per_page * $nCont;
			$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
		}


	if (!$start_invalid)
	{
		$page_num = ($start / $num_per_page + 1);
		$pageindex .= '<strong>' . $page_num . '</strong> ';
		if ($page_num > 1 && !isset($context['page_indicator']))
			$context['page_indicator'] = number_context('page_indicator', $page_num);
	}
	else
		$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);


	$tmpMaxPages = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
	for ($nCont = 1; $nCont <= $contiguous; $nCont++)
		if ($start + $num_per_page * $nCont <= $tmpMaxPages)
		{
			$tmpStart = $start + $num_per_page * $nCont;
			$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
		}


	if ($start + $num_per_page * ($contiguous + 1) < $tmpMaxPages)
	{
		if (!isset($base_page))
			$base_page = $flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d';
		$pageindex .= '<a data-href="' . $base_page . '" onclick="expandPages(this, ' . ($start + $num_per_page * ($contiguous + 1)) . ', ' . $tmpMaxPages . ', ' . $num_per_page . ');">&hellip;</a> ';
	}


	if ($start + $num_per_page * $contiguous < $tmpMaxPages)
		$pageindex .= sprintf($base_link, $tmpMaxPages, $tmpMaxPages / $num_per_page + 1);


	if ($show_prevnext && $start + $num_per_page < $max_value)
	{
		$pageindex .= '<span class="next_page">' . sprintf($base_link, $start + $num_per_page, $txt['previous_next_forward']) . '</span>';


		if (empty($options['view_newest_first']) && !empty($topicinfo['new_from']) && $topicinfo['new_from'] <= $topicinfo['id_last_msg'])
			$pageindex .= ' <div class="note next_page">' . $txt['new_short'] . '</div> ';
	}

	return rtrim($pageindex, ' ');
}


function template_button_strip($button_strip, $class = '', $extra = '')
{
	global $context, $txt;


	if ($context['right_to_left'])
		$button_strip = array_reverse($button_strip, true);


	$buttons = array();
	foreach ($button_strip as $key => $value)
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<li><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="' . $key . (!empty($value['class']) ? ' ' . $value['class'] : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a></li>';


	if (empty($buttons))
		return '';


	$buttons[count($buttons) - 1] = str_replace('<li>', '<li class="last">', $buttons[count($buttons) - 1]);

	return '
			<ul class="buttonlist' . ($class ? ' ' . $class : '') . (empty($buttons) ? ' hide' : '') . '"' . ($extra ? ' ' . ltrim($extra) : '') . '>' .
				implode('', $buttons) . '
			</ul>';
}
