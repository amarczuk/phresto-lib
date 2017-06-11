<?php

namespace Phresto\Modules\Controller;
use Phresto\CustomModelController;
use Phresto\View;
use Phresto\Config;
use Phresto\Container;
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
	protected $routeMapping = [ 'auth_get' => [ 'service' => 0 ], 'all' => [ 'id' => 0 ] ];

	protected function auth( $methodName, $args = null ) {

		$hasAccess = $this->currentUser->hasAccess( $this->modelName, $methodName );
		if ( in_array( $methodName, ['authenticate_post', 'auth_get', 'register_post', 'current_get' ] ) ) {
			return $hasAccess;
		}

		/**
		 * user can only access himself unless it's superuser (status = 2) 
		 * or it's getting users related to the other model
		 */
		return $hasAccess && 
			   ( $this->currentUser->status == 2 || 
				 ( !empty( $args[0] ) && $this->currentUser->getIndex() == $args[0] ) ||
				 ( in_array( $methodName, ['head', 'get'] ) && !empty( $this->contextModel ) )
			   );
	}

	/**
	* update record
	* @param id id of record to update
	* @param json model properties
	* @return object updated record
	*/
	public function patch( $id = null ) {
		if ( !empty( $this->contextModel ) ) {
			throw new RequestException( LAN_HTTP_BAD_REQUEST, 400 );
		}

		if ( empty( $id ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}

		if ( empty( $this->body ) ) {
			throw new RequestException( LAN_HTTP_NO_CONTENT, 204 );
		}

		$modelInstance = Container::{$this->modelName}( $id );
		if ( empty( $modelInstance->id ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}

		// only super user can change status and profile
		if ( $this->currentUser->status != 2 ) {
			if ( !empty( $this->body['status'] ) ) $this->body['status'] = $modelInstance->status;
			if ( !empty( $this->body['profile'] ) ) $this->body['profile'] = $modelInstance->profile;
		}

		$modelInstance->update( $this->body );
		$modelInstance->save();
		return $this->jsonResponse( $modelInstance );
	}

	/**
	* upsert record
	* @param id (optional)
	* @param json model properties
	* @return object updated record
	*/
	public function put( $id = null ) {
		if ( !empty( $this->contextModel ) ) {
			throw new RequestException( LAN_HTTP_BAD_REQUEST, 400 );
		}

		if ( empty( $this->body ) ) {
			throw new RequestException( LAN_HTTP_NO_CONTENT, 204 );
		}

		$modelInstance = Container::{$this->modelName}( $id );
		
		// only super user can change status and profile
		if ( $this->currentUser->status != 2 ) {
			if ( !empty( $this->body['status'] ) ) $this->body['status'] = $modelInstance->status;
			if ( !empty( $this->body['profile'] ) ) $this->body['profile'] = $modelInstance->profile;
		}

		$modelInstance->update( $this->body );
		$modelInstance->save();
		return $this->jsonResponse( $modelInstance );
	}

	public function authenticate_post( string $email, string $password ) {
		$user = static::MODELCLASS;
		$token = $user::login( $email, $password );

		$ua = ( !empty( $this->headers['User-Agent'] ) ) ? $this->headers['User-Agent'] : '';
		$encrypted = $token->encrypt( $ua );
		setcookie( 'prsid', $encrypted, 0, '/' );
		return View::jsonResponse( [ 'token' => $encrypted, 'expires' => $token->expires->format( \DateTime::ISO8601 ) ] );
	}

	public function current_get() {
		return View::jsonResponse( $this->currentUser );
	}

	public function register_post( string $email, string $name, string $password ) {
		$userClass = static::MODELCLASS;
		$user = new $userClass();
		$user->email = $email;
		$user->name = $name;
		$user->password = $password;
		$user->status = 1;
		$user->profile = 2;

		$user->save();

		return $this->authenticate_post( $email, $password );
	}

	/** 
	* login/register using OAuth
	* @param string $service name of the external service (google, facebook, github, linkedin)
	* @param in query string ?ret=welcome.html service to redirect after login
	* @return destination's service URL html
	*/
	public function auth_get( $service ) {
		try {

			if ( !empty($this->query['ret']) ) {
				$_SESSION['ret'] = $this->query['ret'];
			}

			$conf = Config::getConfig( 'social', 'user' );
			switch ( $service ) {
				case 'google':
					$oauth = new GoogleApi( $conf['google']['key'], $conf['google']['secret'] );
					break;
				case 'facebook':
					$oauth = new FBApi( $conf['fb']['key'], $conf['fb']['secret'] );
					break;
				case 'github':
					$oauth = new GithubApi( $conf['github']['key'], $conf['github']['secret'] );
					break;
				case 'linkedin':
					$oauth = new LinkedinApi( $conf['linkedin']['key'], $conf['linkedin']['secret'] );
					break;
				default:
					throw new RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
					break;
			}

			$user = static::MODELCLASS;
			$token = $user::socialLogin( $oauth->getUserDetails() );
			$view = View::getView( 'oauth', 'user' );
			$view->add( 'oauthSuccess', 
						['ret' => ( !empty( $_SESSION['ret'] ) ? $_SESSION['ret'] : '')], 
						'user' );
			unset( $_SESSION['ret'] );
			$ua = ( !empty( $this->headers['User-Agent'] ) ) ? $this->headers['User-Agent'] : '';
			setcookie( 'prsid', $token->encrypt( $ua ), 0, '/' );
			return $view->get();
			
		} catch( \Exception $e ) {
			throw new RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}
	}

}