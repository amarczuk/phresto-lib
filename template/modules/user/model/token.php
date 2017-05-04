<?php

namespace Phresto\Modules\Model;
use Phresto\MySQLModel;
use Phresto\Config;

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
        if ( $this->_new ) $this->token = str_replace( '.', '', uniqid( '',true ) );
    }

    protected function filterJson( $fields ) {
        $fields['token'] = '*********';
        $fields['expires'] = $this->expires;
    	return $fields;
    }

    public function encrypt( $userAgent ) {
        $conf = Config::getConfig( 'app' );
        return openssl_encrypt( 
            md5( $userAgent ) . '_' . $this->token,
            'aes-256-ctr',
            $conf['tokenEncryptionPass']
        );
    }

    public static function decrypt( $token, $userAgent ) {
        $conf = Config::getConfig( 'app' );
        list($ua, $token) = explode( '_', openssl_decrypt( 
            $token,
            'aes-256-ctr',
            $conf['tokenEncryptionPass']
        ));

        if ( md5( $userAgent ) != $ua ) {
            return false;
        }

        return new token( [ 'where' => [ 'token' => $token ] ] );
    }

}