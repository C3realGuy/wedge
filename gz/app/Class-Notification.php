<?php








if (!defined('WEDGE'))
	die('File cannot be requested directly.');






class Notification
{



	protected $id;
	protected $id_member;
	protected $id_member_from;
	protected $notifier;
	protected $id_object;
	protected $time;
	protected $unread;
	protected $data;














	public static function get($id = null, $id_member = null, $count = 1, $unread = false, $object = null, $notifier = '')
	{
		if (empty($id) && empty($id_member))
			return [];

		$request = wesql::query('
			SELECT *
			FROM {db_prefix}notifications
			WHERE ' . (!empty($id) ? 'id_notification = {int:id}' : '1=1') . (!empty($id_member) ? '
				AND id_member = {int:member}' : '') . ($unread ? '
				AND unread = 1' : '') . (!empty($object) ? '
				AND id_object = {int:object}' : '') . (!empty($notifier) ? '
				AND notifier = {string:notifier}' : '') . '
			ORDER BY time DESC' . ($count ? '
			LIMIT {int:count}' : ''),
			[
				'id' => (int) $id,
				'member' => (int) $id_member,
				'count' => (int) $count,
				'object' => (int) $object,
				'notifier' => $notifier,
			]
		);
		return self::fetchNotifications($request);
	}








	protected static function fetchNotifications($request)
	{
		$notifications = [];
		$notifiers = weNotif::getNotifiers();

		while ($row = wesql::fetch_assoc($request))
		{

			if (!isset($notifiers[$row['notifier']]))
				continue;

			$notifications[] = new self($row, $notifiers[$row['notifier']]);
		}

		wesql::free_result($request);

		return $notifications;
	}











	public static function markReadForNotifier($id_member, Notifier $notifier, $objects)
	{

		wesql::query('
			UPDATE {db_prefix}notifications
			SET unread = 0
			WHERE id_member = {int:member}
				AND id_object IN ({array_int:object})
				AND notifier = {string:notifier}
				AND unread = 1',
			[
				'member' => (int) $id_member,
				'object' => (array) $objects,
				'notifier' => $notifier->getName(),
			]
		);
		$affected_rows = wesql::affected_rows();

		if ($affected_rows > 0)
		{
			wesql::query('
				UPDATE {db_prefix}members
				SET unread_notifications = unread_notifications - {int:count}
				WHERE id_member = {int:member}',
				[
					'count' => $affected_rows,
					'member' => (int) $id_member,
				]
			);


			cache_put_data('quick_notification_' . $id_member, null, 86400);
		}
	}















