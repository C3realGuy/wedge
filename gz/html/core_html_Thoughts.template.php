<?php








function template_showThoughts()
{
	global $context, $txt;

	echo '
		<we:cat>
			', $txt['thoughts'], '
			<div class="thought_icon"></div>
		</we:cat>
		<div class="wide list-thoughts">
		<table class="cs0 thoughts" id="thought_thread" data-cx="thread ', $context['thought_context'], ' ', $_REQUEST['start'], '">',
			template_thoughts_thread(), '
		</table>
		</div>';
}

function template_showLatestThoughts()
{
	global $context, $txt;

	echo '
		<we:cat>
			', $txt['thoughts'], ' (', $context['total_thoughts'], ')
			<div class="thought_icon"></div>
		</we:cat>
		<div class="pagesection">
			', $context['page_index'], '
		</div>
		<div class="wide list-thoughts">
		<table class="cs0 thoughts" data-cx="profile ', $context['thought_context'], ' ', $_REQUEST['start'], '">',
			template_thoughts_table(), '
		</table>
		</div>
		<div class="pagesection">
			', $context['page_index'], '
		</div>';
}

function template_thoughts()
{
	global $context, $txt;

	if (!$context['action'])
		echo '
		<we:cat style="margin-top: 16px">
			<span class="floatright"><a href="<URL>?action=thoughts">', $txt['all_pages'], '</a></span>
			', $txt['thoughts'], '...
			<div class="thought_icon"></div>';
	else
		echo '
		<we:cat>
			<img src="', ASSETS, '/icons/profile_sm.gif">
			', $txt['thoughts'], empty($context['member']) ? '' : ' - ' . $context['member']['name'], ' (', $context['total_thoughts'], ')';

	echo '
		</we:cat>
		<div class="wide list-thoughts">
		<table class="cs0 thoughts" data-cx="latest ', $context['thought_context'], ' 0">',
			template_thoughts_table(), '
		</table>
		</div>';
}

function template_thoughts_thread()
{
	global $context, $txt;

	template_init_thoughts();

	$col = 2;

	foreach ($context['thoughts'] as $id => $thought)
	{
		$col = empty($col) ? 2 : '';
		echo '
			<tr><td class="windowbg', $col, ' thought"><ul><li id="t', $id, '">
				<div>';

		if (empty($thought['owner_name']))
			echo get_privacy_icon($thought['privacy']), $thought['text'], '
				</div>';
		else
			echo '
					<a class="more_button thome" data-id="', $id, '">', $txt['actions_button'], '</a>', get_privacy_icon($thought['privacy']),
					'<a href="<URL>?action=profile;u=', $thought['id_member'], '">', $thought['owner_name'], '</a>
					<span class="date">(', $thought['updated'], ')</span> &raquo; ', $thought['text'], template_thought_likes($id), '
				</div>';

		if (!empty($thought['sub']))
			template_sub_thoughts($thought);

		echo '
			</li></ul>', AJAX ? $context['header'] : '', '</td></tr>';
	}
}

function template_sub_thoughts(&$thought)
{
	global $txt;

	if (empty($thought['sub']))
		return;


	echo '<ul>';
	foreach ($thought['sub'] as $id => $tho)
	{
		if (empty($tho['owner_name']))
			echo '<li id="t', $id, '"><div>', get_privacy_icon($thought['privacy']), $tho['text'], '</div>';
		else
			echo '<li id="t', $id, '"><div><a class="more_button thome" data-id="', $id, '">', $txt['actions_button'], '</a>', get_privacy_icon($thought['privacy']),
			'<a href="<URL>?action=profile;u=', $tho['id_member'], '">', $tho['owner_name'], '</a> <span class="date">(', $tho['updated'], ')</span> &raquo; ',
			parse_bbc($tho['text'], 'thought', array('user' => $tho['id_member'])), template_thought_likes($id), '</div>';

		if (!empty($tho['sub']))
			template_sub_thoughts($tho);

		echo '</li>';
	}
	echo '</ul>';
}

function template_thoughts_table()
{
	global $context, $txt;

	template_init_thoughts();


	if (allowedTo('post_thought'))
		echo '
			<tr class="windowbg2">', SKIN_MOBILE ? '' : '
				<td class="bc">' . $txt['date'] . '</td>', '
				<td><span class="my thought" id="thought0"><span></span></span></td>
			</tr>';

	$col = 2;
	if (!SKIN_MOBILE)
	{
		foreach ($context['thoughts'] as $id => $thought)
		{
			$col = empty($col) ? 2 : '';
			echo '
			<tr class="windowbg', $col, '">
				<td class="bc">', $thought['updated'], '</td>
				<td><a class="more_button thome" data-id="', $id, '">', $txt['actions_button'], '</a>',
				get_privacy_icon($thought['privacy']), '<a href="<URL>?action=profile;u=', $thought['id_member'], '" id="t', $id, '">',
				$thought['owner_name'], '</a> &raquo; ', $thought['text'], template_thought_likes($id), '</td>
			</tr>';
		}
	}
	else
	{
		foreach ($context['thoughts'] as $id => $thought)
		{
			$col = empty($col) ? 2 : '';
			echo '
			<tr class="windowbg', $col, '">
				<td>', $thought['updated'], '
				<div class="more_button thome" data-id="', $id, '">', $txt['actions_button'], '</div><br>',
				get_privacy_icon($thought['privacy']), '<a href="<URL>?action=profile;u=', $thought['id_member'], '" id="t', $id, '">',
				$thought['owner_name'], '</a> &raquo; ', $thought['text'], template_thought_likes($id), '</td>
			</tr>';
		}
	}

	if (empty($context['thoughts']))
		echo '
			<tr class="windowbg center">
				<td', allowedTo('post_thought') ? ' colspan="2"' : '', '>', $txt['no_thoughts'], '</td>
			</tr>';

	if (AJAX && !empty($context['header']))
		echo '<tr class="hide"><td>', $context['header'], '</td></tr>';
}

function template_thought_likes($id_thought)
{
	global $context, $settings;

	if (empty($settings['likes_enabled']) || empty($context['liked_posts'][$id_thought]))
		return;

	$likes =& $context['liked_posts'][$id_thought];

	$you_like = !empty($likes['you']);
	$other_likes = isset($likes['others']) ? $likes['others'] : 0;
	$string = number_context($you_like ? 'you_like_this' : 'like_this', $other_likes);
	$num_likes = $other_likes + ($you_like ? 1 : 0);

	echo ' <span class="like_button" title="', strip_tags($string), '"> <a href="<URL>?action=like;sa=view;type=think;cid=' . $id_thought . '" class="fadein" onclick="return reqWin(this);"><span class="note', $you_like ? 'nice' : '', '">', $num_likes, '</span></a></span>';
}

function template_init_thoughts()
{
	global $settings;

	if (!we::$is_member || (!allowedTo('post_thought') && empty($settings['likes_enabled'])))
		return;

	add_js('
	oThought = new Thought(', PRIVACY_DEFAULT, ', ', PRIVACY_MEMBERS, ', ', PRIVACY_AUTHOR, ');');
}
