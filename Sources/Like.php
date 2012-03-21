<?php
/**
 * Wedge
 *
 * Handles liking and unliking topics (and anything else via hooks)
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function Like()
{
	global $topic, $user_info, $context, $user_profile, $settings;

	if (empty($user_info['id']))
		fatal_lang_error('no_access', false);

	// We might be doing a topic.
	if (empty($topic) || empty($_REQUEST['msg']) || (int) $_REQUEST['msg'] == 0)
	{
		// If it isn't a topic, check the external handler, just in case. They'll have to be checking $_REQUEST themselves, and performing their own session check.
		$result = call_hook('like_handler', array(&$changes));
		if (empty($result))
			fatal_lang_error('not_a_topic', false);

		foreach ($result as $func => $response)
			list ($id_content, $content_type) = $response;
	}
	else
	{
		checkSession('get');

		$id_content = (int) $_REQUEST['msg'];
		$content_type = 'post';

		// Validate this message is in this topic.
		$request = wesql::query('
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:msg}',
			array(
				'msg' => $id_content,
			)
		);
		$in_topic = false;
		if (wesql::num_rows($request) != 0)
		{
			$row = wesql::fetch_row($request);
			$in_topic = $row[0] == $topic;
		}
		wesql::free_result($request);
		if (!$in_topic)
			fatal_lang_error('not_a_topic', false);

		$context['redirect_from_like'] = 'topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg'];
	}

	if (empty($id_content) || empty($content_type))
		fatal_lang_error('no_access', false);

	// Does the current user already like said content?
	$request = wesql::query('
		SELECT like_time
		FROM {db_prefix}likes
		WHERE id_content = {int:id_content}
			AND content_type = {string:content_type}
			AND id_member = {int:user}',
		array(
			'id_content' => $id_content,
			'content_type' => $content_type,
			'user' => $user_info['id'],
		)
	);
	if ($row = wesql::fetch_row($request))
		// We had a row. Kill it.
		wesql::query('
			DELETE FROM {db_prefix}likes
			WHERE id_content = {int:id_content}
				AND content_type = {string:content_type}
				AND id_member = {int:user}',
			array(
				'id_content' => $id_content,
				'content_type' => $content_type,
				'user' => $user_info['id'],
			)
		);
	else
		// No we didn't, insert it.
		wesql::insert('insert',
			'{db_prefix}likes',
			array('id_content' => 'int', 'content_type' => 'string-6', 'id_member' => 'int', 'like_time' => 'int'),
			array($id_content, $content_type, $user_info['id'], time()),
			array('id_content', 'content_type', 'id_member')
		);

	wesql::free_result($request);

	if (isset($_REQUEST['xml']))
	{
		// OK, we're going to send some details back to the user through the magic of AJAX. We need to get those details, first of all.
		$members_load = array();
		$context['liked_posts'] = array();

		$request = wesql::query('
			SELECT id_content, id_member
			FROM {db_prefix}likes
			WHERE id_content = {int:id_content}
				AND content_type = {string:content_type}
			ORDER BY like_time',
			array(
				'id_content' => $id_content,
				'content_type' => $content_type,
			)
		);

		while ($row = wesql::fetch_assoc($request))
		{
			// If it's us, log it as being us.
			if ($row['id_member'] == $user_info['id'])
				$context['liked_posts'][$row['id_content']]['you'] = true;
			// Otherwise, add it to the list, and if it's a member whose name we don't have, save that separately too. But only if we have up to 2 names.
			elseif (empty($context['liked_posts'][$row['id_content']]['names']) || count($context['liked_posts'][$row['id_content']]['names']) < 2)
			{
				$context['liked_posts'][$row['id_content']]['names'][] = $row['id_member'];
				if (!isset($user_profile[$row['id_member']]))
					$members_load[$row['id_member']] = true;
			}
			// More than 3 people liked this (not including current user)? Just get the names.
			else
			{
				if (empty($context['liked_posts'][$row['id_content']]['others']))
					$context['liked_posts'][$row['id_content']]['others'] = 1;
				else
					$context['liked_posts'][$row['id_content']]['others']++;
			}
		}
		wesql::free_result($request);

		// Any members to load? We don't need everything, just their names. Note we deliberately didn't put it in the above query, because the chances are that we actually don't want all the names.
		if (!empty($members_load))
			loadMemberData(array_keys($members_load), false, 'minimal');

		loadTemplate('Display');
		// Now the AJAXish data.
		ob_end_clean();
		if (!empty($settings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		ob_start('ob_sessrewrite');
		header('Content-Type: text/xml; charset=UTF-8');
		echo '<?xml version="1.0" encoding="UTF-8"?' . '><likes>', template_show_likes($id_content), '</likes>';
		obExit(false);
	}
	else
		redirectexit($context['redirect_from_like']);
}

?>