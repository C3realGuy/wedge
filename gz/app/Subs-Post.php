<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');











































































































function sendmail($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false)
{
	global $webmaster_email, $context, $settings, $txt;


	$use_sendmail = empty($settings['mail_type']) || $settings['smtp_host'] == '';



	$br = strpos(PHP_OS, 'WIN') === 0 || !$use_sendmail ? "\r\n" : "\n";


	$mail_result = true;


	$to_array = (array) $to;



	if ($hotmail_fix === null)
	{
		$message = str_replace('<URL>', SCRIPT, $message);
		if (is_string($send_html))
			$send_html = str_replace('<URL>', SCRIPT, $send_html);

		if (!empty($settings['pretty_enable_filters']))
		{

			preg_match_all('~' . preg_quote(SCRIPT, '~') . '[^\s]*~', $message, $urls);
			$message = str_replace($urls[0], prettify_urls($urls[0]), $message);


			if (is_string($send_html))
			{
				preg_match_all('~' . preg_quote(SCRIPT, '~') . '[^\s]*~', $send_html, $urls);
				$send_html = str_replace($urls[0], prettify_urls($urls[0]), $send_html);
			}
		}

		$hotmail_to = array();
		foreach ($to_array as $i => $to_address)
		{
			if (preg_match('~@(att|comcast|bellsouth)\.[a-zA-Z.]{2,6}$~i', $to_address) === 1)
			{
				$hotmail_to[] = $to_address;
				$to_array = array_diff($to_array, array($to_address));
			}
		}


		if (!empty($hotmail_to))
			$mail_result = sendmail($hotmail_to, $subject, $message, $from, $message_id, $send_html, $priority, true);


		$hotmail_fix = false;


		if (empty($to_array))
			return $mail_result;
	}


	$subject = un_htmlspecialchars($subject);

	$message = str_replace(array("\r", "\n"), array('', $br), $message);


	if ($hotmail_fix && !$send_html)
	{
		$send_html = true;
		$message = strtr($message, array($br => '<br>' . $br));
		$message = preg_replace('~(' . preg_quote(SCRIPT, '~') . '(?:[?/][\w%.,?&;=#-]+)?)~', '<a href="$1">$1</a>', $message);
	}

	list ($from_name) = mimespecialchars(addcslashes($from !== null ? $from : $context['forum_name'], '<>()\'\\"'), true, $hotmail_fix, $br);
	list ($subject) = mimespecialchars($subject, true, $hotmail_fix, $br);


	$headers = 'From: ' . $from_name . ' <' . (empty($settings['mail_from']) ? $webmaster_email : $settings['mail_from']) . '>' . $br;
	$headers .= $from !== null ? 'Reply-To: ' . $from_name . ' <' . $from . '>' . $br : '';
	$headers .= 'Return-Path: ' . (empty($settings['mail_from']) ? $webmaster_email : $settings['mail_from']) . $br;
	$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . $br;

	if ($message_id !== null && empty($settings['mail_no_message_id']))
		$headers .= 'Message-ID: <' . md5(SCRIPT . microtime()) . '-' . $message_id . strstr(empty($settings['mail_from']) ? $webmaster_email : $settings['mail_from'], '@') . '>' . $br;
	$headers .= 'X-Mailer: Wedge' . $br;


	if (in_array(false, call_hook('outgoing_email', array(&$subject, &$message, &$headers)), true))
		return false;


	$mime_boundary = 'We' . md5($message . time());


	$headers .= 'Mime-Version: 1.0' . $br;
	$headers .= 'Content-Type: multipart/alternative; boundary=' . $mime_boundary . $br;
	$headers .= 'Content-Transfer-Encoding: 7bit' . $br;

	$raw_message = !$send_html ? $message : (is_string($send_html) ? $send_html : un_htmlspecialchars(strip_tags(strtr($message, array('</title>' => $br, '<br>' => $br, '</li>' => $br, '</ul>' => $br)))));


	list ($plain_message) = mimespecialchars($raw_message, false, true, $br);
	$body = $plain_message . $br;


	list ($plain_charset_message, $encoding) = mimespecialchars($raw_message, false, false, $br);
	$body .= '--' . $mime_boundary . $br;
	$body .= 'Content-Type: text/plain; charset=UTF-8' . $br;
	$body .= 'Content-Transfer-Encoding: ' . $encoding . $br . $br;
	$body .= $plain_charset_message . $br;


	if ($send_html)
	{

		list ($html_message, $encoding) = mimespecialchars($message, false, $hotmail_fix, $br);
		$body .= '--' . $mime_boundary . $br;
		$body .= 'Content-Type: text/html; charset=UTF-8' . $br;
		$body .= 'Content-Transfer-Encoding: ' . ($encoding == '' ? '7bit' : $encoding) . $br . $br;
		$body .= $html_message . $br;
	}

	$body .= '--' . $mime_boundary . '--';


	if (!empty($settings['mail_queue']) && $priority != 0)
		return AddMailQueue(false, $to_array, $subject, $body, $headers, !!$send_html, $priority, $is_private);


	elseif (!empty($settings['mail_queue']) && !empty($settings['mail_limit']))
	{
		list ($last_mail_time, $mails_this_minute) = @explode('|', $settings['mail_recent']);
		if (empty($mails_this_minute) || time() > $last_mail_time + 60)
			$new_queue_stat = time() . '|' . 1;
		else
			$new_queue_stat = $last_mail_time . '|' . ((int) $mails_this_minute + 1);

		updateSettings(array('mail_recent' => $new_queue_stat));
	}


	if ($use_sendmail)
	{
		$subject = strtr($subject, array("\r" => '', "\n" => ''));
		if (!empty($settings['mail_strip_carriage']))
		{
			$body = strtr($body, array("\r" => ''));
			$headers = strtr($headers, array("\r" => ''));
		}

		foreach ($to_array as $to)
		{
			if (!mail(strtr($to, array("\r" => '', "\n" => '')), $subject, $body, $headers))
			{
				loadLanguage('Post');
				log_error(sprintf($txt['mail_send_unable'], $to), 'mail');
				$mail_result = false;
			}


			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
	}
	else
		$mail_result = $mail_result && smtp_mail($to_array, $subject, $body, $headers);


	return $mail_result;
}


function AddMailQueue($flush = false, $to_array = array(), $subject = '', $message = '', $headers = '', $send_html = false, $priority = 3, $is_private = false)
{
	global $context;

	static $cur_insert = array();
	static $cur_insert_len = 0;

	if ($cur_insert_len == 0)
		$cur_insert = array();


	if (($flush || $cur_insert_len > 800000) && !empty($cur_insert))
	{

		$cur_insert_len = 0;


		wesql::insert('',
			'{db_prefix}mail_queue',
			array(
				'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
				'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
			),
			$cur_insert
		);

		$cur_insert = array();
		$context['flush_mail'] = false;
	}


	if ($flush)
	{
		$nextSendTime = time() + 10;

		wesql::query('
			UPDATE {db_prefix}settings
			SET value = {string:nextSendTime}
			WHERE variable = {literal:mail_next_send}
				AND value = {string:no_outstanding}',
			array(
				'nextSendTime' => $nextSendTime,
				'no_outstanding' => '0',
			)
		);
		cache_put_data('settings', null, 'forever');

		return true;
	}


	$context['flush_mail'] = true;

	foreach ($to_array as $to)
	{

		$this_insert_len = strlen($to) + strlen($message) + strlen($headers) + 700;


		if ($this_insert_len + $cur_insert_len > 1000000)
		{

			wesql::insert('',
				'{db_prefix}mail_queue',
				array(
					'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
					'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
				),
				$cur_insert
			);


			$cur_insert = array();
			$cur_insert_len = 0;
		}


		$cur_insert[] = array(time(), (string) $to, (string) $message, (string) $subject, (string) $headers, $send_html ? 1 : 0, $priority, (int) $is_private);
		$cur_insert_len += $this_insert_len;
	}


	if (WEDGE === 'SSI')
		return AddMailQueue(true);

	return true;
}


function sendpm($recipients, $subject, $message, $store_outbox = true, $from = null, $pm_head = 0)
{
	global $txt, $settings;


	loadLanguage('PersonalMessage');
	loadSource('Class-Editor');

	$onBehalf = $from !== null;


	$log = array(
		'failed' => array(),
		'sent' => array()
	);

	if ($from === null)
		$from = array(
			'id' => MID,
			'name' => we::$user['name'],
			'username' => we::$user['username']
		);

	else
		we::$user['name'] = $from['name'];


	$htmlmessage = westr::htmlspecialchars($message, ENT_QUOTES);
	$htmlsubject = westr::htmlspecialchars($subject);
	wedit::preparsecode($htmlmessage);


	call_hook('personal_message', array(&$recipients, &$from['username'], &$subject, &$message));


	$usernames = array();
	foreach ($recipients as $rec_type => $rec)
	{
		foreach ($rec as $id => $member)
		{
			if (!is_numeric($recipients[$rec_type][$id]))
			{
				$recipients[$rec_type][$id] = westr::strtolower(trim(preg_replace('/[<>&"\'=\\\]/', '', $recipients[$rec_type][$id])));
				$usernames[$recipients[$rec_type][$id]] = 0;
			}
		}
	}
	if (!empty($usernames))
	{
		$request = wesql::query('
			SELECT id_member, member_name
			FROM {db_prefix}members
			WHERE member_name IN ({array_string:usernames})',
			array(
				'usernames' => array_keys($usernames),
			)
		);
		while ($row = wesql::fetch_assoc($request))
			if (isset($usernames[westr::strtolower($row['member_name'])]))
				$usernames[westr::strtolower($row['member_name'])] = $row['id_member'];
		wesql::free_result($request);


		foreach ($recipients as $rec_type => $rec)
			foreach ($rec as $id => $member)
			{
				if (is_numeric($recipients[$rec_type][$id]))
					continue;

				if (!empty($usernames[$member]))
					$recipients[$rec_type][$id] = $usernames[$member];
				else
				{
					$log['failed'][$id] = sprintf($txt['pm_error_user_not_found'], $recipients[$rec_type][$id]);
					unset($recipients[$rec_type][$id]);
				}
			}
	}


	$recipients['to'] = array_unique($recipients['to']);


	$recipients['bcc'] = array_diff(array_unique($recipients['bcc']), $recipients['to']);


	$all_to = array_merge($recipients['to'], $recipients['bcc']);


	$request = wesql::query('
		SELECT
			id_member, criteria, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member IN ({array_int:to_members})
			AND delete_pm = {int:delete_pm}',
		array(
			'to_members' => $all_to,
			'delete_pm' => 1,
		)
	);
	$deletes = array();

	while ($row = wesql::fetch_assoc($request))
	{
		$criteria = unserialize($row['criteria']);

		$delete = false;
		foreach ($criteria as $criterium)
		{
			$match = false;
			if (($criterium['t'] == 'mid' && $criterium['v'] == $from['id']) || ($criterium['t'] == 'gid' && in_array($criterium['v'], we::$user['groups'])) || ($criterium['t'] == 'sub' && strpos($subject, $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($message, $criterium['v']) !== false))
				$delete = true;

			elseif (!$row['is_or'])
			{
				$delete = false;
				break;
			}
		}
		if ($delete)
			$deletes[$row['id_member']] = 1;
	}
	wesql::free_result($request);



	static $message_limit_cache = array();
	if (!allowedTo('moderate_forum') && empty($message_limit_cache))
	{
		$request = wesql::query('
			SELECT id_group, max_messages
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$message_limit_cache[$row['id_group']] = $row['max_messages'];
		wesql::free_result($request);
	}


	$allowed_groups = array();
	$disallowed_groups = array();
	$request = wesql::query('
		SELECT id_group, add_deny
		FROM {db_prefix}permissions
		WHERE permission = {literal:pm_read}'
	);

	while ($row = wesql::fetch_assoc($request))
	{
		if (empty($row['add_deny']))
			$disallowed_groups[] = $row['id_group'];
		else
			$allowed_groups[] = $row['id_group'];
	}

	wesql::free_result($request);

	$request = wesql::query('
		SELECT
			member_name, real_name, id_member, email_address, lngfile,
			pm_email_notify, instant_messages,' . (allowedTo('moderate_forum') ? ' 0' : '
			(pm_receive_from = {int:admins_only}' . (empty($settings['enable_buddylist']) ? '' : ' OR
			(pm_receive_from = {int:buddies_only} AND FIND_IN_SET({string:from_id}, buddy_list) = 0) OR
			(pm_receive_from = {int:not_on_ignore_list} AND FIND_IN_SET({string:from_id}, pm_ignore_list) != 0)') . ')') . ' AS ignored,
			FIND_IN_SET({string:from_id}, buddy_list) != 0 AS is_buddy, is_activated,
			additional_groups, id_group, id_post_group
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:recipients})
		ORDER BY lngfile
		LIMIT {int:count_recipients}',
		array(
			'not_on_ignore_list' => 1,
			'buddies_only' => 2,
			'admins_only' => 3,
			'recipients' => $all_to,
			'count_recipients' => count($all_to),
			'from_id' => $from['id'],
		)
	);
	$notifications = array();
	while ($row = wesql::fetch_assoc($request))
	{

		if (isset($deletes[$row['id_member']]))
			continue;


		$groups = explode(',', $row['additional_groups']);
		$groups[] = $row['id_group'];
		$groups[] = $row['id_post_group'];

		$message_limit = -1;

		if (!in_array(1, $groups))
		{
			foreach ($groups as $id)
			{
				if (isset($message_limit_cache[$id]) && $message_limit != 0 && $message_limit < $message_limit_cache[$id])
					$message_limit = $message_limit_cache[$id];
			}

			if ($message_limit > 0 && $message_limit <= $row['instant_messages'])
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_data_limit_reached'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}


			if (count(array_intersect($allowed_groups, $groups)) == 0 || count(array_intersect($disallowed_groups, $groups)) != 0)
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}
		}

		if (!empty($row['ignored']) && $row['id_member'] != $from['id'])
		{
			$log['failed'][$row['id_member']] = sprintf($txt['pm_error_ignored_by_user'], $row['real_name']);
			unset($all_to[array_search($row['id_member'], $all_to)]);
			continue;
		}


		if ($row['is_activated'] >= 20 || ($row['is_activated'] == 4 && !we::$is_admin))
		{
			$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
			unset($all_to[array_search($row['id_member'], $all_to)]);
			continue;
		}


		if (!empty($row['email_address']) && ($row['pm_email_notify'] == 1 || ($row['pm_email_notify'] > 1 && (!empty($settings['enable_buddylist']) && $row['is_buddy']))) && $row['is_activated'] == 1)
			$notifications[empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile']][] = $row['email_address'];

		$log['sent'][$row['id_member']] = sprintf(isset($txt['pm_successfully_sent']) ? $txt['pm_successfully_sent'] : '', $row['real_name']);
	}
	wesql::free_result($request);


	if (empty($all_to))
		return $log;


	wesql::insert('',
		'{db_prefix}personal_messages',
		array(
			'id_pm_head' => 'int', 'id_member_from' => 'int', 'deleted_by_sender' => 'int',
			'from_name' => 'string-255', 'msgtime' => 'int', 'subject' => 'string-255', 'body' => 'string-65534',
		),
		array(
			$pm_head, $from['id'], ($store_outbox ? 0 : 1),
			$from['username'], time(), $htmlsubject, $htmlmessage,
		)
	);
	$id_pm = wesql::insert_id();


	if (!empty($id_pm))
	{

		if (empty($pm_head))
			wesql::query('
				UPDATE {db_prefix}personal_messages
				SET id_pm_head = {int:id_pm_head}
				WHERE id_pm = {int:id_pm_head}',
				array(
					'id_pm_head' => $id_pm,
				)
			);


		wesql::query('
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}',
			array(
				'id_pm' => $id_pm,
			)
		);

		$insertRows = array();
		foreach ($all_to as $to)
			$insertRows[] = array($id_pm, $to, in_array($to, $recipients['bcc']) ? 1 : 0, isset($deletes[$to]) ? 1 : 0, 1);

		wesql::insert('',
			'{db_prefix}pm_recipients',
			array(
				'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'deleted' => 'int', 'is_new' => 'int'
			),
			$insertRows
		);
	}

	censorText($message);
	censorText($subject);
	$message = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc(westr::htmlspecialchars($message), 'pm-notify', array('smileys' => false)), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

	$replacements = array(
		'SENDERNAME' => un_htmlspecialchars($from['name']),
		'SUBJECT' => $subject,
		'MESSAGE' => $message,
		'REPLYLINK' => SCRIPT . '?action=pm;sa=send;f=inbox;pmsg=' . $id_pm . ';quote;u=' . $from['id'],
	);

	foreach ($notifications as $lang => $notification_list)
	{

		$emaildata = loadEmailTemplate('pm_email', $replacements, $lang, true);
		sendmail($notification_list, $emaildata['subject'], $emaildata['body'], null, 'p' . $id_pm, false, 2, null, true);
	}


	foreach ($all_to as $k => $id)
		if (isset($deletes[$id]))
			unset($all_to[$k]);



	if (!empty($all_to))
		updateMemberData($all_to, array('instant_messages' => '+', 'unread_messages' => '+', 'hey_pm' => 1));

	return $log;
}


