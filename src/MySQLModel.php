<?php

namespace Phresto;
use Phresto\Model;
use Phresto\Container;
use Phresto\Exception\RequestException;

class MySQLModel extends Model {

    const CLASSNAME = __CLASS__;

    const DB = 'mysql';
    const NAME = 'model';
    const INDEX = 'id';
    const COLLECTION = 'model';

    /*
    * QUERY:
    *
    * ex1:
    *
    * [ 'where' => [
    *   'field' => value,
    *   'field2' => value2
    * ]] - field = 'value' AND field2 = 'value2'
    *
    * ex2:
    *
    * [ 'where' => [
    *   'field' => ['=' | '<>' | '>' | 'operator', value],
    * ]] - field =|<>|>|operator 'value'
    *
    * ex3:
    *
    * [ 'where' => [
    *   'field' => ['in', [value1, value2...]],
    * ]] - field IN ('value1', 'value2', ...)
    *
    * ex4:
    *
    * [ 'where' => [
    *   'or' => [ex1, ex2, ex3],
    * ]] - ex1 OR ex2 OR ex3
    *
    * ex5:
    *
    * [ 'where' => [
    *   'or' => [ex1, ex2, ['and' => [ex1, ex3]]],
    * ]] - ex1 OR ex2 OR (ex1 AND ex3)
    *
    *
    * ex6:
    *
    * [ 'where' => [
    *   'or' => [
    *       ['field' => value],
    *       ['field' => value2]
    *   ]
    * ]] - (field = 'value' OR field = 'value2')
    *
    */
    protected static function getConds( $query = null, $prefix = '', $i = 0 ) {
        $conds = [];
        $binds = [];

        if ( is_array( $query ) && !empty( $query['where'] ) ) {
            foreach ( $query['where'] as $key => $val ) {
                if ( is_array($val) && !is_string($key) ) {
                    list( $c, $b, $j) = static::getConds([ 'where' => $val ], $prefix, $i);
                    if (!empty($c)) {
                        $binds = array_merge($binds, $b);
                        $conds = array_merge($conds, $c);
                        $i = $j;
                    }
                    continue;
                }

                if ( is_array($val) ) {
                    list( $sql, $b, $j) = static::getNestedConds($key, $val, $prefix, $i);
                    if ($sql) {
                        $binds = array_merge($binds, $b);
                        $conds[] = $sql;
                        $i = $j;
                    }
                    continue;
                }

                if ( array_key_exists( $key, static::$_fields ) ) {
                    $sql = $prefix . $key . ' = :val' . $i;
                    $binds['val' . $i] = $val;
                    $conds[] = $sql;
                    $i++;
                }
            }
        }

        if ( empty( $conds ) ) {
            $conds = [ '1' ];
        }

        return [ $conds, $binds, $i ];
    }

    protected static function getNestedConds( $preKey, $query, $prefix, $i ) {
        $binds = [];
        if (
            is_string($query[0]) &&
            strtolower($query[0]) == 'in' &&
            array_key_exists( $preKey, static::$_fields )
        ) {
            $vals = [];
            foreach($query[1] as $val) {
                $vals[] = ":val{$i}";
                $binds["val{$i}"] = $val;
                $i++;
            }
            $sql = "{$prefix}{$preKey} IN (" . implode(', ', $vals) . ')';
            return [$sql, $binds, $i];
        }
        if (!empty($preKey) && strtolower($preKey) == 'or') {
            list( $cons, $binds, $j ) = static::getConds( ['where' => $query ], $prefix, $i );
            $sql = '(' . implode(' OR ', $cons ) . ')';
            return [$sql, $binds, $j];
        }
        if (!empty($preKey) && strtolower($preKey) == 'and') {
            list( $cons, $binds, $j ) = static::getConds( ['where' => $query ], $prefix, $i );
            $sql = '(' . implode(' AND ', $cons ) . ')';
            return [$sql, $binds, $j];
        }
        if (!empty($preKey) && array_key_exists( $preKey, static::$_fields )) {
            $sql = "{$prefix}{$preKey} {$query[0]} :val{$i}";
            $binds["val{$i}"] = $query[1];
            $i++;
            return [$sql, $binds, $i];
        }

        return ['', [], $i];
    }

    protected static function extendQuery( $query = null, $prefix = '' ) {
        $sql = '';

        if ( !empty( $query['order'] ) && !is_array( $query['order'] ) ) {
            $query['order'] = [ $query['order'] ];
        }

        if ( isset( $query['order'] ) && is_array( $query['order'] ) ) {
            $sql .= ' ORDER BY ' . $prefix . implode( ', ' . $prefix, $query['order'] );
        }

        if ( !empty( $query['limit'] ) ) {
            $sql .= ' LIMIT ' . $query['limit'];
        }

        if ( !empty( $query['offset'] ) ) {
            $sql .= ' OFFSET ' . $query['offset'];
        }

        return $sql;
    }

