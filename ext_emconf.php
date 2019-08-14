<?php

$EM_CONF['xom'] = [
	'title' => 'xom',
	'description' => 'Provides service classes to work with the xom REST API.',
	'category' => 'be',
	'author' => 'medienreaktor GmbH',
	'author_email' => 'info@medienreaktor.de',
	'state' => 'stable',
	'uploadfolder' => false,
	'createDirs' => '',
	'clearCacheOnLoad' => false,
	'version' => '1.0.0',
	'constraints' => [
		'depends' => [
			'typo3' => '8.7.0-9.99.99',
		],
		'conflicts' => [],
		'suggests' => [],
	],
];
