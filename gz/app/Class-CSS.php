<?php














class wess
{





	protected static function rgb2hex($r, $g, $b)
	{
		$hex = sprintf('%02x%02x%02x', $r, $g, $b);
		if (preg_match('~^([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3?$~i', $hex, $m))
			return '#' . $m[1] . $m[2] . $m[3];
		return '#' . $hex;
	}


	protected static function color2string($r, $g, $b, $a)
	{
		$a = max(0, min(1, $a));
		$r = max(0, min(255, round($r)));
		$g = max(0, min(255, round($g)));
		$b = max(0, min(255, round($b)));

		return $a === 1 ? wess::rgb2hex($r, $g, $b) : "rgba($r, $g, $b, $a)";
	}


	protected static function hue2rgb($m1, $m2, $h)
	{
		$h < 0 ? $h++ : ($h > 1 ? $h-- : '');

		if ($h * 6 < 1)
			return $m2 + ($m1 - $m2) * $h * 6;
		if ($h * 2 < 1)
			return $m1;
		if ($h * 3 < 2)
			return $m2 + ($m1 - $m2) * (2 / 3 - $h) * 6;
		return $m2;
	}






	protected static function hsl2rgb($h, $s, $l, $a)
	{
		while ($h < 0)
			$h += 360;
		$h = fmod($h, 360) / 360;
		$s = max(0, min(1, $s / 100));
		$l = max(0, min(1, $l / 100));

		$m1 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;
		$m2 = $l * 2 - $m1;

		return [
			'r' => wess::hue2rgb($m1, $m2, $h + 1 / 3) * 255,
			'g' => wess::hue2rgb($m1, $m2, $h) * 255,
			'b' => wess::hue2rgb($m1, $m2, $h - 1 / 3) * 255,
			'a' => $a
		];
	}





	protected static function rgb2hsl($r, $g, $b, $a)
	{
		$r /= 255;
		$g /= 255;
		$b /= 255;
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$c = $max - $min;
		$l = ($max + $min) / 2;

		if ($max === $min)
			return ['h' => 0, 's' => 0, 'l' => $l * 100, 'a' => $a];

		if ($max === $r)
		{
			$h = ($g - $b) / $c;
			while ($h < 0)
				$h += 6;
			$h = fmod($h, 6);
		}
		elseif ($max === $g)
			$h = (($b - $r) / $c) + 2;
		else
			$h = (($r - $g) / $c) + 4;

		return [
			'h' => $h * 60,
			's' => $c / ($l <= 0.5 ? $l + $l : 2 - $l - $l) * 100,
			'l' => $l * 100,
			'a' => $a
		];
	}


	protected static function string2color($data)
	{

		static $colors = [
			'aqua'		=> '00ffff', 'black'	=> '000000', 'blue'		=> '0000ff',
			'fuchsia'	=> 'ff00ff', 'gray'		=> '808080', 'green'	=> '008000',
			'grey'		=> '808080', 'lime'		=> '00ff00', 'maroon'	=> '800000',
			'navy'		=> '000080', 'olive'	=> '808000', 'purple'	=> '800080',
			'red'		=> 'ff0000', 'silver'	=> 'c0c0c0', 'teal'		=> '008080',
			'white'		=> 'ffffff', 'yellow'	=> 'ffff00'
		];

		if (!function_exists('to_max'))
		{
			function to_max($d, $max = 255)
			{
				return substr($d, -1) === '%' ? (int) substr($d, 0, -1) / 100 * $max : $d;
			}
		}


		preg_match('~(?:(rgb|hsl)a?\(\h*(\d+%?)\h*,\h*(\d+%?)\h*,\h*(\d+%?)(?:\h*\,\h*(\d*(?:\.\d+)?%?))?\h*\)|#([0-9a-f]{6}|[0-9a-f]{3}))~', $data, $rgb);

		$color = $hsl = 0;
		if (empty($rgb[0]))
		{
			$data = explode(',', $data);
			$rgb[0] = $data[0];
			$data = trim($data[0]);
			if (!isset($colors[$data]))
				return false;
			$color = [hexdec(substr($colors[$data], 0, 2)), hexdec(substr($colors[$data], 2, 2)), hexdec(substr($colors[$data], -2)), 1];
		}
		elseif ($rgb[2] !== '' && $rgb[1] === 'rgb')
			$color = [to_max($rgb[2]), to_max($rgb[3]), to_max($rgb[4]), !isset($rgb[5]) || $rgb[5] === '' ? 1 : to_max((float) $rgb[5], 1)];
		elseif ($rgb[2] !== '')
			$hsl = ['h' => to_max($rgb[2], 360), 's' => to_max($rgb[3], 100), 'l' => to_max($rgb[4], 100), 'a' => $rgb[5] === '' ? 1 : to_max((float) $rgb[5], 1)];
		elseif ($rgb[6] !== '' && isset($rgb[6][3]))
			$color = [hexdec(substr($rgb[6], 0, 2)), hexdec(substr($rgb[6], 2, 2)), hexdec(substr($rgb[6], -2)), 1];
		elseif ($rgb[6] !== '')
			$color = [hexdec($rgb[6][0] . $rgb[6][0]), hexdec($rgb[6][1] . $rgb[6][1]), hexdec($rgb[6][2] . $rgb[6][2]), 1];
		else
			$color = [255, 255, 255, 1];

		return [$rgb[0], $color, $hsl];
	}

	function process(&$css) {}
}

class wess_mixin extends wess
{
	var $default = [];
	var $target;

	private function def($a)
	{
		return $this->default[$this->target][(int) $a[1]];
	}

