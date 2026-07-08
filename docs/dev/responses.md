# Responses

> **v2 baseline** — `src/View.php` and the HTML templating system have been removed. All API responses are now JSON (YAML only for the OpenAPI spec). `Response` is the new helper class.

## `Response` (`src/Response.php`)

`Response` replaces the old `View::jsonResponse()` helper and provides two static factory methods.

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

Returns a response array with `content-type: application/yaml`. This is used by `GET /openapi?format=yaml` to serve the OpenAPI specification in YAML.

### Debug output

`bootstrap.php` starts output buffering before routing. When `app.debug=on`, anything captured in the buffer (for example, accidental `echo` statements or PHP notices) is appended to JSON responses under a `_debug` key so it does not corrupt the response body. In production (`debug=off`), the buffer is cleaned and discarded.

## No HTML views

The previous HTML view system has been completely removed:

- `src/View.php` deleted.
- `template/view/`, `template/lang/`, `template/config/view.ini` deleted.
- `template/static/index.html` deleted.
- Bower configuration (`template/bower.json`, `template/.bowerrc`) deleted.
- Admin/Explorer UIs deleted.

If your API needs to serve HTML or static marketing pages, place them outside the framework routing or serve them as static files from `static/`.
