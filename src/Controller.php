<?php

declare(strict_types=1);

namespace Phresto;

use Phresto\Auth\AuthContext;
use Phresto\Exception\RequestException;
use Phresto\Interf\RequestContext as RequestContextInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use TypeError;

class Controller
{
    public const CLASSNAME = __CLASS__;

    /**
     * array describing how to map route parameters (aka path) to method parameters
     *
     * ['method_name' => [ 'param_name' => path index, ... ], ... ]
     *
     * ['all' => ...] will be used for all methods
     */
    protected $routeMapping = [];

    protected $queryDescription = [];

    /** @var AuthContext */
    protected $authContext;

    /** @var RequestContextInterface|null */
    protected $requestContext;

    protected static $type = 'controller';

    public function __construct(?RequestContextInterface $requestContext = null)
    {
        $this->requestContext = $requestContext ?: new RequestContext('get', [], [], [], '', [], new AuthContext());
        $this->authContext = $this->requestContext->authContext ?: new AuthContext($this->requestContext->headers);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'reqType':
                return $this->requestContext->method;
            case 'route':
            case 'headers':
            case 'body':
            case 'query':
            case 'bodyRaw':
                return $this->requestContext->{$name};
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name
            . ' in ' . $trace[0]['file']
            . ' on line ' . $trace[0]['line'],
            E_USER_NOTICE
        );