	function process(&$css)
	{
		$mix = [];


		if (preg_match_all('~@mixin\h+(?:{([^}]+)}\h*)?([\w.-]+)(?:\(([^()]+)\))?[^\v]*\v+(\h+)([^\v]*\n+)((?:\4\h*[^\v]*\v+)*)~i', $css, $mixins, PREG_SET_ORDER))
		{

			foreach ($mixins as $mixin)
			{

				$css = str_replace($mixin[0], '', $css);

				if (!empty($mixin[1]) && !we::is(we::$user['extra_tests'][] = strtolower($mixin[1])))
					continue;


				$mix[$mixin[2]] = rtrim(str_replace("\n" . $mixin[4], "\n", $mixin[5] . $mixin[6]));


				if (!empty($mixin[3]) && preg_match_all('~(\$[\w-]+)\h*(?:[:=]\h*"?([^",]+))?~', $mixin[3], $variables, PREG_SET_ORDER))
				{
					foreach ($variables as $i => $var)
					{
						$mix[$mixin[2]] = str_replace($var[1], '$%' . $i . '%', $mix[$mixin[2]]);
						$def[$mixin[2]][$i] = isset($var[2]) ? trim($var[2], '" ') : '';
					}
				}
			}
		}



		for ($loop = 0; $loop < 10; $loop++)
		{
			$repa = [];
			$selector_regex = '([abipqsu]|[!+>&#*@:.a-z0-9][^{};,\n"()]+)';


			if (preg_match_all('~(?<=\n)(\h*)mixin\h*:\h*' . $selector_regex . '\h*(?:\(([^\n]+)\))?~i', $css, $targets, PREG_SET_ORDER))
			{
				foreach ($targets as $mixin)
				{
					$rep = '';
					$tg = $mixin[2];
					if (isset($mix[$tg]))
					{
						$rep = $mix[$tg];
						if (!empty($mixin[3]))
						{
							$variables = explode(',', $mixin[3]);
							$i = 0;
							foreach ($variables as $i => $var)
								if (!empty($var))
									$rep = str_replace('$%' . $i . '%', trim($var, '" '), $rep);
						}


						if (!empty($def[$tg]))
						{
							$this->default = $def;
							$this->target = $tg;
							$rep = preg_replace_callback('~\$%(\d+)%~', [$this, 'def'], $rep);
						}
					}

					elseif (preg_match_all('~(?<=\n)' . preg_quote($tg, '~') . '\h*(?:[a-zA-Z]+\h*)?\v+(\h+)([^\v]*\v+)((?:\1[^\v]*\v+)*)~', $css, $selectors, PREG_SET_ORDER))
						foreach ($selectors as $sel)
							$rep .= "\n" . rtrim(str_replace("\n" . $sel[1], "\n", $sel[2] . $sel[3]));

					$repa[$mixin[0]] = $mixin[1] . str_replace("\n", "\n" . $mixin[1], $rep);
				}
			}


			if (preg_match_all('~(?<=\n)(\h*)(.*?)\h+mixes\h*' . $selector_regex . '(?:\(([^\n]+)\))?~i', $css, $targets, PREG_SET_ORDER))
			{
				foreach ($targets as $mixin)
				{
					$rep = '';
					$tg = trim($mixin[3]);
					if (isset($mix[$tg]))
					{
						$rep = $mix[$tg];
						if (!empty($mixin[4]))
						{
							$variables = explode(',', $mixin[4]);
							$i = 0;
							foreach ($variables as $i => $var)
								if (!empty($var))
									$rep = str_replace('$%' . $i . '%', trim($var, '" '), $rep);
						}


						if (!empty($def[$tg]))
						{
							$this->default = $def;
							$this->target = $tg;
							$rep = preg_replace_callback('~\$%(\d+)%~', [$this, 'def'], $rep);
						}
					}

					elseif (preg_match_all('~(?<=\n)' . preg_quote($tg, '~') . '\h*(?:[a-zA-Z]+\h*)?\v+(\h+)([^\v]*\v+)((?:\1[^\v]*\v+)*)~', $css, $selectors, PREG_SET_ORDER))
						foreach ($selectors as $sel)
							$rep .= "\n" . rtrim(str_replace("\n" . $sel[1], "\n", $sel[2] . $sel[3]));

					$newline = "\n" . $mixin[1] . (isset($mixin[1][0]) ? $mixin[1][0] : "\t");
					$repa[$mixin[0]] = $mixin[1] . $mixin[2] . $newline . str_replace("\n", $newline, $rep);
				}
			}

			if (!empty($repa))
			{

				$keys = array_map('strlen', array_keys($repa));
				array_multisort($keys, SORT_DESC, $repa);

				$css = str_replace(array_keys($repa), array_values($repa), $css);
			}
			else
				break;
		}
	}
}







class wess_dynamic extends wess
{
	function process(&$css)
	{
		static $done = [];

		if (preg_match_all('~@dynamic\h+([a-z0-9_]+)(?:\h+\([^)]*\))?~i', $css, $functions, PREG_SET_ORDER))
		{
			foreach ($functions as $func)
			{
				$callback = 'dynamic_' . $func[1];
				if (is_callable($callback))
				{
					if (isset($done[$func[0]]))
						continue;
					$data = isset($func[2]) ? call_user_func_array($callback, array_map('trim', explode(',', $func[2]))) : $callback();
					$css = preg_replace('~' . preg_quote($func[0], '~') . '~i', $data, $css);
					$done[$func[0]] = true;
				}
			}
		}
	}
}


class wess_var extends wess
{

	private static function lensort($a, $b)
	{
		return strlen(trim($a)) < strlen(trim($b));
	}

	private static function develop_var($k, $limit = 0)
	{
		global $css_vars;

		if (strpos($css_vars[$k], '$') !== false)
			foreach ($css_vars as $key => $val)
				$css_vars[$k] = str_replace($key, $val, $css_vars[$k]);

		if (strpos($css_vars[$k], '$') !== false && $limit < 8)
			wess_var::develop_var($k, ++$limit);
	}

	function process(&$css)
	{
		global $css_vars, $alphamix;


		$css_vars = isset($css_vars) ? $css_vars : [];

















		if (preg_match_all('~^\h*(\$[\w-]+)\h*(?:{([^}]+)}\h*)?=\h*("?)(.*)\\3;?\s*$~m', $css, $matches))
		{

			$decs = $matches[0];
			usort($decs, 'wess_var::lensort');


			$css = str_replace($decs, '', $css);
			unset($decs);


			foreach ($matches[0] as $i => $dec)
				if (empty($matches[2][$i]) || we::is(we::$user['extra_tests'][] = strtolower($matches[2][$i])))
					$css_vars[$matches[1][$i]] = preg_replace('~' . preg_quote($matches[1][$i], '~') . '\b~', isset($css_vars[$matches[1][$i]]) ? $css_vars[$matches[1][$i]] : '', rtrim($matches[4][$i], '; '));


			$keys = array_map('strlen', array_keys($css_vars));
			array_multisort($keys, SORT_DESC, $css_vars);

			foreach ($css_vars as $key => $val)
				wess_var::develop_var($key);


			if (isset($css_vars['$alphamix']))
				$alphamix = trim($css_vars['$alphamix'], '"');
		}


		while (preg_match_all('~@ifnull\h*\((\$[\w-]+)\s*,\s*+(?!@ifnull)\s*([^)]+)\s*\)~i', $css, $matches))
			foreach ($matches[1] as $i => $var)
				$css = str_replace($matches[0][$i], isset($css_vars[$var]) ? $var : $matches[2][$i], $css);


		if (!empty($css_vars))
			$css = str_replace(array_keys($css_vars), array_values($css_vars), $css);
	}
}







class wess_if extends wess
{
	var $test_vars;

	public function __construct($test_vars = false)
	{
		$this->test_vars = $test_vars;
	}