function mimespecialchars($string, $with_charset = true, $hotmail_fix = false, $br = "\r\n")
{

	if ($hotmail_fix)
		return array(westr::utf8_to_entity($string), '7bit');


	if (preg_match('~&#\d{2,8};~', $string))
		$string = westr::entity_to_utf8($string);


	if (preg_match('~[^\x09\x0a\x0d\x20-\x7f]~', $string))
	{

		$string = base64_encode($string);


		if ($with_charset)
			$string = '=?UTF-8?B?' . $string . '?=';


		else
			$string = chunk_split($string, 76, $br);

		return array($string, 'base64');
	}

	return array($string, '7bit');
}


function smtp_mail($mail_to_array, $subject, $message, $headers)
{
	global $settings, $webmaster_email, $txt;

	$settings['smtp_host'] = trim($settings['smtp_host']);



	if ($settings['mail_type'] == 2 && $settings['smtp_username'] != '' && $settings['smtp_password'] != '')
	{
		$socket = fsockopen($settings['smtp_host'], 110, $errno, $errstr, 2);
		if (!$socket && (substr($settings['smtp_host'], 0, 5) == 'smtp.' || substr($settings['smtp_host'], 0, 11) == 'ssl://smtp.'))
			$socket = fsockopen(strtr($settings['smtp_host'], array('smtp.' => 'pop.')), 110, $errno, $errstr, 2);

		if ($socket)
		{
			fgets($socket, 256);
			fputs($socket, 'USER ' . $settings['smtp_username'] . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'PASS ' . base64_decode($settings['smtp_password']) . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'QUIT' . "\r\n");

			fclose($socket);
		}
	}


	if (!$socket = fsockopen($settings['smtp_host'], empty($settings['smtp_port']) ? 25 : $settings['smtp_port'], $errno, $errstr, 3))
	{

		if (substr($settings['smtp_host'], 0, 4) == 'ssl:' && (empty($settings['smtp_port']) || $settings['smtp_port'] == 25))
		{
			if ($socket = fsockopen($settings['smtp_host'], 465, $errno, $errstr, 3))
				log_error($txt['smtp_port_ssl']);
		}


		if (!$socket)
		{
			log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
			return false;
		}
	}


	if (!server_parse(null, $socket, '220'))
		return false;

	if ($settings['mail_type'] == 1 && $settings['smtp_username'] != '' && $settings['smtp_password'] != '')
	{



		if (server_parse('EHLO ' . $settings['smtp_host'], $socket, null) == '250')
		{
			if (!server_parse('AUTH LOGIN', $socket, '334'))
				return false;

			if (!server_parse(base64_encode($settings['smtp_username']), $socket, '334'))
				return false;

			if (!server_parse($settings['smtp_password'], $socket, '235'))
				return false;
		}
		elseif (!server_parse('HELO ' . $settings['smtp_host'], $socket, '250'))
			return false;
	}
	else
	{

		if (!server_parse('HELO ' . $settings['smtp_host'], $socket, '250'))
			return false;
	}


	$message = strtr($message, array("\r\n" . '.' => "\r\n" . '..'));


	$mail_to_array = array_values($mail_to_array);
	foreach ($mail_to_array as $i => $mail_to)
	{

		if ($i != 0)
		{
			if (!server_parse('RSET', $socket, '250'))
				return false;
		}


		if (!server_parse('MAIL FROM: <' . (empty($settings['mail_from']) ? $webmaster_email : $settings['mail_from']) . '>', $socket, '250'))
			return false;
		if (!server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
			return false;
		if (!server_parse('DATA', $socket, '354'))
			return false;
		fputs($socket, 'Subject: ' . $subject . "\r\n");
		if (strlen($mail_to) > 0)
			fputs($socket, 'To: <' . $mail_to . '>' . "\r\n");
		fputs($socket, $headers . "\r\n\r\n");
		fputs($socket, $message . "\r\n");


		if (!server_parse('.', $socket, '250'))
			return false;


		@set_time_limit(300);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();
	}
	fputs($socket, 'QUIT' . "\r\n");
	fclose($socket);

	return true;
}


function server_parse($message, $socket, $response)
{
	global $txt;

	if ($message !== null)
		fputs($socket, $message . "\r\n");


	$server_response = '';

	while (substr($server_response, 3, 1) != ' ')
		if (!($server_response = fgets($socket, 256)))
		{

			log_error($txt['smtp_bad_response']);
			return false;
		}

	if ($response === null)
		return substr($server_response, 0, 3);

	if (substr($server_response, 0, 3) != $response)
	{
		log_error($txt['smtp_error'] . $server_response);
		return false;
	}

	return true;
}


function sendNotifications($topics, $type, $exclude = array(), $members_only = array())
{
	global $settings;


	if (empty($topics))
		return;

	if (!is_array($topics))
		$topics = array($topics);


	$result = wesql::query('
		SELECT mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic,
			IFNULL(mem.real_name, ml.poster_name) AS poster_name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
		WHERE t.id_topic IN ({array_int:topic_list})
		LIMIT 1',
		array(
			'topic_list' => $topics,
		)
	);
	$topicData = array();
	while ($row = wesql::fetch_assoc($result))
	{

		censorText($row['subject']);
		censorText($row['body']);
		$row['subject'] = un_htmlspecialchars($row['subject']);
		$row['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($row['body'], 'post-notify', array('smileys' => false, 'cache' => $row['id_last_msg'])), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

		$topicData[$row['id_topic']] = array(
			'subject' => $row['subject'],
			'body' => $row['body'],
			'last_id' => $row['id_last_msg'],
			'topic' => $row['id_topic'],
			'name' => we::$user['name'],
			'exclude' => '',
		);
	}
	wesql::free_result($result);


	foreach ($topics as $key => $id)
		if (isset($topicData[$id]) && !empty($exclude[$key]))
			$topicData[$id]['exclude'] = (int) $exclude[$key];


	if (empty($topicData))
		trigger_error('sendNotifications(): topics not found', E_USER_NOTICE);

	$topics = array_keys($topicData);

	if (empty($topics))
		return;


	$digest_insert = array();
	foreach ($topicData as $id => $data)
		$digest_insert[] = array($data['topic'], $data['last_id'], $type, (int) $data['exclude']);
	wesql::insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert
	);


	$members = wesql::query('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
			ln.id_topic
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
			AND mem.is_activated = {int:is_activated}
			AND ln.id_member != {int:current_member}' .
			(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
		ORDER BY mem.lngfile',
		array(
			'current_member' => MID,
			'topic_list' => $topics,
			'notify_types' => $type == 'reply' ? '4' : '3',
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : array($members_only),
		)
	);
	$sent = 0;
	while ($row = wesql::fetch_assoc($members))
	{

		if ($topicData[$row['id_topic']]['exclude'] == $row['id_member'])
			continue;


		if ($type != 'reply' && $row['notify_types'] == 2 && $row['id_member'] != $row['id_member_started'])
			continue;

		if ($row['id_group'] != 1)
		{
			$allowed = explode(',', $row['member_groups']);
			$row['additional_groups'] = explode(',', $row['additional_groups']);
			$row['additional_groups'][] = $row['id_group'];
			$row['additional_groups'][] = $row['id_post_group'];

			if (count(array_intersect($allowed, $row['additional_groups'])) == 0)
				continue;
		}

		$needed_language = empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$message_type = 'notification_' . $type;
		$replacements = array(
			'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
			'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
			'TOPICLINK' => SCRIPT . '?topic=' . $row['id_topic'] . '.new#new',
			'UNSUBSCRIBELINK' => SCRIPT . '?action=notify;topic=' . $row['id_topic'] . '.0',
		);

		if ($type == 'remove')
			unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);

		if (!empty($row['notify_send_body']) && $type == 'reply' && empty($settings['disallow_sendBody']))
		{
			$message_type .= '_body';
			$replacements['MESSAGE'] = $topicData[$row['id_topic']]['body'];
		}
		if (!empty($row['notify_regularity']) && $type == 'reply')
			$message_type .= '_once';


		if ($type != 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
		{
			$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
			$sent++;
		}
	}
	wesql::free_result($members);

	if (isset($current_language) && $current_language != we::$user['language'])
		loadLanguage('Post');


	if ($type == 'reply' && !empty($sent))
		wesql::query('
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => MID,
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);


	if (!empty($sent) && !empty($exclude))
	{
		foreach ($topicData as $id => $data)
			if ($data['exclude'])
				wesql::query('
					UPDATE {db_prefix}log_notify
					SET sent = {int:not_sent}
					WHERE id_topic = {int:id_topic}
						AND id_member = {int:id_member}',
					array(
						'not_sent' => 0,
						'id_topic' => $id,
						'id_member' => $data['exclude'],
					)
				);
	}
}






function createPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $txt, $settings;


	$msgOptions['icon'] = empty($msgOptions['icon']) ? 'xx' : $msgOptions['icon'];
	$msgOptions['smileys_enabled'] = !empty($msgOptions['smileys_enabled']);
	$msgOptions['attachments'] = empty($msgOptions['attachments']) ? array() : $msgOptions['attachments'];
	$msgOptions['approved'] = isset($msgOptions['approved']) ? (int) $msgOptions['approved'] : 1;
	$msgOptions['parent'] = isset($msgOptions['parent']) ? (int) $msgOptions['parent'] : 0;
	$msgOptions['data'] = isset($msgOptions['data']) ? (array) $msgOptions['data'] : array();
	$topicOptions['id'] = empty($topicOptions['id']) ? 0 : (int) $topicOptions['id'];
	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['pin_mode'] = isset($topicOptions['pin_mode']) ? $topicOptions['pin_mode'] : null;
	$topicOptions['privacy'] = isset($topicOptions['privacy']) && preg_match('~^-?\d+$~', $topicOptions['privacy']) ? $topicOptions['privacy'] : null;
	$posterOptions['id'] = empty($posterOptions['id']) ? 0 : (int) $posterOptions['id'];
	$posterOptions['ip'] = empty($posterOptions['ip']) ? we::$user['ip'] : $posterOptions['ip'];


	if (!$settings['postmod_active'])
		$topicOptions['is_approved'] = true;
	elseif (!empty($topicOptions['id']) && !isset($topicOptions['is_approved']))
	{
		$request = wesql::query('
			SELECT approved
			FROM {db_prefix}topics
			WHERE id_topic = {int:id_topic}
			LIMIT 1',
			array(
				'id_topic' => $topicOptions['id'],
			)
		);
		list ($topicOptions['is_approved']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}


	if (!isset($posterOptions['name']) || $posterOptions['name'] == '' || (empty($posterOptions['email']) && !empty($posterOptions['id'])))
	{
		if (empty($posterOptions['id']))
		{
			$posterOptions['id'] = 0;
			$posterOptions['name'] = $txt['guest_title'];
			$posterOptions['email'] = '';
		}
		elseif ($posterOptions['id'] != MID)
		{
			$request = wesql::query('
				SELECT member_name, email_address
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $posterOptions['id'],
				)
			);

			if (wesql::num_rows($request) == 0)
			{
				trigger_error('createPost(): Invalid member id ' . $posterOptions['id'], E_USER_NOTICE);
				$posterOptions['id'] = 0;
				$posterOptions['name'] = $txt['guest_title'];
				$posterOptions['email'] = '';
			}
			else
				list ($posterOptions['name'], $posterOptions['email']) = wesql::fetch_row($request);
			wesql::free_result($request);
		}
		else
		{
			$posterOptions['name'] = we::$user['name'];
			$posterOptions['email'] = we::$user['email'];
		}
	}

	$new_topic = empty($topicOptions['id']);



	call_hook('create_post_before', array(&$msgOptions, &$topicOptions, &$posterOptions, &$new_topic));


	$previous_ignore_user_abort = ignore_user_abort(true);


	wesql::insert('',
		'{db_prefix}messages',
		array(
			'id_board' => 'int', 'id_topic' => 'int', 'id_member' => 'int', 'subject' => 'string-255', 'id_parent' => 'int',
			'body' => isset($settings['max_messageLength']) && ($settings['max_messageLength'] == 0 || $settings['max_messageLength'] > 65534) ? ($settings['max_messageLength'] == 0 ? 'string' : 'string-' . $settings['max_messageLength']) : 'string-65534',
			'poster_name' => 'string-255', 'poster_email' => 'string-255', 'poster_time' => 'int', 'poster_ip' => 'int',
			'smileys_enabled' => 'int', 'modified_name' => 'string', 'icon' => 'string-16', 'approved' => 'int', 'data' => 'string',
		),
		array(
			$topicOptions['board'], $topicOptions['id'], $posterOptions['id'], $msgOptions['subject'], $msgOptions['parent'],
			$msgOptions['body'],
			$posterOptions['name'], $posterOptions['email'], time(), get_ip_identifier($posterOptions['ip']),
			$msgOptions['smileys_enabled'] ? 1 : 0, '', $msgOptions['icon'], $msgOptions['approved'], !empty($msgOptions['data']) ? serialize($msgOptions['data']) : '',
		)
	);
	$msgOptions['id'] = wesql::insert_id();


	if (empty($msgOptions['id']))
		return false;


	if (!empty($msgOptions['attachments']))
		wesql::query('
			UPDATE {db_prefix}attachments
			SET id_msg = {int:id_msg}
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $msgOptions['attachments'],
				'id_msg' => $msgOptions['id'],
			)
		);


	if ($new_topic)
	{
		wesql::insert('',
			'{db_prefix}topics',
			array(
				'id_board' => 'int',
				'id_member_started' => 'int', 'id_member_updated' => 'int',
				'id_first_msg' => 'int', 'id_last_msg' => 'int',
				'unapproved_posts' => 'int', 'approved' => 'int',
				'locked' => 'int',
				'is_pinned' => 'int',
				'id_poll' => 'int', 'num_views' => 'int',
				'privacy' => 'int',
			),
			array(
				$topicOptions['board'],
				$posterOptions['id'], $posterOptions['id'],
				$msgOptions['id'], $msgOptions['id'],
				$msgOptions['approved'] ? 0 : 1, $msgOptions['approved'],
				$topicOptions['lock_mode'] === null ? 0 : $topicOptions['lock_mode'],
				$topicOptions['pin_mode'] === null ? 0 : $topicOptions['pin_mode'],
				$topicOptions['poll'] === null ? 0 : $topicOptions['poll'], 0,
				$topicOptions['privacy'] === null ? PRIVACY_DEFAULT : $topicOptions['privacy'],
			)
		);
		$topicOptions['id'] = wesql::insert_id();


		if (empty($topicOptions['id']))
		{

			wesql::query('
				DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);

			return false;
		}


		wesql::query('
			UPDATE {db_prefix}messages
			SET id_topic = {int:id_topic}
			WHERE id_msg = {int:id_msg}',
			array(
				'id_topic' => $topicOptions['id'],
				'id_msg' => $msgOptions['id'],
			)
		);


		trackStats(array('topics' => '+', 'posts' => '+'));

		updateStats('topic', true);
		updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
	}

	else
	{
		$countChange = $msgOptions['approved'] ? 'num_replies = num_replies + 1' : 'unapproved_posts = unapproved_posts + 1';


		wesql::query('
			UPDATE {db_prefix}topics
			SET
				' . ($msgOptions['approved'] ? 'id_member_updated = {int:poster_id}, id_last_msg = {int:id_msg},' : '') . '
				' . $countChange . ($topicOptions['lock_mode'] === null ? '' : ',
				locked = {int:locked}') . ($topicOptions['pin_mode'] === null ? '' : ',
				is_pinned = {int:is_pinned}') . ($topicOptions['privacy'] === null ? '' : ',
				privacy = {int:privacy}') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'poster_id' => $posterOptions['id'],
				'id_msg' => $msgOptions['id'],
				'locked' => $topicOptions['lock_mode'],
				'is_pinned' => $topicOptions['pin_mode'],
				'id_topic' => $topicOptions['id'],
				'privacy' => $topicOptions['privacy'],
			)
		);


		trackStats(array('posts' => '+'));


		if (!empty($settings['merge_post_auto']) && !(we::$is_admin && empty($settings['merge_post_admin_double_post'])))
		{
			$_REQUEST['msgid'] = $msgOptions['id'];
			$_REQUEST['pid'] = $msgOptions['id'];
			$_REQUEST['topic'] = $topicOptions['id'];
			loadSource('Merge');
			MergePosts(false);
		}
	}



	wesql::query('
		UPDATE {db_prefix}messages
		SET id_msg_modified = {int:id_msg}
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $msgOptions['id'],
		)
	);


	if ($msgOptions['approved'])
		wesql::query('
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1' . ($new_topic ? ', num_topics = num_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);
	else
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET unapproved_posts = unapproved_posts + 1' . ($new_topic ? ', unapproved_topics = unapproved_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);


		wesql::insert('',
			'{db_prefix}approval_queue',
			array(
				'id_msg' => 'int',
			),
			array(
				$msgOptions['id'],
			)
		);
	}


	if (!empty($topicOptions['mark_as_read']) && we::$is_member)
	{

		if (!$new_topic)
		{
			wesql::query('
				UPDATE {db_prefix}log_topics
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_topic = {int:id_topic}',
				array(
					'current_member' => $posterOptions['id'],
					'id_msg' => $msgOptions['id'],
					'id_topic' => $topicOptions['id'],
				)
			);

			$flag = wesql::affected_rows() != 0;
		}

		if (empty($flag))
		{
			wesql::insert('ignore',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
				array($topicOptions['id'], $posterOptions['id'], $msgOptions['id'])
			);
		}
	}


	if (!empty($settings['search_index']) && $settings['search_index'] != 'standard')
	{
		loadSearchAPI($settings['search_index']);
		$search_class_name = $settings['search_index'] . '_search';
		$searchAPI = new $search_class_name();
		if ($searchAPI && $searchAPI->isValid() && method_exists($searchAPI, 'putDocuments'))
			$searchAPI->putDocuments('post', array($msgOptions['id'] => $msgOptions['body']));
	}


	if (!empty($posterOptions['update_post_count']) && !empty($posterOptions['id']) && $msgOptions['approved'])
	{

		if (MID == $posterOptions['id'])
			we::$user['posts']++;
		updateMemberData($posterOptions['id'], array('posts' => '+'));
	}


	$_SESSION['last_read_topic'] = 0;


	updateStats('message', true, $msgOptions['id']);


	if ($msgOptions['approved'])
		updateLastMessages($topicOptions['board'], $new_topic || !empty($topicOptions['is_approved']) ? $msgOptions['id'] : 0);


	ignore_user_abort($previous_ignore_user_abort);


	call_hook('create_post_after', array(&$msgOptions, &$topicOptions, &$posterOptions, &$new_topic));


	return true;
}


