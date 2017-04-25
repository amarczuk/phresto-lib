<?php

namespace Phresto\Interf;

interface DBConnectorInterface
{
	public static function getInstance( $name, $options = null );

    public function connect( $options );
    public function disconnect();
    public function bind( $query, $variables );
    public function query( $query, $bindings = [] );
    public function getNext( $resource );
    public function getLastId();
    public function getLastError();
    public function count( $resource );
}