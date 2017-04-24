<?php










if (defined('WEDGE'))
	return;

define('WEDGE', 'SSI');


global $settings, $context, $sc, $topic, $board, $txt, $time_start;
global $maintenance, $msubject, $mmessage, $mbname, $webmaster_email, $cookiename;
global $db_server, $db_connection, $db_name, $db_user, $db_prefix, $db_persist;
global $db_error_send, $db_last_error, $ssi_db_user, $ssi_db_passwd, $db_passwd;

$time_start = microtime(true);

define('ROOT_DIR', str_replace('\\', '/', dirname(dirname(__FILE__))));
define('APP_DIR', ROOT_DIR . '/core/app');


require_once(ROOT_DIR . '/Settings.php');
require_once(ROOT_DIR . '/index.php');

$ssi_error_reporting = error_reporting(E_ALL | E_STRICT);







$ssi_on_error_method = false;


if ($maintenance == 2 && (!isset($ssi_maintenance_off) || $ssi_maintenance_off !== true))
	exit($mmessage);


loadSource(array(
	'Class-System',
	'QueryString',
	'Subs',
	'Errors',
	'Load',
	'Security',
));


loadConstants();
loadDatabase();
loadSettings();


unset($board, $topic, $_REQUEST['GLOBALS'], $_COOKIE['GLOBALS'], $_REQUEST['ssi_skin'], $_COOKIE['ssi_skin'], $_REQUEST['context']);


if (isset($ssi_gzip) && $ssi_gzip === true && (int) ini_get('zlib.output_compression') < 1 && ini_get('output_handler') != 'ob_gzhandler')
	ob_start('ob_gzhandler');
else
	$settings['enableCompressedOutput'] = 0;

ob_start('ob_sessrewrite');


if (!headers_sent())
	loadSession();
else
{
	if (isset($_COOKIE[session_name()]) || isset($_REQUEST[session_name()]))
	{

		$temp = error_reporting(error_reporting() & !E_WARNING);
		loadSession();
		error_reporting($temp);
	}

	if (!isset($_SESSION['session_value']))
	{
		$_SESSION['session_var'] = substr(md5(mt_rand() . session_id() . mt_rand()), 0, rand(7, 12));
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
	}
	$sc = $_SESSION['session_value'];
}

header('Content-Type: text/html; charset=UTF-8');

$context['linktree'] = array();


we::getInstance();


loadPermissions();



if (empty($settings['allow_guestAccess']) && we::$is_guest)
{
	we::$user['query_see_board'] = '0=1';
	we::$user['query_wanna_see_board'] = '0=1';
}


loadTheme(isset($ssi_skin) && strpos($ssi_skin, '..') === false ? $ssi_skin : '');


call_hook('ssi');


if (isset($_REQUEST['ssi_ban']) || (isset($ssi_ban) && $ssi_ban === true))
	is_not_banned();


setupThemeContext();


if (isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['is_cli']) && session_id() == '')
{
	loadLanguage('Errors', '', false);
	trigger_error($txt['ssi_session_broken'], E_USER_NOTICE);
}


if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote'))
	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];


$disallowed = array('queryPosts', 'fetchPosts', 'fetchMember', 'fetchGroupMembers', 'queryMembers');
if (isset($_GET['ssi_function']) && function_exists('ssi_' . $_GET['ssi_function']) && !in_array($_GET['ssi_function'], $disallowed))
{
	call_user_func('ssi_' . $_GET['ssi_function']);
	exit;
}
if (isset($_GET['ssi_function']))
	exit;

elseif (basename($_SERVER['PHP_SELF']) == 'SSI.php')
{
	loadLanguage('Errors', '', false);
	exit(sprintf($txt['ssi_not_direct'], we::$is_admin ? '\'' . addslashes(__FILE__) . '\'' : '\'core/SSI.php\''));
}

error_reporting($ssi_error_reporting);

return true;


function ssi_run()
{
	if (!isset($_GET['ssi_function']) || $_GET['ssi_function'] != 'shutdown')
		obExit();
}


function ssi_welcome($output_method = 'echo')
{
	global $txt, $settings;

	if ($output_method == 'echo')
	{
		if (we::$is_guest)
			echo sprintf((empty($settings['registration_method']) || $settings['registration_method'] != 3) ? $txt['welcome_guest'] : $txt['welcome_guest_noregister'], $txt['guest_title']);
		else
			echo $txt['hello_member'], ' <strong>', we::$user['name'], '</strong>', allowedTo('pm_read') ? ', ' . str_replace('{new}', number_context('unread_pms', we::$user['unread_messages']), number_context('you_have_msg', we::$user['messages'])) : '';
	}

	else
		return we::$user;
}


function ssi_menubar($output_method = 'echo')
{
	global $context;

	if ($output_method == 'echo')
		template_menu();

	else
		return $context['menu_items'];
}


function ssi_logout($redirect_to = '', $output_method = 'echo')
{
	global $context, $txt;

	if ($redirect_to != '')
		$_SESSION['logout_url'] = $redirect_to;


	if (we::$is_guest)
		return false;

	$link = '<a href="' . SCRIPT . '?action=logout;' . $context['session_query'] . '">' . $txt['logout'] . '</a>';

	if ($output_method == 'echo')
		echo $link;
	else
		return $link;
}


function ssi_recentPosts($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo', $limit_body = true)
{
	global $settings;


	if ($exclude_boards === null && !empty($settings['recycle_enable']) && $settings['recycle_board'] > 0)
		$exclude_boards = array($settings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));


	if (is_array($include_boards) || (int) $include_boards === $include_boards)
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	elseif ($include_boards != null)
		$include_boards = array();


	$query_where = '
		m.id_msg >= {int:min_message_id}
		' . (empty($exclude_boards) ? '' : '
		AND b.id_board NOT IN ({array_int:exclude_boards})') . '
		' . ($include_boards === null ? '' : '
		AND b.id_board IN ({array_int:include_boards})') . '
		AND {query_wanna_see_board}' . (empty(we::$user['can_skip_approval']) ? '
		AND m.approved = 1' : '');

	$query_where_params = array(
		'include_boards' => $include_boards === null ? '' : $include_boards,
		'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
		'min_message_id' => $settings['maxMsgID'] - 25 * $num_recent,
	);


	return ssi_queryPosts($query_where, $query_where_params, $num_recent, 'm.id_msg DESC', $output_method, $limit_body);
}


