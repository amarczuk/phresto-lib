# Controllers

Phresto has three controller classes in the core framework: `Controller`, `ModelController`, and `CustomModelController`. They form a small hierarchy for handling HTTP endpoints.

## `Controller` (`src/Controller.php`)

The base class for all HTTP endpoint classes. A concrete controller lives in namespace `Phresto\Modules\Controller` and declares one or more public/protected verb methods (`get`, `post`, `patch`, `put`, `delete`, `head`, or `<name>_<verb>`).

### Constructor

```php
public function __construct( $reqType, $route, $body, $bodyRaw, $query, $headers )
```

The router creates controllers via the `Container` static factory, passing parsed request data. The constructor also resolves the current user:

```php
$this->currentUser = user::getCurrent( $this->headers );
```

### Method discovery and execution

`Controller::getMethod()`:

1. Looks for `<segment>_<verb>` when a route segment is present.
2. Falls back to a method named exactly like the verb.
3. Binds route, body, and query params using `$routeMapping`.

`Controller::exec()`:

1. Calls `getMethod()`.
2. Calls `auth($methodName, $args)`; on failure throws 401.
3. Invokes the method with bound arguments.
4. Catches `TypeError` and converts it to a 400 response.

### Auth hook

```php
protected function auth( $methodName, $args = null ) {
    return $this->currentUser->hasAccess( static::CLASSNAME, $methodName );
}
```

Controllers can override this to implement custom authorisation logic. The bundled `user` controller restricts users to their own record unless they are a superuser.

### Discovery / introspection

`Controller::discover()` uses reflection to enumerate endpoints, their HTTP verbs, URL parameters, body/query parameters, and docblock descriptions. This powers the Explorer UI and the admin permissions editor.

## `ModelController` (`src/ModelController.php`)

`ModelController` provides a generic REST API for any `Model` subclass. It is instantiated by the router when the first URL segment matches a model class name but no controller class exists.

### Constructor

```php
public function __construct( $modelName, $reqType, $route, $body, $bodyRaw, $query, $headers, Model $contextModel = null )
```

`$modelName` is the fully-qualified model class. `$contextModel` is set when handling a nested relation URL such as `/user/5/token`.

### Auth

Authorisation is checked against the model class and method name:

```php
return $this->currentUser->hasAccess( $this->modelName, $methodName );
```

### REST methods

| Method | Behaviour |
|--------|-----------|
| `head($id = null)` | Return count or existence via `X-Count`. |
| `get($id = null)` | Read one by id, list all, or list related records. |
| `post()` | Create a new record. |
| `patch($id = null)` | Partial update (requires id). |
| `put($id = null)` | Upsert. |
| `delete($id = null)` | Delete by id. |

### Nested routes

`hasNextRoute()` checks whether path segments remain after consuming `routeMapping`. If so, `escalate()`:

1. Loads the parent model by id.
2. Verifies the next segment is a related model.
3. Creates a new `ModelController` for the related model with the parent as context.

## `CustomModelController` (`src/CustomModelController.php`)

A thin subclass that lets a controller class be tied to exactly one model. The subclass declares:

```php
const MODELCLASS = 'Phresto\\Modules\\Model\\user';
```

It overrides the constructor so the router can instantiate it the same way as a regular controller, while still acting as a `ModelController` for the declared model. This is used by the bundled `user` controller to add custom endpoints (`authenticate_post`, `register_post`, `auth_get`, etc.) on top of the standard model REST API.

## Endpoint naming conventions

A controller or model named `product` yields URLs such as:

| URL | Handler |
|-----|---------|
| `/product` GET | `ModelController::get()` → list |
| `/product/42` GET | `ModelController::get(42)` → one record |
| `/product` POST | `ModelController::post()` → create |
| `/product/42` PATCH | `ModelController::patch(42)` → update |
| `/product/42` DELETE | `ModelController::delete(42)` → delete |
| `/product/discover` GET | `ModelController::discover_get()` → endpoint metadata |

If a custom controller `product` exists in `Phresto\Modules\Controller`, it takes precedence and can define arbitrary verb methods.
