# Routing

`Router` is the single entry point for all HTTP traffic. It maps URL segments to `Phresto\Modules\Controller\...` or `Phresto\ModelController` instances, builds a `RequestContext`, runs middleware, and dispatches to the resolved controller.

## URL-to-class mapping

Given a request:

```
GET /product/5/reviews
```

`Router` checks `config/modules.ini` and the file system:

1. If `modules/<x>/controller/<x>.php` exists, it instantiates `Phresto\Modules\Controller\x`.
2. If `modules/<x>/model/<x>.php` exists, it instantiates `Phresto\ModelController` with `$modelName = 'Phresto\\Modules\\Model\\product'`.
3. Otherwise it returns 404.

The first matching segment wins. Controller files take precedence over model files.

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

The matched method must be `public` or `protected`. Private methods are never routed.

## Parameter binding

`getParamValue()` fills method parameters from three sources, in order:

1. **URL segments** via `$routeMapping`.
2. **JSON body** for `post`, `put`, `patch`.
3. **Query string** for `get`, `head`, `delete`.

`routeMapping` maps parameter names to URL segment indices:

```php
protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];
```

This means `id` comes from the first remaining route segment for every method (`all`). Per-method mappings override `all`:

```php
protected $routeMapping = [
    'all'       => [ 'id' => 0 ],
    'sales_get' => [ 'year' => 0 ],
];
```

Only parameters actually declared on the method are published as path parameters in the OpenAPI spec.

## Special routes

### `GET /openapi`

Returns the cached OpenAPI 3.0 spec as JSON.

### `GET /openapi?format=yaml`

Returns the same spec as YAML.

### `GET /swagger`

If the `swagger-api/swagger-ui` Composer package is installed and the `swagger` module is enabled, this serves an interactive Swagger UI that loads `/openapi`.

## Middleware

`Router::route()` builds a `RequestContext` from the HTTP request (method, trimmed route, headers, body, raw body, query) and a base `AuthContext`, then runs the global middleware stack. The preferred pattern is a single global middleware registered in `bootstrap.php`:

```php
Phresto\Router::addMiddleware( new \Phresto\Modules\Middleware\auth() );
```

Each middleware receives the current `RequestContext` and returns a (possibly modified) `RequestContext`. Authentication middleware updates the wrapped `AuthContext` via `$context->withAuthContext(...)`. Route escalation uses `$context->withRoute(...)` to pass a trimmed route to a child controller. The final context is passed to the controller constructor.

Per-class middleware can still be declared via a static `$middlewares` property, but global middleware is preferred because it keeps authentication, logging, rate limiting, and validation out of individual controllers.

## Error responses

`bootstrap.php` catches `RequestException` and generic `Exception` and returns JSON with the corresponding HTTP code:

```json
{
  "status": 404,
  "message": "Not found"
}
```

All responses are JSON; there is no HTML error page.