function ssi_fetchPosts($post_ids, $override_permissions = false, $output_method = 'echo')
{

	$post_ids = is_array($post_ids) ? $post_ids : array($post_ids);


	$query_where = '
		m.id_msg IN ({array_int:message_list})' . ($override_permissions ? '' : '
			AND {query_wanna_see_board}') . (empty(we::$user['can_skip_approval']) ? '
			AND m.approved = {int:is_approved}' : '');
	$query_where_params = array(
		'message_list' => $post_ids,
		'is_approved' => 1,
	);


	return ssi_queryPosts($query_where, $query_where_params, '', 'm.id_msg DESC', $output_method, false, $override_permissions);
}


function ssi_queryPosts($query_where = '', $query_where_params = array(), $query_limit = 10, $query_order = 'm.id_msg DESC', $output_method = 'echo', $limit_body = false, $override_permissions = false)
{
	global $txt;


	$request = wesql::query('
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg, m.id_board, b.name AS board_name,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, ' . (we::$is_guest ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', ' . ($limit_body ? 'SUBSTRING(m.body, 1, 384) AS body' : 'm.body') . ', m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (we::$is_member ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:current_member})' : '') . '
		WHERE 1=1 ' . ($override_permissions ? '' : '
			AND {query_wanna_see_board}') . ($settings['postmod_active'] ? '
			AND m.approved = {int:is_approved}' : '') . '
			' . (empty($query_where) ? '' : 'AND ' . $query_where) . '
		ORDER BY ' . $query_order . '
		' . ($query_limit == '' ? '' : 'LIMIT {int:query_limit}'),
		array_merge($query_where_params, array(
			'current_member' => MID,
			'is_approved' => 1,
			'query_limit' => $query_limit,
		))
	);
	$posts = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['body'] = parse_bbc($row['body'], 'post', array('smileys' => $row['smileys_enabled'], 'cache' => $row['id_msg']));


		censorText($row['subject']);
		censorText($row['body']);

		$preview = strip_tags(strtr($row['body'], array('<br />' => '&#10;')));


		$posts[] = array(
			'id' => $row['id_msg'],
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => SCRIPT . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . SCRIPT . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : SCRIPT . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . SCRIPT . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => shorten_subject($row['subject'], 25),
			'preview' => westr::strlen($preview) > 128 ? westr::substr($preview, 0, 128) . '...' : $preview,
			'body' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => $row['poster_time'],
			'href' => SCRIPT . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#new',
			'link' => '<a href="' . SCRIPT . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'new' => !empty($row['is_read']),
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
		);
	}
	wesql::free_result($request);


	if ($output_method != 'echo' || empty($posts))
		return $posts;

	echo '
		<table class="ssi_table">';

	foreach ($posts as $post)
		echo '
			<tr>
				<td class="top right nowrap">
					[', $post['board']['link'], ']
				</td>
				<td class="top">
					<a href="', $post['href'], '">', $post['subject'], '</a>
					', $txt['by'], ' ', $post['poster']['link'], '
					', $post['is_new'] ? '<a href="' . SCRIPT . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . '#new" rel="nofollow" class="note">' . $txt['new'] . '</a>' : '', '
				</td>
				<td class="right nowrap">
					', $post['time'], '
				</td>
			</tr>';

	echo '
		</table>';
}


function ssi_recentTopics($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo', $just_titles = false)
{
	global $settings, $txt;

	if ($exclude_boards === null && !empty($settings['recycle_enable']) && $settings['recycle_board'] > 0)
		$exclude_boards = array($settings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));


	if (is_array($include_boards) || (int) $include_boards === $include_boards)
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	elseif ($include_boards != null)
	{
		$output_method = $include_boards;
		$include_boards = array();
	}


	$request = wesql::query('
		SELECT
			t.id_topic, b.id_board, b.name AS board_name, b.url
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE {query_see_topic}
			AND t.id_last_msg >= {int:min_message_id}' . (empty($exclude_boards) ? '' : '
			AND b.id_board NOT IN ({array_int:exclude_boards})') . '' . (empty($include_boards) ? '' : '
			AND b.id_board IN ({array_int:include_boards})') . '
			AND {query_wanna_see_board}' . (empty(we::$user['can_skip_approval']) ? '
			AND ml.approved = {int:is_approved}' : '') . '
		ORDER BY t.id_last_msg DESC
		LIMIT {int:num_recent}',
		array(
			'num_recent' => $num_recent,
			'include_boards' => empty($include_boards) ? '' : $include_boards,
			'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
			'min_message_id' => $settings['maxMsgID'] - 35 * $num_recent,
			'is_approved' => 1,
		)
	);
	$topics = array();
	while ($row = wesql::fetch_assoc($request))
		$topics[$row['id_topic']] = $row;
	wesql::free_result($request);


	if (empty($topics))
		return array();

	$request = wesql::query('
		SELECT
			t.id_topic, ml.poster_time, mf.subject, ml.id_member, ml.id_msg, t.num_replies, t.num_views,
			IFNULL(mem.real_name, ml.poster_name) AS poster_name, ' . (we::$is_guest ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= ml.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ($just_titles ? '' : ', SUBSTRING(ml.body, 1, 384) AS body') . ', ml.smileys_enabled, ml.icon
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)' . (we::$is_member ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' : '') . '
		WHERE t.id_topic IN ({array_int:topic_list})
		ORDER BY t.id_last_msg DESC',
		array(
			'current_member' => MID,
			'topic_list' => array_keys($topics),
		)
	);

	$posts = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if (!$just_titles)
		{
			$row['body'] = strip_tags(strtr(parse_bbc($row['body'], 'post-preview', array('smileys' => $row['smileys_enabled'], 'cache' => $row['id_msg'])), array('<br />' => '&#10;')));
			if (westr::strlen($row['body']) > 128)
				$row['body'] = westr::substr($row['body'], 0, 128) . '...';
		}


		censorText($row['subject']);
		if (!$just_titles)
			censorText($row['body']);


		$posts[] = array(
			'board' => array(
				'id' => $topics[$row['id_topic']]['id_board'],
				'name' => $topics[$row['id_topic']]['board_name'],
				'url' => $topics[$row['id_topic']]['url'],
				'href' => SCRIPT . '?board=' . $topics[$row['id_topic']]['id_board'] . '.0',
				'link' => '<a href="' . SCRIPT . '?board=' . $topics[$row['id_topic']]['id_board'] . '.0">' . $topics[$row['id_topic']]['board_name'] . '</a>',
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : SCRIPT . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . SCRIPT . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'replies' => $row['num_replies'],
			'views' => $row['num_views'],
			'short_subject' => shorten_subject($row['subject'], 25),
			'preview' => $just_titles ? '' : $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => $row['poster_time'],
			'href' => SCRIPT . '?topic=' . $row['id_topic'] . '.msg' . (we::$is_guest ? $row['id_msg'] : $row['new_from']) . '#new',
			'link' => '<a href="' . SCRIPT . '?topic=' . $row['id_topic'] . '.msg' . (we::$is_guest ? $row['id_msg'] : $row['new_from']) . '#new" rel="nofollow">' . $row['subject'] . '</a>',

			'new' => !empty($row['is_read']),
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
			'icon' => '<img src="' . ASSETS . '/post/' . $row['icon'] . '.gif" class="middle" alt="' . $row['icon'] . '" />',
		);
	}
	wesql::free_result($request);


	if ($output_method != 'echo' || empty($posts))
		return $posts;

	echo '
		<table class="ssi_table">';

	foreach ($posts as $post)
		echo '
			<tr>
				<td class="top right nowrap">
					[', $post['board']['link'], ']
				</td>
				<td class="top">
					<a href="', $post['href'], '">', $post['subject'], '</a>
					', $txt['by'], ' ', $post['poster']['link'], '
					', !$post['is_new'] ? '' : '<a href="' . SCRIPT . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . '#new" rel="nofollow" class="note">' . $txt['new'] . '</a>', '
				</td>
				<td class="top right nowrap">
					', $post['time'], '
				</td>
			</tr>';

	echo '
		</table>';
}


