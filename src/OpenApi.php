<?php

declare(strict_types=1);

namespace Phresto;

/**
 * OpenAPI 3.0 spec generator for Phresto v2.
 *
 * v2 replaces the old reflection-based Explorer UI with a cached machine-readable
 * API specification. The spec is rebuilt automatically in dev mode and can be
 * refreshed manually via `vendor/bin/phresto -d`.
 */
class OpenApi
{
    public const SPEC_FILE = 'openapi.json';

    protected static $phpToOpenApi = [
        'int' => [ 'type' => 'integer', 'format' => 'int64' ],
        'integer' => [ 'type' => 'integer', 'format' => 'int64' ],
        'string' => [ 'type' => 'string' ],
        'boolean' => [ 'type' => 'boolean' ],
        'bool' => [ 'type' => 'boolean' ],
        'float' => [ 'type' => 'number', 'format' => 'float' ],
        'double' => [ 'type' => 'number', 'format' => 'double' ],
        'DateTime' => [ 'type' => 'string', 'format' => 'date-time' ],
    ];

    /**
     * Build and cache the full API specification.
     */
    public static function buildSpec()
    {
        $modules = Config::getConfig('modules');
        $paths = [];
        $schemas = [];

        $controllers = [];
        $models = [];

        foreach ($modules as $modname => $module) {
            if (!empty($module['Controller']) && is_array($module['Controller'])) {
                foreach ($module['Controller'] as $file) {
                    $name = str_replace('.php', '', $file);
                    if (isset($controllers[$name])) {
                        continue;
                    }
                    $controllers[$name] = true;

                    $class = '\\Phresto\\Modules\\Controller\\' . $name;
                    if (!class_exists($class)) {
                        continue;
                    }

                    $fragment = static::discoverClass($class);
                    $paths = array_merge($paths, $fragment['paths']);
                    if (!empty($fragment['schemas'])) {
                        $schemas = array_merge($schemas, $fragment['schemas']);
                    }
                }
            }

            if (!empty($module['Model']) && is_array($module['Model'])) {
                foreach ($module['Model'] as $file) {
                    $name = str_replace('.php', '', $file);
                    if (isset($models[$name])) {
                        continue;
                    }
                    $models[$name] = true;

                    $class = '\\Phresto\\Modules\\Model\\' . $name;
                    if (!class_exists($class)) {
                        continue;
                    }

                    $fragment = static::discoverModel($class);
                    $paths = array_merge($paths, $fragment['paths']);
                    if (!empty($fragment['schemas'])) {
                        $schemas = array_merge($schemas, $fragment['schemas']);
                    }
                }
            }
        }

        $app = Config::getConfig('app');
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => !empty($app['app']['title']) ? $app['app']['title'] : 'Phresto API',
                'version' => !empty($app['app']['version']) ? $app['app']['version'] : '1.0.0',
            ],
            'paths' => static::sortPaths($paths),
            'components' => [
                'schemas' => $schemas,
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'JWT token issued by /user/authenticate. Send as `Authorization: Bearer <token>`.',
                    ],
                ],
            ],
            'security' => [
                [ 'bearerAuth' => [] ],
            ],
        ];

        static::saveSpec($spec);

        return $spec;
    }

    /**
     * Return the cached spec, rebuilding it in dev mode if missing/stale.
     */
    public static function getSpec()
    {
        $path = PHRESTO_ROOT . '/config/' . self::SPEC_FILE;
        $app = Config::getConfig('app');

        if (!empty($app['app']['env']) && $app['app']['env'] == 'dev') {
            return static::buildSpec();
        }

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        return static::buildSpec();
    }

    public static function saveSpec($spec)
    {
        $path = PHRESTO_ROOT . '/config/' . self::SPEC_FILE;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode($spec, JSON_PRETTY_PRINT));
    }

    /**
     * Discover a controller and return OpenAPI paths + schemas fragments.
     */
    public static function discoverClass($className)
    {
        $reflection = new \ReflectionClass($className);
        $tmp = explode('\\', $className);
        $name = array_pop($tmp);

        $verbs = [ 'get', 'post', 'patch', 'put', 'delete', 'head' ];
        $paths = [];
        $schemas = [];

        $staticProps = $reflection->getDefaultProperties();
        $routeMapping = !empty($staticProps['routeMapping']) ? $staticProps['routeMapping'] : [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            $info = static::parseMethodName($method->name);
            if (empty($info)) {
                continue;
            }

            // Skip inherited REST verbs from ModelController/Controller unless
            // the controller class has no model backing it (plain controller).
            $declaringClass = $method->getDeclaringClass()->name;
            if ($declaringClass !== $className
                && in_array($info['method'], [ 'get', 'post', 'patch', 'put', 'delete', 'head' ], true)
                && is_subclass_of($className, 'Phresto\\ModelController')
            ) {
                continue;
            }

            $path = '/' . $name;
            if (!empty($info['segment'])) {
                $path .= '/' . $info['segment'];
            }

            $methodName = $info['method'];
            $httpVerb = $info['verb'];

            $params = $method->getParameters();
            $mapping = static::getRouteMappingForMethod($routeMapping, $method->name);

            $operation = [
                'operationId' => $method->name,
                'summary' => static::cleanDoc($method->getDocComment()),
                'parameters' => [],
                'responses' => [
                    '200' => [ 'description' => 'OK' ],
                ],
            ];

            $paramNames = array_map(fn ($p) => $p->name, $params);
            $used = [];
            foreach ($mapping as $field => $index) {
                if (is_array($index)) {
                    continue;
                }
                if (!in_array($field, $paramNames, true)) {
                    continue;
                }
                $operation['parameters'][] = [
                    'name' => $field,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [ 'type' => 'string' ],
                ];
                $used[] = $field;
            }

            if (in_array($httpVerb, [ 'post', 'put', 'patch' ])) {
                $bodyProps = [];
                foreach ($params as $param) {
                    if (in_array($param->name, $used)) {
                        continue;
                    }
                    $bodyProps[$param->name] = static::phpTypeToSchema(static::getParamType($param));
                }
                if (!empty($bodyProps)) {
                    $operation['requestBody'] = [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [ 'type' => 'object', 'properties' => $bodyProps ],
                            ],
                        ],
                    ];
                }
            } else {
                foreach ($params as $param) {
                    if (in_array($param->name, $used)) {
                        continue;
                    }
                    $operation['parameters'][] = [
                        'name' => $param->name,
                        'in' => 'query',
                        'schema' => static::phpTypeToSchema(static::getParamType($param)),
                    ];
                }
            }

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            $paths[$path][$httpVerb] = $operation;
        }

        return [ 'paths' => $paths, 'schemas' => $schemas ];
    }

    /**
     * Discover a model and return OpenAPI paths + schemas fragments.
     */
    public static function discoverModel($className)
    {
        $reflection = new \ReflectionClass($className);
        $tmp = explode('\\', $className);
        $name = array_pop($tmp);

        $staticProps = $reflection->getDefaultProperties();
        $fields = !empty($staticProps['_fields']) ? $staticProps['_fields'] : [];
        $calculated = !empty($staticProps['_calculated_fields']) ? $staticProps['_calculated_fields'] : [];
        $relations = !empty($staticProps['_relations']) ? $staticProps['_relations'] : [];

        $schemaName = ucfirst($name);
        $properties = [];
        $required = [];

        foreach ($fields as $field => $type) {
            $properties[$field] = static::phpTypeToSchema(is_array($type) ? $type['type'] : $type);
        }

        foreach ($calculated as $field => $type) {
            $properties[$field] = static::phpTypeToSchema($type);
        }

        $schemas = [
            $schemaName => [
                'type' => 'object',
                'properties' => $properties,
            ],
        ];

        $paths = [
            '/' . $name => [
                'get' => static::makeOperation('List ' . $name, [ '200' => [ 'description' => 'List of ' . $name, 'content' => [ 'application/json' => [ 'schema' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/' . $schemaName ] ] ] ] ] ]),
                'head' => static::makeOperation('Count ' . $name, [ '200' => [ 'description' => 'Count returned in X-Count header' ] ]),
                'post' => static::makeOperation('Create ' . $name, [ '201' => [ 'description' => 'Created ' . $name ] ], $schemaName),
            ],
            '/' . $name . '/{id}' => [
                'get' => static::makeOperation('Read ' . $name, [ '200' => [ 'description' => 'A ' . $name ] ], null, [ 'id' ]),
                'head' => static::makeOperation('Check ' . $name . ' exists', [ '200' => [ 'description' => 'Exists' ] ], null, [ 'id' ]),
                'patch' => static::makeOperation('Update ' . $name, [ '200' => [ 'description' => 'Updated ' . $name ] ], $schemaName, [ 'id' ]),
                'put' => static::makeOperation('Upsert ' . $name, [ '200' => [ 'description' => 'Upserted ' . $name ] ], $schemaName, [ 'id' ]),
                'delete' => static::makeOperation('Delete ' . $name, [ '200' => [ 'description' => 'Deleted ' . $name ] ], null, [ 'id' ]),
            ],
        ];

        foreach ($relations as $relName => $relation) {
            $relSchema = ucfirst($relation['model']);
            $allowed = static::allowedRelationMethods($relation['type']);
            $relPath = '/' . $name . '/{id}/' . $relName;

            if (in_array('get', $allowed)) {
                $paths[$relPath]['get'] = static::makeOperation(
                    'List related ' . $relName,
                    [ '200' => [ 'description' => 'List of ' . $relName, 'content' => [ 'application/json' => [ 'schema' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/' . $relSchema ] ] ] ] ] ],
                    null,
                    [ 'id' ]
                );
            }
            if (in_array('post', $allowed)) {
                $paths[$relPath]['post'] = static::makeOperation(
                    'Create related ' . $relName,
                    [ '201' => [ 'description' => 'Created ' . $relName ] ],
                    $relSchema,
                    [ 'id' ]
                );
            }
        }

        return [ 'paths' => $paths, 'schemas' => $schemas ];
    }

    protected static function makeOperation($summary, $responses, $schemaName = null, $pathParams = [])
    {
        $operation = [
            'summary' => $summary,
            'parameters' => [],
            'responses' => $responses,
        ];

        foreach ($pathParams as $param) {
            $operation['parameters'][] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => [ 'type' => 'string' ],
            ];
        }

        if (!empty($schemaName)) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [ '$ref' => '#/components/schemas/' . $schemaName ],
                    ],
                ],
            ];
        }

        return $operation;
    }

    protected static function parseMethodName($name)
    {
        $verbs = [ 'get', 'post', 'patch', 'put', 'delete', 'head' ];

        if (in_array($name, $verbs)) {
            return [ 'verb' => $name, 'segment' => '', 'method' => $name ];
        }

        if (strpos($name, '_') !== false) {
            list($segment, $verb) = explode('_', $name);
            if (in_array($verb, $verbs)) {
                return [ 'verb' => $verb, 'segment' => $segment, 'method' => $name ];
            }
        }

        return null;
    }

    protected static function getRouteMappingForMethod($routeMapping, $methodName)
    {
        if (isset($routeMapping[$methodName]) && is_array($routeMapping[$methodName])) {
            return $routeMapping[$methodName];
        }
        if (isset($routeMapping['all']) && is_array($routeMapping['all'])) {
            return $routeMapping['all'];
        }

        return [];
    }

    protected static function allowedRelationMethods($type)
    {
        $map = [
            '1:n' => [ 'head', 'get', 'post', 'delete' ],
            'n:n' => [ 'head', 'get', 'post', 'delete' ],
            '1:1' => [ 'head', 'get', 'post', 'delete' ],
            '1>1' => [ 'head', 'get', 'post', 'delete' ],
            '1<1' => [ 'head', 'get' ],
            'n:1' => [ 'head', 'get' ],
        ];

        return isset($map[$type]) ? $map[$type] : [ 'head', 'get' ];
    }

    protected static function phpTypeToSchema($type)
    {
        if (empty($type)) {
            return [ 'type' => 'string' ];
        }
        if (isset(self::$phpToOpenApi[$type])) {
            return self::$phpToOpenApi[$type];
        }

        return [ 'type' => 'string' ];
    }

    protected static function cleanDoc($comment)
    {
        if (empty($comment)) {
            return '';
        }

        return trim(preg_replace('/^\s*\/\*\*|\s*\*\/|\s*\*\s?/m', '', $comment));
    }

    protected static function sortPaths(array $paths): array
    {
        ksort($paths);

        return $paths;
    }

    protected static function getParamType(\ReflectionParameter $parameter)
    {
        $type = $parameter->getType();
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        return null;
    }
}
