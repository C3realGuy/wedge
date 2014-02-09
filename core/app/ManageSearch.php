<?php
/**
 * Configuration for the different search methods, index management and so on.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/* The admin screen to change the search settings.

	void ManageSearch()
		- main entry point for the admin search settings screen.
		- called by ?action=admin;area=managesearch.
		- requires the admin_forum permission.
		- loads the ManageSearch template.
		- loads the Search language file.
		- calls a function based on the given sub-action.
		- defaults to sub-action 'settings'.

	void EditSearchSettings()
		- edit some general settings related to the search function.
		- called by ?action=admin;area=managesearch;sa=settings.
		- requires the admin_forum permission.
		- uses the 'modify_settings' block of the ManageSearch template.

	void EditWeights()
		- edit the relative weight of the search factors.
		- called by ?action=admin;area=managesearch;sa=weights.
		- requires the admin_forum permission.
		- uses the 'modify_weights' block of the ManageSearch template.

	void EditSearchMethod()
		- edit the search method and search index used.
		- called by ?action=admin;area=managesearch;sa=method.
		- requires the admin_forum permission.
		- uses the 'select_search_method' block of the ManageSearch
		  template.
		- allows to delete a custom index (that CreateMessageIndex() created).
		- calculates the size of the current search indexes in use.

	void CreateMessageIndex()
		- create a custom search index for the messages table.
		- called by ?action=admin;area=managesearch;sa=createmsgindex.
		- linked from the EditSearchMethod screen.
		- requires the admin_forum permission.
		- uses the 'create_index', 'create_index_progress', and
		  'create_index_done' blocks of the ManageSearch template.
		- depending on the size of the message table, the process is divided
		  in steps.
*/

function ManageSearch()
{
	global $context, $txt;

	isAllowedTo('admin_forum');

	loadLanguage('Search');
	loadTemplate('ManageSearch');

	$subActions = array(
		'settings' => 'EditSearchSettings',
		'weights' => 'EditWeights',
		'method' => 'EditSearchMethod',
		'remove' => 'EditSearchMethod',
		'createmsgindex' => 'CreateMessageIndex',
	);

	// Default the sub-action to 'edit search settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'weights';

	$context['sub_action'] = $_REQUEST['sa'];

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['manage_search'],
		'help' => 'search',
		'description' => $txt['search_settings_desc'],
		'tabs' => array(
			'weights' => array(
				'description' => $txt['search_weights_desc'],
			),
			'method' => array(
				'description' => $txt['search_method_desc'],
			),
			'settings' => array(
				'description' => $txt['search_settings_desc'],
			),
		),
	);

	// Call the right function for this sub-action.
	$subActions[$_REQUEST['sa']]();
}

function EditSearchSettings($return_config = false)
{
	global $txt, $context, $settings;

	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['search_settings_title'];

	// What are we editing anyway?
	$config_vars = array(
			// Permission...
			array('permissions', 'search_posts'),
			// Some simple settings.
			array('check', 'search_enable_captcha'),
			array('int', 'search_results_per_page'),
			array('int', 'search_max_results', 'subtext' => $txt['search_max_results_disable']),
		'',
			// Some limitations.
			array('int', 'search_floodcontrol_time', 'subtext' => $txt['search_floodcontrol_time_desc']),
	);

	// Perhaps the search method wants to add some settings?
	$settings['search_index'] = empty($settings['search_index']) ? 'standard' : $settings['search_index'];
	if (loadSearchAPI($settings['search_index']) && method_exists($settings['search_index'] . '_search', 'searchSettings'))
		call_user_func_array(array($settings['search_index'] . '_search', 'searchSettings'), array(&$config_vars));

	if ($return_config)
		return $config_vars;

	wetem::load('show_settings');

	// We'll need this for the settings.
	loadSource('ManageServer');

	// A form was submitted.
	if (isset($_REQUEST['save']))
	{
		checkSession();

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_query']);
	}

	// Prep the template!
	$context['post_url'] = '<URL>?action=admin;area=managesearch;save;sa=settings';
	$context['settings_title'] = $txt['search_settings_title'];

	prepareDBSettingContext($config_vars);
}

