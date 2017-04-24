<?php








if (!defined('WEDGE'))
	die('Hacking attempt...');





































final class weSkeleton
{
	private $skeleton = array();
	private $layers = array();
	private $opt = array();
	private $obj = array();
	private $hidden = false;
	var $skip = array();
	var $id = '';

	function __construct($id)
	{
		global $context;

		if (!empty($context['skeleton'][$id]))
			$this->build($context['skeleton'][$id]);
		$this->id = $id;



		if ($id === 'main' && empty($context['skeleton'][$id]))
			$this->hide();


		if (!empty($context['skeleton_ops'][$id]))
			foreach ($context['skeleton_ops'][$id] as $op)
				call_user_func_array(array($this, $op[0]), array_slice($op, 1));
	}


	function has($item)
	{
		return isset($this->layers[$item]) || (bool) $this->parent($item);
	}


	function has_block($block)
	{
		return !isset($this->layers[$block]) && $this->parent($block) !== false;
	}


	function has_layer($layer)
	{
		return isset($this->layers[$layer]);
	}




	function render($from = null)
	{
		if ($this->id === 'main' && empty($this->layers['default']))
			fatal_lang_error('default_layer_missing');
		$here = $from ? $this->layers[$from] : reset($this->skeleton);
		$key = $from ? $from : key($this->skeleton);
		$this->render_recursive($here, $key);
		$this->skip = array();
	}






	function get($targets = '')
	{
		$to = $this->find($targets);


		if ($to === false)
			$to = 'default';
		if (!isset($this->obj[$to]))
			$this->obj[$to] = new weSkeletonItem($this, $to);
		return $this->obj[$to];
	}








	function before($target, $contents = '')
	{
		return $this->op($contents, $target, 'before');
	}


	function after($target, $contents = '')
	{
		return $this->op($contents, $target, 'after');
	}


	function insert($target, $contents = '')
	{
		return $this->op($contents, $target, 'generic');
	}






	function skip($target)
	{
		$this->skip[$target] = true;
	}




	function rename($target, $new_name)
	{
		if (empty($target) || empty($new_name) || $target == 'default' || !$this->has($target))
			return false;
		if (isset($this->layers[$target]))
		{
			$result = $this->insert_layer($new_name, $target, 'rename');
			$result &= $this->remove_layer($target);
		}
		else
		{
			$result = $this->before($target, $new_name) !== false;
			$result &= $this->remove($target);
		}
		return $result ? $new_name : false;
	}








	function remove($target)
	{
		$layer = $this->parent($target);

		if ($layer && !is_array($this->layers[$layer][$target]))
			unset($this->layers[$layer][$target]);

		elseif (isset($this->layers[$layer]))
			return $this->remove_layer($target);
		return true;
	}








	function move($item, $target, $where)
	{
		if (!$this->has($item) || !$this->has($target))
			return false;

		if (isset($this->layers[$item]))
		{
			$to_move = $this->layers[$item];
			unset($this->layers[$item]);
		}
		else
		{
			$parent = $this->parent($item);
			if (!$parent)
				return false;
			$to_move = $this->layers[$parent][$item];
			unset($this->layers[$parent][$item]);
		}
		$this->op(array($item => $to_move), $target, $where, true);
	}







	function parent($child)
	{
		foreach ($this->layers as $id => &$layer)
			if (isset($layer[$child]))
				return $id;

		return false;
	}








	function load($target, $contents = '')
	{
		return $this->op($contents, $target, 'load');
	}


	function add($target, $contents = '')
	{
		return $this->op($contents, $target, 'add');
	}


	function first($target, $contents = '')
	{
		return $this->op($contents, $target, 'first');
	}


	function replace($target, $contents = '')
	{
		return $this->op($contents, $target, 'replace');
	}



