<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');










function getMembersOnlineStats($membersOnlineOptions)
{
	global $settings, $txt;


	$allowed_sort_options = array(
		'log_time',
		'real_name',
		'show_online',
		'group_name',
	);

	if (!isset($membersOnlineOptions['sort']))
	{
		$membersOnlineOptions['sort'] = 'log_time';
		$membersOnlineOptions['reverse_sort'] = true;
	}


	elseif (!in_array($membersOnlineOptions['sort'], $allowed_sort_options))
		trigger_error('Sort method for getMembersOnlineStats() function is not allowed', E_USER_NOTICE);


	$membersOnlineStats = array(
		'users_online' => array(),
		'list_users_online' => array(),
		'online_groups' => array(),
		'num_guests' => 0,
		'num_spiders' => 0,
		'num_buddies' => 0,
		'num_users_hidden' => 0,
		'num_users_online' => 0,
	);


	$spiders = array();
	$spider_finds = array();
	if (!empty($settings['spider_mode']) && !empty($settings['show_spider_online']) && ($settings['show_spider_online'] < 3 || allowedTo('admin_forum')) && !empty($settings['spider_name_cache']))
		$spiders = unserialize($settings['spider_name_cache']);


	$request = wesql::query('
		SELECT
			lo.id_member, lo.log_time, lo.id_spider, mem.real_name, mem.member_name,
			mem.show_online, mg.id_group, mg.group_name
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_mem_group} THEN mem.id_post_group ELSE mem.id_group END)',
		array(
			'reg_mem_group' => 0,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		if (empty($row['real_name']))
		{

			if ($row['id_spider'] && isset($spiders[$row['id_spider']]))
			{
				$spider_finds[$row['id_spider']] = isset($spider_finds[$row['id_spider']]) ? $spider_finds[$row['id_spider']] + 1 : 1;
				$membersOnlineStats['num_spiders']++;
			}

			$membersOnlineStats['num_guests']++;

			continue;
		}

		elseif (empty($row['show_online']) && empty($membersOnlineOptions['show_hidden']))
		{

			$membersOnlineStats['num_users_hidden']++;
			continue;
		}

		$link = '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';


		$is_buddy = in_array($row['id_member'], we::$user['buddies']);
		if ($is_buddy)
		{
			$membersOnlineStats['num_buddies']++;
			$link .= ' <span class="contact"></span>';
		}


		$membersOnlineStats['users_online'][$row[$membersOnlineOptions['sort']] . $row['member_name']] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'group' => $row['id_group'],
			'href' => '<URL>?action=profile;u=' . $row['id_member'],
			'link' => $link,
			'is_buddy' => $is_buddy,
			'hidden' => empty($row['show_online']),
			'is_last' => false,
		);


		$membersOnlineStats['list_users_online'][$row[$membersOnlineOptions['sort']] . $row['member_name']] = empty($row['show_online']) ? $link . ' <span class="notonline" title="' . $txt['hidden'] . '"></span>' : $link;


		if (!isset($membersOnlineStats['online_groups'][$row['id_group']]))
			$membersOnlineStats['online_groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name']
			);
	}
	wesql::free_result($request);


	if (!empty($spider_finds) && $settings['show_spider_online'] > 1)
		foreach ($spider_finds as $id => $count)
		{
			$link = $spiders[$id] . ($count > 1 ? ' (' . $count . ')' : '');
			$sort = $membersOnlineOptions['sort'] === 'log_time' && $membersOnlineOptions['reverse_sort'] ? 0 : 'zzz_';
			$membersOnlineStats['users_online'][$sort . $spiders[$id]] = array(
				'id' => 0,
				'username' => $spiders[$id],
				'name' => $link,
				'group' => $txt['spiders'],
				'href' => '',
				'link' => $link,
				'is_buddy' => false,
				'hidden' => false,
				'is_last' => false,
			);
			$membersOnlineStats['list_users_online'][$sort . $spiders[$id]] = $link;
		}


	if (!empty($membersOnlineStats['users_online']))
	{

		$sortFunction = empty($membersOnlineOptions['reverse_sort']) ? 'ksort' : 'krsort';


		$sortFunction($membersOnlineStats['users_online']);
		$sortFunction($membersOnlineStats['list_users_online']);


		$userKeys = array_keys($membersOnlineStats['users_online']);
		$membersOnlineStats['users_online'][end($userKeys)]['is_last'] = true;
	}


	ksort($membersOnlineStats['online_groups']);


	$membersOnlineStats['num_users_online'] = count($membersOnlineStats['users_online']) + $membersOnlineStats['num_users_hidden'] - (isset($settings['show_spider_online']) && $settings['show_spider_online'] > 1 ? count($spider_finds) : 0);

	return $membersOnlineStats;
}


