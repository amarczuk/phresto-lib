<?php

namespace Phresto;

use Phresto\View;

class Controller {

	const CLASSNAME = __CLASS__;

	protected $routeMapping = [];
	protected $queryDescription = [];

	protected $headers = [];
	protected $body = [];
	protected $bodyRaw = '';
	protected $query = [];
	protected $route = [];
	protected $reqType = 'get';

	public function __construct( $reqType, $route, $body, $bodyRaw, $query, $headers ) {
		$this->reqType = $reqType;
		$this->route = $route;
		$this->headers = $headers;
		$this->body = $body;
		$this->query = $query;
		$this->bodyRaw = $bodyRaw;

		if ( !$this->auth() ) {
			throw new Exception\RequestException( LAN_HTTP_UNAUTHORIZED, 401 );
		}
	}

	protected function getRouteMapping( $reqType ) {
		if ( isset( $this->routeMapping[$reqType] ) && is_array( $this->routeMapping[$reqType] ) ) {
			return $this->routeMapping[$reqType];
		}

		if ( isset( $this->routeMapping['all'] ) && is_array( $this->routeMapping['all'] ) ) {
			return $this->routeMapping['all'];
		}

		return [];
	}

	protected function getMethod() {
		$reflection = new \ReflectionClass( static::CLASSNAME );

		if ( !empty($this->route[0]) && $reflection->hasMethod( $this->route[0] . '_' . $this->reqType ) ) {
			$method = $reflection->getMethod( $this->route[0] . '_' . $this->reqType );
			array_shift( $this->route );
		} else if ( $reflection->hasMethod( $this->reqType ) ) {
			$method = $reflection->getMethod( $this->reqType );
		} else {
			throw new Exception\RequestException( LAN_HTTP_NOT_FOUND, 404 );
		}

		$params = $method->getParameters();
		$args = [];
		$routeMapping = $this->getRouteMapping( $method->name );
		foreach ( $params as $param ) {
			if ( !empty( $routeMapping ) && isset( $routeMapping[$param->name] ) && isset( $this->route[$routeMapping[$param->name]] ) && $this->route[$routeMapping[$param->name]] != '') {
				$args[] = $this->getParamValue( $param, $this->route[$routeMapping[$param->name]] );
			} else if ( isset( $this->body[$param->name] ) ) {
				$args[] = $this->getParamValue( $param, $this->body[$param->name] );
			} else if ( isset( $this->query[$param->name] ) ) {
				$args[] = $this->getParamValue( $param, $this->query[$param->name] );
			} else  if ( $param->isDefaultValueAvailable() ) {
				$args[] = $param->getDefaultValue();
			} else {
				$args[] = null;
			}
		}

		return [ $method, $args ];
	}

	public function exec() {
		list( $method, $args ) = $this->getMethod();
		$method->setAccessible( true );
		return $method->invokeArgs( $this, $args );
	}

	protected function getParamValue(\ReflectionParameter $param, $value) {
		$type = static::getParamType( $param );

		if ( $type ) {
			if ( class_exists( $type ) ) {
				$value = new $type( $value );
			} else if ( class_exists( '\\' . $type ) ) {
				$type = '\\' . $type;
				$value = new $type( $value );
			} else if ( $type == 'boolean' && $value == 'false' ) {
				$value = false;
			} else {
				settype( $value, $type );
			}
		}

		return $value;
	}

	/**
	 * @param ReflectionParameter $parameter
	 * @return string|null
	 */
	protected static function getParamType( \ReflectionParameter $parameter ) {
	    $export = \ReflectionParameter::export(
	        [
	            $parameter->getDeclaringClass()->name,
	            $parameter->getDeclaringFunction()->name
	        ],
	        $parameter->name,
	        true
	    );

	    return preg_match('/[>] ([\\\\A-z]+) /', $export, $matches) ? $matches[1] : null;
	}

	protected function auth() {
		return true;
	}

	protected function jsonResponse( $var ) {
		return View::jsonResponse( $var );
	}

