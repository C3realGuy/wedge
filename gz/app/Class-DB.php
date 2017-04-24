<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');

class wesql
{
	protected static $instance;
	protected static $callback_values;
	public static $link;


	private function __clone()
	{
		return false;
	}

	protected function __construct()
	{
		global $db_prefix;

		self::$callback_values = array(
			'db_prefix' => $db_prefix
		);
	}


	public static function getInstance()
	{

		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	public static function is_connected()
	{
		return (bool) self::$link;
	}

	public static function connect($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
	{
		global $mysql_set_mode, $db_link;


		$connection = mysqli_connect((!empty($db_options['persist']) ? 'p:' : '') . $db_server, $db_user, $db_passwd, empty($db_options['dont_select_db']) ? $db_name : '') or die(mysqli_connect_error());


		if (!$connection)
		{
			if (!empty($db_options['non_fatal']))
				return null;
			else
				show_db_error();
		}

		if (isset($mysql_set_mode) && $mysql_set_mode === true)
			wesql::query('SET sql_mode = \'\', AUTOCOMMIT = 1',
			array(),
			false
		);


		return self::$link = $db_link = $connection;
	}

	public static function fix_prefix(&$db_prefix, $db_name)
	{
		$db_prefix = is_numeric($db_prefix[0]) ? $db_name . '.' . $db_prefix : '`' . $db_name . '`.' . $db_prefix;
		self::register_replacement('db_prefix', $db_prefix);
	}

	public static function quote($query, $db_values, $connection = null)
	{
		global $db_callback;


		if (strpos($query, '{') !== false)
		{

			$db_callback = array($db_values, $connection == null ? self::$link : $connection);


			$query = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', 'wesql::replace_value', $query);


			$db_callback = array();
		}

		return $query;
	}

	public static function query($query, $db_values = array(), $connection = null)
	{
		global $db_cache, $db_count, $db_show_debug, $time_start;
		global $db_unbuffered, $db_callback, $settings;


		static $allowed_comments_from = array(
			'~\s+~s',
			'~/\*!40001 SQL_NO_CACHE \*/~',
			'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
			'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
		);
		static $allowed_comments_to = array(
			' ',
			'',
			'',
			'',
		);


		$connection = $connection === null ? self::$link : $connection;


		$db_count = !isset($db_count) ? 1 : $db_count + 1;

		if (empty($settings['disableQueryCheck']) && strpos($query, '\'') !== false && empty($db_values['security_override']))
			wesql::error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true);


		if (strpos($query, 'GROUP BY') !== false && strpos($query, 'ORDER BY') === false && strpos($query, 'INSERT') === false && strpos($query, 'REPLACE') === false)
		{

			if ($pos = strpos($query, 'LIMIT '))
				$query = substr($query, 0, $pos) . "\t\t\tORDER BY null\n" . substr($query, $pos, strlen($query));
			else

				$query .= "\n\t\t\tORDER BY null";
		}

		if (empty($db_values['security_override']) && (!empty($db_values) || strpos($query, '{db_prefix}') !== false))
		{

			$db_callback = array($db_values, $connection);


			$query = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', 'wesql::replace_value', $query);


			$db_callback = array();
		}


		if (!empty($db_show_debug))
		{

			list ($file, $line) = self::error_backtrace('', '', 'return');


			if (!isset($db_cache))
				$db_cache = array();

			if (!empty($_SESSION['debug_redirect']))
			{
				$db_cache = array_merge($_SESSION['debug_redirect'], $db_cache);
				$db_count = count($db_cache) + 1;
				$_SESSION['debug_redirect'] = array();
			}

			$st = microtime(true);

			$db_cache[$db_count]['q'] = $db_count < 50 ? $query : '...';
			$db_cache[$db_count]['f'] = $file;
			$db_cache[$db_count]['l'] = $line;
			$db_cache[$db_count]['s'] = $st - $time_start;
		}


		if (empty($settings['disableQueryCheck']) && (strpos($query, '/*') > 2 || strhas(strtolower($query), array('--', ';', 'sleep', 'benchmark'))))
		{

			$clean = '';
			$old_pos = 0;
			$pos = -1;
			while (true)
			{
				$pos1 = strpos($query, '\'', $pos + 1);
				$pos2 = strpos($query, '"', $pos + 1);
				if ($pos1 === false && $pos2 === false)
					break;
				$pos = min($pos1 === false ? PHP_INT_MAX : $pos1, $pos2 === false ? PHP_INT_MAX : $pos2);
				$look_for = $query[$pos];
				$clean .= substr($query, $old_pos, $pos - $old_pos);

				while (true)
				{
					$pos1 = strpos($query, $look_for, $pos + 1);
					$pos2 = strpos($query, '\\', $pos + 1);
					if ($pos1 === false)
						break;
					elseif ($pos2 == false || $pos2 > $pos1)
					{
						$pos = $pos1;
						break;
					}

					$pos = $pos2 + 1;
				}
				$clean .= ' %s ';

				$old_pos = $pos + 1;
			}
			$clean .= substr($query, $old_pos);
			$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));


			if (strpos($clean, '/*') > 2 || strhas($clean, array('--', ';')))
				$fail = true;

			elseif (strhas($clean, 'sleep') && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
				$fail = true;
			elseif (strhas($clean, 'benchmark') && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
				$fail = true;

			if (!empty($fail) && function_exists('log_error'))
				self::error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $query, E_USER_ERROR);
		}

		$ret = @mysqli_query($connection, $query, empty($db_unbuffered) ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT);

		if ($ret === false && empty($db_values['db_error_skip']))
			$ret = self::serious_error($query, $connection);


		if (!empty($db_show_debug))
			$db_cache[$db_count]['t'] = microtime(true) - $st;

		return $ret;
	}

