<?php
/**
 * Manages the settings for forum news, plus all newsletter configuration and sending.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
	void ManageNews()
		- the entrance point for all News and Newsletter screens.
		- called by ?action=admin;area=news.
		- does the permission checks.
		- calls the appropriate function based on the requested sub-action.

	void EditNews()
		- changes the current news items for the forum.
		- uses the ManageNews template and edit_news block.
		- called by ?action=admin;area=news.
		- requires the edit_news permission.
		- writes an entry into the moderation log.
		- uses the edit_news administration area.
		- can be accessed with ?action=admin;area=news;sa=editnews.

	void SelectMailingMembers()
		- allows a user to select the membergroups to send their mailing to.
		- uses the ManageNews template and email_members block.
		- called by ?action=admin;area=news;sa=mailingmembers.
		- requires the send_mail permission.
		- form is submitted to ?action=admin;area=news;mailingcompose.

	void ComposeMailing()
		- shows a form to edit a forum mailing and its recipients.
		- uses the ManageNews template and email_members_compose block.
		- called by ?action=admin;area=news;sa=mailingcompose.
		- requires the send_mail permission.
		- form is submitted to ?action=admin;area=news;sa=mailingsend.

	void SendMailing(bool clean_only = false)
		- handles the sending of the forum mailing in batches.
		- uses the ManageNews template and email_members_send block.
		- called by ?action=admin;area=news;sa=mailingsend
		- requires the send_mail permission.
		- redirects to itself when more batches need to be sent.
		- redirects to ?action=admin after everything has been sent.
		- if clean_only is set will only clean the variables, put them in context, then return.

	void NewsSettings()
		- set general news and newsletter settings and permissions.
		- uses the ManageNews template and news_settings block.
		- called by ?action=admin;area=news;sa=settings.
		- requires the admin_forum permission.
*/

// The controller; doesn't do anything, just delegates.
function ManageNews()
{
	global $context, $txt;

	// First, let's do a quick permissions check for the best error message possible.
	isAllowedTo(array('edit_news', 'send_mail', 'admin_forum'));

	loadTemplate('ManageNews');

	// Format: 'sub-action' => array('function', 'permission')
	$subActions = array(
		'editnews' => array('EditNews', 'edit_news'),
		'mailingmembers' => array('SelectMailingMembers', 'send_mail'),
		'mailingcompose' => array('ComposeMailing', 'send_mail'),
		'mailingsend' => array('SendMailing', 'send_mail'),
		'settings' => array('ModifyNewsSettings', 'admin_forum'),
	);

	// Default to sub action 'main' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('edit_news') ? 'editnews' : (allowedTo('send_mail') ? 'mailingmembers' : 'settings'));

	// Have you got the proper permissions?
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Create the tabs for the template.
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

	// Force the right area...
	if (substr($_REQUEST['sa'], 0, 7) == 'mailing')
		$context[$context['admin_menu_name']]['current_subsection'] = 'mailingmembers';

	$subActions[$_REQUEST['sa']][0]();
}