function ssi_recentTopicTitles($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo')
{
	return ssi_recentTopics($num_recent, $exclude_boards, $include_boards, $output_method, true);
}


function ssi_topPoster($topNumber = 1, $output_method = 'echo')
{
	global $settings;

	if (empty($settings['allow_guestAccess']) && we::$is_guest)
		return array();


	$request = wesql::query('
		SELECT id_member, real_name, posts
		FROM {db_prefix}members
		ORDER BY posts DESC
		LIMIT {int:topnumber}',
		array(
			'topnumber' => $topNumber
		)
	);
	$return = array();
	while ($row = wesql::fetch_assoc($request))
		$return[] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'href' => SCRIPT . '?action=profile;u=' . $row['id_member'],
			'link' => '<a href="' . SCRIPT . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'posts' => $row['posts']
		);
	wesql::free_result($request);


	if ($output_method != 'echo')
		return $return;


	$temp_array = array();
	foreach ($return as $member)
		$temp_array[] = $member['link'];

	echo implode(', ', $temp_array);
}


function ssi_topBoards($num_top = 10, $output_method = 'echo')
{
	global $txt, $settings;


	$request = wesql::query('
		SELECT b.name, b.num_topics, b.num_posts, b.id_board, b.id_last_msg
		FROM {db_prefix}boards AS b
		WHERE {query_wanna_see_board}' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
		ORDER BY b.num_posts DESC
		LIMIT {int:num_top}',
		array(
			'current_member' => MID,
			'recycle_board' => (int) $settings['recycle_board'],
			'num_top' => $num_top,
		)
	);
	$boards = array();
	while ($row = wesql::fetch_assoc($request))
		$boards[] = array(
			'id' => $row['id_board'],
			'num_posts' => $row['num_posts'],
			'num_topics' => $row['num_topics'],
			'name' => $row['name'],
			'last_msg' => $row['id_last_msg'],
			'href' => SCRIPT . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . SCRIPT . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>'
		);
	wesql::free_result($request);


	if ($output_method != 'echo' || empty($boards))
		return $boards;

	echo '
		<table class="ssi_table">
			<tr class="left">
				<th>', $txt['board'], '</th>
				<th>', $txt['topics'], '</th>
				<th>', $txt['posts'], '</th>
			</tr>';

	foreach ($boards as $bdata)
		echo '
			<tr>
				<td>', $bdata['link'], '</td>
				<td class="right">', comma_format($bdata['num_topics']), '</td>
				<td class="right">', comma_format($bdata['num_posts']), '</td>
			</tr>';

	echo '
		</table>';
}


function ssi_topTopics($type = 'replies', $num_topics = 10, $output_method = 'echo')
{
	global $txt, $settings;

	if ($settings['totalMessages'] > 100000)
	{

		$request = wesql::query('
			SELECT id_topic
			FROM {db_prefix}topics AS t
			WHERE {query_wanna_see_board}
				AND {query_see_topic}
				AND num_' . ($type != 'replies' ? 'views' : 'replies') . ' != 0
			ORDER BY num_' . ($type != 'replies' ? 'views' : 'replies') . ' DESC
			LIMIT {int:limit}',
			array(
				'limit' => $num_topics > 100 ? ($num_topics + ($num_topics / 2)) : 100,
			)
		);
		$topic_ids = array();
		while ($row = wesql::fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		wesql::free_result($request);
	}
	else
		$topic_ids = array();

	$request = wesql::query('
		SELECT m.subject, m.id_topic, t.num_views, t.num_replies
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE {query_wanna_see_board}
			AND {query_see_topic}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : '') . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_enable}' : '') . '
		ORDER BY t.num_' . ($type != 'replies' ? 'views' : 'replies') . ' DESC
		LIMIT {int:limit}',
		array(
			'topic_list' => $topic_ids,
			'recycle_enable' => $settings['recycle_board'],
			'limit' => $num_topics,
		)
	);
	$topics = array();
	while ($row = wesql::fetch_assoc($request))
	{
		censorText($row['subject']);

		$topics[] = array(
			'id' => $row['id_topic'],
			'subject' => $row['subject'],
			'num_replies' => $row['num_replies'],
			'num_views' => $row['num_views'],
			'href' => SCRIPT . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . SCRIPT . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
		);
	}
	wesql::free_result($request);

	if ($output_method != 'echo' || empty($topics))
		return $topics;

	echo '
		<table class="ssi_table">
			<tr class="left">
				<th></th>
				<th>', $txt['views'], '</th>
				<th>', $txt['replies'], '</th>
			</tr>';

	foreach ($topics as $topic_data)
		echo '
			<tr>
				<td class="left">
					', $topic_data['link'], '
				</td>
				<td class="right">', comma_format($topic_data['num_views']), '</td>
				<td class="right">', comma_format($topic_data['num_replies']), '</td>
			</tr>';

	echo '
		</table>';
}


