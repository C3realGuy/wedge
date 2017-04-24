<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');


function createMenu($menuData, $menuOptions = array())
{
	global $context, $txt;


	$context['menu_image_path'] = isset($context['menu_image_path']) ? $context['menu_image_path'] : ASSETS . '/admin';





























	$context['max_menu_id'] = isset($context['max_menu_id']) ? $context['max_menu_id'] + 1 : 0;


	$context['menu_data_' . $context['max_menu_id']] = array();
	$menu_context =& $context['menu_data_' . $context['max_menu_id']];


	$menu_context['current_action'] = isset($menuOptions['action']) ? $menuOptions['action'] : $context['action'];


	if (isset($menuOptions['current_area']) || isset($_GET['area']))
		$menu_context['current_area'] = isset($menuOptions['current_area']) ? $menuOptions['current_area'] : $_GET['area'];


	$menu_context['extra_parameters'] = '';
	if (!empty($menuOptions['extra_url_parameters']))
		foreach ($menuOptions['extra_url_parameters'] as $key => $value)
			$menu_context['extra_parameters'] .= ';' . $key . '=' . $value;


	if (empty($menuOptions['disable_url_session_check']))
		$menu_context['extra_parameters'] .= ';' . $context['session_query'];

	$include_data = array();


	foreach ($menuData as $section_id => &$section)
	{

		if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($section['permission']) && !allowedTo($section['permission'])))
			continue;


		foreach ($section['areas'] as $area_id => &$area)
		{
			$here =& $menu_context['sections'][$section_id]['areas'][$area_id];
			if (is_numeric($area_id))
				continue;


			if ((!isset($area['enabled']) || $area['enabled'] != false) && (empty($area['permission']) || allowedTo($area['permission'])))
			{

				if (isset($area['label']) || (isset($txt[$area_id]) && !isset($area['select'])))
				{

					if (!isset($menu_context['current_area']))
					{
						$menu_context['current_area'] = $area_id;
						$include_data = $area;
					}


					if (empty($area['hidden']))
					{
						if (!isset($menu_context['sections'][$section_id]['title']))
						{
							$menu_context['sections'][$section_id]['title'] = $section['title'];
							$menu_context['sections'][$section_id]['id'] = $section_id;
							if (isset($section['notice']))
								$menu_context['sections'][$section_id]['notice'] = $section['notice'];
						}

						$here = array('label' => isset($area['label']) ? $area['label'] : $txt[$area_id]);
						if (isset($area['notice']))
							$here['notice'] = $area['notice'];


						if (isset($area['custom_url']))
							$here['url'] = $area['custom_url'];


						$here['icon'] = empty($area['icon']) ? '' : (strpos($area['icon'], '://') !== false ? '<img src="' . $area['icon'] . '">' : '<div class="admenu_icon_' . $area_id . '"></div>') . '&nbsp;&nbsp;';
						$here['bigicon'] = empty($area['bigicon']) ? '' : '<img src="' . (strpos($area['bigicon'], '://') === false ? $context['menu_image_path'] . '/' . $area['bigicon'] : $area['bigicon']) . '">';


						if (!empty($area['subsections']))
						{
							$first_sa = null;
							$here['subsections'] = array();
							foreach ($area['subsections'] as $sa => $sub)
							{
								if (!empty($sub) && (empty($sub[1]) || allowedTo($sub[1])) && (!isset($sub['enabled']) || !empty($sub['enabled'])))
								{
									if ($first_sa === null)
										$first_sa = $sa;

									$here['subsections'][$sa] = array('label' => $sub[0]);

									if (isset($sub['url']))
										$here['subsections'][$sa]['url'] = $sub['url'];


									if ($menu_context['current_area'] == $area_id)
									{

										if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == $sa)
											$menu_context['current_subsection'] = $sa;

										elseif (!isset($menu_context['current_subsection']) && $first_sa == $sa)
											$menu_context['current_subsection'] = $sa;
									}
								}

								else
									$here['subsections'][$sa] = '';
							}
						}
					}
				}


				if ($menu_context['current_area'] == $area_id && empty($found_section))
				{

					$found_section = true;


					$menu_context['current_section'] = $section_id;
					if (isset($area['select']))
						$menu_context['current_area'] = $_GET['area'] = $area['select'];
					else
						$menu_context['current_area'] = $area_id;

					$include_data = $area;
				}

				elseif (empty($found_section) && empty($include_data))
				{
					$menu_context['current_section'] = $section_id;
					$backup_area = isset($area['select']) ? $area['select'] : $area_id;
					$include_data = $area;
				}
			}
			if (empty($here))
				unset($menu_context['sections'][$section_id]['areas'][$area_id]);
		}
	}


	$menu_context['base_url'] = isset($menuOptions['base_url']) ? $menuOptions['base_url'] : '<URL>?action=' . $menu_context['current_action'];


	if (isset($backup_area) && empty($found_section))
		$menu_context['current_area'] = $backup_area;


	if (empty($menu_context['sections']))
	{

		$context['max_menu_id']--;
		if ($context['max_menu_id'] == 0)
			unset($context['max_menu_id']);

		return false;
	}


	foreach ($menu_context['sections'] as &$section)
	{
		$areas =& $section['areas'];

		while (reset($areas) == '' && array_shift($areas) !== null);
		while (end($areas) == '' && array_pop($areas) !== null);

		$ex = false;
		foreach ($areas as $id => &$area)
		{

			if (!empty($area['subsections']))
			{
				while (reset($area['subsections']) === '' && array_shift($area['subsections']) !== null);
				while (end($area['subsections']) === '' && array_pop($area['subsections']) !== null);
				$exs = false;
				foreach ($area['subsections'] as $ids => &$sub)
				{
					if (!empty($exs) && is_numeric($ids))
						unset($areas[$id][$ids]);
					$exs = is_numeric($ids);
				}
			}
			if ($ex && is_numeric($id))
				unset($areas[$id]);
			$ex = is_numeric($id);
		}

		while (reset($areas) == '' && array_shift($areas) !== null);
		while (end($areas) == '' && array_pop($areas) !== null);
	}


	if (!AJAX)
	{
		loadTemplate(isset($menuOptions['template_name']) ? $menuOptions['template_name'] : 'GenericMenu');
		$menu_context['template_name'] = (isset($menuOptions['template_name']) ? $menuOptions['template_name'] : 'generic_menu') . '_dropdown';
		wetem::add(strpos($menu_context['template_name'], 'sidebar') !== false ? 'sidebar' : 'top', $menu_context['template_name']);
		wetem::add('top', 'generic_tabs');
	}


	if (empty($include_data))
		return false;


	$include_data += array(
		'current_action' => $menu_context['current_action'],
		'current_area' => $menu_context['current_area'],
		'current_section' => $menu_context['current_section'],
		'current_subsection' => !empty($menu_context['current_subsection']) ? $menu_context['current_subsection'] : '',
	);

	return $include_data;
}


function destroyMenu($menu_id = 'last')
{
	global $context;

	$menu_name = $menu_id == 'last' && isset($context['max_menu_id'], $context['menu_data_' . $context['max_menu_id']]) ? 'menu_data_' . $context['max_menu_id'] : 'menu_data_' . $menu_id;
	if (!isset($context[$menu_name]))
		return false;

	wetem::remove($context[$menu_name]['template_name']);
	unset($context[$menu_name]);
}
