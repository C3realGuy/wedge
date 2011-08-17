<?php
/**
 * Wedge
 *
 * Generates reports for administrators over users, their permissions and so on.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is exclusively for generating reports to help assist forum
	administrators keep track of their forum configuration and state. The
	core report generation is done in two areas. Firstly, a report "generator"
	will fill context with relevant data. Secondly, the choice of sub-template
	will determine how this data is shown to the user. It has the following
	functions:

	void ReportsMain()
		- requires the admin_forum permission.
		- loads the Reports template and language files.
		- decides which type of report to generate, if this isn't passed
		  through the querystring it will set the report_type sub-template to
		  force the user to choose which type.
		- when generating a report chooses which sub_template to use.
		- depends on the cal_enabled setting, and many of the other cal_
		  settings.
		- will call the relevant report generation function.
		- if generating report will call finishTables before returning.
		- accessed through ?action=admin;area=reports.

	void xxxxxxReport()
		- functions ending with "Report" are responsible for generating data
		  for reporting.
		- they are all called from ReportsMain.
		- never access the context directly, but use the data handling
		  functions to do so.

	void newTable(string title = '', string default_value = '',
			string shading = 'all', string width_normal = 'auto',
			string align_normal = 'center', string width_shaded = 'auto',
			string align_shaded = 'auto')
		- the core of this file, it creates a new, but empty, table of data in
		  context, ready for filling using addData().
		- takes a lot of possible attributes, these have the following effect:
			+ title = Title to be displayed with this data table.
			+ default_value = Value to be displayed if a key is missing from a
			  row.
			+ shading = Should the left, top or both (all) parts of the table
			  be shaded?
			+ width_normal = width of an unshaded column (auto means not
			  defined).
			+ align_normal = alignment of data in an unshaded column.
			+ width_shaded = width of a shaded column (auto means not
			  defined).
			+ align_shaded = alignment of data in a shaded column.
		- fills the context variable current_table with the ID of the table
		  created.
		- keeps track of the current table count using context variable
		  table_count.

	void addData(array inc_data, int custom_table = null)
		- adds an array of data into an existing table.
		- if there are no existing tables, will create one with default
		  attributes.
		- if custom_table isn't specified, it will use the last table created,
		  if it is specified and doesn't exist the function will return false.
		- if a set of keys have been specified, the function will check each
		  required key is present in the incoming data. If this data is missing
		  the current tables default value will be used.
		- if any key in the incoming data begins with '#sep#', the function
		  will add a separator accross the table at this point.
		- once the incoming data has been sanitized, it is added to the table.

	void addSeparator(string title = '', int custom_table = null)
		- adds a separator with title given by attribute "title" after the
		  current row in the table.
		- if there are no existing tables, will create one with default
		  attributes.
		- if custom_table isn't specified, it will use the last table created,
		  if it is specified and doesn't exist the function will return false.
		- if the table is currently having data added by column this may have
		  unpredictable visual results.

	void finishTables()
		- is (unfortunately) required to create some useful variables for
		  templates.
		- foreach data table created, it will count the number of rows and
		  columns in the table.
		- will also create a max_width variable for the table, to give an
		  estimate width for the whole table - if it can.

	void setKeys(string method = 'rows', array keys = array(),
			bool reverse = false)
		- sets the current set of "keys" expected in each data array passed to
		  addData. It also sets the way we are adding data to the data table.
		- method specifies whether the data passed to addData represents a new
		  column, or a new row.
		- keys is an array whose keys are the keys for data being passed to
		  addData().
		- if reverse is set to true, then the values of the variable "keys"
		  are used as oppossed to the keys(!)
*/

