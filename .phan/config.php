<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'.',
		// Things that we don't *depend on*, just *support* (but as phan cannot tell
		// these two things apart, we need to declare all extensions we interact with
		// here...)
		'../../extensions/Comments',
		'../../extensions/Echo',
		'../../extensions/RandomGameUnit',
		'../../extensions/Renameuser',
		'../../extensions/SocialProfile',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'vendor',
		'../../extensions/Comments',
		'../../extensions/Echo',
		'../../extensions/RandomGameUnit',
		'../../extensions/Renameuser',
		'../../extensions/SocialProfile',
	]
);

return $cfg;
