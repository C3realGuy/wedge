<?php








if (!defined('WEDGE'))
	die('File cannot be requested directly.');




interface NotifSubscriber
{







	public function getURL($object);







	public function getName();







	public function getNotifier();








	public function isValidObject($object);














	public function getProfile($id_member);

















	public function getObjects(array $objects);
}
