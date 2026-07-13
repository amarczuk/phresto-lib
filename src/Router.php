<?php

declare(strict_types=1);

namespace Phresto;

use Phresto\Auth\AuthContext;
use Phresto\Auth\Jwt;
use Phresto\Exception\RequestException;
use Phresto\Interf\Middleware;
use Phresto\Interf\RequestContext as RequestContextInterface;
use ReflectionClass;

class Router
{
    /** @var Middleware[] */
    protected static $middlewares = [];

    /**
     * Register a global middleware applied to every request.
     *
     * @param Middleware $middleware
     */
    public static function addMiddleware(Middleware $middleware)
    {
        self::$middlewares[] = $middleware;
    }

    /**
     * Return the list of globally registered middlewares.
     *
     * @return Middleware[]
     */
    public static function getMiddlewares(): array
    {
        return self::$middlewares;
    }

    public static function route()
    {
        $reqType = mb_strtolower($_SERVER['REQUEST_METHOD']);
        $route = explode('/', trim($_GET['PHRESTOREQUESTPATH'], '/'));
        $class = array_shift($route);
        $query = $_GET;
        unset($query['PHRESTOREQUESTPATH']);
        $bodyRaw = '';
        $body = [];
        $headers = static::getRequestHeaders();
        $viewConf = Config::getConfig('app');

        $origin = (is_array($viewConf['app']) && array_key_exists('cors', $viewConf['app'])) ? $viewConf['app']['cors'] : null;
        if ($origin == '*' && !empty($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
        }

        if (!empty($origin) && $reqType == 'options') {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Expose-Headers: *');
            header('Access-Control-Allow-Methods: GET, PUT, PATCH, DELETE, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, Referer, User-Agent');
            header('Access-Control-Max-Age: 1728000');
            header('Content-Length: 0');
            header('Content-Type: text/plain');
            die();
        }

        if (!empty($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: *');
            header('Access-Control-Expose-Headers: *');
        }

        if ($reqType != 'get' && $reqType != 'delete') {
            $bodyRaw = @file_get_contents('php://input');

            if (mb_strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $body = json_decode($bodyRaw, true);
            }

            if (empty($body)) {
                $body = [];
                parse_str($bodyRaw, $body);
            }

            if (empty($body) && !empty($_POST)) {
                $body = $_POST;
                $bodyRaw = http_build_query($_POST);
            }
        }

        $requestContext = static::buildRequestContext($headers, $body, $bodyRaw, $query, $route);

        if (empty($class)) {
            if (is_array($viewConf['app'])
                 && !empty($viewConf['app']['mainmodule'])
                 && class_exists('Phresto\\Modules\\Controller\\' . $viewConf['app']['mainmodule'])) {
                $controllerClass = 'Phresto\\Modules\\Controller\\' . $viewConf['app']['mainmodule'];
                $requestContext = static::applyMiddlewares($requestContext, $controllerClass);
                $instance = Container::{$controllerClass}($requestContext);
            } else {
                return Response::json([ 'status' => 404, 'message' => 'Not found' ], 404);
            }
        } elseif ($class === 'openapi') {
            $spec = OpenApi::getSpec();
            if (isset($query['format']) && $query['format'] === 'yaml') {
                return Response::yaml($spec);
            }

            return Response::json($spec);
        } else {
            if (class_exists('Phresto\\Modules\\Controller\\' . $class)) {
                $controllerClass = 'Phresto\\Modules\\Controller\\' . $class;
                $requestContext = static::applyMiddlewares($requestContext, $controllerClass);
                $instance = Container::{$controllerClass}($requestContext);
            } elseif (class_exists('Phresto\\Modules\\Model\\' . $class)) {
                $modelClass = 'Phresto\\Modules\\Model\\' . $class;
                $requestContext = static::applyMiddlewares($requestContext, $modelClass);
                $instance = Container::ModelController($modelClass, $requestContext);
            } else {
                throw new RequestException('Not found', 404);
            }
        }

        return $instance->exec();
    }

    protected static function getRequestHeaders()
    {
        if (function_exists('apache_request_headers')) {
            return apache_request_headers();
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) != 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }

        return $headers;
    }

    protected static function buildRequestContext(
        array $headers,
        array $body = [],
        string $bodyRaw = '',
        array $query = [],
        array $route = []
    ): RequestContextInterface {
        return new RequestContext(
            mb_strtolower($_SERVER['REQUEST_METHOD'] ?? 'get'),
            $route,
            $headers,
            $body,
            $bodyRaw,
            $query,
            new AuthContext($headers, Jwt::extractToken($headers))
        );
    }

    /**
     * Apply global middlewares plus any middlewares declared on a class.
     *
     * A controller or model can declare per-class middleware via a static
     * $middlewares property:
     *
     *     protected static $middlewares = [\Phresto\Modules\Middleware\auth::class];
     *
     * @param RequestContextInterface $context
     * @param string                  $className
     * @return RequestContextInterface
     */
    protected static function applyMiddlewares(RequestContextInterface $context, string $className): RequestContextInterface
    {
        $middlewares = self::$middlewares;

        if (class_exists($className)) {
            $reflection = new ReflectionClass($className);
            $staticProps = $reflection->getDefaultProperties();
            if (!empty($staticProps['middlewares']) && is_array($staticProps['middlewares'])) {
                foreach ($staticProps['middlewares'] as $middlewareClass) {
                    if (is_string($middlewareClass) && class_exists($middlewareClass)) {
                        $middlewares[] = new $middlewareClass();
                    } elseif (is_object($middlewareClass) && $middlewareClass instanceof Middleware) {
                        $middlewares[] = $middlewareClass;
                    }
                }
            }
        }

        foreach ($middlewares as $middleware) {
            $context = $middleware->process($context);
        }

        return $context;
    }

    public static function routeException($ex = 500, $message = '', $trace = '')
    {
        $app = Config::getConfig('app');
        if (empty($app['app']['env']) || $app['app']['env'] != 'dev') {
            $trace = '';
        }

        $resp = [
            'status' => $ex,
            'message' => $message,
            'trace' => $trace,
        ];

        return Response::json($resp, (int)$ex);
    }
}
