<?php

namespace Phresto;
use Phresto\Controller;
use Phresto\View;
use Phresto\Exception\RequestException;
use Phresto\Modules\Model\user;

class ModelController extends Controller {

	const CLASSNAME = __CLASS__;

	protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];

	protected $modelName;
	protected $contextModel;
	protected $methodName;

	protected static $type = 'model';

	public function __construct( $modelName, $reqType, $route, $body, $bodyRaw, $query, $headers, Model $contextModel = null ) {
		$this->modelName = $modelName;
		$this->contextModel = $contextModel;
		parent::__construct( $reqType, $route, $body, $bodyRaw, $query, $headers );
	}

	public function exec() {
		list( $method, $args ) = $this->getMethod();
		$this->methodName = $method->name;
		if ( !$this->auth( $method->name, $args ) ) {
			throw new Exception\RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}

		if ( $this->hasNextRoute() ) {
			$route = $this->getNextRoute();
			return $this->escalate( ( !empty( $this->route[0] ) ) ? $this->route[0] : 0, $route[0] );
		}

		$method->setAccessible( true );
		try {
			return $method->invokeArgs( $this, $args );
		} catch ( \TypeError $error ) {
            error_log( $error->getMessage() );
			throw new Exception\RequestException( LAN_HTTP_BAD_REQUEST, 400 );
		}
	}

	protected function hasNextRoute() {
		$routeMapping = $this->getRouteMapping( $this->methodName );
		return count( $routeMapping ) < count( $this->route );
	}

	protected function auth( $methodName, $args = null ) {
		return $this->currentUser->hasAccess( $this->modelName, $methodName );
	}

	protected function getNextRoute() {
		$routeMapping = $this->getRouteMapping( $this->methodName );
		$cnt = count( $routeMapping );
		$route = $this->route;
		for ( $i = 0; $i < $cnt; $i++ ) {
			array_shift( $route );
		}
		return $route;
	}

	protected function escalate( $id, $model ) {
		$thisModel = Container::{$this->modelName}();
		if ( !empty( $this->contextModel ) ) {
			$thisModel->setRelatedById( $this->contextModel, $id );
		} else {
			$thisModel->setById( $id );
		}

		$thisModelName = $this->modelName;
		if ( !$thisModel->getIndex() || !$thisModelName::isRelated( $model ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}

		$modelClass = 'Phresto\\Modules\\Model\\' . $model;
		$newRoute = $this->getNextRoute();
		array_shift( $newRoute );
		$modelContr = Container::{'Phresto\\ModelController'}( $modelClass, $this->reqType, $newRoute, $this->body, $this->bodyRaw, $this->query, $this->headers, $thisModel );
		return $modelContr->exec();
	}

	protected static function getParameters( $method, $className ) {
		$params = $method->getParameters();

		if ( in_array( $method->name, ['post', 'put', 'patch'] ) ) {
			$reflection = new \ReflectionClass( $className );
			$staticProps = $reflection->getStaticProperties();
			$modelFields = $staticProps['_fields'];
			foreach ( $modelFields as $key => $value) {
				$params[] = [ 'name' => $key, 'type' => (is_array($value)) ? $value['type'] : $value ];
			}

		}

		return $params;
	}

	protected static function getRelatedEndpoints( $className ) {
		$getModelDisc = function( $paths, $model ) {
			foreach ( $paths as $path ) {
				if ( $path['endpoint'] == $model ) {
					return $path;
				}
			}

			return null;
		};

		$reflection = new \ReflectionClass( $className );
		$staticProps = $reflection->getStaticProperties();
		if ( empty( $staticProps['_relations'] ) ) {
			return [];
		}

		$tmp = explode( '\\', ( isset( $className ) ) ? $className : static::CLASSNAME );
		$classNameOnly = array_pop( $tmp );

		$methodsAllowed = [
			'1:n' => [ 'head', 'get', 'post', 'delete' ],
			'n:n' => [ 'head', 'get', 'post', 'delete' ],
			'1:1' => [ 'head', 'get', 'post', 'delete' ],
			'1>1' => [ 'head', 'get', 'post', 'delete' ],
			'1<1' => [ 'head', 'get' ],
			'n:1' => [ 'head', 'get' ]
		];

		$relatedModels = $staticProps['_relations'];
		$endpoints = [];
		foreach ( $relatedModels as $name => $relation ) {
            $model = $relation['model'];
			$paths = ModelController::discover( false, "\\Phresto\\Modules\\Model\\{$model}", false );
			$modelDiscovery = $getModelDisc( $paths, $model );
			if ( empty( $modelDiscovery ) || empty( $modelDiscovery['methods'] ) ) {
				continue;
			}

			$classMethods = $modelDiscovery['methods'];
			$methods = [];

			while ( !empty( $classMethods ) ) {
				$method = array_shift( $classMethods );
				if ( in_array( $method['name'], $methodsAllowed[$relation['type'] ] ) ) {
					$methods[] = $method;
				}
			}

			$endpoint = $classNameOnly . '/_id_/' . $model;
			$endpoints[$endpoint] = [ 'endpoint' => $endpoint, 'methods' => $methods, 'description' => $modelDiscovery['description'] ];
		}

		return $endpoints;
	}

	/**
	* prints model description
	* @return object
	*/
	protected function discover_get() {
		return $this->jsonResponse( static::discover( false, $this->modelName ) );
	}

	/**
	* check if record exists, returns count of the collection in X-Count header
	* @param id record's index
	* @return 200 - found or 404 - not found
	*/
	public function head( $id = null ) {
		$modelInstance = Container::{$this->modelName}();

		if ( empty( $id ) && empty( $this->contextModel ) ) {
			header( 'X-Count: ' . $modelInstance::count() );
			return null;
		}

		if ( empty( $this->contextModel ) ) {
			$modelInstance->setById( $id );
		} else {
			if ( empty( $id ) ) {
				header( 'X-Count: ' . $modelInstance::countRelated( $this->contextModel ) );
				return null;
			}

			$modelInstance->setRelatedById( $this->contextModel, $id );
		}

		if ( empty( $modelInstance->getIndex() ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}

		header( 'X-Count: 1' );
		return null;
	}

	/**
	* get record
	* @param id record's index (all if empty)
	* @return object / array of objects
	*/
	public function get( $id = null ) {
		if ( empty( $id ) && empty( $this->contextModel ) ) {
			$modelName = $this->modelName;
			return $this->jsonResponse( $modelName::find( $this->query ) );
		}

		$modelInstance = Container::{$this->modelName}();
		if ( empty( $this->contextModel ) ) {
			$modelInstance->setById( $id );
		} else {
			if ( empty( $id ) ) {
				$modelName = $this->modelName;
				return $this->jsonResponse( $modelName::findRelated( $this->contextModel, $this->query ) );
			}
			$modelInstance->setRelatedById( $this->contextModel, $id );
		}

		if ( empty( $modelInstance->getIndex() ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}
		return $this->jsonResponse( $modelInstance );
	}

	/**
	* create record
	* @param json model properties
	* @return object created record
	*/
	public function post() {
		$modelInstance = Container::{$this->modelName}( $this->body );

		if ( !empty( $this->contextModel ) ) {
			$relation = $modelInstance->getRelation( $this->contextModel->getName() );
			if ( in_array( $relation['type'], ['1:n', '1>1'] ) ) {
				throw new RequestException( LAN_HTTP_BAD_REQUEST, 400 );
			}

			$fk = $relation['index'];
			$related = $relation['field'];
			$modelInstance->$fk = $this->contextModel->$related;
		}

		$modelInstance->save();
		return $this->jsonResponse( $modelInstance );
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
		$modelInstance->update( $this->body );
		$modelInstance->save();
		return $this->jsonResponse( $modelInstance );
	}

	/**
	* delete record
	* @param id
	* @return object deleted record
	*/
	public function delete( $id = null ) {
		if ( empty( $id ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}

		$modelInstance = Container::{$this->modelName}();

		if ( !empty( $this->contextModel ) ) {
			$modelInstance->setRelatedById( $this->contextModel, $id );
		} else {
			$modelInstance->setById( $id );
		}

		if ( empty( $modelInstance->getIndex() ) ) {
			throw new RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}

		$modelInstance->delete();
		return $this->jsonResponse( $modelInstance );
	}
}
