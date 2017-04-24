<?php










if (!defined('WEDGE'))
	die('Hacking attempt...');
















































































function PersonalMessage()
{
	global $txt, $context, $user_settings, $settings;


	is_not_guest();


	isAllowedTo('pm_read');


	loadSource('Subs-Post');


	if (isset($_REQUEST['draft']))
	{
		$session_timeout = checkSession('post', '', false) != '';
		$draft = saveDraft(true, isset($_REQUEST['replied_to']) ? (int) $_REQUEST['replied_to'] : 0);
		if (!empty($draft) && !$session_timeout)
		{
			if (!AJAX)
				redirectexit('action=pm;draftsaved');

			loadLanguage('Post');
			draftXmlReturn($draft, true);
		}
	}

	loadLanguage('PersonalMessage');
	loadTemplate('PersonalMessage');
	loadTemplate('Msg');


	if (!empty($user_settings['hey_pm']))
	{
		cache_put_data('labelCounts:' . MID, null, 720);
		$context['labels'] = empty(we::$user['data']['pmlabs']) ? array() : explode(',', we::$user['data']['pmlabs']);
		foreach ($context['labels'] as $id_label => $label_name)
			$context['labels'][(int) $id_label] = array(
				'id' => $id_label,
				'name' => trim($label_name),
				'messages' => 0,
				'unread_messages' => 0,
			);
		$context['labels'][-1] = array(
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		);

		applyRules();
		updateMemberData(MID, array('hey_pm' => 0));
		wesql::query('
			UPDATE {db_prefix}pm_recipients
			SET is_new = 0
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => MID,
			)
		);
	}


	if (AJAX && isset($_GET['sa']) && $_GET['sa'] == 'ajax')
	{
		if (!isset($_GET['preview']))
		{

			$context['can_send'] = allowedTo('pm_send');
			$context['show_drafts'] = $context['can_send'] && allowedTo('save_pm_draft') && !empty($settings['masterSavePmDrafts']);
			wetem::hide();
			wetem::load('pm_popup');


			$request = wesql::query('
				SELECT id_pm
				FROM {db_prefix}pm_recipients
				WHERE id_member = {int:current_member}
					AND is_read = {int:new}
				ORDER BY id_pm DESC',
				array(
					'current_member' => MID,
					'new' => 0,
				)
			);
			$context['personal_messages'] = array();
			while ($row = wesql::fetch_row($request))
				$context['personal_messages'][$row[0]] = array();
			wesql::free_result($request);


			if (count($context['personal_messages']) != we::$user['unread_messages'])
				updateMemberData(
					MID,
					array(
						'unread_messages' => count($context['personal_messages']),
					)
				);


			if (!empty($context['personal_messages']))
			{
				$senders = array();

				$request = wesql::query('
					SELECT pm.id_pm, pm.id_pm_head, IFNULL(mem.id_member, pm.id_member_from) AS id_member_from,
						IFNULL(mem.real_name, pm.from_name) AS member_from, pm.msgtime, pm.subject
					FROM {db_prefix}personal_messages AS pm
						LEFT JOIN {db_prefix}members AS mem ON (pm.id_member_from = mem.id_member)
					WHERE pm.id_pm IN ({array_int:id_pms})',
					array(
						'id_pms' => array_keys($context['personal_messages']),
					)
				);
				while ($row = wesql::fetch_assoc($request))
				{
					if (!empty($row['id_member_from']))
						$senders[] = $row['id_member_from'];
					$row['sprintf'] = $row['id_pm'] == $row['id_pm_head'] ? 'pm_sent_to_you' : 'pm_replied_to_pm';
					$row['member_link'] = !empty($row['id_member_from']) ? '<a href="<URL>?action=profile;u=' . $row['id_member_from'] . '">' . $row['member_from'] . '</a>' : $row['member_from'];
					$row['msg_link'] = '<a href="<URL>?action=pm;f=inbox;pmsg=' . $row['id_pm'] . '">' . $row['subject'] . '</a>';
					$context['personal_messages'][$row['id_pm']] = $row;
				}
				wesql::free_result($request);

				if (!empty($senders))
				{
					$loaded = loadMemberData($senders);
					foreach ($loaded as $member)
						loadMemberAvatar($member, true);
				}
			}

			return;
		}
		else
		{

			$pmsg = (int) $_GET['preview'];
			if (!isAccessiblePM($pmsg, isset($_REQUEST['f']) && $_REQUEST['f'] == 'sent' ? 'outbox' : 'inbox'))
			{
				mark_ajax_pm_read($pmsg);
				return_raw($context['header'] . $txt['pm_not_found']);
			}
			$request = wesql::query('
				SELECT body
				FROM {db_prefix}personal_messages
				WHERE id_pm = {int:pm}',
				array(
					'pm' => $pmsg,
				)
			);
			list ($body) = wesql::fetch_row($request);
			wesql::free_result($request);

			return_raw($context['header'] . parse_bbc($body, 'pm', array('cache' => 'pm' . $pmsg)));
		}
	}


	if (we::$is_admin)
		$context['message_limit'] = 0;
	elseif (($context['message_limit'] = cache_get_data('msgLimit:' . MID, 360)) === null)
	{

		$request = wesql::query('
			SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:users_groups})',
			array(
				'users_groups' => we::$user['groups'],
			)
		);
		list ($maxMessage, $minMessage) = wesql::fetch_row($request);
		wesql::free_result($request);

		$context['message_limit'] = $minMessage == 0 ? 0 : $maxMessage;


		cache_put_data('msgLimit:' . MID, $context['message_limit'], 360);
	}


	if (!empty($context['message_limit']))
	{
		$bar = (we::$user['messages'] * 100) / $context['message_limit'];

		$context['limit_bar'] = array(
			'messages' => we::$user['messages'],
			'allowed' => $context['message_limit'],
			'percent' => $bar,
			'bar' => min(100, (int) $bar),
			'text' => sprintf($txt['pm_currently_using'], we::$user['messages'], round($bar, 1)),
		);
	}


	if (isset($_GET['done']) && ($_GET['done'] == 'sent'))
		$context['pm_sent'] = true;


	if ($context['draft_saved'] = isset($_GET['draftsaved']))
		loadLanguage('Post');


	$context['labels'] = cache_get_data('labelCounts:' . MID, 720, function ()
	{
		global $txt;

		$labels = empty(we::$user['data']['pmlabs']) ? array() : explode(',', we::$user['data']['pmlabs']);
		foreach ($labels as $id_label => $label_name)
			$labels[(int) $id_label] = array(
				'id' => $id_label,
				'name' => trim($label_name),
				'messages' => 0,
				'unread_messages' => 0,
			);
		$labels[-1] = array(
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		);


		$result = wesql::query('
			SELECT labels, is_read, COUNT(*) AS num
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:current_member}
				AND deleted = {int:not_deleted}
			GROUP BY labels, is_read',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($result))
		{
			$this_labels = explode(',', $row['labels']);
			foreach ($this_labels as $this_label)
			{
				$labels[(int) $this_label]['messages'] += $row['num'];
				if (!($row['is_read'] & 1))
					$labels[(int) $this_label]['unread_messages'] += $row['num'];
			}
		}
		wesql::free_result($result);


		return $labels;
	});


	$context['currently_using_labels'] = count($context['labels']) > 1 ? 1 : 0;


	$context['current_label_id'] = isset($_REQUEST['l'], $context['labels'][(int) $_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;
	$context['current_label'] =& $context['labels'][(int) $context['current_label_id']]['name'];
	$context['folder'] = !isset($_REQUEST['f']) || $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent';


	$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($_GET['start']) ? ';start=' . $_GET['start'] : '') . (isset($_REQUEST['l']) ? ';l=' . $_REQUEST['l'] : '');
	$context['can_issue_warning'] = allowedTo('issue_warning');


	add_linktree($txt['personal_messages'], '<URL>?action=pm');


	$context['display_mode'] = $user_settings['pm_prefs'] & 3;
	if ($context['folder'] == 'sent')
		$context['display_mode'] = 0;

	$subActions = array(
		'manlabels' => 'ManageLabels',
		'manrules' => 'ManageRules',
		'markunread' => 'MarkUnread',
		'pmactions' => 'MessageActionsApply',
		'prune' => 'MessagePrune',
		'removeall' => 'MessageKillAllQuery',
		'removeall2' => 'MessageKillAll',
		'report' => 'ReportMessage',
		'search' => 'MessageSearch',
		'search2' => 'MessageSearch2',
		'send' => 'MessagePost',
		'send2' => 'MessagePost2',
		'settings' => 'MessageSettings',
	);

	if (allowedTo('save_pm_draft'))
		$subActions['showdrafts'] = 'MessageDrafts';

	if (isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
	{
		messageIndexBar($_REQUEST['sa']);
		$subActions[$_REQUEST['sa']]();
	}
	else
	{

		unset($_REQUEST['sa']);
		MessageFolder();
	}
}


function mark_ajax_pm_read($pmsg)
{
	wesql::query('
		UPDATE {db_prefix}pm_recipients
		SET is_read = 1
		WHERE id_pm = {int:pm}
			AND id_member = {int:member}
			AND is_read = 0',
		array(
			'pm' => $pmsg,
			'member' => MID,
		)
	);
	$request = wesql::query('
		SELECT COUNT(id_pm)
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:current_member}
			AND is_read = {int:new}
		ORDER BY id_pm',
		array(
			'current_member' => MID,
			'new' => 0,
		)
	);
	list ($count) = wesql::fetch_row($request);
	wesql::free_result($request);

	updateMemberData(MID, array('unread_messages' => $count));


	cache_put_data('labelCounts:' . MID, null);
}


function messageIndexBar($area)
{
	global $txt, $context;

	$pm_areas = array(
		'folders' => array(
			'title' => $txt['pm_messages'],
			'areas' => array(
				'send' => array(
					'label' => $txt['new_message'],
					'custom_url' => '<URL>?action=pm;sa=send',
					'permission' => allowedTo('pm_send'),
				),
				'',
				'inbox' => array(
					'label' => $txt['inbox'],
					'notice' => '',
					'custom_url' => '<URL>?action=pm',
				),
				'sent' => array(
					'label' => $txt['sent_items'],
					'custom_url' => '<URL>?action=pm;f=sent',
				),
				'',
				'showdrafts' => array(
					'label' => $txt['pm_menu_drafts'],
					'notice' => '',
					'custom_url' => '<URL>?action=pm;sa=showdrafts',
					'permission' => allowedTo('save_pm_draft'),
				),
			),
		),
		'labels' => array(
			'title' => $txt['pm_labels'],
			'areas' => array(),
		),
		'actions' => array(
			'title' => $txt['pm_actions'],
			'areas' => array(
				'search' => array(
					'label' => $txt['pm_search_bar_title'],
					'custom_url' => '<URL>?action=pm;sa=search',
				),
				'prune' => array(
					'label' => $txt['pm_prune'],
					'custom_url' => '<URL>?action=pm;sa=prune'
				),
			),
		),
		'pref' => array(
			'title' => $txt['pm_preferences'],
			'areas' => array(
				'manlabels' => array(
					'label' => $txt['pm_manage_labels'],
					'custom_url' => '<URL>?action=pm;sa=manlabels',
				),
				'manrules' => array(
					'label' => $txt['pm_manage_rules'],
					'custom_url' => '<URL>?action=pm;sa=manrules',
				),
				'',
				'settings' => array(
					'label' => $txt['pm_settings'],
					'custom_url' => '<URL>?action=pm;sa=settings',
				),
			),
		),
	);


	if (empty($context['currently_using_labels']))
		unset($pm_areas['labels']);
	else
	{

		$unread_in_labels = 0;
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;


			$unread_in_labels += $label['unread_messages'];


			$pm_areas['labels']['areas']['label' . $label['id']] = array(
				'label' => $label['name'] . (!empty($label['unread_messages']) ? ' <span class="note">' . $label['unread_messages'] . '</span>' : ''),
				'custom_url' => '<URL>?action=pm;l=' . $label['id'],
				'unread_messages' => $label['unread_messages'],
				'messages' => $label['messages'],
			);
		}

		if (!empty($unread_in_labels))
			$pm_areas['labels']['title'] .= ' <span class="notewarn">' . $unread_in_labels . '</span>';
	}

	$pm_areas['folders']['areas']['inbox']['unread_messages'] =& $context['labels'][-1]['unread_messages'];
	$pm_areas['folders']['areas']['inbox']['messages'] =& $context['labels'][-1]['messages'];
	if (!empty($context['labels'][-1]['unread_messages']))
	{
		$pm_areas['folders']['areas']['inbox']['label'] .= ' <span class="note">' . $context['labels'][-1]['unread_messages'] . '</span>';
		$pm_areas['folders']['title'] .= ' <span class="notewarn">' . $context['labels'][-1]['unread_messages'] . '</span>';
	}


	if (!empty($context['message_limit']))
	{
		$bar = round((we::$user['messages'] * 100) / $context['message_limit'], 1);

		$context['limit_bar'] = array(
			'messages' => we::$user['messages'],
			'allowed' => $context['message_limit'],
			'percent' => $bar,
			'bar' => $bar > 100 ? 100 : (int) $bar,
			'text' => sprintf($txt['pm_currently_using'], we::$user['messages'], $bar)
		);
	}

	loadSource('Subs-Menu');


	$menuOptions = array(
		'current_area' => $area,
		'disable_url_session_check' => true,
	);


	$pm_include_data = createMenu($pm_areas, $menuOptions);
	unset($pm_areas);


	$context['pm_menu_id'] = $context['max_menu_id'];
	$context['pm_menu_name'] = 'menu_data_' . $context['pm_menu_id'];


	$context['menu_item_selected'] = $pm_include_data['current_area'];


	wetem::outer('pm');
}


