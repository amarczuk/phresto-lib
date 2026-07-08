# Creating Controllers

When a model's generic CRUD endpoints are not enough, create a controller class to add custom endpoints and logic.

## Basic controller

Create `modules/example/controller/report.php`:

```php
<?php
namespace Phresto\Modules\Controller;
use Phresto\Controller;

class report extends Controller {
    const CLASSNAME = __CLASS__;

    /**
     * GET /report/sales
     */
    public function sales_get() {
        return $this->jsonResponse([
            'total' => 12345,
        ]);
    }
}
```

The method name format is `<segment>_<verb>`. The first remaining URL segment must be `sales` and the HTTP method must be `GET`.

## Simple verb method

```php
public function get() {
    return $this->jsonResponse(['hello' => 'world']);
}
```

This handles `GET /report`.

## Parameters

Controller methods receive parameters from the URL, request body, or query string:

```php
public function search_get( string $q, int $limit = 10 ) {
    // GET /report/search?q=foo&limit=5
}
```

URL parameters come from `$routeMapping`:

```php
protected $routeMapping = [
    'search_get' => [ 'q' => 0, 'limit' => 1 ],
];

public function search_get( string $q, int $limit ) {
    // GET /report/search/foo/5
}
```

## Returning responses

- Return `$this->jsonResponse($data)` for JSON.
- Return a `View` instance's `get()` result for HTML.
- Return plain strings for raw output.

## Custom model controller

To add endpoints around a specific model while keeping the generic REST methods, extend `CustomModelController`:

```php
<?php
namespace Phresto\Modules\Controller;
use Phresto\CustomModelController;

class product extends CustomModelController {
    const CLASSNAME = __CLASS__;
    const MODELCLASS = 'Phresto\\Modules\\Model\\product';
}
```

This preserves `GET /product`, `POST /product`, etc., and lets you add methods like `stats_get()`.

## Auth checks

Controllers inherit:

```php
protected function auth( $methodName, $args = null ) {
    return $this->currentUser->hasAccess( static::CLASSNAME, $methodName );
}
```

Override it for custom rules:

```php
protected function auth( $methodName, $args = null ) {
    // allow public access to search_get
    if ( $methodName === 'search_get' ) return true;

    return parent::auth( $methodName, $args );
}
```

## Discovery

Every controller automatically answers `GET /<name>/discover` with a JSON description of its endpoints, parameters, and docblocks. The Explorer UI uses this to build interactive forms.
