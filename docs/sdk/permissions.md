# Authentication and Permissions

> **v2 baseline** — OAuth/social login has been removed. Authentication is now JWT-based and stateless.

Phresto ships with a `user` module that provides authentication and role-based access control. You can replace it or extend it for your own needs.

## How authentication works

- Passwords are hashed with PHP's native `password_hash()` / `password_verify()`.
- Successful login returns a signed **JWT** (HS256) containing the user id, profile, status and a snapshot of the user's permissions.
- The token can be sent as a `Bearer` `Authorization` header or stored in the `prsid` cookie.
- The framework resolves the token into a `RequestContext` via middleware, **not** by loading the user model on every request. The `RequestContext` contains the parsed request data and an `AuthContext`.
- The database only stores revoked token ids. A file cache sits in front of the revocation list so authentication checks usually require zero DB queries.
- If a user's permissions change, existing tokens still carry the old permissions until they expire or are revoked. The user must re-authenticate to pick up new access rights.

## Profiles and permissions

Three bundled models work together:

- `user` — accounts and credentials
- `profile` — roles such as `visitor`, `user`, `admin`
- `permission` — grants access to routes and HTTP methods

A permission record contains:

| Field | Meaning |
|-------|---------|
| `profile` | profile id |
| `route` | controller/model name, with optional `/method` or `*` wildcard |
| `method` | HTTP verb such as `get`, `post`, or `*` |
| `allow` | boolean |

### Database vs config profiles

Profiles are kept in the database.

- **Pros:** runtime manageability, FK constraints, migrations can seed them, no deploy needed for new roles.
- **Cons:** requires a DB lookup when the visitor fallback is used; permission changes must be re-embedded into a JWT via re-authentication.

A pure config approach would remove all DB calls for auth, but you would lose relational integrity and the ability to manage roles without a deployment. The recommended compromise is **DB profiles with permissions cached inside the JWT**: no per-request permission DB lookup, but roles remain data-driven.

## Default access

`Controller::auth()` checks permissions carried in the `RequestContext`'s `AuthContext`:

```php
$this->authContext->hasAccess( static::CLASSNAME, $methodName );
```

If no permission matches, the default is to deny. You must seed at least one permission to allow public or anonymous access.

## Seeding the initial setup

The bundled migration `migration/initial_user_setup.php` creates the `visitor`, `user` and `admin` profiles, grants appropriate permissions, and creates an admin user:

```bash
php scripts/run_migrations.php
```

Default admin credentials:

- email: `admin@localhost`
- password: `admin`

Change these immediately after the first run.

## Example permissions

Allow visitors to read products:

```php
$permission = new \Phresto\Modules\Model\permission();
$permission->profile = $visitorProfileId;
$permission->route   = 'product';
$permission->method  = 'get';
$permission->allow  = true;
$permission->save();
```

Allow admins full access:

```php
$permission->profile = $adminProfileId;
$permission->route   = '*';
$permission->method  = '*';
$permission->allow   = true;
```

## Authenticating users

```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -d '{"email":"a@b.c","password":"secret"}' \
  /user/authenticate
```

Response:

```json
{
  "token": "eyJ...",
  "expires": "2026-07-08T12:00:00+00:00"
}
```

The token is also stored in a cookie named `prsid` for browser clients.

## Registering users

```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -d '{"email":"a@b.c","password":"secret"}' \
  /user/register
```

## Logging out

```bash
curl -X POST \
  -H 'Authorization: Bearer <token>' \
  /user/logout
```

This revokes the JWT. Revoked tokens are kept in the database and cached; expired revocations can be cleaned with `GET /token/clean`.

## Token client binding

Tokens are bound to a stable client fingerprint derived from the User-Agent browser/client type and Accept-Language, **not** the full version string. A token issued for Chrome on macOS will not be valid from Firefox on Linux or from a changed language preference.

## Custom authorisation

Override `auth()` in a controller to implement bespoke checks:

```php
protected function auth( $methodName, $args = null ) {
    if ( $methodName === 'public_get' ) return true;
    return parent::auth( $methodName, $args );
}
```

For models, override is done in a `CustomModelController` subclass because `ModelController` itself is framework-owned.