	public static function issue($notifier_name, $id_member, $id_object, $data = [])
	{
		loadSource('Subs-Post');

		$notifier = weNotif::getNotifiers($notifier_name);
		$id_object = (int) $id_object;
		if (empty($id_object))
			throw new Exception('Object cannot be empty for notification');

		$members = (array) $id_member;


		$request = wesql::query('
			SELECT data, email_address, id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member})
			LIMIT {int:limit}',
			[
				'member' => $members,
				'limit' => count($members),
			]
		);
		$members = [];
		while ($row = wesql::fetch_assoc($request))
		{
			$member_data = empty($row['data']) ? [] : unserialize($row['data']);
			$members[$row['id_member']] = [
				'id' => $row['id_member'],
				'disabled_notifiers' => empty($member_data['disabled_notifiers']) ? [] : $member_data['disabled_notifiers'],
				'email_notifiers' => empty($member_data['email_notifiers']) ? [] : $member_data['email_notifiers'],
				'email' => $row['email_address'],
			];
		}
		wesql::free_result($request);


		if (!$notifier->beforeNotify($members, $id_object, $data))
			return false;


		$request = wesql::query('
			SELECT *
			FROM {db_prefix}notifications
			WHERE notifier = {string:notifier}
				AND id_member IN ({array_int:members})
				AND id_object = {int:object}
				AND unread = 1
			LIMIT {int:limit}',
			[
				'notifier' => $notifier_name,
				'object' => $id_object,
				'members' => array_keys($members),
				'limit' => count($members),
			]
		);

		while ($row = wesql::fetch_assoc($request))
		{
			$notification = new self($row, $notifier);


			if (!$notifier->handleMultiple($notification, $data) && !in_array($notifier_name, $members[$row['id_member']]['disabled_notifiers']))
			{
				$notification->updateTime();
				unset($members[$row['id_member']]);

				if (!empty($members[$row['id_member']]['email_notifiers'][$notifier_name])
					&& $members[$row['id_member']]['email_notifiers'][$notifier_name] === 1)
				{
					list ($subject, $body) = $notifier->getEmail($notification);
					sendmail($members[$row['id_member']]['email'], $subject, $body);
				}
			}
		}
		wesql::free_result($request);

		$time = time();

		if (empty($members))
			return [];


		$notifications = [];
		foreach ($members as $id_member => $pref)
		{
			if (in_array($notifier_name, $pref['disabled_notifiers']))
				continue;


			wesql::insert('', '{db_prefix}notifications',
				['id_member' => 'int', 'id_member_from' => 'int', 'notifier' => 'string-50', 'id_object' => 'int', 'time' => 'int', 'unread' => 'int', 'data' => 'string'],
				[$id_member, MID, $notifier_name, $id_object, $time, 1, serialize((array) $data)]
			);
			$id_notification = wesql::insert_id();

			if (!empty($id_notification))
			{
				$notifications[$id_member] = new self([
					'id_notification' => $id_notification,
					'id_member' => $id_member,
					'id_member_from' => MID,
					'id_object' => $id_object,
					'time' => $time,
					'unread' => 1,
					'data' => serialize((array) $data),
				], $notifier);


				if (!empty($pref['email_notifiers'][$notifier_name]) && $pref['email_notifiers'][$notifier_name] === 1)
				{
					list ($subject, $body) = $notifier->getEmail($notifications[$id_member]);

					sendmail($pref['email'], $subject, $body);
				}


				cache_put_data('quick_notification_' . $id_member, null, 86400);
			}
			else
				throw new Exception('Unable to create notification');
		}


		wesql::query('
			UPDATE {db_prefix}members
			SET unread_notifications = unread_notifications + 1,
				hey_not = 1
			WHERE id_member IN ({array_int:member})',
			['member' => array_keys($notifications)]
		);


		$notifier->afterNotify($notifications);


		call_hook('notification_new', [$notifications]);

		return is_array($id_member) ? $notifications : array_pop($notifications);
	}









	public function __construct(array $row, Notifier $notifier)
	{

		$this->id = $row['id_notification'];
		$this->id_member = $row['id_member'];
		$this->id_member_from = $row['id_member_from'];
		$this->notifier = $notifier;
		$this->id_object = $row['id_object'];
		$this->time = (int) $row['time'];
		$this->unread = $row['unread'];
		$this->data = unserialize($row['data']);
	}







	public function markAsRead()
	{
		if ($this->unread == 0)
			return;

		$this->unread = 0;
		$this->updateCol('unread', 0);


		wesql::query('
			UPDATE {db_prefix}members
			SET unread_notifications = unread_notifications - 1
			WHERE id_member = {int:member}',
			['member' => $this->getMember()]
		);


		cache_put_data('quick_notification_' . $this->getMember(), null, 86400);
	}








	public function updateData(array $data)
	{
		$this->data = (array) $data;
		$this->updateCol('data', serialize((array) $data));
	}







	public function updateTime()
	{
		$this->time = time();
		$this->updateCol('time', time());
	}









	protected function updateCol($column, $value)
	{
		wesql::query('
			UPDATE {db_prefix}notifications
			SET {raw:column} = {string:value}
			WHERE id_notification = {int:notification}',
			[
				'column' => addslashes($column),
				'value' => $value,
				'notification' => $this->getID(),
			]
		);
	}







	public function getID()
	{
		return $this->id;
	}







	public function getText()
	{
		return $this->notifier->getText($this);
	}







	public function getIcon()
	{
		return $this->notifier->getIcon($this);
	}







	public function getURL()
	{
		return $this->notifier->getURL($this);
	}







	public function getPreview()
	{
		return $this->notifier->getPreview($this);
	}







	public function getNotifier()
	{
		return $this->notifier;
	}







	public function getObject()
	{
		return $this->id_object;
	}







	public function getData()
	{
		return $this->data;
	}







	public function getTime()
	{
		return $this->time;
	}







	public function getUnread()
	{
		return $this->unread;
	}







	public function getMember()
	{
		return $this->id_member;
	}







	public function getMemberFrom()
	{
		return $this->id_member_from;
	}
}
