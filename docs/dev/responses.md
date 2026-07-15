# Responses

All API responses are JSON, except the OpenAPI spec which can also be returned as YAML. `Response` is the helper class that builds response arrays consumed by `bootstrap.php`.

## `Response` (`src/Response.php`)

### JSON responses

```php
Response::json( $data, $code = 200 );
```

Returns a response array:

```php
[
    'body'         => json_encode( $data, JSON_PRETTY_PRINT ),
    'content-type' => 'application/json',
    'code'         => (int) $code,
]
```

Example controller method:

```php
use Phresto\Response;

public function sales_get() {
    return Response::json( [ 'total' => 12345 ] );
}
```

### YAML responses

```php
Response::yaml( $data, $code = 200 );
```

Returns a response array with `content-type: application/yaml`. This is used by `GET /openapi?format=yaml`.

### Custom responses

Controllers may return a plain response array with `body` and `content-type` keys to serve non-JSON content. The built-in `swagger` module does this to serve the Swagger UI HTML shell and static assets.

### Debug output

`bootstrap.php` starts output buffering before routing. When `app.debug=on`, anything captured in the buffer (for example, accidental `echo` statements or PHP notices) is appended to JSON responses under a `_debug` key so it does not corrupt the response body. In production (`debug=off`), the buffer is cleaned and discarded.
