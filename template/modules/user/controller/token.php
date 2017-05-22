<?php

namespace Phresto\Modules\Controller;
use Phresto\CustomModelController;
use Phresto\View;

/** 
* Additional token's REST endpoints
*/
class token extends CustomModelController {

	const CLASSNAME = __CLASS__;
	const MODELCLASS = 'Phresto\\Modules\\Model\\token';
	protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];

	/** 
	* Delete expired tokens
	*/
	public function clean_get() {
		$token = static::MODELCLASS;
		$token::cleanExpired();

		return View::jsonResponse( [ 'ok' => true ] );
	}
}