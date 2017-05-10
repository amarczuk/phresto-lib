<?php

namespace Phresto;
use Phresto\ModelController;

class CustomModelController extends ModelController {

	const CLASSNAME = __CLASS__;
	const MODELCLASS = 'Phresto\\Module\\Model\\Name';

	public function __construct( $reqType, $route, $body, $bodyRaw, $query, $headers ) {
		$this->modelName = static::MODELCLASS;
		parent::__construct( static::MODELCLASS, $reqType, $route, $body, $bodyRaw, $query, $headers );
	}

	protected static function getParameters( $method, $className ) {
		return parent::getParameters( $method, static::MODELCLASS );
	}

	public static function discover( $all = false, $className = null, $getRelated = true ) {
		return parent::discover( $all, static::MODELCLASS, $getRelated );
	}

}