function ssi_topTopicsReplies($num_topics = 10, $output_method = 'echo')
{
	return ssi_topTopics('replies', $num_topics, $output_method);
}


function ssi_topTopicsViews($num_topics = 10, $output_method = 'echo')
{
	return ssi_topTopics('views', $num_topics, $output_method);
}


function ssi_latestMember($output_method = 'echo')
{
	global $txt, $context, $settings;

	if (we::$is_guest && empty($settings['allow_guestAccess']))
		return '';

	if ($output_method == 'echo')
		echo '
	', sprintf($txt['welcome_member'], $context['common_stats']['latest_member']['link']), '<br />';
	else
		return $context['common_stats']['latest_member'];
}


function ssi_randomMember($random_type = '', $output_method = 'echo')
{
	global $settings;


	if ($random_type == 'day')
		mt_srand(floor(time() / 86400));


	$member_id = mt_rand(1, $settings['latestMember']);

	$where_query = '
		id_member >= {int:selected_member}
		AND is_activated = {int:is_activated}';

	$query_where_params = array(
		'selected_member' => $member_id,
		'is_activated' => 1,
	);

	$result = ssi_queryMembers($where_query, $query_where_params, 1, 'id_member ASC', $output_method);


	if (empty($result))
	{
		$where_query = '
			id_member <= {int:selected_member}
			AND is_activated = {int:is_activated}';

		$query_where_params = array(
			'selected_member' => $member_id,
			'is_activated' => 1,
		);

		$result = ssi_queryMembers($where_query, $query_where_params, 1, 'id_member DESC', $output_method);
	}


	if ($random_type != '')
		mt_srand(time());

	return $result;
}


function ssi_fetchMember($member_ids, $output_method = 'echo')
{

	$member_ids = is_array($member_ids) ? $member_ids : array($member_ids);


	$query_where = '
		id_member IN ({array_int:member_list})';

	$query_where_params = array(
		'member_list' => $member_ids,
	);


	return ssi_queryMembers($query_where, $query_where_params, '', 'id_member', $output_method);
}


function ssi_fetchGroupMembers($group_id, $output_method = 'echo')
{
	$query_where = '
		id_group = {int:id_group}
		OR id_post_group = {int:id_group}
		OR FIND_IN_SET({int:id_group}, additional_groups) != 0';

	$query_where_params = array(
		'id_group' => $group_id,
	);

	return ssi_queryMembers($query_where, $query_where_params, '', 'real_name', $output_method);
}


function ssi_queryMembers($query_where, $query_where_params = array(), $query_limit = '', $query_order = 'id_member DESC', $output_method = 'echo')
{
	global $settings, $memberContext;

	if (empty($settings['allow_guestAccess']) && we::$is_guest)
		return array();


	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . $query_where . '
		ORDER BY ' . $query_order . '
		' . ($query_limit == '' ? '' : 'LIMIT ' . $query_limit),
		array_merge($query_where_params, array(
		))
	);
	$members = array();
	while ($row = wesql::fetch_assoc($request))
		$members[] = $row['id_member'];
	wesql::free_result($request);

	if (empty($members))
		return array();


	loadMemberData($members);


	if ($output_method == 'echo')
		echo '
		<table class="ssi_table">';

	$query_members = array();
	foreach ($members as $member)
	{

		if (!loadMemberContext($member))
			continue;


		$query_members[$member] = $memberContext[$member];


		if ($output_method == 'echo')
			echo '
			<tr>
				<td class="top right nowrap">
					', $query_members[$member]['link'], '
					<br />', $query_members[$member]['blurb'], '
					<br />', $query_members[$member]['avatar']['image'], '
				</td>
			</tr>';
	}


	if ($output_method == 'echo')
		echo '
		</table>';


	return $query_members;
}


function ssi_boardStats($output_method = 'echo')
{
	global $txt, $settings;

	if (empty($settings['allow_guestAccess']) && we::$is_guest)
		return array();

	$totals = array(
		'members' => $settings['totalMembers'],
		'posts' => $settings['totalMessages'],
		'topics' => $settings['totalTopics']
	);

	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}boards',
		array(
		)
	);
	list ($totals['boards']) = wesql::fetch_row($result);
	wesql::free_result($result);

	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}categories',
		array(
		)
	);
	list ($totals['categories']) = wesql::fetch_row($result);
	wesql::free_result($result);

	if ($output_method != 'echo')
		return $totals;

	loadLanguage('Stats');

	echo '
		', $txt['total_members'], ': <a href="', SCRIPT . '?action=mlist">', comma_format($totals['members']), '</a><br />
		', $txt['total_posts'], ': ', comma_format($totals['posts']), '<br />
		', $txt['total_topics'], ': ', comma_format($totals['topics']), ' <br />
		', $txt['total_cats'], ': ', comma_format($totals['categories']), '<br />
		', $txt['total_boards'], ': ', comma_format($totals['boards']);
}


