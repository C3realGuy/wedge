<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');
















































function ManageNews()
{
	global $context, $txt;


	isAllowedTo(array('edit_news', 'send_mail', 'admin_forum'));

	loadTemplate('ManageNews');


	$subActions = array(
		'editnews' => array('EditNews', 'edit_news'),
		'mailingmembers' => array('SelectMailingMembers', 'send_mail'),
		'mailingcompose' => array('ComposeMailing', 'send_mail'),
		'mailingsend' => array('SendMailing', 'send_mail'),
		'settings' => array('ModifyNewsSettings', 'admin_forum'),
	);


	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('edit_news') ? 'editnews' : (allowedTo('send_mail') ? 'mailingmembers' : 'settings'));


	isAllowedTo($subActions[$_REQUEST['sa']][1]);


	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['news_title'],
		'help' => 'edit_news',
		'description' => $txt['admin_news_desc'],
		'tabs' => array(
			'editnews' => array(
			),
			'mailingmembers' => array(
				'description' => $txt['news_mailing_desc'],
			),
			'settings' => array(
				'description' => $txt['news_settings_desc'],
			),
		),
	);


	if (substr($_REQUEST['sa'], 0, 7) == 'mailing')
		$context[$context['admin_menu_name']]['current_subsection'] = 'mailingmembers';

	$subActions[$_REQUEST['sa']][0]();
}


function EditNews()
{
	global $txt, $settings, $context;

	loadSource('Class-Editor');

	if (!empty($_POST['saveorder']) && !empty($_POST['order']) && is_array($_POST['order']))
	{
		checkSession();
		$news_lines = explode("\n", $settings['news']);


		foreach ($_POST['order'] as $k => $v)
			$_POST['order'][$k] = (int) $v;
		$_POST['order'] = array_flip($_POST['order']);
		foreach ($_POST['order'] as $new_pos => $item)
		{
			if (isset($news_lines[$new_pos]))
				$_POST['order'][$new_pos] = $news_lines[$new_pos];
			else
				unset($_POST['order'][$new_pos]);
		}

		foreach ($news_lines as $old_pos => $item)
		{
			if (!isset($_POST['order'][$old_pos]))
				$_POST['order']['old' . $old_pos] = $item;
		}
		updateSettings(array('news' => implode("\n", $_POST['order'])));
		clean_cache('css');
		cache_put_data('news_lines', null);
	}
	elseif (!empty($_POST['add']))
	{
		$context['editnews'] = array(
			'privacy' => 'e',
			'id' => -1,
		);
		$context['page_title'] = $txt['editnews_add'];
		$context['postbox'] = new wedit(
			array(
				'id' => 'message',
				'value' => '',
				'labels' => array(
					'post_button' => $txt['save'],
				),
				'buttons' => array(
					array(
						'name' => 'post_button',
						'button_text' => $txt['save'],
						'onclick' => 'return submitThisOnce(this);',
						'accesskey' => 's',
					),
				),
				'height' => '250px',
				'width' => '100%',
				'drafts' => 'none',
			)
		);
		wetem::load('edit_news_item');
		return;
	}
	elseif (!empty($_POST['modify']) && is_array($_POST['modify']))
	{
		$news_lines = explode("\n", $settings['news']);
		$keys = array_keys($_POST['modify']);
		$id = (int) $keys[0] - 1;
		if ($id >= 0 && !empty($news_lines[$id]))
		{
			$context['editnews'] = array(
				'privacy' => $news_lines[$id][0],
				'id' => $id + 1,
			);
			$context['page_title'] = $txt['editnews_edit'];
			$context['postbox'] = new wedit(
				array(
					'id' => 'message',
					'value' => wedit::un_preparsecode(substr($news_lines[$id], 1)),
					'labels' => array(
						'post_button' => $txt['save'],
					),
					'buttons' => array(
						array(
							'name' => 'post_button',
							'button_text' => $txt['save'],
							'onclick' => 'return submitThisOnce(this);',
							'accesskey' => 's',
						),
					),
					'height' => '250px',
					'width' => '100%',
					'drafts' => 'none',
				)
			);
			wetem::load('edit_news_item');
			return;
		}
	}
	elseif (!empty($_POST['post_button']) && !empty($_POST['newsid']) && (int) $_POST['newsid'] != 0 && !empty($_POST['message']) && westr::htmltrim($_POST['message']) !== '')
	{
		checkSession();
		wedit::preparseWYSIWYG('message');
		$news_lines = !empty($settings['news']) ? explode("\n", $settings['news']) : array();
		$_POST['message'] = westr::htmlspecialchars($_POST['message'], ENT_QUOTES);
		wedit::preparsecode($_POST['message']);
		$id = $_POST['newsid'] == -1 ? -1 : $_POST['newsid'] - 1;
		$privacy = isset($_POST['privacy']) && in_array($_POST['privacy'], array('e', 'm', 's', 'a')) ? $_POST['privacy'] : 'a';
		$news_lines[$id] = $privacy . $_POST['message'];
		updateSettings(array('news' => implode("\n", $news_lines)));
		logAction('news');
		clean_cache('css');
		cache_put_data('news_lines', null);
	}
	elseif (!empty($_POST['delete']) && is_array($_POST['delete']))
	{
		checkSession();
		$keys = array_keys($_POST['delete']);
		$id = (int) $keys[0] - 1;
		if ($id >= 0)
		{
			$news_lines = explode("\n", $settings['news']);
			unset($news_lines[$id]);
			updateSettings(array('news' => empty($news_lines) ? '' : implode("\n", $news_lines)));
		}
		logAction('news');
		clean_cache('css');
		cache_put_data('news_lines', null);
	}


	if (!empty($settings['news']))
		foreach (explode("\n", $settings['news']) as $id => $line)
			$context['admin_current_news'][$id] = array(
				'id' => $id,
				'privacy' => $line[0],
				'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', parse_bbc(substr($line, 1), 'news')),
			);

	add_js_file('sortable.min.js');
	add_css('
	#sortable { width: 98% } #sortable .floatright { margin-left: 1em; margin-right: 1em }');
	wetem::load('edit_news');
	$context['page_title'] = $txt['admin_edit_news'];
}