function MessageFolder()
{
	global $txt, $settings, $context, $subjects_request;
	global $messages_request, $recipients, $options, $user_settings;


	if (isset($_GET['view']))
	{
		$view = (int) $_GET['view'];
		if ($view >= 0 && $view <= 2)
		{
			$context['display_mode'] = $view;
			updateMemberData(MID, array('pm_prefs' => ($user_settings['pm_prefs'] & 252) | $context['display_mode']));
		}
	}
	$context['view_select_types'] = array(
		0 => $txt['pm_display_mode_all'],
		1 => $txt['pm_display_mode_one'],
		2 => $txt['pm_display_mode_linked'],
	);


	$context['can_unread'] = $context['display_mode'] != 0;


	if (isset($_GET['start']) && $_GET['start'] != 'new')
		$_GET['start'] = (int) $_GET['start'];
	elseif (!isset($_GET['start']) && !empty($options['view_newest_pm_first']))
		$_GET['start'] = 0;
	else
		$_GET['start'] = 'new';


	$context['from_or_to'] = $context['folder'] != 'sent' ? 'from' : 'to';
	$context['get_pmessage'] = 'prepareMessageContext';
	$context['signature_enabled'] = $settings['signature_settings'][0] == 1;
	$context['disabled_fields'] = isset($settings['disabled_profile_fields']) ? array_flip(explode(',', $settings['disabled_profile_fields'])) : array();

	$labelQuery = $context['folder'] != 'sent' ? '
			AND FIND_IN_SET(' . $context['current_label_id'] . ', pmr.labels) != 0' : '';


	messageIndexBar($context['current_label_id'] == -1 ? $context['folder'] : 'label' . $context['current_label_id']);


	$sort_methods = array(
		'date' => 'pm.id_pm',
		'name' => 'IFNULL(mem.real_name, \'\')',
		'subject' => 'pm.subject',
	);


	if (!isset($_GET['sort']) || !isset($sort_methods[$_GET['sort']]))
	{
		$context['sort_by'] = 'date';
		$_GET['sort'] = 'pm.id_pm';

		$descending = !empty($options['view_newest_pm_first']);
	}

	else
	{
		$context['sort_by'] = $_GET['sort'];
		$_GET['sort'] = $sort_methods[$_GET['sort']];
		$descending = isset($_GET['desc']);
	}

	$context['sort_direction'] = $descending ? 'down' : 'up';


	$pmbox = $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'];
	$txt['delete_all'] = str_replace('PMBOX', $pmbox, $txt['delete_all']);


	if ($context['current_label_id'] == -1)
		add_linktree($pmbox, '<URL>?action=pm;f=' . $context['folder']);


	if ($context['current_label_id'] != -1)
		add_linktree($txt['pm_current_label'] . ': ' . $context['current_label'], '<URL>?action=pm;f=' . $context['folder'] . ';l=' . $context['current_label_id']);


	if ($context['folder'] == 'sent')
		$request = wesql::query('
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
			FROM {db_prefix}personal_messages AS pm
			WHERE pm.id_member_from = {int:current_member}
				AND pm.deleted_by_sender = {int:not_deleted}',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
			)
		);
	else
		$request = wesql::query('
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
			FROM {db_prefix}pm_recipients AS pmr' . ($context['display_mode'] == 2 ? '
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' : '') . '
			WHERE pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}' . $labelQuery,
			array(
				'current_member' => MID,
				'not_deleted' => 0,
			)
		);
	list ($max_messages) = wesql::fetch_row($request);
	wesql::free_result($request);


	if ($context['folder'] == 'sent' && $max_messages == 0)
		isAllowedTo('pm_send');


	$context['show_delete'] = $max_messages > 0;


	if (!is_numeric($_GET['start']) || $_GET['start'] >= $max_messages)
		$_GET['start'] = ($max_messages - 1) - (($max_messages - 1) % $settings['defaultMaxMessages']);
	elseif ($_GET['start'] < 0)
		$_GET['start'] = 0;


	if (isset($_GET['pmid']))
	{
		$pmID = (int) $_GET['pmid'];


		if (!isAccessiblePM($pmID, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
			fatal_lang_error('no_access', false);

		$context['current_pm'] = $pmID;


		if ($max_messages <= $settings['defaultMaxMessages'])
			$_GET['start'] = 0;


		elseif (!isset($_GET['kstart']))
		{
			if ($context['folder'] == 'sent')
				$request = wesql::query('
					SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
					FROM {db_prefix}personal_messages
					WHERE id_member_from = {int:current_member}
						AND deleted_by_sender = {int:not_deleted}
						AND id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}',
					array(
						'current_member' => MID,
						'not_deleted' => 0,
						'id_pm' => $pmID,
					)
				);
			else
				$request = wesql::query('
					SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
					FROM {db_prefix}pm_recipients AS pmr' . ($context['display_mode'] == 2 ? '
						INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' : '') . '
					WHERE pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}' . $labelQuery . '
						AND pmr.id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}',
					array(
						'current_member' => MID,
						'not_deleted' => 0,
						'id_pm' => $pmID,
					)
				);

			list ($_GET['start']) = wesql::fetch_row($request);
			wesql::free_result($request);


			$_GET['start'] = $settings['defaultMaxMessages'] * (int) ($_GET['start'] / $settings['defaultMaxMessages']);
		}
	}


	if (isset($_GET['pmsg']))
	{
		$pmsg = (int) $_GET['pmsg'];

		if (!isAccessiblePM($pmsg, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
			fatal_lang_error('no_access', false);
	}


	$context['page_index'] = template_page_index('<URL>?action=pm;f=' . $context['folder'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $_GET['start'], $max_messages, $settings['defaultMaxMessages']);
	$context['start'] = $_GET['start'];


	$context['links'] = array(
		'prev' => $_GET['start'] >= $settings['defaultMaxMessages'] ? '<URL>?action=pm;start=' . ($_GET['start'] - $settings['defaultMaxMessages']) : '',
		'next' => $_GET['start'] + $settings['defaultMaxMessages'] < $max_messages ? '<URL>?action=pm;start=' . ($_GET['start'] + $settings['defaultMaxMessages']) : '',
	);


	if ($context['display_mode'] == 2)
	{
		$request = wesql::query('
			SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:deleted_by}
					' . $labelQuery . ')') . ($context['sort_by'] == 'name' ? ('
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
			WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
				AND pm.deleted_by_sender = {int:deleted_by}' : '1=1') . (empty($pmsg) ? '' : '
				AND pm.id_pm = {int:pmsg}') . '
			GROUP BY pm.id_pm_head
			ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' && $context['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($_GET['pmsg']) ? '
			LIMIT ' . $_GET['start'] . ', ' . $settings['defaultMaxMessages'] : ''),
			array(
				'current_member' => MID,
				'deleted_by' => 0,
				'sort' => $_GET['sort'],
				'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			)
		);
	}

	else
	{

		$request = wesql::query('
			SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' . ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:is_deleted}
					' . $labelQuery . ')') . ($context['sort_by'] == 'name' ? ('
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
			WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {raw:current_member}
				AND pm.deleted_by_sender = {int:is_deleted}' : '1=1') . (empty($pmsg) ? '' : '
				AND pm.id_pm = {int:pmsg}') . '
			ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' && $context['folder'] != 'sent' ? 'pmr.id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($pmsg) ? '
			LIMIT ' . $_GET['start'] . ', ' . $settings['defaultMaxMessages'] : ''),
			array(
				'current_member' => MID,
				'is_deleted' => 0,
				'sort' => $_GET['sort'],
				'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			)
		);
	}

	$pms = array();
	$lastData = array();
	$posters = $context['folder'] == 'sent' ? array(MID) : array();
	$recipients = array();

	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($recipients[$row['id_pm']]))
		{
			if (isset($row['id_member_from']))
				$posters[$row['id_pm']] = $row['id_member_from'];
			$pms[$row['id_pm']] = $row['id_pm'];
			$recipients[$row['id_pm']] = array(
				'to' => array(),
				'bcc' => array()
			);
		}


		if ((empty($pmID) && (empty($options['view_newest_pm_first']) || !isset($lastData))) || empty($lastData) || (!empty($pmID) && $pmID == $row['id_pm']))
			$lastData = array(
				'id' => $row['id_pm'],
				'head' => $row['id_pm_head'],
			);
	}
	wesql::free_result($request);


	if ($context['display_mode'] == 2 && !empty($pmID) && $pmID != $lastData['id'])
		fatal_lang_error('no_access', false);

	if (!empty($pms))
	{

		if (empty($pmID))
			$context['current_pm'] = $lastData['id'];


		if ($context['display_mode'] == 0)
			$display_pms = $pms;
		else
			$display_pms = array($context['current_pm']);


		if ($context['display_mode'] == 2)
		{
			$request = wesql::query('
				SELECT pm.id_pm, pm.id_member_from, pm.deleted_by_sender, pmr.id_member, pmr.deleted
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head = {int:id_pm_head}
					AND ((pm.id_member_from = {int:current_member} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:current_member} AND pmr.deleted = {int:not_deleted}))
				ORDER BY pm.id_pm',
				array(
					'current_member' => MID,
					'id_pm_head' => $lastData['head'],
					'not_deleted' => 0,
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{

				if ($context['folder'] == 'sent' && $row['id_member_from'] == MID && $row['deleted_by_sender'] == 1)
					continue;
				elseif ($row['id_member'] == MID & $row['deleted'] == 1)
					continue;

				if (!isset($recipients[$row['id_pm']]))
					$recipients[$row['id_pm']] = array(
						'to' => array(),
						'bcc' => array()
					);
				$display_pms[] = $row['id_pm'];
				$posters[$row['id_pm']] = $row['id_member_from'];
			}
			wesql::free_result($request);
		}


		$all_pms = array_merge($pms, $display_pms);
		$all_pms = array_unique($all_pms);


		$request = wesql::query('
			SELECT pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc, pmr.labels, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $all_pms,
			)
		);
		$context['message_labels'] = array();
		$context['message_unread'] = array();
		$context['message_replied'] = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$id =& $row['id_pm'];
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$id][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="<URL>?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($context['folder'] == 'sent' && isset($posters[$id]) && $posters[$id] == MID)
				$context['message_replied'][$id] = (isset($context['message_replied'][$id]) ? $context['message_replied'][$id] : 0) + (($row['is_read'] & 2) >> 1);
			elseif (MID == $row['id_member_to'])
			{
				$context['message_replied'][$id] = $row['is_read'] & 2;
				$context['message_unread'][$id] = ($row['is_read'] & 1) == 0;
				$context['message_can_unread'][$id] = $context['can_unread'] && (!$context['message_unread'][$id] || (!empty($pmID) && $pmID == $id));

				$row['labels'] = $row['labels'] == '' ? array() : explode(',', $row['labels']);
				foreach ($row['labels'] as $v)
				{
					if (isset($context['labels'][(int) $v]))
						$context['message_labels'][$id][(int) $v] = array('id' => $v, 'name' => $context['labels'][(int) $v]['name']);
				}
			}
		}
		wesql::free_result($request);


		if ($context['display_mode'] == 1)
			foreach ($posters as $k => $v)
				if (!in_array($k, $display_pms))
					unset($posters[$k]);


		$posters = array_unique($posters);
		if (!empty($posters))
			loadMemberData($posters, false, 'userbox');


		if ($context['display_mode'] != 0)
		{

			$orderBy = array();
			foreach (array_reverse($pms) as $pm)
				$orderBy[] = 'pm.id_pm = ' . $pm;


			$subjects_request = wesql::query('
				SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.msgtime, IFNULL(mem.real_name, pm.from_name) AS from_name,
					IFNULL(mem.id_member, 0) AS not_guest
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_pm IN ({array_int:pm_list})
				ORDER BY ' . implode(', ', $orderBy) . '
				LIMIT ' . count($pms),
				array(
					'pm_list' => $pms,
				)
			);
		}


		$messages_request = wesql::query('
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') . ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})' : '') . '
			WHERE pm.id_pm IN ({array_int:display_pms})' . ($context['folder'] == 'sent' ? '
			GROUP BY pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name' : '') . '
			ORDER BY ' . ($context['display_mode'] == 2 ? 'pm.id_pm' : $_GET['sort']) . ($descending ? ' DESC' : ' ASC') . '
			LIMIT ' . count($display_pms),
			array(
				'display_pms' => $display_pms,
				'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
			)
		);
	}
	else
		$messages_request = false;

	$context['can_send_pm'] = allowedTo('pm_send');
	if ($context['display_mode'] == 0)
		$context['page_title'] = $txt['pm_inbox'];
	else
		$context['page_title'] = '@replace:pm_convo@';

	wetem::load('folder');


	if ($context['folder'] != 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
	{

		if ($context['display_mode'] == 0)
			markMessages(null, $context['current_label_id']);

		elseif (!empty($context['current_pm']))
			markMessages($display_pms, $context['current_label_id']);
	}
}