	function outer($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset($this->layers[$target]))
			return false;
		return $this->insert_layer($new_layer, $target, 'outer');
	}


	function inner($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset($this->layers[$target]))
			return false;
		$this->layers[$target] = array($new_layer => $this->layers[$target]);
		$this->layers[$new_layer] =& $this->layers[$target][$new_layer];
		return $new_layer;
	}






	function hide($layer = '')
	{
		global $context;

		if (empty($this->layers['default']))
			$this->layers['default'] = array('main' => true);


		if (empty($layer))
			$this->skeleton = array(
				'dummy' => array(
					'default' => $this->layers['default']
				)
			);

		elseif ($layer === 'html')
			$this->skeleton = array(
				'html' => array(
					'body' => array(
						'default' => $this->layers['default']
					)
				)
			);

		else
			$this->skeleton = array(
				'dummy' => array(
					$layer => array(
						'default' => $this->layers['default']
					)
				)
			);
		$this->reindex();


		$context['hide_chrome'] = $this->hidden = true;
	}












	function layer($layer, $target = '', $where = 'replace')
	{











		if (empty($target))
			$target = 'default';


		if (!isset($this->layers[$target]))
			return false;

		if ($where === 'before' || $where === 'after')
			$this->insert_layer($layer, $target, $where);
		elseif ($where === 'replace')
		{
			$this->insert_layer($layer, $target, $where);
			$this->remove_layer($target);
		}
		elseif ($where === 'first' || $where === 'add')
		{
			if ($where === 'first')
				$this->layers[$target] = array_merge(array($layer => array()), $this->layers[$target]);
			else
				$this->layers[$target][$layer] = array();
			$this->layers[$layer] =& $this->layers[$target][$layer];
		}
		else
			return false;
		return $layer;
	}










	private function build($str)
	{

		if (!empty($this->obj))
			foreach ($this->obj as &$layer)
				$layer = null;

		preg_match_all('~<(?!!)(/)?([\w:,]+)\s*([^>]*?)(/?)\>~', $str, $arr, PREG_SET_ORDER);
		$this->parse($arr, $this->skeleton);
	}





	private function parse($arr, &$dest, &$pos = 0, $name = '')
	{
		for ($c = count($arr); $pos < $c;)
		{
			$tag =& $arr[$pos++];


			if (!empty($tag[1]))
			{
				$this->layers[$name] =& $dest;
				return;
			}


			if (empty($tag[4]))
			{
				$dest[$tag[2]] = array();
				$this->parse($arr, $dest[$tag[2]], $pos, $tag[2]);
			}

			else
				$dest[$tag[2]] = true;


			if (!empty($tag[3]))
			{
				preg_match_all('~(\w+)="([^"]+)"?~', $tag[3], $opts, PREG_SET_ORDER);
				foreach ($opts as $option)
					$this->opt[$option[1]][$tag[2]] = $option[2];
			}
		}
	}





	private function reindex()
	{

		$transit = unserialize(serialize($this->skeleton));
		$this->layers = array();
		$this->skeleton = $transit;


		$this->reindex_recursive($this->skeleton);
	}

	private function reindex_recursive(&$here)
	{
		foreach ($here as $id => &$item)
		{
			if (is_array($item))
			{
				$this->layers[$id] =& $item;
				$this->reindex_recursive($item);
			}
		}
	}

	private function render_recursive(&$here, $key)
	{
		if (isset($this->opt['indent'][$key]))
			echo '<inden@zi=', $key, '=', $this->opt['indent'][$key], '>';


		execBlock($key . '_before', 'ignore');

		if ($this->id === 'main' && ($key === 'top' || $key === 'default'))
			while_we_re_here();

		foreach ($here as $id => $temp)
		{
			if (isset($this->skip[$id]))
				continue;


			if (is_array($temp))
				$this->render_recursive($temp, $id);
			elseif (isset($this->opt['indent'][$id]))
			{
				echo '<inden@zi=', $id, '=', $this->opt['indent'][$id], '>';
				execBlock($id);
				echo '</inden@zi=', $id, '>';
			}
			else
				execBlock($id);
		}


		execBlock($key . '_after', 'ignore');

		if (isset($this->opt['indent'][$key]))
			echo '</inden@zi=', $key, '>';


		if ($key === 'html' && !AJAX && !$this->hidden)
			db_debug_junk();
	}







	private function find($targets = '', $where = '')
	{


		foreach ((array) $targets as $target)
		{
			if (empty($target))
				$target = 'target';
			if (isset($this->layers[$target]) || $this->has_block($target))
				return $target;
		}


		return false;
	}

	private function list_blocks($items)
	{
		$blocks = array();
		foreach ($items as $key => $val)
		{
			if (is_array($val))
				$blocks[$key] = $this->list_blocks($val);
			else
				$blocks[$val] = true;
		}
		return $blocks;
	}








	private function insert_layer($source, $target = 'default', $where = 'outer')
	{
		$lay = $this->parent($target);
		$lay = $lay ? $lay : 'default';
		if (!isset($this->layers[$lay]))
			return false;
		$dest =& $this->layers[$lay];

		$temp = array();
		foreach ($dest as $key => &$value)
		{
			if ($key === $target)
			{
				if ($where === 'after')
					$temp[$key] = $value;
				$temp[$source] = $where === 'outer' ? array($key => $value) : ($where === 'replace' ? array() : ($where === 'rename' ? $value : array()));
				if ($where === 'before')
					$temp[$key] = $value;
			}
			else
				$temp[$key] = $value;
		}

		$dest = $temp;

		if ($where !== 'after' && $where !== 'before')
			$this->reindex();
		return true;
	}


	private function remove_layer($layer)
	{

		if (!isset($this->layers[$layer]) || $layer === 'default')
			return false;


		$current = 'default';
		$loop = true;
		while ($loop)
		{
			$loop = false;
			foreach ($this->layers as $id => &$curlay)
			{
				if (isset($curlay[$current]))
				{

					if ($id === $layer)
						return false;
					$current = $id;
					$loop = true;
				}
			}
		}


		$this->skeleton = $this->remove_item($layer);
		$this->reindex();
		return true;
	}

	private function remove_item($item, $from = array(), $level = 0)
	{
		if (empty($from))
			$from = $this->skeleton;

		$ret = array();
		foreach ($from as $key => $val)
			if ($key !== $item)
				$ret[$key] = is_array($val) && !empty($val) ? $this->remove_item($item, $val, $level + 1) : $val;

		return $ret;
	}









	private function op($blocks, $target, $where, $force = false)
	{
















		if (empty($blocks))
			list ($target, $blocks) = array('default', $target);

		if (!$force)
			$blocks = $this->list_blocks((array) $blocks);
		$has_layer = (bool) count(array_filter($blocks, 'is_array'));
		$to = $this->find($where === 'generic' ? array_keys($target) : $target, $where);

		if (empty($to))
			return false;


		if ($where === 'generic')
			$where = $target[$to];



		if ($to === 'default' && $this->hidden && is_array($target) && reset($target) !== 'default')
			return false;


		if (($where === 'load' || $where === 'replace') && $to === 'sidebar')
			$where = 'add';

		if ($where === 'load' || $where === 'replace')
		{

			if ($where === 'replace' || !isset($this->layers[$to]) || count($this->layers[$to]) === count($this->layers[$to], COUNT_RECURSIVE))
			{
				$this->layers[$to] = $blocks;

				if ($where === 'replace' || $has_layer)
					$this->reindex();
				return $to;
			}


			$keys = array_keys($this->layers[$to]);
			foreach ($keys as $id)
			{
				if (!is_array($this->layers[$to][$id]))
				{

					if (!isset($offset))
					{
						$offset = array_search($id, $keys, true);
						$this->layers[$to] = array_merge(array_slice($this->layers[$to], 0, $offset, true), $blocks, array_slice($this->layers[$to], $offset, null, true));
					}

					unset($this->layers[$to][$id]);
				}
			}


			if (!isset($offset))
				$this->layers[$to] += $blocks;

			$this->reindex();
			return $to;
		}

		elseif ($where === 'add')
			$this->layers[$to] += $blocks;

		elseif ($where === 'first')
			$this->layers[$to] = array_merge(array_reverse($blocks), $this->layers[$to]);

		elseif ($where === 'before' || $where === 'after')
		{
			foreach ($this->layers as &$layer)
			{
				if (!isset($layer[$to]))
					continue;

				$layer = array_insert($layer, $to, $blocks, $where === 'after');
				$this->reindex();
				return $to;
			}
		}
		else
			return false;

		if ($has_layer)
			$this->reindex();

		return $to;
	}
}

