    protected static function getQueryFields( $query, $prefix = '' ) {
        $fields = "{$prefix}*";

        if ( !empty( $query['fields'] ) && is_string( $query['fields'] ) ) {
            $query['fields'] = explode( ',', str_replace( ' ', '', $query['fields'] ) );
        }

        if ( !empty( $query['fields'] ) && is_array( $query['fields'] ) ) {
            if ( !in_array( static::INDEX, $query['fields'] ) ) {
                array_unshift( $query['fields'], static::INDEX );
            }

            $queryfields = [];

            foreach ( $query['fields'] as $value) {
                $value = trim( $value );
                if ( array_key_exists( $value, static::$_fields ) ) {
                    $queryfields[] = $value;
                }
            }

            if ( !empty( $queryfields ) ) {
                $fields = "`{$prefix}" . implode( "`, `{$prefix}", $queryfields ) . '`';
            }
        }

        return $fields;
    }

    public static function find( $query = null ) {
        $db = MySQLConnector::getInstance( static::DB );
        list( $conds, $binds ) = static::getConds( $query );

        $fields = static::getQueryFields( $query );

        $sql = "SELECT {$fields} FROM " . static::COLLECTION . " WHERE " . implode( ' AND ', $conds );
        $sql .= static::extendQuery( $query );

        $result = $db->query( $sql, $binds );

        $modelClass = static::CLASSNAME;
        $res = [];
        while ( $row = $db->getNext( $result ) ) {
            $res[] = Container::$modelClass($row, false);
        }

        return $res;
    }

    public static function findOne($query = null) {
        $query['limit'] = 1;
        $res = static::find( $query );

        return !empty($res) && !empty($res[0]) ? $res[0] : null;
    }

    public static function findRelated( Model $model, $query = null ) {
        if ( !static::isRelated( $model->getName() ) || empty( $model->getIndex() ) ) {
            throw new RequestException( LAN_HTTP_BAD_REQUEST, 400 );
        }

        $db = MySQLConnector::getInstance( static::DB );
        $relation = static::getRelation( $model->getName() );
        list( $conds, $binds ) = static::getConds( $query, 'm.' );

        $fields = static::getQueryFields( $query, 'm.' );

        switch ( $relation['type'] ) {
            case '1:1':
            case '1:n':
            case 'n:1':
            case '1>1':
            case '1<1':
                $conds[] = 'm.' . $relation['index'] . ' = r.' . $relation['field'];
                $conds[] = 'r.' . $model->getIndexField() . ' = :mfield';
                $binds['mfield'] = $model->getIndex();
                $sql = "SELECT {$fields} FROM " . static::COLLECTION . " m, " . $model->getCollection() . " r
                 WHERE " . implode( ' AND ', $conds );
                $sql .= ( $relation['type'] == '1:n') ? " GROUP BY m." . static::INDEX : '';
                break;
            case 'n:n':
                break;
        }

        $sql .= static::extendQuery( $query, 'm.' );

        $result = $db->query( $sql, $binds );

        $modelClass = static::CLASSNAME;
        $res = [];
        while ( $row = $db->getNext( $result ) ) {
            $res[] = Container::$modelClass( $row, false );
        }

        return $res;
    }

    protected function saveRecord() {
        $db = MySQLConnector::getInstance( static::DB );

        if ( !$this->_new ) {
            $fields = [];
            foreach ( $this->_properties as $key => $value) {
                $fields[] = '`' . $key . '` = :' . $key;
            }
            $sql = "UPDATE " . static::COLLECTION . " SET " . implode( ', ', $fields );
            $sql .= " WHERE " . static::INDEX . " = :" . static::INDEX . " LIMIT 1";
        } else {
            $sql = "INSERT INTO " . static::COLLECTION . " ( `" . implode( '`, `', $this->filteredFields() ) . "` ) ";
            $sql .= "VALUES ( :" . implode( ', :', $this->filteredFields() ) . " )";
        }
        $db->query( $sql, $this->_properties );

        if ( $this->_new ) {
            $this->_new = false;
            $this->setById( $db->getLastId() );
        }

        return true;
    }

    protected function filteredFields() {
        $fields = static::getFields();
        $filtered = [];
        foreach ( $fields as $field ) {
            if ( array_key_exists( $field, $this->_properties ) ) {
                array_push($filtered, $field);
            }
        }

        return $filtered;
    }

    protected function deleteRecord() {
        $db = MySQLConnector::getInstance( self::DB );

        $sql = "delete from " . static::COLLECTION . " where " . static::INDEX . " = :index limit 1";
        $bind = [ 'index' => $this->_properties[static::INDEX] ];
        $db->query( $sql, $bind );

        return true;
    }

    public static function count($query = null) {
        $db = MySQLConnector::getInstance(static::DB);
        list($conds, $binds) = static::getConds($query);

        $fields = static::getQueryFields($query);

        $sql = "SELECT COUNT(" . static::INDEX . ") as cnt FROM " . static::COLLECTION . " WHERE " . implode(' AND ', $conds);
        $sql .= static::extendQuery($query);

        $result = $db->query($sql, $binds);

        $record = $db->getNext( $result );
        return $record['cnt'];
    }


