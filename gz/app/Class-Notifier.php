<?php








if (!defined('WEDGE'))
	die('File cannot be requested directly.');




class Notifier
{








	public function getURL(Notification $notification)
	{
		$data = $notification->getData();
		$msg = $notification->getObject();


		return '<URL>?topic=' . (isset($data['topic']) ? $data['topic'] : $data['id_topic']) . '.msg' . $msg . '#msg' . $msg;
	}









	public function getText(Notification $notification, $is_email = false)
	{
		global $txt;

		$data = $notification->getData();
		$object = $notification->getObject();
		$object_url = $notification->getURL();

		return strtr(
			$txt['notifier_' . $this->getName() . ($is_email ? '_text' : '_html')],
			array(
				'{MEMBER_NAME}' => $data['member']['name'],
				'{MEMBER_LINK}' => '<a href="<URL>?action=profile;u=' . $data['member']['id'] . '">' . $data['member']['name'] . '</a>',
				'{OBJECT_NAME}' => $data['subject'],
				'{OBJECT_LINK}' => '<a href="' . $object_url . '">' . $data['subject'] . '</a>',
				'{OBJECT_URL}'  => $object_url,
			)
		);
	}







	public function getName()
	{
		static $name = '';
		if (empty($name))
			$name = strtolower(preg_replace('~_Notifier.*~', '', get_class($this)));
		return $name;
	}












	public function handleMultiple(Notification $notification, array &$data)
	{
		return false;
	}










	public function getProfile($id_member)
	{
		global $txt;

		$name = $this->getName();
		return array($txt['notifier_' . $name . '_title'], $txt['notifier_' . $name . '_desc'], array());
	}









	public function saveProfile($id_member, array $settings)
	{
	}








	public function getEmail(Notification $notification)
	{
		global $txt;

		$name = $this->getName();
		return array($txt['notifier_' . $name . '_subject'], $this->getText($notification, true));
	}









	public function getIcon(Notification $notification)
	{
		global $memberContext;

		$member = $notification->getMemberFrom();
		if (empty($memberContext[$member]['avatar']))
			loadMemberAvatar($member, true);
		if (empty($memberContext[$member]['avatar']))
			return '';
		return $memberContext[$member]['avatar']['image'];
	}








	public function getPreview(Notification $notification)
	{
		global $txt;



		$data = $notification->getData();
		$raw = get_single_post($notification->getObject());

		if ($raw !== false)
			return $raw;


		loadLanguage('Errors');
		return '<div class="errorbox">' . $txt['topic_gone'] . '</div>';
	}








	public function getPrefs($id_member = null)
	{
		$all_prefs = weNotif::getPrefs($id_member ? $id_member : MID);
		return $all_prefs[$this->getName()];
	}









	public function getPref($key, $id_member = null)
	{
		$prefs = $this->getPrefs($id_member);
		return !empty($prefs[$key]) ? $prefs[$key] : null;
	}










	public function savePref($key, $value, $id_member = null)
	{
		$prefs = weNotif::getPrefs($id_member ? $id_member : MID);

		if (empty($prefs[$this->getName()]))
			$prefs[$this->getName()] = array();

		$prefs[$this->getName()][$key] = $value;

		weNotif::savePrefs($id_member ? $id_member : MID, $prefs);
	}











	public function beforeNotify(array &$members, &$id_object, array &$data)
	{
		return true;
	}








	public function afterNotify(array $notifications)
	{
	}
}
