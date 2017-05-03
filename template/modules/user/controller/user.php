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
	protected $routeMapping = [ 'auth_get' => [ 'service' => 0 ], 'all' => [ 'id' => 0 ] ];

	public function authenticate_post( string $email, string $password ) {
		$token = User::login( $email, $password );

		$ua = ( !empty( $this->headers['User-Agent'] ) ) ? $this->headers['User-Agent'] : '';
		return View::jsonResponse( [ 'token' => $token->encrypt( $ua ), 'expires' => $token->expires ] );
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
			setcookie( 'prsid', $token->encrypt( $ua ), 0, '/', $this->headers['Host'] );
			return $view->get();
			
		} catch( \Exception $e ) {
			throw new RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}
	}

}