<?php

namespace Phresto;
use Phresto\Interf\DBConnectorInterface;
use Phresto\Exception\DBException;

class DBConnector implements DBConnectorInterface
{

    const CLASSNAME = __CLASS__;

    protected static $dbs = [];
    protected $connection;

    public static function getInstance( $name, $options = null ) {
        if ( !empty( static::$dbs[$name] ) ) {
            return static::$dbs[$name];
        }

        if ( !empty( $options ) ) {
            $class = static::CLASSNAME;
            static::$dbs[$name] = new $class( $name, $options );
            return static::$dbs[$name];
        }

        $options = Config::getConfig( 'db' );
        if ( !empty( $options[$name] ) ) {
            $class = static::CLASSNAME;
            static::$dbs[$name] = new $class( $name, $options[$name] );
            return static::$dbs[$name];
        }

        throw new DBException( "Requested connection not found" );
    }

    public function __construct( $name, $options ) {
        if ( !$this->connection = $this->connect( $options ) ) {
            throw new DBException( "Cannot connect to database" );
        }

        static::$dbs[$name] = $this;
    }

    public function __destruct() {
        $this->disconnect();
    }


    public function connect( $options ) {
    }

    public function disconnect() {
    }

    public function close() {
    }

    public function escape( $var ) {
    }

    public function bind( $query, $variables ) {
    }

    public function query( $query, $bindings = [] ) {
    }

    public function count( $resource ) {
    }

    public function getNext( $resource ) {
    }

    public function getLastId() {
    }

    public function getLastError() {
    }

}
