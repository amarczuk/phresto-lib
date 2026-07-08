# Database Connectors

Phresto abstracts database access behind `DBConnectorInterface`. The framework ships with a MySQL implementation.

## `DBConnectorInterface` (`src/Interf/DBConnectorInterface.php`)

```php
interface DBConnectorInterface {
    public static function getInstance( $name, $options = null );
    public function connect( $options );
    public function disconnect();
    public function bind( $query, $variables );
    public function query( $query, $bindings = [] );
    public function getNext( $resource );
    public function getLastId();
    public function getLastError();
    public function count( $resource );
}
```

## `DBConnector` (`src/DBConnector.php`)

Abstract-ish base class that stores named connections in a static registry:

```php
protected static $dbs = [];
```

`getInstance($name)` returns an existing connection or creates one from `config/db.ini`. Subclasses must implement the actual connection logic.

## `MySQLConnector` (`src/MySQLConnector.php`)

Uses PHP's `mysqli` extension. Connection is made through `Container::mysqli(...)` so it uses the framework's own `Phresto\mysqli` subclass, which is just a namespaced proxy to `\mysqli`.

### Key methods

| Method | Notes |
|--------|-------|
| `connect($options)` | Creates `mysqli` connection, sets names/charset. |
| `escape($var)` | Escapes values for SQL; returns `NULL`, `TRUE`/`FALSE`, quoted strings, numbers, JSON-encoded objects, or arrays wrapped in parentheses. |
| `bind($query, $variables)` | Replaces `:name` placeholders with escaped values. |
| `query($query, $bindings)` | Runs a single query and returns the result resource; throws `DBException` on failure. |
| `exec($query, $bindings)` | Runs multi-query via `mysqli::multi_query`; returns the last error string. |
| `count($resource)` | Number of rows in a result set. |
| `getNext($resource)` | `fetch_assoc()` the next row. |
| `getLastId()` | Last insert id. |
| `getLastError()` | Last connection error. |
| `getFields($table)` | Introspects table columns and returns `name => mysql_type`. |
| `getIndexes($table)` | Introspects table indexes. |

### Binding details

`bind()` uses a regex to replace `:key` followed by whitespace, comma, closing paren, percent, dot, question mark, or end-of-string. Because `$` is special in the replacement string, values containing `$` are temporarily replaced with `&us-dollar;` and restored afterwards.

This is a simple textual interpolation approach, not prepared statements. It relies on `escape()` for SQL injection protection.

### Adding another connector

To support a different database:

1. Create a class extending `DBConnector` (or implementing `DBConnectorInterface`).
2. Implement `connect`, `escape`, `query`, `getNext`, `getLastId`, `getLastError`, etc.
3. Reference it from models via `const DB = '<connection_name>'` in `config/db.ini`.

No additional ORM logic is required because `Model` subclasses own persistence.