    public static function countRelated( $model ) {
        if ( !static::isRelated( $model->getName() ) || empty( $model->getIndex() ) ) {
            throw new RequestException( LAN_HTTP_BAD_REQUEST, 400 );
        }

        $db = MySQLConnector::getInstance( static::DB );
        $relation = static::getRelation( $model->getName() );

        $binds = [];
        switch ( $relation['type'] ) {
            case '1:1':
            case '1:n':
            case 'n:1':
            case '1>1':
            case '1<1':
                $conds[] = 'm.' . $relation['index'] . ' = r.' . $relation['field'];
                $conds[] = 'r.' . $model->getIndexField() . ' = :mfield';
                $binds['mfield'] = $model->getIndex();
                $sql = "SELECT COUNT(m." . static::INDEX . ") as cnt FROM " . static::COLLECTION . " m, " . $model->getCollection() . " r
                 WHERE " . implode( ' AND ', $conds );
                $sql .= ( $relation['type'] == '1:n') ? " GROUP BY m." . static::INDEX : '';
                break;
            case 'n:n':
                break;
        }

        $result = $db->query( $sql, $binds );

        $record = $db->getNext( $result );
        return $record['cnt'];
    }

    public static function getCreationCode() {
        $db = MySQLConnector::getInstance( static::DB );
        $new = true;
        $newCols = [];
        $dropCols = [];
        $modelFields = static::getFields();
        try {
            $fields = $db->getFields( static::COLLECTION );
            $new = false;
            foreach($fields as $name => $type) {
                if (!in_array($name, $modelFields)) {
                    $dropCols[] = $name;
                }
            }
            foreach($modelFields as $field) {
                if (!array_key_exists($field, $fields)) {
                    $newCols[] = $field;
                }
            }
        } catch(\Exception $e) {
            // table does not exist
        }

        $sql = $new
            ? "CREATE TABLE IF NOT EXISTS `" . static::COLLECTION . "` (\n"
            : "ALTER TABLE `" . static::COLLECTION . "` \n";

        foreach (static::$_fields as $field => $type) {
            $sqlType = static::getSqlType($type);
            $add = in_array($field, $newCols);
            $drop = in_array($field, $dropCols);
            if (!$new && $add) {
                $sql .= "  ADD COLUMN ";
            } else if (!$new && $drop) {
                $sql .= "  DROP COLUMN ";
            } else if (!$new) {
                $sql .= "  MODIFY COLUMN ";
            }

            $sql .= "  `{$field}`";
            if (!$drop) {
                $sql .= " {$sqlType}";
                if ($field == static::INDEX) {
                    if ($sqlType == 'INT') $sql .= ' AUTO_INCREMENT';
                    if ($new) $sql .= ' PRIMARY KEY';
                }
            }
            $sql .= ",\n";
        }
        $sql = trim($sql, ",\n");
        $sql .= $new ? "\n) ENGINE=INNODB;\n" : ";\n";
        return $sql;
    }

    public static function getRelationCode() {
        $sqls = [];
        $fkNames = [];
        foreach (static::$_relations as $model => $relation) {
            $sql = '';

            $fkTypes = ['n:1', '1<1', 'n:n'];
            if (!in_array($relation['type'], $fkTypes) || !empty($relation['skipfk'])) continue;

            $fk = [static::NAME . '__' . $relation['index'], $relation['model'] . '__' . $relation['field']];
            if ($relation['type'] != 'n:n') sort($fk);
            $fkName = join($fk, '__');
            $relatedTable = $relation['type'] != 'n:n'
                ? constant("Phresto\\Modules\\Model\\{$relation['model']}::COLLECTION")
                : static::COLLECTION;

            $sql .= "  ADD CONSTRAINT {$fkName}\n";
            $sql .= "    FOREIGN KEY ({$relation['index']})\n";
            $sql .= "      REFERENCES {$relatedTable}({$relation['field']})\n";
            if (!empty($relation['dbactions'])) {
                $sql .= "      {$relation['dbactions']}";
            } else {
                $sql .= "      ON UPDATE CASCADE ON DELETE CASCADE";
            }

            $sqls[] = $sql;
            $fkNames[] = "ALTER TABLE `" . static::COLLECTION . "` DROP FOREIGN KEY IF EXISTS {$fkName};";
        }
        if (empty($sqls)) return '';

        $sql = implode(",\n", $sqls) . ";\n";

        $sql = implode("\n", $fkNames) . "\nALTER TABLE `" . static::COLLECTION . "` \n" . $sql;
        return $sql;
    }

    private static function getSqlType($type) {
        if (is_array($type)) {
            return $type['db'];
        }

        switch ($type) {
            case 'string':
                return 'VARCHAR(255)';
            case 'int':
                return 'INT';
            case 'boolean':
                return 'BOOLEAN';
            case 'double':
            case 'float':
                return 'DOUBLE';
            case 'DateTime':
                return 'DATETIME';
            default:
                return mb_strtoupper($type);
        }
    }
}
