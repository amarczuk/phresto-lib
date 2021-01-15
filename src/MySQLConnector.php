<?php

namespace Phresto;
use Phresto\Exception\DBException;

class MySQLConnector extends DBConnector
{

    const CLASSNAME = __CLASS__;

    public function connect( $options ) {
        $db = @Container::mysqli( $options['host'], $options['user'], $options['passwd'], $options['dbname'] );

        if ( $db->connect_error ) {
            return false;
        }

        if ( empty( $options['names'] ) ) {
            $db->query( "SET NAMES utf8" );
        } else {
            $db->query( "SET NAMES " . $this->escape( $options['names']) );
        }

        return $db;
    }

    public function close() {
        $this->connection->close();
    }

    public function escape( $var ) {

        if ( is_bool( $var ) ) {
            return ( $var ) ? 'TRUE' : 'FALSE';
        }

        if ( empty( $var ) && $var !== 0 ) {
            return "''";
        }

        if ( is_string( $var ) ) {
            return "'" . $this->connection->real_escape_string( $var ) . "'";
        }

        if ( is_array( $var ) ) {
            foreach ( $var as $key => $val ) {
                $var[$key] = $this->escape( $val );
            }
            return '(' . implode( ', ', array_values( $var ) ) . ')';
        }

        if ( is_numeric( $var ) ) {
            return $var;
        }

        if ( is_object( $var ) && $var instanceof \DateTime ) {
            return "'" . $var->format( \DateTime::ISO8601 ) . "'";
        }

        if ( is_object( $var ) ) {
            return "'" . json_encode( $var ) . "'";
        }

        throw new DBException( "Provided type is not supported" );

    }

    public function bind( $query, $variables ) {
        foreach ( $variables as $key => $val ) {
            $val = $this->escape( $val );
            $query = preg_replace( "/\\:{$key}([\\s,\\)\\%\\.\\?]+|$)/isU", "{$val}\$1", $query );
        }

        return $query;
    }

    public function exec( $query, $bindings = [] ) {
        if ( !empty( $bindings ) ) {
            $query = $this->bind( $query, $bindings );
        }

        $this->connection->multi_query( $query );
        while ($this->connection->next_result());
        return $this->getLastError();
    }

    public function query( $query, $bindings = [] ) {
        if ( !empty( $bindings ) ) {
            $query = $this->bind( $query, $bindings );
        }

        if ( !$result = $this->connection->query( $query ) ) {
            throw new DBException( "Query failed: " . $this->getLastError() );

        }
        return $result;
    }

    public function count( $resource ) {
        return $resource->num_rows;
    }

    public function getNext( $resource ) {
        return $resource->fetch_assoc();
    }

    public function getLastId() {
        return $this->connection->insert_id;
    }

    public function getLastError() {
        return $this->connection->error;
    }

}