function prepareMessageContext($type = 'subject', $reset = false)
{
	global $txt, $settings, $context, $messages_request, $memberContext, $recipients;
	global $subjects_request;


	static $counter = null, $last_subject = '', $temp_pm_selected = null;
	if ($counter === null || $reset)
		$counter = $context['start'];

	if ($temp_pm_selected === null)
	{
		$temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : array();
		$_SESSION['pm_selected'] = array();
	}


	if ($context['display_mode'] != 0 && $subjects_request && $type == 'subject')
	{
		$subject = wesql::fetch_assoc($subjects_request);
		if (!$subject)
		{
			wesql::free_result($subjects_request);
			return false;
		}

		$subject['subject'] = $subject['subject'] === '' ? $txt['no_subject'] : $subject['subject'];
		censorText($subject['subject']);

		$output = array(
			'id' => $subject['id_pm'],
			'member' => array(
				'id' => $subject['id_member_from'],
				'name' => $subject['from_name'],
				'link' => $subject['not_guest'] ? '<a href="<URL>?action=profile;u=' . $subject['id_member_from'] . '">' . $subject['from_name'] . '</a>' : $subject['from_name'],
			),
			'recipients' => &$recipients[$subject['id_pm']],
			'subject' => $subject['subject'],
			'on_time' => on_timeformat($subject['msgtime']),
			'timestamp' => $subject['msgtime'],
			'number_recipients' => count($recipients[$subject['id_pm']]['to']),
			'labels' => &$context['message_labels'][$subject['id_pm']],
			'fully_labeled' => count($context['message_labels'][$subject['id_pm']]) == count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$subject['id_pm']],
			'is_unread' => &$context['message_unread'][$subject['id_pm']],
			'is_selected' => !empty($temp_pm_selected) && in_array($subject['id_pm'], $temp_pm_selected),
		);
		if ($output['is_replied_to'])
			$output['replied_msg'] = number_context('pm_is_replied_to_sent', $output['is_replied_to'], false);

		return $output;
	}


	if ($messages_request == false)
	{
		if ($context['display_mode'] != 0)
			add_replacement('@replace:pm_convo@', $txt['conversation']);
		return false;
	}


	if ($reset == true)
		return @wesql::data_seek($messages_request, 0);


	$message = wesql::fetch_assoc($messages_request);
	if (!$message)
	{
		if ($type != 'subject')
			wesql::free_result($messages_request);

		if ($context['display_mode'] != 0)
			add_replacement('@replace:pm_convo@', $txt['conversation'] . ' - ' . $last_subject);
		return false;
	}


	$message['subject'] = $message['subject'] === '' ? $txt['no_subject'] : $message['subject'];


	if (!loadMemberContext($message['id_member_from'], true))
	{
		$memberContext[$message['id_member_from']]['name'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['id'] = 0;

		$memberContext[$message['id_member_from']]['group'] = $message['from_name'] == $context['forum_name'] ? '' : $txt['guest_title'];
		$memberContext[$message['id_member_from']]['link'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['email'] = '';
		$memberContext[$message['id_member_from']]['show_email'] = showEmailAddress(true, 0);
		$memberContext[$message['id_member_from']]['is_guest'] = true;
	}
	else
	{
		$memberContext[$message['id_member_from']]['can_view_profile'] = allowedTo('profile_view_any') || ($message['id_member_from'] == MID && allowedTo('profile_view_own'));
		$memberContext[$message['id_member_from']]['can_see_warning'] = !empty($settings['warning_show']) && $memberContext[$message['id_member_from']]['warning_status'] && ($settings['warning_show'] == 3 || allowedTo('issue_warning') || ($settings['warning_show'] == 2 && $message['id_member_from'] == MID));
	}


	censorText($message['body']);
	censorText($message['subject']);
	if (!empty($message['subject']))
		$last_subject = $message['subject'];


	$message['body'] = parse_bbc($message['body'], 'pm', array('cache' => 'pm' . $message['id_pm']));


	$output = array(
		'alternate' => $counter % 2,
		'id' => $message['id_pm'],
		'member' => &$memberContext[$message['id_member_from']],
		'subject' => $message['subject'],
		'on_time' => on_timeformat($message['msgtime']),
		'timestamp' => $message['msgtime'],
		'counter' => $counter,
		'body' => $message['body'],
		'recipients' => &$recipients[$message['id_pm']],
		'number_recipients' => count($recipients[$message['id_pm']]['to']),
		'labels' => &$context['message_labels'][$message['id_pm']],
		'fully_labeled' => count($context['message_labels'][$message['id_pm']]) == count($context['labels']),
		'is_replied_to' => &$context['message_replied'][$message['id_pm']],
		'is_unread' => &$context['message_unread'][$message['id_pm']],
		'is_selected' => !empty($temp_pm_selected) && in_array($message['id_pm'], $temp_pm_selected),
	);
	if ($output['is_replied_to'])
		$output['replied_msg'] = number_context('pm_is_replied_to_sent', $output['is_replied_to'], false);

	$counter++;

	return $output;
}

function MarkUnread()
{
	checkSession('get');

	$id_pm = isset($_GET['pmid']) ? (int) $_GET['pmid'] : 0;
	if (empty($id_pm))
		redirectexit('action=pm');



	wesql::query('
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read & 254
		WHERE id_member = {int:id_member}
			AND id_pm = {int:id_pm}
			AND is_read & 1 = 1',
		array(
			'id_pm' => $id_pm,
			'id_member' => MID,
		)
	);


	if (wesql::affected_rows() > 0)
		recalculateUnread(MID);

	redirectexit('action=pm');
}

function MessageSearch()
{
	global $context, $txt;

	loadLanguage('Search');

	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$context['search_params'][$k] = $v;
		}
	}
	if (isset($_REQUEST['search']))
		$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);

	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec']);

	if (!empty($context['search_params']['searchtype']))
		$context['search_params']['searchtype'] = 2;

	if (!empty($context['search_params']['minage']))
		$context['search_params']['minage'] = (int) $context['search_params']['minage'];

	if (!empty($context['search_params']['maxage']))
		$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

	$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);
	$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);


	$context['search_labels'] = array();
	$searchedLabels = isset($context['search_params']['labels']) && $context['search_params']['labels'] != '' ? explode(',', $context['search_params']['labels']) : array();
	foreach ($context['labels'] as $label)
	{
		$context['search_labels'][] = array(
			'id' => $label['id'],
			'name' => $label['name'],
			'checked' => !empty($searchedLabels) ? in_array($label['id'], $searchedLabels) : true,
		);
	}


	$context['check_all'] = empty($searchedLabels) || count($context['search_labels']) == count($searchedLabels);


	if (!empty($context['search_errors']))
	{
		loadLanguage('Errors');
		$context['search_errors']['messages'] = array();
		foreach ($context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error == 'messages')
				continue;

			$context['search_errors']['messages'][] = $txt['error_' . $search_error];
		}
	}

	$context['page_title'] = $txt['pm_search_title'];
	wetem::load('search');
	add_linktree($txt['pm_search_bar_title'], '<URL>?action=pm;sa=search');
}

