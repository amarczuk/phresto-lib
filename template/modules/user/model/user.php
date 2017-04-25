<?php

namespace Phresto\Modules\Model;
use Phresto\MySQLModel;
use Phresto\Model;
use Phresto\Modules\Model\token;

class user extends MySQLModel {
	const CLASSNAME = __CLASS__;

    const DB = 'mysql';
    const NAME = 'user';
    const INDEX = 'id';
    const COLLECTION = 'user';

    protected static $_fields = [ 'id' => 'int', 
                                  'email' => 'string', 
                                  'email_md5' => 'string', 
                                  'password' => 'string', 
                                  'name' => 'string', 
                                  'status' => 'int', 
                                  'created' => 'DateTime', 
                                  'last_login' => 'DateTime' 
                                ];

    protected static $_defaults = [ 'status' => 1, 'created' => '' ];
    protected static $_relations = [
        'token' => [
            'type' => '1:n',
            'model' => 'token',
            'field' => 'user',
            'index' => 'id'
        ]
    ];

    protected function image_value() {
        return '//www.gravatar.com/avatar/' . $this->email_md5 . '?d=retro';
    }

    protected function saveFilter() {
        $this->email_md5 = md5( $this->email );
        if ( $this->_initial['password'] != $this->password ) {
            $this->password = static::passHash( $this->password );
        }
    }

    protected function default_created() {
        return new \DateTime();
    }

    protected function filterJson( $fields ) {
    	$fields['password'] = '* * *';
        $fields['image'] = $this->image;
    	return $fields;
    }

    protected static function passHash( $password ) {
        return md5( md5( $password ) );
    }

    public static function login( $email, $password ) {
        if ( empty( $email ) || empty( $password ) ) {
            throw new \Exception( 'Blank password or email' );
        }

        $user = new user( [ 'where' => [ 'email' => $email, 'password' => static::passHash( $password ) ] ] );
        if ( empty( $user->getIndex() ) ) {
            throw new \Exception( 'No user found' );
        }

        $user->last_login = new \DateTime();
        $user->save();

        return static::getToken( $user );
    }

    public static function socialLogin( $userDetails ) {
        var_dump($userDetails);
        $user = new user( [ 'where' => [ 'email' => $userDetails['email'] ] ] );
        
        if ( empty( $user->getIndex() ) ) {
            $user->email = $userDetails['email'];
            $user->name = $userDetails['name'];
            $user->save();
        }

        $user->last_login = new \DateTime();
        $user->save();

        return static::getToken( $user );
    }

    protected static function getToken( Model $user ) {
        $tokens = token::findRelated( $user );
        if ( empty( $tokens ) || empty( $tokens[0] ) ) {
            $token = new token();
            $token->user = $user->getIndex();
            $token->save();
        } else {
            $token = $tokens[0];
        }

        return $token;
    }
}