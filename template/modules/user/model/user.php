<?php

namespace Phresto\Modules\Model;
use Phresto\MySQLModel;
use Phresto\Model;
use Phresto\Modules\Model\token;
use Phresto\Modules\Model\profile;
use Phresto\Modules\Model\permission;

class user extends MySQLModel {
    const CLASSNAME = __CLASS__;

    const DB = 'mysql';
    const NAME = 'user';
    const INDEX = 'id';
    const COLLECTION = 'user';

    protected static $_currentUser = null;
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

    public function hasAccess( $class, $method ) {
        $class = substr( $class, mb_strrpos( $class, '\\' ) + 1 );
        if ( mb_strpos( $method, '_' ) !== false ) {
            $tmp = explode( '_', $method );
            $class = $class . '/' . $tmp[0];
            $method = $tmp[1];
        }
        $permission = permission::find( [ 'where' => ['profile' => $this->profile, 'route' => $class, 'method' => $method ] ] );
    
        return ( !empty( $permission ) && !empty( $permission[0] ) && $permission[0]->getIndex() && $permission[0]->allow === true );
    }

    protected static function passHash( $password ) {
        return md5( md5( $password ) );
    }

    public static function getCurrent( $headers ) {
        if ( !empty( static::$_currentUser ) ) {
            return static::$_currentUser;
        }
        
        $encToken = '';
        if ( !empty( $headers['Authorization'] ) ) {
            $encToken = str_replace( 'Bearer ', '', $headers['Authorization'] );
        } else if ( !empty( $_COOKIE['prsid'] ) ) {
            $encToken = $_COOKIE['prsid'];
        }

        $token = token::decrypt( $encToken, $headers['User-Agent'] );

        if ( $token !== false && !empty( $token->getIndex() ) ) {
            $users = user::findRelated( $token );
            if ( !empty( $users ) && !empty( $users[0] ) ) {
                static::$_currentUser = $users[0];
                return static::$_currentUser;
            }
        }

        static::$_currentUser = new user();
        $profile = profile::find( [ 'where' => [ 'name' => 'visitor' ], 'limit' => 1 ] );

        static::$_currentUser->profile = $profile[0]->getIndex();
        return static::$_currentUser;
    }

    public static function login( $email, $password ) {
        if ( empty( $email ) || empty( $password ) ) {
            throw new \Exception( 'Blank password or email' );
        }

        $user = user::find( [ 'where' => [ 'email' => $email, 'password' => static::passHash( $password ) ] ] );
        if ( empty( $user ) || empty( $user[0] ) || empty( $user[0]->getIndex() ) ) {
            throw new \Exception( 'No user found' );
        }

        $user[0]->last_login = new \DateTime();
        $user[0]->save();

        return static::getToken( $user[0] );
    }

    public static function socialLogin( $userDetails ) {
        $user = new user( [ 'where' => [ 'email' => $userDetails['email'] ] ] );
        
        if ( empty( $user->getIndex() ) ) {
            $user->email = $userDetails['email'];
            $user->name = $userDetails['name'];
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