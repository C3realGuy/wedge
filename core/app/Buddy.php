<?php
/**
 * This file deals with adding/removing users to/from buddy lists.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Adds or removes a member to/from your buddy list.
 *
 * - Called by action=buddy;u=x;session_id=y where x is the user being added.
 * - Session check in URL.
 * - Checks the profile_identity_own permission, and that the user is not a guest.
 * - Simple toggle; checks the current user's buddy list, if present removes it, if not adds it, then updates the current user's settings.
 * - Redirects back to the profile of the user specified in the URL (i.e. the user being added)
 */

function Buddy()
{
	checkSession('get');

	isAllowedTo('profile_identity_own');
	is_not_guest();

	if (empty($_REQUEST['u']))
		fatal_lang_error('no_access', false);
	$_REQUEST['u'] = (int) $_REQUEST['u'];

	// Remove if it's already there...
	if (in_array($_REQUEST['u'], we::$user['buddies']))
	{
		we::$user['buddies'] = array_diff(we::$user['buddies'], array($_REQUEST['u']));
		$buddy_action = 'remove';
	}
	// ...or add if it's not and if it's not you.
	elseif (MID != $_REQUEST['u'])
	{
		we::$user['buddies'][] = (int) $_REQUEST['u'];
		$buddy_action = 'add';
	}

	if (isset($buddy_action))
	{
		// Update the settings.
		updateMemberData(MID, array('buddy_list' => implode(',', we::$user['buddies'])));

		// Call a hook, just in case we want to do something with this. Let's pass both the user we're adding/removing, and what we did with them.
		call_hook('buddy', array((int) $_REQUEST['u'], $buddy_action));
	}

	// Redirect back to the profile
	redirectexit('action=profile;u=' . $_REQUEST['u']);
}
