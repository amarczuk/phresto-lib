# Architecture

Phresto is a JSON-only REST framework. Discovery is generated as a cached OpenAPI 3.0 spec by `src/OpenApi.php` and served at `/openapi`.

## Request lifecycle

1. **Web server** rewrites everything to `template/bootstrap.php` (see [`.htaccess`](../../template/.htaccess)).
2. **`bootstrap.php`** registers the autoloader, calls `Router::route()`, and emits a JSON response envelope.
3. **`Router::route()`** parses the URL, builds a `RequestContext`, runs middleware, resolves the controller, runs it, and returns a response array.
4. **`Controller`** binds parameters, checks permissions, executes the method, and returns `Response::json(...)`.
5. **`bootstrap.php`** sets `http_response_code()`, sends `Content-Type: application/json`, and writes the body.

```
HTTP request
    -> .htaccess -> bootstrap.php
        -> Router::route()
            -> middleware -> Controller/ModelController
                -> Response::json()
    -> JSON response
```

## Main classes

### `Router` (`src/Router.php`)

`Router::route()` is the only entry point. It:

- Parses the request method, path segments, query string, body, raw body, and headers.
- Loads `config/modules.ini`.
- Determines if the first URL segment is a model (`modules/<x>/model/`) or a custom controller (`modules/<x>/controller/`).
- Builds a `RequestContext` with a base `AuthContext`.
- Runs the global middleware stack.
- Instantiates the right controller through `Container`.
- Runs `exec()` and returns the response array.

Special routes:

- `GET /openapi` — returns the cached OpenAPI 3.0 spec as JSON.
- `GET /openapi?format=yaml` — returns the same spec as YAML.
- `GET /swagger` — serves Swagger UI when the `swagger` module and the `swagger-api/swagger-ui` package are installed.

### `Controller` (`src/Controller.php`)

Base class for all custom controllers. Responsibilities:

- Receives a `RequestContext` from `Router` middleware; the context contains parsed request data and an `AuthContext` resolved from the `Authorization` header.
- Stores the `RequestContext` and its `AuthContext`. Legacy property access (`$this->body`, `$this->query`, `$this->headers`, `$this->route`, `$this->bodyRaw`, `$this->reqType`) is provided through `__get()` for backward compatibility.
- Uses reflection to find the method matching the URL (`getMethod()`).
- Binds method parameters from URL segments, body JSON, or query string.
- Calls `auth($methodName, $args)` before executing a method.
- Returns `Response::json($data)`.

The controller does not contain any discovery endpoints; the whole API description is at `/openapi`.

### `ModelController` (`src/ModelController.php`)

Framework-owned controller that exposes REST endpoints for any model. It maps HTTP verbs to CRUD methods:

| HTTP verb | Method | Purpose |
|-----------|--------|---------|
| HEAD | `head($id = null)` | count records or check existence |
| GET | `get($id = null)` | read one, list, or read related |
| POST | `post()` | create |
| PATCH | `patch($id = null)` | partial update |
| PUT | `put($id = null)` | upsert |
| DELETE | `delete($id = null)` | delete |

When the URL escalates into a related model (`/product/5/reviews`), `ModelController` loads the parent model, validates the relation, and creates a new `ModelController` for the child with a trimmed `RequestContext`.

### `CustomModelController` (`src/CustomModelController.php`)

User extension point. Extending it keeps all generic model REST endpoints while allowing custom methods like `stats_get()`.

### `Response` (`src/Response.php`)

JSON and YAML response helpers:

```php
Response::json( $data, $code = 200 );   // response array with application/json
Response::yaml( $data, $code = 200 );   // response array with application/yaml
```

When `config/app.ini` has `debug=on`, captured `ob_*` output is appended under a `_debug` key in JSON responses. In production, debug output is discarded.

Controllers may also return a plain response array with `body` and `content-type` keys to serve non-JSON content, as the Swagger UI controller does.

### `OpenApi` (`src/OpenApi.php`)

Generates the API specification:

```php
OpenApi::buildSpec();   // reflect everything and write config/openapi.json
OpenApi::getSpec();     // return cached spec (rebuilds in dev mode)
OpenApi::discoverClass( $className );
OpenApi::discoverModel( $modelName );
```

The spec includes security metadata (`bearerAuth` JWT security scheme), sorts paths alphabetically, and documents `HEAD` collection endpoints as record counts. It is rebuilt automatically in development (`env=dev`) when `Utils::registerAutoload()` runs. In production it is only rebuilt by running `vendor/bin/phresto -o` or `vendor/bin/phresto -m`.

`GET /swagger` serves Swagger UI when the `swagger` module and the `swagger-api/swagger-ui` Composer package are installed.

### `Utils` (`src/Utils.php`)

- `updateModules()` scans `modules/` and writes `config/modules.ini`.
- `registerAutoload()` registers the module autoloader and, in dev mode, rebuilds `config/openapi.json`.
- `autoload()` resolves `Phresto\Modules\...` class names using the registry.

### `Model` / `MySQLModel`

See [`models.md`](models.md) and [`database.md`](database.md).
