<?php











if (!defined('WEDGE'))
	die('File cannot be requested directly.');




class weNotif
{
	protected static $notifiers = array();
	protected static $quick_count = 25;
	protected static $disabled = array();
	protected static $pref_cache = array();
	protected static $subscribers = array();









	public static function getNotifiers($notifier = null)
	{
		return !empty($notifier) ? (!empty(self::$notifiers[$notifier]) ? self::$notifiers[$notifier] : null) : self::$notifiers;
	}









	public static function getSubscribers($subscriber = null)
	{
		return !empty($subscriber) ? (!empty(self::$subscribers[$subscriber]) ? self::$subscribers[$subscriber] : null) : self::$subscribers;
	}









	public static function isNotifierDisabled(Notifier $notifier)
	{
		return we::$is_member && in_array($notifier->getName(), self::$disabled);
	}









	public static function initialize()
	{
		global $context;

		loadSource(array(
			'Class-Notification',
			'Class-Notifier',
			'Class-NotifSubscriber',
			'Class-NotifSubscription'
		));


		if (MID)
		{
			loadSource(array('notifiers/Likes', 'notifiers/Move'));
			self::$notifiers['move'] = new Move_Notifier();
			self::$notifiers['likes'] = new Likes_Notifier();
			self::$notifiers['likes_thought'] = new Likes_Thought_Notifier();
		}

		call_hook('notification_callback', array(&self::$notifiers));

		foreach (self::$notifiers as $notifier => $object)
		{
			unset(self::$notifiers[$notifier]);
			if ($object instanceof Notifier)
				self::$notifiers[$object->getName()] = $object;
		}


		call_hook('notification_subscription', array(&self::$subscribers));

		foreach (self::$subscribers as $type => $object)
			if (!($object instanceof NotifSubscriber) || self::isNotifierDisabled($object->getNotifier()))
				unset(self::$subscribers[$type]);

		loadLanguage('Notifications');


		if (MID)
		{
			$context['unread_notifications'] = we::$user['unread_notifications'];
			$disabled_notifiers = !empty(we::$user['data']['disabled_notifiers']) ? we::$user['data']['disabled_notifiers'] : array();
			$prefs = !empty(we::$user['data']['notifier_prefs']) ? we::$user['data']['notifier_prefs'] : array();


			self::$pref_cache[MID] = $prefs;

			self::$disabled = $disabled_notifiers;

			add_js_inline('
	we_notifs = ', (int) $context['unread_notifications'], ';');
		}
	}








	protected static function get_quick_notifications()
	{
		$notifications = cache_get_data('quick_notification_' . MID, 86400);


		if ($notifications === null)
		{
			$notifications = Notification::get(null, MID, self::$quick_count);
			cache_put_data('quick_notification_' . MID, $notifications, 86400);
		}

		$notifs = $notification_members = array();
		foreach ($notifications as $notification)
			$notification_members[] = $notification->getMemberFrom();
		loadMemberData($notification_members);

		foreach ($notifications as $notification)
			$notifs[] = array(
				'id' => $notification->getID(),
				'unread' => $notification->getUnread(),
				'text' => $notification->getText(),
				'icon' => $notification->getIcon(),
				'url' => $notification->getURL(),
				'time' => timeformat($notification->getTime()),
			);

		return $notifs;
	}

	public static function unread_count($id_member = 0)
	{
		$request = wesql::query('
			SELECT unread_notifications
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $id_member ?: MID,
			)
		);
		list ($unread) = wesql::fetch_row($request);
		wesql::free_result($request);

		return $unread;
	}









	public static function getPrefs($id_member)
	{
		if (isset(self::$pref_cache[$id_member]))
			return self::$pref_cache[$id_member];

		$request = wesql::query('
			SELECT data
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $id_member,
			)
		);
		list ($data) = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$data = unserialize($data);
		self::$pref_cache[$id_member] = !empty($data['notifier_prefs']) ? $data['notifier_prefs'] : array();

