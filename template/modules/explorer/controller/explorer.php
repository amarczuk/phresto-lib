<?php

namespace Phresto\Modules\Controller;
use Phresto\Controller;
use Phresto\ModelController;
use Phresto\Config;
use Phresto\View;

/** 
* Tool to make testing your application easier
*/
class explorer extends Controller {

	const CLASSNAME = __CLASS__;
	protected $routeMapping = [];

	/** 
	* returns explorer's UI
	* @return html
	*/
	protected function get() {
		$view = View::getView( 'main', 'explorer' );
		$view->add( 'main', [], 'explorer' );

		return $view->get();
	}

	/** 
	* returns all routes available in the app
	* @return json
	*/
	public function routes_get() {
		$controllers = \Phresto\Modules\explorer::getRoutes();
		return View::jsonResponse( $controllers );
	}

}