<?php

declare(strict_types=1);

define('PHRESTO_ROOT', __DIR__);

ob_start();

require_once 'vendor/autoload.php';

Phresto\Utils::registerAutoload();

$response = [
    'body' => json_encode([ 'status' => 500, 'message' => 'Internal server error' ]),
    'content-type' => 'application/json',
    'code' => 500,
];

try {
    $response = Phresto\Router::route();
} catch (Phresto\Exception\RequestException $e) {
    $response = Phresto\Router::routeException($e->getCode(), $e->getMessage(), $e->getTrace());
} catch (Exception $e) {
    $response = Phresto\Router::routeException(500, $e->getMessage(), $e->getTrace());
}

if (!empty($response['code'])) {
    http_response_code((int) $response['code']);
}
header('Content-Type: ' . $response['content-type']);
ob_clean();
echo $response['body'];

ob_end_flush();
