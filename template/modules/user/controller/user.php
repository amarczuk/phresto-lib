<?php

namespace Phresto\Modules\Controller;
use Phresto\CustomModelController;
use Phresto\View;
use Phresto\Config;
use Phresto\Modules\GoogleApi;
use Phresto\Modules\FBApi;
use Phresto\Modules\GithubApi;
use Phresto\Modules\LinkedinApi;
use Phresto\Exception\RequestException;

/** 
* Additional user's REST endpoints
*/
class user extends CustomModelController {

	const CLASSNAME = __CLASS__;
	const MODELCLASS = 'Phresto\\Modules\\Model\\user';

	public function authenticate_post( string $email, string $password ) {
		$token = User::login( $email, $password );

		return View::jsonResponse( [ 'token' => $token->token, 'expires' => $token->expires ] );
	}

	/** 
	* login/register using google's credentials
	*/
	public function google_get() {
		try {
			$conf = $Config->getConfig( 'social', 'user' );
			$oauth = new GoogleApi( $conf['google']['key'], $conf['google']['secret'] );
			
			$user = static::MODELCLASS;
			$token = $user::socialLogin( $oauth->getUserDetails() );
			$view = View::getView( 'oauth' );
			$view->add( 'oauthSuccess', [ 'token' => $token->token, 'expires' => $token->expires->format( \DateTime::ISO8601 ) ], 'user' );
			return $view->get();
			
		} catch( \Exception $e ) {
			throw new RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}
	}

	/** 
	* login/register using facebook's credentials
	*/
	public function facebook_get() {
		try {
			$conf = Config::getConfig( 'social', 'user' );
			$oauth = new FBApi( $conf['fb']['key'], $conf['fb']['secret'] );
			
			$user = static::MODELCLASS;
			$token = $user::socialLogin( $oauth->getUserDetails() );
			$view = View::getView( 'oauth' );
			$view->add( 'oauthSuccess', [ 'token' => $token->token, 'expires' => $token->expires->format( \DateTime::ISO8601 ) ], 'user' );
			return $view->get();
			
		} catch( \Exception $e ) {
			throw new RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}
	}

	/** 
	* login/register using github's credentials
	*/
	public function github_get() {
		try {
			$conf = Config::getConfig( 'social', 'user' );
			$oauth = new GithubApi( $conf['github']['key'], $conf['github']['secret'] );
			
			$user = static::MODELCLASS;
			$token = $user::socialLogin( $oauth->getUserDetails() );
			$view = View::getView( 'oauth' );
			$view->add( 'oauthSuccess', [ 'token' => $token->token, 'expires' => $token->expires->format( \DateTime::ISO8601 ) ], 'user' );
			return $view->get();
			
		} catch( \Exception $e ) {
			throw new RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}
	}

	/** 
	* login/register using linkedin's credentials
	*/
	public function linkedin_get() {
		try {
			$conf = Config::getConfig( 'social', 'user' );
			$oauth = new LinkedinApi( $conf['linkedin']['key'], $conf['linkedin']['secret'] );
			
			$user = static::MODELCLASS;
			$token = $user::socialLogin( $oauth->getUserDetails() );
			$view = View::getView( 'oauth' );
			$view->add( 'oauthSuccess', [ 'token' => $token->token, 'expires' => $token->expires->format( \DateTime::ISO8601 ) ], 'user' );
			return $view->get();
			
		} catch( \Exception $e ) {
			throw new RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}
	}


}