<?php









if (!defined('WEDGE'))
	die('Hacking attempt...');


function ViewRemote()
{
	wetem::hide();

	if (empty($_REQUEST['filename']) || !is_string($_REQUEST['filename']))
		fatal_lang_error('no_access', false);

	$request = wesql::query('
		SELECT data, filetype
		FROM {db_prefix}admin_info_files
		WHERE filename = {string:current_filename}
		LIMIT 1',
		array(
			'current_filename' => $_REQUEST['filename'],
		)
	);

	if (wesql::num_rows($request) == 0)
		fatal_lang_error('admin_file_not_found', true, array($_REQUEST['filename']));

	list ($file_data, $filetype) = wesql::fetch_row($request);
	wesql::free_result($request);


	clean_output(true);


	header('Content-Type: ' . $filetype);
	echo $file_data;
	obExit(false);
}