function createAttachment(&$attachmentOptions)
{
	global $settings;

	loadSource('Subs-Graphics');


	if (!empty($settings['currentAttachmentUploadDir']))
	{
		if (!is_array($settings['attachmentUploadDir']))
			$settings['attachmentUploadDir'] = unserialize($settings['attachmentUploadDir']);


		$attach_dir = $settings['attachmentUploadDir'][$settings['currentAttachmentUploadDir']];
		$id_folder = $settings['currentAttachmentUploadDir'];
	}
	else
	{
		$attach_dir = $settings['attachmentUploadDir'];
		$id_folder = 1;
	}

	$attachmentOptions['errors'] = array();
	if (!isset($attachmentOptions['post']))
		$attachmentOptions['post'] = 0;

	$already_uploaded = preg_match('~^post_tmp_' . $attachmentOptions['poster'] . '_\d+$~', $attachmentOptions['tmp_name']) != 0;
	$file_restricted = ini_get('open_basedir') != '' && !$already_uploaded;

	if ($already_uploaded)
		$attachmentOptions['tmp_name'] = $attach_dir . '/' . $attachmentOptions['tmp_name'];


	if ((!$file_restricted && !file_exists($attachmentOptions['tmp_name'])) || (!$already_uploaded && !is_uploaded_file($attachmentOptions['tmp_name'])))
	{
		$attachmentOptions['errors'] = array('could_not_upload');
		return false;
	}


	$validImageTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff'
	);

	if (!$file_restricted || $already_uploaded)
	{
		$size = @getimagesize($attachmentOptions['tmp_name']);
		list ($attachmentOptions['width'], $attachmentOptions['height']) = $size;


		if (empty($attachmentOptions['mime_type']) && $attachmentOptions['width'])
		{

			if (!empty($size['mime']))
				$attachmentOptions['mime_type'] = $size['mime'];

			elseif (isset($validImageTypes[$size[2]]))
				$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
		}
	}

	if (!empty($attachmentOptions['mime_type']) && strpos($attachmentOptions['mime_type'], 'image/') !== 0)
		$attachmentOptions['width'] = $attachmentOptions['height'] = 0;


	if (empty($attachmentOptions['file_hash']))
		$attachmentOptions['file_hash'] = getAttachmentFilename($attachmentOptions['name'], false, null, true);


	if (!empty($settings['attachmentSizeLimit']) && $attachmentOptions['size'] > $settings['attachmentSizeLimit'] * 1024)
		$attachmentOptions['errors'][] = 'too_large';

	if (!empty($settings['attachmentCheckExtensions']))
	{
		$allowed = explode(',', strtolower($settings['attachmentExtensions']));
		foreach ($allowed as $k => $dummy)
			$allowed[$k] = trim($dummy);

		if (!in_array(strtolower(substr(strrchr($attachmentOptions['name'], '.'), 1)), $allowed))
			$attachmentOptions['errors'][] = 'bad_extension';
	}

	if (!empty($settings['attachmentDirSizeLimit']))
	{

		$dirSize = 0;
		$dir = @scandir($attach_dir) or fatal_lang_error('cant_access_upload_path', 'critical');
		foreach ($dir as $file)
		{
			if ($file == '.' || $file == '..')
				continue;

			if (preg_match('~^post_tmp_\d+_\d+$~', $file) != 0)
			{

				if (filemtime($attach_dir . '/' . $file) < time() - 18000)
					@unlink($attach_dir . '/' . $file);
				continue;
			}

			$dirSize += filesize($attach_dir . '/' . $file);
		}


		if ($attachmentOptions['size'] + $dirSize > $settings['attachmentDirSizeLimit'] * 1024)
			$attachmentOptions['errors'][] = 'directory_full';

		elseif (!isset($settings['attachment_full_notified']) && $settings['attachmentDirSizeLimit'] > 4000 && $attachmentOptions['size'] + $dirSize > ($settings['attachmentDirSizeLimit'] - 2000) * 1024)
		{
			loadSource('Subs-Admin');
			emailAdmins('admin_attachments_full');
			updateSettings(array('attachment_full_notified' => 1));
		}
	}


	if (empty($settings['attachmentEncryptFilenames']))
	{

		$disabledFiles = array('con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php');
		if (in_array(strtolower(basename($attachmentOptions['name'])), $disabledFiles))
			$attachmentOptions['errors'][] = 'bad_filename';


		$request = wesql::query('
			SELECT id_attach
			FROM {db_prefix}attachments
			WHERE filename = {string:filename}
			LIMIT 1',
			array(
				'filename' => strtolower($attachmentOptions['name']),
			)
		);
		if (wesql::num_rows($request) > 0)
			$attachmentOptions['errors'][] = 'taken_filename';
		wesql::free_result($request);
	}

	if (!empty($attachmentOptions['errors']))
		return false;

	if (!is_writable($attach_dir))
		fatal_lang_error('attachments_no_write', 'critical');


	if (empty($attachmentOptions['fileext']))
	{
		$attachmentOptions['fileext'] = strtolower(strrpos($attachmentOptions['name'], '.') !== false ? substr($attachmentOptions['name'], strrpos($attachmentOptions['name'], '.') + 1) : '');
		if (strlen($attachmentOptions['fileext']) > 8 || '.' . $attachmentOptions['fileext'] == $attachmentOptions['name'])
			$attachmentOptions['fileext'] = '';
	}

	wesql::insert('',
		'{db_prefix}attachments',
		array(
			'id_folder' => 'int', 'id_msg' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
			'size' => 'int', 'width' => 'int', 'height' => 'int',
			'mime_type' => 'string-20',
		),
		array(
			$id_folder, (int) $attachmentOptions['post'], $attachmentOptions['name'], $attachmentOptions['file_hash'], $attachmentOptions['fileext'],
			(int) $attachmentOptions['size'], (empty($attachmentOptions['width']) ? 0 : (int) $attachmentOptions['width']), (empty($attachmentOptions['height']) ? '0' : (int) $attachmentOptions['height']),
			(!empty($attachmentOptions['mime_type']) ? $attachmentOptions['mime_type'] : ''),
		)
	);
	$attachmentOptions['id'] = wesql::insert_id();

	if (empty($attachmentOptions['id']))
		return false;

	$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $id_folder, false, $attachmentOptions['file_hash']);

	if ($already_uploaded)
		rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']);
	elseif (!move_uploaded_file($attachmentOptions['tmp_name'], $attachmentOptions['destination']))
		fatal_lang_error('attach_timeout', 'critical');


	@chmod($attachmentOptions['destination'], 0644);

	$size = @getimagesize($attachmentOptions['destination']);
	list ($attachmentOptions['width'], $attachmentOptions['height']) = empty($size) ? array(null, null) : $size;


	if ($file_restricted)
	{

		if (empty($attachmentOptions['mime_type']) && $attachmentOptions['width'])
		{
			if (!empty($size['mime']))
				$attachmentOptions['mime_type'] = $size['mime'];
			elseif (isset($validImageTypes[$size[2]]))
				$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
		}

		if (!empty($attachmentOptions['mime_type']) && strpos($attachmentOptions['mime_type'], 'image/') !== 0)
			$attachmentOptions['width'] = $attachmentOptions['height'] = 0;

		if (!empty($attachmentOptions['width']) && !empty($attachmentOptions['height']))
			wesql::query('
				UPDATE {db_prefix}attachments
				SET
					width = {int:width},
					height = {int:height},
					mime_type = {string:mime_type}
				WHERE id_attach = {int:id_attach}',
				array(
					'width' => (int) $attachmentOptions['width'],
					'height' => (int) $attachmentOptions['height'],
					'id_attach' => $attachmentOptions['id'],
					'mime_type' => empty($attachmentOptions['mime_type']) ? '' : $attachmentOptions['mime_type'],
				)
			);
	}



	if (isset($validImageTypes[$size[2]]))
	{
		if (!checkImageContents($attachmentOptions['destination'], !empty($settings['attachment_image_paranoid'])))
		{

			if (empty($settings['attachment_image_reencode']) || (!reencodeImage($attachmentOptions['destination'], $size[2])))
			{

				loadSource('ManageAttachments');
				removeAttachments(array(
					'id_attach' => $attachmentOptions['id']
				));
				$attachmentOptions['id'] = null;
				$attachmentOptions['errors'][] = 'bad_attachment';

				return false;
			}


			$old_format = $size[2];
			$size = @getimagesize($attachmentOptions['destination']);
			if (!(empty($size)) && ($size[2] != $old_format))
			{



				if (isset($validImageTypes[$size[2]]))
				{
					$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
					wesql::query('
						UPDATE {db_prefix}attachments
						SET
							mime_type = {string:mime_type}
						WHERE id_attach = {int:id_attach}',
						array(
							'id_attach' => $attachmentOptions['id'],
							'mime_type' => $attachmentOptions['mime_type'],
						)
					);
				}
			}
		}
	}

	if (!empty($attachmentOptions['skip_thumbnail']) || (empty($attachmentOptions['width']) && empty($attachmentOptions['height'])))
		return true;


	if (!empty($settings['attachmentThumbnails']) && !empty($settings['attachmentThumbWidth']) && !empty($settings['attachmentThumbHeight']) && ($attachmentOptions['width'] > $settings['attachmentThumbWidth'] || $attachmentOptions['height'] > $settings['attachmentThumbHeight']))
	{
		if (createThumbnail($attachmentOptions['destination'], $settings['attachmentThumbWidth'], $settings['attachmentThumbHeight']))
		{

			$size = @getimagesize($attachmentOptions['destination'] . '_thumb');
			list ($thumb_width, $thumb_height) = $size;

			if (!empty($size['mime']))
				$thumb_mime = $size['mime'];
			elseif (isset($validImageTypes[$size[2]]))
				$thumb_mime = 'image/' . $validImageTypes[$size[2]];

			else
				$thumb_mime = '';

			$thumb_filename = $attachmentOptions['name'] . '_thumb';
			$thumb_size = filesize($attachmentOptions['destination'] . '_thumb');
			$thumb_file_hash = getAttachmentFilename($thumb_filename, false, null, true);


			wesql::insert('',
				'{db_prefix}attachments',
				array(
					'id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40',
					'fileext' => 'string-8', 'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20',
				),
				array(
					$id_folder, (int) $attachmentOptions['post'], 3, $thumb_filename, $thumb_file_hash,
					$attachmentOptions['fileext'], $thumb_size, $thumb_width, $thumb_height, $thumb_mime,
				)
			);
			$attachmentOptions['thumb'] = wesql::insert_id();

			if (!empty($attachmentOptions['thumb']))
			{
				wesql::query('
					UPDATE {db_prefix}attachments
					SET id_thumb = {int:id_thumb}
					WHERE id_attach = {int:id_attach}',
					array(
						'id_thumb' => $attachmentOptions['thumb'],
						'id_attach' => $attachmentOptions['id'],
					)
				);

				rename($attachmentOptions['destination'] . '_thumb', getAttachmentFilename($thumb_filename, $attachmentOptions['thumb'], $id_folder, false, $thumb_file_hash));
			}
		}
	}

	return true;
}


function modifyPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $settings;

	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['pin_mode'] = isset($topicOptions['pin_mode']) ? $topicOptions['pin_mode'] : null;
	$topicOptions['privacy'] = isset($topicOptions['privacy']) && preg_match('~^-?\d+$~', $topicOptions['privacy']) ? $topicOptions['privacy'] : null;


	call_hook('modify_post_before', array(&$msgOptions, &$topicOptions, &$posterOptions));


	$messages_columns = array();
	if (isset($posterOptions['name']))
		$messages_columns['poster_name'] = $posterOptions['name'];
	if (isset($posterOptions['email']))
		$messages_columns['poster_email'] = $posterOptions['email'];
	if (isset($msgOptions['icon']))
		$messages_columns['icon'] = $msgOptions['icon'];
	if (isset($msgOptions['subject']))
		$messages_columns['subject'] = $msgOptions['subject'];
	if (isset($msgOptions['body']))
	{
		$messages_columns['body'] = $msgOptions['body'];

		if (!empty($settings['search_index']) && $settings['search_index'] != 'standard')
		{
			$request = wesql::query('
				SELECT body
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);
			list ($old_body) = wesql::fetch_row($request);
			wesql::free_result($request);
		}
	}
	if (isset($msgOptions['data']))
		$messages_columns['data'] = !empty($msgOptions['data']) ? serialize($msgOptions['data']) : '';
	if (!empty($msgOptions['modify_time']))
	{
		$messages_columns['modified_time'] = $msgOptions['modify_time'];
		$messages_columns['modified_name'] = $msgOptions['modify_name'];
		$messages_columns['modified_member'] = $msgOptions['modify_member'];
		$messages_columns['id_msg_modified'] = $settings['maxMsgID'];
	}
	if (isset($msgOptions['smileys_enabled']))
		$messages_columns['smileys_enabled'] = empty($msgOptions['smileys_enabled']) ? 0 : 1;


	$messageInts = array('modified_time', 'modified_member', 'id_msg_modified', 'smileys_enabled');
	$update_parameters = array(
		'id_msg' => $msgOptions['id'],
	);

	foreach ($messages_columns as $var => $val)
	{
		$messages_columns[$var] = $var . ' = {' . (in_array($var, $messageInts) ? 'int' : 'string') . ':var_' . $var . '}';
		$update_parameters['var_' . $var] = $val;
	}


	if ($topicOptions['pin_mode'] !== null || $topicOptions['lock_mode'] !== null || $topicOptions['poll'] !== null || $topicOptions['privacy'] !== null)
	{
		wesql::query('
			UPDATE {db_prefix}topics
			SET
				is_pinned = {raw:is_pinned},
				locked = {raw:locked},
				id_poll = {raw:id_poll}' . ($topicOptions['privacy'] === null ? '' : ',
				privacy = {int:privacy}') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'is_pinned' => $topicOptions['pin_mode'] === null ? 'is_pinned' : (int) $topicOptions['pin_mode'],
				'locked' => $topicOptions['lock_mode'] === null ? 'locked' : (int) $topicOptions['lock_mode'],
				'id_poll' => $topicOptions['poll'] === null ? 'id_poll' : (int) $topicOptions['poll'],
				'privacy' => $topicOptions['privacy'] === null ? 'privacy' : (int) $topicOptions['privacy'],
				'id_topic' => $topicOptions['id'],
			)
		);
	}


	if (empty($messages_columns))
		return true;


	wesql::query('
		UPDATE {db_prefix}messages
		SET ' . implode(', ', $messages_columns) . '
		WHERE id_msg = {int:id_msg}',
		$update_parameters
	);


	if (!empty($topicOptions['mark_as_read']) && we::$is_member)
	{

		wesql::query('
			UPDATE {db_prefix}log_topics
			SET id_msg = {int:id_msg}
			WHERE id_member = {int:current_member}
				AND id_topic = {int:id_topic}',
			array(
				'current_member' => MID,
				'id_msg' => max($msgOptions['id'], $settings['maxMsgID']),
				'id_topic' => $topicOptions['id'],
			)
		);

		if (wesql::affected_rows() == 0)
			wesql::insert('ignore',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
				array($topicOptions['id'], MID, max($msgOptions['id'], $settings['maxMsgID']))
			);
	}


	if (isset($old_body, $msgOptions['body']) && !empty($settings['search_index']) && $settings['search_index'] != 'standard')
	{
		loadSearchAPI($settings['search_index']);
		$search_class_name = $settings['search_index'] . '_search';
		$searchAPI = new $search_class_name();
		if ($searchAPI && $searchAPI->isValid() && method_exists($searchAPI, 'updateDocument'))
			$searchAPI->updateDocument('post', $msgOptions['id'], $old_body, $msgOptions['body']);
	}

	if (isset($msgOptions['subject']))
	{

		$request = wesql::query('
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_first_msg = {int:id_first_msg}
			LIMIT 1',
			array(
				'id_first_msg' => $msgOptions['id'],
			)
		);
		if (wesql::num_rows($request) == 1)
			updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
		wesql::free_result($request);
	}


	if ($settings['postmod_active'] && isset($msgOptions['approved']))
		approvePosts($msgOptions['id'], $msgOptions['approved']);


	call_hook('modify_post_after', array(&$msgOptions, &$topicOptions, &$posterOptions));

	return true;
}


