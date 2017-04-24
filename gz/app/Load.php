<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');













function loadSettings()
{
	global $settings, $context, $action_list, $action_no_log, $my_plugins;


	$context = array(
		'pretty' => array('db_count' => 0),
		'action' => '',
		'subaction' => '',
		'app_error_count' => 0,
	);


	if (($settings = cache_get_data('settings', 'forever')) === null)
	{
		$request = wesql::query('
			SELECT variable, value
			FROM {db_prefix}settings'
		);
		$settings = array();
		if (!$request)
			show_db_error();
		while ($row = wesql::fetch_row($request))
			$settings[$row[0]] = $row[1];
		wesql::free_result($request);

		if (empty($settings['language']))
			$settings['language'] = 'english';


		if (empty($settings['defaultMaxTopics']) || $settings['defaultMaxTopics'] <= 0 || $settings['defaultMaxTopics'] > 999)
			$settings['defaultMaxTopics'] = 20;
		if (empty($settings['defaultMaxMessages']) || $settings['defaultMaxMessages'] <= 0 || $settings['defaultMaxMessages'] > 999)
			$settings['defaultMaxMessages'] = 15;
		if (empty($settings['defaultMaxMembers']) || $settings['defaultMaxMembers'] <= 0 || $settings['defaultMaxMembers'] > 999)
			$settings['defaultMaxMembers'] = 30;
		$settings['js_lang'] = empty($settings['js_lang']) ? array() : unserialize($settings['js_lang']);
		$settings['registered_hooks'] = empty($settings['registered_hooks']) ? array() : unserialize($settings['registered_hooks']);
		$settings['hooks'] = $settings['registered_hooks'];
		$settings['pretty_filters'] = unserialize($settings['pretty_filters']);

		if (!empty($settings['cache_enable']))
			cache_put_data('settings', $settings, 'forever');
	}














	$action_list = array(
		'activate' =>		'Activate',
		'admin' =>			'Admin',
		'ajax' =>			'Ajax',
		'announce' =>		'Announce',
		'boards' =>			'Boards',
		'buddy' =>			'Buddy',
		'collapse' =>		'Collapse',
		'coppa' =>			'CoppaForm',
		'credits' =>		'Credits',
		'deletemsg' =>		array('RemoveTopic', 'DeleteMessage'),
		'display' =>		'Display',
		'dlattach' =>		'Dlattach',
		'emailuser' =>		'Mailer',
		'feed' =>			'Feed',
		'groups' =>			'Groups',
		'help' =>			'Help',
		'like' =>			'Like',
		'lock' =>			'Lock',
		'login' =>			'Login',
		'login2' =>			'Login2',
		'logout' =>			'Logout',
		'markasread' =>		array('Subs-Boards', 'MarkRead'),
		'media' =>			array('media/Aeva-Gallery', 'aeva_initGallery'),
		'mergeposts' =>		array('Merge', 'MergePosts'),
		'mergetopics' =>	array('Merge', 'MergeTopics'),
		'mlist' =>			'Memberlist',
		'moderate' =>		'ModerationCenter',
		'movetopic' =>		'MoveTopic',
		'movetopic2' =>		array('MoveTopic', 'MoveTopic2'),
		'notify' =>			'Notify',
		'notifyboard' =>	array('Notify', 'BoardNotify'),
		'notification' =>	array('Notifications', 'weNotif::action'),
		'pin' =>			'Pin',
		'pm' =>				'PersonalMessage',
		'poll' =>			'Poll',
		'post' =>			'Post',
		'post2' =>			'Post2',
		'printpage' =>		'PrintPage',
		'profile' =>		array('Profile', 'ModifyProfile'),
		'quickedit' =>		'QuickEdit',
		'quickmod' =>		array('QuickMod', 'QuickModeration'),
		'quickmod2' =>		array('QuickMod', 'QuickInTopicModeration'),
		'quotefast' =>		'QuoteFast',
		'recent' =>			'Recent',
		'register' =>		'Register',
		'register2' =>		array('Register', 'Register2'),
		'reminder' =>		array('Reminder', 'RemindMe'),
		'removetopic2' =>	array('RemoveTopic', 'RemoveTopic2'),
		'report' =>			'Report',
		'restoretopic' =>	array('RemoveTopic', 'RestoreTopic'),
		'search' =>			'Search',
		'search2' =>		'Search2',
		'sendtopic' =>		'Mailer',
		'skin' =>			array('Themes', 'PickTheme'),
		'splittopics' =>	array('Split', 'SplitTopics'),
		'stats' =>			'Stats',
		'suggest' =>		'Suggest',
		'theme' =>			'Themes',
		'thoughts' =>		'Thoughts',
		'trackip' =>		array('Profile-View', 'trackIP'),
		'uncache' =>		array('Subs-Cache', 'uncache'),
		'unread' =>			'Unread',
		'unreadreplies' =>	'UnreadReplies',
		'verification' =>	'VerificationCode',
		'viewquery' =>		'ViewQuery',
		'viewremote' =>		'ViewRemote',
		'who' =>			'Who',
	);

	if (empty($settings['pm_enabled']))
		unset($action_list['pm']);


	$action_no_log = array('ajax', 'dlattach', 'feed', 'like', 'notification', 'verification', 'viewquery', 'viewremote');


	$context['enabled_plugins'] = array();
	if (!empty($settings['enabled_plugins']))
	{

		loadSource('Subs-CachePHP');
		updateSettingsFile(array('my_plugins' => $my_plugins = (string) $settings['enabled_plugins']));
		wesql::query('DELETE FROM {db_prefix}settings WHERE variable = {literal:enabled_plugins}');
	}
	if (!empty($my_plugins))
	{

		$plugins = explode(',', $my_plugins);
		$hook_stack = array();
		foreach ($plugins as $plugin)
		{
			if (!empty($settings['plugin_' . $plugin]) && file_exists(ROOT_DIR . '/plugins/' . $plugin . '/plugin-info.xml'))
			{
				$plugin_details = @unserialize($settings['plugin_' . $plugin]);
				$context['enabled_plugins'][$plugin_details['id']] = $plugin;
				$context['plugins_dir'][$plugin_details['id']] = ROOT_DIR . '/plugins/' . $plugin;
				$context['plugins_url'][$plugin_details['id']] = ROOT . '/plugins/' . $plugin;
				if (isset($plugin_details['actions']))
					foreach ($plugin_details['actions'] as $action)
					{
						if (strpos($action['function'], '::') !== false)
							$action['function'] = explode('::', $action['function']);

						$action_list[$action['action']] = array($action['filename'], $action['function'], $plugin_details['id']);
						if (!empty($action['nolog']))
							$action_no_log[] = $action['action'];
					}

				unset($plugin_details['id'], $plugin_details['provides'], $plugin_details['actions']);

				foreach ($plugin_details as $hook => $functions)
					foreach ($functions as $function)
					{
						$priority = (int) substr(strrchr($function, '|'), 1);
						$hook_stack[$hook][$priority][] = strtr($function, array('$plugindir/' => ''));
					}
			}
			else
				$reset_plugins = true;
		}
		if (isset($reset_plugins))
		{
			loadSource('Subs-CachePHP');
			updateSettingsFile(array('my_plugins' => implode(',', $context['enabled_plugins'])));
		}


		foreach ($hook_stack as $hook => $hooks_by_priority)
		{
			krsort($hooks_by_priority);
			if (!isset($settings['hooks'][$hook]))
				$settings['hooks'][$hook] = array();
			foreach ($hooks_by_priority as $priority => $hooks)
				$settings['hooks'][$hook] = array_merge($settings['hooks'][$hook], $hooks);
		}
	}


	loadSource('Class-String');
	westr::getInstance();


	if (empty($settings['rand_seed']) || mt_rand(1, 250) == 42)
		updateSettings(array('rand_seed' => mt_rand()));


	date_default_timezone_set(isset($settings['default_timezone']) ? $settings['default_timezone'] : @date_default_timezone_get());


	if (!empty($settings['loadavg_enable']))
	{
		if (($settings['load_average'] = cache_get_data('loadavg', 90)) === null)
		{
			$settings['load_average'] = @file_get_contents('/proc/loadavg');
			if (!empty($settings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $settings['load_average'], $matches) != 0)
				$settings['load_average'] = (float) $matches[1];
			elseif (can_shell_exec() && ($settings['load_average'] = `uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $settings['load_average'], $matches) != 0)
				$settings['load_average'] = (float) $matches[1];
			else
				unset($settings['load_average']);

			if (!empty($settings['load_average']))
				cache_put_data('loadavg', $settings['load_average'], 90);
		}

		if (!empty($settings['loadavg_forum']) && !empty($settings['load_average']) && $settings['load_average'] >= $settings['loadavg_forum'])
			show_db_error(true);
	}


	$settings['postmod_active'] = !empty($settings['postmod_active']) || !empty($settings['postmod_rules']);

	if (!empty($settings['imported_from']) && !isset($settings['imported_cleaned']))
		importing_cleanup();


	call_hook('pre_load');


	cleanRequest();

	if (WEDGE == 'SSI')
		return;


	if (isset($_GET['scheduled']))
	{
		loadSource('ScheduledTasks');
		AutoTask();
	}
	elseif (isset($_GET['imperative']))
	{
		loadSource('Subs-Scheduled');
		ImperativeTask();
	}

	if (!headers_sent())
	{

		if (!empty($settings['enableCompressedOutput']))
		{

			if (ini_get('zlib.output_compression') >= 1 || ini_get('output_handler') == 'ob_gzhandler')
				$settings['enableCompressedOutput'] = 0;
			else
			{
				ob_end_clean();
				ob_start('ob_gzhandler');
			}
		}



		header('Server: ');
		header('X-Powered-By: ');
		header('X-XSS-Protection: 1');
		header('X-Frame-Options: SAMEORIGIN');
		header('X-Content-Type-Options: nosniff');
	}
}

function can_shell_exec()
{
	static $result = null;
	if ($result !== null)
		return $result;

	$disable_functions = explode(',', ini_get('disable_functions') . ',' . ini_get('suhosin.executor.func.blacklist'));
	return $result = (is_callable('shell_exec') && !ini_get('safe_mode') && !in_array('shell_exec', $disable_functions));
}












function loadBoard()
{
	global $context, $settings, $board_info, $board, $topic, $user_settings;


	$context['linktree'] = array();


	if (!$context['action'] && empty($topic) && empty($board) && !empty($_REQUEST['msg']))
	{

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];


		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === null)
		{
			$request = wesql::query('
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $_REQUEST['msg'],
				)
			);


			if (wesql::num_rows($request))
			{
				list ($topic) = wesql::fetch_row($request);
				wesql::free_result($request);

				cache_put_data('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}


		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
			rejectTopic();
	}


	if (empty($board) && empty($topic))
	{
		$board_info = array(
			'moderators' => array(),
			'skin' => '',
		);
		return;
	}

	elseif (empty($board) && !empty($topic) && $context['action'] === 'feed')
		return;

	if (!empty($settings['cache_enable']) && (empty($topic) || $settings['cache_enable'] >= 3))
	{

		if (!empty($topic))
			$temp = cache_get_data('topic_board-' . $topic, 120);
		else
			$temp = cache_get_data('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = $board_info['id'];
		}
	}

	if (empty($temp))
	{
		$request = wesql::query('
			SELECT
				c.id_cat, b.name AS bname, b.url, b.id_owner, b.description, b.num_topics, b.member_groups,
				b.num_posts, b.id_parent, c.name AS cname, IFNULL(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level, b.skin, b.skin_mobile,
				b.override_skin, b.count_posts, b.id_profile, b.redirect, b.language, bm.permission = \'deny\' AS banned,
				bm.permission = {literal:access} AS allowed, mco.real_name AS owner_name, mco.buddy_list AS contacts, b.board_type, b.sort_method,
				b.sort_override, b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.privacy, t.id_member_started' : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})' : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				LEFT JOIN {db_prefix}members AS mco ON (mco.id_member = b.id_owner)
				LEFT JOIN {db_prefix}board_members AS bm ON b.id_board = bm.id_board AND b.id_owner = {int:id_member}
			WHERE b.id_board = {raw:board_link}',
			array(
				'current_topic' => $topic,
				'board_link' => empty($topic) ? wesql::quote('{int:current_board}', array('current_board' => $board)) : 't.id_board',
				'id_member' => MID,
			)
		);

		if (wesql::num_rows($request) > 0)
		{
			$row = wesql::fetch_assoc($request);


			if (!empty($row['id_board']))
				$board = $row['id_board'];


			$board_info = array(
				'id' => $board,
				'owner_id' => $row['id_owner'],
				'owner_name' => $row['owner_name'],
				'moderators' => array(),
				'cat' => array(
					'id' => $row['id_cat'],
					'name' => $row['cname']
				),
				'name' => $row['bname'],
				'description' => $row['description'],
				'url' => $row['url'],
				'num_posts' => $row['num_posts'],
				'num_topics' => $row['num_topics'],
				'unapproved_topics' => $row['unapproved_topics'],
				'unapproved_posts' => $row['unapproved_posts'],
				'unapproved_user_topics' => 0,
				'parent_boards' => getBoardParents($row['id_parent']),
				'parent' => $row['id_parent'],
				'child_level' => $row['child_level'],
				'skin' => $row['skin'],
				'skin_mobile' => $row['skin_mobile'],
				'override_skin' => !empty($row['override_skin']),
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) ? 1 : $row['approved'],
				'cur_topic_privacy' => empty($topic) ? PRIVACY_DEFAULT : $row['privacy'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
				'allowed_member' => $row['allowed'],
				'banned_member' => $row['banned'],
				'contacts' => $row['contacts'],
				'language' => $row['language'],
				'type' => $row['board_type'],
				'sort_method' => $row['sort_method'],
				'sort_override' => $row['sort_override'],
			);




			if ($row['member_groups'] === '0')
				$board_info['privacy'] = 'members';
			elseif ($row['member_groups'] === '-1,0')
				$board_info['privacy'] = 'everyone';
			elseif ($row['member_groups'] === 'contacts')
				$board_info['privacy'] = 'contacts';
			elseif ($row['member_groups'] === '')
			{
				$board_info['privacy'] = 'author';
				$row['member_groups'] = '';
			}
			else
				$board_info['privacy'] = 'everyone';

			if (!empty($row['id_owner']))
				$board_info['moderators'] = array(
					$row['id_owner'] => array(
						'id' => $row['id_owner'],
						'name' => $row['owner_name'],
						'href' => '<URL>?action=profile;u=' . $row['id_owner'],
						'link' => '<a href="<URL>?action=profile;u=' . $row['id_owner'] . '">' . $row['owner_name'] . '</a>'
					)
				);

			do
			{
				if (!empty($row['id_moderator']) && $row['id_moderator'] != $row['id_owner'])
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => '<URL>?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="<URL>?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);
			}
			while ($row = wesql::fetch_assoc($request));



			if ($board_info['num_topics'] == 0 && $settings['postmod_active'] && !allowedTo('approve_posts'))
			{
				wesql::free_result($request);

				$request = wesql::query('
					SELECT COUNT(id_topic)
					FROM {db_prefix}topics
					WHERE id_member_started = {int:id_member}
						AND approved = {int:is_unapproved}
						AND id_board = {int:board}',
					array(
						'id_member' => MID,
						'is_unapproved' => 0,
						'board' => $board,
					)
				);

				list ($board_info['unapproved_user_topics']) = wesql::fetch_row($request);
			}

			if (!empty($settings['cache_enable']) && (empty($topic) || $settings['cache_enable'] >= 3))
			{

				if (!empty($topic))
					cache_put_data('topic_board-' . $topic, $board_info, 120);
				cache_put_data('board-' . $board, $board_info, 120);
			}
		}
		else
		{

			$board_info = array(
				'moderators' => array(),
				'skin' => '',
				'error' => 'exist',
			);
			$topic = null;
			$board = 0;
		}
		wesql::free_result($request);
	}

	if (isset($board_info['skin_mobile']) && we::is('mobile'))
		$board_info['skin'] = $board_info['skin_mobile'];

	if (!empty($topic))
		$_GET['board'] = (int) $board;

	if (!empty($board))
	{

		we::$cache = array();
		we::$is['mod'] |= isset($board_info['moderators'][MID]);
		we::$is['b' . $board] = true;
		we::$is['c' . $board_info['cat']['id']] = true;

		if ($board_info['banned_member'] && !$board_info['allowed_member'])
			$board_info['error'] = 'access';

		if (!we::$is_admin && !in_array($board_info['id'], we::$user['qsb_boards']))
		{
			if (!we::$is['mod'] && (!empty($board_info['owner_id']) && MID != $board_info['owner_id']))
			{
				switch ($board_info['privacy'])
				{
					case 'contacts':
						if (!in_array(MID, explode(',', $board_info['contacts'])))
							$board_info['error'] = 'access';
						break;
					case 'members':
						if (we::$is_guest)
							$board_info['error'] = 'access';
						break;
					case 'author':
						$board_info['error'] = 'access';
						break;
					case 'everyone':
						$board_info['error'] = 'access';
						break;
				}
			}
			else
				$board_info['error'] = 'access';
		}


		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
				'url' => '<URL>?category=' . $board_info['cat']['id'],
				'name' => $board_info['cat']['name']
			)),
			array_reverse($board_info['parent_boards']),
			array(array(
				'url' => '<URL>?board=' . $board . '.0',
				'name' => $board_info['name']
			))
		);



		if (!empty($board_info['language']) && empty($user_settings['lngfile']))
		{
			we::$user['language'] = $board_info['language'];
			$user_settings['lngfile'] = $board_info['language'];
		}
	}


	$context['current_topic'] = $topic;
	$context['current_board'] = $board;


	if (!empty($board_info['error']) && ($board_info['error'] != 'access' || !we::$is['mod']))
		rejectTopic();

	if (we::$is['mod'])
		we::$user['groups'][] = 3;
}

function rejectTopic()
{
	global $context, $txt;


	loadPermissions();
	loadTheme();

	$_GET['board'] = '';
	$_GET['topic'] = '';



	$context['linktree'] = array();
	add_linktree($context['forum_name_html_safe'], '<URL>');


	preventPrefetch($context['action'] === 'dlattach');

	if (we::$is_guest)
	{
		loadLanguage('Errors');
		is_not_guest($txt['topic_gone']);
	}
	else
		fatal_lang_error('topic_gone', false);
}













function loadPermissions()
{
	global $board, $board_info, $settings;

	if (we::$is_admin)
	{
		banPermissions();
		we::permissions();
		return;
	}

	if (!empty($settings['cache_enable']))
	{
		$cache_groups = we::$user['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);

		if (we::$user['possibly_robot'])
			$cache_groups .= '-spider';

		if ($settings['cache_enable'] >= 2 && !empty($board) && ($temp = cache_get_data('permissions:' . $cache_groups . ':' . $board, 240)) !== null && time() - 240 > $settings['settings_updated'])
		{
			list (we::$user['permissions']) = $temp;
			banPermissions();
			we::permissions();
			return;
		}
		elseif (($temp = cache_get_data('permissions:' . $cache_groups, 240)) !== null && time() - 240 > $settings['settings_updated'])
			list (we::$user['permissions'], $removals) = $temp;
	}


	$spider_restrict = we::$user['possibly_robot'] && !empty($settings['spider_mode']) && !empty($settings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty(we::$user['permissions']))
	{

		$request = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => we::$user['groups'],
				'spider_group' => !empty($settings['spider_group']) ? $settings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				we::$user['permissions'][] = $row['permission'];
		}
		wesql::free_result($request);

		if (isset($cache_groups))
			cache_put_data('permissions:' . $cache_groups, array(we::$user['permissions'], $removals), 240);
	}


	if (!empty($board))
	{

		if (!isset($board_info['profile']))
			fatal_lang_error('no_board');

		$request = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => we::$user['groups'],
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty($settings['spider_mode']) && !empty($settings['spider_group']) ? $settings['spider_group'] : 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				we::$user['permissions'][] = $row['permission'];
		}
		wesql::free_result($request);

		if (!empty($board_info['owner_id']) && $board_info['owner_id'] == MID)
			we::$user['permissions'][] = 'moderate_board';
	}


	we::$user['permissions'] = array_diff(array_flip(array_flip(we::$user['permissions'])), empty($removals) ? array() : $removals);

	if (isset($cache_groups) && !empty($board) && $settings['cache_enable'] >= 2)
		cache_put_data('permissions:' . $cache_groups . ':' . $board, array(we::$user['permissions'], null), 240);


	banPermissions();


	we::permissions();


	if (we::$is_member)
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $settings['settings_updated'])
		{
			loadSource('Subs-Auth');
			rebuildModCache();
		}
		else
			we::$user['mod_cache'] = $_SESSION['mc'];
	}
}











function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $settings, $board_info;
	static $infraction_levels = null;


	if (empty($users))
		return false;

	if ($infraction_levels === null)
	{
		$infraction_levels = array();
		$levels = !empty($settings['infraction_levels']) ? @unserialize($settings['infraction_levels']) : array();
		foreach ($levels as $infraction => $details)
			if (!empty($details['enabled']))
				$infraction_levels[$infraction] = $details['points'];
	}


	$users = !is_array($users) ? array($users) : array_flip(array_flip($users));
	$loaded_ids = array();

	if (!$is_name && !empty($settings['cache_enable']) && $settings['cache_enable'] >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			if (($data = cache_get_data('member_data-' . $set . '-' . $users[$i], 240)) === null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.transparency, a.id_folder,
			mem.id_member, mem.member_name, mem.real_name, mem.signature, mem.personal_text, mem.location, mem.gender,
			mem.avatar, mem.email_address, mem.hide_email, mem.website_title, mem.website_url, mem.birthdate,
			mem.posts, mem.id_group, mem.id_post_group, mem.show_online, mem.warning, mem.is_activated, mem.data,

			IFNULL(mg.group_name, {string:blank}) AS member_group,
			IFNULL(pg.group_name, {string:blank}) AS post_group,'
			. (!empty($settings['titlesEnable']) ? ' mem.usertitle,' : '');

	$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';

	if ($set === 'normal')
	{
		$select_columns .= '
			mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile,
			mem.time_offset, mem.date_registered, mem.buddy_list,
			mem.media_items, mem.media_comments';
	}
	elseif ($set === 'profile')
	{
		$select_columns .= '
			mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile,
			mem.time_offset, mem.date_registered, mem.buddy_list,
			mem.media_items, mem.media_comments,

			mem.additional_groups,

			mem.pm_ignore_list, mem.pm_email_notify, mem.pm_receive_from,
			mem.time_format, mem.timezone, mem.smiley_set, mem.total_time_logged_in,
			mem.ignore_boards, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
			mem.notify_types, lo.url, mem.password_salt, mem.pm_prefs, mem.data';

		$get_badges = true;
	}
	elseif ($set === 'userbox')
	{
		$select_columns .= '
			mem.additional_groups';

		$get_badges = true;
	}
	elseif ($set === 'minimal')
	{
		$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';

		$select_tables = '';
	}
	else
		trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);

	if (isset($get_badges))
	{

		if (($mba = cache_get_data('member-badges', 5000)) === null)
		{
			$mba = array();
			$request = wesql::query('
				SELECT g.id_group, g.stars, g.show_when, g.display_order
				FROM {db_prefix}membergroups AS g
				WHERE g.show_when != {int:never}
				ORDER BY g.display_order',
				array(
					'never' => 0,
				)
			);

			while ($row = wesql::fetch_assoc($request))
				if (!empty($row['stars']))
					$mba[$row['id_group']] = array($row['show_when'], $row['stars'], $row['display_order']);
			wesql::free_result($request);
			cache_put_data('member-badges', $mba, 5000);
		}
	}

	if (!empty($users))
	{

		$request = wesql::query('
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . (count($users) == 1 ? ' = {' . ($is_name ? 'string' : 'int') . ':users}' : ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})'),
			array(
				'blank' => '',
				'users' => count($users) == 1 ? current($users) : $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$new_loaded_ids[] = $row['id_member'];
			$loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			$row['set'] = $set;
			if (!empty($row['member_ip']))
			{
				$row['member_ip'] = format_ip($row['member_ip']);
				$row['member_ip2'] = format_ip($row['member_ip2']);
			}

			if (!empty($settings['signature_minposts']) && ((int) $row['posts'] < (int) $settings['signature_minposts']))
			{

				if (($set === 'normal' || $set === 'userbox') && $row['id_group'] != 1)
					$row['signature'] = '';

				elseif ($set === 'profile' && !(($row['id_member'] == MID && allowedTo('profile_signature_own')) || allowedTo('profile_signature_any')))
					$row['signature'] = '';
			}


			if (!empty($row['data']))
			{
				$row['data'] = @unserialize($row['data']);
				$row['sanctions'] = !empty($row['data']['sanctions']) ? $row['data']['sanctions'] : array();
			}
			else
				$row['data'] = array();



			if (!empty($infraction_levels) && !empty($row['warning']))
				foreach ($infraction_levels as $infraction => $points)
					if ($row['warning'] >= $points)
						$row['sanctions'][$infraction] = 1;


			if (!empty($row['sanctions']))
				foreach ($row['sanctions'] as $infraction => $expiry)
					if ($expiry != 1 && $expiry < time())
						unset($row['sanctions'][$infraction]);


			if (!empty($row['sanctions']['no_sig']))
			{

				if ($set === 'normal' || $set === 'userbox' || ($set === 'profile' && !(($row['id_member'] == MID && allowedTo('profile_signature_own')) || allowedTo('profile_signature_any'))))
					$row['signature'] = '';
			}


			if (!empty($settings['ban_group']) && !empty($row['is_activated']) && ($row['is_activated'] >= 20 || isset($row['sanctions']['hard_ban'])))
			{
				$row['additional_groups'] = !empty($row['additional_groups']) ? $row['id_group'] . ',' . $row['additional_groups'] : $row['id_group'];
				$row['id_group'] = $settings['ban_group'];
			}

			$user_profile[$row['id_member']] = $row;
		}
		wesql::free_result($request);
	}


	if (!empty($loaded_ids) && $set == 'profile')
	{
		$last = wesql::get_assoc('
			SELECT m.id_member, m.poster_time, m.id_msg, m.id_topic, m.subject
			FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}topics AS t ON t.id_topic = m.id_topic
			WHERE m.id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})') . '
				AND {query_see_topic}
			ORDER BY m.id_msg DESC
			LIMIT 1',
			array('loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids)
		);

		if (!empty($last))
			$user_profile[$last['id_member']]['last_post'] = array(
				'on_time' => on_timeformat($last['poster_time']),
				'link' => '<a href="' . SCRIPT . '?topic=' . $last['id_topic'] . '.msg' . $last['id_msg'] . '#new">' . $last['subject'] . '</a>',
			);
	}

	if (!empty($new_loaded_ids) && $set !== 'minimal')
	{
		$request = wesql::query('
			SELECT *
			FROM {db_prefix}themes
			WHERE id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		wesql::free_result($request);
	}

	if (!empty($new_loaded_ids) && !empty($settings['cache_enable']) && $settings['cache_enable'] >= 3)
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);


	if (!empty($loaded_ids) && !empty($board_info['moderators']) && ($set === 'normal' || $set === 'userbox') && count($temp_mods = array_intersect($loaded_ids, array_keys($board_info['moderators']))) > 0)
	{
		if (($row = cache_get_data('moderator_group_info', 480)) === null)
		{
			$request = wesql::query('
				SELECT group_name AS member_group, stars
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				array(
					'moderator_group' => 3,
				)
			);
			$row = wesql::fetch_assoc($request);
			wesql::free_result($request);

			cache_put_data('moderator_group_info', $row, 480);
		}
		foreach ($temp_mods as $id)
		{

			if (empty($user_profile[$id]['additional_groups']))
				$user_profile[$id]['additional_groups'] = '3';
			else
				$user_profile[$id]['additional_groups'] .= ',3';


			if ($user_profile[$id]['id_group'] != 1 && $user_profile[$id]['id_group'] != 2)
				$user_profile[$id]['member_group'] = $row['member_group'];


			if (!empty($row['stars']))
				$user_profile[$id]['stars'] = $row['stars'];
		}
	}


	if (!empty($loaded_ids) && isset($get_badges))
	{





		foreach ($loaded_ids as $id)
		{
			if (isset($user_profile[$id]['badges']))
				continue;
			$user_profile[$id]['badges'] = array();


			$badges = array();


			$gid = $user_profile[$id]['id_group'];
			if (isset($mba[$gid]))
				$badges[$mba[$gid][2]] = array($gid, $mba[$gid][1]);


			foreach (explode(',', $user_profile[$id]['additional_groups']) as $gid)
				if (isset($mba[$gid]) && $mba[$gid][0] != 2 && ($mba[$gid][0] == 1 || empty($badges)))
					$badges[$mba[$gid][2]] = array($gid, $mba[$gid][1]);


			$gid = $user_profile[$id]['id_post_group'];
			if (isset($mba[$gid]) && ($mba[$gid][0] == 1 || empty($badges)))
				$badges[$mba[$gid][2]] = array($gid, $mba[$gid][1]);

			if (!empty($badges))
				ksort($badges);
			foreach ($badges as $badge)
				$user_profile[$id]['badges'][$badge[0]] = $badge[1];
		}
	}

	return empty($loaded_ids) ? false : $loaded_ids;
}






























function loadMemberContext($user, $full_profile = false)
{
	global $memberContext, $user_profile, $txt, $context, $settings;
	static $ban_threshold = null, $dataLoaded = array();

	if (is_array($user))
	{
		foreach ($user as $uid)
			loadMemberContext($uid);
		return;
	}


	if (isset($dataLoaded[$user]))
		return true;


	if ($user == 0)
		return false;
	if (!isset($user_profile[$user]))
	{
		trigger_error('loadMemberContext(): member id ' . $user . ' not previously loaded by loadMemberData()', E_USER_WARNING);
		return false;
	}

	if (empty($ban_threshold))
		$ban_threshold = $user == MID ? 20 : 10;


	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];


	censorText($profile['signature']);
	censorText($profile['personal_text']);
	censorText($profile['location']);


	$profile['signature'] = str_replace(array("\n", "\r"), array('<br>', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], 'signature', array('cache' => 'sig' . $profile['id_member']));

	$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;

	$profile['buddy'] = in_array($profile['id_member'], we::$user['buddies']);
	$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();


	if (!empty($profile['sanctions']['hard_ban']) || (!empty($profile['is_activated']) && $profile['is_activated'] > 20))
		$profile['warning_status'] = 'hard_ban';
	elseif (!we::$is_guest && $user != MID && (!empty($profile['sanctions']['soft_ban']) || (!empty($profile['is_activated']) && $profile['is_activated'] > 10)))
		$profile['warning_status'] = 'soft_ban';
	elseif (!empty($profile['sanctions']['post_ban']))
		$profile['warning_status'] = 'mute';
	elseif (!empty($profile['sanctions']['moderate']))
		$profile['warning_status'] = 'moderate';
	elseif (!empty($profile['warning']))
		$profile['warning_status'] = 'warned';
	else
		$profile['warning_status'] = '';


	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'is_buddy' => $profile['buddy'],
		'is_reverse_buddy' => in_array(MID, $buddy_list),
		'buddies' => $buddy_list,
		'title' => !empty($settings['titlesEnable']) ? $profile['usertitle'] : '',
		'href' => '<URL>?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="<URL>?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['view_profile'] . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : $profile['date_registered'],
		'blurb' => $profile['personal_text'],
		'gender' => $profile['gender'] == 2 ? 'female' : ($profile['gender'] == 1 ? 'male' : ''),
		'website' => array(
			'title' => $profile['website_title'],
			'url' => $profile['website_url'],
		),
		'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
		'signature' => $profile['signature'],
		'location' => $profile['location'],
		'real_posts' => $profile['posts'],
		'posts' => comma_format($profile['posts']),
		'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
		'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
		'last_post' => empty($profile['last_post']) ? 0 : $profile['last_post'],
		'ip' => isset($profile['member_ip']) ? htmlspecialchars($profile['member_ip']) : '',
		'ip2' => isset($profile['member_ip2']) ? htmlspecialchars($profile['member_ip2']) : '',
		'online' => array(
			'is_online' => $profile['is_online'],
			'text' => $txt[$profile['is_online'] ? 'online' : 'offline'],
			'href' => '<URL>?action=pm;sa=send;u=' . $profile['id_member'],
			'link' => '<a href="<URL>?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
			'image_href' => ASSETS . '/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.gif',
			'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
		),
		'language' => isset($profile['lngfile']) ? westr::ucwords(strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))) : '',
		'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
		'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 20 : 0,
		'options' => $profile['options'],
		'is_guest' => false,
		'group' => $profile['member_group'],
		'group_id' => $profile['id_group'],
		'post_group' => $profile['post_group'],
		'group_badges' => array(),
		'warning' => $profile['warning'],
		'warning_status' => $profile['warning_status'],
		'local_time' => isset($profile['time_offset']) ? timeformat(time() + ($profile['time_offset'] - we::$user['time_offset']) * 3600, false) : 0,
		'media' => isset($profile['media_items']) ? array(
			'total_items' => $profile['media_items'],
			'total_comments' => $profile['media_comments'],
		) : array(),
		'avatar' => array(
			'name' => '',
			'image' => '',
			'href' => '',
			'url' => '',
		),
	);

	if (!empty($profile['badges']))
	{
		foreach ($profile['badges'] as $badge)
		{
			$stars = explode('#', $badge);
			if (!empty($stars[0]) && !empty($stars[1]))
				$memberContext[$user]['group_badges'][] = str_repeat('<img src="' . str_replace('$language', we::$user['language'], ASSETS . '/' . $stars[1]) . '">', $stars[0]);
		}
	}


	loadMemberAvatar($user, true);


	if ($full_profile && !empty($settings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();
		if (!isset($context['display_fields']))
			$context['display_fields'] = unserialize($settings['displayFields']);

		foreach ($context['display_fields'] as $custom)
		{
			if (empty($custom['title']) || empty($profile['options'][$custom['colname']]))
				continue;
			elseif (!we::$is_admin && count(array_intersect(we::$user['groups'], $custom['can_see'])) == 0)
				continue;
			elseif (!we::$is_admin && $user == MID && !in_array(-2, $custom['can_see']))
				continue;

			$value = $profile['options'][$custom['colname']];


			if ($custom['bbc'])
				$value = parse_bbc($value, 'custom-field');

			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? $txt['yes'] : $txt['no'];


			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => '<URL>',
					'{IMAGES_URL}' => ASSETS,
					'{INPUT}' => $value,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	return true;
}




function loadMemberAvatar($user, $force = false)
{
	global $settings, $memberContext, $user_profile;
	static $dataLoaded = array(), $avatar_width = null, $avatar_height = null;

	if (is_array($user))
	{
		foreach ($user as $uid)
			loadMemberAvatar($uid);
		return;
	}


	if (isset($dataLoaded[$user]) && !$force)
		return true;

	$dataLoaded[$user] = true;
	if (!isset($user_profile[$user]))
		return;

	$profile = $user_profile[$user];


	if (isset($memberContext[$user]) && $memberContext[$user]['is_banned'] && !empty($settings['avatar_banned_hide']))
		return;


	if (!empty($profile['sanctions']['no_avatar']))
	{

		if ($profile['set'] === 'normal' || $profile['set'] === 'userbox' || ($profile['set'] === 'profile' && !(($user == MID && allowedTo(array('profile_upload_avatar', 'profile_remote_avatar'))) || allowedTo('profile_extra_any'))))
			return;
	}


	if ($avatar_width === null)
	{
		$set_size = $settings['avatar_action_too_large'] == 'option_html_resize' || $settings['avatar_action_too_large'] == 'option_js_resize';
		$avatar_width = $set_size && !empty($settings['avatar_max_width_external']) ? ' width="' . $settings['avatar_max_width_external'] . '"' : '';
		$avatar_height = $set_size && !empty($settings['avatar_max_height_external']) ? ' height="' . $settings['avatar_max_height_external'] . '"' : '';
	}


	if (!empty($profile['avatar']))
	{
		if (stristr($profile['avatar'], 'gravatar://'))
		{
			$image = get_gravatar_url($profile['avatar'] === 'gravatar://' || empty($settings['gravatarAllowExtraEmail']) ? $profile['email_address'] : substr($profile['avatar'], 11));
			$image_tag = '<img class="avatar" src="' . $image . '"' . $avatar_width . $avatar_height . '>';
		}
		else
		{
			$image = strpos($profile['avatar'], '://') !== false || strpos($profile['avatar'], '//') === 0 ? $profile['avatar'] : AVATARS . '/' . $profile['avatar'];
			$image_tag = $image === $profile['avatar'] ? '<img class="avatar" src="' . $image . '"' . $avatar_width . $avatar_height . '>' : '<img class="avatar" src="' . AVATARS . '/' . htmlspecialchars($profile['avatar']) . '">';
		}
		$memberContext[$user]['avatar'] = array(
			'name' => $profile['avatar'],
			'image' => $image_tag,
			'href' => $image,
			'url' => $image,
		);
	}

	elseif (!empty($profile['id_attach']))
	{
		if (!$profile['transparency'])
		{
			$filename = getAttachmentFilename($profile['filename'], $profile['id_attach'], $profile['id_folder']);
			$profile['transparency'] =
				we_resetTransparency(
					$profile['id_attach'],
					empty($profile['attachment_type']) ? $filename : $settings['custom_avatar_dir'] . '/' . $profile['filename'],
					$profile['filename']
				) ? 'transparent' : 'opaque';
		}
		$memberContext[$user]['avatar'] = array(
			'name' => $profile['avatar'],
			'image' => $profile['id_attach'] > 0 ? '<img class="' . ($profile['transparency'] == 'transparent' ? '' : 'opaque ') . 'avatar" src="' . (empty($profile['attachment_type']) ? '<URL>?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $settings['custom_avatar_url'] . '/' . $profile['filename']) . '">' : '',
			'href' => $profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? '<URL>?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $settings['custom_avatar_url'] . '/' . $profile['filename']) : '',
			'url' => '',
		);
	}

	elseif (false)
	{

	}
}






function we_resetTransparency($id_attach, $path, $real_name)
{
	loadSource('media/Subs-Media');
	$is_transparent = aeva_isTransparent($path, $real_name);
	wesql::query('
		UPDATE {db_prefix}attachments
		SET transparency = {string:transparency}
		WHERE id_attach = {int:id_attach}',
		array(
			'id_attach' => $id_attach,
			'transparency' => $is_transparent ? 'transparent' : 'opaque',
		)
	);
	return $is_transparent;
}



















function loadTheme($skin = '', $initialize = true)
{
	global $user_settings, $board_info, $footer_coding;
	global $txt, $mbname, $settings, $context, $options;


	if (!$skin)
	{

		if (!empty($settings['theme_allow']) || allowedTo('admin_forum'))
			$skin = empty($_REQUEST['presk']) || strpos($_REQUEST['presk'], '/') !== false ? we::$user['skin'] : $_REQUEST['presk'];


		if (!empty($board_info['skin']) && $board_info['override_skin'])
			$skin = isset($board_info['skin']) ? $board_info['skin'] : '';
	}


	$context['skin_actual'] = $skin;
	$context['skin'] = '/' . ($skin ? ltrim($skin, '/') : get_default_skin());





	$context['main_css_files'] = array(
		'index' => false,
		'sections' => false
	);

	$member = MID ? MID : -1;

	if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings:' . $member, 60)) !== null && time() - 60 > $settings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	elseif (($temp = cache_get_data('theme_settings', 90)) !== null && time() - 60 > $settings['settings_updated'])
		$themeData = $temp + array($member => array());
	else
		$themeData = array(-1 => array(), 0 => array(), $member => array());

	if (empty($flag))
	{

		$result = wesql::query('
			SELECT variable, value, id_member
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}'),
			array(
				'id_member' => $member,
			)
		);
		while ($row = wesql::fetch_assoc($result))
			if (!isset($themeData[$row['id_member']][$row['variable']]))
				$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
		wesql::free_result($result);

		if (!empty($themeData[-1]))
			foreach ($themeData[-1] as $k => $v)
				if (!isset($themeData[$member][$k]))
					$themeData[$member][$k] = $v;

		if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
			cache_put_data('theme_settings:' . $member, $themeData, 60);

		elseif (!isset($temp))
			cache_put_data('theme_settings', array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	$options = $themeData[$member];


	$context['template_folders'] = array(TEMPLATES_DIR);

	if (!$initialize)
		return;


	we::$user['smiley_set'] = (!in_array(we::$user['smiley_set'], explode(',', $settings['smiley_sets_known'])) && we::$user['smiley_set'] != 'none') || empty($settings['smiley_sets_enable']) ? $settings['smiley_sets_default'] : we::$user['smiley_set'];


	if (!isset($context['header']))
		$context['header'] = '';
	if (!isset($context['footer']))
		$context['footer'] = '';
	if (!isset($context['footer_js']))
		$context['footer_js'] = '';
	if (!isset($context['footer_js_inline']))
		$context['footer_js_inline'] = '';



	$footer_coding = true;

	$context['page_separator'] = !empty($context['page_separator']) ? $context['page_separator'] : '&nbsp;&nbsp;';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['session_query'] = $context['session_var'] . '=' . $context['session_id'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = westr::htmlspecialchars($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? $context['forum_name_html_safe']
		: 'htmlsafe::' . westr::htmlspecialchars('<img src="' . westr::htmlspecialchars($settings['header_logo_url']) . '" alt="' . $context['forum_name'] . '">');
	$context['site_slogan'] = empty($settings['site_slogan']) ? '<div id="logo"></div>' : '<div id="slogan">' . $settings['site_slogan'] . '</div>';
	if (isset($settings['load_average']))
		$context['load_average'] = $settings['load_average'];


	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => strpos(PHP_OS, 'WIN') === 0,
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
	);

	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];


	add_linktree($context['forum_name_html_safe'], '<URL>', null, null, true);

	if (!isset($txt))
		$txt = array();


	$context['macros'] = array();
	$context['skeleton'] = array();
	$context['skeleton_ops'] = array();
	$context['jquery_version'] = we::is('ie[-8],firefox[-3.6]') ? '1.12.4' : '3.2.1';
	loadSource('Subs-Cache');




	if (AJAX || $context['action'] === 'feed' || $context['action'] === 'printpage')
	{
		loadLanguage('index');
		$context['right_to_left'] = !empty($txt['lang_rtl']);
		wedge_get_skin_options(true);
	}
	else
	{

		wedge_get_skin_options();


		if (we::is('ie8'))
			add_js_file('respond.js');


		if (isset($context['theme_templates']))
			$templates = explode(',', $context['theme_templates']);
		else
			$templates = array('index');




		$templates[] = 'Custom';


		foreach ($templates as $template)
			loadTemplate($template, $template !== 'Custom');


		loadLanguage($templates, '', false);
		we::$is['rtl'] = $context['right_to_left'] = !empty($txt['lang_rtl']);


		weInitJS();


		execBlock('init', 'ignore');
	}


	wetem::createMainSkeleton();


	if (we::is('ie6,ie7') && !we::$user['possibly_robot'])
	{
		loadTemplate('Errors');
		wetem::add(array('top', 'default'), 'unsupported_browser');
	}


	if (!empty($context['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);


	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		we::$user['time_format'] = $txt['time_format'];

	if (empty(we::$user['name']) && !empty($txt['guest_title']))
		we::$user['name'] = $txt['guest_title'];

	$context['tabindex'] = 1;
	$time = time();


	if ((!empty($settings['mail_next_send']) && $settings['mail_next_send'] < $time && empty($settings['mail_queue_use_cron'])) || empty($settings['next_task_time']) || $settings['next_task_time'] < $time)
	{
		$is_task = empty($settings['next_task_time']) || $settings['next_task_time'] < $time;
		if (we::$browser['possibly_robot'])
		{

			loadSource('ScheduledTasks');


			if ($is_task)
				AutoTask();
			else
				ReduceMailQueue();
		}
		else
		{
			$type = $is_task ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $settings['mail_next_send'] : $settings['next_task_time'];

			add_js('
	$.get(weUrl("scheduled=' . $type . ';ts=' . $ts . '"));');
		}
	}


	if (empty($settings['next_imperative']))
	{
		loadSource('Subs-Scheduled');
		recalculateNextImperative();
	}

	if ($settings['next_imperative'] < $time)
		add_js('
	$.get(weUrl("imperative"));');


	loadSource('Notifications');
	weNotif::initialize();


	call_hook('load_theme');


	$context['theme_loaded'] = true;
}

function weInitJS()
{
	global $settings, $context;

	$origin = empty($settings['jquery_origin']) ? 'local' : $settings['jquery_origin'];


	if ($origin !== 'local' && $origin !== 'jquery' && strhas($context['jquery_version'], array('b', 'rc')))
		$origin = 'jquery';

	if ($origin === 'local')
		$context['main_js_files'] = array(
			'jquery-' . $context['jquery_version'] . '.min.js' => true,
			'script.js' => true,
			'sbox.js' => false,
			'custom.js' => false
		);
	else
	{
		$remote = array(
			'google' =>		'ajax.googleapis.com/ajax/libs/jquery/' . $context['jquery_version'] . '/jquery.min.js',
			'microsoft' =>	'ajax.aspnetcdn.com/ajax/jquery/jquery-' . $context['jquery_version'] . '.min.js',
			'jquery' =>		'code.jquery.com/jquery-' . $context['jquery_version'] . '.min.js',
		);
		$context['remote_js_files'] = array('//' . $remote[$origin]);
		$context['main_js_files'] = array(
			'script.js' => true,
			'sbox.js' => false,
			'custom.js' => false
		);
	}
}

function loadPluginSource($plugin_name, $source_name)
{
	global $context;

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	foreach ((array) $source_name as $file)
		require_once($context['plugins_dir'][$plugin_name] . '/' . $file . '.php');
}

function loadPluginTemplate($plugin_name, $template_name, $fatal = true)
{
	global $context;

	if (empty($context['plugins_dir'][$plugin_name]))
		return;


	$old_templates = $context['template_folders'];
	$context['template_folders'] = array($context['plugins_dir'][$plugin_name]);
	loadTemplate($template_name, $fatal);
	$context['template_folders'] = $old_templates;
}

function loadPluginLanguage($plugin_name, $template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $context, $settings, $txt, $db_show_debug;
	static $already_loaded = array();

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	if (empty($txt))
		$txt = array();


	if ($lang == '')
		$lang = isset(we::$user['language']) ? we::$user['language'] : $settings['language'];

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	$key = $plugin_name . ':' . $template_name;
	$file_key = valid_filename($key);


	if (file_exists($filename = CACHE_DIR . '/lang/' . $lang . '_' . $file_key . '.php'))
	{
		@include($filename);
		if (!empty($val))
		{
			$txt = array_merge($txt, unserialize($val));

			$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $plugin_name . ', cached)';
			$already_loaded[$plugin_name . ':' . $template_name] = $lang;

			return $lang;
		}
	}


	$oldtxt = $txt;
	$txt = array();

	$attempts = array('english');
	if ($lang != 'english')
		$attempts[] = $lang;



	$found = false;
	foreach ($attempts as $load_lang)
	{
		if (file_exists($context['plugins_dir'][$plugin_name] . '/' . $template_name . '.' . $load_lang . '.php'))
		{
			template_include($context['plugins_dir'][$plugin_name] . '/' . $template_name . '.' . $load_lang . '.php', false, true);
			$found = true;
		}
	}


	if (!$found)
	{

		if (isset($txt))
			$txt = !empty($txt) ? array_merge($oldtxt, $txt) : $oldtxt;
		if ($fatal)
			log_error(sprintf($txt['theme_language_error'], '(' . $plugin_name . ') ' . $template_name . '.' . $lang, 'template'));
	}

	else
	{

		$request = wesql::query('
			SELECT lang_key, lang_string, serial
			FROM {db_prefix}language_changes
			WHERE id_lang = {string:lang}
				AND lang_file = {string:lang_file}',
			array(
				'lang' => $lang,
				'lang_file' => $key,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$txt[$row['lang_key']] = !empty($row['serial']) ? @unserialize($row['lang_string']) : $row['lang_string'];
		wesql::free_result($request);


		$filename = CACHE_DIR . '/lang/' . $lang . '_' . $file_key . '.php';
		if (!empty($txt))
			$txt = array_map('westr::entity_to_utf8', $txt);
		$cache_data = '<' . '?php if(defined(\'WEDGE\'))$val=\'' . addcslashes(serialize($txt), '\\\'') . '\';?' . '>';
		if (file_put_contents($filename, $cache_data, LOCK_EX) !== strlen($cache_data))
			@unlink($filename);


		if (!empty($txt) || !empty($oldtxt))
			$txt = array_merge($oldtxt, $txt);
	}


	if (!empty($db_show_debug))
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $plugin_name . ')';


	$already_loaded[$plugin_name . ':' . $template_name] = $lang;


	return $lang;
}














function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false, $fallback = false)
{
	global $context, $settings, $db_show_debug, $txt;
	static $already_loaded = array(), $reps = null;

	if ($force_reload === 'all')
	{
		loadLanguage(array_keys($already_loaded), $lang, $fatal, true, $fallback);
		return '';
	}


	if ($lang == '')
		$lang = isset(we::$user['language']) ? we::$user['language'] : $settings['language'];

	if (empty($txt))
		$txt = array();


	foreach ((array) $template_name as $template)
	{
		if (!$force_reload && isset($already_loaded[$template]) && ($already_loaded[$template] == $lang || $fallback))
			continue;

		if (!defined('WEDGE_INSTALLER'))
		{

			$filename = CACHE_DIR . '/lang/' . $lang . '_' . $template . '.php';
			if (file_exists($filename))
			{
				include($filename);
				if (!empty($val))
				{
					$txt = array_merge($txt, @unserialize($val));
					$loaded = true;
				}
			}

			if (isset($loaded))
			{

				$context['debug']['language_files'][] = $template . '.' . $lang;
				$already_loaded[$template] = $lang;
				unset($loaded);
				continue;
			}


			$oldtxt = $txt;
			$txt = array();
		}


		if (!isset($language_attempts))
			$language_attempts = array_flip(array_flip(array('english', ($pos = strpos($lang, '-')) !== false ? substr($lang, 0, $pos) : $lang, $lang)));


		$found = $template === 'Custom';
		$rep_str = '';
		foreach ($language_attempts as $attempt)
		{
			if ($reps === null && file_exists(LANGUAGES_DIR . '/' . $attempt . '/replacements.xml'))
				$rep_str .= file_get_contents(LANGUAGES_DIR . '/' . $attempt . '/replacements.xml');

			if (file_exists(LANGUAGES_DIR . '/' . $template . '.' . $attempt . '.php'))
				template_include(LANGUAGES_DIR . '/' . $template . '.' . $attempt . '.php', false, true);
			elseif (file_exists(LANGUAGES_DIR . '/' . $attempt . '/' . $template . '.' . $attempt . '.php'))
				template_include(LANGUAGES_DIR . '/' . $attempt . '/' . $template . '.' . $attempt . '.php', false, true);
			else
				continue;
			$found = true;
		}
		if ($reps === null)
		{
			loadSource('Subs-CachePHP');
			$reps = wedge_parse_mod_tags($rep_str, 'replace', array('from', 'regex', 'file'));
			foreach ($reps as $key => $val)
				$reps[$key]['file'] = array_flip(array_map('trim', explode(',', $val['file'])));
		}


		if (!$found)
		{

			$txt = !empty($txt) ? array_merge($oldtxt, $txt) : $oldtxt;

			if ($fatal)
			{
				log_error(sprintf($txt['theme_language_error'], $template . '.' . $lang), 'template');
				break;
			}
		}

		if ($found && !defined('WEDGE_INSTALLER'))
		{

			$request = wesql::query('
				SELECT lang_key, lang_string, serial
				FROM {db_prefix}language_changes
				WHERE id_lang = {string:lang}
					AND lang_file = {string:lang_file}',
				array(
					'lang' => $lang,
					'lang_file' => $template,
				)
			);
			$additions = array();
			while ($row = wesql::fetch_assoc($request))
			{


				if (!isset($additions[$row['lang_key']]))
				{
					$txt[$row['lang_key']] = !empty($row['serial']) ? @unserialize($row['lang_string']) : $row['lang_string'];
					$additions[$row['lang_key']] = true;
				}
			}
			wesql::free_result($request);


			foreach ($reps as $rep)
			{
				if (!empty($rep['file']) && !isset($rep['file'][$lang]))
					continue;
				$from = $rep['from'] ?: '"' . $rep['regex'] . '"s';
				$func = $rep['from'] ? 'str_replace' : 'preg_replace';
				foreach ($txt as $key => $val)
					$txt[$key] = $func($from, $rep['value'], $val);
			}


			$filename = CACHE_DIR . '/lang/' . $lang . '_' . $template . '.php';

			if (!empty($txt))
				$txt = array_map('westr::entity_to_utf8', $txt);
			$cache_data = '<' . '?php if(defined(\'WEDGE\'))$val=\'' . addcslashes(serialize($txt), '\\\'') . '\';?' . '>';
			if (file_put_contents($filename, $cache_data, LOCK_EX) !== strlen($cache_data))
				@unlink($filename);


			if (!empty($txt) || !empty($oldtxt))
				$txt = array_merge($oldtxt, $txt);
		}


		if ($found && !$fallback && $template === 'index')
		{
			we::$user['setlocale'] = setlocale(LC_TIME, $txt['lang_locale'] . '.utf-8', $txt['lang_locale'] . '.utf8');
			if (empty(we::$user['time_format']))
				we::$user['time_format'] = $txt['time_format'];
		}


		if (!empty($db_show_debug))
			$context['debug']['language_files'][] = $template . '.' . $lang;


		$already_loaded[$template] = $lang;
	}


	return $lang;
}







function loadSearchAPI($api)
{
	$file = APP_DIR . '/SearchAPI-' . ucwords($api) . '.php';
	if (!file_exists($file))
		return false;
	@include($file);
	return class_exists($api . '_search');
}










function getBoardParents($id_parent)
{
	$boards = array();


	if (($boards = cache_get_data('board_parents-' . $id_parent, 480)) === null)
	{
		$boards = array();
		$original_parent = $id_parent;


		while ($id_parent != 0)
		{
			$result = wesql::query('
				SELECT
					b.id_parent, b.name, {int:board_parent} AS id_board, IFNULL(mem.id_member, 0) AS id_moderator,
					mem.real_name, b.child_level
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				WHERE b.id_board = {int:board_parent}',
				array(
					'board_parent' => $id_parent,
				)
			);

			if (wesql::num_rows($result) == 0)
				fatal_lang_error('parent_not_found', 'critical');
			while ($row = wesql::fetch_assoc($result))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => '<URL>?board=' . $row['id_board'] . '.0',
						'name' => $row['name'],
						'level' => $row['child_level'],
						'moderators' => array()
					);
				}

				if (!empty($row['id_moderator']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderators'][$row['id_moderator']] = array(
							'id' => $row['id_moderator'],
							'name' => $row['real_name'],
							'href' => '<URL>?action=profile;u=' . $row['id_moderator'],
							'link' => '<a href="<URL>?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
						);
					}
			}
			wesql::free_result($result);
		}

		cache_put_data('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}







function getLanguages($use_cache = true)
{
	global $context, $settings;



	if ($use_cache && (isset($context['languages']) || ($context['languages'] = cache_get_data('known_languages', !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600)) !== null))
		return getAvailableLanguages();


	$language_directories = array(
		'root' => LANGUAGES_DIR,
	);
	$language_directories += (array) glob(LANGUAGES_DIR . '/*', GLOB_ONLYDIR);


	$context['languages'] = array();


	foreach ($language_directories as $language_dir)
	{

		if (!$language_dir || !file_exists($language_dir))
			continue;

		$dir = glob($language_dir . '/index.*.php');
		foreach ($dir as $entry)
		{
			if (!preg_match('~/index\.([^.]+)\.php$~', $entry, $matches))
				continue;

			$txt = array();
			@include($entry);
			$context['languages'][$matches[1]] = array(
				'name' => $txt['lang_name'],
				'code' => isset($txt['lang_hreflang']) ? $txt['lang_hreflang'] : (isset($txt['lang_dictionary']) ? $txt['lang_dictionary'] : ''),
				'filename' => $matches[1],
				'folder' => str_replace(LANGUAGES_DIR, '', $language_dir),
				'location' => $entry,
			);
		}
	}


	if (!empty($settings['cache_enable']))
		cache_put_data('known_languages', $context['languages'], !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600);

	return $use_cache ? getAvailableLanguages() : $context['languages'];
}

function getAvailableLanguages()
{
	global $settings, $context;

	$langs = !empty($settings['langsAvailable']) ? array_flip(explode(',', $settings['langsAvailable'])) : array();
	if (empty($langs))
		$langs[$settings['language']] = 1;
	foreach ($context['languages'] as $lang => $dummy)
		if (!isset($langs[$lang]))
			unset($context['languages'][$lang]);

	return $context['languages'];
}












function loadSession()
{
	global $settings, $sc;


	ini_set('session.use_cookies', 1);
	ini_set('session.use_only_cookies', 0);
	ini_set('session.cookie_httponly', 1);
	ini_set('session.use_trans_sid', 0);
	ini_set('arg_separator.output', '&amp;');
	ini_set('url_rewriter.tags', '');

	if (!empty($settings['globalCookies']))
	{
		$parsed_url = parse_url(ROOT);

		if (!preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) && preg_match('~(?:[^.]+\.)?([^.]{2,}\..+)\z~i', $parsed_url['host'], $parts))
			ini_set('session.cookie_domain', '.' . $parts[1]);
	}



	if ((ini_get('session.auto_start') == 1 && !empty($settings['databaseSession_enable'])) || session_id() == '')
	{

		if (ini_get('session.auto_start') == 1)
			@session_write_close();


		if (isset($_REQUEST[session_name()]) && preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $_REQUEST[session_name()]) == 0 && !isset($_COOKIE[session_name()]))
		{
			$session_id = md5(md5('we_sess_' . time()) . mt_rand());
			$_REQUEST[session_name()] = $session_id;
			$_GET[session_name()] = $session_id;
			$_POST[session_name()] = $session_id;
		}


		if (!empty($settings['databaseSession_enable']))
		{
			ini_set('session.serialize_handler', 'php');
			session_set_save_handler('sessionOpen', 'sessionClose', 'sessionRead', 'sessionWrite', 'sessionDestroy', 'sessionGC');
			ini_set('session.gc_probability', '1');
		}
		elseif (ini_get('session.gc_maxlifetime') <= 1440 && !empty($settings['databaseSession_lifetime']))
			ini_set('session.gc_maxlifetime', max($settings['databaseSession_lifetime'], 60));

		session_start();


		if (!empty($settings['databaseSession_loose']))
			header('Cache-Control: private');
	}


	if (!isset($_SESSION['session_var']))
	{
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
		$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', sha1(mt_rand() . session_id() . mt_rand())), 0, rand(7, 12));
	}
	$sc = $_SESSION['session_value'];
}








function sessionOpen($save_path, $session_name)
{
	return true;
}






function sessionClose()
{
	return true;
}







function sessionRead($session_id)
{
	if (preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id) == 0)
		return false;


	$result = wesql::query('
		SELECT data
		FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}
		LIMIT 1',
		array(
			'session_id' => $session_id,
		)
	);
	list ($sess_data) = wesql::fetch_row($result);
	wesql::free_result($result);

	return empty($sess_data) ? '' : $sess_data;
}








function sessionWrite($session_id, $data)
{


	if (!class_exists('wesql') || !preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id))
		return false;

	return wesql::insert('update',
		'{db_prefix}sessions',
		array('session_id' => 'string', 'data' => 'string', 'last_update' => 'int'),
		array($session_id, substr($data, 0, 65535), time())
	);
}







function sessionDestroy($session_id)
{
	global $db_link, $db_prefix;

	if (!preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id) || !isset($db_link))
		return false;


	mysqli_query($db_link, 'DELETE FROM ' . $db_prefix . 'sessions WHERE session_id = "' . mysqli_real_escape_string($db_link, $session_id) . '"');

	return true;
}






function sessionGC($max_lifetime)
{
	global $db_link, $db_prefix, $settings;


	if (!empty($settings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || $settings['databaseSession_lifetime'] > $max_lifetime))
		$max_lifetime = max($settings['databaseSession_lifetime'], 60);


	mysqli_query($db_link, 'DELETE FROM ' . $db_prefix . 'sessions WHERE last_update < ' . (int) (time() - $max_lifetime));

	if (mt_rand(1, 10) == 3 && mysqli_affected_rows($db_link) > 0)
		mysqli_query($db_link, 'OPTIMIZE TABLE ' . $db_prefix . 'sessions');

	return true;
}










function loadDatabase()
{
	global $db_persist, $db_server, $db_user, $db_passwd;
	global $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix;


	loadSource('Class-DB');
	wesql::getInstance();


	if (WEDGE == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$con = wesql::connect($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true));


	if (empty($con))
		$con = wesql::connect($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => WEDGE == 'SSI'));


	if (!$con)
		show_db_error();


	if (WEDGE == 'SSI')
		wesql::fix_prefix($db_prefix, $db_name);


	if (!mysqli_set_charset($con, 'utf8'))
		wesql::query('SET NAMES utf8');
}

function importing_cleanup()
{
	$result = wesql::query('
		SELECT id_member, buddy_list
		FROM {db_prefix}members
		WHERE buddy_list != {string:empty}
		LIMIT 20',
		array(
			'empty' => '',
		)
	);

	$users = array();
	while ($row = wesql::fetch_assoc($result))
	{
		$contacts = explode(',', $row['buddy_list']);
		$users[] = $row['id_member'];

		wesql::insert('ignore',
			'{db_prefix}contact_lists',
			array('id_owner' => 'int', 'added' => 'int'),
			array($row['id_member'], time())
		);
		$cid = wesql::insert_id();

		foreach ($contacts as $contact)
			if ((int) $contact > 0)
				wesql::insert('ignore',
					'{db_prefix}contacts',
					array('id_member' => 'int', 'id_owner' => 'int', 'id_list' => 'int', 'added' => 'int'),
					array($contact, $row['id_member'], $cid, time())
				);
	}
	wesql::free_result($result);


	if (count($users) == 0)
	{
		wesql::insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('imported_cleaned', 1)
		);
		cache_put_data('settings', null, 'forever');
	}
	else
		wesql::query('
			UPDATE {db_prefix}members
			SET buddy_list = {string:empty}
			WHERE id_member IN ({array_int:users})',
			array(
				'empty' => '',
				'users' => $users,
			)
		);
}
