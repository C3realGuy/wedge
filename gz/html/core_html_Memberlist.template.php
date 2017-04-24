<?php










function template_main()
{
	global $context, $txt;


	$memberlist_buttons = array(
		'view_all_members' => array('text' => 'view_all_members', 'url' => '<URL>?action=mlist;sa=all', 'class' => 'active'),
		'mlist_search' => array('text' => 'mlist_search', 'url' => '<URL>?action=mlist;sa=search'),
	);

	echo '
	<div class="main_section" id="memberlist">
		<we:cat>', !isset($context['old_search']) ? '
			<span class="floatright">' . $context['letter_links'] . '</span>' : '', '
			<a class="memfeed" href="<URL>?action=feed;sa=members" class="feed_icon"></a>
			', $txt['members_list'];

	echo '
		</we:cat>
		<div class="pagesection">',
			template_button_strip($memberlist_buttons), '
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>

		<div id="mlist" class="topic_table">
			<table class="table_grid w100 cs0">
			<thead>
				<tr class="catbg">';


	foreach ($context['columns'] as $column)
	{

		if (isset($context['old_search']))
			echo '
					<th', isset($column['class']) ? ' class="' . $column['class'] . '"' : '', isset($column['width']) ? ' style="width: ' . $column['width'] . 'px"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>', $column['label'], '</th>';

		elseif ($column['selected'])
			echo '
					<th class="nowrap', isset($column['class']) ? ' ' . $column['class'] : '', '" style="width: auto"' . (isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '') . '><a href="' . $column['href'] . '" rel="nofollow">' . $column['label'] . ' <span class="sort_' . $context['sort_direction'] . '"></span></a></th>';

		else
			echo '
					<th', isset($column['class']) ? ' class="' . $column['class'] . '"' : '', isset($column['width']) ? ' style="width: ' . $column['width'] . 'px"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>', $column['link'], '</th>';
	}
	echo '
				</tr>
			</thead>
			<tbody>';


	if (!empty($context['members']))
	{
		foreach ($context['members'] as $member)
		{
			echo '
				<tr class="windowbg center"', empty($member['sort_letter']) ? '' : ' id="letter' . $member['sort_letter'] . '"', '>
					<td class="windowbg2">
						', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '', '<img src="', $member['online']['image_href'], '" alt="', $member['online']['text'], '">', $context['can_send_pm'] ? '</a>' : '', '
					</td>
					<td class="left">', $member['link'], '</td>
					<td class="windowbg2">', $member['show_email'] == 'no' ? '' : '<a href="<URL>?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . ASSETS . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '"></a>', '</td>';

		if (!isset($context['disabled_fields']['website']))
			echo '
					<td>', $member['website']['url'] != '' ? '<a href="' . $member['website']['url'] . '" target="_blank" class="new_win"><img src="' . ASSETS . '/www.gif" alt="' . $member['website']['title'] . '" title="' . $member['website']['title'] . '"></a>' : '', '</td>';


		foreach ($context['custom_fields'] as $field_name => $details)
			echo '
					<td>', !empty($member['mlist_cf'][$field_name]) ? $member['mlist_cf'][$field_name] : '', '</td>';


		echo '
					<td>', empty($member['group']) ? $member['post_group'] : $member['group'], '</td>
					<td>', $member['registered_date'], '</td>';

		if (!isset($context['disabled_fields']['posts']))
		{
			echo '
					<td class="windowbg2 nowrap" style="width: 15px">', $member['posts'], '</td>
					<td class="statsbar" style="width: 120px">';

			if (!empty($member['post_percent']))
				echo '
						<div class="bar" style="width: ', $member['post_percent'] + 4, 'px">
							<div style="width: ', $member['post_percent'], 'px"></div>
						</div>';

			echo '
					</td>';
		}

		echo '
				</tr>';
		}
	}

	else
		echo '
				<tr>
					<td colspan="', $context['colspan'], '" class="windowbg">', $txt['search_no_results'], '</td>
				</tr>';


	echo '
			</tbody>
			</table>
		</div>';

	echo '
		<div class="pagesection">';


	if (isset($context['old_search']))
		echo '
			<div class="floatright">
				<a href="<URL>?action=mlist;sa=search;search=', $context['old_search_value'], '">', $txt['mlist_search_again'], '</a>
			</div>';

	echo '
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>
	</div>';
}


function template_search()
{
	global $context, $txt;


	$memberlist_buttons = array(
		'view_all_members' => array('text' => 'view_all_members', 'url' => '<URL>?action=mlist;sa=all'),
		'mlist_search' => array('text' => 'mlist_search', 'url' => '<URL>?action=mlist;sa=search', 'class' => 'active'),
	);


	echo '
	<form action="<URL>?action=mlist;sa=search" method="post" accept-charset="UTF-8">
		<div id="memberlist">
			<we:cat>
				<img src="' . ASSETS . '/buttons/search.gif">
				', $txt['mlist_search'], '
			</we:cat>
			<div class="pagesection">',
				template_button_strip($memberlist_buttons), '
			</div>';


	echo '
			<div id="memberlist_search" class="clear">
				<div class="roundframe">
					<div id="mlist_search" class="flow_hidden">
						<div id="search_term_input"><br>
							<strong>', $txt['search_for'], ':</strong>
							<input type="search" name="search" value="', $context['old_search'], '" size="28" class="search"> <input type="submit" value="' . $txt['search'] . '">
						</div>
						<div class="padding">
							<span class="floatleft">';

	$count = 0;
	foreach ($context['search_fields'] as $id => $title)
	{
		echo '
								<label><input type="checkbox" name="fields[]" value="', $id, '"', in_array($id, $context['search_defaults']) ? ' checked' : '', '>', $title, '</label><br>';

		if (round(count($context['search_fields']) / 2) == ++$count)
			echo '
							</span>
							<span class="floatleft">';
	}

	echo '
							</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>';
}
