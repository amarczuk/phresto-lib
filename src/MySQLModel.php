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

    protected static function getConds( $query = null, $prefix = '' ) {
        $conds = [];
        $binds = [];
        $i = 0;

        if ( is_array( $query ) && !empty( $query['where'] ) ) {
            foreach ( $query['where'] as $key => $val ) {
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

        return [ $conds, $binds ];
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
            $res[] = Container::$modelClass($row);
        }

        return $res;
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
            $res[] = Container::$modelClass( $row );
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
            $sql = "INSERT INTO " . static::COLLECTION . " ( `" . implode( '`, `', static::getFields() ) . "` ) ";
            $sql .= "VALUES ( :" . implode( ', :', static::getFields() ) . " )";
        }
        $db->query( $sql, $this->_properties );

        if ( $this->_new ) {
            $this->_new = false;
            $this->setById( $db->getLastId() );
        }

        return true;
    }

    protected function deleteRecord() {
        $db = MySQLConnector::getInstance( self::DB );

        $sql = "delete from " . static::COLLECTION . " where " . static::INDEX . " = :index limit 1";
        $bind = [ 'index' => $this->_properties[static::INDEX] ];
        $db->query( $sql, $bind );

        return true;
    }

    public static function count() {
        $db = MySQLConnector::getInstance( static::DB );

        $sql = "SELECT COUNT(" . static::INDEX . ") as cnt FROM " . static::COLLECTION . " WHERE 1;";
        $result = $db->query( $sql );

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
        $sql = "CREATE TABLE IF NOT EXISTS `" . static::COLLECTION . "` (\n";
        foreach (static::$_fields as $field => $type) {
            $sqlType = static::getSqlType($type);
            $sql .= "  `{$field}` {$sqlType}";
            if ($field == static::INDEX) {
                if ($sqlType == 'INT') {
                    $sql .= ' AUTO_INCREMENT';
                }
                $sql .= ' PRIMARY KEY';
            }
            $sql .= ",\n";
        }
        return trim($sql, ",\n") . "\n) ENGINE=INNODB;\n";
    }

    public static function getRelationCode() {
        $sql = '';
        foreach (static::$_relations as $model => $relation) {

            $fkTypes = ['n:1', '1<1'];
            if (!in_array($relation['type'], $fkTypes)) continue;
            $sql .= "  ADD CONSTRAINT\n";
            $sql .= "    FOREIGN KEY ({$relation['index']})\n";
            $sql .= "      REFERENCES {$relation['model']}({$relation['field']})\n";
            if (!empty($relation['dbactions'])) {
                $sql .= "      {$relation['dbactions']};\n";
            } else {
                $sql .= "      ON UPDATE CASCADE ON DELETE CASCADE;\n";
            }
        }
        if (empty($sql)) return '';

        $sql = "ALTER TABLE `" . static::COLLECTION . "` \n" . $sql;
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