// Handling function for generating reports.
function ReportsMain()
{
	global $txt, $modSettings, $context, $scripturl;

	// Only admins, only EVER admins!
	isAllowedTo('admin_forum');

	// Let's get our things running...
	loadTemplate('Reports');
	loadLanguage('Reports');

	$context['page_title'] = $txt['generate_reports'];

	// These are the types of reports which exist - and the functions to generate them.
	$context['report_types'] = array(
		'boards' => 'BoardReport',
		'board_perms' => 'BoardPermissionsReport',
		'member_groups' => 'MemberGroupsReport',
		'group_perms' => 'GroupPermissionsReport',
		'staff' => 'StaffReport',
	);

	$is_first = 0;
	foreach ($context['report_types'] as $k => $temp)
		$context['report_types'][$k] = array(
			'id' => $k,
			'title' => isset($txt['gr_type_' . $k]) ? $txt['gr_type_' . $k] : $type['id'],
			'description' => isset($txt['gr_type_desc_' . $k]) ? $txt['gr_type_desc_' . $k] : null,
			'function' => $temp,
			'is_first' => $is_first++ == 0,
		);

	// If they haven't choosen a report type which is valid, send them off to the report type chooser!
	if (empty($_REQUEST['rt']) || !isset($context['report_types'][$_REQUEST['rt']]))
	{
		loadSubTemplate('report_type');
		return;
	}
	$context['report_type'] = $_REQUEST['rt'];

	// What are valid templates for showing reports?
	$reportTemplates = array(
		'main' => array(
			'layers' => null,
		),
		'print' => array(
			'layers' => array('print'),
		),
	);

	// Specific template? Use that instead of main!
	if (isset($_REQUEST['st'], $reportTemplates[$_REQUEST['st']]))
	{
		loadSubTemplate($_REQUEST['st']);

		// Are we disabling the other layers - print friendly for example?
		if ($reportTemplates[$_REQUEST['st']]['layers'] !== null)
			hideChrome($reportTemplates[$_REQUEST['st']]['layers']);
	}

	// Make the page title more descriptive.
	$context['page_title'] .= ' - ' . (isset($txt['gr_type_' . $context['report_type']]) ? $txt['gr_type_' . $context['report_type']] : $context['report_type']);
	// Now generate the data.
	$context['report_types'][$context['report_type']]['function']();

	// Finish the tables before exiting - this is to help the templates a little more.
	finishTables();
}