final class weSkeletonItem
{
	private $target;
	private $skeleton;

	function __construct($that, $to = 'default')
	{
		$this->target = $to;
		$this->skeleton = $that;
	}


	function remove()
	{
		$this->skeleton->remove($this->target);
	}


	function load($items)		{ $this->skeleton->load($this->target, $items); return $this; }
	function replace($items)	{ $this->skeleton->replace($this->target, $items); return $this; }
	function add($items)		{ $this->skeleton->add($this->target, $items); return $this; }
	function first($items)		{ $this->skeleton->first($this->target, $items); return $this; }
	function before($items)		{ $this->skeleton->before($this->target, $items); return $this; }
	function after($items)		{ $this->skeleton->after($this->target, $items); return $this; }
	function insert($items)		{ $this->skeleton->insert($this->target, $items); return $this; }
	function move($layer, $p)	{ $this->skeleton->move($this->target, $layer, $p); return $this; }
	function rename($layer)		{ $this->skeleton->rename($this->target, $layer); return $this; }
	function outer($layer)		{ $this->skeleton->outer($this->target, $layer); return $this; }
	function inner($layer)		{ $this->skeleton->inner($this->target, $layer); return $this; }
	function skip()				{ $this->skeleton->skip($this->target); return $this; }
	function parent()			{ return $this->skeleton->get($this->skeleton->parent($this->target)); }
}