function EditWeights()
{
	global $txt, $context, $settings;

	$context['page_title'] = $txt['search_weights_title'];
	wetem::load('modify_weights');

	$factors = array(
		'search_weight_frequency',
		'search_weight_age',
		'search_weight_length',
		'search_weight_subject',
		'search_weight_first_message',
		'search_weight_pinned',
	);

	// A form was submitted.
	if (isset($_POST['save']))
	{
		checkSession();

		$changes = array();
		foreach ($factors as $factor)
			$changes[$factor] = (int) $_POST[$factor];
		updateSettings($changes);
	}

	$context['relative_weights'] = array('total' => 0);
	foreach ($factors as $factor)
		$context['relative_weights']['total'] += isset($settings[$factor]) ? $settings[$factor] : 0;

	foreach ($factors as $factor)
		$context['relative_weights'][$factor] = round(100 * (isset($settings[$factor]) ? $settings[$factor] : 0) / $context['relative_weights']['total'], 1);
}

function EditSearchMethod()
{
	global $txt, $context, $settings, $db_prefix;

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_method_title'];
	wetem::load('select_search_method');

	// Load any apis.
	$context['search_apis'] = loadAllSearchAPIs();

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'remove' && isset($_REQUEST['index'], $context['search_apis'][$_REQUEST['index']]))
	{
		checkSession('get');

		$search_class_name = $_REQUEST['index'] . '_search';
		$searchAPI = new $search_class_name();

		if ($searchAPI && $searchAPI->isValid() && method_exists($searchAPI, 'dropIndex'))
		{
			$searchAPI->dropIndex();
			// We should also update the information we already collected.
			$context['search_apis'][$_REQUEST['index']]['state'] = 'none';
			$context['search_apis'][$_REQUEST['index']]['size'] = 0;
		}

		// If we were using this index, officially stop using it.
		if (!empty($settings['search_index']) && $settings['search_index'] == $_REQUEST['index'])
			updateSettings(array(
				'search_index' => '',
			));
	}
	elseif (isset($_POST['save']))
	{
		checkSession();
		updateSettings(array(
			'search_index' => empty($_POST['search_index']) || (!isset($context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
			'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
			'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
		));
	}

	foreach ($context['search_apis'] as $api => $index)
	{
		if (!empty($settings['search_index']) && isset($context['search_apis'][$settings['search_index']]))
			$context['search_apis'][$api]['active'] = true;
		else
			$context['search_apis']['standard']['active'] = true;

		// We also need to reformat the size nicely.
		if (!empty($index['size']) && is_numeric($index['size']))
			$context['search_apis'][$api]['formatted_size'] = comma_format($index['size'] / 1024) . ' ' . $txt['search_method_kilobytes'];
	}

	// I'd quite like 'no index' to be the first option but we probably don't have it as such.
	$standard_index = $context['search_apis']['standard'];
	unset($context['search_apis']['standard']);
	$context['search_apis'] = array_merge(
		array('standard' => $standard_index),
		$context['search_apis']
	);

	$context['table_info'] = array(
		'data_length' => 0,
		'index_length' => 0,
	);

	// Get some info about the messages table, to show its size and index size.
	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		$request = wesql::query('
			SHOW TABLE STATUS
			FROM {string:database_name}
			LIKE {string:table_name}',
			array(
				'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
				'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
			)
		);
	else
		$request = wesql::query('
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
			)
		);

	if ($request !== false && wesql::num_rows($request) == 1)
	{
		// Only do this if the user has permission to execute this query.
		$row = wesql::fetch_assoc($request);
		$context['table_info']['data_length'] = $row['Data_length'];
		$context['table_info']['index_length'] = $row['Index_length'];
		wesql::free_result($request);
	}

	// Format the data and index length in kilobytes.
	foreach ($context['table_info'] as $type => $size)
	{
		// If it's not numeric then just break.  This database engine doesn't support size.
		if (!is_numeric($size))
			break;

		$context['table_info'][$type] = comma_format($context['table_info'][$type] / 1024) . ' ' . $txt['search_method_kilobytes'];
	}
}