// Standard report about what settings the boards have.
function BoardReport()
{
	global $context, $txt, $modSettings;

	// Load the permission profiles.
	loadSource('ManagePermissions');
	loadLanguage('ManagePermissions');
	loadPermissionProfiles();

	// Get every moderator.
	$request = wesql::query('
		SELECT mods.id_board, mods.id_member, mem.real_name
		FROM {db_prefix}moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)',
		array(
		)
	);
	$moderators = array();
	while ($row = wesql::fetch_assoc($request))
		$moderators[$row['id_board']][] = $row['real_name'];
	wesql::free_result($request);

	// Get all the possible membergroups!
	$request = wesql::query('
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$groups = array(-1 => $txt['guest_title'], 0 => $txt['full_member']);
	if (empty($modSettings['allow_guestAccess']))
		unset($groups[-1]);
	while ($row = wesql::fetch_assoc($request))
		$groups[$row['id_group']] = empty($row['online_color']) ? $row['group_name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>';
	wesql::free_result($request);

	// All the fields we'll show.
	$boardSettings = array(
		'category' => $txt['board_category'],
		'parent' => $txt['board_parent'],
		'num_topics' => $txt['board_num_topics'],
		'num_posts' => $txt['board_num_posts'],
		'count_posts' => $txt['board_count_posts'],
		'theme' => $txt['board_theme'],
		'override_theme' => $txt['board_override_theme'],
		'profile' => $txt['board_profile'],
		'moderators' => $txt['board_moderators'],
		'groups' => $txt['board_groups'],
	);

	// Do it in columns, it's just easier.
	setKeys('cols');

	// Go through each board!
	$request = wesql::query('
		SELECT b.id_board, b.name, b.num_posts, b.num_topics, b.count_posts, b.member_groups, b.override_theme, b.id_profile,
			c.name AS cat_name, IFNULL(par.name, {string:text_none}) AS parent_name, IFNULL(th.value, {string:text_none}) AS theme_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			LEFT JOIN {db_prefix}boards AS par ON (par.id_board = b.id_parent)
			LEFT JOIN {db_prefix}themes AS th ON (th.id_theme = b.id_theme AND th.variable = {string:name})',
		array(
			'name' => 'name',
			'text_none' => $txt['none'],
		)
	);
	$boards = array(0 => array('name' => $txt['global_boards']));
	while ($row = wesql::fetch_assoc($request))
	{
		// Each board has it's own table.
		newTable($row['name'], '', 'left', 'auto', 'left', 200, 'left');

		// First off, add in the side key.
		addData($boardSettings);

		// Format the profile name.
		$profile_name = $context['profiles'][$row['id_profile']]['name'];

		// Create the main data array.
		$boardData = array(
			'category' => $row['cat_name'],
			'parent' => $row['parent_name'],
			'num_posts' => $row['num_posts'],
			'num_topics' => $row['num_topics'],
			'count_posts' => empty($row['count_posts']) ? $txt['yes'] : $txt['no'],
			'theme' => $row['theme_name'],
			'profile' => $profile_name,
			'override_theme' => $row['override_theme'] ? $txt['yes'] : $txt['no'],
			'moderators' => empty($moderators[$row['id_board']]) ? $txt['none'] : implode(', ', $moderators[$row['id_board']]),
		);

		// Work out the membergroups who can access it.
		$allowedGroups = explode(',', $row['member_groups']);
		foreach ($allowedGroups as $key => $group)
		{
			if (isset($groups[$group]))
				$allowedGroups[$key] = $groups[$group];
			else
				unset($allowedGroups[$key]);
		}
		$boardData['groups'] = implode(', ', $allowedGroups);

		// Next add the main data.
		addData($boardData);
	}
	wesql::free_result($request);
}

// Generate a report on the current permissions by board and membergroup.
function BoardPermissionsReport()
{
	global $context, $txt, $modSettings;

	// Get as much memory as possible as this can be big.
	@ini_set('memory_limit', '256M');

	if (isset($_REQUEST['boards']))
	{
		if (!is_array($_REQUEST['boards']))
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $k => $dummy)
			$_REQUEST['boards'][$k] = (int) $dummy;

		$board_clause = 'id_board IN ({array_int:boards})';
	}
	else
		$board_clause = '1=1';

	if (isset($_REQUEST['groups']))
	{
		if (!is_array($_REQUEST['groups']))
			$_REQUEST['groups'] = explode(',', $_REQUEST['groups']);
		foreach ($_REQUEST['groups'] as $k => $dummy)
			$_REQUEST['groups'][$k] = (int) $dummy;

		$group_clause = 'id_group IN ({array_int:groups})';
	}
	else
		$group_clause = '1=1';

	// Fetch all the board names.
	$request = wesql::query('
		SELECT id_board, name, id_profile
		FROM {db_prefix}boards
		WHERE ' . $board_clause . '
		ORDER BY id_board',
		array(
			'boards' => isset($_REQUEST['boards']) ? $_REQUEST['boards'] : array(),
		)
	);
	$profiles = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$boards[$row['id_board']] = array(
			'name' => $row['name'],
			'profile' => $row['id_profile'],
		);
		$profiles[] = $row['id_profile'];
	}
	wesql::free_result($request);

	// Get all the possible membergroups, except admin!
	$request = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE ' . $group_clause . '
			AND id_group != {int:admin_group}' . (empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'admin_group' => 1,
			'min_posts' => -1,
			'newbie_group' => 4,
			'groups' => isset($_REQUEST['groups']) ? $_REQUEST['groups'] : array(),
		)
	);
	if (!isset($_REQUEST['groups']) || in_array(-1, $_REQUEST['groups']) || in_array(0, $_REQUEST['groups']))
		$member_groups = array('col' => '', -1 => $txt['membergroups_guests'], 0 => $txt['membergroups_members']);
	else
		$member_groups = array('col' => '');
	while ($row = wesql::fetch_assoc($request))
		$member_groups[$row['id_group']] = $row['group_name'];
	wesql::free_result($request);

	if (empty($modSettings['allow_guestAccess']))
		unset($member_groups[-1]);

	// Make sure that every group is represented - plus in rows!
	setKeys('rows', $member_groups);

	// Cache every permission setting, to make sure we don't miss any allows.
	$permissions = array();
	$board_permissions = array();
	$request = wesql::query('
		SELECT id_profile, id_group, add_deny, permission
		FROM {db_prefix}board_permissions
		WHERE id_profile IN ({array_int:profile_list})
			AND ' . $group_clause . (empty($modSettings['permission_enable_deny']) ? '
			AND add_deny = {int:not_deny}' : '') . '
		ORDER BY id_profile, permission',
		array(
			'profile_list' => $profiles,
			'not_deny' => 1,
			'groups' => isset($_REQUEST['groups']) ? $_REQUEST['groups'] : array(),
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		foreach ($boards as $id => $board)
			if ($board['profile'] == $row['id_profile'])
				$board_permissions[$id][$row['id_group']][$row['permission']] = $row['add_deny'];

		// Make sure we get every permission.
		if (!isset($permissions[$row['permission']]))
		{
			// This will be reused on other boards.
			$permissions[$row['permission']] = array(
				'title' => isset($txt['board_perms_name_' . $row['permission']]) ? $txt['board_perms_name_' . $row['permission']] : $row['permission'],
			);
		}
	}
	wesql::free_result($request);

	// Now cycle through the board permissions array... lots to do ;)
	foreach ($board_permissions as $board => $groups)
	{
		// Create the table for this board first.
		newTable($boards[$board]['name'], 'x', 'all', 100, 'center', 200, 'left');

		// Add the header row - shows all the membergroups.
		addData($member_groups);

		// Add the separator.
		addSeparator($txt['board_perms_permission']);

		// Here cycle through all the detected permissions.
		foreach ($permissions as $id_perm => $perm_info)
		{
			// Is this identical to the global?
			$identicalGlobal = $board == 0 ? false : true;

			// Default data for this row.
			$curData = array('col' => $perm_info['title']);

			// Now cycle each membergroup in this set of permissions.
			foreach ($member_groups as $id_group => $name)
			{
				// Don't overwrite the key column!
				if ($id_group === 'col')
					continue;

				$group_permissions = isset($groups[$id_group]) ? $groups[$id_group] : array();

				// Do we have any data for this group?
				// Set the data for this group to be the local permission.
				if (isset($group_permissions[$id_perm]))
					$curData[$id_group] = $group_permissions[$id_perm];
				// Otherwise means it's set to disallow..
				else
					$curData[$id_group] = 'x';

				// Now actually make the data for the group look right.
				if (empty($curData[$id_group]))
					$curData[$id_group] = '<span style="color: red;">' . $txt['board_perms_deny'] . '</span>';
				elseif ($curData[$id_group] == 1)
					$curData[$id_group] = '<span style="color: darkgreen;">' . $txt['board_perms_allow'] . '</span>';
				else
					$curData[$id_group] = 'x';

				// Embolden those permissions different from global (makes it a lot easier!)
				if (@$board_permissions[0][$id_group][$id_perm] != @$group_permissions[$id_perm])
					$curData[$id_group] = '<strong>' . $curData[$id_group] . '</strong>';
			}

			// Now add the data for this permission.
			addData($curData);
		}
	}
}