final class wetem
{
	private static $main = null;
	public static $hooks = null;


	private function __clone()
	{
		return false;
	}


	static function createMainSkeleton()
	{

		if (self::$main != null)
			return;

		self::$main = new weSkeleton('main');
		self::$hooks = new weSkeleton('hooks');
		self::$hooks->hide();
	}


	static function add_hook($target, $contents = '')
	{
		if (!self::$hooks->has_layer($target))
			self::$hooks->layer($target, 'default', 'add');
		return self::$hooks->add($target, $contents);
	}

	static function has($item)									{ return self::$main->has($item); }
	static function has_block($block)							{ return self::$main->has_block($block); }
	static function has_layer($layer)							{ return self::$main->has_layer($layer); }
	static function render()									{		 self::$main->render(); }
	static function get($targets = '')							{ return self::$main->get($targets); }
	static function before($target, $contents = '')				{ return self::$main->before($target, $contents); }
	static function after($target, $contents = '')				{ return self::$main->after($target, $contents); }
	static function insert($target, $contents = '')				{ return self::$main->insert($target, $contents); }
	static function skip($target)								{ return self::$main->skip($target); }
	static function remove($target)								{		 self::$main->remove($target); }
	static function move($item, $target, $where)				{ return self::$main->move($item, $target, $where); }
	static function parent($child)								{ return self::$main->parent($child); }
	static function load($target, $contents = '')				{ return self::$main->load($target, $contents); }
	static function add($target, $contents = '')				{ return self::$main->add($target, $contents); }
	static function first($target, $contents = '')				{ return self::$main->first($target, $contents); }
	static function replace($target, $contents = '')			{ return self::$main->replace($target, $contents); }
	static function rename($target, $new_name)					{ return self::$main->rename($target, $new_name); }
	static function outer($target, $new_layer = '')				{ return self::$main->outer($target, $new_layer); }
	static function inner($target, $new_layer = '')				{ return self::$main->inner($target, $new_layer); }
	static function hide($layer = '')							{ return self::$main->hide($layer); }
	static function layer($layer, $target = '', $where = '')	{ return self::$main->layer($layer, $target, $where ? $where : 'replace'); }
}
