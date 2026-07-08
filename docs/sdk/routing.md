# Routing and Endpoints

> **v2 baseline** — Routing is unchanged, but every response is JSON and the OpenAPI 3.0 spec is available at `/openapi`.

Phresto does not use a central route file. URLs map to classes and methods by naming convention.

## URL format

```
https://example.com/<controller-or-model>[/<segment>...][?<query>]
```

Examples:

```
GET    /product              # list products
GET    /product/12           # read product 12
POST   /product              # create product
PATCH  /product/12           # update product 12
DELETE /product/12           # delete product 12
GET    /product/12/reviews   # list reviews of product 12
POST   /product/12/reviews  # create review for product 12
GET    /report/sales         # custom controller method
```

## Resolution order

1. If a controller class `Phresto\Modules\Controller\&lt;name&gt;` exists, use it.
2. If a model class `Phresto\Modules\Model\&lt;name&gt;` exists, wrap it in `ModelController`.
3. Otherwise return 404.

The empty root path (`/`) no longer serves `static/index.html`; it returns a JSON 404 unless you add a custom controller.

## HTTP verbs

Controllers and models expose methods named after HTTP verbs:

| Verb | Method name |
|------|-------------|
| GET | `get(...)` |
| POST | `post(...)` |
| PATCH | `patch(...)` |
| PUT | `put(...)` |
| DELETE | `delete(...)` |
| HEAD | `head(...)` |

When a URL has an extra segment, the method name becomes `<segment>_<verb>`:

```
GET /user/current   →  current_get()
GET /report/sales   →  sales_get()
```

## Route mapping for URL parameters

Declare `$routeMapping` in the controller to bind path positions to method parameters:

```php
protected $routeMapping = [
    'all'      => [ 'id' => 0 ],          // applies to every method
    'sales_get' => [ 'year' => 0 ],      // only for sales_get
];
```

With `all => [ 'id' => 0 ]`, the URL `/product/12` maps `12` to `$id` in `get($id)`.

## Parameter sources

When calling a controller method, parameters are filled in this order:

1. URL segment from `routeMapping`
2. Request body field
3. Query string field
4. Method default value
5. `null`

The framework coerces values to declared PHP types (e.g., `int`, `string`, `DateTime`).

## Nested resources

For a model with a `1:n` relation, child resources are accessed through the parent:

```
GET /user/5/token
POST /user/5/token
DELETE /user/5/token/3
```

The framework loads user 5, verifies the `token` relation, then forwards the rest of the path to the `token` model controller scoped by that user.

## CORS

Enable cross-origin requests in `config/app.ini`:

```ini
[app]
cors=*
```

Use `*` to allow any origin, or set a specific origin domain. Preflight `OPTIONS` requests are handled automatically.

## Discovery and the OpenAPI spec

The old per-endpoint `GET /<name>/discover` format has been replaced by a single OpenAPI 3.0 specification:

```
GET /openapi            # JSON spec
GET /openapi?format=yaml # YAML spec
GET /product/discover   # OpenAPI fragment for the product endpoints
```

Use the spec with Swagger UI, Postman, or any OpenAPI-compatible tool to explore and test the API.