	public static function affected_rows($connection = null)
	{
		return mysqli_affected_rows($connection === null ? self::$link : $connection);
	}

	public static function insert_id($connection = null)
	{
		$connection = $connection === null ? self::$link : $connection;
		return mysqli_insert_id($connection);
	}

	public static function transaction($operation = 'commit', $connection = null)
	{

		$connection = $connection === null ? self::$link : $connection;

		switch ($operation)
		{
				case 'begin':
				case 'rollback':
				case 'commit':
					return @mysqli_query($connection, strtoupper($operation));
			default:
				return false;
		}
	}

	public static function error($connection = null)
	{
		return mysqli_error($connection === null ? self::$link : $connection);
	}

	public static function serious_error($query, $connection = null)
	{
		global $txt, $context, $webmaster_email, $settings, $db_last_error, $db_persist;
		global $db_server, $db_user, $db_passwd, $db_name, $db_show_debug, $ssi_db_user, $ssi_db_passwd;

		if (isset($txt) && !isset($txt['mysql_error_space'], $txt['file']))
			loadLanguage(array('index', 'Errors'), '', false, true);

		if (!isset($txt['lang_name']))
			$txt = array();


		list ($file, $line) = self::error_backtrace('', '', 'return');


		$connection = $connection === null ? self::$link : $connection;


		$query_error = mysqli_error($connection);
		$query_errno = mysqli_errno($connection);












		if ($query_errno != 1213 && $query_errno != 1205 && function_exists('log_error'))
			log_error((empty($txt) ? 'Database error' : $txt['database_error']) . ': ' . $query_error . (!empty($settings['enableErrorQueryLogging']) ? "\n\n$query" : ''), 'database', $file, $line);


		if (function_exists('cache_get_data') && (!isset($settings['autoFixDatabase']) || $settings['autoFixDatabase'] == '1'))
		{

			$old_cache = @$settings['cache_enable'];
			$settings['cache_enable'] = '1';

			if (($temp = cache_get_data('db_last_error', 600)) !== null)
				$db_last_error = max(@$db_last_error, $temp);

			if (@$db_last_error < time() - 3600 * 24 * 3)
			{

				if ($query_errno == 1030 && strpos($query_error, ' 127 ') !== false)
				{
					preg_match_all('~(?:[\n\r]|^)[^\']+?(?:FROM|JOIN|UPDATE|TABLE) ((?:[^\n\r(]+?(?:, )?)*)~s', $query, $matches);

					$fix_tables = array();
					foreach ($matches[1] as $tables)
					{
						$tables = array_unique(explode(',', $tables));

						foreach ($tables as $table)
							if (trim($table) != '')
								$fix_tables[] = '`' . strtr(trim($table), array('`' => '')) . '`';
					}

					$fix_tables = array_unique($fix_tables);
				}

				elseif ($query_errno == 1016)
				{
					if (preg_match('~\'([^.\']+)~', $query_error, $match) != 0)
						$fix_tables = array('`' . $match[1] . '`');
				}

				elseif ($query_errno == 1034 || $query_errno == 1035)
				{
					preg_match('~\'([^\']+?)\'~', $query_error, $match);
					$fix_tables = array('`' . $match[1] . '`');
				}
			}


			if (!empty($fix_tables))
			{

				loadSource('Subs-Post');


				@touch(CACHE_DIR . '/error.lock');


				foreach ($fix_tables as $table)
					wesql::query("
						REPAIR TABLE $table", false, false);


				sendmail($webmaster_email, empty($txt) ? 'Database error' : $txt['database_error'], empty($txt) ? 'Please try again.' : $txt['tried_to_repair']);

				$settings['cache_enable'] = $old_cache;


				$ret = self::query($query, false, false);
				if ($ret !== false)
					return $ret;
			}
			else
				$settings['cache_enable'] = $old_cache;


			if (in_array($query_errno, array(1205, 1213, 2006, 2013)))
			{
				if (in_array($query_errno, array(2006, 2013)) && self::$link == $connection)
				{

					if (WEDGE == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
						self::$link = @mysqli_connect((!empty($db_persist) ? 'p:' : '') . $db_server, $ssi_db_user, $ssi_db_passwd);


					if (!self::$link)
						self::$link = @mysqli_connect((!empty($db_persist) ? 'p:' : '') . $db_server, $db_user, $db_passwd);

					if (!self::$link || !@mysqli_select_db(self::$link, $db_name))
						self::$link = false;
				}

				if (self::$link)
				{

					for ($n = 0; $n < 4; $n++)
					{
						$ret = self::query($query, false, false);

						$new_errno = mysqli_errno(self::$link);
						if ($ret !== false || in_array($new_errno, array(1205, 1213)))
							break;
					}


					if ($ret !== false)
						return $ret;
				}
			}

			elseif ($query_errno == 1030 && strhas($query_error, array(' -1 ', ' 28 ', ' 12 ')))
				$query_error .= !isset($txt, $txt['mysql_error_space']) ? ' - check database storage space.' : $txt['mysql_error_space'];
		}


		if (empty($context) || empty($txt))
			exit($query . '<br><br>' . $query_error);


		$context['error_title'] = $txt['database_error'];
		if (allowedTo('admin_forum'))
			$context['error_message'] = nl2br($query_error, false) . '<br>' . $txt['file'] . ': ' . $file . '<br>' . $txt['line'] . ': ' . $line;
		else
			$context['error_message'] = $txt['try_again'];

		if (allowedTo('admin_forum') && !empty($db_show_debug))
			$context['error_message'] .= '<br><br>' . nl2br($query, false);


		fatal_error($context['error_message'], false);
	}

	public static function insert($method, $table, $columns, $data)
	{
		global $db_prefix;

		$connection = self::$link;


		if (empty($data))
			return false;


		$table = str_replace('{db_prefix}', $db_prefix, $table);


		if (!is_array($data[array_rand($data)]))
			$data = array($data);



		if ($method === 'update')
		{
			foreach ($data as $id => $row)
			{
				$set = array();
				$where_val = reset($columns);
				$where_key = key($columns);
				foreach ($columns as $key => $val)
					if ($key !== $where_key)
						$set[] = $key . ' = {' . $val . ':' . $key . '}';

				self::query('
					UPDATE ' . $table . '
					SET ' . implode(', ', $set) . '
					WHERE ' . $where_key . ' = {' . $where_val . ':' . $where_key . '}',
					array_combine(array_keys($columns), $row)
				);

				if (self::affected_rows() > 0)
					unset($data[$id]);
			}
			if (empty($data))
				return true;


			$method = 'ignore';
		}


		$insertData = '(';


		if (isset($columns[0]))
		{
			$columns = array_flip($columns);
			foreach ($columns as $k => &$v)
				$v = is_int($k) ? 'int' : 'string';
		}
		foreach ($columns as $columnName => $type)
		{

			if (strpos($type, 'string-') !== false)
				$insertData .= sprintf('SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
			else
				$insertData .= sprintf('{%1$s:%2$s}, ', $type, $columnName);
		}
		$insertData = substr($insertData, 0, -2) . ')';


		$indexed_columns = array_keys($columns);


		$insertRows = array();

		foreach ($data as $dataRow)
			$insertRows[] = self::quote($insertData, array_combine($indexed_columns, $dataRow), $connection);


		$queryTitle = $method === 'replace' ? 'REPLACE' : ($method === 'ignore' ? 'INSERT IGNORE' : 'INSERT');


		return !!self::query('
			' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
			VALUES
				' . implode(',
				', $insertRows),
			array(
				'security_override' => true,
				'db_error_skip' => $table === $db_prefix . 'log_errors',
			),
			$connection
		);
	}

	public static function register_replacement($match, $value)
	{
		self::$callback_values[$match] = $value;
	}

	public static function replace_value($matches)
	{
		global $db_callback;

		list ($values, $connection) = $db_callback;
		if ($connection === null)
			$connection = self::$link;

		if (!is_object($connection))
			show_db_error();

		if (isset(self::$callback_values[$matches[1]]))
			return self::$callback_values[$matches[1]];

		if (!isset($matches[2]))
		{
			if (in_array($matches[1], array('literal', 'int', 'string', 'array_int', 'array_string', 'date', 'float', 'raw')))
				self::error_backtrace('Invalid value inserted into database, {' . $matches[1] . ':???}.', '', E_USER_ERROR);
			else
				self::error_backtrace('Invalid database variable, {' . $matches[1] . '}.', '', E_USER_ERROR);
		}

		if ($matches[1] == 'literal')
			return sprintf('\'%1$s\'', mysqli_real_escape_string($connection, $matches[2]));

		if (!isset($values[$matches[2]]))
			self::error_backtrace('The database value you\'re trying to insert does not exist: ' . htmlspecialchars($matches[2]), '', E_USER_ERROR);

		$replacement = $values[$matches[2]];

		switch ($matches[1])
		{
			case 'int':
				if (!is_numeric($replacement) || (string) $replacement !== (string) (int) $replacement)
					self::error_backtrace('Wrong value type sent to the database. Integer expected. (' . $matches[2] . ' set to ' . $replacement . ')', '', E_USER_ERROR);
				return (string) (int) $replacement;

			case 'string':
				return sprintf('\'%1$s\'', self::escape_string_replacement($replacement, $connection));

			case 'array_int':
				if (is_array($replacement))
				{
					if (empty($replacement))
						self::error_backtrace('Database error, given array of integer values is empty. (' . $matches[2] . ')', '', E_USER_ERROR);

					foreach ($replacement as $key => $value)
					{
						if (!is_numeric($value) || (string) $value !== (string) (int) $value)
							self::error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR);

						$replacement[$key] = (string) (int) $value;
					}

					return implode(', ', $replacement);
				}
				else
					self::error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR);

			case 'array_string':
				if (is_array($replacement))
				{
					if (empty($replacement))
						self::error_backtrace('Database error, given array of string values is empty. (' . $matches[2] . ')', '', E_USER_ERROR);

					foreach ($replacement as $key => $value)
						$replacement[$key] = sprintf('\'%1$s\'', self::escape_string_replacement($value, $connection));

					return implode(', ', $replacement);
				}
				else
					self::error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $matches[2] . ')', '', E_USER_ERROR);

			case 'date':
				if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1)
					return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]);
				else
					self::error_backtrace('Wrong value type sent to the database. Date expected. (' . $matches[2] . ')', '', E_USER_ERROR);

			case 'float':
				if (!is_numeric($replacement))
					self::error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $matches[2] . ')', '', E_USER_ERROR);
				return (string) (float) $replacement;

			case 'raw':
				return $replacement;

			default:
				self::error_backtrace('Undefined type used in the database query. (' . $matches[1] . ':' . $matches[2] . ')');
		}
	}

	public static function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
	{
		if (empty($log_message))
			$log_message = $error_message;

		$trace_log = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		foreach ($trace_log as $step)
		{

			if ((!isset($step['class']) || $step['class'] !== 'wesql') && strpos($step['function'], 'query') === false && (!in_array(substr($step['function'], 0, 5), array('preg_', 'mysql'))))
			{
				$log_message .= '<br>Function: ' . $step['function'];
				break;
			}

			if (isset($step['line']))
			{
				$file = $step['file'];
				$line = $step['line'];
			}
		}


		if ($error_type == 'return')
			return array($file, $line);


		if (function_exists('log_error'))
			log_error($log_message, 'critical', $file, $line);

		if (function_exists('fatal_error'))
		{
			fatal_error($error_message, false);


			exit;
		}
		elseif ($error_type)
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
		else
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));
	}

	public static function escape_wildcard_string($string, $translate_human_wildcards = false)
	{
		$replacements = array(
			'%' => '\%',
			'_' => '\_',
			'\\' => '\\\\',
		);

		if ($translate_human_wildcards)
			$replacements += array(
				'*' => '%',
			);

		return strtr($string, $replacements);
	}

	public static function fetch_assoc($result)
	{
		return mysqli_fetch_assoc($result);
	}

	public static function fetch_row($result)
	{
		return mysqli_fetch_row($result);
	}

	public static function fetch_all($result, $type = MYSQLI_ASSOC)
	{
		if ($result === false)
			return array();
		if (function_exists('mysqli_fetch_all'))
			return (array) mysqli_fetch_all($result, $type);
		$arr = array();
		$func_name = $type === MYSQLI_ASSOC ? 'mysqli_fetch_assoc' : 'mysqli_fetch_row';
		while ($row = $func_name($result))
			$arr[] = $row;
		return $arr;
	}

	public static function fetch_rows($result)
	{
		return self::fetch_all($result, MYSQLI_NUM);
	}

	public static function free_result($result)
	{
		return mysqli_free_result($result);
	}












	public static function get($query, $db_values = array(), $connection = null, $job = '')
	{
		$request = self::query($query, $db_values, $connection);
		if ($request !== true && !is_a($request, 'mysqli_result'))
			return false;
		$results = call_user_func('self::fetch_' . ($job ?: 'assoc'), $request);
		wesql::free_result($request);

		return $job || !is_array($results) || count($results) > 1 ? $results : reset($results);
	}

	public static function get_assoc($query, $db_values = array(), $connection = null)
	{
		return self::get($query, $db_values, $connection, 'assoc');
	}

	public static function get_row($query, $db_values = array(), $connection = null)
	{
		return self::get($query, $db_values, $connection, 'row');
	}

	public static function get_all($query, $db_values = array(), $connection = null)
	{
		return self::get($query, $db_values, $connection, 'all');
	}

	public static function get_rows($query, $db_values = array(), $connection = null)
	{
		return self::get($query, $db_values, $connection, 'rows');
	}

	public static function data_seek($result, $row_num)
	{
		return mysqli_data_seek($result, $row_num);
	}

	public static function num_fields($result)
	{
		return mysqli_num_fields($result);
	}

	public static function num_rows($result)
	{
		return mysqli_num_rows($result);
	}

	public static function escape_string_replacement($str, $connection = null)
	{
		return mysqli_real_escape_string($connection === null ? self::$link : $connection, $str);
	}

	public static function escape_string($string)
	{
		return addslashes($string);
	}

	public static function unescape_string($string)
	{
		return stripslashes($string);
	}

	public static function server_info($connection = null)
	{
		return mysqli_get_server_info($connection === null ? self::$link : $connection);
	}

	public static function select_db($db_name, $connection = null)
	{
		return mysqli_select_db($connection === null ? self::$link : $connection, $db_name);
	}
}
