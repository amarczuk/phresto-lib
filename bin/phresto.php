<?php

define( 'PHRESTO_ROOT', __DIR__ . '/../../' );

require_once __DIR__ . '/../autoload.php';

$functions = [
	'--updatemodulesiniulesini' => [ 'func' => 'updatemodules', 'desc' => 'Updates/creates conf/modules.ini' ],
	'-umi' => [ 'func' => 'updatemodules', 'desc' => 'Updates/creates conf/modules.ini' ],
	'--help' => [ 'func' => 'help', 'desc' => 'Prints help information' ],
	'-h' => [ 'func' => 'help', 'desc' => 'Prints help information' ]
];

foreach ($functions as $key => $value) {
	$run = 0;
	if (in_array($key, $argv)) {
		$value['func']();
		$run++;
	}

	if ($run == 0) {
		help();
	}
}

function updatemodulesini() {
	echo "Updating modules.ini...\n";

	Phresto\Utils::registerAutoload();
	Phresto\Utils::updateModules();

	echo "Done\n";
}

function help() {
	echo <<<END
   _o   ___  ___       ___  ___  __  ____  ____
 _//___/ o/ /__/ /__/ /__/ /__  /_    /   /   /
/_____/__/ /    /  / / \  /__  __/   /   /___/
_O_____O____________________________________
END;

	echo "\nPhresto cli\n\n";

	foreach ($functions as $key => $value) {
		echo "{$key} - {$value['desc']}\n";
	}
}