<?php

define( 'PHRESTO_ROOT', __DIR__ );

session_start();
ob_start();

require_once 'vendor/autoload.php';
require_once 'kernel/class/Utils.php';

Phresto\Utils::registerAutoload();

try {
	$response = Phresto\Router::route();
} catch( Phresto\Exception\RequestException $e ) {
	$response = Phresto\Router::routeException( $e->getCode(), $e->getMessage(), $e->getTrace() );
} catch( Exception $e ) {
	$response = Phresto\Router::routeException( 500, $e->getMessage(), $e->getTrace() );
}

if ( is_array( $response ) ) {
	header( 'Content-Type: ' . $response['content-type'] );
	ob_clean();
	echo $response['body'];
} else {
	echo $response;
}

ob_end_flush();