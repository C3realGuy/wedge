<?php
/**
 * Configuration page and task log access for scheduled tasks, to determine what should run, when, and what has previously run.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
	void ManageScheduledTasks()
		// !!!

	void ScheduledTasks()
		// !!!

	array list_getScheduledTasks()
		// !!!

	void EditTask()
		// !!!

	void TaskLog()
		// !!!

	array list_getTaskLogEntries()
		// !!!

	array list_getNumTaskLog()
		// !!!
*/

function ManageScheduledTasks()
{
	global $context, $txt;

	isAllowedTo('admin_forum');

	loadLanguage('ManageScheduledTasks');
	loadTemplate('ManageScheduledTasks');

	$subActions = array(
		'taskedit' => 'EditTask',
		'tasklog' => 'TaskLog',
		'tasks' => 'ScheduledTasks',
	);

	// We need to find what's the action.
	if (isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'tasks';

	// Now for the lovely tabs. That we all love.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['scheduled_tasks_title'],
		'description' => $txt['maintain_info'],
		'tabs' => array(
			'tasks' => array(
				'description' => $txt['maintain_tasks_desc'],
			),
			'tasklog' => array(
				'description' => $txt['scheduled_log_desc'],
			),
		),
	);

	// Call it.
	$subActions[$context['sub_action']]();
}

// List all the scheduled task in place on the forum.
function ScheduledTasks()
{
	global $context, $txt;

	// Mama, setup the template first - cause it's like the most important bit, like pickle in a sandwich.
	// ... ironically I don't like pickle. </grudge>
	wetem::load('view_scheduled_tasks');
	$context['page_title'] = $txt['maintain_tasks'];

	// Saving changes?
	if (isset($_REQUEST['save'], $_POST['enable_task']))
	{
		checkSession();

		// We'll recalculate the dates at the end!
		loadSource('ScheduledTasks');

		// Enable and disable as required.
		$enablers = array(0);
		foreach ($_POST['enable_task'] as $id => $enabled)
			if ($enabled)
				$enablers[] = (int) $id;

		// Do the update!
		wesql::query('
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = CASE WHEN id_task IN ({array_int:id_task_enable}) THEN 0 ELSE 1 END',
			array(
				'id_task_enable' => $enablers,
			)
		);

		// Pop along...
		CalculateNextTrigger();
	}

	// Want to run any of the tasks?
	if (isset($_REQUEST['run'], $_POST['run_task']))
	{
		// Let's figure out which ones they want to run.
		$tasks = array();
		foreach ($_POST['run_task'] as $task => $dummy)
			$tasks[] = (int) $task;

		// Load up the tasks.
		$request = wesql::query('
			SELECT id_task, task, sourcefile
			FROM {db_prefix}scheduled_tasks
			WHERE id_task IN ({array_int:tasks})
			LIMIT ' . count($tasks),
			array(
				'tasks' => $tasks,
			)
		);

		// Let's get it on!
		loadSource('ScheduledTasks');
		ignore_user_abort(true);
		while ($row = wesql::fetch_assoc($request))
		{
			if (!empty($row['sourcefile']))
			{
				if (strpos($row['sourcefile'], 'plugin;') === 0)
				{
					list (, $plugin_id, $filename) = explode(';', $row['sourcefile']);
					if (!empty($filename) && !empty($context['plugins_dir'][$plugin_id]))
						loadPluginSource($plugin_id, $filename);
				}
				else
					loadSource($row['sourcefile']);
			}

			$start_time = microtime(true);
			// The functions got to exist for us to use it.
			$sched_function = strpos($row['task'], '::') !== false ? explode('::', $row['task']) : 'scheduled_' . $row['task'];
			if (!is_callable($sched_function))
			{
				$sched_function = $row['task'];
				if (!is_callable($sched_function))
					continue;
			}

			// Try to stop a timeout, this would be bad...
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			// Do the task...
			$completed = call_user_func($sched_function);

			// Log that we did it ;)
			if ($completed)
			{
				$total_time = round(microtime(true) - $start_time, 3);
				wesql::insert('',
					'{db_prefix}log_scheduled_tasks',
					array('id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float'),
					array($row['id_task'], time(), $total_time)
				);
			}
		}
		wesql::free_result($request);
		redirectexit('action=admin;area=scheduledtasks;done');
	}

	$listOptions = array(
		'id' => 'scheduled_tasks',
		'title' => $txt['maintain_tasks'],
		'base_href' => '<URL>?action=admin;area=scheduledtasks',
		'get_items' => array(
			'function' => 'list_getScheduledTasks',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_name'],
					'style' => 'width: 40%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							<a href="<URL>?action=admin;area=scheduledtasks;sa=taskedit;tid=%1$d">%2$s</a><div class="smalltext">%3$s</div>',
						'params' => array(
							'id' => false,
							'name' => false,
							'desc' => false,
						),
					),
				),
			),
			'next_due' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_next_time'],
				),
				'data' => array(
					'db' => 'next_time',
					'class' => 'smalltext',
				),
			),
			'regularity' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_regularity'],
				),
				'data' => array(
					'db' => 'regularity',
					'class' => 'smalltext',
				),
			),
			'enabled' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_enabled'],
					'style' => 'width: 6%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' =>
							'<input type="hidden" name="enable_task[%1$d]" id="task_%1$d" value="0"><input type="checkbox" name="enable_task[%1$d]" id="task_check_%1$d" %2$s>',
						'params' => array(
							'id' => false,
							'checked_state' => false,
						),
					),
					'style' => 'text-align: center;',
				),
			),
			'run_now' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_run_now'],
					'style' => 'width: 12%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' =>
							'<input type="checkbox" name="run_task[%1$d]" id="run_task_%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=admin;area=scheduledtasks',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="save" value="' . $txt['scheduled_tasks_save_changes'] . '" class="save">
					<input type="submit" name="run" value="' . $txt['scheduled_tasks_run_now'] . '" class="submit">',
				'class' => 'floatright',
				'style' => 'text-align: right;',
			),
			array(
				'position' => 'after_title',
				'value' => $txt['scheduled_tasks_time_offset'],
				'class' => 'smalltext',
			),
		),
	);

	loadSource('Subs-List');
	createList($listOptions);

	wetem::load('view_scheduled_tasks');

	$context['tasks_were_run'] = isset($_GET['done']);
}