	function process(&$css)
	{




		$skipped = 0;
		$strex = '\s*+("(?:[^"@]|@(?!is\h*\())*"|\'(?:[^\'@]|@(?!is\h*\())*\'|(?:[^\'",@]|@(?!is\h*\())(?:[^,@]|@(?!is\h*\())*)\s*+';
		while (preg_match_all('~@is\h*\(' . $strex . '(?:,' . $strex . '(?:,' . str_replace(',', ')', $strex) . ')?)?\)~i', $css, $matches) > $skipped)
		{
			foreach ($matches[1] as $i => $match)
			{

				if ($match[0] == '\'' || $match[0] == '"')
					$match = substr($match, 1, -1);


				if (!$this->test_vars && strpos($match, '$') !== false)
				{
					$css = preg_replace('~@is\h+\(\h+' . preg_quote($match, '~') . '~', '@is (' . preg_replace('~(?<!")(\$\w+)\b~', '"$1"', $match), $css);
					$skipped++;
					continue;
				}


				if (empty($matches[2][$i]) && empty($matches[3][$i]))
				{
					$matches[2][$i] = 'true';
					$matches[3][$i] = 'false';
				}

				if (we::is(we::$user['extra_tests'][] = $match))
				{
					if ($matches[2][$i][0] == '\'' || $matches[2][$i][0] == '"')
						$matches[2][$i] = substr($matches[2][$i], 1, -1);
					$css = str_replace($matches[0][$i], $matches[2][$i], $css);
				}
				else
				{
					if (!isset($matches[3][$i]))
						$matches[3][$i] = '';
					if ($matches[3][$i] !== '' && ($matches[3][$i][0] == '\'' || $matches[3][$i][0] == '"'))
						$matches[3][$i] = substr($matches[3][$i], 1, -1);
					$css = str_replace($matches[0][$i], $matches[3][$i], $css);
				}
			}
		}









		$skipped = 0;
		while (preg_match_all('~(?<=\n)(\h*)@if\h+([^\n]*)(\n(?>[^@]|@(?!if\h))*?)\n\1@endif~i', $css, $matches, PREG_SET_ORDER) > $skipped)
		{
			foreach ($matches as $m)
			{
				$match = $m[2];
				$parts = explode($m[1] . '@else', $m[3]);
				if (!isset($parts[1]))
					$parts[1] = '';
				$remove_tabs = preg_match('~\h+~', $m[3], $tabs) ? strlen($tabs[0]) - strlen($m[1]) : 0;
				foreach ($parts as &$part)
					$part = preg_replace('~\n\h{' . $remove_tabs . '}~', "\n", $part);

				$i = -1;
				$num = count($parts);
				while (++$i < $num)
				{
					if (strtolower(substr($parts[$i], 0, 2)) == 'if' || strtolower(substr($parts[$i], 0, 3)) == ' if')
					{
						$match = preg_match('~^if\h*([^\n]*)~', $parts[$i], $newif) ? trim($newif[1]) : 'true';
						$parts[$i] = substr($parts[$i], strlen($newif[0]));
					}


					if (!$this->test_vars && strpos($match, '$') !== false)
					{
						$css = preg_replace('~@if\h+' . preg_quote($match, '~') . '~', '@if ' . preg_replace('~(?<!")(\$\w+)\b~', '"$1"', $match), $css);
						$skipped++;
						continue 2;
					}


					if ($match !== '' && we::is(we::$user['extra_tests'][] = $match))
						break;

					$match = 'true';
				}
				$css = str_replace($m[0], $i < $num ? $parts[$i] : '', $css);
			}
		}
	}
}




class wess_color extends wess
{
	var $arg, $parg;




	protected static function gradient_background($input)
	{
		$bg1 = $input[2];
		$bg2 = empty($input[3]) ? $bg1 : $input[3];
		$dir = empty($input[4]) ? '180deg' : $input[4];


		if (we::is('ie8down') || (we::is('ie9') && $bg1 != $bg2))
		{
			if (preg_match('~^#[0-9a-f]{3}$~i', $bg1))
				$bg1 = '#' . $bg1[1] . $bg1[1] . $bg1[2] . $bg1[2] . $bg1[3] . $bg1[3];
			if (preg_match('~^#[0-9a-f]{3}$~i', $bg2))
				$bg2 = '#' . $bg2[1] . $bg2[1] . $bg2[2] . $bg2[2] . $bg2[3] . $bg2[3];
			return $input[1] . 'background: none' . $input[1] . (we::is('ie6,ie7') ? 'zoom: 1' . $input[1] : '') .
				'filter:progid:DXImageTransform.Microsoft.Gradient(startColorStr=' . $bg1 . ',endColorStr=' . $bg2 . ($dir == 'left' ? ',GradientType=1' : '') . ')';
		}


		if (we::is('ie') && we::$browser['version'] < 10)
			return $input[1] . 'background-color: ' . $bg1;

		return $input[1] . 'background: ' . sprintf(we::is('safari') && we::$browser['version'] < 5.1 ?
			'-webkit-gradient(linear, 0%% 0%%, ' . ($dir == 'left' ? '100%% 0%%' : '0%% 100%%') . ', from(%1$s), to(%2$s))' :
			'linear-gradient(' . ($dir == '180deg' ? '' : $dir . ', ') . '%1$s, %2$s)',
			$bg1,
			$bg2
		);
	}

	private function op($origin, $index = 0, $reverse = false)
	{
		$arg = $this->arg[$index];
		$parg = isset($this->parg[$index]) ? $this->parg[$index] : null;

		if ((isset($parg) && ($parg[0] === '+' || $parg[0] === '-')) || ($arg && ($arg[0] === '+' || $arg[0] === '-')))
			return $origin + (isset($parg) ? $origin * $parg : $arg) * ($reverse ? -1 : 1);
		return isset($parg) ? $parg : $arg;
	}


	function process(&$css)
	{
		$nodupes = [];


		while (preg_match_all('~(strength|luma|saturation|hue|complement|average|alpha|channels)\(((?:(?:rgb|hsl)a?\([^()]+\)|[^()])+)\)~i', $css, $matches))
		{
			foreach ($matches[0] as $i => $dec)
			{
				if (isset($nodupes[$dec]))
					continue;
				$nodupes[$dec] = true;
				$code = strtolower($matches[1][$i]);
				$m = strtolower(trim($matches[2][$i]));
				if (empty($m))
					continue;

				$rgb = wess::string2color($m);
				if ($rgb === false)
				{

					if ($code === 'alpha' && strpos($m, 'opacity') !== false)
						$css = str_replace($dec, 'alpha_ms_wedge' . substr($dec, 5), $css);

					else
						$css = str_replace($dec, 'red', $css);
					continue;
				}

				$nc = 0;
				$color = $rgb[1];
				$hsl = $rgb[2];
				$arg = array_map('trim', explode(',', substr($m, strlen($rgb[0]))));
				$parg = [];
				while ($arg && $arg[0] === '')
					array_shift($arg);

				$arg[0] = isset($arg[0]) ? $arg[0] : 5;
				if ($code === 'channels' && !isset($arg[0], $arg[1], $arg[2], $arg[3]))
					for ($i = 1; $i < 4; $i++)
						$arg[$i] = isset($arg[$i]) ? $arg[$i] : '+0';
				foreach ($arg as $i => $a)
					$parg[$i] = substr($a, -1) === '%' ? ((float) substr($a, 0, -1)) / 100 : null;
				$hsl = $hsl ? $hsl : wess::rgb2hsl($color[0], $color[1], $color[2], $color[3]);












				$this->arg = $arg;
				$this->parg = $parg;

				if ($code === 'average' && !empty($rgb[0]))
				{


					$rgb2 = wess::string2color(ltrim(substr($m, strlen($rgb[0])), ', '));
					$color2 = $rgb2[1];
					$hsl2 = $rgb2[2] ? $rgb2[2] : wess::rgb2hsl($rgb2[1][0], $rgb2[1][1], $rgb2[1][2], $rgb2[1][3]);
					$hsl['h'] = ($hsl['h'] + $hsl2['h']) / 2;
					$hsl['s'] = ($hsl['s'] + $hsl2['s']) / 2;
					$hsl['l'] = ($hsl['l'] + $hsl2['l']) / 2;
					$hsl['a'] = ($hsl['a'] + $hsl2['a']) / 2;
				}


				elseif ($code === 'alpha')
					$hsl['a'] = $this->op($hsl['a']);


				elseif ($code === 'luma' || $code === 'strength')
					$hsl['l'] = $this->op($hsl['l'], 0, $code === 'strength' && $hsl['l'] < 50);


				elseif ($code === 'saturation')
					$hsl['s'] = $this->op($hsl['s']);


				elseif ($code === 'hue')
					$hsl['h'] += $parg[0] ? $parg[0] * 360 : $arg[0];


				elseif ($code === 'complement')
					$hsl['h'] += 180;


				elseif ($code === 'channels')
				{
					if ($color === 0)
						$color = wess::hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
					$nc = [
						'r' => $this->op($color[0], 0),
						'g' => $this->op($color[1], 1),
						'b' => $this->op($color[2], 2),
						'a' => $this->op($color[3], 3)
					];
				}

				else
				{


					$hook = call_hook('css_color', [&$nc, &$hsl, &$color, &$arg, &$parg, &$dec]);



					if (empty($hook))
						continue;
				}

				$nc = $nc ? $nc : wess::hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
				$css = str_replace($dec, wess::color2string($nc['r'], $nc['g'], $nc['b'], $nc['a']), $css);
			}
		}

		$colval = '((?:rgb|hsl)a?\([^()]+\)|[^()\n,]+)';
		$css = preg_replace_callback('~(\n\h*)gradient\h*:\h*' . $colval . '(?:\s*,\s*' . $colval . ')?(?:\s*,\s*(-?\w+(?: \w+)?)?)?~i', 'wess_color::gradient_background', $css);
		$css = str_replace('alpha_ms_wedge', 'alpha', $css);
	}
}