function approvePosts($msgs, $approve = true)
{
	if (!is_array($msgs))
		$msgs = array($msgs);

	if (empty($msgs))
		return false;


	$request = wesql::query('
		SELECT m.id_msg, m.approved, m.id_topic, m.id_board, t.id_first_msg, t.id_last_msg,
			m.body, m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.id_member,
			t.approved AS topic_approved, b.count_posts, m.data
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			AND m.approved = {int:approved_state}',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 0 : 1,
		)
	);
	$msgs = array();
	$topics = array();
	$topic_changes = array();
	$board_changes = array();
	$notification_topics = array();
	$notification_posts = array();
	$member_post_changes = array();
	while ($row = wesql::fetch_assoc($request))
	{

		$msgs[$row['id_msg']] = !empty($row['data']) ? unserialize($row['data']) : array();
		$topics[] = $row['id_topic'];


		if (!isset($topic_changes[$row['id_topic']]))
			$topic_changes[$row['id_topic']] = array(
				'id_last_msg' => $row['id_last_msg'],
				'approved' => $row['topic_approved'],
				'replies' => 0,
				'unapproved_posts' => 0,
			);
		if (!isset($board_changes[$row['id_board']]))
			$board_changes[$row['id_board']] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
			);


		if ($row['id_msg'] == $row['id_first_msg'])
		{
			$topic_changes[$row['id_topic']]['approved'] = $approve ? 1 : 0;

			$board_changes[$row['id_board']]['unapproved_topics'] += $approve ? -1 : 1;
			$board_changes[$row['id_board']]['topics'] += $approve ? 1 : -1;


			$notification_topics[] = array(
				'body' => $row['body'],
				'subject' => $row['subject'],
				'name' => $row['poster_name'],
				'board' => $row['id_board'],
				'topic' => $row['id_topic'],
				'msg' => $row['id_first_msg'],
				'poster' => $row['id_member'],
			);
		}
		else
		{
			$topic_changes[$row['id_topic']]['replies'] += $approve ? 1 : -1;


			if ($row['id_msg'] > $row['id_last_msg'])
				$notification_posts[$row['id_topic']][] = array(
					'id' => $row['id_msg'],
					'body' => $row['body'],
					'subject' => $row['subject'],
					'name' => $row['poster_name'],
					'topic' => $row['id_topic'],
				);
		}


		if ($approve && $row['id_msg'] > $topic_changes[$row['id_topic']]['id_last_msg'])
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_msg'];

		elseif (!$approve)

			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_first_msg'];

		$topic_changes[$row['id_topic']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['posts'] += $approve ? 1 : -1;


		if ($row['id_member'] && empty($row['count_posts']))
			$member_post_changes[$row['id_member']] = isset($member_post_changes[$row['id_member']]) ? $member_post_changes[$row['id_member']] + 1 : 1;
	}
	wesql::free_result($request);

	if (empty($msgs))
		return;


	$easy_msg = array();
	foreach ($msgs as $msg => $data)
		if (!$approve || !isset($data['unapproved_msg']))
		{
			$easy_msg[] = $msg;
			unset($msgs[$msg]);
		}

	if (!empty($easy_msg))
		wesql::query('
			UPDATE {db_prefix}messages
			SET approved = {int:approved_state}
			WHERE id_msg IN ({array_int:message_list})',
			array(
				'message_list' => $easy_msg,
				'approved_state' => $approve ? 1 : 0,
			)
		);


	if ($approve && !empty($msgs))
	{
		foreach ($msgs as $msg => $data)
		{
			unset($data['unapproved_msg']);
			wesql::query('
				UPDATE {db_prefix}messages
				SET approved = {int:approved_state},
					data = {string:data}
				WHERE id_msg = {int:msg}',
				array(
					'approved_state' => 1,
					'data' => !empty($data) ? serialize($data) : '',
					'msg' => $msg,
				)
			);
		}
	}


	if (!$approve)
	{
		$request = wesql::query('
			SELECT id_topic, MAX(id_msg) AS id_last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND approved = {int:is_approved}
			GROUP BY id_topic',
			array(
				'topic_list' => $topics,
				'is_approved' => 1,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
		wesql::free_result($request);
	}


	foreach ($topic_changes as $id => $changes)
		wesql::query('
			UPDATE {db_prefix}topics
			SET approved = {int:approved_state}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_replies = num_replies + {int:num_replies}, id_last_msg = {int:id_last_msg}
			WHERE id_topic = {int:id_topic}',
			array(
				'approved_state' => $changes['approved'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_replies' => $changes['replies'],
				'id_last_msg' => $changes['id_last_msg'],
				'id_topic' => $id,
			)
		);


	foreach ($board_changes as $id => $changes)
		wesql::query('
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + {int:num_posts}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_topics = num_topics + {int:num_topics}, unapproved_topics = unapproved_topics + {int:unapproved_topics}
			WHERE id_board = {int:id_board}',
			array(
				'num_posts' => $changes['posts'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_topics' => $changes['topics'],
				'unapproved_topics' => $changes['unapproved_topics'],
				'id_board' => $id,
			)
		);


	if ($approve)
	{
		if (!empty($notification_topics))
		{
			loadSource('Post2');
			notifyMembersBoard($notification_topics);
		}
		if (!empty($notification_posts))
			sendApprovalNotifications($notification_posts);

		wesql::query('
			DELETE FROM {db_prefix}approval_queue
			WHERE id_msg IN ({array_int:message_list})',
			array(
				'message_list' => array_merge($easy_msg, array_keys($msgs)),
			)
		);
	}

	else
	{
		$msgInserts = array();
		foreach ($msgs as $msg)
			$msgInserts[] = array($msg);

		wesql::insert('ignore',
			'{db_prefix}approval_queue',
			array('id_msg' => 'int'),
			$msgInserts
		);
	}


	updateLastMessages(array_keys($board_changes));


	if (!empty($member_post_changes))
		foreach ($member_post_changes as $id_member => $count_change)
			updateMemberData($id_member, array('posts' => 'posts ' . ($approve ? '+' : '-') . ' ' . $count_change));

	updateStats('message');
	return true;
}


function approveTopics($topics, $approve = true)
{
	if (!is_array($topics))
		$topics = array($topics);

	if (empty($topics))
		return false;

	$approved = $approve ? 0 : 1;


	$request = wesql::query('
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topic_list})
			AND approved = {int:approved_state}',
		array(
			'topic_list' => $topics,
			'approved_state' => $approved,
		)
	);
	$msgs = array();
	while ($row = wesql::fetch_assoc($request))
		$msgs[] = $row['id_msg'];
	wesql::free_result($request);

	return approvePosts($msgs, $approve);
}


function sendApprovalNotifications(&$topicData)
{
	global $settings;


	if (!is_array($topicData) || empty($topicData))
		return;

	$topics = array();
	$digest_insert = array();
	foreach ($topicData as $topic => $msgs)
	{
		foreach ($msgs as $msgKey => $msg)
		{
			censorText($topicData[$topic][$msgKey]['subject']);
			censorText($topicData[$topic][$msgKey]['body']);
			$topicData[$topic][$msgKey]['subject'] = un_htmlspecialchars($topicData[$topic][$msgKey]['subject']);
			$topicData[$topic][$msgKey]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$topic][$msgKey]['body'], 'post-notify', array('smileys' => false)), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

			$topics[] = $msg['id'];
			$digest_insert[] = array($msg['topic'], $msg['id'], 'reply', MID);
		}
	}


	wesql::insert('',
		'{db_prefix}log_digest',
		array('id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int'),
		$digest_insert
	);


	$members = wesql::query('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
			ln.id_topic
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		GROUP BY mem.id_member, ln.id_topic, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile, ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started
		ORDER BY mem.lngfile',
		array(
			'topic_list' => $topics,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	$sent = 0;
	while ($row = wesql::fetch_assoc($members))
	{
		if ($row['id_group'] != 1)
		{
			$allowed = explode(',', $row['member_groups']);
			$row['additional_groups'] = explode(',', $row['additional_groups']);
			$row['additional_groups'][] = $row['id_group'];
			$row['additional_groups'][] = $row['id_post_group'];

			if (count(array_intersect($allowed, $row['additional_groups'])) == 0)
				continue;
		}

		$needed_language = empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$sent_this_time = false;

		foreach ($topicData[$row['id_topic']] as $msg)
		{
			$replacements = array(
				'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
				'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
				'TOPICLINK' => SCRIPT . '?topic=' . $row['id_topic'] . '.new#new',
				'UNSUBSCRIBELINK' => SCRIPT . '?action=notify;topic=' . $row['id_topic'] . '.0',
			);

			$message_type = 'notification_reply';

			if (!empty($row['notify_send_body']) && empty($settings['disallow_sendBody']))
			{
				$message_type .= '_body';
				$replacements['BODY'] = $topicData[$row['id_topic']]['body'];
			}
			if (!empty($row['notify_regularity']))
				$message_type .= '_once';


			if (empty($row['notify_regularity']) || (empty($row['sent']) && !$sent_this_time))
			{
				$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
				$sent++;
			}

			$sent_this_time = true;
		}
	}
	wesql::free_result($members);

	if (isset($current_language) && $current_language != we::$user['language'])
		loadLanguage('Post');


	if (!empty($sent))
		wesql::query('
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => MID,
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);
}


function updateLastMessages($setboards, $id_msg = 0)
{
	global $board_info, $board;


	if (empty($setboards))
		return false;

	if (!is_array($setboards))
		$setboards = array($setboards);


	if (!$id_msg)
	{

		$request = wesql::query('
			SELECT id_board, MAX(id_last_msg) AS id_msg
			FROM {db_prefix}topics
			WHERE id_board IN ({array_int:board_list})
				AND approved = {int:is_approved}
				AND privacy = {int:privacy}
			GROUP BY id_board',
			array(
				'board_list' => $setboards,
				'is_approved' => 1,
				'privacy' => PRIVACY_DEFAULT,
			)
		);
		$lastMsg = array();
		while ($row = wesql::fetch_assoc($request))
			$lastMsg[$row['id_board']] = $row['id_msg'];
		wesql::free_result($request);
	}
	else
	{

		foreach ($setboards as $id_board)
			$lastMsg[$id_board] = $id_msg;
	}

	$parent_boards = array();

	$lastModified = $lastMsg;

	foreach ($setboards as $id_board)
	{
		if (!isset($lastMsg[$id_board]))
		{
			$lastMsg[$id_board] = 0;
			$lastModified[$id_board] = 0;
		}

		if (!empty($board) && $id_board == $board)
			$parents = $board_info['parent_boards'];
		else
			$parents = getBoardParents($id_board);



		foreach ($parents as $id => $parent)
		{
			if ($parent['level'] != 0)
			{

				if (isset($lastModified[$id]) && $lastModified[$id_board] > $lastModified[$id])
					$lastModified[$id] = $lastModified[$id_board];
				elseif (!isset($lastModified[$id]) && (!isset($parent_boards[$id]) || $parent_boards[$id] < $lastModified[$id_board]))
					$parent_boards[$id] = $lastModified[$id_board];
			}
		}
	}




	$board_updates = array();
	$parent_updates = array();

	foreach ($parent_boards as $id => $msg)
	{
		if (!isset($parent_updates[$msg]))
			$parent_updates[$msg] = array($id);
		else
			$parent_updates[$msg][] = $id;
	}

	foreach ($lastMsg as $id => $msg)
	{
		if (!isset($board_updates[$msg . '-' . $lastModified[$id]]))
			$board_updates[$msg . '-' . $lastModified[$id]] = array(
				'id' => $msg,
				'updated' => $lastModified[$id],
				'boards' => array($id)
			);

		else
			$board_updates[$msg . '-' . $lastModified[$id]]['boards'][] = $id;
	}


	foreach ($parent_updates as $id_msg => $boards)
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})
				AND id_msg_updated < {int:id_msg_updated}',
			array(
				'board_list' => $boards,
				'id_msg_updated' => $id_msg,
			)
		);
	}
	foreach ($board_updates as $bdata)
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})',
			array(
				'board_list' => $bdata['boards'],
				'id_last_msg' => $bdata['id'],
				'id_msg_updated' => $bdata['updated'],
			)
		);
	}
}


