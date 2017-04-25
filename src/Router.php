<?php

namespace Phresto;

use Phresto\View;
use Phresto\Config;

class Router {

	public static function route() {
		$reqType = mb_strtolower( $_SERVER['REQUEST_METHOD'] );
        $route = explode( '/', trim( $_GET['PHRESTOREQUESTPATH'], '/' ) );
        $class = array_shift( $route );
        $query = $_GET;
        unset( $query['PHRESTOREQUESTPATH'] );
        $bodyRaw = '';
        $body = [];
        $headers = [];

		if ( $reqType != 'get' && $reqType != 'delete' ) {
	        $bodyRaw = @file_get_contents('php://input');

	        if ( mb_strpos( $_SERVER["CONTENT_TYPE"], 'application/json' ) !== false ) {
	        	$body = json_decode( $bodyRaw, true );
	        }

	        if ( empty( $body ) ) {
	        	$body = [];
	        	parse_str( $bodyRaw, $body );
	        }

	        if ( empty( $body ) && !empty( $_POST ) ) {
	        	$body = $_POST;
	        	$bodyRaw = http_build_query( $_POST );
	        }
	    }

		if ( empty( $class ) ) {
			$viewConf = Config::getConfig( 'app' );
        	if ( is_array( $viewConf['app'] ) &&
        		 !empty( $viewConf['app']['mainmodule'] ) &&
        		 class_exists( 'Phresto\\Modules\\Controller\\' . $viewConf['app']['mainmodule'] ) ) {
        		$instance = Container::{'Phresto\\Modules\\Controller\\' . $viewConf['app']['mainmodule']}( $reqType, $route, $body, $bodyRaw, $query, $headers );
        	} else if ( file_exists( 'static/index.html' ) ) {
        		return file_get_contents( 'static/index.html' );
        	} else {
        		throw new Exception\RequestException( LAN_HTTP_NOT_FOUND, 404 );
        	}
        } else {
		    if ( class_exists( 'Phresto\\Modules\\Controller\\' . $class ) ) {
		    	$instance = Container::{'Phresto\\Modules\\Controller\\' . $class}( $reqType, $route, $body, $bodyRaw, $query, $headers );
		    } else if ( class_exists( 'Phresto\\Modules\\Model\\' . $class ) ) {
		    	$instance = Container::ModelController( 'Phresto\\Modules\\Model\\' . $class, $reqType, $route, $body, $bodyRaw, $query, $headers );
		    } else {
		    	throw new Exception\RequestException( LAN_HTTP_NOT_FOUND, 404 );
		    }
		}

	    return $instance->exec();
	}

	public static function routeException( $ex = 500, $message = '', $trace = '' ) {
		http_response_code((int)$ex);
		
		$app = Config::getConfig( 'app' );
		if ( empty( $app['app']['env'] ) || $app['app']['env'] != 'dev' ) {
			$trace = '';
		}

		$resp = [
			'status' => $ex,
			'message' => $message,
			'trace' => $trace
		];
		if ( !empty( $_SERVER["CONTENT_TYPE"] ) && mb_strpos( $_SERVER["CONTENT_TYPE"], 'application/json' ) !== false ) {
			return View::jsonResponse( $resp );
		}

		$view = View::getView('error');
		$view->add('error', $resp);
		
		return $view->get();
	}
}
