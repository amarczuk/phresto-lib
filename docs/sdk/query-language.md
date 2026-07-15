# Phresto Query Language

Model collection endpoints (`GET /{model}` and `HEAD /{model}`) accept a query object through the URL query string. The same object can also be passed to `Model::find()` and `Model::count()` from PHP.

## Top-level keys

| Key | Type | Purpose |
|-----|------|---------|
| `where` | array | Filters and logical groups. |
| `order` | string / array | Sort order. |
| `limit` | integer | Maximum records to return. |
| `offset` | integer | Records to skip. |
| `fields` | string / array | Fields to include in the result. |

## Equality filters

A simple equality filter:

```bash
curl "http://localhost:8000/product?where[status]=1"
```

Multiple fields are combined with `AND`:

```bash
curl "http://localhost:8000/product?where[status]=1&where[in_stock]=true"
```

## Operators

Use an array with an operator and a value:

```bash
curl "http://localhost:8000/product?where[price][>]=10"
curl "http://localhost:8000/product?where[name][like]=Widget%"
curl "http://localhost:8000/product?where[status][<>]=0"
```

Supported operators include `=`, `<>`, `>`, `>=`, `<`, `<=`, `like`.

## IN / NOT IN

```bash
curl "http://localhost:8000/product?where[id][in][]=1&where[id][in][]=2&where[id][in][]=3"
```

## Logical groups

The keys `or` and `and` group nested conditions. They can be passed as JSON in the `where` parameter for complex queries:

```bash
curl "http://localhost:8000/product?where={%22or%22:[{%22status%22:1},{%22and%22:[{%22price%22:{%22%3C=%22:50}},{%22in_stock%22:true}]}]}"
```

Decoded `where` value:

```json
{
  "or": [
    { "status": 1 },
    {
      "and": [
        { "price": { "<=": 50 } },
        { "in_stock": true }
      ]
    }
  ]
}
```

## Sorting

```bash
curl "http://localhost:8000/product?order=created%20DESC"
curl "http://localhost:8000/product?order[]=created%20DESC&order[]=name%20ASC"
```

## Pagination

```bash
curl "http://localhost:8000/product?limit=10&offset=20"
```

## Field selection

```bash
curl "http://localhost:8000/product?fields=id,name,price"
```

The primary key is always added to the selected fields.

## Counting

`HEAD /{model}` returns the count in the `X-Count` response header and accepts the same `where` filters:

```bash
curl -I "http://localhost:8000/product?where[in_stock]=true"
# X-Count: 42
```

## From PHP

The same query array can be passed directly to the model:

```php
$products = product::find([
    'where'  => [
        'status' => 1,
        'price'  => [ '<=', 100 ],
        'or'     => [
            [ 'name' => [ 'like', 'Widget%' ] ],
            [ 'sku'  => [ 'like', 'W%' ] ],
        ],
    ],
    'order'  => [ 'created DESC' ],
    'limit'  => 10,
    'offset' => 20,
    'fields' => [ 'id', 'name', 'price' ],
]);
```

## Notes

- Field names in `where` must exist in the model's `$_fields` declaration. Unknown fields are ignored.
- Values are type-coerced to the declared PHP type (`int`, `bool`, `DateTime`, etc.).
- The query engine builds prepared SQL with named placeholders, so the syntax is safe from SQL injection.