	/**
	* prints controller description
	* @return object
	*/
	protected function discover_get() {
		return $this->jsonResponse( static::discover() );
	}

	protected static function getParameters( $method, $className ) {
		return $method->getParameters();
	}

	protected static function getRelatedEndpoints( $className ) {
		return [];
	}

	public static function discover( $className = null, $getRelated = true ) {

		$hasParam = function( $params, $field ) {
			foreach ($params as $param) {
				if ( is_object($param) && $param->name == $field) return true;
			}

			return false;
		};

		$getDescription = function( $desc ) {
			return trim( preg_replace ( ['$^[\s]*/\*\*$isU', '$[\s]*\*\/$isU', '$[\s]*\*[\s]*$isU'], ['', '', "\n"], $desc ) );
		};

		$reflection = new \ReflectionClass( static::CLASSNAME );

		$requestTypes = [ 'get', 'post', 'patch', 'put', 'delete', 'head' ];
		$endpoints = [];
		
		$tmp = explode( '\\', ( isset( $className ) ) ? $className : static::CLASSNAME );
		$classNameOnly = array_pop( $tmp );

		$classMethods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
		$staticProps = $reflection->getDefaultProperties(); 
		$fields = $staticProps['routeMapping'];

		foreach ( $classMethods as $method ) {
			if ( !in_array( $method->name, $requestTypes ) && 
				 !( strpos( $method->name, '_' ) !== false && 
				 	in_array( substr( $method->name, strpos( $method->name, '_' ) + 1 ), $requestTypes )
				  )
				) continue;

			$describe = [ 'name' => $method->name, 'urlparams' => [], 'params' => [] ];
			$params = static::getParameters( $method, $className );
			$ignore = [];

			$routeMapping = [];
			if ( !empty( $fields[$method->name] ) && is_array( $fields[$method->name] ) ) {
				$routeMapping = $fields[$method->name];
			} else if ( !empty( $fields['all'] ) && is_array( $fields['all'] ) ) {
				$routeMapping = $fields['all'];
			};

			if ( !empty( $routeMapping ) ) {
				$values = array_values( $routeMapping );
				if ( isset( $values[0] ) && is_array( $values[0] ) ) $routeMapping = [];
				asort( $routeMapping );
				foreach ( $routeMapping as $field => $index ) {
					if ( $hasParam($params, $field) ) {
						$describe['urlparams'][$index] = $field;
						$ignore[] = $field;
					}
				}
			}

			$describe['urlparams'] = array_values( $describe['urlparams'] );

			foreach ( $params as $param ) {
				$paramWithType = ( is_object($param) ) ? [ 'name' => $param->name, 'type' => static::getParamType($param) ] : $param;
				if ( in_array( $paramWithType['name'], $ignore ) ) continue;
				$describe['params'][] = $paramWithType;
			}

			$methodName = $method->name;
			if ( strpos( $method->name, '_' ) !== false ) {
				list($methodName, $reqType) = explode('_', $methodName);
			}

			$endpoint = $classNameOnly;
			if ( isset( $reqType ) ) {
				$endpoint .= '/' . $methodName;
				$methodName = $reqType;
			}

			if ( empty( $endpoints[$endpoint] ) ) {
				$endpoints[$endpoint] = ['endpoint' => $endpoint, 'methods' => [], 'description' => ''];
				if ( !isset( $reqType ) ) {
					$endpoints[$endpoint]['description'] = $getDescription( $reflection->getDocComment() );
				}
			}

			$describe['description'] = $getDescription( $method->getDocComment() );
			$describe['name'] = $methodName;
			unset($reqType);
			unset($methodName);

			$endpoints[$endpoint]['methods'][] = $describe;
		}

		if ( !empty( $className ) && $getRelated ) {
			$relatedEndpoints = static::getRelatedEndpoints( $className );
			$endpoints = array_merge( $endpoints, $relatedEndpoints );
		}

		return array_values( $endpoints );
	}

}