function list_getScheduledTasks($start, $items_per_page, $sort)
{
	global $txt;

	$request = wesql::query('
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks',
		array(
		)
	);
	$known_tasks = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Find the next for regularity - don't offset as it's always server time!
		$offset = sprintf($txt['scheduled_task_reg_starting'], date('H:i', $row['time_offset']));
		$repeating = sprintf($txt['scheduled_task_reg_repeating'], $row['time_regularity'], $txt['scheduled_task_reg_unit_' . $row['time_unit']]);

		$known_tasks[] = array(
			'id' => $row['id_task'],
			'function' => $row['task'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat(($row['next_time'] == 0 ? time() : $row['next_time']), true, 'server'),
			'disabled' => $row['disabled'],
			'checked_state' => $row['disabled'] ? '' : 'checked',
			'regularity' => $offset . ', ' . $repeating,
		);
	}
	wesql::free_result($request);

	return $known_tasks;
}

// Function for editing a task.
function EditTask()
{
	global $context, $txt;

	// Just set up some lovely context stuff.
	$context[$context['admin_menu_name']]['current_subsection'] = 'tasks';
	wetem::load('edit_scheduled_tasks');
	$context['page_title'] = $txt['scheduled_task_edit'];
	$context['server_time'] = timeformat(time(), false, 'server');

	// Cleaning...
	if (!isset($_GET['tid']))
		fatal_lang_error('no_access', false);
	$_GET['tid'] = (int) $_GET['tid'];

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// We'll need this for calculating the next event.
		loadSource('ScheduledTasks');

		// Do we have a valid offset?
		preg_match('~(\d{1,2}):(\d{1,2})~', $_POST['offset'], $matches);

		// If a half is empty then assume zero offset!
		if (!isset($matches[2]) || $matches[2] > 59)
			$matches[2] = 0;
		if (!isset($matches[1]) || $matches[1] > 23)
			$matches[1] = 0;

		// Now the offset is easy; easy peasy - except we need to offset by a few hours...
		$offset = $matches[1] * 3600 + $matches[2] * 60 - date('Z');

		// The other time bits are simple!
		$interval = max((int) $_POST['regularity'], 1);
		$unit = in_array(substr($_POST['unit'], 0, 1), array('m', 'h', 'd', 'w')) ? substr($_POST['unit'], 0, 1) : 'd';

		// Don't allow one minute intervals.
		if ($interval == 1 && $unit == 'm')
			$interval = 2;

		// Is it disabled?
		$disabled = !isset($_POST['enabled']) ? 1 : 0;

		// Do the update!
		wesql::query('
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = {int:disabled}, time_offset = {int:time_offset}, time_unit = {string:time_unit},
				time_regularity = {int:time_regularity}
			WHERE id_task = {int:id_task}',
			array(
				'disabled' => $disabled,
				'time_offset' => $offset,
				'time_regularity' => $interval,
				'id_task' => $_GET['tid'],
				'time_unit' => $unit,
			)
		);

		// Check the next event.
		CalculateNextTrigger($_GET['tid'], true);

		// Return to the main list.
		redirectexit('action=admin;area=scheduledtasks');
	}

	// Load the task, understand? Que? Que?
	$request = wesql::query('
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task = {int:id_task}',
		array(
			'id_task' => $_GET['tid'],
		)
	);

	// Should never, ever, happen!
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('no_access', false);

	while ($row = wesql::fetch_assoc($request))
	{
		$context['task'] = array(
			'id' => $row['id_task'],
			'function' => $row['task'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat($row['next_time'] == 0 ? time() : $row['next_time'], true, 'server'),
			'disabled' => $row['disabled'],
			'offset' => $row['time_offset'],
			'regularity' => $row['time_regularity'],
			'offset_formatted' => date('H:i', $row['time_offset']),
			'unit' => $row['time_unit'],
		);
	}
	wesql::free_result($request);
}