// Let the administrator(s) edit the news.
function EditNews()
{
	global $txt, $settings, $context;

	loadSource('Class-Editor');

	if (!empty($_POST['saveorder']) && !empty($_POST['order']) && is_array($_POST['order']))
	{
		checkSession();
		$news_lines = explode("\n", $settings['news']);

		// First, get the order into something we can work with.
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
		// So, that should largely be it, in theory. Just check there aren't any items left lying around
		foreach ($news_lines as $old_pos => $item)
		{
			if (!isset($_POST['order'][$old_pos]))
				$_POST['order']['old' . $old_pos] = $item;
		}
		updateSettings(array('news' => implode("\n", $_POST['order'])));
		clean_cache('css'); // No news means no need to cache related CSS.
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
		$privacy = isset($_POST['privacy']) && in_array($_POST['privacy'], array('e', 'm', 's', 'a')) ? $_POST['privacy'] : 'a'; // if not specified, assume admin only, just in case
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

	// Ready the current news.
	if (!empty($settings['news']))
		foreach (explode("\n", $settings['news']) as $id => $line)
			$context['admin_current_news'][$id] = array(
				'id' => $id,
				'privacy' => $line[0],
				'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', parse_bbc(substr($line, 1), 'news')),
			);

	add_jquery_ui();
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

	// Get all the extra groups as well as Administrator and Global Moderator.
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

	// If we have post groups, let's count the number of members...
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
		// Find people who are members of this group...
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

		// Also do those who have it as an additional membergroup - this ones more yucky...
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

	// Any moderators?
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

// Email your members...
function ComposeMailing()
{
	global $txt, $context;

	// Start by finding any members!
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
			// Remove the quotes.
			$_POST[$type] = strtr($_POST[$type], array('\\"' => '"'));

			preg_match_all('~"([^"]+)"~', $_POST[$type], $matches);
			$_POST[$type] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST[$type]))));

			foreach ($_POST[$type] as $index => $member)
				if (strlen(trim($member)) > 0)
					$_POST[$type][$index] = westr::htmlspecialchars(westr::strtolower(trim($member)));
				else
					unset($_POST[$type][$index]);

			// Find the members
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

	// Clean the other vars.
	SendMailing(true);

	// We need a couple strings from the email template file and sendtopic_send from ManageTopics.
	loadLanguage(array('EmailTemplates', 'ManageTopics'));

	// Get a list of all full banned users. Only get the ones that can't login to turn off notification.
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

	// Did they select moderators - if so add them as specific members...
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

	// For progress bar!
	$context['total_emails'] = count($context['recipients']['emails']);
	$request = wesql::query('
		SELECT MAX(id_member)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($context['max_id_member']) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Clean up the arrays.
	$context['recipients']['members'] = array_unique($context['recipients']['members']);
	$context['recipients']['exclude_members'] = array_unique($context['recipients']['exclude_members']);

	// Setup the template!
	$context['page_title'] = $txt['admin_newsletters'];
	wetem::load('email_members_compose');

	$context['default_subject'] = htmlspecialchars($context['forum_name'] . ': ' . $txt['subject']);
	$context['default_message'] = htmlspecialchars($txt['message'] . "\n\n" . str_replace('{FORUMNAME}', $context['forum_name'], $txt['regards_team']) . "\n\n" . '{$board_url}');
}

