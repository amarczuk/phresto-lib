<?php

declare(strict_types=1);

namespace Phresto;

use Phresto\Auth\AuthContext;
use Phresto\Exception\RequestException;
use Phresto\Interf\RequestContext as RequestContextInterface;
use ReflectionClass;
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
}