function SelectMailingMembers()
{
	global $txt, $context;

	$context['page_title'] = $txt['admin_newsletters'];

	wetem::load('email_members');

	$context['groups'] = array();
	$postGroups = array();
	$normalGroups = array();


	$request = wesql::query('
		SELECT mg.id_group, mg.group_name, mg.min_posts
		FROM {db_prefix}membergroups AS mg
		GROUP BY mg.id_group, mg.min_posts, mg.group_name
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'newbie_group' => 4,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'min_posts' => $row['min_posts'],
			'is_post' => $row['min_posts'] >= 0,
			'member_count' => 0,
		);

		if ($row['min_posts'] == -1)
			$normalGroups[$row['id_group']] = $row['id_group'];
		else
			$postGroups[$row['id_group']] = $row['id_group'];
	}
	wesql::free_result($request);


	if (!empty($postGroups))
	{
		$query = wesql::query('
			SELECT mem.id_post_group AS id_group, COUNT(*) AS member_count
			FROM {db_prefix}members AS mem
			WHERE mem.id_post_group IN ({array_int:post_group_list})
			GROUP BY mem.id_post_group',
			array(
				'post_group_list' => $postGroups,
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['groups'][$row['id_group']]['member_count'] += $row['member_count'];
		wesql::free_result($query);
	}

	if (!empty($normalGroups))
	{

		$query = wesql::query('
			SELECT id_group, COUNT(*) AS member_count
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list})
			GROUP BY id_group',
			array(
				'normal_group_list' => $normalGroups,
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['groups'][$row['id_group']]['member_count'] += $row['member_count'];
		wesql::free_result($query);


		$query = wesql::query('
			SELECT mg.id_group, COUNT(*) AS member_count
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
					AND mem.id_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
			WHERE mg.id_group IN ({array_int:normal_group_list})
			GROUP BY mg.id_group',
			array(
				'normal_group_list' => $normalGroups,
				'blank_string' => '',
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['groups'][$row['id_group']]['member_count'] += $row['member_count'];
		wesql::free_result($query);
	}


	$request = wesql::query('
		SELECT COUNT(DISTINCT id_member) AS num_distinct_mods
		FROM {db_prefix}moderators
		LIMIT 1',
		array(
		)
	);
	list ($context['groups'][3]['member_count']) = wesql::fetch_row($request);
	wesql::free_result($request);

	$context['can_send_pm'] = allowedTo('pm_send');
}


function ComposeMailing()
{
	global $txt, $context;


	$toClean = array();
	if (!empty($_POST['members']))
		$toClean[] = 'members';
	if (!empty($_POST['exclude_members']))
		$toClean[] = 'exclude_members';
	if (!empty($toClean))
	{
		loadSource('Subs-Auth');
		foreach ($toClean as $type)
		{

			$_POST[$type] = strtr($_POST[$type], array('\\"' => '"'));

			preg_match_all('~"([^"]+)"~', $_POST[$type], $matches);
			$_POST[$type] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST[$type]))));

			foreach ($_POST[$type] as $index => $member)
				if (strlen(trim($member)) > 0)
					$_POST[$type][$index] = westr::htmlspecialchars(westr::strtolower(trim($member)));
				else
					unset($_POST[$type][$index]);


			$_POST[$type] = implode(',', array_keys(findMembers($_POST[$type])));
		}
	}

	if (isset($_POST['member_list']) && is_array($_POST['member_list']))
	{
		$members = array();
		foreach ($_POST['member_list'] as $member_id)
			$members[] = (int) $member_id;
		$_POST['members'] = implode(',', $members);
	}

	if (isset($_POST['exclude_member_list']) && is_array($_POST['exclude_member_list']))
	{
		$members = array();
		foreach ($_POST['exclude_member_list'] as $member_id)
			$members[] = (int) $member_id;
		$_POST['exclude_members'] = implode(',', $members);
	}


	SendMailing(true);


	loadLanguage(array('EmailTemplates', 'ManageTopics'));


	$request = wesql::query('
		SELECT mem.id_member
		FROM {db_prefix}members AS mem
		WHERE is_activated >= {int:full_ban}',
		array(
			'full_ban' => 20,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$context['recipients']['exclude_members'][] = $row['id_member'];
	wesql::free_result($request);


	if ((!empty($context['recipients']['groups']) && in_array(3, $context['recipients']['groups'])) || (!empty($context['recipients']['exclude_groups']) && in_array(3, $context['recipients']['exclude_groups'])))
	{
		$request = wesql::query('
			SELECT DISTINCT mem.id_member AS identifier
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member)
			WHERE mem.is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (in_array(3, $context['recipients']))
				$context['recipients']['exclude_members'][] = $row['identifier'];
			else
				$context['recipients']['members'][] = $row['identifier'];
		}
		wesql::free_result($request);
	}


	$context['total_emails'] = count($context['recipients']['emails']);
	$request = wesql::query('
		SELECT MAX(id_member)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($context['max_id_member']) = wesql::fetch_row($request);
	wesql::free_result($request);


	$context['recipients']['members'] = array_unique($context['recipients']['members']);
	$context['recipients']['exclude_members'] = array_unique($context['recipients']['exclude_members']);


	$context['page_title'] = $txt['admin_newsletters'];
	wetem::load('email_members_compose');

	$context['default_subject'] = htmlspecialchars($context['forum_name'] . ': ' . $txt['subject']);
	$context['default_message'] = htmlspecialchars($txt['message'] . "\n\n" . str_replace('{FORUMNAME}', $context['forum_name'], $txt['regards_team']) . "\n\n" . '{$board_url}');
}


function SendMailing($clean_only = false)
{
	global $txt, $context, $settings;


	$num_at_once = empty($settings['mail_queue']) ? 60 : 1000;


	if (!empty($_POST['send_pm']))
		$num_at_once /= 2;

	checkSession();


	$context['start'] = isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;
	$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
	$context['max_id_member'] = !empty($_POST['max_id_member']) ? (int) $_POST['max_id_member'] : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';
	$context['parse_html'] = !empty($_POST['parse_html']) ? '1' : '0';


	$context['recipients'] = array(
		'groups' => array(),
		'exclude_groups' => array(),
		'members' => array(),
		'exclude_members' => array(),
		'emails' => array(),
	);


	if (!empty($_POST['exclude_members']))
	{
		$members = explode(',', $_POST['exclude_members']);
		foreach ($members as $member)
			if ($member >= $context['start'])
				$context['recipients']['exclude_members'][] = (int) $member;
	}


	if (!empty($_POST['members']))
	{
		$members = explode(',', $_POST['members']);
		foreach ($members as $member)
			if ($member >= $context['start'])
				$context['recipients']['members'][] = (int) $member;
	}

	if (!empty($_POST['groups']))
	{
		if (is_array($_POST['groups']))
		{
			foreach ($_POST['groups'] as $group => $dummy)
				$context['recipients']['groups'][] = (int) $group;
		}
		else
		{
			$groups = explode(',', $_POST['groups']);
			foreach ($groups as $group)
				$context['recipients']['groups'][] = (int) $group;
		}
	}

	if (!empty($_POST['exclude_groups']))
	{
		if (is_array($_POST['exclude_groups']))
		{
			foreach ($_POST['exclude_groups'] as $group => $dummy)
				$context['recipients']['exclude_groups'][] = (int) $group;
		}
		else
		{
			$groups = explode(',', $_POST['exclude_groups']);
			foreach ($groups as $group)
				$context['recipients']['exclude_groups'][] = (int) $group;
		}
	}

	if (!empty($_POST['emails']))
	{
		$addressed = array_unique(explode(';', strtr($_POST['emails'], array("\n" => ';', "\r" => ';', ',' => ';'))));
		foreach ($addressed as $curmem)
		{
			$curmem = trim($curmem);
			if (!empty($curmem) && is_valid_email($curmem))
				$context['recipients']['emails'][$curmem] = $curmem;
		}
	}


	if ($clean_only)
		return;

	loadSource('Subs-Post');


	$context['subject'] = htmlspecialchars($_POST['subject']);
	$context['message'] = htmlspecialchars($_POST['message']);


	if (!$context['send_pm'] && !empty($_POST['send_html']))
	{

		if (!empty($_POST['parse_html']))
			$_POST['message'] = str_replace(array("\n", '  '), array("<br>\n", '&nbsp; '), $_POST['message']);


		if (preg_match('~\<html~i', $_POST['message']) == 0)
		{
			if (preg_match('~\<body~i', $_POST['message']) == 0)
				$_POST['message'] = '<html><head><title>' . $_POST['subject'] . '</title></head>' . "\n" . '<body>' . $_POST['message'] . '</body></html>';
			else
				$_POST['message'] = '<html>' . $_POST['message'] . '</html>';
		}
	}

	$variables = array(
		'{$board_url}',
		'{$current_time}',
		'{$latest_member.link}',
		'{$latest_member.id}',
		'{$latest_member.name}'
	);


	$cleanLatestMember = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($settings['latestRealName']) : $settings['latestRealName'];


	$_POST['message'] = str_replace($variables,
		array(
			!empty($_POST['send_html']) ? '<a href="' . ROOT . '">' . ROOT . '</a>' : ROOT,
			timeformat(forum_time(), false),
			!empty($_POST['send_html']) ? '<a href="' . SCRIPT . '?action=profile;u=' . $settings['latestMember'] . '">' . $cleanLatestMember . '</a>' : ($context['send_pm'] ? '[url=' . SCRIPT . '?action=profile;u=' . $settings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : $cleanLatestMember),
			$settings['latestMember'],
			$cleanLatestMember
		), $_POST['message']);
	$_POST['subject'] = str_replace($variables,
		array(
			ROOT,
			timeformat(forum_time(), false),
			$settings['latestRealName'],
			$settings['latestMember'],
			$settings['latestRealName']
		), $_POST['subject']);

	$from_member = array(
		'{$member.email}',
		'{$member.link}',
		'{$member.id}',
		'{$member.name}'
	);


	$i = 0;
	foreach ($context['recipients']['emails'] as $k => $email)
	{

		if ($i >= $num_at_once)
			break;


		unset($context['recipients']['emails'][$k]);


		if ($context['send_pm'])
			continue;

		$to_member = array(
			$email,
			!empty($_POST['send_html']) ? '<a href="mailto:' . $email . '">' . $email . '</a>' : $email,
			'??',
			$email
		);

		sendmail($email, str_replace($from_member, $to_member, $_POST['subject']), str_replace($from_member, $to_member, $_POST['message']), null, null, !empty($_POST['send_html']), 5);


		$i++;
	}


	$last_id_member = 0;
	if ($i < $num_at_once)
	{

		$sendQuery = '(';
		$sendParams = array();
		if (!empty($context['recipients']['groups']))
		{

			$queryBuild = array();
			foreach ($context['recipients']['groups'] as $group)
			{
				$sendParams['group_' . $group] = $group;
				$queryBuild[] = 'mem.id_group = {int:group_' . $group . '}';
				if (!empty($group))
				{
					$queryBuild[] = 'FIND_IN_SET({int:group_' . $group . '}, mem.additional_groups) != 0';
					$queryBuild[] = 'mem.id_post_group = {int:group_' . $group . '}';
				}
			}
			if (!empty($queryBuild))
			$sendQuery .= implode(' OR ', $queryBuild);
		}
		if (!empty($context['recipients']['members']))
		{
			$sendQuery .= ($sendQuery == '(' ? '' : ' OR ') . 'mem.id_member IN ({array_int:members})';
			$sendParams['members'] = $context['recipients']['members'];
		}

		$sendQuery .= ')';


		if ($sendQuery == '()')
			redirectexit('action=admin');


		if (!empty($context['recipients']['exclude_groups']) && in_array(0, $context['recipients']['exclude_groups']))
			$sendQuery .= ' AND mem.id_group != {int:regular_group}';
		if (!empty($context['recipients']['exclude_members']))
		{
			$sendQuery .= ' AND mem.id_member NOT IN ({array_int:exclude_members})';
			$sendParams['exclude_members'] = $context['recipients']['exclude_members'];
		}


		if (empty($context['email_force']))
			$sendQuery .= ' AND mem.notify_announcements = {int:notify_announcements}';


		$result = wesql::query('
			SELECT mem.id_member, mem.email_address, mem.real_name, mem.id_group, mem.additional_groups, mem.id_post_group
			FROM {db_prefix}members AS mem
			WHERE mem.id_member > {int:min_id_member}
				AND mem.id_member < {int:max_id_member}
				AND ' . $sendQuery . '
				AND mem.is_activated = {int:is_activated}
			ORDER BY mem.id_member ASC
			LIMIT {int:atonce}',
			array_merge($sendParams, array(
				'min_id_member' => $context['start'],
				'max_id_member' => $context['start'] + $num_at_once - $i,
				'atonce' => $num_at_once - $i,
				'regular_group' => 0,
				'notify_announcements' => 1,
				'is_activated' => 1,
			))
		);

		while ($row = wesql::fetch_assoc($result))
		{
			$last_id_member = $row['id_member'];


			if (empty($row['additional_groups']))
				$groups = array($row['id_group'], $row['id_post_group']);
			else
				$groups = array_merge(
					array($row['id_group'], $row['id_post_group']),
					explode(',', $row['additional_groups'])
				);


			if (array_intersect($groups, $context['recipients']['exclude_groups']))
				continue;


			$cleanMemberName = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($row['real_name']) : $row['real_name'];


			$message = str_replace($from_member,
				array(
					$row['email_address'],
					!empty($_POST['send_html']) ? '<a href="' . SCRIPT . '?action=profile;u=' . $row['id_member'] . '">' . $cleanMemberName . '</a>' : ($context['send_pm'] ? '[url=' . SCRIPT . '?action=profile;u=' . $row['id_member'] . ']' . $cleanMemberName . '[/url]' : $cleanMemberName),
					$row['id_member'],
					$cleanMemberName,
				), $_POST['message']);

			$subject = str_replace($from_member,
				array(
					$row['email_address'],
					$row['real_name'],
					$row['id_member'],
					$row['real_name'],
				), $_POST['subject']);


			if (!$context['send_pm'])
				sendmail($row['email_address'], $subject, $message, null, null, !empty($_POST['send_html']), 5);
			else
				sendpm(array('to' => array($row['id_member']), 'bcc' => array()), $subject, $message, false);
		}
		wesql::free_result($result);
	}


	if ($i >= $num_at_once)
		$last_id_member = $context['start'];

	elseif (empty($last_id_member) && $context['start'] + $num_at_once < $context['max_id_member'])
		$last_id_member = $context['start'] + $num_at_once;

	elseif (empty($last_id_member) && empty($context['recipients']['emails']))
	{

		logAction('newsletter', array(), 'admin');

		redirectexit('action=admin');
	}

	$context['start'] = $last_id_member;


	$percentEmails = $context['total_emails'] == 0 ? 0 : ((count($context['recipients']['emails']) / $context['total_emails']) * ($context['total_emails'] / ($context['total_emails'] + $context['max_id_member'])));
	$percentMembers = ($context['start'] / $context['max_id_member']) * ($context['max_id_member'] / ($context['total_emails'] + $context['max_id_member']));
	$context['percentage_done'] = round(($percentEmails + $percentMembers) * 100, 2);

	$context['page_title'] = $txt['admin_newsletters'];
	wetem::load('email_members_send');
}

function ModifyNewsSettings($return_config = false)
{
	global $context, $txt;

	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['admin_edit_news'] . ' - ' . $txt['settings'];

	$config_vars = array(
		array('title', 'settings'),

			array('permissions', 'edit_news', 'help' => '', 'exclude' => array(-1)),
			array('permissions', 'send_mail', 'exclude' => array(-1)),
		'',
			array('check', 'enable_news'),
			array('check', 'show_newsfader'),
			array('int', 'newsfader_time', 'min' => 100),
		'',

			array('check', 'xmlnews_enable', 'onclick' => '$(\'#xmlnews_maxlen\').prop(\'disabled\', !this.checked);'),
			array('int', 'xmlnews_maxlen', 'subtext' => $txt['xmlnews_maxlen_subtext']),
			array('check', 'xmlnews_sidebar'),
	);

	if ($return_config)
		return $config_vars;

	wetem::load('show_settings');


	loadSource(array('ManagePermissions', 'ManageServer'));


	$context['post_url'] = '<URL>?action=admin;area=news;save;sa=settings';


	add_js('
	$("#xmlnews_maxlen").prop("disabled", !$("#xmlnews_enable").is(":checked"));');


	if (isset($_GET['save']))
	{
		checkSession();
		clean_cache('css');

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=news;sa=settings');
	}

	prepareDBSettingContext($config_vars);
}

function cache_getNews()
{
	global $settings;

	$news = explode("\n", str_replace("\r", '', trim(addslashes($settings['news']))));
	$result = array();
	foreach ($news as $key => $value)
	{
		$value = trim($value);
		if ($value != '')
			$result[] = parse_bbc(stripslashes(trim($value)), 'news');
	}

	return array(
		'data' => $result,
		'expires' => time() + 7200,
	);
}