        return null;
    }

    protected function getRouteMapping($reqType)
    {
        if (isset($this->routeMapping[$reqType]) && is_array($this->routeMapping[$reqType])) {
            return $this->routeMapping[$reqType];
        }

        if (isset($this->routeMapping['all']) && is_array($this->routeMapping['all'])) {
            return $this->routeMapping['all'];
        }

        return [];
    }

    protected function getMethod()
    {
        $reflection = new ReflectionClass(static::CLASSNAME);
        $reqType = $this->requestContext->method;
        $route = &$this->requestContext->route;

        if (!empty($route[0]) && $reflection->hasMethod($route[0] . '_' . $reqType)) {
            $method = $reflection->getMethod($route[0] . '_' . $reqType);
            array_shift($route);
        } elseif ($reflection->hasMethod($reqType)) {
            $method = $reflection->getMethod($reqType);
        } else {
            throw new RequestException('Not found', 404);
        }

        $params = $method->getParameters();
        $args = [];
        $routeMapping = $this->getRouteMapping($method->name);
        foreach ($params as $param) {
            if (!empty($routeMapping) && isset($routeMapping[$param->name]) && isset($route[$routeMapping[$param->name]]) && $route[$routeMapping[$param->name]] != '') {
                $args[] = $this->getParamValue($param, $route[$routeMapping[$param->name]]);
            } elseif (isset($this->requestContext->body[$param->name])) {
                $args[] = $this->getParamValue($param, $this->requestContext->body[$param->name]);
            } elseif (isset($this->requestContext->query[$param->name])) {
                $args[] = $this->getParamValue($param, $this->requestContext->query[$param->name]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return [ $method, $args ];
    }

    public function exec()
    {
        list($method, $args) = $this->getMethod();

        if (!$this->auth($method->name, $args)) {
            throw new RequestException('Unauthorized', 401);
        }

        $method->setAccessible(true);

        try {
            return $method->invokeArgs($this, $args);
        } catch (TypeError $error) {
            error_log($error->getMessage());

            throw new RequestException('Bad request', 400);
        }
    }

    protected function getParamValue(ReflectionParameter $param, $value)
    {
        $type = static::getParamType($param);

        if ($type) {
            if (class_exists($type)) {
                $value = new $type($value);
            } elseif (class_exists('\\' . $type)) {
                $type = '\\' . $type;
                $value = new $type($value);
            } elseif ($type == 'boolean' && $value == 'false') {
                $value = false;
            } else {
                settype($value, $type);
            }
        }

        return $value;
    }

    /**
     * @param ReflectionParameter $parameter
     * @return string|null
     */
    protected static function getParamType(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return null;
    }

    protected function auth($methodName, $args = null)
    {
        return $this->authContext->hasAccess(static::CLASSNAME, $methodName);
    }

    /**
    * return OpenAPI fragment for this controller
    * @return object
    */
    protected function discover_get()
    {
        return Response::json(OpenApi::discoverClass(static::CLASSNAME));
    }

    protected static function getParameters($method, $className)
    {
        return $method->getParameters();
    }

    protected static function getRelatedEndpoints($className)
    {
        return [];
    }

    /**
     * Discover HTTP endpoints exposed by this class.
     * Returns an OpenAPI-style paths fragment.
     */
    public static function discover($all = false, $className = null, $getRelated = true)
    {

        $hasParam = function ($params, $field) {
            foreach ($params as $param) {
                if (is_object($param) && $param->name == $field) {
                    return true;
                }
            }

            return false;
        };

        $getDescription = function ($desc) {
            return trim(preg_replace(['$^[\s]*/\*\*$isU', '$[\s]*\*\/$isU', '$[\s]*\*[\s]*$isU'], ['', '', "\n"], $desc));
        };

        $reflection = new ReflectionClass(static::CLASSNAME);

        $requestTypes = [ 'get', 'post', 'patch', 'put', 'delete', 'head' ];
        $endpoints = [];

        $tmp = explode('\\', (isset($className)) ? $className : static::CLASSNAME);
        $classNameOnly = array_pop($tmp);

        $methodTypes = ($all) ? ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED : ReflectionMethod::IS_PUBLIC;

        $classMethods = $reflection->getMethods($methodTypes);
        $staticProps = $reflection->getDefaultProperties();
        $fields = $staticProps['routeMapping'];

        foreach ($classMethods as $method) {
            if (!in_array($method->name, $requestTypes)
                 && !(
                     strpos($method->name, '_') !== false
                && in_array(substr($method->name, strpos($method->name, '_') + 1), $requestTypes)
                 )
            ) {
                continue;
            }

            $describe = [ 'name' => $method->name, 'urlparams' => [], 'params' => [] ];
            $params = static::getParameters($method, $className);
            $ignore = [];

            $routeMapping = [];
            if (isset($fields[$method->name]) && is_array($fields[$method->name])) {
                $routeMapping = $fields[$method->name];
            } elseif (!empty($fields['all']) && is_array($fields['all'])) {
                $routeMapping = $fields['all'];
            };

            if (!empty($routeMapping)) {
                $values = array_values($routeMapping);
                if (isset($values[0]) && is_array($values[0])) {
                    $routeMapping = [];
                }
                asort($routeMapping);
                foreach ($routeMapping as $field => $index) {
                    if ($hasParam($params, $field)) {
                        $describe['urlparams'][$index] = $field;
                        $ignore[] = $field;
                    }
                }
            }

            $describe['urlparams'] = array_values($describe['urlparams']);

            foreach ($params as $param) {
                $paramWithType = (is_object($param)) ? [ 'name' => $param->name, 'type' => static::getParamType($param) ] : $param;
                if (in_array($paramWithType['name'], $ignore)) {
                    continue;
                }
                $describe['params'][] = $paramWithType;
            }

            $methodName = $method->name;
            if (strpos($method->name, '_') !== false) {
                list($methodName, $reqType) = explode('_', $methodName);
            }

            $endpoint = $classNameOnly;
            if (isset($reqType)) {
                $endpoint .= '/' . $methodName;
                $methodName = $reqType;
            }

            if (empty($endpoints[$endpoint])) {
                $endpoints[$endpoint] = ['endpoint' => $endpoint, 'methods' => [], 'description' => '', 'type' => static::$type];
                if (!isset($reqType)) {
                    $endpoints[$endpoint]['description'] = $getDescription($reflection->getDocComment());
                }
            }

            $describe['description'] = $getDescription($method->getDocComment());
            $describe['name'] = $methodName;
            unset($reqType);
            unset($methodName);

            $endpoints[$endpoint]['methods'][] = $describe;
        }

        if (!empty($className) && $getRelated) {
            $relatedEndpoints = static::getRelatedEndpoints($className);
            $endpoints = array_merge($endpoints, $relatedEndpoints);
        }

        return array_values($endpoints);
    }
}