function MessageSearch2()
{
	global $settings, $context, $txt, $memberContext;

	if (!empty($context['load_average']) && !empty($settings['loadavg_search']) && $context['load_average'] >= $settings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	loadLanguage('Search');


	$context['folder'] = 'inbox';


	$context['can_send_pm'] = allowedTo('pm_send');


	$maxMembersToSearch = 500;


	$search_params = array();
	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$search_params[$k] = $v;
		}
	}

	$context['start'] = isset($_GET['start']) ? (int) $_GET['start'] : 0;


	if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
		$search_params['searchtype'] = 2;


	if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
		$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];


	if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] != 9999))
		$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);
	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);


	if (!empty($search_params['user_spec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];


	$searchq_parameters = array();


	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr(westr::htmlspecialchars($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++)
		{
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0)
				unset($possible_users[$k]);
		}



		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE real_name LIKE {raw:real_name_implode}',
			array(
				'real_name_implode' => '\'' . implode('\' OR real_name LIKE \'', $possible_users) . '\'',
			)
		);

		if (wesql::num_rows($request) > $maxMembersToSearch)
			$userQuery = '';
		elseif (wesql::num_rows($request) == 0)
		{
			$userQuery = 'AND pm.id_member_from = 0 AND (pm.from_name LIKE {raw:guest_user_name_implode})';
			$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR pm.from_name LIKE \'', $possible_users) . '\'';
		}
		else
		{
			$memberlist = array();
			while ($row = wesql::fetch_assoc($request))
				$memberlist[] = $row['id_member'];
			$userQuery = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND (pm.from_name LIKE {raw:guest_user_name_implode})))';
			$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR pm.from_name LIKE \'', $possible_users) . '\'';
			$searchq_parameters['member_list'] = $memberlist;
		}
		wesql::free_result($request);
	}



	$sort_columns = array(
		'pm.id_pm',
	);
	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'pm.id_pm';
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';


	$labelQuery = '';
	if ($context['folder'] == 'inbox' && $context['currently_using_labels'])
	{

		if (isset($search_params['labels']))
			$_REQUEST['searchlabel'] = explode(',', $search_params['labels']);


		if (!empty($_REQUEST['searchlabel']) && is_array($_REQUEST['searchlabel']))
		{
			foreach ($_REQUEST['searchlabel'] as $key => $id)
				$_REQUEST['searchlabel'][$key] = (int) $id;
		}
		else
			$_REQUEST['searchlabel'] = array();


		$search_params['labels'] = implode(',', $_REQUEST['searchlabel']);


		if (empty($_REQUEST['searchlabel']))
			$context['search_errors']['no_labels_selected'] = true;

		elseif (count($_REQUEST['searchlabel']) != count($context['labels']))
		{
			$labelQuery = '
			AND {raw:label_implode}';

			$labelStatements = array();
			foreach ($_REQUEST['searchlabel'] as $label)
				$labelStatements[] = wesql::quote('FIND_IN_SET({string:label}, pmr.labels) != 0', array(
					'label' => $label,
				));

			$searchq_parameters['label_implode'] = '(' . implode(' OR ', $labelStatements) . ')';
		}
	}


	$search_params['search'] = !empty($search_params['search']) ? $search_params['search'] : (isset($_REQUEST['search']) ? $_REQUEST['search'] : '');

	if (!isset($search_params['search']) || $search_params['search'] == '')
		$context['search_errors']['invalid_search_string'] = true;


	preg_match_all('~(?:^|\s)([-]?)"([^"]+)"(?:$|\s)~u', $search_params['search'], $matches);
	$searchArray = $matches[2];


	$tempSearch = explode(' ', preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $search_params['search']));


	$excludedWords = array();


	foreach ($matches[1] as $index => $word)
		if ($word == '-')
		{
			$word = westr::strtolower(trim($searchArray[$index]));
			if (strlen($word) > 0)
				$excludedWords[] = $word;
			unset($searchArray[$index]);
		}


	foreach ($tempSearch as $index => $word)
		if (strpos(trim($word), '-') === 0)
		{
			$word = substr(westr::strtolower(trim($word)), 1);
			if (strlen($word) > 0)
				$excludedWords[] = $word;
			unset($tempSearch[$index]);
		}

	$searchArray = array_merge($searchArray, $tempSearch);


	foreach ($searchArray as $index => $value)
	{
		$searchArray[$index] = westr::strtolower(trim($value));
		if ($searchArray[$index] == '')
			unset($searchArray[$index]);
		else
		{

			$searchArray[$index] = westr::htmlspecialchars($searchArray[$index]);
		}
	}
	$searchArray = array_unique($searchArray);


	$context['mark'] = array();
	foreach ($searchArray as $word)
		$context['mark'][$word] = '<mark>' . $word . '</mark>';


	$searchWords = array_merge($searchArray, $excludedWords);


	if (empty($searchArray))
		$context['search_errors']['invalid_search_string'] = true;


	$context['search_params'] = $search_params;
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec']);


	$context['params'] = array();
	foreach ($search_params as $k => $v)
		$context['params'][] = $k . '|\'|' . $v;
	$context['params'] = base64_encode(implode('|"|', $context['params']));


	$andQueryParts = array();

	foreach ($searchWords as $index => $word)
	{
		if ($word == '')
			continue;

		if ($search_params['subject_only'])
			$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
		else
			$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';
		$searchq_parameters['search_' . $index] = '%' . strtr($word, array('_' => '\\_', '%' => '\\%')) . '%';
	}

	$searchQuery = ' 1=1';
	if (!empty($andQueryParts))
		$searchQuery = implode(!empty($search_params['searchtype']) && $search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);


	$timeQuery = '';
	if (!empty($search_params['minage']))
		$timeQuery .= ' AND pm.msgtime < ' . (time() - $search_params['minage'] * 86400);
	if (!empty($search_params['maxage']))
		$timeQuery .= ' AND pm.msgtime > ' . (time() - $search_params['maxage'] * 86400);


	if (!empty($context['search_errors']))
	{
		$_REQUEST['params'] = $context['params'];
		return MessageSearch();
	}


	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')',
		array_merge($searchq_parameters, array(
			'current_member' => MID,
			'not_deleted' => 0,
		))
	);
	list ($numResults) = wesql::fetch_row($request);
	wesql::free_result($request);



	$request = wesql::query('
		SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')
		ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
		LIMIT ' . $context['start'] . ', ' . $settings['search_results_per_page'],
		array_merge($searchq_parameters, array(
			'current_member' => MID,
			'not_deleted' => 0,
		))
	);
	$foundMessages = array();
	$posters = array();
	$head_pms = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$foundMessages[] = $row['id_pm'];
		$posters[] = $row['id_member_from'];
		$head_pms[$row['id_pm']] = $row['id_pm_head'];
	}
	wesql::free_result($request);


	if ($context['display_mode'] == 2 && !empty($head_pms))
	{
		$request = wesql::query('
			SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
			WHERE pm.id_pm_head IN ({array_int:head_pms})
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			GROUP BY pm.id_pm_head
			LIMIT {int:limit}',
			array(
				'head_pms' => array_unique($head_pms),
				'current_member' => MID,
				'not_deleted' => 0,
				'limit' => count($head_pms),
			)
		);
		$real_pm_ids = array();
		while ($row = wesql::fetch_assoc($request))
			$real_pm_ids[$row['id_pm_head']] = $row['id_pm'];
		wesql::free_result($request);
	}


	$posters = array_unique($posters);
	if (!empty($posters))
		loadMemberData($posters);


	$context['page_index'] = template_page_index('<URL>?action=pm;sa=search2;params=' . $context['params'], $_GET['start'], $numResults, $settings['search_results_per_page'], false);

	$context['message_labels'] = array();
	$context['message_replied'] = array();
	$context['personal_messages'] = array();

	if (!empty($foundMessages))
	{

		$request = wesql::query('
			SELECT
				pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name,
				pmr.bcc, pmr.labels, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:message_list})',
			array(
				'message_list' => $foundMessages,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$row['id_pm']][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="<URL>?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($row['id_member_to'] == MID && $context['folder'] != 'sent')
			{
				$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;

				$row['labels'] = $row['labels'] == '' ? array() : explode(',', $row['labels']);

				foreach ($row['labels'] as $v)
				{
					if (isset($context['labels'][(int) $v]))
						$context['message_labels'][$row['id_pm']][(int) $v] = array('id' => $v, 'name' => $context['labels'][(int) $v]['name']);


					if (!isset($context['first_label'][$row['id_pm']]) && !in_array('-1', $row['labels']))
						$context['first_label'][$row['id_pm']] = (int) $v;
				}
			}
		}


		$request = wesql::query('
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm
			WHERE pm.id_pm IN ({array_int:message_list})
			ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
			LIMIT ' . count($foundMessages),
			array(
				'message_list' => $foundMessages,
			)
		);
		$counter = 0;
		while ($row = wesql::fetch_assoc($request))
		{

			$row['subject'] = $row['subject'] === '' ? $txt['no_subject'] : $row['subject'];


			if (!loadMemberContext($row['id_member_from'], true))
			{
				$memberContext[$row['id_member_from']]['name'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['id'] = 0;
				$memberContext[$row['id_member_from']]['group'] = $txt['guest_title'];
				$memberContext[$row['id_member_from']]['link'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['email'] = '';
				$memberContext[$row['id_member_from']]['show_email'] = showEmailAddress(true, 0);
				$memberContext[$row['id_member_from']]['is_guest'] = true;
			}


			censorText($row['body']);
			censorText($row['subject']);


			$row['body'] = parse_bbc($row['body'], 'pm', array('cache' => 'pm' . $row['id_pm']));

			$href = '<URL>?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . ($context['display_mode'] == 2 && isset($real_pm_ids[$head_pms[$row['id_pm']]]) ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg' . $row['id_pm'];
			$is_replied_to =& $context['message_replied'][$row['id_pm']];
			$context['personal_messages'][$row['id_pm']] = array(
				'id' => $row['id_pm'],
				'member' => &$memberContext[$row['id_member_from']],
				'subject' => $row['subject'],
				'body' => $row['body'],
				'on_time' => on_timeformat($row['msgtime']),
				'recipients' => &$recipients[$row['id_pm']],
				'labels' => &$context['message_labels'][$row['id_pm']],
				'fully_labeled' => count($context['message_labels'][$row['id_pm']]) == count($context['labels']),
				'replied_msg' => $is_replied_to ? number_context('pm_is_replied_to_sent', $is_replied_to, false) : 0,
				'is_replied_to' => $is_replied_to,
				'href' => $href,
				'link' => '<a href="' . $href . '">' . $row['subject'] . '</a>',
				'counter' => ++$counter,
			);
		}
		wesql::free_result($request);
	}


	$context['page_title'] = $txt['pm_search_title'];
	wetem::load('search_results');
	$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'search';
	add_linktree($txt['pm_search_bar_title'], '<URL>?action=pm;sa=search');
}


