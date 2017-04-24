<?php









function template_boards()
{
	global $context, $txt, $settings;

	echo '
	<div id="boards_container">';





	$alt = false;
	$is_guest = we::$is_guest;
	$nb_new = get_unread_numbers($context['board_ids'], false, true);

	foreach ($context['categories'] as $category)
	{

		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		echo '
		<we:cat id="title_cat_', $category['id'], '">';

		if (!$is_guest && !empty($category['show_unread']))
			echo '
			<a class="unreadlink" href="<URL>?action=unread;c=', $category['id'], '">', $txt['view_unread_category'], '</a>';

		echo '
			', $category['link'];

		if (empty($category['hide_rss']))
			echo '
			<a class="catfeed feed_icon" href="<URL>?action=feed;c=', $category['id'], '"></a>';


		if ($category['can_collapse'])
			echo '
			<a class="collapse" href="', $category['collapse_href'], '">', $category['collapse_image'], '</a>';

		echo '
		</we:cat>
		<div class="wide">
			<table class="table_list board_list" id="boards_cat_', $category['id'], '">';


		if (!$category['is_collapsed'])
		{





			foreach ($category['boards'] as $board)
			{
				$alt = !$alt;
				$nb = !empty($nb_new[$board['id']]) ? $nb_new[$board['id']] : '';
				$boardstate = 'boardstate' . (SKIN_MOBILE ? ' mobile' : '');

				echo '
				<tr id="board_', $board['id'], '" class="windowbg', $alt ? '2' : '', '">
					<td class="icon">';


				if ($board['is_redirect'])
					echo '<div class="', $boardstate, ' link" title="', $txt['redirect_board'], '"></div>';
				else
				{
					echo '<a href="<URL>?action=unread;board=' . $board['id'] . ';children" title="' . $txt['show_unread'] . '">';


					if (!empty($board['custom_class']))
						echo '<div class="', $boardstate, ' ', $board['custom_class'], '"', !empty($board['custom_title']) ? ' title="' . $board['custom_title'] . '"' : '', '></div></a>';

					else
						echo '<div class="', $boardstate, empty($board['new']) ? '' : ' unread', '"></div></a>';
				}

				echo '
					</td>
					<td class="info">
						', $settings['display_flags'] == 'all' || ($settings['display_flags'] == 'specified' && !empty($board['language'])) ? '<img src="' . LANGUAGES . $context['languages'][$board['language']]['folder'] . '/Flag.' . $board['language'] . '.gif">&nbsp; ': '', '<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' class="subject" href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';


				if (!empty($board['can_approve_posts']) && (!empty($board['unapproved_posts']) || !empty($board['unapproved_topics'])))
					echo '
						<a href="<URL>?action=moderate;area=postmod;sa=', $board['unapproved_topics'] > 0 ? 'topics' : 'posts', ';brd=', $board['id'], ';', $context['session_query'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

				if ($nb && empty($board['redirect_newtab']))
					echo '
						<a href="<URL>?action=unread;board=', $board['id'], '.0;children', '" title="', $txt['show_unread'], '" class="note">', $nb, '</a>';

				if (!empty($board['description']))
					echo '
						<p>', $board['description'], '</p>';


				if (!empty($board['moderators']))
					echo '
						<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';


				if (!empty($board['children']))
				{

					$children = array();


					foreach ($board['children'] as $child)
					{
						$display = array();
						if (isset($child['display']))
							foreach ($child['display'] as $item => $string)
								if (isset($child[$item]))
									$display[] = number_context($string, $child[$item]);

						if ($child['is_redirect'])
							$child['link'] = '<a href="' . $child['href'] . '"' . (!empty($display) ? ' title="' . implode(', ', $display) . '"' : '') . '>' . $child['name'] . '</a>';
						else
						{
							$nb = !empty($nb_new[$child['id']]) ? $nb_new[$child['id']] : 0;
							$child['link'] = '<a href="' . $child['href'] . '">' . $child['name'] . '</a>' . ($nb ? ' <a href="<URL>?action=unread;board=' . $child['id'] . ';children" title="' . $txt['show_unread'] . '" class="notevoid">' . $nb . '</a>' : '');
						}


						if (!empty($child['can_approve_posts']) && (!empty($child['unapproved_posts']) || !empty($child['unapproved_topics'])))
							$child['link'] .= ' <a href="<URL>?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_query'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

						$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
					}
					echo '
						<div class="children" id="board_', $board['id'], '_children">
							<p><strong>', $txt['sub_boards'], '</strong>: ', implode(', ', $children), '</p>
						</div>';
				}

				echo '
					</td>';


				$display = array();
				foreach ($board['display'] as $item => $string)
					if (isset($board[$item]))
						$display[] = number_context($string, $board[$item]);

				echo '
					<td class="stats">', empty($display) ? '' : '
						<div>' . implode('</div><div>', $display) . '</div>
					', '</td>';





				if (!empty($board['last_post']['offlimits']))
					echo '
					<td class="lastpost">
						<p>', $board['last_post']['offlimits'], '</p>
					</td>';
				elseif (!empty($board['last_post']['id']))
					echo '
					<td class="lastpost">
						<p>
							', strtr($txt['last_post_author_link_time'], array(
								'{author}' => $board['last_post']['member']['link'],
								'{link}' => $board['last_post']['link'],
								'{time}' => $board['last_post']['on_time'])
							), '
						</p>
					</td>';
				else
					echo '
					<td class="lastpost"></td>';

				echo '
				</tr>';
			}
		}

		echo '
			</table>
		</div>';
	}

	echo '
	</div>';
}