// Show what the membergroups are made of.
function MemberGroupsReport()
{
	global $context, $txt, $settings, $modSettings;

	// Fetch all the board names.
	$request = wesql::query('
		SELECT id_board, name, member_groups, id_profile
		FROM {db_prefix}boards',
		array(
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		if (trim($row['member_groups']) == '')
			$groups = array(1);
		else
			$groups = array_merge(array(1), explode(',', $row['member_groups']));

		$boards[$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'profile' => $row['id_profile'],
			'groups' => $groups,
		);
	}
	wesql::free_result($request);

	// Standard settings.
	$mgSettings = array(
		'name' => '',
		'#sep#1' => $txt['member_group_settings'],
		'color' => $txt['member_group_color'],
		'min_posts' => $txt['member_group_min_posts'],
		'max_messages' => $txt['member_group_max_messages'],
		'stars' => $txt['member_group_stars'],
		'#sep#2' => $txt['member_group_access'],
	);

	// Add on the boards!
	foreach ($boards as $board)
		$mgSettings['board_' . $board['id']] = $board['name'];

	// Add all the membergroup settings, plus we'll be adding in columns!
	setKeys('cols', $mgSettings);

	// Only one table this time!
	newTable($txt['gr_type_member_groups'], '-', 'all', 100, 'center', 200, 'left');

	// Get the shaded column in.
	addData($mgSettings);

	// Now start cycling the membergroups!
	$request = wesql::query('
		SELECT mg.id_group, mg.group_name, mg.online_color, mg.min_posts, mg.max_messages, mg.stars,
			CASE WHEN bp.permission IS NOT NULL OR mg.id_group = {int:admin_group} THEN 1 ELSE 0 END AS can_moderate
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}board_permissions AS bp ON (bp.id_group = mg.id_group AND bp.id_profile = {int:default_profile} AND bp.permission = {string:moderate_board})
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'admin_group' => 1,
			'default_profile' => 1,
			'newbie_group' => 4,
			'moderate_board' => 'moderate_board',
		)
	);

	// Cache them so we get regular members too.
	$rows = array(
		array(
			'id_group' => -1,
			'group_name' => $txt['membergroups_guests'],
			'online_color' => '',
			'min_posts' => -1,
			'max_messages' => null,
			'stars' => ''
		),
		array(
			'id_group' => 0,
			'group_name' => $txt['membergroups_members'],
			'online_color' => '',
			'min_posts' => -1,
			'max_messages' => null,
			'stars' => ''
		),
	);
	while ($row = wesql::fetch_assoc($request))
		$rows[] = $row;
	wesql::free_result($request);

	if (empty($modSettings['allow_guestAccess']))
		unset ($rows[0]);

	foreach ($rows as $row)
	{
		$row['stars'] = explode('#', $row['stars']);

		$group = array(
			'name' => $row['group_name'],
			'color' => empty($row['online_color']) ? '-' : '<span style="color: ' . $row['online_color'] . ';">' . $row['online_color'] . '</span>',
			'min_posts' => $row['min_posts'] == -1 ? 'N/A' : $row['min_posts'],
			'max_messages' => $row['max_messages'],
			'stars' => !empty($row['stars'][0]) && !empty($row['stars'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/' . $row['stars'][1] . '">', $row['stars'][0]) : '',
		);

		// Board permissions.
		foreach ($boards as $board)
			$group['board_' . $board['id']] = in_array($row['id_group'], $board['groups']) ? '<span style="color: darkgreen;">' . $txt['board_perms_allow'] . '</span>' : 'x';

		addData($group);
	}
}

// Show the large variety of group permissions assigned to each membergroup.
function GroupPermissionsReport()
{
	global $context, $txt, $modSettings;

	// We might need some of the other permissions strings
	loadLanguage('ManagePermissions');

	if (isset($_REQUEST['groups']))
	{
		if (!is_array($_REQUEST['groups']))
			$_REQUEST['groups'] = explode(',', $_REQUEST['groups']);
		foreach ($_REQUEST['groups'] as $k => $dummy)
			$_REQUEST['groups'][$k] = (int) $dummy;
		$_REQUEST['groups'] = array_diff($_REQUEST['groups'], array(3));

		$clause = 'id_group IN ({array_int:groups})';
	}
	else
		$clause = 'id_group != {int:moderator_group}';

	// Get all the possible membergroups, except admin!
	$request = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE ' . $clause . '
			AND id_group != {int:admin_group}' . (empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'admin_group' => 1,
			'min_posts' => -1,
			'newbie_group' => 4,
			'moderator_group' => 3,
			'groups' => isset($_REQUEST['groups']) ? $_REQUEST['groups'] : array(),
		)
	);
	if (!isset($_REQUEST['groups']) || in_array(-1, $_REQUEST['groups']) || in_array(0, $_REQUEST['groups']))
		$groups = array('col' => '', -1 => $txt['membergroups_guests'], 0 => $txt['membergroups_members']);
	else
		$groups = array('col' => '');
	while ($row = wesql::fetch_assoc($request))
		$groups[$row['id_group']] = $row['group_name'];
	wesql::free_result($request);

	if (empty($modSettings['allow_guestAccess']))
		unset($groups[-1]);

	// Make sure that every group is represented!
	setKeys('rows', $groups);

	// Create the table first.
	newTable($txt['gr_type_group_perms'], '-', 'all', 100, 'center', 200, 'left');

	// Show all the groups
	addData($groups);

	// Add a separator
	addSeparator($txt['board_perms_permission']);

	// Now the big permission fetch!
	$request = wesql::query('
		SELECT id_group, add_deny, permission
		FROM {db_prefix}permissions
		WHERE ' . $clause . (empty($modSettings['permission_enable_deny']) ? '
			AND add_deny = {int:not_denied}' : '') . '
		ORDER BY permission',
		array(
			'not_denied' => 1,
			'moderator_group' => 3,
			'groups' => isset($_REQUEST['groups']) ? $_REQUEST['groups'] : array(),
		)
	);
	$lastPermission = null;
	while ($row = wesql::fetch_assoc($request))
	{
		// If this is a new permission flush the last row.
		if ($row['permission'] != $lastPermission)
		{
			// Send the data!
			if ($lastPermission !== null)
				addData($curData);

			// Add the permission name in the left column.
			$curData = array('col' => isset($txt['group_perms_name_' . $row['permission']]) ? $txt['group_perms_name_' . $row['permission']] : (isset($txt['permissionname_' . $row['permission']]) ? $txt['permissionname_' . $row['permission']] : $row['permission']));

			$lastPermission = $row['permission'];
		}

		// Good stuff - add the permission to the list!
		if ($row['add_deny'])
			$curData[$row['id_group']] = '<span style="color: darkgreen;">' . $txt['board_perms_allow'] . '</span>';
		else
			$curData[$row['id_group']] = '<span style="color: red;">' . $txt['board_perms_deny'] . '</span>';
	}
	wesql::free_result($request);

	// Flush the last data!
	addData($curData);
}