function MessagePost()
{
	global $txt, $settings, $user_profile, $context, $options;

	isAllowedTo('pm_send');

	loadLanguage('PersonalMessage');


	loadTemplate('PersonalMessage');
	wetem::load('send');


	loadSource(array('Subs-Editor', 'Class-Editor'));


	list ($settings['max_pm_recipients'], $settings['pm_posts_verification'], $settings['pm_posts_per_hour']) = explode(',', $settings['pm_spam_settings']);


	$context['page_title'] = $txt['new_message'];

	$context['reply'] = isset($_REQUEST['pmsg']) || isset($_REQUEST['quote']);
	$_REQUEST['draft_id'] = isset($_REQUEST['draft_id']) ? (int) $_REQUEST['draft_id'] : 0;


	if (!empty($settings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && we::$user['mod_cache']['bq'] == '0=1' && we::$user['mod_cache']['gq'] == '0=1')
	{

		$request = wesql::query('
			SELECT COUNT(pr.id_pm) AS post_count
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
			WHERE pm.id_member_from = {int:current_member}
				AND pm.msgtime > {int:msgtime}',
			array(
				'current_member' => MID,
				'msgtime' => time() - 3600,
			)
		);
		list ($postCount) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (!empty($postCount) && $postCount >= $settings['pm_posts_per_hour'])
			fatal_lang_error('pm_too_many_per_hour', true, array($settings['pm_posts_per_hour']));
	}


	if (!empty($_REQUEST['pmsg']))
	{
		$pmsg = (int) $_REQUEST['pmsg'];


		if (!isAccessiblePM($pmsg))
			fatal_lang_error('pm_not_yours', false);


		$request = wesql::query('
			SELECT
				id_pm
			FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => MID,
				'id_pm' => $pmsg,
			)
		);
		$isReceived = wesql::num_rows($request) != 0;
		wesql::free_result($request);


		$request = wesql::query('
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:id_pm_head_empty} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . (!$isReceived ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:id_pm})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}' . (!$isReceived ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => MID,
				'id_pm_head_empty' => 0,
				'id_pm' => $pmsg,
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('pm_not_yours', false);
		$row_quoted = wesql::fetch_assoc($request);
		wesql::free_result($request);


		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);


		getRePrefix();

		$form_subject = $row_quoted['subject'];
		if ($context['reply'] && trim($context['response_prefix']) != '' && westr::strpos($form_subject, trim($context['response_prefix'])) !== 0)
			$form_subject = $context['response_prefix'] . $form_subject;

		if (isset($_REQUEST['quote']))
		{

			$form_message = preg_replace('~<br\s*/?\>~i', "\n", $row_quoted['body']);
			if (!empty($settings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);
			if (empty($row_quoted['id_member']))
				$form_message = '[quote author=&quot;' . $row_quoted['real_name'] . '&quot;]' . "\n" . $form_message . "\n" . '[/quote]';
			else
				$form_message = '[quote author=' . $row_quoted['real_name'] . ' link=action=profile;u=' . $row_quoted['id_member'] . ' date=' . $row_quoted['msgtime'] . ']' . "\n" . $form_message . "\n" . '[/quote]';
		}
		else
			$form_message = '';


		$row_quoted['body'] = parse_bbc($row_quoted['body'], 'pm', array('cache' => 'pm' . $row_quoted['id_pm']));


		$context['quoted_message'] = array(
			'id' => $row_quoted['id_pm'],
			'pm_head' => $row_quoted['pm_head'],
			'member' => array(
				'name' => $row_quoted['real_name'],
				'username' => $row_quoted['member_name'],
				'id' => $row_quoted['id_member'],
				'href' => !empty($row_quoted['id_member']) ? '<URL>?action=profile;u=' . $row_quoted['id_member'] : '',
				'link' => !empty($row_quoted['id_member']) ? '<a href="<URL>?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
			),
			'subject' => $row_quoted['subject'],
			'on_time' => on_timeformat($row_quoted['msgtime']),
			'timestamp' => $row_quoted['msgtime'],
			'body' => $row_quoted['body']
		);
	}
	else
	{
		$context['quoted_message'] = false;
		$form_subject = '';
		$form_message = '';
	}

	$context['recipients'] = array(
		'to' => array(),
		'bcc' => array(),
	);


	if (isset($_REQUEST['u']))
	{

		if ($_REQUEST['u'] == 'all' && isset($row_quoted))
		{

			if ($row_quoted['id_member'] != MID)
				$context['recipients']['to'][] = array(
					'id' => $row_quoted['id_member'],
					'name' => htmlspecialchars($row_quoted['real_name']),
				);


			$request = wesql::query('
				SELECT mem.id_member, mem.real_name
				FROM {db_prefix}pm_recipients AS pmr
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
				WHERE pmr.id_pm = {int:id_pm}
					AND pmr.id_member != {int:current_member}
					AND pmr.bcc = {int:not_bcc}',
				array(
					'current_member' => MID,
					'id_pm' => $pmsg,
					'not_bcc' => 0,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			wesql::free_result($request);
		}
		else
		{
			$_REQUEST['u'] = explode(',', $_REQUEST['u']);
			foreach ($_REQUEST['u'] as $key => $uID)
				$_REQUEST['u'][$key] = (int) $uID;

			$_REQUEST['u'] = array_unique($_REQUEST['u']);

			$request = wesql::query('
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT ' . count($_REQUEST['u']),
				array(
					'member_list' => $_REQUEST['u'],
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			wesql::free_result($request);
		}


		$names = array();
		foreach ($context['recipients']['to'] as $to)
			$names[] = $to['name'];
		$context['to_value'] = empty($names) ? '' : '&quot;' . implode('&quot;, &quot;', $names) . '&quot;';
	}
	else
		$context['to_value'] = '';


	if (!empty($_REQUEST['draft_id']) && allowedTo('save_pm_draft') && empty($_POST['subject']) && empty($_POST['message']))
	{
		$query = wesql::query('
			SELECT subject, body, extra
			FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
				AND is_pm = {int:is_pm}
			LIMIT 1',
			array(
				'draft' => $_REQUEST['draft_id'],
				'member' => MID,
				'is_pm' => 1,
			)
		);

		if ($row = wesql::fetch_assoc($query))
		{

			$form_subject = $row['subject'];
			$form_message = wedit::un_preparsecode($row['body']);
			$row['extra'] = empty($row['extra']) ? array() : unserialize($row['extra']);


			$row['extra']['recipients']['to'] = isset($row['extra']['recipients']['to']) ? $row['extra']['recipients']['to'] : array();
			$row['extra']['recipients']['bcc'] = isset($row['extra']['recipients']['bcc']) ? $row['extra']['recipients']['bcc'] : array();
			$users = array_merge($row['extra']['recipients']['to'], $row['extra']['recipients']['bcc']);
			$users = loadMemberData($users);

			$context['recipients'] = array(
				'to' => array(),
				'bcc' => array(),
			);

			if (!empty($users))
			{
				$row['extra']['recipients']['to'] = array_intersect($row['extra']['recipients']['to'], $users);
				$row['extra']['recipients']['bcc'] = array_intersect($row['extra']['recipients']['bcc'], $users);
			}

			$names = array();
			foreach ($row['extra']['recipients'] as $recType => $recList)
			{
				foreach ($recList as $recipient)
				{
					$context['recipients'][$recType][] = array(
						'id' => $recipient,
						'name' => $user_profile[$recipient]['real_name'],
					);
					$names[$recType][] = $user_profile[$recipient]['real_name'];
				}
				$context[$recType . '_value'] = empty($names[$recType]) ? '' : '&quot;' . implode('&quot;, &quot;', $names[$recType]) . '&quot;';
			}
		}
		wesql::free_result($query);
	}


	$context['subject'] = $form_subject;
	$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);
	$context['post_error'] = array();

	$context['buddy_list'] = getContactList();


	add_linktree($txt['new_message'], '<URL>?action=pm;sa=send');


	$context['postbox'] = new wedit(
		array(
			'id' => 'message',
			'value' => $context['message'],
			'height' => '250px',
			'width' => '100%',
			'buttons' => array(
				array(
					'name' => 'post_button',
					'button_text' => $txt['send_message'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 's',
				),
				array(
					'name' => 'preview',
					'button_text' => $txt['preview'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 'p',
				),
			),
			'drafts' => !allowedTo('save_pm_draft') || empty($settings['masterSavePmDrafts']) ? 'none' : (!allowedTo('auto_save_pm_draft') || empty($settings['masterAutoSavePmDrafts']) || !empty($options['disable_auto_save']) ? 'basic_pm' : 'auto_pm'),
		)
	);
	$context['postbox']->addEntityField('subject');

	$context['bcc_value'] = '';

	$context['require_verification'] = !we::$is_admin && !empty($settings['pm_posts_verification']) && we::$user['posts'] < $settings['pm_posts_verification'];
	if ($context['require_verification'])
	{
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}


	checkSubmitOnce('register');
}


function messagePostError($error_types, $named_recipients, $recipient_ids = array())
{
	global $txt, $context, $settings, $options;

	$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'send';

	wetem::load('send');

	$context['page_title'] = $txt['send_message'];


	$context['recipients'] = array(
		'to' => array(),
		'bcc' => array(),
	);
	if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
	{
		$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

		$request = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $allRecipients,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$recipientType = in_array($row['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
			$context['recipients'][$recipientType][] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
			);
		}
		wesql::free_result($request);
	}


	$context['subject'] = isset($_REQUEST['subject']) ? westr::htmlspecialchars($_REQUEST['subject']) : '';
	$context['message'] = isset($_REQUEST['message']) ? str_replace(array('  '), array('&nbsp; '), westr::htmlspecialchars($_REQUEST['message'])) : '';
	$context['reply'] = !empty($_REQUEST['replied_to']);

	$context['buddy_list'] = getContactList();

	if ($context['reply'])
	{
		$_REQUEST['replied_to'] = (int) $_REQUEST['replied_to'];

		$request = wesql::query('
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:no_id_pm_head} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:replied_to})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:replied_to}' . ($context['folder'] == 'sent' ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => MID,
				'no_id_pm_head' => 0,
				'replied_to' => $_REQUEST['replied_to'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('pm_not_yours', false);
		$row_quoted = wesql::fetch_assoc($request);
		wesql::free_result($request);

		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);

		$context['quoted_message'] = array(
			'id' => $row_quoted['id_pm'],
			'pm_head' => $row_quoted['pm_head'],
			'member' => array(
				'name' => $row_quoted['real_name'],
				'username' => $row_quoted['member_name'],
				'id' => $row_quoted['id_member'],
				'href' => !empty($row_quoted['id_member']) ? '<URL>?action=profile;u=' . $row_quoted['id_member'] : '',
				'link' => !empty($row_quoted['id_member']) ? '<a href="<URL>?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
			),
			'subject' => $row_quoted['subject'],
			'on_time' => on_timeformat($row_quoted['msgtime']),
			'timestamp' => $row_quoted['msgtime'],
			'body' => parse_bbc($row_quoted['body'], 'pm', array('cache' => 'pm' . $row_quoted['id_pm'])),
		);
	}


	add_linktree($txt['new_message'], '<URL>?action=pm;sa=send');


	loadLanguage('Errors');
	$context['post_error'] = array(
		'messages' => array(),
	);
	foreach ($error_types as $error)
	{
		if (is_array($error))
		{
			$context['post_error'][$error[0]] = true;
			if (isset($txt['error_' . $error[0]]))
				$context['post_error']['messages'][] = sprintf($txt['error_' . $error[0]], $error[1]);
		}
		else
		{
			$context['post_error'][$error] = true;
			if (isset($txt['error_' . $error]))
				$context['post_error']['messages'][] = $txt['error_' . $error];
		}
	}


	loadSource(array('Subs-Editor', 'Class-Editor'));


	$context['postbox'] = new wedit(
		array(
			'id' => 'message',
			'value' => $context['message'],
			'width' => '90%',
			'buttons' => array(
				array(
					'name' => 'post_button',
					'button_text' => $txt['send_message'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 's',
				),
				array(
					'name' => 'preview',
					'button_text' => $txt['preview'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 'p',
				),
			),
			'drafts' => !allowedTo('save_pm_draft') || empty($settings['masterSavePmDrafts']) ? 'none' : (!allowedTo('auto_save_pm_draft') || empty($settings['masterAutoSavePmDrafts']) || !empty($options['disable_auto_save']) ? 'basic_pm' : 'auto_pm'),
		)
	);
	$context['postbox']->addEntityField('subject');


	$context['require_verification'] = !we::$is_admin && !empty($settings['pm_posts_verification']) && we::$user['posts'] < $settings['pm_posts_verification'];
	if ($context['require_verification'])
	{
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	$context['to_value'] = empty($named_recipients['to']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['to']) . '&quot;';
	$context['bcc_value'] = empty($named_recipients['bcc']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['bcc']) . '&quot;';


	checkSubmitOnce('free');


	checkSubmitOnce('register');
}

function getContactList()
{
	global $settings, $user_profile;

	$buddies = array();

	if (empty($settings['enable_buddylist']) || empty(we::$user['buddies']))
		return $buddies;

	loadMemberData(we::$user['buddies']);
	foreach (we::$user['buddies'] as $buddy)
		if (isset($user_profile[$buddy]))
			$buddies[$buddy] = $user_profile[$buddy]['real_name'];
	asort($buddies);
	return $buddies;
}


function MessagePost2()
{
	global $txt, $context, $settings;

	isAllowedTo('pm_send');
	loadSource(array('Subs-Auth', 'Class-Editor'));

	loadLanguage('PersonalMessage', '', false);

	$session_timeout = checkSession('post', '', false) != '';


	list ($settings['max_pm_recipients'], $settings['pm_posts_verification'], $settings['pm_posts_per_hour']) = explode(',', $settings['pm_spam_settings']);


	if (!empty($settings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && we::$user['mod_cache']['bq'] == '0=1' && we::$user['mod_cache']['gq'] == '0=1')
	{

		$request = wesql::query('
			SELECT COUNT(pr.id_pm) AS post_count
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
			WHERE pm.id_member_from = {int:current_member}
				AND pm.msgtime > {int:msgtime}',
			array(
				'current_member' => MID,
				'msgtime' => time() - 3600,
			)
		);
		list ($postCount) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (!empty($postCount) && $postCount >= $settings['pm_posts_per_hour'])
			fatal_lang_error('pm_too_many_per_hour', true, array($settings['pm_posts_per_hour']));
	}


	wedit::preparseWYSIWYG('message');


	$post_errors = array();


	if ($session_timeout)
		$post_errors[] = 'session_timeout';

	$_REQUEST['subject'] = isset($_REQUEST['subject']) ? trim($_REQUEST['subject']) : '';
	$_REQUEST['to'] = empty($_POST['to']) ? (empty($_GET['to']) ? '' : $_GET['to']) : $_POST['to'];
	$_REQUEST['bcc'] = empty($_POST['bcc']) ? (empty($_GET['bcc']) ? '' : $_GET['bcc']) : $_POST['bcc'];


	if (!empty($_POST['u']))
		$_POST['recipient_to'] = explode(',', $_POST['u']);


	$recipientList = array();
	$namedRecipientList = array();
	$namesNotFound = array();
	getPmRecipients($recipientList, $namedRecipientList, $namesNotFound);


	$is_recipient_change = !empty($_POST['delete_recipient']) || !empty($_POST['to_submit']) || !empty($_POST['bcc_submit']);


	if (empty($recipientList['to']) && empty($recipientList['bcc']))
		$post_errors[] = 'no_to';


	if (!$is_recipient_change)
	{
		foreach ($recipientList as $recipientType => $dummy)
		{
			if (!empty($namesNotFound[$recipientType]))
			{
				$post_errors[] = 'bad_' . $recipientType;


				$post_errors = array_diff($post_errors, array('no_to'));

				foreach ($namesNotFound[$recipientType] as $name)
					$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
			}
		}
	}


	if ($_REQUEST['subject'] === '')
		$post_errors[] = 'no_subject';
	if (!isset($_REQUEST['message']) || $_REQUEST['message'] === '')
		$post_errors[] = 'no_message';
	elseif (!empty($settings['max_messageLength']) && westr::strlen($_REQUEST['message']) > $settings['max_messageLength'])
		$post_errors[] = array('long_message', $settings['max_messageLength']);
	else
	{

		$message = $_REQUEST['message'];
		wedit::preparsecode($message);


		if (westr::htmltrim(strip_tags(parse_bbc(westr::htmlspecialchars($message, ENT_QUOTES), 'empty-test', array('smileys' => false)), '<img><object><embed><iframe><video><audio>')) === '' && (!allowedTo('admin_forum') || strpos($message, '[html]') === false))
			$post_errors[] = 'no_message';
	}


	if (!we::$is_admin && !empty($settings['pm_posts_verification']) && we::$user['posts'] < $settings['pm_posts_verification'])
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['require_verification']))
			$post_errors = array_merge($post_errors, $context['require_verification']);
	}


	if (!empty($post_errors) && !$is_recipient_change && !isset($_REQUEST['preview']))
		return messagePostError($post_errors, $namedRecipientList, $recipientList);


	if (isset($_REQUEST['preview']))
	{

		$context['preview_subject'] = westr::htmlspecialchars($_REQUEST['subject']);
		$context['preview_message'] = westr::htmlspecialchars($_REQUEST['message'], ENT_QUOTES);
		wedit::preparsecode($context['preview_message'], true);


		$context['preview_message'] = parse_bbc($context['preview_message'], 'preview-pm');


		censorText($context['preview_subject']);
		censorText($context['preview_message']);


		$context['page_title'] = $txt['preview'] . ' - ' . $context['preview_subject'];


		return messagePostError($post_errors, $namedRecipientList, $recipientList);
	}


	elseif ($is_recipient_change)
	{

		foreach ($namesNotFound as $recipientType => $names)
		{
			$post_errors[] = 'bad_' . $recipientType;
			foreach ($names as $name)
				$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
		}

		return messagePostError(array(), $namedRecipientList, $recipientList);
	}


	elseif (!empty($settings['max_pm_recipients']) && count($recipientList['to']) + count($recipientList['bcc']) > $settings['max_pm_recipients'] && !allowedTo(array('moderate_forum', 'send_mail', 'admin_forum')))
	{
		$context['send_log'] = array(
			'sent' => array(),
			'failed' => array(number_context('pm_too_many_recipients', $settings['max_pm_recipients'])),
		);
		return messagePostError($post_errors, $namedRecipientList, $recipientList);
	}


	spamProtection('pm');


	checkSubmitOnce('check');


	if (!empty($recipientList['to']) || !empty($recipientList['bcc']))
		$context['send_log'] = sendpm($recipientList, $_REQUEST['subject'], $_REQUEST['message'], true, null, !empty($_REQUEST['pm_head']) ? (int) $_REQUEST['pm_head'] : 0);
	else
		$context['send_log'] = array(
			'sent' => array(),
			'failed' => array()
		);


	if (!empty($context['send_log']['sent']) && !empty($_REQUEST['replied_to']) && isset($_REQUEST['f']) && $_REQUEST['f'] == 'inbox')
	{
		wesql::query('
			UPDATE {db_prefix}pm_recipients
			SET is_read = is_read | 2
			WHERE id_pm = {int:replied_to}
				AND id_member = {int:current_member}',
			array(
				'current_member' => MID,
				'replied_to' => (int) $_REQUEST['replied_to'],
			)
		);
	}


	if (!empty($_POST['draft_id']) && MID)
		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
			LIMIT 1',
			array(
				'draft' => (int) $_POST['draft_id'],
				'member' => MID,
			)
		);


	if (!empty($context['send_log']['failed']))
		return messagePostError($post_errors, $namesNotFound, array(
			'to' => array_intersect($recipientList['to'], $context['send_log']['failed']),
			'bcc' => array_intersect($recipientList['bcc'], $context['send_log']['failed'])
		));


	if (!empty($context['send_log']) && empty($context['send_log']['failed']))
		$context['current_label_redirect'] = $context['current_label_redirect'] . ';done=sent';


	redirectexit($context['current_label_redirect']);
}



function getPmRecipients(&$recipientList, &$namedRecipientList, &$namesNotFound)
{
	foreach (array('to', 'bcc') as $recipientType)
	{

		$recipientList[$recipientType] = array();
		if (!empty($_POST['recipient_' . $recipientType]) && is_array($_POST['recipient_' . $recipientType]))
		{
			foreach ($_POST['recipient_' . $recipientType] as $recipient)
				$recipientList[$recipientType][] = (int) $recipient;
		}


		if (!empty($_REQUEST[$recipientType]))
		{

			$recipientString = strtr($_REQUEST[$recipientType], array('\\"' => '"'));

			preg_match_all('~"([^"]+)"~', $recipientString, $matches);
			$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

			foreach ($namedRecipientList[$recipientType] as $index => $recipient)
			{
				if (strlen(trim($recipient)) > 0)
					$namedRecipientList[$recipientType][$index] = westr::htmlspecialchars(westr::strtolower(trim($recipient)));
				else
					unset($namedRecipientList[$recipientType][$index]);
			}

			if (!empty($namedRecipientList[$recipientType]))
			{
				loadSource('Subs-Auth');
				$foundMembers = findMembers($namedRecipientList[$recipientType]);


				$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

				foreach ($foundMembers as $member)
				{
					$testNames = array(
						westr::strtolower($member['username']),
						westr::strtolower($member['name']),
						westr::strtolower($member['email']),
					);

					if (count(array_intersect($testNames, $namedRecipientList[$recipientType])) !== 0)
					{
						$recipientList[$recipientType][] = $member['id'];


						$namesNotFound[$recipientType] = array_diff($namesNotFound[$recipientType], $testNames);
					}
				}
			}
		}


		if (!empty($_POST['delete_recipient']))
			$recipientList[$recipientType] = array_diff($recipientList[$recipientType], array((int) $_POST['delete_recipient']));


		$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
	}
}


function MessageActionsApply()
{
	global $context, $options;

	checkSession('request');

	if (isset($_REQUEST['del_selected']))
		$_REQUEST['pm_action'] = 'delete';

	if (isset($_REQUEST['pm_action']) && $_REQUEST['pm_action'] != '' && !empty($_REQUEST['pms']) && is_array($_REQUEST['pms']))
	{
		foreach ($_REQUEST['pms'] as $pm)
			$_REQUEST['pm_actions'][(int) $pm] = $_REQUEST['pm_action'];
	}

	if (empty($_REQUEST['pm_actions']))
		redirectexit($context['current_label_redirect']);


	if ($context['display_mode'] == 2 && isset($_REQUEST['conversation']))
	{
		$id_pms = array();
		foreach ($_REQUEST['pm_actions'] as $pm => $dummy)
			$id_pms[] = (int) $pm;

		$request = wesql::query('
			SELECT id_pm_head, id_pm
			FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:id_pms})',
			array(
				'id_pms' => $id_pms,
			)
		);
		$pm_heads = array();
		while ($row = wesql::fetch_assoc($request))
			$pm_heads[$row['id_pm_head']] = $row['id_pm'];
		wesql::free_result($request);

		$request = wesql::query('
			SELECT id_pm, id_pm_head
			FROM {db_prefix}personal_messages
			WHERE id_pm_head IN ({array_int:pm_heads})',
			array(
				'pm_heads' => array_keys($pm_heads),
			)
		);

		while ($row = wesql::fetch_assoc($request))
		{
			if (isset($pm_heads[$row['id_pm_head']], $_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]]))
				$_REQUEST['pm_actions'][$row['id_pm']] = $_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]];
		}
		wesql::free_result($request);
	}

	$to_delete = array();
	$to_label = array();
	$label_type = array();
	foreach ($_REQUEST['pm_actions'] as $pm => $action)
	{
		if ($action === 'delete')
			$to_delete[] = (int) $pm;
		else
		{
			if (substr($action, 0, 4) == 'add_')
			{
				$type = 'add';
				$action = substr($action, 4);
			}
			elseif (substr($action, 0, 4) == 'rem_')
			{
				$type = 'rem';
				$action = substr($action, 4);
			}
			else
				$type = 'unk';

			if ($action == '-1' || $action == '0' || (int) $action > 0)
			{
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type;
			}
		}
	}


	if (!empty($to_delete))
		deleteMessages($to_delete, $context['display_mode'] == 2 ? null : $context['folder']);


	if (!empty($to_label) && $context['folder'] == 'inbox')
	{
		$updateErrors = 0;


		$request = wesql::query('
			SELECT id_pm, labels
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:current_member}
				AND id_pm IN ({array_int:to_label})
			LIMIT ' . count($to_label),
			array(
				'current_member' => MID,
				'to_label' => array_keys($to_label),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$labels = $row['labels'] == '' ? array('-1') : explode(',', trim($row['labels']));


			$id_label = array_search($to_label[$row['id_pm']], $labels);
			if ($id_label !== false && $label_type[$row['id_pm']] !== 'add')
				unset($labels[$id_label]);
			elseif ($label_type[$row['id_pm']] !== 'rem')
				$labels[] = $to_label[$row['id_pm']];

			if (!empty($options['pm_remove_inbox_label']) && $to_label[$row['id_pm']] != '-1' && ($key = array_search('-1', $labels)) !== false)
				unset($labels[$key]);

			$set = implode(',', array_unique($labels));
			if ($set == '')
				$set = '-1';


			if ($set > 60)
				$updateErrors++;
			else
			{
				wesql::query('
					UPDATE {db_prefix}pm_recipients
					SET labels = {string:labels}
					WHERE id_pm = {int:id_pm}
						AND id_member = {int:current_member}',
					array(
						'current_member' => MID,
						'id_pm' => $row['id_pm'],
						'labels' => $set,
					)
				);
			}
		}
		wesql::free_result($request);



		if (!empty($updateErrors))
			fatal_lang_error('labels_too_many', true, array($updateErrors));
	}


	$_SESSION['pm_selected'] = array_keys($to_label);
	redirectexit($context['current_label_redirect'] . (count($to_label) == 1 ? '#msg' . $_SESSION['pm_selected'][0] : ''), count($to_label) == 1 && we::is('ie'));
}