// Show the log of all tasks that have taken place.
function TaskLog()
{
	global $context, $txt;

	// Let's load the language just in case we are outside the Scheduled area.
	loadLanguage('ManageScheduledTasks');

	// Empty the log?
	if (!empty($_POST['removeAll']))
	{
		checkSession();

		wesql::query('
			TRUNCATE {db_prefix}log_scheduled_tasks',
			array(
			)
		);
	}

	// Setup the list.
	$listOptions = array(
		'id' => 'task_log',
		'items_per_page' => 30,
		'title' => $txt['scheduled_log'],
		'no_items_label' => $txt['scheduled_log_empty'],
		'base_href' => $context['admin_area'] == 'scheduledtasks' ? '<URL>?action=admin;area=scheduledtasks;sa=tasklog' : '<URL>?action=admin;area=logs;sa=tasklog',
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getTaskLogEntries',
		),
		'get_count' => array(
			'function' => 'list_getNumTaskLogEntries',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_name'],
				),
				'data' => array(
					'db' => 'name'
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['scheduled_log_time_run'],
				),
				'data' => array(
					'function' => function ($rowData) {
						return timeformat($rowData['time_run'], true);
					},
				),
				'sort' => array(
					'default' => 'lst.id_log DESC',
					'reverse' => 'lst.id_log',
				),
			),
			'time_taken' => array(
				'header' => array(
					'value' => $txt['scheduled_log_time_taken'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => $txt['scheduled_log_time_taken_seconds'],
						'params' => array(
							'time_taken' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'lst.time_taken',
					'reverse' => 'lst.time_taken DESC',
				),
			),
		),
		'form' => array(
			'href' => $context['admin_area'] == 'scheduledtasks' ? '<URL>?action=admin;area=scheduledtasks;sa=tasklog' : '<URL>?action=admin;area=logs;sa=tasklog',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="removeAll" value="' . $txt['scheduled_log_empty_log'] . '" class="delete">',
				'style' => 'text-align: right;',
			),
			array(
				'position' => 'after_title',
				'value' => $txt['scheduled_tasks_time_offset'],
				'class' => 'smalltext',
			),
		),
	);

	loadSource('Subs-List');
	createList($listOptions);

	wetem::load('show_list');
	$context['default_list'] = 'task_log';

	// Make it all look tify.
	$context[$context['admin_menu_name']]['current_subsection'] = 'tasklog';
	$context['page_title'] = $txt['scheduled_log'];
}

function list_getTaskLogEntries($start, $items_per_page, $sort)
{
	global $txt;

	$request = wesql::query('
		SELECT lst.id_log, lst.id_task, lst.time_run, lst.time_taken, st.task
		FROM {db_prefix}log_scheduled_tasks AS lst
			INNER JOIN {db_prefix}scheduled_tasks AS st ON (st.id_task = lst.id_task)
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
		)
	);
	$log_entries = array();
	while ($row = wesql::fetch_assoc($request))
		$log_entries[] = array(
			'id' => $row['id_log'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'time_run' => $row['time_run'],
			'time_taken' => $row['time_taken'],
		);
	wesql::free_result($request);

	return $log_entries;
}

function list_getNumTaskLogEntries()
{
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_scheduled_tasks',
		array(
		)
	);
	list ($num_entries) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $num_entries;
}
