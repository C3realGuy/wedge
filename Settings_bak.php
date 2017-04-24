<?php
/**
 * Contains master settings for Wedge, including database credentials.
 * DO NOT CHANGE ANYTHING, UNLESS YOU KNOW WHAT YOU'RE DOING!
 */

# 1 = Maintenance Mode (admin-only)
# 2 = Install Mode
# 3 = Closed Mode. EVEN to admins! Change to 0 here to reopen.
$maintenance = 0;
$mtitle = 'Maintenance Mode';
$mmessage = 'This website is currently under maintenance. Please bear with us, we\'ll restore access as soon as we can!';

# Forum details
$mbname = 'Localhost';
$boardurl = 'http://localhost:8080';		# URL to your forum's root folder
$webmaster_email = 'local.host@local.host';
$cookiename = 'WedgeCookie106';
$cache_type = 'file';
$remove_index = 0;
$we_shot = 5;

# MySQL server
$db_server = 'localhost';
$db_name = 'wedge';
$db_user = 'root';
$db_passwd = '';
$ssi_db_user = '';
$ssi_db_passwd = '';
$db_prefix = 'wedge_';
$db_persist = 0;
$db_error_send = 1;
$db_show_debug = 0;
$db_last_error = 0;

# Enabled plugins
$my_plugins = '';


?>