function MessageKillAllQuery()
{
	global $txt, $context;


	wetem::load('ask_delete');
	$context['page_title'] = $txt['delete_all'];
	$context['delete_all'] = $_REQUEST['f'] == 'all';


	$txt['delete_all'] = str_replace('PMBOX', $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'], $txt['delete_all']);
}


function MessageKillAll()
{
	global $context;

	checkSession('get');


	if ($_REQUEST['f'] == 'all')
		deleteMessages(null, null);

	else
		deleteMessages(null, $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent');


	redirectexit($context['current_label_redirect']);
}


function MessagePrune()
{
	global $txt, $context;


	if (isset($_REQUEST['age']))
	{
		checkSession();


		$deleteTime = max(0, time() - (86400 * (int) $_REQUEST['age']));


		$toDelete = array();


		$request = wesql::query('
			SELECT id_pm
			FROM {db_prefix}personal_messages
			WHERE deleted_by_sender = {int:not_deleted}
				AND id_member_from = {int:current_member}
				AND msgtime < {int:msgtime}',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = wesql::fetch_row($request))
			$toDelete[] = $row[0];
		wesql::free_result($request);


		$request = wesql::query('
			SELECT pmr.id_pm
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			WHERE pmr.deleted = {int:not_deleted}
				AND pmr.id_member = {int:current_member}
				AND pm.msgtime < {int:msgtime}',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$toDelete[] = $row['id_pm'];
		wesql::free_result($request);


		deleteMessages($toDelete);


		redirectexit($context['current_label_redirect']);
	}


	add_linktree($txt['pm_prune'], '<URL>?action=pm;sa=prune');

	wetem::load('prune');
	$context['page_title'] = $txt['pm_prune'];
}


function deleteMessages($personal_messages, $folder = null, $owner = null)
{
	if ($owner === null)
		$owner = array(MID);
	elseif (empty($owner))
		return;
	elseif (!is_array($owner))
		$owner = array($owner);

	if ($personal_messages !== null)
	{
		if (empty($personal_messages) || !is_array($personal_messages))
			return;

		foreach ($personal_messages as $index => $delete_id)
			$personal_messages[$index] = (int) $delete_id;

		$where = '
				AND id_pm IN ({array_int:pm_list})';
	}
	else
		$where = '';

	if ($folder == 'sent' || $folder === null)
	{
		wesql::query('
			UPDATE {db_prefix}personal_messages
			SET deleted_by_sender = {int:is_deleted}
			WHERE id_member_from IN ({array_int:member_list})
				AND deleted_by_sender = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
	}
	if ($folder != 'sent' || $folder === null)
	{

		$request = wesql::query('
			SELECT id_member, COUNT(*) AS num_deleted_messages, CASE WHEN is_read & 1 >= 1 THEN 1 ELSE 0 END AS is_read
			FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where . '
			GROUP BY id_member, is_read',
			array(
				'member_list' => $owner,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);

		while ($row = wesql::fetch_assoc($request))
		{
			if ($row['is_read'])
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages']));
			else
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages'], 'unread_messages' => $where == '' ? 0 : 'unread_messages - ' . $row['num_deleted_messages']));


			if (MID == $row['id_member'])
			{
				we::$user['messages'] -= $row['num_deleted_messages'];
				if (!($row['is_read']))
					we::$user['unread_messages'] -= $row['num_deleted_messages'];
			}
		}
		wesql::free_result($request);


		wesql::query('
			UPDATE {db_prefix}pm_recipients
			SET deleted = {int:is_deleted}
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
	}


	$request = wesql::query('
		SELECT pm.id_pm AS sender, pmr.id_pm
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.deleted = {int:not_deleted})
		WHERE pm.deleted_by_sender = {int:is_deleted}
			' . str_replace('id_pm', 'pm.id_pm', $where) . '
		GROUP BY sender, pmr.id_pm
		HAVING pmr.id_pm IS null',
		array(
			'not_deleted' => 0,
			'is_deleted' => 1,
			'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
		)
	);
	$remove_pms = array();
	while ($row = wesql::fetch_assoc($request))
		$remove_pms[] = $row['sender'];
	wesql::free_result($request);

	if (!empty($remove_pms))
	{
		wesql::query('
			DELETE FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);

		wesql::query('
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);
	}


	cache_put_data('labelCounts:' . MID, null, 720);
}


function markMessages($personal_messages = null, $label = null, $owner = null)
{
	if ($owner === null)
		$owner = MID;

	wesql::query('
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 1
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)' . ($label === null ? '' : '
			AND FIND_IN_SET({string:label}, labels) != 0') . ($personal_messages !== null ? '
			AND id_pm IN ({array_int:personal_messages})' : ''),
		array(
			'personal_messages' => $personal_messages,
			'id_member' => $owner,
			'label' => $label,
		)
	);


	if (wesql::affected_rows() > 0)
		recalculateUnread($owner);
}

function recalculateUnread($owner)
{
	global $context;

	if ($owner == MID)
		foreach ($context['labels'] as $label)
			$context['labels'][(int) $label['id']]['unread_messages'] = 0;

	$result = wesql::query('
		SELECT labels, COUNT(*) AS num
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)
			AND deleted = {int:is_not_deleted}
		GROUP BY labels',
		array(
			'id_member' => $owner,
			'is_not_deleted' => 0,
		)
	);
	$total_unread = 0;
	while ($row = wesql::fetch_assoc($result))
	{
		$total_unread += $row['num'];

		if ($owner != MID)
			continue;

		$this_labels = explode(',', $row['labels']);
		foreach ($this_labels as $this_label)
			$context['labels'][(int) $this_label]['unread_messages'] += $row['num'];
	}
	wesql::free_result($result);


	cache_put_data('labelCounts:' . $owner, $context['labels'], 720);
	updateMemberData($owner, array('unread_messages' => $total_unread));


	if ($owner == MID)
		we::$user['unread_messages'] = $total_unread;
}


function ManageLabels()
{
	global $txt, $context;


	$context['linktree'][] = array(
		'url' => '<URL>?action=pm;sa=manlabels',
		'name' => $txt['pm_manage_labels']
	);

	$context['page_title'] = $txt['pm_manage_labels'];
	wetem::load('labels');

	$the_labels = array();

	foreach ($context['labels'] as $label)
	{
		if ($label['id'] != -1)
			$the_labels[$label['id']] = $label['name'];
	}

	if (isset($_POST[$context['session_var']]))
	{
		checkSession('post');


		$message_changes = array();
		$new_labels = array();
		$rule_changes = array();


		loadRules();


		if (isset($_POST['add']))
		{
			$_POST['label'] = strtr(westr::htmlspecialchars(trim($_POST['label'])), array(',' => '&#044;'));

			if (westr::strlen($_POST['label']) > 30)
				$_POST['label'] = westr::substr($_POST['label'], 0, 30);
			if ($_POST['label'] != '')
				$the_labels[] = $_POST['label'];
		}

		elseif (isset($_POST['delete'], $_POST['delete_label']))
		{
			$i = 0;
			foreach ($the_labels as $id => $name)
			{
				if (isset($_POST['delete_label'][$id]))
				{
					unset($the_labels[$id]);
					$message_changes[$id] = true;
				}
				else
					$new_labels[$id] = $i++;
			}
		}

		elseif (isset($_POST['save']) && !empty($_POST['label_name']))
		{
			$i = 0;
			foreach ($the_labels as $id => $name)
			{
				if ($id == -1)
					continue;
				elseif (isset($_POST['label_name'][$id]))
				{
					$_POST['label_name'][$id] = trim(strtr(westr::htmlspecialchars($_POST['label_name'][$id]), array(',' => '&#044;')));

					if (westr::strlen($_POST['label_name'][$id]) > 30)
						$_POST['label_name'][$id] = westr::substr($_POST['label_name'][$id], 0, 30);
					if ($_POST['label_name'][$id] != '')
					{
						$the_labels[(int) $id] = $_POST['label_name'][$id];
						$new_labels[$id] = $i++;
					}
					else
					{
						unset($the_labels[(int) $id]);
						$message_changes[(int) $id] = true;
					}
				}
				else
					$new_labels[$id] = $i++;
			}
		}


		updateMyData(array('pmlabs' => implode(',', $the_labels)));


		if (!empty($message_changes))
		{
			$searchArray = array_keys($message_changes);

			if (!empty($new_labels))
			{
				for ($i = max($searchArray) + 1, $n = max(array_keys($new_labels)); $i <= $n; $i++)
					$searchArray[] = $i;
			}


			$request = wesql::query('
				SELECT id_pm, labels
				FROM {db_prefix}pm_recipients
				WHERE FIND_IN_SET({raw:find_label_implode}, labels) != 0
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'find_label_implode' => '\'' . implode('\', labels) != 0 OR FIND_IN_SET(\'', $searchArray) . '\'',
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{

				$toChange = explode(',', $row['labels']);

				foreach ($toChange as $key => $value)
					if (in_array($value, $searchArray))
					{
						if (isset($new_labels[$value]))
							$toChange[$key] = $new_labels[$value];
						else
							unset($toChange[$key]);
					}

				if (empty($toChange))
					$toChange[] = '-1';


				wesql::query('
					UPDATE {db_prefix}pm_recipients
					SET labels = {string:new_labels}
					WHERE id_pm = {int:id_pm}
						AND id_member = {int:current_member}',
					array(
						'current_member' => MID,
						'id_pm' => $row['id_pm'],
						'new_labels' => implode(',', array_unique($toChange)),
					)
				);
			}
			wesql::free_result($request);


			foreach ($context['rules'] as $k => $rule)
			{

				foreach ($rule['actions'] as $k2 => $action)
				{
					if ($action['t'] != 'lab' || !in_array($action['v'], $searchArray))
						continue;

					$rule_changes[] = $rule['id'];

					if (isset($new_labels[$action['v']]))
						$context['rules'][$k]['actions'][$k2]['v'] = $new_labels[$action['v']];
					else
						unset($context['rules'][$k]['actions'][$k2]);
				}
			}
		}


		if (!empty($rule_changes))
		{
			$rule_changes = array_unique($rule_changes);

			foreach ($rule_changes as $k => $id)
				if (!empty($context['rules'][$id]['actions']))
				{
					wesql::query('
						UPDATE {db_prefix}pm_rules
						SET actions = {string:actions}
						WHERE id_rule = {int:id_rule}
							AND id_member = {int:current_member}',
						array(
							'current_member' => MID,
							'id_rule' => $id,
							'actions' => serialize($context['rules'][$id]['actions']),
						)
					);
					unset($rule_changes[$k]);
				}


			if (!empty($rule_changes))
				wesql::query('
					DELETE FROM {db_prefix}pm_rules
					WHERE id_rule IN ({array_int:rule_list})
							AND id_member = {int:current_member}',
					array(
						'current_member' => MID,
						'rule_list' => $rule_changes,
					)
				);
		}


		cache_put_data('labelCounts:' . MID, null, 720);


		redirectexit('action=pm;sa=manlabels');
	}
}


function MessageSettings()
{
	global $txt, $context, $profile_vars, $cur_profile, $user_profile;


	loadSource(array('Profile', 'Profile-Modify'));


	$context['profile_custom_submit_url'] = '<URL>?action=pm;sa=settings;save';

	loadMemberData(MID, false, 'profile');
	$cur_profile = $user_profile[MID];

	loadLanguage('Profile');
	loadTemplate('Profile');

	we::$user['is_owner'] = true;
	$context['page_title'] = $txt['pm_settings'];
	$context['id_member'] = MID;
	$context['require_password'] = false;
	$context['menu_item_selected'] = 'settings';
	$context['submit_button_text'] = $txt['pm_settings'];
	$context['profile_header_text'] = $txt['personal_messages'];


	add_linktree($txt['pm_settings'], '<URL>?action=pm;sa=settings');


	if (isset($_REQUEST['save']))
	{
		checkSession('post');


		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);


		saveProfileFields();

		if (!empty($profile_vars))
			updateMemberData(MID, $profile_vars);
	}


	pmprefs(MID);
}


function ReportMessage()
{
	global $txt, $context, $settings;


	if (empty($_REQUEST['pmsg']))
		fatal_lang_error('no_access', false);

	$pmsg = (int) $_REQUEST['pmsg'];

	if (!isAccessiblePM($pmsg, 'inbox'))
		fatal_lang_error('no_access', false);

	$context['pm_id'] = $pmsg;
	$context['page_title'] = $txt['pm_report_title'];


	if (!isset($_POST['report']))
	{
		wetem::load('report_message');



		$request = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
			ORDER BY real_name',
			array(
				'admin_group' => 1,
			)
		);
		$context['admins'] = array();
		while ($row = wesql::fetch_assoc($request))
			$context['admins'][$row['id_member']] = $row['real_name'];
		wesql::free_result($request);


		$context['admin_count'] = count($context['admins']);
	}

	else
	{

		checkSession('post');


		$request = wesql::query('
			SELECT pm.subject, pm.body, pm.msgtime, pm.id_member_from, IFNULL(m.real_name, pm.from_name) AS sender_name
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			LIMIT 1',
			array(
				'current_member' => MID,
				'id_pm' => $context['pm_id'],
				'not_deleted' => 0,
			)
		);

		if (wesql::num_rows($request) == 0)
			fatal_lang_error('no_access', false);
		list ($subject, $body, $time, $memberFromID, $memberFromName) = wesql::fetch_row($request);
		wesql::free_result($request);


		$body = preg_replace('~<br\s*/?\>~i', "\n", $body);


		$request = wesql::query('
			SELECT mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm = {int:id_pm}
				AND pmr.id_member != {int:current_member}',
			array(
				'current_member' => MID,
				'id_pm' => $context['pm_id'],
			)
		);
		$recipients = array();
		$hidden_recipients = 0;
		while ($row = wesql::fetch_assoc($request))
		{

			if ($row['bcc'])
				$hidden_recipients++;
			else
				$recipients[] = '[url=' . SCRIPT . '?action=profile;u=' . $row['id_member_to'] . ']' . $row['to_name'] . '[/url]';
		}
		wesql::free_result($request);

		if ($hidden_recipients)
			$recipients[] = number_context('pm_report_pm_hidden', $hidden_recipients);


		$request = wesql::query('
			SELECT id_member, real_name, lngfile
			FROM {db_prefix}members
			WHERE (id_group = {int:admin_id} OR FIND_IN_SET({int:admin_id}, additional_groups) != 0)
				' . (empty($_POST['id_admin']) ? '' : 'AND id_member = {int:specific_admin}') . '
			ORDER BY lngfile',
			array(
				'admin_id' => 1,
				'specific_admin' => isset($_POST['id_admin']) ? (int) $_POST['id_admin'] : 0,
			)
		);


		if (wesql::num_rows($request) == 0)
			fatal_lang_error('no_access', false);

		$memberFromName = un_htmlspecialchars($memberFromName);


		$messagesToSend = array();

		while ($row = wesql::fetch_assoc($request))
		{

			$cur_language = empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile'];

			if (!isset($messagesToSend[$cur_language]))
			{
				loadLanguage('PersonalMessage', $cur_language, false);


				$report_body = str_replace(array('{REPORTER}', '{SENDER}'), array(un_htmlspecialchars(we::$user['name']), $memberFromName), $txt['pm_report_pm_user_sent']);
				$report_body .= "\n" . '[b]' . $_POST['reason'] . '[/b]' . "\n\n";
				if (!empty($recipients))
					$report_body .= $txt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
				$report_body .= $txt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($memberFromID) ? '&quot;' . $memberFromName . '&quot;' : $memberFromName . ' link=action=profile;u=' . $memberFromID . ' date=' . $time) . ']' . "\n" . un_htmlspecialchars($body) . '[/quote]';


				$messagesToSend[$cur_language] = array(
					'subject' => (westr::strpos($subject, $txt['pm_report_pm_subject']) === false ? $txt['pm_report_pm_subject'] : '') . un_htmlspecialchars($subject),
					'body' => $report_body,
					'recipients' => array(
						'to' => array(),
						'bcc' => array()
					),
				);
			}


			$messagesToSend[$cur_language]['recipients']['to'][$row['id_member']] = $row['id_member'];
		}
		wesql::free_result($request);


		foreach ($messagesToSend as $lang => $message)
			sendpm($message['recipients'], $message['subject'], $message['body'], false);


		if (!empty($settings['userLanguage']))
			loadLanguage('PersonalMessage', '', false);


		wetem::load('report_message_complete');
	}
}


function ManageRules()
{
	global $txt, $context;


	add_linktree($txt['pm_manage_rules'], '<URL>?action=pm;sa=manrules');

	$context['page_title'] = $txt['pm_manage_rules'];
	wetem::load('rules');


	loadRules();


	$request = wesql::query('
		SELECT mg.id_group, mg.group_name, IFNULL(gm.id_member, 0) AS can_moderate, mg.hidden
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:moderator_group}
			AND mg.hidden = {int:not_hidden}
		ORDER BY mg.group_name',
		array(
			'current_member' => MID,
			'min_posts' => -1,
			'moderator_group' => 3,
			'not_hidden' => 0,
		)
	);
	$context['groups'] = array();
	while ($row = wesql::fetch_assoc($request))
	{

		if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
			continue;

		$context['groups'][$row['id_group']] = $row['group_name'];
	}
	wesql::free_result($request);


	if (isset($_REQUEST['apply']))
	{
		checkSession();

		applyRules(true);
		redirectexit('action=pm;sa=manrules');
	}

	if (isset($_REQUEST['add']))
	{
		$context['rid'] = isset($_GET['rid'], $context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;
		wetem::load('add_rule');


		if ($context['rid'])
		{
			$context['rule'] = $context['rules'][$context['rid']];
			$members = array();

			foreach ($context['rule']['criteria'] as $k => $criteria)
				if ($criteria['t'] == 'mid' && !empty($criteria['v']))
					$members[(int) $criteria['v']] = $k;

			if (!empty($members))
			{
				$request = wesql::query('
					SELECT id_member, member_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:member_list})',
					array(
						'member_list' => array_keys($members),
					)
				);
				while ($row = wesql::fetch_assoc($request))
					$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
				wesql::free_result($request);
			}
		}
		else
			$context['rule'] = array(
				'id' => '',
				'name' => '',
				'criteria' => array(),
				'actions' => array(),
				'logic' => 'and',
			);
	}

	elseif (isset($_GET['save']))
	{
		checkSession('post');
		$context['rid'] = isset($_GET['rid'], $context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;


		$ruleName = westr::htmlspecialchars(trim($_POST['rule_name']));
		if (empty($ruleName))
			fatal_lang_error('pm_rule_no_name', false);


		if (empty($_POST['ruletype']) || empty($_POST['acttype']))
			fatal_lang_error('pm_rule_no_criteria', false);


		$criteria = array();
		foreach ($_POST['ruletype'] as $ind => $type)
		{

			if ($type == 'gid' && (!isset($_POST['ruledefgroup'][$ind]) || !isset($context['groups'][$_POST['ruledefgroup'][$ind]])))
				continue;
			elseif ($type != 'bud' && !isset($_POST['ruledef'][$ind]))
				continue;


			if ($type == 'mid')
			{
				$name = trim($_POST['ruledef'][$ind]);
				$request = wesql::query('
					SELECT id_member
					FROM {db_prefix}members
					WHERE real_name = {string:member_name}
						OR member_name = {string:member_name}',
					array(
						'member_name' => $name,
					)
				);
				if (wesql::num_rows($request) == 0)
					continue;
				list ($memID) = wesql::fetch_row($request);
				wesql::free_result($request);

				$criteria[] = array('t' => 'mid', 'v' => $memID);
			}
			elseif ($type == 'bud')
				$criteria[] = array('t' => 'bud', 'v' => 1);
			elseif ($type == 'gid')
				$criteria[] = array('t' => 'gid', 'v' => (int) $_POST['ruledefgroup'][$ind]);
			elseif (in_array($type, array('sub', 'msg')) && trim($_POST['ruledef'][$ind]) != '')
				$criteria[] = array('t' => $type, 'v' => westr::htmlspecialchars(trim($_POST['ruledef'][$ind])));
		}


		$actions = array();
		$doDelete = 0;
		$isOr = $_POST['rule_logic'] == 'or' ? 1 : 0;
		foreach ($_POST['acttype'] as $ind => $type)
		{

			if ($type == 'lab' && (!isset($_POST['labdef'][$ind]) || !isset($context['labels'][$_POST['labdef'][$ind] - 1])))
				continue;


			if ($type == 'del')
				$doDelete = 1;
			elseif ($type == 'lab')
				$actions[] = array('t' => 'lab', 'v' => (int) $_POST['labdef'][$ind] - 1);
		}

		if (empty($criteria) || (empty($actions) && !$doDelete))
			fatal_lang_error('pm_rule_no_criteria', false);


		$criteria = serialize($criteria);
		$actions = serialize($actions);


		if (empty($context['rid']))
			wesql::insert('',
				'{db_prefix}pm_rules',
				array(
					'id_member' => 'int', 'rule_name' => 'string', 'criteria' => 'string', 'actions' => 'string',
					'delete_pm' => 'int', 'is_or' => 'int',
				),
				array(
					MID, $ruleName, $criteria, $actions, $doDelete, $isOr,
				)
			);
		else
			wesql::query('
				UPDATE {db_prefix}pm_rules
				SET rule_name = {string:rule_name}, criteria = {string:criteria}, actions = {string:actions},
					delete_pm = {int:delete_pm}, is_or = {int:is_or}
				WHERE id_rule = {int:id_rule}
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'delete_pm' => $doDelete,
					'is_or' => $isOr,
					'id_rule' => $context['rid'],
					'rule_name' => $ruleName,
					'criteria' => $criteria,
					'actions' => $actions,
				)
			);

		redirectexit('action=pm;sa=manrules');
	}

	elseif (isset($_POST['delselected']) && !empty($_POST['delrule']))
	{
		checkSession('post');
		$toDelete = array();
		foreach ($_POST['delrule'] as $k => $v)
			$toDelete[] = (int) $k;

		if (!empty($toDelete))
			wesql::query('
				DELETE FROM {db_prefix}pm_rules
				WHERE id_rule IN ({array_int:delete_list})
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'delete_list' => $toDelete,
				)
			);

		redirectexit('action=pm;sa=manrules');
	}
}


