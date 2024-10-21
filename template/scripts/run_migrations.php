<?php
namespace Phresto;

require_once(__DIR__ . '/bootstrap.php');

$db = MySQLConnector::getInstance( MySQLModel::DB );

function logs($s) {
  echo date('Y-d-m H:i:s.') . gettimeofday()["usec"] . " {$s}\n";
}

logs('Starting mirgation');

$db->query("CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$res = $db->query("SELECT * FROM migrations ORDER BY date");
if (!$res) {
  echo "{$db->error}\n";
  die(1);
}

$migrations = [];

while($migration = $res->fetch_object()) {
  array_push($migrations, $migration->name);
}

$migrationFolder = __DIR__ . '/../migration/';
$migrationFiles = glob("{$migrationFolder}*.php");
$toRun = [];

foreach($migrationFiles as $migrationFile) {
  $migrationClass = 'Migration_' . str_replace([$migrationFolder, '.php'], '', $migrationFile);
  require_once($migrationFile);
  $m = new $migrationClass();
  $name = $m->getName();
  if (strlen($name) > 50) {
    logs("Error ({$name}): Migration name should be max 50 characters long");
    die(1);
  }
  if (in_array($name, $migrations)) {
    logs("'{$name}' from {$migrationFile} has beed already executed (skipping)");
    continue;
  }
  array_push($toRun, $m);
}

usort($toRun, function($a, $b) { return ($a->getTime() <=> $b->getTime()); });

foreach($toRun as $script) {
  logs("Running {$script->getName()}");
  try {
    $script->run($db);
  } catch (\Exception $e) {
    logs("Error: {$e->getMessage()}");
    die(1);
  }
  logs("Excecution succesfull");

  $name = $db->escape($script->getName());
  $time = time();
  $db->query("INSERT INTO migrations (`date`, `name`) VALUES ({$time}, {$name})");
}

logs("Migrtion finished");