function adminNotify($type, $memberID, $member_name = null)
{
	global $settings;


	$notify_list = !empty($settings['notify_new_registration']) ? unserialize($settings['notify_new_registration']) : array();
	if (empty($notify_list))
		return;

	if ($member_name == null)
	{

		$request = wesql::query('
			SELECT real_name
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $memberID,
			)
		);
		list ($member_name) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	$toNotify = array();
	$groups = array();


	$request = wesql::query('
		SELECT id_member, lngfile, email_address
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:members})
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'members' => $notify_list,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$replacements = array(
			'USERNAME' => $member_name,
			'PROFILELINK' => SCRIPT . '?action=profile;u=' . $memberID
		);
		$emailtype = 'admin_notify';


		if ($type == 'approval')
		{
			$replacements['APPROVALLINK'] = SCRIPT . '?action=admin;area=viewmembers;sa=browse;type=approve';
			$emailtype .= '_approval';
		}

		$emaildata = loadEmailTemplate($emailtype, $replacements, empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile']);


		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
	}
	wesql::free_result($request);

	if (isset($current_language) && $current_language != we::$user['language'])
		loadLanguage('Login');
}

function loadEmailTemplate($template, $replacements = array(), $lang = '', $loadLang = true)
{
	global $txt, $mbname, $context;


	if ($loadLang)
		loadLanguage('EmailTemplates', $lang);

	if (!isset($txt['emailtemplate_' . $template]))
		fatal_lang_error('email_no_template', 'template', array($template));


	$ret = $txt['emailtemplate_' . $template];


	$replacements += array(
		'FORUMNAME' => $mbname,
		'SCRIPTURL' => SCRIPT,
		'IMAGESURL' => ASSETS,
		'REGARDS' => str_replace('{FORUMNAME}', $context['forum_name'], $txt['regards_team']),
	);


	$find = array();
	$replace = array();

	foreach ($replacements as $f => $r)
	{
		$find[] = '{' . $f . '}';
		$replace[] = $r;
	}


	$ret['subject'] = str_replace($find, $replace, $ret['subject']);
	$ret['body'] = str_replace($find, $replace, $ret['body']);


	$ret['subject'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['subject']);
	$ret['body'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['body']);


	return $ret;
}

