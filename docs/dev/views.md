# Views (Legacy HTML Templating)

Phresto includes a legacy HTML view system in `src/View.php`. It is used by the bundled Explorer and Admin modules. JSON APIs typically bypass it and use `View::jsonResponse()` directly.

## `View::jsonResponse()`

```php
public static function jsonResponse( $response )
```

Encodes the response as pretty-printed JSON and optionally appends buffered debug output when `app.debug=on`.

Returns an array:

```php
[
    'body' => json_encode( $response, JSON_PRETTY_PRINT ),
    'content-type' => 'application/json'
]
```

`bootstrap.php` detects this array and sends the appropriate `Content-Type` header.

## HTML view lifecycle

```php
$view = View::getView( 'main', 'explorer' );
$view->add( 'main', [], 'explorer' );
return $view->get();
```

1. `getView($name, $module)` returns a cached or new `View` instance.
2. The constructor loads `config/view.ini` (global or module-specific with inheritance).
3. `add($template, $data, $module)` pushes a template file or inline content onto an element stack.
4. `get()` renders the `<html>` head plus all queued elements and returns the full page.

## Template syntax

Templates are `.htm` files with custom placeholders processed by `View::render()`:

- `{? $variable ?}` — replaced with `$element['data']['variable']`.
- `{? CONSTANT ?}` — replaced with the value of PHP constant `CONSTANT`.
- `{? repeat($variable) ?}` ... `{?/repeat?}` — loops over an array.
- `{? if($left=$right) ?}` ... `{?/if?}` — simple equality condition.
- `{? insert(other_template) ?}` — includes another `.htm` file.

Arrays and objects passed as template data are JSON-encoded before substitution.

## View configuration

`config/view.ini`:

```ini
[page]
lang=en
charset=utf-8
doctype=html
title=Phresto

[css]
foundation=/static/vendor/foundation-sites/dist/css/foundation.min.css

[js]
jquery=/static/vendor/jquery/dist/jquery.min.js

[closingjs]
phresto=/static/js/phresto.js
```

`[js]` assets go in the `<head>`; `[closingjs]` assets go just before `</body>`.

## Deprecation notice

Most `View` methods are marked `@deprecated`. New projects are expected to build frontends as single-page applications consuming JSON endpoints, using these templates only for small admin/explorer UIs.
