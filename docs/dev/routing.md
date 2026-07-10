# Routing

> **v2 baseline** — Routing is unchanged, but every successful response is now JSON and discovery is served as a cached OpenAPI 3.0 spec at `/openapi`.

## URL-to-class mapping

Given a request:

```
GET /product/5/reviews
```

`Router` decides the first segment is a model because `modules/product/model/product.php` exists. It instantiates `Phresto\ModelController` with `$modelName = 'Phresto\\Modules\\Model\\product'` and a `RequestContext`.

If the first segment matched a custom controller file (`modules/<x>/controller/<x>.php`), it would instantiate `Phresto\Modules\Controller\x` with a `RequestContext`.

## Method resolution

Inside a controller, `getMethod()` looks for a method named after the next route segment plus the request verb:

```
GET /report/sales
```

```php
class report extends Controller {
    public function sales_get() { ... }
}
```

If no segment remains, the bare verb method is used:

```
GET /report
```

```php
public function get() { ... }
```

## Parameter binding

`getParamValue()` fills method parameters from three sources, in order:

1. **URL segments** via `$routeMapping`.
2. **JSON body** for `post`, `put`, `patch`.
3. **Query string** for `get`, `head`, `delete`.

`routeMapping` maps parameter names to URL segment indices:

```php
protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];
```

This means `id` comes from `$route[0]` for every method (`all`).

## Special routes

### `GET /openapi`

Returns the cached OpenAPI 3.0 specification as JSON:

```json
{
  "openapi": "3.0.0",
  "info": { ... },
  "paths": { ... }
}
```

### `GET /openapi?format=yaml`

Returns the same specification as YAML.

### `GET /<controller>/discover`

Still works, but now returns the OpenAPI fragment for that controller via `OpenApi::discoverClass()` instead of the old custom JSON discovery format.

## Middleware

`Router` builds a `RequestContext` from the HTTP request (method, route, headers, body, query) and a base `AuthContext`, then applies middleware before invoking the controller:

1. Global middlewares registered with `Router::addMiddleware()`.
2. Per-class middlewares declared via a controller/model's static `$middlewares` property.

Each middleware receives the current `RequestContext` and returns a (possibly modified) `RequestContext`. Authentication middleware updates the wrapped `AuthContext` via `$context->withAuthContext(...)`. Route escalation uses `$context->withRoute(...)` to pass a trimmed route to the child controller. The final context is passed to the controller constructor. This keeps authentication out of controllers and models and makes middleware reusable for logging, validation, rate limiting, etc.

## Error responses

The bootstrap catches `RequestException` and generic `Exception` and returns JSON with the corresponding HTTP code:

```json
{
  "status": 404,
  "message": "Not found"
}
```

All errors are JSON; there is no HTML error page anymore.