function ssi_whosOnline($output_method = 'echo')
{
	global $txt, $settings;

	if (empty($settings['allow_guestAccess']) && we::$is_guest)
		return array();

	loadSource('Subs-MembersOnline');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	);
	$return = getMembersOnlineStats($membersOnlineOptions);


	if ($output_method != 'echo')
		return $return + array(
			'users' => $return['users_online'],
			'guests' => $return['num_guests'],
			'hidden' => $return['num_users_hidden'],
			'buddies' => $return['num_buddies'],
			'num_users' => $return['num_users_online'],
			'total_users' => $return['num_users_online'] + $return['num_guests'] + $return['num_spiders'],
		);

	echo '
		', comma_format($return['num_guests']), ' ', $return['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($return['num_users_online']), ' ', $return['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	$bracketList = array();
	if (!empty(we::$user['buddies']))
		$bracketList[] = comma_format($return['num_buddies']) . ' ' . ($return['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($return['num_spiders']))
		$bracketList[] = comma_format($return['num_spiders']) . ' ' . ($return['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($return['num_users_hidden']))
		$bracketList[] = comma_format($return['num_users_hidden']) . ' ' . $txt['hidden'];

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo '<br />
			', implode(', ', $return['list_users_online']);


	if (!empty($settings['show_group_key']) && !empty($return['membergroups']))
		echo '<br />
			[' . implode(']&nbsp;&nbsp;[', $return['membergroups']) . ']';
}


function ssi_logOnline($output_method = 'echo')
{
	writeLog();

	if ($output_method != 'echo')
		return ssi_whosOnline($output_method);
	else
		ssi_whosOnline($output_method);
}


function ssi_login($redirect_to = '', $output_method = 'echo')
{
	global $txt;

	if ($redirect_to != '')
		$_SESSION['login_url'] = $redirect_to;

	if ($output_method != 'echo' || we::$is_member)
		return we::$is_guest;

	echo '
		<form action="', SCRIPT, '?action=login2" method="post" accept-charset="UTF-8">
			<table class="ssi_table cs1 cp0">
				<tr>
					<td class="right"><label for="user">', $txt['username'], ':</label>&nbsp;</td>
					<td><input id="user" name="user" size="9" value="', we::$user['username'], '"></td>
				</tr><tr>
					<td class="right"><label for="passwrd">', $txt['password'], ':</label>&nbsp;</td>
					<td><input type="password" name="passwrd" id="passwrd" size="9"></td>
				</tr>
				<tr>
					<td><input type="hidden" name="cookielength" value="-1" /></td>
					<td><input type="submit" value="', $txt['login'], '" class="submit" /></td>
				</tr>
			</table>
		</form>';

}


function ssi_topPoll($output_method = 'echo')
{

	return ssi_recentPoll(true, $output_method);
}


function ssi_recentPoll($topPollInstead = false, $output_method = 'echo')
{
	global $txt, $context, $settings;

	$boardsAllowed = array_intersect(boardsAllowedTo('poll_view'), boardsAllowedTo('poll_vote'));

	if (empty($boardsAllowed))
		return array();

	$request = wesql::query('
		SELECT p.id_poll, p.question, t.id_topic, p.max_votes, p.guest_vote, p.hide_results, p.expire_time
		FROM {db_prefix}polls AS p
			INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll AND {query_see_topic})
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)' . ($topPollInstead ? '
			INNER JOIN {db_prefix}poll_choices AS pc ON (pc.id_poll = p.id_poll)' : '') . '
			LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member > {int:no_member} AND lp.id_member = {int:current_member})
		WHERE p.voting_locked = {int:voting_opened}
			AND (p.expire_time = {int:no_expiration} OR {int:current_time} < p.expire_time)
			AND ' . (we::$is_guest ? 'p.guest_vote = {int:guest_vote_allowed}' : 'lp.id_choice IS NULL') . '
			AND {query_wanna_see_board}' . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_allowed_list})' : '') . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_enable}' : '') . '
		ORDER BY ' . ($topPollInstead ? 'pc.votes' : 'p.id_poll') . ' DESC
		LIMIT 1',
		array(
			'current_member' => MID,
			'boards_allowed_list' => $boardsAllowed,
			'guest_vote_allowed' => 1,
			'no_member' => 0,
			'voting_opened' => 0,
			'no_expiration' => 0,
			'current_time' => time(),
			'recycle_enable' => $settings['recycle_board'],
		)
	);
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);


	if ($row === null)
		return array();


	if (we::$is_guest && (!$row['guest_vote'] || (isset($_COOKIE['guest_poll_vote']) && in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote'])))))
		return ssi_showPoll($row['id_topic'], $output_method);

	$request = wesql::query('
		SELECT COUNT(DISTINCT id_member)
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	list ($total) = wesql::fetch_row($request);
	wesql::free_result($request);

	$request = wesql::query('
		SELECT id_choice, label, votes
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	$opts = array();
	while ($rowChoice = wesql::fetch_assoc($request))
	{
		censorText($rowChoice['label']);

		$opts[$rowChoice['id_choice']] = array($rowChoice['label'], $rowChoice['votes']);
	}
	wesql::free_result($request);


	$is_expired = !empty($row['expire_time']) && $row['expire_time'] < time();
	$allow_view_results = allowedTo('moderate_board') || $row['hide_results'] == 0 || $is_expired;

	$return = array(
		'id' => $row['id_poll'],
		'image' => 'poll',
		'question' => parse_bbc($row['question'], 'poll-question'),
		'total_votes' => $total,
		'is_locked' => false,
		'topic' => $row['id_topic'],
		'allow_view_results' => $allow_view_results,
		'options' => array()
	);


	$divisor = $return['total_votes'] == 0 ? 1 : $return['total_votes'];
	foreach ($opts as $i => $option)
	{
		$bar = floor(($option[1] * 100) / $divisor);
		$barWide = $bar == 0 ? 1 : floor(($bar * 5) / 3);
		$return['options'][$i] = array(
			'percent' => $bar,
			'votes' => $option[1],
			'bar' => '<span class="nowrap"><img src="' . ASSETS . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.gif" /><img src="' . ASSETS . '/poll_middle.gif" width="' . $barWide . '" height="12" alt="-" /><img src="' . ASSETS . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.gif" /></span>',
			'option' => parse_bbc($option[0], 'poll-option'),
			'vote_button' => '<input type="' . ($row['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" value="' . $i . '">'
		);
	}

	$return['allowed_warning'] = $row['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($opts), $row['max_votes'])) : '';

	if ($output_method != 'echo')
		return $return;

	if ($allow_view_results)
	{
		echo '
		<form class="ssi_poll" action="', ROOT, '/SSI.php?ssi_function=pollVote" method="post" accept-charset="UTF-8">
			<strong>', $return['question'], '</strong><br />
			', !empty($return['allowed_warning']) ? $return['allowed_warning'] . '<br />' : '';

		foreach ($return['options'] as $option)
			echo '
			<label>', $option['vote_button'], ' ', $option['option'], '</label><br />';

		echo '
			<input type="submit" value="', $txt['poll_vote'], '" class="submit" />
			<input type="hidden" name="poll" value="', $return['id'], '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>';
	}
	else
		echo $txt['poll_cannot_see'];
}

function ssi_showPoll($id_topic = null, $output_method = 'echo')
{
	global $txt, $context;

	$boardsAllowed = boardsAllowedTo('poll_view');

	if (empty($boardsAllowed))
		return array();

	if ($id_topic === null && isset($_REQUEST['ssi_topic']))
		$id_topic = (int) $_REQUEST['ssi_topic'];
	else
		$id_topic = (int) $id_topic;

	$request = wesql::query('
		SELECT
			p.id_poll, p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.guest_vote, b.id_board
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE t.id_topic = {int:current_topic}
			AND {query_see_board}
			AND {query_see_topic}' . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_allowed_see})' : '') . '
		LIMIT 1',
		array(
			'current_topic' => $id_topic,
			'boards_allowed_see' => $boardsAllowed,
		)
	);


	if (wesql::num_rows($request) == 0)
		return array();

	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);


	if (!empty($row['expire_time']) && $row['expire_time'] < time())
		$allow_vote = false;
	elseif (we::$is_guest && $row['guest_vote'] && (!isset($_COOKIE['guest_poll_vote']) || !in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote']))))
		$allow_vote = true;
	elseif (we::$is_guest)
		$allow_vote = false;
	elseif (!empty($row['voting_locked']) || !allowedTo('poll_vote', $row['id_board']))
		$allow_vote = false;
	else
	{
		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}log_polls
			WHERE id_poll = {int:current_poll}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => MID,
				'current_poll' => $row['id_poll'],
			)
		);
		$allow_vote = wesql::num_rows($request) == 0;
		wesql::free_result($request);
	}


	$is_expired = !empty($row['expire_time']) && $row['expire_time'] < time();
	$allow_view_results = allowedTo('moderate_board') || $row['hide_results'] == 0 || ($row['hide_results'] == 1 && !$allow_vote) || $is_expired;

	$request = wesql::query('
		SELECT COUNT(DISTINCT id_member)
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	list ($total) = wesql::fetch_row($request);
	wesql::free_result($request);

	$request = wesql::query('
		SELECT id_choice, label, votes
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	$opts = array();
	$total_votes = 0;
	while ($rowChoice = wesql::fetch_assoc($request))
	{
		censorText($rowChoice['label']);

		$opts[$rowChoice['id_choice']] = array($rowChoice['label'], $rowChoice['votes']);
		$total_votes += $rowChoice['votes'];
	}
	wesql::free_result($request);

	$return = array(
		'id' => $row['id_poll'],
		'image' => empty($row['voting_locked']) ? 'poll' : 'locked_poll',
		'question' => parse_bbc($row['question'], 'poll-question'),
		'total_votes' => $total,
		'is_locked' => !empty($row['voting_locked']),
		'allow_vote' => $allow_vote,
		'allow_view_results' => $allow_view_results,
		'topic' => $id_topic
	);


	$divisor = $total_votes == 0 ? 1 : $total_votes;
	foreach ($opts as $i => $option)
	{
		$bar = floor(($option[1] * 100) / $divisor);
		$barWide = $bar == 0 ? 1 : floor(($bar * 5) / 3);
		$return['options'][$i] = array(
			'percent' => $bar,
			'votes' => $option[1],
			'bar' => '<span class="nowrap"><img src="' . ASSETS . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.gif" alt="" /><img src="' . ASSETS . '/poll_middle.gif" width="' . $barWide . '" height="12" alt="-" /><img src="' . ASSETS . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.gif" alt="" /></span>',
			'option' => parse_bbc($option[0], 'poll-option'),
			'vote_button' => '<input type="' . ($row['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" value="' . $i . '">'
		);
	}

	$return['allowed_warning'] = $row['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($opts), $row['max_votes'])) : '';

	if ($output_method != 'echo')
		return $return;

	if ($return['allow_vote'])
	{
		echo '
			<form class="ssi_poll" action="', ROOT, '/SSI.php?ssi_function=pollVote" method="post" accept-charset="UTF-8">
				<strong>', $return['question'], '</strong><br />
				', !empty($return['allowed_warning']) ? $return['allowed_warning'] . '<br />' : '';

		foreach ($return['options'] as $option)
			echo '
				<label>', $option['vote_button'], ' ', $option['option'], '</label><br />';

		echo '
				<input type="submit" value="', $txt['poll_vote'], '" class="submit" />
				<input type="hidden" name="poll" value="', $return['id'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>';
	}
	elseif ($return['allow_view_results'])
	{
		echo '
			<div class="ssi_poll">
				<strong>', $return['question'], '</strong>
				<dl>';

		foreach ($return['options'] as $option)
			echo '
					<dt>', $option['option'], '</dt>
					<dd>
						<div class="ssi_poll_bar" style="border: 1px solid #666; height: 1em">
							<div class="ssi_poll_bar_fill" style="background: #ccf; height: 1em; width: ', $option['percent'], '%"></div>
						</div>
						', $option['votes'], ' (', $option['percent'], '%)
					</dd>';
		echo '
				</dl>
				<strong>', $txt['poll_total_voters'], ': ', $return['total_votes'], '</strong>
			</div>';
	}

	else
		echo $txt['poll_cannot_see'];
}


function ssi_pollVote()
{
	global $context, $sc, $settings;

	if (!isset($_POST[$context['session_var']]) || $_POST[$context['session_var']] != $sc || empty($_POST['options']) || !isset($_POST['poll']))
	{
		echo '<!DOCTYPE html>
<html><head>
	<script>
		history.go(-1);
	</script>
</head>
<body>&laquo;</body>
</html>';
		return;
	}


	checkSession();

	$_POST['poll'] = (int) $_POST['poll'];


	$request = wesql::query('
		SELECT
			p.id_poll, p.voting_locked, p.expire_time, p.max_votes, p.guest_vote,
			t.id_topic,
			IFNULL(lp.id_choice, -1) AS selected
		FROM {db_prefix}polls AS p
			INNER JOIN {db_prefix}topics AS t ON (t.id_poll = {int:current_poll} AND {query_see_topic})
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
			LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member = {int:current_member})
		WHERE p.id_poll = {int:current_poll}
		LIMIT 1',
		array(
			'current_member' => MID,
			'current_poll' => $_POST['poll'],
		)
	);
	if (wesql::num_rows($request) == 0)
		exit;
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	if (!empty($row['voting_locked']) || ($row['selected'] != -1 && we::$is_member) || (!empty($row['expire_time']) && time() > $row['expire_time']))
		redirectexit('topic=' . $row['id_topic'] . '.0');


	if (count($_REQUEST['options']) > $row['max_votes'])
		redirectexit('topic=' . $row['id_topic'] . '.0');


	if (we::$is_guest)
	{

		if (!$row['guest_vote'])
			redirectexit('topic=' . $row['id_topic'] . '.0');

		elseif (isset($_COOKIE['guest_poll_vote']) && in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote'])))
			redirectexit('topic=' . $row['id_topic'] . '.0');
	}

	$opts = array();
	$inserts = array();
	foreach ($_REQUEST['options'] as $id)
	{
		$id = (int) $id;

		$opts[] = $id;
		$inserts[] = array($_POST['poll'], MID, $id);
	}


	wesql::insert('',
		'{db_prefix}log_polls',
		array('id_poll' => 'int', 'id_member' => 'int', 'id_choice' => 'int'),
		$inserts
	);
	wesql::query('
		UPDATE {db_prefix}poll_choices
		SET votes = votes + 1
		WHERE id_poll = {int:current_poll}
			AND id_choice IN ({array_int:option_list})',
		array(
			'option_list' => $opts,
			'current_poll' => $_POST['poll'],
		)
	);


	if (we::$is_guest)
	{
		$_COOKIE['guest_poll_vote'] = !empty($_COOKIE['guest_poll_vote']) ? ($_COOKIE['guest_poll_vote'] . ',' . $row['id_poll']) : $row['id_poll'];

		loadSource('Subs-Auth');
		$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));
		setcookie('guest_poll_vote', $_COOKIE['guest_poll_vote'], time() + 2500000, $cookie_url[1], $cookie_url[0], 0, true);
	}

	redirectexit('topic=' . $row['id_topic'] . '.0');
}


function ssi_quickSearch($output_method = 'echo')
{
	global $txt;

	if (!allowedTo('search_posts'))
		return '';

	if ($output_method != 'echo')
		return SCRIPT . '?action=search';

	echo '
		<form action="', SCRIPT, '?action=search2" method="post" accept-charset="UTF-8">
			<input type="search" class="search" name="search" size="30" /> <input type="submit" class="submit" value="', $txt['search'], '" />
		</form>';
}


function ssi_news($output_method = 'echo')
{
	global $context, $settings;

	if (empty($settings['allow_guestAccess']) && we::$is_guest)
		return array();

	if ($output_method != 'echo')
		return $context['random_news_line'];

	echo $context['random_news_line'];
}


function ssi_boardNews($id_board = null, $limit = null, $start = null, $length = null, $output_method = 'echo')
{
	global $txt, $settings;

	if (empty($settings['allow_guestAccess']) && we::$is_guest)
		return array();

	loadLanguage('Stats');


	if ($limit === null)
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
	else
		$limit = (int) $limit;

	if ($start === null)
		$start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
	else
		$start = (int) $start;

	if ($id_board !== null)
		$id_board = (int) $id_board;
	elseif (isset($_GET['board']))
		$id_board = (int) $_GET['board'];

	if ($length === null)
		$length = isset($_GET['length']) ? (int) $_GET['length'] : 0;
	else
		$length = (int) $length;

	$limit = max(0, $limit);
	$start = max(0, $start);


	$request = wesql::query('
		SELECT id_board
		FROM {db_prefix}boards
		WHERE ' . ($id_board === null ? '' : 'id_board = {int:current_board}
			AND ') . 'FIND_IN_SET(-1, member_groups) != 0
		LIMIT 1',
		array(
			'current_board' => $id_board,
		)
	);
	if (wesql::num_rows($request) == 0)
	{
		if ($output_method == 'echo')
		{
			echo $txt['ssi_no_guests'];
			return;
		}
		else
			return array();
	}
	list ($id_board) = wesql::fetch_row($request);
	wesql::free_result($request);


	$request = wesql::query('
		SELECT id_first_msg
		FROM {db_prefix}topics AS t
		WHERE t.id_board = {int:current_board}
			AND {query_see_topic}
		ORDER BY id_first_msg DESC
		LIMIT {int:start}, {int:limit}',
		array(
			'current_board' => $id_board,
			'start' => $start,
			'limit' => $limit,
		)
	);
	$posts = array();
	while ($row = wesql::fetch_assoc($request))
		$posts[] = $row['id_first_msg'];
	wesql::free_result($request);

	if (empty($posts))
		return array();


	$request = wesql::query('
		SELECT
			m.icon, m.subject, m.body, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			t.num_replies, t.id_topic, m.id_member, m.smileys_enabled, m.id_msg, t.locked, t.id_last_msg
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE t.id_first_msg IN ({array_int:post_list})
		ORDER BY t.id_first_msg DESC
		LIMIT {int:count_posts}',
		array(
			'post_list' => $posts,
			'count_posts' => count($posts),
		)
	);
	$return = array();
	while ($row = wesql::fetch_assoc($request))
	{

		if (!empty($length) && westr::strlen($row['body']) > $length)
		{
			$row['body'] = westr::substr($row['body'], 0, $length);


			$cutoff = max(strrpos($row['body'], ' '), strrpos($row['body'], '<'));

			if ($cutoff !== false)
				$row['body'] = westr::substr($row['body'], 0, $cutoff);
			$row['body'] .= '...';
		}

		$row['body'] = parse_bbc($row['body'], 'post', array('smileys' => $row['smileys_enabled'], 'cache' => $row['id_msg'], 'user' => $row['id_member']));

		censorText($row['subject']);
		censorText($row['body']);

		$return[] = array(
			'id' => $row['id_topic'],
			'message_id' => $row['id_msg'],
			'icon' => '<img src="' . ASSETS . '/post/' . $row['icon'] . '.gif" alt="' . $row['icon'] . '" />',
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => $row['poster_time'],
			'body' => $row['body'],
			'href' => SCRIPT . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . SCRIPT . '?topic=' . $row['id_topic'] . '.0">' . $row['num_replies'] . ' ' . ($row['num_replies'] == 1 ? $txt['ssi_comment'] : $txt['ssi_comments']) . '</a>',
			'replies' => $row['num_replies'],
			'comment_href' => !empty($row['locked']) ? '' : SCRIPT . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last=' . $row['id_last_msg'],
			'comment_link' => !empty($row['locked']) ? '' : '<a href="' . SCRIPT . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last=' . $row['id_last_msg'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'new_comment' => !empty($row['locked']) ? '' : '<a href="' . SCRIPT . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => !empty($row['id_member']) ? SCRIPT . '?action=profile;u=' . $row['id_member'] : '',
				'link' => !empty($row['id_member']) ? '<a href="' . SCRIPT . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name']
			),
			'locked' => !empty($row['locked']),
			'is_last' => false
		);
	}
	wesql::free_result($request);

	if (empty($return))
		return $return;

	$return[count($return) - 1]['is_last'] = true;

	if ($output_method != 'echo')
		return $return;

	foreach ($return as $news)
	{
		echo '
			<div class="news_item">
				<h3 class="news_header">
					', $news['icon'], '
					<a href="', $news['href'], '">', $news['subject'], '</a>
				</h3>
				<div class="news_timestamp">', $news['time'], ' ', $txt['by'], ' ', $news['poster']['link'], '</div>
				<div class="news_body" style="padding: 2ex 0">', $news['body'], '</div>
				', $news['link'], $news['locked'] ? '' : ' | ' . $news['comment_link'], '
			</div>';

		if (!$news['is_last'])
			echo '
			<hr />';
	}
}


function ssi_checkPassword($id = null, $password = null, $is_username = false)
{

	if ($id === null)
		return;

	$request = wesql::query('
		SELECT passwd, member_name, is_activated
		FROM {db_prefix}members
		WHERE ' . ($is_username ? 'member_name' : 'id_member') . ' = {string:id}
		LIMIT 1',
		array(
			'id' => $id,
		)
	);
	list ($pass, $user, $active) = wesql::fetch_row($request);
	wesql::free_result($request);

	return sha1(strtolower($user) . $password) == $pass && $active == 1;
}


function ssi_recentAttachments($num_attachments = 10, $attachment_ext = array(), $output_method = 'echo')
{
	global $settings, $txt;


	$attachments_boards = boardsAllowedTo('view_attachments');


	if (empty($attachments_boards))
		return array();


	if (!is_array($attachment_ext))
		$attachment_ext = array($attachment_ext);


	$request = wesql::query('
		SELECT
			att.id_attach, att.id_msg, att.filename, IFNULL(att.size, 0) AS filesize, att.downloads, mem.id_member,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.id_topic, m.subject, t.id_board, m.poster_time,
			att.width, att.height' . (empty($settings['attachmentShowImages']) || empty($settings['attachmentThumbnails']) ? '' : ', IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
		FROM {db_prefix}attachments AS att
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = att.id_msg)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (empty($settings['attachmentShowImages']) || empty($settings['attachmentThumbnails']) ? '' : '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = att.id_thumb)') . '
		WHERE {query_see_topic}
			AND att.attachment_type = 0' . ($attachments_boards === array(0) ? '' : '
			AND m.id_board IN ({array_int:boards_can_see})') . (!empty($attachment_ext) ? '
			AND att.fileext IN ({array_string:attachment_ext})' : '') .
			(empty(we::$user['can_skip_approval']) ? '
			AND m.approved = 1' : '') . '
		ORDER BY att.id_attach DESC
		LIMIT {int:num_attachments}',
		array(
			'boards_can_see' => $attachments_boards,
			'attachment_ext' => $attachment_ext,
			'num_attachments' => $num_attachments,
		)
	);


	$attachments = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$filename = westr::htmlspecialchars($row['filename']);


		$attachments[$row['id_attach']] = array(
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . SCRIPT . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
			),
			'file' => array(
				'filename' => $filename,
				'filesize' => round($row['filesize'] /1024, 2) . $txt['kilobyte'],
				'downloads' => $row['downloads'],
				'href' => SCRIPT . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'],
				'link' => '<img src="' . ASSETS . '/icons/clip.gif" alt="" /> <a href="' . SCRIPT . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . '">' . $filename . '</a>',
				'is_image' => !empty($row['width']) && !empty($row['height']) && !empty($settings['attachmentShowImages']),
			),
			'topic' => array(
				'id' => $row['id_topic'],
				'subject' => $row['subject'],
				'href' => SCRIPT . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'link' => '<a href="' . SCRIPT . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'time' => timeformat($row['poster_time']),
			),
		);


		if ($attachments[$row['id_attach']]['file']['is_image'])
		{
			$id_thumb = empty($row['id_thumb']) ? $row['id_attach'] : $row['id_thumb'];
			$attachments[$row['id_attach']]['file']['image'] = array(
				'id' => $id_thumb,
				'width' => $row['width'],
				'height' => $row['height'],
				'img' => '<img src="' . SCRIPT . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image" alt="' . $filename . '" />',
				'thumb' => '<img src="' . SCRIPT . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '" />',
				'href' => SCRIPT . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image',
				'link' => '<a href="' . SCRIPT . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image"><img src="' . SCRIPT . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '" /></a>',
			);
		}
	}
	wesql::free_result($request);


	if ($output_method == 'array' || empty($attachments))
		return $attachments;


	echo '
		<table class="ssi_downloads" cellpadding="2">
			<tr class="left">
				<th>', $txt['file'], '</th>
				<th>', $txt['posted_by'], '</th>
				<th>', $txt['downloads'], '</th>
				<th>', $txt['filesize'], '</th>
			</tr>';

	foreach ($attachments as $attach)
		echo '
			<tr>
				<td>', $attach['file']['link'], '</td>
				<td>', $attach['member']['link'], '</td>
				<td class="center">', $attach['file']['downloads'], '</td>
				<td>', $attach['file']['filesize'], '</td>
			</tr>';

	echo '
		</table>';
}