// Send out the mailing!
function SendMailing($clean_only = false)
{
	global $txt, $context, $settings;

	// How many to send at once? Quantity depends on whether we are queueing or not.
	$num_at_once = empty($settings['mail_queue']) ? 60 : 1000;

	// If by PM's I suggest we half the above number.
	if (!empty($_POST['send_pm']))
		$num_at_once /= 2;

	checkSession();

	// Where are we actually to?
	$context['start'] = isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;
	$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
	$context['max_id_member'] = !empty($_POST['max_id_member']) ? (int) $_POST['max_id_member'] : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';
	$context['parse_html'] = !empty($_POST['parse_html']) ? '1' : '0';

	// Create our main context.
	$context['recipients'] = array(
		'groups' => array(),
		'exclude_groups' => array(),
		'members' => array(),
		'exclude_members' => array(),
		'emails' => array(),
	);

	// Have we any excluded members?
	if (!empty($_POST['exclude_members']))
	{
		$members = explode(',', $_POST['exclude_members']);
		foreach ($members as $member)
			if ($member >= $context['start'])
				$context['recipients']['exclude_members'][] = (int) $member;
	}

	// What about members we *must* do?
	if (!empty($_POST['members']))
	{
		$members = explode(',', $_POST['members']);
		foreach ($members as $member)
			if ($member >= $context['start'])
				$context['recipients']['members'][] = (int) $member;
	}
	// Cleaning groups is simple - although deal with both checkbox and commas.
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
	// Same for excluded groups
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
	// Finally - emails!
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

	// If we're only cleaning drop out here.
	if ($clean_only)
		return;

	loadSource('Subs-Post');

	// Save the message and its subject in $context
	$context['subject'] = htmlspecialchars($_POST['subject']);
	$context['message'] = htmlspecialchars($_POST['message']);

	// Prepare the message for sending it as HTML
	if (!$context['send_pm'] && !empty($_POST['send_html']))
	{
		// Prepare the message for HTML.
		if (!empty($_POST['parse_html']))
			$_POST['message'] = str_replace(array("\n", '  '), array("<br>\n", '&nbsp; '), $_POST['message']);

		// This is here to prevent spam filters from tagging this as spam.
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

	// We might need this in a bit
	$cleanLatestMember = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($settings['latestRealName']) : $settings['latestRealName'];

	// Replace in all the standard things.
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

	// If we still have emails, do them first!
	$i = 0;
	foreach ($context['recipients']['emails'] as $k => $email)
	{
		// Done as many as we can?
		if ($i >= $num_at_once)
			break;

		// Don't sent it twice!
		unset($context['recipients']['emails'][$k]);

		// Dammit - can't PM emails!
		if ($context['send_pm'])
			continue;

		$to_member = array(
			$email,
			!empty($_POST['send_html']) ? '<a href="mailto:' . $email . '">' . $email . '</a>' : $email,
			'??',
			$email
		);

		sendmail($email, str_replace($from_member, $to_member, $_POST['subject']), str_replace($from_member, $to_member, $_POST['message']), null, null, !empty($_POST['send_html']), 5);

		// Done another...
		$i++;
	}

	// Got some more to send this batch?
	$last_id_member = 0;
	if ($i < $num_at_once)
	{
		// Need to build quite a query!
		$sendQuery = '(';
		$sendParams = array();
		if (!empty($context['recipients']['groups']))
		{
			// Take the long route...
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

		// If we've not got a query then we must be done!
		if ($sendQuery == '()')
			redirectexit('action=admin');

		// Anything to exclude?
		if (!empty($context['recipients']['exclude_groups']) && in_array(0, $context['recipients']['exclude_groups']))
			$sendQuery .= ' AND mem.id_group != {int:regular_group}';
		if (!empty($context['recipients']['exclude_members']))
		{
			$sendQuery .= ' AND mem.id_member NOT IN ({array_int:exclude_members})';
			$sendParams['exclude_members'] = $context['recipients']['exclude_members'];
		}

		// Force them to have it?
		if (empty($context['email_force']))
			$sendQuery .= ' AND mem.notify_announcements = {int:notify_announcements}';

		// Get the smelly people - note we respect the id_member range as it gives us a quicker query.
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

			// What groups are we looking at here?
			if (empty($row['additional_groups']))
				$groups = array($row['id_group'], $row['id_post_group']);
			else
				$groups = array_merge(
					array($row['id_group'], $row['id_post_group']),
					explode(',', $row['additional_groups'])
				);

			// Excluded groups?
			if (array_intersect($groups, $context['recipients']['exclude_groups']))
				continue;

			// We might need this
			$cleanMemberName = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($row['real_name']) : $row['real_name'];

			// Replace the member-dependent variables
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

			// Send the actual email - or a PM!
			if (!$context['send_pm'])
				sendmail($row['email_address'], $subject, $message, null, null, !empty($_POST['send_html']), 5);
			else
				sendpm(array('to' => array($row['id_member']), 'bcc' => array()), $subject, $message, false);
		}
		wesql::free_result($result);
	}

	// If used our batch assume we still have a member.
	if ($i >= $num_at_once)
		$last_id_member = $context['start'];
	// Or we didn't have one in range?
	elseif (empty($last_id_member) && $context['start'] + $num_at_once < $context['max_id_member'])
		$last_id_member = $context['start'] + $num_at_once;
	// If we have no id_member then we're done.
	elseif (empty($last_id_member) && empty($context['recipients']['emails']))
	{
		// Log this into the admin log.
		logAction('newsletter', array(), 'admin');

		redirectexit('action=admin');
	}

	$context['start'] = $last_id_member;

	// Working out progress is a black art of sorts.
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
			// Inline permissions.
			array('permissions', 'edit_news', 'help' => '', 'exclude' => array(-1)),
			array('permissions', 'send_mail', 'exclude' => array(-1)),
		'',
			array('check', 'enable_news'),
			array('check', 'show_newsfader'),
			array('int', 'newsfader_time', 'min' => 100),
		'',
			// Just the remaining settings.
			array('check', 'xmlnews_enable', 'onclick' => '$(\'#xmlnews_maxlen\').prop(\'disabled\', !this.checked);'),
			array('int', 'xmlnews_maxlen', 'subtext' => $txt['xmlnews_maxlen_subtext']),
			array('check', 'xmlnews_sidebar'),
	);

	if ($return_config)
		return $config_vars;

	wetem::load('show_settings');

	// Needed for the inline permission functions, and the settings template.
	loadSource(array('ManagePermissions', 'ManageServer'));

	// Wrap it all up nice and warm...
	$context['post_url'] = '<URL>?action=admin;area=news;save;sa=settings';

	// Add some JavaScript at the bottom...
	add_js('
	$("#xmlnews_maxlen").prop("disabled", !$("#xmlnews_enable").is(":checked"));');

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();
		clean_cache('css'); // The news needs some space in the CSS files.

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
			$result[] = parse_bbc(stripslashes(trim($value)), 'news'); // really no point setting this to be cached since we're caching the entire news.
	}

	return array(
		'data' => $result,
		'expires' => time() + 7200,
	);
}
