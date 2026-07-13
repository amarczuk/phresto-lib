# Controllers

> **v2 baseline** — Controllers return JSON through `Response::json()`. The old `View` HTML layer and `LAN_HTTP_*` language constants are gone. `discover_get()` now returns an OpenAPI fragment.

## Base `Controller`

All custom controllers extend `Phresto\Controller`.

```php
<?php
namespace Phresto\Modules\Controller;
use Phresto\Controller;
use Phresto\Response;

class report extends Controller {
    const CLASSNAME = __CLASS__;

    public function sales_get() {
        return Response::json( [ 'total' => 12345 ] );
    }
}
```

## Returning data

The canonical return is a response array from `Response`:

```php
return Response::json( $data, 200 );
return Response::json( [ 'error' => 'Bad input' ], 400 );
```

`Response::json()` produces:

```php
[
    'body'         => json_encode( $data, JSON_PRETTY_PRINT ),
    'content-type' => 'application/json',
    'code'         => 200,
]
```

Plain strings, arrays, and objects are still accepted by `bootstrap.php`, but `Response::json()` is the intended path.

## Parameter binding

Same as before:

```php
public function search_get( string $q, int $limit = 10 ) {
    // GET /report/search?q=foo&limit=5
}
```

URL mapping:

```php
protected $routeMapping = [
    'search_get' => [ 'q' => 0, 'limit' => 1 ],
];

public function search_get( string $q, int $limit ) {
    // GET /report/search/foo/5
}
```

## Auth

Controllers receive a `RequestContext` built by Router middleware instead of a loaded user model. The `RequestContext` carries the parsed request data plus an `AuthContext`; the default `auth()` checks permissions carried in that auth context:

```php
protected function auth( $methodName, $args = null ) {
    return $this->authContext->hasAccess( static::CLASSNAME, $methodName );
}
```

The `Controller` constructor stores only the `RequestContext` and its `AuthContext`; there is no per-request copying of large request bodies or headers. New code should read request data directly from `$this->requestContext` (e.g. `$this->requestContext->body`, `$this->requestContext->headers`). For backward compatibility, legacy property access such as `$this->body`, `$this->query`, `$this->headers`, `$this->route`, `$this->bodyRaw`, `$this->reqType`, and `$this->authContext` is provided through `__get()`.

Override for public endpoints:

```php
protected function auth( $methodName, $args = null ) {
    if ( $methodName === 'public_get' ) return true;
    return parent::auth( $methodName, $args );
}
```

## Middleware

The preferred pattern in v2 is to register middleware globally on the router in `bootstrap.php`:

```php
Phresto\Router::addMiddleware( new \Phresto\Modules\Middleware\auth() );
```

Middleware receives and returns a `RequestContext` so it can resolve users, add logging, rate-limiting, validation, etc. Per-class middleware can still be declared via a static `$middlewares` property, but the default user module no longer does so; the global `auth` middleware is sufficient.

## `ModelController`

Framework-owned. Maps HTTP verbs to CRUD. It now uses `Response::json()` and plain text error messages (no language constants). The constructor accepts a `RequestContext`:

```php
public function __construct(
    $modelName,
    ?RequestContext $requestContext = null,
    ?Model $contextModel = null
)
```

## `CustomModelController`

User extension point for model REST endpoints:

```php
<?php
namespace Phresto\Modules\Controller;
use Phresto\CustomModelController;
use Phresto\Response;

class product extends CustomModelController {
    const CLASSNAME = __CLASS__;
    const MODELCLASS = 'Phresto\\Modules\\Model\\product';

    public function stats_get() {
        return Response::json( [ 'count' => 42 ] );
    }
}
```

## Discovery

`Controller::discover_get()` now returns an OpenAPI path-item fragment via `OpenApi::discoverClass(static::CLASSNAME)`. The full spec is available at `GET /openapi`.
