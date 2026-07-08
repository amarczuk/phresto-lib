# Container

`Phresto\Container` is a minimal reflection-based factory used throughout the framework to instantiate classes. It also has a simple optional caching layer.

## API (`src/Container.php`)

```php
Container::ClassName( $arg1, $arg2 );
Container::'Phresto\\Some\\Class'( $arg1, $arg2 );
```

Calls are static. If the name contains no backslash, `Phresto\` is prepended.

## Factory behaviour

```php
public static function __callStatic( $name, $arguments ) {
    if ( mb_strpos( $name, '\\' ) === false ) {
        $name = __NAMESPACE__ . '\\' . $name;
    }

    $cacheName = self::_getCacheName( $name, $arguments );
    if ( self::$cacheOn && self::$objectCache[$cacheName] ) {
        return self::$objectCache[$cacheName];
    }

    $reflection_class = new \ReflectionClass( $name );
    $objectInstance = $reflection_class->newInstanceArgs( $arguments );
    if ( self::$cacheOn ) {
        self::$objectCache[$cacheName] = $objectInstance;
    }

    return $objectInstance;
}
```

The container uses `newInstanceArgs()` to pass constructor arguments. This means classes instantiated through the container must have constructors compatible with the arguments supplied by the caller.

## Caching

Set `Container::$cacheOn = true` to enable instance caching. The cache key is `md5( name + serialize(arguments) )`.

```php
Container::_reset();          // clear the cache
Container::_register( $name, $value ); // manually register a value
```

## Usage in the framework

- `Router::route()` instantiates controllers via `Container::Phresto\Modules\Controller\Foo(...)`.
- `Router::route()` instantiates `ModelController` via `Container::ModelController(...)`.
- `MySQLConnector::connect()` instantiates `mysqli` via `Container::mysqli(...)`.
- `MySQLModel` instantiates model instances via `Container::$modelClass($row, false)`.

## Limitations

- The container does not support named constructor injection or interface resolution.
- It does not manage lifecycle beyond instance creation.
- Constructor argument lists must match exactly; mismatches produce reflection errors.
