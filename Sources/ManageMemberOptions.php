<?php
/**
 * Wedge
 *
 * Common administration settings are declared and managed in this file.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is here to make it easier for installed mods to have settings
	and options.  It uses the following functions:

	void ModifySignatureSettings()
		// !!!

	void pauseSignatureApplySettings()
		// !!!

	void ShowCustomProfiles()
		// !!!

	void EditCustomProfiles()
		// !!!
*/

// This function passes control through to the relevant tab.
function ManageMemberOptions()
{
	global $context, $txt, $scripturl, $modSettings, $settings;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// Will need the utility functions from here.
	loadSource('ManageServer');
	loadBlock('show_settings');
	
	$context['page_title'] = $txt['themeadmin_reset_title'];

	$subActions = array(
		'options' => 'ModifyMemberSettings',
		'sig' => 'ModifySignatureSettings',
		'profile' => 'ShowCustomProfiles',
		'profileedit' => 'EditCustomProfiles',
	);

	// By default do the basic settings.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : array_shift(array_keys($subActions));
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['member_options_title'],
		'description' => $txt['member_options_desc'],
		'tabs' => array(
			'options' => array(
				'description' => $txt['member_options_desc'],
			),
			'sig' => array(
				'description' => $txt['signature_settings_desc'],
			),
			'profile' => array(
				'description' => $txt['custom_profile_desc'],
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

function ModifyMemberSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	$config_vars = array(
			// Basic stuff, titles, flash, permissions...
			array('check', 'enable_buddylist'),
			array('check', 'allow_editDisplayName'),
			array('check', 'allow_hideOnline'),
			array('check', 'titlesEnable'),
			array('text', 'default_personal_text'),
	);

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		saveDBSettings($config_vars);

		writeLog();
		redirectexit('action=admin;area=memberoptions;sa=options');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=memberoptions;save;sa=options';
	$context['settings_title'] = $txt['mods_cat_features'];

	prepareDBSettingContext($config_vars);
}

// You'll never guess what this function does...
function ModifySignatureSettings($return_config = false)
{
	global $context, $txt, $modSettings, $sig_start, $helptxt, $scripturl;

	$config_vars = array(
			// Are signatures even enabled?
			array('check', 'signature_enable'),
		'',
			// Tweaking settings!
			array('int', 'signature_max_length'),
			array('int', 'signature_max_lines'),
			array('int', 'signature_max_font_size'),
			array('check', 'signature_allow_smileys', 'onclick' => '$(\'#signature_max_smileys\').attr(\'disabled\', !this.checked);'),
			array('int', 'signature_max_smileys'),
		'',
			// Image settings.
			array('int', 'signature_max_images'),
			array('int', 'signature_max_image_width'),
			array('int', 'signature_max_image_height'),
		'',
			array('bbc', 'signature_bbc'),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template.
	$context['page_title'] = $txt['signature_settings'];
	loadBlock('show_settings');

	// Disable the max smileys option if we don't allow smileys at all!
	add_js('
	$(\'#signature_max_smileys\').attr(\'disabled\', !($(\'#signature_allow_smileys\').attr(\'checked\')));');

	// Load all the signature settings.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);
	$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

	// Applying to ALL signatures?!!
	if (isset($_GET['apply']))
	{
		// Security!
		checkSession('get');

		$sig_start = time();
		// This is horrid - but I suppose some people will want the option to do it.
		$_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;
		$done = false;

		$request = wesql::query('
			SELECT MAX(id_member)
			FROM {db_prefix}members',
			array(
			)
		);
		list ($context['max_member']) = wesql::fetch_row($request);
		wesql::free_result($request);

		while (!$done)
		{
			$changes = array();

			$request = wesql::query('
				SELECT id_member, signature
				FROM {db_prefix}members
				WHERE id_member BETWEEN ' . $_GET['step'] . ' AND ' . $_GET['step'] . ' + 49
					AND id_group != {int:admin_group}
					AND FIND_IN_SET({int:admin_group}, additional_groups) = 0',
				array(
					'admin_group' => 1,
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{
				// Apply all the rules we can realistically do.
				$sig = strtr($row['signature'], array('<br>' => "\n"));

				// Max characters...
				if (!empty($sig_limits[1]))
					$sig = westr::substr($sig, 0, $sig_limits[1]);
				// Max lines...
				if (!empty($sig_limits[2]))
				{
					$count = 0;
					for ($i = 0; $i < strlen($sig); $i++)
					{
						if ($sig[$i] == "\n")
						{
							$count++;
							if ($count >= $sig_limits[2])
								$sig = substr($sig, 0, $i) . strtr(substr($sig, $i), array("\n" => ' '));
						}
					}
				}

				if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d.]+)?(px|pt|em|x-large|larger)~i', $sig, $matches) !== false && isset($matches[2]))
				{
					foreach ($matches[1] as $ind => $size)
					{
						$limit_broke = 0;
						// Attempt to allow all sizes of abuse, so to speak.
						if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
							$limit_broke = $sig_limits[7] . 'px';
						elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
							$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
						elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
							$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
						elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
							$limit_broke = 'large';

						if ($limit_broke)
							$sig = str_replace($matches[0][$ind], '[size=' . $sig_limits[7] . 'px', $sig);
					}
				}

				// Stupid images - this is stupidly, stupidly challenging.
				if ((!empty($sig_limits[3]) || !empty($sig_limits[5]) || !empty($sig_limits[6])))
				{
					$replaces = array();
					$img_count = 0;
					// Get all BBC tags...
					preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $sig, $matches);
					// ... and all HTML ones.
					preg_match_all('~&lt;img\s+src=(?:&quot;)?((?:http://|ftp://|https://|ftps://).+?)(?:&quot;)?(?:\s+alt=(?:&quot;)?(.*?)(?:&quot;)?)?(?:\s?/)?&gt;~i', $sig, $matches2, PREG_PATTERN_ORDER);
					// And stick the HTML in the BBC.
					if (!empty($matches2))
					{
						foreach ($matches2[0] as $ind => $dummy)
						{
							$matches[0][] = $matches2[0][$ind];
							$matches[1][] = '';
							$matches[2][] = '';
							$matches[3][] = '';
							$matches[4][] = '';
							$matches[5][] = '';
							$matches[6][] = '';
							$matches[7][] = $matches2[1][$ind];
						}
					}
					// Try to find all the images!
					if (!empty($matches))
					{
						$image_count_holder = array();
						foreach ($matches[0] as $key => $image)
						{
							$width = -1; $height = -1;
							$img_count++;
							// Too many images?
							if (!empty($sig_limits[3]) && $img_count > $sig_limits[3])
							{
								// If we've already had this before we only want to remove the excess.
								if (isset($image_count_holder[$image]))
								{
									$img_offset = -1;
									$rep_img_count = 0;
									while ($img_offset !== false)
									{
										$img_offset = strpos($sig, $image, $img_offset + 1);
										$rep_img_count++;
										if ($rep_img_count > $image_count_holder[$image])
										{
											// Only replace the excess.
											$sig = substr($sig, 0, $img_offset) . str_replace($image, '', substr($sig, $img_offset));
											// Stop looping.
											$img_offset = false;
										}
									}
								}
								else
									$replaces[$image] = '';

								continue;
							}

							// Does it have predefined restraints? Width first.
							if ($matches[6][$key])
								$matches[2][$key] = $matches[6][$key];
							if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
							{
								$width = $sig_limits[5];
								$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
							}
							elseif ($matches[2][$key])
								$width = $matches[2][$key];
							// ... and height.
							if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
							{
								$height = $sig_limits[6];
								if ($width != -1)
									$width = $width * ($height / $matches[4][$key]);
							}
							elseif ($matches[4][$key])
								$height = $matches[4][$key];

							// If the dimensions are still not fixed - we need to check the actual image.
							if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
							{
								$sizes = url_image_size($matches[7][$key]);
								if (is_array($sizes))
								{
									// Too wide?
									if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
									{
										$width = $sig_limits[5];
										$sizes[1] = $sizes[1] * ($width / $sizes[0]);
									}
									// Too high?
									if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
									{
										$height = $sig_limits[6];
										if ($width == -1)
											$width = $sizes[0];
										$width = $width * ($height / $sizes[1]);
									}
									elseif ($width != -1)
										$height = $sizes[1];
								}
							}

							// Did we come up with some changes? If so remake the string.
							if ($width != -1 || $height != -1)
							{
								$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
							}

							// Record that we got one.
							$image_count_holder[$image] = isset($image_count_holder[$image]) ? $image_count_holder[$image] + 1 : 1;
						}
						if (!empty($replaces))
							$sig = str_replace(array_keys($replaces), array_values($replaces), $sig);
					}
				}
				// Try to fix disabled tags.
				if (!empty($disabledTags))
				{
					$sig = preg_replace('~\[(?:' . implode('|', $disabledTags) . ').+?\]~i', '', $sig);
					$sig = preg_replace('~\[/(?:' . implode('|', $disabledTags) . ')\]~i', '', $sig);
				}

				$sig = strtr($sig, array("\n" => '<br>'));
				if ($sig != $row['signature'])
					$changes[$row['id_member']] = $sig;
			}
			if (wesql::num_rows($request) == 0)
				$done = true;
			wesql::free_result($request);

			// Do we need to delete what we have?
			if (!empty($changes))
			{
				foreach ($changes as $id => $sig)
					wesql::query('
						UPDATE {db_prefix}members
						SET signature = {string:signature}
						WHERE id_member = {int:id_member}',
						array(
							'id_member' => $id,
							'signature' => $sig,
						)
					);
			}

			$_GET['step'] += 50;
			if (!$done)
				pauseSignatureApplySettings();
		}
	}

	$context['signature_settings'] = array(
		'enable' => isset($sig_limits[0]) ? $sig_limits[0] : 0,
		'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
		'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
		'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
		'allow_smileys' => isset($sig_limits[4]) && $sig_limits[4] == -1 ? 0 : 1,
		'max_smileys' => isset($sig_limits[4]) && $sig_limits[4] != -1 ? $sig_limits[4] : 0,
		'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
		'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
		'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
	);

	// Temporarily make each setting a modSetting!
	foreach ($context['signature_settings'] as $key => $value)
		$modSettings['signature_' . $key] = $value;

	// Make sure we check the right tags!
	$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Clean up the tag stuff!
		$bbcTags = array();
		foreach (parse_bbc(false) as $tag)
			$bbcTags[] = $tag['tag'];

		if (!isset($_POST['signature_bbc_enabledTags']))
			$_POST['signature_bbc_enabledTags'] = array();
		elseif (!is_array($_POST['signature_bbc_enabledTags']))
			$_POST['signature_bbc_enabledTags'] = array($_POST['signature_bbc_enabledTags']);

		$sig_limits = array();
		foreach ($context['signature_settings'] as $key => $value)
		{
			if ($key == 'allow_smileys')
				continue;
			elseif ($key == 'max_smileys' && empty($_POST['signature_allow_smileys']))
				$sig_limits[] = -1;
			else
				$sig_limits[] = !empty($_POST['signature_' . $key]) ? max(1, (int) $_POST['signature_' . $key]) : 0;
		}

		$_POST['signature_settings'] = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $_POST['signature_bbc_enabledTags']));

		// Even though we have practically no settings let's keep the convention going!
		$save_vars = array();
		$save_vars[] = array('text', 'signature_settings');

		saveDBSettings($save_vars);
		redirectexit('action=admin;area=memberoptions;sa=sig');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=memberoptions;save;sa=sig';
	$context['settings_title'] = $txt['signature_settings'];

	$context['settings_message'] = '<p class="centertext">' . sprintf($txt['signature_settings_warning'], $context['session_id'], $context['session_var']) . '</p>';

	prepareDBSettingContext($config_vars);
}

// Just pause the signature applying thing.
function pauseSignatureApplySettings()
{
	global $context, $txt, $sig_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we exhausted all the time we allowed?
	if (time() - array_sum(explode(' ', $sig_start)) < 3)
		return;

	$context['continue_get_data'] = '?action=admin;area=memberoptions;sa=sig;apply;step=' . $_GET['step'] . ';' . $context['session_query'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	loadBlock('not_done');

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'sig';

	// Get the right percent.
	$context['continue_percent'] = round(($_GET['step'] / $context['max_member']) * 100);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}

// Show all the custom profile fields available to the user.
function ShowCustomProfiles()
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	$context['page_title'] = $txt['custom_profile_title'];
	loadBlock('show_custom_profile');

	// What about standard fields they can tweak?
	$standard_fields = array('location', 'gender', 'website', 'posts', 'warning_status');
	// What fields can't you put on the registration page?
	$context['fields_no_registration'] = array('posts', 'warning_status');

	// Are we saving any standard field changes?
	if (isset($_POST['save']))
	{
		checkSession();

		// Do the active ones first.
		$disable_fields = array_flip($standard_fields);
		if (!empty($_POST['active']))
		{
			foreach ($_POST['active'] as $value)
				if (isset($disable_fields[$value]))
					unset($disable_fields[$value]);
		}
		// What we have left!
		$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

		// Things we want to show on registration?
		$reg_fields = array();
		if (!empty($_POST['reg']))
		{
			foreach ($_POST['reg'] as $value)
				if (in_array($value, $standard_fields) && !isset($disable_fields[$value]))
					$reg_fields[] = $value;
		}
		// What we have left!
		$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

		if (!empty($changes))
			updateSettings($changes);
	}

	loadSource('Subs-List');

	$listOptions = array(
		'id' => 'standard_profile_fields',
		'title' => $txt['standard_profile_title'],
		'base_href' => $scripturl . '?action=admin;area=memberoptions;sa=profile',
		'get_items' => array(
			'function' => 'list_getProfileFields',
			'params' => array(
				true,
			),
		),
		'columns' => array(
			'field' => array(
				'header' => array(
					'value' => $txt['standard_profile_field'],
					'style' => 'text-align: left;',
				),
				'data' => array(
					'db' => 'label',
					'style' => 'width: 60%;',
				),
			),
			'active' => array(
				'header' => array(
					'value' => $txt['custom_edit_active'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						$isChecked = $rowData[\'disabled\'] ? \'\' : \' checked\';
						$onClickHandler = $rowData[\'can_show_register\'] ? sprintf(\' onclick="document.getElementById(\\\'reg_%1$s\\\').disabled = !this.checked;"\', $rowData[\'id\']) : \'\';
						return sprintf(\'<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s"%2$s%3$s>\', $rowData[\'id\'], $isChecked, $onClickHandler);
					'),
					'style' => 'width: 20%; text-align: center;',
				),
			),
			'show_on_registration' => array(
				'header' => array(
					'value' => $txt['custom_edit_registration'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						$isChecked = $rowData[\'on_register\'] && !$rowData[\'disabled\'] ? \' checked\' : \'\';
						$isDisabled = $rowData[\'can_show_register\'] ? \'\' : \' disabled\';
						return sprintf(\'<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s"%2$s%3$s>\', $rowData[\'id\'], $isChecked, $isDisabled);
					'),
					'style' => 'width: 20%; text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=memberoptions;sa=profile',
			'name' => 'standardProfileFields',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="save" value="' . $txt['save'] . '" class="save">',
				'style' => 'text-align: right;',
			),
		),
	);
	createList($listOptions);

	$listOptions = array(
		'id' => 'custom_profile_fields',
		'title' => $txt['custom_profile_title'],
		'base_href' => $scripturl . '?action=admin;area=memberoptions;sa=profile',
		'default_sort_col' => 'field_name',
		'no_items_label' => $txt['custom_profile_none'],
		'items_per_page' => 25,
		'get_items' => array(
			'function' => 'list_getProfileFields',
			'params' => array(
				false,
			),
		),
		'get_count' => array(
			'function' => 'list_getProfileFieldSize',
		),
		'columns' => array(
			'field_name' => array(
				'header' => array(
					'value' => $txt['custom_profile_fieldname'],
					'style' => 'text-align: left;',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl;

						return sprintf(\'<a href="%1$s?action=admin;area=memberoptions;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>\', $scripturl, $rowData[\'id_field\'], $rowData[\'field_name\'], $rowData[\'field_desc\']);
					'),
					'style' => 'width: 62%;',
				),
				'sort' => array(
					'default' => 'field_name',
					'reverse' => 'field_name DESC',
				),
			),
			'field_type' => array(
				'header' => array(
					'value' => $txt['custom_profile_fieldtype'],
					'style' => 'text-align: left;',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						$textKey = sprintf(\'custom_profile_type_%1$s\', $rowData[\'field_type\']);
						return isset($txt[$textKey]) ? $txt[$textKey] : $textKey;
					'),
					'style' => 'width: 15%;',
				),
				'sort' => array(
					'default' => 'field_type',
					'reverse' => 'field_type DESC',
				),
			),
			'active' => array(
				'header' => array(
					'value' => $txt['custom_profile_active'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return $rowData[\'active\'] ? $txt[\'yes\'] : $txt[\'no\'];
					'),
					'style' => 'width: 8%; text-align: center;',
				),
				'sort' => array(
					'default' => 'active DESC',
					'reverse' => 'active',
				),
			),
			'placement' => array(
				'header' => array(
					'value' => $txt['custom_profile_placement'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return $txt[\'custom_profile_placement_\' . (empty($rowData[\'placement\']) ? \'standard\' : ($rowData[\'placement\'] == 1 ? \'withicons\' : \'abovesignature\'))];
					'),
					'style' => 'width: 8%; text-align: center;',
				),
				'sort' => array(
					'default' => 'placement DESC',
					'reverse' => 'placement',
				),
			),
			'show_on_registration' => array(
				'header' => array(
					'value' => $txt['modify'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=memberoptions;sa=profileedit;fid=%1$s">' . $txt['modify'] . '</a>',
						'params' => array(
							'id_field' => false,
						),
					),
					'style' => 'width: 15%; text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=memberoptions;sa=profileedit',
			'name' => 'customProfileFields',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="new" value="' . $txt['custom_profile_make_new'] . '" class="new">',
				'style' => 'text-align: right;',
			),
		),
	);
	createList($listOptions);
}

function list_getProfileFields($start, $items_per_page, $sort, $standardFields)
{
	global $txt, $modSettings;

	$list = array();

	if ($standardFields)
	{
		$standard_fields = array('location', 'gender', 'website', 'posts', 'warning_status');
		$fields_no_registration = array('posts', 'warning_status');
		$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
		$registration_fields = isset($modSettings['registration_fields']) ? explode(',', $modSettings['registration_fields']) : array();

		foreach ($standard_fields as $field)
			$list[] = array(
				'id' => $field,
				'label' => isset($txt['standard_profile_field_' . $field]) ? $txt['standard_profile_field_' . $field] : (isset($txt[$field]) ? $txt[$field] : $field),
				'disabled' => in_array($field, $disabled_fields),
				'on_register' => in_array($field, $registration_fields) && !in_array($field, $fields_no_registration),
				'can_show_register' => !in_array($field, $fields_no_registration),
			);
	}
	else
	{
		// Load all the fields.
		$request = wesql::query('
			SELECT id_field, col_name, field_name, field_desc, field_type, active, placement
			FROM {db_prefix}custom_fields
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items_per_page}',
			array(
				'sort' => $sort,
				'start' => $start,
				'items_per_page' => $items_per_page,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$list[] = $row;
		wesql::free_result($request);
	}

	return $list;
}

function list_getProfileFieldSize()
{
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}custom_fields',
		array(
		)
	);

	list ($numProfileFields) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $numProfileFields;
}

// Edit some profile fields?
function EditCustomProfiles()
{
	global $txt, $scripturl, $context, $settings;

	// Sort out the context!
	$context['fid'] = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
	$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
	$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : $txt['custom_add_title'];
	loadBlock('edit_profile_field');

	// Load the profile language for section names.
	loadLanguage('Profile');

	if ($context['fid'])
	{
		$request = wesql::query('
			SELECT
				id_field, col_name, field_name, field_desc, field_type, field_length, field_options,
				show_reg, show_display, show_profile, private, guest_access, active, default_value,
				can_search, bbc, mask, enclose, placement
			FROM {db_prefix}custom_fields
			WHERE id_field = {int:current_field}',
			array(
				'current_field' => $context['fid'],
			)
		);
		$context['field'] = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if ($row['field_type'] == 'textarea')
				@list ($rows, $cols) = @explode(',', $row['default_value']);
			else
			{
				$rows = 3;
				$cols = 30;
			}

			$context['field'] = array(
				'name' => $row['field_name'],
				'desc' => $row['field_desc'],
				'colname' => $row['col_name'],
				'profile_area' => $row['show_profile'] === 'theme' ? 'options' : $row['show_profile'],
				'reg' => $row['show_reg'],
				'display' => $row['show_display'],
				'type' => $row['field_type'],
				'max_length' => $row['field_length'],
				'rows' => $rows,
				'cols' => $cols,
				'bbc' => $row['bbc'] ? true : false,
				'default_check' => $row['field_type'] == 'check' && $row['default_value'] ? true : false,
				'default_select' => $row['field_type'] == 'select' || $row['field_type'] == 'radio' ? $row['default_value'] : '',
				'options' => strlen($row['field_options']) > 1 ? explode(',', $row['field_options']) : array('', '', ''),
				'active' => $row['active'],
				'private' => $row['private'],
				'guest_access' => $row['guest_access'],
				'can_search' => $row['can_search'],
				'mask' => $row['mask'],
				'regex' => substr($row['mask'], 0, 5) == 'regex' ? substr($row['mask'], 5) : '',
				'enclose' => $row['enclose'],
				'placement' => $row['placement'],
			);
		}
		wesql::free_result($request);
	}

	// Setup the default values as needed.
	if (empty($context['field']))
		$context['field'] = array(
			'name' => '',
			'colname' => '???',
			'desc' => '',
			'profile_area' => 'forumprofile',
			'reg' => false,
			'display' => false,
			'type' => 'text',
			'max_length' => 255,
			'rows' => 4,
			'cols' => 30,
			'bbc' => false,
			'default_check' => false,
			'default_select' => '',
			'options' => array('', '', ''),
			'active' => true,
			'private' => false,
			'guest_access' => false,
			'can_search' => false,
			'mask' => 'nohtml',
			'regex' => '',
			'enclose' => '',
			'placement' => 0,
		);

	// Some of the more common field types. For each of the templates, the sub-array indicates the form element that needs to be adjusted.
	// Note that I'm mainly not bothering with anything daft like $txt strings - the string will be the same in every language because it's a name.
	$context['template_fields'] = array(
		'social' => array(
			'facebook' => array(
				'field_name' => 'Facebook',
				'field_desc' => $txt['your_facebook'],
				'profile_area' => 'forumprofile',
				'display' => true,
				'placement' => '1', // with icons
				'enclose' => '<a class="facebook" href="http://www.facebook.com/profile.php?id={INPUT}" target="_blank" title="Facebook - {INPUT}"><img src="{IMAGES_URL}/fields/facebook.png" alt="Facebook - {INPUT}"></a>',
				'field_type' => 'text',
				'max_length' => '32',
				'bbc' => false,
				'mask' => 'regex',
				'regex' => '~[0-9]{1,11}|[a-z0-9.]{3,32}~i',
				'private' => '0', // users can see it, owner can edit it
			),
			'twitter' => array(
				'field_name' => 'Twitter',
				'field_desc' => $txt['your_twitter'],
				'profile_area' => 'forumprofile',
				'display' => true,
				'placement' => '1', // with icons
				'enclose' => '<a class="twitter" href="http://twitter.com/#!/{INPUT}" target="_blank" title="Twitter - {INPUT}"><img src="{IMAGES_URL}/fields/twitter.png" alt="Twitter - {INPUT}"></a>',
				'field_type' => 'text',
				'max_length' => '16',
				'bbc' => false,
				'mask' => 'regex',
				'regex' => '~[a-z0-9_]{1,16}~i',
				'private' => '0', // users can see it, owner can edit it
			),
		),
		'im' => array(
			'aim' => array(
				'field_name' => 'AOL Instant Messenger',
				'field_desc' => $txt['your_aim'],
				'profile_area' => 'forumprofile',
				'display' => true,
				'placement' => '1', // with icons
				'enclose' => '<a class="aim" href="aim:goim?screenname={INPUT}&amp;message=' . $txt['aim_default_message'] . '" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/fields/aim.gif" alt="AIM - {INPUT}"></a>',
				'field_type' => 'text',
				'max_length' => '50',
				'bbc' => false,
				'mask' => 'regex',
				'regex' => '~[a-z][0-9a-z.-]{1,31}~i',
				'private' => '0', // users can see it, owner can edit it
			),
			'yim' => array(
				'field_name' => 'Yahoo! Messenger',
				'field_desc' => $txt['your_yim'],
				'profile_area' => 'forumprofile',
				'display' => true,
				'placement' => '1', // with icons
				'enclose' => '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="http://opi.yahoo.com/online?m=g&amp;t=0&amp;u={INPUT}" alt="Yahoo! Messenger - {INPUT}"></a>',
				'field_type' => 'text',
				'max_length' => '50',
				'bbc' => false,
				'mask' => 'email',
				'private' => '0', // users can see it, owner can edit it
			),
			'live' => array(
				'field_name' => 'MSN/Live',
				'field_desc' => $txt['msn_email_address'],
				'profile_area' => 'forumprofile',
				'display' => true,
				'placement' => '1', // with icons
				'enclose' => '<a class="msn" href="http://members.msn.com/{INPUT}" target="_blank" title="Live - {INPUT}"><img src="{IMAGES_URL}/fields/msntalk.gif" alt="Live - {INPUT}"></a>',
				'field_type' => 'text',
				'max_length' => '50',
				'bbc' => false,
				'mask' => 'email',
				'private' => '0', // users can see it, owner can edit it
			),
			'icq' => array(
				'field_name' => 'ICQ',
				'field_desc' => $txt['your_icq'],
				'profile_area' => 'forumprofile',
				'display' => true,
				'placement' => '1', // with icons
				'enclose' => '<a class="icq" href="http://www.icq.com/whitepages/about_me.php?uin={INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&amp;icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>',
				'field_type' => 'text',
				'max_length' => '12',
				'bbc' => false,
				'mask' => 'regex',
				'regex' => '~[1-9][0-9]{4,9}~i', // The lowest was 10000, highest unknown but 10 digits total should cover it
				'private' => '0', // users can see it, owner can edit it
			),
		),
		'gaming' => array(
			'steam' => array(
				'field_name' => 'Steam',
				'field_desc' => $txt['your_steam'],
				'profile_area' => 'forumprofile',
				'display' => true,
				'placement' => '1',
				'enclose' => '<a class="steam" href="http://steamcommunity.com/id/{INPUT}" target="_blank" title="Steam - {INPUT}"><img src="{IMAGES_URL}/fields/steam.png"></a>',
				'field_type' => 'text',
				'max_length' => '50',
				'bbc' => false,
				'mask' => 'regex',
				'regex' => '~[0-9a-z_-]{2,50}~i',
				'private' => '0', // users can see it, owner can edit it
			),
		),
	);

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession();

		// Everyone needs a name - even the (bracket) unknown...
		if (trim($_POST['field_name']) == '')
			fatal_lang_error('custom_option_need_name');
		$_POST['field_name'] = westr::htmlspecialchars($_POST['field_name']);
		$_POST['field_desc'] = westr::htmlspecialchars($_POST['field_desc']);

		// Checkboxes...
		$show_reg = isset($_POST['reg']) ? (int) $_POST['reg'] : 0;
		$show_display = isset($_POST['display']) ? 1 : 0;
		$bbc = isset($_POST['bbc']) ? 1 : 0;
		$show_profile = $_POST['profile_area'];
		$active = isset($_POST['active']) ? 1 : 0;
		$private = isset($_POST['private']) ? (int) $_POST['private'] : 0;
		$guest_access = isset($_POST['guest_access']) && $private < 2 ? 1 : 0;
		$can_search = isset($_POST['can_search']) ? 1 : 0;

		// Some masking stuff...
		$mask = isset($_POST['mask']) ? $_POST['mask'] : '';
		if ($mask == 'regex' && isset($_POST['regex']))
			$mask .= $_POST['regex'];

		$field_length = isset($_POST['max_length']) ? (int) $_POST['max_length'] : 255;
		$enclose = isset($_POST['enclose']) ? $_POST['enclose'] : '';
		$placement = isset($_POST['placement']) ? (int) $_POST['placement'] : 0;

		// Select options?
		$field_options = '';
		$newOptions = array();
		$default = isset($_POST['default_check']) && $_POST['field_type'] == 'check' ? 1 : '';
		if (!empty($_POST['select_option']) && ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio'))
		{
			foreach ($_POST['select_option'] as $k => $v)
			{
				// Clean, clean, clean...
				$v = westr::htmlspecialchars($v);
				$v = strtr($v, array(',' => ''));

				// Nada, zip, etc...
				if (trim($v) == '')
					continue;

				// Otherwise, save it boy.
				$field_options .= $v . ',';
				// This is just for working out what happened with old options...
				$newOptions[$k] = $v;

				// Is it default?
				if (isset($_POST['default_select']) && $_POST['default_select'] == $k)
					$default = $v;
			}
			$field_options = substr($field_options, 0, -1);
		}

		// Text area has default has dimensions
		if ($_POST['field_type'] == 'textarea')
			$default = (int) $_POST['rows'] . ',' . (int) $_POST['cols'];

		// Come up with the unique name?
		if (empty($context['fid']))
		{
			$colname = westr::substr(strtr($_POST['field_name'], array(' ' => '')), 0, 6);
			preg_match('~([\w\d_-]+)~', $colname, $matches);

			// If there is nothing to the name, then let's start out own - for foreign languages etc.
			if (isset($matches[1]))
				$colname = $initial_colname = 'cust_' . strtolower($matches[1]);
			else
				$colname = $initial_colname = 'cust_' . mt_rand(1, 999);

			// Make sure this is unique.
			// !!! This may not be the most efficient way to do this.
			$unique = false;
			for ($i = 0; !$unique && $i < 9; $i ++)
			{
				$request = wesql::query('
					SELECT id_field
					FROM {db_prefix}custom_fields
					WHERE col_name = {string:current_column}',
					array(
						'current_column' => $colname,
					)
				);
				if (wesql::num_rows($request) == 0)
					$unique = true;
				else
					$colname = $initial_colname . $i;
				wesql::free_result($request);
			}

			// Still not a unique colum name? Leave it up to the user, then.
			if (!$unique)
				fatal_lang_error('custom_option_not_unique');
		}
		// Work out what to do with the user data otherwise...
		else
		{
			// Anything going to check or select is pointless keeping - as is anything coming from check!
			if (($_POST['field_type'] == 'check' && $context['field']['type'] != 'check')
				|| (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && $context['field']['type'] != 'select' && $context['field']['type'] != 'radio')
				|| ($context['field']['type'] == 'check' && $_POST['field_type'] != 'check'))
			{
				wesql::query('
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:current_column}
						AND id_member > {int:no_member}',
					array(
						'no_member' => 0,
						'current_column' => $context['field']['colname'],
					)
				);
			}
			// Otherwise - if the select is edited may need to adjust!
			elseif ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio')
			{
				$optionChanges = array();
				$takenKeys = array();
				// Work out what's changed!
				foreach ($context['field']['options'] as $k => $option)
				{
					if (trim($option) == '')
						continue;

					// Still exists?
					if (in_array($option, $newOptions))
					{
						$takenKeys[] = $k;
						continue;
					}
				}

				// Finally - have we renamed it - or is it really gone?
				foreach ($optionChanges as $k => $option)
				{
					// Just been renamed?
					if (!in_array($k, $takenKeys) && !empty($newOptions[$k]))
						wesql::query('
							UPDATE {db_prefix}themes
							SET value = {string:new_value}
							WHERE variable = {string:current_column}
								AND value = {string:old_value}
								AND id_member > {int:no_member}',
							array(
								'no_member' => 0,
								'new_value' => $newOptions[$k],
								'current_column' => $context['field']['colname'],
								'old_value' => $option,
							)
						);
				}
			}
			//!!! Maybe we should adjust based on new text length limits?
		}

		// Do the insertion/updates.
		if ($context['fid'])
		{
			wesql::query('
				UPDATE {db_prefix}custom_fields
				SET
					field_name = {string:field_name}, field_desc = {string:field_desc},
					field_type = {string:field_type}, field_length = {int:field_length},
					field_options = {string:field_options}, show_reg = {int:show_reg},
					show_display = {int:show_display}, show_profile = {string:show_profile},
					private = {int:private}, guest_access = {int:guest_access},
					active = {int:active}, default_value = {string:default_value},
					can_search = {int:can_search}, bbc = {int:bbc}, mask = {string:mask},
					enclose = {string:enclose}, placement = {int:placement}
				WHERE id_field = {int:current_field}',
				array(
					'field_length' => $field_length,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'private' => $private,
					'guest_access' => $guest_access,
					'active' => $active,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'current_field' => $context['fid'],
					'field_name' => $_POST['field_name'],
					'field_desc' => $_POST['field_desc'],
					'field_type' => $_POST['field_type'],
					'field_options' => $field_options,
					'show_profile' => $show_profile,
					'default_value' => $default,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
				)
			);

			// Just clean up any old selects - these are a pain!
			if (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && !empty($newOptions))
				wesql::query('
					DELETE FROM {db_prefix}themes
					WHERE variable = {string:current_column}
						AND value NOT IN ({array_string:new_option_values})
						AND id_member > {int:no_member}',
					array(
						'no_member' => 0,
						'new_option_values' => $newOptions,
						'current_column' => $context['field']['colname'],
					)
				);
		}
		else
		{
			wesql::insert('',
				'{db_prefix}custom_fields',
				array(
					'col_name' => 'string', 'field_name' => 'string', 'field_desc' => 'string',
					'field_type' => 'string', 'field_length' => 'string', 'field_options' => 'string',
					'show_reg' => 'int', 'show_display' => 'int', 'show_profile' => 'string',
					'private' => 'int', 'guest_access' => 'int', 'active' => 'int', 'default_value' => 'string',
					'can_search' => 'int', 'bbc' => 'int', 'mask' => 'string', 'enclose' => 'string', 'placement' => 'int',
				),
				array(
					$colname, $_POST['field_name'], $_POST['field_desc'],
					$_POST['field_type'], $field_length, $field_options,
					$show_reg, $show_display, $show_profile,
					$private, $guest_access, $active, $default,
					$can_search, $bbc, $mask, $enclose, $placement,
				),
				array('id_field')
			);
		}

		// As there's currently no option to priorize certain fields over others, let's order them alphabetically.
		wesql::query('
			ALTER TABLE {db_prefix}custom_fields
			ORDER BY field_name',
			array(
				'db_error_skip' => true,
			)
		);
	}
	// Deleting?
	elseif (isset($_POST['delete']) && $context['field']['colname'])
	{
		checkSession();

		// Delete the user data first.
		wesql::query('
			DELETE FROM {db_prefix}themes
			WHERE variable = {string:current_column}
				AND id_member > {int:no_member}',
			array(
				'no_member' => 0,
				'current_column' => $context['field']['colname'],
			)
		);
		// Finally - the field itself is gone!
		wesql::query('
			DELETE FROM {db_prefix}custom_fields
			WHERE id_field = {int:current_field}',
			array(
				'current_field' => $context['fid'],
			)
		);
	}

	// Rebuild display cache etc.
	if (isset($_POST['delete']) || isset($_POST['save']))
	{
		checkSession();

		$request = wesql::query('
			SELECT col_name, field_name, field_type, bbc, enclose, placement, guest_access
			FROM {db_prefix}custom_fields
			WHERE show_display = {int:is_displayed}
				AND active = {int:active}
				AND private != {int:not_owner_only}
				AND private != {int:not_admin_only}',
			array(
				'is_displayed' => 1,
				'active' => 1,
				'not_owner_only' => 2,
				'not_admin_only' => 3,
			)
		);

		$fields = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$fields[] = array(
				'colname' => strtr($row['col_name'], array('|' => '', ';' => '')),
				'title' => strtr($row['field_name'], array('|' => '', ';' => '')),
				'type' => $row['field_type'],
				'bbc' => $row['bbc'] ? '1' : '0',
				'placement' => !empty($row['placement']) ? $row['placement'] : '0',
				'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
				'show_guest' => !empty($row['guest_access']),
			);
		}
		wesql::free_result($request);

		updateSettings(array('displayFields' => !empty($fields) ? serialize($fields) : ''));
		redirectexit('action=admin;area=memberoptions;sa=profile');
	}
}

?>