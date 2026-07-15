# Creating Controllers

When a model's generic CRUD endpoints are not enough, create a controller class to add custom endpoints and logic.

## Basic controller

Create `modules/example/controller/report.php`:

```php
<?php
namespace Phresto\Modules\Controller;
use Phresto\Controller;
use Phresto\Response;

class report extends Controller {
    const CLASSNAME = __CLASS__;

    /**
     * GET /report/sales
     */
    public function sales_get() {
        return Response::json([
            'total' => 12345,
        ]);
    }
}
```

The method name format is `<segment>_<verb>`. The first remaining URL segment must be `sales` and the HTTP method must be `GET`.

## Simple verb method

```php
public function get() {
    return Response::json(['hello' => 'world']);
}
```

This handles `GET /report`.

## Parameters

Controller methods receive parameters from the URL, request body, or query string:

```php
public function search_get( string $q, int $limit = 10 ) {
    // GET /report/search?q=foo&limit=5
}
```

URL parameters come from `$routeMapping`:

```php
protected $routeMapping = [
    'search_get' => [ 'q' => 0, 'limit' => 1 ],
];

public function search_get( string $q, int $limit ) {
    // GET /report/search/foo/5
}
```

## Returning responses

Always return `Response::json($data)`:

```php
return Response::json( $data );
return Response::json( $data, 201 );
return Response::json( ['error' => 'Bad request'], 400 );
```

## Request data

The controller stores a `RequestContext` in `$this->requestContext`. Read request data from it directly:

```php
$this->requestContext->method;   // HTTP method
$this->requestContext->route;    // remaining URL segments
$this->requestContext->headers;   // request headers
$this->requestContext->body;      // decoded JSON body
$this->requestContext->query;     // query string array
$this->requestContext->bodyRaw;   // raw request body
$this->requestContext->authContext; // resolved auth context
```

For backward compatibility, legacy properties such as `$this->body`, `$this->query`, `$this->headers`, `$this->route`, `$this->bodyRaw`, `$this->reqType`, and `$this->authContext` are mapped through `__get()`.

## Custom model controller

To add endpoints around a specific model while keeping the generic REST methods, extend `CustomModelController`:

```php
<?php
namespace Phresto\Modules\Controller;
use Phresto\CustomModelController;
use Phresto\Response;

class product extends CustomModelController {
    const CLASSNAME = __CLASS__;
    const MODELCLASS = 'Phresto\\Modules\\Model\\product';

    public function stats_get() {
        return Response::json( ['count' => 42] );
    }
}
```

This preserves `GET /product`, `POST /product`, etc., and lets you add methods like `stats_get()`.

## Auth checks

Controllers inherit:

```php
protected function auth( $methodName, $args = null ) {
    return $this->authContext->hasAccess( static::CLASSNAME, $methodName );
}
```

Override it for custom rules:

```php
protected function auth( $methodName, $args = null ) {
    // allow public access to search_get
    if ( $methodName === 'search_get' ) return true;

    return parent::auth( $methodName, $args );
}
```

## Discovery

The full API description is available as an OpenAPI 3.0 spec at `GET /openapi` and as interactive Swagger UI at `GET /swagger` when enabled.
