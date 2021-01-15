<?php
namespace Phresto;

require_once(__DIR__ . '/bootstrap.php');

$modules = Config::getConfig( 'modules' );

$sql = "SET foreign_key_checks = 0;\n\n";
$relationSql = '';

foreach ($modules as $type => $module) {
    if (isset($module['Model'])) {
        foreach ($module['Model'] as $file) {
            $model = "Phresto\Modules\Model\\" . str_replace('.php', '', $file);
            $sql .= $model::getCreationCode() . "\n";
            $relationSql .= $model::getRelationCode() . "\n";
        }
    }
}

$sql .= "{$relationSql}\n\nSET foreign_key_checks = 1;\n";

echo $sql;

$db = MySQLConnector::getInstance( MySQLModel::DB );
echo $db->exec($sql, []);