function applyRules($all_messages = false)
{
	global $context, $options;


	loadRules();


	if (empty($context['rules']))
		return;


	$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';



	$request = wesql::query('
		SELECT
			pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group, pmr.labels
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
		WHERE pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}
			' . $ruleQuery,
		array(
			'current_member' => MID,
			'not_deleted' => 0,
		)
	);
	$actions = array();
	while ($row = wesql::fetch_assoc($request))
	{
		foreach ($context['rules'] as $rule)
		{
			$match = false;

			foreach ($rule['criteria'] as $criterium)
			{
				if (($criterium['t'] == 'mid' && $criterium['v'] == $row['id_member_from']) || ($criterium['t'] == 'gid' && $criterium['v'] == $row['id_group']) || ($criterium['t'] == 'sub' && strpos($row['subject'], $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($row['body'], $criterium['v']) !== false))
					$match = true;

				elseif ($rule['logic'] == 'and')
				{
					$match = false;
					break;
				}
			}


			if ($match)
			{
				if ($rule['delete'])
					$actions['deletes'][] = $row['id_pm'];
				else
				{
					foreach ($rule['actions'] as $ruleAction)
					{
						if ($ruleAction['t'] == 'lab')
						{

							if (!isset($actions['labels'][$row['id_pm']]))
								$actions['labels'][$row['id_pm']] = empty($row['labels']) ? array() : explode(',', $row['labels']);
							$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
						}
					}
				}
			}
		}
	}
	wesql::free_result($request);


	if (!empty($actions['deletes']))
		deleteMessages($actions['deletes']);


	if (!empty($actions['labels']))
	{
		foreach ($actions['labels'] as $pm => $labels)
		{

			$realLabels = array();
			foreach ($context['labels'] as $label)
				if (in_array($label['id'], $labels) && ($label['id'] != -1 || empty($options['pm_remove_inbox_label'])))
					$realLabels[] = $label['id'];

			wesql::query('
				UPDATE {db_prefix}pm_recipients
				SET labels = {string:new_labels}
				WHERE id_pm = {int:id_pm}
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'id_pm' => $pm,
					'new_labels' => empty($realLabels) ? '' : implode(',', $realLabels),
				)
			);
		}
	}
}


