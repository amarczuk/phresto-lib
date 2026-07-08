# Authentication and Permissions

Phresto ships with a `user` module that provides authentication and role-based access control. You can replace it or extend it for your own needs.

## How authentication works

The `user` model issues encrypted tokens stored in a cookie (`prsid`) or passed as a Bearer token in the `Authorization` header. On every request, `Controller` resolves the current user:

```php
$this->currentUser = user::getCurrent( $this->headers );
```

If no valid token is found, the user is assigned the `visitor` profile.

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

## Default access

`Controller::auth()` calls:

```php
$this->currentUser->hasAccess( static::CLASSNAME, $methodName );
```

If no permission matches, the default is to deny. You must seed at least one permission to allow public or anonymous access.

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
  "token": "...",
  "expires": "2026-07-08T12:00:00+00:00"
}
```

The token is also stored in a cookie named `prsid` for browser clients.

## Social login

Configure OAuth credentials in `modules/user/config/social.ini`, then redirect users to:

```
GET /user/auth/google?ret=/welcome.html
```

Supported services: Google, Facebook, GitHub, LinkedIn.

## Custom authorisation

Override `auth()` in a controller to implement bespoke checks:

```php
protected function auth( $methodName, $args = null ) {
    if ( $methodName === 'public_get' ) return true;
    return parent::auth( $methodName, $args );
}
```

For models, override is done in a `CustomModelController` subclass because `ModelController` itself is framework-owned.
