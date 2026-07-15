# Controllers

All custom controllers extend `Phresto\Controller`. Controllers return data through `Response::json()`, which produces a response array that `bootstrap.php` turns into a JSON response.

## Base `Controller`

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

Controllers that serve non-JSON content can return a custom response array with `body` and `content-type` keys. The Swagger UI controller in the `swagger` module does this to serve HTML and static assets.

## Request data

The controller constructor receives a `RequestContext`. The context is the single source of truth for request data:

| Property | Meaning |
|----------|---------|
| `$this->requestContext->method` | HTTP method, lowercased |
| `$this->requestContext->route` | Remaining URL segments after the controller name |
| `$this->requestContext->headers` | Request headers |
| `$this->requestContext->body` | Decoded JSON body |
| `$this->requestContext->bodyRaw` | Raw request body |
| `$this->requestContext->query` | Query string array |
| `$this->requestContext->authContext` | `AuthContext` resolved by middleware |

For backward compatibility, legacy property access is provided through `__get()`:

```php
$this->reqType;  // $this->requestContext->method
$this->route;     // $this->requestContext->route
$this->headers;   // $this->requestContext->headers
$this->body;      // $this->requestContext->body
$this->query;     // $this->requestContext->query
$this->bodyRaw;   // $this->requestContext->bodyRaw
$this->authContext; // $this->requestContext->authContext
```

New code should read directly from `$this->requestContext`.

## Parameter binding

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

Only parameters actually declared on the method are published as path parameters in the OpenAPI spec.

## Auth

The default `auth()` checks permissions carried in the request's `AuthContext`:

```php
protected function auth( $methodName, $args = null ) {
    return $this->authContext->hasAccess( static::CLASSNAME, $methodName );
}
```

Override for public endpoints:

```php
protected function auth( $methodName, $args = null ) {
    if ( $methodName === 'public_get' ) return true;
    return parent::auth( $methodName, $args );
}
```

## Middleware

The preferred pattern is to register middleware globally on the router in `bootstrap.php`:

```php
Phresto\Router::addMiddleware( new \Phresto\Modules\Middleware\auth() );
```

Middleware receives and returns a `RequestContext`, so it can resolve users, add logging, rate-limiting, validation, etc. Per-class middleware can still be declared via a static `$middlewares` property, but the global `auth` middleware is normally sufficient.

## `ModelController`

Framework-owned controller that maps HTTP verbs to CRUD:

| HTTP verb | Method | Purpose |
|-----------|--------|---------|
| HEAD | `head($id = null)` | Count records or check existence |
| GET | `get($id = null)` | Read one, list, or read related |
| POST | `post()` | Create |
| PATCH | `patch($id = null)` | Partial update |
| PUT | `put($id = null)` | Upsert |
| DELETE | `delete($id = null)` | Delete |

The constructor accepts a model name, an optional `RequestContext`, and an optional parent `Model` for related-resource escalation.

### Related-model creation rules

When the URL escalates into a related model (`/product/5/review`), `ModelController::post()` creates a child only when the relation type allows it:

- `1:n` and `1>1` — rejected with HTTP 400. The FK is on the parent/context side, so a nested POST from the child route cannot set it.
- `n:1`, `1:1`, `1<1`, `n:n` — allowed. For `n:n` the model must implement junction-table persistence itself.

## `CustomModelController`

User extension point for model REST endpoints. Extending it keeps all generic model REST methods and lets you add custom ones:

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