function user_info_callback($matches)
{
	if (empty($matches[1]))
		return '';

	$use_ref = true;
	$ref =& we::$user;

	foreach (explode('.', $matches[1]) as $index)
	{
		if ($use_ref && isset($ref[$index]))
			$ref =& $ref[$index];
		else
		{
			$use_ref = false;
			break;
		}
	}

	return $use_ref ? $ref : $matches[0];
}














function saveDraft($is_pm, $id_context = 0)
{
	global $txt, $board, $settings;


	if (we::$is_guest || !empty($_REQUEST['msg']))
		return false;


	if ((!$is_pm && (!allowedTo('save_post_draft') || empty($settings['masterSavePostDrafts']))) || ($is_pm && (!allowedTo('save_pm_draft') || empty($settings['masterSavePmDrafts']))))
		return false;


	$subject = isset($_POST['subject']) ? westr::htmltrim(westr::htmlspecialchars($_POST['subject'])) : '';
	$message = isset($_POST['message']) ? westr::htmlspecialchars($_POST['message'], ENT_QUOTES) : '';

	if ($subject === '' && westr::htmltrim($message) === '')
	{
		if (!isset($txt['empty_draft']))
			loadLanguage('Post');
		fatal_lang_error('empty_draft', false);
	}


	$icon = isset($_POST['icon']) ? preg_replace('~[./\\\\*:"\'<>]~', '', $_POST['icon']) : 'xx';
	$is_pm = (bool) $is_pm;
	$id_context = (int) $id_context;

	loadSource('Class-Editor');


	if ($is_pm)
		wedit::preparseWYSIWYG('message');
	wedit::preparsecode($message);

	$extra = array();


	$_REQUEST['draft_id'] = isset($_REQUEST['draft_id']) ? (int) $_REQUEST['draft_id'] : 0;
	if (!empty($_REQUEST['draft_id']))
	{

		$query = wesql::query('
			SELECT extra
			FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND is_pm = {int:is_pm}
				AND id_context = {int:id_context}
				AND id_member = {int:id_member}',
			array(
				'draft' => $_REQUEST['draft_id'],
				'is_pm' => $is_pm ? 1 : 0,
				'id_context' => $id_context,
				'id_member' => MID,
			)
		);

		if ($row = wesql::fetch_row($query))
		{
			$extra = empty($row[0]) ? array() : unserialize($row[0]);
			$found = true;
		}
		wesql::free_result($query);
	}


	if (!$is_pm)
	{
		$extra['post_icon'] = $icon;
		$extra['smileys_enabled'] = !isset($_POST['ns']) ? 1 : 0;



		call_hook('save_post_draft', array(&$subject, &$message, &$extra, &$is_pm, &$id_context));
	}
	else
	{

		$recipientList = array();
		$namedRecipientList = array();
		$namesNotFound = array();
		getPmRecipients($recipientList, $namedRecipientList, $namesNotFound);

		$extra['recipients'] = $recipientList;

		call_hook('save_pm_draft', array(&$subject, &$message, &$extra, &$is_pm, &$id_context));
	}
	$extra = serialize($extra);

	if (!empty($found))
	{
		wesql::query('
			UPDATE {db_prefix}drafts
			SET subject = {string:subject},
				body = {string:body},
				post_time = {int:post_time},
				extra = {string:extra}
			WHERE id_draft = {int:id_draft}
				AND is_pm = {int:is_pm}
				AND id_context = {int:id_context}
				AND id_member = {int:id_member}',
			array(
				'subject' => $subject,
				'body' => $message,
				'post_time' => time(),
				'extra' => $extra,
				'id_draft' => $_REQUEST['draft_id'],
				'id_member' => MID,
				'is_pm' => !empty($is_pm) ? 1 : 0,
				'id_context' => $id_context,
			)
		);

		if (wesql::affected_rows() != 0)
			return $_REQUEST['draft_id'];
	}


	wesql::insert('',
		'{db_prefix}drafts',
		array(
			'id_member' => 'int',
			'subject' => 'string',
			'body' => 'string',
			'post_time' => 'int',
			'is_pm' => 'int',
			'id_board' => 'int',
			'id_context' => 'int',
			'extra' => 'string',
		),
		array(
			MID,
			$subject,
			$message,
			time(),
			$is_pm ? 1 : 0,
			$board,
			$id_context,
			$extra,
		)
	);

	return wesql::insert_id();
}

function draftXmlReturn($draft, $is_pm)
{
	global $txt;


	return_xml('<draft id="', $draft, '" url="<URL>', $is_pm ? '?action=pm;sa=showdrafts;delete=%id%' : '?action=profile;area=showdrafts;delete=%id%', '"><![CD', 'ATA[', $txt['last_saved_on'], ': ', timeformat(time()), ']', ']></draft>');
}