class wess_func extends wess
{
	function process(&$css)
	{
		if (!preg_match_all('~\b(width|height)\(([^)]+)\)~i', $css, $matches))
			return;

		$done = [];
		foreach ($matches[2] as $i => $file)
		{
			if (isset($done[$file]))
				continue;
			$done[$file] = true;


			list ($width, $height) = @getimagesize(str_replace(ROOT, ROOT_DIR, $file));
			$css = str_replace(['width(' . $file . ')', 'height(' . $file . ')'], [$width, $height], $css);
		}
	}
}

class wess_nesting extends wess
{
	var $rules, $props;


	private static function lensort($a, $b)
	{
		return strlen($b[0]) - strlen($a[0]);
	}

	function process(&$css)
	{





		$tree = str_replace('"', '#wedge-quote#', trim($css));


		$regular_css = [];
		if (strpos($css, '{') !== false && strpos($css, '}') !== false)
			$tree = preg_replace_callback(
				'~(?<=^|[\s};])([!+>&#*@:.a-z0-9](?:[^{\n]|(?=,)\n)*?)\s*({(?>[^{}]+|(?1))*})~i',
				function ($m) use (&$regular_css) {
					$regular_css[] = $m[0];
					return '.wedge_css_placeholder';
				},
				$tree
			);




		$tree = preg_replace('~\v\h+(?=\v)~', '', $tree);
		$atree = preg_split('~\v+~', $tree);
		$tree = $ex_string = '';
		$levels = [0];

		foreach (array_map('ltrim', $atree) as $n => $line)
		{
			$l = strlen($atree[$n]) - strlen($line);


			$level = end($levels);



			if ($level >= $l && strhas($ex_string, [' extends ', ' unextends ']))
				$tree .= " {\n}\n";


			elseif ($level == $l && $ex_string && substr($ex_string, -1) !== ',')
				$tree .= strpos($ex_string, 'placeholder') !== false ? "\n" : ";\n";


			elseif ($level < $l && $ex_string)
			{
				$tree .= " {\n";
				$levels[] = $l;
			}

			while ($level > $l)
			{
				array_pop($levels);
				$level = end($levels);
				$tree .= "\n" . str_repeat("\t", $level) . "}\n";
			}

			$tree .= str_repeat("\t", $l) . $line;
			$ex_string = $line;
		}
		unset($atree);


		if (strhas($ex_string, [' extends ', ' unextends ']))
			$tree .= " {\n}\n";
		for ($i = count($levels); $i > 1; $i--)
			$tree .= '}';


		if (!empty($regular_css))
			$tree = preg_replace_callback(
				'~\.wedge_css_placeholder~',
				function ($m) use ($regular_css) {
					static $i = 0;
					return $regular_css[$i++];
				},
				$tree
			);










		preg_match_all('~\n\h*@replace\h*{\n\h*([^\n]+);\n\h*([^\n]*)\n?}~i', $tree, $replacements, PREG_SET_ORDER);
		if (!empty($replacements))
			foreach ($replacements as $replace)
				$tree = str_replace($replace[1], $replace[2], $tree);
		$tree = preg_replace('~\n\h*@replace\h*{[^}]*}~i', "\n", $tree);









		$tree = preg_replace('~^(@(?:import|charset)\h+[^{}\n]*);?$~mi', '<rule selector="$1"></rule>', $tree);
		$tree = preg_replace('~^([!+>&#*@:.a-z0-9][^{};]*?\h*reset);~mi', '<rule selector="$1"></rule>', $tree);
		$tree = preg_replace_callback('~\burl\([^)]+\)~', function ($a) { return str_replace(':', '#wedge-colon#', $a[0]); }, $tree);
		$tree = preg_replace('~([a-z, -]+)\h*:(?!//)\h*([^;}{\n]+?);*\h*(?=[\n}])~i', '<property name="$1" value="$2">', $tree);
		$tree = preg_replace('~<property name="[^"]+" value="\h+">~', '', $tree);
		$tree = preg_replace('~(?<=^|[\s};])([!+>&#*@:.a-z0-9](?:[^{\n]|(?=,)\n)*?)\s*{~i', '<rule selector="$1">', $tree);
		$tree = preg_replace('~ {2,}~', ' ', $tree);
		$tree = str_replace(['}', "\n"], ['</rule>', "\n\t"], $tree);


		$this->pierce($tree);





		$css = $standard_nest = '';
		$bases = $virtuals = $used_virtuals = $removals = $selector_removals = $unextends = [];
		$selector_regex = '((?<![a-z])[abipqsu]|[!+>&#*@:.a-z0-9][^{};,\n"]+)';



		foreach ($this->rules as $n => &$node)
		{
			if (!isset($node['selector']))
				exit('Malformed CSS file!');
			if (strpos($node['selector'], ' reset') !== false)
			{
				preg_match_all('~' . $selector_regex . '\h+reset\b~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
				{



					$selector = $m[1];
					$current_node = $node;
					while ($current_node['parent'] > 0)
					{
						$current_node = $this->rules[$current_node['parent']];
						$selector = $current_node['selector'] . ' ' . $selector;
					}
					$quoted = preg_quote($selector, '~');
					$full_test = '~(?:^|\s|,)' . $quoted . '(?:,|\s|$)~m';


					foreach ($this->rules as $n2 => &$node2)
					{
						if ($n === $n2)
							break;

						if (strpos($node2['selector'], $selector) !== false && preg_match($full_test, $node2['selector']))
						{
							$node2['selector'] = trim(preg_replace('~(?:^|,)[^,]*' . $quoted . '[^,]*(?:,|$)~m', ',', $node2['selector']), "\x00..\x20,");
							$node2['selector'] = str_replace(',,', ',', $node2['selector']);
							if (empty($node2['selector']))
								$this->unset_recursive($n2);
						}
					}
				}
				$node['selector'] = preg_replace('~\breset\b~i', '', $node['selector']);
				if (trim($node['selector'] == ''))
				{
					unset($this->rules[$n]);
					continue;
				}
			}
		}



		foreach ($this->rules as $n => &$node)
		{
			if (strpos($node['selector'], ' virtual') !== false)
			{
				if (preg_match('~' . $selector_regex . '\h+virtual\b~i', $node['selector'], $matches))
				{
					$node['selector'] = str_replace($matches[0], $matches[1], $node['selector']);
					$virtuals[$matches[1]] = $n;
				}
			}
		}

		$is_ie6 = we::is('ie6');


		foreach ($this->rules as $n => &$node)
		{










			if (strpos($node['selector'], '@remove') === 0)
			{
				$sels = preg_match('~@remove\h+(?:from\h+)?([^\n]+)~', $node['selector'], $sels) ? array_map('trim', explode(',', trim(str_replace('#wedge-quote#', '"', $sels[1]), "\x00..\x20\""))) : [];
				foreach ($node['props'] as $remove)
				{
					if (empty($sels))
						$removals[$remove['name'] . ':' . $remove['value']] = true;
					else
						foreach ($sels as $selector)
							$selector_removals[$selector][$remove['name'] . ':' . $remove['value']] = true;
				}
				unset($this->rules[$n]);
				continue;
			}



			if (strpos($node['selector'], ' unextends') !== false)
			{
				preg_match_all('~' . $selector_regex . '\h+unextends\b~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
					$unextends[$m[1]] = $n;
				$node['selector'] = preg_replace('~\bunextends\b~i', '', $node['selector']);
				if (trim($node['selector'] == ''))
				{
					unset($this->rules[$n]);
					continue;
				}
			}



			if (strpos($node['selector'], 'extends') !== false)
			{


				if ($is_ie6 && strpos($node['selector'], '>') !== false)
					$node['selector'] = '.ie6';
				$node['selector'] = str_replace('#wedge-quote#', '"', $node['selector']);
				preg_match_all('~' . $selector_regex . '\h+extends\h+("[^\n{"]+"|[^\n,{"]+)~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
				{
					$save_selector = $node['selector'];
					$node['selector'] = $m[1];
					$path = implode(',', $this->parse_ancestors($node));


					if (strpos($m[2], '&') !== false)
						$m[2] = str_replace('&', reset(($this->parse_ancestors($this->rules[$node['parent']]))), $m[2]);


					$targets = array_map('trim', explode(',', trim($m[2], '"')));
					foreach ($targets as $target)
						$bases[] = [
							$target,
							preg_quote($target),
							$path,
							$n,
						];
					$node['selector'] = str_replace($m[0], $m[1], $save_selector);
				}
			}
		}


		foreach ($this->props as &$node)
		{
			if ($node['name'] === 'base')
			{
				$selectors = $this->split_selectors($this->rules[$node['parent']]['selector']);
				foreach ($selectors as &$here)
				{
					$parent = empty($this->rules[$node['parent']]['parent']) ? [] : $this->get_ancestors($this->rules[$this->rules[$node['parent']]['parent']]);
					$path = $this->parse_ancestors(array_merge((array) $here, $parent), true);

					if (strpos($node['value'], '&') !== false)
						$node['value'] = str_replace('&', $path[0], $node['value']);

					$path = implode(',', $path);
					$targets = $this->split_selectors($node['value']);
					foreach ($targets as $target)
						$bases[] = [
							$target,
							preg_quote($target),
							$path,
							$node['id'],
						];
				}
				if (isset($this->rules[$node['parent']]))
					unset($this->rules[$node['parent']]['props'][$node['id']]);
				unset($this->props[$node['id']], $node);
			}
		}

		foreach ($bases as $i => $base)
		{

			if (isset($unextends[$base[2]]) && $base[3] < $unextends[$base[2]])
				unset($bases[$i]);


			elseif (strpos($base[2], ',') !== false)
			{
				$selectors = $this->split_selectors($base[2]);
				$bases[$i][2] = $selectors[0];
				unset($selectors[0]);
				foreach ($selectors as $sel)
					$bases[] = [
						$base[0],
						$base[1],
						$sel,
						$base[3],
					];
			}
		}


		usort($bases, 'wess_nesting::lensort');



		foreach ($bases as $i => $base)
			if (isset($virtuals[$base[0]]) && !isset($virtuals[$base[2]]))
				$used_virtuals[$base[0]] = $virtuals[$base[0]];
		$unused_virtuals = array_diff_key($virtuals, $used_virtuals);
		foreach ($unused_virtuals as $n2)
			$this->unset_recursive($n2);


		$extends = [];
		foreach ($bases as $base)
		{
			if (isset($extends[$base[0]]))
				$extends[$base[0]][2][] = $base[2];
			else
			{
				$extends[$base[0]] = $base;
				$extends[$base[0]][2] = [$extends[$base[0]][0] => $extends[$base[0]][2]];
			}
		}


		$alpha = array_flip(array_merge(range('a', 'z'), range('A', 'Z')));


		$no_virtuals_regex = '';
		foreach ($virtuals as $virtual => $dummy)
			$no_virtuals_regex .= (isset($alpha[$virtual[0]]) ? '(?<![a-z0-9_-])' : '') . preg_quote($virtual, '~') . '|';
		$no_virtuals_regex = '~(' . substr($no_virtuals_regex, 0, -1) . ')(?![a-z0-9_-])~i';


		foreach ($this->rules as &$node)
		{

			if ($node['selector'][0] === '@' && stripos($node['selector'], '@viewport') !== 0)
			{
				if (stripos($node['selector'], '@import') === 0 || stripos($node['selector'], '@charset') === 0)
				{
					$css .= $node['selector'] . ';';
					continue;
				}

				if (preg_match('~^@[a-z]+~i', $node['selector']))
				{

					if (!empty($standard_nest))
						$css .= '}';
					$standard_nest = $node['selector'];
					$css .= $node['selector'] . ' {';
					continue;
				}
			}

			$selectors = $this->parse_ancestors($node);
			foreach ($selectors as $key => $val)
				if (strpos($val, '&gt;') !== false)
					$selectors[$key] = str_replace('&gt;', '>', $val);
			$done = [];
			$changed = true;

			while ($changed)
			{
				$done_temp = [];
				$changed = false;
				foreach ($extends as $name => $base)
				{

					$is_in = false;
					foreach ($selectors as $sel)
					{
						if (strpos($sel, $name) !== false)
						{
							$is_in = true;
							break;
						}
					}
					if ($is_in)
					{
						$beginning = isset($alpha[$name[0]]) ? '(?<![\w-])' : '';

						foreach ($selectors as &$snippet)
						{
							if (!isset($done[$snippet]) && preg_match('~' . $beginning . '(' . $base[1] . ')(?![\w-]|.*\h+final\b)~i', $snippet))
							{

								foreach ($base[2] as $extend)
									$selectors[] = trim(str_replace($name, $extend, $snippet));


								$done_temp[$snippet] = true;
								$changed = true;
							}
						}
					}
				}

				if ($changed)
					$done = array_merge($done, $done_temp);
			}

			$selectors = array_flip(array_flip($selectors));
			if (!empty($virtuals))
				foreach ($selectors as $i => $sel)
					if (preg_match($no_virtuals_regex, $sel))
						unset($selectors[$i]);

			sort($selectors);
			$selector = implode(',', $selectors);

			$specific_removals = [];
			foreach ($selectors as $removable_selector)
				if (isset($selector_removals[$removable_selector]))
					$specific_removals += $selector_removals[$removable_selector];

			if (!empty($standard_nest))
			{
				if (substr_count($selector, $standard_nest))
					$selector = trim(str_replace($standard_nest, '', $selector));
				else
				{
					$css .= '}';
					$standard_nest = '';
				}
			}

			$css .= $selector . ' {';

			foreach ($node['props'] as &$prop)
			{

				if (isset($removals[$prop['name'] . ':' . $prop['value']]) || isset($removals[$prop['name'] . ':*']))
					continue;

				if (isset($specific_removals[$prop['name'] . ':' . $prop['value']]) || isset($specific_removals[$prop['name'] . ':*']))
					continue;

				if (!strpos($prop['name'], ','))
					$css .= $prop['name'] . ': ' . $prop['value'] . ';';

				else
					foreach (explode(',', $prop['name']) as $names)
						$css .= $names . ': ' . $prop['value'] . ';';
			}

			$css .= '}';
		}

		if (!empty($standard_nest))
			$css .= '}';

		$css = str_replace('#wedge-colon#', ':', $css);
	}

	private function unset_recursive($n)
	{
		foreach ($this->rules[$n]['props'] as $n2 => $dummy)
			unset($this->rules[$n2], $this->props[$n2]);
		foreach ($this->rules[$n]['children'] as $n2)
			if (isset($this->rules[$n2]))
				$this->unset_recursive($n2);
		unset($this->rules[$n]);
	}

	private function get_ancestors(&$node)
	{
		if (empty($node['parent']))
			return (array) $node['selector'];

		return array_merge((array) $node['selector'], $this->get_ancestors($this->rules[$node['parent']]));
	}

	private function parse_ancestors($node, $is_ancestors = false)
	{
		$ancestors = $is_ancestors ? $node : $this->get_ancestors($node);
		$growth = [];

		foreach ($ancestors as $selector)
		{
			$these = $this->split_selectors($selector);

			if (empty($growth))
			{
				$growth = $these;
				continue;
			}

			$fresh = [];

			foreach ($these as $tSelector)
			{
				foreach ($growth as $gSelector)
				{
					$amp = strpos($gSelector, '&');
					$fresh[] = ($amp > 0 ? substr($gSelector, 0, $amp) : '') . $tSelector . ($amp !== false ? substr($gSelector, $amp + 1) : ($gSelector[0] === ':' ? '' : ' ') . $gSelector);
				}
			}

			$growth = $fresh;
		}

		sort($growth);
		return $growth;
	}

	private function split_selectors($selector)
	{


		if ($has_commas = strpos($selector, ',') !== false)
			while (preg_match('~\([^(]*,[^(]*\)|\[[^[]*,[^[]*]|"[^"]*,[^"]*"|\'[^\']*,[^\']*\'~', $selector, $match))
				$selector = str_replace($match[0], strtr($match[0], ',', "\x14"), $selector);

		$arr = array_map('trim', explode(',', $selector));
		if ($has_commas)
			foreach ($arr as $key => $val)
				if (strpos($val, "\x14") !== false)
					$arr[$key] = strtr($val, "\x14", ',');

		return $arr;
	}

	private function pierce(&$data)
	{
		preg_match_all('~<(/?)([a-z]+)\h*(?:name="([^"]*)"\h*)?(?:(?:value|selector)="([^"]*)")?[^>]*>~s', $data, $tags, PREG_SET_ORDER);

		$id = 1;
		$parent = [0];
		$level = 0;
		$rules = $props = [];
		$rule = 'rule';
		$property = 'property';


		foreach ($tags as &$tag)
		{
			if (empty($tag[1]))
			{
				if ($tag[2] === $rule)
				{
					$rules[$id] = [
						'selector' => $tag[4],
						'parent' => $parent[$level],
						'children' => [],
						'props' => [],
					];
					if (!empty($parent[$level]))
						$rules[$parent[$level]]['children'][] = $id;
					$parent[++$level] = $id++;
				}
				elseif ($tag[2] === $property)
				{
					$props[$id] = [
						'name' => $tag[3],
						'value' => $tag[4],
						'id' => $id,
						'parent' => $parent[$level],
					];
					$rules[$parent[$level]]['props'][$id] =& $props[$id];
					$id++;
				}
			}


			elseif ($level > 0)
				$level--;
		}

		$this->rules =& $rules;
		$this->props =& $props;
	}
}



class wess_math extends wess
{
	function process(&$css)
	{
		$limit = 0;
		$done = [];
		while (preg_match_all('~\b(?:math\h*(\(((?>[\h\d.+/*%-]+|(?<=\d)([a-z]{2,4}))|(?1))*\))|(abs|boolval|ceil|floatval|floor|fmod|intval|max|min|rand|round)\h*\(([^()]*)\))~', $css, $matches, PREG_SET_ORDER) && $limit++ < 50)
		{
			foreach ($matches as $val)
			{
				if (isset($done[$val[0]]) && (!isset($val[4]) || $val[4] !== 'rand'))
					continue;
				$done[$val[0]] = true;


				if (preg_match('~\d([a-z]{2,4})~', $val[1] ?: $val[5], $unit))
					$val[1] = preg_replace('~(?<=\d)([a-z]{2,4})~', '', $val[1]);

				if (isset($val[4]))
				{
					$params = explode(',', $val[5]);
					$result = call_user_func_array($val[4], array_map('trim', $params, array_fill(0, count($params), '"\' '))) . (isset($unit[1]) ? $unit[1] : '');
					$css = $val[4] === 'rand' ? substr_replace($css, $result, strpos($css, $val[0]), strlen($val[0])) : str_replace($val[0], $result, $css);
					continue;
				}


				$css = str_replace($val[0], eval('return (' . $val[1] . ');') . (isset($unit[1]) ? $unit[1] : ''), $css);
			}
		}
	}
}



class wess_rgba extends wess
{

	private static function rgba2rgb($input)
	{
		global $alphamix;
		static $cache = [];

		if (isset($cache[$input[0]]))
			return $cache[$input[0]];

		$str = wess::string2color($input[2]);
		if (empty($str))
			return $cache[$input[0]] = 'red';
		list ($r, $g, $b, $a) = $str[1] ? $str[1] : wess::hsl2rgb($str[2]['h'], $str[2]['s'], $str[2]['l'], $str[2]['a']);

		if ($a == 1)
			return $cache[$input[0]] = $input[1] . wess::rgb2hex($r, $g, $b);
		if (!empty($input[1]))
			return $cache[$input[0]] = $input[1] . '#' . sprintf('%02x%02x%02x%02x', round($a * 255), $r, $g, $b);


		if (isset($alphamix) && !is_array($alphamix))
		{
			$rgb = wess::string2color($alphamix);
			if (empty($rgb[1]) && !empty($rgb[2]))
				$rgb[1] = hsl2rgb($rgb[2]['h'], $rgb[2]['s'], $rgb[2]['l'], $rgb[2]['a']);
			$alphamix = $rgb[1];
		}
		elseif (!isset($alphamix))
			$alphamix = [255, 255, 255];

		$ma = 1 - $a;
		$r = $a * $r + $ma * $alphamix[0];
		$g = $a * $g + $ma * $alphamix[1];
		$b = $a * $b + $ma * $alphamix[2];

		return $cache[$input[0]] = wess::rgb2hex($r, $g, $b);
	}

	function process(&$css)
	{
		$css = preg_replace_callback('~(colorstr=)' . (we::is('ie8down') ? '?' : '') . '((?:rgb|hsl)a?\([^()]*\))~i', 'wess_rgba::rgba2rgb', $css);
	}
}


class wess_prefixes extends wess
{



	var $prefix = '';

	public function __construct()
	{
		$this->prefix = we::is('opera') ? '-o-' : (we::is('webkit') ? '-webkit-' : (we::is('gecko') ? '-moz-' : (we::is('ie') ? '-ms-' : '')));
	}








	private function fix_rules($matches)
	{

		$unchanged = $matches[0];
		$prefixed = $this->prefix . $unchanged;
		$both = $prefixed . $unchanged;
		$b = we::$browser;
		$os = we::$os;
		$v = $b['version'];
		$ov = $os['version'];
		list ($ie, $ie8down, $ie9, $opera, $firefox, $webkit, $safari, $chrome, $ios, $android) = [
			$b['ie'], $b['ie8down'], $b['ie9'], $b['opera'], $b['firefox'], $b['webkit'],
			$b['safari'] && !$os['ios'], $b['chrome'], $os['ios'], $os['android'] && $b['webkit'] && !$b['chrome']
		];


		if (strpos($matches[1], '-radius') !== false)
		{
			if ($ie8down)
				return '';

			if (($firefox && $v < 4) || ($ios && $ov < 4) || ($safari && $v < 5) || ($android && $ov < 2.2))
				return $prefixed;
			return $unchanged;
		}


		if ($matches[1] === 'border-image')
		{
			if ($ie && $v < 11)
				return '';
			if ($ie || $chrome || ($ios && $ov >= 6) || ($safari && $v >= 6) || ($firefox && $v >= 15))
				return $unchanged;
			return $prefixed;
		}


		if ($matches[1] === 'box-shadow')
		{
			if ($ie8down)
				return '';
			if (($firefox && $v < 4) || ($ios && $ov < 5) || ($safari && $v < 5.1))
				return $prefixed;
			if ($android)
				return $both;
			return $unchanged;
		}


		if ($matches[1] === 'box-sizing')
		{
			if ($ie && $v < 8)
				return '';
			if ($firefox || ($ios && $ov < 5) || ($safari && $v < 5.1) || ($android && $ov < 4))
				return $prefixed;
			return $unchanged;
		}


		if (strpos($matches[1], 'column') === 0)
		{
			if ($ie8down || $ie9 || ($firefox && $v < 3.6) || ($opera && $v < 11.1))
				return '';
			return $opera || ($chrome && $v >= 50) || ($firefox && $v >= 52) || ($ie && $v >= 10) ? $unchanged : $prefixed;
		}


		if (strpos($matches[1], 'break-') === 0)
		{
			if ($ie8down || $ie9 || ($firefox && $v < 3.6) || ($opera && $v < 11.1))
				return '';
			return $opera || ($ie && $v >= 10) ? $unchanged : $this->prefix . 'column-' . $unchanged;
		}


		if ($matches[1] === 'user-select')
		{
			if ($chrome && $v >= 54)
				return $unchanged;
			if ($firefox || $webkit || ($ie && $v >= 10))
				return $prefixed;
			return '';
		}


		if ($matches[1] === 'font-feature-settings')
		{
			if (($chrome && $v >= 48) || ($firefox && $v >= 34) || ($ie && $v >= 10))
				return $unchanged;
			return $ie ? '' : $prefixed;
		}


		if (strpos($matches[1], 'animation') === 0)
		{
			if ($ie8down || $ie9 || ($firefox && $v < 5) || ($opera && $v < 12) || ($safari && $v < 4))
				return '';
			if (($opera && $v < 12.1) || ($firefox && $v < 16) || ($chrome && $v < 43) || ($safari && $v < 9))
				return $prefixed;
			return $unchanged;
		}


		if (strpos($matches[1], 'transform') === 0)
		{
			if ($ie8down || ($firefox && $v < 3.5))
				return '';
			if ($ie9 || ($opera && $v < 12.1) || ($firefox && $v < 16) || ($chrome && $v < 36) || ($safari && $v < 9))
				return $prefixed;
			return $unchanged;
		}


		if (strpos($matches[1], 'backface-visibility') === 0 || strpos($matches[1], 'perspective') === 0)
		{
			if (($ie && $v >= 10) || ($firefox && $v >= 16) || ($chrome && $v >= 36))
				return $unchanged;
			if (($firefox && $v >= 15) || $webkit)
				return $prefixed;
			return '';
		}


		return $both;
	}







	private function fix_values($matches)
	{
		$unchanged = $matches[0];
		$b = we::$browser;
		$os = we::$os;
		$v = $b['version'];
		$ov = $os['version'];



		if (strpos($matches[1], 'gradient(') !== false)
		{
			if (($b['chrome'] && $v >= 27) || ($b['gecko'] && $v >= 16) || ($b['opera'] && $v >= 12.1) || ($b['safari'] && $v >= 7) || ($b['ie'] && $v >= 10))
				return $unchanged;

			$prefixed = preg_replace('~(?<=[\s:])([a-z][a-z-]+-gradient\h*\()~', $this->prefix . '$1', $unchanged);


			if (strpos($prefixed, 'deg') !== false)
				$prefixed = preg_replace_callback('~(gradient\h*\(\s*)(-?(?:\d+|\d*\.\d+))(?=deg\b)~', function ($a) { return $a[1] . (90 - $a[2]); }, $prefixed);

			if (strpos($prefixed, 'radial-gradient') !== false && $b['webkit'])
				$prefixed = preg_replace_callback('~(?<=radial-gradient\()([\sa-z-]+\s+)?at\s([^,]+)(?=,)~', function ($a) { return $a[2] . ($a[1] != '' ? ', ' . $a[1] : ''); }, $prefixed);

			return $prefixed;
		}


		if (strpos($matches[1], 'transition') !== false)
		{
			if ($b['ie8down'] || $b['ie9'] || ($b['firefox'] && $v < 4))
				return '';

			if (($b['opera'] && $v < 12.1) || ($b['firefox'] && $v < 16) || ($b['webkit'] && (!$b['chrome'] || $v < 36)))
				$unchanged = str_replace($matches[2], preg_replace('~\btransform\b~', $this->prefix . 'transform', $matches[2]), $unchanged);
			if (($b['opera'] && $v < 12.1) || ($b['firefox'] && $v < 16) || ($b['chrome'] && $v < 27) || ($b['safari'] && $v < 7) || ($os['ios'] && $ov < 7))
				return $this->prefix . $unchanged;
			return $unchanged;
		}


		if ((($b['safari'] && $v >= 7) || ($os['ios'] && $ov >= 7) || ($b['chrome'] && $v >= 21 && $v < 29)) && strpos($matches[1], 'flex') !== false)
			return str_replace(['inline-flex', 'flex'], [$this->prefix . 'inline-flex', $this->prefix . 'flex'], $unchanged);


		if (strpos($matches[1], 'resolution') !== false)
		{

			$dpi = $matches[4] == 'dpi' ? $matches[3] : $matches[3] * 96;


			if ($b['firefox'] && $v < 16)
				return $matches[2] . '-moz-device-pixel-ratio:' . ($dpi / 96);

			if ($b['firefox'] || ($b['chrome'] && $v >= 29) || ($b['opera'] && $v >= 12.1))
				return $unchanged;

			if ($b['webkit'])
				return $this->prefix . $matches[2] . '-device-pixel-ratio:' . ($dpi / 96);

			return $matches[2] . '-resolution:' . $dpi . 'dpi';
		}


		if ($b['ie'] && $v < 8 && strpos($matches[1], 'rect') !== false)
			return str_replace($matches[2], str_replace(',', ' ', $matches[2]), $matches[1]);


		if (strpos($matches[1], 'calc') !== false)
		{
			if (($b['ie'] && $v >= 9) || ($b['chrome'] && $v >= 27) || ($b['firefox'] && $v >= 16))
				return $matches[1];
			if (($b['chrome'] && $v >= 19) || ($b['firefox'] && $v >= 4) || ($b['safari'] && $v == 6) || ($os['ios'] && $ov >= 6 && $ov < 7))
				return $this->prefix . $matches[1];

			return $matches[1];
		}


		return $unchanged;
	}

	function process(&$css)
	{

		$rules = [

			'border(?:-[a-z-]+)?-radius',
			'box-shadow',
			'box-sizing',
			'border-image',
			'user-select',
			'font-feature-settings',
			'hyphens',
			'column(?:s|-[a-z-]+)',
			'break-[a-z-]+',
			'grid-[a-z]+',
			'animation(?:-[a-z-]+)?',
			'transform(?:-[a-z-]+)?',
			'backface-visibility',
			'perspective(?:-origin)?',

		];
		foreach ($rules as $val)
			$css = preg_replace_callback('~(?<!-)(' . $val . '):[^\n;]+[\n;]~', [$this, 'fix_rules'], $css);


		$values = [

			'background(?:-image)?:([^\n;]*?(?<!-o-)(?:linear|radial)-gradient\([^)]+\)[^\n;]*)',
			'transition(?:-[a-z-]+)?:([^\n;]*)',
			'display:\h*(flex|inline-flex)\b',
			'\b(min|max)-resolution:\h*([\d.]+)(dppx|dpi)',
			'\brect\h*\(([^)]+)\)',
			'\bcalc\h*\(',

		];
		foreach ($values as $val)
			$css = preg_replace_callback('~(?<!-)(' . $val . ')~', [$this, 'fix_values'], $css);



		$b = we::$browser;
		$os = we::$os;
		$v = $b['version'];
		$ov = $os['version'];


		if ($b['ie'] && $v == 6)
			$css = preg_replace('~\bmin-height\b~', 'height', $css);


		if (($b['opera'] && $v < 12.1) || ($b['firefox'] && $v < 16) || $b['webkit'])
			$css = str_replace('@keyframes ', '@' . $this->prefix . 'keyframes ', $css);


		if (($b['opera'] && $v >= 11) || ($b['ie'] && $v >= 10))
			$css = str_replace('@viewport', '@' . $this->prefix . 'viewport', $css);


		if (($b['safari'] && $v >= 7) || ($os['ios'] && $ov >= 7) || ($b['chrome'] && $v >= 21 && $v < 29))
			$css = preg_replace('~\b(order|justify-content|align-(?:content|items|self)|flex(?:-[a-z]+)?)\h*:~', $this->prefix . '$1:', $css);



		if ($b['ie'] && $v == 10)
			$css = preg_replace(
				[
					'~\bdisplay\h*:\h*(flex|inline-flex)\b~',
					'~\bflex\h*:~',
					'~\border\h*:~',
					'~\balign-items\h*:~',
					'~\balign-self\h*:~',
					'~\balign-content\h*:~',
					'~\bjustify-content\h*:~',
					'~\bflex-direction\h*:~',
					'~\bflex-wrap\h*:\h*nowrap\b~',
					'~\bflex-wrap\h*:~',
					'~\bspace-between\b~',
					'~\bflex-(start|end)\b~',
				],
				[
					'display:-ms-$1box',
					'-ms-flex:',
					'-ms-flex-order:',
					'-ms-flex-align:',
					'-ms-flex-item-align:',
					'-ms-flex-line-pack:',
					'-ms-flex-pack:',
					'-ms-flex-direction:',
					'-ms-flex-wrap:none',
					'-ms-flex-wrap:',
					'justify',
					'$1',
				],
				$css
			);



		$css = preg_replace('~(?<![\w-])-prefix-(?=[a-z-])~', $this->prefix, $css);
	}
}

class wess_base64 extends wess
{
	var $folder;

	public function __construct($base_folder)
	{
		$this->folder = $base_folder;
	}

	function process(&$css)
	{
		$images = [];
		if (preg_match_all('~(?<!raw-)url\(([^)]+)\)~i', $css, $matches))
		{
			foreach ($matches[1] as $img)
				if (preg_match('~\.(gif|png|jpe?g|svgz?)$~', $img, $ext))
					$images[$img] = $ext[1] == 'jpg' ? 'jpeg' : $ext[1];

			foreach ($images as $img => $img_ext)
			{
				$imgr = str_replace(ROOT, '', $img);
				$path = strpos($this->folder . $imgr, '../') !== false ? CACHE_DIR . '/css/' . $this->folder . $imgr : ROOT_DIR . $imgr;
				$absolut = realpath($path);


				if (file_exists($absolut) && filesize($absolut) <= ($img_ext == 'svg' ? 15000 : 3072) && ($img_ext != 'svg' || !we::is('ie[-8]')))
				{
					$img_raw = file_get_contents($absolut);
					if ($img_ext == 'svg' && !we::is('chrome'))
						$img_raw = str_replace(['%20', '%3D', '%3A', '%2F', '%22'], [' ', '=', ':', '/', "'"], rawurlencode($img_raw));

					if ($img_ext == 'svg')
						$img_data = 'url("data:image/svg+xml,' . preg_replace('~^.*?(?=\<svg)~s', '', str_replace(['"', "\t", "\n"], ["'", ' ', ' '], $img_raw)) . '")';
					else
						$img_data = 'url(data:image/' . ($img_ext == 'svgz' ? 'svg+xml' : $img_ext) . ';base64,' . base64_encode($img_raw) . ')';
					$css = str_replace('url(' . $img . ')', $img_data, $css);
				}
			}
		}
	}
}