function trackStatsUsersOnline($total_users_online)
{
	global $settings;

	$settingsToUpdate = array();


	if (!isset($settings['mostOnline']) || $total_users_online >= $settings['mostOnline'])
		$settingsToUpdate = array(
			'mostOnline' => $total_users_online,
			'mostDate' => time()
		);

	$date = strftime('%Y-%m-%d', forum_time(false));


	if (!isset($settings['mostOnlineUpdated']) || $settings['mostOnlineUpdated'] != $date)
	{
		$request = wesql::query('
			SELECT most_on
			FROM {db_prefix}log_activity
			WHERE date = {date:date}
			LIMIT 1',
			array(
				'date' => $date,
			)
		);


		if (wesql::num_rows($request) === 0)
		{
			wesql::insert('ignore',
				'{db_prefix}log_activity',
				array('date' => 'date', 'most_on' => 'int'),
				array($date, $total_users_online)
			);
		}

		else
		{
			list ($settings['mostOnlineToday']) = wesql::fetch_row($request);

			if ($total_users_online > $settings['mostOnlineToday'])
				trackStats(array('most_on' => $total_users_online));

			$total_users_online = max($total_users_online, $settings['mostOnlineToday']);
		}
		wesql::free_result($request);

		$settingsToUpdate['mostOnlineUpdated'] = $date;
		$settingsToUpdate['mostOnlineToday'] = $total_users_online;
	}


	elseif ($total_users_online > $settings['mostOnlineToday'])
	{
		trackStats(array('most_on' => $total_users_online));
		$settingsToUpdate['mostOnlineToday'] = $total_users_online;
	}

	if (!empty($settingsToUpdate))
		updateSettings($settingsToUpdate);
}


function getMembersOnlineDetails($type = 'board')
{
	global $context, $board, $topic, $txt;

	if ($type !== 'board' && $type !== 'topic')
		return;

	$context['view_members'] = array();
	$context['view_members_list'] = array();
	$context['view_num_hidden'] = 0;

	$request = wesql::query('
		SELECT
			lo.id_member, lo.log_time, mem.real_name, mem.member_name,
			mem.show_online, mg.id_group, mg.group_name
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_member_group} THEN mem.id_post_group ELSE mem.id_group END)
		WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
		array(
			'reg_member_group' => 0,
			'in_url_string' => $type === 'board' ? ('s:5:"board";i:' . $board . ';') : ('s:5:"topic";i:' . $topic . ';'),
			'session' => we::$is_guest ? 'ip' . we::$user['ip'] : session_id(),
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		if (empty($row['id_member']))
			continue;

		$link = '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';

		$is_buddy = in_array($row['id_member'], we::$user['buddies']);
		if ($is_buddy)
			$link .= ' <span class="contact"></span>';


		if (!empty($row['show_online']) || !empty($context['can_moderate_members']) || !empty($context['can_moderate_board']))
			$context['view_members_list'][$row['log_time'] . $row['member_name']] = empty($row['show_online']) ? $link . ' <span class="notonline" title="' . $txt['hidden'] . '"></span>' : $link;
		$context['view_members'][$row['log_time'] . $row['member_name']] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'group' => $row['id_group'],
			'href' => '<URL>?action=profile;u=' . $row['id_member'],
			'link' => $link,
			'is_buddy' => $is_buddy,
			'hidden' => empty($row['show_online']),
		);

		if (empty($row['show_online']))
			$context['view_num_hidden']++;
	}
	$context['view_num_guests'] = wesql::num_rows($request) - count($context['view_members']);
	wesql::free_result($request);


	krsort($context['view_members_list']);
	krsort($context['view_members']);
}
