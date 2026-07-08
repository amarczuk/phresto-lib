# Architecture

> **v2 baseline** — HTML templating and the Admin/Explorer UI are gone. Responses are JSON-only (YAML only for the OpenAPI spec). Discovery is now handled by `src/OpenApi.php` and served at `/openapi`.

## Request lifecycle

1. **Web server** rewrites everything to `template/bootstrap.php` (see [`.htaccess`](../../template/.htaccess)).
2. **`bootstrap.php`** registers the autoloader, calls `Router::route()`, and emits a JSON response envelope.
3. **`Router::route()`** parses the URL, resolves the controller, runs it, and returns a response array.
4. **`Controller`** binds parameters, checks permissions, executes the method, and returns `Response::json(...)`.
5. **`bootstrap.php`** sets `http_response_code()`, sends `Content-Type: application/json`, and writes the body.

```
HTTP request
    -> .htaccess -> bootstrap.php
        -> Router::route()
            -> Controller/ModelController
                -> Response::json()
    -> JSON response
```

## Main classes

### `Router` (`src/Router.php`)

`Router::route()` is the only entry point. It:

- Parses the request method, path segments, query string, body, and headers.
- Loads `config/modules.ini`.
- Determines if the first URL segment is a model (`modules/<x>/model/`) or a custom controller (`modules/<x>/controller/`).
- Instantiates the right controller through `Container`.
- Runs `exec()` and returns the response array.

Special routes:

- `GET /openapi` — returns the cached OpenAPI 3.0 spec as JSON.
- `GET /openapi?format=yaml` — returns the same spec as YAML.

### `Controller` (`src/Controller.php`)

Base class for all custom controllers. Responsibilities:

- Stores request context: `$reqType`, `$route`, `$body`, `$query`, `$headers`.
- Resolves `$currentUser` from the `prsid` cookie or `Authorization` header.
- Uses reflection to find the method matching the URL (`getMethod()`).
- Binds method parameters from URL segments, body JSON, or query string.
- Calls `auth($methodName, $args)` before executing a method.
- Returns `Response::json($data)`.

The `discover_get()` method now returns a fragment of the OpenAPI spec for the current controller via `OpenApi::discoverClass()`.

### `ModelController` (`src/ModelController.php`)

Framework-owned controller that exposes REST endpoints for any model. It maps HTTP verbs to CRUD methods:

| HTTP verb | Method | Purpose |
|-----------|--------|---------|
| HEAD | `head($id)` | existence / count |
| GET | `get($id)` | read one, list, or read related |
| POST | `post()` | create |
| PATCH | `patch($id)` | partial update |
| PUT | `put($id)` | upsert |
| DELETE | `delete($id)` | delete |

When the URL escalates into a related model (`/product/5/reviews`), `ModelController` loads the parent model, validates the relation, and creates a new `ModelController` for the child.

### `CustomModelController` (`src/CustomModelController.php`)

User extension point. Extending it keeps all generic model REST endpoints while allowing custom methods like `stats_get()`.

### `Response` (`src/Response.php`)

Replaces the old `View` layer. Two public methods:

```php
Response::json( $data, $code = 200 );   // returns response array with application/json
Response::yaml( $data, $code = 200 );   // returns response array with application/yaml
```

When `config/app.ini` has `debug=on`, captured `ob_*` output is appended under a `_debug` key in JSON responses. In production, debug output is discarded.

### `OpenApi` (`src/OpenApi.php`)

Generates the API specification:

```php
OpenApi::buildSpec();   // reflect everything and write config/openapi.json
OpenApi::getSpec();     // return cached spec (rebuilds in dev mode)
OpenApi::discoverClass( $className );
OpenApi::discoverModel( $modelName );
```

The spec is rebuilt automatically in development (`env=dev`) when `Utils::registerAutoload()` runs. In production it is only rebuilt by running `vendor/bin/phresto -d` or `vendor/bin/phresto -m`.

### `Utils` (`src/Utils.php`)

- `updateModules()` scans `modules/` and writes `config/modules.ini`.
- `registerAutoload()` registers the module autoloader and, in dev mode, rebuilds `config/openapi.json`.
- `autoload()` resolves `Phresto\Modules\...` class names using the registry.

### `Model` / `MySQLModel`

See [`models.md`](models.md) and [`database.md`](database.md).

## Removed in v2

- `src/View.php` and the HTML templating pipeline.
- `template/view/`, `template/lang/`, `template/bower.json`, `template/.bowerrc`, `template/static/index.html`.
- `template/modules/admin/` and `template/modules/explorer/`.
- `template/modules/user/class/` (social OAuth adapters), `template/modules/user/view/`, `template/modules/user/config/social.ini`.
- `lusitanian/oauth` Composer dependency.
