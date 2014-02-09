<?php
/**
 * This file is a sample homepage that can be used in place of your board index.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	It contains only the following function:

	void Welcome()
		- prepares the homepage data.
		- uses the Welcome template (main block) and language file.
		- is accessed via index.php, if $settings['default_index'] == 'Welcome'.
		- to create your own custom entry point, just set $settings['default_index'] to 'Homepage'
		  and create your own Homepage.php file so it won't be overwritten by Wedge updates!
*/

// Welcome to the show.
function Welcome()
{
	global $context, $txt, $settings;

	// Load the 'Welcome' template.
	loadTemplate('Welcome');

	// We don't have language files for now, but in case we add them...
	// loadLanguage('Welcome');

	// Set a canonical URL for this page.
	$context['canonical_url'] = '<URL>';

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		$context['robot_no_index'] = true;

	// In this case, we want to load the info center as well -- but not in the sidebar.
	// We will simply create the info_center layer at the end of the main block and inject the blocks into it.
	loadTemplate('InfoCenter');

	wetem::add(
		array(
			'thoughts',
			'info' => array(
				'info_center' => array(
					'info_center_statistics',
					'info_center_usersonline',
					'info_center_personalmsg',
				),
			),
		)
	);

	/*
		Except for $context['page_title'], the rest is taken directly
		from Boards.php and used to generate the info center.
	*/

	// Get the user online list.
	loadSource('Subs-MembersOnline');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	);
	$context += getMembersOnlineStats($membersOnlineOptions);

	$context['show_buddies'] = !empty(we::$user['buddies']);

	// Are we showing all membergroups on the board index?
	if (!empty($settings['show_group_key']))
		$context['membergroups'] = cache_quick_get('membergroup_list', 'Subs-Membergroups', 'cache_getMembergroupList', array());

	// Track most online statistics? (Subs-MembersOnline.php)
	if (!empty($settings['trackStats']))
		trackStatsUsersOnline($context['num_guests'] + $context['num_spiders'] + $context['num_users_online']);

	$context['show_stats'] = allowedTo('view_stats') && !empty($settings['trackStats']);
	$context['show_member_list'] = allowedTo('view_mlist');
	$context['show_who'] = allowedTo('who_view') && !empty($settings['who_enabled']);

	$context['page_title'] = isset($txt['homepage_title']) ? $txt['homepage_title'] : $context['forum_name'] . ' - ' . $txt['home'];

	call_hook('info_center');

	// Now onto the thoughts...
	$request = wesql::query('
		SELECT COUNT(id_thought)
		FROM {db_prefix}thoughts
		LIMIT 1', array()
	);
	list ($total_thoughts) = wesql::fetch_row($request);
	wesql::free_result($request);

	$page = isset($_GET['page']) ? $_GET['page'] : 0;

	$limit = 15;
	if (isset($_GET['s']) && $_GET['s'] === 'thoughts')
	{
		$limit = 30;
		$context['page_index'] = template_page_index('<URL>?s=thoughts;page=%1$d', $page, round($total_thoughts / 30), 1, true);
	}

	$request = wesql::query('
		SELECT
			h.updated, h.thought, h.id_thought, h.id_parent, h.privacy,
			h.id_member, h.id_master, h2.id_member AS id_parent_owner,
			m.real_name AS owner_name, mp.real_name AS parent_name, m.posts
		FROM {db_prefix}thoughts AS h
		LEFT JOIN {db_prefix}thoughts AS h2 ON (h.id_parent = h2.id_thought)
		LEFT JOIN {db_prefix}members AS m ON (h.id_member = m.id_member)
		LEFT JOIN {db_prefix}members AS mp ON (h2.id_member = mp.id_member)
		WHERE {query_see_thought}
		ORDER BY h.id_thought DESC LIMIT ' . ($page * 30) . ', ' . $limit,
		array(
			'me' => MID
		)
	);
	$is_touch = we::is('android,ios');
	$thoughts = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$id = $row['id_thought'];
		$mid = $row['id_master'];
		$thoughts[$row['id_thought']] = array(
			'id' => $row['id_thought'],
			'id_member' => $row['id_member'],
			'id_parent' => $row['id_parent'],
			'id_master' => $mid,
			'id_parent_owner' => $row['id_parent_owner'],
			'owner_name' => $row['owner_name'],
			'privacy' => $row['privacy'],
			'updated' => timeformat($row['updated']),
			'text' => $row['posts'] < 10 ? preg_replace('~\</?a(?:\s[^>]+)?\>(?:https?://)?~', '', parse_bbc_inline($row['thought'], 'thought', array('user' => $row['id_member']))) : parse_bbc_inline($row['thought'], 'thought', array('user' => $row['id_member'])),
		);

		$thought =& $thoughts[$row['id_thought']];
		$thought['text'] = '<span class="thought" id="thought' . $id . '" data-oid="' . $id . '" data-prv="' . $thought['privacy'] . '"' . (we::$is_member ? ' data-tid="' . $id . '"' . ($mid && $mid != $id ? ' data-mid="' . $mid . '"' : '') : '') . ($is_touch ? ' onclick="return true;"' : '') . '><span>' . $thought['text'] . '</span></span>';

		if (!empty($row['id_parent_owner']))
		{
			if (empty($row['parent_name']) && !isset($txt['deleted_thought']))
					loadLanguage('Post');
			$thought['text'] = '@<a href="<URL>?action=profile;u=' . $row['id_parent_owner'] . '">' . (empty($row['parent_name']) ? $txt['deleted_thought'] : $row['parent_name']) . '</a>&gt; ' . $thought['text'];
		}
	}
	wesql::free_result($request);

	$context['thoughts'] =& $thoughts;

	if (empty($thoughts))
		wetem::remove('thoughts');
}
