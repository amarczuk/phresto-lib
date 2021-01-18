<?php
namespace Phresto;

require_once(__DIR__ . '/bootstrap.php');

if (empty($argv[2])) {
  echo "Provide data file name\n\n";
  die(1);
}

if (empty($argv[1])) {
  echo "Provide model name\n\n";
  die(1);
}

$data = json_decode(file_get_contents(getcwd() . "{$argv[2]}.json"));
$model = "Phresto\\Modules\\Model\\{$argv[1]}";

foreach ($data as $d) {
    $obj = new $model($d);
    try {
        $obj->save();
    } catch(\Exception $e) {
        echo $e->getMessage() . "\n";
    }
}

echo "done\n";

