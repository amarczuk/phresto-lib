# Defining Models

Models are the heart of Phresto. A model class describes data fields, defaults, relations, and indexes. Once a model exists, the framework automatically exposes REST endpoints for it.

## Minimal model

Create `modules/example/model/product.php`:

```php
<?php
namespace Phresto\Modules\Model;
use Phresto\MySQLModel;

class product extends MySQLModel {
    const CLASSNAME = __CLASS__;

    const DB         = 'mysql';
    const NAME       = 'product';
    const INDEX      = 'id';
    const COLLECTION = 'product';

    protected static $_fields = [
        'id'          => 'int',
        'name'        => 'string',
        'price'       => 'float',
        'in_stock'    => 'boolean',
        'created'     => 'DateTime',
    ];
}
```

This immediately gives you endpoints such as:

| Method | Endpoint | Behaviour |
|--------|----------|-----------|
| GET    | `/product` | List products; accepts `where`, `order`, `limit`, `offset`, and `fields` query parameters |
| GET    | `/product/1` | Read product 1 |
| HEAD   | `/product` | Count products; accepts the same `where` filters as `GET` |
| POST   | `/product` | Create a product |
| PATCH  | `/product/1` | Update product 1 |
| DELETE | `/product/1` | Delete product 1 |

## Field types

| Type | PHP handling | Default SQL type |
|------|--------------|------------------|
| `int` | integer | `INT` |
| `string` | string | `VARCHAR(255)` |
| `boolean` / `bool` | boolean | `BOOLEAN` |
| `float` / `double` | double | `DOUBLE` |
| `DateTime` | `DateTime` object | `DATETIME` |
| custom class | instantiated with value | custom `db` type if provided |

For non-standard SQL column types, use an array descriptor:

```php
protected static $_fields = [
    'price' => ['type' => 'float', 'db' => 'DECIMAL(10,2)'],
];
```

## Defaults

```php
protected static $_defaults = [
    'in_stock' => true,
    'created'  => '',   // empty triggers default_created()
];

protected function default_created() {
    return new \DateTime();
}
```

## Calculated fields

Fields that are not stored in the database:

```php
protected static $_calculated_fields = [
    'summary' => 'string',
];

protected function summary_value() {
    return $this->name . ' - $' . $this->price;
}
```

Calculated fields appear in JSON output but are never persisted.

## Relations

```php
protected static $_relations = [
    'category' => [
        'type'  => 'n:1',
        'model' => 'category',
        'field' => 'id',
        'index' => 'category_id',
    ],
    'reviews' => [
        'type'  => '1:n',
        'model' => 'review',
        'field' => 'product',
        'index' => 'id',
    ],
];
```

Relation types:

- `1:n` — one product has many reviews
- `n:1` — many products belong to one category
- `1:1` / `1>1` / `1<1` — one-to-one variants
- `n:n` — many-to-many (requires a `junction` table definition)

With the relations above, these endpoints become available:

- `GET /product/5/category` — category of product 5
- `GET /product/5/reviews` — reviews of product 5
- `POST /product/5/reviews` — create a review for product 5

## Indexes

```php
protected static $_indexes = [
    'idx_name' => [
        'fields' => ['name'],
        'unique' => true,
    ],
    'idx_price' => [
        'fields' => ['price'],
        'unique' => false,
    ],
];
```

## Lifecycle hooks

```php
protected function saveFilter() {
    // transform data before saving
}

protected function saveValidate() {
    // return false to abort save
}

protected function saveAfter() {
    // run after save
}

protected function filterJson( $fields ) {
    // remove sensitive fields or add computed ones
    unset( $fields['secret_code'] );
    return $fields;
}
```

## Querying from PHP

```php
$products = product::find([
    'where' => [
        'in_stock' => true,
        'price'    => ['<=', 100],
    ],
    'order' => ['created DESC'],
    'limit' => 10,
]);

$product = product::findOne([
    'where' => ['name' => 'Sample']
]);

$count = product::count(['where' => ['in_stock' => true]]);
```

The same query object can be sent from the URL query string. See [`query-language.md`](query-language.md) for the full syntax.

## Schema updates

After changing `$_fields` or `$_indexes`, run:

```bash
php scripts/create_models.php
```

The script prints and executes the SQL needed to create or alter tables and constraints.

## OpenAPI

Model fields, relations, and calculated fields appear in the generated OpenAPI spec at `GET /openapi`. Keep `filterJson()` in mind when you want to hide internal fields from the JSON API.