		return self::$pref_cache[$id_member];
	}











	public static function savePrefs($id_member, array $prefs)
	{
		unset(self::$pref_cache[$id_member]);

		$request = wesql::query('
			SELECT data
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $id_member,
			)
		);
		list ($data) = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$data = unserialize($data);
		$data['notifier_prefs'] = $prefs;
		updateMemberData($id_member, array('data' => serialize($data)));
	}








	public static function action()
	{
		global $context, $txt, $settings, $user_settings;

		$sa = !empty($_REQUEST['sa']) ? $_REQUEST['sa'] : '';

		if (we::$is_guest)
			fatal_lang_error('no_access', $sa == 'unread' ? false : 'general');

		if ($sa == 'redirect' && isset($_REQUEST['in']))
		{

			list ($notification) = Notification::get((int) $_REQUEST['in'], MID);


			if (empty($notification))
				fatal_lang_error('notification_not_found');


			$notification->markAsRead();


			redirectexit($notification->getURL());
		}

		if ($sa == 'subscribe' || $sa == 'unsubscribe')
		{
			checkSession('get');

			$object = (int) $_REQUEST['object'];
			$type = strtolower(trim($_REQUEST['type']));

			if (empty($object) || empty($type) || we::$is_guest || !isset(self::$subscribers[$type]))
				fatal_lang_error('wenotif_subs_object_type_empty');



			if (!self::$subscribers[$type]->isValidObject($object))
				fatal_lang_error('wenotif_subs_invalid_object');

			$subscription = NotifSubscription::get(self::$subscribers[$type], $object);
			if (($sa == 'subscribe' && $subscription !== false) || ($sa == 'unsubscribe' && $subscription === false))
				fatal_lang_error('wenotif_subs_' . ($sa == 'subscribe' ? 'already' : 'not') . '_subscribed');

			if ($sa == 'subscribe')
				NotifSubscription::store(self::$subscribers[$type], $object);
			else
				$subscription->delete();

			redirectexit(self::$subscribers[$type]->getURL($object));
		}

		if ($sa == 'unread')
			return_raw(self::unread_count() . ';' . (!empty($settings['pm_enabled']) ? we::$user['unread_messages'] : -1));

		if ($sa == 'markread' && isset($_REQUEST['in']))
		{
			$notifications = Notification::get($_REQUEST['in'], MID);

			if (!empty($notifications[0]))
				$notifications[0]->markAsRead();

			if (AJAX)
				exit();
			redirectexit();
		}
		elseif ($sa == 'markread')
		{

			wesql::query('UPDATE {db_prefix}notifications SET unread = 0 WHERE id_member = {int:me}', array('me' => we::$id));
			wesql::query('UPDATE {db_prefix}members SET unread_notifications = 0 WHERE id_member = {int:me}', array('me' => we::$id));


			cache_put_data('quick_notification_' . we::$id, null, 86400);
		}

		if ($sa == 'preview' && isset($_REQUEST['in']))
		{
			$notifications = Notification::get($_REQUEST['in'], MID);


			$preview = !empty($notifications[0]) ? $notifications[0]->getPreview() : '';
			return_raw($context['header'] . $preview . '<script>' . $context['footer_js'] . '</script>');
		}
		elseif (!empty($sa) && !empty(self::$notifiers[$sa]) && is_callable(self::$notifiers[$sa], 'action'))
			return self::$notifiers[$sa]->action();


		loadTemplate('Notifications');
		wetem::load('notifications_list');
		if (AJAX)
			wetem::hide();
		else
			$context['page_title'] = $txt['notifications'];

		if (isset($_GET['show']))
			updateMyData(array('n_all' => $_GET['show'] == 'latest'));


		if (!empty($user_settings['hey_not']))
			updateMemberData(MID, array('hey_not' => 0));


		$context['notifications'] = (array) Notification::get(null, MID, 0, AJAX && empty(we::$user['data']['n_all']));
		$notification_members = array();
		foreach ($context['notifications'] as $notif)
			$notification_members[] = $notif->getMemberFrom();
		loadMemberData($notification_members);

		$context['unread_count'] = self::unread_count();
	}









	public static function profile($memID)
	{
		global $context, $txt;

		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['notifications'],
			'description' => $txt['notification_profile_desc'],
			'icon' => 'profile_sm.gif',
			'tabs' => array(
				'general' => array(),
				'posts' => array(),
			),
		);


		if (isset($_GET['sa']) && $_GET['sa'] == 'posts')
		{
			loadSource('Profile-Modify');
			wetem::rename('weNotif::profile', 'notification');
			$context[$context['profile_menu_name']]['tab_data']['description'] = $txt['notification_info'];
			notification($memID);
			return;
		}


		if ($memID != MID)
			fatal_lang_error('no_access');

		$notifiers = self::getNotifiers();

		$request = wesql::query('
			SELECT data, notify_email_period
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => MID,
			)
		);
		list ($data, $period) = wesql::fetch_row($request);
		wesql::free_result($request);

		$data = unserialize($data);
		$disabled_notifiers = !empty($data['disabled_notifiers']) ? $data['disabled_notifiers'] : array();
		$email_notifiers = !empty($data['email_notifiers']) ? $data['email_notifiers'] : array();


		$settings_map = array();


		$config_vars = array();
		$config_vars[] = array(
			'int', 'notify_period',
			'text_label' => $txt['notify_period'],
			'subtext' => $txt['notify_period_desc'],
			'value' => (int) $period,
		);
		$config_vars[] = '';

		foreach ($notifiers as $notifier)
		{
			list ($title, $desc, $notifier_config) = $notifier->getProfile(MID);


			$config_vars[] = array(
				'select', 'disable_' . $notifier->getName(),
				'text_label' => $title,
				'subtext' => $desc,
				'data' => array(
					array(0, $txt['enabled']),
					array(1, $txt['disabled']),
				),
				'value' => in_array($notifier->getName(), $disabled_notifiers)
			);


			$config_vars[] = array(
				'select', 'email_' . $notifier->getName(),
				'value' => !empty($email_notifiers[$notifier->getName()]) ? $email_notifiers[$notifier->getName()] : 0,
				'text_label' => $txt['notification_email'],
				'data' => array(
					array(0, $txt['notify_periodically']),
					array(1, $txt['notify_instantly']),
					array(2, $txt['notify_disable']),
				),
			);


			$config_vars = array_merge($config_vars, $notifier_config);


			foreach ($notifier_config as $config)
				if (!empty($config) && !empty($config[1]) && !in_array($config[0], array('message', 'warning', 'title', 'desc')))
					$settings_map[$config[1]] = $notifier->getName();

			$config_vars[] = '';
		}

		unset($config_vars[count($config_vars) - 1]);


		if (isset($_GET['nsave']))
		{
			$disabled = array();
			$email = array();
			foreach ($notifiers as $notifier)
			{
				if (!empty($_POST['disable_' . $notifier->getName()]))
					$disabled[] = $notifier->getName();
				if (isset($_POST['email_' . $notifier->getName()]))
					$email[$notifier->getName()] = (int) $_POST['email_' . $notifier->getName()];
			}

			$data['disabled_notifiers'] = $disabled;
			$data['email_notifiers'] = $email;

			updateMemberData(MID, array(
				'data' => serialize($data),
				'notify_email_period' => min(120, max(1, (int) $_POST['notify_period'])),
			));


			$notifier_settings = array();
			foreach ($settings_map as $setting => $notifier)
			{
				if (empty($notifier_settings[$notifier]))
					$notifier_settings[$notifier] = array();

				if (!empty($_POST[$setting]))
					$notifier_settings[$notifier][$setting] = $_POST[$setting];
			}


			foreach ($notifier_settings as $notifier => $setting)
				$notifiers[$notifier]->saveProfile(MID, $setting);

			redirectexit('action=profile;area=notifications;updated');
		}


		loadSource('ManageServer');
		loadTemplate('Admin');

		prepareDBSettingContext($config_vars);

		$context['post_url'] = '<URL>?action=profile;area=notifications;nsave';

		wetem::load('show_settings');
	}









	public static function subs_profile($memID)
	{
		global $txt, $context;

		$subscriptions = array();
		$starttimes = array();
		foreach (self::$subscribers as $type => $subscriber)
		{
			$subscriptions[$type] = array(
				'type' => $type,
				'subscriber' => $subscriber,
				'profile' => $subscriber->getProfile($memID),
				'objects' => array(),
			);
			$starttimes[$type] = array();
		}

		$request = wesql::query('
			SELECT id_object, type, starttime
			FROM {db_prefix}notif_subs
			WHERE id_member = {int:member}',
			array(
				'member' => $memID,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (isset($subscriptions[$row['type']]))
				$subscriptions[$row['type']]['objects'][] = $row['id_object'];
			$starttimes[$row['type']][$row['id_object']] = timeformat($row['starttime']);
		}

		wesql::free_result($request);


		foreach ($subscriptions as &$subscription)
			if (!empty($subscription['objects']))
			{
				$subscription['objects'] = $subscription['subscriber']->getObjects($subscription['objects']);

				foreach ($subscription['objects'] as $id => &$object)
					$object['time'] = $starttimes[$subscription['type']][$id];
			}

		$context['notif_subscriptions'] = $subscriptions;
		$context['page_title'] = $txt['notif_subs'];
		loadTemplate('Notifications');
		wetem::load('notification_subs_profile');
	}








	public static function scheduled_prune()
	{
		global $settings;

		wesql::query('
			DELETE FROM {db_prefix}notifications
			WHERE unread = 0
				AND time < {int:time}',
			array(
				'time' => time() - ($settings['notifications_prune_days'] * 86400),
			)
		);
		wedb::optimize_table('{db_prefix}notifications');
	}








	public static function scheduled_periodical()
	{
		global $txt;

		loadSource('Subs-Post');


		$request = wesql::query('
			SELECT id_member, real_name, email_address, data, unread_notifications
			FROM {db_prefix}members
			WHERE unread_notifications > 0
				AND UNIX_TIMESTAMP() > notify_email_last_sent + (notify_email_period * 86400)',
			array()
		);

		$members = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$data = unserialize($row['data']);

			$valid_notifiers = array();

			foreach (self::$notifiers as $notifier)
			{
				$status = isset($data['email_notifiers'][$notifier->getName()]) ? $data['email_notifiers'][$notifier->getName()] : 0;
				if ($status < 2 && (empty($data['disabled_notifiers']) || !in_array($notifier, $data['disabled_notifiers'])))
					$valid_notifiers[$notifier->getName()] = true;
			}

			if (empty($valid_notifiers))
				continue;

			$members[$row['id_member']] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'email' => $row['email_address'],
				'valid_notifiers' => $valid_notifiers,
				'notifications' => array(),
				'unread' => $row['unread_notifications'],
			);
		}

		wesql::free_result($request);

		if (empty($members))
			return true;



		$request = wesql::query('
			SELECT *
			FROM {db_prefix}notifications
			WHERE id_member IN ({array_int:members})
				AND unread = 1',
			array(
				'members' => array_keys($members),
			)
		);

		while ($row = wesql::fetch_assoc($request))
			if (isset($members[$row['id_member']]['valid_notifiers'][$row['notifier']]))
			{
				$mem =& $members[$row['id_member']];

				if (empty($mem['notifications'][$row['notifier']]))
					$mem['notifications'][$row['notifier']] = array();

				$mem['notifications'][$row['notifier']][] = new Notification($row, self::getNotifiers($row['notifier']));
			}

		wesql::free_result($request);

		loadTemplate('Notifications');

		foreach ($members as $m)
		{
			if (empty($m['notifications']))
				continue;



			$use_html = false;
			$body = template_notification_email($m['notifications'], $use_html);
			$plain_body = $use_html ? template_notification_email($m['notifications'], false) : false;
			$subject = sprintf($txt['notification_email_periodical_subject'], $m['name'], $m['unread']);

			sendmail($m['email'], $subject, $body, null, null, $plain_body);
		}

		updateMemberData(array_keys($members), array(
			'notify_email_last_sent' => time(),
		));
	}
}
