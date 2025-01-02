<?php
namespace Phresto;

require_once(__DIR__ . '/bootstrap.php');

Utils::updateModules();
$modules = Config::getConfig( 'modules' );

$sql = "SET foreign_key_checks = 0;\n\n";
$sql .= "DROP PROCEDURE IF EXISTS PROC_DROP_FOREIGN_KEY;\n\n";
$sql .= "CREATE PROCEDURE PROC_DROP_FOREIGN_KEY(IN tableName VARCHAR(64), IN constraintName VARCHAR(64))\n"
    . "    BEGIN\n"
    . "        IF EXISTS(\n"
    . "            SELECT * FROM information_schema.table_constraints\n"
    . "            WHERE \n"
    . "                table_schema    = DATABASE()     AND\n"
    . "                table_name      = tableName      AND\n"
    . "                constraint_name = constraintName AND\n"
    . "                constraint_type = 'FOREIGN KEY')\n"
    . "        THEN\n"
    . "            SET @query = CONCAT('ALTER TABLE ', tableName, ' DROP FOREIGN KEY ', constraintName, ';');PREPARE stmt FROM @query;EXECUTE stmt;DEALLOCATE PREPARE stmt;END IF;END;\n\n";

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
