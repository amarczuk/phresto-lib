# Routing and Request Lifecycle

Phresto maps HTTP requests to PHP classes and methods by convention. This document describes how the `Router` class resolves a URL and how `Controller` executes the chosen handler.

## Entry point and rewrite rules

The `template/.htaccess` file routes all non-static traffic to `bootstrap.php` with the original path in `$_GET['PHRESTOREQUESTPATH']`:

```apache
RewriteRule ^ bootstrap.php?PHRESTOREQUESTPATH=%1 [QSA,L]
```

`bootstrap.php` then calls `Phresto\Router::route()` and emits the response.

## Router internals (`src/Router.php`)

`Router::route()` performs the following steps:

1. **Read HTTP metadata**
   - `$_SERVER['REQUEST_METHOD']` → lower-cased verb (`get`, `post`, etc.)
   - `$_GET['PHRESTOREQUESTPATH']` → split into path segments
   - Query string minus `PHRESTOREQUESTPATH`
   - Request body parsed from JSON, form data, or `$_POST`
   - Request headers via `getRequestHeaders()`

2. **CORS handling**
   - If `config/app.ini` contains `cors`, preflight `OPTIONS` requests return the configured headers.
   - Otherwise the configured origin headers are added to all responses.

3. **Resolve the first segment**
   - Empty path:
     - Try `app.mainmodule` controller (`Phresto\Modules\Controller\<mainmodule>`).
     - Fall back to `static/index.html`.
     - Otherwise 404.
   - Non-empty path:
     - Controller exists? `Phresto\Modules\Controller\<class>`.
     - Otherwise model exists? `Phresto\Modules\Model\<class>` → wrap with `ModelController`.
     - Otherwise 404.

4. **Execute and render**
   - Calls `$instance->exec()` and returns the result.
   - `bootstrap.php` sends JSON content type when an array is returned, otherwise echoes strings.

## Controller method resolution (`src/Controller.php`)

`Controller::getMethod()` decides which PHP method to invoke:

1. If the next route segment is non-empty and a method named `<segment>_<verb>` exists, it is chosen and the segment is consumed.
2. Otherwise, if a method named exactly like the HTTP verb exists, it is chosen.
3. Otherwise a 404 `RequestException` is thrown.

Example mappings for controller `user`:

| URL | HTTP method | Controller method |
|-----|-------------|-------------------|
| `/user` | GET | `get()` |
| `/user/123` | GET | `get(123)` via `routeMapping` |
| `/user/authenticate` | POST | `authenticate_post(...)` |
| `/user/auth/google` | GET | `auth_get('google')` via `routeMapping` |

## Parameter binding

`Controller::getMethod()` reflects the chosen method and builds an argument list for each parameter:

1. If `routeMapping` names this parameter and a matching path segment exists, use the segment.
2. Else if the body contains the parameter name, use the body value.
3. Else if the query string contains the parameter name, use the query value.
4. Else if the parameter has a default value, use it.
5. Else pass `null`.

`getParamValue()` then coerces the value to the parameter's declared type. Classes are instantiated via `new $type($value)`. `'false'` strings are converted to boolean `false`. Everything else uses `settype()`.

## Route mapping syntax

Controllers declare a protected `$routeMapping` array. The special key `all` applies to every method.

```php
protected $routeMapping = [
    'all' => [ 'id' => 0 ],          // first segment → $id for all methods
    'auth_get' => [ 'service' => 0 ], // first segment → $service only for auth_get
];
```

The value is the zero-based index inside the remaining route array after the controller name.

## ModelController routing (`src/ModelController.php`)

`ModelController` extends `Controller` and exposes a generic REST interface over any `Model` subclass.

Default mapping:

```php
protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];
```

Method mapping:

| HTTP method | ModelController method | Behaviour |
|-------------|------------------------|-----------|
| `HEAD` | `head($id)` | Existence check; returns `X-Count` header. |
| `GET` | `get($id)` | Read one or list. |
| `POST` | `post()` | Create record. |
| `PATCH` | `patch($id)` | Partial update. |
| `PUT` | `put($id)` | Upsert. |
| `DELETE` | `delete($id)` | Delete record. |

If additional segments remain after the id, `ModelController::escalate()` treats the next segment as a related model name and forwards to a new `ModelController` for that related model, scoped by the parent record. This is how URLs like `/user/5/token` work.

## Error handling

`Router::routeException()` builds the response for thrown exceptions:

- Sets the HTTP response code.
- Includes a stack trace only when `app.env = dev`.
- Returns JSON if the request asked for JSON, otherwise renders the `error` view.

All endpoint classes should throw `Phresto\Exception\RequestException` with an HTTP status code for client errors.