// Report for showing all the forum staff members - quite a feat!
function StaffReport()
{
	global $context, $txt;

	loadSource('Subs-Members');

	// Fetch all the board names.
	$request = wesql::query('
		SELECT id_board, name
		FROM {db_prefix}boards',
		array(
		)
	);
	$boards = array();
	while ($row = wesql::fetch_assoc($request))
		$boards[$row['id_board']] = $row['name'];
	wesql::free_result($request);

	// Get every moderator.
	$request = wesql::query('
		SELECT mods.id_board, mods.id_member
		FROM {db_prefix}moderators AS mods',
		array(
		)
	);
	$moderators = array();
	$local_mods = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$moderators[$row['id_member']][] = $row['id_board'];
		$local_mods[$row['id_member']] = $row['id_member'];
	}
	wesql::free_result($request);

	// Get a list of global moderators (i.e. members with moderation powers).
	$global_mods = array_intersect(membersAllowedTo('moderate_board', 0), membersAllowedTo('approve_posts', 0), membersAllowedTo('remove_any', 0), membersAllowedTo('modify_any', 0));

	// How about anyone else who is special?
	$allStaff = array_merge(membersAllowedTo('admin_forum'), membersAllowedTo('manage_membergroups'), membersAllowedTo('manage_permissions'), $local_mods, $global_mods);

	// Make sure everyone is there once - no admin less important than any other!
	$allStaff = array_unique($allStaff);

	// This is a bit of a cop out - but we're protecting their forum, really!
	if (count($allStaff) > 300)
		fatal_lang_error('report_error_too_many_staff');

	// Get all the possible membergroups!
	$request = wesql::query('
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$groups = array(0 => $txt['full_member']);
	while ($row = wesql::fetch_assoc($request))
		$groups[$row['id_group']] = empty($row['online_color']) ? $row['group_name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>';
	wesql::free_result($request);

	// All the fields we'll show.
	$staffSettings = array(
		'position' => $txt['report_staff_position'],
		'moderates' => $txt['report_staff_moderates'],
		'posts' => $txt['report_staff_posts'],
		'last_login' => $txt['report_staff_last_login'],
	);

	// Do it in columns, it's just easier.
	setKeys('cols');

	// Get each member!
	$request = wesql::query('
		SELECT id_member, real_name, id_group, posts, last_login
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:staff_list})
		ORDER BY real_name',
		array(
			'staff_list' => $allStaff,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Each member gets their own table!.
		newTable($row['real_name'], '', 'left', 'auto', 'left', 200, 'center');

		// First off, add in the side key.
		addData($staffSettings);

		// Create the main data array.
		$staffData = array(
			'position' => isset($groups[$row['id_group']]) ? $groups[$row['id_group']] : $groups[0],
			'posts' => $row['posts'],
			'last_login' => timeformat($row['last_login']),
			'moderates' => array(),
		);

		// What do they moderate?
		if (in_array($row['id_member'], $global_mods))
			$staffData['moderates'] = '<em>' . $txt['report_staff_all_boards'] . '</em>';
		elseif (isset($moderators[$row['id_member']]))
		{
			// Get the names
			foreach ($moderators[$row['id_member']] as $board)
				if (isset($boards[$board]))
					$staffData['moderates'][] = $boards[$board];

			$staffData['moderates'] = implode(', ', $staffData['moderates']);
		}
		else
			$staffData['moderates'] = '<em>' . $txt['report_staff_no_boards'] . '</em>';

		// Next add the main data.
		addData($staffData);
	}
	wesql::free_result($request);
}