function CreateMessageIndex()
{
	global $settings, $context, $db_prefix, $txt;

	// Scotty, we need more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_index_custom'];

	$messages_per_batch = 50;

	$index_properties = array(
		2 => array(
			'column_definition' => 'small',
			'step_size' => 1000000,
		),
		4 => array(
			'column_definition' => 'medium',
			'step_size' => 1000000,
			'max_size' => 16777215,
		),
		5 => array(
			'column_definition' => 'large',
			'step_size' => 100000000,
			'max_size' => 2000000000,
		),
	);

	if (isset($_REQUEST['resume']) && !empty($settings['search_custom_index_resume']))
	{
		$context['index_settings'] = unserialize($settings['search_custom_index_resume']);
		$context['start'] = (int) $context['index_settings']['resume_at'];
		unset($context['index_settings']['resume_at']);
		$context['step'] = 1;
	}
	else
	{
		$context['index_settings'] = array(
			'bytes_per_word' => isset($_REQUEST['bytes_per_word'], $index_properties[$_REQUEST['bytes_per_word']]) ? (int) $_REQUEST['bytes_per_word'] : 2,
		);
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['step'] = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;
	}

	if ($context['step'] !== 0)
		checkSession('request');

	// Step 0: let the user determine how they like their index.
	if ($context['step'] === 0)
	{
		wetem::load('create_index');
	}

	// Step 1: insert all the words.
	if ($context['step'] === 1)
	{
		wetem::load('create_index_progress');

		if ($context['start'] === 0)
		{
			loadSource('Class-DBPackages');

			$tables = wedbPackages::list_tables(false, $db_prefix . 'log_search_words');
			if (!empty($tables))
			{
				wesql::query('
					DROP TABLE {db_prefix}log_search_words',
					array(
					)
				);
			}

			create_word_search($index_properties[$context['index_settings']['bytes_per_word']]['column_definition']);

			// Temporarily switch back to not using a search index.
			if (!empty($settings['search_index']) && $settings['search_index'] == 'custom')
				updateSettings(array('search_index' => ''));

			// Don't let simultanious processes be updating the search index.
			if (!empty($settings['search_custom_index_config']))
				updateSettings(array('search_custom_index_config' => ''));
		}

		$num_messages = array(
			'done' => 0,
			'todo' => 0,
		);

		$request = wesql::query('
			SELECT id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
			FROM {db_prefix}messages
			GROUP BY todo',
			array(
				'starting_id' => $context['start'],
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];

		if (empty($num_messages['todo']))
		{
			$context['step'] = 2;
			$context['percentage'] = 80;
			$context['start'] = 0;
		}
		else
		{
			// Number of seconds before the next step.
			$stop = time() + 3;
			while (time() < $stop)
			{
				$inserts = array();
				$request = wesql::query('
					SELECT id_msg, body
					FROM {db_prefix}messages
					WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
					LIMIT {int:limit}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $messages_per_batch - 1,
						'limit' => $messages_per_batch,
					)
				);
				$forced_break = false;
				$number_processed = 0;
				while ($row = wesql::fetch_assoc($request))
				{
					// In theory it's possible for one of these to take friggin ages so add more timeout protection.
					if ($stop < time())
					{
						$forced_break = true;
						break;
					}

					$number_processed++;
					foreach (text2words($row['body'], $context['index_settings']['bytes_per_word'], true) as $id_word)
					{
						$inserts[] = array($id_word, $row['id_msg']);
					}
				}
				$num_messages['done'] += $number_processed;
				$num_messages['todo'] -= $number_processed;
				wesql::free_result($request);

				$context['start'] += $forced_break ? $number_processed : $messages_per_batch;

				if (!empty($inserts))
					wesql::insert('ignore',
						'{db_prefix}log_search_words',
						array('id_word' => 'int', 'id_msg' => 'int'),
						$inserts
					);
				if ($num_messages['todo'] === 0)
				{
					$context['step'] = 2;
					$context['start'] = 0;
					break;
				}
				else
					updateSettings(array('search_custom_index_resume' => serialize(array_merge($context['index_settings'], array('resume_at' => $context['start'])))));
			}

			// Since there are still two steps to go, 90% is the maximum here.
			$context['percentage'] = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 3) * 80;
		}
	}

	// Step 2: removing the words that occur too often and are of no use.
	elseif ($context['step'] === 2)
	{
		if ($context['index_settings']['bytes_per_word'] < 4)
			$context['step'] = 3;
		else
		{
			$stop_words = $context['start'] === 0 || empty($settings['search_stopwords']) ? array() : explode(',', $settings['search_stopwords']);
			$stop = time() + 3;
			wetem::load('create_index_progress');
			$max_messages = ceil(60 * $settings['totalMessages'] / 100);

			while (time() < $stop)
			{
				$request = wesql::query('
					SELECT id_word, COUNT(id_word) AS num_words
					FROM {db_prefix}log_search_words
					WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
					GROUP BY id_word
					HAVING COUNT(id_word) > {int:minimum_messages}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $index_properties[$context['index_settings']['bytes_per_word']]['step_size'] - 1,
						'minimum_messages' => $max_messages,
					)
				);
				while ($row = wesql::fetch_assoc($request))
					$stop_words[] = $row['id_word'];
				wesql::free_result($request);

				updateSettings(array('search_stopwords' => implode(',', $stop_words)));

				if (!empty($stop_words))
					wesql::query('
						DELETE FROM {db_prefix}log_search_words
						WHERE id_word in ({array_int:stop_words})',
						array(
							'stop_words' => $stop_words,
						)
					);

				$context['start'] += $index_properties[$context['index_settings']['bytes_per_word']]['step_size'];
				if ($context['start'] > $index_properties[$context['index_settings']['bytes_per_word']]['max_size'])
				{
					$context['step'] = 3;
					break;
				}
			}
			$context['percentage'] = 80 + round($context['start'] / $index_properties[$context['index_settings']['bytes_per_word']]['max_size'], 3) * 20;
		}
	}

	// Step 3: remove words not distinctive enough.
	if ($context['step'] === 3)
	{
		wetem::load('create_index_done');

		wesql::query('
			DELETE FROM {db_prefix}settings
			WHERE variable = {literal:search_custom_index_resume}'
		);
		updateSettings(array('search_index' => 'custom', 'search_custom_index_config' => serialize($context['index_settings'])));
	}
}

