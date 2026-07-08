# Phresto v2 — Framework Review & Refactor Direction

## Overall assessment

Phresto is a cohesive, convention-driven micro-framework. Its strongest idea is that **models become REST endpoints automatically** and controllers are discovered through reflection rather than configured by hand. That core mechanism is still viable.

The parts that age badly are the ancillary infrastructure: frontend assets, custom discovery UI, ad-hoc crypto, and the assumption that MySQL is the only real backend. Your refactoring plan aligns well with keeping the routing/model contract and replacing the rest.

---

## Strengths

### 1. Convention-over-configuration routing is simple and effective

[`Router::route()`](src/Router.php#L10) turns URL segments into class names and HTTP verbs into method names with almost no boilerplate. For a small API this is genuinely productive: creating a model immediately gives you:

- `GET /product`
- `POST /product`
- `PATCH /product/12`
- etc.

The nested-resource routing in [`ModelController::escalate()`](src/ModelController.php#L67) is a nice touch — `/user/5/token` is handled naturally through relation metadata.

### 2. Reflection-based parameter binding is pragmatic

[`Controller::getMethod()`](src/Controller.php#L55) and [`getParamValue()`](src/Controller.php#L103) bind path, body, and query parameters to method arguments and coerce them to declared types. It removes a lot of boilerplate that frameworks usually require for request DTOs.

### 3. Model metadata design is declarative and readable

[`Model.php`](src/Model.php#L28) uses static arrays for fields, calculated fields, defaults, relations, and indexes. This keeps the data shape close to the code and makes it easy to generate both SQL and API schemas from the same source — which is exactly what you would want to feed an OpenAPI generator later.

### 4. MySQL integration is surprisingly capable for a small framework

[`MySQLModel`](src/MySQLModel.php#L191) has a working query builder with:

- `where`
- `order`
- `limit` / `offset`
- `fields`
- nested `and` / `or` conditions
- related-model joins

[`getCreationCode()`](src/MySQLModel.php#L360) and [`getRelationCode()`](src/MySQLModel.php#L433) can introspect and migrate tables automatically. For rapid prototyping this is valuable.

### 5. Module autoloading is straightforward

[`Utils::autoload()`](src/Utils.php#L7) plus the generated `config/modules.ini` registry makes it easy to drop modules into `modules/` and have them discovered. The dev-mode auto-refresh in [`Utils::registerAutoload()`](src/Utils.php#L51) is a nice DX feature.

### 6. Auth/permission model is complete

The `user` module provides tokens, profiles, and a permission matrix out of the box. [`user::hasAccess()`](template/modules/user/model/user.php#L67) checks route/method wildcards, which is enough for many real apps.

---

## Weaknesses

### 1. Security: SQL binding is not real prepared-statement parameterization

[`MySQLConnector::bind()`](src/MySQLConnector.php#L73) does string interpolation after escaping. It works, but it is **not** a prepared statement. The regex-based placeholder replacement is fragile; the special handling for `$` (`&us-dollar;`) is a tell that this was built around replacement-string edge cases rather than a proper DB API.

> For a modern refactor you should switch to PDO / mysqli prepared statements or bind variables natively.

### 2. Security: password hashing and token encryption are outdated

- [`passHash()`](template/modules/user/model/user.php#L86) uses `md5(md5($password))`. This is not acceptable today; use `password_hash()` / `password_verify()`.
- [`token::encrypt()`](template/modules/user/model/token.php#L53) uses AES-256-CTR with a **hardcoded IV** (`'abcdefghijk12345'`). That destroys the security of the mode. Either use a random IV/nonce and store it with the ciphertext, or move to stateless JWTs signed with a secret key and stop encrypting tokens entirely.

### 3. Error handling relies on global language constants

Controllers throw `RequestException` with messages like `LAN_HTTP_NOT_FOUND`. Those constants are loaded from `lang/errors_*.php`. This couples routing to translation plumbing and makes the framework harder to embed or test.

> A refactor should use typed exceptions with machine-readable codes and keep human messages optional.

### 4. `Container` is magical and not type-safe

[`Container::__callStatic()`](src/Container.php#L25) lets you write `Container::Foo($args)`, but IDEs cannot autocomplete it, and constructor signatures must match exactly. There is also a latent bug in [`_register()`](src/Container.php#L17):

```php
if ( self::$objectCache[$name] )  // emits warning when key absent
```

It should be:

```php
if ( isset(self::$objectCache[$name]) )
```

A modern DI container (or even a simple factory map) would be more maintainable.

### 5. Module namespace collisions

All module controllers live in `Phresto\Modules\Controller\` and all models in `Phresto\Modules\Model\`, regardless of which module owns them. Two modules cannot both define a `product` model or `user` controller.

> If you want a plugin-ready architecture, names should be module-prefixed (e.g., `Phresto\Modules\Shop\Model\Product`) or PSR-4 autoloaded per module.

### 6. The View/HTML layer is a liability

[`View.php`](src/View.php) is a custom template engine with regex parsing, deprecated helpers, and hardcoded Foundation/AngularJS dependencies. It also has a bug at line 21 where the debug check does an **assignment** (`$conf['app'] = 'on'`) instead of comparison.

Your plan to drop HTML templating and admin/explorer UIs in favor of OpenAPI specs is the right call.

### 7. `Model` mixes too many responsibilities

The base model handles:

- persistence
- query building
- JSON serialization
- field type coercion
- defaults
- relations
- lifecycle hooks

As you add more connectors/ORMs, the inheritance tree (`Model` → `MySQLModel`) becomes a constraint. A better direction is to separate:

- an entity / data-mapping contract
- a repository / storage interface
- per-backend implementations (MySQL, PostgreSQL, file, external ORM)

This matches your idea of a plugin-ready persistence layer.

### 8. Reflection-based discovery is slow and hard to extend

[`Controller::discover()`](src/Controller.php#L171) and [`ModelController::discover()`](src/ModelController.php#L163) parse docblocks and reflection on every request (in dev) or at least on every Explorer load. Generating an OpenAPI spec at build time and caching it is a much healthier approach.

### 9. No `n:n` relation implementation in `MySQLModel`

[`MySQLModel::findRelated()`](src/MySQLModel.php#L225) has an empty `case 'n:n':`. The schema generator also skips many-to-many junction logic. Relations are advertised in the metadata but not actually implemented for the most complex case.

### 10. No input validation beyond type coercion

Type coercion in [`Controller::getParamValue()`](src/Controller.php#L103) catches `TypeError` but does not validate ranges, formats, required fields, or business rules. A modern framework should expose a validation layer before the controller method is invoked.

### 11. Dependency stack is dated

- PHP `>=7.0.0` is now very old.
- `lusitanian/oauth` is not the most active OAuth stack.
- Bower is deprecated.
- Explorer/Admin depend on Foundation + AngularJS 1.x, which are heavy and old.

Your plan to remove these is correct.

### 12. No middleware pipeline

There is no clean way to add cross-cutting concerns (logging, rate limiting, request ID, content negotiation) without overriding `Controller::auth()` or `Router::route()`. A small middleware interface would make the framework more extensible.

---

## Recommendations for your refactor

| Keep / strengthen | Remove or replace |
|---|---|
| Model metadata arrays (`$_fields`, `$_relations`, `$_indexes`) | HTML templating and `View` |
| Convention-based routing (`<name>/<segment>_<verb>`) | Custom Explorer/Admin UI → OpenAPI / Swagger UI |
| `ModelController` generic REST mapping | Bower and Foundation/AngularJS frontend deps |
| Module-based organisation | `lusitanian/oauth` → a modern OAuth2 library or remove bundled OAuth |
| `Config` INI system (it is simple and works) | md5 password hashing → `password_hash` |
| `Container` concept | Hardcoded AES IV → JWT or proper nonce handling |
| Auth/permission matrix design | String-interpolation SQL binding → prepared statements |

### Suggested architectural direction

1. **Persist contract layer**
   Define `RepositoryInterface` and `QueryInterface`. `Model` should not know SQL. Let `MySQLRepository`, `PostgresRepository`, `EloquentRepository`, etc. implement the contract. Models become data bags with metadata.

2. **OpenAPI-first discovery**
   Walk `$_fields`, `$_relations`, and controller reflection once at build/compile time, generate an OpenAPI document, and serve it statically. Replace Explorer with Swagger UI or Redoc.

3. **Plugin-ready persistence**
   Allow registering repository backends via config or a plugin manifest, so users can plug in Doctrine/Eloquent/Doctrine DBAL without forking the core.

4. **Typed request/response**
   Keep reflection parameter binding, but add a validation stage and return a `Response` object from `Router` instead of mixed `array|string`.

5. **Module namespacing**
   Let modules declare their own PSR-4 roots or at least prefix class names by module to avoid collisions.

6. **Middleware**
   Add a minimal `MiddlewareInterface` around `Router::route()` so users can inject logging, auth, CORS, etc. cleanly.

---

## Bottom line

The core REST-creation flow — define a model, get CRUD endpoints — is the strongest part of the framework and worth preserving. Most of the rest is either outdated or should be externalised through contracts rather than built into the core.
