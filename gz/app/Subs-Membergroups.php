<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');







































function deleteMembergroups($groups)
{
	global $settings;


	if (!is_array($groups))
		$groups = array((int) $groups);
	else
	{
		$groups = array_unique($groups);


		foreach ($groups as $key => $value)
			$groups[$key] = (int) $value;
	}


	$protected_groups = array(-1, 0, 1, 3, 4);


	if (!allowedTo('admin_forum'))
	{
		$request = wesql::query('
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		wesql::free_result($request);
	}


	$groups = array_diff($groups, array_unique($protected_groups));
	if (empty($groups))
		return false;


	$request = wesql::query('
		SELECT group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		logAction('delete_group', array('group' => $row['group_name']), 'admin');
	wesql::free_result($request);


	wesql::query('
		DELETE FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);


	wesql::query('
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	wesql::query('
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	wesql::query('
		DELETE FROM {db_prefix}board_groups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	wesql::query('
		DELETE FROM {db_prefix}group_moderators
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);


	wesql::query('
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);


	wesql::query('
		UPDATE {db_prefix}members
		SET id_group = {int:regular_group}
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'regular_group' => 0,
		)
	);


	wesql::query('
		UPDATE {db_prefix}membergroups
		SET id_parent = {int:uninherited}
		WHERE id_parent IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'uninherited' => -2,
		)
	);


	$request = wesql::query('
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:additional_groups_explode}, additional_groups) != 0',
		array(
			'additional_groups_explode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = wesql::fetch_assoc($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	wesql::free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
		updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups))));


	$request = wesql::query('
		SELECT id_board, member_groups
		FROM {db_prefix}boards
		WHERE FIND_IN_SET({raw:member_groups_explode}, member_groups) != 0',
		array(
			'member_groups_explode' => implode(', member_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = wesql::fetch_assoc($request))
		$updates[$row['member_groups']][] = $row['id_board'];
	wesql::free_result($request);

	foreach ($updates as $member_groups => $boardArray)
		wesql::query('
			UPDATE {db_prefix}boards
			SET member_groups = {string:member_groups}
			WHERE id_board IN ({array_int:board_lists})',
			array(
				'board_lists' => $boardArray,
				'member_groups' => implode(',', array_diff(explode(',', $member_groups), $groups)),
			)
		);


	wesql::query('ALTER TABLE {db_prefix}membergroups AUTO_INCREMENT = 1');


	updateStats('postgroups');


	cache_put_data('member-groups', null);
	clean_cache('css');


	$settings_update = array('settings_updated' => time());

	if (!empty($settings['ban_group']) && in_array($settings['ban_group'], $groups))
		$settings_update['ban_group'] = 0;

	if (isset($settings['spider_group']) && in_array($settings['spider_group'], $groups))
		$settings_update['spider_group'] = 0;

	updateSettings($settings_update);


	return true;
}


function removeMembersFromGroups($members, $groups = null, $permissionCheckDone = false)
{
	global $settings;


	if (!$permissionCheckDone)
		isAllowedTo('manage_membergroups');


	updateSettings(array('settings_updated' => time()));


	if (!is_array($members))
		$members = array((int) $members);
	else
	{
		$members = array_unique($members);


		foreach ($members as $key => $value)
			$members[$key] = (int) $value;
	}


	if ($groups === null || $groups == 1 || (is_array($groups) && in_array(1, $groups)))
	{
		$admins = array();
		listMembergroupMembers_Href($admins, 1);


		$non_changing_admins = array_diff(array_keys($admins), $members);

		if (empty($non_changing_admins))
			$members = array_diff($members, array_keys($admins));
	}


	if (empty($members))
		return false;
	elseif ($groups === null)
	{

		wesql::query('
			UPDATE {db_prefix}members
			SET
				id_group = {int:regular_member},
				additional_groups = {string:blank_string}
			WHERE id_member IN ({array_int:member_list})' . (allowedTo('admin_forum') ? '' : '
				AND id_group != {int:admin_group}
				AND FIND_IN_SET({int:admin_group}, additional_groups) = 0'),
			array(
				'member_list' => $members,
				'regular_member' => 0,
				'admin_group' => 1,
				'blank_string' => '',
			)
		);

		updateStats('postgroups', $members);


		foreach ($members as $member)
			logAction('removed_all_groups', array('member' => $member), 'admin');

		return true;
	}
	elseif (!is_array($groups))
		$groups = array((int) $groups);
	else
	{
		$groups = array_unique($groups);


		foreach ($groups as $key => $value)
			$groups[$key] = (int) $value;
	}


	$implicitGroups = array(-1, 0, 3);
	$request = wesql::query('
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	$group_names = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if ($row['min_posts'] != -1)
			$implicitGroups[] = $row['id_group'];
		else
			$group_names[$row['id_group']] = $row['group_name'];
	}
	wesql::free_result($request);


	$groups = array_diff($groups, $implicitGroups);


	if (!allowedTo('admin_forum'))
	{
		$request = wesql::query('
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		$protected_groups = array(1);
		while ($row = wesql::fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		wesql::free_result($request);


		$groups = array_diff($groups, array_unique($protected_groups));
	}


	if (empty($groups) || empty($members))
		return false;


	$log_inserts = array();
	$request = wesql::query('
		SELECT id_member, id_group
		FROM {db_prefix}members AS members
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		array(
			'group_list' => $groups,
			'member_list' => $members,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$log_inserts[] = array(
			time(), 3, MID, we::$user['ip'], 'removed_from_group',
			0, 0, 0, serialize(array('group' => $group_names[$row['id_group']], 'member' => $row['id_member'])),
		);
	wesql::free_result($request);

	wesql::query('
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		array(
			'group_list' => $groups,
			'member_list' => $members,
			'regular_member' => 0,
		)
	);


	$request = wesql::query('
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE (FIND_IN_SET({raw:additional_groups_implode}, additional_groups) != 0)
			AND id_member IN ({array_int:member_list})
		LIMIT ' . count($members),
		array(
			'member_list' => $members,
			'additional_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = wesql::fetch_assoc($request))
	{

		foreach (explode(',', $row['additional_groups']) as $group)
			if (in_array($group, $groups))
				$log_inserts[] = array(
					time(), 3, MID, get_ip_identifier(we::$user['ip']), 'removed_from_group',
					0, 0, 0, serialize(array('group' => $group_names[$group], 'member' => $row['id_member'])),
				);

		$updates[$row['additional_groups']][] = $row['id_member'];
	}
	wesql::free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
		wesql::query('
			UPDATE {db_prefix}members
			SET additional_groups = {string:additional_groups}
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $memberArray,
				'additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups)),
			)
		);


	updateStats('postgroups', $members);


	cache_put_data('member-groups', null);


	if (!empty($log_inserts) && !empty($settings['log_enabled_admin']))
		wesql::insert('',
			'{db_prefix}log_actions',
			array(
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'int', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			),
			$log_inserts
		);


	return true;
}












function addMembersToGroup($members, $group, $type = 'auto', $permissionCheckDone = false)
{
	global $settings;


	if (!$permissionCheckDone)
		isAllowedTo('manage_membergroups');


	updateSettings(array('settings_updated' => time()));

	if (!is_array($members))
		$members = array((int) $members);
	else
	{
		$members = array_unique($members);


		foreach ($members as $key => $value)
			$members[$key] = (int) $value;
	}
	$group = (int) $group;


	$implicitGroups = array(-1, 0, 3);
	$request = wesql::query('
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $group,
		)
	);
	$group_names = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if ($row['min_posts'] != -1)
			$implicitGroups[] = $row['id_group'];
		else
			$group_names[$row['id_group']] = $row['group_name'];
	}
	wesql::free_result($request);


	if (in_array($group, $implicitGroups) || empty($members))
		return false;


	if (!allowedTo('admin_forum') && $group == 1)
		return false;

	elseif (!allowedTo('admin_forum'))
	{
		$request = wesql::query('
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			array(
				'current_group' => $group,
				'limit' => 1,
			)
		);
		list ($is_protected) = wesql::fetch_row($request);
		wesql::free_result($request);


		if ($is_protected == 1)
			return false;
	}


	if ($type == 'only_additional')
		wesql::query('
			UPDATE {db_prefix}members
			SET additional_groups = CASE WHEN additional_groups = {string:blank_string} THEN {string:id_group_string} ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			array(
				'member_list' => $members,
				'id_group' => $group,
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
				'blank_string' => '',
			)
		);
	elseif ($type == 'only_primary' || $type == 'force_primary')
		wesql::query('
			UPDATE {db_prefix}members
			SET id_group = {int:id_group}
			WHERE id_member IN ({array_int:member_list})' . ($type == 'force_primary' ? '' : '
				AND id_group = {int:regular_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0'),
			array(
				'member_list' => $members,
				'id_group' => $group,
				'regular_group' => 0,
			)
		);
	elseif ($type == 'auto')
		wesql::query('
			UPDATE {db_prefix}members
			SET
				id_group = CASE WHEN id_group = {int:regular_group} THEN {int:id_group} ELSE id_group END,
				additional_groups = CASE WHEN id_group = {int:id_group} THEN additional_groups
					WHEN additional_groups = {string:blank_string} THEN {string:id_group_string}
					ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			array(
				'member_list' => $members,
				'regular_group' => 0,
				'id_group' => $group,
				'blank_string' => '',
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
			)
		);

	else
		trigger_error('addMembersToGroup(): Unknown type \'' . $type . '\'', E_USER_WARNING);


	updateStats('postgroups', $members);


	cache_put_data('member-groups', null);


	$log_inserts = array();
	foreach ($members as $member)
		$log_inserts[] = array(
			time(), 3, MID, get_ip_identifier(we::$user['ip']), 'added_to_group',
			0, 0, 0, serialize(array('group' => $group_names[$group], 'member' => $member)),
		);

	if (!empty($log_inserts) && !empty($settings['log_enabled_admin']))
		wesql::insert('',
			'{db_prefix}log_actions',
			array(
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'int', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			),
			$log_inserts
		);

	return true;
}

function listMembergroupMembers_Href(&$members, $membergroup, $limit = null)
{
	$request = wesql::query('
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_group = {int:id_group} OR FIND_IN_SET({int:id_group}, additional_groups) != 0' . ($limit === null ? '' : '
		LIMIT ' . ($limit + 1)),
		array(
			'id_group' => $membergroup,
		)
	);
	$members = array();
	while ($row = wesql::fetch_assoc($request))
		$members[$row['id_member']] = '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
	wesql::free_result($request);


	if ($limit !== null && count($members) > $limit)
	{
		array_pop($members);
		return true;
	}
	else
		return false;
}


function cache_getMembergroupList()
{
	$request = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE hidden = {int:not_hidden}
			AND id_group != {int:mod_group}
			AND online_color != {string:blank_string}
		ORDER BY display_order',
		array(
			'not_hidden' => 0,
			'mod_group' => 3,
			'blank_string' => '',
		)
	);
	$groupCache = array();
	while ($row = wesql::fetch_assoc($request))
		$groupCache[] = '<a href="<URL>?action=groups;sa=members;group=' . $row['id_group'] . '" class="group' . $row['id_group'] . '">' . $row['group_name'] . '</a>';
	wesql::free_result($request);

	return array(
		'data' => $groupCache,
		'expires' => time() + 3600,
		'refresh_eval' => 'return $GLOBALS[\'settings\'][\'settings_updated\'] > ' . time() . ';',
	);
}

function list_getMembergroups($start, $items_per_page, $sort, $membergroup_type)
{
	$groups = array();


	$request = wesql::query('
		SELECT id_group, group_name, min_posts, stars, 0 AS num_members
		FROM {db_prefix}membergroups
		WHERE min_posts ' . ($membergroup_type === 'post_count' ? '!=' : '=') . ' -1' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY {raw:sort}',
		array(
			'is_protected' => 1,
			'sort' => $sort,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$groups[$row['id_group']] = array(
			'id_group' => $row['id_group'],
			'group_name' => $row['group_name'],
			'min_posts' => $row['min_posts'],
			'stars' => $row['stars'],
			'num_members' => $row['num_members'],
		);
	wesql::free_result($request);


	if (!empty($groups))
	{
		if ($membergroup_type === 'post_count')
		{
			$query = wesql::query('
				SELECT id_post_group AS id_group, COUNT(*) AS num_members
				FROM {db_prefix}members
				WHERE id_post_group IN ({array_int:group_list})
				GROUP BY id_post_group',
				array(
					'group_list' => array_keys($groups),
				)
			);
			while ($row = wesql::fetch_assoc($query))
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			wesql::free_result($query);
		}

		else
		{
			$query = wesql::query('
				SELECT id_group, COUNT(*) AS num_members
				FROM {db_prefix}members
				WHERE id_group IN ({array_int:group_list})
				GROUP BY id_group',
				array(
					'group_list' => array_keys($groups),
				)
			);
			while ($row = wesql::fetch_assoc($query))
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			wesql::free_result($query);

			$query = wesql::query('
				SELECT mg.id_group, COUNT(*) AS num_members
				FROM {db_prefix}membergroups AS mg
					INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
						AND mem.id_group != mg.id_group
						AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
				WHERE mg.id_group IN ({array_int:group_list})
				GROUP BY mg.id_group',
				array(
					'group_list' => array_keys($groups),
					'blank_string' => '',
				)
			);
			while ($row = wesql::fetch_assoc($query))
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			wesql::free_result($query);
		}
	}


	if (substr($sort, 0, 1) == '1' || strpos($sort, ', 1') !== false)
	{
		$sort_ascending = strpos($sort, 'DESC') === false;

		foreach ($groups as $group)
			$sort_array[] = $group['id_group'] != 3 ? (int) $group['num_members'] : -1;

		array_multisort($sort_array, $sort_ascending ? SORT_ASC : SORT_DESC, SORT_REGULAR, $groups);
	}

	return $groups;
}
