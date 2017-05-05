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
                                  'password' => 'string', 
                                  'name' => 'string', 
                                  'status' => 'int', 
                                  'created' => 'DateTime', 
                                  'last_login' => 'DateTime',
                                  'profile' => 'int' 
                                ];

    protected static $_defaults = [ 'status' => 1, 'created' => '' ];
    protected static $_relations = [
        'token' => [
            'type' => '1:n',
            'model' => 'token',
            'field' => 'user',
            'index' => 'id'
        ],
        'profile' => [
            'type' => 'n:1',
            'model' => 'profile',
            'field' => 'id',
            'index' => 'profile'
        ]
    ];

    protected function image_value() {
        return '//www.gravatar.com/avatar/' . md5( $this->email ) . '?d=retro';
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
        if ( isset( $fields['password'] ) ) $fields['password'] = '*';
        if ( isset( $fields['email'] ) ) $fields['image'] = $this->image;
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

    public static function loginWithToken( $t ) {
        $t = str_replace( 'Bearer ', '', $t );
        $token = new token( ['where' => ['token' => $t] ] );
        if ( empty( $token->getIndex() ) ) {
            throw new \Exception( 'Token not found', 401 );
        }
        $users = static::findRelated( $token );
        return $users[0];
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