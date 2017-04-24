<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');












function Logout($internal = false, $redirect = true)
{
	global $user_settings, $context;


	if (!$internal)
		checkSession('get');

	loadSource('Subs-Auth');

	if (isset($_SESSION['pack_ftp']))
		$_SESSION['pack_ftp'] = null;


	unset($_SESSION['first_login']);


	if (we::$is_member)
	{

		call_hook('logout', array($user_settings['member_name']));


		wesql::query('
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => MID,
			)
		);
	}

	$_SESSION['log_time'] = 0;


	setLoginCookie(-3600, 0);


	session_destroy();
	if (!empty(we::$id))
		updateMemberData(we::$id, array('password_salt' => substr(md5(mt_rand()), 0, 4)));


	if ($redirect)
	{
		if (empty($_SESSION['logout_url']))
			redirectexit('', $context['server']['needs_login_fix']);
		else
		{
			$temp = $_SESSION['logout_url'];
			unset($_SESSION['logout_url']);

			redirectexit($temp, $context['server']['needs_login_fix']);
		}
	}
}