function template_boards_ministats()
{
	global $context, $settings, $txt;


	if (empty($settings['show_stats_index']))
		echo '
	<div id="index_common_stats">
		', $txt['members'], ': ', $context['common_stats']['total_members'], ' &nbsp;&#8226;&nbsp; ', $txt['posts_made'], ': ', $context['common_stats']['total_posts'], ' &nbsp;&#8226;&nbsp; ', $txt['topics'], ': ', $context['common_stats']['total_topics'], '
		', !empty($settings['show_latest_member']) ? ' &nbsp;&#8226;&nbsp; ' . sprintf($txt['welcome_member'], '<strong>' . $context['common_stats']['latest_member']['link'] . '</strong>') : '', '
	</div>';
}

function template_boards_newsfader()
{

	global $context, $options, $txt, $settings;

	if (!empty($settings['show_newsfader']) && !empty($context['fader_news_lines']))
	{
		echo '
	<div id="newsfader">
		<we:cat>
			<div id="newsupshrink" title="', $txt['upshrink_description'], '"', empty($options['collapse_news_fader']) ? ' class="fold"' : '', '></div>
			', $txt['news'], '
		</we:cat>
		<ul class="reset" id="fadeScroller">';

		foreach ($context['news_lines'] as $news)
			echo '
			<li>', $news, '</li>';

		echo '
		</ul>
	</div>';

		add_js_file('fader.js');


		add_js('
	new weFader({
		control: \'fadeScroller\',
		template: \'%1$s\',
		delay: ', empty($settings['newsfader_time']) ? 5000 : $settings['newsfader_time'], '
	});

	new weToggle({', empty($options['collapse_news_fader']) ? '' : '
		isCollapsed: true,', '
		aSwapContainers: [\'fadeScroller\'],
		aSwapImages: [\'newsupshrink\'],
		sOption: \'collapse_news_fader\'
	});');
	}
}

function template_boards_below()
{
	global $context;

	if (we::$is_member)
	{
		echo '
	<ul id="posting_icons" class="reset floatleft">';


		$mark_read_button = array(
			'markread' => array('text' => 'mark_as_read', 'url' => '<URL>?action=markasread;sa=all;' . $context['session_query']),
		);

		echo '
	</ul>';


		if (!empty($context['categories']))
			echo '<div class="mark_read">', template_button_strip($mark_read_button), '</div>';
	}
}
