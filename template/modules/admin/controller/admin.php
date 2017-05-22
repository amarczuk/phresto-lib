<?php

namespace Phresto\Modules\Controller;
use Phresto\Controller;
use Phresto\View;
use Phresto\Exception\RequestException;
use Phresto\Modules\explorer;
use Phresto\Modules\Model\permission;
use Phresto\Modules\Model\profile;

/** 
* Tool to make administering your application easier
*/
class admin extends Controller {

	const CLASSNAME = __CLASS__;
	protected $routeMapping = [
		'permissions_get' => ['profileId' => 0]
	];

	/** 
	* returns admin's UI
	* @return string html
	*/
	public function get() {
		$view = View::getView( 'main', 'admin' );
		$view->add( 'main', [], 'admin' );

		return $view->get();
	}

	/** 
	* returns set of permissions for available routes and profile
	* @param $profileId mixed id of the profile
	* @return array json
    * @throws RequestException
	*/
	public function permissions_get( $profileId ) {
		$routes = explorer::getRoutes( true );
		$profile = new profile( $profileId );
		if ( empty( $profile->getIndex() ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}
		$permissions = permission::findRelated( $profile );
		$perm = [];
		foreach ($routes as $route) {
			if ( strpos( $route['endpoint'], '/_id_/' ) !== false ) continue;
			$perm[$route['endpoint']]['name'] = $route['endpoint'];
			foreach ($route['methods'] as $method) {
				$perm[$route['endpoint']][$method['name']] = new permission(['allow' => false, 'route' => $route['endpoint'], 'method' => $method['name'], 'profile' => $profileId]);
			}
		}
		foreach ($permissions as $value) {
			$perm[$value->route][$value->method] = $value;
		}
		return $this->jsonResponse( array_values( $perm ) );
	}
}