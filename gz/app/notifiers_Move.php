<?php










if (!defined('WEDGE'))
	die('File cannot be requested directly');

class Move_Notifier extends Notifier
{

	public function getURL(Notification $notification)
	{
		$data = $notification->getData();
		return '<URL>?topic=' . $notification->getObject() . '.0';
	}


	public function getText(Notification $notification, $is_email = false)
	{
		global $txt;

		$data = $notification->getData();


		$notif = we::$is['admin'] || in_array($data['id_board'], we::$user['qsb_boards']) ? 'notifier_move' : 'notifier_move_noaccess';

		return strtr($txt[$notif . ($is_email ? '_text' : '_html')], array(
			'{MEMBER_NAME}' => $data['member']['name'],
			'{MEMBER_LINK}' => '<a href="<URL>?action=profile;u=' . $data['member']['id'] . '">' . $data['member']['name'] . '</a>',
			'{TOPIC_NAME}' => $data['subject'],
			'{TOPIC_LINK}' => '<a href="' . $notification->getURL() . '">' . $data['subject'] . '</a>',
			'{BOARD_NAME}' => $data['board'],
			'{BOARD_LINK}' => '<a href="<URL>?board=' . $data['id_board'] . '">' . $data['board'] . '</a>',
			'{BOARD_URL}' => '<URL>?board=' . $data['id_board'],
		));
	}

	public function getPreview(Notification $notification)
	{
		global $txt;

		$data = $notification->getData();
		$raw = get_single_post($data['id_msg']);

		if ($raw !== false)
			return $raw;


		loadLanguage('Errors');
		return '<div class="errorbox">' . $txt['topic_gone'] . '</div>';
	}
}
