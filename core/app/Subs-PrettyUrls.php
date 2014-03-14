<?php
/**
 * Support for handling URL post-processing.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Generate a pretty URL from a given text
function pretty_generate_url($text, $is_board = false, $slash = false)
{
	// Do you know your ABCs?
	$characterHash = array(
		'-' =>	array(';-)', ';)', ';o)', ':-)', ':)', ':o)', '^^', '^_^', ';-p', ':-p', ';-P', ':-P', ':D', ';D', '>_<', '°_°', '@_@', '^o^', ':-/'),
		chr(18)	=> array("'", 'ﺀ', 'ع', '‘', '’'),
		'('	=>	array('{', '['),
		')'	=>	array('}', ']'),
		'a'	=>	array('ª', 'ą', 'Ą', 'а', 'А', 'ạ', 'Ạ', 'ả', 'Ả', 'Ầ', 'ầ', 'Ấ', 'ấ', 'Ậ', 'ậ', 'Ẩ', 'ẩ',
					  'Ẫ', 'ẫ', 'Ă', 'ă', 'Ắ', 'ắ', 'Ẵ', 'ẵ', 'Ặ', 'ặ', 'Ằ', 'ằ', 'Ẳ', 'ẳ', 'α', 'Α'),
		'b'	=>	array('б', 'Б', 'ب'),
		'c'	=>	array('ć', 'Ć', 'č', 'Č', '¢', '©', '&copy;'),
		'ch' =>	array('ч', 'Ч', 'χ', 'Χ'),
		'd'	=>	array('Ð', 'д', 'Д', 'د', 'ض', 'đ', 'Đ', 'δ', 'Δ'),
		'e'	=>	array('ę', 'Ę', 'е', 'Е', 'ё', 'Ё', 'э', 'Э', 'Ẹ', 'ẹ', 'Ẻ', 'ẻ', 'Ẽ', 'ẽ',
					  'Ề', 'ề', 'Ế', 'ế', 'Ệ', 'ệ', 'Ể', 'ể', 'Ễ', 'ễ', 'ε', 'Ε', '€'),
		'f'	=>	array('ф', 'Ф', 'ﻑ', 'φ', 'Φ'),
		'g'	=>	array('ğ', 'Ğ', 'г', 'Г', 'γ', 'Γ'),
		'h'	=>	array('ح', 'ه'),
		'i'	=>	array('ı', 'İ', 'и', 'И', 'Ị', 'ị', 'Ỉ', 'ỉ', 'Ĩ', 'ĩ', 'η', 'Η', 'Ι', 'ι'),
		'k'	=>	array('к', 'К', 'ك', 'κ', 'Κ'),
		'kh' =>	array('х', 'Х', 'خ'),
		'l'	=>	array('ł', 'Ł', 'л', 'Л', 'ل', 'λ', 'Λ'),
		'm'	=>	array('м', 'М', 'م', 'μ', 'Μ'),
		'n'	=>	array('ń', 'Ń', 'н', 'Н', 'ن', 'ν', 'Ν'),
		'o'	=>	array('°', 'º', 'о', 'О', 'Ọ', 'ọ', 'Ỏ', 'ỏ', 'Ộ', 'ộ', 'Ố', 'ố', 'Ỗ', 'ỗ', 'Ồ', 'ồ', 'Ổ',
					  'ổ', 'Ơ', 'ơ', 'Ờ', 'ờ', 'Ớ', 'ớ', 'Ợ', 'ợ', 'Ở', 'ở', 'Ỡ', 'ỡ', 'ο', 'Ο', 'ω', 'Ω'),
		'p'	=>	array('%', 'п', 'П', 'π', 'Π', '£'),
		'ps' =>	array('ψ', 'Ψ'),
		'r'	=>	array('р', 'Р', 'ر', '®', '&reg;'),
		's'	=>	array('ş', 'Ş', 'ś', 'Ś', 'с', 'С', 'س', 'ص', 'š', 'Š', 'σ', 'ς', 'Σ'),
		'sh' =>	array('ш', 'Ш', 'ش'),
		'shch' => array('щ', 'Щ'),
		't'	=>	array('т', 'Т', 'ت', 'ط', 'τ', 'Τ', 'ţ', 'Ţ'),
		'th' =>	array('ث', 'θ', 'Θ'),
		'tm' => array('™', '&trade;'),
		'ts' =>	array('ц', 'Ц'),
		'u'	=>	array('у', 'У', 'Ụ', 'ụ', 'Ủ', 'ủ', 'Ũ', 'ũ', 'Ư', 'ư', 'Ừ', 'ừ', 'Ứ', 'ứ', 'Ự', 'ự', 'Ử', 'ử', 'Ữ', 'ữ', 'υ', 'Υ'),
		'v'	=>	array('в', 'В', 'β', 'Β'),
		'x'	=>	array('×', 'ξ', 'Ξ'),
		'y'	=>	array('й', 'Й', 'ы', 'Ы', 'ي', 'Ỳ', 'ỳ', 'Ỵ', 'ỵ', 'Ỷ', 'ỷ', 'Ỹ', 'ỹ'),
		'ya' =>	array('я', 'Я'),
		'yu' =>	array('ю', 'Ю'),
		'z'	=>	array('ż', 'Ż', 'ź', 'Ź', 'з', 'З', 'ز', 'ظ', 'ž', 'Ž', 'ζ', 'Ζ'),
		'zh' =>	array('ж', 'Ж'),
	);

	// Turn numeric entities into UTF-8 for further processing.
	$text = westr::entity_to_utf8($text);

	$text = str_replace(array('&amp;', '&quot;', '¥',   'ß',  '¹', '²', '³', '©', '®', '™',  '½',   '¼',   '¾',   '§'),
						array('&',     '"',      'yen', 'ss', '1', '2', '3', 'c', 'r', 'tm', '1-2', '1-4', '3-4', 's'), $text);
	$text = str_replace(array('ج', 'ذ', 'غ', 'ﻻ', 'ق', 'و', 'ا', 'ﻯ'), array('j', 'dh', 'gh', 'la', 'q', 'w', 'aa', 'ae'), $text);

	foreach ($characterHash as $replace => $search)
		$text = str_replace($search, $replace, $text);

	if (function_exists('mb_convert_encoding'))
		$text = strtolower(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'));
	else
		$text = strtolower(htmlentities($text, ENT_NOQUOTES, 'UTF-8'));

	$text = preg_replace('~&(..?)(acute|grave|cedil|uml|circ|ring|tilde|lig|slash);~', '$1', $text);

	// Anything not converted after all of that, we'll just turn into a dash.
	$text = preg_replace(array('~&[^;]*?;~', '~[^a-z0-9\$%_' . ($slash ? '/' : '') . '-]~'), '-', $text);

	// If this is a board name, then only [a-z0-9] and hyphens are allowed -- standard host name policy.
	if ($is_board)
		$text = trim(preg_replace('~[^/a-z0-9-]~', '-', $text), '/-');

	return trim(preg_replace('~-+~', '-', $text), '-');
}

// Check a new pretty URL against the list of existing boards to ensure there won't be a conflict.
function is_already_taken($url, $id, $id_owner)
{
	global $context, $action_list, $boardurl;

	// Is the board name in the action list?
	$board_name = substr($url, strlen($boardurl) + 1);
	$forbidden = array_merge($action_list, array('do' => true));

	foreach (explode('/', $board_name) as $name)
		if (isset($forbidden[$name]))
			return -1;

	$query = wesql::query('
		SELECT id_board, url, id_owner
		FROM {db_prefix}boards AS b
		WHERE (b.url = {string:url} AND b.id_board != {int:id})' . (we::$is_admin ? '' : '
			OR (SUBSTRING(b.url, 1, {int:slashlen}) = {string:slash} AND b.id_owner != {int:owner})
			OR (b.url = SUBSTRING({string:url}, 1, urllen) AND b.id_owner != {int:owner})') . '
		LIMIT 1',
		array(
			'url' => $url,
			'slash' => $url . '/',
			'id' => $id,
			'owner' => $id_owner,
			'slashlen' => strlen($board_name) + 1,
		)
	);

	// Count that query!
	$context['pretty']['db_count']++;

	if (wesql::num_rows($query) > 0)
	{
		list ($board) = wesql::fetch_row($query);
		wesql::free_result($query);
		return $board;
	}

	wesql::free_result($query);
	return false;
}

// Update the database based on the installed filters
function pretty_update_filters()
{
	global $settings;

	// Update the settings table with our enabled filters
	updateSettings(array('pretty_filters' => serialize($settings['pretty_filters'])));

	// Clear the URLs cache
	wesql::query('
		TRUNCATE TABLE {db_prefix}pretty_urls_cache');

	// Don't rewrite anything for this page
	$settings['pretty_filters'] = array();
	$settings['pretty_enable_filters'] = false;
}

function pretty_update_topic($subject, $topic_id)
{
	global $context;

	$pretty_text = trimpercent(substr(pretty_generate_url($subject), 0, 80));

	// Can't be empty
	if ($pretty_text == '')
		$pretty_text = '-';

	// Update the database
	wesql::query('
		REPLACE INTO {db_prefix}pretty_topic_urls (id_topic, pretty_url)
		VALUES ({int:topic_id}, {string:pretty_text})',
		array(
			'topic_id' => $topic_id,
			'pretty_text' => $pretty_text
		)
	);

	// Count this query!
	if (isset($context, $context['pretty']))
		$context['pretty']['db_count']++;
}

// Remove percent-encoded multi-byte characters that were not completely trimmed at the end of a pretty URL
function trimpercent($str)
{
	if (strpos($str, '%') === false)
		return trim($str, '-' . chr(18));
	return trim(preg_replace('~(?:%f[0-4](?:%(?:[8-9a-b](?:[0-9a-f](?:%(?:[8-9a-b](?:[0-9a-f](?:%[8-9a-b]?)?)?)?)?)?)?)?|%e[0-9a-f](?:%(?:[8-9a-b](?:[0-9a-f](?:%[8-9a-b]?)?)?)?)?|%d[0-9a-f](?:%[8-9a-b]?)?|%c[2-9a-f](?:%[8-9a-b]?)?|%[0-f]?)$~', '', $str), '-' . chr(18));
}
