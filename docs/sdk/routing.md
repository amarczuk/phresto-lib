# Routing and Endpoints

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

1. If the path is empty, serve `static/index.html` or the configured `mainmodule` controller.
2. If a controller class `Phresto\Modules\Controller\&lt;name&gt;` exists, use it.
3. If a model class `Phresto\Modules\Model\&lt;name&gt;` exists, wrap it in `ModelController`.
4. Otherwise return 404.

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

## Discovery endpoint

Every controller and model answers `GET /<name>/discover` with a JSON schema describing its endpoints and parameters. This is used by the built-in Explorer tool.
