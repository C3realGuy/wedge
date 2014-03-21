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

	void Home()
		- prepares the homepage data.
		- uses the Home template (main block) and language file.
		- is accessed via index.php, if $settings['default_index'] == 'Home'.
		- to create your own custom entry point, just set $settings['default_index'] to 'Homepage'
		  and create your own Homepage.php file so it won't be overwritten by Wedge updates!
*/

// Welcome to the show.
function Home()
{
	global $context, $txt, $settings;

	// Here are a few variables to make it easy to enable or disable a feature on the default homepage...
	$context['home_show']['topics'] = true;
	$context['home_show']['thoughts'] = true;
	$context['home_show']['boards'] = true;
	$context['home_show']['info'] = true;

	// Load the 'Home' template.
	loadTemplate('Home');
	loadLanguage('Home');

	// Set a canonical URL for this page.
	$context['canonical_url'] = '<URL>';

	$context['page_title'] = isset($txt['homepage_title']) ? $txt['homepage_title'] : $context['forum_name'] . ' - ' . $txt['home'];

	/* Share any thoughts...?
	------------------------- */

	if ($context['home_show']['thoughts'])
	{
		loadSource('Thoughts');
		embedThoughts();
	}

	/* Retrieve the categories and boards.
	-------------------------------------- */

	if ($context['home_show']['boards'])
	{
		// Load our block.
		loadTemplate('Boards');
		wetem::add('boards');

		loadSource('Subs-BoardIndex');
		$context['categories'] = getBoardIndex(array(
			'include_categories' => true,
			'base_level' => 0,
			'parent_id' => 0,
			'category' => 0,
			'set_latest_post' => true,
			'countChildPosts' => !empty($settings['countChildPosts']),
		));
	}

	/* Taken directly from Boards.php, used to generate the info center.
	-------------------------------------------------------------------- */

	if ($context['home_show']['info'])
	{
		// In this case, we want to load the info center -- where? Most convenient places for your device.
		// We will simply create the info_center layer at the end of the main (or sidebar) block and inject the blocks into it.
		wetem::add(we::is('mobile') ? 'default' : 'sidebar', array(
			'info_center' => array(
				'info_center_statistics',
				'info_center_usersonline',
				'info_center_personalmsg',
			),
		));

		loadTemplate('InfoCenter');
		loadSource('Subs-MembersOnline');

		// Get the user online list.
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

		call_hook('info_center');
	}
}