// Get the installed APIs.
function loadAllSearchAPIs()
{
	global $sourcedir;

	$apis = array();
	if ($dh = scandir($sourcedir))
	{
		foreach ($dh as $file)
		{
			if (is_file($sourcedir . '/' . $file) && preg_match('~SearchAPI-([A-Za-z\d_]+)\.php$~', $file, $matches))
			{
				// Check this is definitely a valid API!
				$fp = fopen($sourcedir . '/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, 'class ' . strtolower($matches[1]) . '_search') !== false)
				{
					require_once($sourcedir . '/' . $file);

					$index_name = strtolower($matches[1]);
					$search_class_name = $index_name . '_search';
					$searchAPI = new $search_class_name();

					// No Support? NEXT!
					if ($searchAPI->is_supported)
						$apis[$index_name] = $searchAPI->getInfo();
				}
			}
		}
	}

	return $apis;
}

function create_word_search($size)
{
	if ($size == 'small' || $size == 'medium')
		$size .= 'int'; // since small or medium => smallint or mediumint
	else
		$size = 'int'; // yeah, largeint isn't a real field

	wedbPackages::create_table(
		'{db_prefix}log_search_words',
		array(
			array('name' => 'id_word', 'type' => $size, 'unsigned' => true, 'null' => false, 'default' => 0),
			array('name' => 'id_msg', 'type' => 'int', 'unsigned' => true, 'null' => false, 'default' => 0),
		),
		array(
			array(
				'type' => 'primary',
				'columns' => array('id_word', 'id_msg'),
			),
		)
	);
}