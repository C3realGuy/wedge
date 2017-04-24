<?php











class NotifSubscription
{
	protected $member;
	protected $object;
	protected $subs;











	public static function get(NotifSubscriber $subs, $object, $member = null)
	{
		if ($member == null)
			$member = MID;

		$query = wesql::query('
			SELECT id_member, id_object
			FROM {db_prefix}notif_subs
			WHERE id_member = {int:member}
				AND id_object = {int:object}
				AND type = {string:type}
			LIMIT 1', array(
				'member' => $member,
				'object' => $object,
				'type' => $subs->getName(),
			)
		);

		if (wesql::num_rows($query) == 0)
			return false;

		list ($id_member, $id_object) = wesql::fetch_row($query);
		wesql::free_result($query);

		return new self($id_member, $id_object, $subs);
	}











	public static function store(NotifSubscriber $subs, $object, $member = null)
	{
		if ($member == null)
			$member = MID;

		wesql::insert('', '{db_prefix}notif_subs',
			array('id_member' => 'int', 'id_object' => 'int', 'type' => 'string', 'starttime' => 'int'),
			array($member, $object, $subs->getName(), time())
		);

		return new self($member, $object, $subs);
	}













	public static function issue(NotifSubscriber $subs, string $notifier, $object, array $data)
	{

		$query = wesql::query('
			SELECT id_member
			FROM {db_prefix}notif_subs
			WHERE type = {string:type}
				AND id_object = {int:object}',
			array(
				'type' => $subs->getName(),
				'object' => $object,
			)
		);
		if (wesql::num_rows($query) == 0)
			return array();

		$notifications = array();
		$members = array();

		while ($row = wesql::fetch_row($query))
			if ($row[0] != MID)
				$members[] = $row[0];

		wesql::free_result($query);

		if (empty($members))
			return array();

		$notifications = Notification::issue($notifier, $members, $object, $data);

		return $notifications;
	}











	protected function __construct($member, $object, $subs)
	{
		$this->member = (int) $member;
		$this->object = (int) $object;
		$this->subs = $subs;
	}







	public function delete()
	{
		wesql::query('
			DELETE FROM {db_prefix}notif_subs
			WHERE id_member = {int:member}
				AND id_object = {int:object}
				AND type = {string:type}',
			array(
				'member' => $this->member,
				'object' => $this->object,
				'type' => $this->subs->getName(),
			)
		);
	}
}