// This function creates a new table of data, most functions will only use it once.
function newTable($title = '', $default_value = '', $shading = 'all', $width_normal = 'auto', $align_normal = 'center', $width_shaded = 'auto', $align_shaded = 'auto')
{
	global $context;

	// Set the table count if needed.
	if (empty($context['table_count']))
		$context['table_count'] = 0;

	// Create the table!
	$context['tables'][$context['table_count']] = array(
		'title' => $title,
		'default_value' => $default_value,
		'shading' => array(
			'left' => $shading == 'all' || $shading == 'left',
			'top' => $shading == 'all' || $shading == 'top',
		),
		'width' => array(
			'normal' => $width_normal,
			'shaded' => $width_shaded,
		),
		'align' => array(
			'normal' => $align_normal,
			'shaded' => $align_shaded,
		),
		'data' => array(),
	);

	$context['current_table'] = $context['table_count'];

	// Increment the count...
	$context['table_count']++;
}

// Add an extra slice of data to the table
function addData($inc_data, $custom_table = null)
{
	global $context;

	// No tables? Create one even though we are probably already in a bad state!
	if (empty($context['table_count']))
		newTable();

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$custom_table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// If we have keys, sanitise the data...
	if (!empty($context['keys']))
	{
		// Basically, check every key exists!
		foreach ($context['keys'] as $key => $dummy)
		{
			$data[$key] = array(
				'v' => empty($inc_data[$key]) ? $context['tables'][$table]['default_value'] : $inc_data[$key],
			);
			// Special "hack" the adding separators when doing data by column.
			if (substr($key, 0, 5) == '#sep#')
				$data[$key]['separator'] = true;
		}
	}
	else
	{
		$data = $inc_data;
		foreach ($data as $key => $value)
		{
			$data[$key] = array(
				'v' => $value,
			);
			if (substr($key, 0, 5) == '#sep#')
				$data[$key]['separator'] = true;
		}
	}

	// Is it by row?
	if (empty($context['key_method']) || $context['key_method'] == 'rows')
	{
		// Add the data!
		$context['tables'][$table]['data'][] = $data;
	}
	// Otherwise, tricky!
	else
	{
		foreach ($data as $key => $item)
			$context['tables'][$table]['data'][$key][] = $item;
	}
}