function loadRules($reload = false)
{
	global $context;

	if (isset($context['rules']) && !$reload)
		return;

	$request = wesql::query('
		SELECT
			id_rule, rule_name, criteria, actions, delete_pm, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => MID,
		)
	);
	$context['rules'] = array();

	while ($row = wesql::fetch_assoc($request))
	{
		$context['rules'][$row['id_rule']] = array(
			'id' => $row['id_rule'],
			'name' => $row['rule_name'],
			'criteria' => unserialize($row['criteria']),
			'actions' => unserialize($row['actions']),
			'delete' => $row['delete_pm'],
			'logic' => $row['is_or'] ? 'or' : 'and',
		);

		if ($row['delete_pm'])
			$context['rules'][$row['id_rule']]['actions'][] = array('t' => 'del', 'v' => 1);
	}
	wesql::free_result($request);
}


function isAccessiblePM($pmID, $validFor = 'in_or_outbox')
{
	$request = wesql::query('
		SELECT
			pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted} AS valid_for_outbox,
			pmr.id_pm IS NOT NULL AS valid_for_inbox
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.id_member = {int:id_current_member} AND pmr.deleted = {int:not_deleted})
		WHERE pm.id_pm = {int:id_pm}
			AND ((pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted}) OR pmr.id_pm IS NOT NULL)',
		array(
			'id_pm' => $pmID,
			'id_current_member' => MID,
			'not_deleted' => 0,
		)
	);

	if (wesql::num_rows($request) === 0)
	{
		wesql::free_result($request);
		return false;
	}

	$validationResult = wesql::fetch_assoc($request);
	wesql::free_result($request);

	switch ($validFor)
	{
		case 'inbox':
			return !empty($validationResult['valid_for_inbox']);
		break;

		case 'outbox':
			return !empty($validationResult['valid_for_outbox']);
		break;

		case 'in_or_outbox':
			return !empty($validationResult['valid_for_inbox']) || !empty($validationResult['valid_for_outbox']);
		break;

		default:
			trigger_error('Undefined validation type given', E_USER_ERROR);
		break;
	}
}

function MessageDrafts()
{
	global $context, $txt, $settings, $user_profile;

	loadLanguage('PersonalMessage');


	if (isset($_GET['deleteall']))
	{
		checkSession('post');
		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE is_pm = {int:is_pm}
				AND id_member = {int:member}',
			array(
				'is_pm' => 1,
				'member' => MID,
			)
		);

		redirectexit('action=pm;sa=showdrafts');
	}
	elseif (!empty($_GET['delete']))
	{
		$draft_id = (int) $_GET['delete'];
		checkSession('get');

		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
			LIMIT 1',
			array(
				'draft' => $draft_id,
				'member' => MID,
			)
		);

		if (AJAX)
			obExit(false);
		else
			redirectexit('action=pm;sa=showdrafts');
	}


	$context['start'] = (int) $_REQUEST['start'];
	wetem::load('pm_drafts');
	$context['page_title'] = $txt['showDrafts'];

	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = 10;


	$request = wesql::query('
		SELECT COUNT(id_draft)
		FROM {db_prefix}drafts AS d
		WHERE id_member = {int:member}
			AND is_pm = {int:is_pm}',
		array(
			'member' => MID,
			'is_pm' => 1,
		)
	);
	list ($msgCount) = wesql::fetch_row($request);
	wesql::free_result($request);

	$reverse = false;
	$maxIndex = (int) $settings['defaultMaxMessages'];


	$context['page_index'] = template_page_index('<URL>?action=pm;sa=showdrafts', $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;


	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $settings['defaultMaxMessages'];
		$start = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $settings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $settings['defaultMaxMessages'];
	}


	$request = wesql::query('
		SELECT
			d.id_draft, d.subject, d.body, d.post_time, d.id_context, d.extra
		FROM {db_prefix}drafts AS d
		WHERE d.id_member = {int:current_member}
			AND d.is_pm = {int:is_pm}
		ORDER BY d.post_time ' . ($reverse ? 'ASC' : 'DESC') . '
		LIMIT ' . $start . ', ' . $maxIndex,
		array(
			'current_member' => MID,
			'is_pm' => 1,
		)
	);


	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = array();
	$users = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['subject'] = westr::htmltrim($row['subject']);
		if ($row['subject'] === '')
			$row['subject'] = $txt['no_subject'];

		$row['extra'] = empty($row['extra']) ? array() : unserialize($row['extra']);
		$row['extra']['recipients']['to'] = isset($row['extra']['recipients']['to']) ? $row['extra']['recipients']['to'] : array();
		$row['extra']['recipients']['bcc'] = isset($row['extra']['recipients']['bcc']) ? $row['extra']['recipients']['bcc'] : array();
		$users = array_merge($users, $row['extra']['recipients']['to'], $row['extra']['recipients']['bcc']);

		censorText($row['body']);
		censorText($row['subject']);


		$row['body'] = parse_bbc($row['body'], 'pm-draft', array('smileys' => !empty($row['extra']['smileys_enabled']), 'cache' => 'pmdraft' . $row['id_draft']));


		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'id' => $row['id_draft'],
			'subject' => $row['subject'],
			'body' => $row['body'],
			'counter' => $counter,
			'alternate' => $counter % 2,
			'on_time' => on_timeformat($row['post_time']),
			'timestamp' => $row['post_time'],
			'recipients' => $row['extra']['recipients'],
			'pmsg' => $row['id_context'],
		);
	}
	wesql::free_result($request);


	if (!empty($users))
	{
		$users = loadMemberData(array_unique($users));
		foreach ($context['posts'] as $id => $post)
		{
			$recipients = $post['recipients'];
			foreach ($recipients as $recType => $recList)
			{
				$context['posts'][$id]['recipients'][$recType] = array();
				foreach ($recList as $recipient)
					$context['posts'][$id]['recipients'][$recType][] = '<a href="<URL>?action=profile;u=' . $recipient . '" target="_blank">' . $user_profile[$recipient]['real_name'] . '</a>';
			}
		}
	}


	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);
}
