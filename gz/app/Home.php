<?php








if (!defined('WEDGE'))
	die('Hacking attempt...');












function Home()
{
	global $context, $txt, $settings;


	loadTemplate('Home');


	$context['canonical_url'] = '<URL>';

	$context['page_title'] = isset($txt['homepage_title']) ? $txt['homepage_title'] : $context['forum_name'] . ' - ' . $txt['home'];
	$commands = empty($settings['homepage_custom']) ? "topics\nthoughts\nboards\ninfo" : $settings['homepage_custom'];

	foreach (explode("\n", $commands) as $item)
	{
		$item = trim($item);
		$param = strpos($item, ':') !== false ? strrchr($item, ':') : '';
		$item = str_replace($param, '', $item);




		if ($item == 'blurb')
			wetem::add('home_blurb' . $param);




		elseif ($item == 'topics')
			wetem::add('home_topics' . $param);




		elseif ($item == 'thoughts')
		{
			loadSource('Thoughts');
			embedThoughts($param ? substr($param, 1) : 10);
		}




		elseif ($item == 'boards')
		{

			loadTemplate('Boards');
			wetem::add('boards' . $param);

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




		elseif ($item == 'info')
		{


			wetem::add(we::is('mobile') ? 'default' : 'sidebar', array(
				'info_center' => array(
					'info_center_statistics',
					'info_center_usersonline',
					'info_center_personalmsg',
				),
			));

			loadTemplate('InfoCenter');
			loadSource('Subs-MembersOnline');


			$membersOnlineOptions = array(
				'show_hidden' => allowedTo('moderate_forum'),
				'sort' => 'log_time',
				'reverse_sort' => true,
			);
			$context += getMembersOnlineStats($membersOnlineOptions);

			$context['show_buddies'] = !empty(we::$user['buddies']);


			if (!empty($settings['show_group_key']))
				$context['membergroups'] = cache_quick_get('membergroup_list', 'Subs-Membergroups', 'cache_getMembergroupList', array());


			if (!empty($settings['trackStats']))
				trackStatsUsersOnline($context['num_guests'] + $context['num_spiders'] + $context['num_users_online']);

			$context['show_stats'] = allowedTo('view_stats') && !empty($settings['trackStats']);
			$context['show_member_list'] = allowedTo('view_mlist');
			$context['show_who'] = allowedTo('who_view') && !empty($settings['who_enabled']);

			call_hook('info_center');
		}
	}
}
