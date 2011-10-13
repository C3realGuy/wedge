<?php
/**
 * Wedge
 *
 * Configuration of mail, and more usefully, the mail queue.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is all about mail, how we love it so. In particular it handles the admin side of
	mail configuration, as well as reviewing the mail queue - if enabled.

	void ManageMail()
		// !!

	void BrowseMailQueue()
		// !!

	void ModifyMailSettings()
		// !!

	void ClearMailQueue()
		// !!

*/

// This function passes control through to the relevant section
function ManageMail()
{
	global $context, $txt, $scripturl, $modSettings;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageMail');

	// We'll need the utility functions from here.
	loadSource('ManageServer');

	$context['page_title'] = $txt['mailqueue_title'];
	wetem::load('show_settings');

	$subActions = array(
		'browse' => 'BrowseMailQueue',
		'clear' => 'ClearMailQueue',
		'settings' => 'ModifyMailSettings',
	);

	// By default we want to browse
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'browse';
	if (empty($modSettings['mail_queue']))
		$_REQUEST['sa'] = 'settings';

	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['mailqueue_title'],
		'help' => '',
		'description' => $txt['mailqueue_desc'],
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

// Display the mail queue...
function BrowseMailQueue()
{
	global $scripturl, $context, $modSettings, $txt;

	// First, are we deleting something from the queue?
	if (isset($_REQUEST['delete']))
	{
		checkSession('post');

		wesql::query('
			DELETE FROM {db_prefix}mail_queue
			WHERE id_mail IN ({array_int:mail_ids})',
			array(
				'mail_ids' => $_REQUEST['delete'],
			)
		);
	}

	// How many items do we have?
	$request = wesql::query('
		SELECT COUNT(*) AS queue_size, MIN(time_sent) AS oldest
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize, $mailOldest) = wesql::fetch_row($request);
	wesql::free_result($request);

	$context['oldest_mail'] = empty($mailOldest) ? $txt['mailqueue_oldest_not_available'] : time_since(time() - $mailOldest);
	$context['mail_queue_size'] = comma_format($mailQueueSize);

	$listOptions = array(
		'id' => 'mail_queue',
		'title' => $txt['mailqueue_browse'],
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=mailqueue',
		'default_sort_col' => 'age',
		'no_items_label' => $txt['mailqueue_no_items'],
		'get_items' => array(
			'function' => 'list_getMailQueue',
		),
		'get_count' => array(
			'function' => 'list_getMailQueueSize',
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['mailqueue_subject'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return westr::strlen($rowData[\'subject\']) > 50 ? sprintf(\'%1$s...\', htmlspecialchars(westr::substr($rowData[\'subject\'], 0, 47))) : htmlspecialchars($rowData[\'subject\']);
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'subject',
					'reverse' => 'subject DESC',
				),
			),
			'recipient' => array(
				'header' => array(
					'value' => $txt['mailqueue_recipient'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => array(
							'recipient' => true,
						),
					),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'recipient',
					'reverse' => 'recipient DESC',
				),
			),
			'priority' => array(
				'header' => array(
					'value' => $txt['mailqueue_priority'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						// We probably have a text label with your priority.
						$txtKey = sprintf(\'mq_mpriority_%1$s\', $rowData[\'priority\']);

						// But if not, revert to priority 0.
						return isset($txt[$txtKey]) ? $txt[$txtKey] : $txt[\'mq_mpriority_1\'];
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'priority',
					'reverse' => 'priority DESC',
				),
			),
			'age' => array(
				'header' => array(
					'value' => $txt['mailqueue_age'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return time_since(time() - $rowData[\'time_sent\']);
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'time_sent',
					'reverse' => 'time_sent DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="checkbox" name="delete[]" value="\' . $rowData[\'id_mail\'] . \'">\';
					'),
					'class' => 'smalltext',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=mailqueue',
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '[<a href="' . $scripturl . '?action=admin;area=mailqueue;sa=clear;' . $context['session_query'] . '" onclick="return confirm(' . JavaScriptEscape($txt['mailqueue_clear_list_warning']) . ');">' . $txt['mailqueue_clear_list'] . '</a>] <input type="submit" name="delete_redirects" value="' . $txt['delete'] . '" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ');" class="delete">',
			),
		),
	);

	loadSource('Subs-List');
	createList($listOptions);

	loadTemplate('ManageMail');
	wetem::load('browse');
}

function list_getMailQueue($start, $items_per_page, $sort)
{
	global $txt;

	$request = wesql::query('
		SELECT
			id_mail, time_sent, recipient, priority, private, subject
		FROM {db_prefix}mail_queue
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'start' => $start,
			'sort' => $sort,
			'items_per_page' => $items_per_page,
		)
	);
	$mails = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
		if (!empty($row['private']))
			$row['subject'] = $txt['personal_message'];

		$mails[] = $row;
	}
	wesql::free_result($request);

	return $mails;
}

function list_getMailQueueSize()
{
	// How many items do we have?
	$request = wesql::query('
		SELECT COUNT(*) AS queue_size
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $mailQueueSize;
}

function ModifyMailSettings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings;

	$config_vars = array(
			// Mail queue stuff, this rocks ;)
			array('check', 'mail_queue'),
			array('int', 'mail_limit'),
			array('int', 'mail_quantity'),
		'',
			// SMTP stuff.
			array('select', 'mail_type', array($txt['mail_type_default'], 'SMTP')),
			array('text', 'smtp_host'),
			array('text', 'smtp_port'),
			array('text', 'smtp_username'),
			array('password', 'smtp_password'),
	);

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		// Make the SMTP password a little harder to see in a backup etc.
		if (!empty($_POST['smtp_password'][1]))
		{
			$_POST['smtp_password'][0] = base64_encode($_POST['smtp_password'][0]);
			$_POST['smtp_password'][1] = base64_encode($_POST['smtp_password'][1]);
		}
		checkSession();

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=mailqueue;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=mailqueue;save;sa=settings';
	$context['settings_title'] = $txt['mailqueue_settings'];

	prepareDBSettingContext($config_vars);
}

// This function clears the mail queue of all emails, and at the end redirects to browse.
function ClearMailQueue()
{
	checkSession('get');

	// This is certainly needed!
	loadSource('ScheduledTasks');

	// If we don't yet have the total to clear, find it.
	if (!isset($_GET['te']))
	{
		// How many items do we have?
		$request = wesql::query('
			SELECT COUNT(*) AS queue_size
			FROM {db_prefix}mail_queue',
			array(
			)
		);
		list ($_GET['te']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}
	else
		$_GET['te'] = (int) $_GET['te'];

	$_GET['sent'] = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;

	// Send 50 at a time, then go for a break...
	while (ReduceMailQueue(50, true, true) === true)
	{
		// Sent another 50.
		$_GET['sent'] += 50;
		pauseMailQueueClear();
	}

	return BrowseMailQueue();
}

// Used for pausing the mail queue.
function pauseMailQueueClear()
{
	global $context, $txt, $time_start;

	// Try to get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if (microtime(true) - $time_start < 5)
		return;

	$context['continue_get_data'] = '?action=admin;area=mailqueue;sa=clear;te=' . $_GET['te'] . ';sent=' . $_GET['sent'] . ';' . $context['session_query'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	wetem::load('not_done');

	// Keep browse selected.
	$context['selected'] = 'browse';

	// What percent through are we?
	$context['continue_percent'] = round(($_GET['sent'] / $_GET['te']) * 100, 1);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}

// Little function to calculate how long ago a time was.
function time_since($time_diff)
{
	global $txt;

	if ($time_diff < 0)
		$time_diff = 0;

	// Just do a bit of an if fest...
	if ($time_diff > 86400)
	{
		$days = round($time_diff / 86400, 1);
		return sprintf($days == 1 ? $txt['mq_day'] : $txt['mq_days'], $time_diff / 86400);
	}
	// Hours?
	elseif ($time_diff > 3600)
	{
		$hours = round($time_diff / 3600, 1);
		return sprintf($hours == 1 ? $txt['mq_hour'] : $txt['mq_hours'], $hours);
	}
	// Minutes?
	elseif ($time_diff > 60)
	{
		$minutes = (int) ($time_diff / 60);
		return sprintf($minutes == 1 ? $txt['mq_minute'] : $txt['mq_minutes'], $minutes);
	}
	// Otherwise must be second
	else
		return sprintf($time_diff == 1 ? $txt['mq_second'] : $txt['mq_seconds'], $time_diff);
}

?>