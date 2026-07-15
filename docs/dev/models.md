# Models

## Base `Model` (`src/Model.php`)

A model is a PHP class extending `Phresto\Model` (or `Phresto\MySQLModel`) with static metadata describing its shape.

### Required constants

| Constant | Purpose |
|----------|---------|
| `DB` | Name of the database connection to use (key in `config/db.ini`). |
| `NAME` | Short model name used in relations and URLs. |
| `INDEX` | Primary key field name, usually `id`. |
| `COLLECTION` | Database table name. |

### Static field descriptors

```php
protected static $_fields = [
    'id'         => 'int',
    'email'      => 'string',
    'created'    => 'DateTime',
    'price'      => ['type' => 'float', 'db' => 'DECIMAL(10,2)'],
];
```

Supported scalar type names map to PHP types: `int`, `string`, `boolean`/`bool`, `float`/`double`, `DateTime`. When the descriptor is an array, the `type` key is the PHP type and `db` is the SQL column type used by `MySQLModel::getCreationCode()`.

### Calculated fields

```php
protected static $_calculated_fields = [
    'fullName' => 'string',
];
```

Calculated fields are not persisted. They can be populated manually or via a `__get` hook named `<field>_value()`.

### Defaults

```php
protected static $_defaults = [
    'status'  => 1,
    'created' => '',   // empty => use default_created()
];
```

If a default value is empty and a protected method `default_<field>()` exists, it is called during `saveSetDefaults()`.

### Relations

```php
protected static $_relations = [
    'token' => [
        'type'   => '1:n',
        'model'  => 'token',
        'field'  => 'user',   // FK in related model
        'index'  => 'id',     // FK in this model
    ],
];
```

Relation types used by the framework:

- `1:n` — one parent, many children
- `n:1` — many children point to one parent
- `1:1`, `1>1`, `1<1` — one-to-one variants. The arrow indicates which side owns the foreign key:
  - `1>1` — the related model has the FK (`related_table.this_id` → `this_table.id`).
  - `1<1` — the current model has the FK (`this_table.related_id` → `related_table.id`).
  - `1:1` — generic one-to-one with no FK preference.
- `n:n` — many-to-many (requires `junction` array)

Optional keys: `dbactions` (referential actions), `junction` (for `n:n`), `skipfk` (skip foreign key generation).

#### `n:n` relations

```php
protected static $_relations = [
    'tag' => [
        'type' => 'n:n',
        'model' => 'tag',
        'field' => 'id',
        'index' => 'id',
        'junction' => [
            'collection' => 'post_tag', // junction table name
            'field' => 'tag_id',        // FK to related model in junction table
            'index' => 'post_id',        // FK to this model in junction table
        ],
    ],
];
```

If `junction` is omitted, `MySQLModel` auto-generates a junction table named from the sorted model names (`{a}_{b}`) with `{model}_id` and `{self}_id` columns. For bidirectional querying, define the relation on both models using the same junction table name with `field`/`index` swapped.

`getCreationCode()` creates the junction table with a composite primary key and indexes on both FK columns. `getRelationCode()` adds foreign keys from the junction table to both related tables.

### Lifecycle hooks

Models can override these protected methods to customise behaviour:

| Hook | Called during | Purpose |
|------|---------------|---------|
| `saveFilter()` | `save()` | Pre-process properties before validation/persistence. |
| `saveValidate()` | `save()` | Return `false` to abort save. |
| `saveRecord()` | `save()` | Actual persistence (`MySQLModel` overrides this). |
| `saveAfter()` | `save()` | Post-save side effects. |
| `deleteValidate()` | `delete()` | Return `false` to abort delete. |
| `deleteRecord()` | `delete()` | Actual deletion (`MySQLModel` overrides this). |
| `deleteAfter()` | `delete()` | Post-delete cleanup. |
| `filterJson($fields)` | `jsonSerialize()` | Strip or augment fields before JSON output. |

### Construction

`new Model($option, $checkIfNew)` accepts many forms:

- `null` / omitted → empty new record.
- numeric/string id → load by primary key.
- array with `where` → find one matching record.
- plain array → populate fields.
- object / JSON string → populate fields.

`setNew()` checks whether a primary key already exists in the DB when `$checkIfNew` is `true`.

## `MySQLModel` (`src/MySQLModel.php`)

`MySQLModel` extends `Model` and implements MySQL persistence and query building.

### Connection

Each model fetches a `MySQLConnector` via:

```php
$db = MySQLConnector::getInstance( static::DB );
```

`static::DB` is the key in `config/db.ini`.

### Query syntax

`find()`, `count()`, and related methods accept a query array:

```php
$user::find([
    'where'  => [
        'status' => 1,
        'name'   => ['like', 'John%'],
        'id'     => ['in', [1, 2, 3]],
        'or'     => [
            ['email' => 'a@b.c'],
            ['and'  => [['name' => 'x'], ['status' => 2]]],
        ],
    ],
    'order'  => ['created DESC'],
    'limit'  => 10,
    'offset' => 20,
    'fields' => ['id', 'name'],
]);
```

The query builder (`getConds()`, `getNestedConds()`) produces SQL with named placeholders such as `:val0`. `MySQLConnector::bind()` replaces placeholders with escaped values.

### CRUD mapping

| Model method | SQL generated |
|--------------|---------------|
| `find()` | `SELECT ... FROM table WHERE ... ORDER ... LIMIT ... OFFSET` |
| `findOne()` | `find()` with `limit = 1`, returns first object or `null` |
| `count()` | `SELECT COUNT(pk) FROM table WHERE ...` |
| `findRelated()` | Join with related table based on relation metadata, including `n:n` via junction table |
| `countRelated()` | `SELECT COUNT(pk) ...` for related records, including `n:n` via junction table |
| `save()` | `INSERT` for new records, `UPDATE ... LIMIT 1` for existing |
| `delete()` | `DELETE FROM table WHERE pk = :index LIMIT 1` |

### Schema generation

`MySQLModel::getCreationCode()` introspects the live table and produces `CREATE TABLE` or `ALTER TABLE` SQL to match `$_fields` and `$_indexes`.

`MySQLModel::getRelationCode()` produces foreign key constraints from `$_relations`.

`template/scripts/create_models.php` combines these for every model and executes the resulting SQL.

### Iterator support

`findIter()` returns the raw `mysqli_result` so callers can stream large result sets instead of materialising an array.

### Type coercion

`getTyped()` casts incoming values to the declared PHP type and handles `DateTime` construction. Boolean strings such as `'false'` become boolean `false`. Objects of the declared class are reused; otherwise `new $type($value)` is called.

## OpenAPI generation from models

`OpenApi::discoverModel($modelClass)` reads a model's `$_fields`, `$_relations`, and `$_calculated_fields` to produce an OpenAPI schema object and path items for the standard REST endpoints. This is merged into the cached `config/openapi.json` spec.