// Add a separator row, only really used when adding data by rows.
function addSeparator($title = '', $custom_table = null)
{
	global $context;

	// No tables - return?
	if (empty($context['table_count']))
		return;

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// Plumb in the separator
	$context['tables'][$table]['data'][] = array(0 => array(
		'separator' => true,
		'v' => $title
	));
}

// This does the necessary count of table data before displaying them.
function finishTables()
{
	global $context;

	if (empty($context['tables']))
		return;

	// Loop through each table counting up some basic values, to help with the templating.
	foreach ($context['tables'] as $id => $table)
	{
		$context['tables'][$id]['id'] = $id;
		$context['tables'][$id]['row_count'] = count($table['data']);
		$curElement = current($table['data']);
		$context['tables'][$id]['column_count'] = count($curElement);

		// Work out the rough width - for templates like the print template. Without this we might get funny tables.
		if ($table['shading']['left'] && $table['width']['shaded'] != 'auto' && $table['width']['normal'] != 'auto')
			$context['tables'][$id]['max_width'] = $table['width']['shaded'] + ($context['tables'][$id]['column_count'] - 1) * $table['width']['normal'];
		elseif ($table['width']['normal'] != 'auto')
			$context['tables'][$id]['max_width'] = $context['tables'][$id]['column_count'] * $table['width']['normal'];
		else
			$context['tables'][$id]['max_width'] = 'auto';
	}
}

// Set the keys in use by the tables - these ensure entries MUST exist if the data isn't sent.
function setKeys($method = 'rows', $keys = array(), $reverse = false)
{
	global $context;

	// Do we want to use the keys of the keys as the keys? :P
	if ($reverse)
		$context['keys'] = array_flip($keys);
	else
		$context['keys'] = $keys;

	// Rows or columns?
	$context['key_method'] = $method == 'rows' ? 'rows' : 'cols';
}

?>