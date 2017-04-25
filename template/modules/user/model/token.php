<?php

namespace Phresto\Modules\Model;
use Phresto\MySQLModel;

class token extends MySQLModel {
	const CLASSNAME = __CLASS__;

    const DB = 'mysql';
    const NAME = 'token';
    const INDEX = 'id';
    const COLLECTION = 'token';

    protected static $_fields = [ 'id' => 'int', 
                                  'created' => 'DateTime', 
                                  'token' => 'string', 
                                  'user' => 'int', 
                                  'ttl' => 'int' 
                                ];
    protected static $_defaults = [ 'ttl' => 7, 'created' => '' ];
    protected static $_relations = [
        'user' => [
            'type' => 'n:1',
            'model' => 'user',
            'field' => 'id',
            'index' => 'user'
        ]
    ];
    
    protected function default_created() {
        return new \DateTime();
    }

    protected function expires_value() {
        if ( empty( $this->created ) ) return null;
        $expires = new \DateTime( $this->created->format( \DateTime::ISO8601 ) );
        $expires->modify( "+ {$this->ttl} day" );
        return $expires;
    }

    protected function saveFilter() {
        if ( $this->_new ) $this->token = uniqid();
    }

    protected function filterJson( $fields ) {
        $fields['token'] = '*********';
        $fields['expires'] = $this->expires;
    	return $fields;
    }

}